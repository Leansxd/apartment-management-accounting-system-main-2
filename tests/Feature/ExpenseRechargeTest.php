<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Unit;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Gider girilirken masrafın (ör. teknik servis) bir daireye borç olarak yansıtılması */
class ExpenseRechargeTest extends TestCase
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
    }

    /** @param  array<string, mixed>  $overrides */
    private function storeExpense(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->manager)->post(route('site.expenses.store', $this->site), $overrides + [
            'description' => 'Gider açma servisi', 'invoice_no' => 'TS-12', 'amount' => 1000, 'vat_rate' => 20,
            'recharge_unit_id' => $this->flat->id, 'recharge_responsible' => 'tenant', 'recharge_due_date' => '2026-12-31',
        ]);
    }

    public function test_gider_kaydedilirken_daireye_kdv_dahil_borc_yazilir(): void
    {
        $this->storeExpense()->assertSessionHasNoErrors()->assertSessionHas('status', fn (string $m) => str_contains($m, 'A-1 dairesine'));

        $debt = Debt::sole();
        $this->assertSame($this->flat->id, $debt->unit_id);
        $this->assertSame(Expense::sole()->id, $debt->expense_id);
        $this->assertEquals(1200.00, (float) $debt->amount);
        $this->assertSame('tenant', $debt->responsible);
        $this->assertSame('Masraf Yansıtma', $debt->category_name);
        $this->assertSame('Gider açma servisi (Fatura: TS-12)', $debt->description);
        $this->assertLedgerBalanced($this->site);
    }

    public function test_daireden_farkli_tutar_alinabilir(): void
    {
        $this->storeExpense(['recharge_amount' => 750])->assertSessionHasNoErrors();

        $this->assertEquals(750.00, (float) Debt::sole()->amount);
    }

    public function test_daire_secilmezse_borc_yazilmaz(): void
    {
        $this->storeExpense(['recharge_unit_id' => ''])->assertSessionHasNoErrors();

        $this->assertSame(1, Expense::count());
        $this->assertSame(0, Debt::count());
    }

    public function test_baska_sitenin_dairesine_yansitilamaz(): void
    {
        $foreign = $this->unit($this->site(1, $this->manager));

        $this->storeExpense(['recharge_unit_id' => $foreign->id])->assertSessionHasErrors('recharge_unit_id');
        $this->assertSame(0, Expense::count());
    }

    public function test_ayni_gider_ikinci_kez_yansitilamaz(): void
    {
        $this->storeExpense();

        $this->expectExceptionMessage('zaten bir daireye yansıtılmış');
        app(FinanceService::class)->rechargeExpense(Expense::sole(), $this->unit($this->site, 1), 100, '2026-12-31');
    }

    public function test_gider_silinince_tahsil_edilmemis_borc_da_silinir(): void
    {
        $this->storeExpense();

        $this->actingAs($this->manager)->delete(route('site.expenses.destroy', [$this->site, Expense::sole()]))
            ->assertSessionHas('status', 'Gider ve daireye yansıtılan borcu silindi.');

        $this->assertSame(0, Debt::count());
        $this->assertLedgerBalanced($this->site);
    }

    public function test_borcu_tahsil_edilen_gider_silinemez(): void
    {
        $this->storeExpense();
        app(FinanceService::class)->collect($this->flat, $this->cashbox($this->site), 120000);

        $this->actingAs($this->manager)->delete(route('site.expenses.destroy', [$this->site, Expense::sole()]))->assertSessionHas('error');

        $this->assertSame(1, Expense::count());
    }

    public function test_gider_listesinde_yansitilan_daire_gorunur(): void
    {
        $this->storeExpense();

        $this->actingAs($this->manager)->get(route('site.expenses.index', $this->site))
            ->assertSee('Daireye yansıtıldı')
            ->assertSee('href="'.route('site.units.show', [$this->site, $this->flat]).'"', false);
    }
}
