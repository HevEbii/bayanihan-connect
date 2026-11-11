<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#14532d">
    @auth
        @php
            // Pages pre-cached for offline field use (FR07), limited to what this role may open.
            $offlineUrls = collect([
                route('dashboard'),
                Gate::allows('beneficiaries.manage') ? route('beneficiaries.create') : null,
                Gate::allows('distributions.manage') ? route('distributions.create') : null,
                Gate::allows('messages.use') ? route('messages.index') : null,
                Gate::allows('schedule.own') ? route('schedule.index') : null,
            ])->filter()->values();
        @endphp
        <meta name="offline-urls" content="{{ $offlineUrls->toJson() }}">
        <meta name="user-id" content="{{ auth()->id() }}">
    @endauth
    <title>@yield('title', 'Dashboard') · Bayanihan Connect</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
@php $user = auth()->user(); @endphp
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <a href="{{ route('dashboard') }}" class="brand">
            <span class="brand-mark"><x-icon name="users" :size="18"/></span>
            <span>Bayanihan Connect</span>
        </a>
        <nav class="nav">
            @php
                $nav = [
                    ['dashboard', 'Dashboard', 'home', null, 'dashboard'],
                    ['impact', 'My Impact', 'heart', 'impact.view', 'impact'],
                    ['schedule.index', 'My Schedule', 'calendar', 'schedule.own', 'schedule.*'],
                    ['beneficiaries.index', 'Beneficiaries', 'users', 'beneficiaries.view', 'beneficiaries.*'],
                    ['donors.index', 'Donor Registry', 'heart', 'donors.view', 'donors.*'],
                    ['donations.index', 'Donations', 'gift', 'donations.view', 'donations.*'],
                    ['distributions.index', 'Distribution', 'box', 'distributions.view', 'distributions.*'],
                    ['programs.index', 'Programs & Activities', 'list', 'programs.view', ['programs.*', 'activities.*']],
                    ['volunteers.index', 'Volunteers', 'hand', 'volunteers.view', 'volunteers.*'],
                    ['messages.index', 'Messages', 'message', 'messages.use', 'messages.*'],
                    ['reports.index', 'Reports', 'chart', 'reports.view', 'reports.*'],
                ];
                $admin = [
                    ['users.index', 'Users & Roles', 'shield', 'users.manage', 'users.*'],
                    ['audit.index', 'Audit Log', 'log', 'audit.view', 'audit.*'],
                    ['units.index', 'Settings', 'settings', 'settings.manage', 'units.*'],
                ];
                $unread = Gate::allows('messages.use') ? $user->unreadMessagesCount() : 0;
            @endphp
            @foreach ($nav as [$route, $label, $icon, $ability, $active])
                @if (! $ability || Gate::allows($ability))
                    <a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($active)])>
                        <x-icon :name="$icon"/> <span>{{ $label }}</span>
                        @if ($route === 'messages.index' && $unread)
                            <span class="badge-count" aria-label="{{ $unread }} unread">{{ $unread }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
            @if (collect($admin)->contains(fn ($i) => Gate::allows($i[3])))
                <div class="nav-section">Administration</div>
                @foreach ($admin as [$route, $label, $icon, $ability, $active])
                    @can($ability)
                        <a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($active)])>
                            <x-icon :name="$icon"/> <span>{{ $label }}</span>
                        </a>
                    @endcan
                @endforeach
            @endif
        </nav>
        <div class="sidebar-foot"><span class="dot"></span> System v1.0.0</div>
    </aside>
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-btn" id="menu-btn" type="button" aria-label="Open menu"><x-icon name="menu"/></button>
            <div class="sync-status" id="sync-status" data-state="online">
                <span class="sync-dot"></span><span class="sync-text">Online</span>
            </div>
            <div class="topbar-user">
                <span class="avatar"><x-icon name="user" :size="16"/></span>
                <span class="who"><strong>{{ $user->name }}</strong><small>{{ $user->roleLabel() }}</small></span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="link-btn" type="submit">Logout</button>
                </form>
            </div>
        </header>

        <div class="offline-banner" id="offline-banner" hidden>
            <x-icon name="wifi-off"/>
            <span>You are offline. Entries on field forms are saved on this device and will sync automatically once you reconnect.</span>
        </div>
        <div class="pending-panel" id="pending-panel" hidden></div>

        <main class="content">
            @if (session('status'))
                <div class="alert alert-success" role="status"><x-icon name="check"/> {{ session('status') }}</div>
            @endif
            @if ($errors->any() && ! View::hasSection('inline-errors'))
                <div class="alert alert-error" role="alert">
                    <strong>Please check the form.</strong>
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
