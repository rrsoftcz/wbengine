<div class="my-4 md:my-16 flex justify-center">
    <div class="mt-16 mx-2 md:mt-24 p-8 border border-gray-300 bg-white rounded-lg w-full md:w-2/4">
        <h1>Login form</h1>
        <form class="block" method="post" action="/login/" autocomplete="on" id="_loginform">
            <div class="border border-gray-200 rounded-lg p-8 mb-4">
                <div class="flex flex-col py-2">
                    <label for="uname1">Username</label>
                    <input type="text" class="border border-blue-200 rounded p-2" name="userName" id="userName"
                        placeholder="user@email.com" value="{$userName}">
                </div>
                <div class="flex flex-col py-2">
                    <label>Password</label>
                    <input name="userPassword" type="password" class="border border-blue-200 rounded p-2" id="userPassword" autocomplete="on"
                        placeholder="Passwort" value="{$userPassword}">
                </div>
                <div class="chk form-check large">
                </div>
                <div id="alert" class="alert-danger {if $error}show{/if}collapse" role="alert">{$error}</div>
                {if $captcha}
                    <div class="g-recaptcha" data-callback="captchaProc"
                        data-sitekey="6LeDc0UUAAAAAMeern62PQ8Hp4rhrXlYLh9Xt5h3"></div>
                {else}
                    <div id="ajax-g-recaptcha"></div>
                {/if}

            </div>
            <button type="submit" class="btn btn-primary btn-md" id="submit">Login</button>
            <span id="spinner" class="spinner fa"></span>
            <input type="hidden" name="token" value="{$token}" id="token">
            <input type="hidden" name="cResponse" value="" id="cResponse">
        </form>
    </div>
</div>