<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT_PASSWORD = 'password';

    private const NEW_PASSWORD = 'YeniGucluSifre123';

    private function storedSession(string $id, User $user): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }

    public function test_giris_yapmamis_ziyaretci_giris_ekranina_yonlendirilir(): void
    {
        $this->put(route('account.password.update'), $this->payload())->assertRedirect(route('login'));
    }

    public function test_sakin_hesabi_panelden_sifre_degistiremez(): void
    {
        $resident = User::factory()->create()->fresh();

        $this->actingAs($resident)->put(route('account.password.update'), $this->payload())->assertRedirect(route('login'));

        $this->assertTrue(Hash::check(self::CURRENT_PASSWORD, $resident->fresh()->password));
    }

    public function test_panel_menusu_sifre_degistirme_ekranina_goturur(): void
    {
        $this->actingAs($this->admin())->get(route('account.password'))
            ->assertOk()
            ->assertSee('Mevcut şifre')
            ->assertSee(route('account.password.update'), false);
    }

    public function test_dogru_mevcut_sifreyle_sifre_degisir_ve_diger_oturumlar_kapanir(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->admin();
        $otherAdmin = $this->admin();
        $admin->createToken('telefon');
        $rememberToken = $admin->fresh()->remember_token;
        $this->storedSession('baska-cihaz', $admin);
        $this->storedSession('baska-kullanici', $otherAdmin);

        $this->actingAs($admin)->from(route('account.password'))->put(route('account.password.update'), $this->payload())
            ->assertRedirect(route('account.password'))
            ->assertSessionHas('status', 'Şifreniz değiştirildi.');

        $admin->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $admin->password));
        $this->assertNotSame($rememberToken, $admin->remember_token);
        $this->assertSame(0, $admin->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['id' => 'baska-cihaz']);
        $this->assertDatabaseHas('sessions', ['id' => 'baska-kullanici']);
    }

    /** @param array<string, string> $overrides */
    #[DataProvider('invalidPayloads')]
    public function test_gecersiz_girdi_sifreyi_degistirmez_ve_hatayi_gosterir(array $overrides, string $field, string $message): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('account.password.update'), $this->payload($overrides))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertTrue(Hash::check(self::CURRENT_PASSWORD, $admin->fresh()->password));
    }

    /** @return array<string, array{0: array<string, string>, 1: string, 2: string}> */
    public static function invalidPayloads(): array
    {
        return [
            'hatalı mevcut şifre' => [['current_password' => 'yanlis-sifre'], 'current_password', 'Mevcut şifreniz hatalı.'],
            'tekrar eşleşmiyor' => [['password_confirmation' => 'BaskaSifre123'], 'password', 'Yeni şifre tekrarı eşleşmiyor.'],
            'yeni şifre eskisiyle aynı' => [['password' => self::CURRENT_PASSWORD, 'password_confirmation' => self::CURRENT_PASSWORD], 'password', 'Yeni şifre mevcut şifrenizden farklı olmalıdır.'],
            'kısa şifre' => [['password' => 'kisa', 'password_confirmation' => 'kisa'], 'password', 'yeni şifre en az 8 karakter olmalıdır.'],
        ];
    }
}
