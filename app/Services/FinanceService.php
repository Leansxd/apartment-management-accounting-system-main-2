<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\AuditLog;
use App\Models\Cashbox;
use App\Models\CashTransaction;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\DuesDefinition;
use App\Models\Expense;
use App\Models\LedgerEntry;
use App\Models\Unit;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Apartman muhasebesi. Her işlem tek bir veritabanı transaction'ıdır ve etkilenen
 * kasa / daire / borç satırları kilitlenerek okunur: eşzamanlı iki işlem aynı bakiyeyi
 * bozamaz. Her para hareketi çift taraflı yevmiye kaydı (borç = alacak) üretir.
 */
class FinanceService
{
    // ----------------------------------------------------------------- TAHAKKUK

    /**
     * Bir daireye bir borç yazar. Dairenin avansı varsa hemen mahsup edilir.
     * $key verilirse aynı anahtarla ikinci çağrı borç üretmez ('exists' döner).
     *
     * @param  array{description:string, amount:int, due_date:string|\DateTimeInterface, category_id?:int|null,
     *     category_name?:string, period?:string|null, distribution_id?:string|null, dues_definition_id?:int|null}  $fields  amount kuruş
     * @return 'created'|'exists'
     */
    public function accrue(Unit $unit, array $fields, ?string $key = null): string
    {
        $amount = (int) $fields['amount'];
        if ($amount <= 0) {
            throw new FinanceException('Borç tutarı 0\'dan büyük olmalıdır.');
        }

        try {
            return DB::transaction(function () use ($unit, $fields, $key, $amount) {
                if ($key && Debt::where('idempotency_key', $key)->exists()) {
                    return 'exists';
                }

                $unit = Unit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
                $credit = Money::cents($unit->credit_balance);
                $usage = $credit > 0 ? min($credit, $amount) : 0;
                $remaining = $amount - $usage;

                $debt = Debt::create([
                    'apartment_id' => $unit->apartment_id,
                    'unit_id' => $unit->id,
                    'category_id' => $fields['category_id'] ?? null,
                    'category_name' => $fields['category_name'] ?? 'Genel',
                    'description' => $fields['description'],
                    'period' => $fields['period'] ?? null,
                    'due_date' => $fields['due_date'],
                    'amount' => Money::decimal($amount),
                    'paid_amount' => Money::decimal($usage),
                    'remaining_amount' => Money::decimal($remaining),
                    'status' => $remaining === 0 ? Debt::PAID : Debt::PENDING,
                    'distribution_id' => $fields['distribution_id'] ?? null,
                    'dues_definition_id' => $fields['dues_definition_id'] ?? null,
                    'kind' => $fields['kind'] ?? 'due',
                    'parent_debt_id' => $fields['parent_debt_id'] ?? null,
                    'idempotency_key' => $key,
                ]);

                $label = $unit->label();
                // Tahakkuk: daire borçlanır, gelir oluşur
                $this->ledgerPair($unit->apartment_id, "debt:{$debt->id}", 'accrual',
                    [LedgerEntry::UNIT, $unit->id], [LedgerEntry::INCOME, $debt->category_id],
                    $amount, trim("Tahakkuk: {$label} {$debt->description}"));

                if ($usage > 0) {
                    $unit->update(['credit_balance' => Money::decimal($credit - $usage)]);
                    DebtPayment::create([
                        'apartment_id' => $unit->apartment_id, 'debt_id' => $debt->id, 'unit_id' => $unit->id,
                        'type' => 'credit_usage', 'amount' => Money::decimal($usage),
                    ]);
                    // Avans mahsubu: avans hesabı kapanır, daire alacağı düşer
                    $this->ledgerPair($unit->apartment_id, "debt:{$debt->id}", 'credit_usage',
                        [LedgerEntry::UNIT_ADVANCE, $unit->id], [LedgerEntry::UNIT, $unit->id],
                        $usage, "Avans Mahsubu: {$label}");
                }

                return 'created';
            });
        } catch (UniqueConstraintViolationException) {
            return 'exists'; // aynı anahtarla eşzamanlı çalışan diğer işlem kazandı
        }
    }

    /**
     * Seçilen dairelere aynı borcu yazar. Her daire kendi transaction'ındadır: bir dairedeki
     * hata diğerlerini etkilemez, raporda döner.
     *
     * @param  Collection<int, Unit>  $units
     * @return array{created:int, failed:array<int, array{unit:string, message:string}>}
     */
    public function distribute(Apartment $apartment, Collection $units, array $fields, ?User $by = null): array
    {
        if ($units->isEmpty()) {
            throw new FinanceException('Hedef daire seçilmedi.');
        }
        $fields['distribution_id'] = (string) Str::uuid();
        $report = ['created' => 0, 'failed' => []];

        foreach ($units as $unit) {
            try {
                $this->accrue($unit, $fields) === 'created' && $report['created']++;
            } catch (FinanceException $e) {
                $report['failed'][] = ['unit' => $unit->label(), 'message' => $e->getMessage()];
            }
        }

        $this->audit($apartment, $by, 'DISTRIBUTE_DEBT', 'DEBT', $fields['distribution_id'], [
            'description' => $fields['description'], 'amount' => Money::decimal($fields['amount']),
            'units' => $units->count(), 'created' => $report['created'],
        ]);

        return $report;
    }

    /**
     * Aktif aidat tanımlarından dönem borçlarını üretir. Borç anahtarı (dönem + tanım + daire)
     * sabittir: aynı dönemi tekrar çalıştırmak mükerrer borç oluşturmaz.
     *
     * @return array{created:int, skipped:int, failed:array, skipped_no_area:array<int,string>}
     */
    public function generateDues(Apartment $apartment, string $period, ?User $by = null): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new FinanceException('Dönem YYYY-AA biçiminde olmalıdır.');
        }
        $definitions = $apartment->duesDefinitions()->where('is_active', true)->with('category')->get();
        if ($definitions->isEmpty()) {
            throw new FinanceException('Aktif aidat / borç tanımı bulunamadı.');
        }

        $units = $apartment->units()->with('block')->get();
        $report = ['created' => 0, 'skipped' => 0, 'failed' => [], 'skipped_no_area' => []];

        foreach ($definitions as $def) {
            foreach ($this->targetsOf($def, $units) as $unit) {
                $amount = Money::cents($def->amount);
                if ($def->calculation === 'sqm') {
                    if (! (float) $unit->gross_area) {
                        $report['skipped_no_area'][] = $unit->label();
                        continue;
                    }
                    $amount = (int) round($amount * (float) $unit->gross_area);
                }
                if ($amount <= 0) {
                    continue;
                }

                try {
                    $result = $this->accrue($unit, [
                        'description' => "{$period} - {$def->name}",
                        'amount' => $amount,
                        'due_date' => self::dueDateForPeriod($period, $def->due_day),
                        'period' => $period,
                        'category_id' => $def->category_id,
                        'category_name' => $def->category?->name ?? 'Aidat',
                        'dues_definition_id' => $def->id,
                    ], "dues:{$apartment->id}:{$period}:{$def->id}:{$unit->id}");
                    $result === 'created' ? $report['created']++ : $report['skipped']++;
                } catch (FinanceException $e) {
                    $report['failed'][] = ['unit' => $unit->label(), 'message' => $e->getMessage()];
                }
            }
            $def->update(['last_run_period' => $period, 'last_run_at' => now()]);
        }

        $this->audit($apartment, $by, 'GENERATE_DUES', 'DEBT', $period, $report);

        return $report;
    }

    /** "2026-02" + 31 -> 28 Şubat 2026 (ay kısaysa son güne çekilir) */
    public static function dueDateForPeriod(string $period, int $day): string
    {
        $first = CarbonImmutable::createFromFormat('Y-m-d', "{$period}-01");

        return $first->setDay(min(max($day, 1), $first->daysInMonth))->toDateString();
    }

    /** @param  Collection<int, Unit>  $units */
    private function targetsOf(DuesDefinition $def, Collection $units): Collection
    {
        return match ($def->target_type) {
            'block' => $units->where('block_id', $def->target_id),
            'unit' => $units->where('id', $def->target_id),
            default => $units,
        };
    }

    /**
     * Borcu siler. Tahsilat veya avans mahsubu yapılmış borç silinemez.
     * Silinen tahakkuk için ters yevmiye kaydı atılır.
     */
    public function deleteDebt(Debt $debt, ?User $by = null): void
    {
        DB::transaction(function () use ($debt, $by) {
            $debt = Debt::whereKey($debt->id)->lockForUpdate()->firstOrFail();
            $payments = DebtPayment::where('debt_id', $debt->id)->get();
            $cashPaid = $payments->where('type', 'collection')->sum(fn ($p) => Money::cents($p->amount));
            if ($cashPaid > 0 || (Money::cents($debt->paid_amount) > 0 && $payments->isEmpty())) {
                throw new FinanceException('Bu borca '.Money::format($debt->paid_amount)
                    .' tahsilat yapılmış; silmeden önce tahsilatı iptal edin.');
            }
            $amount = Money::cents($debt->amount);
            $label = $debt->unit?->label();

            // Yalnızca avans mahsubu yapılmışsa avans daireye geri verilir
            $usage = $payments->where('type', 'credit_usage')->sum(fn ($p) => Money::cents($p->amount));
            if ($usage > 0) {
                $this->adjustCredit($debt->unit_id, $usage);
                $this->ledgerPair($debt->apartment_id, "debt:{$debt->id}", 'credit_usage_reversal',
                    [LedgerEntry::UNIT, $debt->unit_id], [LedgerEntry::UNIT_ADVANCE, $debt->unit_id],
                    $usage, "Avans mahsubu iptali: {$label}");
                DebtPayment::where('debt_id', $debt->id)->delete();
            }

            $this->ledgerPair($debt->apartment_id, "debt:{$debt->id}", 'accrual_reversal',
                [LedgerEntry::INCOME, $debt->category_id], [LedgerEntry::UNIT, $debt->unit_id],
                $amount, trim("Tahakkuk İptali: {$label} {$debt->description}"));
            $this->audit($debt->apartment, $by, 'DELETE_DEBT', 'DEBT', "debt:{$debt->id}", [
                'amount' => $debt->amount, 'unit' => $label, 'description' => $debt->description,
            ]);
            $debt->delete();
        });
    }

    // ----------------------------------------------------------------- TAHSİLAT

    /**
     * Daireden tahsilat. Tutar önce (varsa) seçilen borca, sonra dairenin diğer açık
     * borçlarına en eski vadeden başlayarak dağıtılır; artan kısım daireye avans yazılır.
     *
     * @param  int  $amount  kuruş
     */
    public function collect(Unit $unit, Cashbox $cashbox, int $amount, ?string $description = null,
        ?User $by = null, ?Debt $preferred = null, string $subType = 'collection'): CashTransaction
    {
        if ($amount <= 0) {
            throw new FinanceException('Tahsilat tutarı 0\'dan büyük olmalıdır.');
        }
        $this->assertSameApartment($unit->apartment_id, $cashbox, $preferred);

        return DB::transaction(function () use ($unit, $cashbox, $amount, $description, $by, $preferred, $subType) {
            $unit = Unit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $cashbox = Cashbox::whereKey($cashbox->id)->lockForUpdate()->firstOrFail();

            $open = Debt::where('unit_id', $unit->id)->where('status', Debt::PENDING)
                ->orderBy('due_date')->orderBy('id')->lockForUpdate()->get();
            if ($preferred) {
                $open = $open->sortBy(fn (Debt $d) => $d->id === $preferred->id ? 0 : 1, SORT_REGULAR, false)->values();
            }

            $label = $unit->label();
            $tx = CashTransaction::create([
                'apartment_id' => $unit->apartment_id,
                'cashbox_id' => $cashbox->id,
                'type' => 'income',
                'sub_type' => $subType,
                'amount' => Money::decimal($amount),
                'unit_id' => $unit->id,
                'debt_id' => $preferred?->id,
                'description' => $description ?: "{$label} Tahsilat",
                'performed_by' => $by?->id,
            ]);

            $left = $amount;
            foreach ($open as $debt) {
                if ($left <= 0) {
                    break;
                }
                $remaining = Money::cents($debt->remaining_amount);
                $pay = min($left, $remaining);
                if ($pay <= 0) {
                    continue;
                }
                $newRemaining = $remaining - $pay;
                $debt->update([
                    'remaining_amount' => Money::decimal($newRemaining),
                    'paid_amount' => Money::decimal(Money::cents($debt->paid_amount) + $pay),
                    'status' => $newRemaining === 0 ? Debt::PAID : Debt::PENDING,
                ]);
                DebtPayment::create([
                    'apartment_id' => $unit->apartment_id, 'debt_id' => $debt->id, 'unit_id' => $unit->id,
                    'cash_transaction_id' => $tx->id, 'type' => 'collection', 'amount' => Money::decimal($pay),
                ]);
                $left -= $pay;
            }

            $applied = $amount - $left;
            $tx->update(['applied_to_debts' => Money::decimal($applied), 'to_advance' => Money::decimal($left)]);
            if ($left > 0) {
                $unit->update(['credit_balance' => Money::decimal(Money::cents($unit->credit_balance) + $left)]);
            }
            $cashbox->update(['balance' => Money::decimal(Money::cents($cashbox->balance) + $amount)]);

            // Yevmiye: Kasa (B) / Daire alacağı (A) + fazlası Avans (A)
            $ref = "cash_tx:{$tx->id}";
            $desc = "Tahsilat: {$label}";
            $this->ledger($unit->apartment_id, $ref, 'collection', LedgerEntry::CASH, $cashbox->id, $amount, 0, $desc);
            if ($applied > 0) {
                $this->ledger($unit->apartment_id, $ref, 'collection', LedgerEntry::UNIT, $unit->id, 0, $applied, $desc);
            }
            if ($left > 0) {
                $this->ledger($unit->apartment_id, $ref, 'collection', LedgerEntry::UNIT_ADVANCE, $unit->id, 0, $left, "{$desc} (Avans)");
            }

            $this->audit($unit->apartment, $by, 'COLLECTION', 'FINANCE', $ref, [
                'unit' => $label, 'cashbox' => $cashbox->name, 'amount' => Money::decimal($amount),
                'applied' => Money::decimal($applied), 'advance' => Money::decimal($left),
            ]);

            return $tx;
        });
    }

    // ----------------------------------------------------------------- KASA

    /**
     * Borçla ilişkisiz gelir / gider. Gider kasadan çıkar (bakiye kontrolü yapılır).
     *
     * @param  'income'|'expense'  $type
     * @param  int  $amount  kuruş
     */
    public function generalTransaction(Cashbox $cashbox, string $type, int $amount, string $description,
        ?int $categoryId = null, ?User $by = null): CashTransaction
    {
        if ($amount <= 0) {
            throw new FinanceException('İşlem tutarı 0\'dan büyük olmalıdır.');
        }

        return DB::transaction(function () use ($cashbox, $type, $amount, $description, $categoryId, $by) {
            $cashbox = Cashbox::whereKey($cashbox->id)->lockForUpdate()->firstOrFail();
            $isIncome = $type === 'income';
            if (! $isIncome) {
                $this->assertCanPay($cashbox, $amount);
            }
            $balance = Money::cents($cashbox->balance) + ($isIncome ? $amount : -$amount);
            $cashbox->update(['balance' => Money::decimal($balance)]);

            $tx = CashTransaction::create([
                'apartment_id' => $cashbox->apartment_id, 'cashbox_id' => $cashbox->id, 'type' => $type,
                'sub_type' => 'general', 'amount' => Money::decimal($amount), 'category_id' => $categoryId,
                'description' => $description, 'performed_by' => $by?->id,
            ]);

            $ref = "cash_tx:{$tx->id}";
            $other = $isIncome ? LedgerEntry::INCOME : LedgerEntry::EXPENSE;
            $isIncome
                ? $this->ledgerPair($cashbox->apartment_id, $ref, 'general', [LedgerEntry::CASH, $cashbox->id], [$other, $categoryId], $amount, $description)
                : $this->ledgerPair($cashbox->apartment_id, $ref, 'general', [$other, $categoryId], [LedgerEntry::CASH, $cashbox->id], $amount, $description);

            $this->audit($cashbox->apartment, $by, $isIncome ? 'GENERAL_INCOME' : 'GENERAL_EXPENSE', 'FINANCE', $ref,
                ['cashbox' => $cashbox->name, 'amount' => Money::decimal($amount), 'description' => $description]);

            return $tx;
        });
    }

    /** Kasalar arası virman. @param int $amount kuruş */
    public function transfer(Cashbox $from, Cashbox $to, int $amount, ?string $description = null, ?User $by = null): void
    {
        if ($amount <= 0) {
            throw new FinanceException('Virman tutarı 0\'dan büyük olmalıdır.');
        }
        if ($from->id === $to->id) {
            throw new FinanceException('Kaynak ve hedef kasa aynı olamaz.');
        }
        if ($from->apartment_id !== $to->apartment_id) {
            throw new FinanceException('Kasalar aynı siteye ait olmalıdır.');
        }

        DB::transaction(function () use ($from, $to, $amount, $description, $by) {
            // Kilitler her zaman id sırasıyla alınır (karşılıklı virmanlarda kilitlenme olmaz)
            $boxes = Cashbox::whereIn('id', [$from->id, $to->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $from = $boxes[$from->id];
            $to = $boxes[$to->id];
            $this->assertCanPay($from, $amount);

            $from->update(['balance' => Money::decimal(Money::cents($from->balance) - $amount)]);
            $to->update(['balance' => Money::decimal(Money::cents($to->balance) + $amount)]);

            $desc = trim($description ?? '') ?: "{$from->name} → {$to->name}";
            $out = CashTransaction::create([
                'apartment_id' => $from->apartment_id, 'cashbox_id' => $from->id, 'type' => 'expense',
                'sub_type' => 'transfer_out', 'amount' => Money::decimal($amount),
                'description' => "Virman (Giden): {$desc}", 'performed_by' => $by?->id,
            ]);
            $in = CashTransaction::create([
                'apartment_id' => $to->apartment_id, 'cashbox_id' => $to->id, 'type' => 'income',
                'sub_type' => 'transfer_in', 'amount' => Money::decimal($amount),
                'description' => "Virman (Gelen): {$desc}", 'performed_by' => $by?->id,
                'related_transaction_id' => $out->id,
            ]);
            $out->update(['related_transaction_id' => $in->id]);

            $this->ledgerPair($from->apartment_id, "cash_tx:{$out->id}", 'transfer',
                [LedgerEntry::CASH, $to->id], [LedgerEntry::CASH, $from->id], $amount, "Virman: {$desc}");
            $this->audit($from->apartment, $by, 'TRANSFER', 'FINANCE', "cash_tx:{$out->id}",
                ['from' => $from->name, 'to' => $to->name, 'amount' => Money::decimal($amount)]);
        });
    }

    // ----------------------------------------------------------------- GİDER

    /** Bekleyen gideri onaylar: KDV dahil tutar seçilen kasadan düşer. */
    public function approveExpense(Expense $expense, Cashbox $cashbox, ?User $by = null): void
    {
        $this->assertSameApartment($expense->apartment_id, $cashbox);

        DB::transaction(function () use ($expense, $cashbox, $by) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($expense->status === 'approved') {
                throw new FinanceException('Bu gider zaten onaylanmış.');
            }
            $cashbox = Cashbox::whereKey($cashbox->id)->lockForUpdate()->firstOrFail();
            $amount = Money::cents($expense->total_amount);
            if ($amount <= 0) {
                throw new FinanceException('Gider tutarı geçersiz.');
            }
            $this->assertCanPay($cashbox, $amount);

            $cashbox->update(['balance' => Money::decimal(Money::cents($cashbox->balance) - $amount)]);
            $expense->update([
                'status' => 'approved', 'cashbox_id' => $cashbox->id,
                'approved_by' => $by?->id, 'approved_at' => now(),
            ]);
            $invoice = $expense->invoice_no ? " (Fatura: {$expense->invoice_no})" : '';
            $tx = CashTransaction::create([
                'apartment_id' => $expense->apartment_id, 'cashbox_id' => $cashbox->id, 'type' => 'expense',
                'sub_type' => 'invoice', 'amount' => Money::decimal($amount), 'expense_id' => $expense->id,
                'category_id' => $expense->category_id,
                'description' => "Gider: {$expense->description}{$invoice}", 'performed_by' => $by?->id,
            ]);
            $this->ledgerPair($expense->apartment_id, "cash_tx:{$tx->id}", 'expense',
                [LedgerEntry::EXPENSE, $expense->category_id], [LedgerEntry::CASH, $cashbox->id],
                $amount, $expense->description);
            $this->audit($expense->apartment, $by, 'APPROVE_EXPENSE', 'EXPENSE', "expense:{$expense->id}",
                ['cashbox' => $cashbox->name, 'amount' => Money::decimal($amount)]);
        });
    }

    // ----------------------------------------------------------------- GECİKME ZAMMI

    /**
     * Henüz yazılmamış gecikme zammı kalemleri. Her açık borç için, vadesinden (+ ek süre) sonra
     * tamamlanan her ay bir kalem üretir: tutar = o anki kalan borç × aylık oran. Kalem anahtarı
     * (borç + ay no) sabittir; aynı hesap tekrar çalıştırılsa mükerrer zam yazılmaz.
     * Gecikme zammı borçlarına ayrıca zam işletilmez.
     *
     * @return Collection<int, array{debt: Debt, month: int, amount: int, key: string}>
     */
    public function pendingLateFees(Apartment $apartment, ?CarbonImmutable $asOf = null): Collection
    {
        $rate = (float) $apartment->late_fee_rate;
        if (! $apartment->late_fee_enabled || $rate <= 0) {
            return collect();
        }
        $asOf ??= CarbonImmutable::today();
        $existing = Debt::where('apartment_id', $apartment->id)->where('kind', 'late_fee')
            ->pluck('idempotency_key')->flip();

        $items = collect();
        $debts = $apartment->debts()->whereIn('kind', ['due', 'installment'])->where('status', Debt::PENDING)
            ->whereDate('due_date', '<', $asOf)->with('unit.block')->orderBy('due_date')->get();
        foreach ($debts as $debt) {
            $start = CarbonImmutable::parse($debt->due_date)->addDays($apartment->late_fee_grace_days);
            $months = $start->lt($asOf) ? (int) floor($start->diffInMonths($asOf)) : 0;
            $amount = (int) round(Money::cents($debt->remaining_amount) * $rate / 100);
            for ($n = 1; $n <= $months && $amount > 0; $n++) {
                if (! $existing->has($key = "late:{$debt->id}:{$n}")) {
                    $items->push(['debt' => $debt, 'month' => $n, 'amount' => $amount, 'key' => $key]);
                }
            }
        }

        return $items;
    }

    /** @return array{created: int, total: int} total kuruş */
    public function runLateFees(Apartment $apartment, ?CarbonImmutable $asOf = null, ?User $by = null): array
    {
        $asOf ??= CarbonImmutable::today();
        $items = $this->pendingLateFees($apartment, $asOf);
        if ($items->isEmpty()) {
            return ['created' => 0, 'total' => 0];
        }
        $category = $apartment->categories()->firstOrCreate(['name' => 'Gecikme Zammı', 'type' => 'income']);

        $created = $total = 0;
        foreach ($items as $item) {
            /** @var Debt $debt */
            $debt = $item['debt'];
            $result = $this->accrue($debt->unit, [
                'description' => "Gecikme zammı ({$item['month']}. ay): {$debt->description}",
                'amount' => $item['amount'],
                'due_date' => $asOf->toDateString(),
                'period' => $asOf->format('Y-m'),
                'category_id' => $category->id,
                'category_name' => $category->name,
                'kind' => 'late_fee',
                'parent_debt_id' => $debt->id,
            ], $item['key']);
            if ($result === 'created') {
                $created++;
                $total += $item['amount'];
            }
        }
        $this->audit($apartment, $by, 'LATE_FEES', 'DEBT', $asOf->toDateString(), ['created' => $created, 'total' => Money::decimal($total)]);

        return ['created' => $created, 'total' => $total];
    }

    // ----------------------------------------------------------------- İPTAL, İADE, MAHSUP, TAKSİT

    /**
     * Kasa hareketini iptal eder: yalnızca ters kayıt atılır, hiçbir kayıt silinmez.
     * Tahsilat iptalinde ödenen borçlar yeniden açılır ve avans geri alınır; gider ödemesi iptalinde
     * gider tekrar onay bekler. Virmanlar iptal edilemez (ters virman yapılır).
     */
    public function reverse(CashTransaction $tx, string $reason, ?User $by = null): CashTransaction
    {
        return DB::transaction(function () use ($tx, $reason, $by) {
            $tx = CashTransaction::whereKey($tx->id)->lockForUpdate()->firstOrFail();
            if ($tx->isReversed() || $tx->sub_type === 'reversal') {
                throw new FinanceException('Bu hareket zaten iptal edilmiş veya bir iptal kaydıdır.');
            }
            if ($tx->isTransfer()) {
                throw new FinanceException('Virman iptal edilemez; ters yönde yeni bir virman yapın.');
            }
            $cashbox = Cashbox::whereKey($tx->cashbox_id)->lockForUpdate()->firstOrFail();
            $amount = Money::cents($tx->amount);
            $wasIncome = $tx->type === 'income';
            if ($wasIncome) {
                $this->assertCanPay($cashbox, $amount); // iptal, kasadan para çıkışıdır
            }

            match ($tx->sub_type) {
                'collection', 'pos', 'advance' => $this->undoCollection($tx),
                'invoice' => $this->undoExpense($tx),
                'refund' => $this->adjustCredit($tx->unit_id, $amount),
                default => null,
            };

            $cashbox->update(['balance' => Money::decimal(Money::cents($cashbox->balance) + ($wasIncome ? -$amount : $amount))]);
            $reversal = CashTransaction::create([
                'apartment_id' => $tx->apartment_id, 'cashbox_id' => $tx->cashbox_id,
                'type' => $wasIncome ? 'expense' : 'income', 'sub_type' => 'reversal', 'amount' => Money::decimal($amount),
                'unit_id' => $tx->unit_id, 'category_id' => $tx->category_id, 'expense_id' => $tx->expense_id,
                'description' => "İptal: {$tx->description} — {$reason}", 'performed_by' => $by?->id,
                'reversal_of_id' => $tx->id,
            ]);
            foreach (LedgerEntry::where('reference', "cash_tx:{$tx->id}")->get() as $entry) {
                LedgerEntry::create([
                    'apartment_id' => $entry->apartment_id, 'reference' => "cash_tx:{$reversal->id}", 'entry_type' => 'reversal',
                    'account_name' => $entry->account_name, 'account_id' => $entry->account_id,
                    'debit' => $entry->credit, 'credit' => $entry->debit, 'description' => mb_substr("İptal: {$entry->description}", 0, 250),
                ]);
            }
            $tx->update(['reversed_at' => now(), 'reversed_by' => $by?->id, 'reversal_reason' => $reason]);
            $this->audit($tx->apartment, $by, 'REVERSE_TRANSACTION', 'FINANCE', "cash_tx:{$tx->id}",
                ['amount' => Money::decimal($amount), 'reason' => $reason, 'type' => $tx->sub_type]);

            return $reversal;
        });
    }

    private function undoCollection(CashTransaction $tx): void
    {
        $unit = Unit::whereKey($tx->unit_id)->lockForUpdate()->firstOrFail();
        $toAdvance = Money::cents($tx->to_advance);
        if ($toAdvance > 0 && Money::cents($unit->credit_balance) < $toAdvance) {
            throw new FinanceException('Bu tahsilattan doğan avans başka bir borca mahsup edilmiş; önce o borcu iptal edin.');
        }
        foreach (DebtPayment::where('cash_transaction_id', $tx->id)->get() as $payment) {
            $debt = Debt::whereKey($payment->debt_id)->lockForUpdate()->first();
            if ($debt) {
                $debt->update([
                    'paid_amount' => Money::decimal(Money::cents($debt->paid_amount) - Money::cents($payment->amount)),
                    'remaining_amount' => Money::decimal(Money::cents($debt->remaining_amount) + Money::cents($payment->amount)),
                    'status' => Debt::PENDING,
                ]);
            }
            $payment->delete();
        }
        if ($toAdvance > 0) {
            $this->adjustCredit($unit->id, -$toAdvance);
        }
    }

    private function undoExpense(CashTransaction $tx): void
    {
        Expense::whereKey($tx->expense_id)->update(['status' => 'pending', 'cashbox_id' => null, 'approved_by' => null, 'approved_at' => null]);
    }

    /** Dairenin avansını kuruş cinsinden artırır / azaltır (çağıran transaction içinde olmalıdır). */
    private function adjustCredit(?int $unitId, int $cents): void
    {
        $unit = Unit::whereKey($unitId)->lockForUpdate()->firstOrFail();
        $unit->update(['credit_balance' => Money::decimal(Money::cents($unit->credit_balance) + $cents)]);
    }

    /** Dairenin avansını kasadan geri öder. @param int $amount kuruş */
    public function refundAdvance(Unit $unit, Cashbox $cashbox, int $amount, ?User $by = null): CashTransaction
    {
        if ($amount <= 0) {
            throw new FinanceException('İade tutarı 0\'dan büyük olmalıdır.');
        }
        $this->assertSameApartment($unit->apartment_id, $cashbox);

        return DB::transaction(function () use ($unit, $cashbox, $amount, $by) {
            $unit = Unit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $cashbox = Cashbox::whereKey($cashbox->id)->lockForUpdate()->firstOrFail();
            if ($amount > Money::cents($unit->credit_balance)) {
                throw new FinanceException('İade tutarı dairenin avansını ('.Money::format($unit->credit_balance).') aşamaz.');
            }
            $this->assertCanPay($cashbox, $amount);

            $unit->update(['credit_balance' => Money::decimal(Money::cents($unit->credit_balance) - $amount)]);
            $cashbox->update(['balance' => Money::decimal(Money::cents($cashbox->balance) - $amount)]);
            $tx = CashTransaction::create([
                'apartment_id' => $unit->apartment_id, 'cashbox_id' => $cashbox->id, 'type' => 'expense', 'sub_type' => 'refund',
                'amount' => Money::decimal($amount), 'unit_id' => $unit->id,
                'description' => "Avans iadesi: {$unit->label()}", 'performed_by' => $by?->id,
            ]);
            $this->ledgerPair($unit->apartment_id, "cash_tx:{$tx->id}", 'refund',
                [LedgerEntry::UNIT_ADVANCE, $unit->id], [LedgerEntry::CASH, $cashbox->id], $amount, "Avans iadesi: {$unit->label()}");
            $this->audit($unit->apartment, $by, 'REFUND_ADVANCE', 'FINANCE', "cash_tx:{$tx->id}", ['unit' => $unit->label(), 'amount' => Money::decimal($amount)]);

            return $tx;
        });
    }

    /** Dairenin mevcut avansını açık borçlarına en eski vadeden başlayarak mahsup eder. @return int mahsup edilen kuruş */
    public function applyAdvance(Unit $unit, ?User $by = null): int
    {
        return DB::transaction(function () use ($unit, $by) {
            $unit = Unit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $credit = Money::cents($unit->credit_balance);
            if ($credit <= 0) {
                throw new FinanceException('Dairenin mahsup edilecek avansı yok.');
            }
            $used = 0;
            foreach (Debt::where('unit_id', $unit->id)->where('status', Debt::PENDING)->orderBy('due_date')->orderBy('id')->lockForUpdate()->get() as $debt) {
                $pay = min($credit - $used, Money::cents($debt->remaining_amount));
                if ($pay <= 0) {
                    continue;
                }
                $left = Money::cents($debt->remaining_amount) - $pay;
                $debt->update([
                    'remaining_amount' => Money::decimal($left),
                    'paid_amount' => Money::decimal(Money::cents($debt->paid_amount) + $pay),
                    'status' => $left === 0 ? Debt::PAID : Debt::PENDING,
                ]);
                DebtPayment::create(['apartment_id' => $unit->apartment_id, 'debt_id' => $debt->id, 'unit_id' => $unit->id,
                    'type' => 'credit_usage', 'amount' => Money::decimal($pay)]);
                $used += $pay;
                if ($used >= $credit) {
                    break;
                }
            }
            if ($used === 0) {
                throw new FinanceException('Dairenin açık borcu yok; avans mahsup edilemedi.');
            }
            $unit->update(['credit_balance' => Money::decimal($credit - $used)]);
            $this->ledgerPair($unit->apartment_id, "unit:{$unit->id}", 'credit_usage',
                [LedgerEntry::UNIT_ADVANCE, $unit->id], [LedgerEntry::UNIT, $unit->id], $used, "Avans mahsubu: {$unit->label()}");
            $this->audit($unit->apartment, $by, 'APPLY_ADVANCE', 'FINANCE', "unit:{$unit->id}", ['unit' => $unit->label(), 'amount' => Money::decimal($used)]);

            return $used;
        });
    }

    /**
     * Açık borcu taksitlendirir. Borcun ödenmemiş kısmı ters kayıtla kapatılır ve eşit (kuruşu
     * tutan) taksitler olarak yeniden tahakkuk ettirilir. Ödenmiş kısım özgün borçta kalır.
     *
     * @return Collection<int, Debt> oluşan taksitler
     */
    public function installments(Debt $debt, int $count, string $firstDue, int $everyMonths = 1, ?User $by = null): Collection
    {
        if ($count < 2 || $count > 36) {
            throw new FinanceException('Taksit sayısı 2 ile 36 arasında olmalıdır.');
        }
        if ($everyMonths < 1 || $everyMonths > 12) {
            throw new FinanceException('Taksit aralığı 1 ile 12 ay arasında olmalıdır.');
        }

        return DB::transaction(function () use ($debt, $count, $firstDue, $everyMonths, $by) {
            $debt = Debt::whereKey($debt->id)->lockForUpdate()->firstOrFail();
            $remaining = Money::cents($debt->remaining_amount);
            if ($debt->status !== Debt::PENDING || $remaining <= 0) {
                throw new FinanceException('Yalnızca açık borç taksitlendirilebilir.');
            }
            $unit = $debt->unit;
            $this->ledgerPair($debt->apartment_id, "debt:{$debt->id}", 'restructure',
                [LedgerEntry::INCOME, $debt->category_id], [LedgerEntry::UNIT, $debt->unit_id],
                $remaining, "Taksitlendirme: {$unit->label()} {$debt->description}");
            $debt->update(['amount' => $debt->paid_amount, 'remaining_amount' => 0, 'status' => Debt::PAID,
                'description' => "{$debt->description} (taksitlendirildi)"]);

            $parts = Money::allocate($remaining, array_fill(1, $count, 1));
            $first = CarbonImmutable::parse($firstDue);
            $created = collect();
            foreach ($parts as $i => $part) {
                $this->accrue($unit, [
                    'description' => "Taksit {$i}/{$count}: ".str_replace(' (taksitlendirildi)', '', $debt->description),
                    'amount' => $part,
                    'due_date' => $first->addMonthsNoOverflow(($i - 1) * $everyMonths)->toDateString(),
                    'period' => $debt->period,
                    'category_id' => $debt->category_id, 'category_name' => $debt->category_name,
                    'kind' => 'installment', 'parent_debt_id' => $debt->id,
                ], "inst:{$debt->id}:{$i}");
            }
            $this->audit($debt->apartment, $by, 'INSTALLMENTS', 'DEBT', "debt:{$debt->id}",
                ['unit' => $unit->label(), 'amount' => Money::decimal($remaining), 'count' => $count]);

            return Debt::where('parent_debt_id', $debt->id)->orderBy('due_date')->get();
        });
    }

    /** Borcu icra / hukuki takip olarak işaretler veya işareti kaldırır. */
    public function setLegal(Debt $debt, bool $on, ?string $note = null, ?User $by = null): void
    {
        $debt->update($on
            ? ['legal_status' => 'icra', 'legal_note' => $note, 'legal_at' => today()]
            : ['legal_status' => null, 'legal_note' => null, 'legal_at' => null]);
        $this->audit($debt->apartment, $by, $on ? 'LEGAL_ON' : 'LEGAL_OFF', 'DEBT', "debt:{$debt->id}", ['note' => $note]);
    }

    // ----------------------------------------------------------------- YARDIMCILAR

    /** Apartman ayarı izin vermiyorsa kasa eksiye düşemez. Çağıran, kasayı kilitli okumuş olmalıdır. */
    private function assertCanPay(Cashbox $cashbox, int $amount): void
    {
        $allowNegative = (bool) Apartment::whereKey($cashbox->apartment_id)->value('allow_negative_cashbox');
        $balance = Money::cents($cashbox->balance);
        if (! $allowNegative && $balance - $amount < 0) {
            throw new FinanceException("\"{$cashbox->name}\" bakiyesi yetersiz: mevcut ".Money::format($balance / 100)
                .', gereken '.Money::format($amount / 100).'. (Eksi bakiyeye izin vermek için Kasa ayarlarını kullanabilirsiniz.)');
        }
    }

    private function assertSameApartment(int $apartmentId, Cashbox $cashbox, ?Debt $debt = null): void
    {
        if ($cashbox->apartment_id !== $apartmentId || ($debt && $debt->apartment_id !== $apartmentId)) {
            throw new FinanceException('Kayıtlar aynı siteye ait değil.');
        }
    }

    /** @param array{0:string,1:int|string|null} $debit @param array{0:string,1:int|string|null} $credit */
    private function ledgerPair(int $apartmentId, string $ref, string $type, array $debit, array $credit, int $amount, string $description): void
    {
        $this->ledger($apartmentId, $ref, $type, $debit[0], $debit[1], $amount, 0, $description);
        $this->ledger($apartmentId, $ref, $type, $credit[0], $credit[1], 0, $amount, $description);
    }

    private function ledger(int $apartmentId, string $ref, string $type, string $account, int|string|null $accountId,
        int $debit, int $credit, string $description): void
    {
        LedgerEntry::create([
            'apartment_id' => $apartmentId, 'reference' => $ref, 'entry_type' => $type,
            'account_name' => $account, 'account_id' => $accountId === null ? null : (string) $accountId,
            'debit' => Money::decimal($debit), 'credit' => Money::decimal($credit),
            'description' => mb_substr($description, 0, 250),
        ]);
    }

    public function audit(?Apartment $apartment, ?User $by, string $action, string $module, ?string $reference, array $data = []): void
    {
        AuditLog::create([
            'apartment_id' => $apartment?->id, 'user_id' => $by?->id, 'action' => $action,
            'module' => $module, 'reference' => $reference, 'data' => $data,
        ]);
    }
}
