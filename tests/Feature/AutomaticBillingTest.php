<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\AuditLog;
use App\Models\Debt;
use App\Services\FinanceException;
use App\Services\FinanceService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AutomaticBillingTest extends TestCase
{
    use RefreshDatabase;

    private function siteWithDues(int $units = 1): Apartment
    {
        $apartment = $this->site($units);
        $apartment->duesDefinitions()->create(['name' => 'Aidat', 'amount' => 500, 'calculation' => 'fixed']);

        return $apartment;
    }

    private function overdueDebt(Apartment $apartment): void
    {
        app(FinanceService::class)->accrue($this->unit($apartment), ['description' => 'Eski borç', 'amount' => 100000, 'due_date' => '2026-01-10']);
    }

    public function test_komut_her_gece_saat_ikide_zamanlanir(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'app:run-billing'));

        $this->assertNotNull($event);
        $this->assertSame('0 2 * * *', $event->expression);
    }

    public function test_otomatik_acik_sitede_donem_aidati_ve_gecikme_zammi_yazilir(): void
    {
        $this->travelTo('2026-02-15');
        $apartment = $this->siteWithDues();
        $apartment->update(['dues_auto_run' => true, 'late_fee_auto_run' => true, 'late_fee_enabled' => true, 'late_fee_rate' => 5, 'late_fee_grace_days' => 0]);
        $this->overdueDebt($apartment);

        $this->artisan('app:run-billing')->assertSuccessful();

        $this->assertSame(1, $apartment->debts()->where('period', '2026-02')->where('kind', 'due')->count());
        $this->assertSame(1, $apartment->debts()->where('kind', 'late_fee')->count());
        $this->assertSame('50.00', $apartment->debts()->where('kind', 'late_fee')->first()->amount);
        $this->assertLedgerBalanced($apartment);
    }

    public function test_otomatik_kapali_siteye_borc_yazilmaz(): void
    {
        $this->travelTo('2026-02-15');
        $apartment = $this->siteWithDues();
        $apartment->update(['late_fee_enabled' => true, 'late_fee_rate' => 5, 'late_fee_grace_days' => 0]);
        $this->overdueDebt($apartment);

        $this->artisan('app:run-billing')->assertSuccessful();

        $this->assertSame(1, $apartment->debts()->count());
    }

    public function test_yalnizca_aidat_otomatikse_gecikme_zammi_yazilmaz(): void
    {
        $this->travelTo('2026-02-15');
        $apartment = $this->siteWithDues();
        $apartment->update(['dues_auto_run' => true, 'late_fee_enabled' => true, 'late_fee_rate' => 5, 'late_fee_grace_days' => 0]);
        $this->overdueDebt($apartment);

        $this->artisan('app:run-billing')->assertSuccessful();

        $this->assertSame(0, $apartment->debts()->where('kind', 'late_fee')->count());
        $this->assertSame(1, $apartment->debts()->where('period', '2026-02')->count());
    }

    public function test_ay_dolunca_yazilan_zam_borc_sonradan_odense_de_kalir(): void
    {
        $finance = app(FinanceService::class);
        $apartment = $this->site(1);
        $apartment->update(['late_fee_auto_run' => true, 'late_fee_enabled' => true, 'late_fee_rate' => 5, 'late_fee_grace_days' => 0]);
        $this->overdueDebt($apartment);

        $this->travelTo('2026-02-11');
        $this->artisan('app:run-billing')->assertSuccessful();
        $finance->collect($this->unit($apartment), $this->cashbox($apartment), 100000);
        $this->travelTo('2026-03-11');
        $this->artisan('app:run-billing')->assertSuccessful();

        $this->assertSame(1, $apartment->debts()->where('kind', 'late_fee')->count());
        $this->assertSame('paid', $apartment->debts()->where('kind', 'due')->value('status'));
    }

    public function test_tekrar_calistirmak_mukerrer_borc_ve_denetim_kaydi_yazmaz(): void
    {
        $this->travelTo('2026-02-15');
        $apartment = $this->siteWithDues();
        $apartment->update(['dues_auto_run' => true]);

        $this->artisan('app:run-billing')->assertSuccessful();
        $this->artisan('app:run-billing')->assertSuccessful();

        $this->assertSame(1, $apartment->debts()->count());
        $this->assertSame(1, AuditLog::where('action', 'GENERATE_DUES')->count());
    }

    public function test_aktif_tanimi_olmayan_site_komutu_hataya_dusurmez(): void
    {
        $apartment = $this->site(1);
        $apartment->update(['dues_auto_run' => true]);

        $this->artisan('app:run-billing')->assertSuccessful();

        $this->assertSame(0, $apartment->debts()->count());
    }

    public function test_bir_sitedeki_hata_digerini_durdurmaz_ve_raporlanir(): void
    {
        Exceptions::fake([RuntimeException::class]);
        $this->travelTo('2026-02-15');
        $broken = $this->siteWithDues();
        $healthy = $this->siteWithDues();
        Apartment::query()->update(['dues_auto_run' => true]);
        $failure = new RuntimeException('Beklenmeyen hata');

        $this->partialMock(FinanceService::class, function ($mock) use ($broken, $failure) {
            $mock->shouldReceive('generateDues')->once()->with(Mockery::on(fn ($site) => $site->is($broken)), '2026-02')->andThrow($failure);
            $mock->shouldReceive('generateDues')->once()->with(Mockery::on(fn ($site) => ! $site->is($broken)), '2026-02')->passthru();
        });

        $this->artisan('app:run-billing')->assertFailed();

        Exceptions::assertReported(fn (RuntimeException $e) => $e === $failure);
        $this->assertSame(0, $broken->debts()->count());
        $this->assertSame(1, $healthy->debts()->count());
    }

    public function test_yonetici_otomatik_ayarlari_acip_kapatabilir(): void
    {
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);

        $this->actingAs($manager)->put(route('site.dues.settings', $apartment), ['dues_auto_run' => 1])->assertSessionHas('status');
        $this->actingAs($manager)->put(route('site.late-fees.settings', $apartment), ['late_fee_auto_run' => 1, 'late_fee_rate' => 5, 'late_fee_grace_days' => 0])->assertSessionHas('status');
        $this->assertTrue($apartment->fresh()->dues_auto_run);
        $this->assertTrue($apartment->fresh()->late_fee_auto_run);

        $this->actingAs($manager)->put(route('site.dues.settings', $apartment), [])->assertSessionHas('status');
        $this->assertFalse($apartment->fresh()->dues_auto_run);
    }

    public function test_baska_sitenin_yoneticisi_ve_izleyici_otomatik_ayari_degistiremez(): void
    {
        $apartment = $this->site(1);
        $viewer = $this->admin();
        $apartment->managers()->attach($viewer, ['role' => 'viewer']);

        $this->actingAs($this->admin())->put(route('site.dues.settings', $apartment), ['dues_auto_run' => 1])->assertForbidden();
        $this->actingAs($viewer)->put(route('site.dues.settings', $apartment), ['dues_auto_run' => 1])->assertForbidden();

        $this->assertFalse($apartment->fresh()->dues_auto_run);
    }

    public function test_silinen_aidat_ayni_donem_tekrar_calistirilinca_geri_gelmez(): void
    {
        $finance = app(FinanceService::class);
        $apartment = $this->siteWithDues(2);
        $finance->generateDues($apartment, '2026-02');
        $deleted = $apartment->debts()->where('unit_id', $this->unit($apartment)->id)->firstOrFail();

        $finance->deleteDebt($deleted);
        $report = $finance->generateDues($apartment, '2026-02');

        $this->assertSame(1, $report['waived']);
        $this->assertSame(1, $report['skipped']);
        $this->assertSame(0, $report['created']);
        $this->assertModelMissing($deleted);
        $this->assertLedgerBalanced($apartment);
    }

    public function test_silinen_aidat_diger_donemin_borcunu_engellemez(): void
    {
        $finance = app(FinanceService::class);
        $apartment = $this->siteWithDues();
        $finance->generateDues($apartment, '2026-02');
        $finance->deleteDebt($apartment->debts()->firstOrFail());

        $report = $finance->generateDues($apartment, '2026-03');

        $this->assertSame(1, $report['created']);
        $this->assertSame(0, $report['waived']);
    }

    public function test_silinenleri_geri_yaz_secenegi_yalnizca_o_donemi_geri_getirir(): void
    {
        $finance = app(FinanceService::class);
        $apartment = $this->siteWithDues();
        $finance->generateDues($apartment, '2026-02');
        $finance->generateDues($apartment, '2026-03');
        foreach ($apartment->debts()->get() as $debt) {
            $finance->deleteDebt($debt);
        }

        $restored = $finance->generateDues($apartment, '2026-02', null, true);
        $stillWaived = $finance->generateDues($apartment, '2026-03');

        $this->assertSame(1, $restored['created']);
        $this->assertSame(1, $stillWaived['waived']);
        $this->assertSame(['2026-02'], $apartment->debts()->pluck('period')->all());
        $this->assertLedgerBalanced($apartment);
    }

    public function test_panel_silinen_borcu_bildirir_ve_secenekle_geri_yazar(): void
    {
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $apartment->duesDefinitions()->create(['name' => 'Aidat', 'amount' => 500, 'calculation' => 'fixed']);
        $finance = app(FinanceService::class);
        $finance->generateDues($apartment, '2026-02');
        $finance->deleteDebt($apartment->debts()->firstOrFail());

        $this->actingAs($manager)->post(route('site.dues.run', $apartment), ['period' => '2026-02'])
            ->assertSessionHas('status', '2026-02: 0 borç oluşturuldu, 0 zaten vardı. 1 borç daha önce silindiği için atlandı.');
        $this->assertSame(0, $apartment->debts()->count());

        $this->actingAs($manager)->post(route('site.dues.run', $apartment), ['period' => '2026-02', 'restore_waived' => 1])
            ->assertSessionHas('status', '2026-02: 1 borç oluşturuldu, 0 zaten vardı.');
        $this->assertSame(1, $apartment->debts()->count());
    }

    public function test_gecikme_zammi_borcu_taksitlendirilemez(): void
    {
        $finance = app(FinanceService::class);
        $this->travelTo('2026-02-15');
        $apartment = $this->site(1);
        $apartment->update(['late_fee_enabled' => true, 'late_fee_rate' => 5, 'late_fee_grace_days' => 0]);
        $this->overdueDebt($apartment);
        $finance->runLateFees($apartment->fresh());
        $lateFee = $apartment->debts()->where('kind', 'late_fee')->firstOrFail();

        try {
            $finance->installments($lateFee, 2, '2026-03-01');
            $this->fail('Gecikme zammı taksitlendirilmemeliydi.');
        } catch (FinanceException $e) {
            $this->assertSame('Gecikme zammı taksitlendirilemez: taksitlere yeniden zam işler.', $e->getMessage());
        }

        $this->assertSame(0, Debt::where('kind', 'installment')->count());
        $this->assertSame('pending', $lateFee->fresh()->status);
    }

    public function test_borc_listesi_taksit_formunu_gecikme_zammi_icin_gostermez(): void
    {
        $finance = app(FinanceService::class);
        $this->travelTo('2026-02-15');
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $apartment->update(['late_fee_enabled' => true, 'late_fee_rate' => 5, 'late_fee_grace_days' => 0]);
        $this->overdueDebt($apartment);
        $finance->runLateFees($apartment->fresh());
        $lateFee = $apartment->debts()->where('kind', 'late_fee')->firstOrFail();
        $principal = $apartment->debts()->where('kind', 'due')->firstOrFail();

        $this->actingAs($manager)->get(route('site.debts.index', $apartment))
            ->assertOk()
            ->assertSee(route('site.debts.installments', [$apartment, $principal]), false)
            ->assertDontSee(route('site.debts.installments', [$apartment, $lateFee]), false);
    }
}
