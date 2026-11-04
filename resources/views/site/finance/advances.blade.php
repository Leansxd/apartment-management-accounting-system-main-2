<x-layout heading="Avans & Mahsup" :subheading="$apartment->name.' · toplam avans '.tl($total)">
    <section class="card">
        <div class="card-head"><h2 class="card-title">Avansı olan daireler</h2>
            <span class="text-xs text-slate-500">Fazla ödemeler yeni borca otomatik mahsup edilir; buradan elle mahsup veya iade yapabilirsiniz.</span></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Daire</th><th class="num">Avans</th><th class="num">Açık borç</th><th>İşlem</th></tr></thead>
                <tbody>
                @forelse ($units as $u)
                    <tr>
                        <td class="font-medium">{{ $u->label() }}</td>
                        <td class="num font-semibold text-emerald-600">{{ tl($u->credit_balance) }}</td>
                        <td class="num">{{ tl($openByUnit[$u->id] ?? 0) }}</td>
                        <td class="flex flex-wrap items-center gap-2">
                            @if (($openByUnit[$u->id] ?? 0) > 0)
                                <form method="POST" action="{{ route('site.advances.apply', [$apartment, $u]) }}" onsubmit="return confirm('Avans açık borçlara mahsup edilsin mi?')">@csrf<button class="btn btn-secondary btn-sm">Borca mahsup et</button></form>
                            @endif
                            <form method="POST" action="{{ route('site.advances.refund', [$apartment, $u]) }}" class="flex items-center gap-1">
                                @csrf
                                <label class="sr-only" for="rb-{{ $u->id }}">Kasa</label>
                                <select class="input !w-auto !py-1 text-xs" id="rb-{{ $u->id }}" name="cashbox_id">@foreach ($cashboxes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                                <label class="sr-only" for="ra-{{ $u->id }}">İade tutarı</label>
                                <input class="input !w-28 !py-1 text-xs" id="ra-{{ $u->id }}" type="number" step="0.01" min="0.01" max="{{ $u->credit_balance }}" name="amount" value="{{ $u->credit_balance }}" required>
                                <button class="btn btn-danger btn-sm" onclick="return confirm('Avans kasadan iade edilsin mi?')">İade et</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-slate-500">Avansı olan daire yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
