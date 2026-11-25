<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProductionCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionCheckServiceTest extends TestCase
{
    use RefreshDatabase;

    private function checkStatus(string $label): string
    {
        return collect(app(ProductionCheckService::class)->results())->firstWhere('label', $label)['status'];
    }

    public function test_acik_hata_ekrani_engeldir(): void
    {
        Storage::fake('local');
        config(['app.debug' => true]);
        $this->assertSame('fail', $this->checkStatus('Hata ekranı'));

        config(['app.debug' => false]);
        $this->assertSame('ok', $this->checkStatus('Hata ekranı'));
    }

    public function test_demo_hesabi_engeldir(): void
    {
        Storage::fake('local');
        $this->assertSame('ok', $this->checkStatus('Demo hesapları'));

        User::factory()->create(['email' => 'admin@demo.test']);

        $this->assertSame('fail', $this->checkStatus('Demo hesapları'));
    }

    public function test_log_e_posta_surucusu_uyari_verir(): void
    {
        Storage::fake('local');
        config(['mail.default' => 'log']);
        $this->assertSame('warn', $this->checkStatus('E-posta sunucusu'));

        config(['mail.default' => 'smtp']);
        $this->assertSame('ok', $this->checkStatus('E-posta sunucusu'));
    }

    public function test_yedek_yoksa_ve_eskiyse_uyari_verir(): void
    {
        Storage::fake('local');
        $this->assertSame('warn', $this->checkStatus('Son yedek'));

        Storage::disk('local')->put('backups/backup-2026-01-01-030000-mysql.gz', 'x');
        $this->assertSame('ok', $this->checkStatus('Son yedek'));

        $this->travel(27)->hours();
        $this->assertSame('warn', $this->checkStatus('Son yedek'));
    }

    public function test_komut_engel_varsa_hata_koduyla_biter(): void
    {
        Storage::fake('local');
        config(['app.debug' => true]);

        $this->artisan('app:check-production')->expectsOutputToContain('Hata ekranı')->assertFailed();
    }

    public function test_guvenilen_proxy_gercek_istemci_adresini_ve_https_yi_gecirir(): void
    {
        config(['trustedproxy.proxies' => '*']);
        Route::get('/_proxy-test', fn (Request $request) => [$request->ip(), $request->secure()]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
            ->get('/_proxy-test')->assertExactJson(['203.0.113.9', true]);
    }

    public function test_proxy_ayari_yoksa_sahte_baslik_dikkate_alinmaz(): void
    {
        config(['trustedproxy.proxies' => null]);
        Route::get('/_proxy-test', fn (Request $request) => [$request->ip(), $request->secure()]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
            ->get('/_proxy-test')->assertExactJson(['10.0.0.1', false]);
    }
}
