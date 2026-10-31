<?php
namespace Wbengine\Tests\Unit\Mcp;

use PHPUnit\Framework\TestCase;
use Wbengine\Mcp\Api\Client;
use Wbengine\Mcp\ToolError;
use Wbengine\Mcp\ToolRegistry;
use Wbengine\Mcp\Tools\Insight;
use Wbengine\Mcp\Tools\SiteFacts;

/** Statistics, the report and the activity analysis against a site that answers with known numbers. */
final class InsightTest extends TestCase
{
    /** @var array<int, array{string, array}> every request: path and query */
    private array $seen = [];

    private function tools(callable $routes): ToolRegistry
    {
        $transport = function (string $method, string $url) use ($routes): array {
            $path = substr(parse_url($url, PHP_URL_PATH), strlen('/api/v2'));
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $this->seen[] = [$path, $query];
            $answer = $routes($path, $query);
            [$status, $data] = $answer;
            $total = $answer[2] ?? (is_array($data) && array_is_list($data) ? count($data) : 1);
            $body = 200 === $status ? ['success' => true, 'data' => $data, 'pagination' => ['page' => (int) ($query['page'] ?? 1), 'per_page' => 100, 'total' => $total, 'total_pages' => 1]] : ['success' => false, 'error' => ['code' => $data, 'message' => $data]];
            return ['status' => $status, 'headers' => ['etag' => '"e"'], 'body' => json_encode($body)];
        };
        $api = new Client('https://site.example', 'wbv2_k', 'mcp:test', $transport);
        $registry = new ToolRegistry();
        (new Insight($api, new SiteFacts($api)))->register($registry);
        return $registry;
    }

    private function call(ToolRegistry $tools, string $name, array $args = []): array
    {
        $r = $tools->get($name)->run($args);
        return ['text' => $r->text, 'data' => $r->structured];
    }

    private function overview(int $pages, array $extra = []): array
    {
        return $extra + [
            'timezone' => 'UTC',
            'period' => ['from' => '2026-10-25', 'to' => '2026-10-31', 'granularity' => 'day', 'complete' => true],
            'totals' => ['page_views' => $pages, 'article_views' => 0, 'box_appearances' => 0],
            'lifetime_totals' => ['page_views' => 1000, 'article_views' => 100, 'box_appearances' => 0],
            'series' => [['period_start' => '2026-10-29', 'page_views' => $pages - 10, 'article_views' => 0, 'box_appearances' => 0], ['period_start' => '2026-10-30', 'page_views' => 10, 'article_views' => 0, 'box_appearances' => 0], ['period_start' => '2026-10-31', 'page_views' => 0, 'article_views' => 0, 'box_appearances' => 0]],
            'top_pages' => [['id' => 1, 'title' => 'Home', 'views' => $pages - 20], ['id' => 2, 'title' => 'Blog | news', 'views' => 20]],
            'top_articles' => [], 'top_boxes' => [],
        ];
    }

    public function testStatisticsListTheNumbersAndMarkSiteTextAsData(): void
    {
        $tools = $this->tools(fn($path) => '/analytics/overview' === $path ? [200, $this->overview(120)] : [404, 'not_found']);
        $r = $this->call($tools, 'get_statistics', ['days' => 7]);
        $this->assertStringContainsString('120 page views, 0 article views', $r['text']);
        $this->assertStringContainsString('Since counting began: 1000 page views, 100 article views', $r['text']);
        $this->assertStringContainsString('BEGIN SITE DATA', $r['text']);
        $this->assertStringContainsString('#1 Home: 100', $r['text']);
        $this->assertStringContainsString('Top articles: none counted.', $r['text']);
        $this->assertSame(120, $r['data']['totals']['page_views']);
        $this->assertSame('day', $this->seen[0][1]['granularity']);
        $this->assertSame(6, (new \DateTimeImmutable($this->seen[0][1]['to']))->diff(new \DateTimeImmutable($this->seen[0][1]['from']))->days, 'seven days end to end');
    }

    public function testAPeriodThatStartsBeforeCountingIsSaidToBeIncomplete(): void
    {
        $data = $this->overview(5);
        $data['period']['complete'] = false;
        $tools = $this->tools(fn() => [200, $data]);
        $this->assertStringContainsString('nothing was counted, not because nobody came', $this->call($tools, 'get_statistics', ['days' => 30])['text']);
        $this->assertStringContainsString('starts before counting began', $this->call($tools, 'site_report', ['days' => 30, 'compare' => false])['text']);
    }

    public function testFromAndToNeedEachOtherAndDatesAreChecked(): void
    {
        $tools = $this->tools(fn() => [200, $this->overview(1)]);
        $this->expectException(ToolError::class);
        $this->call($tools, 'get_statistics', ['from' => '2026-10-01']);
    }

    public function testADayAheadOfTheSiteIsRetriedOneDayEarlier(): void
    {
        $calls = 0;
        $tools = $this->tools(function ($path, $query) use (&$calls) {
            ++$calls;
            return 1 === $calls ? [400, 'invalid_period'] : [200, $this->overview(9)];
        });
        $this->assertSame(9, $this->call($tools, 'get_statistics', ['days' => 3])['data']['totals']['page_views']);
        $this->assertSame(2, $calls);
        $this->assertSame(1, (new \DateTimeImmutable($this->seen[0][1]['to']))->diff(new \DateTimeImmutable($this->seen[1][1]['to']))->days);
    }

    public function testAnImpossiblePeriodIsExplained(): void
    {
        $tools = $this->tools(fn() => [400, 'invalid_period']);
        try { $this->call($tools, 'get_statistics', ['days' => 365]); $this->fail('expected an error'); } catch (ToolError $e) { $this->assertStringContainsString('older than the site keeps', $e->getMessage()); }
    }

    public function testTheReportComputesChangesAgainstThePeriodBefore(): void
    {
        $tools = $this->tools(function ($path, $query) {
            if ('/analytics/overview' !== $path) { return [404, 'not_found']; }
            return [200, $this->overview($query['from'] >= (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->modify('-7 days')->format('Y-m-d') ? 150 : 100)];
        });
        $r = $this->call($tools, 'site_report', ['days' => 7]);
        $this->assertStringContainsString('| Page views | 150 | 100 | +50 (+50%) |', $r['text']);
        $this->assertStringContainsString('| 1 | Home | 130 | +50 (+63%) |', $r['text']);
        $this->assertStringContainsString('Blog / news', $r['text'], 'a pipe in a title does not break the table');
        $this->assertStringContainsString('2026-10-29: 140 page views', $r['text'], 'busiest day first');
        $this->assertStringContainsString('Visitors are not identified', $r['text']);
        $this->assertStringContainsString('Only the top ten', $r['text']);
        $this->assertSame(50, $r['data']['changes']->page_views['percent']);
        $this->assertTrue($r['data']['compared']);
    }

    public function testTheReportSaysWhenThereIsNothingToCompareWith(): void
    {
        $calls = 0;
        $tools = $this->tools(function () use (&$calls) { return 1 === ++$calls ? [200, $this->overview(40)] : [400, 'invalid_period']; });
        $r = $this->call($tools, 'site_report', ['days' => 7]);
        $this->assertStringContainsString('There is no comparison', $r['text']);
        $this->assertStringNotContainsString('| Before |', $r['text']);
        $this->assertFalse($r['data']['compared']);
    }

    public function testActivityIsCountedByActorResourceActionAndDay(): void
    {
        $t = strtotime('today 12:00 UTC');
        $row = fn($id, $actor, $res, $rid, $action, $at) => ['change_id' => $id, 'created_at' => $at, 'resource' => $res, 'resource_id' => (string) $rid, 'action' => $action, 'actor' => $actor];
        $changes = [$row(1, 'mcp:a', 'articles', 5, 'draft.save', $t), $row(2, 'mcp:a', 'articles', 5, 'draft.save', $t), $row(3, 'mcp:a', 'articles', 5, 'publish', $t - 86400), $row(4, 'mcp:b', 'pages', 9, 'update', $t - 86400)];
        $refused = array_map(fn($i) => ['audit_id' => $i, 'created_at' => $t, 'action' => 'pages.delete', 'outcome' => 'rejected', 'detail' => 'resource_in_use', 'actor' => 'mcp:b'], range(1, 5));
        $tools = $this->tools(fn($path, $query) => '/changes' === $path ? [200, 'rejected' === ($query['outcome'] ?? '') ? $refused : $changes] : [404, 'not_found']);
        $r = $this->call($tools, 'analyze_activity', ['days' => 3]);
        $this->assertStringContainsString('4 changes', $r['text']);
        $this->assertStringContainsString('5 refused writes', $r['text']);
        $this->assertSame(['mcp:a' => 3, 'mcp:b' => 1], (array) $r['data']['by_actor']);
        $this->assertSame(['articles' => 3, 'pages' => 1], (array) $r['data']['by_resource']);
        $this->assertSame(2, ((array) $r['data']['by_action'])['articles.draft.save']);
        $this->assertSame(['articles 5' => 3], (array) $r['data']['repeated']);
        $this->assertSame(['resource_in_use' => 5], (array) $r['data']['refused_by_error']);
        $this->assertStringContainsString('Repeated refusals of the same kind (resource_in_use, 5 times)', $r['text']);
        $this->assertStringContainsString('BEGIN SITE DATA', $r['text']);
        $filters = array_filter($this->seen, static fn($s) => '/changes' === $s[0]);
        $this->assertGreaterThan(0, (int) array_values($filters)[0][1]['since']);
    }

    public function testActivityWorksForAKeyThatCannotReadRefusedWrites(): void
    {
        $tools = $this->tools(fn($path, $query) => '/changes' === $path ? (isset($query['outcome']) ? [403, 'forbidden'] : [200, []]) : [404, 'not_found']);
        $r = $this->call($tools, 'analyze_activity');
        $this->assertStringContainsString('0 changes, 0 refused writes', $r['text']);
    }

    public function testThereIsNoChangeAgainstAPeriodThatBeganBeforeCounting(): void
    {
        $calls = 0;
        $tools = $this->tools(function () use (&$calls) {
            $data = $this->overview(40);
            if (++$calls > 1) { $data['period']['complete'] = false; $data['totals']['page_views'] = 0; }
            return [200, $data];
        });
        $r = $this->call($tools, 'site_report', ['days' => 7]);
        $this->assertStringContainsString('There is no comparison: the period before starts before counting began', $r['text']);
        $this->assertStringNotContainsString('| Before |', $r['text']);
        $this->assertStringNotContainsString('new (+', $r['text']);
        $this->assertStringNotContainsString('%)', $r['text']);
        $this->assertFalse($r['data']['compared']);
        $this->assertSame(40, $r['data']['totals']['page_views']);
    }
}
