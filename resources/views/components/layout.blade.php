@props(['title' => null, 'heading' => null, 'subheading' => null])
@php
    $user = auth()->user();
    $route = request()->route();
    $routeName = (string) $route?->getName();
    $is = fn (string ...$patterns) => request()->routeIs(...$patterns);

    $erp = $site && $is('site.*') && ! $is('site.security.*', 'site.tickets.*', 'site.announcements.*');
    $switchRoute = $site && $is('site.*') && count($route->parameterNames()) === 1 && request()->isMethod('get') ? $routeName : 'site.dashboard';

    $siteRoute = fn (string $name) => $currentSite ? route($name, $currentSite) : route('sites.index');
    $mainTabs = [
        ['Dashboard', 'layout-dashboard', route('dashboard'), $is('dashboard')],
        ['Personel & Sakin', 'users', route('users.index'), $is('users.*')],
        ['Planlama & Aidat', 'calendar', $siteRoute('site.dues.index'), $is('site.dues.*', 'site.reservations.*', 'site.meetings.*')],
        ['Finans', 'circle-dollar-sign', $siteRoute('site.finance'), $is('site.finance*', 'site.collections.*', 'site.debts.*', 'site.expenses.*', 'site.cashboxes.*')],
        ['Envanter & Emlak', 'package', route('properties.index'), $is('properties.*', 'sites.*', 'site.inventory.*', 'site.assets.*')],
        ['Analitik', 'bar-chart-3', $siteRoute('site.reports.index'), $is('site.reports.*', 'site.dashboard')],
    ];
    $badgeCount = max(1, (int) $openTickets);
@endphp
<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Memoshome | {{ $title ?? $heading ?? 'Panel' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-ink antialiased selection:bg-brand-100">

<header class="no-print sticky top-0 z-50 bg-white">
    <div class="border-b border-line">
        <div class="mx-auto flex h-[68px] max-w-[1520px] items-center justify-between gap-4 px-4 sm:px-8">
            <div class="flex min-w-0 items-center gap-5 lg:gap-6">
                <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-2.5">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" class="text-ink">
                        <path d="M4 4l5.5 5.5L4 15V4zM20 4l-5.5 5.5L20 15V4zM4 20l5.5-5.5L15 20H4zM20 20l-5.5-5.5L9 20h11z" fill="currentColor"/>
                    </svg>
                    <span class="text-[18px] font-bold tracking-[-0.02em] text-ink">Memoshome</span>
                </a>
                <span class="hidden h-6 w-px bg-line sm:block"></span>
                <button type="button" data-toggle="apt-modal" class="hidden min-w-0 items-center gap-2.5 rounded-lg px-2 py-1 text-[13.5px] font-medium text-ink transition-colors hover:bg-slate-50 sm:flex" aria-haspopup="dialog">
                    <x-icon name="building-2" :size="16" class="text-ink" />
                    <span class="max-w-[180px] truncate">{{ $currentSite?->name ?? 'Konut seç' }}</span>
                    <x-icon name="chevron-down" :size="15" class="text-muted" />
                </button>
                <span class="hidden h-6 w-px bg-line md:block"></span>
                <button type="button" data-toggle="apt-modal" class="hidden items-center gap-1.5 rounded-lg px-2 py-1 text-[13.5px] font-medium text-ink transition-colors hover:bg-slate-50 md:flex">
                    <x-icon name="plus" :size="16" /><span>Konut ekle</span>
                </button>
            </div>

            <div class="flex items-center gap-3">
                <details class="dropdown relative">
                    <summary class="icon-btn" aria-label="Ara"><x-icon name="search" :size="17" /></summary>
                    <form method="GET" action="{{ route('dashboard') }}" class="absolute right-0 top-12 z-50 w-72 rounded-xl border border-line bg-white p-2 shadow-[0_12px_32px_-12px_rgba(23,22,44,0.18)]">
                        <label class="sr-only" for="top-q">Kullanıcı ara</label>
                        <input id="top-q" name="q" class="input" placeholder="Kullanıcı, e-posta veya telefon…" autocomplete="off">
                    </form>
                </details>
                <a href="{{ $siteRoute('site.tickets.index') }}" class="icon-btn" aria-label="Açık talepler: {{ $badgeCount }}" title="Açık destek talepleri">
                    <x-icon name="bell" :size="17" />
                    <span class="absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#6e5ce0] px-1 text-[10px] font-semibold text-white">{{ $badgeCount }}</span>
                </a>
                <details class="dropdown relative ml-1">
                    <summary class="flex items-center gap-2.5 rounded-xl p-1 transition-colors hover:bg-slate-50">
                        <span class="overflow-hidden rounded-full ring-1 ring-line"><x-dash.avatar :index="0" :size="36" /></span>
                        <span class="hidden text-left leading-tight md:block">
                            <span class="block max-w-[140px] truncate text-[13px] font-semibold text-ink">{{ $user->name }}</span>
                            <span class="block text-xs text-muted">{{ $user->is_super_admin ? 'Süper admin' : 'Yönetici' }}</span>
                        </span>
                        <x-icon name="chevron-down" :size="15" class="hidden text-muted md:block" />
                    </summary>
                    <div class="absolute right-0 top-12 z-50 w-52 rounded-xl border border-line bg-white p-1.5 shadow-[0_12px_32px_-12px_rgba(23,22,44,0.18)]">
                        <a href="{{ route('users.show', $user) }}" class="drop-item">Profilim</a>
                        @if ($currentSite)<a href="{{ route('site.settings', $currentSite) }}" class="drop-item">Site ayarları</a>@endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="drop-item w-full text-rose-600 hover:text-rose-700">Çıkış yap</button>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    </div>

    <div class="border-b border-line">
        <div class="mx-auto flex max-w-[1520px] items-center justify-between gap-4 px-4 py-2.5 sm:px-8">
            <nav class="flex flex-wrap items-center gap-2" aria-label="{{ $erp ? 'ERP modülleri' : 'Ana gezinti' }}">
                @if ($erp)
                    <x-erp-nav :site="$site" />
                @else
                    @foreach ($mainTabs as [$label, $icon, $href, $active])
                        <a href="{{ $href }}" class="tab-pill {{ $active ? 'active' : '' }}" @if ($active) aria-current="page" @endif>
                            <x-icon :name="$icon" :size="16" /><span>{{ $label }}</span>
                        </a>
                    @endforeach
                @endif
            </nav>
            @if ($erp)
                <a href="{{ route('site.collections.index', $site) }}" class="btn-brand hidden sm:inline-flex"><x-icon name="plus" :size="15" /><span>Tahsilat al</span></a>
            @else
                <a href="{{ $siteRoute('site.collections.index') }}" class="btn-brand hidden sm:inline-flex"><x-icon name="plus" :size="15" /><span>Uyarı oluştur</span></a>
            @endif
        </div>
    </div>
</header>

<div id="apt-modal" class="no-print fixed inset-0 z-[200] hidden items-start justify-center bg-ink/30 pt-16 sm:pt-24" role="dialog" aria-modal="true" aria-label="Yönetiminizdeki konutlar">
    <div class="mx-4 w-full max-w-lg overflow-hidden rounded-2xl border border-line bg-white shadow-[0_24px_60px_-20px_rgba(23,22,44,0.35)]">
        <div class="flex items-center justify-between border-b border-line px-6 py-4">
            <div>
                <h3 class="text-[15px] font-semibold">Konutlar</h3>
                <p class="text-[13px] text-muted">Yönetmek istediğiniz site veya apartmanı seçin</p>
            </div>
            <button type="button" data-close="apt-modal" class="flex h-8 w-8 items-center justify-center rounded-lg text-muted transition-colors hover:bg-slate-100 hover:text-ink" aria-label="Kapat"><x-icon name="x" :size="18" /></button>
        </div>
        <div class="max-h-[60vh] space-y-4 overflow-y-auto p-6">
            <div class="space-y-2">
                @foreach ($sites as $s)
                    @php $active = $currentSite?->id === $s->id; @endphp
                    <div class="group flex items-center justify-between rounded-xl border p-3 transition-colors {{ $active ? 'border-brand-300 bg-brand-50' : 'border-line hover:bg-slate-50' }}">
                        <a href="{{ route($switchRoute, $s) }}" class="flex flex-1 items-center gap-3 text-left">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $active ? 'bg-brand-500 text-white' : 'bg-slate-100 text-muted' }}"><x-icon name="building-2" :size="18" /></span>
                            <span>
                                <span class="block text-[14px] font-medium">{{ $s->name }}</span>
                                <span class="block text-xs text-muted">{{ $s->code }} · {{ $s->city ?? 'Şehir belirtilmedi' }}</span>
                            </span>
                        </a>
                        <div class="flex items-center gap-2">
                            @if ($user->is_super_admin || $s->owner_id === $user->id)
                                <form method="POST" action="{{ route('site.destroy', $s) }}" data-confirm-code="{{ $s->code }}" data-confirm-text="“{{ $s->name }}” konutu ve tüm verileri silinecek. Onaylamak için konut kodunu ({{ $s->code }}) yazın:">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="confirm" value="">
                                    <button class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-300 opacity-0 transition-all hover:bg-rose-50 hover:text-rose-600 group-hover:opacity-100 focus:opacity-100" title="Konutu sil" aria-label="{{ $s->name }} sil"><x-icon name="trash-2" :size="15" /></button>
                                </form>
                            @endif
                            @if ($active)<span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white"><x-icon name="check" :size="13" /></span>@endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div id="apt-add-form" class="hidden">
                <form method="POST" action="{{ route('sites.store') }}" class="space-y-3 rounded-xl border border-line bg-slate-50/60 p-4">
                    @csrf
                    <div><label class="label" for="apt-name">Konut adı</label><input class="input" id="apt-name" name="name" placeholder="Örn: Concept Rezidans" required></div>
                    <div><label class="label" for="apt-code">Kısa kod</label><input class="input uppercase" id="apt-code" name="code" placeholder="Örn: CNR" maxlength="10" required></div>
                    <div class="flex gap-2 pt-1">
                        <button type="button" data-toggle="apt-add-form" class="btn btn-secondary flex-1">İptal</button>
                        <button class="btn btn-primary flex-1"><x-icon name="plus" :size="14" />Ekle</button>
                    </div>
                </form>
            </div>

            <button type="button" data-toggle="apt-add-form" data-hide-self class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 p-3 text-[13px] font-medium text-muted transition-colors hover:border-brand-400 hover:text-brand-700">
                <x-icon name="plus" :size="16" /><span>Yeni konut ekle</span>
            </button>
            <a href="{{ route('sites.index') }}" class="block text-center text-[13px] text-muted hover:text-ink">Tüm konutları yönet →</a>
        </div>
    </div>
</div>

<main class="min-w-0">
    <div class="print-full mx-auto max-w-[1520px] space-y-6 px-4 py-6 sm:px-8 lg:py-7">
        @if ($heading)
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-[28px] font-bold leading-tight tracking-[-0.02em] text-ink">{{ $heading }}</h1>
                    @if ($subheading)<p class="mt-1 text-[13px] text-muted">{{ $subheading }}</p>@endif
                </div>
                @isset($actions)<div class="no-print flex flex-wrap items-center gap-2.5">{{ $actions }}</div>@endisset
            </div>
        @endif

        @if (session('status'))
            <div role="status" class="no-print flex items-center gap-2.5 rounded-xl bg-emerald-50 px-4 py-3 text-[13px] font-medium text-emerald-800">
                <x-icon name="check" :size="16" class="text-emerald-600" /><span>{{ session('status') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div role="alert" class="no-print flex items-center gap-2.5 rounded-xl bg-rose-50 px-4 py-3 text-[13px] font-medium text-rose-800">
                <x-icon name="alert-circle" :size="16" class="text-rose-600" /><span>{{ session('error') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div role="alert" class="no-print rounded-xl bg-rose-50 p-4 text-[13px] font-medium text-rose-800">
                <ul class="list-disc space-y-0.5 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        {{ $slot }}
    </div>
</main>
</body>
</html>
