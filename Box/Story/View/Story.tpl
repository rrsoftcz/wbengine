<!-- START STORY BOX -->
<div class="story">
    {if $Story->title}
        <h1>Wbeigine {$Story->title}</h1>
    {/if}
    {if $Story->author}
        <p><i>Author:</i> <b><a href="#">{$Story->author}</a></b>, <i>Source:</i> <a href="{$Story->source}"> {$Story->source}</a></p>
    {/if}
    <!-- story box content start -->
    {$Story->introtext}
    {$Story->bodytext}
    <!-- story box content end -->
    <p>&nbsp;</p>
</div>
<!-- END STORY BOX -->