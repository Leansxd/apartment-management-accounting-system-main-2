<x-layout heading="Kayıtlı Kullanıcılar" :subheading="auth()->user()->is_super_admin ? 'Tüm kullanıcılar' : 'Sitelerinize bağlı sakinler ve yöneticiler'">
    @can('super-admin')
        <details class="panel card" @if($errors->any()) open @endif>
            <summary class="card-head"><span class="card-title">+ Kullanıcı oluştur</span></summary>
            <form method="POST" action="{{ route('users.store') }}" class="card-body space-y-4">
                @csrf
                <div class="form-grid">
                    <div><label class="label" for="name">Ad soyad</label><input class="input" id="name" name="name" value="{{ old('name') }}" required></div>
                    <div><label class="label" for="email">E-posta</label><input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required></div>
                    <div><label class="label" for="password">Şifre (en az 8)</label><input class="input" id="password" type="password" name="password" required autocomplete="new-password"></div>
                    <div><label class="label" for="role">Rol</label><select class="input" id="role" name="role"><option value="admin">Panel yöneticisi</option><option value="user">Sakin (mobil)</option></select></div>
                </div>
                <button class="btn btn-primary">Oluştur</button>
            </form>
        </details>
    @endcan

    <section class="card">
        <div class="card-head">
            <form method="GET" class="flex flex-wrap gap-2">
                <label class="sr-only" for="q">Ara</label>
                <input class="input !w-auto" id="q" name="q" value="{{ $search }}" placeholder="Ad veya e-posta">
                <label class="sr-only" for="f-role">Rol</label>
                <select class="input !w-auto" id="f-role" name="role"><option value="">Tüm roller</option><option value="admin" @selected($role === 'admin')>Yönetici</option><option value="user" @selected($role === 'user')>Sakin</option></select>
                <button class="btn btn-secondary">Filtrele</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Ad</th><th>E-posta</th><th>Rol</th><th class="num">Daire</th><th>Son giriş</th><th></th></tr></thead>
                <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td><a class="font-medium text-slate-900 hover:text-brand-600" href="{{ route('users.show', $u) }}">{{ $u->name }}</a></td>
                        <td>{{ $u->email }}</td>
                        <td>@if ($u->is_super_admin)<span class="badge badge-blue">Süper Admin</span>@elseif ($u->isAdmin())<span class="badge badge-green">Yönetici</span>@else<span class="badge badge-gray">Sakin</span>@endif</td>
                        <td class="num">{{ $u->memberships_count }}</td>
                        <td>{{ $u->last_login_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td class="text-right"><a class="btn btn-secondary btn-sm" href="{{ route('users.show', $u) }}">Detay</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Kullanıcı yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $users->links() }}</div>
    </section>
</x-layout>
