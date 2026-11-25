<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Member;
use App\Models\Unit;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SiteSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Apartment $site;

    private Unit $flat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->admin();
        $this->site = $this->site(2, $this->manager);
        $this->flat = $this->unit($this->site);
        $this->actingAs($this->manager);
    }

    private function member(Apartment $site, Unit $unit, array $attributes = []): Member
    {
        return $site->members()->create($attributes + ['unit_id' => $unit->id, 'full_name' => 'Zeynep Kaya', 'invite_code' => Member::generateInviteCode('TST', (string) $unit->id)]);
    }

    private function search(string $term): TestResponse
    {
        return $this->get(route('site.search', [$this->site, 'q' => $term]))->assertOk();
    }

    public function test_sakin_ad_telefon_ve_eposta_ile_bulunur(): void
    {
        $this->member($this->site, $this->flat, ['phone' => '05551112233', 'email' => 'zeynep@test.com']);

        $this->search('Zeynep')->assertSee('Zeynep Kaya');
        $this->search('5551112')->assertSee('Zeynep Kaya');
        $this->search('zeynep@test')->assertSee('Zeynep Kaya');
    }

    public function test_daire_blok_numara_biciminde_bulunur(): void
    {
        $this->search('A-2')->assertSee('Daireler (1)');
    }

    public function test_borc_talep_ve_gider_bulunur(): void
    {
        app(FinanceService::class)->accrue($this->flat, ['description' => 'Çatı onarımı', 'amount' => 1000, 'due_date' => '2026-01-01']);
        $this->site->tickets()->create(['subject' => 'Çatı akıyor', 'category' => 'Teknik']);
        $this->site->expenses()->create(['description' => 'Çatı ustası', 'amount' => 100, 'total_amount' => 120]);

        $this->search('Çatı')->assertSee('Çatı onarımı')->assertSee('Çatı akıyor')->assertSee('Çatı ustası');
    }

    public function test_baska_sitenin_kaydi_cikmaz(): void
    {
        $other = $this->site(1, $this->manager);
        $this->member($other, $this->unit($other), ['full_name' => 'Yabancı Sakin']);

        $this->search('Yabancı')->assertDontSee('Yabancı Sakin')->assertSee('sonuç bulunamadı');
    }

    public function test_kimlik_numarasi_aranamaz(): void
    {
        $this->member($this->site, $this->flat, ['id_number' => '12345678901']);

        $this->search('12345678901')->assertSee('sonuç bulunamadı');
    }

    public function test_joker_karakter_her_seyi_getirmez(): void
    {
        $this->member($this->site, $this->flat);

        $this->search('%%')->assertDontSee('Zeynep Kaya');
    }

    public function test_tek_karakter_arama_yapmaz(): void
    {
        $this->search('Z')->assertSee('en az 2 karakter');
    }

    public function test_arama_metni_kacirilir(): void
    {
        $this->search('<script>x</script>')->assertDontSee('<script>x</script>', false);
    }

    public function test_siteye_erisimi_olmayan_arayamaz(): void
    {
        $this->actingAs($this->admin())->get(route('site.search', [$this->site, 'q' => 'Zeynep']))->assertForbidden();
    }
}
