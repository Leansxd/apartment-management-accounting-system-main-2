<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\BankStatementLine;
use App\Models\CashTransaction;
use App\Models\Debt;
use App\Services\AccountingReportService;
use App\Services\BankImportService;
use App\Services\FinanceException;
use App\Services\FinanceService;
use App\Services\PayrollService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementServicesTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ BANKA

    public function test_banka_ekstresi_ayrisir_daire_eslestirir_tahsilata_cevirir_ve_mukerrer_yuklemez(): void
    {
        $apt = $this->site(3);
        $finance = app(FinanceService::class);
        $bank = app(BankImportService::class);
        foreach ($apt->units()->get() as $u) {
            $finance->accrue($u, ['description' => 'Aidat', 'amount' => 150000, 'due_date' => '2026-01-31']);
        }
        $apt->members()->create(['unit_id' => $apt->units()->orderBy('id')->get()[2]->id, 'full_name' => 'Zeynep Çelik']);

        $csv = "Tarih;Açıklama;Tutar;Bakiye\n"
            ."03.02.2026;AIDAT A-1 OCAK;1.500,00;1.500,00\n"
            ."04.02.2026;HAVALE ZEYNEP CELIK;1.500,00;3.000,00\n"
            ."05.02.2026;BILINMEYEN GONDEREN;200,00;3.200,00\n"
            ."06.02.2026;ELEKTRIK FATURASI;-450,50;2.749,50\n";
        $rows = $bank->parse($csv);
        $this->assertCount(4, $rows);
        $this->assertSame(-45050, $rows[3]['amount']);

        $box = $this->cashbox($apt);
        $stats = $bank->import($apt, $box, $rows);
        $this->assertSame(['imported' => 4, 'duplicates' => 0, 'matched' => 2], collect($stats)->only(['imported', 'duplicates', 'matched'])->all());
        $again = $bank->import($apt, $box, $rows);
        $this->assertSame(['imported' => 0, 'duplicates' => 4], collect($again)->only(['imported', 'duplicates'])->all());

        $this->assertSame(['posted' => 2, 'failed' => []], $bank->postMatched($apt));
        $this->assertEquals(3000, (float) $box->fresh()->balance);
        $this->assertSame(2, Debt::where('status', 'paid')->count());

        $bank->post(BankStatementLine::where('status', 'pending')->where('amount', '>', 0)->first()); // eşleşmeyen gelen = genel gelir
        $expense = BankStatementLine::where('amount', '<', 0)->first();
        $bank->post($expense);
        $this->assertEquals(3000 + 200 - 450.50, (float) $box->fresh()->balance);
        $this->assertLedgerBalanced($apt);

        $this->expectException(FinanceException::class);
        $bank->post($expense->fresh()); // ikinci kez işlenemez
    }

    public function test_banka_ekstresi_ayri_giris_cikis_sutunlari(): void
    {
        $rows = app(BankImportService::class)->parse("Tarih,Açıklama,Giriş,Çıkış\n2026-03-01,Kira,2500.00,\n2026-03-02,Gider,,\"300,50\"\n");

        $this->assertSame(250000, $rows[0]['amount']);
        $this->assertSame(-30050, $rows[1]['amount']);
    }

    public function test_banka_ekstresinde_islem_ile_baslayan_sutunlar_aciklamanin_yerine_gecmez(): void
    {
        $bank = app(BankImportService::class);

        $dateAndDescription = $bank->parse("İşlem Tarihi;İşlem;Tutar\n01.02.2026;A-5 aidat;100,00\n");
        $withTransactionType = $bank->parse("İşlem Tarihi;İşlem Türü;Açıklama;Tutar\n01.02.2026;Havale;A-5 aidat;100,00\n");

        $this->assertSame('2026-02-01', $dateAndDescription[0]['date']);
        $this->assertSame('A-5 aidat', $dateAndDescription[0]['description']);
        $this->assertSame('A-5 aidat', $withTransactionType[0]['description']);
    }

    public function test_banka_satirindan_gelen_tahsilat_iptal_edilince_satir_yeniden_islenebilir(): void
    {
        $apt = $this->site(1);
        $unit = $this->unit($apt);
        $finance = app(FinanceService::class);
        $bank = app(BankImportService::class);
        $box = $this->cashbox($apt);
        $finance->accrue($unit, ['description' => 'Aidat', 'amount' => 150000, 'due_date' => '2026-01-31']);
        $bank->import($apt, $box, [['date' => '2026-02-03', 'description' => 'AIDAT', 'amount' => 150000, 'reference' => null]]);
        $line = BankStatementLine::firstOrFail();
        $line->update(['unit_id' => $unit->id]);
        $bank->post($line);

        $finance->reverse(CashTransaction::findOrFail($line->fresh()->cash_transaction_id), 'yanlış daire');

        $this->assertSame('pending', $line->fresh()->status);
        $this->assertNull($line->fresh()->cash_transaction_id);

        $bank->post($line->fresh());

        $this->assertSame('posted', $line->fresh()->status);
        $this->assertEquals(1500, (float) $box->fresh()->balance);
        $this->assertLedgerBalanced($apt);
    }

    public function test_uzun_banka_aciklamasi_kasa_hareketinde_255_karaktere_kisalir(): void
    {
        $apt = $this->site(1);
        $bank = app(BankImportService::class);
        $bank->import($apt, $this->cashbox($apt), [['date' => '2026-02-03', 'description' => str_repeat('x', 500), 'amount' => 10000, 'reference' => null]]);

        $bank->post(BankStatementLine::firstOrFail());

        $this->assertSame(255, mb_strlen(CashTransaction::firstOrFail()->description));
    }

    public function test_banka_ekstresinde_hatali_tarih_reddedilir(): void
    {
        $this->expectException(FinanceException::class);
        app(BankImportService::class)->parse("Tarih;Açıklama;Tutar\nyarın;x;5\n");
    }

    // ------------------------------------------------------------ MALİ RAPORLAR

    public function test_mizan_dengeli_kasa_defteri_kasa_bakiyesiyle_ayni_ve_bilanco_tutarli(): void
    {
        $apt = $this->site(2);
        $finance = app(FinanceService::class);
        $box = $this->cashbox($apt);
        $bank = $apt->cashboxes()->create(['name' => 'Banka', 'type' => 'bank']);
        foreach ($apt->units()->get() as $u) {
            $finance->accrue($u, ['description' => 'Aidat', 'amount' => 100000, 'due_date' => '2026-01-31']);
        }
        $finance->collect($this->unit($apt), $box, 130000); // 1.000 borç + 300 avans
        $finance->generalTransaction($box, 'income', 50000, 'Kira');
        $finance->transfer($box, $bank, 40000);
        $finance->generalTransaction($box, 'expense', 10000, 'Temizlik');
        $finance->reverse($finance->generalTransaction($box, 'income', 7000, 'Hatalı'), 'yanlış');
        $apt->assets()->create(['name' => 'Jeneratör', 'purchase_date' => today()->subYear(), 'cost' => 12000, 'useful_life_years' => 4, 'salvage_value' => 0]);

        $reports = app(AccountingReportService::class);
        $tb = $reports->trialBalance($apt);
        $this->assertEquals($tb['debit'], $tb['credit']);
        $this->assertEqualsWithDelta((float) $apt->cashboxes()->sum('balance'), $tb['groups']['Kasa/Banka']['balance'], 0.001);

        foreach ([$box, $bank] as $cb) {
            $this->assertEqualsWithDelta((float) $cb->fresh()->balance, $reports->cashBook($cb->fresh())['closing'], 0.001);
        }

        $sheet = $reports->balanceSheet($apt);
        $this->assertEquals(1700, $sheet['cash_total']);   // 1.300 + 500 − 100 (virman ve iptal nötr)
        $this->assertEquals(1000, $sheet['receivables']);  // ikinci dairenin borcu
        $this->assertEquals(300, $sheet['advances']);
        $this->assertEquals(9000, $sheet['fixed_assets']); // 12.000 − 1 yıl amortisman (3.000)
        $this->assertEquals(11700, $sheet['assets_total']);
        $this->assertEquals(11400, $sheet['net']);
    }

    public function test_kasa_defteri_acilis_bakiyesi_tarih_filtresiyle_hesaplanir(): void
    {
        $apt = $this->site(1);
        $box = $this->cashbox($apt);
        $finance = app(FinanceService::class);
        $first = $finance->generalTransaction($box, 'income', 100000, 'Eski');
        $first->forceFill(['created_at' => now()->subDays(10)])->save();
        $finance->generalTransaction($box, 'expense', 30000, 'Yeni');

        $book = app(AccountingReportService::class)->cashBook($box, today()->subDay()->toDateString());
        $this->assertEquals(1000, $book['opening']);
        $this->assertEquals(700, $book['closing']);
        $this->assertCount(1, $book['rows']);
    }

    public function test_alacak_yaslandirma_dilimleri(): void
    {
        $apt = $this->site(1);
        $finance = app(FinanceService::class);
        $unit = $this->unit($apt);
        foreach (['2026-07-15' => 100, '2026-06-10' => 200, '2026-05-01' => 300, '2026-03-01' => 400] as $due => $amount) {
            $finance->accrue($unit, ['description' => "d{$due}", 'amount' => $amount * 100, 'due_date' => $due]);
        }

        $aging = app(AccountingReportService::class)->aging($apt, CarbonImmutable::parse('2026-06-30'));
        $row = $aging['rows'][0]['buckets'];
        $this->assertEquals([100, 200, 300, 0, 400], array_values($row));
        $this->assertEquals(1000, $aging['totals']['total']);
    }

    public function test_butce_gerceklesen_ile_karsilastirilir(): void
    {
        $apt = $this->site(1);
        $finance = app(FinanceService::class);
        $box = $this->cashbox($apt);
        $aidat = $apt->categories()->create(['name' => 'Aidat', 'type' => 'income']);
        $elektrik = $apt->categories()->create(['name' => 'Elektrik', 'type' => 'expense']);
        $year = (int) now()->year;
        $apt->budgetItems()->create(['year' => $year, 'category_id' => $aidat->id, 'amount' => 12000]);
        $apt->budgetItems()->create(['year' => $year, 'category_id' => $elektrik->id, 'amount' => 3000]);

        $finance->accrue($this->unit($apt), ['description' => 'Aidat', 'amount' => 100000, 'due_date' => now()->toDateString(), 'category_id' => $aidat->id]);
        $finance->collect($this->unit($apt), $box, 40000);
        $finance->generalTransaction($box, 'expense', 25000, 'Elektrik', $elektrik->id);

        $b = app(AccountingReportService::class)->budget($apt, $year);
        $this->assertEquals(400, $b['income'][0]['actual']);
        $this->assertEquals(1000, $b['income'][0]['accrued']);
        $this->assertEquals(3.3, $b['income'][0]['pct']);
        $this->assertEquals(250, $b['expense'][0]['actual']);
        $this->assertEquals(8.3, $b['expense'][0]['pct']);
        $this->assertEquals(12000, $b['totals']['income_planned']);
    }

    // ------------------------------------------------------------ DEMİRBAŞ

    public function test_demirbas_dogrusal_amortisman_hurda_degerini_asmaz(): void
    {
        $asset = new Asset(['purchase_date' => CarbonImmutable::parse('2024-01-01'), 'cost' => 12000, 'salvage_value' => 2000, 'useful_life_years' => 5]);

        $this->assertSame(0, $asset->accumulatedDepreciation(CarbonImmutable::parse('2024-01-01')));
        $this->assertSame(200000, $asset->accumulatedDepreciation(CarbonImmutable::parse('2025-01-01'))); // 10.000 ₺ / 5 yıl
        $this->assertSame(1000000, $asset->accumulatedDepreciation(CarbonImmutable::parse('2040-01-01'))); // hurda değerinde durur
        $this->assertSame(200000, $asset->bookValue(CarbonImmutable::parse('2040-01-01')));
    }

    // ------------------------------------------------------------ BORDRO

    public function test_bordro_donem_basina_bir_kez_uretilir_ve_kasadan_odenir(): void
    {
        $apt = $this->site(1);
        $apt->staff()->create(['name' => 'Kemal', 'role' => 'Güvenlik', 'salary' => 25000.50]);
        $apt->staff()->create(['name' => 'Ayşe', 'role' => 'Temizlik', 'salary' => 0]);
        $apt->staff()->create(['name' => 'Eski', 'role' => 'Bahçe', 'salary' => 1000, 'status' => 'Ayrıldı']);
        $payroll = app(PayrollService::class);

        $this->assertSame(['created' => 1, 'skipped' => 0], $payroll->generate($apt, '2026-03'));
        $this->assertSame(['created' => 0, 'skipped' => 1], $payroll->generate($apt, '2026-03'));
        $expense = $apt->expenses()->first();
        $this->assertEquals(25000.50, (float) $expense->total_amount);
        $this->assertSame('Personel', $expense->category->name);

        $finance = app(FinanceService::class);
        $finance->generalTransaction($this->cashbox($apt), 'income', 3000000, 'Kasa');
        $finance->approveExpense($expense, $this->cashbox($apt));
        $this->assertEquals(4999.50, (float) $this->cashbox($apt)->fresh()->balance);

        $this->expectException(FinanceException::class);
        $payroll->generate($apt, '2026-13');
    }
}
