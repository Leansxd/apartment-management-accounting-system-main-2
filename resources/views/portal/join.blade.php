<x-auth-layout title="Sakin Hesabı Aç">
    <h1 class="auth-title">Sakin hesabı açın</h1>
    <p class="auth-subtitle">Site yönetiminizin verdiği davet koduyla dairenize bağlanın.</p>

    <div class="mt-7"></div>
    <x-auth-alerts />

    <form method="POST" action="{{ route('portal.join.store') }}" class="space-y-4">
        @csrf
        <div><label for="invite_code" class="auth-label">Davet kodu <span class="text-orange-500">*</span></label>
            <input id="invite_code" name="invite_code" value="{{ old('invite_code') }}" required autocomplete="off" class="auth-input uppercase"></div>
        <div><label for="name" class="auth-label">Ad soyad <span class="text-orange-500">*</span></label>
            <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name" class="auth-input"></div>
        <div><label for="email" class="auth-label">E-posta <span class="text-orange-500">*</span></label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" class="auth-input"></div>
        <div><label for="phone" class="auth-label">Telefon</label>
            <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" class="auth-input"></div>
        <div><label for="password" class="auth-label">Şifre <span class="text-orange-500">*</span></label>
            <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" class="auth-input"></div>
        <div><label for="password_confirmation" class="auth-label">Şifre tekrar <span class="text-orange-500">*</span></label>
            <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="auth-input"></div>
        <button type="submit" class="auth-submit mt-3">Hesabımı aç</button>
    </form>

    <p class="mt-5 text-center text-[13px] text-slate-500">Hesabınız var mı? <a href="{{ route('login') }}" class="font-semibold text-slate-900 hover:text-orange-600">Giriş yapın</a></p>
</x-auth-layout>
