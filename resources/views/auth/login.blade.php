<x-auth-layout title="Giriş Yap">
    <h1 class="auth-title">Tekrar hoş geldiniz</h1>
    <p class="auth-subtitle">Devam etmek için hesabınıza giriş yapın.</p>

    @if ($demoLogin)
        <div class="mt-7 grid grid-cols-2 gap-3.5">
            <button type="button" data-fill-email="admin@demo.test" data-fill-pass="password" data-auto-submit class="auth-demo">Demo yönetici</button>
            <button type="button" data-fill-email="sakin@demo.test" data-fill-pass="password" data-auto-submit class="auth-demo">Demo sakin</button>
        </div>
        <div class="my-6 flex items-center gap-3.5">
            <span class="h-px flex-1 bg-slate-100"></span>
            <span class="text-[12px] font-medium text-slate-400">veya</span>
            <span class="h-px flex-1 bg-slate-100"></span>
        </div>
    @else
        <div class="mt-7"></div>
    @endif

    <x-auth-alerts />

    <form method="POST" action="{{ url('/login') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="auth-label">E-posta <span class="text-orange-500">*</span></label>
            <input id="email" type="email" name="email" value="{{ old('email', $demoLogin ? 'admin@demo.test' : '') }}" required autofocus autocomplete="username" placeholder="E-posta adresiniz" class="auth-input">
        </div>

        <div>
            <label for="password" class="auth-label">Şifre <span class="text-orange-500">*</span></label>
            <div class="relative">
                <input id="password" type="password" name="password" value="{{ $demoLogin ? 'password' : '' }}" required autocomplete="current-password" placeholder="Şifreniz" class="auth-input pr-16">
                <button type="button" data-toggle-password="password" class="absolute inset-y-0 right-0 px-4 text-[12px] font-medium text-slate-400 hover:text-slate-700">Göster</button>
            </div>
        </div>

        <div class="flex items-center justify-between pt-1.5 text-[13px]">
            <label class="group inline-flex cursor-pointer items-center gap-2 text-slate-600 select-none">
                <input type="checkbox" name="remember" value="1" checked class="h-4 w-4 rounded-full border-slate-300 accent-orange-500">
                <span class="group-hover:text-slate-900">Beni hatırla</span>
            </label>
            <a href="{{ route('password.request') }}" class="font-medium text-slate-500 transition-colors hover:text-orange-600">Şifremi unuttum</a>
        </div>

        <button type="submit" class="auth-submit mt-3">Giriş yap</button>
    </form>
</x-auth-layout>
