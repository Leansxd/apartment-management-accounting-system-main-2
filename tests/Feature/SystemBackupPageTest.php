<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\DatabaseBackupService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class SystemBackupPageTest extends TestCase
{
    use RefreshDatabase;

    private const BACKUP = 'backup-2026-01-01-030000-mysql.gz';

    private function validRestore(array $overrides = []): array
    {
        return $overrides + ['backup' => self::BACKUP, 'password' => 'password', 'confirm' => 'GERİ YÜKLE'];
    }

    private function backupsMock(): MockInterface
    {
        return $this->mock(DatabaseBackupService::class);
    }

    public function test_yedek_sayfasi_listeler(): void
    {
        $this->backupsMock()->shouldReceive('available')->andReturn(collect([['name' => self::BACKUP, 'size' => 2097152, 'created_at' => CarbonImmutable::parse('2026-01-01 03:00')]]));

        $this->actingAs($this->admin(true))->get(route('system.backups'))->assertOk()->assertSee(self::BACKUP)->assertSee('2,00 MB');
    }

    public function test_normal_admin_yedeklere_erisemez(): void
    {
        $this->backupsMock()->shouldNotReceive('restore');
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('system.backups'))->assertForbidden();
        $this->actingAs($admin)->post(route('system.backups.store'))->assertForbidden();
        $this->actingAs($admin)->post(route('system.backups.restore'), $this->validRestore())->assertForbidden();
    }

    public function test_sifre_hataliysa_geri_yuklenmez(): void
    {
        $this->backupsMock()->shouldNotReceive('restore');

        $this->actingAs($this->admin(true))->post(route('system.backups.restore'), $this->validRestore(['password' => 'yanlis']))
            ->assertSessionHasErrors('password');
    }

    public function test_onay_metni_yanlissa_geri_yuklenmez(): void
    {
        $this->backupsMock()->shouldNotReceive('restore');

        $this->actingAs($this->admin(true))->post(route('system.backups.restore'), $this->validRestore(['confirm' => 'evet']))
            ->assertSessionHasErrors('confirm');
    }

    public function test_geri_yukleme_oturumu_kapatir_ve_kaydedilir(): void
    {
        $super = $this->admin(true);
        $this->backupsMock()->shouldReceive('restore')->once()->with(self::BACKUP)->andReturn('backups/guvenlik.gz');

        $this->actingAs($super)->post(route('system.backups.restore'), $this->validRestore())->assertRedirect(route('login'));

        $this->assertGuest();
        $log = AuditLog::where('action', 'RESTORE_BACKUP')->firstOrFail();
        $this->assertSame($super->email, $log->data['performed_by']);
        $this->assertSame('guvenlik.gz', $log->data['safety_backup']);
    }

    public function test_geri_yukleme_hatasi_ekranda_gosterilir_oturum_kalir(): void
    {
        $this->withoutExceptionHandling();
        $this->backupsMock()->shouldReceive('restore')->andThrow(new RuntimeException('Bozuk dosya'));

        $this->actingAs($this->admin(true))->post(route('system.backups.restore'), $this->validRestore())->assertSessionHas('error');

        $this->assertAuthenticated();
    }

    public function test_komut_yedegi_geri_yukler_ve_kaydeder(): void
    {
        $mock = $this->backupsMock();
        $mock->shouldReceive('available')->andReturn(collect([['name' => self::BACKUP]]));
        $mock->shouldReceive('restore')->once()->with(self::BACKUP)->andReturn('backups/guvenlik.gz');

        $this->artisan('app:restore', ['backup' => self::BACKUP, '--force' => true])->assertSuccessful();

        $this->assertSame('console', AuditLog::where('action', 'RESTORE_BACKUP')->firstOrFail()->data['channel']);
    }

    public function test_onay_verilmezse_komut_geri_yuklemez(): void
    {
        $mock = $this->backupsMock();
        $mock->shouldReceive('available')->andReturn(collect([['name' => self::BACKUP]]));
        $mock->shouldNotReceive('restore');

        $this->artisan('app:restore', ['backup' => self::BACKUP])
            ->expectsConfirmation(self::BACKUP.' geri yüklenecek ve mevcut veriler bu yedekle değişecek. Emin misiniz?', 'no')
            ->assertFailed();
    }
}
