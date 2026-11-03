@props(['site'])
@php
    // Eski "Yönetim & ERP Merkezi" ikinci çubuğu: düz sekmeler + açılır menüler
    $l = fn (string $route) => route($route, $site);
    $tabs = [
        ['Genel Durum', 'layout-dashboard', 'site.dashboard'],
        ['Raporlar', 'bar-chart-3', 'site.reports.*'],
        ['Üyeler', 'users', 'site.members.*'],
        ['Üniteler', 'building-2', 'site.units.*'],
    ];
    $groups = [
        ['Finansal İşlemler', 'wallet', [
            ['Tahsilat İşlemleri', 'site.collections.index', 'site.collections.*'],
            ['Borçlandırma & Tahakkuk', 'site.debts.index', 'site.debts.*'],
            ['Gider Yönetimi', 'site.expenses.index', 'site.expenses.*'],
            ['Kasa Hareketleri', 'site.cashboxes.index', 'site.cashboxes.*'],
            ['Firma / Tedarikçiler', 'site.vendors.index', 'site.vendors.*'],
            ['Gelir Gider Tablosu', 'site.income-expense', 'site.income-expense'],
            ['Daire Hesap Ekstreleri', 'site.statements', 'site.statements'],
            ['Finans Özeti', 'site.finance', 'site.finance'],
            ['Yevmiye Defteri', 'site.ledger', 'site.ledger'],
        ]],
        ['Operasyonel Plan', 'calendar', [
            ['Otomasyon Paneli', 'site.dues.index', 'site.dues.*'],
            ['Görev Atama', 'site.tasks.index', 'site.tasks.*'],
            ['Bildirim Gönderimi', 'site.notifications.index', 'site.notifications.*'],
            ['Borç Hatırlatıcı', 'site.reminders.index', 'site.reminders.*'],
        ]],
        ['İdari Yönetim', 'briefcase', [
            ['Duyuru İşlemleri', 'site.announcements.index', 'site.announcements.*'],
            ['Anketler', 'site.polls.index', 'site.polls.*'],
            ['Karar Defteri', 'site.decisions.index', 'site.decisions.*'],
            ['Stok/Envanter', 'site.inventory.index', 'site.inventory.*'],
        ]],
        ['Sistem Ayarları', 'settings', [
            ['Aidat ve Borçlanma Motoru', 'site.dues.index', 'site.dues.*'],
            ['Kasa, Banka ve Varlıklar', 'site.cashboxes.index', 'site.cashboxes.*'],
            ['Personel Hesapları', 'site.staff.index', 'site.staff.*'],
            ['Sayaç Tanımları', 'site.meters.index', 'site.meters.*'],
            ['Site Ayarları', 'site.settings', 'site.settings*'],
            ['İşlem Kayıtları', 'site.audit', 'site.audit'],
        ]],
    ];
@endphp
<div class="no-print sticky top-16 z-40 border-b border-slate-200/80 bg-white/95 backdrop-blur-md">
    <div class="mx-auto max-w-[1720px] px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between py-2.5 lg:hidden">
            <span class="truncate text-xs font-bold text-[#ff5a36]">Yönetim & ERP Merkezi · {{ $site->code }}</span>
            <button type="button" class="btn btn-secondary btn-sm" data-toggle="erp-mobile-menu" aria-controls="erp-mobile-menu" aria-expanded="false">Menü</button>
        </div>
        <div id="erp-mobile-menu" class="hidden max-h-[70vh] overflow-y-auto pb-3 lg:hidden">
            @foreach ($tabs as [$label, $icon, $pattern])
                <a href="{{ $l($pattern === 'site.dashboard' ? 'site.dashboard' : str_replace('.*', '.index', $pattern)) }}" class="erp-drop-item {{ request()->routeIs($pattern) ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
            @foreach ($groups as [$label, $icon, $items])
                <p class="px-5 pb-1 pt-3 text-[10px] font-bold uppercase tracking-wider text-[#ff5a36]">{{ $label }}</p>
                @foreach ($items as [$itemLabel, $route, $pattern])
                    <a href="{{ $l($route) }}" class="erp-drop-item {{ request()->routeIs($pattern) ? 'active' : '' }}">{{ $itemLabel }}</a>
                @endforeach
            @endforeach
        </div>

        <nav class="hidden items-center gap-1 py-0 lg:flex overflow-x-auto" aria-label="Yönetim ve ERP merkezi">
            @foreach ($tabs as [$label, $icon, $pattern])
                <a href="{{ $l($pattern === 'site.dashboard' ? 'site.dashboard' : str_replace('.*', '.index', $pattern)) }}" class="erp-tab {{ request()->routeIs($pattern) ? 'active' : '' }}">
                    <x-icon :name="$icon" :size="15" class="{{ request()->routeIs($pattern) ? 'text-[#ff5a36]' : 'text-slate-400' }}" />
                    <span>{{ $label }}</span>
                </a>
            @endforeach
            <span class="mx-2 h-5 w-px self-center bg-slate-200"></span>
            @foreach ($groups as [$label, $icon, $items])
                @php $groupActive = collect($items)->contains(fn ($i) => request()->routeIs($i[2])); @endphp
                <div class="group relative">
                    <button type="button" class="erp-tab {{ $groupActive ? 'active' : '' }}" aria-haspopup="true">
                        <x-icon :name="$icon" :size="15" class="{{ $groupActive ? 'text-[#ff5a36]' : 'text-slate-400' }}" />
                        <span>{{ $label }}</span>
                        <x-icon name="chevron-down" :size="12" class="ml-0.5 text-slate-400 transition-transform duration-200 group-hover:rotate-180 group-focus-within:rotate-180" />
                    </button>
                    <div class="invisible absolute left-0 top-full z-[100] min-w-[260px] pt-1 opacity-0 transition-all group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100">
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white py-2 shadow-xl">
                            <div class="mb-1 border-b border-slate-100 px-5 pb-2 pt-1"><p class="text-[10px] font-bold uppercase tracking-wider text-[#ff5a36]">{{ $label }}</p></div>
                            @foreach ($items as [$itemLabel, $route, $pattern])
                                <a href="{{ $l($route) }}" class="erp-drop-item group/item {{ request()->routeIs($pattern) ? 'active' : '' }}">
                                    <span>{{ $itemLabel }}</span>
                                    @if (request()->routeIs($pattern))
                                        <span class="h-1.5 w-1.5 rounded-full bg-[#ff5a36]"></span>
                                    @else
                                        <x-icon name="chevron-right" :size="12" class="-translate-x-1 text-[#ff5a36] opacity-0 transition-all group-hover/item:translate-x-0 group-hover/item:opacity-100" />
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </nav>
    </div>
</div>

