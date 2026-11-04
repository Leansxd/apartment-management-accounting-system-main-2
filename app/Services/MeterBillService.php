<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\MeterBill;
use App\Models\MeterReading;
use App\Models\Unit;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sayaç faturası dağıtımı (su, ısınma, doğalgaz…): toplam fatura tutarı dairelere tüketime, m²'ye
 * ya da eşit olarak paylaştırılır; her pay daire borcu olur. Paylar kuruşu kuruşuna toplam tutara eşittir.
 */
class MeterBillService
{
    public function __construct(private FinanceService $finance)
    {
    }

    /**
     * Önizleme: daire başına ağırlık ve pay.
     *
     * @return Collection<int, array{unit: Unit, weight: float, amount: int}>
     */
    public function preview(Apartment $apartment, string $type, string $method, string $from, string $to, int $total): Collection
    {
        if ($total <= 0) {
            throw new FinanceException('Fatura tutarı 0\'dan büyük olmalıdır.');
        }
        $units = $apartment->units()->with('block')->get();

        $weights = match ($method) {
            'consumption' => $this->consumptionWeights($apartment, $type, $from, $to),
            'area' => $units->mapWithKeys(fn (Unit $u) => [$u->id => (float) $u->gross_area])->filter(fn ($w) => $w > 0)->all(),
            'equal' => $units->mapWithKeys(fn (Unit $u) => [$u->id => 1.0])->all(),
            default => throw new FinanceException('Geçersiz dağıtım yöntemi.'),
        };
        if (array_sum($weights) <= 0) {
            throw new FinanceException($method === 'consumption'
                ? "Seçilen aralıkta {$type} sayaçlarında tüketim kaydı yok. Önce sayaç okumalarını girin."
                : 'Dağıtılacak daire bulunamadı (m² bilgileri eksik olabilir).');
        }
        $shares = Money::allocate($total, $weights);

        return $units->filter(fn (Unit $u) => isset($shares[$u->id]) && $shares[$u->id] > 0)
            ->map(fn (Unit $u) => ['unit' => $u, 'weight' => $weights[$u->id], 'amount' => $shares[$u->id]])->values();
    }

    /** @return array<int, float> daire id => dönem tüketimi */
    private function consumptionWeights(Apartment $apartment, string $type, string $from, string $to): array
    {
        return MeterReading::query()
            ->join('meters', 'meters.id', '=', 'meter_readings.meter_id')
            ->where('meters.apartment_id', $apartment->id)->where('meters.type', $type)->whereNotNull('meters.unit_id')
            ->whereBetween('meter_readings.read_on', [$from, $to])
            ->selectRaw('meters.unit_id, SUM(meter_readings.consumption) total')->groupBy('meters.unit_id')
            ->pluck('total', 'unit_id')->map(fn ($v) => (float) $v)->filter(fn ($v) => $v > 0)->all();
    }

    /**
     * Faturayı dairelere borç olarak yazar.
     *
     * @return array{bill: MeterBill, created: int}
     */
    public function distribute(Apartment $apartment, array $data, ?User $by = null): array
    {
        $total = Money::cents($data['total_amount']);
        $rows = $this->preview($apartment, $data['meter_type'], $data['method'], $data['period_from'], $data['period_to'], $total);
        $category = $apartment->categories()->firstOrCreate(['name' => $data['meter_type'], 'type' => 'income']);

        return DB::transaction(function () use ($apartment, $data, $rows, $category, $by) {
            $bill = $apartment->meterBills()->create([
                'meter_type' => $data['meter_type'], 'method' => $data['method'],
                'period_from' => $data['period_from'], 'period_to' => $data['period_to'],
                'total_amount' => Money::decimal(Money::cents($data['total_amount'])),
                'description' => $data['description'], 'units_count' => $rows->count(), 'created_by' => $by?->id,
            ]);
            $created = 0;
            foreach ($rows as $row) {
                $this->finance->accrue($row['unit'], [
                    'description' => $data['description'], 'amount' => $row['amount'], 'due_date' => $data['due_date'],
                    'period' => substr($data['period_to'], 0, 7), 'category_id' => $category->id, 'category_name' => $category->name,
                    'distribution_id' => "meter:{$bill->id}",
                ], "meterbill:{$bill->id}:{$row['unit']->id}") === 'created' && $created++;
            }
            $this->finance->audit($apartment, $by, 'METER_BILL', 'DEBT', "meter_bill:{$bill->id}",
                ['type' => $bill->meter_type, 'total' => $bill->total_amount, 'units' => $created]);

            return ['bill' => $bill, 'created' => $created];
        });
    }
}
