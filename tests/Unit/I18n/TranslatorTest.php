<?php

namespace Wbengine\Tests\Unit\I18n;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\I18n\Translator;

class TranslatorTest extends TestCase
{
    private string $dir;
    private string $dir2;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/wb-i18n-' . bin2hex(random_bytes(4));
        $this->dir = $base . '/a';
        $this->dir2 = $base . '/b';
        mkdir($this->dir, 0777, true);
        mkdir($this->dir2, 0777, true);
        $this->catalog($this->dir, 'en', [
            'hello' => 'Hello', 'greet' => 'Hello %name%', 'only.en' => 'English only',
            'files' => ['one' => '%count% file', 'other' => '%count% files'],
            'price' => 'Price: 100 %', 'framework' => 'framework text',
        ]);
        $this->catalog($this->dir, 'cs', [
            'hello' => 'Ahoj', 'greet' => 'Ahoj %name%',
            'files' => ['one' => '%count% soubor', 'few' => '%count% soubory', 'many' => '%count% souboru', 'other' => '%count% souborů'],
        ]);
        $this->catalog($this->dir, 'pt', ['hello' => 'Olá', 'greet' => 'Olá %name%']);
        $this->catalog($this->dir, 'pt_BR', ['hello' => 'Oi']);
        $this->catalog($this->dir2, 'en', ['framework' => 'application text', 'app.only' => 'App']);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dir, $this->dir2] as $d) {
            foreach (glob($d . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($d);
            @rmdir(dirname($d));
        }
    }

    private function catalog(string $dir, string $locale, array $messages): void
    {
        file_put_contents("$dir/$locale.php", '<?php return ' . var_export($messages, true) . ';');
    }

    private function translator(string $locale = 'en'): Translator
    {
        return (new Translator([$this->dir], 'en'))->setLocale($locale);
    }

    public function testTranslatesInTheCurrentLocale(): void
    {
        $this->assertSame('Hello', $this->translator('en')->translate('hello'));
        $this->assertSame('Ahoj', $this->translator('cs')->translate('hello'));
    }

    public function testParameters(): void
    {
        $t = $this->translator('cs');

        $this->assertSame('Ahoj Eva', $t->translate('greet', ['name' => 'Eva']));
        $this->assertSame('Ahoj %name%', $t->translate('greet'), 'a missing parameter stays visible');
        $this->assertSame('Ahoj 5', $t->translate('greet', ['name' => 5]));
        $this->assertSame('Ahoj ', $t->translate('greet', ['name' => null]));
        $this->assertSame('Ahoj %name%', $t->translate('greet', ['name' => ['array']]), 'non-scalars are ignored');
    }

    public function testParametersAreNotReinterpreted(): void
    {
        $t = $this->translator('en');

        $this->assertSame('Hello %other% 100%', $t->translate('greet', ['name' => '%other% 100%']));
        $this->assertSame('Price: 100 %', $t->translate('price', ['name' => 'x']), 'a lone percent sign is left alone');
    }

    public function testPluralForms(): void
    {
        $cs = $this->translator('cs');
        $this->assertSame('1 soubor', $cs->translate('files', [], 1));
        $this->assertSame('3 soubory', $cs->translate('files', [], 3));
        $this->assertSame('5 souborů', $cs->translate('files', [], 5));
        $this->assertSame('0 souborů', $cs->translate('files', [], 0));
        $this->assertSame('1.5 souboru', $cs->translate('files', [], 1.5));

        $en = $this->translator('en');
        $this->assertSame('1 file', $en->translate('files', [], 1));
        $this->assertSame('2 files', $en->translate('files', [], 2));
    }

    public function testCountIsAvailableAsAParameterOfPlainMessages(): void
    {
        $this->catalog($this->dir, 'en', ['items' => 'You have %count% items']);

        $this->assertSame('You have 7 items', $this->translator('en')->translate('items', [], 7));
    }

    public function testFallbackChain(): void
    {
        $t = $this->translator('pt_BR');

        $this->assertSame('Oi', $t->translate('hello'), 'the exact locale');
        $this->assertSame('Olá Ana', $t->translate('greet', ['name' => 'Ana']), 'then the language');
        $this->assertSame('English only', $t->translate('only.en'), 'then the default locale');
    }

    public function testAMissingKeyReturnsTheKeyAndIsRecorded(): void
    {
        $t = $this->translator('cs');

        $this->assertSame('no.such.key', $t->translate('no.such.key'));
        $this->assertSame('no.such.key', $t->translate('no.such.key', ['a' => 1]));
        $this->assertSame(['cs:no.such.key'], $t->getMissing());
    }

    public function testAnUnknownLocaleFallsBackToTheDefault(): void
    {
        $this->assertSame('Hello', $this->translator('de')->translate('hello'));
    }

    public function testLaterDirectoriesOverrideEarlierOnes(): void
    {
        $t = (new Translator([$this->dir, $this->dir2], 'en'))->setLocale('en');

        $this->assertSame('application text', $t->translate('framework'));
        $this->assertSame('App', $t->translate('app.only'));
        $this->assertSame('Hello', $t->translate('hello'), 'keys that are not overridden still come from the first');
    }

    public function testHasAndTranslatingIntoAnotherLocale(): void
    {
        $t = $this->translator('en');

        $this->assertTrue($t->has('hello'));
        $this->assertFalse($t->has('nope'));
        $this->assertSame('Ahoj', $t->translate('hello', [], null, 'cs'));
        $this->assertSame('en', $t->getLocale(), 'the current locale did not change');
    }

    #[DataProvider('normalizations')]
    public function testNormalize($input, ?string $expected): void
    {
        $this->assertSame($expected, Translator::normalize($input));
    }

    public static function normalizations(): array
    {
        return [
            'language'        => ['cs', 'cs'], 'upper'            => ['CS', 'cs'],
            'with region'     => ['cs_CZ', 'cs_CZ'], 'dash'       => ['cs-cz', 'cs_CZ'],
            'mixed'           => ['pT-bR', 'pt_BR'], 'spaces'     => [' en ', 'en'],
            'three letters'   => ['fil', 'fil'],
            'traversal'       => ['../etc/passwd', null], 'slash' => ['cs/x', null],
            'null byte'       => ["cs\0", null], 'long'           => ['english', null],
            'empty'           => ['', null], 'null'               => [null, null], 'array' => [['cs'], null],
            'number'          => ['12', null], 'script'           => ['<s>', null],
        ];
    }

    public function testHostileLocalesCannotChooseAFile(): void
    {
        $t = $this->translator('en');

        $t->setLocale('../../etc/passwd');
        $this->assertSame('en', $t->getLocale(), 'rejected, the locale did not change');
        $this->assertSame('Hello', $t->translate('hello', [], null, '../../x'));
    }

    public function testLanguageOfALocale(): void
    {
        $this->assertSame('pt', $this->translator('pt_BR')->getLanguage());
        $this->assertSame('cs', $this->translator('cs')->getLanguage());
    }

    public function testAPlainTextIsNotEscapedHere(): void
    {
        $this->catalog($this->dir, 'en', ['html' => '<b>%name%</b>']);

        $this->assertSame('<b><i></b>', $this->translator('en')->translate('html', ['name' => '<i>']), 'escaping is the template engine\'s job');
    }
}
