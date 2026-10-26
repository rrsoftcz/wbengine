<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>WBengine exception details</title>
    <style>
        :root { color-scheme: light; font: 16px/1.55 system-ui, sans-serif; background: #f1f5f9; color: #0f172a; }
        body { margin: 0; padding: clamp(1rem, 5vw, 4rem); }
        main { max-width: 64rem; margin: 0 auto; padding: clamp(1.25rem, 4vw, 3rem); border: 1px solid #cbd5e1; border-radius: 1rem; background: #fff; box-shadow: 0 1rem 3rem #0f172a12; }
        h1 { margin-top: 0; font-size: clamp(1.5rem, 4vw, 2.25rem); }
        .notice { padding: .75rem 1rem; border-left: .25rem solid #d97706; background: #fffbeb; }
        dl { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: .5rem 1rem; }
        dt { color: #475569; font-weight: 600; }
        dd { margin: 0; overflow-wrap: anywhere; }
        pre { overflow: auto; max-height: 60vh; padding: 1rem; border-radius: .5rem; background: #0f172a; color: #e2e8f0; font: .85rem/1.5 ui-monospace, monospace; white-space: pre-wrap; overflow-wrap: anywhere; }
    </style>
</head>
<body>
<main>
    <h1>WBengine caught an exception</h1>
    <p class="notice">Detailed errors are visible because debug mode is enabled. Disable debug mode on public production sites.</p>
    <dl>
        <dt>Exception</dt><dd>%s</dd>
        <dt>Code</dt><dd>%s</dd>
        <dt>Message</dt><dd>%s</dd>
        <dt>File</dt><dd>%s</dd>
        <dt>Line</dt><dd>%s</dd>
    </dl>
    <h2>Stack trace</h2>
    <pre>%s</pre>
</main>
</body>
</html>
