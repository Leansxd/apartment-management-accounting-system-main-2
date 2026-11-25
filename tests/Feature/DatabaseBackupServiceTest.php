<?php

namespace Tests\Feature;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DatabaseBackupServiceTest extends TestCase
{
    private const SECRET = 'Gizli"Sifre';

    private function useSqlite(): void
    {
        config(['database.default' => 'backup_sqlite', 'database.connections.backup_sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    }

    private function useMysql(): void
    {
        config(['database.default' => 'backup_mysql', 'database.connections.backup_mysql' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'memoshome', 'username' => 'root', 'password' => self::SECRET,
        ]]);
    }

    private function fakeDump(string $content, int $exitCode = 0): void
    {
        Process::fake(function (PendingProcess $process) use ($content, $exitCode) {
            $file = collect($process->command)->first(fn ($arg) => str_starts_with($arg, '--result-file='));
            file_put_contents(substr($file, strlen('--result-file=')), $content);

            return Process::result(errorOutput: $exitCode ? 'Erisim reddedildi' : '', exitCode: $exitCode);
        });
    }

    public function test_sqlite_veritabani_sikistirilmis_yedeklenir(): void
    {
        Storage::fake('local');
        $this->useSqlite();

        $path = app(DatabaseBackupService::class)->create();

        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith('SQLite format 3', gzdecode(Storage::disk('local')->get($path)));
        $this->assertCount(1, Storage::disk('local')->allFiles('backups'));
    }

    public function test_mysql_yedegi_alinir_ve_sifre_komut_satirina_yazilmaz(): void
    {
        Storage::fake('local');
        $this->useMysql();
        $this->fakeDump('-- Dump completed');

        $path = app(DatabaseBackupService::class)->create();

        $this->assertSame('-- Dump completed', gzdecode(Storage::disk('local')->get($path)));
        Process::assertRan(fn (PendingProcess $process) => ! str_contains(implode(' ', $process->command), 'Gizli')
            && in_array('--single-transaction', $process->command, true) && end($process->command) === 'memoshome');
    }

    public function test_basarisiz_mysqldump_yarim_yedek_birakmaz_ve_komut_hata_verir(): void
    {
        Exceptions::fake([RuntimeException::class]);
        Storage::fake('local');
        $this->useMysql();
        $this->fakeDump('yarim', 2);

        $this->artisan('app:backup')->expectsOutputToContain('Erisim reddedildi')->assertFailed();

        Exceptions::assertReported(RuntimeException::class);
        $this->assertSame([], Storage::disk('local')->allFiles('backups'));
    }

    public function test_bos_yedek_kabul_edilmez(): void
    {
        Storage::fake('local');
        $this->useMysql();
        $this->fakeDump('');

        $this->expectExceptionMessage('Yedek dosyası boş oluştu.');

        app(DatabaseBackupService::class)->create();
    }

    public function test_yalnizca_son_yedekler_saklanir(): void
    {
        Storage::fake('local');
        $this->useSqlite();
        config(['backup.keep' => 2]);
        $backups = app(DatabaseBackupService::class);

        $paths = [];
        foreach (['2026-01-01 03:00:00', '2026-01-02 03:00:00', '2026-01-03 03:00:00'] as $moment) {
            $this->travelTo($moment);
            $paths[] = $backups->create();
        }

        Storage::disk('local')->assertMissing($paths[0]);
        Storage::disk('local')->assertExists($paths[1]);
        Storage::disk('local')->assertExists($paths[2]);
    }

    public function test_yedek_her_gece_zamanlanir(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'app:backup'));

        $this->assertNotNull($event);
        $this->assertSame('0 3 * * *', $event->expression);
    }
}
