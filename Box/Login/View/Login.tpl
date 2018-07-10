<div class="py-5">
    <div class="row">
        <div class="col-md-12">
            <div class="row">
                <div class="col-md-4 mx-auto">
                    <span class="anchor" id="formLogin"></span>
                    <!-- form card login -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="mb-0">Wbengine Login</h3>
                        </div>
                        <div class="card-body">
                            {if !$crit}
                                <form class="form" method="post" action="/login/" autocomplete="on" id="_loginform">
                                    <div class="form-group has-danger">
                                        <label for="uname1">Benutzername</label>
                                        <input type="text" class="form-control form-control-danger" name="userName" id="userName" placeholder="user@email.com" value="{$login.userName}" >
                                    </div>
                                    <div class="form-group has-danger">
                                        <label>Passwort</label>
                                        <input name="userPassword" type="password" class="form-control" id="userPassword" autocomplete="on" placeholder="Passwort" value="{$login.userPassword}">
                                    </div>
                                    <div class="chk form-check large">
                                    </div>
                                    <div id="alert" class="alert alert-danger {if $error}show{/if}collapse" role="alert">{$error}</div>
                                    {if $captcha}
                                        <div class="g-recaptcha" data-callback="captchaProc" data-sitekey="6LeDc0UUAAAAAMeern62PQ8Hp4rhrXlYLh9Xt5h3"></div>
                                    {else}
                                        <div id="ajax-g-recaptcha"></div>
                                    {/if}
                                    <br />
                                    <button type="submit" class="btn btn-primary btn-md float-right" id="submit">Login</button>
                                    <span id="spinner" class="spinner fa"></span>
                                    <input type="hidden" name="token" value="{$token}" id="token">
                                    <input type="hidden" name="cResponse" value="" id="cResponse">
                                </form>
                                <noscript>
                                    <style type="text/css">
                                        .form {
                                            display:none;
                                        }
                                    </style>
                                    <div class="alert alert-warning">
                                        <p>Looks like You don't have javascript enabled or your browser does not support it.</p>
                                        <p>Unfortunately this is not allowed.</p>
                                    </div>
                                </noscript>
                            {else}
                                <div id="alert" class="alert alert-danger" role="alert">{$crit}</div>
                            {/if}
                        </div>
                        <!--/card-block-->
                    </div>
                    <!-- /form card login -->
                </div>
            </div>
            <!--/row-->
        </div>
        <!--/col-->
    </div>
    <!--/row-->
</div>