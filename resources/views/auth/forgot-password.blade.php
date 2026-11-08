<x-auth-layout title="Şifremi Unuttum">
    <h1 class="auth-title">Şifrenizi mi unuttunuz?</h1>
    <p class="auth-subtitle">E-posta adresinizi yazın, size şifre sıfırlama bağlantısı gönderelim.</p>

    <div class="mt-7"></div>
    <x-auth-alerts />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="auth-label">E-posta <span class="text-orange-500">*</span></label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="E-posta adresiniz" class="auth-input">
        </div>
        <button type="submit" class="auth-submit mt-3">Sıfırlama bağlantısı gönder</button>
    </form>

    <a href="{{ route('login') }}" class="mt-6 block text-center text-[13px] font-medium text-slate-500 transition-colors hover:text-orange-600">Girişe dön</a>
</x-auth-layout>
