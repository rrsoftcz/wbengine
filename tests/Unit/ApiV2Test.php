<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Api;
use Wbengine\Api\V2;
use Wbengine\Api\V2\Contract;
use Wbengine\Api\V2\Model;
use Wbengine\Api\V2\Problem;

class ApiV2Test extends TestCase
{
    private \Wbengine\RateLimit\Limiter $isolatedLimiter;
    private string $limiterDirectory;
    protected function setUp(): void
    {
        $this->limiterDirectory = sys_get_temp_dir() . '/api-v2-unit-rate-' . bin2hex(random_bytes(8));
        $this->isolatedLimiter = new \Wbengine\RateLimit\Limiter($this->limiterDirectory);
    }
    protected function tearDown(): void
    {
        foreach (glob($this->limiterDirectory . '/*') ?: [] as $file) { unlink($file); }
        if (is_dir($this->limiterDirectory)) { rmdir($this->limiterDirectory); }
    }

    public function testDiscoveryAdvertisesOnlyImplementedCapabilitiesWithoutDiagnostics(): void
    {
        $controller = new V2(new Api());
        $response = $controller->handle('GET', '/api/v2');
        self::assertSame(200, $response['status']);
        self::assertSame('2.0', $response['body']['data']['api_version']);
        self::assertTrue($response['body']['data']['capabilities']['articles']['write']);
        self::assertFalse($response['body']['data']['capabilities']['templates']['write']);
        self::assertIsString($response['body']['data']['i18n']['default']);
        self::assertContains($response['body']['data']['i18n']['default'], $response['body']['data']['i18n']['locales']);
        self::assertArrayNotHasKey('php_version', $response['body']['data']);
        self::assertArrayNotHasKey('database', $response['body']['data']);
    }

    public function testUnknownPathsAndWrongMethodsUseSameProblemContract(): void
    {
        $controller = new V2(new Api());
        foreach ([['POST', '/api/v2', 405, 'method_not_allowed'], ['GET', '/api/v2/PHP/execute', 404, 'not_found']] as [$method, $path, $status, $code]) {
            $response = $controller->handle($method, $path);
            self::assertSame($status, $response['status']);
            self::assertFalse($response['body']['success']);
            self::assertSame($code, $response['body']['error']['code']);
            self::assertIsString($response['body']['error']['message']);
        }
    }

    public function testJsonObjectValidationRejectsListsScalarsMalformedAndUnknownFields(): void
    {
        foreach (['[]', '[{}]', 'null', 'false', '1', '"test"', '{', '{"evil":1}'] as $json) {
            try {
                Contract::objectBody($json, ['name']);
                self::fail('Invalid body accepted: ' . $json);
            } catch (Problem $e) {
                self::assertSame(400, $e->getCode());
            }
        }
        self::assertSame(['name' => 'a'], Contract::objectBody('{"name":"a"}', ['name']));
    }

    public function testPaginationRejectsUnboundedOrInjectedValues(): void
    {
        self::assertSame(['page' => 1, 'per_page' => 25, 'offset' => 0], Contract::pagination([]));
        self::assertSame(['page' => 3, 'per_page' => 10, 'offset' => 20], Contract::pagination(['page' => '3', 'per_page' => '10']));
        foreach ([['page' => "1 OR 1=1"], ['per_page' => 101], ['page' => 0], ['page' => []], ['page' => '99999999999999999999999']] as $query) {
            try {
                Contract::pagination($query);
                self::fail('Invalid pagination accepted');
            } catch (Problem $e) {
                self::assertSame(400, $e->getCode());
            }
        }
    }

    public function testSearchTextIsBoundedAndWildcardsAreEscaped(): void
    {
        self::assertNull(Contract::search([]));
        self::assertNull(Contract::search(['search' => '   ']));
        self::assertSame('abc', Contract::search(['search' => ' abc ']));
        self::assertSame(str_repeat('a', 100), Contract::search(['search' => str_repeat('a', 100)]));
        self::assertSame('%50\\%\\_a\\\\b%', Contract::likePattern('50%_a\\b'));
        foreach ([['search' => str_repeat('a', 101)], ['search' => ['x']], ['search' => "a\0b"]] as $query) {
            try {
                Contract::search($query);
                self::fail('Invalid search accepted');
            } catch (Problem $e) {
                self::assertSame('invalid_search', $e->errorCode);
                self::assertSame(400, $e->getCode());
            }
        }
    }

    public function testCapabilitiesAdvertiseSearch(): void
    {
        $data = (new V2(new Api()))->handle('GET', '/api/v2/capabilities')['body']['data'];
        self::assertSame(['resources' => ['pages', 'boxes'], 'max_length' => 100], $data['search']);
    }

    public function testBoxRegistryTellsWhichBoxesRenderArticles(): void
    {
        $registry = new V2\BoxRegistry();
        $registry->register('story', 'blog', 'story', [], ['central'], ['renders' => 'article']);
        $registry->register('promo', 'promo', 'show');
        self::assertTrue($registry->rendersArticles('blog', 'story'));
        self::assertFalse($registry->rendersArticles('promo', 'show'));
        self::assertFalse($registry->rendersArticles('other', 'unregistered'));
        self::assertTrue($registry->rendersArticles('Other', 'getArticleBox'));
        self::assertTrue($registry->rendersArticles('Article', 'anything'));
        self::assertSame('article', $registry->catalog()[0]['renders']);
        self::assertNull($registry->catalog()[1]['renders']);
        foreach ([['renders' => 'page'], ['module' => 'x']] as $metadata) {
            try {
                $registry->register('bad', 'bad', 'bad', [], ['central'], $metadata);
                self::fail('Invalid metadata accepted');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('metadata', $e->getMessage());
            }
        }
    }

    public function testPageResponseIncludesBoundedMetadataWithoutInternalOffset(): void
    {
        self::assertSame([
            'success' => true,
            'data' => [['id' => 5]],
            'pagination' => ['page' => 2, 'per_page' => 2, 'total' => 3, 'total_pages' => 2],
        ], Contract::page([['id' => 5]], ['page' => '2', 'per_page' => '2'], 3));
    }

    public function testIntegrationRequiresHttpsBeforeAnyKeyLookup(): void
    {
        $model = new ApiV2MemoryModel();
        $controller = new V2(new Api(), $model, null, $this->isolatedLimiter);
        $response = $controller->handle('GET', '/api/v2/identity', 'Bearer secret', false);
        self::assertSame(403, $response['status']);
        self::assertSame('https_required', $response['body']['error']['code']);
        self::assertSame([], $model->events);
    }

    public function testMissingOrInvalidKeyIsUnauthorizedAndDoesNotLeakFailureDetails(): void
    {
        $model = new ApiV2MemoryModel();
        $controller = new V2(new Api(), $model, null, $this->isolatedLimiter);
        foreach (['', 'Basic secret', 'Bearer wrong'] as $authorization) {
            $response = $controller->handle('GET', '/api/v2/identity', $authorization, true);
            self::assertSame(401, $response['status']);
            self::assertSame('unauthorized', $response['body']['error']['code']);
            self::assertStringNotContainsString('secret', json_encode($response));
        }
    }

    public function testIdentityIgnoresRoleMetadataAndAuditSeparatesCentralActor(): void
    {
        $model = new ApiV2MemoryModel();
        $controller = new V2(new Api(), $model, null, $this->isolatedLimiter);
        $response = $controller->handle('GET', '/api/v2/identity', 'Bearer good', true, 'central-user-42');
        self::assertSame(200, $response['status']);
        self::assertSame(['integration_id' => 7, 'name' => 'central', 'scopes' => ['discovery:read']], $response['body']['data']);
        self::assertSame([7, 'central-user-42', 'identity.read', 'allowed'], $model->events[0]);
        self::assertArrayNotHasKey('role', $response['body']['data']);
    }

    public function testWrongScopeProduces403AndAuditsKnownIdentity(): void
    {
        $model = new ApiV2MemoryModel();
        $controller = new V2(new Api(), $model, null, $this->isolatedLimiter);
        $response = $controller->handle('GET', '/api/v2/identity', 'Bearer no-scope', true, 'actor');
        self::assertSame(403, $response['status']);
        self::assertSame([8, 'actor', 'identity.read', 'forbidden'], $model->events[0]);
    }

    public function testFailedAuthIsLimitedPerTrustedIpBeforeFurtherAuditWrites(): void
    {
        $dir = sys_get_temp_dir() . '/api-v2-limiter-' . bin2hex(random_bytes(8));
        $model = new ApiV2MemoryModel();
        $controller = new V2(new Api(), $model, null, new \Wbengine\RateLimit\Limiter($dir));
        try {
            for ($i = 0; $i < 10; $i++) {
                self::assertSame(401, $controller->handle('GET', '/api/v2/identity', 'Bearer random-' . $i, true, null, '', [], null, '192.0.2.17')['status']);
            }
            self::assertSame(429, $controller->handle('GET', '/api/v2/identity', 'Bearer another', true, null, '', [], null, '192.0.2.17')['status']);
            self::assertCount(10, $model->events);
            self::assertSame(401, $controller->handle('GET', '/api/v2/identity', 'Bearer another', true, null, '', [], null, '192.0.2.18')['status']);
            self::assertCount(2, glob($dir . '/*.rl'));
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) { unlink($file); }
            if (is_dir($dir)) { rmdir($dir); }
        }
    }

    public function testProvisionAndRevokeRejectUnsafeInputBeforeDatabaseLookup(): void
    {
        $model = new Model();
        foreach ([
            fn () => $model->provision(1, 'Central', ['*']),
            fn () => $model->provision(1, 'Central', [['discovery:read']]),
            fn () => $model->provision(1, 'Central', []),
            fn () => $model->provision(1, '', ['discovery:read']),
            fn () => $model->revoke(1, "1 OR 1=1"),
            fn () => $model->revoke(1, [1]),
            fn () => $model->revoke(1, 0),
            fn () => $model->audit(1, "actor\nforged", 'identity.read', 'allowed'),
        ] as $work) {
            try {
                $work();
                self::fail('Invalid input accepted');
            } catch (Problem $e) {
                self::assertSame(400, $e->getCode());
            }
        }
    }

    public function testV2RoutesAreRegisteredByApiAndPreserveProblemContract(): void
    {
        $server = $_SERVER;
        try {
            foreach ([['GET', '/api/v2?probe=1', 200], ['PATCH', '/api/v2', 405], ['OPTIONS', '/api/v2', 405], ['GET', '/api/v2/arbitrary/method', 404]] as [$method, $uri, $status]) {
                $_SERVER['REQUEST_METHOD'] = $method;
                $_SERVER['REQUEST_URI'] = $uri;
                $api = new Api();
                $api->setTerminator(static function () { throw new ApiV2ResponseSent(); });
                ob_start();
                try {
                    $api->dispatchV2();
                    self::fail('API v2 did not send a response.');
                } catch (ApiV2ResponseSent $e) {
                    $body = json_decode(ob_get_contents(), true, 64, JSON_THROW_ON_ERROR);
                } finally {
                    ob_end_clean();
                }
                self::assertSame($status, $api->getLastStatus());
                self::assertSame(200 === $status, $body['success']);
            }
        } finally {
            $_SERVER = $server;
        }
    }

    public function testUnexpectedStorageFailureReturnsGenericUnavailable(): void
    {
        $model = new ApiV2MemoryModel();
        $controller = new V2(new Api(), $model, null, $this->isolatedLimiter);
        $log = ini_set('error_log', '/dev/null');
        try {
            $response = $controller->handle('GET', '/api/v2/identity', 'Bearer failure', true);
        } finally {
            ini_set('error_log', $log);
        }
        self::assertSame(503, $response['status']);
        self::assertStringNotContainsString('/secret', json_encode($response));
        self::assertStringNotContainsString('SQL', json_encode($response));
    }
}

class ApiV2MemoryModel extends Model
{
    public array $events = [];
    public function authenticate(string $token, ?string $clientIp = null): ?array
    {
        if ('failure' === $token) {
            throw new \RuntimeException('SQL /secret/password');
        }
        if ('good' === $token) {
            return ['integration_id' => 7, 'name' => 'central', 'scopes' => ['discovery:read']];
        }
        if ('no-scope' === $token) {
            return ['integration_id' => 8, 'name' => 'restricted', 'scopes' => ['articles:read']];
        }
        return null;
    }
    public function audit(?int $integrationId, ?string $centralActor, string $action, string $outcome, ?int $localUserId = null, ?string $detail = null): void
    {
        $this->events[] = [$integrationId, $centralActor, $action, $outcome];
    }
}

class ApiV2ResponseSent extends \RuntimeException {}
