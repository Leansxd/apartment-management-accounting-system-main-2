<x-layout heading="Gider Yönetimi" :subheading="$apartment->name">
    <x-dash.module-summary :summary="$moduleSummary ?? null" />

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
                <div><label class="label" for="pay_now">Hemen öde</label><select class="input" id="pay_now" name="pay_now_cashbox_id"><option value="">Hayır, onaya bırak</option>@foreach ($cashboxes->where('currency', 'TRY') as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ tl($c->balance) }})</option>@endforeach</select></div>
            </div>
            <fieldset class="rounded-xl border border-slate-200 p-3">
                <legend class="px-1 text-sm font-semibold">Daireye yansıt <span class="font-normal text-slate-500">(isteğe bağlı)</span></legend>
                <p class="mb-3 text-xs text-slate-500">Bir daireye gönderilen servis gibi masrafları seçilen daireye aynı anda borç olarak yazar. Borç bu gidere bağlı kalır.</p>
                <div class="form-grid">
                    <div><label class="label" for="recharge_unit_id">Daire</label><select class="input" id="recharge_unit_id" name="recharge_unit_id"><option value="">Yansıtma</option>@foreach ($units as $u)<option value="{{ $u->id }}" @selected((string) old('recharge_unit_id') === (string) $u->id)>{{ $u->label() }}</option>@endforeach</select></div>
                    <div><label class="label" for="recharge_amount">Daireden alınacak (₺)</label><input class="input" id="recharge_amount" type="number" step="0.01" min="0.01" name="recharge_amount" value="{{ old('recharge_amount') }}" placeholder="Boş: KDV dahil tutar"></div>
                    <div><label class="label" for="recharge_responsible">Sorumlu</label><select class="input" id="recharge_responsible" name="recharge_responsible"><option value="">Daire geneli</option>@foreach (\App\Models\Debt::RESPONSIBLES as $value => $label)<option value="{{ $value }}" @selected(old('recharge_responsible') === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div><label class="label" for="recharge_due_date">Son ödeme tarihi</label><input class="input" id="recharge_due_date" type="date" name="recharge_due_date" value="{{ old('recharge_due_date', today()->endOfMonth()->toDateString()) }}"></div>
                </div>
            </fieldset>
            <button class="btn btn-primary">Gideri kaydet</button>
        </form>
    </details>

    <x-dash.filters :fields="[['name' => 'vendor_id', 'label' => 'Firma', 'type' => 'select', 'options' => $vendors->pluck('name', 'id')->all()], ['name' => 'category_id', 'label' => 'Kategori', 'type' => 'select', 'options' => $categories->pluck('name', 'id')->all()], ['name' => 'from', 'label' => 'Başlangıç', 'type' => 'date'], ['name' => 'to', 'label' => 'Bitiş', 'type' => 'date'], ['name' => 'q', 'label' => 'Ara', 'type' => 'search', 'placeholder' => 'Açıklama veya fatura no']]" :keep="['status']" />

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Giderler</h2>
            <div class="flex gap-1">
                @foreach (['' => 'Tümü', 'pending' => 'Ödeme bekleyen', 'partial' => 'Kısmi ödenen', 'approved' => 'Ödendi'] as $k => $v)
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
                        <td>{{ $e->description }} @if($e->invoice_no)<span class="text-xs text-slate-400">#{{ $e->invoice_no }}</span>@endif
                            @if ($e->rechargedDebt)<span class="block text-xs text-slate-500">Daireye yansıtıldı: <x-unit-link :unit="$e->rechargedDebt->unit" /> · {{ tl($e->rechargedDebt->amount) }} · {{ $e->rechargedDebt->status === 'paid' ? 'tahsil edildi' : 'kalan '.tl($e->rechargedDebt->remaining_amount) }}</span>@endif</td>
                        <td>{{ $e->vendor?->name ?? '—' }}</td>
                        <td>{{ $e->category?->name ?? '—' }}</td>
                        <td class="num">{{ tl($e->amount) }}</td>
                        <td class="num font-semibold">{{ tl($e->total_amount) }}</td>
                        <td>@if ($e->status === 'approved')<span class="badge badge-green">Ödendi · {{ $e->cashbox?->name }}</span>@elseif ($e->isPartiallyPaid())<span class="badge badge-blue">Kısmi ödendi</span><span class="block text-xs text-slate-500">{{ tl($e->paid_amount) }} ödendi · kalan {{ tl($e->remainingAmount()) }}</span>@else<span class="badge badge-amber">Onay bekliyor</span>@endif</td>
                        <td class="text-right whitespace-nowrap">
                            @if ($e->status === 'pending')
                                <form method="POST" action="{{ route('site.expenses.approve', [$apartment, $e]) }}" class="inline-flex gap-1">
                                    @csrf
                                    <label class="sr-only" for="ap-{{ $e->id }}">Kasa</label>
                                    <select class="input !w-auto !py-1 text-xs" id="ap-{{ $e->id }}" name="cashbox_id" data-expense-cashbox>@foreach ($cashboxes as $c)<option value="{{ $c->id }}" data-currency="{{ $c->currency }}" data-rate="{{ $rateSuggestions[$c->currency]['rate'] ?? '' }}">{{ $c->labelWithCurrency() }}</option>@endforeach</select>
                                    <input class="input !w-24 !py-1 text-xs" type="number" step="0.000001" min="0.000001" name="exchange_rate" placeholder="Kur (₺)" aria-label="Kur" data-expense-rate hidden disabled>
                                    <input class="input !w-28 !py-1 text-xs text-right" type="number" step="0.01" min="0.01" max="{{ $e->remainingAmount() }}" name="amount" value="{{ number_format($e->remainingAmount(), 2, '.', '') }}" aria-label="Ödenecek tutar (₺)" title="Ödenecek tutar (₺); azaltırsanız kısmi ödeme olur">
                                    <button class="btn btn-primary btn-sm">{{ $e->isPartiallyPaid() ? 'Öde' : 'Onayla / Öde' }}</button>
                                </form>
                                @unless ($e->isPartiallyPaid())
                                <x-dash.edit-panel :action="route('site.expenses.update', [$apartment, $e])" width="w-[28rem]">
                                    <div><label class="label" for="ex-desc-{{ $e->id }}">Açıklama *</label><input class="input" id="ex-desc-{{ $e->id }}" name="description" maxlength="200" value="{{ $e->description }}" required></div>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div><label class="label" for="ex-vendor-{{ $e->id }}">Firma</label><select class="input" id="ex-vendor-{{ $e->id }}" name="vendor_id"><option value="">—</option>@foreach ($vendors as $v)<option value="{{ $v->id }}" @selected($v->id === $e->vendor_id)>{{ $v->name }}</option>@endforeach</select></div>
                                        <div><label class="label" for="ex-cat-{{ $e->id }}">Kategori</label><select class="input" id="ex-cat-{{ $e->id }}" name="category_id"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected($c->id === $e->category_id)>{{ $c->name }}</option>@endforeach</select></div>
                                        <div><label class="label" for="ex-amt-{{ $e->id }}">Net tutar *</label><input class="input" id="ex-amt-{{ $e->id }}" type="number" step="0.01" min="0.01" name="amount" value="{{ $e->amount }}" required></div>
                                        <div><label class="label" for="ex-vat-{{ $e->id }}">KDV</label><select class="input" id="ex-vat-{{ $e->id }}" name="vat_rate">@foreach ([20, 10, 1, 0] as $r)<option value="{{ $r }}" @selected((int) $e->vat_rate === $r)>%{{ $r }}</option>@endforeach</select></div>
                                        <div><label class="label" for="ex-no-{{ $e->id }}">Fatura no</label><input class="input" id="ex-no-{{ $e->id }}" name="invoice_no" maxlength="40" value="{{ $e->invoice_no }}"></div>
                                        <div><label class="label" for="ex-date-{{ $e->id }}">Fatura tarihi</label><input class="input" id="ex-date-{{ $e->id }}" type="date" name="invoice_date" value="{{ $e->invoice_date?->toDateString() }}"></div>
                                    </div>
                                </x-dash.edit-panel>
                                <form method="POST" action="{{ route('site.expenses.destroy', [$apartment, $e]) }}" class="inline" onsubmit="return confirm('Gider silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                                @endunless
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
