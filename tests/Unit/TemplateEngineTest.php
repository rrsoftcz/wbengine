<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Cache;
use Wbengine\Cache\FileCache;
use Wbengine\Template\Engine;
use Wbengine\Template\Exception\TemplateException;

class TemplateEngineTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wb-tpl-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/tpl', 0777, true);
        mkdir($this->dir . '/compiled', 0777, true);
        mkdir($this->dir . '/outside', 0777, true);
        $GLOBALS['wb_pwned'] = false;
    }

    protected function tearDown(): void
    {
        Cache::setDriver(null);
        $this->rmTree($this->dir);
        unset($GLOBALS['wb_pwned']);
    }

    private function rmTree(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? $this->rmTree($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    private function engine(bool $autoEscape = true): Engine
    {
        return (new Engine())
            ->setTemplateDir($this->dir . '/tpl')
            ->setCompileDir($this->dir . '/compiled')
            ->setAutoEscape($autoEscape);
    }

    private function write(string $name, string $source): void
    {
        $path = $this->dir . '/tpl/' . $name;
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $source);
    }

    private function render(string $source, array $vars = [], bool $autoEscape = true): string
    {
        $this->write('t.tpl', $source);
        return $this->engine($autoEscape)->assign($vars)->fetch('t.tpl');
    }

    // ------------------------------------------------------------- escaping

    public function testOutputIsEscapedByDefault(): void
    {
        $this->assertSame('&lt;b&gt;&amp;&quot;&#039;', $this->render('{$x}', ['x' => '<b>&"\'']));
    }

    public function testAQuotedTextWithAModifierIsAnOutputTag(): void
    {
        $this->assertSame('A&lt;B', $this->render("{'a<b'|upper}"));
        $this->assertSame('A&lt;B', $this->render('{"a<b"|upper}'));
        $this->assertSame('it&#039;s', $this->render("{'it\\'s'|lower}"));
    }

    public function testABraceBeforeAQuoteWithoutAModifierStaysText(): void
    {
        $js = "<script>var o = {'a': 1, \"b\": '|'}; f({'k': 2});</script>";
        $this->assertSame($js, $this->render($js));
    }

    public function testRawAndNofilterSkipEscaping(): void
    {
        $this->assertSame('<b><b>', $this->render('{$x|raw}{$x|nofilter}', ['x' => '<b>']));
    }

    public function testExplicitEscapeIsNotAppliedTwice(): void
    {
        $this->assertSame('&lt;a&gt;', $this->render('{$x|escape}', ['x' => '<a>']));
    }

    public function testAutoEscapeCanBeSwitchedOffForOldTemplates(): void
    {
        $this->assertSame('<b>', $this->render('{$x}', ['x' => '<b>'], false));
        $this->assertSame('&lt;b&gt;', $this->render('{$x|escape}', ['x' => '<b>'], false));
    }

    public function testModifiersThatProduceHtmlOrAreSafeAreNotEscapedAgain(): void
    {
        $this->assertSame("a<br>\nb", $this->render('{$x|nl2br}', ['x' => "a\nb"]));
        $this->assertSame('a%20b%26', $this->render('{$x|url}', ['x' => 'a b&']));
    }

    public function testOtherModifiersStillGetEscaped(): void
    {
        $this->assertSame('&lt;A&gt;', $this->render('{$x|upper}', ['x' => '<a>']));
    }

    #[DataProvider('oddValues')]
    public function testOddValuesNeverWarn($value, string $expected): void
    {
        $this->assertSame($expected, $this->render('[{$x}]', ['x' => $value]));
    }

    public static function oddValues(): array
    {
        return [
            'null'     => [null, '[]'],
            'false'    => [false, '[]'],
            'true'     => [true, '[1]'],
            'int'      => [0, '[0]'],
            'float'    => [1.5, '[1.5]'],
            'array'    => [[1, 2], '[]'],
            'object'   => [new \stdClass(), '[]'],
            'stringable' => [new class { public function __toString(): string { return '<x>'; } }, '[&lt;x&gt;]'],
        ];
    }

    // --------------------------------------------------------------- access

    public function testMissingThingsAreEmptyNotWarnings(): void
    {
        $this->assertSame('[][][][][]', $this->render('[{$nope}][{$a.b}][{$o->p}][{$o->m()}][{$a.b.c.d}]', ['a' => [], 'o' => null]));
    }

    public function testPathsAndMethods(): void
    {
        $o = new class {
            public $title = 'T';
            public function hello($name, $n = 1) { return "hi $name $n"; }
        };
        $this->assertSame(
            'T|hi Bob 2|v|v|2',
            $this->render("{\$o->title}|{\$o->hello('Bob', 2)}|{\$a.k}|{\$a['k']}|{\$a.list.1}", ['o' => $o, 'a' => ['k' => 'v', 'list' => [1, 2]]])
        );
    }

    public function testMagicGetObjectsAreRead(): void
    {
        $o = new class { public function __get($n) { return 'magic-' . $n; } };
        $this->assertSame('magic-foo', $this->render('{$o->foo}', ['o' => $o]));
    }

    public function testPrivatePropertiesAreNotReadable(): void
    {
        $o = new class { private $secret = 'hidden'; public $open = 'ok'; };
        $this->assertSame('[][ok]', $this->render('[{$o->secret}][{$o->open}]', ['o' => $o]));
    }

    public function testThrowingMagicGetIsContained(): void
    {
        $o = new class { public function __get($n) { throw new \RuntimeException('no'); } };
        $this->assertSame('[]', $this->render('[{$o->x}]', ['o' => $o]));
    }

    // ----------------------------------------------------------- expressions

    #[DataProvider('expressions')]
    public function testExpressions(string $expr, string $expected): void
    {
        $this->assertSame($expected, $this->render('{if ' . $expr . '}Y{else}N{/if}', ['a' => 5, 'b' => 3, 's' => 'x', 'z' => 0, 'n' => null, 'arr' => ['k' => 1]]));
    }

    public static function expressions(): array
    {
        return [
            ['$a > $b', 'Y'], ['$a < $b', 'N'], ['$a >= 5', 'Y'], ['$a <= 4', 'N'],
            ['$a == 5', 'Y'], ['$a === 5', 'Y'], ['$a !== 5', 'N'], ['$a != 6', 'Y'],
            ['$a > 1 && $b > 1', 'Y'], ['$a > 9 || $b > 1', 'Y'], ['$a > 9 or $b > 9', 'N'], ['$a > 1 and $b > 1', 'Y'],
            ['!$z', 'Y'], ['not $a', 'N'], ['!($a > $b)', 'N'],
            ['($a + $b) * 2 === 16', 'Y'], ['$a - $b === 2', 'Y'], ['$a / 0 === 0', 'Y'], ['$a % 0 === 0', 'Y'], ['-$a === -5', 'Y'],
            ["\$s ~ 'y' === 'xy'", 'Y'], ["\$a ~ \$b === '53'", 'Y'],
            ['$n === null', 'Y'], ['$missing', 'N'], ['true', 'Y'], ['false', 'N'], ['null', 'N'],
            ['$arr.k === 1', 'Y'], ["\$arr['k'] === 1", 'Y'],
            ["'a\\'b' === \"a'b\"", 'Y'],
            ['1.5 + 1.5 == 3', 'Y'],
        ];
    }

    #[DataProvider('modifiers')]
    public function testModifiers(string $template, array $vars, string $expected): void
    {
        $this->assertSame($expected, $this->render($template, $vars));
    }

    public static function modifiers(): array
    {
        return [
            'upper' => ['{$x|upper}', ['x' => 'čau'], 'ČAU'],
            'lower' => ['{$x|lower}', ['x' => 'ČAU'], 'čau'],
            'trim' => ['[{$x|trim}]', ['x' => '  a '], '[a]'],
            'default empty' => ["{\$x|default:'none'}", ['x' => ''], 'none'],
            'default set' => ["{\$x|default:'none'}", ['x' => 'v'], 'v'],
            'default var' => ['{$x|default:$d}', ['x' => null, 'd' => 'D'], 'D'],
            'truncate' => ['{$x|truncate:5}', ['x' => 'abcdefghij'], 'abcde...'],
            'truncate custom' => ["{\$x|truncate:3:'~'}", ['x' => 'abcdef'], 'abc~'],
            'truncate short' => ['{$x|truncate:50}', ['x' => 'abc'], 'abc'],
            'length string' => ['{$x|length}', ['x' => 'čau'], '3'],
            'length array' => ['{$x|count}', ['x' => [1, 2, 3]], '3'],
            'date' => ["{\$x|date:'Y-m-d'}", ['x' => 86400 * 365], '1971-01-01'],
            'join' => ["{\$x|join:' / '}", ['x' => ['a', 'b']], 'a / b'],
            'chain' => ['{$x|trim|upper}', ['x' => ' a '], 'A'],
        ];
    }

    public function testJsonIsSafeInsideHtmlAndScripts(): void
    {
        $value = array('t' => '</script><b onload="x">&\'', 'n' => 3);

        $out = $this->render('<script>var d = {$v|json};</script>', array('v' => $value));

        preg_match('/var d = (.*);<\/script>$/', $out, $m);
        $this->assertSame(1, preg_match('/^[^<>&]*$/', $m[1]), 'no raw HTML characters in the JSON');
        $this->assertSame(1, substr_count($out, '</script>'), 'the value cannot close the script element');
        $this->assertSame($value, json_decode($m[1], true), 'and it is still the same data');
    }

    // ------------------------------------------------------------ structure

    public function testIfElseifElse(): void
    {
        $t = '{if $n === 1}one{elseif $n === 2}two{else}many{/if}';
        $this->assertSame('one', $this->render($t, ['n' => 1]));
        $this->assertSame('two', $this->render($t, ['n' => 2]));
        $this->assertSame('many', $this->render($t, ['n' => 3]));
    }

    public function testForeachWithKeyAndElse(): void
    {
        $t = '{foreach $items as $k => $v}{$k}={$v};{foreachelse}none{/foreach}';
        $this->assertSame('a=1;b=2;', $this->render($t, ['items' => ['a' => 1, 'b' => 2]]));
        $this->assertSame('none', $this->render($t, ['items' => []]));
        $this->assertSame('none', $this->render($t, ['items' => null]));
        $this->assertSame('none', $this->render($t, []));
    }

    public function testForeachWithoutElseAndNesting(): void
    {
        $t = '{foreach $rows as $row}[{foreach $row as $c}{$c}{/foreach}]{/foreach}';
        $this->assertSame('[12][34]', $this->render($t, ['rows' => [[1, 2], [3, 4]]]));
    }

    public function testForeachOverObjectsWithMethods(): void
    {
        $item = new class { public $name = 'n'; public function active() { return 'on'; } };
        $this->assertSame('n:on|', $this->render('{foreach $m as $i}{$i->name}:{$i->active()}|{/foreach}', ['m' => [$item]]));
    }

    public function testForeachOverGeneratorsAndIterators(): void
    {
        $this->assertSame('123', $this->render('{foreach $g as $v}{$v}{/foreach}', ['g' => new \ArrayIterator([1, 2, 3])]));
    }

    public function testSet(): void
    {
        $this->assertSame('42-43', $this->render('{set $n = 42}{$n}-{set $m = $n + 1}{$m}'));
    }

    public function testComments(): void
    {
        $this->assertSame('ab', $this->render('a{* a comment' . "\n" . 'over two lines {$x} *}b'));
    }

    public function testLiteralAndPlainBraces(): void
    {
        $this->assertSame('{$x} {/if}', $this->render('{literal}{$x} {/if}{/literal}', ['x' => 1]));
        $css = "<style>\n  body { color: red; }\n  .a{margin:0}\n</style><script>if (a) { b(); }</script>";
        $this->assertSame($css, $this->render($css));
    }

    public function testNewlinesAfterTagsArePreserved(): void
    {
        $this->assertSame("a\n\nb\n", $this->render("{\$x}\n\n{\$y}\n", ['x' => 'a', 'y' => 'b']) . "");
        $this->assertSame("<p>\nx\n</p>", $this->render("<p>\n{\$x}\n</p>", ['x' => 'x']));
    }

    public function testInclude(): void
    {
        $this->write('part.tpl', '[{$name}]');
        $this->assertSame('a[X]b', $this->render("a{include 'part.tpl'}b", ['name' => 'X']));
    }

    public function testIncludeCannotChangeTheCallersVariables(): void
    {
        $this->write('part.tpl', '{set $name = "changed"}');
        $this->assertSame('X', $this->render("{include 'part.tpl'}{\$name}", ['name' => 'X']));
    }

    public function testRecursiveIncludeStops(): void
    {
        $this->write('loop.tpl', "{include 'loop.tpl'}");
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('too deep');
        $this->engine()->fetch('loop.tpl');
    }

    // ------------------------------------------------------------- security

    public function testPhpInTemplateTextIsNotExecuted(): void
    {
        $source = "<?php \$GLOBALS['wb_pwned'] = true; ?>x<?= 'y' ?> <script language=\"php\">1</script>";
        $out = $this->render($source);

        $this->assertFalse($GLOBALS['wb_pwned']);
        $this->assertSame($source, $out, 'PHP tags in a template are plain text');
    }

    public function testPhpAndTemplateSyntaxInDataIsNotInterpreted(): void
    {
        $evil = "<?php \$GLOBALS['wb_pwned'] = true; ?>{\$secret}{if 1}x{/if}";
        $out = $this->render('{$x|raw}', ['x' => $evil, 'secret' => 'S']);

        $this->assertFalse($GLOBALS['wb_pwned']);
        $this->assertSame($evil, $out);
    }

    #[DataProvider('injectionAttempts')]
    public function testExpressionsCannotRunPhp(string $template): void
    {
        try {
            $this->render($template, ['x' => 'v']);
        } catch (TemplateException $e) {
            // Rejected by the parser: fine.
        }
        $this->assertFalse($GLOBALS['wb_pwned']);
    }

    public static function injectionAttempts(): array
    {
        return [
            'function call'      => ['{if \\Wbengine\\Tests\\Unit\\wb_pwn()}x{/if}'],
            'plain call'         => ['{if wb_pwn()}x{/if}'],
            'call via modifier'  => ['{$x|wb_pwn}'],
            'string breakout'    => ["{\$x|default:'\\'.wb_pwn().\\''}"],
            'quote breakout'     => ['{$x|default:"\\".wb_pwn().\\""}'],
            'assignment'         => ['{$x = wb_pwn()}'],
            'backtick'           => ['{$x|default:`id`}'],
            'dollar dollar'      => ['{$$x}'],
            'static call'        => ['{if Foo::bar()}x{/if}'],
            'new'                => ['{if new Foo}x{/if}'],
            'closure'            => ['{if function(){}}x{/if}'],
            'semicolon'          => ['{if 1; wb_pwn()}x{/if}'],
            'comma'              => ['{$x, wb_pwn()}'],
            'brace in string'    => ["{\$x|default:'}<?php wb_pwn(); ?>{'}"],
            'close tag in expr'  => ['{$x|default:"?> <?php wb_pwn(); ?>"}'],
        ];
    }

    public function testStringLiteralWithPhpIsJustText(): void
    {
        $this->assertSame("a&#039;b ?&gt; &lt;?php x", $this->render("{\$x|default:'a\\'b ?> <?php x'}"));
        $this->assertFalse($GLOBALS['wb_pwned']);
    }

    public function testBracesAroundAQuotedStringArePlainText(): void
    {
        $text = "{'a ?> <?php wb_pwn(); ?>'}";

        $this->assertSame($text, $this->render($text));
        $this->assertFalse($GLOBALS['wb_pwned']);
    }

    #[DataProvider('badTemplates')]
    public function testSyntaxErrorsAreReported(string $template, string $message): void
    {
        try {
            $this->render($template);
            $this->fail('Expected a TemplateException');
        } catch (TemplateException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public static function badTemplates(): array
    {
        return [
            'unclosed if'        => ['{if 1}x', 'never closed'],
            'unclosed foreach'   => ['{foreach $a as $b}x', 'never closed'],
            'stray endif'        => ['x{/if}', 'without a matching'],
            'mismatched close'   => ['{if 1}{foreach $a as $b}{/if}{/foreach}', 'without a matching'],
            'else outside'       => ['{else}', 'outside'],
            'elseif after else'  => ['{if 1}{else}{elseif 2}{/if}', 'after {else}'],
            'two else'           => ['{if 1}{else}{else}{/if}', 'after {else}'],
            'unknown modifier'   => ['{$x|nonsense}', 'unknown modifier'],
            'unknown word'       => ['{if foo}x{/if}', 'unknown word'],
            'bad character'      => ['{$x;}', 'unexpected character'],
            'unterminated str'   => ["{\$x|default:'abc}", 'never closed'],
            'bad foreach'        => ['{foreach $a}x{/foreach}', 'as $item'],
            'bad set'            => ['{set 5}', 'needs'],
            'dangling operator'  => ['{if 1 +}x{/if}', 'ends too early'],
            'unclosed comment'   => ['{* never', 'never closed'],
            'unclosed literal'   => ['{literal}x', 'never closed'],
        ];
    }

    public function testErrorsNameTheLine(): void
    {
        try {
            $this->render("a\nb\nc\n{if 1}\n{\$x|nonsense}\n{/if}");
            $this->fail('Expected a TemplateException');
        } catch (TemplateException $e) {
            $this->assertStringContainsString('line 5', $e->getMessage());
        }
    }

    // -------------------------------------------------------------- engine

    public function testNamesCannotEscapeTheTemplateDirectory(): void
    {
        file_put_contents($this->dir . '/outside/secret.tpl', 'TOP SECRET');
        $e = $this->engine();

        foreach (['../outside/secret.tpl', $this->dir . '/outside/secret.tpl', '/etc/passwd', "t.tpl\0.x", ''] as $name) {
            try {
                $e->fetch($name);
                $this->fail("Rendered " . json_encode($name));
            } catch (TemplateException $ex) {
                $this->assertTrue(true);
            }
        }
    }

    public function testSymlinkOutOfTheRootIsRefused(): void
    {
        file_put_contents($this->dir . '/outside/secret.tpl', 'TOP SECRET');
        symlink($this->dir . '/outside/secret.tpl', $this->dir . '/tpl/link.tpl');

        $this->expectException(TemplateException::class);
        $this->engine()->fetch('link.tpl');
    }

    public function testExtraRootsAreAllowedByAbsolutePathOnly(): void
    {
        file_put_contents($this->dir . '/outside/bundled.tpl', 'bundled {$v}');
        $engine = $this->engine()->addRoot($this->dir . '/outside')->assign('v', 1);

        $this->assertSame('bundled 1', $engine->fetch($this->dir . '/outside/bundled.tpl'));
    }

    public function testMissingTemplate(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('not found');
        $this->engine()->fetch('nope.tpl');
    }

    public function testCompiledFileIsReusedAndRecompiledWhenTheSourceChanges(): void
    {
        $this->write('t.tpl', 'one {$x}');
        $engine = $this->engine()->assign('x', 1);
        $this->assertSame('one 1', $engine->fetch('t.tpl'));
        $this->assertCount(1, glob($this->dir . '/compiled/*.php'));

        sleep(1);
        $this->write('t.tpl', 'two {$x}');
        $this->assertSame('two 1', $engine->fetch('t.tpl'));
        $this->assertCount(1, glob($this->dir . '/compiled/*.php'), 'same file, rewritten');
    }

    public function testWithoutCompileCheckTheCompiledFileWins(): void
    {
        $this->write('t.tpl', 'one');
        $engine = $this->engine()->setCompileCheck(false);
        $this->assertSame('one', $engine->fetch('t.tpl'));

        sleep(1);
        $this->write('t.tpl', 'two');
        $this->assertSame('one', $engine->fetch('t.tpl'), 'production mode does not look at the source');

        $engine->setCompileCheck(true);
        $this->assertSame('two', $engine->fetch('t.tpl'));
    }

    public function testEscapingModeIsPartOfTheCompiledFileIdentity(): void
    {
        $this->write('t.tpl', '{$x}');
        $this->assertSame('&lt;', $this->engine(true)->assign('x', '<')->fetch('t.tpl'));
        $this->assertSame('<', $this->engine(false)->assign('x', '<')->fetch('t.tpl'));
    }

    public function testAnExceptionInsideATemplateDoesNotLeakOutputBuffers(): void
    {
        $level = ob_get_level();
        $o = new class { public function boom() { throw new \RuntimeException('boom'); } };

        try {
            $this->render('before{$o->boom()}after', ['o' => $o]);
            $this->fail('Expected the exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }
        $this->assertSame($level, ob_get_level());
    }

    public function testOverridingVariablesPerFetch(): void
    {
        $this->write('t.tpl', '{$x}');
        $engine = $this->engine()->assign('x', 'global');

        $this->assertSame('local', $engine->fetch('t.tpl', ['x' => 'local']));
        $this->assertSame('global', $engine->fetch('t.tpl'));
    }

    // ------------------------------------------------------ fragment cache

    public function testFragmentCacheRendersOnce(): void
    {
        Cache::setDriver(new FileCache($this->dir . '/cache'), 60);
        $o = new class { public int $calls = 0; public function expensive() { return 'E' . ++$this->calls; } };
        $this->write('t.tpl', "{cache 'frag' ttl=60}[{\$o->expensive()}]{/cache}");
        $engine = $this->engine()->assign('o', $o);

        $this->assertSame('[E1]', $engine->fetch('t.tpl'));
        $this->assertSame('[E1]', $engine->fetch('t.tpl'));
        $this->assertSame(1, $o->calls);
    }

    public function testFragmentsAreSeparatedByLocale(): void
    {
        Cache::setDriver(new FileCache($this->dir . '/cache'), 60);
        $settings = ['enabled' => true, 'default' => 'en', 'locales' => ['en', 'cs']];
        try {
            \Wbengine\I18n::configure((new \Wbengine\I18n\Translator([], 'en'))->setLocale('cs'), $settings);
            $this->assertSame('Cesky', $this->render("{cache 'sidebar'}{\$text}{/cache}", ['text' => 'Cesky']));
            \Wbengine\I18n::configure((new \Wbengine\I18n\Translator([], 'en'))->setLocale('en'), $settings);
            $this->assertSame('English', $this->render("{cache 'sidebar'}{\$text}{/cache}", ['text' => 'English']));
            \Wbengine\I18n::configure((new \Wbengine\I18n\Translator([], 'en'))->setLocale('cs'), $settings);
            $this->assertSame('Cesky', $this->render("{cache 'sidebar'}{\$text}{/cache}", ['text' => 'changed']));
        } finally {
            \Wbengine\I18n::configure(null, null);
        }
    }

    public function testFragmentCacheKeyCanDependOnVariables(): void
    {
        Cache::setDriver(new FileCache($this->dir . '/cache'), 60);
        $this->write('t.tpl', "{cache 'item-' ~ \$id}{\$name}{/cache}");
        $engine = $this->engine();

        $this->assertSame('A', $engine->fetch('t.tpl', ['id' => 1, 'name' => 'A']));
        $this->assertSame('B', $engine->fetch('t.tpl', ['id' => 2, 'name' => 'B']));
        $this->assertSame('A', $engine->fetch('t.tpl', ['id' => 1, 'name' => 'changed']));
    }

    public function testFragmentIsRenderedEveryTimeWithoutACache(): void
    {
        $o = new class { public int $calls = 0; public function n() { return ++$this->calls; } };
        $this->write('t.tpl', "{cache 'k'}{\$o->n()}{/cache}");
        $engine = $this->engine()->assign('o', $o);

        $this->assertSame('1', $engine->fetch('t.tpl'));
        $this->assertSame('2', $engine->fetch('t.tpl'));
    }

    public function testFragmentsNestAndKeepControlStructures(): void
    {
        Cache::setDriver(new FileCache($this->dir . '/cache'), 60);
        $this->write('t.tpl', "{cache 'list'}{foreach \$i as \$v}{if \$v > 1}<{\$v}>{/if}{/foreach}{/cache}");

        $this->assertSame('<2><3>', $this->engine()->fetch('t.tpl', ['i' => [1, 2, 3]]));
        $this->assertSame('<2><3>', $this->engine()->fetch('t.tpl', ['i' => [9]]), 'served from the cache');
    }
}
