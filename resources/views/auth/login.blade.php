<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#14532d">
    <title>Sign in · Bayanihan Connect</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="auth">
    <section class="auth-side">
        <div class="brand" style="padding:0;color:#fff">
            <span class="brand-mark" style="border-color:#fff"><x-icon name="users"/></span>
            <span>Bayanihan Connect</span>
        </div>
        <div>
            <h2>One shared record for every program, donation, and volunteer.</h2>
            <ul>
                <li>Beneficiary registry shared across programs</li>
                <li>Every donation traced to the families it reached</li>
                <li>Volunteer schedules and messages in one place</li>
                <li>Works in the field, even offline</li>
            </ul>
        </div>
        <small>Bayanihan Outreach Foundation</small>
    </section>
    <main class="auth-main">
        <div class="auth-card">
            <div class="brand">
                <span class="brand-mark"><x-icon name="users"/></span>
                <span>Bayanihan Connect</span>
            </div>
            <h1>Sign in</h1>
            <p style="color:var(--text-2);margin:6px 0 24px">Use the account given to you by the Foundation.</p>

            @if ($errors->any())
                <div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ url('/login') }}">
                @csrf
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           @class(['is-invalid' => $errors->has('email')])>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password">
                </div>
                <label class="check" style="margin-bottom:20px"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
                <button class="btn btn-block" type="submit">Sign In</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
