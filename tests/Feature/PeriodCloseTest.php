<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\Debt;
use App\Models\Unit;
use App\Models\User;
use App\Services\FinanceException;
use App\Services\FinanceService;
use App\Services\MeterBillService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodCloseTest extends TestCase
{
    use RefreshDatabase;

    private FinanceService $finance;

    private User $owner;

    private Apartment $site;

    private Unit $flat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finance = app(FinanceService::class);
        $this->owner = $this->admin();
        $this->site = $this->site(1, $this->owner);
        $this->flat = $this->unit($this->site);
    }

    private function debtDue(string $date): Debt
    {
        $this->finance->accrue($this->flat, ['description' => 'Aidat', 'amount' => 100000, 'due_date' => $date]);

        return Debt::latest('id')->firstOrFail();
    }

    private function close(string $date = '2026-01-31'): void
    {
        $this->site->update(['closed_until' => $date]);
        $this->site->refresh();
    }

    private function assertClosedPeriodError(callable $operation): void
    {
        try {
            $operation();
        } catch (FinanceException $e) {
            $this->assertStringContainsString('dönem kapalı', $e->getMessage());

            return;
        }
        $this->fail('Kapalı dönem işlemi reddedilmedi.');
    }

    public function test_kapali_donemdeki_hareket_iptal_edilemez(): void
    {
        $this->travelTo('2026-01-31 12:00');
        $this->debtDue('2026-01-31');
        $tx = $this->finance->collect($this->flat, $this->cashbox($this->site), 100000);
        $this->travelTo('2026-03-15 12:00');
        $this->close();
        $before = CashTransaction::count();

        $this->assertClosedPeriodError(fn () => $this->finance->reverse($tx->fresh(), 'Hata'));

        $this->assertSame($before, CashTransaction::count());
    }

    public function test_kapanistan_sonraki_gunun_hareketi_iptal_edilebilir(): void
    {
        $this->travelTo('2026-02-01 09:00');
        $this->debtDue('2026-02-28');
        $tx = $this->finance->collect($this->flat, $this->cashbox($this->site), 100000);
        $this->travelTo('2026-03-15 12:00');
        $this->close();

        $this->finance->reverse($tx->fresh(), 'Yanlış daire');

        $this->assertSame(100000.0, (float) Debt::first()->remaining_amount * 100);
    }

    public function test_kapali_donem_borcu_bugun_tahsil_edilebilir(): void
    {
        $this->travelTo('2026-01-10 12:00');
        $debt = $this->debtDue('2026-01-31');
        $this->travelTo('2026-03-15 12:00');
        $this->close();

        $this->finance->collect($this->flat, $this->cashbox($this->site), 100000);

        $this->assertSame(Debt::PAID, $debt->fresh()->status);
    }

    public function test_kapali_donem_borcu_silinemez_ve_taksitlendirilemez(): void
    {
        $this->travelTo('2026-01-10 12:00');
        $debt = $this->debtDue('2026-01-31');
        $this->travelTo('2026-03-15 12:00');
        $this->close();

        $this->assertClosedPeriodError(fn () => $this->finance->deleteDebt($debt));
        $this->assertClosedPeriodError(fn () => $this->finance->installments($debt, 2, '2026-04-30'));

        $this->assertNotNull($debt->fresh());
    }

    public function test_kapali_doneme_vadeli_borc_ve_taksit_yazilamaz(): void
    {
        $this->travelTo('2026-03-15 12:00');
        $debt = $this->debtDue('2026-04-30');
        $this->close();

        $this->assertClosedPeriodError(fn () => $this->finance->distribute($this->site, collect([$this->flat]), ['description' => 'Eski', 'amount' => 100, 'due_date' => '2026-01-31']));
        $this->assertClosedPeriodError(fn () => $this->finance->installments($debt, 2, '2026-01-15'));
    }

    public function test_kapali_doneme_aidat_bordro_ve_sayac_faturasi_yazilamaz(): void
    {
        $this->travelTo('2026-03-15 12:00');
        $this->close();

        $this->assertClosedPeriodError(fn () => $this->finance->generateDues($this->site, '2026-01'));
        $this->assertClosedPeriodError(fn () => app(PayrollService::class)->generate($this->site, '2026-01'));
        $this->assertClosedPeriodError(fn () => app(MeterBillService::class)->distribute($this->site, ['total_amount' => 100, 'due_date' => '2026-01-20']));
    }

    public function test_kismen_kapali_ayin_aidati_uretilebilir(): void
    {
        $this->travelTo('2026-03-15 12:00');
        $this->close('2026-02-15');
        $this->site->duesDefinitions()->create(['name' => 'Aidat', 'amount' => 500, 'calculation' => 'fixed']);

        $this->finance->generateDues($this->site, '2026-02');

        $this->assertSame(1, Debt::count());
    }

    public function test_kapali_doneme_ait_bekleyen_gider_silinemez(): void
    {
        $this->travelTo('2026-01-10 12:00');
        $expense = $this->site->expenses()->create(['description' => 'Elektrik', 'amount' => 100, 'total_amount' => 120]);
        $this->travelTo('2026-03-15 12:00');
        $this->close();

        $this->actingAs($this->owner)->delete(route('site.expenses.destroy', [$this->site, $expense]))->assertSessionHas('error');

        $this->assertNotNull($expense->fresh());
    }

    public function test_site_sahibi_donemi_kapatir_ve_kaydedilir(): void
    {
        $this->travelTo('2026-03-15 12:00');

        $this->actingAs($this->owner)->put(route('site.settings.period', $this->site), ['closed_until' => '2026-02-28'])->assertSessionHas('status');

        $this->assertSame('2026-02-28', $this->site->fresh()->closed_until->toDateString());
        $log = AuditLog::where('action', 'CLOSE_PERIOD')->firstOrFail();
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertSame(['old' => null, 'new' => '2026-02-28'], $log->data['changes']['closed_until']);
    }

    public function test_bugun_ve_gelecek_tarih_kapatilamaz(): void
    {
        $this->travelTo('2026-03-15 12:00');
        $this->actingAs($this->owner);

        $this->put(route('site.settings.period', $this->site), ['closed_until' => '2026-03-15'])->assertSessionHasErrors('closed_until');
        $this->put(route('site.settings.period', $this->site), ['closed_until' => '2026-04-01'])->assertSessionHasErrors('closed_until');

        $this->assertNull($this->site->fresh()->closed_until);
    }

    public function test_donemi_geri_acmak_gerekce_ister(): void
    {
        $this->travelTo('2026-03-15 12:00');
        $this->close();
        $this->actingAs($this->owner);

        $this->put(route('site.settings.period', $this->site), ['closed_until' => ''])->assertSessionHas('error');
        $this->assertNotNull($this->site->fresh()->closed_until);

        $this->put(route('site.settings.period', $this->site), ['closed_until' => '', 'reason' => 'Fatura düzeltmesi'])->assertSessionHas('status');
        $this->assertNull($this->site->fresh()->closed_until);
        $this->assertSame('Fatura düzeltmesi', AuditLog::where('action', 'REOPEN_PERIOD')->firstOrFail()->data['reason']);
    }

    public function test_kapanisi_ileri_almak_gerekce_istemez(): void
    {
        $this->travelTo('2026-03-15 12:00');
        $this->close();

        $this->actingAs($this->owner)->put(route('site.settings.period', $this->site), ['closed_until' => '2026-02-28'])->assertSessionHas('status');

        $this->assertSame('2026-02-28', $this->site->fresh()->closed_until->toDateString());
    }

    public function test_sahip_olmayan_yonetici_donemi_degistiremez(): void
    {
        $this->travelTo('2026-03-15 12:00');
        $other = $this->admin();
        $this->site->managers()->attach($other, ['role' => 'manager']);

        $this->actingAs($other)->put(route('site.settings.period', $this->site), ['closed_until' => '2026-02-28'])->assertForbidden();

        $this->assertNull($this->site->fresh()->closed_until);
    }
}
