<?php

namespace Wbengine\Box;

use Wbengine\Api\Model\ApiUserModel;
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
    /**
     * The profile "Site management in WBengine Admin": exactly these scopes, nothing derived from
     * Model::SCOPES, so a scope the framework adds later is never issued by accident.
     */
    const PROFILE_SCOPES = ['discovery:read', 'articles:read', 'articles:write', 'pages:read', 'pages:write', 'menu:read', 'menu:write', 'sections:read', 'sections:write', 'boxes:read', 'boxes:write', 'translations:read', 'translations:write', 'images:read', 'images:write', 'preview:read', 'publish:write', 'audit:read', 'users:read', 'users:write', 'cache:write', 'config:read'];

    /** The optional scope added by the "Analytics" choice, on top of PROFILE_SCOPES. */
    const ANALYTICS_SCOPE = 'analytics:read';

    /** Where the operator reads how to enable analytics. */
    const ANALYTICS_DOCS = 'https://github.com/rrsoftcz/wbengine#readme';

    const TEMPLATE = 'Integrations/View/Integrations';

    /** Recommended name of the integration in the central Admin. */
    const DEFAULT_NAME = 'WBengine Admin';

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
        $purposes = ['issue' => self::PURPOSE_ISSUE, 'revoke' => self::PURPOSE_REVOKE];
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
        $withAnalytics = false;
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
            $withAnalytics = '1' === $analyticsInput;
            if ($withAnalytics && $this->analyticsProblems()) {
                return $this->show($userId, 400, ['error' => 'error.analytics_unavailable']);
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
                $issued = $model->provision($userId, $name, self::scopesFor($withAnalytics));
                return $this->show($userId, 200, ['issued' => $issued, 'replaces' => $replaces]);
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
            ];
            if ($active && (!isset($state['issued']) || (int) $state['issued']['integration_id'] !== $row['integration_id'])) {
                $rotatable[] = ['id' => $row['integration_id'], 'label' => $row['name'] . ' #' . $row['integration_id'], 'analytics' => in_array(self::ANALYTICS_SCOPE, $row['scopes'], true) ? '1' : '0'];
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
            'scopes' => $this->scopeDescriptions(),
            'scopeCount' => count(self::PROFILE_SCOPES),
            'analytics' => ['available' => !$analyticsProblems, 'off' => (bool) $analyticsProblems, 'problems' => $analyticsMessages, 'docs' => self::ANALYTICS_DOCS],
            'integrations' => $list,
            'rotatable' => $rotatable,
            'issued' => $issued,
            'tokens' => ['issue' => $csrf->token(self::PURPOSE_ISSUE), 'revoke' => $csrf->token(self::PURPOSE_REVOKE)],
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

    private function texts(): array
    {
        $texts = [];
        foreach (['title', 'intro', 'address_label', 'connect_title', 'connect_help', 'name_label', 'scopes_title', 'password_label',
            'password_help', 'issue_submit', 'replaces_label', 'replaces_none', 'list_title', 'list_empty', 'col_name', 'col_created',
            'col_by', 'col_last_used', 'col_scopes', 'col_status', 'revoke_submit', 'revoke_password', 'rotation_title', 'rotation_1',
            'rotation_2', 'rotation_3', 'issued_title', 'issued_warning', 'copy', 'copied', 'copy_failed', 'instr_title', 'instr_1',
            'instr_2', 'instr_site', 'instr_name', 'instr_key', 'instr_3', 'problem_title', 'problem_help', 'name_help', 'analytics_label',
            'analytics_help', 'analytics_off', 'analytics_docs', 'analytics_badge'] as $key) {
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
