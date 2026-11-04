@props(['points' => []])
@php
    $default = [
        ['day' => 'Pzt', 'date' => '31 Eki 2026', 'v1' => 110, 'v2' => 65],
        ['day' => 'Sal', 'date' => '1 Kas 2026', 'v1' => 185, 'v2' => 95],
        ['day' => 'Çar', 'date' => '2 Kas 2026', 'v1' => 115, 'v2' => 110],
        ['day' => 'Per', 'date' => '3 Kas 2026', 'v1' => 220, 'v2' => 100],
        ['day' => 'Cum', 'date' => '4 Kas 2026', 'v1' => 95, 'v2' => 130],
        ['day' => 'Cmt', 'date' => '5 Kas 2026', 'v1' => 155, 'v2' => 90],
        ['day' => 'Paz', 'date' => '6 Kas 2026', 'v1' => 175, 'v2' => 205],
    ];
    $pts = count($points) === 7 ? $points : $default;
    $w = 720;
    $h = 180;
    $padX = 24;
    $padY = 18;
    $maxV = 300;
    $coords = collect($pts)->map(function ($p, $i) use ($w, $h, $padX, $padY, $maxV) {
        $x = round($padX + $i * (($w - $padX * 2) / 6), 1);
        $y1 = round($h - $padY - (min($maxV, max(0, $p['v1'])) / $maxV) * ($h - $padY * 2), 1);
        $y2 = round($h - $padY - (min($maxV, max(0, $p['v2'])) / $maxV) * ($h - $padY * 2), 1);
        return $p + ['x' => $x, 'y1' => $y1, 'y2' => $y2, 'pctX' => round($x / $w * 100, 2), 'pctY1' => round($y1 / $h * 100, 2)];
    })->all();

    $curve = function (array $list, string $key): string {
        $d = "M {$list[0]['x']} {$list[0][$key]}";
        for ($i = 0; $i < count($list) - 1; $i++) {
            $curr = $list[$i];
            $next = $list[$i + 1];
            $cx1 = round(($curr['x'] + $next['x']) / 2, 1);
            $cx2 = $cx1;
            $d .= " C {$cx1} {$curr[$key]}, {$cx2} {$next[$key]}, {$next['x']} {$next[$key]}";
        }
        return $d;
    };
    $path1 = $curve($coords, 'y1');
    $path2 = $curve($coords, 'y2');
    $area1 = $path1." L {$coords[6]['x']} ".($h - $padY)." L {$coords[0]['x']} ".($h - $padY).' Z';
    $active = $coords[3];
@endphp
<div class=" flex gap-4" data-spline>
    <div class="flex h-[180px] flex-col justify-between py-2 text-right text-xs text-muted tabular-nums">
        <span>300</span>
        <span>200</span>
        <span>100</span>
        <span>0</span>
    </div>
    <div class="relative flex-1">
        <div class="relative h-[180px] w-full">
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-full w-full overflow-visible" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="spline-grad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#6e5ce0" stop-opacity="0.16" />
                        <stop offset="100%" stop-color="#6e5ce0" stop-opacity="0.0" />
                    </linearGradient>
                </defs>
                <path d="{{ $area1 }}" fill="url(#spline-grad)" />
                <path d="{{ $path2 }}" fill="none" stroke="#c3baf5" stroke-width="2" stroke-dasharray="5 5" vector-effect="non-scaling-stroke" />
                <path d="{{ $path1 }}" fill="none" stroke="#6e5ce0" stroke-width="2.2" vector-effect="non-scaling-stroke" />
                <line data-spline-line x1="{{ $active['x'] }}" y1="{{ $active['y1'] }}" x2="{{ $active['x'] }}" y2="{{ $h - $padY }}" stroke="#c3baf5" stroke-width="1.5" stroke-dasharray="3 3" vector-effect="non-scaling-stroke" />
            </svg>

            <span data-spline-dot class="pointer-events-none absolute z-20 h-3.5 w-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[2.5px] border-white bg-[#17162c] shadow-md transition-all duration-200" style="left: {{ $active['pctX'] }}%; top: {{ $active['pctY1'] }}%"></span>

            <div data-spline-tip class="pointer-events-none absolute z-30 w-[152px] -translate-y-1/2 rounded-2xl bg-[#1e1d33] p-3.5 text-white shadow-[0_18px_38px_-10px_rgba(23,22,44,0.45)] transition-all duration-200" style="left: calc({{ $active['pctX'] }}% + 14px); top: {{ max(26, min(72, $active['pctY1'])) }}%">
                <div class="flex items-center gap-2 text-xs font-semibold">
                    <i class="h-3.5 w-[3px] rounded-full bg-[#6e5ce0]"></i>
                    <span data-spline-v1 class="tabular-nums">{{ $active['v1'] }} işlem</span>
                </div>
                <div class="mt-1.5 flex items-center gap-2 text-xs font-semibold">
                    <i class="h-3.5 w-[3px] rounded-full bg-[#7c7a94]"></i>
                    <span data-spline-v2 class="tabular-nums">{{ $active['v2'] }} işlem</span>
                </div>
                <div class="mt-2.5 text-[11px] text-white/60">
                    <b data-spline-day class="font-semibold text-white">{{ $active['day'] }}</b>
                    <span data-spline-date>{{ $active['date'] }}</span>
                </div>
            </div>

            <div class="absolute inset-0 z-10 grid grid-cols-7">
                @foreach ($coords as $idx => $c)
                    <button type="button" class="h-full w-full cursor-pointer bg-transparent focus:outline-none"
                        data-spline-point="{{ $idx }}"
                        data-x="{{ $c['x'] }}"
                        data-y1="{{ $c['y1'] }}"
                        data-pct-x="{{ $c['pctX'] }}"
                        data-pct-y="{{ $c['pctY1'] }}"
                        data-day="{{ $c['day'] }}"
                        data-date="{{ $c['date'] }}"
                        data-v1="{{ $c['v1'] }} işlem"
                        data-v2="{{ $c['v2'] }} işlem"
                        aria-label="{{ $c['day'] }} {{ $c['v1'] }} işlem"></button>
                @endforeach
            </div>
        </div>

        <div class="mt-2 grid grid-cols-7 text-center text-xs text-muted">
            @foreach ($coords as $idx => $c)
                <span data-spline-axis="{{ $idx }}" class="{{ $idx === 3 ? 'font-semibold text-ink' : '' }}">{{ $c['day'] }}</span>
            @endforeach
        </div>
    </div>
</div>

