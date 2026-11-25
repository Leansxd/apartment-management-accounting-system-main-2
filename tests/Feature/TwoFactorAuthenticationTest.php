<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    private const RECOVERY_CODE = 'ABC123-DEF456';

    private function adminWithTwoFactor(): User
    {
        $admin = $this->admin()->fresh();
        $admin->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_recovery_codes' => [self::RECOVERY_CODE, 'FFF000-AAA111'], 'two_factor_confirmed_at' => now()])->save();

        return $admin->fresh();
    }

    private function currentCode(string $secret = self::SECRET): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    private function submitPassword(User $user, bool $remember = false)
    {
        return $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => $remember ? 1 : 0]);
    }

    public function test_acik_hesapta_dogru_sifre_koda_yonlendirir_ve_oturum_acmaz(): void
    {
        $this->submitPassword($this->adminWithTwoFactor())->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_yanlis_sifre_kod_adimina_gecmez(): void
    {
        $user = $this->adminWithTwoFactor();

        $this->post('/login', ['email' => $user->email, 'password' => 'yanlis'])->assertSessionHasErrors('email');

        $this->assertFalse(session()->has('two_factor'));
    }

    public function test_kapali_hesap_dogrudan_girer(): void
    {
        $admin = $this->admin();

        $this->submitPassword($admin)->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_onaylanmamis_kurulum_giriste_kod_istemez(): void
    {
        $admin = $this->admin()->fresh();
        $admin->forceFill(['two_factor_secret' => self::SECRET])->save();

        $this->submitPassword($admin)->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_dogru_kod_girisi_tamamlar_ve_beni_hatirlayi_korur(): void
    {
        $user = $this->adminWithTwoFactor();
        $this->submitPassword($user, true);

        $response = $this->post(route('two-factor.store'), ['code' => $this->currentCode()]);

        $response->assertRedirect(route('dashboard'))->assertCookie(Auth::guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
        $this->assertFalse(session()->has('two_factor'));
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_yanlis_kod_giris_yapmaz(): void
    {
        $this->submitPassword($this->adminWithTwoFactor());

        $this->post(route('two-factor.store'), ['code' => '000000'])->assertSessionHasErrors(['code' => 'Doğrulama kodu hatalı.']);

        $this->assertGuest();
    }

    public function test_ayni_kod_ikinci_kez_kullanilamaz(): void
    {
        $user = $this->adminWithTwoFactor();
        $code = $this->currentCode();
        $this->submitPassword($user);
        $this->post(route('two-factor.store'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        $this->submitPassword($user);
        $this->post(route('two-factor.store'), ['code' => $code])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_kurtarma_kodu_bir_kez_gecerlidir(): void
    {
        $user = $this->adminWithTwoFactor();
        $this->submitPassword($user);
        $this->post(route('two-factor.store'), ['code' => strtolower(self::RECOVERY_CODE)])
            ->assertRedirect(route('dashboard'))->assertSessionHas('status', 'Kurtarma kodu kullanıldı, 1 kod kaldı.');
        $this->assertSame(['FFF000-AAA111'], $user->fresh()->two_factor_recovery_codes);
        $this->post(route('logout'));

        $this->submitPassword($user);
        $this->post(route('two-factor.store'), ['code' => self::RECOVERY_CODE])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_suresi_dolan_dogrulama_girise_doner(): void
    {
        $user = $this->adminWithTwoFactor();
        $this->submitPassword($user);
        $this->travel(6)->minutes();

        $this->post(route('two-factor.store'), ['code' => $this->currentCode()])->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_sifre_adimi_olmadan_kod_ekrani_acilmaz(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    }

    public function test_kod_denemesi_dakikada_bes_ile_sinirlidir(): void
    {
        $this->submitPassword($this->adminWithTwoFactor());

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('two-factor.store'), ['code' => '000000']);
        }

        $this->post(route('two-factor.store'), ['code' => $this->currentCode()])->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_kurulum_sifre_ister(): void
    {
        $admin = $this->admin()->fresh();

        $this->actingAs($admin)->post(route('account.two-factor.store'), ['current_password' => 'yanlis'])
            ->assertSessionHasErrors(['current_password' => 'Mevcut şifreniz hatalı.']);

        $this->assertNull($admin->fresh()->two_factor_secret);
    }

    public function test_kurulum_qr_gosterir_ve_dogru_kodla_acilir(): void
    {
        $admin = $this->admin()->fresh();

        $this->actingAs($admin)->post(route('account.two-factor.store'), ['current_password' => 'password'])->assertRedirect(route('account.two-factor'));
        $admin->refresh();
        $this->actingAs($admin)->get(route('account.two-factor'))->assertOk()->assertSee('<svg', false)->assertSee($admin->two_factor_secret);

        $this->actingAs($admin)->post(route('account.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());

        $response = $this->actingAs($admin)->post(route('account.two-factor.confirm'), ['code' => $this->currentCode($admin->two_factor_secret)]);

        $admin->refresh();
        $this->assertTrue($admin->hasTwoFactorEnabled());
        $this->assertCount(8, $admin->two_factor_recovery_codes);
        $response->assertSessionHas('recoveryCodes', $admin->two_factor_recovery_codes);
    }

    public function test_acikken_gizli_anahtar_ekranda_gosterilmez(): void
    {
        $user = $this->adminWithTwoFactor();

        $this->actingAs($user)->get(route('account.two-factor'))->assertOk()->assertDontSee(self::SECRET)->assertDontSee(self::RECOVERY_CODE)->assertSee('2 kurtarma kodunuz kaldı');
    }

    public function test_gizli_veriler_veritabaninda_sifreli_ve_ciktida_gizlidir(): void
    {
        $user = $this->adminWithTwoFactor();
        $row = DB::table('users')->find($user->id);

        $this->assertStringNotContainsString(self::SECRET, $row->two_factor_secret);
        $this->assertStringNotContainsString(self::RECOVERY_CODE, $row->two_factor_recovery_codes);
        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $user->toArray());
    }

    public function test_kapatma_sifre_ister_ve_verileri_siler(): void
    {
        $user = $this->adminWithTwoFactor();

        $this->actingAs($user)->delete(route('account.two-factor.destroy'), ['current_password' => 'yanlis'])->assertSessionHasErrors('current_password');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->actingAs($user)->delete(route('account.two-factor.destroy'), ['current_password' => 'password'])->assertSessionHas('status');

        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
    }

    public function test_kurtarma_kodlarini_yenilemek_eskileri_gecersiz_yapar(): void
    {
        $user = $this->adminWithTwoFactor();

        $this->actingAs($user)->post(route('account.two-factor.codes'), ['current_password' => 'password'])->assertSessionHas('recoveryCodes');

        $codes = $user->fresh()->two_factor_recovery_codes;
        $this->assertCount(8, $codes);
        $this->assertNotContains(self::RECOVERY_CODE, $codes);
    }

    public function test_ziyaretci_ve_sakin_ayar_ekranina_giremez(): void
    {
        $this->get(route('account.two-factor'))->assertRedirect(route('login'));

        $resident = User::factory()->create()->fresh();
        $this->actingAs($resident)->get(route('account.two-factor'))->assertRedirect(route('login'));
    }

    public function test_sifre_sifirlama_kod_adimini_atlatmaz(): void
    {
        $user = $this->adminWithTwoFactor();
        $user->forceFill(['password' => Hash::make('YeniSifre123')])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'YeniSifre123'])->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
    }

    public function test_sunucu_komutu_hesabin_dogrulamasini_kapatir(): void
    {
        $user = $this->adminWithTwoFactor();

        $this->artisan('app:disable-two-factor', ['email' => $user->email])->assertSuccessful();
        $this->artisan('app:disable-two-factor', ['email' => 'yok@ornek.com'])->assertFailed();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_cikis_denetimi_kapali_yoneticide_uyarir(): void
    {
        Storage::fake('local');
        $this->admin();

        $this->artisan('app:check-production')->expectsOutputToContain('Tüm yöneticiler iki adımlı doğrulamayı açmalı.');
    }
}
