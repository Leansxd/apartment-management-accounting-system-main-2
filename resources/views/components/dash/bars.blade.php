@props(['items' => [], 'daily' => [], 'label' => 'Gelir özeti'])
@php
    $days = count($daily) ? $daily : collect(range(29, 0))->map(function ($i) {
        $d = now()->subDays($i);
        return [
            'label' => $d->locale('tr')->translatedFormat('j M'),
            'paid' => 0,
            'invoiced' => 0,
        ];
    })->all();
    $maxDay = max(1, (float) collect($days)->max(fn ($x) => max($x['paid'] ?? 0, $x['invoiced'] ?? 0)));
    $maxMonth = max(1, (float) collect($items)->max(fn ($x) => max($x['value'] ?? 0, $x['income'] ?? 0, $x['expense'] ?? 0)));
    $defaultIdx = min(count($days) - 1, 19);
    $defaultPoint = $days[$defaultIdx] ?? ['label' => now()->locale('tr')->translatedFormat('j M'), 'paid' => 0, 'invoiced' => 0];
@endphp
<div data-revenue-chart class="relative z-10">
    <div data-view-panel="bar" class="relative pt-8">
        <div data-timeline-tip class="pointer-events-none absolute bottom-12 z-30 min-w-[148px] -translate-x-1/2 rounded-xl border border-[#2a2a32] bg-[#18181c]/95 px-3.5 py-2.5 text-left shadow-[0_16px_36px_rgba(0,0,0,0.75)] backdrop-blur-md transition-all duration-200 ease-out" style="left: {{ round(($defaultIdx + 0.5) / max(1, count($days)) * 100, 2) }}%">
            <p data-tip-date class="text-xs font-semibold text-white">{{ $defaultPoint['label'] }}</p>
            <div class="mt-1.5 space-y-1 text-[11px]">
                <div class="flex items-center justify-between gap-3"><span class="text-[#71717a]">Tahsilat</span><span data-tip-paid class="font-medium text-emerald-400 tabular-nums">{{ tl($defaultPoint['paid'] ?? 0) }}</span></div>
                <div class="flex items-center justify-between gap-3"><span class="text-[#71717a]">Tahakkuk</span><span data-tip-inv class="font-medium text-white tabular-nums">{{ tl($defaultPoint['invoiced'] ?? 0) }}</span></div>
            </div>
        </div>

        <div class="overflow-x-auto pb-1 [scrollbar-width:none]">
            <div class="grid min-w-[920px] gap-1" style="grid-template-columns: repeat({{ count($days) }}, minmax(0, 1fr))">
                @foreach ($days as $idx => $d)
                    @php
                        $paid = (float) ($d['paid'] ?? 0);
                        $inv = (float) ($d['invoiced'] ?? 0);
                        $val = max($paid, $inv);
                        $pct = $val > 0 ? max(8, min(100, round($val / $maxDay * 100))) : 0;
                        $leftPct = round(($idx + 0.5) / max(1, count($days)) * 100, 2);
                    @endphp
                    <div class="group relative flex h-[220px] cursor-pointer flex-col items-center justify-end"
                         data-timeline-col
                         data-date="{{ $d['label'] }}"
                         data-paid="{{ tl($paid) }}"
                         data-inv="{{ tl($inv) }}"
                         data-left="{{ $leftPct }}">
                        <span class="pointer-events-none absolute inset-y-4 w-px bg-gradient-to-t from-white/25 via-white/10 to-transparent opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                        <div class="relative mb-2.5 flex h-[175px] w-full items-end justify-center gap-[2px]">
                            @if ($inv > 0)
                                <span class="grow-h w-1.5 rounded-full bg-white/80 transition-all duration-200 group-hover:w-2 group-hover:bg-white" style="height: {{ max(8, round($inv / $maxDay * 100)) }}%; --d: {{ $idx }}"></span>
                            @endif
                            @if ($paid > 0)
                                <span class="grow-h w-1.5 rounded-full bg-emerald-500 transition-all duration-200 group-hover:w-2 group-hover:bg-emerald-400" style="height: {{ max(8, round($paid / $maxDay * 100)) }}%; --d: {{ $idx }}"></span>
                            @endif
                        </div>
                        <span data-timeline-dot class="relative z-10 h-2.5 w-2.5 rounded-full transition-all duration-200 group-hover:scale-125 group-hover:shadow-[0_0_12px_rgba(255,255,255,0.7)] {{ $idx === $defaultIdx ? 'scale-110 bg-white shadow-[0_0_10px_rgba(255,255,255,0.5)]' : ($val > 0 ? 'bg-emerald-500/70 group-hover:bg-white' : 'bg-[#232329] group-hover:bg-white') }}"></span>
                        <span class="mt-2.5 text-[10px] text-[#52525b] transition-colors duration-200 group-hover:text-white whitespace-nowrap">{{ $d['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div data-view-panel="month" class="hidden pt-6">
        <div class="grid grid-cols-6 gap-2 sm:grid-cols-12 sm:gap-2.5">
            @foreach ($items as $idx => $m)
                @php
                    $val = (float) ($m['value'] ?? $m['income'] ?? 0);
                    $exp = (float) ($m['expense'] ?? ($val * 0.65));
                    $pctInc = $val > 0 ? max(10, min(100, round($val / $maxMonth * 100))) : 8;
                    $pctExp = $exp > 0 ? max(8, min(100, round($exp / $maxMonth * 100))) : 6;
                @endphp
                <div class="group relative flex flex-col items-center">
                    <div class="pointer-events-none absolute -top-14 left-1/2 z-30 hidden min-w-[136px] -translate-x-1/2 rounded-xl border border-[#2a2a32] bg-[#18181c]/95 px-2.5 py-1.5 text-[10px] shadow-xl backdrop-blur-md group-hover:block">
                        <p class="font-semibold text-white">{{ $m['full'] ?? $m['label'] }}</p>
                        <p class="mt-0.5 flex justify-between gap-2 text-emerald-400"><span>Gelir:</span><span class="tabular-nums">{{ tl($val) }}</span></p>
                        <p class="flex justify-between gap-2 text-[#e4e4e7]"><span>Gider:</span><span class="tabular-nums">{{ tl($exp) }}</span></p>
                    </div>
                    <div class="flex h-[185px] w-full max-w-[48px] items-end justify-center gap-1 rounded-2xl border border-transparent bg-[#18181c] p-1.5 transition-all duration-200 group-hover:-translate-y-1 group-hover:border-[#2c2c34]">
                        <span class="grow-h w-2 rounded-full bg-emerald-500 transition-all group-hover:bg-emerald-400" style="height: {{ $pctInc }}%; --d: {{ $idx }}" title="Gelir: {{ tl($val) }}"></span>
                        <span class="grow-h w-2 rounded-full bg-white/85 transition-all group-hover:bg-white" style="height: {{ $pctExp }}%; --d: {{ $idx }}" title="Gider: {{ tl($exp) }}"></span>
                    </div>
                    <span class="mt-2 text-[11px] text-[#71717a] transition-colors group-hover:text-white">{{ $m['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
