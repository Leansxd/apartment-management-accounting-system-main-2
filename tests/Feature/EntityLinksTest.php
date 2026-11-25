<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Debt;
use App\Models\Member;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityLinksTest extends TestCase
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
        $this->site = $this->site(2, $this->manager);
        $this->flat = $this->unit($this->site);
        $this->owner = $this->resident('Selin Avcı', 'owner');
        $this->site->debts()->create([
            'unit_id' => $this->flat->id, 'description' => 'Kasım aidatı', 'kind' => 'due', 'amount' => 500,
            'paid_amount' => 0, 'remaining_amount' => 500, 'due_date' => today()->subDays(5), 'status' => Debt::PENDING,
        ]);
    }

    private function resident(string $name, string $type): Member
    {
        return $this->site->members()->create(['unit_id' => $this->flat->id, 'full_name' => $name, 'type' => $type]);
    }

    private function unitHref(): string
    {
        return 'href="'.route('site.units.show', [$this->site, $this->flat]).'"';
    }

    private function memberHref(Member $member): string
    {
        return 'href="'.route('site.members.show', [$this->site, $member]).'"';
    }

    public function test_daire_detayi_sakinleri_ve_borclari_gosterir(): void
    {
        $this->actingAs($this->manager)->get(route('site.units.show', [$this->site, $this->flat]))
            ->assertOk()
            ->assertSee('Daire A-1')
            ->assertSee($this->memberHref($this->owner), false)
            ->assertSee('Kasım aidatı');
    }

    public function test_baska_sitenin_dairesi_acilamaz(): void
    {
        $other = $this->site(1, $this->manager);

        $this->actingAs($this->manager)->get(route('site.units.show', [$other, $this->flat]))->assertNotFound();
    }

    public function test_yetkisiz_yonetici_daire_detayini_goremez(): void
    {
        $this->actingAs($this->admin())->get(route('site.units.show', [$this->site, $this->flat]))->assertForbidden();
    }

    public function test_panel_sayfalarinda_daire_ve_sakin_baglantilari_vardir(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('site.debts.index', $this->site))->assertSee($this->unitHref(), false);
        $this->get(route('site.units.index', $this->site))->assertSee($this->memberHref($this->owner), false);
        $this->get(route('site.accounting.aging', $this->site))
            ->assertSee($this->unitHref(), false)
            ->assertSee($this->memberHref($this->owner), false);
    }

    public function test_birden_cok_sakin_virgulle_ayrilmis_baglanti_olur(): void
    {
        $tenant = $this->resident('Kaya Mali Müşavirlik', 'tenant');

        $response = $this->actingAs($this->manager)->get(route('site.collections.index', [$this->site, 'unit_id' => $this->flat->id]));

        $response->assertSee('Selin Avcı</a>, <a '.$this->memberHref($tenant), false);
    }

    public function test_rapor_hucreleri_baglantilidir_csv_duz_kalir(): void
    {
        $this->actingAs($this->manager);
        $url = route('site.reports.show', [$this->site, 'debts']);

        $this->get($url)->assertSee($this->unitHref(), false)->assertSee($this->memberHref($this->owner), false);

        $xml = $this->spreadsheetXml($this->get($url.'?format=xlsx'));
        $this->assertStringContainsString('>A-1</t>', $xml);
        $this->assertStringContainsString('>Selin Avcı</t>', $xml);
        $this->assertStringNotContainsString('href', $xml);
    }

    public function test_gecmise_tasinan_sakin_duz_metin_yazilir(): void
    {
        $this->owner->delete();

        $this->actingAs($this->manager)->get(route('site.members.history', $this->site))
            ->assertSee('Selin Avcı')
            ->assertDontSee($this->memberHref($this->owner), false);
    }

    public function test_kullanici_baglantisi_yalnizca_super_admine_verilir(): void
    {
        $colleague = $this->admin();
        $this->site->managers()->attach($colleague);
        $href = 'href="'.route('users.show', $colleague).'"';

        $this->actingAs($this->manager)->get(route('site.settings', $this->site))->assertDontSee($href, false);
        $this->actingAs($this->admin(super: true))->get(route('site.settings', $this->site))->assertSee($href, false);
    }
}
