<?php

namespace Tests\Feature;

use App\Services\DashboardService;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    public function test_kasa_gecmisi_acilis_bakiyesini_girildigi_aydan_once_saymaz(): void
    {
        $site = $this->site(1);
        app(FinanceService::class)->recordOpeningBalance($this->cashbox($site), 100000);

        $history = app(DashboardService::class)->site($site)['balanceHistory'];

        $this->assertEquals(1000, end($history)['value']);
        $this->assertEquals(0, $history[count($history) - 2]['value']);
    }

    use RefreshDatabase;

    private function finance(): FinanceService
    {
        return app(FinanceService::class);
    }

    public function test_gunluk_akis_tahsilat_tahakkuk_gider_ve_adetleri_ayri_verir(): void
    {
        $this->travelTo('2026-05-15 12:00:00');
        $manager = $this->admin();
        $apartment = $this->site(2, $manager);
        $cashbox = $this->cashbox($apartment);
        $this->finance()->accrue($this->unit($apartment), ['description' => 'Aidat', 'amount' => 100000, 'due_date' => '2026-06-01']);
        $this->finance()->accrue($this->unit($apartment, 1), ['description' => 'Aidat', 'amount' => 50000, 'due_date' => '2026-06-01']);
        $this->finance()->collect($this->unit($apartment), $cashbox, 40000);
        $this->finance()->generalTransaction($cashbox->fresh(), 'expense', 15000, 'Temizlik');

        $daily = $this->actingAs($manager)->get(route('site.dashboard', $apartment))->assertOk()->viewData('daily');

        $today = end($daily);
        $this->assertSame(400.0, $today['paid']);
        $this->assertSame(1, $today['paid_count']);
        $this->assertSame(1500.0, $today['invoiced']);
        $this->assertSame(2, $today['invoiced_count']);
        $this->assertSame(150.0, $today['expense']);
        $this->assertTrue($today['is_today']);
        $this->assertSame('15 Mayıs 2026, Cuma', $today['full']);
        $this->assertCount(30, $daily);
    }

    public function test_iptal_edilen_tahsilat_ve_virman_gunluk_akisa_girmez(): void
    {
        $this->travelTo('2026-05-15 12:00:00');
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $cashbox = $this->cashbox($apartment);
        $bank = $apartment->cashboxes()->create(['name' => 'Banka', 'type' => 'bank', 'balance' => 0]);
        $reversed = $this->finance()->collect($this->unit($apartment), $cashbox, 30000);
        $this->finance()->reverse($reversed, 'Hatalı giriş');
        $this->finance()->generalTransaction($cashbox->fresh(), 'income', 50000, 'Gelir');
        $this->finance()->transfer($cashbox->fresh(), $bank, 20000);

        $daily = $this->actingAs($manager)->get(route('site.dashboard', $apartment))->viewData('daily');

        $this->assertSame(500.0, end($daily)['paid']);
        $this->assertSame(0.0, end($daily)['expense']);
    }

    public function test_grafik_seri_toplamlarini_ve_gunleri_gosterir(): void
    {
        $this->travelTo('2026-05-15 12:00:00');
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $this->finance()->accrue($this->unit($apartment), ['description' => 'Aidat', 'amount' => 100000, 'due_date' => '2026-06-01']);
        $this->finance()->collect($this->unit($apartment), $this->cashbox($apartment), 25000);

        $this->actingAs($manager)->get(route('site.dashboard', $apartment))
            ->assertOk()
            ->assertSee('data-series-toggle', false)
            ->assertSee('15 Mayıs 2026, Cuma')
            ->assertSee('250,00 ₺')
            ->assertSee('1.000,00 ₺');
    }

    public function test_portfoy_grafigi_erisilen_siteleri_toplar_baskasini_saymaz(): void
    {
        $this->travelTo('2026-05-15 12:00:00');
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $foreign = $this->site(1);
        $this->finance()->generalTransaction($this->cashbox($apartment), 'income', 10000, 'Kendi');
        $this->finance()->generalTransaction($this->cashbox($foreign), 'income', 90000, 'Yabancı');

        $daily = $this->actingAs($manager)->get(route('dashboard'))->assertOk()->viewData('daily');

        $this->assertSame(100.0, end($daily)['paid']);
    }

    public function test_verisi_olmayan_sitede_grafik_hatasiz_acilir(): void
    {
        $manager = $this->admin();

        $this->actingAs($manager)->get(route('site.dashboard', $this->site(0, $manager)))->assertOk()->assertSee('data-series-chart', false);
    }
}
