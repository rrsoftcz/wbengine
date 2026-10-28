<!-- START STORY BOX -->
<div class="story">
    {if $Article->title}
        <h1>{$Article->title|escape}</h1>
    {/if}
    {if $Article->byline}
        <p class="story-byline">{if $Article->author}<i>{$t.author|escape}</i> <b>{$Article->author|escape}</b>{/if}{if $Article->source_text}{if $Article->author}, {/if}<i>{$t.source|escape}</i> {if $Article->source_href}<a href="{$Article->source_href|escape}"{if $Article->source_external} rel="noopener noreferrer"{/if}>{$Article->source_text|escape}</a>{else}{$Article->source_text|escape}{/if}{/if}</p>
    {/if}
    <!-- story box content start -->
    {* Trusted HTML: safe Markdown renderer output or unchanged trusted legacy HTML from administrators. *}
    {$Article->introtext|raw}
    {$Article->bodytext|raw}
    <!-- story box content end -->
    <p>&nbsp;</p>
</div>
<!-- END STORY BOX -->