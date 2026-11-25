<x-layout heading="Daire Hesap Ekstresi" :subheading="$apartment->name.($unit ? ' · '.$unit->label() : '')">
    @if ($unit)
        <x-slot:actions><x-report.actions /></x-slot:actions>
    @endif

    <x-report.toolbar :range="(bool) $unit" :from="$from" :to="$to">
        <div><label class="label" for="unit_id">Daire</label>
            <select class="input" id="unit_id" name="unit_id" required data-autosubmit><option value="">Seçin</option>@foreach ($units as $u)<option value="{{ $u->id }}" @selected($unit?->id === $u->id)>{{ $u->label() }}</option>@endforeach</select></div>
    </x-report.toolbar>

    @if ($statement)
        <x-dash.module-summary :summary="$moduleSummary ?? null" />

        <div class="no-print grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="stat"><p class="stat-label">Devreden bakiye</p><p class="stat-value whitespace-nowrap text-xl">{{ tl($statement['opening']) }}</p></div>
            <div class="stat"><p class="stat-label">Dönemde yazılan borç</p><p class="stat-value whitespace-nowrap text-xl text-rose-600">{{ tl($statement['period_debit']) }}</p></div>
            <div class="stat"><p class="stat-label">Dönemde ödenen</p><p class="stat-value whitespace-nowrap text-xl text-emerald-600">{{ tl($statement['period_credit']) }}</p></div>
            <div class="stat"><p class="stat-label">Kapanış bakiyesi</p><p class="stat-value whitespace-nowrap text-xl {{ $statement['closing'] > 0 ? 'text-rose-600' : '' }}">{{ tl($statement['closing']) }}</p><p class="text-xs text-slate-500">{{ $statement['closing'] > 0 ? 'borçlu' : ($statement['closing'] < 0 ? 'alacaklı (avans)' : 'borcu yok') }}</p></div>
        </div>

        <section class="card">
            <x-report.print-header :apartment="$apartment" title="Daire Hesap Ekstresi" :from="$from" :to="$to"
                :note="'Daire: '.$unit->label().($unit->members->isNotEmpty() ? ' · '.$unit->members->pluck('full_name')->implode(', ') : '')" />
            <div class="card-body no-print border-b border-slate-100">
                <div class="flex flex-wrap justify-between gap-4">
                    <div>
                        <p class="text-lg font-bold">{{ $apartment->name }}</p>
                        <p class="text-sm text-slate-500">{{ $apartment->address }}</p>
                    </div>
                    <div class="text-right text-sm">
                        <p class="font-semibold">HESAP EKSTRESİ — <x-unit-link :unit="$unit" /></p>
                        <p><x-member-links :members="$unit->members" empty="" /></p>
                        <p class="text-slate-500">{{ $from ? \Carbon\Carbon::parse($from)->format('d.m.Y') : 'Başlangıç' }} – {{ $to ? \Carbon\Carbon::parse($to)->format('d.m.Y') : today()->format('d.m.Y') }}</p>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Tarih</th><th>Açıklama</th><th>Vade</th><th class="num">Borç</th><th class="num">Alacak</th><th class="num">Bakiye</th></tr></thead>
                    <tbody>
                    <tr class="bg-slate-50"><td colspan="5" class="font-medium">Devreden bakiye</td><td class="num font-semibold">{{ tl($statement['opening']) }}</td></tr>
                    @forelse ($statement['rows'] as $r)
                        <tr>
                            <td class="whitespace-nowrap">{{ $r['date']->format('d.m.Y') }}</td>
                            <td>@if ($r['tx_id'] ?? null)<a class="hover:text-brand-600 hover:underline" href="{{ route('site.transactions.receipt', [$apartment, $r['tx_id']]) }}">{{ $r['description'] }}</a>@else{{ $r['description'] }}@endif</td>
                            <td class="whitespace-nowrap">{{ $r['due']?->format('d.m.Y') }}</td>
                            <td class="num">{{ $r['debit'] ? tl($r['debit']) : '' }}</td>
                            <td class="num">{{ $r['credit'] ? tl($r['credit']) : '' }}</td>
                            <td class="num font-medium">{{ tl($r['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-slate-500">Bu aralıkta hareket yok.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-semibold"><td colspan="3">Dönem toplamı</td><td class="num">{{ tl($statement['period_debit']) }}</td><td class="num">{{ tl($statement['period_credit']) }}</td><td></td></tr>
                        <tr class="bg-slate-50"><td colspan="5" class="font-semibold">Kapanış bakiyesi (+ borçlu / − alacaklı)</td><td class="num font-bold">{{ tl($statement['closing']) }}</td></tr>
                    </tfoot>
                </table>
            </div>
            <div class="card-body flex flex-wrap gap-6 text-sm text-slate-600">
                <span>Bugünkü açık borç: <b>{{ tl($statement['open_debt']) }}</b></span>
                <span>Avans: <b>{{ tl($statement['credit']) }}</b></span>
                <span class="no-print"><a class="text-brand-600 hover:underline" href="{{ route('site.collections.index', [$apartment, 'unit_id' => $unit->id]) }}">Tahsilat al →</a></span>
                <span class="ml-auto text-xs text-slate-400">Düzenlenme: {{ now()->format('d.m.Y H:i') }}</span>
            </div>
            <p class="print-only mt-3 text-[8.5pt]">Bu ekstre {{ now()->format('d.m.Y') }} tarihindeki kayıtlara göre düzenlenmiştir. Kapanış bakiyesi pozitifse daire borçlu, negatifse alacaklıdır (avans).</p>
        </section>
        <x-report.signatures :roles="['Düzenleyen', 'Yönetici']" />
    @else
        <p class="card card-body text-sm text-slate-500">Ekstresini görmek istediğiniz daireyi seçin.</p>
    @endif
</x-layout>
