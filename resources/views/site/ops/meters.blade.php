<x-layout heading="Sayaçlar" :subheading="$apartment->name">
    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    <x-dash.filters :fields="[['name' => 'type', 'label' => 'Tür', 'type' => 'select', 'options' => array_combine(\App\Models\Meter::TYPES, \App\Models\Meter::TYPES)]]" />

    <details class="panel card" @if($errors->any()) open @endif>
        <summary class="card-head"><span class="card-title">+ Sayaç tanımla</span></summary>
        <form method="POST" action="{{ route('site.meters.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            <div class="form-grid">
                <div><label class="label" for="name">Ad *</label><input class="input" id="name" name="name" required placeholder="ör. Ortak alan elektrik"></div>
                <div><label class="label" for="type">Tür</label><select class="input" id="type" name="type">@foreach (\App\Models\Meter::TYPES as $t)<option>{{ $t }}</option>@endforeach</select></div>
                <div><label class="label" for="unit_id">Bağlı daire</label><select class="input" id="unit_id" name="unit_id"><option value="">Ortak alan</option>@foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->label() }}</option>@endforeach</select></div>
                <div><label class="label" for="serial_no">Seri no</label><input class="input" id="serial_no" name="serial_no"></div>
                <div><label class="label" for="initial_value">İlk endeks</label><input class="input" id="initial_value" type="number" step="0.001" min="0" name="initial_value" value="0" required></div>
            </div>
            <button class="btn btn-primary">Kaydet</button>
        </form>
    </details>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($meters as $m)
            <section class="card">
                <div class="card-head">
                    <div><h2 class="card-title">{{ $m->name }}</h2><p class="text-xs text-slate-500">{{ $m->type }} · <x-unit-link :unit="$m->unit" fallback="Ortak alan" /> @if($m->serial_no)· {{ $m->serial_no }}@endif</p></div>
                    <p class="text-right"><span class="block text-xs text-slate-500">Son endeks</span><b class="tabular-nums">{{ number_format($m->last_value, 3, ',', '.') }}</b></p>
                </div>
                <form method="POST" action="{{ route('site.meters.readings', [$apartment, $m]) }}" class="card-body flex flex-wrap items-end gap-2 border-b border-slate-100">
                    @csrf
                    <div><label class="label" for="val-{{ $m->id }}">Yeni okuma</label><input class="input !w-32" id="val-{{ $m->id }}" type="number" step="0.001" min="{{ $m->last_value }}" name="value" required></div>
                    <div><label class="label" for="date-{{ $m->id }}">Tarih</label><input class="input" id="date-{{ $m->id }}" type="date" name="read_on" value="{{ today()->toDateString() }}" required></div>
                    <div class="flex-1"><label class="label" for="note-{{ $m->id }}">Not</label><input class="input" id="note-{{ $m->id }}" name="note"></div>
                    <button class="btn btn-primary">Kaydet</button>
                </form>
                <table class="table">
                    <thead><tr><th>Tarih</th><th class="num">Endeks</th><th class="num">Tüketim</th><th>Not</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($m->readings as $r)
                        <tr><td>{{ $r->read_on->format('d.m.Y') }}</td><td class="num">{{ number_format($r->value, 3, ',', '.') }}</td><td class="num font-medium">{{ number_format($r->consumption, 3, ',', '.') }}</td><td class="text-xs">{{ $r->note }}</td>
                            <td class="whitespace-nowrap text-right">
                                @if ($r->id === $m->readings->max('id'))
                                    <x-dash.edit-panel :action="route('site.meters.readings.update', [$apartment, $m, $r])" width="w-72">
                                        <div><label class="label" for="rv-{{ $r->id }}">Endeks *</label><input class="input" id="rv-{{ $r->id }}" type="number" step="0.001" min="0" name="value" value="{{ $r->value }}" required></div>
                                        <div><label class="label" for="rd-{{ $r->id }}">Tarih *</label><input class="input" id="rd-{{ $r->id }}" type="date" name="read_on" max="{{ today()->toDateString() }}" value="{{ $r->read_on->toDateString() }}" required></div>
                                        <div><label class="label" for="rn-{{ $r->id }}">Not</label><input class="input" id="rn-{{ $r->id }}" name="note" maxlength="200" value="{{ $r->note }}"></div>
                                    </x-dash.edit-panel>
                                    <form method="POST" action="{{ route('site.meters.readings.destroy', [$apartment, $m, $r]) }}" class="inline" onsubmit="return confirm('Son okuma silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                                @endif
                            </td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-slate-500">Okuma yok.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <div class="card-body flex justify-end gap-1 pt-3">
                    <x-dash.edit-panel :action="route('site.meters.update', [$apartment, $m])" label="Sayacı düzenle">
                        <div><label class="label" for="me-name-{{ $m->id }}">Ad *</label><input class="input" id="me-name-{{ $m->id }}" name="name" maxlength="80" value="{{ $m->name }}" required></div>
                        <div><label class="label" for="me-type-{{ $m->id }}">Tür</label><select class="input" id="me-type-{{ $m->id }}" name="type">@foreach (\App\Models\Meter::TYPES as $type)<option @selected($type === $m->type)>{{ $type }}</option>@endforeach</select></div>
                        <div><label class="label" for="me-unit-{{ $m->id }}">Daire</label><select class="input" id="me-unit-{{ $m->id }}" name="unit_id"><option value="">Ortak alan</option>@foreach ($units as $u)<option value="{{ $u->id }}" @selected($u->id === $m->unit_id)>{{ $u->label() }}</option>@endforeach</select></div>
                        <div><label class="label" for="me-serial-{{ $m->id }}">Seri no</label><input class="input" id="me-serial-{{ $m->id }}" name="serial_no" maxlength="60" value="{{ $m->serial_no }}"></div>
                    </x-dash.edit-panel>
                    <form method="POST" action="{{ route('site.meters.destroy', [$apartment, $m]) }}" onsubmit="return confirm('Sayaç ve okumaları silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sayacı sil</button></form>
                </div>
            </section>
        @empty
            <p class="text-sm text-slate-500">Sayaç tanımlı değil.</p>
        @endforelse
    </div>
</x-layout>
