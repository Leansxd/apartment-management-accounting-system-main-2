<?php

namespace Tests\Feature;

use App\Models\CashTransaction;
use App\Models\Debt;
use App\Services\FinanceException;
use App\Services\FinanceService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingExtensionsTest extends TestCase
{
    use RefreshDatabase;

    private FinanceService $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finance = app(FinanceService::class);
    }

    private function fields(int $amount, string $due, string $desc = 'Aidat'): array
    {
        return ['description' => $desc, 'amount' => $amount, 'due_date' => $due];
    }

    public function test_allocate_toplami_kurusu_kurusuna_korur(): void
    {
        $this->assertSame([34, 33, 33], array_values(Money::allocate(100, [1, 1, 1])));
        $parts = Money::allocate(100000, [3, 3, 4]);
        $this->assertSame(100000, array_sum($parts));
        $this->assertSame([2 => 40000], array_intersect_key($parts, [2 => 1]));
        $this->assertSame([5, 5], array_values(Money::allocate(10, [0, 0])));
    }

    public function test_gecikme_zammi_aylik_hesaplanir_ve_tekrar_calistirinca_mukerrer_yazmaz(): void
    {
        $apt = $this->site(1);
        $apt->update(['late_fee_enabled' => true, 'late_fee_rate' => 5, 'late_fee_grace_days' => 0]);
        $unit = $this->unit($apt);
        $this->finance->accrue($unit, $this->fields(100000, '2026-01-10'));
        $asOf = CarbonImmutable::parse('2026-04-15'); // 3 tam ay

        $this->assertCount(3, $this->finance->pendingLateFees($apt->fresh(), $asOf));
        $first = $this->finance->runLateFees($apt->fresh(), $asOf);
        $second = $this->finance->runLateFees($apt->fresh(), $asOf);

        $this->assertSame(3, $first['created']);
        $this->assertSame(15000, $first['total']); // 3 × %5 × 1.000 ₺
        $this->assertSame(0, $second['created']);
        $this->assertSame(3, Debt::where('kind', 'late_fee')->count());
        $this->assertSame('Gecikme Zammı', Debt::where('kind', 'late_fee')->first()->category_name);
        $this->assertLedgerBalanced($apt);

        // Gecikme zammının kendisine zam işletilmez
        $this->assertSame(0, $this->finance->pendingLateFees($apt->fresh(), CarbonImmutable::parse('2026-04-16'))->where('debt.kind', 'late_fee')->count());
    }

    public function test_gecikme_zammi_kapaliyken_ve_ek_sure_icindeyken_islemez(): void
    {
        $apt = $this->site(1);
        $this->finance->accrue($this->unit($apt), $this->fields(100000, '2026-01-10'));
        $asOf = CarbonImmutable::parse('2026-03-20');

        $this->assertCount(0, $this->finance->pendingLateFees($apt->fresh(), $asOf)); // kapalı
        $apt->update(['late_fee_enabled' => true, 'late_fee_grace_days' => 45]);
        $this->assertCount(0, $this->finance->pendingLateFees($apt->fresh(), $asOf)); // ek süre dolmadı
    }

    public function test_tahsilat_iptali_borcu_acar_avansi_geri_alir_kasayi_dusurur(): void
    {
        $apt = $this->site(1);
        $unit = $this->unit($apt);
        $box = $this->cashbox($apt);
        $this->finance->accrue($unit, $this->fields(10000, '2026-01-31'));
        $tx = $this->finance->collect($unit, $box, 15000); // 100 borç + 50 avans

        $this->assertEquals(50, (float) $unit->fresh()->credit_balance);
        $reversal = $this->finance->reverse($tx, 'Hatalı giriş');

        $this->assertEquals(0, (float) $box->fresh()->balance);
        $this->assertEquals(0, (float) $unit->fresh()->credit_balance);
        $debt = Debt::first();
        $this->assertSame(Debt::PENDING, $debt->status);
        $this->assertEquals(100, (float) $debt->remaining_amount);
        $this->assertEquals(0, (float) $debt->paid_amount);
        $this->assertTrue($tx->fresh()->isReversed());
        $this->assertSame('reversal', $reversal->sub_type);
        $this->assertEquals(0.0, (float) $apt->cashTransactions()->operating()->where('type', 'income')->sum('amount')); // toplamlara girmez
        $this->assertLedgerBalanced($apt);

        $this->expectException(FinanceException::class); // ikinci kez iptal edilemez
        $this->finance->reverse($tx->fresh(), 'tekrar');
    }

    public function test_avansi_baska_borca_mahsup_edilmis_tahsilat_iptal_edilemez(): void
    {
        $apt = $this->site(1);
        $unit = $this->unit($apt);
        $tx = $this->finance->collect($unit, $this->cashbox($apt), 20000); // tamamı avans
        $this->finance->accrue($unit, $this->fields(20000, '2026-06-30')); // avans mahsup edildi

        $this->expectException(FinanceException::class);
        $this->expectExceptionMessage('mahsup');
        $this->finance->reverse($tx, 'yanlış');
    }

    public function test_gider_odemesi_iptali_gideri_tekrar_onaya_dusurur(): void
    {
        $apt = $this->site(1);
        $box = $this->cashbox($apt);
        $this->finance->generalTransaction($box, 'income', 100000, 'Kasa girişi');
        $expense = $apt->expenses()->create(['description' => 'Elektrik', 'amount' => 100, 'total_amount' => 120]);
        $this->finance->approveExpense($expense, $box);
        $this->assertEquals(880, (float) $box->fresh()->balance);

        $tx = CashTransaction::where('sub_type', 'invoice')->first();
        $this->finance->reverse($tx, 'Fatura iptal');

        $this->assertEquals(1000, (float) $box->fresh()->balance);
        $this->assertSame('pending', $expense->fresh()->status);
        $this->finance->approveExpense($expense->fresh(), $box); // yeniden onaylanabilir
        $this->assertLedgerBalanced($apt);
    }

    public function test_virman_iptal_edilemez(): void
    {
        $apt = $this->site(1);
        $box = $this->cashbox($apt);
        $bank = $apt->cashboxes()->create(['name' => 'Banka', 'type' => 'bank']);
        $this->finance->generalTransaction($box, 'income', 10000, 'Giriş');
        $this->finance->transfer($box, $bank, 5000);

        $this->expectException(FinanceException::class);
        $this->finance->reverse(CashTransaction::where('sub_type', 'transfer_out')->first(), 'x');
    }

    public function test_avans_iadesi_kasadan_cikar_ve_avansi_dusurur(): void
    {
        $apt = $this->site(1);
        $unit = $this->unit($apt);
        $box = $this->cashbox($apt);
        $this->finance->collect($unit, $box, 30000);

        $this->finance->refundAdvance($unit, $box, 10000);
        $this->assertEquals(200, (float) $unit->fresh()->credit_balance);
        $this->assertEquals(200, (float) $box->fresh()->balance);
        $this->assertLedgerBalanced($apt);

        try {
            $this->finance->refundAdvance($unit->fresh(), $box, 50000);
            $this->fail('Avansı aşan iade reddedilmeliydi');
        } catch (FinanceException $e) {
            $this->assertStringContainsString('aşamaz', $e->getMessage());
        }

        // İadeyi iptal etmek avansı geri verir
        $this->finance->reverse(CashTransaction::where('sub_type', 'refund')->first(), 'yanlış iade');
        $this->assertEquals(300, (float) $unit->fresh()->credit_balance);
    }

    public function test_taksitlendirme_toplami_korur_ve_ters_kayit_atar(): void
    {
        $apt = $this->site(1);
        $unit = $this->unit($apt);
        $this->finance->accrue($unit, $this->fields(100000, '2026-01-31', 'Demirbaş'));
        $this->finance->collect($unit, $this->cashbox($apt), 10000); // 100 ₺ ödendi, 900 kaldı
        $debt = Debt::first();

        $list = $this->finance->installments($debt, 3, '2026-03-01', 1);

        $this->assertCount(3, $list);
        $this->assertSame(90000, $list->sum(fn ($d) => Money::cents($d->amount)));
        $this->assertEquals([300, 300, 300], $list->map(fn ($d) => (float) $d->amount)->all());
        $this->assertSame(['2026-03-01', '2026-04-01', '2026-05-01'], $list->map(fn ($d) => $d->due_date->toDateString())->all());
        $this->assertSame('installment', $list[0]->kind);
        $this->assertSame($debt->id, $list[0]->parent_debt_id);
        $this->assertEquals(100, (float) $debt->fresh()->amount); // özgün borçta yalnızca ödenen kalır
        $this->assertEquals(0, (float) $debt->fresh()->remaining_amount);
        $this->assertEquals(900, (float) $apt->debts()->where('status', 'pending')->sum('remaining_amount'));
        $this->assertLedgerBalanced($apt);

        $this->expectException(FinanceException::class); // ödenmiş/yapılandırılmış borç tekrar taksitlenmez
        $this->finance->installments($debt->fresh(), 2, '2026-06-01');
    }

    public function test_icra_isareti_konur_ve_kaldirilir(): void
    {
        $apt = $this->site(1);
        $this->finance->accrue($this->unit($apt), $this->fields(5000, '2026-01-31'));
        $debt = Debt::first();

        $this->finance->setLegal($debt, true, 'Dosya no 2026/12');
        $this->assertSame('icra', $debt->fresh()->legal_status);
        $this->finance->setLegal($debt->fresh(), false);
        $this->assertNull($debt->fresh()->legal_status);
    }

    public function test_avans_mahsubu_manuel_calisir(): void
    {
        $apt = $this->site(1);
        $unit = $this->unit($apt);
        $this->finance->accrue($unit, $this->fields(10000, '2026-02-28'));
        $unit->update(['credit_balance' => 60]); // sonradan eklenmiş avans

        $this->assertSame(6000, $this->finance->applyAdvance($unit));
        $this->assertEquals(40, (float) Debt::first()->remaining_amount);
        $this->assertEquals(0, (float) $unit->fresh()->credit_balance);
    }
}
