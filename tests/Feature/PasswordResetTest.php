<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'YeniGucluSifre123';

    private const GENERIC_STATUS = 'Bu e-posta adresi kayıtlıysa şifre sıfırlama bağlantısı gönderildi.';

    public function test_sifirlama_baglantisi_istegin_host_basligini_degil_uygulama_adresini_kullanir(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('http://evil.test/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            return str_starts_with($notification->toMail($user)->actionUrl, config('app.url').'/reset-password/');
        });
    }

    public function test_giris_ekrani_sifremi_unuttum_baglantisini_gosterir(): void
    {
        User::factory()->create();

        $this->get('/login')->assertOk()
            ->assertSee(route('password.request'), false)
            ->assertSee('Giriş yap')
            ->assertDontSee('Welcome back')
            ->assertDontSee('Sarah Johnson');
    }

    public function test_kayitli_kullaniciya_sifirlama_baglantisi_gider(): void
    {
        $user = User::factory()->create();
        Notification::fake();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status', self::GENERIC_STATUS);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_kayitsiz_e_posta_ayni_yaniti_alir_ve_baglanti_gitmez(): void
    {
        User::factory()->create();
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'yok@ornek.com'])
            ->assertSessionHas('status', self::GENERIC_STATUS)
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_gecersiz_e_posta_reddedilir(): void
    {
        User::factory()->create();

        $this->post(route('password.email'), ['email' => 'gecersiz'])->assertSessionHasErrors('email');
    }

    public function test_cok_fazla_istek_engellenir(): void
    {
        User::factory()->create();
        Notification::fake();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('password.email'), ['email' => 'yok@ornek.com'])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'yok@ornek.com'])->assertTooManyRequests();
    }

    public function test_gecerli_baglanti_sifreyi_degistirir_ve_eski_oturumlari_kapatir(): void
    {
        $user = User::factory()->create(['remember_token' => 'eski-token']);
        $user->createToken('mobil');

        $this->post(route('password.update'), $this->resetForm($user))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertNotSame('eski-token', $user->remember_token);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_baglanti_ikinci_kez_kullanilamaz(): void
    {
        $user = User::factory()->create();
        $form = $this->resetForm($user);

        $this->post(route('password.update'), $form);
        $this->post(route('password.update'), ['password' => 'BaskaSifre123', 'password_confirmation' => 'BaskaSifre123'] + $form)
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
    }

    public function test_gecersiz_baglanti_sifreyi_degistirmez(): void
    {
        $user = User::factory()->create();
        $previousHash = $user->password;

        $this->post(route('password.update'), ['token' => 'sahte'] + $this->resetForm($user))
            ->assertSessionHasErrors(['email' => 'Bu sıfırlama bağlantısı geçersiz veya süresi dolmuş.']);

        $this->assertSame($previousHash, $user->fresh()->password);
    }

    public function test_kisa_veya_eslesmeyen_sifre_reddedilir(): void
    {
        $user = User::factory()->create();
        $previousHash = $user->password;

        $this->post(route('password.update'), ['password' => 'kisa', 'password_confirmation' => 'kisa'] + $this->resetForm($user))
            ->assertSessionHasErrors('password');
        $this->post(route('password.update'), ['password_confirmation' => 'farkli-sifre'] + $this->resetForm($user))
            ->assertSessionHasErrors('password');

        $this->assertSame($previousHash, $user->fresh()->password);
    }

    /** @return array{token:string, email:string, password:string, password_confirmation:string} */
    private function resetForm(User $user): array
    {
        return [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }
}
