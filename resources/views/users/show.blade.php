<x-layout :heading="$user->name" :subheading="$user->email">
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="stat"><p class="stat-label">Rol</p><p class="stat-value">{{ $user->is_super_admin ? 'Süper Admin' : ($user->isAdmin() ? 'Yönetici' : 'Sakin') }}</p></div>
        <div class="stat"><p class="stat-label">Bağlı daire</p><p class="stat-value">{{ $memberships->count() }}</p></div>
        <div class="stat"><p class="stat-label">Açık borç</p><p class="stat-value text-rose-600">{{ tl($openDebt) }}</p></div>
    </div>

    @can('super-admin')
        @unless ($user->is(auth()->user()))
            <section class="card">
                <div class="card-head"><h2 class="card-title">Yetki</h2></div>
                <div class="card-body flex flex-wrap items-end gap-3">
                    <form method="POST" action="{{ route('users.role', $user) }}" class="flex items-end gap-2">
                        @csrf @method('PATCH')
                        <div><label class="label" for="role">Rol</label>
                            <select class="input" id="role" name="role">
                                <option value="user" @selected(! $user->isAdmin())>Sakin (mobil)</option>
                                <option value="admin" @selected($user->isAdmin() && ! $user->is_super_admin)>Panel yöneticisi</option>
                                <option value="super" @selected($user->is_super_admin)>Süper admin</option>
                            </select></div>
                        <button class="btn btn-primary">Kaydet</button>
                    </form>
                    <form method="POST" action="{{ route('users.destroy', $user) }}" class="ml-auto" onsubmit="return confirm('Kullanıcı silinsin mi? Daire bağlantıları kaldırılır.')">@csrf @method('DELETE')<button class="btn btn-danger">Kullanıcıyı sil</button></form>
                </div>
            </section>
        @endunless
    @endcan

    @if ($managed->isNotEmpty())
        <section class="card">
            <div class="card-head"><h2 class="card-title">Yönettiği siteler</h2></div>
            <ul class="divide-y divide-slate-100">@foreach ($managed as $a)<li class="px-5 py-2.5 text-sm">{{ $a->name }} <span class="text-slate-400">{{ $a->code }}</span></li>@endforeach</ul>
        </section>
    @endif

    <section class="card">
        <div class="card-head"><h2 class="card-title">Daireleri</h2></div>
        <ul class="divide-y divide-slate-100">
            @forelse ($memberships as $m)
                <li class="flex justify-between px-5 py-2.5 text-sm">
                    <span>{{ $m->apartment->name }} · <b>{{ $m->unit->label() }}</b> <span class="text-slate-400">({{ \App\Models\Member::TYPES[$m->type] ?? $m->type }})</span></span>
                    <a class="text-brand-600" href="{{ route('site.members.show', [$m->apartment, $m]) }}">Üye kaydı</a>
                </li>
            @empty
                <li class="px-5 py-3 text-sm text-slate-500">Bağlı daire yok.</li>
            @endforelse
        </ul>
    </section>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Borçlar</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Daire</th><th>Açıklama</th><th>Vade</th><th class="num">Tutar</th><th class="num">Kalan</th><th>Durum</th></tr></thead>
                <tbody>
                @forelse ($debts as $d)
                    <tr><td>{{ $d->unit->label() }}</td><td>{{ $d->description }}</td><td>{{ $d->due_date->format('d.m.Y') }}</td><td class="num">{{ tl($d->amount) }}</td><td class="num">{{ tl($d->remaining_amount) }}</td><td><x-debt-status :debt="$d" /></td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Borç yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
