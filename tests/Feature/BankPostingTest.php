<?php

namespace Tests\Feature;

use App\Models\BankStatementLine;
use App\Models\CashTransaction;
use App\Models\LedgerEntry;
use App\Services\BankImportService;
use App\Services\FinanceException;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Banka satırının işlenmesi: satır tarihi ve mevcut gidere bağlama */
class BankPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_satir_kendi_tarihinde_kasa_hareketi_ve_yevmiye_yazar(): void
    {
        $site = $this->site(1);
        $box = $this->cashbox($site);
        $bank = app(BankImportService::class);
        $bank->import($site, $box, [['date' => '2026-01-15', 'description' => 'Havale', 'amount' => 25000, 'reference' => null]]);

        $bank->post(BankStatementLine::first());

        $tx = CashTransaction::firstOrFail();
        $this->assertSame('2026-01-15', $tx->created_at->toDateString());
        $this->assertSame(['2026-01-15'], LedgerEntry::where('reference', "cash_tx:{$tx->id}")->get()
            ->map(fn ($e) => $e->created_at->toDateString())->unique()->values()->all());
    }

    public function test_kapali_doneme_dusen_satir_islenmez(): void
    {
        $site = $this->site(1);
        $site->update(['closed_until' => '2026-01-31']);
        $bank = app(BankImportService::class);
        $bank->import($site, $this->cashbox($site), [['date' => '2026-01-15', 'description' => 'Havale', 'amount' => 25000, 'reference' => null]]);

        $this->expectException(FinanceException::class);
        $bank->post(BankStatementLine::first());
    }

    public function test_giden_satir_mevcut_gidere_baglanir_ve_cift_gider_olusmaz(): void
    {
        $site = $this->site(1);
        $box = $this->cashbox($site);
        app(FinanceService::class)->recordOpeningBalance($box, 500000);
        $expense = $site->expenses()->create(['description' => 'Asansör', 'amount' => 1000, 'vat_rate' => 0, 'total_amount' => 1000]);
        $bank = app(BankImportService::class);
        $bank->import($site, $box, [['date' => '2026-01-15', 'description' => 'Asansor EFT', 'amount' => -100000, 'reference' => null]]);

        $bank->post(BankStatementLine::first(), null, null, null, $expense);

        $this->assertSame('approved', $expense->fresh()->status);
        $this->assertEquals(4000.00, (float) $box->fresh()->balance);
        $this->assertSame(1, CashTransaction::where('sub_type', 'invoice')->count());
        $this->assertSame(0, CashTransaction::where('sub_type', 'general')->count());
        $this->assertLedgerBalanced($site);
    }

    public function test_ekstrede_karsiligi_olmayan_kasa_hareketi_listelenir(): void
    {
        $site = $this->site(1);
        $box = $this->cashbox($site);
        $finance = app(FinanceService::class);
        $bank = app(BankImportService::class);
        $bank->import($site, $box, [['date' => now()->toDateString(), 'description' => 'Havale', 'amount' => 25000, 'reference' => null]]);
        $bank->post(BankStatementLine::first());
        $finance->generalTransaction($box, 'income', 5000, 'Bankada görünmeyen');

        $items = $bank->unmatchedCashTransactions($box);

        $this->assertSame(['Bankada görünmeyen'], $items->pluck('description')->all());
    }
}
