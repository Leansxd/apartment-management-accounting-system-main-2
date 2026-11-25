<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\BankStatementLine;
use App\Models\Cashbox;
use App\Models\CashTransaction;
use App\Models\Debt;
use App\Models\LedgerEntry;
use App\Models\Unit;
use App\Models\User;
use App\Services\AccountingReportService;
use App\Services\BankImportService;
use App\Services\FinanceException;
use App\Services\FinanceService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiCurrencyCollectionTest extends TestCase
{
    use RefreshDatabase;

    private const TCMB_URL = 'https://www.tcmb.gov.tr/kurlar/today.xml';

    private FinanceService $finance;

    private User $manager;

    private Apartment $site;

    private Unit $flat;

    private Cashbox $tryBox;

    private Cashbox $usdBox;

    private Cashbox $eurBox;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->finance = app(FinanceService::class);
        $this->manager = $this->admin();
        $this->site = $this->site(2, $this->manager);
        $this->flat = $this->unit($this->site);
        $this->tryBox = $this->cashbox($this->site);
        $this->usdBox = $this->site->cashboxes()->create(['name' => 'Dolar Kasası', 'type' => 'cash', 'currency' => 'USD']);
        $this->eurBox = $this->site->cashboxes()->create(['name' => 'Euro Hesabı', 'type' => 'bank', 'currency' => 'EUR']);
    }

    private function debt(int $amountCents = 100000): Debt
    {
        $this->finance->accrue($this->flat, ['description' => 'Aidat', 'amount' => $amountCents, 'due_date' => '2026-01-31']);

        return Debt::where('unit_id', $this->flat->id)->latest('id')->firstOrFail();
    }

    /** @return array{cashbox: Cashbox, amount: int, method: string, exchange_rate: string|null} */
    private function part(Cashbox $cashbox, int $amount, ?string $rate = null, string $method = 'collection'): array
    {
        return ['cashbox' => $cashbox, 'amount' => $amount, 'method' => $method, 'exchange_rate' => $rate];
    }

    private function fundUsd(int $amount, string $rate): void
    {
        $this->finance->recordOpeningBalance($this->usdBox, $amount, null, $rate);
    }

    public function test_tek_borc_farkli_doviz_ve_kasalarla_parcali_kapanir(): void
    {
        $debt = $this->debt();

        $parts = $this->finance->collectParts($this->flat, [
            $this->part($this->tryBox, 20000),
            $this->part($this->usdBox, 1000, '30', 'pos'),
            $this->part($this->eurBox, 500, '100'),
        ], null, $this->manager, $debt);

        $this->assertSame(Debt::PAID, $debt->fresh()->status);
        $this->assertEquals(0, (float) $debt->fresh()->remaining_amount);
        $this->assertEquals([200, 300, 500], $parts->map(fn (CashTransaction $tx) => (float) $tx->fresh()->amount)->all());
        $this->assertCount(1, $parts->pluck('payment_group')->unique());
        $this->assertNotNull($parts->first()->payment_group);
        $this->assertSame('pos', $parts[1]->sub_type);
        $this->assertSame(3, $debt->payments()->where('type', 'collection')->count());

        $this->assertEquals(200, (float) $this->tryBox->fresh()->balance);
        $this->assertEquals(300, (float) $this->usdBox->fresh()->balance);
        $this->assertEquals(10, (float) $this->usdBox->fresh()->currency_balance);
        $this->assertEquals(500, (float) $this->eurBox->fresh()->balance);
        $this->assertEquals(5, (float) $this->eurBox->fresh()->currency_balance);
        $this->assertLedgerBalanced($this->site);
    }

    public function test_odeme_aninda_kur_ve_orijinal_tutar_saklanir_ve_kurus_yuvarlanir(): void
    {
        $this->debt();

        $tx = $this->finance->collect($this->flat, $this->usdBox, 3333, null, $this->manager, null, 'collection', '30.123456');

        $tx->refresh();
        $this->assertSame('USD', $tx->currency);
        $this->assertEquals(33.33, (float) $tx->original_amount);
        $this->assertEquals(30.123456, (float) $tx->exchange_rate);
        $this->assertEquals(1004.01, (float) $tx->amount); // 33,33 × 30,123456 = 1004,0148
    }

    public function test_kismi_odeme_borcu_acik_birakir_sonraki_doviz_parcasi_kalani_kapatir(): void
    {
        $debt = $this->debt();

        $this->finance->collect($this->flat, $this->usdBox, 1000, null, null, $debt, 'collection', '30');
        $this->assertSame(Debt::PENDING, $debt->fresh()->status);
        $this->assertEquals(700, (float) $debt->fresh()->remaining_amount);
        $this->assertEquals(300, (float) $debt->fresh()->paid_amount);

        $this->finance->collect($this->flat, $this->eurBox, 700, null, null, $debt, 'collection', '100');
        $this->assertSame(Debt::PAID, $debt->fresh()->status);
    }

    public function test_fazla_odeme_tl_avans_olarak_yazilir(): void
    {
        $this->debt();

        $tx = $this->finance->collect($this->flat, $this->usdBox, 5000, null, null, null, 'collection', '30');

        $this->assertEquals(1000, (float) $tx->fresh()->applied_to_debts);
        $this->assertEquals(500, (float) $tx->fresh()->to_advance);
        $this->assertEquals(500, (float) $this->flat->fresh()->credit_balance);
        $this->assertLedgerBalanced($this->site);
    }

    public function test_doviz_parcasinda_kur_yoksa_hicbir_parca_kaydedilmez(): void
    {
        $debt = $this->debt();

        try {
            $this->finance->collectParts($this->flat, [$this->part($this->tryBox, 20000), $this->part($this->usdBox, 1000)]);
            $this->fail('Kursuz döviz parçası reddedilmeliydi.');
        } catch (FinanceException $e) {
            $this->assertStringContainsString('kur girilmelidir', $e->getMessage());
        }

        $this->assertSame(0, CashTransaction::count());
        $this->assertEquals(0, (float) $this->tryBox->fresh()->balance);
        $this->assertEquals(1000, (float) $debt->fresh()->remaining_amount);
    }

    public function test_tl_kasada_gonderilen_kur_yok_sayilir(): void
    {
        $tx = $this->finance->collect($this->flat, $this->tryBox, 10000, null, null, null, 'collection', '45');

        $this->assertSame('TRY', $tx->fresh()->currency);
        $this->assertEquals(1, (float) $tx->fresh()->exchange_rate);
        $this->assertEquals(100, (float) $tx->fresh()->amount);
        $this->assertEquals(100, (float) $tx->fresh()->original_amount);
    }

    public function test_doviz_parcasi_iptali_kasayi_ve_borcu_tam_geri_alir(): void
    {
        $debt = $this->debt();
        $parts = $this->finance->collectParts($this->flat, [$this->part($this->tryBox, 20000), $this->part($this->usdBox, 1000, '30')], null, null, $debt);

        $reversal = $this->finance->reverse($parts[1], 'Hatalı kur');

        $this->assertEquals(0, (float) $this->usdBox->fresh()->balance);
        $this->assertEquals(0, (float) $this->usdBox->fresh()->currency_balance);
        $this->assertEquals(800, (float) $debt->fresh()->remaining_amount);
        $this->assertEquals(200, (float) $this->tryBox->fresh()->balance);
        $this->assertSame('USD', $reversal->currency);
        $this->assertEquals(10, (float) $reversal->original_amount);
        $this->assertLedgerBalanced($this->site);
    }

    public function test_iptal_sonrasi_dovizi_kalmayan_kasada_tl_artigi_kur_farkina_aktarilir(): void
    {
        $first = $this->finance->collect($this->flat, $this->usdBox, 10000, null, null, null, 'collection', '30');
        $this->finance->collect($this->flat, $this->usdBox, 10000, null, null, null, 'collection', '32');
        $this->finance->generalTransaction($this->usdBox, 'expense', 10000, 'Döviz gider');

        $this->finance->reverse($first, 'Hatalı kayıt');

        $this->assertEquals(0, (float) $this->usdBox->fresh()->currency_balance);
        $this->assertEquals(0, (float) $this->usdBox->fresh()->balance);
        $this->assertSame(1, CashTransaction::where('sub_type', 'fx_difference')->count());
        $this->assertLedgerBalanced($this->site);
    }

    public function test_doviz_tl_kasaya_bozdurulunca_kur_kari_yazilir(): void
    {
        $this->fundUsd(10000, '30');
        $this->fundUsd(10000, '32');

        $this->finance->transfer($this->usdBox, $this->tryBox, 10000, null, null, 330000);

        $this->assertEquals(3300, (float) $this->tryBox->fresh()->balance);
        $this->assertEquals(100, (float) $this->usdBox->fresh()->currency_balance);
        $this->assertEquals(3100, (float) $this->usdBox->fresh()->balance); // ortalama maliyet 31
        $gain = CashTransaction::where('sub_type', 'fx_difference')->firstOrFail();
        $this->assertSame('income', $gain->type);
        $this->assertEquals(200, (float) $gain->amount);
        $this->assertEquals(200, (float) LedgerEntry::where('account_name', LedgerEntry::FX_DIFFERENCE)->sum('credit'));
        $this->assertLedgerBalanced($this->site);
    }

    public function test_doviz_ortalama_maliyetin_altinda_bozdurulunca_kur_zarari_yazilir(): void
    {
        $this->fundUsd(10000, '30');

        $this->finance->transfer($this->usdBox, $this->tryBox, 10000, null, null, 280000);

        $this->assertEquals(0, (float) $this->usdBox->fresh()->balance);
        $this->assertEquals(0, (float) $this->usdBox->fresh()->currency_balance);
        $loss = CashTransaction::where('sub_type', 'fx_difference')->firstOrFail();
        $this->assertSame('expense', $loss->type);
        $this->assertEquals(200, (float) $loss->amount);
        $this->assertLedgerBalanced($this->site);
    }

    public function test_tl_ile_doviz_alinca_kur_farki_olusmaz(): void
    {
        $this->finance->recordOpeningBalance($this->tryBox, 300000);

        $this->finance->transfer($this->tryBox, $this->usdBox, 300000, null, null, 10000);

        $this->assertEquals(3000, (float) $this->usdBox->fresh()->balance);
        $this->assertEquals(100, (float) $this->usdBox->fresh()->currency_balance);
        $this->assertSame(0, CashTransaction::where('sub_type', 'fx_difference')->count());
    }

    public function test_para_birimi_farkli_virmanda_hedef_tutar_zorunludur(): void
    {
        $this->fundUsd(10000, '30');

        $this->expectException(FinanceException::class);
        $this->finance->transfer($this->usdBox, $this->tryBox, 10000);
    }

    public function test_doviz_kasasi_eksi_bakiye_izni_olsa_da_eksiye_dusmez(): void
    {
        $this->site->update(['allow_negative_cashbox' => true]);
        $this->fundUsd(1000, '30');

        $this->expectException(FinanceException::class);
        $this->finance->generalTransaction($this->usdBox, 'expense', 2000, 'Fazla çıkış');
    }

    public function test_avans_iadesi_doviz_kasasindan_yapilamaz(): void
    {
        $this->fundUsd(100000, '30');
        $this->flat->update(['credit_balance' => 100]);

        foreach ([
            fn () => $this->finance->refundAdvance($this->flat, $this->usdBox, 10000),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Döviz kasasından ödeme reddedilmeliydi.');
            } catch (FinanceException $e) {
                $this->assertStringContainsString('yalnızca TL kasadan', $e->getMessage());
            }
        }
    }

    public function test_formdan_parcali_tahsilat_kaydedilir(): void
    {
        $debt = $this->debt();

        $this->actingAs($this->manager)->post(route('site.collections.store', $this->site), [
            'unit_id' => $this->flat->id, 'debt_id' => $debt->id,
            'parts' => [
                ['cashbox_id' => $this->tryBox->id, 'amount' => '400', 'method' => 'collection'],
                ['cashbox_id' => $this->usdBox->id, 'amount' => '20', 'method' => 'pos', 'exchange_rate' => '30'],
            ],
        ])->assertSessionHas('status', 'Tahsilat alındı: 1.000,00 ₺ · 2 parça (borçlara 1.000,00 ₺, avansa 0,00 ₺)');

        $this->assertSame(Debt::PAID, $debt->fresh()->status);
        $this->assertEquals(20, (float) $this->usdBox->fresh()->currency_balance);
    }

    public function test_tek_parcali_eski_form_calismaya_devam_eder(): void
    {
        $this->actingAs($this->manager)->post(route('site.collections.store', $this->site), [
            'unit_id' => $this->flat->id, 'cashbox_id' => $this->tryBox->id, 'amount' => '150', 'method' => 'collection',
        ])->assertSessionHas('status');

        $tx = CashTransaction::firstOrFail();
        $this->assertEquals(150, (float) $tx->amount);
        $this->assertNull($tx->payment_group);
    }

    public function test_gecersiz_parcalar_dogrulama_hatasi_verir(): void
    {
        $this->actingAs($this->manager)->post(route('site.collections.store', $this->site), [
            'unit_id' => $this->flat->id,
            'parts' => [
                ['cashbox_id' => $this->tryBox->id, 'amount' => '0', 'method' => 'collection'],
                ['cashbox_id' => $this->usdBox->id, 'amount' => '10', 'method' => 'bitcoin', 'exchange_rate' => '-1'],
            ],
        ])->assertSessionHasErrors(['parts.0.amount', 'parts.1.method', 'parts.1.exchange_rate']);

        $this->assertSame(0, CashTransaction::count());
    }

    public function test_parca_olmadan_tahsilat_reddedilir(): void
    {
        $this->actingAs($this->manager)->post(route('site.collections.store', $this->site), ['unit_id' => $this->flat->id])
            ->assertSessionHasErrors('parts');
    }

    public function test_formda_kursuz_doviz_parcasi_hata_mesaji_doner(): void
    {
        $this->actingAs($this->manager)->post(route('site.collections.store', $this->site), [
            'unit_id' => $this->flat->id,
            'parts' => [['cashbox_id' => $this->usdBox->id, 'amount' => '10', 'method' => 'collection']],
        ])->assertSessionHas('error');

        $this->assertSame(0, CashTransaction::count());
    }

    public function test_baska_sitenin_kasasi_parca_olarak_kullanilamaz(): void
    {
        $other = $this->site(1);

        $this->actingAs($this->manager)->post(route('site.collections.store', $this->site), [
            'unit_id' => $this->flat->id,
            'parts' => [['cashbox_id' => $this->cashbox($other)->id, 'amount' => '10', 'method' => 'collection']],
        ])->assertSessionHasErrors('parts.0.cashbox_id');
    }

    public function test_izleyici_ve_site_disi_yonetici_parcali_tahsilat_yapamaz(): void
    {
        $viewer = $this->admin();
        $this->site->managers()->attach($viewer->id, ['role' => 'viewer']);
        $payload = [
            'unit_id' => $this->flat->id,
            'parts' => [['cashbox_id' => $this->usdBox->id, 'amount' => '10', 'method' => 'collection', 'exchange_rate' => '30']],
        ];

        $this->actingAs($viewer)->post(route('site.collections.store', $this->site), $payload)->assertForbidden();
        $this->actingAs($this->admin())->post(route('site.collections.store', $this->site), $payload)->assertForbidden();

        $this->assertSame(0, CashTransaction::count());
    }

    public function test_doviz_kasasi_acilis_bakiyesi_ve_kuruyla_olusur(): void
    {
        $this->actingAs($this->manager)->post(route('site.cashboxes.store', $this->site), [
            'name' => 'Sterlin Kasası', 'type' => 'cash', 'currency' => 'GBP', 'opening_balance' => '100', 'opening_rate' => '40',
        ])->assertSessionHas('status');

        $box = $this->site->cashboxes()->where('currency', 'GBP')->firstOrFail();
        $this->assertEquals(4000, (float) $box->balance);
        $this->assertEquals(100, (float) $box->currency_balance);
        $this->assertLedgerBalanced($this->site);
    }

    public function test_acilis_kuru_eksikse_doviz_kasasi_olusmaz(): void
    {
        $this->actingAs($this->manager)->post(route('site.cashboxes.store', $this->site), [
            'name' => 'Sterlin Kasası', 'type' => 'cash', 'currency' => 'GBP', 'opening_balance' => '100',
        ])->assertSessionHas('error');

        $this->assertFalse($this->site->cashboxes()->where('currency', 'GBP')->exists());
    }

    public function test_tahsilat_ekrani_tcmb_kurunu_onerir(): void
    {
        Http::fake([self::TCMB_URL => Http::response(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <Tarih_Date Tarih="10.11.2026" Date="11/10/2026" Bulten_No="2026/210">
                <Currency CrossOrder="0" Kod="USD" CurrencyCode="USD"><Unit>1</Unit><ForexBuying>42.1234</ForexBuying><ForexSelling>42.2</ForexSelling></Currency>
                <Currency CrossOrder="1" Kod="EUR" CurrencyCode="EUR"><Unit>1</Unit><ForexBuying>48.5</ForexBuying><ForexSelling>48.6</ForexSelling></Currency>
            </Tarih_Date>
            XML)]);

        $this->actingAs($this->manager)->get(route('site.collections.index', [$this->site, 'unit_id' => $this->flat->id]))
            ->assertOk()->assertSee('42.123400', false)->assertSee('TCMB 10.11.2026', false)->assertSee('Dolar Kasası (USD)');
    }

    public function test_tcmb_yanit_vermezse_son_kullanilan_kur_onerilir(): void
    {
        Http::fake([self::TCMB_URL => Http::response('hata', 503)]);
        $this->finance->collect($this->flat, $this->usdBox, 1000, null, null, null, 'collection', '39.5');

        $this->actingAs($this->manager)->get(route('site.collections.index', [$this->site, 'unit_id' => $this->flat->id]))
            ->assertOk()->assertSee('39.500000', false)->assertSee('son kullan\\u0131lan kur', false);
    }

    public function test_makbuz_tum_parcalari_doviz_ve_tl_karsiligiyla_gosterir(): void
    {
        $this->debt();
        $parts = $this->finance->collectParts($this->flat, [$this->part($this->tryBox, 20000), $this->part($this->usdBox, 1000, '30')]);

        $this->actingAs($this->manager)->get(route('site.transactions.receipt', [$this->site, $parts[0]]))
            ->assertOk()->assertSee('10,00 $')->assertSee('30,0000')->assertSee('300,00 ₺')->assertSee('500,00 ₺')
            ->assertSee($parts[1]->receiptNo());
    }

    public function test_makbuz_toplami_iptal_edilen_parcayi_saymaz(): void
    {
        $parts = $this->finance->collectParts($this->flat, [$this->part($this->tryBox, 20000), $this->part($this->usdBox, 1000, '30')]);
        $this->finance->reverse($parts[1], 'Hatalı kur');

        $this->actingAs($this->manager)->get(route('site.transactions.receipt', [$this->site, $parts[0]]))
            ->assertOk()->assertSee('200,00 ₺')->assertDontSee('500,00 ₺');
    }

    public function test_ekstre_tl_karsiligi_ve_orijinal_dovizi_gosterir(): void
    {
        $this->debt(30000);
        $this->finance->collect($this->flat, $this->usdBox, 1500, null, null, null, 'collection', '30');

        $statement = app(ReportService::class)->statement($this->flat);
        $collection = collect($statement['rows'])->first(fn ($row) => str_starts_with($row['description'], 'Tahsilat'));
        $advance = collect($statement['rows'])->first(fn ($row) => str_starts_with($row['description'], 'Avans'));

        $this->assertSame('Tahsilat (10,00 $ × 30,0000)', $collection['description']);
        $this->assertEquals(300, $collection['credit']);
        $this->assertSame('Avans (fazla ödeme) (5,00 $ × 30,0000)', $advance['description']);
        $this->assertEquals(-150, $statement['closing']);
    }

    public function test_kasa_defteri_ve_mali_tablolar_tl_ve_doviz_bakiyesini_tutarli_verir(): void
    {
        $this->finance->collect($this->flat, $this->usdBox, 1000, null, null, null, 'collection', '30');
        $this->finance->collect($this->flat, $this->usdBox, 1000, null, null, null, 'collection', '32');

        $reports = app(AccountingReportService::class);
        $book = $reports->cashBook($this->usdBox->fresh());
        $this->assertEquals(620, $book['closing']);
        $this->assertEquals(20, $book['native_closing']);

        $this->assertEquals(620, $reports->balanceSheet($this->site)['cash_total']);
        $trial = $reports->trialBalance($this->site);
        $this->assertSame($trial['debit'], $trial['credit']);
        $this->assertTrue($trial['rows']->contains('sub', 'Dolar Kasası (USD)'));
        $this->assertEquals(620, app(ReportService::class)->summary($this->site)['balance']);
    }

    public function test_doviz_hesabi_banka_satiri_kurla_tahsilata_doner_toplu_islemde_atlanir(): void
    {
        $line = BankStatementLine::create([
            'apartment_id' => $this->site->id, 'cashbox_id' => $this->usdBox->id, 'batch' => 'b', 'line_date' => '2026-11-01',
            'description' => 'A-1 aidat', 'amount' => 10, 'unit_id' => $this->flat->id, 'hash' => 'h1',
        ]);
        $bank = app(BankImportService::class);

        $result = $bank->postMatched($this->site);
        $this->assertSame(0, $result['posted']);
        $this->assertCount(1, $result['failed']);

        $bank->post($line, null, $this->manager, '30');

        $this->assertSame('posted', $line->fresh()->status);
        $this->assertEquals(300, (float) $this->usdBox->fresh()->balance);
        $this->assertEquals(10, (float) $this->usdBox->fresh()->currency_balance);
    }

    public function test_sakin_api_odemelerde_doviz_bilgisini_doner(): void
    {
        $resident = User::factory()->create();
        $this->site->members()->create(['unit_id' => $this->flat->id, 'user_id' => $resident->id, 'full_name' => 'Sakin']);
        $this->finance->collect($this->flat, $this->usdBox, 1000, null, null, null, 'collection', '30');

        $this->actingAs($resident, 'sanctum')->getJson("/api/sites/{$this->site->id}/payments")
            ->assertOk()->assertJsonPath('0.amount', 300)->assertJsonPath('0.currency', 'USD')
            ->assertJsonPath('0.original_amount', 10)->assertJsonPath('0.exchange_rate', 30);
    }
}
