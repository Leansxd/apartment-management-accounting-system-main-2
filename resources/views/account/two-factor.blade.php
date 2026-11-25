<x-layout heading="İki Adımlı Doğrulama" subheading="Girişte şifrenize ek olarak telefonunuzdaki kod istenir">
    @if (session('recoveryCodes'))
        <section class="card max-w-2xl" data-recovery-codes>
            <div class="card-head"><h2 class="card-title">Kurtarma kodlarınız</h2><span class="badge badge-amber">Yalnızca bir kez gösterilir</span></div>
            <div class="card-body space-y-3">
                <p class="text-sm text-slate-500">Bu kodları güvenli bir yere kaydedin. Telefonunuza erişemezseniz her kod bir kez giriş yapmanızı sağlar.</p>
                <ul class="grid grid-cols-2 gap-2 font-mono text-sm tabular-nums text-white">
                    @foreach (session('recoveryCodes') as $code)<li class="soft-row !py-2">{{ $code }}</li>@endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($enabled)
        <section class="card max-w-2xl">
            <div class="card-head"><h2 class="card-title">Durum</h2><span class="badge badge-green">Açık</span></div>
            <div class="card-body space-y-5">
                <p class="text-sm text-slate-500">{{ $remainingCodes }} kurtarma kodunuz kaldı.</p>
                <form method="POST" action="{{ route('account.two-factor.codes') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div><label class="label" for="regen-password">Mevcut şifre</label><input class="input" id="regen-password" type="password" name="current_password" required autocomplete="current-password"></div>
                    <button class="btn btn-secondary">Kurtarma kodlarını yenile</button>
                </form>
                <form method="POST" action="{{ route('account.two-factor.destroy') }}" class="flex flex-wrap items-end gap-3" onsubmit="return confirm('İki adımlı doğrulama kapatılsın mı?')">
                    @csrf @method('DELETE')
                    <div><label class="label" for="off-password">Mevcut şifre</label><input class="input" id="off-password" type="password" name="current_password" required autocomplete="current-password"></div>
                    <button class="btn btn-danger">Kapat</button>
                </form>
            </div>
        </section>
    @elseif ($pending)
        <section class="card max-w-2xl">
            <div class="card-head"><h2 class="card-title">Kurulumu tamamlayın</h2><span class="badge badge-amber">Onay bekliyor</span></div>
            <div class="card-body space-y-4">
                <p class="text-sm text-slate-500">Google Authenticator, Microsoft Authenticator veya benzeri bir uygulamayla QR kodu okutun, ardından üretilen kodu yazın.</p>
                <div class="inline-block rounded-xl bg-white p-3" data-two-factor-qr>{!! $qrCode !!}</div>
                <p class="text-xs text-slate-500">QR okutamıyorsanız bu anahtarı elle girin: <span class="font-mono text-white select-all">{{ $secret }}</span></p>
                <form method="POST" action="{{ route('account.two-factor.confirm') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div><label class="label" for="code">6 haneli kod</label><input class="input" id="code" name="code" inputmode="numeric" required autofocus autocomplete="one-time-code"></div>
                    <button class="btn btn-primary">Onayla ve aç</button>
                </form>
                <form method="POST" action="{{ route('account.two-factor.destroy') }}">@csrf @method('DELETE')<button class="btn btn-secondary btn-sm">Vazgeç</button></form>
            </div>
        </section>
    @else
        <section class="card max-w-2xl">
            <div class="card-head"><h2 class="card-title">Durum</h2><span class="badge badge-gray">Kapalı</span></div>
            <form method="POST" action="{{ route('account.two-factor.store') }}" class="card-body space-y-4">
                @csrf
                <p class="text-sm text-slate-500">Açıldığında şifreniz ele geçse bile telefonunuzdaki kod olmadan panele girilemez.</p>
                <div class="max-w-xs"><label class="label" for="current_password">Mevcut şifre</label><input class="input" id="current_password" type="password" name="current_password" required autocomplete="current-password"></div>
                <button class="btn btn-primary">Kuruluma başla</button>
            </form>
        </section>
    @endif
</x-layout>
