<x-layout heading="Kasa Defteri" :subheading="$apartment->name.($cashbox ? ' · '.$cashbox->labelWithCurrency() : '')">
    <x-slot:actions><x-report.actions :csv="(bool) $book" /></x-slot:actions>

    <x-report.toolbar :range="true" :from="$from" :to="$to">
        <div><label class="label" for="cashbox_id">Kasa / Banka</label>
            <select class="input" id="cashbox_id" name="cashbox_id">@foreach ($cashboxes as $c)<option value="{{ $c->id }}" @selected($cashbox?->id === $c->id)>{{ $c->labelWithCurrency() }}@unless ($c->is_active) (pasif)@endunless</option>@endforeach</select></div>
    </x-report.toolbar>

    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    @if ($book)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="stat"><p class="stat-label">Devir</p><p class="stat-value whitespace-nowrap text-xl">{{ tl($book['opening']) }}</p>@if ($cashbox->isForeign())<p class="text-xs text-slate-500">{{ money($book['native_opening'], $cashbox->currency) }}</p>@endif</div>
            <div class="stat"><p class="stat-label">Giriş</p><p class="stat-value whitespace-nowrap text-xl text-emerald-600">{{ tl($book['in']) }}</p><p class="text-xs text-slate-500">{{ collect($book['rows'])->where('in', '>', 0)->count() }} hareket</p></div>
            <div class="stat"><p class="stat-label">Çıkış</p><p class="stat-value whitespace-nowrap text-xl text-rose-600">{{ tl($book['out']) }}</p><p class="text-xs text-slate-500">{{ collect($book['rows'])->where('out', '>', 0)->count() }} hareket</p></div>
            <div class="stat"><p class="stat-label">Kapanış</p><p class="stat-value whitespace-nowrap text-xl">{{ tl($book['closing']) }}</p>@if ($cashbox->isForeign())<p class="text-xs text-slate-500">{{ money($book['native_closing'], $cashbox->currency) }}</p>@endif</div>
        </div>
        <section class="card">
            <x-report.print-header :apartment="$apartment" title="Kasa Defteri" :from="$from" :to="$to" :note="$cashbox->labelWithCurrency()" />
            <div class="card-head no-print"><h2 class="card-title">{{ $cashbox->labelWithCurrency() }} · {{ \Carbon\Carbon::parse($from)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($to)->format('d.m.Y') }}</h2>@if ($cashbox->isForeign())<span class="text-xs text-slate-500">Tutarlar TL karşılığıdır; altta {{ $cashbox->currency }} bakiyesi gösterilir</span>@endif</div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Tarih</th><th>No</th><th>Açıklama</th><th>Daire</th><th class="num">Giriş</th><th class="num">Çıkış</th><th class="num">Bakiye</th></tr></thead>
                    <tbody>
                    <tr class="bg-slate-50/70"><td colspan="6" class="font-medium">Devreden bakiye</td><td class="num font-semibold">{{ tl($book['opening']) }}</td></tr>
                    @forelse ($book['rows'] as $r)
                        <tr class="{{ $r['tx']->isReversed() ? 'text-slate-400' : '' }}">
                            <td class="whitespace-nowrap">{{ $r['tx']->created_at->format('d.m.Y H:i') }}</td>
                            <td class="whitespace-nowrap font-mono text-xs"><a class="hover:text-brand-600 hover:underline" href="{{ route('site.transactions.receipt', [$apartment, $r['tx']]) }}">{{ $r['tx']->receiptNo() }}</a></td>
                            <td>{{ $r['tx']->description }}@if ($r['tx']->originalLabel())<span class="block text-[11px] text-slate-500">{{ $r['tx']->originalLabel() }}</span>@endif @if ($r['tx']->isReversed()) <span class="badge badge-red">İptal</span>@endif</td>
                            <td><x-unit-link :unit="$r['tx']->unit" fallback="" /></td>
                            <td class="num">{{ $r['in'] ? tl($r['in']) : '' }}</td>
                            <td class="num">{{ $r['out'] ? tl($r['out']) : '' }}</td>
                            <td class="num font-medium">{{ tl($r['balance']) }}@if ($cashbox->isForeign())<span class="block text-[11px] font-normal text-slate-500">{{ money($r['native_balance'], $cashbox->currency) }}</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-slate-500">Bu aralıkta hareket yok.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-bold"><td colspan="4">TOPLAM / KAPANIŞ</td><td class="num">{{ tl($book['in']) }}</td><td class="num">{{ tl($book['out']) }}</td><td class="num">{{ tl($book['closing']) }}</td></tr>
                    </tfoot>
                </table>
            </div>
            <p class="card-body text-xs text-slate-400">İptal edilen hareketler ve iptal kayıtları birlikte listelenir; ikisi birbirini sıfırlar. Numaraya tıklayınca makbuz / işlem belgesi açılır.</p>
        </section>
    @else
        <p class="card card-body text-sm text-slate-500">Henüz kasa yok. Kasa & Banka ekranından kasa açabilirsiniz.</p>
    @endif
    <x-report.signatures />
</x-layout>
