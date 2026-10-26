<!-- START STORY BOX -->
<div class="story">
    {if $Article->title}
        <h1>{$Article->title|escape}</h1>
    {/if}
    {if $Article->author}
        <p><i>{$t.author|escape}</i> <b><a href="#">{$Article->author|escape}</a></b>, <i>{$t.source|escape}</i> <a href="{$Article->source|escape}"> {$Article->source|escape}</a></p>
    {/if}
    <!-- story box content start -->
    {* Trusted HTML: safe Markdown renderer output or unchanged trusted legacy HTML from administrators. *}
    {$Article->introtext|raw}
    {$Article->bodytext|raw}
    <!-- story box content end -->
    <p>&nbsp;</p>
</div>
<!-- END STORY BOX -->