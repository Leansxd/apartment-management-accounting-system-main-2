<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\AccountingReportService;
use App\Services\FinanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bütçe ve gerçekleşenin geçmiş yıllarla karşılaştırılması */
class BudgetComparisonTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Apartment $site;

    private Unit $flat;

    private TransactionCategory $aidat;

    private TransactionCategory $elektrik;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->admin();
        $this->site = $this->site(1, $this->manager);
        $this->flat = $this->unit($this->site);
        $this->aidat = $this->site->categories()->create(['name' => 'Aidat', 'type' => 'income']);
        $this->elektrik = $this->site->categories()->create(['name' => 'Elektrik', 'type' => 'expense']);
        $this->site->budgetItems()->create(['year' => 2025, 'category_id' => $this->aidat->id, 'amount' => 1000]);
        $this->site->budgetItems()->create(['year' => 2026, 'category_id' => $this->aidat->id, 'amount' => 1200]);

        $this->year(2025, collected: 40000, spent: 25000);
        $this->year(2026, collected: 70000, spent: 10000);
        $this->travelBack();
    }

    /** O yıl içinde bir aidat tahakkuk edip kısmen tahsil eder ve elektrik gideri yazar (tutarlar kuruş). */
    private function year(int $year, int $collected, int $spent): void
    {
        $this->travelTo("{$year}-06-10 12:00:00");
        $finance = app(FinanceService::class);
        $finance->accrue($this->flat, ['description' => "{$year} aidat", 'amount' => 100000, 'due_date' => "{$year}-06-10", 'category_id' => $this->aidat->id]);
        $finance->collect($this->flat, $this->cashbox($this->site), $collected);
        $finance->generalTransaction($this->cashbox($this->site), 'expense', $spent, 'Elektrik', $this->elektrik->id);
    }

    public function test_kategori_satirina_gecmis_yil_ve_degisim_eklenir(): void
    {
        $comparison = app(AccountingReportService::class)->budgetComparison($this->site, 2026, [2025]);

        $income = $comparison['rows']['income']->firstWhere('category.name', 'Aidat');
        $this->assertEquals(700, $income['actual']);
        $this->assertEquals(400, $income['history'][2025]['actual']);
        $this->assertEquals(1000, $income['history'][2025]['planned']);
        $this->assertEquals(300, $income['change']);
        $this->assertEquals(75.0, $income['change_pct']);

        $expense = $comparison['rows']['expense']->firstWhere('category.name', 'Elektrik');
        $this->assertEquals(100, $expense['actual']);
        $this->assertEquals(-150, $expense['change']);
        $this->assertEquals(-60.0, $expense['change_pct']);
    }

    public function test_yillik_ozet_eskiden_yeniye_siralanir_ve_onceki_yila_gore_degisim_verir(): void
    {
        $summary = app(AccountingReportService::class)->budgetComparison($this->site, 2026, [2025])['summary'];

        $this->assertSame([2025, 2026], $summary->pluck('year')->all());
        $this->assertNull($summary[0]['income_change']);
        $this->assertEquals(75.0, $summary[1]['income_change']);
        $this->assertEquals(-60.0, $summary[1]['expense_change']);
        $this->assertEquals(400 - 250, $summary[0]['net']);
        $this->assertEquals(700 - 100, $summary[1]['net']);
    }

    public function test_bir_onceki_yilda_olmayan_kategoride_degisim_yuzdesi_bos_kalir(): void
    {
        $yeni = $this->site->categories()->create(['name' => 'Yeni gelir', 'type' => 'income']);
        $this->travelTo('2026-07-01 12:00:00');
        app(FinanceService::class)->generalTransaction($this->cashbox($this->site), 'income', 5000, 'Kira', $yeni->id);

        $row = app(AccountingReportService::class)->budgetComparison($this->site, 2026, [2025])['rows']['income']->firstWhere('category.name', 'Yeni gelir');

        $this->assertEquals(50, $row['actual']);
        $this->assertEquals(0, $row['history'][2025]['actual']);
        $this->assertNull($row['change_pct']);
    }

    public function test_aylik_dagilim_gerceklesen_tutarlari_odeme_ayina_yazar(): void
    {
        $monthly = app(AccountingReportService::class)->budgetMonthly($this->site, 2026);

        $this->assertEquals(700, $monthly['income'][6]);
        $this->assertEquals(100, $monthly['expense'][6]);
        $this->assertEquals(700, array_sum($monthly['income']));
        $this->assertEquals(100, array_sum($monthly['expense']));
    }

    public function test_kapanmis_yil_degerlendirmesi_net_hedef_ve_yillik_degisimi_anlatir(): void
    {
        $insights = $this->insights(2026, [2025], '2027-01-15');

        $this->assertSame('good', $insights[0]['tone']);
        $this->assertStringContainsString('gelir, giderden', $insights[0]['text']);
        $this->assertContains(['tone' => 'warn', 'text' => "Planlanan gelirin %58,3'i tahsil edilebildi; ".tl(500).' eksik kaldı.'], $insights);
        $this->assertContains(['tone' => 'good', 'text' => '2025 yılına göre: Gelir %75,0 arttı, Gider %60,0 azaldı.'], $insights);
        $this->assertContains(['tone' => 'info', 'text' => 'Bütçesi girilmemiş ama hareket gören kategoriler: Elektrik.'], $insights);
    }

    public function test_devam_eden_yilda_gider_hizi_yilin_gecen_kismindan_hizliysa_uyarir(): void
    {
        $this->site->budgetItems()->create(['year' => 2026, 'category_id' => $this->elektrik->id, 'amount' => 150]);

        $texts = collect($this->insights(2026, [], '2026-03-01'))->where('tone', 'warn')->pluck('text')->implode(' ');

        $this->assertStringContainsString("gider bütçesinin %66,7'i kullanıldı", $texts);
    }

    public function test_butcesini_asan_gider_kalemi_belirtilir(): void
    {
        $this->site->budgetItems()->create(['year' => 2026, 'category_id' => $this->elektrik->id, 'amount' => 50]);

        $bad = collect($this->insights(2026, [], '2027-01-15'))->where('tone', 'bad')->pluck('text');

        $this->assertTrue($bad->contains(fn (string $t) => str_starts_with($t, 'Gider bütçesi %100,0 aşıldı')));
        $this->assertContains('Bütçesini aşan gider kalemleri: Elektrik (+'.tl(50).').', $bad->all());
    }

    /** @param list<int> $compare */
    private function insights(int $year, array $compare, string $today): array
    {
        $reports = app(AccountingReportService::class);

        return $reports->budgetInsights($reports->budgetComparison($this->site, $year, $compare), $year, $compare, CarbonImmutable::parse($today));
    }

    public function test_sayfa_varsayilan_olarak_bir_onceki_yili_karsilastirir(): void
    {
        $this->actingAs($this->manager)->get(route('site.budget.index', [$this->site, 'year' => 2026]))
            ->assertOk()->assertSee('2025 gerçekleşen')->assertSee('Yıllara göre özet')
            ->assertSee('Kategori bazında karşılaştırma')->assertSee('Gerçekleşen gelir');
    }

    public function test_secilen_yillar_karsilastirilir_ve_secim_kaldirilinca_karsilastirma_kapanir(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('site.budget.index', [$this->site, 'year' => 2026, 'cmp' => 1, 'compare' => [2024, 2025]]))
            ->assertSee('2024 gerçekleşen')->assertSee('2025 gerçekleşen');
        $this->get(route('site.budget.index', [$this->site, 'year' => 2026, 'cmp' => 1]))
            ->assertDontSee('Yıllara göre özet')->assertDontSee('gerçekleşen</th>', false);
    }

    public function test_secilen_yildan_sonraki_yil_karsilastirmaya_girmez(): void
    {
        $this->actingAs($this->manager)->get(route('site.budget.index', [$this->site, 'year' => 2026, 'cmp' => 1, 'compare' => [2027, 2025]]))
            ->assertSee('2025 gerçekleşen')->assertDontSee('2027 gerçekleşen');
    }

    public function test_excel_cikti_gecmis_yil_sutunlarini_icerir(): void
    {
        $xml = $this->spreadsheetXml($this->actingAs($this->manager)->get(route('site.budget.index', [$this->site, 'year' => 2026, 'cmp' => 1, 'compare' => [2025], 'format' => 'xlsx'])));

        $this->assertStringContainsString('2025 Gerçekleşen', $xml);
        $this->assertStringContainsString('2025&apos;e göre fark', $xml); // XML kesme işaretini kaçırır
        $this->assertStringContainsString('Karşılaştırma yılları: 2025', $xml);
    }
}
