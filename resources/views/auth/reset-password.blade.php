<x-auth-layout title="Yeni Şifre">
    <h1 class="auth-title">Yeni şifrenizi belirleyin</h1>
    <p class="auth-subtitle">En az 8 karakterlik yeni bir şifre yazın.</p>

    <div class="mt-7"></div>
    <x-auth-alerts />

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="auth-label">E-posta <span class="text-orange-500">*</span></label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username" class="auth-input">
        </div>
        <div>
            <label for="password" class="auth-label">Yeni şifre <span class="text-orange-500">*</span></label>
            <input id="password" type="password" name="password" required autofocus autocomplete="new-password" class="auth-input">
        </div>
        <div>
            <label for="password_confirmation" class="auth-label">Yeni şifre tekrar <span class="text-orange-500">*</span></label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="auth-input">
        </div>
        <button type="submit" class="auth-submit mt-3">Şifremi değiştir</button>
    </form>
</x-auth-layout>
