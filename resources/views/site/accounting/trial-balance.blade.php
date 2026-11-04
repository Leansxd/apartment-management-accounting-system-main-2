<x-layout heading="Mizan" :subheading="$apartment->name.' · hesap bazında borç / alacak toplamları'">
    <x-slot:actions>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div><label class="label" for="from">Başlangıç</label><input class="input" type="date" id="from" name="from" value="{{ $from }}"></div>
            <div><label class="label" for="to">Bitiş</label><input class="input" type="date" id="to" name="to" value="{{ $to }}"></div>
            <button class="btn btn-secondary">Göster</button>
            <button type="button" class="btn btn-primary" onclick="window.print()">Yazdır</button>
        </form>
    </x-slot:actions>

    <section class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Hesap</th><th>Alt hesap</th><th class="num">Borç</th><th class="num">Alacak</th><th class="num">Bakiye</th></tr></thead>
                <tbody>
                @forelse ($tb['groups'] as $account => $g)
                    <tr class="bg-slate-50/70 font-semibold"><td colspan="2">{{ $account }}</td><td class="num">{{ tl($g['debit']) }}</td><td class="num">{{ tl($g['credit']) }}</td><td class="num">{{ tl($g['balance']) }}</td></tr>
                    @foreach ($g['rows'] as $r)
                        <tr><td></td><td class="text-slate-500">{{ $r['sub'] }}</td><td class="num">{{ $r['debit'] ? tl($r['debit']) : '' }}</td><td class="num">{{ $r['credit'] ? tl($r['credit']) : '' }}</td><td class="num">{{ tl($r['balance']) }}</td></tr>
                    @endforeach
                @empty
                    <tr><td colspan="5" class="text-center text-slate-500">Kayıt yok.</td></tr>
                @endforelse
                <tr class="bg-slate-50 font-bold"><td colspan="2">GENEL TOPLAM</td><td class="num">{{ tl($tb['debit']) }}</td><td class="num">{{ tl($tb['credit']) }}</td>
                    <td class="num">@if (round($tb['debit'] - $tb['credit'], 2) == 0)<span class="text-emerald-600">Dengede ✓</span>@else<span class="text-rose-600">{{ tl($tb['debit'] - $tb['credit']) }}</span>@endif</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
