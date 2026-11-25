<?php

namespace Tests\Feature;

use App\Services\DatabaseBackupService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DatabaseRestoreTest extends TestCase
{
    private const SECRET = 'Gizli"Sifre';

    private const BACKUP = 'backup-2026-01-01-030000-mysql.gz';

    /** @var array<int, array{command: array<int, string>, input: string|null}> */
    private array $ran = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['backup.mysqldump' => '/opt/db/mysqldump', 'backup.mysql' => null, 'database.default' => 'backup_mysql', 'database.connections.backup_mysql' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'memoshome', 'username' => 'root', 'password' => self::SECRET,
        ]]);
    }

    private function expectMigrations(int $exitCode = 0): void
    {
        Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true])->andReturn($exitCode);
        Artisan::shouldReceive('output')->andReturn('Tablo hatası');
    }

    private function storeBackup(string $name = self::BACKUP, string $sql = 'INSERT INTO yedek;'): void
    {
        Storage::disk('local')->put("backups/{$name}", gzencode($sql));
    }

    private function fakeDatabaseTools(int $clientExitCode = 0): void
    {
        Process::fake(function (PendingProcess $process) use ($clientExitCode) {
            $file = collect($process->command)->first(fn ($arg) => str_starts_with($arg, '--result-file='));
            if ($file) {
                file_put_contents(substr($file, strlen('--result-file=')), '-- mevcut durum');
                $this->ran[] = ['command' => $process->command, 'input' => null];

                return Process::result();
            }
            $this->ran[] = ['command' => $process->command, 'input' => stream_get_contents($process->input)];

            return Process::result(errorOutput: $clientExitCode ? 'Bozuk dosya' : '', exitCode: $clientExitCode);
        });
    }

    public function test_mysql_yedegi_guvenlik_yedeginden_sonra_yuklenir(): void
    {
        $this->expectMigrations();
        $this->storeBackup();
        $this->fakeDatabaseTools();

        $safetyBackup = app(DatabaseBackupService::class)->restore(self::BACKUP);

        $this->assertCount(2, $this->ran);
        $this->assertSame('/opt/db/mysqldump', $this->ran[0]['command'][0]);
        $this->assertSame('/opt/db/mysql', $this->ran[1]['command'][0]);
        $this->assertSame('INSERT INTO yedek;', $this->ran[1]['input']);
        $this->assertSame('memoshome', end($this->ran[1]['command']));
        $this->assertSame('-- mevcut durum', gzdecode(Storage::disk('local')->get($safetyBackup)));
    }

    public function test_sifre_komut_satirina_yazilmaz_gecici_dosya_kalmaz(): void
    {
        $this->expectMigrations();
        $this->storeBackup();
        $this->fakeDatabaseTools();

        app(DatabaseBackupService::class)->restore(self::BACKUP);

        $this->assertStringNotContainsString('Gizli', implode(' ', $this->ran[1]['command']));
        $this->assertSame([], array_values(array_filter(Storage::disk('local')->allFiles('backups'), fn ($f) => ! str_ends_with($f, '.gz'))));
    }

    public function test_ayarlanan_mysql_istemcisi_kullanilir(): void
    {
        $this->expectMigrations();
        config(['backup.mysql' => '/ozel/mysql']);
        $this->storeBackup();
        $this->fakeDatabaseTools();

        app(DatabaseBackupService::class)->restore(self::BACKUP);

        $this->assertSame('/ozel/mysql', $this->ran[1]['command'][0]);
    }

    public function test_guvenlik_yedegi_en_eski_yedegi_silse_de_secilen_yuklenir(): void
    {
        $this->expectMigrations();
        config(['backup.keep' => 1]);
        $this->storeBackup();
        $this->fakeDatabaseTools();

        app(DatabaseBackupService::class)->restore(self::BACKUP);

        $this->assertSame('INSERT INTO yedek;', $this->ran[1]['input']);
    }

    public function test_tablolar_guncellenemezse_hata_bildirilir(): void
    {
        $this->expectMigrations(1);
        $this->storeBackup();
        $this->fakeDatabaseTools();

        $this->expectExceptionMessage('tablolar güncellenemedi: Tablo hatası');

        app(DatabaseBackupService::class)->restore(self::BACKUP);
    }

    public function test_klasor_disina_cikilamaz(): void
    {
        $this->storeBackup();
        Process::fake();

        $this->expectExceptionMessage('Yedek dosyası bulunamadı.');

        try {
            app(DatabaseBackupService::class)->restore('../../.env');
        } finally {
            Process::assertNothingRan();
        }
    }

    public function test_farkli_veritabani_turunun_yedegi_yuklenmez(): void
    {
        $name = 'backup-2026-01-01-030000-sqlite.gz';
        $this->storeBackup($name);
        Process::fake();

        $this->expectExceptionMessage('farklı bir veritabanı');

        try {
            app(DatabaseBackupService::class)->restore($name);
        } finally {
            Process::assertNothingRan();
        }
    }

    public function test_bos_yedek_yuklenmez_ve_mevcut_veriye_dokunulmaz(): void
    {
        $this->storeBackup(self::BACKUP, '');
        Process::fake();

        $this->expectExceptionMessage('boş veya bozuk');

        try {
            app(DatabaseBackupService::class)->restore(self::BACKUP);
        } finally {
            Process::assertNothingRan();
        }
    }

    public function test_yukleme_hatasi_bildirilir(): void
    {
        $this->storeBackup();
        $this->fakeDatabaseTools(1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bozuk dosya');

        app(DatabaseBackupService::class)->restore(self::BACKUP);
    }

    public function test_yedekler_yeniden_eskiye_listelenir(): void
    {
        $this->storeBackup('backup-2026-01-01-030000-mysql.gz');
        $this->storeBackup('backup-2026-02-01-030000-mysql.gz');

        $names = app(DatabaseBackupService::class)->available()->pluck('name')->all();

        $this->assertSame(['backup-2026-02-01-030000-mysql.gz', 'backup-2026-01-01-030000-mysql.gz'], $names);
    }
}
