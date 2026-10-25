<form method="post" action="{$logoutAction|escape}">
    <input type="hidden" name="a" value="logout">
    <input type="hidden" name="token" value="{$token|escape}">
    <button type="submit">{$logoutLabel|escape}</button>
</form>
