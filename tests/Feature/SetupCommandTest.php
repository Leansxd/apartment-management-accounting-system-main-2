<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupCommandTest extends TestCase
{
    use RefreshDatabase;

    private array $options = [
        '--no-interaction' => true, '--name' => 'Ayşe Yönetici', '--email' => 'ayse@ornek.com',
        '--password' => 'GucluSifre123', '--site' => 'Çınar Sitesi', '--code' => 'cnr', '--blocks' => 'A,B',
    ];

    public function test_soru_sormadan_temiz_kurulum_yonetici_ve_site_acar(): void
    {
        $this->artisan('app:setup', $this->options)->assertSuccessful();

        $admin = User::where('email', 'ayse@ornek.com')->first();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->is_super_admin);

        $site = Apartment::where('code', 'CNR')->first();
        $this->assertSame($admin->id, $site->owner_id);
        $this->assertSame(2, $site->blocks()->count());
        $this->assertSame(1, $site->cashboxes()->count());
        $this->assertSame(10, $site->categories()->count());
    }

    public function test_tekrar_calistirmak_veri_olusturmaz(): void
    {
        $this->artisan('app:setup', $this->options)->assertSuccessful();
        $this->artisan('app:setup', $this->options)
            ->expectsOutputToContain('hesap ve site adımları atlandı')
            ->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame(1, Apartment::count());
    }

    public function test_soru_sorarak_kurulum(): void
    {
        $this->artisan('app:setup')
            ->expectsChoice('Kurulum türü', 'Temiz kurulum', ['Temiz kurulum', 'Demo verili kurulum'])
            ->expectsQuestion('Yönetici adı soyadı', 'Ali Veli')
            ->expectsQuestion('Yönetici e-postası', 'gecersiz')
            ->expectsQuestion('Yönetici e-postası', 'ali@ornek.com')
            ->expectsQuestion('Şifre (en az 8 karakter)', 'GucluSifre123')
            ->expectsQuestion('Şifre (tekrar)', 'GucluSifre123')
            ->expectsQuestion('İlk site adı', 'Deniz Sitesi')
            ->expectsQuestion('Site kısa kodu (ör. ABC)', 'DNZ')
            ->expectsQuestion('Bloklar (virgülle)', 'A')
            ->assertSuccessful();

        $this->assertTrue(User::where('email', 'ali@ornek.com')->where('is_super_admin', true)->exists());
        $this->assertTrue(Apartment::where('code', 'DNZ')->exists());
    }

    public function test_gecersiz_girdi_hicbir_sey_olusturmaz(): void
    {
        $this->artisan('app:setup', ['--email' => 'gecersiz'] + $this->options)->assertFailed();
        $this->assertSame(0, User::count());

        $this->artisan('app:setup', ['--password' => 'kisa'] + $this->options)->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_secenek_verilmezse_yalnizca_veritabani_hazirlanir(): void
    {
        $this->artisan('app:setup', ['--no-interaction' => true])->assertSuccessful();
        $this->assertSame(0, User::count());
    }

    public function test_demo_verisi_canli_ortamda_reddedilir(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('app:setup', ['--demo' => true, '--no-interaction' => true])->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_temiz_kurulumda_giris_ekrani_demo_bilgisi_gostermez(): void
    {
        $this->artisan('app:setup', $this->options)->assertSuccessful();

        $this->get('/login')->assertOk()->assertDontSee('admin@demo.test')->assertDontSee('data-fill-email', false);
    }

    public function test_demo_kurulumda_giris_ekrani_demo_bilgisi_gosterir(): void
    {
        $this->artisan('app:setup', ['--demo' => true, '--no-interaction' => true])->assertSuccessful();

        $this->get('/login')->assertOk()->assertSee('admin@demo.test');
    }
}
