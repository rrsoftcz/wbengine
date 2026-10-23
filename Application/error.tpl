<div class="jumbotron alert alert-danger">
    <h1 class="display-3">{$t.title|escape}</h1>
    <p class="lead">{$error.title|escape} {$error.code|escape}</p>
    <hr class="my-4">
    <h4>{$t.info|escape}</h4>
    <ul>
        <li>{$error.msg|escape}</li>
    </ul>
    <p>&ensp;</p>
</div>