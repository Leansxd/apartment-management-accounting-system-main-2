<?php

namespace Tests\Feature;

use App\Services\FinanceService;
use App\Services\ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_islemlerden_sonra_sapma_yoktur_ve_elle_bozulan_bakiye_yakalanir(): void
    {
        $finance = app(FinanceService::class);
        $site = $this->site(1);
        $unit = $this->unit($site);
        $box = $this->cashbox($site);
        $finance->accrue($unit, ['description' => 'Aidat', 'amount' => 10000, 'due_date' => '2026-01-31']);
        $finance->collect($unit, $box, 15000);

        $this->assertSame([], app(ReconciliationService::class)->discrepancies($site));
        $this->artisan('app:reconcile')->assertExitCode(0);

        $box->update(['balance' => 1.00]);

        $issues = app(ReconciliationService::class)->discrepancies($site);
        $this->assertSame('Kasa bakiyesi', $issues[0]['check']);
        $this->artisan('app:reconcile')->assertExitCode(1);
    }
}
