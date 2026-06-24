<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You've been invited</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 40px 20px; }
        .container { max-width: 480px; margin: 0 auto; background: #1e293b; border-radius: 12px; padding: 40px; border: 1px solid rgba(255,255,255,0.06); }
        .logo { width: 40px; height: 40px; background: #7c3aed; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 24px; }
        h1 { font-size: 20px; font-weight: 600; color: #f8fafc; margin: 0 0 12px; }
        p { font-size: 14px; color: #94a3b8; line-height: 1.6; margin: 0 0 20px; }
        .btn { display: inline-block; background: #7c3aed; color: #fff; font-size: 14px; font-weight: 500; padding: 12px 24px; border-radius: 8px; text-decoration: none; }
        .url { margin-top: 24px; font-size: 12px; color: #64748b; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <h1>You've been invited</h1>
        <p>Hi {{ $user->name }}, you've been invited to join <strong style="color:#f8fafc">{{ config('app.name') }}</strong>. Click the button below to set up your account.</p>
        <a href="{{ $acceptUrl }}" class="btn">Accept Invitation</a>
        <p class="url">Or copy this link: {{ $acceptUrl }}</p>
    </div>
</body>
</html>
