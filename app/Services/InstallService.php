<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Uygulamanın sıfırdan kurulumu: hem /setup sihirbazı hem app:setup komutu bunu kullanır. */
class InstallService
{
    /** İşaret dosyası bir kez yazıldıktan sonra veritabanı arızası kurulumu yeniden açamaz. */
    public function installed(): bool
    {
        if (is_file(config('setup.marker'))) {
            return true;
        }
        try {
            $hasAdmin = User::where('role', 'admin')->exists();
        } catch (Throwable) {
            return false; // veritabanı veya tablolar henüz yok
        }
        if ($hasAdmin) {
            $this->markInstalled();
        }

        return $hasAdmin;
    }

    /** Veritabanı hazır olmadan sihirbazın açılabilmesi için anahtar, oturum ve önbellek ayarlarını hazırlar. */
    public function prepareRuntime(): void
    {
        $env = base_path('.env');
        if (! is_file($env) && ! @copy(base_path('.env.example'), $env)) {
            throw new RuntimeException('.env dosyası oluşturulamadı: proje klasörüne yazma izni verin.');
        }
        if (! config('app.key')) {
            $key = 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));
            $this->writeEnv(['APP_KEY' => $key]);
            config(['app.key' => $key]);
        }
        foreach (['session.driver', 'cache.default'] as $setting) {
            if (config($setting) === 'database') {
                config([$setting => 'file']);
            }
        }
        config(['app.locale' => 'tr']);
        App::setLocale('tr');
    }

    /** @return list<array{0:string,1:bool}> */
    public function requirements(): array
    {
        return [
            ['PHP 8.3 veya üzeri (şu an '.PHP_VERSION.')', PHP_VERSION_ID >= 80300],
            ['PDO ve SQLite eklentileri', extension_loaded('pdo') && extension_loaded('pdo_sqlite')],
            ['mbstring ve OpenSSL eklentileri', extension_loaded('mbstring') && extension_loaded('openssl')],
            ['storage klasörü yazılabilir', is_writable(storage_path())],
            ['bootstrap/cache klasörü yazılabilir', is_writable(base_path('bootstrap/cache'))],
            ['.env dosyası yazılabilir', is_writable(base_path('.env'))],
        ];
    }

    /** Veritabanına bağlanır, tabloları oluşturur ve dosya bağlantısını kurar. */
    public function prepareDatabase(): void
    {
        $connection = config('database.connections.'.config('database.default'));
        if ($connection['driver'] === 'sqlite' && $connection['database'] !== ':memory:' && ! is_file($connection['database'])) {
            touch($connection['database']);
        }
        DB::connection()->getPdo();

        Artisan::call('migrate', ['--force' => true]);
        if (! file_exists(public_path('storage'))) {
            Artisan::call('storage:link');
        }
    }

    /** @param array{name:string, code:string, blocks?:string|null}|null $site */
    public function createOwner(string $name, string $email, string $password, ?array $site): User
    {
        return DB::transaction(function () use ($name, $email, $password, $site) {
            $admin = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
            $admin->forceFill(['role' => 'admin', 'is_super_admin' => true])->save();
            $site && Apartment::provision($site, $admin);

            return $admin;
        });
    }

    public function loadDemo(): User
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo verisi canlı ortama yüklenemez: herkesin bildiği şifrelerle hesap açar.');
        }
        if (User::exists()) {
            throw new RuntimeException('Demo verisi yalnızca boş veritabanına yüklenebilir.');
        }
        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        return User::where('is_super_admin', true)->firstOrFail();
    }

    /** Sihirbaz formundan kurulum. Veritabanı ayarı en sonda yazılır: hata olursa .env değişmez. */
    public function install(array $data): User
    {
        $database = $this->useDatabase($data);
        $this->prepareDatabase();

        if ($data['mode'] === 'demo') {
            $owner = $this->loadDemo();
        } else {
            if (User::where('email', $data['email'])->exists()) {
                throw new RuntimeException('Bu e-posta adresi zaten kayıtlı.');
            }
            if (Apartment::where('code', mb_strtoupper($data['site_code']))->exists()) {
                throw new RuntimeException('Bu site kodu zaten kullanılıyor.');
            }
            $owner = $this->createOwner($data['name'], $data['email'], $data['password'], [
                'name' => $data['site_name'], 'code' => $data['site_code'], 'blocks' => $data['site_blocks'] ?? 'A',
            ]);
        }

        $database && $this->writeEnv($database);
        $this->markInstalled();

        return $owner;
    }

    /** @return array<string,string>|null .env'e yazılacak MySQL ayarları */
    private function useDatabase(array $data): ?array
    {
        if ($data['db_connection'] !== 'mysql') {
            return null;
        }
        config(['database.default' => 'mysql', 'database.connections.mysql' => [
            'host' => $data['db_host'], 'port' => $data['db_port'], 'database' => $data['db_database'],
            'username' => $data['db_username'], 'password' => $data['db_password'] ?? '',
        ] + config('database.connections.mysql')]);
        DB::purge('mysql');

        return [
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => $data['db_host'], 'DB_PORT' => (string) $data['db_port'],
            'DB_DATABASE' => $data['db_database'], 'DB_USERNAME' => $data['db_username'], 'DB_PASSWORD' => $data['db_password'] ?? '',
        ];
    }

    private function markInstalled(): void
    {
        $marker = config('setup.marker');
        is_dir(dirname($marker)) || mkdir(dirname($marker), 0755, true);
        file_put_contents($marker, now()->toIso8601String());
    }

    /** @param array<string,string> $values */
    private function writeEnv(array $values): void
    {
        $path = base_path('.env');
        $content = (string) file_get_contents($path);
        foreach ($values as $key => $value) {
            $line = $key.'="'.addcslashes($value, '"\\$').'"';
            $replaced = preg_replace_callback("/^#?\\s*{$key}=.*$/m", fn () => $line, $content, 1, $count);
            $content = $count ? $replaced : rtrim($content)."\n{$line}\n";
        }
        if (@file_put_contents($path, $content) === false) {
            throw new RuntimeException('.env dosyasına yazılamadı: dosyaya yazma izni verin.');
        }
    }
}
