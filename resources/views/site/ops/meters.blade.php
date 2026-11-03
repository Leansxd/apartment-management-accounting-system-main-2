<x-layout heading="Sayaçlar" :subheading="$apartment->name">
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
                    <div><h2 class="card-title">{{ $m->name }}</h2><p class="text-xs text-slate-500">{{ $m->type }} · {{ $m->unit?->label() ?? 'Ortak alan' }} @if($m->serial_no)· {{ $m->serial_no }}@endif</p></div>
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
                    <thead><tr><th>Tarih</th><th class="num">Endeks</th><th class="num">Tüketim</th><th>Not</th></tr></thead>
                    <tbody>
                    @forelse ($m->readings as $r)
                        <tr><td>{{ $r->read_on->format('d.m.Y') }}</td><td class="num">{{ number_format($r->value, 3, ',', '.') }}</td><td class="num font-medium">{{ number_format($r->consumption, 3, ',', '.') }}</td><td class="text-xs">{{ $r->note }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-slate-500">Okuma yok.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <form method="POST" action="{{ route('site.meters.destroy', [$apartment, $m]) }}" class="card-body pt-3 text-right" onsubmit="return confirm('Sayaç ve okumaları silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sayacı sil</button></form>
            </section>
        @empty
            <p class="text-sm text-slate-500">Sayaç tanımlı değil.</p>
        @endforelse
    </div>
</x-layout>
