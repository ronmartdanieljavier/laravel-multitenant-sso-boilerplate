<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Something went wrong</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 1rem;
            padding: 2.5rem;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }
        .icon {
            width: 3rem;
            height: 3rem;
            background: rgba(239,68,68,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 1.25rem;
        }
        h1 { font-size: 1.25rem; font-weight: 600; margin-bottom: .5rem; }
        p { font-size: .875rem; color: #94a3b8; line-height: 1.6; margin-bottom: 1.5rem; }
        .code-box {
            background: #0f172a;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: .5rem;
            padding: .75rem 1rem;
            font-family: monospace;
            font-size: 1rem;
            letter-spacing: .05em;
            color: #f87171;
        }
        .label { font-size: .75rem; color: #64748b; margin-bottom: .25rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⚠</div>
        <h1>Something went wrong</h1>
        <p>{{ $message }}</p>
        <div class="label">Error code — quote this when contacting support</div>
        <div class="code-box">{{ $error_code }}</div>
    </div>
</body>
</html>
