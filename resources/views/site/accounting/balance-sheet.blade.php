<x-layout heading="Mali Durum Tablosu" :subheading="$apartment->name.' · '.$asOf->format('d.m.Y').' itibarıyla'">
    <x-slot:actions><x-report.actions /></x-slot:actions>

    <x-report.toolbar>
        <div><label class="label" for="as_of">Tarih itibarıyla</label><input class="input" type="date" id="as_of" name="as_of" value="{{ $asOf->toDateString() }}"></div>
        <div><label class="label" for="compare_to">Karşılaştırma tarihi</label><input class="input" type="date" id="compare_to" name="compare_to" value="{{ $compareTo->toDateString() }}"></div>
    </x-report.toolbar>

    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    @php
        $change = fn (string $key) => round($sheet[$key] - $previous[$key], 2);
        $diff = fn (float $value) => $value == 0 ? '—' : ($value > 0 ? '+' : '−').tl(abs($value));
        $previousCash = $previous['cash']->keyBy(fn ($c) => $c['cashbox']->id);
        $columns = '<th class="num">'.$asOf->format('d.m.Y').'</th><th class="num">'.$compareTo->format('d.m.Y').'</th><th class="num">Değişim</th>';
    @endphp

    <x-report.print-header :apartment="$apartment" title="Mali Durum Tablosu" :as-of="$asOf->toDateString()" :note="'Karşılaştırma: '.$compareTo->format('d.m.Y')" />

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="card">
            <div class="card-head"><h2 class="card-title">Varlıklar</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Kalem</th>{!! $columns !!}</tr></thead>
                    <tbody>
                    @foreach ($sheet['cash'] as $c)
                        <tr>
                            <td class="pl-8 text-slate-500"><a class="hover:text-brand-600 hover:underline" href="{{ route('site.accounting.cash-book', [$apartment, 'cashbox_id' => $c['cashbox']->id, 'to' => $asOf->toDateString()]) }}">{{ $c['cashbox']->labelWithCurrency() }}</a>@if ($c['cashbox']->isForeign())<span class="block text-[11px]">{{ money($c['native'], $c['cashbox']->currency) }}</span>@endif</td>
                            <td class="num">{{ tl($c['balance']) }}</td>
                            <td class="num text-slate-500">{{ tl($previousCash[$c['cashbox']->id]['balance'] ?? 0) }}</td>
                            <td class="num text-slate-500">{{ $diff(round($c['balance'] - ($previousCash[$c['cashbox']->id]['balance'] ?? 0), 2)) }}</td>
                        </tr>
                    @endforeach
                    <tr class="font-medium"><td>Kasa & Banka toplamı</td><td class="num">{{ tl($sheet['cash_total']) }}</td><td class="num">{{ tl($previous['cash_total']) }}</td><td class="num">{{ $diff($change('cash_total')) }}</td></tr>
                    <tr class="font-medium">
                        <td><a class="hover:text-brand-600 hover:underline" href="{{ route('site.accounting.aging', [$apartment, 'as_of' => $asOf->toDateString()]) }}">Daire alacakları</a>
                            @if ($sheet['overdue'] !== null)<span class="block text-xs font-normal text-rose-600">gecikmiş {{ tl($sheet['overdue']) }}</span>@endif</td>
                        <td class="num">{{ tl($sheet['receivables']) }}</td><td class="num">{{ tl($previous['receivables']) }}</td><td class="num">{{ $diff($change('receivables')) }}</td>
                    </tr>
                    <tr class="font-medium"><td><a class="hover:text-brand-600 hover:underline" href="{{ route('site.assets.index', $apartment) }}">Demirbaşlar (net defter değeri)</a></td><td class="num">{{ tl($sheet['fixed_assets']) }}</td><td class="num">{{ tl($previous['fixed_assets']) }}</td><td class="num">{{ $diff($change('fixed_assets')) }}</td></tr>
                    </tbody>
                    <tfoot><tr class="bg-slate-50 font-bold"><td>TOPLAM VARLIK</td><td class="num">{{ tl($sheet['assets_total']) }}</td><td class="num">{{ tl($previous['assets_total']) }}</td><td class="num">{{ $diff($change('assets_total')) }}</td></tr></tfoot>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h2 class="card-title">Yükümlülükler ve net varlık</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Kalem</th>{!! $columns !!}</tr></thead>
                    <tbody>
                    <tr class="font-medium"><td><a class="hover:text-brand-600 hover:underline" href="{{ route('site.advances.index', $apartment) }}">Daire avansları</a><span class="block text-xs font-normal text-slate-500">sakinlerin peşin ödediği, iadesi veya mahsubu gereken tutar</span></td><td class="num">{{ tl($sheet['advances']) }}</td><td class="num">{{ tl($previous['advances']) }}</td><td class="num">{{ $diff($change('advances')) }}</td></tr>
                    <tr class="font-medium"><td><a class="hover:text-brand-600 hover:underline" href="{{ route('site.expenses.index', [$apartment, 'status' => 'pending']) }}">Ödenecek giderler</a><span class="block text-xs font-normal text-slate-500">girilmiş ama ödenmemiş faturalar</span></td><td class="num">{{ tl($sheet['payables']) }}</td><td class="num">{{ tl($previous['payables']) }}</td><td class="num">{{ $diff($change('payables')) }}</td></tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-bold"><td>TOPLAM YÜKÜMLÜLÜK</td><td class="num">{{ tl($sheet['liabilities_total']) }}</td><td class="num">{{ tl($previous['liabilities_total']) }}</td><td class="num">{{ $diff($change('liabilities_total')) }}</td></tr>
                        <tr class="bg-brand-50/60 font-black"><td>NET VARLIK (Varlık − Yükümlülük)</td><td class="num {{ $sheet['net'] < 0 ? 'text-rose-600' : '' }}">{{ tl($sheet['net']) }}</td><td class="num">{{ tl($previous['net']) }}</td><td class="num">{{ $diff($change('net')) }}</td></tr>
                    </tfoot>
                </table>
            </div>
            <p class="card-body text-xs text-slate-400">Kasa, alacak ve avans tutarları yevmiye kayıtlarından seçilen günün sonu itibarıyla hesaplanır. Demirbaşlar doğrusal amortismanla değerlenir. Alacaklar vadesi gelmemiş borçları da içerir; gecikmiş alacak yalnızca bugünün tablosunda gösterilir.</p>
        </section>
    </div>
    <x-report.signatures />
</x-layout>
