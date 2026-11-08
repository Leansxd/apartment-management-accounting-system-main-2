<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Cashbox;
use App\Models\CashTransaction;
use App\Models\Debt;
use App\Models\Unit;
use App\Services\FinanceService;
use App\Services\ReportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    private const AMOUNT = ['required', 'numeric', 'min:0.01', 'max:999999999'];

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

        $debts = $apartment->debts()->with('unit.block')
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->when($status === 'paid', fn ($q) => $q->where('status', Debt::PAID))
            ->when($status === 'open', fn ($q) => $q->where('status', Debt::PENDING))
            ->when($status === 'overdue', fn ($q) => $q->where('status', Debt::PENDING)->whereDate('due_date', '<', today()))
            ->when($request->query('q'), fn ($q, $s) => $q->where('description', 'like', "%{$s}%"))
            ->latest('id')->paginate(50)->withQueryString();

        return view('site.finance.debts', [
            'apartment' => $apartment,
            'debts' => $debts,
            'units' => $this->units($apartment),
            'blocks' => $apartment->blocks()->orderBy('name')->get(),
            'groups' => $apartment->unitGroups()->get(),
            'categories' => $apartment->categories()->where('type', 'income')->orderBy('name')->get(),
            'cashboxes' => $apartment->cashboxes()->orderBy('name')->get(),
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
            ], $request->user());
            $failed = collect($report['failed'])->map(fn ($f) => "{$f['unit']}: {$f['message']}")->implode('; ');

            return "{$report['created']} daireye borç yazıldı.".($failed ? " Hatalı: {$failed}" : '');
        }, '');
    }

    public function destroyDebt(Request $request, Apartment $apartment, Debt $debt)
    {
        return $this->attempt(fn () => $this->finance->deleteDebt($debt, $request->user()), 'Borç silindi (ters kayıt atıldı).');
    }

    // ------------------------------------------------------------------ TAHSİLAT

    public function collections(Request $request, Apartment $apartment)
    {
        $unit = $request->integer('unit_id') ? $apartment->units()->with('block', 'members')->find($request->integer('unit_id')) : null;

        return view('site.finance.collections', [
            'apartment' => $apartment,
            'units' => $this->units($apartment),
            'cashboxes' => $apartment->cashboxes()->orderBy('name')->get(),
            'unit' => $unit,
            'openDebts' => $unit ? $unit->debts()->where('status', Debt::PENDING)->orderBy('due_date')->get() : collect(),
            'recent' => $apartment->cashTransactions()->whereIn('sub_type', ['collection', 'advance', 'pos'])
                ->with('cashbox', 'unit.block', 'performer')->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function collect(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'unit_id' => ['required', Rule::exists('units', 'id')->where('apartment_id', $apartment->id)],
            'cashbox_id' => ['required', Rule::exists('cashboxes', 'id')->where('apartment_id', $apartment->id)],
            'debt_id' => ['nullable', Rule::exists('debts', 'id')->where('apartment_id', $apartment->id)],
            'amount' => self::AMOUNT,
            'method' => ['required', Rule::in(['collection', 'pos', 'advance'])],
            'description' => ['nullable', 'string', 'max:200'],
        ]);
        $unit = Unit::with('block')->findOrFail($data['unit_id']);
        $debt = isset($data['debt_id']) ? Debt::where('unit_id', $unit->id)->findOrFail($data['debt_id']) : null;

        return $this->attempt(function () use ($unit, $data, $debt, $request) {
            $tx = $this->finance->collect($unit, Cashbox::findOrFail($data['cashbox_id']), $this->cents($data['amount']),
                $data['description'] ?? null, $request->user(), $debt, $data['method']);

            return 'Tahsilat alındı: '.Money::format($tx->amount).' (borçlara '.Money::format($tx->applied_to_debts)
                .', avansa '.Money::format($tx->to_advance).')';
        }, '');
    }

    // ------------------------------------------------------------------ KASA

    public function cashboxes(Request $request, Apartment $apartment)
    {
        $cashboxId = $request->integer('cashbox_id') ?: null;

        return view('site.finance.cashboxes', [
            'apartment' => $apartment,
            'cashboxes' => $apartment->cashboxes()->orderBy('name')->get(),
            'categories' => $apartment->categories()->orderBy('type')->orderBy('name')->get(),
            'transactions' => $apartment->cashTransactions()->with('cashbox', 'unit.block', 'category', 'performer')
                ->when($cashboxId, fn ($q) => $q->where('cashbox_id', $cashboxId))
                ->latest('id')->paginate(40)->withQueryString(),
            'cashboxId' => $cashboxId,
        ]);
    }

    /** Yeni kasa. Açılış bakiyesi kasaya işlenir ama dönemin geliri sayılmaz. */
    public function storeCashbox(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(array_keys(Cashbox::TYPES))],
            'account_number' => ['nullable', 'string', 'max:40'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);

        return $this->attempt(function () use ($apartment, $data, $request) {
            $box = $apartment->cashboxes()->create([
                'name' => $data['name'], 'type' => $data['type'], 'account_number' => $data['account_number'] ?? null,
            ]);
            if (($opening = $this->cents($data['opening_balance'] ?? 0)) > 0) {
                $this->finance->recordOpeningBalance($box, $opening, $request->user());
            }
        }, 'Kasa oluşturuldu.');
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

    public function storeTransaction(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'cashbox_id' => ['required', Rule::exists('cashboxes', 'id')->where('apartment_id', $apartment->id)],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => self::AMOUNT,
            'description' => ['required', 'string', 'max:200'],
            'category_id' => ['nullable', Rule::exists('transaction_categories', 'id')->where('apartment_id', $apartment->id)],
        ]);

        return $this->attempt(fn () => $this->finance->generalTransaction(Cashbox::findOrFail($data['cashbox_id']),
            $data['type'], $this->cents($data['amount']), $data['description'], $data['category_id'] ?? null, $request->user()),
            'Kasa hareketi kaydedildi.');
    }

    public function transfer(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'from_id' => ['required', Rule::exists('cashboxes', 'id')->where('apartment_id', $apartment->id)],
            'to_id' => ['required', 'different:from_id', Rule::exists('cashboxes', 'id')->where('apartment_id', $apartment->id)],
            'amount' => self::AMOUNT,
            'description' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->attempt(fn () => $this->finance->transfer(Cashbox::findOrFail($data['from_id']),
            Cashbox::findOrFail($data['to_id']), $this->cents($data['amount']), $data['description'] ?? null, $request->user()),
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

    // ------------------------------------------------------------------ RAPORLAR

    public function ledger(Request $request, Apartment $apartment)
    {
        return view('site.finance.ledger', [
            'apartment' => $apartment,
            'entries' => $apartment->ledgerEntries()->latest('id')->paginate(60),
            'totals' => $apartment->ledgerEntries()->selectRaw('COALESCE(SUM(debit),0) debit, COALESCE(SUM(credit),0) credit')->first(),
        ]);
    }

    /** Gelir gider tablosu: seçilen aralıkta kategori bazında toplamlar (virmanlar hariç) */
    public function incomeExpense(Request $request, Apartment $apartment)
    {
        $from = $request->date('from')?->toDateString() ?? now()->startOfYear()->toDateString();
        $to = $request->date('to')?->toDateString() ?? today()->toDateString();

        $rows = $apartment->cashTransactions()->operating()
            ->whereDate('cash_transactions.created_at', '>=', $from)->whereDate('cash_transactions.created_at', '<=', $to)
            ->leftJoin('transaction_categories as c', 'c.id', '=', 'cash_transactions.category_id')
            ->selectRaw('cash_transactions.type, cash_transactions.sub_type, c.name as category, SUM(cash_transactions.amount) total, COUNT(*) cnt')
            ->groupBy('cash_transactions.type', 'cash_transactions.sub_type', 'c.name')
            ->get()
            ->map(fn ($r) => [
                'type' => $r->type,
                'label' => $r->category ?: (CashTransaction::SUB_TYPES[$r->sub_type] ?? $r->sub_type),
                'total' => (float) $r->total,
                'count' => (int) $r->cnt,
            ])
            ->groupBy('type');

        return view('site.finance.income-expense', [
            'apartment' => $apartment,
            'income' => ($rows['income'] ?? collect())->groupBy('label')->map(fn ($g) => ['total' => $g->sum('total'), 'count' => $g->sum('count')]),
            'expense' => ($rows['expense'] ?? collect())->groupBy('label')->map(fn ($g) => ['total' => $g->sum('total'), 'count' => $g->sum('count')]),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function statement(Request $request, Apartment $apartment, ReportService $reports)
    {
        $unit = $request->integer('unit_id') ? $apartment->units()->with('block', 'members')->find($request->integer('unit_id')) : null;
        $from = $request->date('from')?->toDateString();
        $to = $request->date('to')?->toDateString();

        return view('site.finance.statement', [
            'apartment' => $apartment,
            'units' => $this->units($apartment),
            'unit' => $unit,
            'statement' => $unit ? $reports->statement($unit, $from, $to) : null,
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
