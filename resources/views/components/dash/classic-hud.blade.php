@php
    $s = $summary;
    $bankTotal = $cashboxes->where('type', 'bank')->sum('balance');
    $cashTotal = $cashboxes->where('type', 'cash')->sum('balance');
    $bankShare = $s['balance'] > 0 ? min(100, max(0, round($bankTotal / $s['balance'] * 100))) : 0;
    $net = $s['income'] - $s['expense'];
    $flowMax = max(1, $s['income'], $s['expense']);
    $collectionRate = min(100, $collection['rate']);
    $chartMax = max(1, ...array_map(fn ($m) => max($m['income'], $m['expense']), $monthly));
    $barHeight = fn (float $value) => $value > 0 ? max(2, round($value / $chartMax * 100)) : 0;
    $monthName = fn (array $m) => \Illuminate\Support\Str::before($m['label'], ' ');
    $collectionRows = [
        ['Toplam tahakkuk', $collection['accrued'], false],
        ['Tahsil edilen', $collection['collected'], false],
        ['Bekleyen aidatlar', $s['receivables'], false],
        ['Geciken alacaklar', $s['overdue'], true],
        ['Avans bakiyesi', $s['advances'], false],
    ];
    $firstOfMonth = today()->startOfMonth();
@endphp
<div class="space-y-5" data-classic-dashboard>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="ch-ink text-[22px] font-bold tracking-tight">{{ $apartment->name }}</h1>
            <p class="ch-muted mt-0.5 text-[13px]">{{ today()->locale('tr')->translatedFormat('d F Y, l') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('site.debts.index', $apartment) }}" class="ch-btn" {{ tour(34, 'Kısayollar', 'En çok kullanılan sayfalara tek tıkla gidersiniz: borçlandırma, tahsilat ve raporlar.') }}><x-icon name="file-text" :size="14" />Borçlandırma</a>
            <a href="{{ route('site.collections.index', $apartment) }}" class="ch-btn"><x-icon name="hand-coins" :size="14" />Tahsilat</a>
            <a href="{{ route('site.reports.index', $apartment) }}" class="ch-btn"><x-icon name="printer" :size="14" />Raporlar</a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <section class="ch-hero" {{ tour(30, 'Toplam bakiye', 'Şu an elinizdeki toplam para: banka ve nakit kasaların toplamı. Çizgi, paranın ne kadarının bankada olduğunu gösterir.') }}>
            <p class="text-[13px] font-medium opacity-80">Toplam Güncel Bakiye</p>
            <p class="mt-2 text-[34px] font-bold leading-none tracking-tight tabular-nums">{{ tl($s['balance']) }}</p>
            <div class="mt-auto pt-8">
                <div class="flex h-2 gap-0.5" role="img" aria-label="Banka yüzde {{ $bankShare }}, nakit yüzde {{ 100 - $bankShare }}">
                    @if ($bankShare > 0)<span class="rounded-l bg-white last:rounded-r" style="width: {{ $bankShare }}%"></span>@endif
                    @if ($bankShare < 100)<span class="flex-1 rounded-r bg-white/35 first:rounded-l"></span>@endif
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <dt class="flex items-center gap-1.5 text-xs opacity-80"><span class="h-2 w-2 rounded-full bg-white"></span>Bankalar</dt>
                        <dd class="mt-0.5 text-base font-semibold tabular-nums">{{ tl($bankTotal) }}</dd>
                    </div>
                    <div>
                        <dt class="flex items-center gap-1.5 text-xs opacity-80"><span class="h-2 w-2 rounded-full bg-white/35"></span>Nakit kasa</dt>
                        <dd class="mt-0.5 text-base font-semibold tabular-nums">{{ tl($cashTotal) }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="ch-panel p-5" {{ tour(31, 'Gelir ve gider', 'Bugüne kadarki toplam gelir, gider ve aradaki net fark. Onay bekleyen giderler henüz kasadan düşmemiştir.') }}>
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="ch-title">Gelir ve Gider</h2>
                <a href="{{ route('site.income-expense', $apartment) }}" class="ch-link">Detay</a>
            </div>
            <p class="ch-ink mt-3 text-2xl font-bold tracking-tight tabular-nums">{{ tl($net) }}</p>
            <p class="ch-muted text-xs">Net fark</p>
            <div class="mt-4 space-y-3 text-[13px]">
                @foreach ([['Gelir', $s['income'], 'var(--ch-income)'], ['Gider', $s['expense'], 'var(--ch-expense)']] as [$flowLabel, $flowValue, $flowColor])
                    <div>
                        <div class="flex justify-between gap-3"><span class="ch-muted">{{ $flowLabel }}</span><span class="ch-ink font-semibold tabular-nums">{{ tl($flowValue) }}</span></div>
                        <div class="ch-track mt-1.5"><span class="block h-full rounded-full" style="width: {{ round($flowValue / $flowMax * 100) }}%; background-color: {{ $flowColor }}"></span></div>
                    </div>
                @endforeach
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-[var(--ch-line)] pt-3 text-[13px]">
                <div><dt class="ch-muted text-xs">POS tahsilatları</dt><dd class="ch-ink font-semibold tabular-nums">{{ tl($s['pos']) }}</dd></div>
                <div><dt class="ch-muted text-xs">Onay bekleyen gider</dt><dd class="ch-ink font-semibold tabular-nums">{{ tl($s['pending_expenses']) }}</dd></div>
            </dl>
        </section>

        <section class="ch-panel p-5" {{ tour(32, 'Tahsilat durumu', 'Yazılan borçların yüzde kaçının toplandığını gösterir. Kırmızı tutar, son ödeme tarihi geçmiş alacaklardır.') }}>
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="ch-title">Tahsilat Durumu</h2>
                <a href="{{ route('site.debts.index', $apartment) }}" class="ch-link">Detay</a>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <p class="ch-ink text-2xl font-bold tracking-tight tabular-nums">%{{ number_format($collectionRate, 1, ',', '.') }}</p>
                <p class="ch-muted text-xs">tahsilat oranı</p>
            </div>
            <div class="ch-track mt-2"><span class="ch-track-fill" style="width: {{ $collectionRate }}%"></span></div>
            <dl class="mt-3 text-[13px]">
                @foreach ($collectionRows as [$rowLabel, $rowValue, $rowAlert])
                    <div class="flex justify-between gap-3 border-t border-[var(--ch-line)] py-1.5 first:border-t-0">
                        <dt class="ch-muted">{{ $rowLabel }}</dt>
                        <dd @class(['font-semibold tabular-nums', $rowAlert && $rowValue > 0 ? 'text-rose-500' : 'ch-ink'])>{{ tl($rowValue) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <div class="space-y-4 xl:col-span-8">
            <section class="ch-panel">
                <div class="ch-head">
                    <h2 class="ch-title">Aylık Gelir ve Gider</h2>
                    <div class="ch-muted flex items-center gap-4 text-xs">
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background-color: var(--ch-income)"></span>Gelir</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background-color: var(--ch-expense)"></span>Gider</span>
                    </div>
                </div>
                <div class="p-5">
                    <div class="flex gap-3">
                        <div class="ch-muted flex h-56 w-20 shrink-0 flex-col justify-between text-right text-[10px] tabular-nums" aria-hidden="true">
                            <span>{{ tl($chartMax) }}</span><span>{{ tl($chartMax / 2) }}</span><span>{{ tl(0) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="ch-chart relative grid h-56 grid-cols-12 gap-1" aria-hidden="true">
                                @foreach ($monthly as $month)
                                    <div class="group relative flex h-full items-end justify-center gap-0.5 rounded-md hover:bg-[var(--ch-tint)]">
                                        <span class="w-2 rounded-t-[4px] sm:w-3" style="height: {{ $barHeight($month['income']) }}%; background-color: var(--ch-income)"></span>
                                        <span class="w-2 rounded-t-[4px] sm:w-3" style="height: {{ $barHeight($month['expense']) }}%; background-color: var(--ch-expense)"></span>
                                        <div @class(['ch-tip', $loop->index > 8 ? 'right-0' : ($loop->index < 3 ? 'left-0' : 'left-1/2 -translate-x-1/2')])>
                                            <p class="font-semibold">{{ $month['label'] }}</p>
                                            <p class="mt-1 flex justify-between gap-4"><span class="ch-muted">Gelir</span><span class="tabular-nums">{{ tl($month['income']) }}</span></p>
                                            <p class="flex justify-between gap-4"><span class="ch-muted">Gider</span><span class="tabular-nums">{{ tl($month['expense']) }}</span></p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="ch-muted mt-2 grid grid-cols-12 gap-1 text-center text-[11px]" aria-hidden="true">
                                @foreach ($monthly as $month)<span>{{ $monthName($month) }}</span>@endforeach
                            </div>
                        </div>
                    </div>
                    <table class="sr-only">
                        <caption>Aylık gelir ve gider</caption>
                        <thead><tr><th>Ay</th><th>Gelir</th><th>Gider</th></tr></thead>
                        <tbody>
                            @foreach ($monthly as $month)<tr><td>{{ $month['label'] }}</td><td>{{ tl($month['income']) }}</td><td>{{ tl($month['expense']) }}</td></tr>@endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <section class="ch-panel overflow-hidden">
                    <div class="ch-head">
                        <h2 class="ch-title">Son Gider Evrakları</h2>
                        <a href="{{ route('site.expenses.index', $apartment) }}" class="ch-link">Tümü</a>
                    </div>
                    <div class="max-h-[340px] overflow-auto">
                        <table class="ch-table">
                            <thead><tr><th>Tarih</th><th>Cari / Açıklama</th><th class="text-right">Tutar</th></tr></thead>
                            <tbody>
                                @forelse ($recentExpenses as $expense)
                                    <tr>
                                        <td class="ch-muted whitespace-nowrap">{{ $expense->created_at?->format('d.m.Y') }}</td>
                                        <td class="max-w-[190px]">
                                            <p class="ch-ink truncate font-medium">{{ $expense->vendor?->name ?? $expense->description }}</p>
                                            <p class="ch-muted font-mono text-[10px]">{{ $expense->invoice_no ?? 'G-'.$expense->id }}</p>
                                        </td>
                                        <td class="ch-ink whitespace-nowrap text-right font-semibold tabular-nums">{{ tl($expense->total_amount) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="ch-muted text-center">Gider kaydı bulunmuyor.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="ch-panel overflow-hidden">
                    <div class="ch-head">
                        <h2 class="ch-title">Son Gelir Evrakları</h2>
                        <a href="{{ route('site.collections.index', $apartment) }}" class="ch-link">Tümü</a>
                    </div>
                    <div class="max-h-[340px] overflow-auto">
                        <table class="ch-table">
                            <thead><tr><th>Tarih</th><th>Daire / Açıklama</th><th class="text-right">Tutar</th></tr></thead>
                            <tbody>
                                @forelse ($recentIncomes as $income)
                                    <tr>
                                        <td class="ch-muted whitespace-nowrap">{{ $income->created_at?->format('d.m.Y') }}</td>
                                        <td class="max-w-[190px]">
                                            <p class="ch-ink truncate font-medium">{{ $income->unit?->label() ?? $income->description }}</p>
                                            <p class="ch-muted font-mono text-[10px]">MKB-{{ $income->id }}</p>
                                        </td>
                                        <td class="ch-ink whitespace-nowrap text-right font-semibold tabular-nums">{{ tl($income->amount) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="ch-muted text-center">Tahsilat kaydı bulunmuyor.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <div class="space-y-4 xl:col-span-4">
            <section class="ch-panel" data-tabs {{ tour(35, 'Hızlı işlem', 'En sık yaptığınız dört işlem tek yerde: daireye borç yazma, tahsilat alma, kasa hareketi ve hesaplar arası para aktarma.') }}>
                <div class="ch-head"><h2 class="ch-title">Hızlı İşlem</h2></div>
                <div class="p-5">@include('components.dash.classic-quick-forms')</div>
            </section>

            <section class="ch-panel">
                <div class="ch-head">
                    <h2 class="ch-title">Kasalar</h2>
                    <a href="{{ route('site.cashboxes.index', $apartment) }}" class="ch-link">Tümü</a>
                </div>
                <div class="px-5 py-2 text-[13px]">
                    @foreach ($cashboxes as $box)
                        <div class="flex items-center justify-between gap-3 border-b border-[var(--ch-line)] py-2.5 last:border-b-0">
                            <div class="min-w-0">
                                <p class="ch-ink truncate font-medium">{{ $box->name }}</p>
                                <p class="ch-muted truncate font-mono text-[10px]">{{ $box->account_number ?? \App\Models\Cashbox::TYPES[$box->type] }}</p>
                            </div>
                            <span class="ch-ink shrink-0 font-semibold tabular-nums">{{ tl($box->balance) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="ch-panel">
                <div class="ch-head"><h2 class="ch-title">{{ now()->locale('tr')->translatedFormat('F Y') }}</h2></div>
                <div class="p-4">
                    <div class="ch-muted mb-1 grid grid-cols-7 gap-1 text-center text-[11px] font-medium">
                        @foreach (['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cts', 'Paz'] as $dayName)<span>{{ $dayName }}</span>@endforeach
                    </div>
                    <div class="ch-ink grid grid-cols-7 gap-1 text-center text-xs tabular-nums">
                        @for ($blank = 1; $blank < $firstOfMonth->dayOfWeekIso; $blank++)<span></span>@endfor
                        @for ($day = 1; $day <= $firstOfMonth->daysInMonth; $day++)
                            <span @class(['rounded-md py-1.5', 'ch-today' => $day === today()->day])>{{ $day }}</span>
                        @endfor
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
