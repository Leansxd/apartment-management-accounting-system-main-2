<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Cashbox;
use App\Models\Expense;
use App\Models\Vendor;
use App\Services\FinanceService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Giderler (fatura onayı) ve tedarikçi firmalar */
class ExpenseController extends Controller
{
    public function index(Request $request, Apartment $apartment)
    {
        $status = $request->query('status');

        return view('site.finance.expenses', [
            'apartment' => $apartment,
            'expenses' => $apartment->expenses()->with('vendor', 'category', 'cashbox')
                ->when(in_array($status, ['pending', 'approved'], true), fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(40)->withQueryString(),
            'vendors' => $apartment->vendors()->where('is_active', true)->orderBy('name')->get(),
            'categories' => $apartment->categories()->where('type', 'expense')->orderBy('name')->get(),
            'cashboxes' => $apartment->cashboxes()->orderBy('name')->get(),
            'status' => $status,
        ]);
    }

    public function store(Request $request, Apartment $apartment, FinanceService $finance)
    {
        $data = $request->validate([
            'vendor_id' => ['nullable', Rule::exists('vendors', 'id')->where('apartment_id', $apartment->id)],
            'category_id' => ['nullable', Rule::exists('transaction_categories', 'id')->where('apartment_id', $apartment->id)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'vat_rate' => ['required', Rule::in([0, 1, 10, 20])],
            'description' => ['required', 'string', 'max:200'],
            'invoice_no' => ['nullable', 'string', 'max:40'],
            'invoice_date' => ['nullable', 'date'],
            'pay_now_cashbox_id' => ['nullable', Rule::exists('cashboxes', 'id')->where('apartment_id', $apartment->id)],
        ]);
        $cashboxId = $data['pay_now_cashbox_id'] ?? null;
        unset($data['pay_now_cashbox_id']);
        $net = $this->cents($data['amount']);
        $data['amount'] = Money::decimal($net);
        $data['total_amount'] = Money::decimal((int) round($net * (100 + (int) $data['vat_rate']) / 100));

        return $this->attempt(function () use ($apartment, $data, $cashboxId, $finance, $request) {
            return DB::transaction(function () use ($apartment, $data, $cashboxId, $finance, $request) {
                $expense = $apartment->expenses()->create($data + ['created_by' => $request->user()->id]);
                $finance->audit($apartment, $request->user(), 'CREATE_EXPENSE', 'EXPENSE', "expense:{$expense->id}",
                    ['description' => $expense->description, 'total' => $expense->total_amount]);

                if ($cashboxId) {
                    $cashbox = $apartment->cashboxes()->findOrFail($cashboxId);
                    $finance->approveExpense($expense, $cashbox, $request->user());

                    return 'Gider kaydedildi ve kasadan ödendi.';
                }

                return 'Gider onay bekliyor olarak kaydedildi.';
            });
        }, '');
    }

    public function approve(Request $request, Apartment $apartment, Expense $expense, FinanceService $finance)
    {
        $data = $request->validate(['cashbox_id' => ['required', Rule::exists('cashboxes', 'id')->where('apartment_id', $apartment->id)]]);

        return $this->attempt(fn () => $finance->approveExpense($expense, Cashbox::findOrFail($data['cashbox_id']), $request->user()),
            'Gider onaylandı ve kasadan düşüldü.');
    }

    /** Yalnızca onaylanmamış (kasaya işlenmemiş) gider silinebilir */
    public function destroy(Request $request, Apartment $apartment, Expense $expense, FinanceService $finance)
    {
        if ($expense->status === 'approved') {
            return back()->with('error', 'Onaylanmış gider silinemez; düzeltme için kasaya gelir kaydı girin.');
        }
        $finance->audit($apartment, $request->user(), 'DELETE_EXPENSE', 'EXPENSE', "expense:{$expense->id}",
            ['description' => $expense->description, 'total' => $expense->total_amount]);
        $expense->delete();

        return back()->with('status', 'Gider silindi.');
    }

    public function vendors(Apartment $apartment)
    {
        return view('site.finance.vendors', [
            'apartment' => $apartment,
            'vendors' => $apartment->vendors()->where('is_active', true)->orderBy('name')
                ->withSum(['expenses as paid_total' => fn ($q) => $q->where('status', 'approved')], 'total_amount')->get(),
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
