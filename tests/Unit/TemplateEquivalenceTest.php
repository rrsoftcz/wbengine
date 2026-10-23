<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Renderer\Adapter\NativeRenderer;
use Wbengine\Renderer\Adapter\SmartyRenderer;

/**
 * Templates written in the common subset must render exactly the same with the native
 * engine (escaping off, as for old templates) and with Smarty, so an application can
 * switch the renderer in the settings.
 */
class TemplateEquivalenceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wb-eq-' . bin2hex(random_bytes(4));
        foreach (['tpl', 'smarty', 'native'] as $d) {
            mkdir($this->dir . '/' . $d, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($this->dir . '/*') ?: [] as $d) {
            @rmdir($d);
        }
        @rmdir($this->dir);
    }

    private static function vars(): array
    {
        $menu = new class {
            public $link; public $name;
            public function __construct($link = '/', $name = 'Home') { $this->link = $link; $this->name = $name; }
            public function active() { return $this->link === '/' ? 'active' : ''; }
        };
        $about = clone $menu;
        $about->link = '/about/';
        $about->name = 'About';

        return [
            'title' => 'Hello',
            'html' => '<b>bold</b> & "q"',
            'n' => 3,
            'flag' => true,
            'empty' => '',
            'list' => ['a', 'b', 'c'],
            'map' => ['x' => 1, 'y' => 2],
            'none' => [],
            'user' => ['name' => 'Ann', 'tags' => ['t1', 't2'], 'address' => ['city' => 'Brno']],
            'menu' => [$menu, $about],
            'meta' => ['title' => 'Page', 'desc' => 'D'],
            'obj' => (object) ['title' => 'T', 'nested' => (object) ['v' => 'deep']],
        ];
    }

    private function smarty(string $template): string
    {
        file_put_contents($this->dir . '/tpl/t.tpl', $template);
        $r = new SmartyRenderer();
        $r->setTemplateDir($this->dir . '/tpl');
        $r->setCompileDir($this->dir . '/smarty');
        foreach (self::vars() as $k => $v) {
            $r->assign($k, $v);
        }
        return $r->fetch('t.tpl');
    }

    private function native(string $template): string
    {
        file_put_contents($this->dir . '/tpl/t.tpl', $template);
        $r = new NativeRenderer();
        $r->setTemplateDir($this->dir . '/tpl');
        $r->setCompileDir($this->dir . '/native');
        $r->setAutoEscape(false);
        foreach (self::vars() as $k => $v) {
            $r->assign($k, $v);
        }
        return $r->fetch('t.tpl');
    }

    #[DataProvider('corpus')]
    public function testSameOutput(string $template): void
    {
        $this->assertSame($this->smarty($template), $this->native($template));
    }

    public static function corpus(): array
    {
        $templates = [
            'plain text'              => 'Just text, no tags. { not a tag } and 100% done',
            'variable'                => '<h1>{$title}</h1>',
            'raw html variable'       => '<p>{$html}</p>',
            'escape modifier'         => '<p>{$html|escape}</p>',
            'upper lower'             => '{$title|upper} {$title|lower}',
            'default empty'           => "[{\$empty|default:'none'}] [{\$title|default:'none'}]",
            'array dot'               => '{$meta.title}/{$meta.desc}',
            'nested dot'              => '{$user.address.city}',
            'array in array'          => '{$user.tags.1}',
            'object property'         => '{$obj->title}',
            'nested object'           => '{$obj->nested->v}',
            // (a missing array key makes Smarty raise a PHP warning, the native engine gives empty)
            'missing variable'        => '[{$nothing}]',
            'number'                  => '{$n}',
            'if true'                 => '{if $flag}yes{/if}',
            'if else'                 => '{if $empty}a{else}b{/if}',
            'if elseif else'          => '{if $n == 1}one{elseif $n == 3}three{else}other{/if}',
            'comparison'              => '{if $n > 2 && $n < 5}in{else}out{/if}',
            'not'                     => '{if !$empty}set{/if}',
            'strict'                  => '{if $n === 3}exact{/if}',
            'logical or'              => '{if $empty || $flag}ok{/if}',
            'method call'             => '{foreach $menu as $m}{$m->name}:{$m->active()};{/foreach}',
            'foreach'                 => '{foreach $list as $i}<li>{$i}</li>{/foreach}',
            'foreach key'             => '{foreach $map as $k => $v}{$k}={$v},{/foreach}',
            'foreachelse'             => '{foreach $none as $i}x{foreachelse}empty{/foreach}',
            'nested foreach'          => '{foreach $list as $a}{foreach $map as $b}{$a}{$b} {/foreach}{/foreach}',
            'if in foreach'           => '{foreach $list as $i}{if $i == "b"}[{$i}]{else}{$i}{/if}{/foreach}',
            'menu markup'             => '<ul>{foreach $menu as $m}<li><a class="{$m->active()}" href="{$m->link}">{$m->name}</a></li>{/foreach}</ul>',
            'comment'                 => 'a{* hidden *}b',
            'literal'                 => '{literal}{$title}{/literal}',
            'css braces'              => '<style>a { color: red; }</style>',
            'multiline'               => "<div>\n  {\$title}\n</div>\n",
            'blank lines'             => "a\n\n{\$title}\n\nb",
            'condition spacing'       => "{if \$flag}\nyes\n{/if}\n",
            'else on own lines'       => "{if \$empty}\nA\n{else}\nB\n{/if}\nend",
            'elseif on own lines'     => "{if \$n == 1}\n1\n{elseif \$n == 3}\n3\n{/if}\n",
            'foreach on own lines'    => "<ul>\n{foreach \$list as \$i}\n<li>{\$i}</li>\n{/foreach}\n</ul>\n",
            'foreachelse own lines'   => "{foreach \$none as \$i}\nx\n{foreachelse}\nnone\n{/foreach}\n!",
            'blank line after tag'    => "{if \$flag}\n\nafter blank\n{/if}",
            'comment own line'        => "a\n{* note *}\nb\n",
            'output then newline'     => "{\$title}\n{\$title}\n",
            'two tags one line'       => "{if \$flag}{\$title}{/if}\nnext",
            'indented tags'           => "<ul>\n    {foreach \$list as \$i}\n    <li>{\$i}</li>\n    {/foreach}\n</ul>",
            'user object as in site'  => '{if $user && $user.name === "Ann"}hi {$user.name}{else}anon{/if}',
        ];
        return array_map(fn ($t) => [$t], $templates);
    }
}
