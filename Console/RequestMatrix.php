<?php
namespace Wbengine\Console;

/** Explicit HTTP benchmark client. Never boots or instruments production requests. */
final class RequestMatrix
{
    public static function summarize(array $samples, float $elapsed): array
    {
        if (!$samples || $elapsed <= 0) { throw new \InvalidArgumentException('Provide samples and positive elapsed time.'); }
        $latencies = array_column($samples, 'ms'); sort($latencies, SORT_NUMERIC);
        $n = count($samples);
        $metric = static function (string $key) use ($samples): ?array {
            $values = array_column($samples, $key);
            return count($values) === count($samples) ? ['min'=>min($values), 'max'=>max($values), 'mean'=>array_sum($values) / count($values)] : null;
        };
        return ['requests'=>$n, 'p50_ms'=>(float) $latencies[(int) ceil($n * .50) - 1], 'p95_ms'=>(float) $latencies[(int) ceil($n * .95) - 1],
            'requests_per_second'=>$n / $elapsed, 'elapsed_seconds'=>$elapsed,
            'errors'=>count(array_filter($samples, static fn ($s) => $s['status'] < 200 || $s['status'] >= 400)),
            'server_memory_bytes'=>$metric('memory'), 'sql_queries'=>$metric('queries'), 'sql_connections'=>$metric('connections')];
    }

    public function run(array $manifest, int $requests = 20, int $concurrency = 1): array
    {
        if (!extension_loaded('curl')) { throw new \RuntimeException('Install the curl extension in the benchmark client.'); }
        if ($requests < 1 || $requests > 100000 || $concurrency < 1 || $concurrency > 128) { throw new \InvalidArgumentException('Invalid request count or concurrency.'); }
        foreach (['php','server','database','opcache','data'] as $key) {
            if (!isset($manifest['environment'][$key]) || !is_string($manifest['environment'][$key])) { throw new \InvalidArgumentException('Describe PHP, server, database, OPcache and data in environment.'); }
        }
        $result = ['environment'=>$manifest['environment'], 'client_php'=>PHP_VERSION, 'concurrency'=>$concurrency, 'requests_per_case'=>$requests,
            'instrumentation'=>'Optional response headers X-WB-Benchmark-Memory/Queries/Connections; null means unavailable. Client memory is not server memory.', 'cases'=>[]];
        foreach (['off','data-warm','page-warm'] as $mode) {
            foreach (['anonymous','anonymous-session','logged','custom-api','same-session'] as $identity) {
                $case = $manifest['matrix'][$mode][$identity] ?? null;
                if (!is_array($case) || !isset($case['url'])) { throw new \InvalidArgumentException('Provide all 15 matrix cases.'); }
                $this->validateUrl($case['url']);
                $cookie = $case['cookie'] ?? '';
                if (!is_string($cookie) || preg_match('/[\r\n\x00]/', $cookie) || ($identity === 'anonymous' && $cookie !== '') || (in_array($identity, ['anonymous-session','logged','same-session'], true) && $cookie === '')) { throw new \InvalidArgumentException('Provide valid explicit session cookies for session cases; anonymous must send no Cookie.'); }
                $parallel = $identity === 'same-session' ? max(2, $concurrency) : $concurrency;
                // Manifest URLs select an already configured mode. No setup endpoint is invoked.
                // Warmup uses the exact URL and identity, but is excluded from measured samples.
                if ($mode !== 'off') { $this->batch($case['url'], $cookie, 1, 1); }
                $start = hrtime(true); $samples = $this->batch($case['url'], $cookie, $requests, $parallel);
                $result['cases'][$mode][$identity] = self::summarize($samples, (hrtime(true) - $start) / 1e9) + ['concurrency'=>$parallel];
            }
        }
        return $result;
    }

    private function validateUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http','https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) { throw new \InvalidArgumentException('Use HTTP(S) URLs without credentials.'); }
    }

    private function batch(string $url, string $cookie, int $count, int $concurrency): array
    {
        $multi = curl_multi_init(); $handles = []; $samples = []; $next = 0;
        try {
            while ($next < $count || $handles) {
                while ($next < $count && count($handles) < $concurrency) {
                    $handle = curl_init($url); $id = spl_object_id($handle); $metrics = new \stdClass();
                    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>30, CURLOPT_COOKIE=>$cookie,
                        CURLOPT_HEADERFUNCTION=>static function ($h, string $line) use ($metrics): int {
                            if (preg_match('/^X-WB-Benchmark-(Memory|Queries|Connections):\s*(\d+)\s*$/i', trim($line), $match)) { $key = strtolower($match[1]); $metrics->$key = (int) $match[2]; }
                            return strlen($line);
                        }]);
                    curl_multi_add_handle($multi, $handle); $handles[$id] = [$handle,$metrics]; ++$next;
                }
                do { $code = curl_multi_exec($multi, $active); } while ($code === CURLM_CALL_MULTI_PERFORM);
                if ($code !== CURLM_OK) { throw new \RuntimeException('HTTP benchmark transport failed.'); }
                while ($message = curl_multi_info_read($multi)) {
                    $handle = $message['handle']; $id = spl_object_id($handle); $metrics = $handles[$id][1];
                    $samples[] = ['ms'=>curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000, 'status'=>$message['result'] === CURLE_OK ? curl_getinfo($handle, CURLINFO_RESPONSE_CODE) : 0] + (array) $metrics;
                    curl_multi_remove_handle($multi, $handle); unset($handles[$id]);
                }
                if ($active) { curl_multi_select($multi, 0.1); }
            }
        } finally {
            foreach ($handles as [$handle]) { curl_multi_remove_handle($multi, $handle); }
            unset($multi);
        }
        return $samples;
    }
}
