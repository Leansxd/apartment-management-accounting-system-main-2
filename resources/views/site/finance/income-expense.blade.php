<x-layout heading="Gelir Gider Tablosu" :subheading="$apartment->name.' · '.\Carbon\Carbon::parse($from)->format('d.m.Y').' – '.\Carbon\Carbon::parse($to)->format('d.m.Y')">
    <x-slot:actions><x-report.actions /></x-slot:actions>

    <x-report.toolbar :range="true" :from="$from" :to="$to" />

    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    @php
        $totalIncome = $income->sum('total'); $totalExpense = $expense->sum('total');
        $prevIncome = $income->sum('previous'); $prevExpense = $expense->sum('previous');
        $pct = fn (float $now, float $before) => $before > 0 ? (($now >= $before ? '+' : '−').'%'.number_format(abs($now - $before) / $before * 100, 1, ',', '.')) : '—';
        $previousLabel = \Carbon\Carbon::parse($previousFrom)->format('d.m.Y').' – '.\Carbon\Carbon::parse($previousTo)->format('d.m.Y');
        $detailUrl = fn (array $line) => route('site.cashboxes.index', array_filter([$apartment->id, 'type' => $line['type'], 'category_id' => $line['category_id'],
            'sub_type' => $line['sub_type'], 'from' => $from, 'to' => $to]));
    @endphp

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="stat"><p class="stat-label">Toplam Gelir</p><p class="stat-value whitespace-nowrap text-xl text-emerald-600">{{ tl($totalIncome) }}</p><p class="text-xs text-slate-500">Önceki dönem {{ tl($prevIncome) }} · {{ $pct($totalIncome, $prevIncome) }}</p></div>
        <div class="stat"><p class="stat-label">Toplam Gider</p><p class="stat-value whitespace-nowrap text-xl text-rose-600">{{ tl($totalExpense) }}</p><p class="text-xs text-slate-500">Önceki dönem {{ tl($prevExpense) }} · {{ $pct($totalExpense, $prevExpense) }}</p></div>
        <div class="stat"><p class="stat-label">Net (Gelir − Gider)</p><p class="stat-value whitespace-nowrap text-xl {{ $totalIncome - $totalExpense < 0 ? 'text-rose-600' : '' }}">{{ tl($totalIncome - $totalExpense) }}</p><p class="text-xs text-slate-500">Önceki dönem {{ tl($prevIncome - $prevExpense) }}</p></div>
    </div>

    <x-report.print-header :apartment="$apartment" title="Gelir Gider Tablosu" :from="$from" :to="$to" :note="'Önceki dönem: '.$previousLabel" />

    <div class="grid gap-6 xl:grid-cols-2">
        @foreach (['Gelirler' => $income, 'Giderler' => $expense] as $title => $rows)
            <section class="card">
                <div class="card-head"><h2 class="card-title">{{ $title }}</h2><span class="text-xs text-slate-500">Önceki dönem: {{ $previousLabel }}</span></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Kalem</th><th class="num">Adet</th><th class="num">Tutar</th><th class="num">Pay</th><th class="num">Önceki dönem</th><th class="num">Değişim</th></tr></thead>
                        <tbody>
                        @forelse ($rows as $label => $row)
                            <tr>
                                <td>@if ($row['count'])<a class="hover:text-brand-600 hover:underline" href="{{ $detailUrl($row) }}">{{ $label }}</a>@else{{ $label }}@endif</td>
                                <td class="num">{{ $row['count'] ?: '' }}</td>
                                <td class="num font-medium">{{ tl($row['total']) }}</td>
                                <td class="num text-slate-500">%{{ $rows->sum('total') > 0 ? number_format($row['total'] / $rows->sum('total') * 100, 1, ',', '.') : '0' }}</td>
                                <td class="num text-slate-500">{{ tl($row['previous']) }}</td>
                                <td class="num text-slate-500">{{ $pct($row['total'], $row['previous']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-slate-500">Kayıt yok.</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot><tr class="bg-slate-50 font-bold"><td>TOPLAM</td><td class="num">{{ $rows->sum('count') }}</td><td class="num">{{ tl($rows->sum('total')) }}</td><td class="num">%100</td><td class="num">{{ tl($rows->sum('previous')) }}</td><td class="num">{{ $pct($rows->sum('total'), $rows->sum('previous')) }}</td></tr></tfoot>
                    </table>
                </div>
            </section>
        @endforeach
    </div>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Aylara göre dağılım</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Ay</th><th class="num">Gelir</th><th class="num">Gider</th><th class="num">Net</th><th class="num">Kümülatif net</th></tr></thead>
                <tbody>
                @php $cumulative = 0; @endphp
                @forelse ($months as $m)
                    @php $net = $m['income'] - $m['expense']; $cumulative += $net; @endphp
                    <tr>
                        <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $m['month'])->locale('tr')->translatedFormat('F Y') }}</td>
                        <td class="num text-emerald-600">{{ tl($m['income']) }}</td>
                        <td class="num text-rose-600">{{ tl($m['expense']) }}</td>
                        <td class="num font-medium {{ $net < 0 ? 'text-rose-600' : '' }}">{{ tl($net) }}</td>
                        <td class="num {{ $cumulative < 0 ? 'text-rose-600' : '' }}">{{ tl($cumulative) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-500">Bu aralıkta hareket yok.</td></tr>
                @endforelse
                </tbody>
                @if ($months->isNotEmpty())
                    <tfoot><tr class="bg-slate-50 font-bold"><td>TOPLAM</td><td class="num">{{ tl($months->sum('income')) }}</td><td class="num">{{ tl($months->sum('expense')) }}</td><td class="num">{{ tl($months->sum('income') - $months->sum('expense')) }}</td><td></td></tr></tfoot>
                @endif
            </table>
        </div>
        <p class="card-body text-xs text-slate-400">Nakit esaslıdır: kasaya giren ve çıkan para sayılır. Kasalar arası virmanlar ve açılış bakiyeleri gelir / gider sayılmaz, iptal edilen hareketler hariçtir. Kaleme tıklayınca ilgili kasa hareketleri açılır. Önceki dönem, seçilen aralıkla aynı uzunluktaki bir önceki aralıktır.</p>
    </section>
    <x-report.signatures />
</x-layout>
