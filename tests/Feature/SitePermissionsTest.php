<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\SitePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $accountant;

    private User $viewer;

    private Apartment $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->admin();
        $this->site = $this->site(1, $this->owner);
        $this->accountant = $this->admin();
        $this->viewer = $this->admin();
        $this->site->managers()->attach($this->accountant, ['role' => 'accountant']);
        $this->site->managers()->attach($this->viewer, ['role' => 'viewer']);
    }

    /** @param  array<string, array<string, string>>  $overrides */
    private function grant(array $overrides): void
    {
        $matrix = SitePermissions::matrix($this->site);
        $this->site->update(['role_permissions' => array_replace_recursive($matrix, $overrides)]);
    }

    public function test_varsayilan_izinler_onceki_rol_davranisini_korur(): void
    {
        $this->actingAs($this->accountant)->post(route('site.debts.store', $this->site))->assertSessionHasErrors();
        $this->actingAs($this->accountant)->post(route('site.tasks.store', $this->site))->assertForbidden();
        $this->actingAs($this->viewer)->post(route('site.debts.store', $this->site))->assertForbidden();
        $this->actingAs($this->viewer)->get(route('site.audit', $this->site))->assertOk();
    }

    public function test_erisimi_kapatilan_modul_acilmaz_ve_menude_gorunmez(): void
    {
        $this->grant(['viewer' => ['audit' => SitePermissions::NONE]]);

        $this->actingAs($this->viewer)->get(route('site.audit', $this->site))->assertForbidden();
        $this->actingAs($this->viewer)->get(route('site.dashboard', $this->site))
            ->assertOk()
            ->assertDontSee('href="'.route('site.audit', $this->site).'"', false);
    }

    public function test_islem_izni_verilen_izleyici_kayit_gonderebilir(): void
    {
        $this->grant(['viewer' => ['tasks' => SitePermissions::WRITE]]);

        $this->actingAs($this->viewer)->post(route('site.tasks.store', $this->site))->assertSessionHasErrors();
    }

    public function test_islem_izni_kaldirilan_muhasebeci_kayit_degistiremez(): void
    {
        $this->grant(['accountant' => ['debts' => SitePermissions::READ]]);

        $this->actingAs($this->accountant)->post(route('site.debts.store', $this->site))->assertForbidden();
    }

    public function test_yetkisiz_formlar_sayfadan_cikarilir(): void
    {
        $action = 'action="'.route('site.debts.store', $this->site).'"';

        $this->actingAs($this->viewer)->get(route('site.debts.index', $this->site))->assertOk()->assertDontSee($action, false);
        $this->actingAs($this->accountant)->get(route('site.debts.index', $this->site))->assertSee($action, false);
        $this->actingAs($this->owner)->get(route('site.debts.index', $this->site))->assertSee($action, false);
    }

    public function test_acilamayan_sayfaya_baglanti_duz_metne_doner(): void
    {
        $unit = $this->unit($this->site);
        $this->site->debts()->create([
            'unit_id' => $unit->id, 'description' => 'Aidat', 'kind' => 'due', 'amount' => 100,
            'paid_amount' => 0, 'remaining_amount' => 100, 'due_date' => today(), 'status' => 'pending',
        ]);
        $this->grant(['viewer' => ['units' => SitePermissions::NONE]]);

        $this->actingAs($this->viewer)->get(route('site.debts.index', $this->site))
            ->assertSee('A-1')
            ->assertDontSee('href="'.route('site.units.show', [$this->site, $unit]).'"', false);
    }

    public function test_aramada_erisilemeyen_modul_sonuclari_gizlenir(): void
    {
        $this->site->members()->create(['unit_id' => $this->unit($this->site)->id, 'full_name' => 'Selin Avcı', 'type' => 'owner']);
        $this->grant(['viewer' => ['members' => SitePermissions::NONE]]);

        $this->actingAs($this->viewer)->get(route('site.search', [$this->site, 'q' => 'Selin']))->assertOk()->assertDontSee('Selin Avcı');
        $this->actingAs($this->owner)->get(route('site.search', [$this->site, 'q' => 'Selin']))->assertSee('Selin Avcı');
    }

    public function test_site_sahibi_izinleri_kaydeder_ve_denetim_kaydi_olusur(): void
    {
        $matrix = SitePermissions::matrix($this->site);
        $matrix['viewer']['audit'] = SitePermissions::NONE;

        $this->actingAs($this->owner)->put(route('site.settings.permissions', $this->site), ['permissions' => $matrix])
            ->assertSessionHasNoErrors();

        $this->assertSame(SitePermissions::NONE, SitePermissions::matrix($this->site->fresh())['viewer']['audit']);
        $log = AuditLog::where('action', 'UPDATE_ROLE_PERMISSIONS')->sole();
        $this->assertSame(['old' => 'read', 'new' => 'none'], $log->data['changes']['viewer.audit']);
    }

    public function test_izinleri_site_sahibi_disinda_kimse_degistiremez(): void
    {
        $manager = $this->admin();
        $this->site->managers()->attach($manager, ['role' => 'manager']);
        $payload = ['permissions' => SitePermissions::matrix($this->site)];

        $this->actingAs($manager)->put(route('site.settings.permissions', $this->site), $payload)->assertForbidden();
        $this->actingAs($this->accountant)->put(route('site.settings.permissions', $this->site), $payload)->assertForbidden();
    }

    public function test_gecersiz_izin_seviyesi_reddedilir(): void
    {
        $matrix = SitePermissions::matrix($this->site);
        $matrix['viewer']['audit'] = 'admin';

        $this->actingAs($this->owner)->put(route('site.settings.permissions', $this->site), ['permissions' => $matrix])
            ->assertSessionHasErrors('permissions.viewer.audit');
        $this->assertNull($this->site->fresh()->role_permissions);
    }
}
