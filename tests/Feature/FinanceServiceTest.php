<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\LedgerEntry;
use App\Services\FinanceException;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private FinanceService $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finance = app(FinanceService::class);
    }

    private function fields(int $amount, string $due = '2026-01-31', string $desc = 'Aidat'): array
    {
        return ['description' => $desc, 'amount' => $amount, 'due_date' => $due];
    }

    public function test_dagitim_avansi_mahsup_eder_ve_defteri_dengede_tutar(): void
    {
        $apt = $this->site(2);
        $this->unit($apt)->update(['credit_balance' => 300]);

        $report = $this->finance->distribute($apt, $apt->units()->get(), $this->fields(100000));

        $this->assertSame(2, $report['created']);
        $first = Debt::where('unit_id', $this->unit($apt)->id)->first();
        $this->assertEquals(300, (float) $first->paid_amount);
        $this->assertEquals(700, (float) $first->remaining_amount);
        $this->assertEquals(0, (float) $this->unit($apt)->credit_balance);
        $this->assertSame('credit_usage', $first->payments()->first()->type);
        $this->assertLedgerBalanced($apt);
    }

    public function test_avans_borcun_tamamini_karsilarsa_borc_odenmis_olur_ve_ayni_anahtar_mukerrer_uretmez(): void
    {
        $apt = $this->site(1);
        $unit = $this->unit($apt);
        $unit->update(['credit_balance' => 1500]);

        $this->assertSame('created', $this->finance->accrue($unit, $this->fields(100000), 'k1'));
        $this->assertSame('exists', $this->finance->accrue($unit, $this->fields(100000), 'k1'));
        $this->assertSame('created', $this->finance->accrue($unit, $this->fields(100000), 'k2'));

        $debts = Debt::orderBy('id')->get();
        $this->assertCount(2, $debts);
        $this->assertSame(Debt::PAID, $debts[0]->status);
        $this->assertEquals(500, (float) $debts[1]->paid_amount); // kalan avans kadar
        $this->assertEquals(0, (float) $unit->fresh()->credit_balance); // eksiye düşmez
        $this->assertLedgerBalanced($apt);
    }

    public function test_tahsilat_once_secilen_sonra_en_eski_borcu_kapatir_fazlasi_avansa_gider(): void
    {
        $apt = $this->site(1);
        $unit = $this->unit($apt);
        $this->finance->accrue($unit, $this->fields(10000, '2026-03-31', 'Mart'));
        $this->finance->accrue($unit, $this->fields(10000, '2026-01-31', 'Ocak'));
        $this->finance->accrue($unit, $this->fields(10000, '2026-02-28', 'Şubat'));
        $march = Debt::where('description', 'Mart')->first();

        $tx = $this->finance->collect($unit, $this->cashbox($apt), 25000, null, null, $march);

        $this->assertSame(Debt::PAID, $march->fresh()->status);
        $this->assertSame(Debt::PAID, Debt::where('description', 'Ocak')->first()->status);
        $feb = Debt::where('description', 'Şubat')->first();
        $this->assertEquals(50, (float) $feb->remaining_amount);
        $this->assertSame(Debt::PENDING, $feb->status);
        $this->assertEquals(250, (float) $this->cashbox($apt)->balance);
        $this->assertEquals(250, (float) $tx->applied_to_debts);

        $this->finance->collect($unit, $this->cashbox($apt), 8000);
        $this->assertSame(Debt::PAID, $feb->fresh()->status);
        $this->assertEquals(30, (float) $unit->fresh()->credit_balance);
        $this->assertTrue(LedgerEntry::where('account_name', LedgerEntry::UNIT_ADVANCE)->where('credit', 30)->exists());
        $this->assertLedgerBalanced($apt);
    }

    public function test_kismi_odeme_partial_gecikmis_borc_overdue_gorunur(): void
    {
        $apt = $this->site(1);
        $unit = $this->unit($apt);
        $this->finance->accrue($unit, $this->fields(10000, now()->addMonth()->toDateString()));
        $this->finance->collect($unit, $this->cashbox($apt), 4000);
        $this->assertSame('partial', Debt::first()->displayStatus());

        $this->finance->accrue($unit, $this->fields(10000, now()->subDays(3)->toDateString()));
        $this->assertSame('overdue', Debt::orderByDesc('id')->first()->displayStatus());
    }

    public function test_odemesi_olan_borc_silinemez_odemesiz_borc_ters_kayitla_silinir(): void
    {
        $apt = $this->site(1);
        $unit = $this->unit($apt);
        $this->finance->accrue($unit, $this->fields(10000, '2026-01-31', 'Ödenen'));
        $this->finance->collect($unit, $this->cashbox($apt), 1000);
        $this->finance->accrue($unit, $this->fields(5000, '2026-02-28', 'Ödenmeyen'));

        try {
            $this->finance->deleteDebt(Debt::where('description', 'Ödenen')->first());
            $this->fail('Ödemesi olan borç silinmemeli');
        } catch (FinanceException $e) {
            $this->assertStringContainsString('silinemez', $e->getMessage());
        }

        $this->finance->deleteDebt(Debt::where('description', 'Ödenmeyen')->first());
        $this->assertNull(Debt::where('description', 'Ödenmeyen')->first());
        $this->assertTrue(LedgerEntry::where('entry_type', 'accrual_reversal')->exists());
        $this->assertLedgerBalanced($apt);
    }

    public function test_borcu_olmayan_dairenin_tahsilati_tamamen_avans_olur(): void
    {
        $apt = $this->site(1);
        $unit = $this->unit($apt);

        $this->finance->collect($unit, $this->cashbox($apt), 20000, 'Peşin aidat', null, null, 'advance');

        $this->assertEquals(200, (float) $unit->fresh()->credit_balance);
        $this->assertFalse(LedgerEntry::where('account_name', LedgerEntry::INCOME)->exists());
        $this->assertLedgerBalanced($apt);
    }

    public function test_yetersiz_bakiyede_gider_virman_ve_cikis_reddedilir_ayar_acikken_eksiye_duser(): void
    {
        $apt = $this->site(1);
        $box = $this->cashbox($apt);
        $bank = $apt->cashboxes()->create(['name' => 'Banka', 'type' => 'bank', 'balance' => 0]);
        $expense = $apt->expenses()->create(['description' => 'Elektrik', 'amount' => 100, 'total_amount' => 120]);

        foreach ([
            fn () => $this->finance->generalTransaction($box, 'expense', 5000, 'Çıkış'),
            fn () => $this->finance->transfer($box, $bank, 5000),
            fn () => $this->finance->approveExpense($expense, $box),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Yetersiz bakiyede işlem reddedilmeliydi');
            } catch (FinanceException $e) {
                $this->assertStringContainsString('yetersiz', $e->getMessage());
            }
        }
        $this->assertEquals(0, (float) $box->fresh()->balance);

        $apt->update(['allow_negative_cashbox' => true]);
        $this->finance->approveExpense($expense, $box);
        $this->assertEquals(-120, (float) $box->fresh()->balance);
        $this->assertSame('approved', $expense->fresh()->status);
        $this->assertLedgerBalanced($apt);
    }

    public function test_virman_iki_kasayi_dengeli_gunceller(): void
    {
        $apt = $this->site(1);
        $box = $this->cashbox($apt);
        $bank = $apt->cashboxes()->create(['name' => 'Banka', 'type' => 'bank', 'balance' => 0]);
        $this->finance->generalTransaction($box, 'income', 50000, 'Bağış');

        $this->finance->transfer($box, $bank, 30000, 'Bankaya yatırıldı');

        $this->assertEquals(200, (float) $box->fresh()->balance);
        $this->assertEquals(300, (float) $bank->fresh()->balance);
        $this->assertSame(2, $apt->cashTransactions()->where('sub_type', 'like', 'transfer%')->count());
        $this->assertSame(2, $apt->auditLogs()->count()); // denetim kayıtları siteye bağlı
        $this->assertSame(2, $apt->auditLogs()->count()); // denetim kayıtları siteye bağlı
        $this->assertSame(2, $apt->auditLogs()->count()); // denetim kayıtları siteye bağlı
        $this->assertLedgerBalanced($apt);
    }

    public function test_aidat_motoru_ayni_donemde_mukerrer_yazmaz_m2_bazli_alanla_carpar(): void
    {
        $apt = $this->site(2);
        $this->unit($apt, 1)->update(['gross_area' => null]);
        $apt->duesDefinitions()->create(['name' => 'Aidat', 'amount' => 500, 'calculation' => 'fixed']);
        $apt->duesDefinitions()->create(['name' => 'Yakıt', 'amount' => 2.5, 'calculation' => 'sqm']);

        $first = $this->finance->generateDues($apt, '2026-02');
        $second = $this->finance->generateDues($apt, '2026-02');

        $this->assertSame(3, $first['created']); // 2 aidat + 1 yakıt (m²si olmayan atlanır)
        $this->assertSame(['A-2'], $first['skipped_no_area']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(3, $second['skipped']);
        $this->assertEquals(250, (float) Debt::where('description', '2026-02 - Yakıt')->value('amount'));
        $this->assertSame('2026-02-28', Debt::first()->due_date->toDateString()); // vade: dönemin son günü
        $this->assertLedgerBalanced($apt);
    }

    public function test_gecersiz_donem_reddedilir_ve_vade_ay_sonunu_asmaz(): void
    {
        $this->expectException(FinanceException::class);
        $this->assertSame('2024-02-29', FinanceService::dueDateForPeriod('2024-02', 31));
        $this->assertSame('2026-04-15', FinanceService::dueDateForPeriod('2026-04', 15));
        $this->finance->generateDues($this->site(1), '2026-13');
    }
}
