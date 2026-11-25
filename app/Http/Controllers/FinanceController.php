<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Cashbox;
use App\Models\CashTransaction;
use App\Models\Debt;
use App\Models\LedgerEntry;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\ExchangeRateService;
use App\Services\FinanceService;
use App\Services\ModuleStatsService;
use App\Services\ReportService;
use App\Support\Money;
use App\Support\SpreadsheetExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    private const AMOUNT = ['required', 'numeric', 'min:0.01', 'max:999999999'];

    private const EXCHANGE_RATE = ['nullable', 'numeric', 'gt:0', 'max:99999'];

    private const COLLECTION_METHODS = ['collection', 'pos', 'advance'];

    public function __construct(private FinanceService $finance) {}

    public function overview(Request $request, Apartment $apartment, ReportService $reports)
    {
        return view('site.finance.overview', [
            'apartment' => $apartment,
            'summary' => $reports->summary($apartment),
            'performance' => $reports->collectionPerformance($apartment),
            'cashboxes' => $apartment->cashboxes()->orderBy('name')->get(),
            'pendingExpenses' => $apartment->expenses()->where('status', 'pending')->with('vendor')->latest()->limit(5)->get(),
            'recent' => $apartment->cashTransactions()->with('cashbox', 'unit.block')->latest('id')->limit(10)->get(),
        ]);
    }

    // ------------------------------------------------------------------ BORÇLANDIRMA

    public function debts(Request $request, Apartment $apartment)
    {
        $status = $request->query('status');
        $unitId = $request->integer('unit_id') ?: null;

        $debts = $apartment->debts()->with('unit.block', 'expense')
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->when($status === 'paid', fn ($q) => $q->where('status', Debt::PAID))
            ->when($status === 'open', fn ($q) => $q->where('status', Debt::PENDING))
            ->when($status === 'overdue', fn ($q) => $q->where('status', Debt::PENDING)->whereDate('due_date', '<', today()))
            ->when($request->query('q'), fn ($q, $s) => $q->where('description', 'like', "%{$s}%"))
            ->when($request->integer('block_id'), fn ($q, $block) => $q->whereHas('unit', fn ($u) => $u->where('block_id', $block)))
            ->when(in_array($request->query('kind'), ['due', 'installment', 'late_fee'], true), fn ($q) => $q->where('kind', $request->query('kind')))
            ->when($request->query('category'), fn ($q, $category) => $q->where('category_name', $category))
            ->when($request->boolean('legal'), fn ($q) => $q->whereNotNull('legal_status'))
            ->when($this->dateQuery($request, 'from'), fn ($q, $date) => $q->whereDate('due_date', '>=', $date))
            ->when($this->dateQuery($request, 'to'), fn ($q, $date) => $q->whereDate('due_date', '<=', $date))
            ->latest('id')->paginate(10)->withQueryString();

        return view('site.finance.debts', [
            'apartment' => $apartment,
            'debts' => $debts,
            'units' => $this->units($apartment),
            'blocks' => $apartment->blocks()->orderBy('name')->get(),
            'groups' => $apartment->unitGroups()->get(),
            'categories' => $apartment->categories()->where('type', 'income')->orderBy('name')->get(),
            'cashboxes' => $apartment->activeCashboxes()->orderBy('name')->get(),
            'filters' => ['status' => $status, 'unit_id' => $unitId, 'q' => $request->query('q')],
        ]);
    }

    /** Borç dağıtımı: tüm site, bir blok, bir grup veya tek daire */
    public function storeDebt(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'target' => ['required', Rule::in(['all', 'block', 'group', 'unit'])],
            'block_id' => ['required_if:target,block', 'nullable', Rule::exists('blocks', 'id')->where('apartment_id', $apartment->id)],
            'unit_group_id' => ['required_if:target,group', 'nullable', Rule::exists('unit_groups', 'id')->where('apartment_id', $apartment->id)],
            'unit_id' => ['required_if:target,unit', 'nullable', Rule::exists('units', 'id')->where('apartment_id', $apartment->id)],
            'category_id' => ['nullable', Rule::exists('transaction_categories', 'id')->where('apartment_id', $apartment->id)],
            'description' => ['required', 'string', 'max:200'],
            'amount' => self::AMOUNT,
            'due_date' => ['required', 'date'],
            'period' => ['nullable', 'date_format:Y-m'],
            'responsible' => ['nullable', Rule::in(array_keys(Debt::RESPONSIBLES))],
        ]);

        $units = match ($data['target']) {
            'block' => $apartment->units()->where('block_id', $data['block_id']),
            'group' => $apartment->unitGroups()->findOrFail($data['unit_group_id'])->units(),
            'unit' => $apartment->units()->whereKey($data['unit_id']),
            default => $apartment->units(),
        };
        $category = ($data['category_id'] ?? null) ? $apartment->categories()->find($data['category_id']) : null;

        return $this->attempt(function () use ($apartment, $units, $data, $category, $request) {
            $report = $this->finance->distribute($apartment, $units->with('block')->get(), [
                'description' => $data['description'],
                'amount' => $this->cents($data['amount']),
                'due_date' => $data['due_date'],
                'period' => $data['period'] ?? substr($data['due_date'], 0, 7),
                'category_id' => $category?->id,
                'category_name' => $category?->name ?? 'Genel',
                'responsible' => $data['responsible'] ?? null,
            ], $request->user());
            $failed = collect($report['failed'])->map(fn ($f) => "{$f['unit']}: {$f['message']}")->implode('; ');

            return "{$report['created']} daireye borç yazıldı.".($failed ? " Hatalı: {$failed}" : '');
        }, '');
    }

    public function updateDebt(Request $request, Apartment $apartment, Debt $debt)
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:200'],
            'due_date' => ['required', 'date'],
            'amount' => ['sometimes', ...self::AMOUNT],
            'category_id' => ['sometimes', 'nullable', Rule::exists('transaction_categories', 'id')->where('apartment_id', $apartment->id)],
            'responsible' => ['sometimes', 'nullable', Rule::in(array_keys(Debt::RESPONSIBLES))],
        ]);
        $fields = ['description' => $data['description'], 'due_date' => $data['due_date']];
        if (array_key_exists('responsible', $data)) {
            $fields['responsible'] = $data['responsible'];
        }
        if (array_key_exists('amount', $data)) {
            $fields['amount'] = $this->cents($data['amount']);
        }
        if (array_key_exists('category_id', $data)) {
            $category = $data['category_id'] ? $apartment->categories()->find($data['category_id']) : null;
            $fields['category_id'] = $category?->id;
            $fields['category_name'] = $category?->name ?? 'Genel';
        }

        return $this->attempt(fn () => $this->finance->updateDebt($debt, $fields, $request->user()), 'Borç güncellendi.');
    }

    public function destroyDebt(Request $request, Apartment $apartment, Debt $debt)
    {
        return $this->attempt(fn () => $this->finance->deleteDebt($debt, $request->user()), 'Borç silindi (ters kayıt atıldı).');
    }

    // ------------------------------------------------------------------ TAHSİLAT

    public function collections(Request $request, Apartment $apartment, ExchangeRateService $rates)
    {
        $unit = $request->integer('unit_id') ? $apartment->units()->with('block', 'members')->find($request->integer('unit_id')) : null;

        return view('site.finance.collections', [
            'apartment' => $apartment,
            'units' => $this->units($apartment),
            'cashboxes' => $apartment->activeCashboxes()->orderBy('name')->get(),
            'unit' => $unit,
            'openDebts' => $unit ? $unit->debts()->where('status', Debt::PENDING)->orderBy('due_date')->get() : collect(),
            'rateSuggestions' => $unit ? $rates->suggestions($apartment) : [],
            'recent' => $apartment->cashTransactions()->whereIn('sub_type', ['collection', 'advance', 'pos'])
                ->when($this->dateQuery($request, 'from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                ->when($this->dateQuery($request, 'to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
                ->when($request->integer('cashbox_id'), fn ($q, $cashbox) => $q->where('cashbox_id', $cashbox))
                ->when(in_array($request->query('method'), ['collection', 'pos', 'advance'], true), fn ($q) => $q->where('sub_type', $request->query('method')))
                ->with('cashbox', 'unit.block', 'performer', 'payer')->latest('id')->paginate(10)->withQueryString(),
        ]);
    }

    /** Parçalı tahsilat (parts[]); tek parçalı eski form alanları (cashbox_id, amount, method) da kabul edilir. */
    public function collect(Request $request, Apartment $apartment)
    {
        $cashboxExists = Rule::exists('cashboxes', 'id')->where('apartment_id', $apartment->id);
        $data = $request->validate([
            'unit_id' => ['required', Rule::exists('units', 'id')->where('apartment_id', $apartment->id)],
            'debt_id' => ['nullable', Rule::exists('debts', 'id')->where('apartment_id', $apartment->id)],
            'description' => ['nullable', 'string', 'max:200'],
            'parts' => ['required_without:amount', 'array', 'min:1', 'max:10'],
            'parts.*.cashbox_id' => ['required', $cashboxExists],
            'parts.*.amount' => self::AMOUNT,
            'parts.*.method' => ['required', Rule::in(self::COLLECTION_METHODS)],
            'parts.*.exchange_rate' => self::EXCHANGE_RATE,
            'cashbox_id' => ['required_without:parts', 'nullable', $cashboxExists],
            'amount' => ['required_without:parts', 'nullable', 'numeric', 'min:0.01', 'max:999999999'],
            'method' => ['required_without:parts', 'nullable', Rule::in(self::COLLECTION_METHODS)],
            'exchange_rate' => self::EXCHANGE_RATE,
            'payer_member_id' => ['nullable', Rule::exists('members', 'id')->where('unit_id', $request->integer('unit_id'))->whereNull('deleted_at')],
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ], [
            'payer_member_id.exists' => 'Ödeyen kişi bu dairenin sakini olmalıdır.',
        ]);
        $unit = Unit::with('block')->findOrFail($data['unit_id']);
        $debt = isset($data['debt_id']) ? Debt::where('unit_id', $unit->id)->findOrFail($data['debt_id']) : null;
        $payer = isset($data['payer_member_id']) ? $unit->members()->findOrFail($data['payer_member_id']) : null;
        $allocations = collect($data['allocations'] ?? [])->filter(fn ($value) => (float) $value > 0)
            ->mapWithKeys(fn ($value, $debtId) => [(int) $debtId => $this->cents($value)])->all();

        $rows = $data['parts'] ?? [$data];
        $cashboxes = $apartment->cashboxes()->whereKey(array_column($rows, 'cashbox_id'))->get()->keyBy('id');
        $parts = array_map(fn (array $row) => [
            'cashbox' => $cashboxes[$row['cashbox_id']],
            'amount' => $this->cents($row['amount']),
            'method' => $row['method'],
            'exchange_rate' => isset($row['exchange_rate']) ? (string) $row['exchange_rate'] : null,
        ], array_values($rows));

        return $this->attempt(function () use ($unit, $parts, $data, $debt, $payer, $allocations, $request) {
            $transactions = $this->finance->collectParts($unit, $parts, $data['description'] ?? null, $request->user(), $debt, $payer, $allocations);
            $sum = fn (string $column) => $transactions->sum(fn (CashTransaction $tx) => Money::cents($tx->{$column})) / 100;
            $partCount = $transactions->count() > 1 ? " · {$transactions->count()} parça" : '';

            return 'Tahsilat alındı: '.Money::format($sum('amount')).$partCount.' (borçlara '.Money::format($sum('applied_to_debts'))
                .', avansa '.Money::format($sum('to_advance')).')';
        }, '');
    }

    // ------------------------------------------------------------------ KASA

    public function cashboxes(Request $request, Apartment $apartment, ExchangeRateService $rates)
    {
        $cashboxId = $request->integer('cashbox_id') ?: null;

        return view('site.finance.cashboxes', [
            'apartment' => $apartment,
            'cashboxes' => $apartment->cashboxes()->orderByDesc('is_active')->orderBy('name')->get(),
            'activeCashboxes' => $apartment->activeCashboxes()->orderBy('name')->get(),
            'categories' => $apartment->categories()->orderBy('type')->orderBy('name')->get(),
            'transactions' => $apartment->cashTransactions()->with('cashbox', 'unit.block', 'category', 'performer')
                ->when($cashboxId, fn ($q) => $q->where('cashbox_id', $cashboxId))
                ->when(in_array($request->query('type'), ['income', 'expense'], true), fn ($q) => $q->where('type', $request->query('type')))
                ->when($request->integer('category_id'), fn ($q, $category) => $q->where('category_id', $category)->effective())
                ->when(array_key_exists((string) $request->query('sub_type'), CashTransaction::SUB_TYPES), fn ($q) => $q->where('sub_type', $request->query('sub_type'))->whereNull('category_id')->effective())
                ->when($this->dateQuery($request, 'from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                ->when($this->dateQuery($request, 'to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
                ->when($request->query('q'), fn ($q, $s) => $q->where('description', 'like', "%{$s}%"))
                ->latest('id')->paginate(10)->withQueryString(),
            'cashboxId' => $cashboxId,
            'rateSuggestions' => $rates->suggestions($apartment),
        ]);
    }

    /** Yeni kasa. Açılış bakiyesi kasaya işlenir ama dönemin geliri sayılmaz. */
    public function storeCashbox(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(array_keys(Cashbox::TYPES))],
            'currency' => ['nullable', Rule::in(array_keys(Cashbox::CURRENCIES))],
            'account_number' => ['nullable', 'string', 'max:40'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'opening_rate' => self::EXCHANGE_RATE,
        ]);

        return $this->attempt(fn () => DB::transaction(function () use ($apartment, $data, $request) {
            $box = $apartment->cashboxes()->create([
                'name' => $data['name'], 'type' => $data['type'], 'account_number' => $data['account_number'] ?? null,
                'currency' => $data['currency'] ?? Cashbox::BASE_CURRENCY,
            ]);
            if (($opening = $this->cents($data['opening_balance'] ?? 0)) > 0) {
                $this->finance->recordOpeningBalance($box, $opening, $request->user(), isset($data['opening_rate']) ? (string) $data['opening_rate'] : null);
            }
        }), 'Kasa oluşturuldu.');
    }

    public function updateCashbox(Request $request, Apartment $apartment, Cashbox $cashbox)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'account_number' => ['nullable', 'string', 'max:40'],
        ]);
        $cashbox->update($data);

        return back()->with('status', 'Kasa güncellendi.');
    }

    /** Pasif kasa listelerde çıkmaz, geçmişi raporlarda kalır. Bakiyesi olan veya son aktif kasa pasife alınamaz. */
    public function toggleCashbox(Apartment $apartment, Cashbox $cashbox)
    {
        if ($cashbox->is_active) {
            if ((float) $cashbox->balance !== 0.0 || (float) $cashbox->nativeBalance() !== 0.0) {
                return back()->with('error', 'Bakiyesi olan kasa pasife alınamaz. Önce bakiyeyi virmanla aktarın.');
            }
            if ($apartment->activeCashboxes()->count() === 1) {
                return back()->with('error', 'Son aktif kasa pasife alınamaz.');
            }
        }
        $cashbox->update(['is_active' => ! $cashbox->is_active]);

        return back()->with('status', $cashbox->is_active ? 'Kasa aktifleştirildi.' : 'Kasa pasife alındı.');
    }

    /** Yalnızca hiç hareket görmemiş kasa silinebilir; diğerleri pasife alınır. */
    public function destroyCashbox(Apartment $apartment, Cashbox $cashbox)
    {
        if ($cashbox->transactions()->exists() || $apartment->bankLines()->where('cashbox_id', $cashbox->id)->exists()) {
            return back()->with('error', 'Hareket görmüş kasa silinemez, pasife alabilirsiniz.');
        }
        if ($cashbox->is_active && $apartment->activeCashboxes()->count() === 1) {
            return back()->with('error', 'Son aktif kasa silinemez.');
        }
        $cashbox->delete();

        return back()->with('status', 'Kasa silindi.');
    }

    public function storeTransaction(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'cashbox_id' => ['required', Rule::exists('cashboxes', 'id')->where('apartment_id', $apartment->id)],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => self::AMOUNT,
            'description' => ['required', 'string', 'max:200'],
            'category_id' => ['nullable', Rule::exists('transaction_categories', 'id')->where('apartment_id', $apartment->id)],
            'exchange_rate' => self::EXCHANGE_RATE,
        ]);

        return $this->attempt(fn () => $this->finance->generalTransaction(Cashbox::findOrFail($data['cashbox_id']),
            $data['type'], $this->cents($data['amount']), $data['description'], $data['category_id'] ?? null, $request->user(),
            isset($data['exchange_rate']) ? (string) $data['exchange_rate'] : null),
            'Kasa hareketi kaydedildi.');
    }

    public function transfer(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'from_id' => ['required', Rule::exists('cashboxes', 'id')->where('apartment_id', $apartment->id)],
            'to_id' => ['required', 'different:from_id', Rule::exists('cashboxes', 'id')->where('apartment_id', $apartment->id)],
            'amount' => self::AMOUNT,
            'target_amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999'],
            'description' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->attempt(fn () => $this->finance->transfer(Cashbox::findOrFail($data['from_id']),
            Cashbox::findOrFail($data['to_id']), $this->cents($data['amount']), $data['description'] ?? null, $request->user(),
            isset($data['target_amount']) ? $this->cents($data['target_amount']) : null),
            'Virman yapıldı.');
    }

    public function storeCategory(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'type' => ['required', Rule::in(['income', 'expense'])],
        ]);
        $apartment->categories()->create($data);

        return back()->with('status', 'Kategori eklendi.');
    }

    public function updateCategory(Request $request, Apartment $apartment, TransactionCategory $category)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60',
            Rule::unique('transaction_categories')->where('apartment_id', $apartment->id)->where('type', $category->type)->ignore($category->id)]],
            ['name.unique' => 'Bu adda bir kategori zaten var.']);
        $category->update($data);

        return back()->with('status', 'Kategori güncellendi.');
    }

    /** Kullanılmış kategori silinmez: geçmiş kayıtların ve yevmiyenin hesabı kaybolmasın. */
    public function destroyCategory(Apartment $apartment, TransactionCategory $category)
    {
        $isUsed = $apartment->debts()->where('category_id', $category->id)->exists()
            || $apartment->cashTransactions()->where('category_id', $category->id)->exists()
            || $apartment->expenses()->where('category_id', $category->id)->exists()
            || $apartment->duesDefinitions()->where('category_id', $category->id)->exists()
            || $apartment->budgetItems()->where('category_id', $category->id)->exists()
            || $apartment->ledgerEntries()->whereIn('account_name', [LedgerEntry::INCOME, LedgerEntry::EXPENSE])->where('account_id', (string) $category->id)->exists();
        if ($isUsed) {
            return back()->with('error', 'Kullanılmış kategori silinemez, adını değiştirebilirsiniz.');
        }
        $category->delete();

        return back()->with('status', 'Kategori silindi.');
    }

    // ------------------------------------------------------------------ RAPORLAR

    public function ledger(Request $request, Apartment $apartment)
    {
        $filtered = $apartment->ledgerEntries()
            ->when($this->dateQuery($request, 'from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($this->dateQuery($request, 'to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($request->query('account'), fn ($q, $account) => $q->where('account_name', $account))
            ->when($request->query('account_id') !== null && $request->query('account_id') !== '', fn ($q) => $q->where('account_id', $request->integer('account_id') ?: null))
            ->when(array_key_exists((string) $request->query('type'), LedgerEntry::ENTRY_TYPES), fn ($q) => $q->where('entry_type', $request->query('type')))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('description', 'like', "%{$s}%")->orWhere('reference', 'like', "%{$s}%")));

        if (in_array($request->query('format'), ['xlsx', 'csv'], true)) {
            $rows = (clone $filtered)->orderBy('id')->lazy()->map(fn (LedgerEntry $e) => [
                'date' => $e->created_at->format('d.m.Y H:i'), 'reference' => $e->referenceLabel(), 'type' => LedgerEntry::ENTRY_TYPES[$e->entry_type] ?? $e->entry_type,
                'account' => $e->account_name, 'description' => $e->description, 'debit' => (float) $e->debit, 'credit' => (float) $e->credit,
            ]);
            $sum = (clone $filtered)->selectRaw('COALESCE(SUM(debit),0) debit, COALESCE(SUM(credit),0) credit')->first();

            return SpreadsheetExport::download("{$apartment->code}-yevmiye-".today()->format('Ymd').'.csv',
                ['date' => 'Tarih', 'reference' => 'Belge', 'type' => 'Tür', 'account' => 'Hesap', 'description' => 'Açıklama', 'debit' => 'Borç', 'credit' => 'Alacak'],
                $rows, ['date' => 'TOPLAM', 'debit' => (float) $sum->debit, 'credit' => (float) $sum->credit],
                ['title' => 'Yevmiye Defteri', 'period' => $this->periodLabel($this->dateQuery($request, 'from'), $this->dateQuery($request, 'to'))]);
        }

        return view('site.finance.ledger', [
            'apartment' => $apartment,
            'entries' => (clone $filtered)->latest('id')->paginate(10)->withQueryString(),
            'accounts' => $apartment->ledgerEntries()->distinct()->orderBy('account_name')->pluck('account_name', 'account_name')->all(),
            'totals' => (clone $filtered)->selectRaw('COALESCE(SUM(debit),0) debit, COALESCE(SUM(credit),0) credit, COUNT(*) cnt')->first(),
            'categories' => $apartment->categories()->orderBy('type')->orderBy('name')->get(),
            'reversedManual' => $apartment->ledgerEntries()->where('entry_type', 'manual_reversal')->distinct()->pluck('reference')->all(),
        ]);
    }

    public function storeManualJournal(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:200'],
            'lines' => ['required', 'array', 'max:10'],
            'lines.*.account' => ['nullable', 'string', 'max:40'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);
        $lines = collect($data['lines'])->filter(fn (array $line) => filled($line['account'] ?? null))
            ->map(fn (array $line) => ['account' => $line['account'], 'debit' => $this->cents($line['debit'] ?? 0), 'credit' => $this->cents($line['credit'] ?? 0)])
            ->values()->all();

        return $this->attempt(fn () => $this->finance->manualJournal($apartment, $data['date'], $data['description'], $lines, $request->user()) && null, 'Fiş kaydedildi.');
    }

    public function reverseManualJournal(Request $request, Apartment $apartment)
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:100'], 'reason' => ['required', 'string', 'max:200']]);

        return $this->attempt(fn () => $this->finance->reverseManualJournal($apartment, $data['reference'], $data['reason'], $request->user()), 'Fiş ters kayıtla iptal edildi.');
    }

    /** Gelir gider tablosu: seçilen aralıkta kategori bazında toplamlar (virmanlar hariç) */
    /** Gelir gider tablosu: kalem bazında toplamlar, önceki eşit uzunluktaki dönemle karşılaştırma ve aylık dağılım */
    public function incomeExpense(Request $request, Apartment $apartment)
    {
        $from = $this->dateQuery($request, 'from') ?? now()->startOfYear()->toDateString();
        $to = $this->dateQuery($request, 'to') ?? today()->toDateString();
        $days = (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;
        $previousTo = CarbonImmutable::parse($from)->subDay()->toDateString();
        $previousFrom = CarbonImmutable::parse($from)->subDays($days)->toDateString();

        $current = $this->incomeExpenseLines($apartment, $from, $to);
        $previous = $this->incomeExpenseLines($apartment, $previousFrom, $previousTo);
        $lines = [];
        foreach (['income', 'expense'] as $type) {
            $keys = $current->where('type', $type)->keys()->merge($previous->where('type', $type)->keys())->unique();
            $lines[$type] = $keys->map(fn (string $key) => ($current[$key] ?? $previous[$key]) + [
                'total' => (float) ($current[$key]['total'] ?? 0), 'count' => (int) ($current[$key]['count'] ?? 0),
                'previous' => (float) ($previous[$key]['total'] ?? 0),
            ])->sortByDesc('total')->keyBy('label');
        }

        $months = $apartment->cashTransactions()->operating()
            ->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->get(['type', 'amount', 'created_at'])
            ->groupBy(fn (CashTransaction $t) => $t->created_at->format('Y-m'))->sortKeys()
            ->map(fn ($group, $month) => [
                'month' => $month,
                'income' => round((float) $group->where('type', 'income')->sum('amount'), 2),
                'expense' => round((float) $group->where('type', 'expense')->sum('amount'), 2),
            ])->values();

        if (in_array($request->query('format'), ['xlsx', 'csv'], true)) {
            $rows = collect(['income' => 'Gelir', 'expense' => 'Gider'])->flatMap(fn ($label, $type) => $lines[$type]->map(fn ($l) => [
                'type' => $label, 'label' => $l['label'], 'count' => $l['count'], 'total' => $l['total'], 'previous' => $l['previous'],
            ]));

            return SpreadsheetExport::download("{$apartment->code}-gelir-gider-".today()->format('Ymd').'.csv',
                ['type' => 'Tür', 'label' => 'Kalem', 'count' => 'Adet', 'total' => 'Tutar', 'previous' => 'Önceki dönem'], $rows, [
                    'type' => 'NET', 'total' => round($lines['income']->sum('total') - $lines['expense']->sum('total'), 2),
                    'previous' => round($lines['income']->sum('previous') - $lines['expense']->sum('previous'), 2),
                ], ['title' => 'Gelir Gider Tablosu', 'period' => $this->periodLabel($from, $to)]);
        }

        return view('site.finance.income-expense', [
            'apartment' => $apartment,
            'income' => $lines['income'],
            'expense' => $lines['expense'],
            'months' => $months,
            'from' => $from,
            'to' => $to,
            'previousFrom' => $previousFrom,
            'previousTo' => $previousTo,
        ]);
    }

    /**
     * Dönemin gelir / gider kalemleri: kategorisi olan hareket kategori adıyla, olmayan işlem türüyle gruplanır.
     *
     * @return Collection<string, array{type: string, label: string, category_id: int|null, sub_type: string|null, total: float, count: int}>
     */
    private function incomeExpenseLines(Apartment $apartment, string $from, string $to)
    {
        return $apartment->cashTransactions()->operating()
            ->whereDate('cash_transactions.created_at', '>=', $from)->whereDate('cash_transactions.created_at', '<=', $to)
            ->leftJoin('transaction_categories as c', 'c.id', '=', 'cash_transactions.category_id')
            ->selectRaw('cash_transactions.type, cash_transactions.sub_type, cash_transactions.category_id, c.name as category, SUM(cash_transactions.amount) total, COUNT(*) cnt')
            ->groupBy('cash_transactions.type', 'cash_transactions.sub_type', 'cash_transactions.category_id', 'c.name')
            ->get()
            ->groupBy(fn ($r) => $r->type.'|'.($r->category_id ? 'c'.$r->category_id : 's'.$r->sub_type))
            ->map(fn ($group) => [
                'type' => $group->first()->type,
                'label' => $group->first()->category ?: (CashTransaction::SUB_TYPES[$group->first()->sub_type] ?? $group->first()->sub_type),
                'category_id' => $group->first()->category_id,
                'sub_type' => $group->first()->category_id ? null : $group->first()->sub_type,
                'total' => round((float) $group->sum('total'), 2),
                'count' => (int) $group->sum('cnt'),
            ]);
    }

    public function statement(Request $request, Apartment $apartment, ReportService $reports, ModuleStatsService $stats)
    {
        $unit = $request->integer('unit_id') ? $apartment->units()->with('block', 'members')->find($request->integer('unit_id')) : null;
        $from = $this->dateQuery($request, 'from');
        $to = $this->dateQuery($request, 'to');

        $statement = $unit ? $reports->statement($unit, $from, $to) : null;

        if ($statement && in_array($request->query('format'), ['xlsx', 'csv'], true)) {
            $rows = collect($statement['rows'])->map(fn (array $r) => [
                'date' => $r['date']->format('d.m.Y'), 'description' => $r['description'], 'due' => $r['due']?->format('d.m.Y'),
                'debit' => (float) $r['debit'], 'credit' => (float) $r['credit'], 'balance' => (float) $r['balance'],
            ])->prepend(['description' => 'Devreden bakiye', 'balance' => (float) $statement['opening']]);

            return SpreadsheetExport::download("{$apartment->code}-ekstre-{$unit->label()}-".today()->format('Ymd').'.csv',
                ['date' => 'Tarih', 'description' => 'Açıklama', 'due' => 'Vade', 'debit' => 'Borç', 'credit' => 'Alacak', 'balance' => 'Bakiye'],
                $rows, ['description' => 'Kapanış bakiyesi', 'debit' => $statement['period_debit'], 'credit' => $statement['period_credit'], 'balance' => (float) $statement['closing']],
                ['title' => 'Daire Hesap Ekstresi · '.$unit->label(), 'period' => $this->periodLabel($from, $to)]);
        }

        return view('site.finance.statement', [
            'apartment' => $apartment,
            'units' => $this->units($apartment),
            'unit' => $unit,
            'statement' => $statement,
            'moduleSummary' => $unit ? $stats->unit($unit) : null,
            'from' => $from,
            'to' => $to,
            'print' => $request->boolean('print'),
        ]);
    }

    private function units(Apartment $apartment)
    {
        return $apartment->units()->with('block')->get()->sortBy(fn ($u) => [$u->block?->name, (int) $u->unit_no])->values();
    }
}
