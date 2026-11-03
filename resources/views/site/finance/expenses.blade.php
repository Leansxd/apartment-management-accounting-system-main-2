<x-layout heading="Gider Yönetimi" :subheading="$apartment->name">
    <details class="panel card" @if($errors->any()) open @endif>
        <summary class="card-head"><span class="card-title">+ Gider / fatura gir</span><span class="text-xs text-slate-500">Onaylanınca KDV dahil tutar seçilen kasadan düşülür</span></summary>
        <form method="POST" action="{{ route('site.expenses.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            <div class="form-grid">
                <div><label class="label" for="vendor_id">Firma</label><select class="input" id="vendor_id" name="vendor_id"><option value="">—</option>@foreach ($vendors as $v)<option value="{{ $v->id }}" @selected(old('vendor_id') == $v->id)>{{ $v->name }}</option>@endforeach</select></div>
                <div><label class="label" for="category_id">Kategori</label><select class="input" id="category_id" name="category_id"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                <div><label class="label" for="description">Açıklama *</label><input class="input" id="description" name="description" value="{{ old('description') }}" required></div>
                <div><label class="label" for="invoice_no">Fatura no</label><input class="input" id="invoice_no" name="invoice_no" value="{{ old('invoice_no') }}"></div>
                <div><label class="label" for="amount">Tutar (KDV hariç) *</label><input class="input" id="amount" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required></div>
                <div><label class="label" for="vat_rate">KDV</label><select class="input" id="vat_rate" name="vat_rate">@foreach ([20, 10, 1, 0] as $r)<option value="{{ $r }}" @selected(old('vat_rate', 20) == $r)>%{{ $r }}</option>@endforeach</select></div>
                <div><label class="label" for="invoice_date">Fatura tarihi</label><input class="input" id="invoice_date" type="date" name="invoice_date" value="{{ old('invoice_date', today()->toDateString()) }}"></div>
                <div><label class="label" for="pay_now">Hemen öde</label><select class="input" id="pay_now" name="pay_now_cashbox_id"><option value="">Hayır, onaya bırak</option>@foreach ($cashboxes as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ tl($c->balance) }})</option>@endforeach</select></div>
            </div>
            <button class="btn btn-primary">Gideri kaydet</button>
        </form>
    </details>

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Giderler</h2>
            <div class="flex gap-1">
                @foreach (['' => 'Tümü', 'pending' => 'Onay bekleyen', 'approved' => 'Onaylı'] as $k => $v)
                    <a class="btn btn-sm {{ (string) $status === $k ? 'btn-primary' : 'btn-secondary' }}" href="{{ route('site.expenses.index', [$apartment, 'status' => $k ?: null]) }}">{{ $v }}</a>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Açıklama</th><th>Firma</th><th>Kategori</th><th class="num">Net</th><th class="num">KDV dahil</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                @forelse ($expenses as $e)
                    <tr>
                        <td class="whitespace-nowrap">{{ ($e->invoice_date ?? $e->created_at)->format('d.m.Y') }}</td>
                        <td>{{ $e->description }} @if($e->invoice_no)<span class="text-xs text-slate-400">#{{ $e->invoice_no }}</span>@endif</td>
                        <td>{{ $e->vendor?->name ?? '—' }}</td>
                        <td>{{ $e->category?->name ?? '—' }}</td>
                        <td class="num">{{ tl($e->amount) }}</td>
                        <td class="num font-semibold">{{ tl($e->total_amount) }}</td>
                        <td>@if ($e->status === 'approved')<span class="badge badge-green">Ödendi · {{ $e->cashbox?->name }}</span>@else<span class="badge badge-amber">Onay bekliyor</span>@endif</td>
                        <td class="text-right whitespace-nowrap">
                            @if ($e->status === 'pending')
                                <form method="POST" action="{{ route('site.expenses.approve', [$apartment, $e]) }}" class="inline-flex gap-1">
                                    @csrf
                                    <label class="sr-only" for="ap-{{ $e->id }}">Kasa</label>
                                    <select class="input !w-auto !py-1 text-xs" id="ap-{{ $e->id }}" name="cashbox_id">@foreach ($cashboxes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                                    <button class="btn btn-primary btn-sm">Onayla</button>
                                </form>
                                <form method="POST" action="{{ route('site.expenses.destroy', [$apartment, $e]) }}" class="inline" onsubmit="return confirm('Gider silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-slate-500">Gider kaydı yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $expenses->links() }}</div>
    </section>
</x-layout>
