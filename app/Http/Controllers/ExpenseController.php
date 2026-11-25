<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Cashbox;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Vendor;
use App\Services\ExchangeRateService;
use App\Services\FinanceException;
use App\Services\FinanceService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Giderler (fatura onayı) ve tedarikçi firmalar */
class ExpenseController extends Controller
{
    public function index(Request $request, Apartment $apartment, ExchangeRateService $rates)
    {
        $status = $request->query('status');

        return view('site.finance.expenses', [
            'apartment' => $apartment,
            'expenses' => $apartment->expenses()->with('vendor', 'category', 'cashbox', 'rechargedDebt.unit.block')
                ->when(in_array($status, ['pending', 'approved'], true), fn ($q) => $q->where('status', $status))
                ->when($status === 'partial', fn ($q) => $q->where('status', 'pending')->where('paid_amount', '>', 0))
                ->when($request->integer('vendor_id'), fn ($q, $vendor) => $q->where('vendor_id', $vendor))
                ->when($request->integer('category_id'), fn ($q, $category) => $q->where('category_id', $category))
                ->when($this->dateQuery($request, 'from'), fn ($q, $date) => $q->whereDate('invoice_date', '>=', $date))
                ->when($this->dateQuery($request, 'to'), fn ($q, $date) => $q->whereDate('invoice_date', '<=', $date))
                ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('description', 'like', "%{$s}%")->orWhere('invoice_no', 'like', "%{$s}%")))
                ->latest('id')->paginate(10)->withQueryString(),
            'vendors' => $apartment->vendors()->where('is_active', true)->orderBy('name')->get(),
            'categories' => $apartment->categories()->where('type', 'expense')->orderBy('name')->get(),
            'cashboxes' => $apartment->activeCashboxes()->orderBy('name')->get(),
            'units' => $apartment->units()->with('block')->get()->sortBy(fn ($u) => [$u->block?->name, (int) $u->unit_no])->values(),
            'rateSuggestions' => $rates->suggestions($apartment),
            'status' => $status,
        ]);
    }

    public function store(Request $request, Apartment $apartment, FinanceService $finance)
    {
        $data = $request->validate($this->expenseRules($apartment) + [
            'pay_now_cashbox_id' => ['nullable', Rule::exists('cashboxes', 'id')->where('apartment_id', $apartment->id)],
            'recharge_unit_id' => ['nullable', Rule::exists('units', 'id')->where('apartment_id', $apartment->id)->whereNull('deleted_at')],
            'recharge_amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999'],
            'recharge_responsible' => ['nullable', Rule::in(array_keys(Debt::RESPONSIBLES))],
            'recharge_due_date' => ['nullable', 'date'],
        ]);
        $cashboxId = $data['pay_now_cashbox_id'] ?? null;
        $recharge = array_intersect_key($data, array_flip(['recharge_unit_id', 'recharge_amount', 'recharge_responsible', 'recharge_due_date']));
        $data = array_diff_key($data, $recharge + ['pay_now_cashbox_id' => null]);
        $net = $this->cents($data['amount']);
        $data['amount'] = Money::decimal($net);
        $data['total_amount'] = Money::decimal((int) round($net * (100 + (int) $data['vat_rate']) / 100));

        return $this->attempt(function () use ($apartment, $data, $cashboxId, $recharge, $finance, $request) {
            return DB::transaction(function () use ($apartment, $data, $cashboxId, $recharge, $finance, $request) {
                $expense = $apartment->expenses()->create($data + ['created_by' => $request->user()->id]);
                $finance->audit($apartment, $request->user(), 'CREATE_EXPENSE', 'EXPENSE', "expense:{$expense->id}",
                    ['description' => $expense->description, 'total' => $expense->total_amount]);

                $message = 'Gider onay bekliyor olarak kaydedildi.';
                if ($cashboxId) {
                    $cashbox = $apartment->cashboxes()->findOrFail($cashboxId);
                    $finance->approveExpense($expense, $cashbox, $request->user());
                    $message = 'Gider kaydedildi ve kasadan ödendi.';
                }
                if (! empty($recharge['recharge_unit_id'])) {
                    $unit = $apartment->units()->with('block')->findOrFail($recharge['recharge_unit_id']);
                    $amount = isset($recharge['recharge_amount']) ? $this->cents($recharge['recharge_amount']) : Money::cents($expense->total_amount);
                    $finance->rechargeExpense($expense, $unit, $amount, $recharge['recharge_due_date'] ?? today()->endOfMonth()->toDateString(),
                        $recharge['recharge_responsible'] ?? null, $request->user());
                    $message .= " {$unit->label()} dairesine ".Money::format($amount / 100).' borç yazıldı.';
                }

                return $message;
            });
        }, '');
    }

    /** Yalnızca onaylanmamış (kasaya işlenmemiş) gider düzenlenebilir */
    public function update(Request $request, Apartment $apartment, Expense $expense, FinanceService $finance)
    {
        if ($expense->status === 'approved') {
            return back()->with('error', 'Onaylanmış gider düzenlenemez; düzeltme için kasaya gelir kaydı girin.');
        }
        if ($expense->isPartiallyPaid()) {
            return back()->with('error', 'Kısmi ödemesi yapılmış gider düzenlenemez; önce ödemeleri iptal edin.');
        }
        $data = $request->validate($this->expenseRules($apartment));
        $net = $this->cents($data['amount']);
        $data['amount'] = Money::decimal($net);
        $data['total_amount'] = Money::decimal((int) round($net * (100 + (int) $data['vat_rate']) / 100));

        return $this->attempt(function () use ($apartment, $expense, $data, $finance, $request) {
            $finance->assertPeriodOpen($apartment, $expense->created_at);
            DB::transaction(function () use ($apartment, $expense, $data, $finance, $request) {
                $expense->fill($data);
                if ($expense->isDirty()) {
                    $changes = collect($expense->getDirty())->map(fn ($value, $field) => ['old' => $expense->getRawOriginal($field), 'new' => $value])->all();
                    $expense->save();
                    $finance->audit($apartment, $request->user(), 'UPDATE_EXPENSE', 'EXPENSE', "expense:{$expense->id}", ['changes' => $changes]);
                }
            });
        }, 'Gider güncellendi.');
    }

    /** @return array<string, array<int, mixed>> */
    private function expenseRules(Apartment $apartment): array
    {
        return [
            'vendor_id' => ['nullable', Rule::exists('vendors', 'id')->where('apartment_id', $apartment->id)],
            'category_id' => ['nullable', Rule::exists('transaction_categories', 'id')->where('apartment_id', $apartment->id)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'vat_rate' => ['required', Rule::in([0, 1, 10, 20])],
            'description' => ['required', 'string', 'max:200'],
            'invoice_no' => ['nullable', 'string', 'max:40'],
            'invoice_date' => ['nullable', 'date'],
        ];
    }

    public function approve(Request $request, Apartment $apartment, Expense $expense, FinanceService $finance)
    {
        $data = $request->validate([
            'cashbox_id' => ['required', Rule::exists('cashboxes', 'id')->where('apartment_id', $apartment->id)],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999'],
        ]);
        $amount = isset($data['amount']) ? $this->cents($data['amount']) : null;
        $rate = isset($data['exchange_rate']) ? (string) $data['exchange_rate'] : null;

        return $this->attempt(function () use ($expense, $data, $amount, $rate, $finance, $request) {
            $finance->payExpense($expense, Cashbox::findOrFail($data['cashbox_id']), $amount, $request->user(), $rate);
            $expense->refresh();

            return $expense->status === 'approved'
                ? 'Gider ödendi ve kasadan düşüldü.'
                : 'Kısmi ödeme kaydedildi. Kalan: '.Money::format($expense->remainingAmount());
        }, '');
    }

    /** Yalnızca onaylanmamış (kasaya işlenmemiş) gider silinebilir */
    public function destroy(Request $request, Apartment $apartment, Expense $expense, FinanceService $finance)
    {
        if ($expense->status === 'approved') {
            return back()->with('error', 'Onaylanmış gider silinemez; düzeltme için kasaya gelir kaydı girin.');
        }
        if ($expense->isPartiallyPaid()) {
            return back()->with('error', 'Kısmi ödemesi yapılmış gider silinemez; önce ödemeleri iptal edin.');
        }
        $debt = $expense->rechargedDebt;
        if ($debt && (float) $debt->paid_amount > 0) {
            return back()->with('error', 'Bu giderin daireye yansıtılan borcu tahsil edilmiş; önce tahsilatı iptal edin.');
        }
        try {
            $finance->assertPeriodOpen($apartment, $expense->created_at);
            DB::transaction(function () use ($apartment, $expense, $debt, $finance, $request) {
                // Daireye yansıtılan ödenmemiş borç da ters kayıtla silinir
                if ($debt) {
                    $finance->deleteDebt($debt, $request->user());
                }
                $finance->audit($apartment, $request->user(), 'DELETE_EXPENSE', 'EXPENSE', "expense:{$expense->id}",
                    ['description' => $expense->description, 'total' => $expense->total_amount]);
                $expense->delete();
            });
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $debt ? 'Gider ve daireye yansıtılan borcu silindi.' : 'Gider silindi.');
    }

    public function vendors(Request $request, Apartment $apartment)
    {
        return view('site.finance.vendors', [
            'apartment' => $apartment,
            'vendors' => $apartment->vendors()->where('is_active', true)->orderBy('name')
                ->when($request->query('q'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
                ->withSum('expenses as paid_total', 'paid_amount')->get(),
        ]);
    }

    public function storeVendor(Request $request, Apartment $apartment)
    {
        $apartment->vendors()->create($this->vendorData($request));

        return back()->with('status', 'Firma eklendi.');
    }

    public function updateVendor(Request $request, Apartment $apartment, Vendor $vendor)
    {
        $vendor->update($this->vendorData($request));

        return back()->with('status', 'Firma güncellendi.');
    }

    /** Firma pasife alınır (geçmiş giderlerdeki bağlantısı korunur) */
    public function destroyVendor(Apartment $apartment, Vendor $vendor)
    {
        $vendor->update(['is_active' => false]);

        return back()->with('status', 'Firma silindi.');
    }

    private function vendorData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tax_number' => ['nullable', 'string', 'max:20'],
            'tax_office' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
