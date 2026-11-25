<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Unit;
use App\Models\User;
use App\Services\AccountingReportService;
use App\Services\FinanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Muhasebe menüsündeki rapor sayfaları: ekranlar, Excel (CSV) çıktıları, filtreler ve geçmiş tarihli bilanço */
class AccountingReportPagesTest extends TestCase
{
    use RefreshDatabase;

    private FinanceService $finance;

    private User $manager;

    private Apartment $site;

    private Unit $flat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finance = app(FinanceService::class);
        $this->manager = $this->admin();
        $this->site = $this->site(2, $this->manager);
        $this->flat = $this->unit($this->site);
        $aidat = $this->site->categories()->create(['name' => 'Aidat', 'type' => 'income']);
        $this->finance->accrue($this->flat, ['description' => 'Ocak aidatı', 'amount' => 100000, 'due_date' => today()->subMonths(2)->toDateString(), 'category_id' => $aidat->id]);
        $this->finance->collect($this->flat, $this->cashbox($this->site), 40000);
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>, 2: string}> rota, parametre, CSV'de beklenen başlık */
    public static function reports(): array
    {
        return [
            'mizan' => ['site.accounting.trial-balance', [], 'Alt hesap'],
            'kasa defteri' => ['site.accounting.cash-book', [], 'Bakiye'],
            'bilanço' => ['site.accounting.balance-sheet', [], 'Kalem'],
            'yaşlandırma' => ['site.accounting.aging', [], '90+ gün'],
            'gelir gider' => ['site.income-expense', [], 'Önceki dönem'],
            'yevmiye' => ['site.ledger', [], 'Belge'],
            'bütçe' => ['site.budget.index', [], 'Gerçekleşme'],
            'ekstre' => ['site.statements', ['unit' => true], 'Vade'],
        ];
    }

    /** @param  array<string, mixed>  $params */
    private function url(string $route, array $params, array $query = []): string
    {
        return route($route, [$this->site] + (isset($params['unit']) ? ['unit_id' => $this->flat->id] : []) + $query);
    }

    /** @param  array<string, mixed>  $params */
    #[DataProvider('reports')]
    public function test_sayfa_acilir_excel_ve_yazdir_dugmesi_vardir(string $route, array $params, string $header): void
    {
        $this->actingAs($this->manager)->get($this->url($route, $params))
            ->assertOk()->assertSee("Excel'e aktar", false)->assertSee('Yazdır / PDF');
    }

    /** @param  array<string, mixed>  $params */
    #[DataProvider('reports')]
    public function test_excel_ciktisi_basliklarla_iner(string $route, array $params, string $header): void
    {
        $response = $this->actingAs($this->manager)->get($this->url($route, $params, ['format' => 'xlsx']));

        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString($header, $this->spreadsheetXml($response));
    }

    /** @param  array<string, mixed>  $params */
    #[DataProvider('reports')]
    public function test_resmi_cikti_anteti_ve_imza_alani_vardir(string $route, array $params, string $header): void
    {
        $this->actingAs($this->manager)->get($this->url($route, $params))
            ->assertSee('report-header', false)->assertSee('Belge No:')->assertSee('Ad Soyad / İmza / Tarih');
    }

    public function test_makbuz_tutari_yaziyla_ve_kapatilan_borcla_gosterir(): void
    {
        $tx = $this->site->cashTransactions()->first();

        $this->actingAs($this->manager)->get(route('site.transactions.receipt', [$this->site, $tx]))
            ->assertOk()->assertSee('dört yüz Türk lirası')->assertSee('Kapatılan borçlar')->assertSee('Ocak aidatı');
    }

    public function test_bilanco_gecmis_tarihte_sonraki_hareketleri_saymaz(): void
    {
        $yesterday = CarbonImmutable::yesterday();
        $this->travel(1)->days();
        $this->finance->collect($this->flat, $this->cashbox($this->site), 10000);
        $this->travelBack();

        $reports = app(AccountingReportService::class);
        $past = $reports->balanceSheet($this->site, $yesterday);
        $today = $reports->balanceSheet($this->site);
        $future = $reports->balanceSheet($this->site, CarbonImmutable::today()->addDays(2));

        $this->assertEquals(0, $past['cash_total']);
        $this->assertEquals(400, $today['cash_total']);
        $this->assertEquals(600, $today['receivables']);
        $this->assertEquals(500, $future['cash_total']);
        $this->assertEquals(500, $future['receivables']);
        $this->assertNull($past['overdue']);
    }

    public function test_bilanco_gecmis_tarihli_odenmemis_gideri_ve_sonradan_odenmesini_ayirir(): void
    {
        $this->finance->recordOpeningBalance($this->cashbox($this->site), 100000);
        $expense = $this->site->expenses()->create(['description' => 'Bakım', 'amount' => 300, 'vat_rate' => 0, 'total_amount' => 300]);
        $this->travel(1)->days();
        $this->finance->payExpense($expense, $this->cashbox($this->site), 30000);
        $this->travelBack();

        $reports = app(AccountingReportService::class);
        $this->assertEquals(300, $reports->balanceSheet($this->site)['payables']);
        $this->assertEquals(0, $reports->balanceSheet($this->site, CarbonImmutable::today()->addDays(2))['payables']);
    }

    public function test_bilanco_karsilastirma_sutunu_gosterir(): void
    {
        $this->actingAs($this->manager)->get(route('site.accounting.balance-sheet', [$this->site, 'compare_to' => '2020-12-31']))
            ->assertOk()->assertSee('31.12.2020')->assertSee('Değişim');
    }

    public function test_mizandan_alt_hesabin_yevmiye_kayitlarina_gidilir(): void
    {
        $href = route('site.ledger', [$this->site->id, 'account' => 'Üye/Daire Hesabı', 'account_id' => $this->flat->id]);

        $this->actingAs($this->manager)->get(route('site.accounting.trial-balance', $this->site))->assertSee(e($href), false);
        $this->actingAs($this->manager)->get($href)->assertOk()->assertSee('Ocak aidatı')
            ->assertSee(e(route('site.transactions.receipt', [$this->site, $this->site->cashTransactions()->first()])), false);
    }

    public function test_yevmiye_belge_referansini_okunur_gosterir(): void
    {
        $tx = $this->site->cashTransactions()->first();

        $this->actingAs($this->manager)->get(route('site.ledger', $this->site))
            ->assertSee('Makbuz M-'.str_pad((string) $tx->id, 6, '0', STR_PAD_LEFT))
            ->assertSee('Borç kaydı #')
            ->assertDontSee('>cash_tx:', false);
    }

    public function test_yevmiye_islem_turune_gore_suzulur(): void
    {
        $csv = $this->spreadsheetXml($this->actingAs($this->manager)->get(route('site.ledger', [$this->site, 'type' => 'collection', 'format' => 'xlsx'])));

        $this->assertStringContainsString('Tahsilat', $csv);
        $this->assertStringNotContainsString('Tahakkuk', $csv);
    }

    public function test_gelir_gider_onceki_donemle_karsilastirir(): void
    {
        $from = today()->startOfMonth()->toDateString();
        $to = today()->toDateString();

        $response = $this->actingAs($this->manager)->get(route('site.income-expense', [$this->site, 'from' => $from, 'to' => $to]));

        $response->assertOk()->assertViewHas('income', fn ($income) => $income->sum('total') == 400 && $income->sum('previous') == 0);
        $response->assertViewHas('months', fn ($months) => $months->count() === 1 && $months->first()['income'] == 400);
    }

    public function test_yaslandirma_blok_ve_icra_filtresi_uygulanir(): void
    {
        $this->actingAs($this->manager)->get(route('site.accounting.aging', [$this->site, 'legal' => 1]))
            ->assertOk()->assertViewHas('aging', fn ($aging) => $aging['rows']->isEmpty() && $aging['totals']['total'] == 0);
        $this->actingAs($this->manager)->get(route('site.accounting.aging', $this->site))
            ->assertViewHas('aging', fn ($aging) => $aging['totals']['total'] == 600)
            ->assertSee(e(route('site.statements', [$this->site, 'unit_id' => $this->flat->id])), false);
    }

    public function test_kasa_hareketleri_kategoriye_gore_suzulur(): void
    {
        $category = $this->site->categories()->create(['name' => 'Kira geliri', 'type' => 'income']);
        $this->finance->generalTransaction($this->cashbox($this->site), 'income', 7000, 'Otopark kirası', $category->id);

        $this->actingAs($this->manager)->get(route('site.cashboxes.index', [$this->site, 'category_id' => $category->id]))
            ->assertSee('Otopark kirası')->assertDontSee('Tahsilat</td>', false);
    }

    public function test_baska_sitenin_raporu_acilamaz(): void
    {
        $this->actingAs($this->admin())->get(route('site.accounting.balance-sheet', [$this->site, 'format' => 'xlsx']))->assertForbidden();
    }
}
