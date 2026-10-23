<div class="my-4 md:my-16 flex justify-center">
    <div class="mt-16 mx-2 md:mt-24 p-8 border border-gray-300 bg-white rounded-lg w-full md:w-2/4">
        <h1>{$t.title|escape}</h1>
        <form class="block" method="post" action="{$formAction|default:'/login/'|escape}" autocomplete="on" id="_loginform">
            <div class="border border-gray-200 rounded-lg p-8 mb-4">
                <div class="flex flex-col py-2">
                    <label for="userName">{$t.username|escape}</label>
                    <input type="text" class="border border-blue-200 rounded p-2" name="userName" id="userName"
                        placeholder="user@email.com" value="{$userName|escape}">
                </div>
                <div class="flex flex-col py-2">
                    <label for="userPassword">{$t.password|escape}</label>
                    <input name="userPassword" type="password" class="border border-blue-200 rounded p-2" id="userPassword" autocomplete="on"
                        placeholder="Passwort">
                </div>
                <div class="chk form-check large">
                </div>
                <div id="alert" class="alert-danger {if $error}show{/if}collapse" role="alert">{$error|escape}</div>
                {if $captcha}
                    <div class="g-recaptcha" data-callback="captchaProc"
                        data-sitekey="6LeDc0UUAAAAAMeern62PQ8Hp4rhrXlYLh9Xt5h3"></div>
                {else}
                    <div id="ajax-g-recaptcha"></div>
                {/if}

            </div>
            <button type="submit" class="btn btn-primary btn-md" id="submit">{$t.submit|escape}</button>
            <span id="spinner" class="spinner fa"></span>
            <input type="hidden" name="token" value="{$token|escape}" id="token">
            <input type="hidden" name="cResponse" value="" id="cResponse">
        </form>
    </div>
</div>