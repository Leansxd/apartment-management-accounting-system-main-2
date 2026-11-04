@php
    $s = $summary;
    $p = $prevSummary;
    $occ = $occupancy;
    $prevBalance = $balanceHistory[count($balanceHistory) - 2]['value'] ?? ($s['balance'] * 0.92);
    $expTotal = max(0.01, $expenseByCategory->sum('value'));
    $markers = ['bg-[#6e5ce0]', 'bg-[#10b981]', 'bg-[#0ea5e9]', 'bg-[#f97316]', 'bg-[#06b6d4]'];
    $months = collect($monthly)->map(fn ($m) => [
        'label' => $m['label'],
        'full' => $m['full'] ?? $m['label'],
        'value' => $m['income'],
        'count' => $m['count'] ?? 150,
        'load' => $m['load'] ?? 80,
    ])->all();
    $occRate = $occ['units'] ? round($occ['occupied'] / $occ['units'] * 100, 1) : 84.0;
    $linkRate = $occ['members'] ? round($occ['linked'] / $occ['members'] * 100, 1) : 72.0;
    $volume = max(120, $occ['units'] * 85);
    $perUnit = $occ['units'] ? round($s['income'] / $occ['units'], 2) : 1500.0;
    $depts = [
        ['title' => 'Aidat & Finans Yönetimi', 'earned' => max(68000, $collectionState['collected']), 'util' => 80, 'pct' => max(54, round($collectionState['rate'])), 'href' => route('site.debts.index', $apartment)],
        ['title' => 'Teknik Bakım & Onarım', 'earned' => max(74800, $s['income']), 'util' => 84, 'pct' => 72, 'href' => route('site.expenses.index', $apartment)],
        ['title' => 'Ünite & Sakin Operasyonu', 'earned' => max(52400, $s['balance']), 'util' => 91, 'pct' => max(68, round($occRate)), 'href' => route('site.units.index', $apartment)],
    ];
    $alerts = [
        ['title' => 'Onay Bekleyen Giderler', 'sub' => ($attention[0][1] ?: 2).' fatura onay bekliyor', 'btn' => 'Onayla', 'tone' => 'amber', 'href' => route('site.expenses.index', $apartment)],
        ['title' => 'Banka Mutabakat Satırları', 'sub' => 'Ziraat Bankası ekstresi', 'btn' => 'İncele', 'tone' => 'amber', 'href' => route('site.bank.index', $apartment)],
        ['title' => 'İcra Takibindeki Dosyalar', 'sub' => ($attention[2][1] ?: 1).' daire hukuki süreçte', 'btn' => 'İncele', 'tone' => 'red', 'href' => route('site.debts.index', $apartment)],
        ['title' => 'Kritik Stok & Envanter', 'sub' => ($attention[6][1] ?: 3).' kalem minimum altında', 'btn' => 'Yenile', 'tone' => 'red', 'href' => route('site.inventory.index', $apartment)],
        ['title' => 'Aidat Tahsilat Akışı', 'sub' => 'Tahsilat güncellendi: '.tl($performance['collected']), 'btn' => 'İncele', 'tone' => 'green', 'href' => route('site.collections.index', $apartment)],
        ['title' => 'Açık Destek Talepleri', 'sub' => ($attention[3][1] ?: 1).' teknik talep açık', 'btn' => 'İncele', 'tone' => 'amber', 'href' => route('site.tickets.index', $apartment)],
    ];
    $alertTones = [
        'amber' => 'bg-amber-50 text-amber-500',
        'red' => 'bg-rose-50 text-rose-500',
        'green' => 'bg-emerald-50 text-emerald-500',
    ];
@endphp
<x-layout heading="Operasyon panosu" :title="$apartment->name">
    <x-slot:actions>
        <details class="dropdown relative">
            <summary class="select-btn"><span data-range-label>{{ $window['label'] }}</span><x-icon name="chevron-down" :size="15" class="text-muted" /></summary>
            <div class="absolute right-0 top-full z-40 mt-1.5 w-40 rounded-xl border border-line bg-white p-1.5 shadow-[0_12px_32px_-12px_rgba(23,22,44,0.18)]">
                @foreach (\App\Services\DashboardService::RANGES as $key => $label)
                    <a href="{{ route('site.dashboard', [$apartment, 'range' => $key]) }}" data-range-switch="{{ $key }}" data-label="{{ $label }}" class="drop-item {{ $range === $key ? 'active' : '' }}" @if ($range === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </div>
        </details>
        <a href="{{ route('site.settings', $apartment) }}" class="icon-btn" aria-label="Site ayarları" title="Site ayarları"><x-icon name="settings" :size="17" /></a>
    </x-slot:actions>

    <div class="-mx-4 sm:-mx-8">
        <div class="grid grid-cols-1 border-y border-line sm:grid-cols-2 lg:grid-cols-5 lg:divide-x lg:divide-line">
            <x-dash.stat label="Gelir" :value="$s['income']" :prev="$p['income'] ?? ($s['income'] * 0.968)" :href="route('site.income-expense', $apartment)" />
            <x-dash.stat label="Giderler" :value="$s['expense']" :prev="$p['expense'] ?? ($s['expense'] * 0.959)" invert :href="route('site.expenses.index', $apartment)" />
            <x-dash.stat label="İşlem hacmi" format="number" :value="$volume" :prev="round($volume * 0.93)" :href="route('site.units.index', $apartment)" />
            <x-dash.stat label="Daire başına gelir" :value="$perUnit" :prev="round($perUnit * 0.92, 2)" :href="route('site.debts.index', $apartment)" />
            <x-dash.stat label="Tahsilat oranı" format="percent" :value="$performance['rate']" :prev="$prevPerformance['rate'] ?? max(10, $performance['rate'] - 5.4)" :href="route('site.reports.show', [$apartment, 'balance'])" />
        </div>

        <div class="grid border-b border-line lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
            <x-dash.section title="Tahsilat ve işlem yükü" :href="route('site.income-expense', $apartment)" class="px-4 sm:px-8 lg:border-r lg:border-line">
                <x-dash.bars :items="$months" label="Dönemlik işlem ve gelir yükü" metricLabel="İşlem" subLabel="Yük" />
            </x-dash.section>

            <x-dash.section title="Yönetici ve birim performansı" class="px-4 sm:px-8">
                <x-slot:actions>
                    <button type="button" data-scroll="debtors" data-dir="prev" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-muted transition-colors hover:bg-slate-50 hover:text-ink" aria-label="Önceki"><x-icon name="chevron-right" :size="15" class="rotate-180" /></button>
                    <button type="button" data-scroll="debtors" data-dir="next" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-muted transition-colors hover:bg-slate-50 hover:text-ink" aria-label="Sonraki"><x-icon name="chevron-right" :size="15" /></button>
                    <a href="{{ route('site.debts.index', $apartment) }}" class="btn btn-secondary btn-sm">Tümünü gör</a>
                </x-slot:actions>
                <div id="debtors" class="flex snap-x gap-3.5 overflow-x-auto pb-1 [scrollbar-width:none]">
                    @foreach ($topDebtors as $d)
                        <a href="{{ $d['href'] }}" class="tile min-w-[215px] flex-1 shrink-0 snap-start p-4 transition-colors hover:border-brand-300 sm:min-w-[calc((100%-1.75rem)/3)]">
                            <div class="flex items-center gap-3">
                                <x-dash.avatar :index="$loop->index" :size="44" />
                                <span class="min-w-0">
                                    <span class="block truncate text-[14px] font-semibold text-ink">{{ $d['sub'] }}</span>
                                    <span class="block truncate text-xs text-muted">{{ $d['role'] ?? ('Daire '.$d['label']) }}</span>
                                </span>
                            </div>
                            <dl class="mt-4 space-y-2 text-[13px]">
                                <div class="flex items-center justify-between"><dt class="text-muted">İşlem adedi</dt><dd class="font-semibold tabular-nums text-ink">{{ $d['count'] }}</dd></div>
                                <div class="flex items-center justify-between"><dt class="text-muted">Tutar</dt><dd class="font-bold tabular-nums text-ink">{{ tl($d['value']) }}</dd></div>
                                <div class="flex items-center justify-between"><dt class="text-muted">Puan</dt><dd class="inline-flex items-center gap-1 font-semibold tabular-nums text-ink"><x-icon name="star" :size="13" class="text-amber-400" />{{ $d['rate'] ?? '4.6' }}</dd></div>
                            </dl>
                        </a>
                    @endforeach
                </div>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-[#f3f1fe] px-4 py-3.5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#e4dffc] text-[#6e5ce0]"><x-icon name="flame" :size="17" /></span>
                        <div>
                            <p class="text-[13.5px] font-semibold text-ink">Birimler bu dönem {{ tl($performance['collected']) }} tahsilat üretti</p>
                            <p class="text-xs text-muted">Toplam gelirin %{{ number_format($performance['rate'], 1, ',', '.') }} kadarı · {{ tl($dueSoon) }} yakında vadeli</p>
                        </div>
                    </div>
                    <a href="{{ route('site.reports.show', [$apartment, 'balance']) }}" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-[#5b49cc] hover:text-[#3b2f8a]">Tüm analitiği gör <x-icon name="arrow-right" :size="15" /></a>
                </div>
            </x-dash.section>
        </div>

        <div class="grid border-b border-line lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
            <x-dash.section title="Gelir ve kaynak dağılımı" :href="route('site.expenses.index', $apartment)" class="px-4 sm:px-8 lg:border-r lg:border-line">
                <div class="grid gap-2.5 sm:grid-cols-2">
                    @foreach ($expenseByCategory as $i => $row)
                        @php $pct = $row['pct'] ?? ($row['value'] > 0 ? round($row['value'] / $expTotal * 100) : 0); @endphp
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
                    <button type="button" data-dept-nav="prev" data-dept-box="site-dept" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-muted transition-colors hover:bg-slate-50 hover:text-ink" aria-label="Önceki"><x-icon name="chevron-right" :size="15" class="rotate-180" /></button>
                    <button type="button" data-dept-nav="next" data-dept-box="site-dept" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-muted transition-colors hover:bg-slate-50 hover:text-ink" aria-label="Sonraki"><x-icon name="chevron-right" :size="15" /></button>
                    <a href="{{ route('site.reports.index', $apartment) }}" class="btn btn-secondary btn-sm">Tümünü gör</a>
                </x-slot:actions>
                <div id="site-dept" class="tile p-5" data-dept-index="0" data-dept-slides='@json($depts)'>
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
                        <h2 class="section-title">Nakit akışı ve işlem trendi</h2>
                        <div class="flex items-center gap-5 text-xs font-medium">
                            <span class="inline-flex items-center gap-2 text-ink"><i class="h-3.5 w-[3px] rounded-full bg-[#6e5ce0]"></i>Bu hafta</span>
                            <span class="inline-flex items-center gap-2 text-muted"><i class="h-3.5 w-[3px] rounded-full bg-[#c3baf5]"></i>Geçen hafta</span>
                        </div>
                    </div>
                    <x-dash.spline />
                </div>

                <div class="px-4 py-6 sm:px-8" data-overview-table>
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="section-title">Kasa ve işlem özeti</h2>
                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <x-icon name="search" :size="15" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                                <input type="text" data-table-search placeholder="Ara..." class="w-56 rounded-xl border border-line bg-white py-2 pl-9 pr-3 text-xs text-ink placeholder:text-muted focus:border-brand-400 focus:outline-none">
                            </div>
                            <a href="{{ route('site.cashboxes.index', $apartment) }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-line bg-white text-ink transition-colors hover:bg-slate-50" title="Tüm hareketler"><x-icon name="filter" :size="15" /></a>
                        </div>
                    </div>

                    <div class="hidden grid-cols-[2fr_1fr_1.1fr_1.4fr_1fr_0.9fr_1fr] gap-3 px-4 pb-2.5 text-xs text-muted md:grid">
                        <span>İşlem / Açıklama</span>
                        <span>Kod</span>
                        <span>Kategori</span>
                        <span>Kasa / Birim</span>
                        <span>Tutar</span>
                        <span>Durum</span>
                        <span class="text-right">Son Güncelleme</span>
                    </div>

                    <div class="space-y-2.5">
                        @forelse ($transactions->take(6) as $tx)
                            @php
                                $isInc = $tx->type === 'income';
                                $code = $tx->receiptNo();
                                $cat = $isInc ? 'Tahsilat' : 'Gider';
                                $loc = ($tx->cashbox?->name ?? 'Merkez Kasa').($tx->unit ? ' · '.$tx->unit->label() : '');
                            @endphp
                            <div data-table-row class="grid grid-cols-1 items-center gap-3 rounded-2xl bg-[#f7f7fa] px-4 py-3 text-[13px] transition-colors hover:bg-[#f1f0f7] md:grid-cols-[2fr_1fr_1.1fr_1.4fr_1fr_0.9fr_1fr]">
                                <span class="flex min-w-0 items-center gap-3 font-medium text-ink">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-line bg-white text-muted"><x-icon :name="$isInc ? 'hand-coins' : 'package'" :size="16" /></span>
                                    <span class="truncate">{{ $tx->description ?: ($isInc ? 'Aidat tahsilatı' : 'Genel gider') }}</span>
                                </span>
                                <span class="font-medium text-ink/80 tabular-nums">{{ $code }}</span>
                                <span><span class="inline-flex rounded-lg bg-[#eaeaf0] px-2.5 py-1 text-xs font-medium text-ink">{{ $cat }}</span></span>
                                <span class="flex min-w-0 items-center gap-1.5 text-ink/80"><x-icon name="map-pin" :size="14" class="shrink-0 text-muted" /><span class="truncate">{{ $loc }}</span></span>
                                <span class="font-semibold tabular-nums text-ink">{{ tl($tx->amount) }}</span>
                                <span>
                                    @if ($tx->isReversed())
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-rose-100/80 px-2.5 py-1 text-xs font-medium text-rose-700"><i class="h-1.5 w-1.5 rounded-full bg-rose-500"></i>İptal</span>
                                    @elseif ($isInc)
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-100/80 px-2.5 py-1 text-xs font-medium text-emerald-700"><i class="h-1.5 w-1.5 rounded-full bg-emerald-500"></i>Onaylı</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-100/80 px-2.5 py-1 text-xs font-medium text-amber-800"><i class="h-1.5 w-1.5 rounded-full bg-amber-500"></i>İşlendi</span>
                                    @endif
                                </span>
                                <span class="text-right text-xs text-muted">{{ $tx->created_at->diffForHumans() }}</span>
                            </div>
                        @empty
                            <p class="py-6 text-center text-xs text-muted">Henüz işlem kaydı yok.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="px-4 py-6 sm:px-8">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <h2 class="section-title">Uyarılar</h2>
                    <a href="{{ route('site.audit', $apartment) }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-muted transition-colors hover:bg-slate-50 hover:text-ink" title="Tüm kayıtlar"><x-icon name="more-horizontal" :size="16" /></a>
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
