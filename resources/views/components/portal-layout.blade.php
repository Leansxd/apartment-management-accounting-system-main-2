@props(['title', 'apartment' => null])
@php
    $links = $apartment ? [
        'portal.overview' => 'Özet',
        'portal.debts' => 'Borçlarım',
        'portal.notifications' => 'Bildirimler',
        'portal.polls' => 'Anketler',
        'portal.tickets' => 'Taleplerim',
        'portal.reservations' => 'Rezervasyon',
        'portal.meetings' => 'Toplantılar',
    ] : [];
@endphp
<!DOCTYPE html>
<html lang="tr" class="h-full light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leans | {{ $title }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen font-sans antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
            <a href="{{ route('portal.home') }}" class="min-w-0">
                <span class="block text-sm font-extrabold tracking-tight text-slate-900">leans<span class="text-orange-600">.</span></span>
                <span class="block truncate text-xs text-slate-500">{{ $apartment?->name ?? 'Sakin paneli' }}</span>
            </a>
            <div class="flex shrink-0 items-center gap-2">
                <a href="{{ route('portal.account') }}" class="btn btn-secondary btn-sm">Hesabım</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-secondary btn-sm">Çıkış</button></form>
            </div>
        </div>
        @if ($links)
            <nav class="mx-auto flex max-w-5xl gap-1 overflow-x-auto px-4 pb-2" aria-label="Sakin paneli menüsü">
                @foreach ($links as $route => $label)
                    <a href="{{ route($route, $apartment) }}" @class(['tab-pill', 'active' => request()->routeIs($route)]) @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        @endif
    </header>

    <main class="mx-auto max-w-5xl space-y-4 px-4 py-5">
        <h1 class="text-xl font-bold tracking-tight text-slate-900">{{ $title }}</h1>
        @if (session('status'))
            <div role="status" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>
        @endif
        {{ $slot }}
    </main>
</body>
</html>
