<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    private array $form = [
        'mode' => 'clean', 'db_connection' => 'sqlite', 'name' => 'Ayşe Yönetici', 'email' => 'ayse@ornek.com',
        'password' => 'GucluSifre123', 'password_confirmation' => 'GucluSifre123',
        'site_name' => 'Çınar Sitesi', 'site_code' => 'cnr', 'site_blocks' => 'A,B',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped('Web kurulum sihirbazı (/setup) geçici olarak kapalı.');
        Cache::store('file')->forget('setup-login');
    }

    public function test_kurulum_yapilmadiysa_siteye_giren_sihirbaza_yonlenir(): void
    {
        $this->get('/login')->assertRedirect('/setup');
        $this->get('/')->assertRedirect('/setup');
        $this->get('/setup')->assertOk()->assertSee('Kurulum Sihirbazı')->assertSee('data-tour-step', false);
    }

    public function test_sihirbaz_yonetici_ve_site_acar_ve_panele_alir(): void
    {
        $finish = $this->post('/setup', $this->form)->assertSessionHasNoErrors()->headers->get('Location');

        $admin = User::where('email', 'ayse@ornek.com')->first();
        $this->assertTrue($admin->is_super_admin);
        $this->assertSame($admin->id, Apartment::where('code', 'CNR')->first()->owner_id);
        $this->assertSame(2, Apartment::first()->blocks()->count());

        $this->get($finish)->assertRedirect(route('dashboard', ['tur' => 1]));
        $this->assertAuthenticatedAs($admin);

        auth()->logout();
        $this->get($finish)->assertForbidden();
        $this->assertGuest();
    }

    public function test_kurulum_bitince_sihirbaz_kapanir(): void
    {
        $this->post('/setup', $this->form);

        $this->get('/setup')->assertRedirect('/login');
        $this->post('/setup', ['email' => 'saldirgan@ornek.com'] + $this->form)->assertRedirect('/login');
        $this->assertSame(1, User::count());
    }

    public function test_veritabani_arizasi_kurulumu_yeniden_acmaz(): void
    {
        $this->post('/setup', $this->form);
        User::query()->delete();

        $this->get('/setup')->assertRedirect('/login');
    }

    public function test_imzasiz_baglanti_giris_yaptirmaz(): void
    {
        $this->post('/setup', $this->form);
        $admin = User::first();

        $this->get('/setup/tamamla/'.$admin->id)->assertForbidden();
        $this->assertGuest();
    }

    public function test_gecersiz_form_hicbir_sey_olusturmaz(): void
    {
        $this->post('/setup', ['password_confirmation' => 'farkli'] + $this->form)->assertSessionHasErrors('password');
        $this->post('/setup', ['email' => 'gecersiz'] + $this->form)->assertSessionHasErrors('email');
        $this->assertSame(0, User::count());
    }

    public function test_kurulum_kodu_tanimliysa_zorunludur(): void
    {
        config(['setup.token' => 'gizli-kod']);

        $this->post('/setup', $this->form)->assertSessionHasErrors('setup_token');
        $this->assertSame(0, User::count());

        $this->post('/setup', ['setup_token' => 'gizli-kod'] + $this->form)->assertSessionHasNoErrors();
        $this->assertSame(1, User::count());
    }

    public function test_demo_canli_ortamda_secilemez(): void
    {
        $this->app['env'] = 'production';

        $this->withSession(['_token' => 'test'])
            ->post('/setup', ['_token' => 'test', 'mode' => 'demo'] + $this->form)
            ->assertSessionHasErrors('mode');
        $this->assertSame(0, User::count());
    }

    public function test_demo_kurulum_ornek_veriyi_yukler(): void
    {
        $this->post('/setup', ['mode' => 'demo', 'db_connection' => 'sqlite'])->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'admin@demo.test')->exists());
        $this->assertGreaterThan(0, Apartment::count());
    }
}
