<x-layout heading="Site Ayarları" :subheading="$apartment->name">
    @php $isOwner = auth()->user()->is_super_admin || $apartment->owner_id === auth()->id(); @endphp
    <section class="card">
        <div class="card-head"><h2 class="card-title">Site bilgileri</h2></div>
        <form method="POST" action="{{ route('site.settings.update', $apartment) }}" class="card-body space-y-4">
            @csrf @method('PUT')
            <div class="form-grid">
                <div><label class="label" for="name">Site adı</label><input class="input" id="name" name="name" value="{{ old('name', $apartment->name) }}" required></div>
                <div><label class="label" for="code">Site kodu</label><input class="input uppercase" id="code" name="code" value="{{ old('code', $apartment->code) }}" required></div>
                <div><label class="label" for="city">Şehir</label><input class="input" id="city" name="city" value="{{ old('city', $apartment->city) }}"></div>
                <div><label class="label" for="tax_number">Vergi no</label><input class="input" id="tax_number" name="tax_number" value="{{ old('tax_number', $apartment->tax_number) }}"></div>
                <div class="sm:col-span-2 lg:col-span-4"><label class="label" for="address">Adres</label><input class="input" id="address" name="address" value="{{ old('address', $apartment->address) }}"></div>
            </div>
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="allow_negative_cashbox" value="1" class="mt-0.5 rounded border-slate-300" @checked($apartment->allow_negative_cashbox)>
                <span><b>Kasa bakiyesi eksiye düşebilsin.</b> <span class="text-slate-500">Kapalıyken bakiyesi yetmeyen gider, çıkış ve virman reddedilir.</span></span>
            </label>
            <button class="btn btn-primary">Kaydet</button>
        </form>
    </section>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Yöneticiler</h2><span class="text-xs text-slate-500">Sahip: {{ $apartment->owner?->name ?? '—' }}</span></div>
        <ul class="divide-y divide-slate-100">
            @foreach ($apartment->managers as $m)
                <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                    <span>{{ $m->name }} <span class="text-slate-400">· {{ $m->email }}</span></span>
                    @if ($isOwner && $m->id !== $apartment->owner_id)
                        <form method="POST" action="{{ route('site.managers.destroy', [$apartment, $m]) }}">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Çıkar</button></form>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($isOwner)
            <form method="POST" action="{{ route('site.managers.store', $apartment) }}" class="card-body flex gap-2 border-t border-slate-100">
                @csrf
                <label class="sr-only" for="manager">Yönetici</label>
                <select class="input" id="manager" name="user_id" required>
                    <option value="">Yönetici ekle…</option>
                    @foreach ($admins->whereNotIn('id', $apartment->managers->pluck('id')) as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->email }})</option>@endforeach
                </select>
                <button class="btn btn-primary">Ekle</button>
            </form>
        @endif
    </section>

    @if ($isOwner)
        <section class="card border-rose-200">
            <div class="card-head"><h2 class="card-title text-rose-700">Tehlikeli işlemler</h2></div>
            <div class="card-body grid gap-6 lg:grid-cols-2">
                <form method="POST" action="{{ route('site.reset', $apartment) }}" class="space-y-2" onsubmit="return confirm('Tüm finans ve operasyon verileri silinecek. Emin misiniz?')">
                    @csrf
                    <p class="text-sm"><b>Siteyi sıfırla:</b> blok, daire, üye, borç, kasa hareketi ve diğer tüm kayıtlar silinir; kasalar sıfırlanır. İşlem kayıtları korunur.</p>
                    <label class="label" for="reset-confirm">Onay için site kodunu yazın: <b>{{ $apartment->code }}</b></label>
                    <input class="input" id="reset-confirm" name="confirm" autocomplete="off" required>
                    <button class="btn btn-danger">Siteyi sıfırla</button>
                </form>
                <form method="POST" action="{{ route('site.destroy', $apartment) }}" class="space-y-2" onsubmit="return confirm('Site tamamen silinecek. Emin misiniz?')">
                    @csrf @method('DELETE')
                    <p class="text-sm"><b>Siteyi sil:</b> site ve tüm verileri kalıcı olarak silinir.</p>
                    <label class="label" for="delete-confirm">Onay için site kodunu yazın: <b>{{ $apartment->code }}</b></label>
                    <input class="input" id="delete-confirm" name="confirm" autocomplete="off" required>
                    <button class="btn btn-danger">Siteyi sil</button>
                </form>
            </div>
        </section>
    @endif
</x-layout>
