<x-layout heading="Daire Hesap Ekstresi" :subheading="$apartment->name">
    <form method="GET" class="no-print card card-body flex flex-wrap items-end gap-3">
        <div><label class="label" for="unit_id">Daire</label>
            <select class="input" id="unit_id" name="unit_id" required><option value="">Seçin</option>@foreach ($units as $u)<option value="{{ $u->id }}" @selected($unit?->id === $u->id)>{{ $u->label() }}</option>@endforeach</select></div>
        <div><label class="label" for="from">Başlangıç</label><input class="input" type="date" id="from" name="from" value="{{ $from }}"></div>
        <div><label class="label" for="to">Bitiş</label><input class="input" type="date" id="to" name="to" value="{{ $to }}"></div>
        <button class="btn btn-primary">Göster</button>
        @if ($unit)<button type="button" class="btn btn-secondary" onclick="window.print()">Yazdır / PDF</button>@endif
    </form>

    @if ($statement)
        <section class="card">
            <div class="card-body border-b border-slate-100">
                <div class="flex flex-wrap justify-between gap-4">
                    <div>
                        <p class="text-lg font-bold">{{ $apartment->name }}</p>
                        <p class="text-sm text-slate-500">{{ $apartment->address }}</p>
                    </div>
                    <div class="text-right text-sm">
                        <p class="font-semibold">HESAP EKSTRESİ — {{ $unit->label() }}</p>
                        <p>{{ $unit->members->pluck('full_name')->implode(', ') }}</p>
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
                            <td>{{ $r['description'] }}</td>
                            <td>{{ $r['due']?->format('d.m.Y') }}</td>
                            <td class="num">{{ $r['debit'] ? tl($r['debit']) : '' }}</td>
                            <td class="num">{{ $r['credit'] ? tl($r['credit']) : '' }}</td>
                            <td class="num font-medium">{{ tl($r['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-slate-500">Bu aralıkta hareket yok.</td></tr>
                    @endforelse
                    <tr class="bg-slate-50"><td colspan="5" class="font-semibold">Kapanış bakiyesi (+ borçlu / − alacaklı)</td><td class="num font-bold">{{ tl($statement['closing']) }}</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-body text-sm text-slate-600 flex flex-wrap gap-6">
                <span>Açık borç: <b>{{ tl($statement['open_debt']) }}</b></span>
                <span>Avans: <b>{{ tl($statement['credit']) }}</b></span>
                <span class="ml-auto text-xs text-slate-400">Düzenlenme: {{ now()->format('d.m.Y H:i') }}</span>
            </div>
        </section>
    @endif
</x-layout>
