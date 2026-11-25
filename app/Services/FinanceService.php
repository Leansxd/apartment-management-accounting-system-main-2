<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\AuditLog;
use App\Models\BankStatementLine;
use App\Models\Cashbox;
use App\Models\CashTransaction;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\DuesDefinition;
use App\Models\Expense;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Unit;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
    /** decimal(14,2) kolonlarına sığan en büyük tutar (kuruş) */
    private const MAX_CENTS = 99_999_999_999_999;

    // ----------------------------------------------------------------- TAHAKKUK

    /**
     * Bir daireye bir borç yazar. Dairenin avansı varsa hemen mahsup edilir.
     * $key verilirse aynı anahtarla ikinci çağrı borç üretmez ('exists' döner).
     *
     * @param  array{description:string, amount:int, due_date:string|\DateTimeInterface, category_id?:int|null,
     *     category_name?:string, period?:string|null, distribution_id?:string|null, dues_definition_id?:int|null,
     *     responsible?:string|null}  $fields  amount kuruş; responsible: owner | tenant | null (daire geneli)
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
                    'responsible' => $fields['responsible'] ?? null,
                    'parent_debt_id' => $fields['parent_debt_id'] ?? null,
                    'expense_id' => $fields['expense_id'] ?? null,
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
        $this->assertPeriodOpen($apartment, $fields['due_date']);
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
     * sabittir: aynı dönemi tekrar çalıştırmak mükerrer borç oluşturmaz. Silinerek vazgeçilen borçlar
     * yeniden yazılmaz; $restoreWaived verilirse bu dönemin silinen borçları da üretilir.
     *
     * @return array{created:int, skipped:int, waived:int, failed:array, skipped_no_area:array<int,string>}
     */
    public function generateDues(Apartment $apartment, string $period, ?User $by = null, bool $restoreWaived = false): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new FinanceException('Dönem YYYY-AA biçiminde olmalıdır.');
        }
        $this->assertPeriodOpen($apartment, CarbonImmutable::parse("{$period}-01")->endOfMonth());
        $definitions = $apartment->duesDefinitions()->where('is_active', true)->with('category')->get();
        if ($definitions->isEmpty()) {
            throw new FinanceException('Aktif aidat / borç tanımı bulunamadı.');
        }

        $units = $apartment->units()->with('block')->get();
        $report = ['created' => 0, 'skipped' => 0, 'waived' => 0, 'failed' => [], 'skipped_no_area' => []];

        foreach ($definitions as $def) {
            if ($restoreWaived) {
                $def->update(['waived_unit_periods' => array_values(array_filter($def->waived_unit_periods ?? [],
                    fn (string $waived) => ! str_starts_with($waived, "{$period}:")))]);
            }
            $waivedUnitPeriods = $def->waived_unit_periods ?? [];
            $targets = $this->targetsOf($def, $units);
            // Arsa payında tutar, tanımın toplamıdır: arsa payı girili hedef dairelere oranla bölünür (kuruş kaybı olmadan)
            $landShares = $def->calculation === 'land_share'
                ? Money::allocate(Money::cents($def->amount), $targets->filter(fn (Unit $u) => (float) $u->land_share > 0)
                    ->mapWithKeys(fn (Unit $u) => [$u->id => (float) $u->land_share])->all())
                : [];

            foreach ($targets as $unit) {
                if (in_array("{$period}:{$unit->id}", $waivedUnitPeriods, true)) {
                    $report['waived']++;

                    continue;
                }

                $amount = Money::cents($def->amount);
                if ($def->calculation === 'sqm') {
                    if (! (float) $unit->gross_area) {
                        $report['skipped_no_area'][] = $unit->label();

                        continue;
                    }
                    $amount = (int) round($amount * (float) $unit->gross_area);
                } elseif ($def->calculation === 'land_share') {
                    if (! isset($landShares[$unit->id])) {
                        $report['skipped_no_area'][] = $unit->label();

                        continue;
                    }
                    $amount = $landShares[$unit->id];
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
                        'responsible' => $def->responsible,
                    ], "dues:{$apartment->id}:{$period}:{$def->id}:{$unit->id}");
                    $result === 'created' ? $report['created']++ : $report['skipped']++;
                } catch (FinanceException $e) {
                    $report['failed'][] = ['unit' => $unit->label(), 'message' => $e->getMessage()];
                }
            }
            $def->update(['last_run_period' => $period, 'last_run_at' => now()]);
        }

        // Her gece çalışan otomatik işlem, değişiklik olmadıkça denetim kaydı yazmaz
        if ($by || $report['created'] || $report['failed']) {
            $this->audit($apartment, $by, 'GENERATE_DUES', 'DEBT', $period, $report);
        }

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
     * Silinen tahakkuk için ters yevmiye kaydı atılır. Borca bağlı gecikme zamları ve taksitler de silinir (taksiti tahsil edilmişse hiçbiri silinmez);
     * yalnızca gecikme zammı silinirse o ay ana borçta işaretlenir ve bir daha yazılmaz.
     * Aidat borcu silinirse dönem + daire tanımda işaretlenir, aidat motoru onu yeniden üretmez.
     */
    public function deleteDebt(Debt $debt, ?User $by = null): void
    {
        $this->assertDebtPeriodOpen($debt);
        DB::transaction(function () use ($debt, $by) {
            // Kilit sırası her zaman daire, ana borç, gecikme zammı (kilitlenmeyi önler)
            Unit::whereKey($debt->unit_id)->lockForUpdate()->first();
            $parent = $debt->kind === 'late_fee' && $debt->parent_debt_id
                ? Debt::whereKey($debt->parent_debt_id)->lockForUpdate()->first()
                : null;
            $debt = Debt::whereKey($debt->id)->lockForUpdate()->firstOrFail();
            $payments = DebtPayment::where('debt_id', $debt->id)->get();
            $cashPaid = $payments->where('type', 'collection')->sum(fn ($p) => Money::cents($p->amount));
            if ($cashPaid > 0 || (Money::cents($debt->paid_amount) > 0 && $payments->isEmpty())) {
                throw new FinanceException("\"{$debt->description}\" borcuna ".Money::format($debt->paid_amount)
                    .' tahsilat yapılmış; silmeden önce tahsilatı iptal edin.');
            }

            $lateFees = Debt::where('parent_debt_id', $debt->id)->where('kind', 'late_fee')->orderBy('id')->get();
            foreach ($lateFees as $lateFee) {
                $this->deleteDebt($lateFee, $by);
            }

            foreach (Debt::where('parent_debt_id', $debt->id)->where('kind', 'installment')->orderBy('id')->get() as $installment) {
                $this->deleteDebt($installment, $by);
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

            if ($parent && $debt->idempotency_key) {
                $parent->update(['waived_late_fee_months' => [
                    ...($parent->waived_late_fee_months ?? []),
                    (int) Str::afterLast($debt->idempotency_key, ':'),
                ]]);
            }

            if ($debt->dues_definition_id && str_starts_with((string) $debt->idempotency_key, 'dues:')) {
                $definition = DuesDefinition::whereKey($debt->dues_definition_id)->lockForUpdate()->first();
                $definition?->update(['waived_unit_periods' => [
                    ...($definition->waived_unit_periods ?? []),
                    "{$debt->period}:{$debt->unit_id}",
                ]]);
            }
        });
    }

    /**
     * Açık borcu düzenler. Tutar veya kategori değişirse eski tahakkuk ters kayıtla kapatılır, yenisi yazılır.
     * Ödeme alınmış borçta yalnızca açıklama ve vade değişebilir.
     *
     * @param  array{description: string, due_date: string, amount?: int, category_id?: int|null, category_name?: string}  $fields  amount kuruş
     */
    public function updateDebt(Debt $debt, array $fields, ?User $by = null): void
    {
        DB::transaction(function () use ($debt, $fields, $by) {
            Unit::whereKey($debt->unit_id)->lockForUpdate()->first();
            $debt = Debt::whereKey($debt->id)->lockForUpdate()->firstOrFail();
            if ($debt->status === Debt::PAID) {
                throw new FinanceException('Kapanmış borç düzenlenemez.');
            }
            if ($debt->kind === 'late_fee') {
                throw new FinanceException('Gecikme zammı düzenlenemez; gerekirse silin.');
            }
            $this->assertDebtPeriodOpen($debt);
            $this->assertPeriodOpen($debt->apartment, $fields['due_date']);

            $oldAmount = Money::cents($debt->amount);
            $newAmount = $fields['amount'] ?? $oldAmount;
            $newCategoryId = array_key_exists('category_id', $fields) ? $fields['category_id'] : $debt->category_id;
            $isFinancialChange = $newAmount !== $oldAmount || $newCategoryId !== $debt->category_id;

            if ($isFinancialChange) {
                if (Money::cents($debt->paid_amount) > 0) {
                    throw new FinanceException('Ödeme alınmış borcun tutarı ve kategorisi değiştirilemez; önce ödemeyi iptal edin.');
                }
                if (Debt::where('parent_debt_id', $debt->id)->where('kind', 'late_fee')->exists()) {
                    throw new FinanceException('Bu borca gecikme zammı yazılmış; tutarı değiştirmeden önce zammı silin.');
                }
                if ($newAmount <= 0) {
                    throw new FinanceException('Borç tutarı 0\'dan büyük olmalıdır.');
                }
                $label = $debt->unit?->label();
                $this->ledgerPair($debt->apartment_id, "debt:{$debt->id}", 'accrual_reversal',
                    [LedgerEntry::INCOME, $debt->category_id], [LedgerEntry::UNIT, $debt->unit_id],
                    $oldAmount, trim("Tahakkuk Düzeltme İptali: {$label} {$debt->description}"));
                $this->ledgerPair($debt->apartment_id, "debt:{$debt->id}", 'accrual',
                    [LedgerEntry::UNIT, $debt->unit_id], [LedgerEntry::INCOME, $newCategoryId],
                    $newAmount, trim("Tahakkuk: {$label} {$fields['description']}"));
            }

            $debt->fill([
                'description' => $fields['description'], 'due_date' => $fields['due_date'],
                'amount' => Money::decimal($newAmount), 'remaining_amount' => Money::decimal($newAmount - Money::cents($debt->paid_amount)),
                'category_id' => $newCategoryId, 'category_name' => $fields['category_name'] ?? $debt->category_name,
                'responsible' => array_key_exists('responsible', $fields) ? $fields['responsible'] : $debt->responsible,
            ]);
            if (! $debt->isDirty()) {
                return;
            }
            $changes = collect($debt->getDirty())->map(fn ($value, $field) => ['old' => $debt->getRawOriginal($field), 'new' => $value])->all();
            $debt->save();
            $this->audit($debt->apartment, $by, 'UPDATE_DEBT', 'DEBT', "debt:{$debt->id}", ['changes' => $changes]);
        });
    }

    // ----------------------------------------------------------------- TAHSİLAT

    /**
     * Daireden tek parça tahsilat.
     *
     * @param  int  $amount  kasanın para birimi cinsinden kuruş
     * @param  string|null  $exchangeRate  döviz kasası için 1 birimin TRY karşılığı
     */
    public function collect(Unit $unit, Cashbox $cashbox, int $amount, ?string $description = null,
        ?User $by = null, ?Debt $preferred = null, string $subType = 'collection', ?string $exchangeRate = null): CashTransaction
    {
        return $this->collectParts($unit, [[
            'cashbox' => $cashbox, 'amount' => $amount, 'method' => $subType, 'exchange_rate' => $exchangeRate,
        ]], $description, $by, $preferred)->first();
    }

    /**
     * Daireden parçalı tahsilat: her parça ayrı kasaya, kendi para birimi ve kuruyla girer. Parçaların TRY
     * karşılığı önce elle tutar girilen borçlara ($allocations), sonra (varsa) seçilen borca, sonra dairenin diğer
     * açık borçlarına en eski vadeden başlayarak dağıtılır; artan kısım TRY olarak daireye avans yazılır.
     * Ödeyen sakin seçilirse otomatik dağıtım yalnızca onun sorumlu olduğu ve daire geneli borçlara yapılır.
     * Bir parça hatalıysa hiçbiri kaydedilmez.
     *
     * @param  list<array{cashbox: Cashbox, amount: int, method?: string, exchange_rate?: string|null}>  $parts  amount kasanın para birimi cinsinden kuruş
     * @param  array<int, int>  $allocations  borç id => bu ödemeden kapatılacak TL kuruş
     * @return Collection<int, CashTransaction> parça başına bir kasa hareketi
     */
    public function collectParts(Unit $unit, array $parts, ?string $description = null, ?User $by = null, ?Debt $preferred = null,
        ?Member $payer = null, array $allocations = []): Collection
    {
        if ($payer && $payer->unit_id !== $unit->id) {
            throw new FinanceException('Ödeyen kişi bu dairenin sakini değil.');
        }
        if ($parts === []) {
            throw new FinanceException('En az bir ödeme parçası girilmelidir.');
        }
        foreach ($parts as $part) {
            if ($part['amount'] <= 0) {
                throw new FinanceException('Tahsilat tutarı 0\'dan büyük olmalıdır.');
            }
            $this->assertSameApartment($unit->apartment_id, $part['cashbox'], $preferred);
        }

        return DB::transaction(function () use ($unit, $parts, $description, $by, $preferred, $payer, $allocations) {
            $unit = Unit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $cashboxes = Cashbox::whereIn('id', array_map(fn (array $part) => $part['cashbox']->id, $parts))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $cashboxes->each(fn (Cashbox $box) => $this->assertCashboxActive($box));

            $open = Debt::where('unit_id', $unit->id)->where('status', Debt::PENDING)
                ->orderBy('due_date')->orderBy('id')->lockForUpdate()->get();
            if ($preferred) {
                $open = $open->sortBy(fn (Debt $d) => $d->id === $preferred->id ? 0 : 1, SORT_REGULAR, false)->values();
            }

            $caps = [];
            foreach ($allocations as $debtId => $cents) {
                if ($cents <= 0) {
                    continue;
                }
                $debt = $open->firstWhere('id', (int) $debtId);
                if (! $debt) {
                    throw new FinanceException('Tutar girilen borç bu dairenin açık borçları arasında değil.');
                }
                if ($cents > Money::cents($debt->remaining_amount)) {
                    throw new FinanceException("\"{$debt->description}\" için girilen tutar kalan borcu (".Money::format($debt->remaining_amount).') aşıyor.');
                }
                $caps[$debt->id] = $cents;
            }
            $auto = $payer
                ? $open->filter(fn (Debt $debt) => $debt->responsible === null || $debt->responsible === $payer->type)->values()
                : $open;

            $group = count($parts) > 1 ? (string) Str::uuid() : null;
            $transactions = collect();
            foreach ($parts as $part) {
                $transactions->push($this->collectPart($unit, $cashboxes[$part['cashbox']->id], $part, $open, $auto, $caps,
                    $group, $description, $by, $preferred, $payer));
            }
            if (array_sum($caps) > 0) {
                throw new FinanceException('Borçlara elle yazılan tutarların toplamı ödeme tutarından fazla.');
            }

            return $transactions;
        });
    }

    /**
     * @param  array{cashbox: Cashbox, amount: int, method?: string, exchange_rate?: string|null}  $part
     * @param  Collection<int, Debt>  $open  kilitli açık borçlar; sonraki parçalar güncel kalanı görür
     * @param  Collection<int, Debt>  $auto  otomatik dağıtıma girebilen açık borçlar
     * @param  array<int, int>  $caps  elle girilen borç tutarlarından henüz kapatılmayan kuruşlar (parçalar arasında azalır)
     */
    private function collectPart(Unit $unit, Cashbox $cashbox, array $part, Collection $open, Collection $auto, array &$caps,
        ?string $group, ?string $description, ?User $by, ?Debt $preferred, ?Member $payer): CashTransaction
    {
        $money = $this->inflow($cashbox, $part['amount'], $part['exchange_rate'] ?? null);
        $amount = $money['try'];
        $label = $unit->label();

        $tx = CashTransaction::create($this->moneyFields($cashbox, $money) + [
            'apartment_id' => $unit->apartment_id,
            'cashbox_id' => $cashbox->id,
            'type' => 'income',
            'sub_type' => $part['method'] ?? 'collection',
            'payment_group' => $group,
            'unit_id' => $unit->id,
            'payer_member_id' => $payer?->id,
            'debt_id' => $preferred?->id,
            'description' => $description ?: "{$label} Tahsilat",
            'performed_by' => $by?->id,
        ]);

        $left = $amount;
        foreach ($caps as $debtId => $cap) {
            if ($left <= 0) {
                break;
            }
            $paid = $this->payDebt($open->firstWhere('id', $debtId), $tx, min($left, $cap));
            $caps[$debtId] -= $paid;
            $left -= $paid;
        }
        foreach ($auto as $debt) {
            if ($left <= 0) {
                break;
            }
            $left -= $this->payDebt($debt, $tx, $left);
        }

        $applied = $amount - $left;
        $tx->update(['applied_to_debts' => Money::decimal($applied), 'to_advance' => Money::decimal($left)]);
        if ($left > 0) {
            $unit->update(['credit_balance' => Money::decimal(Money::cents($unit->credit_balance) + $left)]);
        }
        $this->moveCashbox($cashbox, $amount, $money['original']);

        // Yevmiye: Kasa (B) / Daire alacağı (A) + fazlası Avans (A)
        $ref = "cash_tx:{$tx->id}";
        $desc = "Tahsilat: {$label}".$this->foreignNote($cashbox, $money);
        $this->ledger($unit->apartment_id, $ref, 'collection', LedgerEntry::CASH, $cashbox->id, $amount, 0, $desc);
        if ($applied > 0) {
            $this->ledger($unit->apartment_id, $ref, 'collection', LedgerEntry::UNIT, $unit->id, 0, $applied, $desc);
        }
        if ($left > 0) {
            $this->ledger($unit->apartment_id, $ref, 'collection', LedgerEntry::UNIT_ADVANCE, $unit->id, 0, $left, "{$desc} (Avans)");
        }

        $this->audit($unit->apartment, $by, 'COLLECTION', 'FINANCE', $ref, [
            'unit' => $label, 'cashbox' => $cashbox->name, 'amount' => Money::decimal($amount),
            'currency' => $cashbox->currency, 'original_amount' => Money::decimal($money['original']), 'exchange_rate' => $money['rate'],
            'payment_group' => $group, 'applied' => Money::decimal($applied), 'advance' => Money::decimal($left),
            'payer' => $payer?->full_name,
        ]);

        return $tx;
    }

    /** Tahsilattan borca en fazla $max kuruş yazar. @return int yazılan kuruş */
    private function payDebt(Debt $debt, CashTransaction $tx, int $max): int
    {
        $remaining = Money::cents($debt->remaining_amount);
        $pay = min($max, $remaining);
        if ($pay <= 0) {
            return 0;
        }
        $debt->update([
            'remaining_amount' => Money::decimal($remaining - $pay),
            'paid_amount' => Money::decimal(Money::cents($debt->paid_amount) + $pay),
            'status' => $remaining - $pay === 0 ? Debt::PAID : Debt::PENDING,
        ]);
        DebtPayment::create([
            'apartment_id' => $tx->apartment_id, 'debt_id' => $debt->id, 'unit_id' => $tx->unit_id,
            'cash_transaction_id' => $tx->id, 'type' => 'collection', 'amount' => Money::decimal($pay),
        ]);

        return $pay;
    }

    // ----------------------------------------------------------------- KASA

    /**
     * Borçla ilişkisiz gelir / gider. Gider kasadan çıkar (bakiye kontrolü yapılır).
     * Döviz kasasında gelir verilen kurla, gider kasanın ortalama maliyetiyle TRY'ye çevrilir.
     *
     * @param  'income'|'expense'  $type
     * @param  int  $amount  kasanın para birimi cinsinden kuruş
     */
    public function generalTransaction(Cashbox $cashbox, string $type, int $amount, string $description,
        ?int $categoryId = null, ?User $by = null, ?string $exchangeRate = null): CashTransaction
    {
        if ($amount <= 0) {
            throw new FinanceException('İşlem tutarı 0\'dan büyük olmalıdır.');
        }

        return DB::transaction(function () use ($cashbox, $type, $amount, $description, $categoryId, $by, $exchangeRate) {
            $cashbox = Cashbox::whereKey($cashbox->id)->lockForUpdate()->firstOrFail();
            $this->assertCashboxActive($cashbox);
            $isIncome = $type === 'income';
            $money = $isIncome ? $this->inflow($cashbox, $amount, $exchangeRate) : $this->outflow($cashbox, $amount);
            $this->moveCashbox($cashbox, $isIncome ? $money['try'] : -$money['try'], $isIncome ? $money['original'] : -$money['original']);

            $tx = CashTransaction::create($this->moneyFields($cashbox, $money) + [
                'apartment_id' => $cashbox->apartment_id, 'cashbox_id' => $cashbox->id, 'type' => $type,
                'sub_type' => 'general', 'category_id' => $categoryId,
                'description' => $description, 'performed_by' => $by?->id,
            ]);
            $amount = $money['try'];

            $ref = "cash_tx:{$tx->id}";
            $other = $isIncome ? LedgerEntry::INCOME : LedgerEntry::EXPENSE;
            $isIncome
                ? $this->ledgerPair($cashbox->apartment_id, $ref, 'general', [LedgerEntry::CASH, $cashbox->id], [$other, $categoryId], $amount, $description)
                : $this->ledgerPair($cashbox->apartment_id, $ref, 'general', [$other, $categoryId], [LedgerEntry::CASH, $cashbox->id], $amount, $description);

            $this->audit($cashbox->apartment, $by, $isIncome ? 'GENERAL_INCOME' : 'GENERAL_EXPENSE', 'FINANCE', $ref,
                ['cashbox' => $cashbox->name, 'amount' => Money::decimal($amount), 'currency' => $cashbox->currency, 'original_amount' => Money::decimal($money['original']), 'description' => $description]);

            return $tx;
        });
    }

    /** Yeni kasanın açılış bakiyesi: kasaya girer, karşılığı gelir değil açılış hesabıdır. @param int $amount kasanın para birimi cinsinden kuruş */
    public function recordOpeningBalance(Cashbox $cashbox, int $amount, ?User $by = null, ?string $exchangeRate = null): CashTransaction
    {
        if ($amount <= 0) {
            throw new FinanceException('Açılış bakiyesi 0\'dan büyük olmalıdır.');
        }

        return DB::transaction(function () use ($cashbox, $amount, $by, $exchangeRate) {
            $cashbox = Cashbox::whereKey($cashbox->id)->lockForUpdate()->firstOrFail();
            $this->assertCashboxActive($cashbox);
            $money = $this->inflow($cashbox, $amount, $exchangeRate);
            $amount = $money['try'];
            $this->moveCashbox($cashbox, $amount, $money['original']);

            $tx = CashTransaction::create($this->moneyFields($cashbox, $money) + [
                'apartment_id' => $cashbox->apartment_id, 'cashbox_id' => $cashbox->id, 'type' => 'income',
                'sub_type' => 'opening',
                'description' => 'Açılış bakiyesi', 'performed_by' => $by?->id,
            ]);

            $ref = "cash_tx:{$tx->id}";
            $this->ledgerPair($cashbox->apartment_id, $ref, 'opening',
                [LedgerEntry::CASH, $cashbox->id], [LedgerEntry::OPENING, null], $amount, 'Açılış bakiyesi');
            $this->audit($cashbox->apartment, $by, 'OPENING_BALANCE', 'FINANCE', $ref,
                ['cashbox' => $cashbox->name, 'amount' => Money::decimal($amount)]);

            return $tx;
        });
    }

    /**
     * Kasalar arası virman. Para birimleri farklıysa (döviz bozdurma / alma) hedef kasaya giren tutar verilir.
     * Döviz TRY kasaya bozdurulunca alınan TRY ile kasanın ortalama maliyeti arasındaki fark kur farkı olarak yazılır.
     *
     * @param  int  $amount  kaynak kasanın para birimi cinsinden kuruş
     * @param  int|null  $targetAmount  hedef kasanın para birimi cinsinden kuruş
     */
    public function transfer(Cashbox $from, Cashbox $to, int $amount, ?string $description = null, ?User $by = null, ?int $targetAmount = null): void
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

        DB::transaction(function () use ($from, $to, $amount, $description, $by, $targetAmount) {
            // Kilitler her zaman id sırasıyla alınır (karşılıklı virmanlarda kilitlenme olmaz)
            $boxes = Cashbox::whereIn('id', [$from->id, $to->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $boxes->each(fn (Cashbox $box) => $this->assertCashboxActive($box));
            $from = $boxes[$from->id];
            $to = $boxes[$to->id];

            $received = $from->currency === $to->currency ? $amount : (int) $targetAmount;
            if ($received <= 0) {
                throw new FinanceException('Para birimi farklı kasalar arasında hedef kasaya girecek tutar zorunludur.');
            }

            $outgoing = $this->outflow($from, $amount);
            // Döviz TRY'ye dönüyorsa değer alınan TRY'dir; diğer durumlarda maliyet aynen taşınır
            $bookValue = $from->isForeign() && ! $to->isForeign() ? $received : $outgoing['try'];
            $desc = trim($description ?? '') ?: "{$from->name} → {$to->name}";
            $this->recordFxDifference($from, $bookValue - $outgoing['try'], "Kur farkı: {$desc}", $by);

            $this->moveCashbox($from, -$bookValue, -$amount);
            $this->moveCashbox($to, $bookValue, $received);

            $out = CashTransaction::create($this->moneyFields($from, $this->money($bookValue, $amount)) + [
                'apartment_id' => $from->apartment_id, 'cashbox_id' => $from->id, 'type' => 'expense',
                'sub_type' => 'transfer_out',
                'description' => "Virman (Giden): {$desc}", 'performed_by' => $by?->id,
            ]);
            $in = CashTransaction::create($this->moneyFields($to, $this->money($bookValue, $received)) + [
                'apartment_id' => $to->apartment_id, 'cashbox_id' => $to->id, 'type' => 'income',
                'sub_type' => 'transfer_in',
                'description' => "Virman (Gelen): {$desc}", 'performed_by' => $by?->id,
                'related_transaction_id' => $out->id,
            ]);
            $out->update(['related_transaction_id' => $in->id]);

            $this->ledgerPair($from->apartment_id, "cash_tx:{$out->id}", 'transfer',
                [LedgerEntry::CASH, $to->id], [LedgerEntry::CASH, $from->id], $bookValue, "Virman: {$desc}");
            $this->audit($from->apartment, $by, 'TRANSFER', 'FINANCE', "cash_tx:{$out->id}", [
                'from' => $from->name, 'to' => $to->name, 'amount' => Money::decimal($bookValue),
                'sent' => Money::decimal($amount), 'sent_currency' => $from->currency,
                'received' => Money::decimal($received), 'received_currency' => $to->currency,
            ]);
        });
    }

    // ----------------------------------------------------------------- GİDER

    /**
     * Gideri (ör. bir daireye gönderilen teknik servisin faturasını) daireye borç olarak yansıtır. Borç gidere bağlı
     * kalır; aynı gider ikinci kez yansıtılamaz. Gelir "Masraf Yansıtma" kategorisine yazılır.
     *
     * @param  int  $amount  daireden alınacak TL kuruş
     */
    public function rechargeExpense(Expense $expense, Unit $unit, int $amount, string $dueDate, ?string $responsible = null, ?User $by = null): Debt
    {
        if ($unit->apartment_id !== $expense->apartment_id) {
            throw new FinanceException('Daire bu siteye ait değil.');
        }
        if (Debt::where('expense_id', $expense->id)->exists()) {
            throw new FinanceException('Bu gider zaten bir daireye yansıtılmış.');
        }
        $this->assertPeriodOpen($expense->apartment, $dueDate);
        $category = $expense->apartment->categories()->firstOrCreate(['name' => 'Masraf Yansıtma', 'type' => 'income']);
        $invoice = $expense->invoice_no ? " (Fatura: {$expense->invoice_no})" : '';

        $this->accrue($unit, [
            'description' => mb_substr("{$expense->description}{$invoice}", 0, 200),
            'amount' => $amount, 'due_date' => $dueDate, 'period' => substr($dueDate, 0, 7),
            'category_id' => $category->id, 'category_name' => $category->name,
            'responsible' => $responsible, 'expense_id' => $expense->id,
        ], "recharge:{$expense->id}");
        $debt = Debt::where('expense_id', $expense->id)->firstOrFail();
        $this->audit($expense->apartment, $by, 'RECHARGE_EXPENSE', 'EXPENSE', "expense:{$expense->id}",
            ['unit' => $unit->label(), 'amount' => Money::decimal($amount), 'debt' => $debt->id]);

        return $debt;
    }

    /** Bekleyen giderin kalan tutarının tamamını öder: KDV dahil kalan tutar seçilen kasadan düşer. */
    public function approveExpense(Expense $expense, Cashbox $cashbox, ?User $by = null, ?string $exchangeRate = null): void
    {
        $this->payExpense($expense, $cashbox, null, $by, $exchangeRate);
    }

    /**
     * Gidere (tedarikçi faturasına) ödeme yapar. Kısmi ödemede gider bekleyen kalır; kalan tutar sıfırlanınca
     * gider onaylanmış (ödendi) olur. Her ödeme ayrı kasa hareketi ve yevmiye kaydıdır, ayrı iptal edilebilir.
     *
     * @param  int|null  $amount  TL kuruş; null ise kalan tutarın tamamı
     */
    public function payExpense(Expense $expense, Cashbox $cashbox, ?int $amount, ?User $by = null, ?string $exchangeRate = null): CashTransaction
    {
        $this->assertSameApartment($expense->apartment_id, $cashbox);

        return DB::transaction(function () use ($expense, $cashbox, $amount, $by, $exchangeRate) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($expense->status === 'approved') {
                throw new FinanceException('Bu gider zaten onaylanmış.');
            }
            $cashbox = Cashbox::whereKey($cashbox->id)->lockForUpdate()->firstOrFail();
            $this->assertCashboxActive($cashbox);
            $total = Money::cents($expense->total_amount);
            $paidBefore = Money::cents($expense->paid_amount);
            $remaining = $total - $paidBefore;
            if ($total <= 0 || $remaining <= 0) {
                throw new FinanceException('Gider tutarı geçersiz.');
            }
            $amount ??= $remaining;
            if ($amount <= 0) {
                throw new FinanceException('Ödeme tutarı 0\'dan büyük olmalıdır.');
            }
            if ($amount > $remaining) {
                throw new FinanceException('Ödeme tutarı giderin kalan tutarını ('.Money::format($remaining / 100).') aşamaz.');
            }

            $money = $this->expensePayment($cashbox, $amount, $exchangeRate, $expense->description, $by);
            $this->moveCashbox($cashbox, -$money['try'], -$money['original']);
            $isFullyPaid = $amount === $remaining;
            $expense->update([
                'paid_amount' => Money::decimal($paidBefore + $amount),
                'status' => $isFullyPaid ? 'approved' : 'pending', 'cashbox_id' => $cashbox->id,
                'approved_by' => $isFullyPaid ? $by?->id : null, 'approved_at' => $isFullyPaid ? now() : null,
            ]);
            $invoice = $expense->invoice_no ? " (Fatura: {$expense->invoice_no})" : '';
            $partial = $amount === $total ? '' : ($isFullyPaid ? ' — son ödeme' : ' — kısmi ödeme');
            $tx = CashTransaction::create($this->moneyFields($cashbox, $money) + [
                'apartment_id' => $expense->apartment_id, 'cashbox_id' => $cashbox->id, 'type' => 'expense',
                'sub_type' => 'invoice', 'expense_id' => $expense->id,
                'category_id' => $expense->category_id,
                'description' => "Gider: {$expense->description}{$invoice}{$partial}", 'performed_by' => $by?->id,
            ]);
            $this->ledgerPair($expense->apartment_id, "cash_tx:{$tx->id}", 'expense',
                [LedgerEntry::EXPENSE, $expense->category_id], [LedgerEntry::CASH, $cashbox->id],
                $amount, $expense->description.$partial);
            $this->audit($expense->apartment, $by, $isFullyPaid ? 'APPROVE_EXPENSE' : 'PAY_EXPENSE', 'EXPENSE', "expense:{$expense->id}",
                ['cashbox' => $cashbox->name, 'amount' => Money::decimal($amount), 'currency' => $cashbox->currency, 'original_amount' => Money::decimal($money['original']), 'exchange_rate' => $money['rate'],
                    'paid_total' => Money::decimal($paidBefore + $amount), 'remaining' => Money::decimal($remaining - $amount)]);

            return $tx;
        });
    }

    /**
     * TL tutarlı giderin kasadan çıkışı. Döviz kasasında çıkacak döviz güncel kurla bulunur;
     * ortalama maliyetle güncel değer arasındaki fark kur farkı olarak yazılır.
     *
     * @return array{try: int, original: int, rate: string}
     */
    private function expensePayment(Cashbox $cashbox, int $amount, ?string $exchangeRate, string $description, ?User $by): array
    {
        if (! $cashbox->isForeign()) {
            $this->assertCanPay($cashbox, $amount);

            return $this->money($amount, $amount);
        }
        if ($exchangeRate === null || (float) $exchangeRate <= 0) {
            throw new FinanceException("\"{$cashbox->name}\" kasası {$cashbox->currency} cinsindendir; kur girilmelidir.");
        }

        $native = (int) round($amount / (float) $exchangeRate);
        if ($native <= 0) {
            throw new FinanceException('Tutar ve kurun döviz karşılığı geçersiz.');
        }
        $outgoing = $this->outflow($cashbox, $native);
        $this->recordFxDifference($cashbox, $amount - $outgoing['try'], "Kur farkı: {$description}", $by);

        return $this->money($amount, $native);
    }

    // ----------------------------------------------------------------- ELLE YEVMİYE

    /**
     * Elle yevmiye fişi. Kasa ve daire hesapları kullanılamaz: bunların bakiyesi kendi ekranlarındaki
     * işlemlerle oluşur, fiş bakiyeyle yevmiyeyi birbirinden ayırırdı.
     *
     * @param  array<int, array{account: string, debit: int, credit: int}>  $lines  tutarlar kuruş; hesap: income:ID, expense:ID, fx, opening
     */
    public function manualJournal(Apartment $apartment, string $date, string $description, array $lines, ?User $by = null): string
    {
        $this->assertPeriodOpen($apartment, $date);
        if (count($lines) < 2) {
            throw new FinanceException('Fişte en az iki satır olmalıdır.');
        }

        $resolved = array_map(function (array $line) use ($apartment) {
            if (($line['debit'] > 0) === ($line['credit'] > 0) || $line['debit'] < 0 || $line['credit'] < 0) {
                throw new FinanceException('Her satıra yalnızca borç veya yalnızca alacak tutarı yazın.');
            }

            return $this->manualAccount($apartment, $line['account']) + $line;
        }, $lines);

        $debit = array_sum(array_column($resolved, 'debit'));
        if ($debit !== array_sum(array_column($resolved, 'credit'))) {
            throw new FinanceException('Fiş dengede değil: toplam borç ile toplam alacak eşit olmalıdır.');
        }

        $reference = 'manual:'.Str::uuid();
        $createdAt = CarbonImmutable::parse($date)->setTimeFrom(now());
        DB::transaction(function () use ($apartment, $resolved, $reference, $createdAt, $description, $debit, $by) {
            foreach ($resolved as $line) {
                LedgerEntry::create([
                    'apartment_id' => $apartment->id, 'reference' => $reference, 'entry_type' => 'manual',
                    'account_name' => $line['name'], 'account_id' => $line['id'],
                    'debit' => Money::decimal($line['debit']), 'credit' => Money::decimal($line['credit']),
                    'description' => mb_substr($description, 0, 250), 'created_at' => $createdAt,
                ]);
            }
            $this->audit($apartment, $by, 'MANUAL_JOURNAL', 'FINANCE', $reference, [
                'date' => $createdAt->toDateString(), 'description' => $description, 'total' => Money::decimal($debit),
            ]);
        });

        return $reference;
    }

    public function reverseManualJournal(Apartment $apartment, string $reference, string $reason, ?User $by = null): void
    {
        DB::transaction(function () use ($apartment, $reference, $reason, $by) {
            $entries = $apartment->ledgerEntries()->where('reference', $reference)->lockForUpdate()->get();
            $manual = $entries->where('entry_type', 'manual');
            if ($manual->isEmpty()) {
                throw new FinanceException('Yalnızca elle kesilmiş fiş iptal edilebilir.');
            }
            if ($entries->contains('entry_type', 'manual_reversal')) {
                throw new FinanceException('Bu fiş zaten iptal edilmiş.');
            }
            $this->assertPeriodOpen($apartment, $manual->first()->created_at);

            foreach ($manual as $entry) {
                LedgerEntry::create([
                    'apartment_id' => $apartment->id, 'reference' => $reference, 'entry_type' => 'manual_reversal',
                    'account_name' => $entry->account_name, 'account_id' => $entry->account_id,
                    'debit' => $entry->credit, 'credit' => $entry->debit,
                    'description' => mb_substr("İptal: {$entry->description} — {$reason}", 0, 250),
                ]);
            }
            $this->audit($apartment, $by, 'REVERSE_MANUAL_JOURNAL', 'FINANCE', $reference, ['reason' => $reason]);
        });
    }

    /** @return array{name: string, id: string|null} */
    private function manualAccount(Apartment $apartment, string $account): array
    {
        [$kind, $categoryId] = array_pad(explode(':', $account, 2), 2, null);

        return match ($kind) {
            'fx' => ['name' => LedgerEntry::FX_DIFFERENCE, 'id' => null],
            'opening' => ['name' => LedgerEntry::OPENING, 'id' => null],
            'income', 'expense' => [
                'name' => $kind === 'income' ? LedgerEntry::INCOME : LedgerEntry::EXPENSE,
                'id' => (string) ($apartment->categories()->where('type', $kind)->find((int) $categoryId)?->id
                    ?? throw new FinanceException('Seçilen kategori bu siteye ait değil.')),
            ],
            default => throw new FinanceException('Kasa ve daire hesaplarına elle fiş kesilemez; ilgili ekrandan işlem yapın.'),
        };
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
            $waivedMonths = $debt->waived_late_fee_months ?? [];
            for ($n = 1; $n <= $months && $amount > 0; $n++) {
                if (! $existing->has($key = "late:{$debt->id}:{$n}") && ! in_array($n, $waivedMonths, true)) {
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
                'responsible' => $debt->responsible,
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
     * gider tekrar onay bekler. Hareket banka satırından geldiyse satır yeniden işlenebilir olur.
     * Virmanlar iptal edilemez (ters virman yapılır).
     */
    public function reverse(CashTransaction $tx, string $reason, ?User $by = null): CashTransaction
    {
        $this->assertPeriodOpen($tx->apartment, $tx->created_at);

        return DB::transaction(function () use ($tx, $reason, $by) {
            $tx = CashTransaction::whereKey($tx->id)->lockForUpdate()->firstOrFail();
            if ($tx->isReversed() || $tx->sub_type === 'reversal') {
                throw new FinanceException('Bu hareket zaten iptal edilmiş veya bir iptal kaydıdır.');
            }
            if ($tx->isTransfer()) {
                throw new FinanceException('Virman iptal edilemez; ters yönde yeni bir virman yapın.');
            }
            if ($tx->isFxDifference()) {
                throw new FinanceException('Kur farkı kaydı iptal edilemez.');
            }
            // Tahsilatla aynı kilit sırası: önce daire, sonra kasa
            if ($tx->unit_id) {
                Unit::whereKey($tx->unit_id)->lockForUpdate()->first();
            }
            $cashbox = Cashbox::whereKey($tx->cashbox_id)->lockForUpdate()->firstOrFail();
            $amount = Money::cents($tx->amount);
            $original = Money::cents($tx->original_amount);
            $wasIncome = $tx->type === 'income';
            if ($wasIncome) {
                $this->assertCanPay($cashbox, $original); // iptal, kasadan para çıkışıdır
            }

            match ($tx->sub_type) {
                'collection', 'pos', 'advance' => $this->undoCollection($tx),
                'invoice' => $this->undoExpense($tx),
                'refund' => $this->adjustCredit($tx->unit_id, $amount),
                default => null,
            };

            $this->moveCashbox($cashbox, $wasIncome ? -$amount : $amount, $wasIncome ? -$original : $original);
            $reversal = CashTransaction::create([
                'apartment_id' => $tx->apartment_id, 'cashbox_id' => $tx->cashbox_id,
                'type' => $wasIncome ? 'expense' : 'income', 'sub_type' => 'reversal', 'amount' => Money::decimal($amount),
                'currency' => $tx->currency, 'original_amount' => $tx->original_amount, 'exchange_rate' => $tx->exchange_rate,
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
            $this->settleEmptyForeignCashbox($cashbox, $by);
            BankStatementLine::where('cash_transaction_id', $tx->id)->update(['status' => 'pending', 'cash_transaction_id' => null]);
            $this->audit($tx->apartment, $by, 'REVERSE_TRANSACTION', 'FINANCE', "cash_tx:{$tx->id}",
                ['amount' => Money::decimal($amount), 'currency' => $tx->currency, 'original_amount' => $tx->original_amount, 'reason' => $reason, 'type' => $tx->sub_type]);

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
                if (Debt::where('parent_debt_id', $debt->id)->where('kind', 'installment')->exists()) {
                    throw new FinanceException('Bu tahsilatın ait olduğu borç taksitlendirilmiş; önce taksitleri iptal edin.');
                }
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

    /** İptal edilen ödeme giderin ödenen tutarından düşer; gider yeniden bekleyen olur. */
    private function undoExpense(CashTransaction $tx): void
    {
        $expense = Expense::whereKey($tx->expense_id)->lockForUpdate()->first();
        if (! $expense) {
            return;
        }
        $paid = max(Money::cents($expense->paid_amount) - Money::cents($tx->amount), 0);
        $expense->update([
            'paid_amount' => Money::decimal($paid), 'status' => 'pending', 'approved_by' => null, 'approved_at' => null,
            'cashbox_id' => $paid > 0 ? $expense->cashbox_id : null,
        ]);
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
            $this->assertCashboxActive($cashbox);
            $this->assertBaseCurrency($cashbox, 'Avans iadesi');
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
        $this->assertDebtPeriodOpen($debt);
        $this->assertPeriodOpen($debt->apartment, $firstDue);

        return DB::transaction(function () use ($debt, $count, $firstDue, $everyMonths, $by) {
            Unit::whereKey($debt->unit_id)->lockForUpdate()->first();
            $debt = Debt::whereKey($debt->id)->lockForUpdate()->firstOrFail();
            $remaining = Money::cents($debt->remaining_amount);
            if ($debt->status !== Debt::PENDING || $remaining <= 0) {
                throw new FinanceException('Yalnızca açık borç taksitlendirilebilir.');
            }
            if ($debt->kind === 'late_fee') {
                throw new FinanceException('Gecikme zammı taksitlendirilemez: taksitlere yeniden zam işler.');
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
                    'kind' => 'installment', 'parent_debt_id' => $debt->id, 'responsible' => $debt->responsible,
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

    /**
     * Kasaya giren tutarın TRY karşılığı. Döviz kasası için kur zorunludur; TRY kasada kur yok sayılır.
     *
     * @return array{try: int, original: int, rate: string}
     */
    private function inflow(Cashbox $cashbox, int $amount, ?string $exchangeRate): array
    {
        if (! $cashbox->isForeign()) {
            return ['try' => $amount, 'original' => $amount, 'rate' => '1'];
        }
        if ($exchangeRate === null || (float) $exchangeRate <= 0) {
            throw new FinanceException("\"{$cashbox->name}\" kasası {$cashbox->currency} cinsindendir; kur girilmelidir.");
        }

        $try = Money::convert($amount, $exchangeRate);
        if ($try <= 0 || $try > self::MAX_CENTS) {
            throw new FinanceException('Tutar ve kurun TRY karşılığı geçersiz.');
        }

        return ['try' => $try, 'original' => $amount, 'rate' => number_format((float) $exchangeRate, 6, '.', '')];
    }

    /**
     * Kasadan çıkan tutarın TRY maliyeti: döviz kasasında ağırlıklı ortalama maliyet.
     * Kasanın tümü çıkıyorsa kalan maliyetin tamamı alınır, kuruş artığı kalmaz.
     *
     * @return array{try: int, original: int, rate: string}
     */
    private function outflow(Cashbox $cashbox, int $amount): array
    {
        $this->assertCanPay($cashbox, $amount);
        if (! $cashbox->isForeign()) {
            return ['try' => $amount, 'original' => $amount, 'rate' => '1'];
        }

        $native = Money::cents($cashbox->currency_balance);
        $book = Money::cents($cashbox->balance);

        return $this->money($amount === $native ? $book : (int) round($book * $amount / $native), $amount);
    }

    /** @return array{try: int, original: int, rate: string} */
    private function money(int $try, int $original): array
    {
        return ['try' => $try, 'original' => $original, 'rate' => number_format($original > 0 ? $try / $original : 1, 6, '.', '')];
    }

    /**
     * @param  array{try: int, original: int, rate: string}  $money
     * @return array{amount: string, currency: string, original_amount: string, exchange_rate: string}
     */
    private function moneyFields(Cashbox $cashbox, array $money): array
    {
        return [
            'amount' => Money::decimal($money['try']),
            'currency' => $cashbox->currency,
            'original_amount' => Money::decimal($money['original']),
            'exchange_rate' => $cashbox->isForeign() ? $money['rate'] : '1',
        ];
    }

    /** Kasa bakiyesini günceller. Çağıran, kasayı kilitli okumuş olmalıdır. Tutarlar kuruş ve işaretlidir. */
    private function moveCashbox(Cashbox $cashbox, int $tryCents, int $originalCents): void
    {
        $changes = ['balance' => Money::decimal(Money::cents($cashbox->balance) + $tryCents)];
        if ($cashbox->isForeign()) {
            $changes['currency_balance'] = Money::decimal(Money::cents($cashbox->currency_balance) + $originalCents);
        }
        $cashbox->update($changes);
    }

    /** Döviz kasasının TRY değerini düzeltir: hareket TRY'dedir, döviz mevcudu değişmez. $difference kuruş, + kazanç / − zarar */
    private function recordFxDifference(Cashbox $cashbox, int $difference, string $description, ?User $by): void
    {
        if ($difference === 0) {
            return;
        }

        $amount = abs($difference);
        $isGain = $difference > 0;
        $this->moveCashbox($cashbox, $difference, 0);
        $tx = CashTransaction::create([
            'apartment_id' => $cashbox->apartment_id, 'cashbox_id' => $cashbox->id,
            'type' => $isGain ? 'income' : 'expense', 'sub_type' => 'fx_difference',
            'amount' => Money::decimal($amount), 'currency' => $cashbox->currency, 'original_amount' => 0,
            'description' => $description, 'performed_by' => $by?->id,
        ]);

        $ref = "cash_tx:{$tx->id}";
        $isGain
            ? $this->ledgerPair($cashbox->apartment_id, $ref, 'fx_difference', [LedgerEntry::CASH, $cashbox->id], [LedgerEntry::FX_DIFFERENCE, null], $amount, $description)
            : $this->ledgerPair($cashbox->apartment_id, $ref, 'fx_difference', [LedgerEntry::FX_DIFFERENCE, null], [LedgerEntry::CASH, $cashbox->id], $amount, $description);
        $this->audit($cashbox->apartment, $by, 'FX_DIFFERENCE', 'FINANCE', $ref, ['cashbox' => $cashbox->name, 'amount' => Money::decimal($difference)]);
    }

    /** Dövizi kalmayan kasada TRY değeri kalmışsa (iptal sonrası maliyet artığı) kur farkına aktarılır. */
    private function settleEmptyForeignCashbox(Cashbox $cashbox, ?User $by): void
    {
        if ($cashbox->isForeign() && Money::cents($cashbox->currency_balance) === 0) {
            $this->recordFxDifference($cashbox, -Money::cents($cashbox->balance), "Kur farkı: {$cashbox->name} kapanış", $by);
        }
    }

    /** Kasa, para birimi cinsinden kontrol edilir. Ortalama maliyet eksi bakiyede tanımsız olduğundan döviz kasası eksiye düşemez. */
    private function assertCanPay(Cashbox $cashbox, int $amount): void
    {
        $allowNegative = ! $cashbox->isForeign() && (bool) Apartment::whereKey($cashbox->apartment_id)->value('allow_negative_cashbox');
        $balance = Money::cents($cashbox->nativeBalance());
        if (! $allowNegative && $balance - $amount < 0) {
            throw new FinanceException("\"{$cashbox->name}\" bakiyesi yetersiz: mevcut ".Money::formatIn($balance / 100, $cashbox->currency)
                .', gereken '.Money::formatIn($amount / 100, $cashbox->currency).'.'
                .($cashbox->isForeign() ? '' : ' (Eksi bakiyeye izin vermek için Kasa ayarlarını kullanabilirsiniz.)'));
        }
    }

    private function assertBaseCurrency(Cashbox $cashbox, string $operation): void
    {
        if ($cashbox->isForeign()) {
            throw new FinanceException("{$operation} yalnızca TL kasadan yapılabilir. \"{$cashbox->name}\" kasasındaki dövizi önce virmanla TL kasaya aktarın.");
        }
    }

    /** Parça, makbuz ve yevmiye açıklamaları için döviz notu */
    private function foreignNote(Cashbox $cashbox, array $money): string
    {
        return $cashbox->isForeign()
            ? ' ['.Money::formatIn($money['original'] / 100, $cashbox->currency).' × '.Money::formatRate($money['rate']).']'
            : '';
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

    /** Kapatılan döneme (bu tarih dahil) kayıt eklenemez, oradaki kayıtlar iptal edilemez. */
    public function assertPeriodOpen(Apartment $apartment, CarbonInterface|string $date): void
    {
        $closedUntil = $apartment->closed_until;
        if ($closedUntil && CarbonImmutable::parse($date)->toDateString() <= $closedUntil->toDateString()) {
            throw new FinanceException("{$closedUntil->format('d.m.Y')} tarihine kadarki dönem kapalı. Bu döneme kayıt eklenemez ve oradaki kayıtlar değiştirilemez.");
        }
    }

    private function assertCashboxActive(Cashbox $cashbox): void
    {
        if (! $cashbox->is_active) {
            throw new FinanceException("\"{$cashbox->name}\" pasif olduğu için işlem yapılamaz.");
        }
    }

    private function assertDebtPeriodOpen(Debt $debt): void
    {
        $this->assertPeriodOpen($debt->apartment, $debt->due_date);
        $this->assertPeriodOpen($debt->apartment, $debt->created_at);
    }

    public function audit(?Apartment $apartment, ?User $by, string $action, string $module, ?string $reference, array $data = []): void
    {
        AuditLog::create([
            'apartment_id' => $apartment?->id, 'user_id' => $by?->id, 'action' => $action,
            'module' => $module, 'reference' => $reference, 'data' => $data,
        ]);
    }
}
