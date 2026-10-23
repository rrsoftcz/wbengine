<!-- START STORY BOX -->
<div class="story">
    {if $Article->title}
        <h1>Wbeigine {$Article->title|escape}</h1>
    {/if}
    {if $Article->author}
        <p><i>Author:</i> <b><a href="#">{$Article->author|escape}</a></b>, <i>Source:</i> <a href="{$Article->source|escape}"> {$Article->source|escape}</a></p>
    {/if}
    <!-- story box content start -->
    {* Trusted HTML: formatted by the framework (Texy) from content written by administrators. *}
    {$Article->introtext|raw}
    {$Article->bodytext|raw}
    <!-- story box content end -->
    <p>&nbsp;</p>
</div>
<!-- END STORY BOX -->