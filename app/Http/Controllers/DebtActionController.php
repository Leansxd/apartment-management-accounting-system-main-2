<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\CashTransaction;
use App\Models\Debt;
use App\Services\FinanceService;
use Illuminate\Http\Request;

/** Tek bir borç / kasa hareketi üzerindeki işlemler: taksitlendirme, icra işareti, iptal, makbuz */
class DebtActionController extends Controller
{
    public function __construct(private FinanceService $finance)
    {
    }

    public function installments(Request $request, Apartment $apartment, Debt $debt)
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'between:2,36'],
            'first_due' => ['required', 'date', 'after_or_equal:today'],
            'every_months' => ['required', 'integer', 'between:1,12'],
        ]);

        return $this->attempt(fn () => $this->finance->installments($debt, (int) $data['count'], $data['first_due'],
            (int) $data['every_months'], $request->user()), 'Borç taksitlendirildi.');
    }

    public function legal(Request $request, Apartment $apartment, Debt $debt)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:200']]);
        $on = $debt->legal_status === null;
        $this->finance->setLegal($debt, $on, $data['note'] ?? null, $request->user());

        return back()->with('status', $on ? 'Borç icra takibine alındı.' : 'İcra işareti kaldırıldı.');
    }

    /** Kasa hareketini iptal eder (ters kayıtla) */
    public function reverse(Request $request, Apartment $apartment, CashTransaction $cashTransaction)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:150']]);

        return $this->attempt(fn () => $this->finance->reverse($cashTransaction, $data['reason'], $request->user()), 'Hareket iptal edildi (ters kayıt atıldı).');
    }

    /** Yazdırılabilir tahsilat / ödeme makbuzu */
    public function receipt(Apartment $apartment, CashTransaction $cashTransaction)
    {
        return view('site.finance.receipt', [
            'apartment' => $apartment,
            'tx' => $cashTransaction->load('unit.block', 'unit.members', 'cashbox', 'performer'),
        ]);
    }
}
