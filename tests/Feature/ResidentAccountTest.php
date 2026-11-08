<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentAccountTest extends TestCase
{
    use RefreshDatabase;

    private array $form = ['name' => 'Ahmet Yılmaz', 'email' => 'ahmet@ornek.com', 'password' => 'GucluSifre123', 'role' => 'user', 'member_type' => 'owner'];

    public function test_sakin_hesabi_secilen_daireye_baglanir(): void
    {
        $owner = $this->admin(super: true);
        $unit = $this->unit($this->site(manager: $owner));

        $this->actingAs($owner)->post(route('users.store'), ['unit_id' => $unit->id] + $this->form)->assertSessionHas('status');

        $resident = User::where('email', 'ahmet@ornek.com')->firstOrFail();
        $this->assertSame('user', $resident->role);
        $member = $resident->memberships()->sole();
        $this->assertSame($unit->id, $member->unit_id);
        $this->assertSame('Ahmet Yılmaz', $member->full_name);
        $this->assertNotNull($member->activated_at);
    }

    public function test_sakin_icin_daire_zorunludur(): void
    {
        $owner = $this->admin(super: true);

        $this->actingAs($owner)->post(route('users.store'), $this->form)
            ->assertSessionHasErrors(['unit_id' => 'Sakin için bir daire seçin.']);

        $this->assertFalse(User::where('email', 'ahmet@ornek.com')->exists());
    }

    public function test_panel_yoneticisi_daireye_baglanmaz(): void
    {
        $owner = $this->admin(super: true);

        $this->actingAs($owner)->post(route('users.store'), ['role' => 'admin'] + $this->form)->assertSessionHasNoErrors();

        $this->assertSame(0, User::where('email', 'ahmet@ornek.com')->firstOrFail()->memberships()->count());
    }

    public function test_dairedeki_hesapsiz_uye_yeni_hesaba_baglanir(): void
    {
        $owner = $this->admin(super: true);
        $site = $this->site(manager: $owner);
        $unit = $this->unit($site);
        $member = $site->members()->create(['unit_id' => $unit->id, 'type' => 'owner', 'full_name' => 'Kayıtlı Sakin']);

        $this->actingAs($owner)->post(route('users.store'), ['unit_id' => $unit->id] + $this->form)->assertSessionHas('status');

        $this->assertSame(1, Member::count());
        $this->assertSame(User::where('email', 'ahmet@ornek.com')->value('id'), $member->fresh()->user_id);
    }

    public function test_hesabi_olan_uyenin_yerine_yeni_hesap_acilmaz(): void
    {
        $owner = $this->admin(super: true);
        $site = $this->site(manager: $owner);
        $unit = $this->unit($site);
        $existing = User::factory()->create();
        $site->members()->create(['unit_id' => $unit->id, 'user_id' => $existing->id, 'type' => 'owner', 'full_name' => 'Kayıtlı Sakin']);

        $this->actingAs($owner)->post(route('users.store'), ['unit_id' => $unit->id] + $this->form)->assertSessionHas('error');

        $this->assertFalse(User::where('email', 'ahmet@ornek.com')->exists());
        $this->assertSame(1, Member::count());
    }

    public function test_liste_sakinin_konut_ve_dairesini_gosterir(): void
    {
        $owner = $this->admin(super: true);
        $site = $this->site(manager: $owner);
        $resident = User::factory()->create();
        $site->members()->create(['unit_id' => $this->unit($site, 1)->id, 'user_id' => $resident->id, 'type' => 'owner', 'full_name' => $resident->name]);

        $this->actingAs($owner)->get(route('users.index'))->assertOk()->assertSee('Test Sitesi · A-2');
    }

    public function test_yonetici_baska_sitedeki_dairesini_gormez(): void
    {
        $manager = $this->admin();
        $ownSite = $this->site(manager: $manager);
        $otherSite = $this->site();
        $otherSite->update(['name' => 'Gizli Site']);
        $resident = User::factory()->create();
        foreach ([$ownSite, $otherSite] as $site) {
            $site->members()->create(['unit_id' => $this->unit($site)->id, 'user_id' => $resident->id, 'type' => 'owner', 'full_name' => $resident->name]);
        }

        $this->actingAs($manager)->get(route('users.index'))->assertOk()
            ->assertSee('Test Sitesi · A-1')
            ->assertDontSee('Gizli Site');
    }

    public function test_sakin_hesabini_yalnizca_sirket_sahibi_acabilir(): void
    {
        $manager = $this->admin();
        $unit = $this->unit($this->site(manager: $manager));

        $this->actingAs($manager)->post(route('users.store'), ['unit_id' => $unit->id] + $this->form)->assertForbidden();

        $this->assertFalse(User::where('email', 'ahmet@ornek.com')->exists());
    }
}
