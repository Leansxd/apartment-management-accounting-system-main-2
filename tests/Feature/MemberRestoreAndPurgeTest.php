<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Member;
use App\Models\Unit;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Geçmişe taşınan sakini geri alma ve yanlış girilen sakin kaydını kalıcı silme */
class MemberRestoreAndPurgeTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Apartment $site;

    private Unit $flat;

    private Member $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->admin();
        $this->site = $this->site(1, $this->manager);
        $this->flat = $this->unit($this->site);
        $this->owner = $this->site->members()->create(['unit_id' => $this->flat->id, 'full_name' => 'Selin Avcı', 'type' => 'owner']);
    }

    private function archive(): void
    {
        $this->actingAs($this->manager)->delete(route('site.members.destroy', [$this->site, $this->owner]), ['move_out_reason' => 'Satış']);
    }

    public function test_gecmise_tasinan_sakin_geri_alinir(): void
    {
        $this->archive();

        $this->actingAs($this->manager)->post(route('site.members.restore', [$this->site, $this->owner]))
            ->assertRedirect(route('site.members.show', [$this->site, $this->owner]));

        $member = $this->owner->fresh();
        $this->assertFalse($member->trashed());
        $this->assertNull($member->move_out_reason);
        $this->assertNotNull($member->invite_code);
    }

    public function test_dairede_ayni_turde_sakin_varsa_geri_alinamaz(): void
    {
        $this->archive();
        $this->site->members()->create(['unit_id' => $this->flat->id, 'full_name' => 'Yeni Malik', 'type' => 'owner']);

        $this->actingAs($this->manager)->post(route('site.members.restore', [$this->site, $this->owner]))->assertSessionHas('error');

        $this->assertTrue($this->owner->fresh()->trashed());
    }

    public function test_yanlis_kayit_kalici_silinir(): void
    {
        $this->actingAs($this->manager)->delete(route('site.members.purge', [$this->site, $this->owner]))
            ->assertRedirect(route('site.members.index', $this->site));

        $this->assertNull(Member::withTrashed()->find($this->owner->id));
    }

    public function test_gecmisteki_kayit_da_kalici_silinir(): void
    {
        $this->archive();

        $this->actingAs($this->manager)->delete(route('site.members.purge', [$this->site, $this->owner]))
            ->assertRedirect(route('site.members.history', $this->site));

        $this->assertNull(Member::withTrashed()->find($this->owner->id));
    }

    public function test_tahsilatta_odeyen_olarak_gecen_sakin_kalici_silinemez(): void
    {
        app(FinanceService::class)->collectParts($this->flat, [['cashbox' => $this->cashbox($this->site), 'amount' => 10000]], payer: $this->owner);

        $this->actingAs($this->manager)->delete(route('site.members.purge', [$this->site, $this->owner]))->assertSessionHas('error');

        $this->assertNotNull($this->owner->fresh());
    }

    public function test_baska_sitenin_sakini_geri_alinamaz_silinemez(): void
    {
        $this->archive();
        $other = $this->site(1, $this->manager);

        $this->actingAs($this->manager)->post(route('site.members.restore', [$other, $this->owner]))->assertNotFound();
        $this->actingAs($this->manager)->delete(route('site.members.purge', [$other, $this->owner]))->assertNotFound();
    }

    public function test_izleyici_sakin_silemez(): void
    {
        $viewer = $this->admin();
        $this->site->managers()->attach($viewer, ['role' => 'viewer']);

        $this->actingAs($viewer)->delete(route('site.members.purge', [$this->site, $this->owner]))->assertForbidden();
        $this->assertNotNull($this->owner->fresh());
    }
}
