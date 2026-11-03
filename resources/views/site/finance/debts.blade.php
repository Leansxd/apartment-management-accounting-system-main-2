<x-layout heading="Borçlandırma & Tahakkuk" :subheading="$apartment->name">
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
                <button class="btn btn-secondary">Filtrele</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Daire</th><th>Açıklama</th><th>Kategori</th><th>Vade</th><th class="num">Tutar</th><th class="num">Kalan</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                @forelse ($debts as $d)
                    <tr>
                        <td class="font-medium">{{ $d->unit->label() }}</td>
                        <td>{{ $d->description }}</td>
                        <td>{{ $d->category_name }}</td>
                        <td class="whitespace-nowrap">{{ $d->due_date->format('d.m.Y') }}</td>
                        <td class="num">{{ tl($d->amount) }}</td>
                        <td class="num font-semibold">{{ tl($d->remaining_amount) }}</td>
                        <td><x-debt-status :debt="$d" /></td>
                        <td class="text-right whitespace-nowrap">
                            @if ($d->status === 'pending')
                                <a class="btn btn-secondary btn-sm" href="{{ route('site.collections.index', [$apartment, 'unit_id' => $d->unit_id, 'debt_id' => $d->id]) }}">Tahsil et</a>
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
