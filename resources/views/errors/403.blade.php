<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access restricted · Bayanihan Connect</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<main class="auth-main" style="min-height:100vh;background:var(--bg)">
    <div class="card confirm" style="max-width:480px">
        <div class="confirm-icon"><x-icon name="shield" :size="26"/></div>
        <h1>This area isn't available for your role</h1>
        @php($msg = $exception->getMessage())
        <p>{{ $msg && $msg !== 'This action is unauthorized.' ? $msg : 'Your account does not have access to this module. The attempt has been logged for audit.' }}</p>
        <div class="confirm-actions"><a class="btn" href="{{ url('/dashboard') }}">Back to Dashboard</a></div>
    </div>
</main>
</body>
</html>
