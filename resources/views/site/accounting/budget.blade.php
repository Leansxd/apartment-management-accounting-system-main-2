<x-layout :heading="'Bütçe '.$year" :subheading="$apartment->name.' · planlanan ve gerçekleşen'">
    <x-slot:actions>
        <form method="POST" action="{{ route('site.budget.copy', $apartment) }}" class="no-print">@csrf<input type="hidden" name="year" value="{{ $year }}"><button class="btn btn-secondary" title="{{ $year - 1 }} bütçesindeki tutarları {{ $year }} yılına aktarır; girilmiş kalemlerin üzerine yazmaz">Önceki yılı kopyala</button></form>
        <x-report.actions />
    </x-slot:actions>

    <x-report.toolbar>
        <div><label class="label" for="year">Yıl</label><select class="input" id="year" name="year" data-autosubmit>@foreach ($years as $y)<option @selected($y === $year)>{{ $y }}</option>@endforeach</select></div>
        <input type="hidden" name="cmp" value="1">
        <div>
            <span class="label">Geçmiş yıllarla karşılaştır</span>
            <div class="flex min-h-[38px] flex-wrap items-center gap-2" role="group" aria-label="Karşılaştırılacak yıllar">
                @foreach ($years as $y)
                    @continue($y >= $year)
                    <label class="year-chip"><input type="checkbox" name="compare[]" value="{{ $y }}" class="sr-only" @checked(in_array($y, $compare, true))><x-icon name="check" size="13" class="year-chip-check" />{{ $y }}</label>
                @endforeach
            </div>
        </div>
        <p class="w-full text-xs text-slate-500 lg:w-auto lg:max-w-xs">Bir veya birkaç geçmiş yıl seçip <b>Göster</b>'e basın. Yüzde değişimler en yakın seçili yıla göre hesaplanır.</p>
    </x-report.toolbar>

    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    @php
        $t = $budget['totals'];
        $ratio = fn (float $actual, float $plan) => $plan > 0 ? round($actual / $plan * 100, 1) : null;
        $fmtPct = fn (?float $value) => $value === null ? '—' : '%'.number_format($value, 1, ',', '.');
        $pct = fn (?float $value) => $value === null ? '—' : ($value > 0 ? '+' : ($value < 0 ? '−' : '')).'%'.number_format(abs($value), 1, ',', '.');
        $summary = $comparison['summary'];
        $previous = $compare ? $summary->firstWhere('year', $compare[0]) : null;
        $current = $summary->firstWhere('year', $year);
        // Gelir artışı iyi, gider artışı kötü; renk buna göre verilir
        $tone = fn (?float $value, bool $incomeLike) => $value === null || $value == 0 ? 'text-slate-500' : (($value > 0) === $incomeLike ? 'text-emerald-600' : 'text-rose-600');
        $incomeRate = $ratio($t['income_actual'], $t['income_planned']);
        $expenseRate = $ratio($t['expense_actual'], $t['expense_planned']);
        $net = $t['income_actual'] - $t['expense_actual'];
        $plannedNet = $t['income_planned'] - $t['expense_planned'];
        $isOngoing = $year === (int) now()->year;
        $elapsed = $isOngoing ? round(now()->dayOfYear / now()->daysInYear * 100, 1) : null;
        $toneStyles = [
            'good' => ['icon' => 'check-circle-2', 'class' => 'metric-good'],
            'warn' => ['icon' => 'alert-triangle', 'class' => 'metric-warn'],
            'bad' => ['icon' => 'alert-circle', 'class' => 'metric-bad'],
            'info' => ['icon' => 'help-circle', 'class' => 'text-sky-500'],
        ];
    @endphp

    <x-report.print-header :apartment="$apartment" :title="'Bütçe ve Gerçekleşen · '.$year" :note="$compare ? 'Karşılaştırma yılları: '.implode(', ', $compare) : null" />

    {{-- 1. Ana göstergeler --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat">
            <div class="flex items-center justify-between"><p class="stat-label">Gerçekleşen gelir</p><x-icon name="trending-up" class="metric-good" /></div>
            <p class="stat-value whitespace-nowrap text-xl text-emerald-600">{{ tl($t['income_actual']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Plan: {{ tl($t['income_planned']) }}</p>
            @if ($incomeRate !== null)
                <div class="mt-3 h-1.5 rounded-full chart-track"><div class="h-1.5 rounded-full bg-emerald-500" style="width: {{ min(100, $incomeRate) }}%"></div></div>
                <p class="mt-1 text-[11px] text-slate-500">Hedefin <b class="text-slate-700">{{ $fmtPct($incomeRate) }}</b>'i tahsil edildi</p>
            @else
                <p class="mt-3 text-[11px] text-slate-500">Gelir bütçesi girilmemiş</p>
            @endif
            @if ($previous)<p class="mt-2 border-t border-slate-200 pt-2 text-[11px] text-slate-500">{{ $compare[0] }}: {{ tl($previous['income_actual']) }} <span class="font-medium {{ $tone($current['income_change'] ?? null, true) }}">{{ $pct($current['income_change'] ?? null) }}</span></p>@endif
        </div>
        <div class="stat">
            <div class="flex items-center justify-between"><p class="stat-label">Gerçekleşen gider</p><x-icon name="trending-down" class="metric-bad" /></div>
            <p class="stat-value whitespace-nowrap text-xl text-rose-600">{{ tl($t['expense_actual']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Plan: {{ tl($t['expense_planned']) }}</p>
            @if ($expenseRate !== null)
                <div class="relative mt-3 h-1.5 rounded-full chart-track">
                    <div class="h-1.5 rounded-full {{ $expenseRate > 100 ? 'bg-rose-500' : 'bg-amber-500' }}" style="width: {{ min(100, $expenseRate) }}%"></div>
                    @if ($elapsed !== null)<span class="absolute -top-1 h-3.5 w-0.5 rounded bg-slate-500" style="left: {{ $elapsed }}%" title="Yılın geçen kısmı: {{ $fmtPct($elapsed) }}"></span>@endif
                </div>
                <p class="mt-1 text-[11px] text-slate-500">Bütçenin <b class="text-slate-700">{{ $fmtPct($expenseRate) }}</b>'i harcandı{{ $elapsed !== null ? ' · çizgi: yılın '.$fmtPct($elapsed).'\'i' : '' }}</p>
            @else
                <p class="mt-3 text-[11px] text-slate-500">Gider bütçesi girilmemiş</p>
            @endif
            @if ($previous)<p class="mt-2 border-t border-slate-200 pt-2 text-[11px] text-slate-500">{{ $compare[0] }}: {{ tl($previous['expense_actual']) }} <span class="font-medium {{ $tone($current['expense_change'] ?? null, false) }}">{{ $pct($current['expense_change'] ?? null) }}</span></p>@endif
        </div>
        <div class="stat">
            <div class="flex items-center justify-between"><p class="stat-label">Net sonuç (gelir − gider)</p><x-icon name="wallet" class="{{ $net < 0 ? 'metric-bad' : 'metric-good' }}" /></div>
            <p class="stat-value whitespace-nowrap text-xl {{ $net < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ tl($net) }}</p>
            <p class="mt-1 text-xs text-slate-500">Planlanan net: {{ tl($plannedNet) }}</p>
            <p class="mt-3 text-[11px] text-slate-500">{{ $net >= 0 ? 'Gelir giderleri karşıladı, kasada fazla oluştu.' : 'Giderler gelirleri aştı; fark kasadaki birikimden karşılandı.' }}</p>
            @if ($previous)<p class="mt-2 border-t border-slate-200 pt-2 text-[11px] text-slate-500">{{ $compare[0] }}: <span class="{{ $previous['net'] < 0 ? 'text-rose-600' : '' }}">{{ tl($previous['net']) }}</span></p>@endif
        </div>
        <div class="stat">
            <div class="flex items-center justify-between"><p class="stat-label">Bütçe durumu</p><x-icon name="piggy-bank" class="text-sky-500" /></div>
            @php
                $overCount = $comparison['rows']['expense']->filter(fn ($r) => $r['planned'] > 0 && $r['actual'] > $r['planned'])->count();
                $plannedCount = $comparison['rows']['income']->merge($comparison['rows']['expense'])->filter(fn ($r) => $r['planned'] > 0)->count();
                $categoryCount = $comparison['rows']['income']->count() + $comparison['rows']['expense']->count();
            @endphp
            <p class="stat-value text-xl {{ $overCount ? 'text-rose-600' : '' }}">{{ $overCount ? $overCount.' kalem aşımda' : 'Aşım yok' }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $plannedCount }} / {{ $categoryCount }} kategoriye bütçe girildi</p>
            <p class="mt-3 text-[11px] text-slate-500">{{ $isOngoing ? 'Yıl devam ediyor; tutarlar bugüne kadarki gerçekleşendir.' : ($year < now()->year ? 'Kapanmış yıl; tutarlar kesinleşmiştir.' : 'Gelecek yıl; henüz gerçekleşen yok.') }}</p>
        </div>
    </div>

    {{-- 2. Otomatik değerlendirme --}}
    <section class="card">
        <div class="card-head"><h2 class="card-title">Değerlendirme</h2><span class="text-xs text-slate-500">Rakamlardan otomatik çıkarılan özet</span></div>
        <ul class="card-body space-y-2.5">
            @foreach ($insights as $insight)
                <li class="flex items-start gap-2.5 text-sm"><x-icon :name="$toneStyles[$insight['tone']]['icon']" class="mt-0.5 {{ $toneStyles[$insight['tone']]['class'] }}" /><span>{{ $insight['text'] }}</span></li>
            @endforeach
        </ul>
    </section>

    {{-- 3. Aylık dağılım --}}
    <section class="card">
        <div class="card-head"><h2 class="card-title">Aylık gelir ve gider</h2><span class="text-xs text-slate-500">Çubuğun üzerine gelin veya tıklayın; seri adına basarak gizleyip gösterin</span></div>
        <div class="card-body">
            @php
                $monthNames = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
                $series = [['label' => "Gelir {$year}", 'total' => array_sum($monthly['income'])], ['label' => "Gider {$year}", 'total' => array_sum($monthly['expense'])]];
                $colors = [3, 2];
                if ($previousMonthly) {
                    $series[] = ['label' => "Gelir {$compare[0]}", 'total' => array_sum($previousMonthly['income'])];
                    $series[] = ['label' => "Gider {$compare[0]}", 'total' => array_sum($previousMonthly['expense'])];
                    $colors = [3, 2, 4, 5];
                }
                $monthColumns = collect(range(1, 12))->map(function (int $m) use ($monthNames, $monthly, $previousMonthly, $year, $compare) {
                    $values = [$monthly['income'][$m], $monthly['expense'][$m]];
                    $rows = [["Gelir {$year}", tl($monthly['income'][$m])], ["Gider {$year}", tl($monthly['expense'][$m])], ['Net', tl($monthly['income'][$m] - $monthly['expense'][$m])]];
                    if ($previousMonthly) {
                        $values[] = $previousMonthly['income'][$m];
                        $values[] = $previousMonthly['expense'][$m];
                        $rows[] = ["Gelir {$compare[0]}", tl($previousMonthly['income'][$m])];
                        $rows[] = ["Gider {$compare[0]}", tl($previousMonthly['expense'][$m])];
                    }

                    return ['label' => mb_substr($monthNames[$m - 1], 0, 3), 'title' => "{$monthNames[$m - 1]} {$year}", 'values' => $values, 'rows' => $rows];
                })->all();
                $busiest = collect($monthly['expense'])->sortDesc()->keys()->first();
            @endphp
            <x-dash.series-chart :series="$series" :columns="$monthColumns" :colors="$colors" />
            @if (array_sum($monthly['expense']) > 0)
                <p class="mt-3 text-xs text-slate-500">En yüksek gider {{ $monthNames[$busiest - 1] }} ayında: {{ tl($monthly['expense'][$busiest]) }}. Aylık ortalama gider {{ tl(array_sum($monthly['expense']) / ($isOngoing ? max(1, now()->month) : 12)) }}.</p>
            @endif
        </div>
    </section>

    {{-- 4. Yıllar arası özet --}}
    @if ($compare)
        <section class="card">
            <div class="card-head"><h2 class="card-title">Yıllara göre özet</h2><span class="text-xs text-slate-500">Yüzdeler bir önceki yılın gerçekleşenine göredir</span></div>
            @php
                $peak = max(1, $summary->max(fn ($s) => max($s['income_actual'], $s['expense_actual'], $s['income_planned'], $s['expense_planned'])));
                $h = fn (float $v) => $v > 0 ? 'max(3px, '.round($v / $peak * 100, 1).'%)' : '0';
            @endphp
            <div class="card-body">
                <div class="mb-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-slate-600">
                    <span class="flex items-center gap-1.5"><i class="inline-block h-2.5 w-2.5 rounded-sm bg-emerald-500"></i>Gerçekleşen gelir</span>
                    <span class="flex items-center gap-1.5"><i class="inline-block h-2.5 w-2.5 rounded-sm bg-rose-500"></i>Gerçekleşen gider</span>
                    <span class="flex items-center gap-1.5"><i class="inline-block h-2.5 w-4 rounded-sm border border-dashed border-[#71717a]"></i>Bütçe (plan)</span>
                </div>
                <div class="flex items-end gap-3 overflow-x-auto pb-1 sm:gap-6" role="img" aria-label="Yıllara göre gelir ve gider karşılaştırması">
                    @foreach ($summary as $s)
                        <div class="flex min-w-[88px] flex-1 flex-col items-center gap-2 {{ $s['year'] === $year ? '' : 'opacity-80' }}">
                            <div class="flex h-44 w-full items-end justify-center gap-1.5 rounded-lg px-2 {{ $s['year'] === $year ? 'bg-slate-50' : '' }}">
                                <div class="relative flex h-full w-1/2 max-w-[44px] items-end" title="{{ $s['year'] }} gelir: {{ tl($s['income_actual']) }} (plan {{ tl($s['income_planned']) }})">
                                    @if ($s['income_planned'] > 0)<span class="absolute inset-x-0 rounded-t border-2 border-dashed border-emerald-300" style="bottom:0;height:{{ $h($s['income_planned']) }}"></span>@endif
                                    <span class="relative block w-full rounded-t bg-emerald-500" style="height:{{ $h($s['income_actual']) }}"></span>
                                </div>
                                <div class="relative flex h-full w-1/2 max-w-[44px] items-end" title="{{ $s['year'] }} gider: {{ tl($s['expense_actual']) }} (plan {{ tl($s['expense_planned']) }})">
                                    @if ($s['expense_planned'] > 0)<span class="absolute inset-x-0 rounded-t border-2 border-dashed border-rose-300" style="bottom:0;height:{{ $h($s['expense_planned']) }}"></span>@endif
                                    <span class="relative block w-full rounded-t bg-rose-500" style="height:{{ $h($s['expense_actual']) }}"></span>
                                </div>
                            </div>
                            <div class="text-center leading-tight">
                                <p class="text-sm {{ $s['year'] === $year ? 'font-bold text-slate-900' : 'font-medium text-slate-600' }}">{{ $s['year'] }}</p>
                                <p class="text-[11px] font-medium tabular-nums {{ $s['net'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">Net {{ tl($s['net']) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table" data-print-whole>
                    <thead><tr><th>Yıl</th><th class="num">Planlanan gelir</th><th class="num">Gerçekleşen gelir</th><th class="num">Değişim</th><th class="num">Planlanan gider</th><th class="num">Gerçekleşen gider</th><th class="num">Değişim</th><th class="num">Net (gelir − gider)</th></tr></thead>
                    <tbody>
                    @foreach ($summary as $s)
                        <tr class="{{ $s['year'] === $year ? 'font-semibold' : '' }}">
                            <td>{{ $s['year'] }}@if ($s['year'] === $year) <span class="badge badge-blue">Seçili</span>@endif</td>
                            <td class="num">{{ tl($s['income_planned']) }}</td>
                            <td class="num text-emerald-600">{{ tl($s['income_actual']) }}</td>
                            <td class="num {{ $tone($s['income_change'], true) }}">{{ $pct($s['income_change']) }}</td>
                            <td class="num">{{ tl($s['expense_planned']) }}</td>
                            <td class="num text-rose-600">{{ tl($s['expense_actual']) }}</td>
                            <td class="num {{ $tone($s['expense_change'], false) }}">{{ $pct($s['expense_change']) }}</td>
                            <td class="num {{ $s['net'] < 0 ? 'text-rose-600' : '' }}">{{ tl($s['net']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="card-body text-xs text-slate-400">Geçmiş yılların gerçekleşen tutarları da aynı kurallarla hesaplanır: gelirde borçlara yapılan tahsilat ve avans mahsubu, giderde ödenen giderler. Bütçesi girilmemiş yılların planı 0 görünür.@if ($isOngoing) {{ $year }} henüz bitmediği için tam yıllarla kıyası yanıltıcı olabilir.@endif</p>
        </section>
    @endif

    {{-- 5. Kategori dağılımı ve yıllar arası kategori kıyası --}}
    <section class="card">
        <div class="card-head"><h2 class="card-title">{{ $compare ? 'Kategori bazında karşılaştırma' : 'Kategori dağılımı' }}</h2><span class="text-xs text-slate-500">{{ $compare ? "Renkli çubuk {$year}, gri çubuklar geçmiş yıllar · rozet {$compare[0]}'e göre değişim" : 'Her kategorinin toplam içindeki payı' }}</span></div>
        <div class="grid gap-8 p-4 lg:grid-cols-2">
            @foreach (['income' => ['Gelirler', 'bg-emerald-500', '--chart-3'], 'expense' => ['Giderler', 'bg-rose-500', '--chart-2']] as $type => [$title, $bar, $chartVar])
                @php
                    $items = $comparison['rows'][$type]->filter(fn ($r) => $r['actual'] > 0 || collect($r['history'])->sum('actual') > 0)->sortByDesc('actual')->values();
                    $scale = max(1, $items->max(fn ($r) => max($r['actual'], $r['planned'], ...array_column($r['history'], 'actual'))) ?? 1);
                    $w = fn (float $v) => $v > 0 ? 'max(2px, '.round($v / $scale * 100, 1).'%)' : '0';
                    $typeTotal = $items->sum('actual');
                    $shareOpacity = [100, 78, 60, 45, 32, 22];
                    $shareItems = $items->filter(fn ($r) => $r['actual'] > 0)->take(5)->values();
                    $otherShare = $typeTotal - $shareItems->sum('actual');
                @endphp
                <div>
                    <div class="mb-3 flex items-baseline justify-between"><h3 class="text-sm font-semibold text-slate-700">{{ $title }}</h3><span class="text-xs tabular-nums text-slate-500">Toplam {{ tl($typeTotal) }}</span></div>
                    @if ($typeTotal > 0)
                        <div class="mb-4 flex h-3 overflow-hidden rounded-full chart-track" role="img" aria-label="{{ $title }} kategori payları">
                            @foreach ($shareItems as $i => $r)
                                <span title="{{ $r['category']->name }}: {{ $fmtPct($r['actual'] / $typeTotal * 100) }}" style="width: {{ $r['actual'] / $typeTotal * 100 }}%; background-color: color-mix(in srgb, var({{ $chartVar }}) {{ $shareOpacity[$i] }}%, transparent)"></span>
                            @endforeach
                            @if ($otherShare > 0)<span title="Diğer: {{ $fmtPct($otherShare / $typeTotal * 100) }}" style="width: {{ $otherShare / $typeTotal * 100 }}%; background-color: color-mix(in srgb, var(--ui-muted) 30%, transparent)"></span>@endif
                        </div>
                    @endif
                    <div class="space-y-4">
                        @forelse ($items as $i => $r)
                            <div>
                                <div class="mb-1 flex items-center justify-between gap-2">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <i class="inline-block h-2.5 w-2.5 shrink-0 rounded-sm" style="background-color: {{ $i < 5 && $r['actual'] > 0 ? 'color-mix(in srgb, var('.$chartVar.') '.$shareOpacity[$i].'%, transparent)' : 'color-mix(in srgb, var(--ui-muted) 30%, transparent)' }}"></i>
                                        <span class="truncate text-sm font-medium">{{ $r['category']->name }}</span>
                                        @if ($typeTotal > 0 && $r['actual'] > 0)<span class="text-[11px] tabular-nums text-slate-500">{{ $fmtPct($r['actual'] / $typeTotal * 100) }}</span>@endif
                                    </span>
                                    @if ($compare)
                                        <span class="badge {{ $r['change_pct'] === null || $r['change_pct'] == 0 ? 'badge-gray' : (($r['change_pct'] > 0) === ($type === 'income') ? 'badge-green' : 'badge-red') }}" title="{{ $compare[0] }} yılına göre">{{ $r['change_pct'] === null && $r['actual'] > 0 ? 'Yeni' : $pct($r['change_pct']) }}</span>
                                    @endif
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2"><span class="w-9 shrink-0 text-[11px] font-semibold tabular-nums text-slate-700">{{ $year }}</span><div class="chart-track h-3 flex-1 rounded"><div class="h-3 rounded {{ $bar }}" style="width:{{ $w($r['actual']) }}"></div></div><span class="w-24 shrink-0 text-right text-[11px] font-semibold tabular-nums">{{ tl($r['actual']) }}</span></div>
                                    @if ($r['planned'] > 0)
                                        <div class="flex items-center gap-2"><span class="w-9 shrink-0 text-[11px] text-slate-500">Plan</span><div class="h-2 flex-1"><div class="h-2 rounded border border-dashed border-[#71717a]" style="width:{{ min(100, $r['planned'] / $scale * 100) }}%"></div></div><span class="w-24 shrink-0 text-right text-[11px] tabular-nums text-slate-500">{{ tl($r['planned']) }}</span></div>
                                    @endif
                                    @foreach ($compare as $ci => $y)
                                        <div class="flex items-center gap-2"><span class="w-9 shrink-0 text-[11px] tabular-nums text-slate-500">{{ $y }}</span><div class="chart-track h-2 flex-1 rounded"><div class="h-2 rounded" style="width:{{ $w($r['history'][$y]['actual']) }};background-color:color-mix(in srgb, var(--ui-muted) {{ [75, 55, 40, 30][$ci] ?? 30 }}%, transparent)"></div></div><span class="w-24 shrink-0 text-right text-[11px] tabular-nums text-slate-500">{{ tl($r['history'][$y]['actual']) }}</span></div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">{{ $year }} yılında bu türde hareket yok.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 6. Terimler --}}
    <details class="card no-print group">
        <summary class="card-head cursor-pointer list-none"><h2 class="card-title flex items-center gap-2"><x-icon name="help-circle" />Bu sayfadaki rakamlar ne anlama geliyor?</h2><x-icon name="chevron-down" class="transition-transform group-open:rotate-180" /></summary>
        <dl class="card-body grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
            <div><dt class="font-semibold">Bütçe (plan)</dt><dd class="text-slate-500">Yıl başında kategori için öngördüğünüz tutar. Aşağıdaki tablodan girilir.</dd></div>
            <div><dt class="font-semibold">Tahakkuk</dt><dd class="text-slate-500">Yıl içinde vadesi gelen ve dairelere borç olarak yazılan tutar (henüz ödenmemiş olabilir).</dd></div>
            <div><dt class="font-semibold">Gerçekleşen / tahsil edilen gelir</dt><dd class="text-slate-500">Borçlara yapılan tahsilat, avanstan mahsup ve kategorisi seçilmiş genel gelirler.</dd></div>
            <div><dt class="font-semibold">Gerçekleşen / ödenen gider</dt><dd class="text-slate-500">Kasadan ödenen, kategorisi seçilmiş giderler. Kasalar arası virman ve açılış bakiyesi sayılmaz.</dd></div>
            <div><dt class="font-semibold">Fark</dt><dd class="text-slate-500">Gerçekleşen − bütçe. Gelirde eksi, giderde artı değer olumsuzdur.</dd></div>
            <div><dt class="font-semibold">Gerçekleşme</dt><dd class="text-slate-500">Gerçekleşenin bütçeye oranı. Giderde %100'ün üstü bütçe aşımıdır.</dd></div>
            <div><dt class="font-semibold">Değişim</dt><dd class="text-slate-500">Seçilen yılın gerçekleşeninin, karşılaştırılan en yakın yıla göre farkı. Gelirde artış yeşil, giderde artış kırmızıdır.</dd></div>
            <div><dt class="font-semibold">Net sonuç</dt><dd class="text-slate-500">Gerçekleşen gelir − gerçekleşen gider. Eksi ise o yıl kasadaki birikim azalmıştır.</dd></div>
        </dl>
    </details>

    {{-- 7. Düzenlenebilir bütçe tablosu --}}
    <form method="POST" action="{{ route('site.budget.save', $apartment) }}" class="space-y-6">
        @csrf<input type="hidden" name="year" value="{{ $year }}">
        @foreach (['income' => 'Gelirler', 'expense' => 'Giderler'] as $type => $title)
            @php
                $rows = $comparison['rows'][$type];
                $planned = $rows->sum('planned');
                $actual = $rows->sum('actual');
                $columnCount = 7 + ($type === 'income' ? 1 : 0) + count($compare) + ($compare ? 2 : 0);
                $totalBase = $compare ? $rows->sum("history.{$compare[0]}.actual") : null;
            @endphp
            <section class="card {{ $compare ? 'print-landscape' : '' }}">
                <div class="card-head"><h2 class="card-title">{{ $title }} · bütçe planı</h2><span class="text-xs text-slate-500">{{ $type === 'income' ? 'Bütçe sütununa yıllık hedefi girin. Kategori adına tıklayınca o yılın borçları açılır.' : 'Bütçe sütununa yıllık harcama sınırını girin. Kategori adına tıklayınca o yılın ödemeleri açılır.' }}</span></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr>
                            <th>Kategori</th><th class="num" title="Yıllık planlanan tutar">Bütçe (₺)</th>@if ($type === 'income')<th class="num" title="Yıl içinde vadesi gelen borçlar">Tahakkuk</th>@endif<th class="num" title="{{ $type === 'income' ? 'Tahsilat + avans mahsubu + kategorili genel gelir' : 'Kasadan ödenen kategorili giderler' }}">{{ $type === 'income' ? 'Tahsil edilen' : 'Ödenen' }}</th><th class="num" title="Gerçekleşen − bütçe">Fark</th><th class="num" title="Gerçekleşen / bütçe">Gerçekleşme</th><th class="w-32 no-print"></th>
                            @foreach ($compare as $y)<th class="num">{{ $y }} gerçekleşen</th>@endforeach
                            @if ($compare)<th class="num">{{ $compare[0] }}'e göre fark</th><th class="num">%</th>@endif
                        </tr></thead>
                        <tbody>
                        @forelse ($rows as $row)
                            @php $isOver = $type === 'expense' && $row['planned'] > 0 && $row['actual'] > $row['planned']; @endphp
                            <tr>
                                <td class="font-medium"><a class="hover:text-brand-600 hover:underline" href="{{ $type === 'income'
                                    ? route('site.debts.index', [$apartment, 'category' => $row['category']->name, 'from' => $year.'-01-01', 'to' => $year.'-12-31'])
                                    : route('site.cashboxes.index', [$apartment, 'category_id' => $row['category']->id, 'from' => $year.'-01-01', 'to' => $year.'-12-31']) }}">{{ $row['category']->name }}</a>
                                    @if ($isOver) <span class="badge badge-red">Aşım</span>@elseif ($row['planned'] == 0 && $row['actual'] > 0) <span class="badge badge-gray" title="Bu kategoriye bütçe girilmemiş">Plansız</span>@endif
                                </td>
                                <td class="num"><label class="sr-only" for="b-{{ $row['category']->id }}">{{ $row['category']->name }} bütçesi</label><input class="input !w-32 text-right" id="b-{{ $row['category']->id }}" type="number" step="0.01" min="0" name="amounts[{{ $row['category']->id }}]" value="{{ $row['planned'] ?: '' }}" placeholder="0"></td>
                                @if ($type === 'income')<td class="num">{{ tl($row['accrued']) }}</td>@endif
                                <td class="num font-semibold">{{ tl($row['actual']) }}</td>
                                <td class="num {{ ($type === 'income' ? $row['diff'] < 0 : $row['diff'] > 0) && $row['planned'] ? 'text-rose-600' : 'text-emerald-600' }}">{{ $row['planned'] ? tl($row['diff']) : '—' }}</td>
                                <td class="num">{{ $fmtPct($row['pct']) }}</td>
                                <td class="no-print">@if ($row['pct'] !== null)<div class="h-2 rounded chart-track"><div class="h-2 rounded {{ $isOver ? 'bg-rose-500' : ($type === 'income' ? 'bg-emerald-500' : 'bg-amber-500') }}" style="width: {{ min(100, $row['pct']) }}%"></div></div>@endif</td>
                                @foreach ($compare as $y)<td class="num text-slate-600">{{ $row['history'][$y]['actual'] ? tl($row['history'][$y]['actual']) : '—' }}</td>@endforeach
                                @if ($compare)
                                    <td class="num {{ $tone($row['change'], $type === 'income') }}">{{ $row['change'] ? ($row['change'] > 0 ? '+' : '−').tl(abs($row['change'])) : '—' }}</td>
                                    <td class="num {{ $tone($row['change_pct'], $type === 'income') }}">{{ $pct($row['change_pct']) }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $columnCount }}" class="text-center text-slate-500">Kategori yok. Kasa & Banka sayfasından kategori ekleyin.</td></tr>
                        @endforelse
                        </tbody>
                        @if ($rows->isNotEmpty())
                            <tfoot><tr class="bg-slate-50 font-bold">
                                <td>TOPLAM</td><td class="num">{{ tl($planned) }}</td>@if ($type === 'income')<td class="num">{{ tl($rows->sum('accrued')) }}</td>@endif<td class="num">{{ tl($actual) }}</td><td class="num">{{ $planned ? tl($actual - $planned) : '—' }}</td><td class="num">{{ $fmtPct($ratio($actual, $planned)) }}</td><td class="no-print"></td>
                                @foreach ($compare as $y)<td class="num">{{ tl($rows->sum("history.{$y}.actual")) }}</td>@endforeach
                                @if ($compare)
                                    @php $totalChange = round($actual - $totalBase, 2); $totalPct = $totalBase ? round($totalChange / $totalBase * 100, 1) : null; @endphp
                                    <td class="num {{ $tone($totalChange, $type === 'income') }}">{{ $totalChange ? ($totalChange > 0 ? '+' : '−').tl(abs($totalChange)) : '—' }}</td>
                                    <td class="num {{ $tone($totalPct, $type === 'income') }}">{{ $pct($totalPct) }}</td>
                                @endif
                            </tr></tfoot>
                        @endif
                    </table>
                </div>
            </section>
        @endforeach
        <div class="no-print flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">Boş bırakılan veya 0 girilen kategori bütçeden çıkarılır. Kaydettikten sonra göstergeler ve değerlendirme yeni plana göre güncellenir.</p>
            <button class="btn btn-primary">Bütçeyi kaydet</button>
        </div>
    </form>
    <x-report.signatures />
</x-layout>
