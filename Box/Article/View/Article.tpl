<!-- START STORY BOX -->
<div class="story">
    {if $Article->title}
        <h1>Wbeigine {$Article->title}</h1>
    {/if}
    {if $Article->author}
        <p><i>Author:</i> <b><a href="#">{$Article->author}</a></b>, <i>Source:</i> <a href="{$Article->source}"> {$Article->source}</a></p>
    {/if}
    <!-- story box content start -->
    {$Article->introtext}
    {$Article->bodytext}
    <!-- story box content end -->
    <p>&nbsp;</p>
</div>
<!-- END STORY BOX -->