@props(['pct', 'count' => 44])
@php
    $pct = max(0, min(100, (float) $pct));
    $filled = (int) round($pct / 100 * $count);
@endphp
<div data-tick-bar data-pct="{{ $pct }}" data-total="{{ $count }}">
    <div class="flex h-6 items-stretch gap-[3.5px]" role="img" aria-label="Yüzde {{ number_format($pct, 0) }}">
        @for ($t = 0; $t < $count; $t++)
            <span data-tick="{{ $t }}" class="tick-in flex-1 rounded-[2px] transition-colors duration-150 {{ $t < $filled ? 'bg-emerald-500' : 'bg-slate-200/80' }}" style="--d: {{ $t }}"></span>
        @endfor
    </div>
    <div class="relative mt-2.5 h-4 text-xs text-muted tabular-nums">
        <span class="absolute left-0">0</span>
        <span class="absolute left-1/2 -translate-x-1/2">50</span>
        <span class="absolute right-0">100</span>
        <span data-tick-label class="absolute -translate-x-1/2 font-semibold text-ink transition-[left] duration-75" style="left: {{ max(8, min(92, $pct)) }}%">{{ number_format($pct, 0) }}%</span>
    </div>
</div>
