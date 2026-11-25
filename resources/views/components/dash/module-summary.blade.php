@props(['summary' => null])
@php
    $metrics = $summary['metrics'] ?? [];
    $panels = $summary['panels'] ?? [];
    $toneClass = ['good' => 'metric-good', 'warn' => 'metric-warn', 'bad' => 'metric-bad'];
@endphp
@if ($metrics || $panels)
    <div class="no-print space-y-4" data-module-summary>
        @if ($metrics)
            {{-- Kart sayısı kaç olursa olsun satırı eşit genişlikte doldurur (eskiden 6 sütunlu ızgarada 5 kart sağda boşluk bırakıyordu) --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-[repeat(auto-fit,minmax(10rem,1fr))]">
                @foreach ($metrics as $metric)
                    <div class="stat min-w-0 !p-4">
                        <p class="stat-label truncate" title="{{ $metric['label'] }}">{{ $metric['label'] }}</p>
                        <p class="mt-1.5 truncate text-lg font-bold tracking-tight tabular-nums {{ $toneClass[$metric['tone'] ?? ''] ?? 'text-white' }}" title="{{ $metric['value'] }}">{{ $metric['value'] }}</p>
                        @if ($metric['hint'] ?? null)<p class="mt-1 truncate text-[11px] text-slate-500" title="{{ $metric['hint'] }}">{{ $metric['hint'] }}</p>@endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($panels)
            @php
                // Panel sayısına göre sütun sayısı: trend paneli iki sütun kaplar; 2 sütuna sığan düzen yarı yarıya, daha fazlası üç sütuna dizilir
                $span = collect($panels)->sum(fn ($p) => $p['type'] === 'trend' ? 2 : 1);
                $panelGrid = $span <= 2 ? 'lg:grid-cols-2' : 'lg:grid-cols-2 xl:grid-cols-3';
            @endphp
            <div class="grid items-start gap-4 {{ $panelGrid }}">
                @foreach ($panels as $panel)
                    <section class="card min-w-0 {{ $panel['type'] === 'trend' ? 'lg:col-span-2 xl:col-span-2' : '' }}">
                        <div class="card-head !py-3">
                            <h2 class="card-title !text-[13px]">{{ $panel['title'] }}</h2>
                            @if ($panel['type'] === 'trend' && count($panel['series']) > 1)
                                <div class="flex items-center gap-3 text-[11px] text-slate-500">
                                    @foreach ($panel['series'] as $i => $series)
                                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background: var(--chart-{{ $i + 1 }})"></span>{{ $series }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @if ($panel['type'] === 'bars')
                            <div class="card-body !py-4">
                                @forelse ($panel['rows'] as $row)
                                    <div class="py-1.5" title="{{ $row['label'] }}: {{ $row['display'] }}">
                                        <div class="flex items-baseline justify-between gap-3 text-xs">
                                            <span class="truncate text-slate-500">{{ $row['label'] }}</span>
                                            <span class="shrink-0 font-semibold tabular-nums text-white">{{ $row['display'] }}</span>
                                        </div>
                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full chart-track">
                                            <div class="h-full rounded-full" style="width: {{ $row['share'] }}%; background: var(--chart-1)"></div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="py-4 text-center text-xs text-slate-500">Henüz veri yok.</p>
                                @endforelse
                            </div>
                        @else
                            @php
                                $peak = max(1, (float) collect($panel['months'])->flatMap(fn ($m) => $m['values'])->max());
                                $hasData = collect($panel['months'])->flatMap(fn ($m) => $m['values'])->sum() > 0;
                            @endphp
                            <div class="card-body !py-4">
                                @if ($hasData)
                                    <p class="mb-1 text-[11px] tabular-nums text-slate-500">En yüksek: {{ $panel['format'] === 'money' ? tl($peak) : number_format($peak, 0, ',', '.') }}</p>
                                    <div class="flex h-36 items-stretch gap-1 border-b chart-axis" aria-hidden="true">
                                        @foreach ($panel['months'] as $month)
                                            <div class="group relative flex min-w-0 flex-1 items-end justify-center gap-[2px] rounded-t-md transition-colors hover:bg-white/5">
                                                @foreach ($month['values'] as $i => $value)
                                                    <span class="w-full max-w-[12px] rounded-t" style="height: {{ $value > 0 ? max(2, round($value / $peak * 100, 1)) : 0 }}%; background: var(--chart-{{ $i + 1 }})"></span>
                                                @endforeach
                                                <div class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-1 hidden min-w-[130px] -translate-x-1/2 rounded-xl border border-[#2a2a32] bg-[#18181c] px-2.5 py-1.5 text-[11px] shadow-xl group-hover:block">
                                                    <p class="font-semibold text-white">{{ $month['label'] }}</p>
                                                    @foreach ($month['values'] as $i => $value)
                                                        <p class="flex justify-between gap-3 text-[#a1a1aa]"><span>{{ $panel['series'][$i] }}</span><span class="tabular-nums text-white">{{ $panel['format'] === 'money' ? tl($value) : number_format($value, 0, ',', '.') }}</span></p>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="mt-1.5 flex gap-1" aria-hidden="true">
                                        @foreach ($panel['months'] as $month)
                                            <span class="min-w-0 flex-1 truncate text-center text-[10px] text-slate-500">{{ $month['label'] }}</span>
                                        @endforeach
                                    </div>
                                    <div class="sr-only"><table>
                                        <caption>{{ $panel['title'] }}</caption>
                                        <thead><tr><th>Dönem</th>@foreach ($panel['series'] as $series)<th>{{ $series }}</th>@endforeach</tr></thead>
                                        <tbody>@foreach ($panel['months'] as $month)<tr><td>{{ $month['label'] }}</td>@foreach ($month['values'] as $value)<td>{{ $panel['format'] === 'money' ? tl($value) : number_format($value, 0, ',', '.') }}</td>@endforeach</tr>@endforeach</tbody>
                                    </table></div>
                                @else
                                    <p class="py-10 text-center text-xs text-slate-500">Henüz veri yok.</p>
                                @endif
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </div>
@endif
