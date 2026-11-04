<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\Asset;
use App\Models\Cashbox;
use App\Models\CashTransaction;
use App\Models\Debt;
use App\Models\LedgerEntry;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Mali raporlar: mizan, kasa defteri, mali durum (bilanço), alacak yaşlandırma ve bütçe karşılaştırması. */
class AccountingReportService
{
    /**
     * Mizan: her hesabın borç / alacak toplamı ve bakiyesi. Hesap altları (kasa, kategori, daire)
     * ayrı satırdır; toplamlar her zaman dengelidir.
     *
     * @return array{rows: Collection, groups: Collection, debit: float, credit: float}
     */
    public function trialBalance(Apartment $apartment, ?string $from = null, ?string $to = null): array
    {
        $rows = $apartment->ledgerEntries()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->selectRaw('account_name, account_id, SUM(debit) debit, SUM(credit) credit')
            ->groupBy('account_name', 'account_id')->get();

        $cashboxes = $apartment->cashboxes()->pluck('name', 'id');
        $categories = $apartment->categories()->pluck('name', 'id');
        $units = $apartment->units()->withTrashed()->with('block')->get()->keyBy('id');

        $rows = $rows->map(function ($r) use ($cashboxes, $categories, $units) {
            $id = $r->account_id;
            $sub = match ($r->account_name) {
                LedgerEntry::CASH => $cashboxes[$id] ?? null,
                LedgerEntry::INCOME, LedgerEntry::EXPENSE => $id ? ($categories[$id] ?? null) : 'Kategorisiz',
                LedgerEntry::UNIT, LedgerEntry::UNIT_ADVANCE => $id ? $units[$id]?->label() : null,
                default => null,
            };

            return [
                'account' => $r->account_name, 'sub' => $sub ?? ($id ? "#{$id}" : '—'),
                'debit' => (float) $r->debit, 'credit' => (float) $r->credit, 'balance' => round((float) $r->debit - (float) $r->credit, 2),
            ];
        })->sortBy(fn ($r) => [$r['account'], $r['sub']])->values();

        return [
            'rows' => $rows,
            'groups' => $rows->groupBy('account')->map(fn ($g) => [
                'debit' => round($g->sum('debit'), 2), 'credit' => round($g->sum('credit'), 2), 'balance' => round($g->sum('balance'), 2), 'rows' => $g,
            ]),
            'debit' => round($rows->sum('debit'), 2),
            'credit' => round($rows->sum('credit'), 2),
        ];
    }

    /**
     * Kasa defteri: seçilen aralıktaki tüm hareketler (iptaller dahil) yürüyen bakiyeyle.
     *
     * @return array{opening: float, rows: array<int, array>, closing: float, in: float, out: float}
     */
    public function cashBook(Cashbox $cashbox, ?string $from = null, ?string $to = null): array
    {
        $signed = 'CASE WHEN type = \'income\' THEN amount ELSE -amount END';
        $opening = $from
            ? (float) CashTransaction::where('cashbox_id', $cashbox->id)->whereDate('created_at', '<', $from)->sum(DB::raw($signed))
            : 0.0;

        $balance = Money::cents($opening);
        $rows = [];
        $in = $out = 0;
        $txs = CashTransaction::where('cashbox_id', $cashbox->id)->with('unit.block')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderBy('id')->get();
        foreach ($txs as $t) {
            $cents = Money::cents($t->amount);
            $isIn = $t->type === 'income';
            $balance += $isIn ? $cents : -$cents;
            $isIn ? $in += $cents : $out += $cents;
            $rows[] = ['tx' => $t, 'in' => $isIn ? $cents / 100 : 0.0, 'out' => $isIn ? 0.0 : $cents / 100, 'balance' => $balance / 100];
        }

        return ['opening' => $opening, 'rows' => $rows, 'closing' => $balance / 100, 'in' => $in / 100, 'out' => $out / 100];
    }

    /**
     * Mali durum tablosu: varlıklar (kasa, alacaklar, demirbaş net değeri) ve yükümlülükler (daire avansları,
     * ödenecek giderler); fark net varlıktır.
     */
    public function balanceSheet(Apartment $apartment): array
    {
        $cash = $apartment->cashboxes()->orderBy('name')->get(['name', 'type', 'balance']);
        $assets = $apartment->assets()->where('status', 'active')->get();
        $fixed = $assets->sum(fn (Asset $a) => $a->bookValue()) / 100;

        $receivables = (float) $apartment->debts()->where('status', Debt::PENDING)->sum('remaining_amount');
        $overdue = (float) $apartment->debts()->where('status', Debt::PENDING)->whereDate('due_date', '<', today())->sum('remaining_amount');
        $cashTotal = (float) $cash->sum('balance');
        $advances = (float) $apartment->units()->sum('credit_balance');
        $payables = (float) $apartment->expenses()->where('status', 'pending')->sum('total_amount');

        $assetsTotal = round($cashTotal + $receivables + $fixed, 2);
        $liabilitiesTotal = round($advances + $payables, 2);

        return [
            'cash' => $cash, 'cash_total' => $cashTotal, 'receivables' => $receivables, 'overdue' => $overdue,
            'fixed_assets' => $fixed, 'assets_total' => $assetsTotal,
            'advances' => $advances, 'payables' => $payables, 'liabilities_total' => $liabilitiesTotal,
            'net' => round($assetsTotal - $liabilitiesTotal, 2),
        ];
    }

    /**
     * Alacak yaşlandırma: açık borçlar vadesinin üzerinden geçen güne göre dilimlenir.
     *
     * @return array{buckets: array<string, string>, rows: Collection, totals: array<string, float>}
     */
    public function aging(Apartment $apartment, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::today();
        $buckets = ['current' => 'Vadesi gelmemiş', 'd30' => '1-30 gün', 'd60' => '31-60 gün', 'd90' => '61-90 gün', 'd90p' => '90+ gün'];
        $bucketOf = function (int $days): string {
            return match (true) {
                $days <= 0 => 'current', $days <= 30 => 'd30', $days <= 60 => 'd60', $days <= 90 => 'd90', default => 'd90p',
            };
        };

        $rows = $apartment->debts()->where('status', Debt::PENDING)->with('unit.block', 'unit.members')->get()
            ->groupBy('unit_id')->map(function (Collection $debts) use ($asOf, $bucketOf, $buckets) {
                $sums = array_fill_keys(array_keys($buckets), 0);
                foreach ($debts as $d) {
                    $days = $d->due_date->lt($asOf) ? (int) $d->due_date->diffInDays($asOf) : 0;
                    $sums[$bucketOf($days)] += Money::cents($d->remaining_amount);
                }

                return ['unit' => $debts->first()->unit, 'member' => $debts->first()->unit->members->first()?->full_name,
                    'buckets' => array_map(fn ($c) => $c / 100, $sums), 'total' => array_sum($sums) / 100, 'legal' => $debts->contains(fn ($d) => $d->legal_status)];
            })->sortByDesc('total')->values();

        return [
            'buckets' => $buckets,
            'rows' => $rows,
            'totals' => collect($buckets)->mapWithKeys(fn ($l, $k) => [$k => round($rows->sum("buckets.{$k}"), 2)])->all() + ['total' => round($rows->sum('total'), 2)],
        ];
    }

    /**
     * Bütçe karşılaştırması: kategori başına planlanan, gerçekleşen (tahakkuk / tahsilat veya ödenen gider).
     *
     * @return array{income: Collection, expense: Collection, totals: array<string, float>}
     */
    public function budget(Apartment $apartment, int $year): array
    {
        $planned = $apartment->budgetItems()->where('year', $year)->pluck('amount', 'category_id');
        $categories = $apartment->categories()->orderBy('name')->get();
        $start = "{$year}-01-01";
        $end = "{$year}-12-31";

        $accrued = $apartment->debts()->whereBetween('due_date', [$start, $end])
            ->selectRaw('category_id, SUM(amount) total')->groupBy('category_id')->pluck('total', 'category_id');
        $collected = DB::table('debt_payments')->join('debts', 'debts.id', '=', 'debt_payments.debt_id')
            ->where('debt_payments.apartment_id', $apartment->id)->where('debt_payments.type', 'collection')
            ->whereBetween('debt_payments.created_at', [$start.' 00:00:00', $end.' 23:59:59'])
            ->selectRaw('debts.category_id, SUM(debt_payments.amount) total')->groupBy('debts.category_id')->pluck('total', 'category_id');
        $general = $apartment->cashTransactions()->operating()->whereBetween('created_at', [$start.' 00:00:00', $end.' 23:59:59'])
            ->whereNotNull('category_id')->selectRaw('type, category_id, SUM(amount) total')->groupBy('type', 'category_id')->get();

        $rows = $categories->map(function ($c) use ($planned, $accrued, $collected, $general) {
            $isIncome = $c->type === 'income';
            $actual = $isIncome
                ? (float) ($collected[$c->id] ?? 0) + (float) $general->where('type', 'income')->where('category_id', $c->id)->sum('total')
                : (float) $general->where('type', 'expense')->where('category_id', $c->id)->sum('total');
            $plan = (float) ($planned[$c->id] ?? 0);

            return ['category' => $c, 'planned' => $plan, 'accrued' => $isIncome ? (float) ($accrued[$c->id] ?? 0) : null,
                'actual' => round($actual, 2), 'diff' => round($actual - $plan, 2), 'pct' => $plan > 0 ? round($actual / $plan * 100, 1) : null];
        });
        $income = $rows->where('category.type', 'income')->values();
        $expense = $rows->where('category.type', 'expense')->values();

        return [
            'income' => $income, 'expense' => $expense,
            'totals' => [
                'income_planned' => $income->sum('planned'), 'income_actual' => $income->sum('actual'),
                'expense_planned' => $expense->sum('planned'), 'expense_actual' => $expense->sum('actual'),
            ],
        ];
    }
}
