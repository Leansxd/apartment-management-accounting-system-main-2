<?php

use App\Models\Apartment;
use App\Models\User;
use App\Services\InstallService;
use Illuminate\Support\Facades\Artisan;
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
