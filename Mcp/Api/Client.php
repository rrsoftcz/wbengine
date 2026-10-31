<?php

namespace Wbengine\Mcp\Api;

/**
 * Client of the WBengine API v2. HTTPS with a verified certificate, no redirects, JSON only, bounded
 * time and size. The key travels only in the Authorization header and is scrubbed from every text
 * this class produces.
 *
 * The transport is a callable (method, url, headers, body, limits) returning
 * ['status' => int, 'headers' => array<lowercase name, value>, 'body' => string], so tests can call
 * the API controller in process.
 */
final class Client
{
    public const MAX_RESPONSE_BYTES = 2097152;

    /** @var callable */
    private $transport;
    private string $base;

    public function __construct(
        string $baseUrl,
        #[\SensitiveParameter] private readonly string $key,
        private readonly string $actor,
        ?callable $transport = null,
        bool $allowInsecure = false,
        private readonly int $timeout = 30,
        private readonly int $maxBytes = self::MAX_RESPONSE_BYTES,
    ) {
        if ('' === $key || preg_match('/[^\x21-\x7e]/', $key)) {
            throw new ApiError('invalid_configuration', 'The integration key is empty or contains spaces or control characters.');
        }
        $this->base = self::baseUrl($baseUrl, $allowInsecure);
        $this->transport = $transport ?? [$this, 'streamTransport'];
    }

    /** Validate the site address: HTTPS (plain HTTP to localhost only when allowed), no credentials, query or fragment. */
    public static function baseUrl(string $url, bool $allowInsecure): string
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ('' === $host || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new ApiError('invalid_configuration', 'WBENGINE_MCP_URL must be an address like https://www.example.org without credentials, query or fragment.');
        }
        $local = in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
        if (!('https' === $scheme || ('http' === $scheme && $local && $allowInsecure))) {
            throw new ApiError('invalid_configuration', 'WBENGINE_MCP_URL must use https' . ($local ? ' (plain http to localhost needs WBENGINE_MCP_ALLOW_INSECURE=1).' : '.'));
        }
        return $scheme . '://' . $host . (isset($parts['port']) ? ':' . $parts['port'] : '') . rtrim($parts['path'] ?? '', '/');
    }

    /**
     * @param array<string, scalar> $query
     * @param array<string, mixed>|null $body
     * @throws ApiError
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null, ?string $ifMatch = null): Response
    {
        $url = $this->base . '/api/v2' . $path . ([] === $query ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        $headers = ['Authorization' => 'Bearer ' . $this->key, 'Accept' => 'application/json', 'X-WBengine-Actor' => $this->actor];
        $payload = null;
        if (null !== $body) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION);
            $headers['Content-Type'] = 'application/json';
        }
        if (null !== $ifMatch) { $headers['If-Match'] = $ifMatch; }
        try {
            $answer = ($this->transport)($method, $url, $headers, $payload, ['timeout' => $this->timeout, 'max_bytes' => $this->maxBytes]);
        } catch (ApiError $e) {
            throw new ApiError($e->errorCode, $this->scrub($e->getMessage()), $e->status);
        }
        return $this->parse($answer);
    }

    /** @param array{status: int, headers: array<string, string>, body: string} $answer */
    private function parse(array $answer): Response
    {
        $status = (int) $answer['status'];
        if ($status >= 300 && $status < 400) {
            throw new ApiError('redirect_refused', 'HTTP ' . $status, $status);
        }
        if (strlen($answer['body']) > $this->maxBytes) {
            throw new ApiError('response_too_large', 'More than ' . $this->maxBytes . ' bytes.', $status);
        }
        $json = json_decode($answer['body'], true, 64);
        if (!is_array($json) || !isset($json['success']) || !is_bool($json['success'])) {
            throw new ApiError('bad_response', 'HTTP ' . $status . ', the body is not an API v2 envelope.', $status);
        }
        if (!$json['success']) {
            $error = is_array($json['error'] ?? null) ? $json['error'] : [];
            $code = is_string($error['code'] ?? null) && preg_match('/^[a-z0-9_]{1,64}$/', $error['code']) ? $error['code'] : 'bad_response';
            throw new ApiError($code, $this->scrub((string) ($error['message'] ?? '')), $status);
        }
        $etag = $answer['headers']['etag'] ?? null;
        return new Response($status, $json['data'] ?? null, is_array($json['pagination'] ?? null) ? $json['pagination'] : null, is_string($etag) ? $etag : null);
    }

    /** Remove the key from any text that may be shown to the model or logged. */
    private function scrub(string $text): string
    {
        return str_replace($this->key, '[key]', $text);
    }

    /** @internal default transport: PHP streams with certificate and host name verification, redirects off. */
    public function streamTransport(string $method, string $url, array $headers, ?string $body, array $limits): array
    {
        $lines = [];
        foreach ($headers as $name => $value) { $lines[] = $name . ': ' . $value; }
        $context = stream_context_create([
            'http' => ['method' => $method, 'header' => implode("\r\n", $lines), 'content' => $body ?? '', 'timeout' => $limits['timeout'], 'follow_location' => 0, 'max_redirects' => 0, 'ignore_errors' => true],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
        ]);
        $handle = @fopen($url, 'rb', false, $context);
        if (false === $handle) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            throw new ApiError('transport', 'Cannot connect to ' . $host . ' (network, certificate or timeout).');
        }
        $deadline = microtime(true) + $limits['timeout'];
        $content = '';
        while (!feof($handle)) {
            $chunk = fread($handle, 65536);
            if (false === $chunk) { break; }
            $content .= $chunk;
            if (strlen($content) > $limits['max_bytes']) {
                fclose($handle);
                throw new ApiError('response_too_large', 'More than ' . $limits['max_bytes'] . ' bytes.');
            }
            if (microtime(true) > $deadline || stream_get_meta_data($handle)['timed_out']) {
                fclose($handle);
                throw new ApiError('transport', 'The site did not answer within ' . $limits['timeout'] . ' seconds.');
            }
        }
        $meta = stream_get_meta_data($handle)['wrapper_data'] ?? [];
        fclose($handle);
        $status = 0;
        $received = [];
        foreach ($meta as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) { $status = (int) $m[1]; $received = []; continue; }
            if (str_contains($line, ':')) { [$name, $value] = explode(':', $line, 2); $received[strtolower(trim($name))] = trim($value); }
        }
        return ['status' => $status, 'headers' => $received, 'body' => $content];
    }
}
