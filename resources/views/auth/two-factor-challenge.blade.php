<x-auth-layout title="Doğrulama Kodu">
    <h1 class="auth-title">Doğrulama kodu</h1>
    <p class="auth-subtitle">Doğrulama uygulamanızdaki 6 haneli kodu yazın. Telefonunuza erişemiyorsanız kurtarma kodlarınızdan birini kullanın.</p>

    <div class="mt-7"></div>
    <x-auth-alerts />

    <form method="POST" action="{{ route('two-factor.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="code" class="auth-label">Kod <span class="text-orange-500">*</span></label>
            <input id="code" type="text" name="code" required autofocus autocomplete="one-time-code" class="auth-input">
        </div>
        <button type="submit" class="auth-submit mt-3">Doğrula ve giriş yap</button>
    </form>
    <p class="mt-5 text-center text-[13px]"><a href="{{ route('login') }}" class="font-medium text-slate-500 hover:text-orange-600">Giriş ekranına dön</a></p>
</x-auth-layout>
