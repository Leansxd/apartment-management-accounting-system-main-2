<x-layout heading="Kasa Defteri" :subheading="$apartment->name">
    <x-slot:actions>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div><label class="label" for="cashbox_id">Kasa</label><select class="input" id="cashbox_id" name="cashbox_id">@foreach ($cashboxes as $c)<option value="{{ $c->id }}" @selected($cashbox?->id === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div><label class="label" for="from">Başlangıç</label><input class="input" type="date" id="from" name="from" value="{{ $from }}"></div>
            <div><label class="label" for="to">Bitiş</label><input class="input" type="date" id="to" name="to" value="{{ $to }}"></div>
            <button class="btn btn-secondary">Göster</button>
            <button type="button" class="btn btn-primary" onclick="window.print()">Yazdır</button>
        </form>
    </x-slot:actions>

    @if ($book)
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="stat"><p class="stat-label">Devir</p><p class="stat-value">{{ tl($book['opening']) }}</p></div>
            <div class="stat"><p class="stat-label">Giriş</p><p class="stat-value text-emerald-600">{{ tl($book['in']) }}</p></div>
            <div class="stat"><p class="stat-label">Çıkış</p><p class="stat-value text-rose-600">{{ tl($book['out']) }}</p></div>
            <div class="stat"><p class="stat-label">Kapanış</p><p class="stat-value">{{ tl($book['closing']) }}</p></div>
        </div>
        <section class="card">
            <div class="card-head"><h2 class="card-title">{{ $cashbox->name }} · {{ \Carbon\Carbon::parse($from)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($to)->format('d.m.Y') }}</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Tarih</th><th>No</th><th>Açıklama</th><th>Daire</th><th class="num">Giriş</th><th class="num">Çıkış</th><th class="num">Bakiye</th></tr></thead>
                    <tbody>
                    <tr class="bg-slate-50/70"><td colspan="6" class="font-medium">Devreden bakiye</td><td class="num font-semibold">{{ tl($book['opening']) }}</td></tr>
                    @forelse ($book['rows'] as $r)
                        <tr class="{{ $r['tx']->isReversed() ? 'text-slate-400' : '' }}">
                            <td class="whitespace-nowrap">{{ $r['tx']->created_at->format('d.m.Y H:i') }}</td>
                            <td class="font-mono text-xs">{{ $r['tx']->receiptNo() }}</td>
                            <td>{{ $r['tx']->description }}@if ($r['tx']->isReversed()) <span class="badge badge-red">İptal</span>@endif</td>
                            <td>{{ $r['tx']->unit?->label() ?? '—' }}</td>
                            <td class="num">{{ $r['in'] ? tl($r['in']) : '' }}</td>
                            <td class="num">{{ $r['out'] ? tl($r['out']) : '' }}</td>
                            <td class="num font-medium">{{ tl($r['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-slate-500">Bu aralıkta hareket yok.</td></tr>
                    @endforelse
                    <tr class="bg-slate-50 font-bold"><td colspan="4">TOPLAM / KAPANIŞ</td><td class="num">{{ tl($book['in']) }}</td><td class="num">{{ tl($book['out']) }}</td><td class="num">{{ tl($book['closing']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <p class="text-sm text-slate-500">Henüz kasa yok.</p>
    @endif
</x-layout>
