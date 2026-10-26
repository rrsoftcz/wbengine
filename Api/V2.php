<?php

namespace Wbengine\Api;

use Wbengine\Api;
use Wbengine\Api\V2\Contract;
use Wbengine\Api\V2\Model;
use Wbengine\Api\V2\Problem;

/** Versioned API controller. No client-selected class names or methods are executed. */
class V2 extends WbengineRestapiAbstract implements WbengineRestapiInterface
{
    private ?Model $model;

    public function __construct(Api $api, ?Model $model = null)
    {
        parent::__construct($api);
        $this->model = $model;
    }

    public function getModel(): Model
    {
        return $this->model ??= new Model();
    }

    /** Return a response before emitting it, so validation does not depend on output. */
    public function handle(string $method, string $path, string $authorization = '', bool $secure = false, ?string $centralActor = null): array
    {
        $path = rtrim((string) parse_url($path, PHP_URL_PATH), '/');
        try {
            if (!in_array($path, ['/api/v2', '/api/v2/capabilities', '/api/v2/identity'], true)) {
                throw new Problem('not_found', 'Resource not found.', 404);
            }
            if ('GET' !== $method) {
                throw new Problem('method_not_allowed', 'Method not allowed.', 405);
            }
            if ('/api/v2/identity' === $path) {
                $data = $this->identity($authorization, $secure, $centralActor);
            } else {
                $data = $this->capabilities();
            }
            return ['status' => 200, 'body' => Contract::success($data)];
        } catch (Problem $e) {
            return ['status' => $e->getCode(), 'body' => Contract::error($e)];
        } catch (\Throwable $e) {
            // Public responses must not contain SQL, paths, credentials or server versions.
            error_log(__METHOD__ . ': API v2 storage operation failed (' . get_class($e) . ').');
            $problem = new Problem('unavailable', 'The API is temporarily unavailable.', 503);
            return ['status' => 503, 'body' => Contract::error($problem)];
        }
    }

    public function dispatch(string $method, string $path, string $authorization, bool $secure, ?string $centralActor = null): void
    {
        $response = $this->handle($method, $path, $authorization, $secure, $centralActor);
        if (405 === $response['status']) {
            \Wbengine\Application\Env\Http::PrintHeader('Allow: GET');
        }
        \Wbengine\Application\Env\Http::PrintHeader('Cache-Control: no-store');
        $this->Api()->toJson($response['body'], $response['status']);
    }

    public function capabilities(): array
    {
        $resources = [];
        foreach (['articles', 'pages', 'menu', 'sections', 'boxes', 'translations', 'images', 'templates'] as $resource) {
            $resources[$resource] = ['read' => false, 'write' => false];
        }
        $resources['discovery'] = ['read' => true, 'write' => false];
        $resources['preview'] = ['read' => false, 'write' => false];
        $resources['publish'] = ['read' => false, 'write' => false];
        return [
            'api_version' => '2.0',
            'capabilities' => $resources,
            'box_types' => [],
            'authentication' => ['type' => 'integration_bearer', 'https_required' => true],
            'pagination' => ['type' => 'page', 'default_per_page' => 25, 'max_per_page' => 100],
        ];
    }

    private function identity(string $authorization, bool $secure, ?string $centralActor): array
    {
        if (!$secure) {
            throw new Problem('https_required', 'HTTPS is required for integration authentication.', 403);
        }
        if (null !== $centralActor && (strlen($centralActor) > 128 || preg_match('/[\x00-\x1f\x7f]/', $centralActor))) {
            throw new Problem('invalid_actor', 'Invalid central actor identifier.');
        }
        $identity = null;
        if (preg_match('/^Bearer ([^\s]+)$/Di', $authorization, $matches)) {
            $identity = $this->getModel()->authenticate($matches[1]);
        }
        if (!$identity) {
            $this->getModel()->audit(null, $centralActor, 'identity.read', 'unauthorized');
            throw new Problem('unauthorized', 'Invalid integration credentials.', 401);
        }
        if (!in_array('discovery:read', $identity['scopes'], true)) {
            $this->getModel()->audit($identity['integration_id'], $centralActor, 'identity.read', 'forbidden');
            throw new Problem('forbidden', 'The integration does not have the required scope.', 403);
        }
        $this->getModel()->audit($identity['integration_id'], $centralActor, 'identity.read', 'allowed');
        return $identity;
    }
}
