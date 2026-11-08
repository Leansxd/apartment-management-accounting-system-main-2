@props([
    'label',
    'value' => 0,
    'prev' => null,
    'format' => 'money',
    'invert' => false,
    'note' => null,
    'href' => null,
    'icon' => 'wallet',
    'progress' => null,
    'ratio' => null,
    'stagger' => 0,
])
@php
    $value = (float) $value;
    $fmt = fn (float $v) => match ($format) {
        'percent' => number_format($v, 0, ',', '.').'%',
        'number' => number_format($v, 0, ',', '.'),
        default => '₺'.number_format($v, 0, ',', '.'),
    };
    $tag = $href ? 'a' : 'div';
    $pctBar = $progress !== null ? max(0, min(100, (float) $progress)) : null;
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif style="--stagger: {{ (int) $stagger }}" class="spotlight-card reveal-up group flex min-h-[138px] min-w-0 flex-col justify-between rounded-[22px] border border-[#1f1f23] bg-[#141416] p-5 transition-all duration-300 hover:-translate-y-0.5 hover:border-[#2c2c34] hover:shadow-[0_14px_34px_rgba(0,0,0,0.45)]">
    <div class="relative z-10 flex items-center justify-between gap-2">
        <span class="text-xs font-medium text-[#8a8a93] transition-colors duration-200 group-hover:text-[#d4d4d8]">{{ $label }}</span>
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-[#26262c] bg-[#1a1a1f] text-[#71717a] transition-all duration-300 group-hover:scale-110 group-hover:border-[#3a3a44] group-hover:bg-[#23232a] group-hover:text-white">
            <x-icon :name="$icon" :size="13" />
        </span>
    </div>

    @if ($ratio !== null)
        <p class="relative z-10 my-2 truncate text-[28px] font-bold leading-none tracking-tight text-white tabular-nums">{{ $ratio }}</p>
    @else
        <p class="relative z-10 my-2 truncate text-[28px] font-bold leading-none tracking-tight text-white tabular-nums" data-count="{{ $value }}" data-format="{{ $format }}">{!! $fmt($value) !!}</p>
    @endif

    @if ($pctBar !== null)
        <div class="relative z-10 flex items-center gap-3">
            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-[#1f1f23]">
                <div class="grow-w h-full rounded-full bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.45)]" style="width: {{ $pctBar }}%"></div>
            </div>
            <span class="text-[11px] font-medium text-[#71717a] tabular-nums transition-colors group-hover:text-[#a1a1aa]">{{ round($pctBar) }}%</span>
        </div>
    @else
        <div class="relative z-10 flex items-center justify-between text-xs text-[#71717a] transition-colors group-hover:text-[#a1a1aa]">
            <span>{{ $note ?? '0 kayıt' }}</span>
        </div>
    @endif
</{{ $tag }}>
