<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline · Bayanihan Connect</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<main class="auth-main" style="min-height:100vh">
    <div class="card confirm" style="max-width:460px">
        <div class="confirm-icon"><x-icon name="wifi-off" :size="26"/></div>
        <h1>You're offline</h1>
        <p>This page hasn't been saved on this device yet. Field forms you have opened before (beneficiary registration,
            distribution logging, messages) still work offline and will sync when you reconnect.</p>
        <div class="confirm-actions">
            <a class="btn btn-outline" href="{{ url('/beneficiaries/create') }}">Register beneficiary</a>
            <a class="btn" href="{{ url('/dashboard') }}">Try dashboard</a>
        </div>
    </div>
</main>
</body>
</html>
