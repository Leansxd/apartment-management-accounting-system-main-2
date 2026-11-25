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

        $cashboxes = $apartment->cashboxes()->get()->mapWithKeys(fn (Cashbox $box) => [$box->id => $box->labelWithCurrency()]);
        $categories = $apartment->categories()->pluck('name', 'id');
        $units = $apartment->units()->withTrashed()->with('block')->get()->keyBy('id');

        $rows = $rows->map(function ($r) use ($cashboxes, $categories, $units) {
            $id = $r->account_id;
            $sub = match ($r->account_name) {
                LedgerEntry::CASH => $cashboxes[$id] ?? null,
                LedgerEntry::INCOME, LedgerEntry::EXPENSE => $id ? ($categories[$id] ?? null) : 'Kategorisiz',
                LedgerEntry::UNIT, LedgerEntry::UNIT_ADVANCE => $id ? ($units[$id] ?? null)?->label() : null,
                default => null,
            };

            return [
                'account' => $r->account_name, 'account_id' => $id, 'sub' => $sub ?? ($id ? "#{$id}" : '—'),
                'unit' => $id && in_array($r->account_name, [LedgerEntry::UNIT, LedgerEntry::UNIT_ADVANCE], true) ? ($units[$id] ?? null) : null,
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
     * Tutarlar TRY karşılığıdır; döviz kasasında native_* alanları kasanın kendi para birimindedir.
     *
     * @return array{opening: float, rows: array<int, array>, closing: float, in: float, out: float, native_opening: float, native_closing: float}
     */
    public function cashBook(Cashbox $cashbox, ?string $from = null, ?string $to = null): array
    {
        $signed = 'CASE WHEN type = \'income\' THEN amount ELSE -amount END';
        $opening = $from
            ? (float) CashTransaction::where('cashbox_id', $cashbox->id)->whereDate('created_at', '<', $from)->sum(DB::raw($signed))
            : 0.0;
        $nativeOpening = $from
            ? (float) CashTransaction::where('cashbox_id', $cashbox->id)->whereDate('created_at', '<', $from)
                ->sum(DB::raw('CASE WHEN type = \'income\' THEN original_amount ELSE -original_amount END'))
            : 0.0;

        $balance = Money::cents($opening);
        $nativeBalance = Money::cents($nativeOpening);
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
            $nativeCents = Money::cents($t->original_amount);
            $nativeBalance += $isIn ? $nativeCents : -$nativeCents;
            $rows[] = ['tx' => $t, 'in' => $isIn ? $cents / 100 : 0.0, 'out' => $isIn ? 0.0 : $cents / 100, 'balance' => $balance / 100, 'native_balance' => $nativeBalance / 100];
        }

        return ['opening' => $opening, 'rows' => $rows, 'closing' => $balance / 100, 'in' => $in / 100, 'out' => $out / 100,
            'native_opening' => $nativeOpening, 'native_closing' => $nativeBalance / 100];
    }

    /**
     * Mali durum tablosu, verilen günün sonu itibarıyla: varlıklar (kasa, alacaklar, demirbaş net değeri) ve
     * yükümlülükler (daire avansları, ödenecek giderler); fark net varlıktır. Kasa, alacak ve avans yevmiyeden
     * hesaplanır, bu yüzden geçmiş bir tarih için de doğrudur. Gecikmiş alacak yalnızca bugün için verilir.
     *
     * @return array{as_of: CarbonImmutable, cash: Collection, cash_total: float, receivables: float, overdue: float|null,
     *     fixed_assets: float, assets_total: float, advances: float, payables: float, liabilities_total: float, net: float}
     */
    public function balanceSheet(Apartment $apartment, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::today();
        $until = $asOf->endOfDay();
        $ledger = fn (string $account) => $apartment->ledgerEntries()->where('account_name', $account)->where('created_at', '<=', $until);

        $cashByBox = $ledger(LedgerEntry::CASH)->selectRaw('account_id, SUM(debit) - SUM(credit) balance')->groupBy('account_id')->pluck('balance', 'account_id');
        $boxes = $apartment->cashboxes()->orderBy('name')->get();
        $native = CashTransaction::whereIn('cashbox_id', $boxes->pluck('id'))->where('created_at', '<=', $until)
            ->selectRaw("cashbox_id, SUM(CASE WHEN type = 'income' THEN original_amount ELSE -original_amount END) total")
            ->groupBy('cashbox_id')->pluck('total', 'cashbox_id');
        $cash = $boxes->map(fn (Cashbox $box) => [
            'cashbox' => $box, 'balance' => round((float) ($cashByBox[$box->id] ?? 0), 2), 'native' => round((float) ($native[$box->id] ?? 0), 2),
        ]);

        $fixed = $apartment->assets()->whereDate('purchase_date', '<=', $asOf)->get()
            ->reject(fn (Asset $a) => $a->disposed_on && $a->disposed_on->lte($asOf))
            ->sum(fn (Asset $a) => (clone $a)->forceFill(['disposed_on' => null])->bookValue($asOf)) / 100;

        // Ödenecek gider: o güne kadar girilen faturalar − o güne kadar yapılan (iptal edilmemiş) ödemeler
        $invoiced = (float) $apartment->expenses()->where('created_at', '<=', $until)->sum('total_amount');
        $paid = (float) CashTransaction::where('apartment_id', $apartment->id)->whereNotNull('expense_id')->where('created_at', '<=', $until)
            ->sum(DB::raw("CASE WHEN sub_type = 'invoice' THEN amount WHEN sub_type = 'reversal' THEN -amount ELSE 0 END"));

        $cashTotal = round($cash->sum('balance'), 2);
        $receivables = round((float) $ledger(LedgerEntry::UNIT)->sum(DB::raw('debit - credit')), 2);
        $advances = round((float) $ledger(LedgerEntry::UNIT_ADVANCE)->sum(DB::raw('credit - debit')), 2);
        $payables = round(max($invoiced - $paid, 0), 2);
        $overdue = $asOf->isToday()
            ? (float) $apartment->debts()->where('status', Debt::PENDING)->whereDate('due_date', '<', today())->sum('remaining_amount')
            : null;

        $assetsTotal = round($cashTotal + $receivables + $fixed, 2);
        $liabilitiesTotal = round($advances + $payables, 2);

        return [
            'as_of' => $asOf, 'cash' => $cash, 'cash_total' => $cashTotal, 'receivables' => $receivables, 'overdue' => $overdue,
            'fixed_assets' => round($fixed, 2), 'assets_total' => $assetsTotal,
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
     * Gelirde gerçekleşen, borca yapılan tahsilat ile avanstan mahsup edilen tutarın toplamıdır: avans girdiğinde
     * kategorisizdir, hangi gelire ait olduğu mahsup edildiği borçla belli olur.
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
            ->where('debt_payments.apartment_id', $apartment->id)->whereIn('debt_payments.type', ['collection', 'credit_usage'])
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

    /**
     * Bütçe ve gerçekleşenin geçmiş yıllarla karşılaştırması. Kategori satırları seçilen yılın kategorileridir;
     * her satıra geçmiş yılların planı ve gerçekleşeni, en yakın geçmiş yıla göre fark ve yüzde değişim eklenir.
     * Yıllık özet eskiden yeniye sıralıdır, her yıl bir önceki satıra göre yüzde değişimiyle gelir.
     *
     * @param  list<int>  $compareYears  seçilen yıldan önceki yıllar, yeniden eskiye
     * @return array{rows: array<string, Collection>, summary: Collection, years: list<int>}
     */
    public function budgetComparison(Apartment $apartment, int $year, array $compareYears): array
    {
        $years = array_values(array_unique([$year, ...$compareYears]));
        $budgets = [];
        foreach ($years as $y) {
            $budgets[$y] = $this->budget($apartment, $y);
        }

        $rows = [];
        foreach (['income', 'expense'] as $type) {
            $rows[$type] = $budgets[$year][$type]->map(function (array $row) use ($budgets, $compareYears, $type) {
                $history = [];
                foreach ($compareYears as $y) {
                    $past = $budgets[$y][$type]->first(fn (array $r) => $r['category']->id === $row['category']->id);
                    $history[$y] = ['planned' => (float) ($past['planned'] ?? 0), 'actual' => (float) ($past['actual'] ?? 0)];
                }
                $base = $compareYears === [] ? null : $history[$compareYears[0]]['actual'];

                return $row + [
                    'history' => $history,
                    'change' => $base === null ? null : round($row['actual'] - $base, 2),
                    'change_pct' => $base ? round(($row['actual'] - $base) / $base * 100, 1) : null,
                ];
            });
        }

        $pct = fn (float $now, ?float $before) => $before ? round(($now - $before) / $before * 100, 1) : null;
        $previous = null;
        $summary = collect($years)->sort()->values()->map(function (int $y) use ($budgets, $pct, &$previous) {
            $t = $budgets[$y]['totals'];
            $row = [
                'year' => $y,
                'income_planned' => (float) $t['income_planned'], 'income_actual' => (float) $t['income_actual'],
                'expense_planned' => (float) $t['expense_planned'], 'expense_actual' => (float) $t['expense_actual'],
                'net' => round($t['income_actual'] - $t['expense_actual'], 2),
                'income_change' => $previous ? $pct($t['income_actual'], $previous['income_actual']) : null,
                'expense_change' => $previous ? $pct($t['expense_actual'], $previous['expense_actual']) : null,
            ];
            $previous = $row;

            return $row;
        });

        return ['rows' => $rows, 'summary' => $summary, 'years' => $years];
    }

    /**
     * Bütçedeki gerçekleşenin aylara dağılımı. budget() ile aynı kurallar: gelirde kategorili borçlara yapılan
     * tahsilat ve avans mahsubu ile kategorili genel gelir, giderde kategorili genel gider.
     *
     * @return array{income: array<int, float>, expense: array<int, float>} ay (1-12) => tutar
     */
    public function budgetMonthly(Apartment $apartment, int $year): array
    {
        $months = array_fill(1, 12, 0.0);
        $result = ['income' => $months, 'expense' => $months];
        $start = "{$year}-01-01 00:00:00";
        $end = "{$year}-12-31 23:59:59";
        $categoryIds = $apartment->categories()->pluck('id');

        $collections = DB::table('debt_payments')->join('debts', 'debts.id', '=', 'debt_payments.debt_id')
            ->where('debt_payments.apartment_id', $apartment->id)->whereIn('debt_payments.type', ['collection', 'credit_usage'])
            ->whereIn('debts.category_id', $categoryIds)->whereBetween('debt_payments.created_at', [$start, $end])
            ->get(['debt_payments.amount', 'debt_payments.created_at']);
        foreach ($collections as $payment) {
            $result['income'][(int) CarbonImmutable::parse($payment->created_at)->month] += (float) $payment->amount;
        }

        $transactions = $apartment->cashTransactions()->operating()->whereBetween('created_at', [$start, $end])
            ->whereIn('category_id', $categoryIds)->get(['type', 'amount', 'created_at']);
        foreach ($transactions as $transaction) {
            if (isset($result[$transaction->type])) {
                $result[$transaction->type][(int) $transaction->created_at->month] += (float) $transaction->amount;
            }
        }

        return array_map(fn (array $values) => array_map(fn (float $v) => round($v, 2), $values), $result);
    }

    /**
     * Bütçe sayfası için düz metin değerlendirmeler: net sonuç, bütçe gerçekleşmesi (içinde bulunulan yılda
     * yılın geçen kısmıyla kıyaslanır), önceki yıla göre değişim, bütçeyi aşan ve en çok artan gider kalemleri.
     *
     * @param  array{rows: array<string, Collection>, summary: Collection}  $comparison  budgetComparison() sonucu
     * @param  list<int>  $compareYears
     * @return list<array{tone: 'good'|'warn'|'bad'|'info', text: string}>
     */
    public function budgetInsights(array $comparison, int $year, array $compareYears, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $current = $comparison['summary']->firstWhere('year', $year);
        $income = $comparison['rows']['income'];
        $expense = $comparison['rows']['expense'];
        $insights = [];
        $percent = fn (float $value) => '%'.number_format($value, 1, ',', '.');

        if ($current['income_actual'] == 0 && $current['expense_actual'] == 0) {
            return [['tone' => 'info', 'text' => "{$year} yılında bütçe kategorilerine işlenmiş gelir veya gider yok."]];
        }

        $net = $current['net'];
        $insights[] = $net >= 0
            ? ['tone' => 'good', 'text' => "{$year} yılında gelir, giderden ".tl($net).' fazla.']
            : ['tone' => 'bad', 'text' => "{$year} yılında gider, gelirden ".tl(abs($net)).' fazla; aradaki fark kasadan karşılanıyor.'];

        $elapsed = match (true) {
            $year < $today->year => 100.0,
            $year > $today->year => 0.0,
            default => round($today->dayOfYear / $today->daysInYear * 100, 1),
        };
        $isOngoing = $year === $today->year;

        if ($current['expense_planned'] > 0) {
            $used = round($current['expense_actual'] / $current['expense_planned'] * 100, 1);
            $insights[] = match (true) {
                $used > 100 => ['tone' => 'bad', 'text' => 'Gider bütçesi '.$percent($used - 100).' aşıldı ('.tl($current['expense_actual'] - $current['expense_planned']).' fazla harcandı).'],
                $isOngoing && $used > $elapsed + 10 => ['tone' => 'warn', 'text' => "Yılın {$percent($elapsed)}'i geçti ama gider bütçesinin {$percent($used)}'i kullanıldı; bu hızla yıl sonunda bütçe aşılabilir."],
                $isOngoing => ['tone' => 'good', 'text' => "Yılın {$percent($elapsed)}'i geçti, gider bütçesinin {$percent($used)}'i kullanıldı; harcama planla uyumlu."],
                default => ['tone' => 'good', 'text' => "Gider bütçesinin {$percent($used)}'i kullanıldı; bütçe aşılmadı."],
            };
        }

        if ($current['income_planned'] > 0) {
            $collected = round($current['income_actual'] / $current['income_planned'] * 100, 1);
            $insights[] = match (true) {
                $collected >= 100 => ['tone' => 'good', 'text' => "Gelir hedefi tutturuldu: planlanan gelirin {$percent($collected)}'i tahsil edildi."],
                $isOngoing && $collected + 10 < $elapsed => ['tone' => 'warn', 'text' => "Planlanan gelirin {$percent($collected)}'i tahsil edildi; yılın geçen kısmına ({$percent($elapsed)}) göre tahsilat geride."],
                $isOngoing => ['tone' => 'info', 'text' => "Planlanan gelirin {$percent($collected)}'i tahsil edildi (yılın {$percent($elapsed)}'i geçti)."],
                default => ['tone' => 'warn', 'text' => "Planlanan gelirin {$percent($collected)}'i tahsil edilebildi; ".tl($current['income_planned'] - $current['income_actual']).' eksik kaldı.'],
            };
        }

        if ($compareYears !== []) {
            $base = $compareYears[0];
            $changes = [];
            foreach (['income' => 'Gelir', 'expense' => 'Gider'] as $type => $label) {
                $change = $current["{$type}_change"];
                if ($change !== null && $change != 0) {
                    $changes[] = $label.' '.($change > 0 ? $percent($change).' arttı' : $percent(abs($change)).' azaldı');
                }
            }
            if ($changes !== []) {
                $expenseUp = ($current['expense_change'] ?? 0) > ($current['income_change'] ?? 0);
                $note = $isOngoing ? ' (yıl henüz bitmediği için tam yıl ile kıyaslanıyor)' : '';
                $insights[] = ['tone' => $expenseUp ? 'warn' : 'good', 'text' => "{$base} yılına göre: ".implode(', ', $changes)."{$note}."];
            }

            $riser = $expense->filter(fn (array $r) => $r['change'] > 0)->sortByDesc('change')->first();
            if ($riser) {
                $insights[] = ['tone' => 'warn', 'text' => "En çok artan gider: {$riser['category']->name} (".'+'.tl($riser['change']).($riser['change_pct'] !== null ? ', +'.$percent($riser['change_pct']) : ', önceki yıl yoktu').').'];
            }
        }

        $over = $expense->filter(fn (array $r) => $r['planned'] > 0 && $r['actual'] > $r['planned']);
        if ($over->isNotEmpty()) {
            $insights[] = ['tone' => 'bad', 'text' => 'Bütçesini aşan gider kalemleri: '.$over->map(fn (array $r) => $r['category']->name.' (+'.tl($r['actual'] - $r['planned']).')')->implode(', ').'.'];
        }

        $top = $expense->sortByDesc('actual')->first();
        if ($top && $top['actual'] > 0 && $current['expense_actual'] > 0) {
            $insights[] = ['tone' => 'info', 'text' => "En büyük gider kalemi {$top['category']->name}: toplam giderin ".$percent($top['actual'] / $current['expense_actual'] * 100).'\'i.'];
        }

        $unplanned = $income->merge($expense)->filter(fn (array $r) => $r['planned'] == 0 && $r['actual'] > 0);
        if ($current['income_planned'] == 0 && $current['expense_planned'] == 0) {
            $insights[] = ['tone' => 'info', 'text' => "{$year} için bütçe girilmemiş; sayfanın altındaki tablodan kategori tutarlarını girerek plan oluşturabilirsiniz."];
        } elseif ($unplanned->isNotEmpty()) {
            $insights[] = ['tone' => 'info', 'text' => 'Bütçesi girilmemiş ama hareket gören kategoriler: '.$unplanned->map(fn (array $r) => $r['category']->name)->implode(', ').'.'];
        }

        return $insights;
    }
}
