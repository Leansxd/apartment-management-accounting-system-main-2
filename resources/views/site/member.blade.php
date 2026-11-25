<x-layout :heading="$member->full_name" :subheading="$member->unit->label().' · '.(\App\Models\Member::TYPES[$member->type] ?? '')">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('site.collections.index', [$apartment, 'unit_id' => $member->unit_id]) }}">Tahsilat al</a>
        <a class="btn btn-secondary" href="{{ route('site.statements', [$apartment, 'unit_id' => $member->unit_id]) }}">Hesap ekstresi</a>
        <a class="btn btn-secondary" href="{{ route('site.units.show', [$apartment, $member->unit_id]) }}">Daire detayı</a>
    </x-slot:actions>

    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat"><p class="stat-label">Açık Borç</p><p class="stat-value text-rose-600">{{ tl($statement['open_debt']) }}</p></div>
        <div class="stat"><p class="stat-label">Avans</p><p class="stat-value text-emerald-600">{{ tl($statement['credit']) }}</p></div>
        <div class="stat"><p class="stat-label">Net Bakiye</p><p class="stat-value">{{ tl($statement['closing']) }}</p><p class="text-xs text-slate-500">+ borçlu / − alacaklı</p></div>
        <div class="stat"><p class="stat-label">Mobil Hesap</p>
            @if ($member->user)
                <p class="mt-2 text-sm font-medium">{{ $member->user->email }}</p><p class="text-xs text-slate-500">{{ $member->activated_at?->format('d.m.Y') }} tarihinde bağlandı</p>
            @else
                @can('view-member-secrets', $apartment)
                    <p class="mt-2 font-mono text-sm">{{ $member->invite_code }}</p>
                @endcan
                <p class="text-xs text-slate-500">Davet kodu (henüz bağlanmadı)</p>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <div class="card-head"><h2 class="card-title">Bilgiler</h2></div>
            <form method="POST" action="{{ route('site.members.update', [$apartment, $member]) }}" class="card-body space-y-4">
                @csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label" for="type">Tür</label><select class="input" id="type" name="type">@foreach (\App\Models\Member::TYPES as $k => $v)<option value="{{ $k }}" @selected($member->type === $k)>{{ $v }}</option>@endforeach</select></div>
                    <div><label class="label" for="full_name">Ad soyad</label><input class="input" id="full_name" name="full_name" value="{{ old('full_name', $member->full_name) }}" required></div>
                    <div><label class="label" for="phone">Telefon</label><input class="input" id="phone" name="phone" value="{{ old('phone', $member->phone) }}"></div>
                    <div><label class="label" for="email">E-posta</label><input class="input" id="email" type="email" name="email" value="{{ old('email', $member->email) }}"></div>
                    @can('view-member-secrets', $apartment)
                        <div><label class="label" for="id_number">TC kimlik no</label><input class="input" id="id_number" name="id_number" maxlength="11" value="{{ old('id_number', $member->id_number) }}"></div>
                    @endcan
                    <div><label class="label" for="emergency_phone">Acil durum tel.</label><input class="input" id="emergency_phone" name="emergency_phone" value="{{ old('emergency_phone', $member->emergency_phone) }}"></div>
                    <div class="sm:col-span-2"><label class="label" for="address">Adres</label><input class="input" id="address" name="address" value="{{ old('address', $member->address) }}"></div>
                    <div class="sm:col-span-2"><label class="label" for="notes">Notlar</label><textarea class="input" id="notes" name="notes" rows="2">{{ old('notes', $member->notes) }}</textarea></div>
                </div>
                <button class="btn btn-primary">Kaydet</button>
            </form>
        </section>
        <section class="card">
            <div class="card-head"><h2 class="card-title">Mobil erişim</h2></div>
            <div class="card-body space-y-3 text-sm">
                <form method="POST" action="{{ route('site.members.invite', [$apartment, $member]) }}" onsubmit="return confirm('Eski kod geçersiz olacak. Devam edilsin mi?')">@csrf<button class="btn btn-secondary w-full">Yeni davet kodu üret</button></form>
                @if ($member->user_id)
                    <form method="POST" action="{{ route('site.members.unlink', [$apartment, $member]) }}" onsubmit="return confirm('Sakin bu dairenin kayıtlarını artık göremeyecek. Devam?')">@csrf<button class="btn btn-secondary w-full">Mobil bağlantıyı kaldır</button></form>
                @endif
                <details class="panel rounded-xl border border-red-100">
                    <summary class="btn btn-danger w-full">Daireden ayrıldı / devir</summary>
                    <form method="POST" action="{{ route('site.members.destroy', [$apartment, $member]) }}" class="space-y-2 p-3" onsubmit="return confirm('Üye geçmişe taşınsın mı? Mobil erişimi hemen biter.')">
                        @csrf @method('DELETE')
                        <label class="label" for="mo-date">Ayrılış tarihi</label><input class="input" id="mo-date" type="date" name="moved_out_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}">
                        <label class="label" for="mo-reason">Neden</label><input class="input" id="mo-reason" name="move_out_reason" placeholder="ör. Satış, kira sonu">
                        <button class="btn btn-danger w-full">Geçmişe taşı</button>
                    </form>
                </details>
                <details class="panel rounded-xl border border-red-100">
                    <summary class="btn btn-secondary w-full">Yanlış kayıt — kalıcı sil</summary>
                    <div class="space-y-2 p-3 text-xs text-slate-500">
                        @if ($canPurge)
                            <p>Yanlış daireye ya da yanlışlıkla eklenen kayıt içindir. Kayıt geçmişte de görünmez ve geri alınamaz. Daireden ayrılan biri için "Daireden ayrıldı / devir"i kullanın.</p>
                            <form method="POST" action="{{ route('site.members.purge', [$apartment, $member]) }}" onsubmit="return confirm(@js($member->full_name.' kaydı kalıcı olarak silinsin mi? Bu işlem geri alınamaz.'))">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger w-full">Kalıcı olarak sil</button>
                            </form>
                        @else
                            <p>Bu kişi tahsilatlarda ödeyen olarak geçtiği için kalıcı silinemez; makbuzlarda kimin ödediği kaybolurdu. "Daireden ayrıldı / devir" ile geçmişe taşıyabilirsiniz.</p>
                        @endif
                    </div>
                </details>
            </div>
        </section>
    </div>

    @include('site._unit-debts-payments')
</x-layout>
