@props(['title' => null, 'heading' => null, 'subheading' => null])
@php
    $user = auth()->user();
    $route = request()->route();
    $routeName = (string) $route?->getName();
    $is = fn (string ...$patterns) => request()->routeIs(...$patterns);

    $switchRoute = $site && $is('site.*') && count($route->parameterNames()) === 1 && request()->isMethod('get') ? $routeName : 'site.dashboard';
    $siteRoute = fn (string $name) => $currentSite ? route($name, $currentSite) : route('sites.index');
    $initial = mb_strtoupper(mb_substr($user->name, 0, 1));
    $classicDashboard = $currentSite && $is('dashboard', 'site.dashboard') && ! request()->filled('q');
    $menuHelp = [
        'Yapı, Daire & Sakinler' => 'Bloklarınızı, dairelerinizi ve sakinleri buradan tanımlarsınız. Sisteme ilk başlarken gelmeniz gereken yer burası.',
        'Tahakkuk, Aidat & Sayaç' => 'Aidatı ve diğer borçları dairelere yazarsınız. Gecikme zammı ile sayaç faturaları da burada yönetilir.',
        'Tahsilat, Kasa & Banka' => 'Sakinden alınan ödemeyi kaydeder, makbuz keser, kasa ve banka hesaplarını izlersiniz.',
        'Muhasebe, Defter & Bütçe' => 'Yevmiye, mizan, bilanço, daire ekstreleri ve yıllık bütçe karşılaştırması gibi mali raporlar burada.',
        'Gider, Tedarikçi & İK' => 'Faturaları girip onaylar, tedarikçileri, personeli ve maaşları takip edersiniz.',
        'Saha, Envanter & Güvenlik' => 'Bakım görevleri, stok, demirbaş, ortak alan rezervasyonu, ziyaretçi kaydı ve arıza talepleri.',
        'Kurul, İletişim & Arşiv' => 'Genel kurul, karar defteri, duyuru, anket, toplu bildirim, borç hatırlatma ve belge arşivi.',
        'Rapor, Denetim & Ayarlar' => 'PDF ve Excel raporları alır, kimin ne yaptığını izler, site ayarlarını ve yöneticileri düzenlersiniz.',
    ];

    $railLinks = [
        ['Genel Portföy Panosu', 'layout-dashboard', route('dashboard'), $is('dashboard')],
        ['Site Finans Panosu', 'bar-chart-3', $siteRoute('site.dashboard'), $is('site.dashboard')],
    ];

    $railMenus = [
        ['Yapı, Daire & Sakinler', 'building-2', 'site.units.index', $is('sites.*', 'site.units.*', 'site.members.*', 'users.*', 'properties.*'), [
            ['Konut & Siteler', 'sites.index', 'building', true],
            ['Blok, Daire & Gruplar', 'site.units.index', 'building-2', false],
            ['Sakin & Üye Listesi', 'site.members.index', 'users', false],
            ['Sakin & Taşınma Geçmişi', 'site.members.history', 'clock', false],
            ['Sistem Kullanıcıları', 'users.index', 'user-plus', true],
            ['Emlak Portföyü', 'properties.index', 'home', true],
        ]],
        ['Tahakkuk, Aidat & Sayaç', 'file-text', 'site.debts.index', $is('site.dues.*', 'site.debts.*', 'site.late-fees.*', 'site.meters.*', 'site.meter-bills.*'), [
            ['Aidat & Otomatik Tahakkuk', 'site.dues.index', 'filter', false],
            ['Borçlandırma & Taksit / İcra', 'site.debts.index', 'circle-dollar-sign', false],
            ['Gecikme Zammı Motoru', 'site.late-fees.index', 'flame', false],
            ['Sayaç & Endeks Okuma', 'site.meters.index', 'zap', false],
            ['Sayaç Faturası Dağıtımı', 'site.meter-bills.index', 'receipt', false],
        ]],
        ['Tahsilat, Kasa & Banka', 'wallet', 'site.collections.index', $is('site.collections.*', 'site.cashboxes.*', 'site.bank.*', 'site.advances.*', 'site.finance'), [
            ['Tahsilat & Makbuz Kesimi', 'site.collections.index', 'hand-coins', false],
            ['Kasa & Banka Hesapları', 'site.cashboxes.index', 'wallet', false],
            ['Banka Ekstresi Eşleştirme', 'site.bank.index', 'landmark', false],
            ['Avans & Kredi Mahsup', 'site.advances.index', 'credit-card', false],
            ['Finansal Özet & Virman', 'site.finance', 'trending-up', false],
        ]],
        ['Muhasebe, Defter & Bütçe', 'database', 'site.income-expense', $is('site.income-expense', 'site.statements', 'site.accounting.*', 'site.ledger', 'site.budget.*'), [
            ['Gelir - Gider & Kategoriler', 'site.income-expense', 'trending-up', false],
            ['Daire Hesap Ekstreleri', 'site.statements', 'file-text', false],
            ['Yevmiye Defteri', 'site.ledger', 'clipboard-list', false],
            ['Kasa Defteri', 'site.accounting.cash-book', 'wallet', false],
            ['Mizan Tablosu', 'site.accounting.trial-balance', 'layout-list', false],
            ['Mali Durum / Bilanço', 'site.accounting.balance-sheet', 'bar-chart-3', false],
            ['Alacak Yaşlandırma', 'site.accounting.aging', 'clock', false],
            ['Bütçe & Gerçekleşen', 'site.budget.index', 'piggy-bank', false],
        ]],
        ['Gider, Tedarikçi & İK', 'receipt', 'site.expenses.index', $is('site.expenses.*', 'site.vendors.*', 'site.staff.*', 'site.payroll.*'), [
            ['Gider & Fatura Onayı', 'site.expenses.index', 'trending-down', false],
            ['Tedarikçi & Cari Hesaplar', 'site.vendors.index', 'briefcase', false],
            ['Personel & Görevliler', 'site.staff.index', 'users-round', false],
            ['Personel Maaş & Bordro', 'site.payroll.index', 'circle-dollar-sign', false],
        ]],
        ['Saha, Envanter & Güvenlik', 'package', 'site.tasks.index', $is('site.tasks.*', 'site.inventory.*', 'site.assets.*', 'site.reservations.*', 'site.security.*', 'site.tickets.*'), [
            ['Görev & Bakım Takibi', 'site.tasks.index', 'check-circle-2', false],
            ['Stok & Sarf Malzeme', 'site.inventory.index', 'package', false],
            ['Demirbaş & Zimmet Kaydı', 'site.assets.index', 'wrench', false],
            ['Ortak Alan Rezervasyonu', 'site.reservations.index', 'calendar-check', false],
            ['Güvenlik, Ziyaretçi & Kargo', 'site.security.index', 'shield-check', false],
            ['Arıza & Destek Talepleri', 'site.tickets.index', 'message-square', false],
        ]],
        ['Kurul, İletişim & Arşiv', 'megaphone', 'site.meetings.index', $is('site.meetings.*', 'site.decisions.*', 'site.announcements.*', 'site.polls.*', 'site.notifications.*', 'site.reminders.*', 'site.documents.*'), [
            ['Genel Kurul & Toplantılar', 'site.meetings.index', 'users', false],
            ['Karar Defteri', 'site.decisions.index', 'gavel', false],
            ['Duyuru Panosu', 'site.announcements.index', 'megaphone', false],
            ['Sakin Anketleri & Oylama', 'site.polls.index', 'star', false],
            ['Toplu Bildirim Gönderimi', 'site.notifications.index', 'bell', false],
            ['Otomatik Borç Hatırlatıcı', 'site.reminders.index', 'mail', false],
            ['Dijital Belge Arşivi', 'site.documents.index', 'file-text', false],
        ]],
        ['Rapor, Denetim & Ayarlar', 'settings', 'site.reports.index', $is('site.reports.*', 'site.audit', 'site.settings*'), [
            ['Rapor Merkezi (PDF / CSV)', 'site.reports.index', 'printer', false],
            ['İşlem & Denetim Kayıtları', 'site.audit', 'shield-alert', false],
            ['Site Ayarları & Yöneticiler', 'site.settings', 'settings', false],
        ]],
    ];
@endphp
<!DOCTYPE html>
<html lang="tr" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leans | {{ $title ?? $heading ?? 'Panel' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        (function () {
            var hud = localStorage.getItem('hud-mode') === 'classic' ? 'classic' : 'modern';
            var t = hud === 'classic' ? (localStorage.getItem('classic-theme') || 'light') : (localStorage.getItem('theme') || 'dark');
            document.documentElement.classList.remove('dark', 'light');
            document.documentElement.classList.add(t === 'light' ? 'light' : 'dark');
            document.documentElement.setAttribute('data-hud', hud);
            document.documentElement.setAttribute('data-accent', localStorage.getItem('classic-accent') || 'teal');
            document.documentElement.setAttribute('data-motion', localStorage.getItem('motion') || 'on');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#09090b] font-sans text-[#fafafa] antialiased selection:bg-white/15">

@include('components.dash.classic-nav')

<aside id="app-sidebar" class="hud-modern-only no-print rail-sidebar px-2">
    <div class="flex min-h-0 flex-1 flex-col gap-1.5">
        <div class="flex items-center justify-between px-1.5 pb-1">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3" aria-label="Leans">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-[#09090b] shadow-md transition-all duration-200 hover:scale-105 hover:shadow-[0_0_20px_rgba(255,255,255,0.3)]">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
                    </svg>
                </span>
                <div class="rail-text min-w-0">
                    <div class="flex items-center gap-1.5">
                        <p class="truncate text-[14px] font-extrabold tracking-tight text-white">leans<span class="text-orange-500">.</span></p>
                        <span class="rounded bg-orange-500/10 px-1 py-0.2 text-[9px] font-bold text-orange-400">SaaS</span>
                    </div>
                    <p class="truncate text-[10px] text-[#71717a]">{{ $currentSite?->name ?? 'Mülk & Finans Bulutu' }}</p>
                </div>
            </a>
            <button type="button" data-toggle="rail-pin" class="rail-text flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-[#71717a] transition-colors hover:bg-[#1c1c22] hover:text-white" title="Menüyü sabitle / daralt" aria-label="Menüyü sabitle">
                <x-icon name="chevron-right" :size="15" data-pin-icon class="transition-transform duration-200" />
            </button>
        </div>

        <button type="button" data-toggle="apt-modal" class="rail-row" aria-label="Konut seç">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-[#26262e] bg-[#141416] text-[#d4d4d8] transition-all duration-200">
                <x-icon name="plus" :size="15" />
            </span>
            <span class="rail-text truncate font-semibold text-white">Hızlı Konut Seç / Ekle</span>
        </button>

        <nav class="mt-1 flex-1 space-y-1 overflow-y-auto overflow-x-hidden pr-0.5 [scrollbar-width:none]" aria-label="Sol menü">
            @foreach ($railLinks as [$label, $icon, $href, $active])
                <a href="{{ $href }}" class="rail-row {{ $active ? 'active' : '' }}" aria-label="{{ $label }}" @if ($active) aria-current="page" @endif>
                    <span class="rail-icon">
                        <x-icon :name="$icon" :size="17" />
                    </span>
                    <span class="rail-text flex-1 truncate">{{ $label }}</span>
                </a>
            @endforeach

            @foreach ($railMenus as [$groupLabel, $icon, $primaryRoute, $active, $items])
                <div class="rail-group {{ $active ? 'is-open' : '' }}" data-rail-group>
                    <div class="rail-row {{ $active ? 'active' : '' }}" {{ tour(20 + $loop->index, $groupLabel, $menuHelp[$groupLabel]) }}>
                        <button type="button" data-rail-expand class="flex min-w-0 flex-1 items-center gap-3 text-left" aria-expanded="{{ $active ? 'true' : 'false' }}">
                            <span class="rail-icon">
                                <x-icon :name="$icon" :size="17" />
                            </span>
                            <span class="rail-text flex-1 truncate">{{ $groupLabel }}</span>
                            <span class="rail-text"><span class="rail-chevron"><x-icon name="chevron-down" :size="13" /></span></span>
                        </button>
                    </div>
                    <div class="rail-sub"><div class="rail-sub-inner">
                        @foreach ($items as $item)
                            @php
                                [$itemLabel, $rName, $subIcon] = $item;
                                $isGlobal = $item[3] ?? false;
                                $subActive = $is($rName.'*');
                                $itemHref = $isGlobal ? route($rName) : $siteRoute($rName);
                            @endphp
                            <a href="{{ $itemHref }}" class="rail-sub-link {{ $subActive ? 'active' : '' }}" @if ($subActive) aria-current="page" @endif>
                                <x-icon :name="$subIcon" :size="14" class="{{ $subActive ? 'text-emerald-400' : 'text-[#71717a]' }}" />
                                <span class="flex-1 truncate">{{ $itemLabel }}</span>
                                @if ($subActive)<i class="h-1.5 w-1.5 rounded-full bg-emerald-500"></i>@endif
                            </a>
                        @endforeach
                    </div></div>
                </div>
            @endforeach
        </nav>
    </div>

    <div class="mt-2 space-y-1 border-t border-[#18181b] pt-2">
        <a href="{{ $siteRoute('site.settings') }}" class="rail-row {{ $is('site.settings*') ? 'active' : '' }}" aria-label="Ayarlar">
            <span class="rail-icon">
                <x-icon name="settings" :size="17" />
            </span>
            <span class="rail-text flex-1 truncate">Site Ayarları</span>
        </a>

        <a href="{{ route('users.show', $user) }}" class="rail-row" aria-label="{{ $user->name }}">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-[#27272a] bg-[#16161a] text-[11px] font-semibold text-[#e4e4e7]">
                {{ $initial }}
            </span>
            <div class="rail-text min-w-0 flex-1">
                <p class="truncate text-xs font-semibold text-white">{{ $user->name }}</p>
                <p class="truncate text-[10px] text-[#71717a]">{{ $user->is_super_admin ? 'Süper admin' : 'Yönetici' }}</p>
            </div>
        </a>
    </div>
</aside>

<div id="apt-modal" class="no-print fixed inset-0 z-[200] hidden items-start justify-center bg-black/70 pt-16 backdrop-blur-xs sm:pt-24" role="dialog" aria-modal="true" aria-label="Yönetiminizdeki konutlar">
    <div class="mx-4 w-full max-w-lg overflow-hidden rounded-[24px] border border-[#242428] bg-[#141416] shadow-[0_24px_60px_rgba(0,0,0,0.85)]">
        <div class="flex items-center justify-between border-b border-[#1f1f23] px-6 py-4">
            <div>
                <h3 class="text-[15px] font-semibold text-white">Konutlar</h3>
                <p class="text-xs text-[#71717a]">Yönetmek istediğiniz site veya apartmanı seçin</p>
            </div>
            <button type="button" data-close="apt-modal" class="flex h-8 w-8 items-center justify-center rounded-full text-[#71717a] transition-colors hover:bg-[#1f1f23] hover:text-white" aria-label="Kapat"><x-icon name="x" :size="17" /></button>
        </div>
        <div class="max-h-[60vh] space-y-4 overflow-y-auto p-6">
            <div class="space-y-2">
                @foreach ($sites as $s)
                    @php $active = $currentSite?->id === $s->id; @endphp
                    <div class="group flex items-center justify-between rounded-2xl border p-3 transition-all duration-200 {{ $active ? 'border-[#3f3f46] bg-[#1c1c21]' : 'border-[#1f1f23] hover:border-[#2e2e36] hover:bg-[#18181c]' }}">
                        <a href="{{ route($switchRoute, $s) }}" class="flex flex-1 items-center gap-3 text-left">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $active ? 'bg-white text-[#09090b]' : 'bg-[#1f1f23] text-[#a1a1aa]' }}"><x-icon name="building-2" :size="17" /></span>
                            <span>
                                <span class="block text-[13.5px] font-medium text-white">{{ $s->name }}</span>
                                <span class="block text-xs text-[#71717a]">{{ $s->code }} · {{ $s->city ?? 'Şehir belirtilmedi' }}</span>
                            </span>
                        </a>
                        <div class="flex items-center gap-2">
                            @if ($user->is_super_admin || $s->owner_id === $user->id)
                                <form method="POST" action="{{ route('site.destroy', $s) }}" data-confirm-code="{{ $s->code }}" data-confirm-text="“{{ $s->name }}” konutu ve tüm verileri silinecek. Onaylamak için konut kodunu ({{ $s->code }}) yazın:">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="confirm" value="">
                                    <button class="flex h-8 w-8 items-center justify-center rounded-lg text-[#52525b] opacity-0 transition-all hover:bg-rose-500/15 hover:text-rose-400 group-hover:opacity-100 focus:opacity-100" title="Konutu sil" aria-label="{{ $s->name }} sil"><x-icon name="trash-2" :size="15" /></button>
                                </form>
                            @endif
                            @if ($active)<span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-[#09090b]"><x-icon name="check" :size="13" /></span>@endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div id="apt-add-form" class="hidden">
                <form method="POST" action="{{ route('sites.store') }}" class="space-y-3 rounded-2xl border border-[#242428] bg-[#0e0e10] p-4">
                    @csrf
                    <div><label class="label" for="apt-name">Konut adı</label><input class="input" id="apt-name" name="name" placeholder="Örn: Concept Rezidans" required></div>
                    <div><label class="label" for="apt-code">Kısa kod</label><input class="input uppercase" id="apt-code" name="code" placeholder="Örn: CNR" maxlength="10" required></div>
                    <div class="flex gap-2 pt-1">
                        <button type="button" data-toggle="apt-add-form" class="btn btn-secondary flex-1">İptal</button>
                        <button class="btn btn-primary flex-1"><x-icon name="plus" :size="14" />Ekle</button>
                    </div>
                </form>
            </div>

            <button type="button" data-toggle="apt-add-form" data-hide-self class="flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-[#27272a] p-3 text-xs font-medium text-[#8a8a93] transition-colors hover:border-[#52525b] hover:text-white">
                <x-icon name="plus" :size="15" /><span>Yeni konut ekle</span>
            </button>
            <a href="{{ route('sites.index') }}" class="block text-center text-xs text-[#71717a] hover:text-white">Tüm konutları yönet →</a>
        </div>
    </div>
</div>

@include('components.dash.settings-modal')
@include('components.dash.setup-guide')

<div id="app-shell" class="min-h-screen pl-[64px] transition-[padding] duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]">
    <header class="hud-modern-only no-print flex items-center justify-end gap-2 px-5 pt-4 sm:px-8">
        @include('components.dash.setup-button', ['buttonClass' => 'select-btn'])

        <button type="button" {{ tour(40, 'Arayüzü değiştir', 'Modern ve Klasik görünüm arasında geçiş yapar. Verileriniz değişmez, yalnızca ekran düzeni değişir.') }} data-toggle="hud" class="select-btn" title="HUD Görünümünü Değiştir (Modern / Klasik)" aria-label="HUD modu değiştir">
            <x-icon name="layout-dashboard" :size="14" class="text-teal-500" />
            <span data-hud-label>Klasik HUD</span>
        </button>

        <button type="button" data-toggle="theme" class="select-btn" title="Açık / Koyu Tema Değiştir" aria-label="Tema değiştir">
            <x-icon name="sun" :size="14" data-theme-sun class="text-amber-400" />
            <x-icon name="moon" :size="14" data-theme-moon class="hidden text-indigo-500" />
            <span data-theme-label>Açık Tema</span>
        </button>

        <button type="button" {{ tour(10, 'Site seçici', 'Yönettiğiniz siteler arasında buradan geçersiniz. Ekrandaki tüm rakamlar seçili siteye aittir. Yeni site de buradan eklenir.') }} data-toggle="apt-modal" class="inline-flex items-center gap-1.5 rounded-full bg-[#e4e4e7] px-3.5 py-1.5 text-xs font-semibold text-[#09090b] transition-all duration-200 hover:-translate-y-0.5 hover:bg-white hover:shadow-[0_0_20px_rgba(255,255,255,0.2)] active:scale-95">
            <x-icon name="building-2" :size="13" />
            <span class="max-w-[160px] truncate">{{ $currentSite?->name ?? 'Konut Seç' }}</span>
        </button>

        <button type="button" {{ tour(41, 'Görünüm ayarları', 'Arayüz, açık/koyu mod ve animasyon tercihleri burada. Bu anlatımı da buradan yeniden başlatabilirsiniz.') }} data-toggle="settings-modal" class="icon-btn" title="Görünüm ayarları" aria-label="Görünüm ayarları">
            <x-icon name="settings" :size="15" />
        </button>

        <a href="{{ $siteRoute('site.dashboard') }}" class="icon-btn" title="Site Panosu" aria-label="Site Panosu">
            <x-icon name="clock" :size="15" />
        </a>

        <details class="dropdown relative">
            <summary class="icon-btn" aria-label="Ara" title="Ara"><x-icon name="search" :size="15" /></summary>
            <form method="GET" action="{{ route('dashboard') }}" class="absolute right-0 top-11 z-50 w-72 rounded-2xl border border-[#242428] bg-[#141416] p-2 shadow-2xl">
                <label class="sr-only" for="top-q">Kullanıcı ara</label>
                <input id="top-q" name="q" class="input" placeholder="Kullanıcı, e-posta veya telefon…" autocomplete="off">
            </form>
        </details>

        <a href="{{ $siteRoute('site.tickets.index') }}" class="icon-btn" aria-label="Bildirimler" title="Açık destek talepleri" {{ tour(42, 'Destek talepleri', 'Sakinlerin açtığı arıza ve destek taleplerine gider. Açık talep varsa üzerinde işaret görünür.') }}>
            <x-icon name="bell" :size="15" />
            @if ($openTickets)
                <span class="absolute -right-0.5 -top-0.5 h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
            @endif
        </a>

        <details class="dropdown relative">
            <summary class="flex h-9 w-9 items-center justify-center rounded-full border border-[#242428] bg-[#141416] text-xs font-semibold text-[#e4e4e7] transition-all duration-200 hover:-translate-y-0.5 hover:border-[#3a3a44] hover:bg-[#1c1c20]">
                {{ $initial }}
            </summary>
            <div class="absolute right-0 top-11 z-50 w-52 rounded-2xl border border-[#242428] bg-[#141416] p-1.5 shadow-2xl">
                <div class="border-b border-[#1f1f23] px-3 py-2">
                    <p class="truncate text-xs font-semibold text-white">{{ $user->name }}</p>
                    <p class="text-[11px] text-[#71717a]">{{ $user->is_super_admin ? 'Süper admin' : 'Yönetici' }}</p>
                </div>
                <a href="{{ route('users.show', $user) }}" class="drop-item mt-1">Profilim</a>
                @if ($currentSite)<a href="{{ route('site.settings', $currentSite) }}" class="drop-item">Site ayarları</a>@endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="drop-item w-full text-rose-400 hover:text-rose-300">Çıkış yap</button>
                </form>
            </div>
        </details>
    </header>

    <main class="min-w-0">
        <div class="print-full mx-auto max-w-[1600px] space-y-4 px-5 pb-10 pt-2 sm:px-8" data-stagger-root>
            @if ($heading)
                <div @class(['reveal-up flex flex-wrap items-center justify-between gap-4 pb-1', 'hud-modern-only' => $classicDashboard]) style="--stagger: 0">
                    <div>
                        <h1 class="text-[24px] font-bold leading-tight tracking-tight text-white">{{ $heading }}</h1>
                        <p class="mt-1 text-[13px] text-[#71717a]">{{ $subheading ?? 'Tahakkuk eden gelirleri, tahsilatları ve bekleyen bakiyeleri takip edin.' }}</p>
                    </div>
                    @isset($actions)<div class="no-print flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
                </div>
            @endif

            @if (session('status'))
                <div role="status" class="no-print reveal-up flex items-center gap-2.5 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-300">
                    <x-icon name="check" :size="15" class="text-emerald-400" /><span>{{ session('status') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div role="alert" class="no-print reveal-up flex items-center gap-2.5 rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-xs font-medium text-rose-300">
                    <x-icon name="alert-circle" :size="15" class="text-rose-400" /><span>{{ session('error') }}</span>
                </div>
            @endif
            @if ($errors->any())
                <div role="alert" class="no-print reveal-up rounded-2xl border border-rose-500/20 bg-rose-500/10 p-4 text-xs font-medium text-rose-300">
                    <ul class="list-disc space-y-0.5 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @if ($classicDashboard)
                <div class="hud-classic-only">@include('components.dash.classic-hud', ['apartment' => $currentSite])</div>
                <div class="hud-modern-only space-y-4">{{ $slot }}</div>
            @else
                {{ $slot }}
            @endif
        </div>
    </main>
</div>
</body>
</html>
