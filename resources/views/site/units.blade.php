<x-layout heading="Bloklar & Daireler" :subheading="$apartment->name.' · '.$units->count().' daire'">
    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card">
            <div class="card-head"><h2 class="card-title">Bloklar</h2></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($blocks as $block)
                    <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                        <span><b>{{ $block->name }}</b> Blok <span class="text-slate-400">· {{ $block->units_count }} daire</span></span>
                        <span class="flex gap-1">
                        <x-dash.edit-panel :action="route('site.blocks.update', [$apartment, $block])" width="w-64">
                            <div><label class="label" for="bl-{{ $block->id }}">Blok adı *</label><input class="input uppercase" id="bl-{{ $block->id }}" name="name" maxlength="30" value="{{ $block->name }}" required></div>
                        </x-dash.edit-panel>
                        <form method="POST" action="{{ route('site.blocks.destroy', [$apartment, $block]) }}" onsubmit="return confirm('Blok silinsin mi?')">
                            @csrf @method('DELETE')<button class="btn btn-danger btn-sm" aria-label="{{ $block->name }} bloğunu sil">Sil</button>
                        </form>
                        </span>
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

    <x-dash.filters :fields="[['name' => 'block_id', 'label' => 'Blok', 'type' => 'select', 'options' => $blocks->pluck('name', 'id')->all()], ['name' => 'status', 'label' => 'Durum', 'type' => 'select', 'options' => ['Dolu' => 'Dolu', 'Boş' => 'Boş']], ['name' => 'usage_type', 'label' => 'Kullanım', 'type' => 'select', 'options' => ['Konut' => 'Konut', 'İşyeri' => 'İşyeri', 'Depo' => 'Depo', 'Otopark' => 'Otopark']], ['name' => 'debtors', 'label' => 'Yalnızca borçlular', 'type' => 'check']]" />

    <section class="card">
        <div class="card-head"><h2 class="card-title">Daireler</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Daire</th><th>Kat</th><th>Kullanım</th><th>Durum</th><th class="num">m²</th><th>Sakinler</th><th class="num">Açık borç</th><th class="num">Avans</th><th></th></tr></thead>
                <tbody>
                @forelse ($units as $u)
                    <tr>
                        <td class="whitespace-nowrap font-medium"><x-unit-link :unit="$u" /></td>
                        <td>{{ $u->floor ?: '—' }}</td>
                        <td>{{ $u->usage_type }}</td>
                        <td><span class="badge {{ $u->status === 'Dolu' ? 'badge-green' : 'badge-gray' }}" title="{{ $u->status === 'Dolu' ? 'Daire kullanılıyor' : 'Dairede oturan / kullanan yok (malik kayıtlı olabilir)' }}">{{ $u->status }}</span></td>
                        <td class="num">{{ $u->gross_area ? number_format($u->gross_area, 2, ',', '.') : '—' }}</td>
                        <td class="text-xs"><x-member-links :members="$u->members" :with-type="true" /></td>
                        <td class="num {{ ($debtByUnit[$u->id] ?? 0) > 0 ? 'metric-bad' : '' }}">{{ ($debtByUnit[$u->id] ?? 0) > 0 ? tl($debtByUnit[$u->id]) : '—' }}</td>
                        <td class="num">{{ (float) $u->credit_balance ? tl($u->credit_balance) : '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <x-dash.edit-panel :action="route('site.units.update', [$apartment, $u])" width="w-[32rem]">
                                <div>
                                    <p class="card-title">{{ $u->label() }} dairesini düzenle</p>
                                    <p class="text-xs text-slate-500">Sakinler: <x-member-links :members="$u->members" :with-type="true" empty="kayıtlı sakin yok" /></p>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div><label class="label" for="un-block-{{ $u->id }}">Blok *</label>
                                        <select class="input" id="un-block-{{ $u->id }}" name="block_id">@foreach ($blocks as $b)<option value="{{ $b->id }}" @selected($b->id === $u->block_id)>{{ $b->name }}</option>@endforeach</select></div>
                                    <div><label class="label" for="un-no-{{ $u->id }}">Daire no *</label>
                                        <input class="input" id="un-no-{{ $u->id }}" name="unit_no" value="{{ $u->unit_no }}" maxlength="20" required></div>
                                    <div><label class="label" for="un-floor-{{ $u->id }}">Kat</label>
                                        <input class="input" id="un-floor-{{ $u->id }}" name="floor" value="{{ $u->floor }}" placeholder="ör. 3"></div>
                                    <div><label class="label" for="un-usage-{{ $u->id }}">Kullanım</label>
                                        <select class="input" id="un-usage-{{ $u->id }}" name="usage_type">@foreach (['Konut', 'İşyeri', 'Depo', 'Otopark'] as $o)<option @selected($o === $u->usage_type)>{{ $o }}</option>@endforeach</select></div>
                                    <div><label class="label" for="un-area-{{ $u->id }}">Brüt alan (m²)</label>
                                        <input class="input" id="un-area-{{ $u->id }}" name="gross_area" type="number" step="0.01" min="0" value="{{ $u->gross_area }}" placeholder="ör. 120"></div>
                                    <div><label class="label" for="un-share-{{ $u->id }}">Arsa payı</label>
                                        <input class="input" id="un-share-{{ $u->id }}" name="land_share" type="number" step="0.0001" min="0" value="{{ $u->land_share }}" placeholder="ör. 0,0313">
                                        <p class="mt-0.5 text-[11px] text-slate-400">Arsa payına göre aidatta kullanılır</p></div>
                                    <div class="sm:col-span-2"><label class="label" for="un-status-{{ $u->id }}">Durum</label>
                                        <select class="input" id="un-status-{{ $u->id }}" name="status">@foreach (['Dolu' => 'Dolu — dairede oturan / kullanan var', 'Boş' => 'Boş — oturan / kullanan yok'] as $value => $text)<option value="{{ $value }}" @selected($value === $u->status)>{{ $text }}</option>@endforeach</select>
                                        <p class="mt-0.5 text-[11px] text-slate-400">Malik dairede oturmaya başlarsa "Dolu" seçin. Kiracı eklenince otomatik "Dolu" olur.</p></div>
                                </div>
                            </x-dash.edit-panel>
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
                        <span class="flex gap-1">
                            <x-dash.edit-panel :action="route('site.groups.update', [$apartment, $g])">
                                <div><label class="label" for="gr-n-{{ $g->id }}">Grup adı *</label><input class="input" id="gr-n-{{ $g->id }}" name="name" maxlength="60" value="{{ $g->name }}" required></div>
                                <div><label class="label" for="gr-d-{{ $g->id }}">Açıklama</label><input class="input" id="gr-d-{{ $g->id }}" name="details" maxlength="255" value="{{ $g->details }}"></div>
                                <div><label class="label" for="gr-u-{{ $g->id }}">Daireler</label><select class="input h-40" id="gr-u-{{ $g->id }}" name="unit_ids[]" multiple>@foreach ($allUnits as $unit)<option value="{{ $unit->id }}" @selected($g->units->contains('id', $unit->id))>{{ $unit->label() }}</option>@endforeach</select></div>
                            </x-dash.edit-panel>
                            <form method="POST" action="{{ route('site.groups.destroy', [$apartment, $g]) }}" onsubmit="return confirm('Grup silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                        </span>
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
