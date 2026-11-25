@props(['items' => [], 'daily' => [], 'label' => 'Gelir özeti'])
@php
    $rows = fn (array $pairs) => collect($pairs)->filter(fn ($pair) => $pair[1] != 0)->map(fn ($pair) => [$pair[0], tl($pair[1])])->values()->all();

    $days = collect($daily);
    $dayColumns = $days->map(fn ($d, $i) => [
        'label' => $d['day'] === 1 ? '1 '.$d['month'] : ($i % 4 === 0 ? (string) $d['day'] : ''),
        'title' => $d['full'],
        'values' => [$d['paid'], $d['invoiced'], $d['expense']],
        'rows' => $rows([['Tahsilat', $d['paid']], ['Tahakkuk', $d['invoiced']], ['Gider', $d['expense']], ['Net nakit', $d['paid'] - $d['expense']]]),
    ])->all();

    $months = collect($items);
    $monthColumns = $months->map(fn ($m) => [
        'label' => $m['label'],
        'title' => $m['full'] ?? $m['label'],
        'values' => [(float) ($m['income'] ?? 0), (float) ($m['expense'] ?? 0)],
        'rows' => $rows([['Gelir', (float) ($m['income'] ?? 0)], ['Gider', (float) ($m['expense'] ?? 0)], ['Net', (float) ($m['income'] ?? 0) - (float) ($m['expense'] ?? 0)]]),
    ])->all();
@endphp
<div data-revenue-chart class="relative z-10 mt-4">
    <div data-view-panel="bar">
        @if ($dayColumns)
            <x-dash.series-chart :columns="$dayColumns" :colors="[3, 1, 2]" :series="[['label' => 'Tahsilat', 'total' => (float) $days->sum('paid')], ['label' => 'Tahakkuk', 'total' => (float) $days->sum('invoiced')], ['label' => 'Gider', 'total' => (float) $days->sum('expense')]]" />
        @endif
    </div>

    <div data-view-panel="month" class="hidden">
        @if ($monthColumns)
            <x-dash.series-chart :columns="$monthColumns" :colors="[3, 2]" :series="[['label' => 'Gelir', 'total' => (float) $months->sum('income')], ['label' => 'Gider', 'total' => (float) $months->sum('expense')]]" />
        @endif
    </div>
</div>
