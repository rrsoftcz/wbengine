<div class="login-box">
    <div class="login">
        <h1>Login form</h1>
        <form class="form" method="post" action="/login/" autocomplete="on" id="_loginform">
            <div class="form-group has-danger">
                <label for="uname1">Username</label>
                <input type="text" class="form-control form-control-danger" name="userName" id="userName" placeholder="user@email.com" value="{$login.userName}" >
            </div>
            <div class="form-group has-danger">
                <label>Password</label>
                <input name="userPassword" type="password" class="form-control" id="userPassword" autocomplete="on" placeholder="Passwort" value="{$login.userPassword}">
            </div>
            <div class="chk form-check large">
            </div>
            <div id="alert" class="alert-danger {if $error}show{/if}collapse" role="alert">{$error}</div>
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
    </div>
</div>




