<x-portal-layout title="Hesabım">
    <section class="card max-w-lg">
        <div class="card-head"><h2 class="card-title">Şifremi değiştir</h2></div>
        <form method="POST" action="{{ route('portal.password') }}" class="card-body space-y-4">
            @csrf @method('PUT')
            <div><label class="label" for="current_password">Mevcut şifre *</label><input class="input" id="current_password" type="password" name="current_password" required autocomplete="current-password"></div>
            <div><label class="label" for="password">Yeni şifre *</label><input class="input" id="password" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
            <div><label class="label" for="password_confirmation">Yeni şifre tekrar *</label><input class="input" id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></div>
            <button class="btn btn-primary">Şifremi değiştir</button>
        </form>
    </section>
</x-portal-layout>
