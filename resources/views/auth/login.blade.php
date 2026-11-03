<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Memoshome | Giriş</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 p-4 selection:bg-teal-500/20">
    {{-- Minimalist arka plan --}}
    <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute left-[-10%] top-[-10%] h-[40%] w-[40%] rounded-full bg-teal-500/5 blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] h-[40%] w-[40%] rounded-full bg-blue-500/5 blur-[120px]"></div>
    </div>

    <main class="z-10 w-full max-w-[440px]">
        <div class="mb-10 text-center">
            <div class="mb-6 inline-flex rounded-2xl bg-teal-500 p-4 shadow-xl shadow-teal-500/20"><x-icon name="lock" :size="32" class="text-white" /></div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight text-slate-900">Memoshome</h1>
            <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-slate-400">Yönetim Portalı v2.0</p>
        </div>

        <div class="rounded-3xl border border-gray-100 bg-white p-10 shadow-xl shadow-slate-200/50">
            <form method="POST" action="{{ url('/login') }}" class="space-y-6">
                @csrf
                <div class="space-y-2">
                    <label for="email" class="ml-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">E-Posta Adresi</label>
                    <div class="group relative">
                        <x-icon name="mail" :size="18" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 transition-colors group-focus-within:text-teal-500" />
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="admin@memoshome.com"
                               class="w-full rounded-xl border border-gray-100 bg-slate-50/50 py-4 pl-12 pr-4 font-medium text-slate-900 outline-none transition-all placeholder:text-gray-300 focus:border-teal-500 focus:bg-white focus:ring-4 focus:ring-teal-500/5">
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="password" class="ml-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Şifre</label>
                    <div class="group relative">
                        <x-icon name="lock" :size="18" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 transition-colors group-focus-within:text-teal-500" />
                        <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••"
                               class="w-full rounded-xl border border-gray-100 bg-slate-50/50 py-4 pl-12 pr-4 font-medium text-slate-900 outline-none transition-all placeholder:text-gray-300 focus:border-teal-500 focus:bg-white focus:ring-4 focus:ring-teal-500/5">
                    </div>
                </div>

                @if ($errors->any())
                    <div role="alert" class="flex items-center gap-3 rounded-xl border border-red-100 bg-red-50 p-4 text-red-600">
                        <x-icon name="shield-alert" :size="18" />
                        <p class="text-[11px] font-bold leading-tight">{{ $errors->first() }}</p>
                    </div>
                @endif

                <button type="submit" class="group flex w-full items-center justify-center gap-3 rounded-xl bg-teal-500 py-4 text-sm font-bold tracking-wide text-white shadow-lg shadow-teal-500/20 transition-all hover:bg-teal-600 active:scale-[0.98]">
                    Sisteme Giriş Yap
                    <x-icon name="arrow-right" :size="18" class="transition-transform group-hover:translate-x-1" />
                </button>
            </form>

            <p class="mt-8 border-t border-slate-50 pt-8 text-center text-[10px] font-bold uppercase tracking-widest text-slate-400">
                Yönetici hesabı yalnızca sunucu tarafından tanımlanır
            </p>
        </div>

        <p class="mt-10 text-center text-[9px] font-bold uppercase tracking-[0.3em] text-slate-400">Güvenli Erişim Paneli · © {{ now()->year }}</p>
    </main>
</body>
</html>
