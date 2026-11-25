<x-layout heading="Şifremi Değiştir" subheading="Şifreniz değişince diğer cihazlardaki oturumlarınız kapanır">
    <section class="card max-w-lg">
        <form method="POST" action="{{ route('account.password.update') }}" class="card-body space-y-4">
            @csrf @method('PUT')
            <div><label class="label" for="current_password">Mevcut şifre *</label><input class="input" id="current_password" type="password" name="current_password" required autofocus autocomplete="current-password"></div>
            <div><label class="label" for="password">Yeni şifre *</label><input class="input" id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
                <p class="mt-1 text-[11px] text-slate-400">En az 8 karakter.</p></div>
            <div><label class="label" for="password_confirmation">Yeni şifre tekrar *</label><input class="input" id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></div>
            <button class="btn btn-primary">Şifremi değiştir</button>
        </form>
    </section>
</x-layout>
