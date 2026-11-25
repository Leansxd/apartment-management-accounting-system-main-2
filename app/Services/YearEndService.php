<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Yıl sonu kapanışı: yılın gelir-gider sonucunu ve 31 Aralık bakiyelerini denetim kaydına mühürler, dönemi kapatır.
 * Yevmiye sürekli olduğundan bakiyeler yeni yıla kendiliğinden devreder; ayrı açılış kaydı yazılmaz.
 */
class YearEndService
{
    public function __construct(private FinanceService $finance, private ReconciliationService $reconciliation, private AccountingReportService $reports) {}

    /**
     * @return array{year: int, income: float, expense: float, result: float, cash: float, receivables: float, advances: float}
     */
    public function summary(Apartment $apartment, int $year): array
    {
        $from = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $to = CarbonImmutable::create($year, 12, 31)->endOfDay();
        $sum = fn (string $account, string $plus, string $minus) => Money::cents((string) $apartment->ledgerEntries()
            ->where('account_name', $account)->whereBetween('created_at', [$from, $to])->sum(DB::raw("{$plus} - {$minus}")));
        $income = $sum(LedgerEntry::INCOME, 'credit', 'debit');
        $expense = $sum(LedgerEntry::EXPENSE, 'debit', 'credit');
        $sheet = $this->reports->balanceSheet($apartment, $to->startOfDay());

        return [
            'year' => $year, 'income' => $income / 100, 'expense' => $expense / 100, 'result' => ($income - $expense) / 100,
            'cash' => $sheet['cash_total'], 'receivables' => $sheet['receivables'], 'advances' => $sheet['advances'],
        ];
    }

    /**
     * @return array{year: int, income: float, expense: float, result: float, cash: float, receivables: float, advances: float}
     */
    public function close(Apartment $apartment, int $year, ?User $by = null): array
    {
        if ($year >= (int) today()->format('Y') || $year < 2000) {
            throw new FinanceException('Yıl sonu kapanışı yalnızca tamamlanmış bir yıl için yapılabilir.');
        }
        $end = CarbonImmutable::create($year, 12, 31);
        if ($apartment->closed_until && $apartment->closed_until->gte($end)) {
            throw new FinanceException("{$year} yılı zaten kapalı.");
        }
        if ($issues = $this->reconciliation->discrepancies($apartment)) {
            throw new FinanceException('Yevmiye bakiyelerle tutmuyor ('.count($issues).' sapma); kapanıştan önce php artisan app:reconcile ile inceleyin.');
        }

        $summary = $this->summary($apartment, $year);
        DB::transaction(function () use ($apartment, $end, $summary, $by, $year) {
            $apartment->update(['closed_until' => $end->toDateString()]);
            $this->finance->audit($apartment, $by, 'YEAR_END_CLOSE', 'FINANCE', "year:{$year}", $summary);
        });

        return $summary;
    }
}
