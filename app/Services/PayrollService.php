<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\Expense;
use App\Models\Staff;
use App\Models\User;
use App\Support\Money;

/**
 * Personel maaş bordrosu. Her aktif personel için dönem başına bir gider kaydı üretilir (onay bekler);
 * ödeme, gider onayıyla (kasa seçilerek) yapılır. Aynı personel + dönem için ikinci kayıt yazılmaz.
 */
class PayrollService
{
    public function __construct(private FinanceService $finance)
    {
    }

    /** @return array{created: int, skipped: int} */
    public function generate(Apartment $apartment, string $period, ?User $by = null): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new FinanceException('Dönem YYYY-AA biçiminde olmalıdır.');
        }
        $category = $apartment->categories()->firstOrCreate(['name' => 'Personel', 'type' => 'expense']);
        $result = ['created' => 0, 'skipped' => 0];

        foreach ($apartment->staff()->where('status', 'Aktif')->where('salary', '>', 0)->get() as $staff) {
            if ($apartment->expenses()->where('staff_id', $staff->id)->where('period', $period)->exists()) {
                $result['skipped']++;
                continue;
            }
            $cents = Money::cents($staff->salary);
            $apartment->expenses()->create([
                'staff_id' => $staff->id, 'period' => $period, 'category_id' => $category->id,
                'description' => "Maaş: {$staff->name} ({$staff->role}) {$period}",
                'amount' => Money::decimal($cents), 'vat_rate' => 0, 'total_amount' => Money::decimal($cents),
                'invoice_date' => today(), 'created_by' => $by?->id,
            ]);
            $result['created']++;
        }
        $this->finance->audit($apartment, $by, 'PAYROLL', 'EXPENSE', $period, $result);

        return $result;
    }

    /** Dönem için personel başına bordro durumu: null (yazılmadı) | Expense */
    public function statusFor(Apartment $apartment, string $period): array
    {
        $expenses = $apartment->expenses()->where('period', $period)->whereNotNull('staff_id')->get()->keyBy('staff_id');

        return $apartment->staff()->orderBy('name')->get()->map(fn (Staff $s) => ['staff' => $s, 'expense' => $expenses->get($s->id)])->all();
    }
}
