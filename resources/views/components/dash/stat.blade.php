@props(['label', 'value', 'prev' => null, 'format' => 'money', 'invert' => false, 'note' => null, 'href' => null])
@php
    $value = (float) $value;
    $money = function (float $v): string {
        [$int, $dec] = explode(',', number_format($v, 2, ',', '.'));
        return '₺'.$int.'<span class="dec">,'.$dec.'</span>';
    };
    $fmt = fn (float $v) => match ($format) {
        'percent' => number_format($v, 1, ',', '.').'<span class="dec"> %</span>',
        'number' => number_format($v, 0, ',', '.'),
        default => $money($v),
    };
    $pct = null;
    if ($prev !== null && ! ((float) $prev == 0.0 && $value == 0.0)) {
        $pct = $format === 'percent' ? $value - (float) $prev : ((float) $prev == 0.0 ? 100.0 : ($value - (float) $prev) / abs((float) $prev) * 100);
    }
    $good = $invert ? ($pct ?? 0) <= 0 : ($pct ?? 0) >= 0;
    $plain = fn (float $v) => match ($format) {
        'percent' => number_format($v, 1, ',', '.').'%',
        'number' => number_format($v, 0, ',', '.'),
        default => tl($v),
    };
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif class="flex min-w-0 flex-col border-b border-line px-4 py-5 transition-colors last:border-b-0 sm:px-6 lg:border-b-0 {{ $href ? 'hover:bg-slate-50/60' : '' }}">
    <span class="text-[13px] font-medium text-ink">{{ $label }}</span>
    <p class="mt-3.5 truncate text-[28px] font-bold leading-none tracking-[-0.03em] tabular-nums text-ink" data-count="{{ $value }}" data-format="{{ $format }}">{!! $fmt($value) !!}</p>
    <div class="mt-3 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs">
        <span class="text-muted">{{ $note ?? ($prev !== null ? $plain((float) $prev).' geçen dönem' : 'tüm zamanlar') }}</span>
        @if ($pct !== null)
            <span class="inline-flex items-center gap-1 font-semibold {{ $good ? 'text-emerald-600' : 'text-rose-500' }}">
                <x-icon :name="$pct >= 0 ? 'trending-up' : 'trending-down'" :size="14" />{{ $pct >= 0 ? '+' : '−' }} {{ number_format(abs($pct), 1, ',', '.') }}%
            </span>
        @endif
    </div>
</{{ $tag }}>
