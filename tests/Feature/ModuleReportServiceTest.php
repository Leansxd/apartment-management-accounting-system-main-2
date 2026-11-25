<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportController;
use App\Models\Apartment;
use App\Models\User;
use App\Services\FinanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function finance(): FinanceService
    {
        return app(FinanceService::class);
    }

    private function rows(User $manager, Apartment $apartment, string $type, array $query = []): array
    {
        return $this->actingAs($manager)->get(route('site.reports.show', [$apartment, $type, ...$query]))->assertOk()->viewData('rows')->all();
    }

    public function test_tum_raporlar_demo_verisinde_ekran_ve_csv_olarak_uretilir(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@demo.test')->first();
        $apartment = Apartment::first();

        foreach (array_keys(ReportController::TYPES) as $type) {
            $this->actingAs($admin)->get(route('site.reports.show', [$apartment, $type]))->assertOk();
            $this->actingAs($admin)->get(route('site.reports.show', [$apartment, $type, 'format' => 'xlsx']))->assertOk()->assertDownload();
        }
        $this->actingAs($admin)->get(route('site.reports.index', $apartment))->assertOk()->assertSee('Aylık Nakit Akışı');
    }

    public function test_rapor_merkezi_olmayan_raporda_404_doner(): void
    {
        $manager = $this->admin();

        $this->actingAs($manager)->get('/sites/'.$this->site(1, $manager)->id.'/reports/olmayan-rapor')->assertNotFound();
    }

    public function test_aylik_tahsilat_raporu_vade_ayina_gore_oran_hesaplar(): void
    {
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $this->finance()->accrue($this->unit($apartment), ['description' => 'Ocak', 'amount' => 100000, 'due_date' => '2026-01-10']);
        $this->finance()->accrue($this->unit($apartment), ['description' => 'Şubat', 'amount' => 50000, 'due_date' => '2026-02-10']);
        $this->finance()->collect($this->unit($apartment), $this->cashbox($apartment), 50000);

        $rows = $this->rows($manager, $apartment, 'monthly-collection');

        $this->assertSame(['month' => '2026-01', 'count' => 1, 'amount' => 1000.0, 'paid' => 500.0, 'remaining' => 500.0, 'rate' => '%50,0'], $rows[0]);
        $this->assertSame('%0,0', $rows[1]['rate']);
    }

    public function test_nakit_akisi_kumulatif_toplami_ve_virman_haric_tutar(): void
    {
        $this->travelTo('2026-01-15');
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $cashbox = $this->cashbox($apartment);
        $second = $apartment->cashboxes()->create(['name' => 'Banka', 'type' => 'bank', 'balance' => 0]);
        $this->finance()->generalTransaction($cashbox, 'income', 100000, 'Gelir');
        $this->finance()->transfer($cashbox->fresh(), $second, 20000);
        $this->travelTo('2026-02-15');
        $this->finance()->generalTransaction($cashbox->fresh(), 'expense', 30000, 'Gider');

        $rows = $this->rows($manager, $apartment, 'cash-flow');

        $this->assertSame([1000.0, 700.0], array_column($rows, 'cumulative'));
        $this->assertSame([1000.0, 0.0], array_column($rows, 'income'));
    }

    public function test_blok_filtresi_borclu_daireleri_daraltir(): void
    {
        $this->travelTo('2026-05-15');
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $blockB = $apartment->blocks()->create(['name' => 'B']);
        $unitB = $apartment->units()->create(['block_id' => $blockB->id, 'unit_no' => '7']);
        $this->finance()->accrue($this->unit($apartment), ['description' => 'A', 'amount' => 90000, 'due_date' => '2026-04-10']);
        $this->finance()->accrue($unitB, ['description' => 'B', 'amount' => 10000, 'due_date' => '2026-04-10']);

        $this->assertSame(['A-1', 'B-7'], array_column($this->rows($manager, $apartment, 'top-debtors'), 'unit'));
        $this->assertSame(['B-7'], array_column($this->rows($manager, $apartment, 'top-debtors', ['block_id' => $blockB->id]), 'unit'));
    }

    public function test_talep_raporu_tarih_araligina_uyar_ve_baska_siteyi_gostermez(): void
    {
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $this->travelTo('2026-01-15');
        $apartment->tickets()->create(['subject' => 'Ocak talebi', 'category' => 'Genel']);
        $this->site(1)->tickets()->create(['subject' => 'Yabancı talep', 'category' => 'Genel']);
        $this->travelTo('2026-03-15');
        $apartment->tickets()->create(['subject' => 'Mart talebi', 'category' => 'Genel']);

        $this->assertEqualsCanonicalizing(['Ocak talebi', 'Mart talebi'], array_column($this->rows($manager, $apartment, 'tickets'), 'subject'));
        $this->assertSame(['Ocak talebi'], array_column($this->rows($manager, $apartment, 'tickets', ['to' => '2026-02-01']), 'subject'));
    }

    public function test_sakinin_yazdigi_metin_csv_de_formul_olarak_calismaz(): void
    {
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $apartment->tickets()->create(['subject' => '=HYPERLINK("http://kotu.test")', 'category' => 'Genel']);

        $xml = $this->spreadsheetXml($this->actingAs($manager)->get(route('site.reports.show', [$apartment, 'tickets', 'format' => 'xlsx'])));

        // Hücre formül olarak değil, düz metin olarak yazılır
        $this->assertStringContainsString('t="inlineStr"><is><t xml:space="preserve">=HYPERLINK', $xml);
        $this->assertStringNotContainsString('<f>', $xml);
    }

    public function test_baska_sitenin_yoneticisi_raporu_acamaz(): void
    {
        $this->actingAs($this->admin())->get(route('site.reports.show', [$this->site(1), 'tickets']))->assertForbidden();
    }
}
