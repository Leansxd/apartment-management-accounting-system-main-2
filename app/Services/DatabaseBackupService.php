<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Veritabanının sıkıştırılmış yedeğini herkese açık olmayan diske alır ve eski yedekleri temizler.
 * Şifre komut satırına yazılmaz; yarım kalan yedek listede görünmez.
 */
class DatabaseBackupService
{
    private const DIRECTORY = 'backups';

    /** @return string diske göre yedeğin yolu */
    public function create(): string
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIRECTORY);
        $connection = config('database.connections.'.config('database.default'));
        $stamp = now()->format('Y-m-d-His');
        $raw = $disk->path(self::DIRECTORY."/.{$stamp}.tmp");
        $target = self::DIRECTORY."/backup-{$stamp}-{$connection['driver']}.gz";

        try {
            match ($connection['driver']) {
                'mysql', 'mariadb' => $this->dumpMysql($connection, $raw),
                'sqlite' => DB::statement('VACUUM INTO ?', [$raw]),
                default => throw new RuntimeException("{$connection['driver']} veritabanı yedeklenemiyor."),
            };
            if (! is_file($raw) || filesize($raw) === 0) {
                throw new RuntimeException('Yedek dosyası boş oluştu.');
            }
            $this->compress($raw, $disk->path($target));
        } finally {
            @unlink($raw);
            @unlink($disk->path($target).'.part');
        }

        $this->prune();

        return $target;
    }

    public function lastBackupAt(): ?CarbonImmutable
    {
        $latest = $this->backups()->last();

        return $latest ? CarbonImmutable::createFromTimestamp(Storage::disk('local')->lastModified($latest), config('app.timezone')) : null;
    }

    /** @return Collection<int, array{name: string, size: int, created_at: CarbonImmutable}> yeniden eskiye */
    public function available(): Collection
    {
        $disk = Storage::disk('local');

        return $this->backups()->reverse()->values()->map(fn (string $path) => [
            'name' => basename($path),
            'size' => $disk->size($path),
            'created_at' => CarbonImmutable::createFromTimestamp($disk->lastModified($path), config('app.timezone')),
        ]);
    }

    /**
     * Yedeği geri yükler. Önce mevcut durumun güvenlik yedeğini alır, böylece işlem de geri alınabilir.
     * Seçilen yedek, güvenlik yedeği eski dosyaları temizlemeden önce açılır.
     *
     * @return string alınan güvenlik yedeğinin yolu
     */
    public function restore(string $backup): string
    {
        $disk = Storage::disk('local');
        $path = self::DIRECTORY.'/'.basename($backup);
        if (! $this->backups()->contains($path)) {
            throw new RuntimeException('Yedek dosyası bulunamadı.');
        }
        $connection = config('database.connections.'.config('database.default'));
        if (! str_ends_with($path, "-{$connection['driver']}.gz")) {
            throw new RuntimeException('Bu yedek farklı bir veritabanı türüne ait.');
        }

        $raw = $disk->path(self::DIRECTORY.'/.restore-'.Str::random(12).'.tmp');

        try {
            $this->decompress($disk->path($path), $raw);
            $safetyBackup = $this->create();
            match ($connection['driver']) {
                'mysql', 'mariadb' => $this->loadMysql($connection, $raw),
                'sqlite' => $this->loadSqlite($connection, $raw),
                default => throw new RuntimeException("{$connection['driver']} veritabanı geri yüklenemiyor."),
            };
        } finally {
            @unlink($raw);
        }

        // Eski bir yedek, uygulamanın şimdiki sürümünden eski tablolarla gelebilir
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException('Yedek yüklendi ama tablolar güncellenemedi: '.trim(Artisan::output()));
        }

        return $safetyBackup;
    }

    /** @param array<string, mixed> $connection */
    private function dumpMysql(array $connection, string $raw): void
    {
        $result = $this->withMysqlCredentials($connection, fn (string $credentials) => Process::timeout(900)->run([
            config('backup.mysqldump'), "--defaults-extra-file={$credentials}", '--single-transaction', '--quick',
            '--no-tablespaces', '--default-character-set=utf8mb4', "--result-file={$raw}", $connection['database'],
        ]));

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'mysqldump başarısız oldu.');
        }
    }

    /** @param array<string, mixed> $connection */
    private function loadMysql(array $connection, string $raw): void
    {
        $client = config('backup.mysql') ?: preg_replace('/mysqldump(\.exe)?$/i', 'mysql$1', (string) config('backup.mysqldump'));
        $input = fopen($raw, 'rb');

        try {
            $result = $this->withMysqlCredentials($connection, fn (string $credentials) => Process::timeout(900)->input($input)->run([
                $client, "--defaults-extra-file={$credentials}", '--default-character-set=utf8mb4', $connection['database'],
            ]));
        } finally {
            fclose($input);
        }

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'mysql başarısız oldu.');
        }
    }

    /** @param array<string, mixed> $connection */
    private function loadSqlite(array $connection, string $raw): void
    {
        if ($connection['database'] === ':memory:') {
            throw new RuntimeException('Bellek içi veritabanı geri yüklenemez.');
        }
        DB::disconnect();
        if (! copy($raw, $connection['database'])) {
            throw new RuntimeException('Veritabanı dosyası yazılamadı.');
        }
    }

    /**
     * Şifre komut satırında görünmesin diye geçici, yalnızca sahibinin okuyabildiği dosyaya yazılır.
     *
     * @param  array<string, mixed>  $connection
     * @param  callable(string): ProcessResult  $run
     */
    private function withMysqlCredentials(array $connection, callable $run): ProcessResult
    {
        $credentials = tempnam(sys_get_temp_dir(), 'backup');
        chmod($credentials, 0600);
        file_put_contents($credentials, implode("\n", array_filter([
            '[client]',
            'host='.$connection['host'],
            'port='.$connection['port'],
            'user='.$connection['username'],
            'password="'.addcslashes((string) $connection['password'], '\\"').'"',
            filled($connection['unix_socket'] ?? null) ? 'socket='.$connection['unix_socket'] : null,
        ]))."\n");

        try {
            return $run($credentials);
        } finally {
            @unlink($credentials);
        }
    }

    private function decompress(string $source, string $target): void
    {
        $input = gzopen($source, 'rb');
        $output = fopen($target, 'wb');
        while (! gzeof($input)) {
            fwrite($output, gzread($input, 1048576));
        }
        gzclose($input);
        fclose($output);
        if (filesize($target) === 0) {
            throw new RuntimeException('Yedek dosyası boş veya bozuk.');
        }
    }

    private function compress(string $source, string $target): void
    {
        $partial = "{$target}.part";
        $input = fopen($source, 'rb');
        $output = gzopen($partial, 'wb6');
        while (! feof($input)) {
            gzwrite($output, fread($input, 1048576));
        }
        fclose($input);
        gzclose($output);
        rename($partial, $target);
    }

    private function prune(): void
    {
        $disk = Storage::disk('local');
        $backups = $this->backups();
        $disk->delete($backups->slice(0, max(0, $backups->count() - max(1, (int) config('backup.keep'))))->all());
    }

    /** @return Collection<int, string> eskiden yeniye */
    private function backups()
    {
        return collect(Storage::disk('local')->files(self::DIRECTORY))
            ->filter(fn (string $file) => str_ends_with($file, '.gz'))->sort()->values();
    }
}
