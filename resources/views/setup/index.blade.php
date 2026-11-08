@php
    $canContinue = collect($requirements)->every(fn ($requirement) => $requirement[1]);
    $initialPanel = match (true) {
        $errors->has('setup_token') => 'start',
        $errors->hasAny(['name', 'email', 'password']) => 'admin',
        $errors->hasAny(['site_name', 'site_code', 'site_blocks']) => 'site',
        $errors->hasAny(['install', 'db_host', 'db_port', 'db_database', 'db_username', 'db_password']) => 'db',
        default => 'start',
    };
    $wizardSteps = ['start' => 'Başlangıç', 'db' => 'Veritabanı', 'admin' => 'Yönetici', 'site' => 'İlk Site', 'review' => 'Kurulum'];
@endphp
<!DOCTYPE html>
<html lang="tr" class="h-full light" data-hud="classic" data-accent="teal" data-motion="on">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leans | Kurulum</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">
<main class="mx-auto flex min-h-screen max-w-4xl flex-col justify-center gap-6 px-4 py-10">
    <header class="flex items-center gap-3">
        <span class="ch-logo">L</span>
        <div>
            <h1 class="ch-ink text-xl font-bold tracking-tight">Leans Kurulum Sihirbazı</h1>
            <p class="ch-muted text-[13px]">Birkaç adımda sistemi hazırlayalım. Her adımda ne yapacağınızı göstereceğiz.</p>
        </div>
    </header>

    <div class="ch-panel grid overflow-hidden md:grid-cols-[210px_1fr]">
        <nav class="border-b border-[var(--ch-line)] bg-[var(--ch-subtle)] p-4 md:border-b-0 md:border-r" aria-label="Kurulum adımları">
            <ol class="flex flex-wrap gap-1 md:flex-col">
                @foreach ($wizardSteps as $key => $label)
                    <li class="setup-nav" data-setup-nav="{{ $key }}"><span class="setup-dot">{{ $loop->iteration }}</span>{{ $label }}</li>
                @endforeach
            </ol>
        </nav>

        <form id="setup-wizard" method="POST" action="{{ url('/setup') }}" class="flex min-h-[460px] flex-col p-6" data-initial-panel="{{ $initialPanel }}" @if ($errors->any()) data-has-errors @endif novalidate>
            @csrf

            @if ($errors->any())
                <div role="alert" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
                    <ul class="list-disc space-y-0.5 pl-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="flex-1 space-y-4">
                <section data-setup-panel="start" class="space-y-4">
                    <div>
                        <h2 class="ch-ink text-base font-semibold">Hoş geldiniz</h2>
                        <p class="ch-muted mt-0.5 text-[13px]">Önce sunucunun uygun olduğunu kontrol ediyoruz.</p>
                    </div>
                    <ul {{ tour(1, 'Sistem kontrolü', 'Sistemin çalışması için gereken parçalar burada denetlenir. Hepsinin yanında yeşil işaret olmalı. Kırmızı varsa önce onu düzeltmeniz gerekir.') }} class="space-y-1.5 rounded-xl border border-[var(--ch-line)] p-3 text-[13px]">
                        @foreach ($requirements as [$requirementLabel, $requirementOk])
                            <li class="flex items-center gap-2">
                                <span @class(['flex h-4 w-4 items-center justify-center rounded-full text-[10px] font-bold', 'bg-emerald-100 text-emerald-700' => $requirementOk, 'bg-rose-100 text-rose-700' => ! $requirementOk])>{{ $requirementOk ? '✓' : '!' }}</span>
                                <span class="ch-ink">{{ $requirementLabel }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <div {{ tour(2, 'Kurulum türü', 'Temiz kurulum, kendi verilerinizle sıfırdan başlar. Demo kurulum, sistemi denemeniz için örnek site, daire ve finans kayıtları yükler. Gerçek kullanım için Temiz kurulumu seçin.') }}>
                        <label class="label" for="mode">Kurulum türü</label>
                        <select id="mode" name="mode" class="input">
                            <option value="clean" @selected(old('mode') === 'clean')>Temiz kurulum</option>
                            @if ($allowDemo)<option value="demo" @selected(old('mode') === 'demo')>Demo verili kurulum</option>@endif
                        </select>
                    </div>
                    @if ($needsToken)
                        <div {{ tour(3, 'Kurulum kodu', 'Sunucu sahibinin .env dosyasına yazdığı SETUP_TOKEN değeridir. Yetkisiz kişilerin kurulumu tamamlamasını engeller.') }}>
                            <label class="label" for="setup_token">Kurulum kodu</label>
                            <input id="setup_token" name="setup_token" type="password" class="input" required autocomplete="off">
                        </div>
                    @endif
                </section>

                <section data-setup-panel="db" class="space-y-4" hidden>
                    <div>
                        <h2 class="ch-ink text-base font-semibold">Veritabanı</h2>
                        <p class="ch-muted mt-0.5 text-[13px]">Tüm kayıtların saklanacağı yer.</p>
                    </div>
                    <div {{ tour(10, 'Veritabanı türü', 'SQLite ayrı bir program istemez, tek bilgisayarda ve küçük kullanımda en kolayıdır. Birden fazla kişinin aynı anda çalışacağı canlı sistemde MySQL seçin.') }}>
                        <label class="label" for="db_connection">Veritabanı türü</label>
                        <select id="db_connection" name="db_connection" class="input" data-show-target>
                            <option value="sqlite" @selected(old('db_connection') === 'sqlite')>SQLite (kolay, ek program gerekmez)</option>
                            <option value="mysql" @selected(old('db_connection') === 'mysql')>MySQL</option>
                        </select>
                    </div>
                    <div data-target-for="mysql" class="grid gap-3 sm:grid-cols-2" hidden>
                        <div {{ tour(11, 'Sunucu adresi', 'MySQL aynı bilgisayardaysa 127.0.0.1 kalır. Hosting kullanıyorsanız panelinizde yazan adresi girin.') }}><label class="label" for="db_host">Sunucu</label><input id="db_host" name="db_host" class="input" value="{{ old('db_host', '127.0.0.1') }}"></div>
                        <div {{ tour(12, 'Port', 'MySQL için standart değer 3306. Hosting farklı bir değer vermediyse değiştirmeyin.') }}><label class="label" for="db_port">Port</label><input id="db_port" name="db_port" type="number" class="input" value="{{ old('db_port', 3306) }}"></div>
                        <div {{ tour(13, 'Veritabanı adı', 'phpMyAdmin veya hosting panelinde önceden oluşturduğunuz boş veritabanının adı.') }}><label class="label" for="db_database">Veritabanı adı</label><input id="db_database" name="db_database" class="input" value="{{ old('db_database') }}"></div>
                        <div {{ tour(14, 'Kullanıcı adı', 'Bu veritabanına erişim yetkisi olan MySQL kullanıcısı.') }}><label class="label" for="db_username">Kullanıcı adı</label><input id="db_username" name="db_username" class="input" value="{{ old('db_username', 'root') }}" autocomplete="off"></div>
                        <div class="sm:col-span-2" {{ tour(15, 'Şifre', 'MySQL kullanıcısının şifresi. XAMPP varsayılan kurulumunda boş bırakılır.') }}><label class="label" for="db_password">Şifre</label><input id="db_password" name="db_password" type="password" class="input" autocomplete="new-password"></div>
                    </div>
                </section>

                <section data-setup-panel="admin" data-clean-only class="space-y-4" hidden>
                    <div>
                        <h2 class="ch-ink text-base font-semibold">Yönetici hesabı</h2>
                        <p class="ch-muted mt-0.5 text-[13px]">Sisteme tam yetkiyle girecek ilk kişi siz olacaksınız.</p>
                    </div>
                    <div {{ tour(20, 'Ad soyad', 'Panelde ve işlem kayıtlarında görünecek adınız. Yapılan her finans işlemi bu adla kayda geçer.') }}><label class="label" for="name">Ad soyad</label><input id="name" name="name" class="input" value="{{ old('name') }}" required maxlength="120" autocomplete="name"></div>
                    <div {{ tour(21, 'E-posta', 'Panele giriş yaparken kullanacağınız adres.') }}><label class="label" for="email">E-posta</label><input id="email" name="email" type="email" class="input" value="{{ old('email') }}" required maxlength="120" autocomplete="username"></div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div {{ tour(22, 'Şifre', 'En az 8 karakter olmalı. Finans verilerine erişim sağladığı için tahmin edilemez bir şifre seçin ve bir yere not edin.') }}><label class="label" for="password">Şifre</label><input id="password" name="password" type="password" class="input" required minlength="8" autocomplete="new-password"></div>
                        <div {{ tour(23, 'Şifre tekrarı', 'Yanlış yazmadığınızdan emin olmak için aynı şifreyi tekrar girin.') }}><label class="label" for="password_confirmation">Şifre (tekrar)</label><input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password"></div>
                    </div>
                </section>

                <section data-setup-panel="site" data-clean-only class="space-y-4" hidden>
                    <div>
                        <h2 class="ch-ink text-base font-semibold">İlk site</h2>
                        <p class="ch-muted mt-0.5 text-[13px]">Yöneteceğiniz apartman veya siteyi tanımlayın. Sonra başkalarını da ekleyebilirsiniz.</p>
                    </div>
                    <div {{ tour(30, 'Site adı', 'Makbuz, rapor ve bildirimlerde görünecek ad. Örneğin Çınar Sitesi.') }}><label class="label" for="site_name">Site adı</label><input id="site_name" name="site_name" class="input" value="{{ old('site_name') }}" required maxlength="120"></div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div {{ tour(31, 'Kısa kod', 'Siteyi ayıran 2-5 harflik kod, örneğin CNR. Siteyi silmek gibi kritik işlemlerde onay olarak da istenir.') }}><label class="label" for="site_code">Kısa kod</label><input id="site_code" name="site_code" class="input uppercase" value="{{ old('site_code') }}" required maxlength="20" pattern="[A-Za-z0-9_\-]+"></div>
                        <div {{ tour(32, 'Bloklar', 'Bloklarınızı virgülle yazın, örneğin A,B,C. Tek binaysa A kalabilir. Daireleri kurulumdan sonra bu blokların altına ekleyeceksiniz.') }}><label class="label" for="site_blocks">Bloklar</label><input id="site_blocks" name="site_blocks" class="input uppercase" value="{{ old('site_blocks', 'A') }}" maxlength="100"></div>
                    </div>
                </section>

                <section data-setup-panel="review" class="space-y-4" hidden>
                    <div>
                        <h2 class="ch-ink text-base font-semibold">Kuruluma hazır</h2>
                        <p class="ch-muted mt-0.5 text-[13px]">Bilgileri kontrol edin. Kurulum birkaç saniye sürer.</p>
                    </div>
                    <dl {{ tour(40, 'Son kontrol', 'Seçimlerinizin özeti. Yanlış bir şey görürseniz Geri düğmesiyle dönüp düzeltebilirsiniz.') }} class="divide-y divide-[var(--ch-line)] rounded-xl border border-[var(--ch-line)] px-4 text-[13px]">
                        <div class="flex justify-between gap-3 py-2"><dt class="ch-muted">Kurulum türü</dt><dd class="ch-ink font-medium" data-summary="mode"></dd></div>
                        <div class="flex justify-between gap-3 py-2"><dt class="ch-muted">Veritabanı</dt><dd class="ch-ink font-medium" data-summary="db_connection"></dd></div>
                        <div class="flex justify-between gap-3 py-2" data-summary-clean><dt class="ch-muted">Yönetici</dt><dd class="ch-ink font-medium" data-summary="email"></dd></div>
                        <div class="flex justify-between gap-3 py-2" data-summary-clean><dt class="ch-muted">İlk site</dt><dd class="ch-ink font-medium" data-summary="site_name"></dd></div>
                    </dl>
                </section>
            </div>

            <div class="mt-6 flex items-center justify-between gap-3 border-t border-[var(--ch-line)] pt-4">
                <button type="button" class="ch-link" data-setup-guide data-tour-once {{ tour(90, 'Anlatımı tekrar aç', 'Bir adımı yeniden dinlemek isterseniz bu bağlantıya tıklayın.') }}>Bu adımı anlat</button>
                <div class="flex gap-2">
                    <button type="button" class="ch-btn" data-setup-back data-tour-once hidden {{ tour(91, 'Geri', 'Bir önceki adıma döner. Yazdıklarınız silinmez.') }}>Geri</button>
                    <button type="button" class="ch-btn-solid !w-auto px-5" data-setup-next data-tour-once @disabled(! $canContinue) {{ tour(92, 'İleri', 'Bu adımdaki bilgiler doğruysa sonraki adıma geçer. Eksik alan varsa uyarır.') }}>İleri</button>
                    <button type="submit" class="ch-btn-solid !w-auto px-5" data-setup-submit hidden {{ tour(93, 'Kurulumu başlat', 'Tabloları oluşturur, hesabınızı ve sitenizi açar, sizi panele alır. Ardından paneldeki düğmeleri de tek tek tanıtacağız.') }}>Kurulumu Başlat</button>
                </div>
            </div>
        </form>
    </div>
</main>
</body>
</html>
