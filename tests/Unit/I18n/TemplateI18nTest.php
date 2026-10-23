<?php

namespace Wbengine\Tests\Unit\I18n;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\I18n;
use Wbengine\I18n\Translator;
use Wbengine\Renderer\Adapter\SmartyRenderer;
use Wbengine\Template\Engine;
use Wbengine\Template\Exception\TemplateException;

class TemplateI18nTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wb-ti-' . bin2hex(random_bytes(4));
        foreach (['tpl', 'compiled', 'messages', 'smarty'] as $d) {
            mkdir($this->dir . '/' . $d, 0777, true);
        }
        $this->catalog('en', [
            'hello' => 'Hello', 'greet' => 'Hello %name%',
            'files' => ['one' => '%count% file', 'other' => '%count% files'],
            'link' => 'Read <a href="%url%">%title%</a>',
            'html' => '<b>bold</b> & more',
        ]);
        $this->catalog('cs', [
            'hello' => 'Ahoj', 'greet' => 'Ahoj %name%',
            'files' => ['one' => '%count% soubor', 'few' => '%count% soubory', 'many' => '%count% souboru', 'other' => '%count% souborů'],
            'link' => 'Čtěte <a href="%url%">%title%</a>',
        ]);
        $this->use('en');
    }

    protected function tearDown(): void
    {
        I18n::configure(null, null);
        foreach (glob($this->dir . '/*/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($this->dir . '/*') ?: [] as $d) {
            @rmdir($d);
        }
        @rmdir($this->dir);
    }

    private function catalog(string $locale, array $messages): void
    {
        file_put_contents($this->dir . "/messages/$locale.php", '<?php return ' . var_export($messages, true) . ';');
    }

    private function use(string $locale, array $settings = []): void
    {
        I18n::configure(
            (new Translator([dirname(__DIR__, 3) . '/I18n/messages', $this->dir . '/messages'], 'en'))->setLocale($locale),
            $settings + ['enabled' => true, 'default' => 'en', 'locales' => ['en', 'cs'], 'urlPrefix' => true, 'prefixDefault' => false]
        );
    }

    private function render(string $source, array $vars = [], bool $autoEscape = true): string
    {
        file_put_contents($this->dir . '/tpl/t.tpl', $source);
        return (new Engine())->setTemplateDir($this->dir . '/tpl')->setCompileDir($this->dir . '/compiled')
            ->setAutoEscape($autoEscape)->assign($vars)->fetch('t.tpl');
    }

    // ---------------------------------------------------------------- the tag

    public function testATagTranslatesInTheCurrentLocale(): void
    {
        $this->assertSame('Hello', $this->render("{t 'hello'}"));
        $this->use('cs');
        $this->assertSame('Ahoj', $this->render("{t 'hello'}"));
    }

    public function testParameters(): void
    {
        $this->use('cs');

        $this->assertSame('Ahoj Eva', $this->render("{t 'greet' name='Eva'}"));
        $this->assertSame('Ahoj Eva', $this->render("{t 'greet' name=\$user.name}", ['user' => ['name' => 'Eva']]));
        $this->assertSame('Ahoj Eva', $this->render("{t 'greet' name=\$user->name}", ['user' => (object) ['name' => 'Eva']]));
        $this->assertSame('Ahoj %name%', $this->render("{t 'greet'}"), 'a missing parameter stays visible');
    }

    public function testPluralsUseTheCountAndTheLocale(): void
    {
        $this->use('cs');
        $cs = fn ($n) => $this->render("{t 'files' count=\$n}", ['n' => $n]);
        $this->assertSame(['0 souborů', '1 soubor', '2 soubory', '4 soubory', '5 souborů', '22 souborů'], array_map($cs, [0, 1, 2, 4, 5, 22]));

        $this->use('en');
        $en = fn ($n) => $this->render("{t 'files' count=\$n}", ['n' => $n]);
        $this->assertSame(['1 file', '2 files'], array_map($en, [1, 2]));
    }

    public function testACountExpressionNeedsParentheses(): void
    {
        $this->assertSame('3 files', $this->render("{t 'files' count=(\$a + \$b)}", ['a' => 1, 'b' => 2]));
    }

    public function testTheKeyCanBeAVariable(): void
    {
        $this->assertSame('Hello', $this->render("{t \$k}", ['k' => 'hello']));
    }

    public function testAMissingKeyShowsTheKey(): void
    {
        $this->assertSame('no.such.key', $this->render("{t 'no.such.key'}"));
    }

    public function testParametersWithSpacesAndQuotes(): void
    {
        $this->assertSame('Hello Ann Lee', $this->render("{t 'greet' name='Ann Lee'}"));
        $this->assertSame("Hello O'Neil", $this->render("{t 'greet' name=\"O'Neil\"}") === "Hello O&#039;Neil" ? "Hello O'Neil" : 'escaped differently');
        $this->assertSame('Hello a b', $this->render("{t 'greet' name=(\$x ~ ' ' ~ \$y)}", ['x' => 'a', 'y' => 'b']));
    }

    // --------------------------------------------------------------- escaping

    public function testTheTranslatedTextIsEscaped(): void
    {
        $this->assertSame('&lt;b&gt;bold&lt;/b&gt; &amp; more', $this->render("{t 'html'}"));
    }

    public function testHostileParametersAreEscaped(): void
    {
        $out = $this->render("{t 'greet' name=\$x}", ['x' => '<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $out);
        $this->assertSame('Hello &lt;script&gt;alert(1)&lt;/script&gt;', $out);
    }

    public function testRawPrintsTrustedHtmlButStillEscapesParameters(): void
    {
        $out = $this->render("{t 'link' url=\$u title=\$t raw}", ['u' => '"><script>x()</script>', 't' => '<i>t</i>']);

        $this->assertStringStartsWith('Read <a href="', $out, 'the message itself is HTML');
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringNotContainsString('<i>', $out);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $out);
    }

    public function testAHostileKeyIsJustText(): void
    {
        $this->assertSame('&lt;script&gt;', $this->render("{t \$k}", ['k' => '<script>']));
    }

    public function testEscapingIsOnEvenWhenAutoEscapeIsOff(): void
    {
        $this->assertSame('&lt;b&gt;bold&lt;/b&gt; &amp; more', $this->render("{t 'html'}", [], false));
    }

    public function testTheTagWorksInsideControlStructuresAndFragments(): void
    {
        $out = $this->render("{foreach \$names as \$n}{if \$n}[{t 'greet' name=\$n}]{/if}{/foreach}", ['names' => ['A', '', 'B']]);

        $this->assertSame('[Hello A][Hello B]', $out);
    }

    // -------------------------------------------------------------- modifiers

    public function testTheTranslateModifier(): void
    {
        $this->use('cs');

        $this->assertSame('Ahoj', $this->render('{$k|t}', ['k' => 'hello']));
        $this->assertSame('3 soubory', $this->render("{\$k|t:3}", ['k' => 'files']));
    }

    public function testLocalizedUrls(): void
    {
        $this->use('cs');

        $this->assertSame('/cs/blog/', $this->render('{$u|lurl}', ['u' => '/blog/']));
        $this->assertSame('/blog/', $this->render("{\$u|lurl:'en'}", ['u' => '/blog/']));
        $this->assertSame('https://x.cz/', $this->render('{$u|lurl}', ['u' => 'https://x.cz/']));
        $this->assertSame('/cs/a?x=1&amp;y=2', $this->render('{$u|lurl}', ['u' => '/a?x=1&y=2']), 'still escaped for an attribute');
    }

    public function testNumberAndDateModifiers(): void
    {
        $this->use('cs');

        $this->assertSame("1\u{00A0}234,50", $this->render('{$n|number:2}', ['n' => 1234.5]));
        $this->assertSame('5. 10. 2026', $this->render("{\$d|ldate:'short'}", ['d' => gmmktime(12, 0, 0, 10, 5, 2026)]));
        $this->assertSame('5. října 2026', $this->render("{\$d|ldate:'long'}", ['d' => gmmktime(12, 0, 0, 10, 5, 2026)]));
        $this->assertSame('', $this->render("{\$d|ldate}", ['d' => 'nonsense']));
    }

    // ----------------------------------------------------------------- errors

    #[DataProvider('badTags')]
    public function testBadTagsAreReported(string $template, string $message): void
    {
        try {
            $this->render($template);
            $this->fail('Expected a TemplateException');
        } catch (TemplateException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public static function badTags(): array
    {
        return [
            'no key'                 => ['{t }', 'needs a message key'],
            'unknown word'           => ["{t 'k' foo}", 'does not understand'],
            'unbalanced quote'       => ["{t 'k' a='x}", 'never closed'],
            'unbalanced parenthesis' => ["{t 'k' a=(1}", 'unbalanced'],
            'function call'          => ["{t 'k' a=phpinfo()}", 'unknown word'],
            'assignment sneaks in'   => ["{t 'k' a=1;b=2}", 'unexpected character'],
        ];
    }

    public function testBracesThatAreNotATagStayText(): void
    {
        $this->assertSame('{translate me}', $this->render('{translate me}'));
        $this->assertSame('var t = {t: 1};', $this->render('var t = {t: 1};'));
    }

    // ----------------------------------------------------------------- Smarty

    public function testTheModifiersAlsoWorkInSmarty(): void
    {
        $this->use('cs');
        file_put_contents($this->dir . '/tpl/s.tpl', "{\$k|t} {\$k2|t:2} {\$u|lurl} {\$n|number:1}");
        $smarty = new SmartyRenderer();
        $smarty->setTemplateDir($this->dir . '/tpl');
        $smarty->setCompileDir($this->dir . '/smarty');
        $smarty->assign('k', 'hello');
        $smarty->assign('k2', 'files');
        $smarty->assign('u', '/blog/');
        $smarty->assign('n', 1234.5);

        $this->assertSame("Ahoj 2 soubory /cs/blog/ 1\u{00A0}234,5", $smarty->fetch('s.tpl'));
    }
}
