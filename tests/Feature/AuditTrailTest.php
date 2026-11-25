<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function member(Apartment $site, array $attributes = []): Member
    {
        return $site->members()->create($attributes + [
            'unit_id' => $this->unit($site)->id, 'full_name' => 'Eski Ad', 'invite_code' => Member::generateInviteCode('TST', 'A1'),
        ]);
    }

    private function lastLog(string $action): AuditLog
    {
        return AuditLog::where('action', $action)->latest('id')->firstOrFail();
    }

    public function test_kayit_degisikligi_kullanici_site_ve_eski_yeni_degerle_yazilir(): void
    {
        $manager = $this->admin();
        $site = $this->site(1, $manager);
        $this->actingAs($manager);
        $member = $this->member($site);

        $member->update(['full_name' => 'Yeni Ad']);

        $log = $this->lastLog('UPDATE_MEMBER');
        $this->assertSame($manager->id, $log->user_id);
        $this->assertSame($site->id, $log->apartment_id);
        $this->assertSame('MEMBERS', $log->module);
        $this->assertSame("member:{$member->id}", $log->reference);
        $this->assertSame(['old' => 'Eski Ad', 'new' => 'Yeni Ad'], $log->data['changes']['full_name']);
    }

    public function test_silinen_kaydin_degerleri_saklanir(): void
    {
        $manager = $this->admin();
        $site = $this->site(1, $manager);
        $this->actingAs($manager);
        $member = $this->member($site);

        $member->delete();

        $this->assertSame('Eski Ad', $this->lastLog('DELETE_MEMBER')->data['values']['full_name']);
    }

    public function test_sifre_ve_kimlik_bilgileri_maskelenir(): void
    {
        $manager = $this->admin();
        $site = $this->site(1, $manager);
        $this->actingAs($manager);
        $member = $this->member($site, ['id_number' => '12345678901']);
        $member->update(['id_number' => '10987654321']);
        $manager->update(['password' => 'yeni-sifre-123']);

        $this->assertSame('***', $this->lastLog('CREATE_MEMBER')->data['values']['id_number']);
        $this->assertSame('***', $this->lastLog('CREATE_MEMBER')->data['values']['invite_code']);
        $this->assertSame(['old' => '***', 'new' => '***'], $this->lastLog('UPDATE_MEMBER')->data['changes']['id_number']);
        $this->assertSame(['old' => '***', 'new' => '***'], $this->lastLog('UPDATE_USER')->data['changes']['password']);

        $stored = AuditLog::pluck('data')->toJson();
        $this->assertStringNotContainsString('12345678901', $stored);
        $this->assertStringNotContainsString($manager->getRawOriginal('password'), $stored);
        $this->assertStringNotContainsString($member->getRawOriginal('invite_code'), $stored);
    }

    public function test_sistem_alanlari_tek_basina_kayit_olusturmaz(): void
    {
        $manager = $this->admin();
        $site = $this->site(1, $manager);
        $before = AuditLog::count();

        $manager->forceFill(['last_login_at' => now()])->save();
        $this->cashbox($site)->update(['balance' => 500]);

        $this->assertSame($before, AuditLog::count());
    }

    public function test_demo_verisi_yuklenirken_otomatik_kayit_kapatilabilir(): void
    {
        config(['audit.record_model_changes' => false]);
        $before = AuditLog::count();

        $this->site(1);

        $this->assertSame($before, AuditLog::count());
    }

    public function test_site_silinse_de_kaydi_kalir(): void
    {
        $manager = $this->admin();
        $site = $this->site(1, $manager);
        $this->actingAs($manager);
        $id = $site->id;

        $site->delete();

        $log = $this->lastLog('DELETE_APARTMENT');
        $this->assertNull($log->apartment_id);
        $this->assertSame("apartment:{$id}", $log->reference);
    }

    public function test_kullanici_kendi_hesabini_silerse_kayit_yazilir(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        $user->delete();

        $this->assertNull($this->lastLog('DELETE_USER')->user_id);
    }

    public function test_hatali_giris_ip_ve_tarayiciyla_kaydedilir_sifre_yazilmaz(): void
    {
        $user = User::factory()->create(['email' => 'hedef@test.com']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->withHeader('User-Agent', 'TestTarayici/1.0')
            ->post('/login', ['email' => 'hedef@test.com', 'password' => 'yanlis-deneme-sifresi'])->assertSessionHasErrors('email');

        $log = $this->lastLog('LOGIN_FAILED');
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('203.0.113.7', $log->ip_address);
        $this->assertSame('TestTarayici/1.0', $log->user_agent);
        $this->assertStringNotContainsString('yanlis-deneme-sifresi', json_encode($log->data));
    }

    public function test_olmayan_hesaba_giris_denemesi_de_kaydedilir(): void
    {
        $this->post('/login', ['email' => 'yok@test.com', 'password' => 'x'])->assertSessionHasErrors('email');

        $log = $this->lastLog('LOGIN_FAILED');
        $this->assertNull($log->user_id);
        $this->assertSame('yok@test.com', $log->data['email']);
    }

    public function test_basarili_giris_ve_cikis_kaydedilir(): void
    {
        $user = $this->admin();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertSame($user->id, $this->lastLog('LOGIN')->user_id);

        $this->post('/logout')->assertRedirect();
        $this->assertSame($user->id, $this->lastLog('LOGOUT')->user_id);
    }

    public function test_api_girisleri_kaydedilir(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'yanlis', 'device_name' => 'telefon'])->assertUnprocessable();
        $this->assertSame('api', $this->lastLog('LOGIN_FAILED')->data['channel']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'telefon'])->assertOk();
        $this->assertSame('telefon', $this->lastLog('LOGIN')->data['device']);
    }

    public function test_yonetici_rolu_degisikligi_eski_yeni_rolle_kaydedilir(): void
    {
        $owner = $this->admin();
        $site = $this->site(1, $owner);
        $other = $this->admin();
        $site->managers()->attach($other, ['role' => 'viewer']);

        $this->actingAs($owner)->patch(route('site.managers.role', [$site, $other]), ['role' => 'accountant'])->assertRedirect();

        $log = $this->lastLog('UPDATE_MANAGER_ROLE');
        $this->assertSame($site->id, $log->apartment_id);
        $this->assertSame(['old' => 'viewer', 'new' => 'accountant'], $log->data['changes']['role']);
    }

    public function test_site_kayit_sayfasi_degisikligi_ve_ipyi_gosterir(): void
    {
        $manager = $this->admin();
        $site = $this->site(1, $manager);
        $this->actingAs($manager);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);
        AuditLog::create([
            'apartment_id' => $site->id, 'user_id' => $manager->id, 'action' => 'UPDATE_MEMBER', 'module' => 'MEMBERS',
            'data' => ['changes' => ['full_name' => ['old' => '<b>Eski</b>', 'new' => 'Yeni']]], 'ip_address' => '203.0.113.9',
        ]);

        $this->get(route('site.audit', $site))->assertOk()
            ->assertSee('203.0.113.9')->assertSee('&lt;b&gt;Eski&lt;/b&gt; → Yeni', false);
    }

    public function test_sistem_kayitlari_yalnizca_super_admine_acik(): void
    {
        $user = User::factory()->create(['email' => 'gorunen@test.com']);
        AuditLog::create(['user_id' => $user->id, 'action' => 'LOGIN_FAILED', 'module' => 'AUTH', 'data' => ['email' => 'gorunen@test.com']]);

        $this->actingAs($this->admin())->get(route('system.audit'))->assertForbidden();
        $this->actingAs($this->admin(true))->get(route('system.audit'))->assertOk()->assertSee('gorunen@test.com');
    }

    public function test_sifre_hashi_kaydin_disinda_dogrulanabilir_kalir(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        $user->update(['password' => 'yeni-sifre-123']);

        $this->assertTrue(Hash::check('yeni-sifre-123', $user->fresh()->password));
    }
}
