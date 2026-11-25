<x-layout heading="Borçlandırma & Tahakkuk" :subheading="$apartment->name">
    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    <details class="panel card" @if($errors->any()) open @endif>
        <summary class="card-head"><span class="card-title">+ Borç dağıt</span><span class="text-xs text-slate-500">Dairenin avansı varsa borçtan otomatik düşülür</span></summary>
        <form method="POST" action="{{ route('site.debts.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            <div class="form-grid">
                <div><label class="label" for="target">Hedef *</label>
                    <select class="input" id="target" name="target" data-show-target>
                        <option value="all" @selected(old('target') === 'all')>Tüm site ({{ $units->count() }} daire)</option>
                        <option value="block" @selected(old('target') === 'block')>Blok</option>
                        <option value="group" @selected(old('target') === 'group')>Daire grubu</option>
                        <option value="unit" @selected(old('target') === 'unit')>Tek daire</option>
                    </select></div>
                <div data-target-for="block" hidden><label class="label" for="block_id">Blok</label>
                    <select class="input" id="block_id" name="block_id">@foreach ($blocks as $b)<option value="{{ $b->id }}">{{ $b->name }} Blok</option>@endforeach</select></div>
                <div data-target-for="group" hidden><label class="label" for="unit_group_id">Grup</label>
                    <select class="input" id="unit_group_id" name="unit_group_id">@foreach ($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select></div>
                <div data-target-for="unit" hidden><label class="label" for="unit_id_t">Daire</label>
                    <select class="input" id="unit_id_t" name="unit_id">@foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->label() }}</option>@endforeach</select></div>
                <div><label class="label" for="category_id">Kategori</label>
                    <select class="input" id="category_id" name="category_id"><option value="">Genel</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                <div><label class="label" for="description">Açıklama *</label><input class="input" id="description" name="description" value="{{ old('description') }}" required placeholder="ör. Ocak 2026 Aidatı"></div>
                <div><label class="label" for="amount">Daire başı tutar (₺) *</label><input class="input" id="amount" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required></div>
                <div><label class="label" for="responsible">Sorumlu</label>
                    <select class="input" id="responsible" name="responsible"><option value="">Daire geneli</option>@foreach (\App\Models\Debt::RESPONSIBLES as $value => $label)<option value="{{ $value }}" @selected(old('responsible') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="label" for="due_date">Son ödeme tarihi *</label><input class="input" id="due_date" type="date" name="due_date" value="{{ old('due_date', now()->endOfMonth()->toDateString()) }}" required></div>
            </div>
            <button class="btn btn-primary">Borçları oluştur</button>
        </form>
    </details>

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Borç kayıtları</h2>
            <form method="GET" class="flex flex-wrap gap-2">
                <label class="sr-only" for="f-unit">Daire</label>
                <select class="input !w-auto" id="f-unit" name="unit_id"><option value="">Tüm daireler</option>@foreach ($units as $u)<option value="{{ $u->id }}" @selected($filters['unit_id'] == $u->id)>{{ $u->label() }}</option>@endforeach</select>
                <label class="sr-only" for="f-status">Durum</label>
                <select class="input !w-auto" id="f-status" name="status">
                    <option value="">Tümü</option>
                    @foreach (['open' => 'Açık', 'overdue' => 'Gecikmiş', 'paid' => 'Ödenmiş'] as $k => $v)<option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $v }}</option>@endforeach
                </select>
                <label class="sr-only" for="f-q">Ara</label>
                <input class="input !w-auto" id="f-q" name="q" value="{{ $filters['q'] }}" placeholder="Açıklama ara">
                <label class="sr-only" for="f-block">Blok</label>
                <select class="input !w-auto" id="f-block" name="block_id"><option value="">Tüm bloklar</option>@foreach ($blocks as $b)<option value="{{ $b->id }}" @selected((int) request('block_id') === $b->id)>{{ $b->name }}</option>@endforeach</select>
                <label class="sr-only" for="f-kind">Borç türü</label>
                <select class="input !w-auto" id="f-kind" name="kind"><option value="">Tüm türler</option>@foreach (['due' => 'Aidat / borç', 'installment' => 'Taksit', 'late_fee' => 'Gecikme zammı'] as $k => $v)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ $v }}</option>@endforeach</select>
                <label class="sr-only" for="f-category">Kategori</label>
                <select class="input !w-auto" id="f-category" name="category"><option value="">Tüm kategoriler</option>@foreach ($categories as $c)<option @selected(request('category') === $c->name)>{{ $c->name }}</option>@endforeach</select>
                <label class="sr-only" for="f-from">Vade başlangıç</label>
                <input class="input !w-auto" type="date" id="f-from" name="from" value="{{ request('from') }}" title="Vade başlangıç">
                <label class="sr-only" for="f-to">Vade bitiş</label>
                <input class="input !w-auto" type="date" id="f-to" name="to" value="{{ request('to') }}" title="Vade bitiş">
                <label class="flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="legal" value="1" class="rounded border-slate-300" @checked(request('legal'))>İcradakiler</label>
                <button class="btn btn-secondary">Filtrele</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Daire</th><th>Açıklama</th><th>Kategori</th><th>Vade</th><th class="num">Tutar</th><th class="num">Kalan</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                @forelse ($debts as $d)
                    <tr>
                        <td class="font-medium"><x-unit-link :unit="$d->unit" /></td>
                        <td>{{ $d->description }}
                            @if ($d->kind === 'late_fee')<span class="badge badge-amber ml-1">Gecikme zammı</span>@elseif ($d->kind === 'installment')<span class="badge badge-blue ml-1">Taksit</span>@endif
                            @if ($d->expense_id)<a class="badge badge-blue ml-1" href="{{ route('site.expenses.index', [$apartment, 'q' => $d->expense?->invoice_no ?: $d->expense?->description]) }}">Gider yansıtma</a>@endif
                            @if ($d->legal_status)<span class="badge badge-red ml-1" title="{{ $d->legal_note }}">İcra</span>@endif
                            @if ($d->responsible)<span class="badge badge-gray ml-1">{{ \App\Models\Debt::RESPONSIBLES[$d->responsible] ?? $d->responsible }}</span>@endif</td>
                        <td>{{ $d->category_name }}</td>
                        <td class="whitespace-nowrap">{{ $d->due_date->format('d.m.Y') }}</td>
                        <td class="num">{{ tl($d->amount) }}</td>
                        <td class="num font-semibold">{{ tl($d->remaining_amount) }}</td>
                        <td><x-debt-status :debt="$d" /></td>
                        <td class="text-right whitespace-nowrap">
                            @if ($d->status === 'pending')
                                <a class="btn btn-secondary btn-sm" href="{{ route('site.collections.index', [$apartment, 'unit_id' => $d->unit_id, 'debt_id' => $d->id]) }}">Tahsil et</a>
                                @if ($d->kind !== 'late_fee')
                                <details class="relative inline-block text-left">
                                    <summary class="btn btn-secondary btn-sm">Taksitlendir</summary>
                                    <form method="POST" action="{{ route('site.debts.installments', [$apartment, $d]) }}" class="absolute right-0 z-10 mt-1 w-72 card card-body space-y-2 shadow-lg">
                                        @csrf
                                        <p class="text-xs text-slate-500">Kalan {{ tl($d->remaining_amount) }} eşit taksitlere bölünür.</p>
                                        <label class="label" for="ic-{{ $d->id }}">Taksit sayısı</label><input class="input" id="ic-{{ $d->id }}" type="number" name="count" min="2" max="36" value="3" required>
                                        <label class="label" for="if-{{ $d->id }}">İlk taksit tarihi</label><input class="input" id="if-{{ $d->id }}" type="date" name="first_due" min="{{ today()->toDateString() }}" value="{{ today()->addMonth()->toDateString() }}" required>
                                        <label class="label" for="ie-{{ $d->id }}">Aralık (ay)</label><input class="input" id="ie-{{ $d->id }}" type="number" name="every_months" min="1" max="12" value="1" required>
                                        <button class="btn btn-primary w-full">Taksitlendir</button>
                                    </form>
                                </details>
                                @endif
                                <form method="POST" action="{{ route('site.debts.legal', [$apartment, $d]) }}" class="inline" data-prompt-note="{{ $d->legal_status ? '' : 'İcra / dosya notu (isteğe bağlı):' }}">
                                    @csrf<input type="hidden" name="note" value="">
                                    <button class="btn btn-sm {{ $d->legal_status ? 'btn-secondary' : 'btn-danger' }}">{{ $d->legal_status ? 'İcrayı kaldır' : 'İcraya al' }}</button>
                                </form>
                            @endif
                            @if ($d->status === \App\Models\Debt::PENDING && $d->kind !== 'late_fee')
                                @php $isPaidPart = (float) $d->paid_amount > 0; @endphp
                                <x-dash.edit-panel :action="route('site.debts.update', [$apartment, $d])">
                                    <div><label class="label" for="ed-desc-{{ $d->id }}">Açıklama *</label><input class="input" id="ed-desc-{{ $d->id }}" name="description" maxlength="200" value="{{ $d->description }}" required></div>
                                    <div><label class="label" for="ed-due-{{ $d->id }}">Vade *</label><input class="input" id="ed-due-{{ $d->id }}" type="date" name="due_date" value="{{ $d->due_date->toDateString() }}" required></div>
                                    <div><label class="label" for="ed-amt-{{ $d->id }}">Tutar *</label><input class="input" id="ed-amt-{{ $d->id }}" type="number" step="0.01" min="0.01" name="amount" value="{{ $d->amount }}" required @disabled($isPaidPart)></div>
                                    <div><label class="label" for="ed-cat-{{ $d->id }}">Kategori</label><select class="input" id="ed-cat-{{ $d->id }}" name="category_id" @disabled($isPaidPart)><option value="">Genel</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected($c->id === $d->category_id)>{{ $c->name }}</option>@endforeach</select></div>
                                    <div><label class="label" for="ed-resp-{{ $d->id }}">Sorumlu</label><select class="input" id="ed-resp-{{ $d->id }}" name="responsible"><option value="">Daire geneli</option>@foreach (\App\Models\Debt::RESPONSIBLES as $value => $label)<option value="{{ $value }}" @selected($d->responsible === $value)>{{ $label }}</option>@endforeach</select></div>
                                    @if ($isPaidPart)<p class="text-xs text-slate-500">Ödeme alındığı için tutar ve kategori değiştirilemez.</p>@endif
                                </x-dash.edit-panel>
                            @endif
                            @if ((float) $d->paid_amount === 0.0)
                                <form method="POST" action="{{ route('site.debts.destroy', [$apartment, $d]) }}" class="inline" onsubmit="return confirm('Borç silinsin mi? (Ters yevmiye kaydı atılır)')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-slate-500">Borç kaydı yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $debts->links() }}</div>
    </section>
</x-layout>
