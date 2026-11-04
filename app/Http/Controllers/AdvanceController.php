<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Cashbox;
use App\Models\Debt;
use App\Models\Unit;
use App\Services\FinanceService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Daire avansları: açık borca mahsup ve kasadan iade */
class AdvanceController extends Controller
{
    public function index(Apartment $apartment)
    {
        $units = $apartment->units()->with('block')->where('credit_balance', '>', 0)->get()
            ->sortBy(fn (Unit $u) => [$u->block?->name, (int) $u->unit_no])->values();
        $openByUnit = $apartment->debts()->where('status', Debt::PENDING)->whereIn('unit_id', $units->pluck('id'))
            ->selectRaw('unit_id, SUM(remaining_amount) total')->groupBy('unit_id')->pluck('total', 'unit_id');

        return view('site.finance.advances', [
            'apartment' => $apartment,
            'units' => $units,
            'openByUnit' => $openByUnit,
            'cashboxes' => $apartment->cashboxes()->orderBy('name')->get(),
            'total' => (float) $units->sum('credit_balance'),
        ]);
    }

    public function apply(Request $request, Apartment $apartment, Unit $unit, FinanceService $finance)
    {
        return $this->attempt(fn () => 'Avans mahsup edildi: '.Money::format($finance->applyAdvance($unit, $request->user()) / 100), '');
    }

    public function refund(Request $request, Apartment $apartment, Unit $unit, FinanceService $finance)
    {
        $data = $request->validate([
            'cashbox_id' => ['required', Rule::exists('cashboxes', 'id')->where('apartment_id', $apartment->id)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
        ]);

        return $this->attempt(fn () => $finance->refundAdvance($unit, Cashbox::findOrFail($data['cashbox_id']), $this->cents($data['amount']), $request->user()),
            'Avans iade edildi.');
    }
}
