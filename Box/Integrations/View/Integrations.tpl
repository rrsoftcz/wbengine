<style>
.wb-integrations .wb-scopes{margin:1rem 0;padding:.75rem 1rem;border:1px solid #cbd5e1;border-radius:.5rem}
.wb-integrations .wb-scopes summary{cursor:pointer;font-weight:600}
.wb-integrations .wb-scopes ul{margin:.75rem 0 0;padding-left:1.25rem}
.wb-integrations .wb-panel{margin:1.5rem 0;padding:1.25rem 1.5rem;border:1px solid #cbd5e1;border-radius:.5rem;background:#f8fafc}
.wb-integrations .wb-panel .wb-fields{display:flex;flex-direction:column;gap:.35rem}
.wb-integrations .wb-panel .wb-fields input,.wb-integrations .wb-panel .wb-fields select{margin-bottom:.75rem}
.wb-integrations .wb-panel label{font-weight:600}
.wb-integrations .wb-check{display:flex;align-items:center;gap:.5rem;font-weight:600}
.wb-integrations .wb-check input{margin:0}
.wb-integrations .wb-badge{display:inline-block;margin-left:.35rem;padding:.05rem .45rem;border-radius:.75rem;background:#e0f2fe;color:#075985;font-size:.75rem;font-weight:600}
.wb-integrations .wb-off{margin:0 0 .75rem;padding:.6rem .85rem;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;color:#475569;font-size:.875rem;line-height:1.4}
.wb-integrations .wb-off ul{margin:.4rem 0 0;padding-left:1.25rem}
.wb-integrations .wb-hint{display:block;margin:-.25rem 0 .75rem;color:#475569;font-size:.875rem;line-height:1.4}
.wb-integrations .wb-keys{list-style:none;margin:.5rem 0 0;padding:0;display:flex;flex-direction:column;gap:.75rem}
.wb-integrations .wb-key{padding:.85rem 1rem;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff}
.wb-integrations .wb-key-head{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem}
.wb-integrations .wb-id{color:#64748b;font-size:.8rem}
.wb-integrations .wb-key-meta{display:flex;flex-wrap:wrap;gap:.25rem 1.5rem;margin:.5rem 0 0}
.wb-integrations .wb-key-meta div{display:flex;gap:.4rem}
.wb-integrations .wb-key-meta dt{color:#64748b}
.wb-integrations .wb-key-meta dd{margin:0}
.wb-integrations .wb-badge-warn{background:#fef3c7;color:#92400e}
.wb-integrations .wb-revoke{margin-top:.6rem}
.wb-integrations .wb-revoke summary{cursor:pointer;color:#475569;font-size:.875rem}
.wb-integrations .wb-revoke form{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.5rem}
.wb-integrations .wb-groups{margin:0 0 .75rem;padding:0;border:0;min-width:0}
.wb-integrations .wb-groups legend{font-weight:600;padding:0}
.wb-integrations .wb-matrix{display:flex;flex-direction:column;gap:.4rem;margin-top:.5rem}
.wb-integrations .wb-mhead,.wb-integrations .wb-mrow{display:grid;grid-template-columns:minmax(0,1fr) repeat(3,5.75rem);align-items:center;gap:.25rem}
.wb-integrations .wb-mhead{padding:0 .75rem 0 1rem;color:#475569;font-size:.8125rem;font-weight:600;text-align:center}
.wb-integrations .wb-presets{margin:.5rem 0 .75rem}.wb-integrations .wb-presets[hidden]{display:none}.wb-integrations .wb-preset-buttons{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.35rem}
.wb-integrations .wb-mrow{--m-bg:#f1f5f9;--m-line:#94a3b8;--m-ink:#334155;padding:.55rem .75rem .55rem 1rem;border:1px solid var(--m-line);border-left-width:6px;border-radius:.6rem;background:var(--m-bg);transition:background .15s,border-color .15s}
.wb-integrations .wb-mrow[data-level="off"],.wb-integrations .wb-mrow:has(input[value="off"]:checked){--m-bg:#fdecee;--m-line:#e4a0aa;--m-ink:#9b2b3d}
.wb-integrations .wb-mrow[data-level="read"],.wb-integrations .wb-mrow:has(input[value="read"]:checked){--m-bg:#eaf3ff;--m-line:#8fb8ec;--m-ink:#1d4f91}
.wb-integrations .wb-mrow[data-level="write"],.wb-integrations .wb-mrow:has(input[value="write"]:checked){--m-bg:#eaf7e6;--m-line:#94cc80;--m-ink:#2d6a1c}
.wb-integrations .wb-mrow:has(input:disabled){--m-bg:#f1f5f9;--m-line:#cbd5e1;--m-ink:#64748b;opacity:.75}
.wb-integrations .wb-mlabel{display:flex;flex-direction:column;gap:.15rem;min-width:0}
.wb-integrations .wb-mlabel strong{color:var(--m-ink)}
.wb-integrations .wb-mlabel small{color:#475569;font-size:.8125rem;line-height:1.35}
.wb-integrations .wb-mcell{display:flex;flex-direction:column;align-items:center;gap:.15rem;cursor:pointer;color:#475569;font-size:.75rem}
.wb-integrations .wb-mtext{display:none}
.wb-integrations .wb-mempty{cursor:default}
.wb-integrations .wb-mcell input{appearance:none;-webkit-appearance:none;width:1.4rem;height:1.4rem;margin:0;border:2px solid var(--m-line);border-radius:50%;background:#fff;cursor:pointer;transition:box-shadow .12s,background .12s}
.wb-integrations .wb-mcell input:checked{background:var(--m-ink);border-color:var(--m-ink);box-shadow:inset 0 0 0 4px #fff}
.wb-integrations .wb-mcell input:hover:not(:disabled){box-shadow:0 0 0 3px rgba(255,255,255,.9),0 0 0 5px var(--m-line)}
.wb-integrations .wb-mcell input:checked:hover:not(:disabled){box-shadow:inset 0 0 0 4px #fff,0 0 0 3px rgba(255,255,255,.9),0 0 0 5px var(--m-line)}
.wb-integrations .wb-mcell input:focus-visible{outline:2px solid var(--m-ink);outline-offset:3px}
.wb-integrations .wb-mcell input:disabled{cursor:not-allowed}
@media (max-width:40rem){
.wb-integrations .wb-mhead{display:none}
.wb-integrations .wb-mrow{grid-template-columns:repeat(3,1fr)}
.wb-integrations .wb-mlabel{grid-column:1 / -1;margin-bottom:.35rem}
.wb-integrations .wb-mtext{display:block}
}
.wb-integrations .wb-badge.wb-lv-read{background:#eaf3ff;color:#1d4f91}
.wb-integrations .wb-badge.wb-lv-write{background:#eaf7e6;color:#2d6a1c}
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
        <h3>{$view.t.mcp_title}</h3>
        <p>{$view.t.mcp_help}</p>
        <div class="wb-fields">
            <label for="wb-mcp-json">{$view.t.mcp_json}</label>
            <textarea readonly rows="14" spellcheck="false" class="w-full rounded border border-slate-300 p-2 font-mono" id="wb-mcp-json">{$view.issued.mcp.json}</textarea>
            <div><button type="button" class="btn-outline" data-wb-copy="wb-mcp-json" data-done="{$view.t.copied}" data-fail="{$view.t.copy_failed}">{$view.t.copy}</button></div>
            <label for="wb-mcp-command">{$view.t.mcp_command}</label>
            <textarea readonly rows="3" spellcheck="false" class="w-full rounded border border-slate-300 p-2 font-mono" id="wb-mcp-command">{$view.issued.mcp.command}</textarea>
            <div><button type="button" class="btn-outline" data-wb-copy="wb-mcp-command" data-done="{$view.t.copied}" data-fail="{$view.t.copy_failed}">{$view.t.copy}</button></div>
        </div>
        <p><small class="wb-hint">{$view.t.mcp_note}</small></p>
        <p><small class="wb-hint">{$view.t.mcp_platforms}</small></p>
        <details class="wb-scopes">
            <summary>{$view.t.scopes_title} ({$view.issued.scope_count})</summary>
            <ul>
                {foreach $view.issued.scopes as $scope}
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
                    <option value="{$option.id}" data-analytics="{$option.analytics}" data-groups="{$option.groups}" data-ips="{$option.ips}">{$option.label}</option>
                    {/foreach}
                </select>
                {/if}
                <fieldset class="wb-groups">
                    <legend>{$view.t.groups_title}</legend>
                    <small class="wb-hint">{$view.t.groups_help}</small>
                    <div class="wb-presets" data-wb-presets hidden>
                        <strong>{$view.t.presets_title}</strong>
                        <small class="wb-hint">{$view.t.presets_help}</small>
                        <div class="wb-preset-buttons">
                            {foreach $view.presets as $preset}
                            <button type="button" class="btn-outline" data-wb-preset="{$preset.id}" data-name="{$preset.name}" data-levels="{$preset.levels}" title="{$preset.help}">{$preset.label}</button>
                            {/foreach}
                        </div>
                    </div>
                    <div class="wb-matrix" data-wb-matrix>
                        <div class="wb-mhead" aria-hidden="true"><span></span><span>{$view.t.col_none}</span><span>{$view.t.col_read}</span><span>{$view.t.col_write}</span></div>
                        {foreach $view.groupForm as $group}
                        <div class="wb-mrow" role="radiogroup" aria-labelledby="wb-group-{$group.name}" aria-describedby="wb-group-help-{$group.name}">
                            <div class="wb-mlabel"><strong id="wb-group-{$group.name}">{$group.label}</strong><small id="wb-group-help-{$group.name}">{$group.help}</small></div>
                            {foreach $group.cells as $cell}
                            {if $cell.present}
                            <label class="wb-mcell"><input type="radio" name="group_{$group.name}" value="{$cell.value}"{if $cell.selected} checked{/if}{if $cell.disabled} disabled{/if}><span class="wb-mtext">{$cell.label}</span></label>
                            {else}
                            <span class="wb-mcell wb-mempty" aria-hidden="true"></span>
                            {/if}
                            {/foreach}
                        </div>
                        {/foreach}
                    </div>
                    {if $view.analytics.off}
                    <div class="wb-off" role="note">
                        {$view.t.analytics_off}
                        <ul>
                            {foreach $view.analytics.problems as $reason}
                            <li>{$reason}</li>
                            {/foreach}
                        </ul>
                        <a href="{$view.analytics.docs}" rel="noopener noreferrer">{$view.t.analytics_docs}</a>
                    </div>
                    {/if}
                </fieldset>
                {if $view.ipSupport}
                <label for="wb-ips">{$view.t.ips_label}</label>
                <input type="text" class="rounded border border-slate-300 p-2" id="wb-ips" name="allowed_ips" maxlength="600" autocomplete="off" spellcheck="false" placeholder="203.0.113.5, 198.51.100.0/24" aria-describedby="wb-ips-help">
                <small class="wb-hint" id="wb-ips-help">{$view.t.ips_help} {$view.t.ips_your}: <code>{$view.yourAddress}</code></small>
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
        {if $view.activeKeys}
        <ul class="wb-keys">
            {foreach $view.activeKeys as $row}
            <li class="wb-key">
                <div class="wb-key-head">
                    <strong>{$row.name}</strong> <span class="wb-id">#{$row.id}</span>
                    {if $row.has_analytics}<span class="wb-badge">{$view.t.analytics_badge}</span>{/if}
                </div>
                <dl class="wb-key-meta">
                    <div><dt>{$view.t.col_created}</dt><dd>{$row.created}, {$row.by}</dd></div>
                    <div><dt>{$view.t.col_last_used}</dt><dd>{$row.last_used}</dd></div>
                    {if $view.ipSupport}
                    <div><dt>{$view.t.col_allowed}</dt><dd>{if $row.restricted}<code>{$row.allowed_text}</code>{else}{$view.t.ips_any}{/if}</dd></div>
                    <div><dt>{$view.t.col_last_ip}</dt><dd>{if $row.last_ip}<code>{$row.last_ip}</code>{else}{$view.t.ips_never}{/if}</dd></div>
                    {/if}
                    <div><dt>{$view.t.col_scopes}</dt><dd>{foreach $row.groups as $g}<span class="wb-badge wb-lv-{$g.level}">{$g.label}</span> {/foreach}</dd></div>
                </dl>
                {if $view.canIssue}
                {if $view.ipSupport}
                <details class="wb-revoke">
                    <summary>{$view.t.restrict_open}</summary>
                    <form method="post" action="{$view.action}" autocomplete="off">
                        <input type="hidden" name="action" value="restrict">
                        <input type="hidden" name="token" value="{$view.tokens.restrict}">
                        <input type="hidden" name="integration_id" value="{$row.id}">
                        <input type="text" class="rounded border border-slate-300 p-1" name="allowed_ips" maxlength="600" value="{$row.ips_prefill}" placeholder="203.0.113.5, 198.51.100.0/24" aria-label="{$view.t.ips_label}" autocomplete="off" spellcheck="false">
                        <input type="password" class="rounded border border-slate-300 p-1" name="password" autocomplete="current-password" required placeholder="{$view.t.confirm_password}" aria-label="{$view.t.confirm_password}">
                        <button type="submit" class="btn-outline">{$view.t.restrict_submit}</button>
                    </form>
                    <small class="wb-hint">{$view.t.ips_card_hint}</small>
                </details>
                {/if}
                <details class="wb-revoke">
                    <summary>{$view.t.revoke_open}</summary>
                    <form method="post" action="{$view.action}" autocomplete="off">
                        <input type="hidden" name="action" value="revoke">
                        <input type="hidden" name="token" value="{$view.tokens.revoke}">
                        <input type="hidden" name="integration_id" value="{$row.id}">
                        <input type="password" class="rounded border border-slate-300 p-1" name="password" autocomplete="current-password" required placeholder="{$view.t.revoke_password}" aria-label="{$view.t.revoke_password}">
                        <button type="submit" class="btn-outline">{$view.t.revoke_submit}</button>
                    </form>
                </details>
                {/if}
            </li>
            {/foreach}
        </ul>
        {else}
        <p>{$view.t.active_empty}</p>
        {/if}
        {if $view.revokedKeys}
        <details class="wb-scopes">
            <summary>{$view.t.revoked_title} ({$view.revokedCount})</summary>
            <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th>{$view.t.col_name}</th>
                        <th>{$view.t.col_created}</th>
                        <th>{$view.t.col_last_used}</th>
                        <th>{$view.t.col_revoked}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $view.revokedKeys as $row}
                    <tr>
                        <td>{$row.name} <span class="wb-id">#{$row.id}</span></td>
                        <td>{$row.created}</td>
                        <td>{$row.last_used}</td>
                        <td>{$row.revoked_on}</td>
                    </tr>
                    {/foreach}
                </tbody>
            </table>
            </div>
        </details>
        {/if}
        {/if}
    </div>
</section>
{if $view.canIssue}
<script>
(function () {
    var matrix = document.querySelector('[data-wb-matrix]');
    if (!matrix) { return; }
    // The row takes the color of the chosen level: red none, blue read, green write.
    function paint(row) {
        var chosen = row.querySelector('input:checked');
        if (chosen) { row.setAttribute('data-level', chosen.value); }
    }
    var rows = matrix.querySelectorAll('.wb-mrow');
    Array.prototype.forEach.call(rows, paint);
    matrix.addEventListener('change', function (event) {
        var row = event.target.closest ? event.target.closest('.wb-mrow') : null;
        if (row) { paint(row); }
    });
    // Set the radios from "group=level;group=level".
    function apply(pairs) {
        var levels = {};
        (pairs || '').split(';').forEach(function (pair) {
            var parts = pair.split('=');
            if (parts[1]) { levels[parts[0]] = parts[1]; }
        });
        Object.keys(levels).forEach(function (name) {
            Array.prototype.forEach.call(document.getElementsByName('group_' + name), function (radio) {
                if (!radio.disabled) { radio.checked = radio.value === levels[name]; }
            });
        });
        Array.prototype.forEach.call(rows, paint);
        return levels;
    }
    // Presets: buttons that fill the matrix and the name. Without JavaScript the form works as it is.
    var presets = document.querySelector('[data-wb-presets]');
    if (presets) {
        presets.hidden = false;
        presets.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('[data-wb-preset]') : null;
            if (!button) { return; }
            apply(button.getAttribute('data-levels'));
            var name = document.getElementById('wb-name');
            if (name) { name.value = button.getAttribute('data-name') || name.value; }
        });
    }
    // Rotation: preset the levels from the key that is being replaced.
    var select = document.getElementById('wb-replaces');
    if (!select) { return; }
    select.addEventListener('change', function () {
        var option = select.options[select.selectedIndex];
        if (!option || option.value === '') { return; }
        var ips = document.getElementById('wb-ips');
        if (ips) { ips.value = option.getAttribute('data-ips') || ''; }
        var levels = apply(option.getAttribute('data-groups'));
        if (!levels.analytics) {
            apply('analytics=' + (option.getAttribute('data-analytics') === '1' ? 'read' : 'off'));
        }
    });
})();
</script>
{/if}
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
