{{-- $series: [['label', 'total']] · $columns: [['label', 'sublabel', 'title', 'values' => [seri başına], 'rows' => [[etiket, tutar]]]] · ölçek ve kutucuk app.js ile çizilir --}}
@props(['series' => [], 'columns' => [], 'colors' => []])
<div data-series-chart>
    <div class="flex flex-wrap gap-2" role="group" aria-label="Görünen seriler">
        @foreach ($series as $index => $item)
            <button type="button" data-series-toggle="{{ $index }}" aria-pressed="true" class="series-chip" title="Grafikte göster veya gizle">
                <span class="series-dot" style="--dot: var(--chart-{{ $colors[$index] }})"></span>
                <span>{{ $item['label'] }}</span>
                <span class="font-semibold tabular-nums text-white">{{ tl($item['total']) }}</span>
            </button>
        @endforeach
    </div>

    <div class="mt-3 overflow-x-auto pb-1 pt-2 [scrollbar-width:none]">
        <div data-chart-plot class="relative min-w-[640px] pl-14">
            <div data-chart-axis class="pointer-events-none absolute inset-x-0 top-0 h-52" aria-hidden="true"></div>
            <div class="relative flex h-52 items-stretch gap-0.5">
                @foreach ($columns as $column)
                    <button type="button" data-chart-col data-title="{{ $column['title'] }}" data-rows="{{ json_encode($column['rows'], JSON_UNESCAPED_UNICODE) }}" aria-label="{{ $column['title'] }}" class="chart-col flex min-w-0 flex-1 items-end justify-center gap-px rounded-t-md">
                        @foreach ($column['values'] as $seriesIndex => $value)
                            <span data-bar="{{ $seriesIndex }}" data-value="{{ $value }}" class="w-full max-w-[8px] rounded-t-[3px]" style="background: var(--chart-{{ $colors[$seriesIndex] }}); height: 0"></span>
                        @endforeach
                    </button>
                @endforeach
            </div>
            <div class="mt-2 flex gap-0.5" aria-hidden="true">
                @foreach ($columns as $column)
                    <span class="min-w-0 flex-1 whitespace-nowrap text-center text-[10px] text-slate-500">{{ $column['label'] }}</span>
                @endforeach
            </div>
            <div data-chart-tip hidden class="pointer-events-none absolute top-1 z-30 min-w-[160px] rounded-xl border border-[#2a2a32] bg-[#18181c] px-3 py-2 shadow-xl">
                <p data-tip-title class="text-[11px] font-semibold text-white"></p>
                <dl data-tip-rows class="mt-1 space-y-0.5"></dl>
            </div>
        </div>
    </div>

    <div class="sr-only"><table>
        <thead><tr><th>Dönem</th>@foreach ($series as $item)<th>{{ $item['label'] }}</th>@endforeach</tr></thead>
        <tbody>@foreach ($columns as $column)<tr><td>{{ $column['title'] }}</td>@foreach ($column['values'] as $value)<td>{{ tl($value) }}</td>@endforeach</tr>@endforeach</tbody>
    </table></div>
</div>
