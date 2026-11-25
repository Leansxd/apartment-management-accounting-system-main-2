<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Cashbox;
use App\Models\Expense;
use App\Models\User;
use App\Services\FinanceException;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tedarikçi faturasının (giderin) parça parça ödenmesi */
class PartialExpensePaymentTest extends TestCase
{
    use RefreshDatabase;

    private FinanceService $finance;

    private User $manager;

    private Apartment $site;

    private Cashbox $box;

    private Expense $expense;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finance = app(FinanceService::class);
        $this->manager = $this->admin();
        $this->site = $this->site(1, $this->manager);
        $this->box = $this->cashbox($this->site);
        $this->finance->recordOpeningBalance($this->box, 500000);
        $this->expense = $this->site->expenses()->create([
            'description' => 'Asansör bakımı', 'amount' => 1000, 'vat_rate' => 0, 'total_amount' => 1000,
        ]);
    }

    public function test_kismi_odemede_gider_bekler_kasa_ve_yevmiye_odenen_kadar_degisir(): void
    {
        $this->finance->payExpense($this->expense, $this->box, 40000, $this->manager);

        $expense = $this->expense->fresh();
        $this->assertSame('pending', $expense->status);
        $this->assertEquals(400.00, (float) $expense->paid_amount);
        $this->assertEquals(600.00, $expense->remainingAmount());
        $this->assertEquals(4600.00, (float) $this->box->fresh()->balance);
        $this->assertLedgerBalanced($this->site);
    }

    public function test_kalan_odenince_gider_odendi_olur(): void
    {
        $this->finance->payExpense($this->expense, $this->box, 40000, $this->manager);
        $this->finance->payExpense($this->expense, $this->box, 60000, $this->manager);

        $this->assertSame('approved', $this->expense->fresh()->status);
        $this->assertEquals(4000.00, (float) $this->box->fresh()->balance);
    }

    public function test_son_parca_odemede_denetim_kaydi_onay_olarak_yazilir(): void
    {
        $this->finance->payExpense($this->expense, $this->box, 40000, $this->manager);
        $last = $this->finance->payExpense($this->expense, $this->box, 60000, $this->manager);

        $this->assertStringContainsString('son ödeme', $last->description);
        $this->assertDatabaseHas('audit_logs', ['action' => 'APPROVE_EXPENSE', 'reference' => "expense:{$this->expense->id}"]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAY_EXPENSE', 'reference' => "expense:{$this->expense->id}"]);
    }

    public function test_kalandan_fazla_odeme_reddedilir(): void
    {
        $this->finance->payExpense($this->expense, $this->box, 40000, $this->manager);

        $this->expectException(FinanceException::class);
        $this->finance->payExpense($this->expense, $this->box, 60001, $this->manager);
    }

    public function test_kismi_odeme_iptal_edilince_odenen_tutar_geri_duser(): void
    {
        $this->finance->payExpense($this->expense, $this->box, 40000, $this->manager);
        $second = $this->finance->payExpense($this->expense, $this->box, 60000, $this->manager);

        $this->finance->reverse($second, 'Yanlış tutar');

        $expense = $this->expense->fresh();
        $this->assertSame('pending', $expense->status);
        $this->assertEquals(400.00, (float) $expense->paid_amount);
        $this->assertLedgerBalanced($this->site);
    }

    public function test_formdan_tutar_verilmezse_kalanin_tamami_odenir(): void
    {
        $this->actingAs($this->manager)->post(route('site.expenses.approve', [$this->site, $this->expense]), [
            'cashbox_id' => $this->box->id,
        ])->assertSessionHas('status', 'Gider ödendi ve kasadan düşüldü.');

        $this->assertSame('approved', $this->expense->fresh()->status);
    }

    public function test_formdan_kismi_odeme_kalan_tutari_bildirir(): void
    {
        $this->actingAs($this->manager)->post(route('site.expenses.approve', [$this->site, $this->expense]), [
            'cashbox_id' => $this->box->id, 'amount' => 250,
        ])->assertSessionHas('status', fn (string $message) => str_contains($message, 'Kısmi ödeme') && str_contains($message, '750'));
    }

    public function test_kismi_odenmis_gider_duzenlenemez_ve_silinemez(): void
    {
        $this->finance->payExpense($this->expense, $this->box, 40000, $this->manager);
        $this->actingAs($this->manager);

        $this->delete(route('site.expenses.destroy', [$this->site, $this->expense]))->assertSessionHas('error');
        $this->put(route('site.expenses.update', [$this->site, $this->expense]), [
            'amount' => 2000, 'vat_rate' => 0, 'description' => 'Değişti',
        ])->assertSessionHas('error');

        $this->assertSame('Asansör bakımı', $this->expense->fresh()->description);
    }

    public function test_bilanco_borcu_ve_firma_toplami_odenen_tutara_gore_hesaplanir(): void
    {
        $vendor = $this->site->vendors()->create(['name' => 'Asansör A.Ş.']);
        $this->expense->update(['vendor_id' => $vendor->id]);
        $this->finance->payExpense($this->expense, $this->box, 40000, $this->manager);

        $this->actingAs($this->manager)->get(route('site.vendors.index', $this->site))->assertSee('400,00');
        $this->actingAs($this->manager)->get(route('site.accounting.balance-sheet', $this->site))->assertSee('600,00');
    }
}
