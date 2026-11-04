@props(['site'])
@php
    $l = fn (string $route) => route($route, $site);
    $is = fn (string $pattern) => request()->routeIs($pattern);
    $main = [
        ['Genel Durum', 'layout-dashboard', 'site.dashboard', 'site.dashboard'],
        ['Raporlar', 'bar-chart-3', 'site.reports.index', 'site.reports.*'],
        ['Üyeler', 'users', 'site.members.index', 'site.members.*'],
        ['Üniteler', 'building-2', 'site.units.index', 'site.units.*'],
    ];
    $groups = [
        ['Finans', 'wallet', [
            ['Tahsilat İşlemleri', 'site.collections.index', 'site.collections.*'],
            ['Borçlandırma & Tahakkuk', 'site.debts.index', 'site.debts.*'],
            ['Gecikme Zammı', 'site.late-fees.index', 'site.late-fees.*'],
            ['Avans & Mahsup', 'site.advances.index', 'site.advances.*'],
            ['Gider Yönetimi', 'site.expenses.index', 'site.expenses.*'],
            ['Banka Hareketleri', 'site.bank.index', 'site.bank.*'],
            ['Kasa Hareketleri', 'site.cashboxes.index', 'site.cashboxes.*'],
            ['Firma / Tedarikçiler', 'site.vendors.index', 'site.vendors.*'],
            ['Sayaç Faturası Dağıtımı', 'site.meter-bills.index', 'site.meter-bills.*'],
            ['Finans Özeti', 'site.finance', 'site.finance'],
        ]],
        ['Muhasebe', 'receipt', [
            ['Gelir Gider Tablosu', 'site.income-expense', 'site.income-expense'],
            ['Daire Hesap Ekstreleri', 'site.statements', 'site.statements'],
            ['Mali Durum Tablosu', 'site.accounting.balance-sheet', 'site.accounting.balance-sheet'],
            ['Mizan', 'site.accounting.trial-balance', 'site.accounting.trial-balance'],
            ['Kasa Defteri', 'site.accounting.cash-book', 'site.accounting.cash-book'],
            ['Yevmiye Defteri', 'site.ledger', 'site.ledger'],
            ['Alacak Yaşlandırma', 'site.accounting.aging', 'site.accounting.aging'],
            ['Bütçe & Gerçekleşen', 'site.budget.index', 'site.budget.*'],
        ]],
        ['Operasyon', 'calendar', [
            ['Otomasyon Paneli', 'site.dues.index', 'site.dues.*'],
            ['Görev Atama', 'site.tasks.index', 'site.tasks.*'],
            ['Rezervasyonlar', 'site.reservations.index', 'site.reservations.*'],
            ['Bildirim Gönderimi', 'site.notifications.index', 'site.notifications.*'],
            ['Borç Hatırlatıcı', 'site.reminders.index', 'site.reminders.*'],
        ]],
        ['İdari', 'briefcase', [
            ['Anketler', 'site.polls.index', 'site.polls.*'],
            ['Toplantılar', 'site.meetings.index', 'site.meetings.*'],
            ['Karar Defteri', 'site.decisions.index', 'site.decisions.*'],
            ['Belge Arşivi', 'site.documents.index', 'site.documents.*'],
            ['Stok/Envanter', 'site.inventory.index', 'site.inventory.*'],
            ['Demirbaş Kaydı', 'site.assets.index', 'site.assets.*'],
        ]],
    ];
    $settings = ['Ayarlar', 'settings', [
        ['Personel Hesapları', 'site.staff.index', 'site.staff.*'],
        ['Personel Bordrosu', 'site.payroll.index', 'site.payroll.*'],
        ['Sayaç Tanımları', 'site.meters.index', 'site.meters.*'],
        ['Site Ayarları & Yetkiler', 'site.settings', 'site.settings*'],
        ['İşlem Kayıtları', 'site.audit', 'site.audit'],
    ]];
@endphp
@foreach ([$main[0]] as [$label, $icon, $route, $pattern])
    <a href="{{ $l($route) }}" class="tab-pill {{ $is($pattern) ? 'active' : '' }}" @if ($is($pattern)) aria-current="page" @endif><x-icon :name="$icon" :size="16" /><span>{{ $label }}</span></a>
@endforeach
@foreach ([...$groups, $settings] as [$label, $icon, $items])
    @php $open = collect($items)->contains(fn ($i) => $is($i[2])); @endphp
    <details class="dropdown relative">
        <summary class="tab-pill {{ $open ? 'active' : '' }}"><x-icon :name="$icon" :size="16" /><span>{{ $label }}</span><x-icon name="chevron-down" :size="14" class="-ml-0.5" /></summary>
        <div class="absolute left-0 top-full z-50 mt-1.5 w-64 rounded-xl border border-line bg-white p-1.5 shadow-[0_12px_32px_-12px_rgba(23,22,44,0.18)]">
            @foreach ($items as [$itemLabel, $route, $pattern])
                <a href="{{ $l($route) }}" class="drop-item {{ $is($pattern) ? 'active' : '' }}" @if ($is($pattern)) aria-current="page" @endif>{{ $itemLabel }}</a>
            @endforeach
        </div>
    </details>
@endforeach
@foreach (array_slice($main, 1) as [$label, $icon, $route, $pattern])
    <a href="{{ $l($route) }}" class="tab-pill {{ $is($pattern) ? 'active' : '' }}" @if ($is($pattern)) aria-current="page" @endif><x-icon :name="$icon" :size="16" /><span>{{ $label }}</span></a>
@endforeach
<span class="mx-1 hidden h-6 w-px bg-line lg:block"></span>
<a href="{{ route('dashboard') }}" class="tab-pill"><x-icon name="home" :size="16" /><span>Emlak paneli</span></a>
