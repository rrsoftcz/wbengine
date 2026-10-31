<?php
namespace Wbengine\Tests\Unit\Mcp;

use PHPUnit\Framework\TestCase;
use Wbengine\Mcp\Api\ApiError;
use Wbengine\Mcp\Api\Client;

final class ClientTest extends TestCase
{
    private const KEY = 'wbv2_supersecretkeyvalue';

    private array $seen = [];

    /** A client whose transport records the request and returns the given answer. */
    private function client(array $answer, int $maxBytes = Client::MAX_RESPONSE_BYTES): Client
    {
        return new Client('https://www.example.org/', self::KEY, 'mcp:test', function (string $method, string $url, array $headers, ?string $body, array $limits) use ($answer): array {
            $this->seen = compact('method', 'url', 'headers', 'body', 'limits');
            return $answer + ['status' => 200, 'headers' => [], 'body' => ''];
        }, false, 30, $maxBytes);
    }

    private function ok(mixed $data, array $headers = []): array
    {
        return ['status' => 200, 'headers' => $headers, 'body' => json_encode(['success' => true, 'data' => $data, 'pagination' => ['page' => 1, 'per_page' => 25, 'total' => 1]])];
    }

    public function testRequestCarriesTheContractHeadersAndParsesTheEnvelope(): void
    {
        $response = $this->client($this->ok(['id' => 5], ['etag' => '"v1"']))->request('PATCH', '/articles/5', ['search' => 'a b', 'page' => 2], ['title' => "Čau"], '"v0"');
        $this->assertSame('PATCH', $this->seen['method']);
        $this->assertSame('https://www.example.org/api/v2/articles/5?search=a%20b&page=2', $this->seen['url']);
        $this->assertSame('Bearer ' . self::KEY, $this->seen['headers']['Authorization']);
        $this->assertSame('mcp:test', $this->seen['headers']['X-WBengine-Actor']);
        $this->assertSame('"v0"', $this->seen['headers']['If-Match']);
        $this->assertSame('application/json', $this->seen['headers']['Content-Type']);
        $this->assertSame('{"title":"Čau"}', $this->seen['body']);
        $this->assertSame(['id' => 5], $response->data);
        $this->assertSame('"v1"', $response->etag);
        $this->assertSame(1, $response->pagination['total']);
    }

    public function testAnEmptyBodyIsSentAsAJsonObject(): void
    {
        $this->client($this->ok([]))->request('POST', '/articles/1/publish', [], [], '"e"');
        $this->assertSame('{}', $this->seen['body']);
    }

    public function testNoBodyNoIfMatchMeansNoSuchHeaders(): void
    {
        $this->client($this->ok([]))->request('GET', '/pages');
        $this->assertArrayNotHasKey('If-Match', $this->seen['headers']);
        $this->assertArrayNotHasKey('Content-Type', $this->seen['headers']);
        $this->assertNull($this->seen['body']);
    }

    public function testApiErrorsKeepTheirCodeAndGetAdvice(): void
    {
        try {
            $this->client(['status' => 409, 'body' => json_encode(['success' => false, 'error' => ['code' => 'article_site_conflict', 'message' => 'Page has an article.']])])->request('POST', '/articles/1/publish');
            $this->fail('expected an error');
        } catch (ApiError $e) {
            $this->assertSame('article_site_conflict', $e->errorCode);
            $this->assertSame(409, $e->status);
            $this->assertStringContainsString('Choose another page', $e->advice());
        }
    }

    public function testUnknownCodesAreShownAsTheSiteSaidThem(): void
    {
        try {
            $this->client(['status' => 422, 'body' => json_encode(['success' => false, 'error' => ['code' => 'something_new', 'message' => 'Strange.']])])->request('GET', '/x');
        } catch (ApiError $e) {
            $this->assertSame('The site refused the request (something_new): Strange.', $e->advice());
        }
    }

    public function testEveryDocumentedCodeHasAdvice(): void
    {
        foreach (['article_site_conflict', 'missing_site', 'precondition_failed', 'ip_not_allowed', 'forbidden', 'hide_unavailable', 'invalid_field', 'duplicate_link', 'unauthorized', 'not_found'] as $code) {
            $this->assertStringNotContainsString('refused the request', (new ApiError($code, 'x', 400))->advice(), $code);
        }
    }

    public function testNonEnvelopeAnswersAreRejected(): void
    {
        foreach (['<html>login</html>', '', '{"data":1}', '[1]', '{"success":"yes"}'] as $body) {
            try {
                $this->client(['body' => $body])->request('GET', '/x');
                $this->fail('expected an error for ' . $body);
            } catch (ApiError $e) {
                $this->assertSame('bad_response', $e->errorCode);
            }
        }
    }

    public function testRedirectsAreRefusedAndOversizeAnswersDropped(): void
    {
        try { $this->client(['status' => 302, 'headers' => ['location' => 'https://evil.example/']])->request('GET', '/x'); $this->fail(); } catch (ApiError $e) { $this->assertSame('redirect_refused', $e->errorCode); }
        try { $this->client($this->ok(str_repeat('x', 500)), 100)->request('GET', '/x'); $this->fail(); } catch (ApiError $e) { $this->assertSame('response_too_large', $e->errorCode); }
    }

    public function testTheKeyIsScrubbedFromErrorTexts(): void
    {
        $answer = ['status' => 403, 'body' => json_encode(['success' => false, 'error' => ['code' => 'forbidden', 'message' => 'bad ' . self::KEY]])];
        try { $this->client($answer)->request('GET', '/x'); $this->fail(); } catch (ApiError $e) {
            $this->assertStringNotContainsString(self::KEY, $e->advice());
            $this->assertStringNotContainsString(self::KEY, $e->getMessage());
            $this->assertStringContainsString('[key]', $e->getMessage());
        }
        $client = new Client('https://www.example.org', self::KEY, 'a', static fn() => throw new ApiError('transport', 'failed with ' . self::KEY));
        try { $client->request('GET', '/x'); $this->fail(); } catch (ApiError $e) { $this->assertStringNotContainsString(self::KEY, $e->getMessage()); }
    }

    public function testAddressRules(): void
    {
        $this->assertSame('https://www.example.org', Client::baseUrl('https://WWW.example.org/', false));
        $this->assertSame('https://example.org:8443/cms', Client::baseUrl('https://example.org:8443/cms/', false));
        $this->assertSame('http://localhost:8080', Client::baseUrl('http://localhost:8080', true));
        foreach ([['http://www.example.org', true], ['http://localhost', false], ['ftp://example.org', false], ['https://u:p@example.org', false], ['https://example.org/?a=1', false], ['https://example.org/#x', false], ['example.org', false], ['', false]] as [$url, $insecure]) {
            try { Client::baseUrl($url, $insecure); $this->fail($url); } catch (ApiError $e) { $this->assertSame('invalid_configuration', $e->errorCode, $url); }
        }
    }

    public function testKeyShapeIsChecked(): void
    {
        foreach (['', "a b", "a\nb"] as $key) {
            try { new Client('https://example.org', $key, 'a'); $this->fail(); } catch (ApiError $e) { $this->assertSame('invalid_configuration', $e->errorCode); $this->assertStringNotContainsString('a b', $e->getMessage()); }
        }
    }

    public function testOnlyHostNamesWithAnIpv4AddressAreTriedOverIpv4First(): void
    {
        $client = new Client('https://www.example.org', self::KEY, 'mcp:test', static fn() => []);
        $has = new \ReflectionMethod($client, 'hasIpv4');
        $this->assertTrue($has->invoke($client, 'localhost'));
        $this->assertFalse($has->invoke($client, '127.0.0.1'), 'an address is used as it is');
        $this->assertFalse($has->invoke($client, '::1'));
        $this->assertFalse($has->invoke($client, ''));
        $this->assertFalse($has->invoke($client, 'no-such-host.invalid'), 'no IPv4 address: the default behaviour');
    }
}
