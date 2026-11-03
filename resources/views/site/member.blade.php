<x-layout :heading="$member->full_name" :subheading="$member->unit->label().' · '.(\App\Models\Member::TYPES[$member->type] ?? '')">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('site.collections.index', [$apartment, 'unit_id' => $member->unit_id]) }}">Tahsilat al</a>
        <a class="btn btn-secondary" href="{{ route('site.statements', [$apartment, 'unit_id' => $member->unit_id]) }}">Hesap ekstresi</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat"><p class="stat-label">Açık Borç</p><p class="stat-value text-rose-600">{{ tl($statement['open_debt']) }}</p></div>
        <div class="stat"><p class="stat-label">Avans</p><p class="stat-value text-emerald-600">{{ tl($statement['credit']) }}</p></div>
        <div class="stat"><p class="stat-label">Net Bakiye</p><p class="stat-value">{{ tl($statement['closing']) }}</p><p class="text-xs text-slate-500">+ borçlu / − alacaklı</p></div>
        <div class="stat"><p class="stat-label">Mobil Hesap</p>
            @if ($member->user)
                <p class="mt-2 text-sm font-medium">{{ $member->user->email }}</p><p class="text-xs text-slate-500">{{ $member->activated_at?->format('d.m.Y') }} tarihinde bağlandı</p>
            @else
                <p class="mt-2 font-mono text-sm">{{ $member->invite_code }}</p><p class="text-xs text-slate-500">Davet kodu (henüz bağlanmadı)</p>
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
                    <div><label class="label" for="id_number">TC kimlik no</label><input class="input" id="id_number" name="id_number" maxlength="11" value="{{ old('id_number', $member->id_number) }}"></div>
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
                <form method="POST" action="{{ route('site.members.destroy', [$apartment, $member]) }}" onsubmit="return confirm('Üye silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger w-full">Üyeyi sil</button></form>
            </div>
        </section>
    </div>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Borçlar</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Açıklama</th><th>Vade</th><th class="num">Tutar</th><th class="num">Ödenen</th><th class="num">Kalan</th><th>Durum</th></tr></thead>
                <tbody>
                @forelse ($debts as $d)
                    <tr><td>{{ $d->description }}</td><td>{{ $d->due_date->format('d.m.Y') }}</td><td class="num">{{ tl($d->amount) }}</td><td class="num">{{ tl($d->paid_amount) }}</td><td class="num font-semibold">{{ tl($d->remaining_amount) }}</td><td><x-debt-status :debt="$d" /></td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Borç kaydı yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Ödemeler</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Açıklama</th><th class="num">Tutar</th><th class="num">Borca</th><th class="num">Avansa</th></tr></thead>
                <tbody>
                @forelse ($payments as $p)
                    <tr><td>{{ $p->created_at->format('d.m.Y H:i') }}</td><td>{{ $p->description }}</td><td class="num font-semibold">{{ tl($p->amount) }}</td><td class="num">{{ tl($p->applied_to_debts) }}</td><td class="num">{{ tl($p->to_advance) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-500">Ödeme yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
