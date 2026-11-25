<?php

use App\Models\Apartment;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\DatabaseBackupService;
use App\Services\FinanceService;
use App\Services\InstallService;
use App\Services\ProductionCheckService;
use App\Services\ReconciliationService;
use App\Services\ReminderService;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/*
 * Sıfırdan kurulum. Tekrar çalıştırmak güvenlidir: anahtarı ve var olan verileri değiştirmez.
 *   php artisan app:setup          (soru sorarak temiz veya demo kurulum)
 *   php artisan app:setup --demo   (demo verisi; canlı ortamda çalışmaz)
 *   php artisan app:setup -n --name="Ad" --email=a@b.com --password="GucluSifre123" --site="Site" --code=ABC
 */
Artisan::command('app:setup {--demo : Demo verisiyle kur} {--name= : Yönetici adı} {--email= : Yönetici e-postası} {--password= : Yönetici şifresi} {--site= : İlk site adı} {--code= : İlk site kısa kodu} {--blocks=A : Virgülle ayrılmış blok adları}',
    function (InstallService $install) {
        if (! is_file(base_path('.env'))) {
            copy(base_path('.env.example'), base_path('.env'));
            $this->info('.env dosyası oluşturuldu.');
        }
        if (! config('app.key')) {
            $this->call('key:generate', ['--force' => true]);
        }

        try {
            $install->prepareDatabase();
        } catch (Throwable $e) {
            $this->error('Veritabanı hazırlanamadı: '.$e->getMessage());
            $this->line('.env içindeki DB_ ayarlarını kontrol edip komutu tekrar çalıştırın.');

            return 1;
        }

        if (! is_file(public_path('build/manifest.json'))) {
            $this->warn('Arayüz henüz derlenmemiş: npm install ve npm run build çalıştırın.');
        }
        if (app()->isProduction() && config('app.debug')) {
            $this->warn('Canlı ortamda .env içinde APP_DEBUG=false yapın.');
        }

        $finish = function () {
            $this->newLine();
            $this->info('Kurulum tamamlandı.');
            $this->line('Başlatmak için: php artisan serve (http://localhost:8000)');

            return 0;
        };

        if (User::where('role', 'admin')->exists()) {
            $this->line('Yönetici hesabı zaten var; hesap ve site adımları atlandı.');

            return $finish();
        }

        $interactive = $this->input->isInteractive();
        $wantsDemo = $this->option('demo') || ($interactive && ! $this->option('email')
            && $this->choice('Kurulum türü', ['Temiz kurulum', 'Demo verili kurulum'], 0) === 'Demo verili kurulum');
        if ($wantsDemo) {
            try {
                $install->loadDemo();
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());

                return 1;
            }
            $this->warn('Demo giriş: admin@demo.test / password');

            return $finish();
        }

        if (! $interactive && ! $this->option('email')) {
            $this->warn('Veritabanı hazır, yönetici oluşturulmadı. Tamamlamak için: php artisan app:setup');

            return 0;
        }

        $prompt = function (string $label, array $rules, ?string $value = null, bool $secret = false) use ($interactive) {
            while (true) {
                $value ??= $interactive ? ($secret ? $this->secret($label) : $this->ask($label)) : null;
                $error = Validator::make(['value' => $value], ['value' => $rules], [], ['value' => $label])->errors()->first('value');
                if (! $error) {
                    return $value;
                }
                if (! $interactive) {
                    throw new InvalidArgumentException($error);
                }
                $this->error($error);
                $value = null;
            }
        };

        try {
            $name = $prompt('Yönetici adı soyadı', ['required', 'string', 'max:120'], $this->option('name'));
            $email = $prompt('Yönetici e-postası', ['required', 'email', 'max:120', 'unique:users,email'], $this->option('email'));
            do {
                $password = $prompt('Şifre (en az 8 karakter)', ['required', 'string', 'min:8'], $this->option('password'), true);
                $confirmed = $this->option('password') || $password === $this->secret('Şifre (tekrar)');
                $confirmed || $this->error('Şifreler aynı değil.');
            } while (! $confirmed);

            $site = null;
            if ($interactive || $this->option('site')) {
                $site = [
                    'name' => $prompt('İlk site adı', ['required', 'string', 'max:120'], $this->option('site')),
                    'code' => $prompt('Site kısa kodu (ör. ABC)', ['required', 'alpha_dash', 'max:20',
                        fn ($attribute, $value, $fail) => Apartment::where('code', mb_strtoupper($value))->exists() && $fail('Bu kod kullanılıyor.'),
                    ], $this->option('code')),
                    'blocks' => $interactive && ! $this->option('site') ? $this->ask('Bloklar (virgülle)', 'A') : $this->option('blocks'),
                ];
            }
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $install->createOwner($name, $email, $password, $site);
        $this->info("Süper admin oluşturuldu: {$email}");

        return $finish();
    })->purpose('Uygulamayı sıfırdan kurar: ortam, veritabanı, ilk yönetici ve site');

/*
 * İlk yöneticiyi oluşturur veya var olan kullanıcıyı yönetici yapar.
 *   php artisan app:make-admin ornek@site.com --create --password="GucluSifre123" --super
 */
Artisan::command('app:make-admin {email} {--create : Kullanıcı yoksa oluştur} {--password= : Yeni kullanıcının şifresi} {--name= : Ad soyad} {--super : Tüm sitelere erişen süper admin}',
    function (string $email) {
        $user = User::where('email', $email)->first();
        if (! $user) {
            if (! $this->option('create')) {
                $this->error("{$email} bulunamadı. Oluşturmak için --create ekleyin.");

                return 1;
            }
            $password = $this->option('password') ?: Str::password(14);
            if (strlen($password) < 8) {
                $this->error('Şifre en az 8 karakter olmalıdır.');

                return 1;
            }
            $user = User::create(['name' => $this->option('name') ?: Str::before($email, '@'), 'email' => $email, 'password' => $password]);
            if (! $this->option('password')) {
                $this->warn("Oluşturulan şifre: {$password}  (giriş yaptıktan sonra değiştirin)");
            }
        }

        $user->forceFill(['role' => 'admin', 'is_super_admin' => (bool) $this->option('super') || $user->is_super_admin])->save();
        $this->info("{$email} artık ".($user->is_super_admin ? 'süper admin' : 'yönetici').'.');

        return 0;
    })->purpose('Panel yöneticisi oluşturur / atar');

/*
 * Otomatik seçeneği açık sitelerde aidat ve gecikme zammı borçlarını yazar. Tekrar çalıştırmak güvenlidir.
 * Bir sitedeki hata diğer siteleri durdurmaz; sorunlu site ertesi gece yeniden denenir.
 */
Artisan::command('app:run-billing', function (FinanceService $finance) {
    $failedSites = 0;

    Apartment::where('dues_auto_run', true)->orWhere('late_fee_auto_run', true)->each(function (Apartment $apartment) use ($finance, &$failedSites) {
        try {
            if ($apartment->dues_auto_run && $apartment->duesDefinitions()->where('is_active', true)->exists()) {
                $finance->generateDues($apartment, now()->format('Y-m'));
            }
            if ($apartment->late_fee_auto_run) {
                $finance->runLateFees($apartment);
            }
        } catch (Throwable $e) {
            report($e);
            $failedSites++;
            $this->error("{$apartment->name}: {$e->getMessage()}");
        }
    });

    return $failedSites === 0 ? 0 : 1;
})->purpose('Otomatik aidat ve gecikme zammı borçlarını yazar');

/*
 * Veritabanının sıkıştırılmış yedeğini storage/app/private/backups altına alır, eski yedekleri temizler.
 */
Artisan::command('app:send-reminders', function (ReminderService $reminders) {
    $failedSites = 0;

    Apartment::where('reminder_auto_run', true)->each(function (Apartment $apartment) use ($reminders, &$failedSites) {
        try {
            $sent = $reminders->runScheduled($apartment);
            if ($sent) {
                $this->info("{$apartment->name}: {$sent} daireye hatırlatma gönderildi.");
            }
        } catch (Throwable $e) {
            report($e);
            $failedSites++;
            $this->error("{$apartment->name}: {$e->getMessage()}");
        }
    });

    return $failedSites === 0 ? 0 : 1;
})->purpose('Otomatik hatırlatması açık sitelerde gecikmiş borç hatırlatması gönderir');

/*
 * Bir yedeği geri yükler. Önce mevcut durumun güvenlik yedeğini alır.
 */
Artisan::command('app:restore {backup? : Yedek dosyasının adı} {--force : Onay sormadan geri yükle}', function (DatabaseBackupService $backups, AuditTrail $audit) {
    $available = $backups->available();
    if ($available->isEmpty()) {
        $this->error('Geri yüklenecek yedek bulunamadı.');

        return 1;
    }
    $name = $this->argument('backup') ?? $this->choice('Hangi yedek geri yüklensin?', $available->pluck('name')->all());
    if (! $this->option('force') && ! $this->confirm("{$name} geri yüklenecek ve mevcut veriler bu yedekle değişecek. Emin misiniz?")) {
        return 1;
    }

    try {
        $safetyBackup = $backups->restore($name);
    } catch (RuntimeException $e) {
        report($e);
        $this->error($e->getMessage());

        return 1;
    }
    $audit->log(null, 'RESTORE_BACKUP', 'SYSTEM', $name, ['safety_backup' => basename($safetyBackup), 'channel' => 'console']);
    $this->info("Yedek geri yüklendi. Önceki durumun yedeği: {$safetyBackup}");

    return 0;
})->purpose('Veritabanı yedeğini geri yükler');

Artisan::command('app:backup', function (DatabaseBackupService $backups) {
    try {
        $this->info('Yedek alındı: '.$backups->create());
    } catch (Throwable $e) {
        report($e);
        $this->error('Yedek alınamadı: '.$e->getMessage());

        return 1;
    }

    return 0;
})->purpose('Veritabanını yedekler');

/*
 * Canlıya çıkmadan önce ayarları denetler. Engel varsa komut hata koduyla biter.
 */
Artisan::command('app:check-production', function (ProductionCheckService $checks) {
    $results = collect($checks->results());
    $marks = ['ok' => 'TAMAM', 'warn' => 'UYARI', 'fail' => 'ENGEL'];
    $this->table(['Durum', 'Kontrol', 'Yapılacak'], $results->map(fn ($r) => [$marks[$r['status']], $r['label'], $r['detail']])->all());
    $this->line($results->where('status', 'fail')->count().' engel, '.$results->where('status', 'warn')->count().' uyarı.');

    return $results->contains('status', 'fail') ? 1 : 0;
})->purpose('Canlıya çıkış ayarlarını denetler');

/*
 * Yevmiye defterini kasa, daire borcu ve avans bakiyeleriyle karşılaştırır. Sapma varsa hata koduyla biter.
 *   php artisan app:reconcile            (tüm siteler)
 *   php artisan app:reconcile --site=ABC (tek site, kısa kod)
 */
Artisan::command('app:reconcile {--site= : Yalnızca bu kısa kodlu site}', function (ReconciliationService $reconciliation) {
    $apartments = Apartment::when($this->option('site'), fn ($q, $code) => $q->where('code', mb_strtoupper($code)))->get();
    $rows = [];
    foreach ($apartments as $apartment) {
        foreach ($reconciliation->discrepancies($apartment) as $issue) {
            $rows[] = [$apartment->name, $issue['check'], $issue['subject'], number_format($issue['expected'] / 100, 2), number_format($issue['actual'] / 100, 2)];
        }
    }
    if ($rows === []) {
        $this->info($apartments->count().' site tutarlı: yevmiye ile bakiyeler eşleşiyor.');

        return 0;
    }
    $this->table(['Site', 'Kontrol', 'Konu', 'Yevmiye', 'Tutulan'], $rows);
    $this->error(count($rows).' sapma bulundu.');

    return 1;
})->purpose('Yevmiye defterini bakiyelerle karşılaştırır');

/*
 * Telefonunu ve kurtarma kodlarını kaybeden yöneticinin iki adımlı doğrulamasını sunucudan kapatır.
 *   php artisan app:disable-two-factor ornek@site.com
 */
Artisan::command('app:disable-two-factor {email}', function (TwoFactorService $twoFactor, string $email) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        $this->error("{$email} bulunamadı.");

        return 1;
    }
    $twoFactor->disable($user);
    $this->info("{$email} için iki adımlı doğrulama kapatıldı.");

    return 0;
})->purpose('Bir hesabın iki adımlı doğrulamasını kapatır');

Schedule::command('app:run-billing')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('app:backup')->dailyAt('03:00')->withoutOverlapping(60);
Schedule::command('app:reconcile')->dailyAt('04:00')->withoutOverlapping();
Schedule::command('app:send-reminders')->dailyAt('09:00')->withoutOverlapping();

// Ayrı bir kuyruk servisi gerekmez: her dakika bekleyen işleri (e-postalar) bitirip çıkar
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(10);
