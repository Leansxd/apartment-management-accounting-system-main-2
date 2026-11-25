<x-layout heading="Üyeler / Sakinler" :subheading="$apartment->name">
    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    <details class="panel card" @if($errors->any()) open @endif>
        <summary class="card-head"><span class="card-title">+ Yeni üye ekle</span><span class="text-xs text-slate-500">Eklenen her üyeye mobil uygulama için davet kodu üretilir</span></summary>
        <form method="POST" action="{{ route('site.members.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            <div class="form-grid">
                <div><label class="label" for="m-unit">Daire *</label>
                    <select class="input" id="m-unit" name="unit_id" required><option value="">Seçin</option>@foreach ($units as $u)<option value="{{ $u->id }}" @selected(old('unit_id') == $u->id)>{{ $u->label() }}</option>@endforeach</select></div>
                <div><label class="label" for="m-type">Tür *</label>
                    <select class="input" id="m-type" name="type">@foreach (\App\Models\Member::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('type') === $k)>{{ $v }}</option>@endforeach</select></div>
                <div><label class="label" for="m-name">Ad soyad *</label><input class="input" id="m-name" name="full_name" value="{{ old('full_name') }}" required></div>
                <div><label class="label" for="m-phone">Telefon</label><input class="input" id="m-phone" name="phone" value="{{ old('phone') }}"></div>
                <div><label class="label" for="m-email">E-posta</label><input class="input" id="m-email" type="email" name="email" value="{{ old('email') }}"></div>
                <div><label class="label" for="m-in">Daireye giriş tarihi</label><input class="input" id="m-in" type="date" name="moved_in_on" value="{{ old('moved_in_on') }}"></div>
                <div><label class="label" for="m-tc">TC kimlik no</label><input class="input" id="m-tc" name="id_number" inputmode="numeric" maxlength="11" value="{{ old('id_number') }}"></div>
                <div><label class="label" for="m-em">Acil durum telefonu</label><input class="input" id="m-em" name="emergency_phone" value="{{ old('emergency_phone') }}"></div>
                <div><label class="label" for="m-addr">Adres</label><input class="input" id="m-addr" name="address" value="{{ old('address') }}"></div>
                <div class="sm:col-span-2 lg:col-span-4"><label class="label" for="m-notes">Notlar</label><textarea class="input" id="m-notes" name="notes" rows="2">{{ old('notes') }}</textarea></div>
            </div>
            <button class="btn btn-primary">Üyeyi kaydet</button>
        </form>
    </details>

    <x-dash.filters :fields="[['name' => 'type', 'label' => 'Tür', 'type' => 'select', 'options' => \App\Models\Member::TYPES], ['name' => 'block_id', 'label' => 'Blok', 'type' => 'select', 'options' => $blocks->pluck('name', 'id')->all()], ['name' => 'mobile', 'label' => 'Mobil', 'type' => 'select', 'options' => ['linked' => 'Bağlı', 'waiting' => 'Bekliyor']]]" :keep="['q']" />

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Üye listesi <a class="ml-2 text-[10px] font-bold text-brand-500 hover:underline" href="{{ route('site.members.history', $apartment) }}">Geçmiş üyeler →</a></h2>
            <form method="GET" class="flex gap-2">
                <label class="sr-only" for="q">Ara</label>
                <input class="input" id="q" name="q" value="{{ $search }}" placeholder="Ad, telefon, e-posta">
                <button class="btn btn-secondary">Ara</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            @php $canViewMemberSecrets = auth()->user()->can('view-member-secrets', $apartment); @endphp
            <table class="table">
                <thead><tr><th>Daire</th><th>Ad Soyad</th><th>Tür</th><th>Telefon</th><th>Mobil</th>@if ($canViewMemberSecrets)<th>Davet Kodu</th>@endif<th></th></tr></thead>
                <tbody>
                @forelse ($members as $m)
                    <tr>
                        <td class="font-medium"><x-unit-link :unit="$m->unit" /></td>
                        <td><x-member-link :member="$m" /></td>
                        <td>{{ \App\Models\Member::TYPES[$m->type] ?? $m->type }}</td>
                        <td>{{ $m->phone ?: '—' }}</td>
                        <td>@if ($m->user_id)<span class="badge badge-green">Bağlı</span>@else<span class="badge badge-gray">Bekliyor</span>@endif</td>
                        @if ($canViewMemberSecrets)<td class="font-mono text-xs">{{ $m->user_id ? '—' : $m->invite_code }}</td>@endif
                        <td class="text-right"><a class="btn btn-secondary btn-sm" href="{{ route('site.members.show', [$apartment, $m]) }}">Detay</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-slate-500">Üye yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $members->links() }}</div>
    </section>
</x-layout>
