<?php
namespace Wbengine\Api;

use Wbengine\Api;
use Wbengine\Api\V2\Contract;
use Wbengine\Api\V2\Model;
use Wbengine\Api\V2\ContentModel;
use Wbengine\Api\V2\ImageModel;
use Wbengine\Api\V2\Resources;
use Wbengine\Api\V2\Problem;
use Wbengine\Application\Env\Http;
use Wbengine\RateLimit\Limiter;

/** Versioned server-to-server controller; clients never select PHP classes or methods. */
class V2 extends WbengineRestapiAbstract implements WbengineRestapiInterface
{
    private ?Model $model;
    private ?ContentModel $content;
    private ?Limiter $limiter;
    private ?ImageModel $images;
    private ?\Wbengine\Analytics\Overview $analytics;
    /** Identity and action of the current request once authorization succeeded, for failure audit. */
    private ?array $context = null;

    public function __construct(Api $api, ?Model $model = null, ?ContentModel $content = null, ?Limiter $limiter = null, ?ImageModel $images = null, ?\Wbengine\Analytics\Overview $analytics = null)
    {
        parent::__construct($api);
        $this->model = $model;
        $this->content = $content;
        $this->limiter = $limiter;
        $this->images = $images;
        $this->analytics = $analytics;
    }

    public function getModel(): Model { return $this->model ??= new Model(); }
    public function getContentModel(): ContentModel { return $this->content ??= new ContentModel(); }
    public function getImageModel(): ImageModel { return $this->images ??= new ImageModel(); }
    public function getAnalytics(): \Wbengine\Analytics\Overview { return $this->analytics ??= new \Wbengine\Analytics\Overview(); }

    /** Return a response independently of output; headers carry strong resource ETags. */
    public function handle(string $method, string $path, string $authorization = '', bool $secure = false, ?string $centralActor = null, string $body = '', array $query = [], ?string $ifMatch = null, ?string $clientIp = null): array
    {
        $path = rtrim((string) parse_url($path, PHP_URL_PATH), '/');
        $allow = 'GET';
        $this->context = null;
        try {
            if (in_array($path, ['/api/v2', '/api/v2/capabilities'], true)) {
                $this->method($method, ['GET']);
                return $this->response(Contract::success($this->capabilities()));
            }
            if ('/api/v2/identity' === $path) {
                $this->method($method, ['GET']);
                $identity = $this->authorize(['discovery:read'], 'identity.read', $authorization, $secure, $centralActor, $clientIp);
                $this->getModel()->audit($identity['integration_id'], $centralActor, 'identity.read', 'allowed');
                return $this->response(Contract::success($identity));
            }
            if ('/api/v2/analytics/overview' === $path) {
                $this->method($method, ['GET']);
                $actor = $this->authorize(['analytics:read'], 'analytics.overview', $authorization, $secure, $centralActor, $clientIp);
                return $this->readReply($this->response(Contract::success($this->getAnalytics()->overview($query))), $actor, 'analytics.overview');
            }
            if (preg_match('~^/api/v2/(articles|pages|menu|sections|boxes)(?:/([1-9][0-9]{0,9}))?$~D', $path, $match)) {
                $resource = $match[1]; $id = isset($match[2]) ? Resources::id($match[2]) : null;
                $methods = null === $id ? ['GET', 'POST'] : ['GET', 'PATCH', 'DELETE'];
                $allow = implode(', ', $methods); $this->method($method, $methods);
                $actor = $this->authorize([$resource . ('GET' === $method ? ':read' : ':write')], $resource . '.' . strtolower($method), $authorization, $secure, $centralActor, $clientIp);
                $actor['central_actor'] = $centralActor;
                $content = $this->getContentModel();
                if ('GET' === $method && null === $id) { return $this->readReply($this->response($content->collection($resource, $query)), $actor, $resource . '.list'); }
                $result = match ($method) {
                    'GET' => $content->read($resource, $id),
                    'POST' => $content->create($resource, Contract::objectBody($body, array_keys(Resources::definition($resource)['fields'])), $actor),
                    'PATCH' => $content->update($resource, $id, Contract::objectBody($body, array_keys(Resources::definition($resource)['fields'])), $ifMatch, $actor),
                    'DELETE' => $content->delete($resource, $id, $ifMatch, $actor),
                };
                $response = $this->resourceResponse($result, 'POST' === $method ? 201 : 200);
                return 'GET' === $method ? $this->readReply($response, $actor, $resource . '.read:' . $id) : $response;
            }
            if (preg_match('~^/api/v2/(pages|menu|sections|boxes)/([1-9][0-9]{0,9})/(changes|restore)$~D', $path, $match)) {
                $resource = $match[1]; $id = Resources::id($match[2]); $action = $match[3];
                $methods = 'changes' === $action ? ['GET'] : ['POST'];
                $allow = implode(', ', $methods); $this->method($method, $methods);
                $actor = $this->authorize([$resource . ('changes' === $action ? ':read' : ':write')], $resource . '.' . $action, $authorization, $secure, $centralActor, $clientIp);
                $actor['central_actor'] = $centralActor;
                if ('changes' === $action) {
                    $history = $this->getContentModel()->history($resource, $id, $query);
                    return $this->readReply($this->response($history['body'], 200, ['ETag' => $history['etag']]), $actor, $resource . '.changes:' . $id);
                }
                $data = Contract::objectBody($body, ['change_id']);
                if (!isset($data['change_id'])) { throw new Problem('missing_field', 'change_id is required.'); }
                return $this->resourceResponse($this->getContentModel()->restoreStructure($resource, $id, $data['change_id'], $ifMatch, $actor));
            }
            if (preg_match('~^/api/v2/articles/([1-9][0-9]{0,9})/(revisions|restore|publish|preview|metrics)$~D', $path, $match)) {
                $id = Resources::id($match[1]); $action = $match[2];
                $methods = in_array($action, ['revisions', 'preview', 'metrics'], true) ? ['GET'] : ['POST'];
                $allow = implode(', ', $methods); $this->method($method, $methods);
                $scopes = match ($action) { 'publish' => ['articles:write', 'publish:write'], 'preview' => ['articles:read', 'preview:read'], 'restore' => ['articles:write'], default => ['articles:read'] };
                $actor = $this->authorize($scopes, 'articles.' . $action, $authorization, $secure, $centralActor, $clientIp);
                $actor['central_actor'] = $centralActor;
                $content = $this->getContentModel();
                if ('metrics' === $action) { return $this->readReply($this->response(Contract::success($content->metrics($id))), $actor, 'articles.metrics'); }
                if ('revisions' === $action) { return $this->readReply($this->response($content->revisions($id, $query)), $actor, 'articles.revisions'); }
                if ('preview' === $action) {
                    if (isset($query['locale']) && !is_string($query['locale'])) { throw new Problem('invalid_locale', 'Invalid locale.'); }
                    return $this->readReply($this->response(Contract::success($content->preview($id, $query['locale'] ?? null))), $actor, 'articles.preview');
                }
                $data = Contract::objectBody($body, 'restore' === $action ? ['revision_id'] : []);
                if ('restore' === $action && !isset($data['revision_id'])) { throw new Problem('missing_field', 'revision_id is required.'); }
                return $this->resourceResponse('restore' === $action ? $content->restore($id, $data['revision_id'], $ifMatch, $actor) : $content->publish($id, $ifMatch, $actor));
            }
            if (preg_match('~^/api/v2/translations/(site|menu|article)/([1-9][0-9]{0,9})/([a-z]{2,3}(?:_[A-Z]{2})?)$~D', $path, $match)) {
                $allow = 'GET, PUT, DELETE'; $this->method($method, ['GET', 'PUT', 'DELETE']);
                $resource = ['site' => 'pages', 'menu' => 'menu', 'article' => 'articles'][$match[1]];
                $access = 'GET' === $method ? ':read' : ':write';
                $actor = $this->authorize(['translations' . $access, $resource . $access], 'translations.' . strtolower($method), $authorization, $secure, $centralActor, $clientIp);
                $actor['central_actor'] = $centralActor;
                $content = $this->getContentModel();
                if ('GET' === $method) { return $this->readReply($this->resourceResponse($content->readTranslation($match[1], $match[2], $match[3])), $actor, 'translations.read'); }
                $fields = match ($match[1]) { 'site' => ['title', 'html_title', 'html_description', 'html_keywords'], 'menu' => ['name', 'description'], 'article' => ['title', 'introtext', 'bodytext', 'content_format'] };
                $data = 'DELETE' === $method ? null : Contract::objectBody($body, $fields);
                return $this->resourceResponse($content->writeTranslation($match[1], $match[2], $match[3], $data, $ifMatch, $actor));
            }
            if (preg_match('~^/api/v2/media/([a-f0-9]{32})$~D', $path, $match)) {
                $this->method($method, ['GET']);
                $image = $this->getImageModel()->media($match[1]);
                return ['status' => 200, 'body' => null, 'binary' => $image['bytes'], 'headers' => [
                    'Content-Type' => $image['mime'], 'Content-Length' => (string) strlen($image['bytes']),
                    'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'",
                    'Content-Disposition' => 'inline; filename="' . $match[1] . match ($image['mime']) { 'image/png' => '.png', 'image/jpeg' => '.jpg', 'image/webp' => '.webp' } . '"',
                    'Cache-Control' => 'public, max-age=0, must-revalidate', 'ETag' => $image['etag'],
                ]];
            }
            if (preg_match('~^/api/v2/images(?:/([a-f0-9]{32})(/content)?)?$~D', $path, $match)) {
                $id = $match[1] ?? null; $include = isset($match[2]);
                $methods = null === $id ? ['GET', 'POST'] : ($include ? ['GET'] : ['GET', 'DELETE']);
                $allow = implode(', ', $methods); $this->method($method, $methods);
                $actor = $this->authorize(['images' . ('GET' === $method ? ':read' : ':write')], 'images.' . strtolower($method), $authorization, $secure, $centralActor, $clientIp);
                $actor['central_actor'] = $centralActor;
                $images = $this->getImageModel();
                if ('GET' === $method && null === $id) { return $this->readReply($this->response($images->images($query)), $actor, 'images.list'); }
                if ('GET' === $method) { return $this->readReply($this->resourceResponse($images->image($id, $include)), $actor, 'images.read:' . $id); }
                if ('POST' === $method) { return $this->resourceResponse($images->upload(Contract::objectBody($body, ['base64', 'mime']), $actor), 201); }
                return $this->resourceResponse($images->deleteImage($id, $ifMatch, $actor));
            }
            throw new Problem('not_found', 'Resource not found.', 404);
        } catch (Problem $e) {
            $headers = 405 === $e->getCode() ? ['Allow' => $allow] : [];
            if (429 === $e->getCode()) { $headers['Retry-After'] = '300'; }
            $message = $e->getMessage();
            if ($e->getCode() >= 500) {
                $reference = $this->failureReference(get_class($e) . ':' . $e->errorCode);
                $this->auditFailure($method, 'error', 'ref:' . $reference);
                return $this->response(Contract::error(new Problem($e->errorCode, $message . ' Reference: ' . $reference . '.', $e->getCode())), $e->getCode(), $headers);
            }
            if (!in_array($e->getCode(), [401, 405, 429], true)) { $this->auditFailure($method, 'rejected', $e->errorCode); }
            return $this->response(Contract::error($e), $e->getCode(), $headers);
        } catch (\Throwable $e) {
            $reference = $this->failureReference(get_class($e));
            $this->auditFailure($method, 'error', 'ref:' . $reference);
            return $this->response(Contract::error(new Problem('unavailable', 'The API is temporarily unavailable. Reference: ' . $reference . '.', 503)), 503);
        }
    }

    /** Server log line with a short reference the client also receives. No message, SQL or payload. */
    private function failureReference(string $what): string
    {
        $reference = bin2hex(random_bytes(4));
        error_log(__CLASS__ . ': API v2 operation failed (' . $what . '), reference ' . $reference . '.');
        return $reference;
    }

    /**
     * A rejected or failed write of an authenticated integration goes to cms_api_audit: action, outcome
     * and an error code or reference. Never the request body and never an IP address. Best effort:
     * a broken audit store must not change the answer.
     */
    private function auditFailure(string $method, string $outcome, string $detail): void
    {
        if (null === $this->context || 'GET' === $method) { return; }
        try {
            $this->getModel()->audit($this->context['integration_id'], $this->context['central_actor'], $this->context['action'], $outcome, null, $detail);
        } catch (\Throwable $e) {
            error_log(__CLASS__ . ': failure audit was not stored (' . get_class($e) . ').');
        }
    }

    public function dispatch(string $method, string $path, string $authorization, bool $secure, ?string $centralActor = null): void
    {
        $body = in_array($method, ['POST', 'PUT', 'PATCH'], true) ? (string) file_get_contents('php://input', false, null, 0, 1048577) : '';
        $response = $this->handle($method, $path, $authorization, $secure, $centralActor, $body, $_GET, Http::getHeader('If-Match'), Http::getClientIp());
        foreach ($response['headers'] as $name => $value) { Http::PrintHeader($name . ': ' . $value); }
        if (isset($response['binary'])) {
            Http::PrintCode($response['status']);
            echo $response['binary'];
            exit;
        }
        $this->Api()->toJson($response['body'], $response['status']);
    }

    public function capabilities(): array
    {
        $resources = [];
        foreach (Resources::names() as $resource) { $resources[$resource] = ['read' => true, 'write' => true]; }
        $resources['boxes']['write'] = [] !== $this->getContentModel()->boxTypes();
        $resources['translations'] = ['read' => true, 'write' => true];
        $resources['images'] = ['read' => $this->getImageModel()->configured(), 'write' => $this->getImageModel()->configured()];
        $resources['templates'] = ['read' => false, 'write' => false];
        $resources['discovery'] = ['read' => true, 'write' => false];
        $resources['preview'] = ['read' => true, 'write' => false];
        $resources['publish'] = ['read' => false, 'write' => true];
        $resources['analytics'] = ['read' => \Wbengine\Config::getAnalyticsSettings()['enabled'], 'write' => false];
        return ['api_version' => '2.0', 'capabilities' => $resources, 'box_types' => $this->getContentModel()->boxTypes(), 'i18n' => ['default' => \Wbengine\I18n::translator()->getDefaultLocale(), 'locales' => array_values(\Wbengine\I18n::locales())], 'authentication' => ['type' => 'integration_bearer', 'https_required' => true], 'pagination' => ['type' => 'page', 'default_per_page' => 25, 'max_per_page' => 100], 'search' => ['resources' => ['pages', 'boxes'], 'max_length' => 100]];
    }

    private function authorize(array $scopes, string $action, string $authorization, bool $secure, ?string $centralActor, ?string $clientIp): array
    {
        if (!$secure) { throw new Problem('https_required', 'HTTPS is required for integration authentication.', 403); }
        if (null !== $centralActor && (strlen($centralActor) > 128 || !preg_match('//u', $centralActor) || preg_match('/[\x00-\x1f\x7f]/', $centralActor))) { throw new Problem('invalid_actor', 'Invalid central actor identifier.'); }
        if (null === $this->limiter) {
            $db = \Wbengine\Config::getDbCredentials();
            $this->limiter = new Limiter(sys_get_temp_dir() . '/wbengine-v2-auth-' . substr(hash('sha256', $db->hostname . '/' . $db->database), 0, 16));
        }
        $rateKey = 'ip:' . ($clientIp ?? Http::getClientIp());
        if ($this->limiter->retryAfter($rateKey, 10, 300) > 0) { throw new Problem('rate_limited', 'Too many authentication failures. Try again later.', 429); }
        $identity = preg_match('/^Bearer ([^\s]+)$/Di', $authorization, $matches) ? $this->getModel()->authenticate($matches[1]) : null;
        if (!$identity) {
            $this->limiter->hit($rateKey, 300);
            $this->getModel()->audit(null, $centralActor, $action, 'unauthorized');
            throw new Problem('unauthorized', 'Invalid integration credentials.', 401);
        }
        if (array_diff($scopes, $identity['scopes'])) {
            $this->limiter->hit($rateKey, 300);
            $this->getModel()->audit($identity['integration_id'], $centralActor, $action, 'forbidden');
            throw new Problem('forbidden', 'The integration does not have the required scope.', 403);
        }
        $this->context = ['integration_id' => $identity['integration_id'], 'central_actor' => $centralActor, 'action' => $action];
        return $identity;
    }

    private function readReply(array $response, array $actor, string $action): array
    {
        $this->getModel()->audit($actor['integration_id'], $actor['central_actor'] ?? null, $action, 'allowed');
        return $response;
    }

    private function method(string $method, array $allowed): void
    {
        if (!in_array($method, $allowed, true)) { throw new Problem('method_not_allowed', 'Method not allowed.', 405); }
    }

    private function response(array $body, int $status = 200, array $headers = []): array
    {
        return ['status' => $status, 'body' => $body, 'headers' => $headers + ['Cache-Control' => 'private, no-store']];
    }

    private function resourceResponse(array $result, int $status = 200): array
    {
        return $this->response(Contract::success($result['data']), $status, isset($result['etag']) ? ['ETag' => $result['etag']] : []);
    }
}
