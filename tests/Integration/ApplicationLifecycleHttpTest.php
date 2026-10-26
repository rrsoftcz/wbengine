<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Db;
use PHPUnit\Framework\Attributes\DataProvider;

class ApplicationLifecycleHttpTest extends DbTestCase
{
    private $server;
    private string $dir;
    private string $origin;

    protected function setUp(): void
    {
        parent::setUp();
        $site = Db::insert('cms_sites', ['link' => '/page/', 'visible' => 1, 'strict' => 0, 'acl' => 0]);
        Db::insert('cms_sites', ['link' => '/legacy-api/', 'visible' => 1, 'strict' => 0, 'acl' => 0]);
        $section = Db::insert('cms_sections', ['key' => 'central', 'active' => 1]);
        Db::insert('cms_boxes', ['module' => 'Probe', 'method' => 'Probe', 'section_id' => $section, 'shared' => 1, 'sites' => json_encode([(string) $site])]);
        $this->dir = sys_get_temp_dir() . '/wb-lifecycle-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700);
        foreach (['Config', 'View', 'View/Compiled'] as $subdir) { mkdir($this->dir . '/app/' . $subdir, 0700, true); }
        $config = json_decode(file_get_contents(dirname(__DIR__) . '/config/Settings.test.json'), true);
        $config['dbAdapterDefinition']['hostname'] = getenv('WB_TEST_DB_HOST') ?: 'db';
        $config['renderer']['adapterName'] = 'NativeRenderer';
        $config['cache'] = ['enabled' => true, 'driver' => 'file', 'dir' => $this->dir . '/cache', 'pages' => ['enabled' => true]];
        $config['i18n'] = ['enabled' => true, 'default' => 'en', 'locales' => ['en', 'cs'], 'detect' => ['url']];
        file_put_contents($this->dir . '/app/Config/Settings.json', json_encode($config));
        file_put_contents($this->dir . '/app/View/site.tpl', 'layout[{$central|raw}]|{$marker|default:"none"}');
        file_put_contents($this->dir . '/app/View/404.tpl', 'not-found');
        file_put_contents($this->dir . '/app/View/410.tpl', 'gone');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->origin = 'http://' . $address;
        $this->server = proc_open([PHP_BINARY, '-d', 'output_buffering=4096', '-S', $address, dirname(__DIR__) . '/fixtures/application-lifecycle.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $this->dir . '/server.log', 'a'],
                2 => ['file', $this->dir . '/server.log', 'a']], $pipes, null,
            array_merge(getenv(), ['WB_LIFECYCLE_DIR' => $this->dir]));
        for ($i = 0; $i < 100; $i++) {
            $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
            if (is_resource($probe)) {
                fclose($probe);
                return;
            }
            usleep(20000);
        }
        $this->fail('HTTP fixture did not start: ' . file_get_contents($this->dir . '/server.log'));
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        $this->removeTree($this->dir);
    }

    private function removeTree(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeTree($file) : unlink($file);
        }
        rmdir($dir);
    }

    private function request(string $path, array $headers = [], string $method = 'GET'): array
    {
        $context = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true, 'follow_location' => 0, 'method' => $method,
            'header' => implode("\r\n", $headers)]]);
        $stream = @fopen($this->origin . $path, 'r', false, $context);
        if (!is_resource($stream)) {
            $this->fail('HTTP fixture request failed: ' . $path . "\n" . (error_get_last()['message'] ?? 'unknown') . "\nServer log:\n" . file_get_contents($this->dir . '/server.log'));
        }
        $body = stream_get_contents($stream);
        $metadata = stream_get_meta_data($stream);
        fclose($stream);
        $result = ['status' => $metadata['wrapper_data'][0], 'body' => $body];
        foreach ($metadata['wrapper_data'] as $header) {
            if (str_contains($header, ':')) {
                [$name, $value] = explode(':', $header, 2);
                $result[strtolower($name)] = trim($value);
            }
        }
        return $result;
    }

    private function configureApi($enabled): void
    {
        $path = $this->dir . '/app/Config/Settings.json';
        $config = json_decode(file_get_contents($path), true);
        $config['apiV2'] = ['enabled' => $enabled];
        $config['http'] = ['trustedProxies' => ['127.0.0.1'], 'forwardedHeaders' => ['x-forwarded-proto']];
        file_put_contents($path, json_encode($config));
    }

    private function assertNoCmsWork(array $response): void
    {
        foreach (['queries', 'connections', 'boxes'] as $metric) { $this->assertSame('0', $response['x-lifecycle-' . $metric] ?? null, $metric); }
        $this->assertSame('none', $response['x-lifecycle-session'] ?? null);
        $this->assertArrayNotHasKey('set-cookie', $response);
        $this->assertArrayNotHasKey('access-control-allow-origin', $response);
        $this->assertArrayNotHasKey('x-wbengine-cache', $response);
    }

    public function testOptInV2DiscoveryDoesNotRequireAnyCmsRowsOrSession(): void
    {
        $this->configureApi(true);
        foreach (['cms_boxes', 'cms_sections', 'cms_sites'] as $table) { Db::query('DELETE FROM ' . $table); }
        foreach (['/api/v2', '/api/v2/', '/api/v2/capabilities?probe=1', '/en/api/v2?probe=1'] as $path) {
            $response = $this->request($path);
            $this->assertStringContainsString('200', $response['status']);
            $this->assertSame('2.0', json_decode($response['body'], true)['data']['api_version'] ?? null);
            $this->assertSame('private, no-store', $response['cache-control'] ?? null);
            $this->assertArrayNotHasKey('location', $response);
            $this->assertNoCmsWork($response);
        }
    }

    public function testV2DispatchRequiresStrictTrueAndExactPathBoundary(): void
    {
        foreach ([false, 'true', 1, null] as $enabled) {
            $this->configureApi($enabled);
            $response = $this->request('/api/v2');
            $this->assertStringContainsString('404', $response['status']);
            $this->assertSame('layout[not-found]|none', $response['body']);
        }
        $this->configureApi(true);
        foreach (['/api/v20', '/api/v2-other', '/api/V2'] as $path) {
            $response = $this->request($path);
            $this->assertStringContainsString('404', $response['status']);
            $this->assertSame('layout[not-found]|none', $response['body']);
        }
        $query = $this->request('/page/?next=/api/v2');
        $this->assertSame('layout[box:default]|none', $query['body']);
        $missing = $this->request('/api/v2/missing');
        $this->assertStringContainsString('404', $missing['status']);
        $this->assertSame('not_found', json_decode($missing['body'], true)['error']['code'] ?? null);
        $this->assertNoCmsWork($missing);
    }

    public function testV2PrecedesStoredCmsApiPageAndSurvivesItsRemoval(): void
    {
        $apiSite = Db::insert('cms_sites', ['link' => '/api/v2/', 'visible' => 1, 'strict' => 0, 'acl' => 0]);
        $cached = $this->request('/api/v2/');
        $this->assertSame('MISS-STORED', $cached['x-wbengine-cache'] ?? null);
        $this->assertSame('layout[box:default]|none', $cached['body']);
        $this->configureApi(true);
        $response = $this->request('/api/v2/');
        $this->assertSame('2.0', json_decode($response['body'], true)['data']['api_version'] ?? null);
        $this->assertNoCmsWork($response);
        Db::query('DELETE FROM cms_sites WHERE site_id = ?', [$apiSite]);
        $afterDelete = $this->request('/api/v2/');
        $this->assertSame('2.0', json_decode($afterDelete['body'], true)['data']['api_version'] ?? null);
        $this->assertNoCmsWork($afterDelete);
        $this->assertSame("box\n", file_get_contents($this->dir . '/events.log'));
    }

    public function testV2KeepsDeferredCustomRoutePriorityAndLazyApiTree(): void
    {
        $this->configureApi(true);
        $custom = $this->request('/en/api/v2', ['X-Lifecycle-Mode: api-custom']);
        $this->assertSame(['queries' => 0, 'connections' => 0, 'boxes' => 0, 'custom' => true, 'lazy_tree' => true], json_decode($custom['body'], true));
        $this->assertNoCmsWork($custom);
        $layout = $this->request('/api/v2', ['X-Lifecycle-Mode: api-layout']);
        $this->assertSame('layout[custom-api-layout]|none', $layout['body']);
        $declined = $this->request('/api/v2', ['X-Lifecycle-Mode: api-decline']);
        $this->assertSame('2.0', json_decode($declined['body'], true)['data']['api_version'] ?? null);
        $this->assertNoCmsWork($declined);
        $this->assertSame("declined\n", file_get_contents($this->dir . '/events.log'));
    }

    public function testV2ProtectedRoutesRejectHttpAndAnonymousHttpsWithoutCms(): void
    {
        $this->configureApi(true);
        $http = $this->request('/api/v2/identity');
        $this->assertStringContainsString('403', $http['status']);
        $this->assertSame('https_required', json_decode($http['body'], true)['error']['code'] ?? null);
        $this->assertNoCmsWork($http);
        $https = $this->request('/api/v2/identity', ['X-Lifecycle-Mode: api-auth-fixture', 'X-Forwarded-Proto: https']);
        $this->assertStringContainsString('401', $https['status']);
        $this->assertSame('unauthorized', json_decode($https['body'], true)['error']['code'] ?? null);
        $this->assertSame('0', $https['x-lifecycle-boxes'] ?? null);
        $this->assertSame('none', $https['x-lifecycle-session'] ?? null);
        $this->assertArrayNotHasKey('access-control-allow-origin', $https);
    }

    public function testConstructorDoesNoDatabaseOrBoxWork(): void
    {
        $response = $this->request('/page/', ['X-Lifecycle-Mode: constructor']);
        $this->assertSame(['queries' => 0, 'connections' => 0, 'boxes' => 0], json_decode($response['body'], true));
    }

    public function testDeferredTerminalRoutePrecedesCmsAndUsesNormalizedLocale(): void
    {
        foreach (['/health/7/' => 'en', '/cs/health/7/' => 'cs', '/en/health/7/?x=1' => 'en'] as $path => $locale) {
            $response = $this->request($path);
            $this->assertStringContainsString('200', $response['status']);
            $this->assertSame(['queries' => 0, 'connections' => 0, 'boxes' => 0, 'registered' => true, 'id' => '7', 'locale' => $locale], json_decode($response['body'], true));
        }
    }

    public function testPostTerminalRouteDoesNotInitializeCms(): void
    {
        $response = $this->request('/page/', ['X-Lifecycle-Mode: post'], 'POST');
        $this->assertSame(['queries' => 0, 'connections' => 0, 'boxes' => 0], json_decode($response['body'], true));
    }

    public function testRoutesRunBeforeBoxesAndFirstAcceptedRouteWins(): void
    {
        $response = $this->request('/page/', ['X-Lifecycle-Mode: order']);
        $this->assertSame('layout[box:route]|none', $response['body']);
        $this->assertSame("first\nsecond\nbox\n", file_get_contents($this->dir . '/events.log'));
    }

    public function testPageHitStillRunsCustomRoutesAndPoliciesFirst(): void
    {
        $first = $this->request('/page/');
        $this->assertSame('MISS-STORED', $first['x-wbengine-cache'] ?? null);
        $hit = $this->request('/page/');
        $this->assertSame('HIT', $hit['x-wbengine-cache'] ?? null);
        $this->assertSame($first['body'], $hit['body']);
        $terminal = $this->request('/page/', ['X-Lifecycle-Mode: terminal']);
        $this->assertSame('terminal', $terminal['body']);
        $this->assertSame("box\n", file_get_contents($this->dir . '/events.log'));
        $private = $this->request('/page/', ['X-Lifecycle-Mode: private']);
        $this->assertSame('private, no-store', $private['cache-control'] ?? null);
        $this->assertNotSame('HIT', $private['x-wbengine-cache'] ?? null);
        $this->assertSame("box\nbox\n", file_get_contents($this->dir . '/events.log'));
    }

    #[DataProvider('configuredModes')]
    public function testConfigurationBeforeRunCannotBeSkippedByAnOldPageHit(string $mode, string $expected, int $boxCount): void
    {
        $this->request('/page/');
        $response = $this->request('/page/', ['X-Lifecycle-Mode: ' . $mode]);
        $this->assertSame($expected, $response['body']);
        $this->assertNotSame('HIT', $response['x-wbengine-cache'] ?? null);
        $this->assertSame($boxCount, substr_count(file_get_contents($this->dir . '/events.log'), "box\n"));
    }

    public static function configuredModes(): array
    {
        return [['central', 'layout[manual]|none', 1], ['vars', 'layout[box:vars]|none', 2], ['renderer', 'layout[box:default]|renderer', 2]];
    }

    #[DataProvider('renderModes')]
    public function testRunAndDisplayRenderExactlyOnce(string $mode, string $expected): void
    {
        $response = $this->request('/page/', ['X-Lifecycle-Mode: ' . $mode]);
        $this->assertSame($expected, $response['body']);
        if (in_array($mode, ['zero', 'empty', 'display-route'], true)) {
            $this->assertFileDoesNotExist($this->dir . '/events.log');
        } else {
            $this->assertSame("box\n", file_get_contents($this->dir . '/events.log'));
        }
    }

    public static function renderModes(): array
    {
        return [['once', 'layout[box:default]|none'], ['zero', 'layout[0]|none'], ['empty', 'layout[]|none'], ['display-route', 'layout[route-display]|none'], ['display-box', 'layout[box-display]|none']];
    }

    public function testRedirectMetricsDoNotAutoloadDuringDeferredHeaderShutdown(): void
    {
        $response = $this->request('/en/page/?x=1', ['X-Lifecycle-Mode: shutdown-no-autoload']);
        $this->assertStringContainsString('301', $response['status']);
        $this->assertSame('0', $response['x-lifecycle-queries']);
        $this->assertSame('0', $response['x-lifecycle-connections']);
        $this->assertSame('none', $response['x-lifecycle-session']);
    }

    public function testCanonicalRedirectAndErrorsDoNotExecuteBoxes(): void
    {
        $redirect = $this->request('/en/page/?x=1');
        $this->assertStringContainsString('301', $redirect['status']);
        $this->assertSame('/page/?x=1', $redirect['location'] ?? null);
        $this->assertFileDoesNotExist($this->dir . '/events.log');
        $missing = $this->request('/missing/');
        $this->assertStringContainsString('404', $missing['status']);
        $this->assertSame('layout[not-found]|none', $missing['body']);
        $this->assertFileDoesNotExist($this->dir . '/events.log');
        $this->request('/page/');
        $gone = $this->request('/page/', ['X-Lifecycle-Mode: gone']);
        $this->assertStringContainsString('410', $gone['status']);
        $this->assertSame('layout[gone]|none', $gone['body']);
        $this->assertSame("box\n", file_get_contents($this->dir . '/events.log'));
    }

    #[DataProvider('declinedResponses')]
    public function testDeclinedOutputAndStatusCannotBeReplacedByAStoredHit(string $mode, string $body, string $status): void
    {
        $this->request('/page/');
        $response = $this->request('/page/', ['X-Lifecycle-Mode: ' . $mode]);
        $this->assertSame($body, $response['body']);
        $this->assertStringContainsString($status, $response['status']);
        $this->assertNotSame('HIT', $response['x-wbengine-cache'] ?? null);
    }

    public static function declinedResponses(): array
    {
        return [['decline-output', 'prefix:layout[box:default]|none', '200'],
            ['decline-status', 'layout[box:default]|none', '403']];
    }

    public function testApiRoutesInsideAnExistingBoxStillWork(): void
    {
        $response = $this->request('/legacy-api/');
        $this->assertSame(['legacy_api' => true], json_decode($response['body'], true));
    }

    public function testPageWithoutSectionsStillRenders(): void
    {
        Db::query('DELETE FROM cms_boxes');
        Db::query('DELETE FROM cms_sections');
        $response = $this->request('/page/');
        $this->assertSame('layout[]|none', $response['body']);
        $this->assertStringContainsString('200', $response['status']);
    }
}
