<style>
.wb-integrations .wb-scopes{margin:1rem 0;padding:.75rem 1rem;border:1px solid #cbd5e1;border-radius:.5rem}
.wb-integrations .wb-scopes summary{cursor:pointer;font-weight:600}
.wb-integrations .wb-scopes ul{margin:.75rem 0 0;padding-left:1.25rem}
.wb-integrations .wb-panel{margin:1.5rem 0;padding:1.25rem 1.5rem;border:1px solid #cbd5e1;border-radius:.5rem;background:#f8fafc}
.wb-integrations .wb-panel .wb-fields{display:flex;flex-direction:column;gap:.35rem}
.wb-integrations .wb-panel .wb-fields input,.wb-integrations .wb-panel .wb-fields select{margin-bottom:.75rem}
.wb-integrations .wb-panel label{font-weight:600}
.wb-integrations .wb-hint{display:block;margin:-.25rem 0 .75rem;color:#475569;font-size:.875rem;line-height:1.4}
</style>
<section class="wb-integrations mx-auto mt-10 max-w-3xl px-4" data-wb-integrations>
    <div class="card p-8">
        <h1 class="mb-2 mt-0 text-3xl">{$view.t.title}</h1>
        <p class="py-0">{$view.t.intro}</p>

        {if $view.error}
        <p class="mt-4 rounded border border-red-300 bg-red-50 p-3 text-red-800" role="alert">{$view.error}</p>
        {/if}
        {if $view.notice}
        <p class="mt-4 rounded border border-green-300 bg-green-50 p-3 text-green-800" role="status">{$view.notice}</p>
        {/if}

        {if $view.issued}
        <h2 class="mt-8">{$view.t.issued_title}</h2>
        <p class="rounded border border-amber-300 bg-amber-50 p-3 text-amber-900" role="alert">{$view.t.issued_warning}</p>
        <h3>{$view.t.instr_title}</h3>
        <ol>
            <li>{$view.t.instr_1}</li>
            <li>{$view.t.instr_2}</li>
        </ol>
        <div class="flex flex-col gap-3">
            <label for="wb-site-url">{$view.t.instr_site}</label>
            <div class="flex gap-2">
                <input type="text" readonly class="w-full rounded border border-slate-300 p-2 font-mono" id="wb-site-url" value="{$view.issued.address}">
                <button type="button" class="btn-outline" data-wb-copy="wb-site-url" data-done="{$view.t.copied}" data-fail="{$view.t.copy_failed}">{$view.t.copy}</button>
            </div>
            <label for="wb-site-name">{$view.t.instr_name}</label>
            <div class="flex gap-2">
                <input type="text" readonly class="w-full rounded border border-slate-300 p-2 font-mono" id="wb-site-name" value="{$view.issued.name}">
                <button type="button" class="btn-outline" data-wb-copy="wb-site-name" data-done="{$view.t.copied}" data-fail="{$view.t.copy_failed}">{$view.t.copy}</button>
            </div>
            <label for="wb-key">{$view.t.instr_key}</label>
            <div class="flex gap-2">
                <input type="text" readonly autocomplete="off" spellcheck="false" class="w-full rounded border border-slate-300 p-2 font-mono" id="wb-key" value="{$view.issued.key}">
                <button type="button" class="btn-primary" data-wb-copy="wb-key" data-done="{$view.t.copied}" data-fail="{$view.t.copy_failed}">{$view.t.copy}</button>
            </div>
        </div>
        <p>{$view.t.instr_3}</p>
        {if $view.issued.replaces}
        <p class="rounded border border-slate-300 bg-slate-50 p-3">{$view.issued.replaces}</p>
        {/if}
        <details class="wb-scopes">
            <summary>{$view.t.scopes_title} ({$view.scopeCount})</summary>
            <ul>
                {foreach $view.scopes as $scope}
                <li>{$scope}</li>
                {/foreach}
            </ul>
        </details>
        {/if}

        {if $view.problems}
        <h2 class="mt-8">{$view.t.problem_title}</h2>
        <p>{$view.t.problem_help}</p>
        <ul>
            {foreach $view.problems as $problem}
            <li>{$problem}</li>
            {/foreach}
        </ul>
        {/if}

        {if $view.canIssue}
        <h2 class="mt-8">{$view.t.connect_title}</h2>
        <p>{$view.t.connect_help}</p>
        <p><strong>{$view.t.address_label}:</strong> <code>{$view.address}</code></p>
        <details class="wb-scopes">
            <summary>{$view.t.scopes_title} ({$view.scopeCount})</summary>
            <ul>
                {foreach $view.scopes as $scope}
                <li>{$scope}</li>
                {/foreach}
            </ul>
        </details>
        <form class="wb-panel" method="post" action="{$view.action}" autocomplete="off">
            <input type="hidden" name="action" value="issue">
            <input type="hidden" name="token" value="{$view.tokens.issue}">
            <div class="wb-fields">
                <label for="wb-name">{$view.t.name_label}</label>
                <input type="text" class="rounded border border-slate-300 p-2" id="wb-name" name="name" maxlength="128" required value="{$view.defaultName}" aria-describedby="wb-name-help">
                <small class="wb-hint" id="wb-name-help">{$view.t.name_help}</small>
                {if $view.rotatable}
                <label for="wb-replaces">{$view.t.replaces_label}</label>
                <select class="rounded border border-slate-300 p-2" id="wb-replaces" name="replaces">
                    <option value="">{$view.t.replaces_none}</option>
                    {foreach $view.rotatable as $option}
                    <option value="{$option.id}">{$option.label}</option>
                    {/foreach}
                </select>
                {/if}
                <label for="wb-password">{$view.t.password_label}</label>
                <input type="password" class="rounded border border-slate-300 p-2" id="wb-password" name="password" autocomplete="current-password" required>
                <small class="wb-hint">{$view.t.password_help}</small>
            </div>
            <p><button type="submit" class="btn-primary">{$view.t.issue_submit}</button></p>
        </form>

        <h3>{$view.t.rotation_title}</h3>
        <ol>
            <li>{$view.t.rotation_1}</li>
            <li>{$view.t.rotation_2}</li>
            <li>{$view.t.rotation_3}</li>
        </ol>
        {/if}

        {if $view.showList}
        <h2 class="mt-8">{$view.t.list_title}</h2>
        {if $view.integrations}
        <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr>
                    <th>{$view.t.col_name}</th>
                    <th>{$view.t.col_status}</th>
                    <th>{$view.t.col_created}</th>
                    <th>{$view.t.col_by}</th>
                    <th>{$view.t.col_last_used}</th>
                    <th>{$view.t.col_scopes}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                {foreach $view.integrations as $row}
                <tr>
                    <td>{$row.name}</td>
                    <td>{$row.status}</td>
                    <td>{$row.created}</td>
                    <td>{$row.by}</td>
                    <td>{$row.last_used}</td>
                    <td>{$row.scope_count}</td>
                    <td>
                        {if $row.active}
                        {if $view.canIssue}
                        <form method="post" action="{$view.action}" autocomplete="off">
                            <input type="hidden" name="action" value="revoke">
                            <input type="hidden" name="token" value="{$view.tokens.revoke}">
                            <input type="hidden" name="integration_id" value="{$row.id}">
                            <input type="password" class="rounded border border-slate-300 p-1" name="password" autocomplete="current-password" required placeholder="{$view.t.revoke_password}" aria-label="{$view.t.revoke_password}">
                            <button type="submit" class="btn-outline">{$view.t.revoke_submit}</button>
                        </form>
                        {/if}
                        {/if}
                    </td>
                </tr>
                {/foreach}
            </tbody>
        </table>
        </div>
        {else}
        <p>{$view.t.list_empty}</p>
        {/if}
        {/if}
    </div>
</section>
{if $view.issued}
<script>
(function () {
    var buttons = document.querySelectorAll('[data-wb-copy]');
    Array.prototype.forEach.call(buttons, function (button) {
        button.addEventListener('click', function () {
            var field = document.getElementById(button.getAttribute('data-wb-copy'));
            var done = function () { button.textContent = button.getAttribute('data-done'); };
            var fail = function () { field.select(); button.textContent = button.getAttribute('data-fail'); };
            field.select();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(field.value).then(done, fail);
            } else {
                try { document.execCommand('copy') ? done() : fail(); } catch (e) { fail(); }
            }
        });
    });
})();
</script>
{/if}
