<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\Meter;
use App\Services\FinanceException;
use App\Services\MeterBillService;
use App\Services\ReservationService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeterAndReservationTest extends TestCase
{
    use RefreshDatabase;

    private function meters($apt, array $consumptions): void
    {
        foreach ($apt->units()->orderBy('id')->get() as $i => $unit) {
            $meter = $apt->meters()->create(['unit_id' => $unit->id, 'name' => "Su {$unit->unit_no}", 'type' => 'Su']);
            $meter->readings()->create(['value' => $consumptions[$i], 'consumption' => $consumptions[$i], 'read_on' => '2026-03-15']);
        }
    }

    private function bill(array $override = []): array
    {
        return $override + ['meter_type' => 'Su', 'method' => 'consumption', 'period_from' => '2026-03-01', 'period_to' => '2026-03-31',
            'total_amount' => 1000, 'description' => 'Mart suyu', 'due_date' => '2026-04-15'];
    }

    public function test_sayac_faturasi_tuketime_gore_kurusu_kurusuna_dagitilir(): void
    {
        $apt = $this->site(3);
        $this->meters($apt, [10, 10, 10]); // eşit tüketim, 1.000,00 / 3 = 333,34 / 333,33 / 333,33
        $service = app(MeterBillService::class);

        $result = $service->distribute($apt, $this->bill());

        $this->assertSame(3, $result['created']);
        $amounts = Debt::orderBy('id')->pluck('amount')->map(fn ($a) => Money::cents($a))->all();
        $this->assertSame(100000, array_sum($amounts));
        $this->assertSame([33334, 33333, 33333], $amounts);
        $this->assertSame('Su', Debt::first()->category_name);
        $this->assertSame(1, $apt->meterBills()->count());
        $this->assertLedgerBalanced($apt);
    }

    public function test_sayac_faturasi_agirliklara_ve_m2ye_gore(): void
    {
        $apt = $this->site(2);
        $this->meters($apt, [30, 10]);
        $service = app(MeterBillService::class);

        $preview = $service->preview($apt, 'Su', 'consumption', '2026-03-01', '2026-03-31', 100000);
        $this->assertSame([75000, 25000], $preview->pluck('amount')->all());

        $apt->units()->orderBy('id')->first()->update(['gross_area' => 300]);
        $areaPreview = $service->preview($apt, 'Su', 'area', '2026-03-01', '2026-03-31', 100000);
        $this->assertSame([75000, 25000], $areaPreview->pluck('amount')->all()); // 300 m² : 100 m²
    }

    public function test_sayac_faturasi_tuketim_yoksa_reddedilir(): void
    {
        $apt = $this->site(2);
        $this->expectException(FinanceException::class);
        $this->expectExceptionMessage('tüketim kaydı yok');
        app(MeterBillService::class)->preview($apt, 'Su', 'consumption', '2026-03-01', '2026-03-31', 100000);
    }

    // ------------------------------------------------------------ REZERVASYON

    public function test_rezervasyon_calisma_saati_ve_cakisma_kontrolu(): void
    {
        $apt = $this->site(2);
        $area = $apt->commonAreas()->create(['name' => 'Tenis Kortu', 'hourly_fee' => 50, 'open_time' => '08:00', 'close_time' => '22:00']);
        [$a, $b] = $apt->units()->orderBy('id')->get();
        $svc = app(ReservationService::class);
        $date = now()->addDays(3)->toDateString();

        $first = $svc->book($area, $a, $date, '10:00', '12:00');
        $this->assertSame('pending', $first->status);
        $this->assertEquals(100, (float) $first->fee); // 2 saat × 50 ₺

        foreach ([['11:00', '13:00'], ['09:00', '10:30'], ['10:30', '11:30']] as [$s, $e]) {
            try {
                $svc->book($area, $b, $date, $s, $e);
                $this->fail("{$s}-{$e} çakışmalıydı");
            } catch (FinanceException $ex) {
                $this->assertStringContainsString('dolu', $ex->getMessage());
            }
        }
        $svc->book($area, $b, $date, '12:00', '13:00'); // bitişik aralık serbest

        foreach ([['07:00', '09:00'], ['21:00', '23:00'], ['15:00', '14:00']] as [$s, $e]) {
            try {
                $svc->book($area, $b, $date, $s, $e);
                $this->fail("{$s}-{$e} reddedilmeliydi");
            } catch (FinanceException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_onaylanan_rezervasyon_ucreti_borc_yazar_iptal_ucreti_siler(): void
    {
        $apt = $this->site(1);
        $area = $apt->commonAreas()->create(['name' => 'Salon', 'hourly_fee' => 200, 'requires_approval' => true]);
        $unit = $this->unit($apt);
        $svc = app(ReservationService::class);
        $date = now()->addDays(5)->toDateString();

        $res = $svc->book($area, $unit, $date, '14:00', '17:00');
        $this->assertSame(0, Debt::count()); // onaydan önce borç yok
        $svc->approve($res);
        $this->assertEquals(600, (float) Debt::first()->amount);
        $this->assertSame(Debt::first()->id, $res->fresh()->debt_id);
        $svc->approve($res->fresh()); // ikinci onay mükerrer borç yazmaz
        $this->assertSame(1, Debt::count());

        $svc->cancel($res->fresh());
        $this->assertSame('cancelled', $res->fresh()->status);
        $this->assertSame(0, Debt::count());
        $this->assertLedgerBalanced($apt);
    }

    public function test_odenmis_ucretli_rezervasyon_iptal_edilemez_onay_gerektirmeyen_alan_dogrudan_onaylanir(): void
    {
        $apt = $this->site(1);
        $area = $apt->commonAreas()->create(['name' => 'Çay bahçesi', 'hourly_fee' => 100, 'requires_approval' => false]);
        $unit = $this->unit($apt);
        $svc = app(ReservationService::class);

        $res = $svc->book($area, $unit, now()->addDays(2)->toDateString(), '10:00', '11:00');
        $this->assertSame('approved', $res->status);

        app(\App\Services\FinanceService::class)->collect($unit, $this->cashbox($apt), 10000);
        $this->expectException(FinanceException::class);
        $svc->cancel($res->fresh());
    }

    public function test_gecmis_zamana_rezervasyon_yapilamaz(): void
    {
        $apt = $this->site(1);
        $area = $apt->commonAreas()->create(['name' => 'Salon']);
        $this->expectException(FinanceException::class);
        app(ReservationService::class)->book($area, $this->unit($apt), now()->subDay()->toDateString(), '10:00', '11:00');
    }
}
