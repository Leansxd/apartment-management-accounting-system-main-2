<?php

namespace Tests\Feature;

use App\Services\FinanceException;
use App\Services\FinanceService;
use App\Services\YearEndService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YearEndCloseTest extends TestCase
{
    use RefreshDatabase;

    public function test_gecen_yil_kapanir_sonuc_muhurlenir_ve_donem_kilitlenir(): void
    {
        $finance = app(FinanceService::class);
        $site = $this->site(1, $this->admin());
        $unit = $this->unit($site);
        $finance->accrue($unit, ['description' => 'Aidat', 'amount' => 30000, 'due_date' => '2025-06-01']);
        $this->travelTo('2026-02-01');

        $summary = app(YearEndService::class)->close($site, 2025);

        $this->assertSame('2025-12-31', $site->fresh()->closed_until->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'YEAR_END_CLOSE', 'reference' => 'year:2025']);
        $this->assertEquals(0.0, $summary['expense']);
        $this->expectException(FinanceException::class);
        app(YearEndService::class)->close($site->fresh(), 2025);
    }

    public function test_icinde_bulunulan_yil_kapatilamaz(): void
    {
        $site = $this->site(1, $this->admin());

        $this->expectException(FinanceException::class);
        app(YearEndService::class)->close($site, (int) today()->format('Y'));
    }
}
