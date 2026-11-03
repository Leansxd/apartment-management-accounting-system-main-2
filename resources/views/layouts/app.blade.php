<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Panel' }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
@php
    $is = fn (string ...$patterns) => request()->routeIs(...$patterns) ? 'active' : '';
    $siteLink = fn (string $route) => $site ? route($route, $site) : '#';
@endphp

{{-- Üst çubuk --}}
<header class="no-print fixed inset-x-0 top-0 z-40 h-14 bg-white border-b border-slate-200 flex items-center gap-3 px-4">
    <button type="button" class="lg:hidden btn btn-secondary btn-sm" data-toggle-sidebar aria-controls="sidebar" aria-label="Menü">☰</button>
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-bold text-slate-900">
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 text-white">M</span>
        <span class="hidden sm:inline">{{ config('app.name') }}</span>
    </a>

    @if ($sites->isNotEmpty())
        @php
            // Site değiştirince aynı sayfada kalmak için mevcut rota adı korunur (parametresiz site sayfalarında)
            $current = request()->route()?->getName();
            $switchRoute = $current && str_starts_with($current, 'site.') && ! str_contains(request()->route()->uri(), '}/')
                ? $current : 'site.dashboard';
            $switchRoute = $site && collect(request()->route()->parameterNames())->count() === 1 ? $current : 'site.dashboard';
        @endphp
        <label class="sr-only" for="site-switch">Site seçin</label>
        <select id="site-switch" class="input !w-auto max-w-[14rem] !py-1.5" data-site-switch>
            @unless ($site)<option value="">Site seçin…</option>@endunless
            @foreach ($sites as $s)
                <option value="{{ route($switchRoute, $s) }}" @selected($site?->id === $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>
    @endif

    <div class="ml-auto flex items-center gap-3">
        <span class="hidden md:inline text-sm text-slate-500">{{ auth()->user()->name }}
            @if (auth()->user()->is_super_admin)<span class="badge badge-blue ml-1">Süper Admin</span>@endif
        </span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-secondary btn-sm">Çıkış</button>
        </form>
    </div>
</header>

{{-- Yan menü --}}
<aside id="sidebar" class="no-print fixed left-0 top-14 bottom-0 z-30 w-60 overflow-y-auto bg-white border-r border-slate-200 p-3 transition-transform -translate-x-full lg:translate-x-0">
    <nav class="space-y-0.5" aria-label="Ana menü">
        <p class="nav-group">Ana Menü</p>
        <a class="nav-link {{ $is('dashboard') }}" href="{{ route('dashboard') }}">Genel Bakış</a>
        <a class="nav-link {{ $is('sites.*') }}" href="{{ route('sites.index') }}">Siteler</a>
        <a class="nav-link {{ $is('users.*') }}" href="{{ route('users.index') }}">Kayıtlı Kullanıcılar</a>
        <a class="nav-link {{ $is('properties.*') }}" href="{{ route('properties.index') }}">Emlak Portföyü</a>

        @if ($site)
            <p class="nav-group truncate" title="{{ $site->name }}">{{ $site->name }}</p>
            <a class="nav-link {{ $is('site.dashboard') }}" href="{{ $siteLink('site.dashboard') }}">Genel Durum</a>
            <a class="nav-link {{ $is('site.units.*') }}" href="{{ $siteLink('site.units.index') }}">Bloklar & Daireler</a>
            <a class="nav-link {{ $is('site.members.*') }}" href="{{ $siteLink('site.members.index') }}">Üyeler / Sakinler</a>
            <a class="nav-link {{ $is('site.reports.*') }}" href="{{ $siteLink('site.reports.index') }}">Raporlar</a>

            <p class="nav-group">Finansal İşlemler</p>
            <a class="nav-link {{ $is('site.finance') }}" href="{{ $siteLink('site.finance') }}">Finans Özeti</a>
            <a class="nav-link {{ $is('site.collections.*') }}" href="{{ $siteLink('site.collections.index') }}">Tahsilat</a>
            <a class="nav-link {{ $is('site.debts.*') }}" href="{{ $siteLink('site.debts.index') }}">Borçlandırma & Tahakkuk</a>
            <a class="nav-link {{ $is('site.dues.*') }}" href="{{ $siteLink('site.dues.index') }}">Aidat Motoru</a>
            <a class="nav-link {{ $is('site.expenses.*') }}" href="{{ $siteLink('site.expenses.index') }}">Gider Yönetimi</a>
            <a class="nav-link {{ $is('site.cashboxes.*') }}" href="{{ $siteLink('site.cashboxes.index') }}">Kasa & Banka</a>
            <a class="nav-link {{ $is('site.vendors.*') }}" href="{{ $siteLink('site.vendors.index') }}">Firmalar / Tedarikçiler</a>
            <a class="nav-link {{ $is('site.income-expense') }}" href="{{ $siteLink('site.income-expense') }}">Gelir Gider Tablosu</a>
            <a class="nav-link {{ $is('site.statements') }}" href="{{ $siteLink('site.statements') }}">Daire Hesap Ekstresi</a>
            <a class="nav-link {{ $is('site.ledger') }}" href="{{ $siteLink('site.ledger') }}">Yevmiye Defteri</a>

            <p class="nav-group">Operasyon</p>
            <a class="nav-link {{ $is('site.tasks.*') }}" href="{{ $siteLink('site.tasks.index') }}">Görevler</a>
            <a class="nav-link {{ $is('site.tickets.*') }}" href="{{ $siteLink('site.tickets.index') }}">Destek Talepleri</a>
            <a class="nav-link {{ $is('site.security.*') }}" href="{{ $siteLink('site.security.index') }}">Güvenlik & Kargo</a>
            <a class="nav-link {{ $is('site.staff.*') }}" href="{{ $siteLink('site.staff.index') }}">Personel</a>
            <a class="nav-link {{ $is('site.inventory.*') }}" href="{{ $siteLink('site.inventory.index') }}">Stok / Envanter</a>
            <a class="nav-link {{ $is('site.meters.*') }}" href="{{ $siteLink('site.meters.index') }}">Sayaçlar</a>

            <p class="nav-group">İletişim</p>
            <a class="nav-link {{ $is('site.announcements.*') }}" href="{{ $siteLink('site.announcements.index') }}">Duyurular</a>
            <a class="nav-link {{ $is('site.polls.*') }}" href="{{ $siteLink('site.polls.index') }}">Anketler</a>
            <a class="nav-link {{ $is('site.decisions.*') }}" href="{{ $siteLink('site.decisions.index') }}">Karar Defteri</a>
            <a class="nav-link {{ $is('site.notifications.*') }}" href="{{ $siteLink('site.notifications.index') }}">Bildirim Gönder</a>
            <a class="nav-link {{ $is('site.reminders.*') }}" href="{{ $siteLink('site.reminders.index') }}">Borç Hatırlatıcı</a>

            <p class="nav-group">Ayarlar</p>
            <a class="nav-link {{ $is('site.settings') }}" href="{{ $siteLink('site.settings') }}">Site Ayarları</a>
            <a class="nav-link {{ $is('site.audit') }}" href="{{ $siteLink('site.audit') }}">İşlem Kayıtları</a>
        @endif
    </nav>
</aside>

<main class="print-full pt-14 lg:pl-60">
    <div class="print-full mx-auto max-w-7xl p-4 sm:p-6 space-y-6">
        @if (isset($heading))
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">{{ $heading }}</h1>
                    @isset($subheading)<p class="text-sm text-slate-500 mt-0.5">{{ $subheading }}</p>@endisset
                </div>
                @isset($actions)<div class="no-print flex flex-wrap gap-2">{{ $actions }}</div>@endisset
            </div>
        @endif

        @if (session('status'))
            <div role="status" class="no-print rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div role="alert" class="no-print rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="no-print rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc pl-5 space-y-0.5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        {{ $slot }}
    </div>
</main>
</body>
</html>
