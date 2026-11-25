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
        <div class="card-head"><h2 class="card-title">Dönem kapatma</h2>
            <span class="text-xs text-slate-500">{{ $apartment->closed_until ? $apartment->closed_until->format('d.m.Y').' tarihine kadar kapalı' : 'Kapalı dönem yok' }}</span></div>
        @if ($isOwner)
            <form method="POST" action="{{ route('site.settings.period', $apartment) }}" class="card-body space-y-4">
                @csrf @method('PUT')
                <p class="text-xs text-slate-500">Seçilen tarih dahil, o tarihe kadarki döneme borç eklenemez, oradaki kayıtlar silinemez ve iptal edilemez. Bugünkü tahsilatlar eski borçlara yazılmaya devam eder.</p>
                <div class="form-grid">
                    <div><label class="label" for="closed_until">Kapanış tarihi</label><input class="input" id="closed_until" type="date" name="closed_until" max="{{ today()->subDay()->toDateString() }}" value="{{ old('closed_until', $apartment->closed_until?->toDateString()) }}"></div>
                    <div class="sm:col-span-2 lg:col-span-3"><label class="label" for="reason">Gerekçe (dönemi geri açarken zorunlu)</label><input class="input" id="reason" name="reason" maxlength="300" value="{{ old('reason') }}"></div>
                </div>
                <button class="btn btn-primary">Kaydet</button>
            </form>
        @else
            <p class="card-body text-sm text-slate-500">Dönemi yalnızca site sahibi kapatabilir veya açabilir.</p>
        @endif
    </section>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Yıl sonu kapanışı</h2></div>
        <div class="card-body space-y-4">
            @if ($isOwner)
                <form method="POST" action="{{ route('site.settings.year-close', $apartment) }}" class="space-y-3" onsubmit="return confirm('Yıl kapatılsın mı? 31 Aralık dahil o tarihe kadarki dönem kilitlenir.')">
                    @csrf
                    <p class="text-xs text-slate-500">Yılın gelir-gider sonucu ve 31 Aralık bakiyeleri denetim kaydına işlenir, dönem kapatılır. Bakiyeler yeni yıla kendiliğinden devreder. Yevmiye bakiyelerle tutmuyorsa kapanış yapılmaz.</p>
                    <div class="flex items-end gap-2">
                        <div><label class="label" for="year">Yıl</label><input class="input" id="year" type="number" name="year" min="2000" max="{{ today()->year - 1 }}" value="{{ today()->year - 1 }}" required></div>
                        <button class="btn btn-primary">Yılı kapat</button>
                    </div>
                </form>
            @endif
            @forelse ($yearClosings as $log)
                <p class="text-sm">{{ $log->data['year'] }} · Gelir {{ tl($log->data['income']) }} · Gider {{ tl($log->data['expense']) }} · Sonuç <b>{{ tl($log->data['result']) }}</b> · Kasa {{ tl($log->data['cash']) }}</p>
            @empty
                <p class="text-sm text-slate-500">Henüz kapatılmış yıl yok.</p>
            @endforelse
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Yöneticiler</h2><span class="text-xs text-slate-500">Sahip: <x-user-link :user="$apartment->owner" /></span></div>
        <ul class="divide-y divide-slate-100">
            @foreach ($apartment->managers as $m)
                <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                    <span><x-user-link :user="$m" /> <span class="text-slate-400">· {{ $m->email }}</span></span>
                    <span class="flex items-center gap-2">
                        @if ($isOwner && $m->id !== $apartment->owner_id)
                            <form method="POST" action="{{ route('site.managers.role', [$apartment, $m]) }}">
                                @csrf @method('PATCH')
                                <label class="sr-only" for="role-{{ $m->id }}">Rol</label>
                                <select class="input !w-auto !py-1 text-xs" id="role-{{ $m->id }}" name="role" data-autosubmit>@foreach (\App\Models\Apartment::ROLES as $k => $v)<option value="{{ $k }}" @selected($m->pivot->role === $k)>{{ $v }}</option>@endforeach</select>
                            </form>
                            <form method="POST" action="{{ route('site.managers.destroy', [$apartment, $m]) }}">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Çıkar</button></form>
                        @else
                            <span class="badge badge-gray">{{ $m->id === $apartment->owner_id ? 'Site sahibi' : \App\Models\Apartment::ROLES[$m->pivot->role] }}</span>
                        @endif
                    </span>
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
                <label class="sr-only" for="manager-role">Rol</label>
                <select class="input !w-auto" id="manager-role" name="role">@foreach (\App\Models\Apartment::ROLES as $k => $v)<option value="{{ $k }}" @selected($k === 'manager')>{{ $v }}</option>@endforeach</select>
                <button class="btn btn-primary">Ekle</button>
            </form>
        @endif
    </section>

    @php $matrix = \App\Support\SitePermissions::matrix($apartment); @endphp
    <section class="card">
        <div class="card-head"><h2 class="card-title">Rol izinleri</h2>
            <span class="text-xs text-slate-500">Yönetici ve site sahibi her modülde tam yetkilidir.</span></div>
        <form method="POST" action="{{ route('site.settings.permissions', $apartment) }}">
            @csrf @method('PUT')
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Modül</th>@foreach (\App\Support\SitePermissions::ROLES as $role)<th>{{ \App\Models\Apartment::ROLES[$role] }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach (collect(\App\Support\SitePermissions::MODULES)->groupBy('group', preserveKeys: true) as $group => $modules)
                        <tr><th colspan="{{ count(\App\Support\SitePermissions::ROLES) + 1 }}" class="bg-slate-50 text-xs uppercase text-slate-500">{{ $group }}</th></tr>
                        @foreach ($modules as $module => $definition)
                            <tr>
                                <td>{{ $definition['label'] }}</td>
                                @foreach (\App\Support\SitePermissions::ROLES as $role)
                                    <td><select class="input !py-1 text-sm" name="permissions[{{ $role }}][{{ $module }}]" aria-label="{{ $definition['label'] }} · {{ \App\Models\Apartment::ROLES[$role] }}" @disabled(! $isOwner)>
                                        @foreach (\App\Support\SitePermissions::LEVELS as $value => $label)<option value="{{ $value }}" @selected($matrix[$role][$module] === $value)>{{ $label }}</option>@endforeach
                                    </select></td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body flex items-center justify-between gap-3 border-t border-slate-100">
                <p class="text-xs text-slate-500">"Erişim yok" seçilen modül menüde görünmez ve açılamaz; "Görüntüleme" seçilende işlem düğmeleri gizlenir.</p>
                @if ($isOwner)<button class="btn btn-primary">İzinleri kaydet</button>@else<span class="text-xs text-slate-500">Yalnızca site sahibi değiştirebilir.</span>@endif
            </div>
        </form>
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
