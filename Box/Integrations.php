<?php

namespace Wbengine\Box;

use Wbengine\Api\Model\ApiUserModel;
use Wbengine\Api\V2\IpList;
use Wbengine\Api\V2\Model;
use Wbengine\Api\V2\Preflight;
use Wbengine\Api\V2\Problem;
use Wbengine\Application\Env\Http;
use Wbengine\Config;
use Wbengine\I18n;
use Wbengine\PageCache;
use Wbengine\Template\FragmentCache;
use Wbengine\User;
use Wbengine\User\PasswordConfirmation;
use Wbengine\User\Role;
use Wbengine\User\TooManyAttemptsException;

/**
 * Local management of API v2 integration keys: issue, list, revoke and rotate.
 *
 * The site tree (Application -> Site -> Section -> Box) only knows logged in or not, so this
 * box checks the ADMIN role itself, from the database. Every change is a POST with a CSRF token
 * bound to the action and a fresh password confirmation. A GET only shows state. The raw key is
 * part of the one POST response and nowhere else: not in a URL, log, audit row, session or cache.
 *
 * An application exposes it with a thin subclass in its own namespace, for example
 * App\Box\Central\Integrations, and a cms_boxes row for a site with acl 1.
 */
class Integrations extends WbengineBoxAbstract
{
    const PURPOSE_ISSUE = 'integrations.issue';
    const PURPOSE_REVOKE = 'integrations.revoke';
    const PURPOSE_RESTRICT = 'integrations.restrict';
    /**
     * The profile "Site management in WBengine Admin": exactly these scopes, nothing derived from
     * Model::SCOPES, so a scope the framework adds later is never issued by accident.
     */
    const PROFILE_SCOPES = ['discovery:read', 'articles:read', 'articles:write', 'pages:read', 'pages:write', 'menu:read', 'menu:write', 'sections:read', 'sections:write', 'boxes:read', 'boxes:write', 'translations:read', 'translations:write', 'images:read', 'images:write', 'preview:read', 'publish:write', 'audit:read', 'users:read', 'users:write', 'cache:write', 'config:read'];

    /** The optional scope added by the "Analytics" choice, on top of PROFILE_SCOPES. */
    const ANALYTICS_SCOPE = 'analytics:read';

    /**
     * The permission groups an administrator chooses from when issuing a key. A group has one or more
     * levels in increasing order; choosing a level also grants the levels before it. discovery:read is
     * always granted. Nothing but these lists decides which scopes a key may get.
     */
    const GROUPS = [
        'content' => ['read' => ['articles:read', 'pages:read', 'menu:read', 'translations:read', 'preview:read'], 'write' => ['articles:write', 'pages:write', 'menu:write', 'translations:write']],
        'publish' => ['write' => ['publish:write']],
        'structure' => ['read' => ['sections:read', 'boxes:read'], 'write' => ['sections:write', 'boxes:write']],
        'images' => ['read' => ['images:read'], 'write' => ['images:write']],
        'site' => ['read' => ['users:read', 'config:read'], 'write' => ['users:write', 'cache:write']],
        'history' => ['read' => ['audit:read']],
        'analytics' => ['read' => ['analytics:read']],
    ];

    /** Scopes every key has. */
    const BASE_SCOPES = ['discovery:read'];

    /** Where the operator reads how to enable analytics. */
    const ANALYTICS_DOCS = 'https://github.com/rrsoftcz/wbengine#readme';

    const TEMPLATE = 'Integrations/View/Integrations';

    /** Recommended name of the integration in the central Admin. */
    const DEFAULT_NAME = 'WBengine Admin';

    /**
     * Presets of the issue form: buttons that fill the permission matrix and the name. They are a
     * convenience of the form, not a kind of key and not a security boundary: only the scopes the key
     * really gets count. Only the editor preset asks for Analytics; where the site cannot offer it the
     * choice stays off.
     */
    const PRESETS = [
        'full' => ['name' => self::DEFAULT_NAME, 'levels' => ['content' => 'write', 'publish' => 'write', 'structure' => 'write', 'images' => 'write', 'site' => 'write', 'history' => 'read']],
        'editor' => ['name' => 'MCP editor', 'levels' => ['content' => 'write', 'publish' => 'write', 'structure' => 'write', 'images' => 'write', 'site' => 'off', 'history' => 'read', 'analytics' => 'read']],
        'agent' => ['name' => 'MCP agent', 'levels' => ['content' => 'write', 'publish' => 'write', 'structure' => 'off', 'images' => 'write', 'site' => 'off', 'history' => 'read']],
        'drafts' => ['name' => 'MCP agent (drafts)', 'levels' => ['content' => 'write', 'publish' => 'off', 'structure' => 'off', 'images' => 'write', 'site' => 'off', 'history' => 'read']],
        'readonly' => ['name' => 'Monitoring', 'levels' => ['content' => 'read', 'publish' => 'off', 'structure' => 'read', 'images' => 'read', 'site' => 'off', 'history' => 'read']],
    ];

    /** Where the MCP server file is expected on the machine of the client, in the generated configuration. */
    const MCP_PHAR_PLACEHOLDER = '/path/to/wbengine-mcp.phar';

    /** @var string[]|null preflight result of this request */
    private ?array $problems = null;

    /** @var string[]|null analytics usability problems of this request */
    private ?array $analyticsProblems = null;

    public function Integrations(): string
    {
        // A page with a secret is never shared: framework caches are bypassed before anything is decided.
        PageCache::bypass();
        FragmentCache::bypass();

        $user = new User($this);
        $userId = $user->getUserIsLogged() ? (int) $user->getUserId() : 0;
        if ($userId < 1) {
            return $this->fail(401, 'error.login_required');
        }
        // The role is read from the database on every request, never from the session, a token or the form.
        $actor = (new ApiUserModel())->getAuthorization($userId);
        if (!$actor || !Role::atLeast($actor['role'], Role::ADMIN)) {
            return $this->fail(403, 'error.forbidden');
        }

        $method = $this->requestMethod();
        if ('GET' === $method || 'HEAD' === $method) {
            return $this->show($userId);
        }
        if ('POST' !== $method) {
            $this->sendHeader('Allow: GET, HEAD, POST');
            return $this->fail(405, 'error.method');
        }
        return $this->handlePost($userId);
    }

    private function handlePost(int $userId): string
    {
        $action = Http::PostString('action');
        $purposes = ['issue' => self::PURPOSE_ISSUE, 'revoke' => self::PURPOSE_REVOKE, 'restrict' => self::PURPOSE_RESTRICT];
        if (!is_string($action) || !isset($purposes[$action])) {
            return $this->show($userId, 400, ['error' => 'error.action']);
        }
        if (!$this->getSession()->getCsrf()->validateRequest($purposes[$action])) {
            return $this->show($userId, 403, ['error' => 'error.token']);
        }
        $problems = $this->preflight();
        if ($problems) {
            return $this->show($userId, 409);
        }

        $model = new Model();
        $replaces = null;
        $revokeId = null;
        $restrictIps = [];
        $issueIps = [];
        $withAnalytics = false;
        $issueScopes = [];
        if ('issue' === $action) {
            $name = trim((string) Http::PostString('name'));
            if ('' === $name || strlen($name) > 128 || preg_match('/[\x00-\x1f\x7f]/', $name)) {
                return $this->show($userId, 400, ['error' => 'error.invalid_name']);
            }
            // Only a checked box or not: a list of scopes from the client is never read.
            $analyticsInput = Http::PostString('analytics');
            if (null !== $analyticsInput && !in_array($analyticsInput, ['', '0', '1'], true)) {
                return $this->show($userId, 400, ['error' => 'error.invalid_analytics']);
            }
            $groupAnalytics = Http::PostString('group_analytics');
            if (null !== $groupAnalytics && !in_array($groupAnalytics, ['', 'off', 'read'], true)) {
                return $this->show($userId, 400, ['error' => 'error.invalid_analytics']);
            }
            $withAnalytics = '1' === $analyticsInput || 'read' === $groupAnalytics;
            if ($withAnalytics && $this->analyticsProblems()) {
                return $this->show($userId, 400, ['error' => 'error.analytics_unavailable']);
            }
            // Group levels: only known groups and levels are read. No group field at all means the full profile.
            $levels = self::levelsFromInput(fn (string $name) => Http::PostString('group_' . $name), $withAnalytics);
            if (null === $levels) {
                return $this->show($userId, 400, ['error' => 'error.invalid_group']);
            }
            $issueScopes = self::scopesForGroups($levels);
            if (count($issueScopes) <= count(self::BASE_SCOPES)) {
                return $this->show($userId, 400, ['error' => 'error.empty_key']);
            }
            $ipInput = trim((string) Http::PostString('allowed_ips'));
            if ('' !== $ipInput) {
                $issueIps = IpList::parse($ipInput);
                if (null === $issueIps) {
                    return $this->show($userId, 400, ['error' => 'error.invalid_ips']);
                }
                if (!$model->supportsIpRestriction()) {
                    return $this->show($userId, 400, ['error' => 'error.ips_unsupported']);
                }
            }
            $replacesInput = Http::PostString('replaces');
            if (is_string($replacesInput) && '' !== $replacesInput) {
                $replaces = $this->activeIntegration($model, $replacesInput);
                if (null === $replaces) {
                    return $this->show($userId, 400, ['error' => 'error.invalid_id']);
                }
            }
        } else {
            $revokeId = Http::PostString('integration_id');
            if (!is_string($revokeId) || !preg_match('/^[1-9][0-9]{0,9}$/D', $revokeId)) {
                return $this->show($userId, 400, ['error' => 'error.invalid_id']);
            }
            if ('restrict' === $action) {
                $restrictIps = IpList::parse(trim((string) Http::PostString('allowed_ips')));
                if (null === $restrictIps) {
                    return $this->show($userId, 400, ['error' => 'error.invalid_ips']);
                }
                if (!$model->supportsIpRestriction()) {
                    return $this->show($userId, 400, ['error' => 'error.ips_unsupported']);
                }
            }
        }

        // Fresh confirmation for every change. Only the submitted password is compared, against the
        // hash of the session's own user, and it is not kept anywhere.
        try {
            $confirmed = $this->passwordConfirmation()->confirm($userId, Http::PostRaw('password'), (string) Http::getClientIp());
        } catch (TooManyAttemptsException $e) {
            $this->sendHeader('Retry-After: ' . $e->getRetryAfter());
            return $this->show($userId, 429, ['error' => 'error.too_many', 'params' => ['seconds' => $e->getRetryAfter()]]);
        }
        if (!$confirmed) {
            $this->auditFailedConfirmation($model, $userId);
            return $this->show($userId, 403, ['error' => 'error.password']);
        }

        try {
            if ('issue' === $action) {
                $issued = $model->provision($userId, $name, $issueScopes, $issueIps);
                return $this->show($userId, 200, ['issued' => $issued, 'replaces' => $replaces]);
            }
            if ('restrict' === $action) {
                $done = $model->restrict($userId, $revokeId, $restrictIps);
                return $this->show($userId, 200, [$done ? 'notice' : 'error' => $done ? 'notice_restricted' : 'notice_not_revoked']);
            }
            $revoked = $model->revoke($userId, $revokeId);
            return $this->show($userId, 200, [$revoked ? 'notice' : 'error' => $revoked ? 'notice_revoked' : 'notice_not_revoked']);
        } catch (Problem $e) {
            // Problem messages are client safe, but the screen shows its own translated text.
            $key = 'invalid_name' === $e->errorCode ? 'error.invalid_name' : ('forbidden' === $e->errorCode ? 'error.forbidden' : 'error.failed');
            return $this->show($userId, 'forbidden' === $e->errorCode ? 403 : 400, ['error' => $key]);
        } catch (\Throwable $e) {
            // Class only: the message could carry SQL, and nothing here may carry the key.
            error_log(__METHOD__ . ': ' . get_class($e));
            return $this->show($userId, 500, ['error' => 'error.failed']);
        }
    }

    /**
     * @param array $state error|notice (message key), params, issued (provision result), replaces (integration row)
     */
    private function show(int $userId, int $status = 200, array $state = []): string
    {
        $problems = $this->preflight();
        $model = new Model();
        $rows = [];
        if (!$problems) {
            try {
                $rows = $model->list();
            } catch (\Throwable $e) {
                error_log(__METHOD__ . ': ' . get_class($e));
                $problems[] = Preflight::DATABASE;
            }
        }
        $canIssue = !$problems;
        $ipSupport = $canIssue && $model->supportsIpRestriction();
        $analyticsProblems = $canIssue ? $this->analyticsProblems() : [];
        $analyticsMessages = [];
        foreach ($analyticsProblems as $code) {
            $analyticsMessages[] = I18n::t('wb.integrations.analytics_problem.' . $code);
        }
        $messages = [];
        foreach ($problems as $code) {
            $messages[] = I18n::t('wb.integrations.problem.' . $code);
        }
        $csrf = $this->getSession()->getCsrf();
        $address = $this->siteAddress();
        $issued = null;
        if (isset($state['issued'])) {
            $replaces = $state['replaces'] ?? null;
            $issuedScopes = (array) ($state['issued']['scopes'] ?? self::PROFILE_SCOPES);
            $issued = [
                'scopes' => $this->scopeDescriptions($issuedScopes),
                'scope_count' => count($issuedScopes),
                'key' => $state['issued']['key'],
                'name' => $state['issued']['name'],
                'address' => $address,
                'mcp' => self::mcpClientConfig($address, (string) $state['issued']['key'], $issuedScopes),
                'replaces' => $replaces ? I18n::t('wb.integrations.instr_replace', ['id' => $replaces['integration_id'], 'name' => $replaces['name']]) : '',
            ];
        }

        $list = [];
        $rotatable = [];
        foreach ($rows as $row) {
            $active = null === $row['revoked_at'];
            $list[] = [
                'id' => $row['integration_id'],
                'name' => $row['name'],
                'active' => $active,
                'status' => $active ? I18n::t('wb.integrations.status_active') : I18n::t('wb.integrations.status_revoked', ['date' => $this->date($row['revoked_at'])]),
                'created' => $this->date($row['created_at']),
                'by' => I18n::t('wb.integrations.user_id', ['id' => $row['created_by']]),
                'last_used' => null === $row['last_used_at'] ? I18n::t('wb.integrations.never_used') : $this->date($row['last_used_at']),
                'scope_count' => count($row['scopes']),
                'has_analytics' => in_array(self::ANALYTICS_SCOPE, $row['scopes'], true),
                'revoked_on' => $active ? '' : $this->date($row['revoked_at']),
                'groups' => $this->groupLabels($row['scopes']),
                'allowed_text' => implode(', ', $row['allowed_ips'] ?? []),
                'restricted' => [] !== ($row['allowed_ips'] ?? []),
                'last_ip' => (string) ($row['last_used_ip'] ?? ''),
                'ips_prefill' => implode(', ', ($row['allowed_ips'] ?? []) ?: (null !== ($row['last_used_ip'] ?? null) ? [$row['last_used_ip']] : [])),
            ];
            if ($active && (!isset($state['issued']) || (int) $state['issued']['integration_id'] !== $row['integration_id'])) {
                $rotatable[] = ['id' => $row['integration_id'], 'label' => $row['name'] . ' #' . $row['integration_id'], 'analytics' => in_array(self::ANALYTICS_SCOPE, $row['scopes'], true) ? '1' : '0', 'groups' => $this->levelsAttribute($row['scopes']), 'ips' => implode(', ', $row['allowed_ips'] ?? [])];
            }
        }

        $view = [
            't' => $this->texts(),
            'error' => isset($state['error']) ? I18n::t('wb.integrations.' . $state['error'], $state['params'] ?? []) : '',
            'notice' => isset($state['notice']) ? I18n::t('wb.integrations.' . $state['notice']) : '',
            'problems' => $messages,
            'canIssue' => $canIssue,
            'showList' => $canIssue,
            'address' => $address,
            'action' => $this->formAction(),
            'defaultName' => self::DEFAULT_NAME,
            'groupForm' => $this->groupForm(!$analyticsProblems),
            'presets' => $this->presets(),
            'analytics' => ['available' => !$analyticsProblems, 'off' => (bool) $analyticsProblems, 'problems' => $analyticsMessages, 'docs' => self::ANALYTICS_DOCS],
            'integrations' => $list,
            'activeKeys' => array_values(array_filter($list, fn(array $row) => $row['active'])),
            'revokedKeys' => array_values(array_filter($list, fn(array $row) => !$row['active'])),
            'revokedCount' => count(array_filter($list, fn(array $row) => !$row['active'])),
            'rotatable' => $rotatable,
            'issued' => $issued,
            'ipSupport' => $ipSupport,
            'yourAddress' => (string) Http::getClientIp(),
            'tokens' => ['issue' => $csrf->token(self::PURPOSE_ISSUE), 'revoke' => $csrf->token(self::PURPOSE_REVOKE), 'restrict' => $csrf->token(self::PURPOSE_RESTRICT)],
        ];
        $this->sendStatus($status);
        $this->sendPrivateHeaders();
        // The last argument keeps the secret values out of any shared template cache.
        $this->getRenderer()->assign('view', $view, true);
        return $this->getRenderer()->render(self::TEMPLATE, null, true);
    }

    /**
     * Sent after the session was touched: PHP's session start replaces Cache-Control otherwise.
     * Only the application sets these headers, a proxy in front must not repeat them.
     */
    private function sendPrivateHeaders(): void
    {
        $this->sendHeader('Cache-Control: private, no-store');
        // same-origin, not no-referrer: no-referrer makes browsers send "Origin: null" with POST forms.
        $this->sendHeader('Referrer-Policy: same-origin');
        $this->sendHeader('X-Robots-Tag: noindex, nofollow');
    }

    /** The fixed profile, plus analytics:read only when the administrator chose it. */
    public static function scopesFor(bool $withAnalytics): array
    {
        return $withAnalytics ? array_merge(self::PROFILE_SCOPES, [self::ANALYTICS_SCOPE]) : self::PROFILE_SCOPES;
    }

    /**
     * Group levels of the full profile: every group at its highest level.
     *
     * @return array<string, string>
     */
    public static function fullLevels(bool $withAnalytics): array
    {
        $levels = [];
        foreach (self::GROUPS as $name => $definition) {
            if ('analytics' === $name && !$withAnalytics) { continue; }
            $levels[$name] = (string) array_key_last($definition);
        }
        return $levels;
    }

    /**
     * Read the chosen levels of a submitted form. $field returns the posted value of a group or null when
     * it was not posted. Null result: an unknown level. No group field at all gives the full profile.
     *
     * @return array<string, string>|null
     */
    public static function levelsFromInput(callable $field, bool $withAnalytics): ?array
    {
        $levels = [];
        $posted = false;
        foreach (self::GROUPS as $name => $definition) {
            if ('analytics' === $name) { continue; }
            $value = $field($name);
            if (null === $value) { continue; }
            $posted = true;
            if ('off' === $value || '' === $value) { continue; }
            if (!is_string($value) || !array_key_exists($value, $definition)) { return null; }
            $levels[$name] = $value;
        }
        if (!$posted) {
            return self::fullLevels($withAnalytics);
        }
        if ($withAnalytics) { $levels['analytics'] = 'read'; }
        return $levels;
    }

    /**
     * The scopes of chosen levels, in a stable order: discovery:read first, then by group.
     *
     * @param array<string, string> $levels group => level
     * @return string[]
     */
    public static function scopesForGroups(array $levels): array
    {
        $scopes = self::BASE_SCOPES;
        foreach (self::GROUPS as $name => $definition) {
            if (!isset($levels[$name]) || !array_key_exists($levels[$name], $definition)) { continue; }
            foreach ($definition as $level => $levelScopes) {
                $scopes = array_merge($scopes, $levelScopes);
                if ($level === $levels[$name]) { break; }
            }
        }
        return array_values(array_unique($scopes));
    }

    /**
     * The levels that exactly describe a set of scopes: for every group the highest level whose
     * scopes (and those of the levels before it) are all present.
     *
     * @param string[] $scopes
     * @return array<string, string>
     */
    public static function levelsOf(array $scopes): array
    {
        $levels = [];
        foreach (self::GROUPS as $name => $definition) {
            $needed = [];
            foreach ($definition as $level => $levelScopes) {
                $needed = array_merge($needed, $levelScopes);
                if (array_diff($needed, $scopes) !== []) { break; }
                $levels[$name] = $level;
            }
        }
        return $levels;
    }

    /**
     * Scopes in plain language. Without an argument: the fixed profile.
     *
     * @param string[]|null $scopes
     */
    protected function scopeDescriptions(?array $scopes = null): array
    {
        $result = [];
        foreach ($scopes ?? self::PROFILE_SCOPES as $scope) {
            $result[] = I18n::t('wb.integrations.scope.' . str_replace(':', '_', $scope));
        }
        return $result;
    }

    /**
     * The rows of the permission matrix: for every group the label, the help and one cell per level
     * (off, read, write). A cell is present only when the group has that level. The highest level is
     * preselected, except for analytics which is off until chosen and disabled when it is not usable.
     *
     * @return array<int, array<string, mixed>>
     */
    private function groupForm(bool $analyticsAvailable): array
    {
        $rows = [];
        foreach (self::GROUPS as $name => $definition) {
            $analytics = 'analytics' === $name;
            $default = $analytics ? 'off' : (string) array_key_last($definition);
            $cells = [];
            foreach (['off', 'read', 'write'] as $level) {
                $cells[] = [
                    'value' => $level,
                    'present' => 'off' === $level || array_key_exists($level, $definition),
                    'selected' => $level === $default,
                    'disabled' => $analytics && !$analyticsAvailable,
                    'label' => I18n::t('wb.integrations.cell.' . $level),
                ];
            }
            $rows[] = ['name' => $name, 'label' => I18n::t('wb.integrations.group.' . $name), 'help' => I18n::t('wb.integrations.group_help.' . $name), 'cells' => $cells];
        }
        return $rows;
    }

    /**
     * The groups of a key in plain language, for example "Content: Read", with the group name for its color.
     *
     * @param string[] $scopes
     * @return array<int, array{name: string, level: string, label: string}>
     */
    private function groupLabels(array $scopes): array
    {
        $labels = [];
        foreach (self::levelsOf($scopes) as $name => $level) {
            $labels[] = ['name' => $name, 'level' => $level, 'label' => 1 === count(self::GROUPS[$name])
                ? I18n::t('wb.integrations.group.' . $name)
                : I18n::t('wb.integrations.group_level', ['group' => I18n::t('wb.integrations.group.' . $name), 'level' => I18n::t('wb.integrations.level.' . $level)])];
        }
        return $labels;
    }

    /**
     * The presets as the template needs them: id, label, help, the name to fill in and the levels in the
     * same "group=level;group=level" form the rotation uses.
     *
     * @return array<int, array{id: string, label: string, help: string, name: string, levels: string}>
     */
    private function presets(): array
    {
        $presets = [];
        foreach (self::PRESETS as $id => $preset) {
            $levels = [];
            foreach ($preset['levels'] as $group => $level) { $levels[] = $group . '=' . $level; }
            $presets[] = ['id' => $id, 'label' => I18n::t('wb.integrations.preset.' . $id), 'help' => I18n::t('wb.integrations.preset_help.' . $id), 'name' => $preset['name'], 'levels' => implode(';', $levels)];
        }
        return $presets;
    }

    /**
     * Ready-to-copy client configuration for the MCP server of this site: the JSON block for the
     * mcpServers section of a client and the command for Claude Code. It carries the raw key, so it is
     * built for the one response that issues the key and never stored. A key without the Publishing
     * group gets the draft mode, which also hides the publish tools locally.
     *
     * @param string[] $scopes
     * @return array{server: string, mode: string, json: string, command: string}
     */
    public static function mcpClientConfig(string $address, #[\SensitiveParameter] string $key, array $scopes): array
    {
        $mode = in_array('publish:write', $scopes, true) ? 'full' : 'draft';
        $host = (string) parse_url($address, PHP_URL_HOST);
        $server = 'wbengine-' . (trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($host)), '-') ?: 'site');
        $env = ['WBENGINE_MCP_URL' => rtrim($address, '/'), 'WBENGINE_MCP_KEY' => $key, 'WBENGINE_MCP_MODE' => $mode];
        $json = json_encode(['mcpServers' => [$server => ['command' => 'php', 'args' => [self::MCP_PHAR_PLACEHOLDER], 'env' => $env]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $command = 'claude mcp add';
        foreach ($env as $name => $value) { $command .= ' --env ' . escapeshellarg($name . '=' . $value); }
        $command .= ' --transport stdio ' . $server . ' -- php ' . self::MCP_PHAR_PLACEHOLDER;
        return ['server' => $server, 'mode' => $mode, 'json' => $json, 'command' => $command];
    }

    /** "content=write;site=read": the levels of a key, to preset the form when it is rotated. */
    private function levelsAttribute(array $scopes): string
    {
        $parts = [];
        foreach (self::GROUPS as $name => $definition) {
            $parts[] = $name . '=' . (self::levelsOf($scopes)[$name] ?? 'off');
        }
        return implode(';', $parts);
    }

    private function texts(): array
    {
        $texts = [];
        foreach (['title', 'intro', 'address_label', 'connect_title', 'connect_help', 'name_label', 'scopes_title', 'password_label',
            'password_help', 'issue_submit', 'replaces_label', 'replaces_none', 'list_title', 'list_empty', 'col_name', 'col_created',
            'col_by', 'col_last_used', 'col_scopes', 'col_status', 'revoke_submit', 'revoke_password', 'rotation_title', 'rotation_1',
            'rotation_2', 'rotation_3', 'issued_title', 'issued_warning', 'copy', 'copied', 'copy_failed', 'instr_title', 'instr_1',
            'instr_2', 'instr_site', 'instr_name', 'instr_key', 'instr_3', 'problem_title', 'problem_help', 'name_help', 'analytics_label',
            'analytics_help', 'analytics_off', 'analytics_docs', 'analytics_badge', 'revoked_title', 'active_empty', 'col_revoked', 'revoke_open', 'groups_title', 'groups_help', 'analytics_title', 'presets_title', 'presets_help', 'mcp_title', 'mcp_help', 'mcp_json', 'mcp_command', 'mcp_note', 'col_none', 'col_read', 'col_write', 'ips_title', 'ips_label', 'ips_help', 'ips_any', 'ips_your', 'col_allowed', 'col_last_ip', 'restrict_open', 'restrict_submit', 'ips_never', 'confirm_password', 'ips_card_hint'] as $key) {
            $texts[$key] = I18n::t('wb.integrations.' . $key);
        }
        return $texts;
    }

    /** A problem page without any state of the integrations. */
    private function fail(int $status, string $messageKey): string
    {
        $this->sendStatus($status);
        $this->sendPrivateHeaders();
        $this->getRenderer()->assign('view', [
            't' => $this->texts(), 'error' => I18n::t('wb.integrations.' . $messageKey), 'notice' => '', 'problems' => [],
            'canIssue' => false, 'showList' => false, 'issued' => null, 'scopes' => [], 'analytics' => ['available' => false, 'off' => false, 'problems' => [], 'docs' => ''], 'scopeCount' => 0, 'integrations' => [], 'rotatable' => [],
            'address' => '', 'action' => '', 'defaultName' => '', 'tokens' => ['issue' => '', 'revoke' => ''],
        ], true);
        return $this->getRenderer()->render(self::TEMPLATE, null, true);
    }

    /** @return string[] problem codes of the read-only preflight */
    protected function preflight(): array
    {
        if (null !== $this->problems) {
            return $this->problems;
        }
        $apiEnabled = is_object(Config::$config) && true === (Config::$config->apiV2->enabled ?? false);
        $baseUrl = (string) (is_object(Config::$config) ? (Config::$config->i18n->baseUrl ?? '') : '');
        return $this->problems = (new Preflight())->problems($apiEnabled, Http::isSecure(), $baseUrl);
    }

    /**
     * Problem codes of the optional analytics permission, empty when analytics is usable:
     * enabled in the configuration and migrations/4.0.0/006-analytics.sql applied.
     *
     * @return string[]
     */
    protected function analyticsProblems(): array
    {
        if (null !== $this->analyticsProblems) {
            return $this->analyticsProblems;
        }
        try {
            $enabled = is_object(Config::$config) && true === Config::getAnalyticsSettings()['enabled'];
        } catch (\Throwable $e) {
            // An invalid analytics section means analytics is not usable.
            $enabled = false;
        }
        return $this->analyticsProblems = (new Preflight())->analyticsProblems($enabled);
    }

    protected function passwordConfirmation(): PasswordConfirmation
    {
        return new PasswordConfirmation();
    }

    /** Failed confirmations are audited without any value of the form. A failed audit still denies. */
    private function auditFailedConfirmation(Model $model, int $userId): void
    {
        try {
            $model->audit(null, null, 'integration.reauth', 'denied', $userId);
        } catch (\Throwable $e) {
            error_log(__METHOD__ . ': ' . get_class($e));
        }
    }

    private function activeIntegration(Model $model, string $id): ?array
    {
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $id)) {
            return null;
        }
        foreach ($model->list() as $row) {
            if ($row['integration_id'] === (int) $id && null === $row['revoked_at']) {
                return $row;
            }
        }
        return null;
    }

    private function siteAddress(): string
    {
        return rtrim(I18n::origin(), '/') . '/';
    }

    private function formAction(): string
    {
        $path = (string) parse_url((string) Http::Uri(), PHP_URL_PATH);
        return str_starts_with($path, '/') && !str_starts_with($path, '//') ? $path : '/';
    }

    private function date(int $timestamp): string
    {
        return date('Y-m-d H:i', $timestamp);
    }

    protected function requestMethod(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    protected function sendHeader(string $line): void
    {
        if (!headers_sent()) {
            header($line);
        }
    }

    protected function sendStatus(int $status): void
    {
        if (!headers_sent()) {
            http_response_code($status);
        }
    }
}
