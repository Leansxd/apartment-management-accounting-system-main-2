<x-layout heading="Bloklar & Daireler" :subheading="$apartment->name.' · '.$units->count().' daire'">
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card">
            <div class="card-head"><h2 class="card-title">Bloklar</h2></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($blocks as $block)
                    <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                        <span><b>{{ $block->name }}</b> Blok <span class="text-slate-400">· {{ $block->units_count }} daire</span></span>
                        <form method="POST" action="{{ route('site.blocks.destroy', [$apartment, $block]) }}" onsubmit="return confirm('Blok silinsin mi?')">
                            @csrf @method('DELETE')<button class="btn btn-danger btn-sm" aria-label="{{ $block->name }} bloğunu sil">Sil</button>
                        </form>
                    </li>
                @empty
                    <li class="px-5 py-3 text-sm text-slate-500">Blok yok.</li>
                @endforelse
            </ul>
            <form method="POST" action="{{ route('site.blocks.store', $apartment) }}" class="card-body flex gap-2 border-t border-slate-100">
                @csrf
                <label class="sr-only" for="block-name">Blok adı</label>
                <input class="input" id="block-name" name="name" placeholder="Blok adı (ör. B)" required maxlength="30">
                <button class="btn btn-primary">Ekle</button>
            </form>
        </section>

        <section class="card lg:col-span-2">
            <div class="card-head"><h2 class="card-title">Daire Ekle</h2></div>
            <div class="card-body space-y-6">
                <form method="POST" action="{{ route('site.units.store', $apartment) }}" class="space-y-3">
                    @csrf
                    <div class="form-grid">
                        <div><label class="label" for="u-block">Blok *</label>
                            <select class="input" id="u-block" name="block_id" required>@foreach ($blocks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
                        <div><label class="label" for="u-no">Daire no *</label><input class="input" id="u-no" name="unit_no" required maxlength="20"></div>
                        <div><label class="label" for="u-floor">Kat</label><input class="input" id="u-floor" name="floor" maxlength="10"></div>
                        <div><label class="label" for="u-area">Brüt m²</label><input class="input" id="u-area" type="number" step="0.01" min="0" name="gross_area"></div>
                        <div><label class="label" for="u-usage">Kullanım</label>
                            <select class="input" id="u-usage" name="usage_type">@foreach (['Konut', 'İşyeri', 'Depo', 'Otopark'] as $o)<option>{{ $o }}</option>@endforeach</select></div>
                        <div><label class="label" for="u-status">Durum</label>
                            <select class="input" id="u-status" name="status"><option>Dolu</option><option>Boş</option></select></div>
                        <div><label class="label" for="u-share">Arsa payı</label><input class="input" id="u-share" type="number" step="0.0001" min="0" name="land_share"></div>
                        <div class="flex items-end"><button class="btn btn-primary w-full">Daire ekle</button></div>
                    </div>
                </form>

                <form method="POST" action="{{ route('site.units.bulk', $apartment) }}" class="rounded-lg bg-slate-50 p-4 space-y-3">
                    @csrf
                    <p class="text-sm font-medium text-slate-700">Toplu daire ekle</p>
                    <div class="form-grid">
                        <div><label class="label" for="b-block">Blok</label>
                            <select class="input" id="b-block" name="block_id" required>@foreach ($blocks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
                        <div><label class="label" for="b-from">İlk no</label><input class="input" id="b-from" type="number" name="from" value="1" min="1" required></div>
                        <div><label class="label" for="b-to">Son no</label><input class="input" id="b-to" type="number" name="to" value="10" min="1" required></div>
                        <div><label class="label" for="b-area">m² (opsiyonel)</label><input class="input" id="b-area" type="number" step="0.01" min="0" name="gross_area"></div>
                    </div>
                    <button class="btn btn-secondary">Toplu ekle</button>
                </form>
            </div>
        </section>
    </div>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Daireler</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Daire</th><th>Kat</th><th>Kullanım</th><th>Durum</th><th class="num">m²</th><th>Sakinler</th><th class="num">Avans</th><th></th></tr></thead>
                <tbody>
                @forelse ($units as $u)
                    <tr>
                        <td class="font-medium">{{ $u->label() }}</td>
                        <td>{{ $u->floor ?: '—' }}</td>
                        <td>{{ $u->usage_type }}</td>
                        <td><span class="badge {{ $u->status === 'Dolu' ? 'badge-green' : 'badge-gray' }}">{{ $u->status }}</span></td>
                        <td class="num">{{ $u->gross_area ? number_format($u->gross_area, 2, ',', '.') : '—' }}</td>
                        <td class="text-xs">{{ $u->members->pluck('full_name')->implode(', ') ?: '—' }}</td>
                        <td class="num">{{ (float) $u->credit_balance ? tl($u->credit_balance) : '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <details class="inline-block text-left relative">
                                <summary class="btn btn-secondary btn-sm">Düzenle</summary>
                                <form method="POST" action="{{ route('site.units.update', [$apartment, $u]) }}" class="absolute right-0 z-10 mt-1 w-72 card card-body space-y-2 shadow-lg">
                                    @csrf @method('PUT')
                                    <select class="input" name="block_id" aria-label="Blok">@foreach ($blocks as $b)<option value="{{ $b->id }}" @selected($b->id === $u->block_id)>{{ $b->name }}</option>@endforeach</select>
                                    <input class="input" name="unit_no" value="{{ $u->unit_no }}" aria-label="Daire no" required>
                                    <input class="input" name="floor" value="{{ $u->floor }}" placeholder="Kat" aria-label="Kat">
                                    <input class="input" name="gross_area" type="number" step="0.01" value="{{ $u->gross_area }}" placeholder="m²" aria-label="m²">
                                    <input class="input" name="land_share" type="number" step="0.0001" value="{{ $u->land_share }}" placeholder="Arsa payı" aria-label="Arsa payı">
                                    <select class="input" name="usage_type" aria-label="Kullanım">@foreach (['Konut', 'İşyeri', 'Depo', 'Otopark'] as $o)<option @selected($o === $u->usage_type)>{{ $o }}</option>@endforeach</select>
                                    <select class="input" name="status" aria-label="Durum">@foreach (['Dolu', 'Boş'] as $o)<option @selected($o === $u->status)>{{ $o }}</option>@endforeach</select>
                                    <button class="btn btn-primary w-full">Kaydet</button>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('site.units.destroy', [$apartment, $u]) }}" class="inline" onsubmit="return confirm(@js($u->label().' silinsin mi?'))">
                                @csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-slate-500">Henüz daire yok. Yukarıdan ekleyin.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Daire Grupları</h2><span class="text-xs text-slate-500">Borç dağıtımında hedef olarak kullanılabilir</span></div>
        <div class="card-body grid gap-6 lg:grid-cols-2">
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($groups as $g)
                    <li class="flex justify-between py-2">
                        <span><b>{{ $g->name }}</b> <span class="text-slate-400">· {{ $g->units_count }} daire</span> <span class="block text-xs text-slate-500">{{ $g->details }}</span></span>
                        <form method="POST" action="{{ route('site.groups.destroy', [$apartment, $g]) }}" onsubmit="return confirm('Grup silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">Grup yok.</li>
                @endforelse
            </ul>
            <form method="POST" action="{{ route('site.groups.store', $apartment) }}" class="space-y-2">
                @csrf
                <label class="label" for="g-name">Grup adı</label><input class="input" id="g-name" name="name" required>
                <label class="label" for="g-details">Açıklama</label><input class="input" id="g-details" name="details">
                <label class="label" for="g-units">Daireler (Ctrl ile çoklu seçim)</label>
                <select class="input h-32" id="g-units" name="unit_ids[]" multiple>@foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->label() }}</option>@endforeach</select>
                <button class="btn btn-primary">Grup oluştur</button>
            </form>
        </div>
    </section>
</x-layout>
