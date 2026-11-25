<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Cashbox;
use App\Models\CashTransaction;
use App\Models\Expense;
use App\Models\User;
use App\Services\FinanceException;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ForeignExpensePaymentTest extends TestCase
{
    use RefreshDatabase;

    private FinanceService $finance;

    private User $manager;

    private Apartment $site;

    private Cashbox $usdBox;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response('', 500)]);
        $this->finance = app(FinanceService::class);
        $this->manager = $this->admin();
        $this->site = $this->site(1, $this->manager);
        $this->usdBox = $this->site->cashboxes()->create(['name' => 'Dolar Kasası', 'type' => 'cash', 'currency' => 'USD']);
        $this->finance->recordOpeningBalance($this->usdBox, 100000, null, '30'); // 1.000 USD, maliyet 30
        $this->usdBox->refresh();
    }

    private function expense(float $total = 4000): Expense
    {
        return $this->site->expenses()->create(['description' => 'Asansör bakımı', 'amount' => $total, 'vat_rate' => 0, 'total_amount' => $total]);
    }

    public function test_guncel_kur_maliyetten_yuksekse_kur_kari_yazilir(): void
    {
        $expense = $this->expense();

        $this->finance->approveExpense($expense, $this->usdBox, $this->manager, '40');

        $this->usdBox->refresh();
        $this->assertEquals(900, (float) $this->usdBox->currency_balance); // 4.000 TL / 40 = 100 USD
        $this->assertEquals(27000, (float) $this->usdBox->balance);        // 900 USD × 30 maliyet
        $this->assertSame('approved', $expense->fresh()->status);
        $this->assertEquals(1000, (float) CashTransaction::where('sub_type', 'fx_difference')->where('type', 'income')->sole()->amount);
        $this->assertLedgerBalanced($this->site);

        $payment = CashTransaction::where('sub_type', 'invoice')->sole();
        $this->assertEquals(4000, (float) $payment->amount);
        $this->assertEquals(100, (float) $payment->original_amount);
        $this->assertSame('USD', $payment->currency);
        $this->assertEquals(4000, (float) $this->site->ledgerEntries()->where('account_name', 'Gider Hesabı')->sum('debit'));
    }

    public function test_guncel_kur_maliyetten_dusukse_kur_zarari_yazilir(): void
    {
        $this->finance->approveExpense($this->expense(2000), $this->usdBox, $this->manager, '20');

        $this->usdBox->refresh();
        $this->assertEquals(900, (float) $this->usdBox->currency_balance);
        $this->assertEquals(27000, (float) $this->usdBox->balance);
        $this->assertEquals(1000, (float) CashTransaction::where('sub_type', 'fx_difference')->where('type', 'expense')->sole()->amount);
        $this->assertLedgerBalanced($this->site);
    }

    public function test_kur_yoksa_odeme_yapilmaz(): void
    {
        $expense = $this->expense();

        $this->expectException(FinanceException::class);
        $this->expectExceptionMessage('kur girilmelidir');

        try {
            $this->finance->approveExpense($expense, $this->usdBox, $this->manager);
        } finally {
            $this->assertSame('pending', $expense->fresh()->status);
        }
    }

    public function test_doviz_yetmezse_odeme_yapilmaz(): void
    {
        $expense = $this->expense(50000); // 1.250 USD gerekir

        $this->expectException(FinanceException::class);
        $this->expectExceptionMessage('bakiyesi yetersiz');

        try {
            $this->finance->approveExpense($expense, $this->usdBox, $this->manager, '40');
        } finally {
            $this->assertEquals(1000, (float) $this->usdBox->fresh()->currency_balance);
            $this->assertSame(0, CashTransaction::where('sub_type', 'fx_difference')->count());
        }
    }

    public function test_iptal_doviz_mevcudunu_geri_verir_gider_bekler(): void
    {
        $expense = $this->expense();
        $this->finance->approveExpense($expense, $this->usdBox, $this->manager, '40');

        $this->finance->reverse(CashTransaction::where('sub_type', 'invoice')->sole(), 'Hatalı');

        $this->assertEquals(1000, (float) $this->usdBox->fresh()->currency_balance);
        $this->assertSame('pending', $expense->fresh()->status);
        $this->assertLedgerBalanced($this->site);
    }

    public function test_tl_kasada_kur_yok_sayilir(): void
    {
        $box = $this->cashbox($this->site);
        $this->finance->generalTransaction($box, 'income', 500000, 'Bağış');

        $this->finance->approveExpense($this->expense(), $box->fresh(), $this->manager, '40');

        $this->assertEquals(1000, (float) $box->fresh()->balance);
        $this->assertSame(0, CashTransaction::where('sub_type', 'fx_difference')->count());
    }

    public function test_avans_iadesi_doviz_kasasindan_yapilamaz(): void
    {
        $flat = $this->unit($this->site);
        $flat->update(['credit_balance' => 100]);

        $this->expectExceptionMessage('yalnızca TL kasadan');

        $this->finance->refundAdvance($flat, $this->usdBox, 10000);
    }

    public function test_formdan_onaylanir_ve_doviz_kasasi_listede_cikar(): void
    {
        $expense = $this->expense();
        $this->actingAs($this->manager);

        $this->get(route('site.expenses.index', $this->site))->assertOk()->assertSee('Dolar Kasası (USD)')->assertSee('data-expense-rate', false);
        $this->post(route('site.expenses.approve', [$this->site, $expense]), ['cashbox_id' => $this->usdBox->id, 'exchange_rate' => '40'])->assertSessionHas('status');

        $this->assertSame('approved', $expense->fresh()->status);
    }

    public function test_formda_kur_eksikse_hata_gosterilir(): void
    {
        $expense = $this->expense();

        $this->actingAs($this->manager)->post(route('site.expenses.approve', [$this->site, $expense]), ['cashbox_id' => $this->usdBox->id])->assertSessionHas('error');

        $this->assertSame('pending', $expense->fresh()->status);
    }

    public function test_gecersiz_kur_reddedilir(): void
    {
        $expense = $this->expense();

        $this->actingAs($this->manager)->post(route('site.expenses.approve', [$this->site, $expense]), ['cashbox_id' => $this->usdBox->id, 'exchange_rate' => '0'])->assertSessionHasErrors('exchange_rate');
    }

    public function test_bordro_sayfasi_doviz_kasasini_listeler(): void
    {
        $this->actingAs($this->manager)->get(route('site.payroll.index', $this->site))->assertOk();
    }
}
