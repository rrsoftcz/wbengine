<div class="my-4 md:my-16 flex justify-center">
    <div class="mt-16 mx-2 md:mt-24 p-8 border border-gray-300 bg-white rounded-lg w-full md:w-2/4">
        <h1>{$t.title|escape}</h1>
        {if $done}
            <p role="status">{$t.done|escape}</p>
            <p><a href="{$loginUrl|escape}">{$t.login|escape}</a></p>
        {elseif !$valid}
            <p role="alert">{$t.invalid|escape}</p>
        {else}
            <p>{$t.intro|escape}</p>
            <form class="block" method="post" action="{$formAction|escape}" autocomplete="off">
                <div class="border border-gray-200 rounded-lg p-8 mb-4">
                    <div class="flex flex-col py-2">
                        <label for="newPassword">{$t.password|escape}</label>
                        <input name="newPassword" type="password" class="border border-blue-200 rounded p-2" id="newPassword" autocomplete="new-password" minlength="{$minLength|escape}" maxlength="256" required>
                    </div>
                    <div class="flex flex-col py-2">
                        <label for="confirmPassword">{$t.confirm|escape}</label>
                        <input name="confirmPassword" type="password" class="border border-blue-200 rounded p-2" id="confirmPassword" autocomplete="new-password" minlength="{$minLength|escape}" maxlength="256" required>
                    </div>
                    <p class="hint">{$t.hint|escape}</p>
                    {if $error}<div class="alert-danger" role="alert">{$error|escape}</div>{/if}
                </div>
                <button type="submit" class="btn btn-primary btn-md">{$t.submit|escape}</button>
                <input type="hidden" name="reset" value="{$resetToken|escape}">
                <input type="hidden" name="token" value="{$token|escape}">
            </form>
        {/if}
    </div>
</div>
