@props(['title' => null, 'heading' => null, 'subheading' => null])
@php
    $user = auth()->user();
    $route = request()->route();
    $routeName = (string) $route?->getName();
    $is = fn (string ...$patterns) => request()->routeIs(...$patterns);

    $erp = $site && $is('site.*') && ! $is('site.security.*', 'site.tickets.*', 'site.announcements.*');
    $switchRoute = $site && $is('site.*') && count($route->parameterNames()) === 1 && request()->isMethod('get') ? $routeName : 'site.dashboard';

    $siteRoute = fn (string $name) => $currentSite ? route($name, $currentSite) : route('sites.index');
    $allNav = [
        ['Dashboard', 'layout-dashboard', route('dashboard'), $is('dashboard')],
        ['Siteler', 'building', route('sites.index'), $is('sites.*')],
        ['Emlak Portföyü', 'layout-list', route('properties.index'), $is('properties.*')],
        ['Kayıtlı Sakinler', 'users', route('users.index'), $is('users.*')],
        ['Yönetim & ERP', 'wrench', $siteRoute('site.dashboard'), $erp],
        ['Güvenlik & Kargo', 'shield-check', $siteRoute('site.security.index'), $is('site.security.*')],
        ['Destek Talepleri', 'message-square', $siteRoute('site.tickets.index'), $is('site.tickets.*')],
        ['Duyuru & Anket', 'megaphone', $siteRoute('site.announcements.index'), $is('site.announcements.*')],
    ];
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f8fafc] font-sans text-slate-700 antialiased selection:bg-orange-100 selection:text-orange-900">

<header class="no-print sticky top-0 z-50 border-b border-slate-200/80 bg-white/95 backdrop-blur-md">
    <div class="mx-auto flex h-16 max-w-[1720px] items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3 lg:gap-5">
            <button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 lg:hidden" data-toggle="top-menu" aria-label="Menüyü aç" aria-expanded="false">
                <x-icon name="menu" :size="18" />
            </button>

            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 transition-opacity hover:opacity-90">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-900 text-sm font-black text-white shadow-xs">
                    M
                </div>
                <div class="hidden sm:flex flex-col">
                    <span class="text-sm font-extrabold tracking-tight text-slate-900 leading-none">Memoshome</span>
                    <span class="text-[10px] font-semibold text-slate-400 leading-tight">Yönetim & ERP</span>
                </div>
            </a>

            <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

            <button type="button" data-toggle="apt-modal" class="group flex items-center gap-2 rounded-xl border border-slate-200/90 bg-slate-50/70 hover:bg-slate-100/80 px-3 py-1.5 transition-colors" aria-haspopup="dialog">
                <span class="flex h-5 w-5 items-center justify-center text-slate-500"><x-icon name="building" :size="14" /></span>
                <span class="max-w-[120px] truncate text-xs font-bold text-slate-800">{{ $currentSite?->name ?? 'Konut Seç' }}</span>
                <span class="rounded bg-slate-200/70 px-1 text-[10px] font-bold text-slate-600">{{ $currentSite?->code ?? 'Tümü' }}</span>
                <x-icon name="chevron-down" :size="12" class="text-slate-400 transition-transform group-hover:translate-y-0.5" />
            </button>
        </div>

        <nav class="hidden lg:flex items-center gap-1 overflow-x-auto py-1" aria-label="Ana Gezinti">
            @foreach ($allNav as [$label, $icon, $href, $active])
                <a href="{{ $href }}" class="nav-pill {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif>
                    <span class="flex h-4 w-4 shrink-0 items-center justify-center {{ $active ? 'text-[#ff5a36]' : 'text-slate-400' }}"><x-icon :name="$icon" :size="15" /></span>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2 sm:gap-3">
            <div class="relative hidden xl:block w-44">
                <x-icon name="search" :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input type="text" placeholder="Hızlı ara..." class="w-full rounded-xl border border-slate-200 bg-slate-50/60 py-1.5 pl-8 pr-7 text-xs text-slate-800 placeholder:text-slate-400 focus:border-[#ff5a36] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#ff5a36]/10">
                <kbd class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded border border-slate-200 bg-white px-1 font-mono text-[10px] text-slate-400">/</kbd>
            </div>

            <button type="button" data-toggle="apt-modal" class="btn-brand hidden sm:inline-flex">
                <x-icon name="plus" :size="14" />
                <span>Konut Ekle</span>
            </button>

            <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

            <div class="flex items-center gap-2">
                <a href="{{ route('users.show', $user) }}" class="flex items-center gap-2 rounded-xl p-1 transition-colors hover:bg-slate-50">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-orange-100 text-xs font-bold text-[#ff5a36]">
                        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                    </div>
                    <div class="hidden md:flex flex-col text-left">
                        <span class="max-w-[130px] truncate text-xs font-bold text-slate-800 leading-none">{{ $user->name }}</span>
                        <span class="text-[10px] font-semibold text-slate-400 leading-tight">{{ $user->is_super_admin ? 'Süper Admin' : 'Yönetici' }}</span>
                    </div>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 text-slate-400 transition-colors hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600" title="Çıkış" aria-label="Çıkış">
                        <x-icon name="power" :size="14" />
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="top-menu" class="hidden border-t border-slate-200 bg-white px-4 py-3 lg:hidden">
        <nav class="grid grid-cols-2 gap-1.5" aria-label="Mobil Gezinti">
            @foreach ($allNav as [$label, $icon, $href, $active])
                <a href="{{ $href }}" class="nav-pill {{ $active ? 'active' : '' }}">
                    <span class="flex h-4 w-4 shrink-0 items-center justify-center {{ $active ? 'text-[#ff5a36]' : 'text-slate-400' }}"><x-icon :name="$icon" :size="15" /></span>
                    <span class="truncate">{{ $label }}</span>
                </a>
            @endforeach
        </nav>
    </div>
</header>

<div id="apt-modal" class="no-print fixed inset-0 z-[200] hidden items-start justify-center bg-slate-900/40 pt-16 backdrop-blur-sm sm:pt-24" role="dialog" aria-modal="true" aria-label="Yönetiminizdeki Konutlar">
    <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden mx-4">
        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/60 px-6 py-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Yönetiminizdeki Konutlar</h3>
                <p class="text-xs text-slate-400">Yönetimini yapmak istediğiniz site veya apartmanı seçin</p>
            </div>
            <button type="button" data-close="apt-modal" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-200/60 hover:text-slate-600 transition-colors" aria-label="Kapat">
                <x-icon name="x" :size="18" />
            </button>
        </div>
        <div class="max-h-[60vh] overflow-y-auto p-6 space-y-4">
            <div class="space-y-2">
                @foreach ($sites as $s)
                    @php $active = $currentSite?->id === $s->id; @endphp
                    <div class="group flex items-center justify-between rounded-xl border p-3.5 transition-all {{ $active ? 'border-[#ff5a36] bg-orange-50/40 shadow-xs' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50/60' }}">
                        <a href="{{ route($switchRoute, $s) }}" class="flex flex-1 items-center gap-3 text-left">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $active ? 'bg-[#ff5a36] text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-slate-200' }}">
                                <x-icon name="building-2" :size="18" />
                            </span>
                            <div>
                                <span class="block text-sm font-bold text-slate-900 leading-snug">{{ $s->name }}</span>
                                <span class="block text-[11px] font-semibold text-slate-400">{{ $s->code }} · {{ $s->city ?? 'Şehir belirtilmedi' }}</span>
                            </div>
                        </a>
                        <div class="flex items-center gap-2">
                            @if ($user->is_super_admin || $s->owner_id === $user->id)
                                <form method="POST" action="{{ route('site.destroy', $s) }}" data-confirm-code="{{ $s->code }}" data-confirm-text="“{{ $s->name }}” konutu ve tüm verileri silinecek. Onaylamak için konut kodunu ({{ $s->code }}) yazın:">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="confirm" value="">
                                    <button class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-300 opacity-0 transition-all hover:bg-rose-50 hover:text-rose-600 group-hover:opacity-100" title="Konutu Sil" aria-label="{{ $s->name }} sil">
                                        <x-icon name="trash-2" :size="15" />
                                    </button>
                                </form>
                            @endif
                            @if ($active)
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#ff5a36] text-white shadow-xs">
                                    <x-icon name="check" :size="13" />
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div id="apt-add-form" class="hidden">
                <form method="POST" action="{{ route('sites.store') }}" class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                    @csrf
                    <div><label class="label" for="apt-name">Konut Adı</label><input class="input" id="apt-name" name="name" placeholder="Örn: Concept Rezidans" required></div>
                    <div><label class="label" for="apt-code">Kısa Kod</label><input class="input uppercase" id="apt-code" name="code" placeholder="Örn: CNR" maxlength="10" required></div>
                    <div class="flex gap-2 pt-1">
                        <button type="button" data-toggle="apt-add-form" class="btn btn-secondary flex-1">İptal</button>
                        <button class="btn btn-primary flex-1"><x-icon name="plus" :size="14" />Ekle</button>
                    </div>
                </form>
            </div>

            <button type="button" data-toggle="apt-add-form" data-hide-self class="group flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 p-3.5 text-xs font-bold text-slate-500 transition-all hover:border-[#ff5a36] hover:bg-orange-50/40 hover:text-[#ff5a36]">
                <x-icon name="plus" :size="16" class="transition-transform group-hover:scale-110" />
                <span>Yeni Konut Ekle</span>
            </button>
            <a href="{{ route('sites.index') }}" class="block text-center text-xs font-semibold text-slate-400 hover:text-[#ff5a36]">Tüm konutları yönet &rarr;</a>
        </div>
    </div>
</div>

@if ($erp)<x-erp-nav :site="$site" />@endif

<div class="flex min-h-[calc(100vh-4rem)] flex-col">
    <main class="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
        <div class="print-full mx-auto max-w-[1720px] space-y-6">
            @if ($heading)
                <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200/80 pb-5">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ $heading }}</h1>
                        @if ($subheading)<p class="mt-1 text-xs text-slate-500">{{ $subheading }}</p>@endif
                    </div>
                    @isset($actions)<div class="no-print flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
                </div>
            @endif

            @if (session('status'))
                <div role="status" class="no-print flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">
                    <x-icon name="check" :size="16" class="text-emerald-600 shrink-0" />
                    <span>{{ session('status') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div role="alert" class="no-print flex items-center gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-800">
                    <x-icon name="alert-circle" :size="16" class="text-rose-600 shrink-0" />
                    <span>{{ session('error') }}</span>
                </div>
            @endif
            @if ($errors->any())
                <div role="alert" class="no-print rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800">
                    <ul class="list-disc space-y-0.5 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            {{ $slot }}
        </div>

        <div class="no-print mt-12 border-t border-slate-200/80 pt-6 text-center text-xs text-slate-400">
            &copy; {{ now()->year }} Memoshome ERP & Yönetim Sistemleri | Tüm Hakları Saklıdır.
        </div>
    </main>
</div>
</body>
</html>

