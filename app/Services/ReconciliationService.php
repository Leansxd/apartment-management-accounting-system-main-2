<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\Cashbox;
use App\Models\Debt;
use App\Models\LedgerEntry;
use App\Models\Unit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Yevmiye defteri ile tutulan bakiyeleri karşılaştırır: yevmiye dengesi, kasa bakiyesi, daire borcu ve daire avansı.
 * Hiçbir kaydı değiştirmez; sapmaları listeler.
 */
class ReconciliationService
{
    /**
     * @return list<array{check: string, subject: string, expected: int, actual: int}> tutarlar kuruş; expected yevmiye, actual tutulan bakiye
     */
    public function discrepancies(Apartment $apartment): array
    {
        $issues = [];
        $entries = fn () => LedgerEntry::where('apartment_id', $apartment->id);

        $debit = Money::cents((string) $entries()->sum('debit'));
        $credit = Money::cents((string) $entries()->sum('credit'));
        if ($debit !== $credit) {
            $issues[] = ['check' => 'Yevmiye dengesi', 'subject' => 'Toplam borç / alacak', 'expected' => $debit, 'actual' => $credit];
        }

        $net = fn (string $account, bool $debitSide) => $entries()->where('account_name', $account)
            ->selectRaw('account_id, SUM(debit) d, SUM(credit) c')->groupBy('account_id')->get()
            ->mapWithKeys(fn ($r) => [(string) $r->account_id => Money::cents((string) ($debitSide ? $r->d - $r->c : $r->c - $r->d))]);

        $cash = $net(LedgerEntry::CASH, true);
        foreach (Cashbox::where('apartment_id', $apartment->id)->get() as $box) {
            $this->compare($issues, 'Kasa bakiyesi', $box->name, $cash->get((string) $box->id, 0), Money::cents($box->balance));
        }

        $receivable = $net(LedgerEntry::UNIT, true);
        $open = Debt::where('apartment_id', $apartment->id)->where('status', Debt::PENDING)
            ->select('unit_id', DB::raw('SUM(remaining_amount) total'))->groupBy('unit_id')->pluck('total', 'unit_id');
        $advance = $net(LedgerEntry::UNIT_ADVANCE, false);
        $units = Unit::withTrashed()->where('apartment_id', $apartment->id)->with('block')->get();
        foreach ($units as $unit) {
            $key = (string) $unit->id;
            $this->compare($issues, 'Daire borcu', $unit->label(), $receivable->get($key, 0), Money::cents((string) ($open[$unit->id] ?? 0)));
            $this->compare($issues, 'Daire avansı', $unit->label(), $advance->get($key, 0), Money::cents($unit->credit_balance));
        }

        return $issues;
    }

    /** @param list<array{check: string, subject: string, expected: int, actual: int}> $issues */
    private function compare(array &$issues, string $check, string $subject, int $expected, int $actual): void
    {
        if ($expected !== $actual) {
            $issues[] = ['check' => $check, 'subject' => $subject, 'expected' => $expected, 'actual' => $actual];
        }
    }
}
