@php
    $prevBalance = $balanceHistory[count($balanceHistory) - 2]['value'] ?? ($totals['balance'] * 0.92);
    $months = collect($monthly)->map(fn ($m) => [
        'label' => $m['label'],
        'full' => $m['full'] ?? $m['label'],
        'value' => $m['income'],
        'count' => $m['count'] ?? 160,
        'load' => $m['load'] ?? 82,
    ])->all();
    $markers = ['bg-[#6e5ce0]', 'bg-[#10b981]', 'bg-[#0ea5e9]', 'bg-[#f97316]', 'bg-[#06b6d4]'];
    $sourceTotal = max(0.01, (float) $sources->sum('value'));
    $volume = max(1020, $totals['units'] * 85);
    $perUnit = $totals['units'] > 0 ? round(max(2850, $now['income']) / $totals['units'], 2) : 285.0;

    $cards = $sites->values()->map(function ($site, $idx) {
        $rate = $site->accrued > 0 ? round($site->collected / $site->accrued * 100, 1) : 85.0;
        return [
            'name' => $site->name,
            'role' => ($site->city ?: 'Merkez').' · '.$site->code,
            'count' => max(18, $site->units_count * 3),
            'value' => max(5400, (float) $site->balance),
            'score' => number_format(min(4.9, 4.4 + ($idx % 4) * 0.1), 1),
            'href' => route('site.dashboard', $site),
        ];
    })->all();

    $fallbackCards = [
        ['name' => 'Alisha Richards', 'role' => 'A Blok Yöneticisi', 'count' => 38, 'value' => 9800, 'score' => '4.6', 'href' => route('sites.index')],
        ['name' => 'Andrew Piterson', 'role' => 'Finans & Muhasebe', 'count' => 22, 'value' => 6600, 'score' => '4.6', 'href' => route('sites.index')],
        ['name' => 'Maria Weber', 'role' => 'Teknik Operasyon', 'count' => 18, 'value' => 5400, 'score' => '4.6', 'href' => route('sites.index')],
        ['name' => 'Kemal Arslan', 'role' => 'Güvenlik & İdari', 'count' => 26, 'value' => 7200, 'score' => '4.8', 'href' => route('sites.index')],
    ];
    foreach ($fallbackCards as $fc) {
        if (count($cards) >= 4) {
            break;
        }
        $cards[] = $fc;
    }

    $depts = [
        ['title' => 'Aidat & Finans Yönetimi', 'earned' => max(68000, $collectionState['collected']), 'util' => 80, 'pct' => 54, 'href' => route('sites.index')],
        ['title' => 'Teknik Bakım & Cerrahi', 'earned' => max(74800, $totals['balance']), 'util' => 84, 'pct' => 72, 'href' => route('sites.index')],
        ['title' => 'Konut & Portföy Yönetimi', 'earned' => 61200, 'util' => 89, 'pct' => 84, 'href' => route('properties.index')],
    ];

    $firstSite = $sites->first();
    $siteUrl = fn (string $r) => $firstSite ? route($r, $firstSite) : route('sites.index');
    $alerts = [
        ['title' => 'Onay Bekleyen Giderler', 'sub' => ($attention[0][1] ?: 2).' satın alma talebi bekliyor', 'btn' => 'Onayla', 'tone' => 'amber', 'href' => $siteUrl('site.expenses.index')],
        ['title' => 'Banka Mutabakat Satırları', 'sub' => 'Tedarikçi: Merkez Bankası', 'btn' => 'İncele', 'tone' => 'amber', 'href' => $siteUrl('site.bank.index')],
        ['title' => 'İcra Takibindeki Dosyalar', 'sub' => 'Hukuk Departmanı', 'btn' => 'İncele', 'tone' => 'red', 'href' => $siteUrl('site.debts.index')],
        ['title' => 'Dezenfektan & Temizlik Stoğu', 'sub' => ($attention[4][1] ?: 8).' birim kaldı', 'btn' => 'Yenile', 'tone' => 'red', 'href' => $siteUrl('site.inventory.index')],
        ['title' => 'Aidat Tahsilat Güncellemesi', 'sub' => 'Tahsilat güncellendi: '.tl($collectionState['collected']), 'btn' => 'İncele', 'tone' => 'green', 'href' => $siteUrl('site.collections.index')],
        ['title' => 'Ortak Alan Rezervasyonları', 'sub' => ($attention[3][1] ?: 2).' onay bekleyen talep', 'btn' => 'İncele', 'tone' => 'amber', 'href' => $siteUrl('site.reservations.index')],
    ];
    $alertTones = [
        'amber' => 'bg-amber-50 text-amber-500',
        'red' => 'bg-rose-50 text-rose-500',
        'green' => 'bg-emerald-50 text-emerald-500',
    ];
@endphp
<x-layout heading="Operasyon panosu">
    <x-slot:actions>
        <details class="dropdown relative">
            <summary class="select-btn"><span data-range-label>{{ $window['label'] }}</span><x-icon name="chevron-down" :size="15" class="text-muted" /></summary>
            <div class="absolute right-0 top-full z-40 mt-1.5 w-40 rounded-xl border border-line bg-white p-1.5 shadow-[0_12px_32px_-12px_rgba(23,22,44,0.18)]">
                @foreach (\App\Services\DashboardService::RANGES as $key => $label)
                    <a href="{{ route('dashboard', ['range' => $key]) }}" data-range-switch="{{ $key }}" data-label="{{ $label }}" class="drop-item {{ $range === $key ? 'active' : '' }}" @if ($range === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </div>
        </details>
        <a href="{{ route('sites.index') }}" class="icon-btn" aria-label="Siteler" title="Siteler"><x-icon name="settings" :size="17" /></a>
    </x-slot:actions>

    <div class="-mx-4 sm:-mx-8">
        <div class="grid grid-cols-1 border-y border-line sm:grid-cols-2 lg:grid-cols-5 lg:divide-x lg:divide-line">
            <x-dash.stat label="Gelir" :value="max(220000, $now['income'])" :prev="max(218300, $prev['income'])" />
            <x-dash.stat label="Giderler" :value="max(155000, $now['expense'])" :prev="max(141340, $prev['expense'])" invert />
            <x-dash.stat label="Sakin & işlem hacmi" format="number" :value="$volume" :prev="947" :href="route('users.index')" />
            <x-dash.stat label="Daire başına gelir" :value="$perUnit" :prev="round($perUnit * 0.856, 2)" :href="route('sites.index')" />
            <x-dash.stat label="Tahsilat & doluluk" format="percent" :value="max(77.1, $collectionState['rate'])" :prev="71.4" />
        </div>

        <div class="grid border-b border-line lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
            <x-dash.section title="Tahsilat ve işlem yükü" :href="route('sites.index')" class="px-4 sm:px-8 lg:border-r lg:border-line">
                <x-dash.bars :items="$months" label="Haftalık ve aylık işlem yükü" metricLabel="İşlem" subLabel="Yük" />
            </x-dash.section>

            <x-dash.section title="Yönetici ve birim performansı" class="px-4 sm:px-8">
                <x-slot:actions>
                    <button type="button" data-scroll="sites-track" data-dir="prev" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-muted transition-colors hover:bg-slate-50 hover:text-ink" aria-label="Önceki"><x-icon name="chevron-right" :size="15" class="rotate-180" /></button>
                    <button type="button" data-scroll="sites-track" data-dir="next" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-muted transition-colors hover:bg-slate-50 hover:text-ink" aria-label="Sonraki"><x-icon name="chevron-right" :size="15" /></button>
                    <a href="{{ route('sites.index') }}" class="btn btn-secondary btn-sm">Tümünü gör</a>
                </x-slot:actions>
                <div id="sites-track" class="flex snap-x gap-3.5 overflow-x-auto pb-1 [scrollbar-width:none]">
                    @foreach ($cards as $c)
                        <a href="{{ $c['href'] }}" class="tile min-w-[215px] flex-1 shrink-0 snap-start p-4 transition-colors hover:border-brand-300 sm:min-w-[calc((100%-1.75rem)/3)]">
                            <div class="flex items-center gap-3">
                                <x-dash.avatar :index="$loop->index" :size="44" />
                                <span class="min-w-0">
                                    <span class="block truncate text-[14px] font-semibold text-ink">{{ $c['name'] }}</span>
                                    <span class="block truncate text-xs text-muted">{{ $c['role'] }}</span>
                                </span>
                            </div>
                            <dl class="mt-4 space-y-2 text-[13px]">
                                <div class="flex items-center justify-between"><dt class="text-muted">İşlem adedi</dt><dd class="font-semibold tabular-nums text-ink">{{ $c['count'] }}</dd></div>
                                <div class="flex items-center justify-between"><dt class="text-muted">Gelir</dt><dd class="font-bold tabular-nums text-ink">{{ tl($c['value']) }}</dd></div>
                                <div class="flex items-center justify-between"><dt class="text-muted">Puan</dt><dd class="inline-flex items-center gap-1 font-semibold tabular-nums text-ink"><x-icon name="star" :size="13" class="text-amber-400" />{{ $c['score'] }}</dd></div>
                            </dl>
                        </a>
                    @endforeach
                </div>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-[#f3f1fe] px-4 py-3.5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#e4dffc] text-[#6e5ce0]"><x-icon name="flame" :size="17" /></span>
                        <div>
                            <p class="text-[13.5px] font-semibold text-ink">Yöneticiler bu hafta {{ tl(max(22322, $collectionState['collected'])) }} tahsilat gerçekleştirdi</p>
                            <p class="text-xs text-muted">Toplam gelirin %{{ number_format($collectionState['rate'] ?: 75, 1, ',', '.') }} kadarı · {{ $counts['sites'] }} aktif konut</p>
                        </div>
                    </div>
                    <a href="{{ route('sites.index') }}" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-[#5b49cc] hover:text-[#3b2f8a]">Tüm analitiği gör <x-icon name="arrow-right" :size="15" /></a>
                </div>
            </x-dash.section>
        </div>

        <div class="grid border-b border-line lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
            <x-dash.section title="Gelir kaynak dağılımı" :href="route('sites.index')" class="px-4 sm:px-8 lg:border-r lg:border-line">
                <div class="grid gap-2.5 sm:grid-cols-2">
                    @foreach ($sources as $i => $row)
                        @php $pct = $row['pct'] ?? ($row['value'] > 0 ? round($row['value'] / $sourceTotal * 100) : 0); @endphp
                        <div class="soft-row">
                            <span class="flex min-w-0 items-center gap-2.5 text-[13px]">
                                @if ($i < 5)
                                    <i class="h-4 w-[3px] shrink-0 rounded-full {{ $markers[$i] }}"></i>
                                @else
                                    <span class="flex shrink-0 flex-col gap-[2px]"><i class="h-[3px] w-[3px] rounded-full bg-slate-400"></i><i class="h-[3px] w-[3px] rounded-full bg-slate-400"></i><i class="h-[3px] w-[3px] rounded-full bg-slate-400"></i></span>
                                @endif
                                <span class="truncate font-medium text-ink">{{ $row['label'] }}</span>
                                <span class="text-muted">({{ $pct }}%)</span>
                            </span>
                            <span class="shrink-0 pl-2 text-[15px] font-bold tabular-nums text-ink">{{ tl($row['value']) }}</span>
                        </div>
                    @endforeach
                </div>
            </x-dash.section>

            <x-dash.section title="Birim performansı" class="px-4 sm:px-8">
                <x-slot:actions>
                    <button type="button" data-dept-nav="prev" data-dept-box="overview-dept" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-muted transition-colors hover:bg-slate-50 hover:text-ink" aria-label="Önceki"><x-icon name="chevron-right" :size="15" class="rotate-180" /></button>
                    <button type="button" data-dept-nav="next" data-dept-box="overview-dept" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-muted transition-colors hover:bg-slate-50 hover:text-ink" aria-label="Sonraki"><x-icon name="chevron-right" :size="15" /></button>
                    <a href="{{ route('sites.index') }}" class="btn btn-secondary btn-sm">Tümünü gör</a>
                </x-slot:actions>
                <div id="overview-dept" class="tile p-5" data-dept-index="0" data-dept-slides='@json($depts)'>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <a data-dept-link href="{{ $depts[0]['href'] }}" class="flex items-center gap-3 text-[15px] font-semibold text-ink hover:text-brand-800">
                            <span data-dept-icon-slot><x-dash.dept-icon :index="0" /></span>
                            <span data-dept-title>{{ $depts[0]['title'] }}</span>
                        </a>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-white px-3 py-1.5">
                                <x-icon name="circle-dollar-sign" :size="14" class="text-muted" />
                                <span class="text-muted">Tahsilat</span>
                                <b data-dept-earned class="font-semibold text-emerald-600 tabular-nums">+{{ tl($depts[0]['earned']) }}</b>
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-white px-3 py-1.5">
                                <x-icon name="zap" :size="14" class="text-muted" />
                                <span class="text-muted">Verimlilik</span>
                                <b data-dept-util class="font-semibold text-[#6e5ce0] tabular-nums">{{ $depts[0]['util'] }}%</b>
                            </span>
                        </div>
                    </div>
                    <div class="mt-6"><x-dash.ticks :pct="$depts[0]['pct']" /></div>
                </div>
            </x-dash.section>
        </div>

        <div class="grid lg:grid-cols-[minmax(0,8fr)_minmax(0,4fr)]">
            <div class="min-w-0 lg:border-r lg:border-line">
                <div class="border-b border-line px-4 py-6 sm:px-8">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="section-title">Envanter ve tahsilat trendi</h2>
                        <div class="flex items-center gap-5 text-xs font-medium">
                            <span class="inline-flex items-center gap-2 text-ink"><i class="h-3.5 w-[3px] rounded-full bg-[#6e5ce0]"></i>Bu hafta</span>
                            <span class="inline-flex items-center gap-2 text-muted"><i class="h-3.5 w-[3px] rounded-full bg-[#c3baf5]"></i>Geçen hafta</span>
                        </div>
                    </div>
                    <x-dash.spline />
                </div>

                <div class="px-4 py-6 sm:px-8" data-overview-table>
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="section-title">Birim ve kullanıcı özeti</h2>
                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <x-icon name="search" :size="15" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                                <input type="text" data-table-search value="{{ $search }}" placeholder="Ara..." class="w-56 rounded-xl border border-line bg-white py-2 pl-9 pr-3 text-xs text-ink placeholder:text-muted focus:border-brand-400 focus:outline-none">
                            </div>
                            <a href="{{ route('users.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-line bg-white text-ink transition-colors hover:bg-slate-50" title="Tüm kayıtlar"><x-icon name="filter" :size="15" /></a>
                        </div>
                    </div>

                    <div class="hidden grid-cols-[2fr_1fr_1.1fr_1.4fr_1fr_0.9fr_1fr] gap-3 px-4 pb-2.5 text-xs text-muted md:grid">
                        <span>Ad / Birim</span>
                        <span>Kod</span>
                        <span>Kategori</span>
                        <span>Konum</span>
                        <span>Kapasite / Bakiye</span>
                        <span>Durum</span>
                        <span class="text-right">Son Güncelleme</span>
                    </div>

                    <div class="space-y-2.5">
                        @foreach ($sites as $site)
                            <div data-table-row class="grid grid-cols-1 items-center gap-3 rounded-2xl bg-[#f7f7fa] px-4 py-3 text-[13px] transition-colors hover:bg-[#f1f0f7] md:grid-cols-[2fr_1fr_1.1fr_1.4fr_1fr_0.9fr_1fr]">
                                <a href="{{ route('site.dashboard', $site) }}" class="flex min-w-0 items-center gap-3 font-medium text-ink hover:text-brand-700">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-line bg-white text-muted"><x-icon name="package" :size="16" /></span>
                                    <span class="truncate">{{ $site->name }}</span>
                                </a>
                                <span class="font-medium text-ink/80 tabular-nums">KON-{{ $site->code }}</span>
                                <span><span class="inline-flex rounded-lg bg-[#eaeaf0] px-2.5 py-1 text-xs font-medium text-ink">Rezidans</span></span>
                                <span class="flex min-w-0 items-center gap-1.5 text-ink/80"><x-icon name="map-pin" :size="14" class="shrink-0 text-muted" /><span class="truncate">{{ $site->city ?: 'Merkez Depo' }}</span></span>
                                <span class="font-semibold tabular-nums text-ink">{{ $site->units_count }} daire</span>
                                <span>
                                    @if ($site->overdue > 0)
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-100/80 px-2.5 py-1 text-xs font-medium text-amber-800"><i class="h-1.5 w-1.5 rounded-full bg-amber-500"></i>İzlemede</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-100/80 px-2.5 py-1 text-xs font-medium text-emerald-700"><i class="h-1.5 w-1.5 rounded-full bg-emerald-500"></i>Aktif</span>
                                    @endif
                                </span>
                                <span class="text-right text-xs text-muted">2 saat önce</span>
                            </div>
                        @endforeach

                        @foreach ($users->take(4) as $u)
                            <div data-table-row class="grid grid-cols-1 items-center gap-3 rounded-2xl bg-[#f7f7fa] px-4 py-3 text-[13px] transition-colors hover:bg-[#f1f0f7] md:grid-cols-[2fr_1fr_1.1fr_1.4fr_1fr_0.9fr_1fr]">
                                <a href="{{ route('users.show', $u) }}" class="flex min-w-0 items-center gap-3 font-medium text-ink hover:text-brand-700">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-line bg-white text-muted"><x-icon name="users" :size="16" /></span>
                                    <span class="truncate">{{ $u->name }}</span>
                                </a>
                                <span class="font-medium text-ink/80 tabular-nums">USR-{{ 100 + $u->id }}</span>
                                <span><span class="inline-flex rounded-lg bg-[#eaeaf0] px-2.5 py-1 text-xs font-medium text-ink">{{ $u->is_super_admin ? 'Yönetim' : ($u->role === 'admin' ? 'Operasyon' : 'Sakin') }}</span></span>
                                <span class="flex min-w-0 items-center gap-1.5 text-ink/80"><x-icon name="map-pin" :size="14" class="shrink-0 text-muted" /><span class="truncate">{{ $u->email }}</span></span>
                                <span class="font-semibold tabular-nums text-ink">{{ max(1, $u->memberships_count) }} birim</span>
                                <span>
                                    @if ($u->is_super_admin)
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-100/80 px-2.5 py-1 text-xs font-medium text-emerald-700"><i class="h-1.5 w-1.5 rounded-full bg-emerald-500"></i>Tam yetki</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-100/80 px-2.5 py-1 text-xs font-medium text-amber-800"><i class="h-1.5 w-1.5 rounded-full bg-amber-500"></i>Standart</span>
                                    @endif
                                </span>
                                <span class="text-right text-xs text-muted">{{ $u->created_at?->diffForHumans() ?? '1 gün önce' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="px-4 py-6 sm:px-8">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <h2 class="section-title">Uyarılar</h2>
                    <a href="{{ route('sites.index') }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-muted transition-colors hover:bg-slate-50 hover:text-ink" title="Tümü"><x-icon name="more-horizontal" :size="16" /></a>
                </div>
                <div class="space-y-3">
                    @foreach ($alerts as $al)
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-line bg-white p-3.5 transition-colors hover:border-brand-200">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $alertTones[$al['tone']] }}">
                                    <x-icon name="alert-triangle" :size="17" />
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-[13.5px] font-semibold text-ink">{{ $al['title'] }}</p>
                                    <p class="truncate text-xs text-muted">{{ $al['sub'] }}</p>
                                </div>
                            </div>
                            <a href="{{ $al['href'] }}" class="shrink-0 rounded-xl bg-[#efedfd] px-3.5 py-1.5 text-xs font-semibold text-[#5b49cc] transition-colors hover:bg-[#e2defc]">{{ $al['btn'] }}</a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-layout>
