@props(['items', 'label' => 'Grafik', 'metricLabel' => 'İşlem', 'subLabel' => 'Yük'])
@php
    $max = max(1, (float) collect($items)->max('value'));
    $mag = 10 ** max(0, floor(log10($max)) - 1);
    $top = max(200, ceil($max / 4 / $mag) * $mag * 4);
    $axis = collect(range(4, 0))->map(fn ($i) => $top / 4 * $i);
    $short = fn ($v) => $v >= 1000000 ? number_format($v / 1000000, 1, ',', '.').' Mn' : ($v >= 1000 ? number_format($v / 1000, 0, ',', '.').' B' : number_format($v, 0, ',', '.'));
@endphp
<div class="flex gap-4" role="img" aria-label="{{ $label }}" data-bars>
    <div class="flex h-[215px] flex-col justify-between pb-7 text-right text-xs text-muted tabular-nums">
        @foreach ($axis as $a)<span class="leading-none">{{ $short($a) }}</span>@endforeach
    </div>
    <div class="grid flex-1 gap-3 sm:gap-4" style="grid-template-columns: repeat({{ max(1, count($items)) }}, minmax(0, 1fr))">
        @foreach ($items as $i => $item)
            @php
                $val = (float) $item['value'];
                $pct = max(18, min(100, round($val / $top * 100)));
                $loadPct = $item['load'] ?? ($max > 0 ? round($val / $max * 100) : 0);
                $isAccent = in_array($i, [1, 4], true);
                $displayVal = isset($item['count']) ? number_format($item['count'], 0, ',', '.') : tl($val);
            @endphp
            <div class="group relative flex flex-col items-center gap-3" tabindex="0" data-bar-col="{{ $i }}">
                <div class="hatch relative h-[188px] w-full max-w-[66px] overflow-hidden rounded-[22px]">
                    <div data-bar-fill class="grow-h absolute inset-x-0 bottom-0 rounded-[22px] transition-all duration-500 {{ $isAccent ? 'bg-[#b8aef6]' : 'bg-[#e5e0fb]' }} group-hover:bg-[#9b8df2] group-focus:bg-[#9b8df2]" style="height: {{ $pct }}%; --d: {{ $i }}"></div>
                </div>
                <span data-bar-dot class="pointer-events-none absolute left-1/2 z-20 h-3.5 w-3.5 -translate-x-1/2 rounded-full border-[2.5px] border-[#6e5ce0] bg-white opacity-0 shadow-sm transition-all duration-200 group-hover:opacity-100 group-focus:opacity-100" style="bottom: calc(28px + {{ $pct }} * 1.88px - 7px)"></span>
                <div class="pointer-events-none invisible absolute bottom-[54%] left-1/2 z-30 w-[180px] -translate-x-1/2 translate-y-1.5 rounded-2xl bg-[#1e1d33] p-3.5 text-white opacity-0 shadow-[0_18px_38px_-10px_rgba(23,22,44,0.45)] transition-all duration-150 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 group-focus:visible group-focus:translate-y-0 group-focus:opacity-100">
                    <div data-bar-date class="rounded-lg bg-white/10 px-2.5 py-1 text-[11px] font-medium text-white/90">{{ $item['full'] ?? $item['label'] }}</div>
                    <div class="mt-2.5 space-y-1.5 text-xs">
                        <div class="flex items-center justify-between gap-2"><span class="text-white/65">{{ $metricLabel }}</span><span data-bar-val class="font-semibold tabular-nums">{{ $displayVal }}</span></div>
                        <div class="flex items-center justify-between gap-2"><span class="text-white/65">{{ $subLabel }}</span><span data-bar-load class="font-semibold tabular-nums">{{ $loadPct }}%</span></div>
                    </div>
                </div>
                <span class="text-xs text-muted">{{ $item['label'] }}</span>
            </div>
        @endforeach
    </div>
</div>
