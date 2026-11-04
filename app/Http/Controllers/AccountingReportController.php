<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Services\AccountingReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Mali raporlar: mizan, kasa defteri, mali durum, alacak yaşlandırma (yazdırılabilir) */
class AccountingReportController extends Controller
{
    public function __construct(private AccountingReportService $reports)
    {
    }

    public function trialBalance(Request $request, Apartment $apartment)
    {
        $from = $request->date('from')?->toDateString();
        $to = $request->date('to')?->toDateString();

        return view('site.accounting.trial-balance', ['apartment' => $apartment, 'tb' => $this->reports->trialBalance($apartment, $from, $to), 'from' => $from, 'to' => $to]);
    }

    public function cashBook(Request $request, Apartment $apartment)
    {
        $cashboxes = $apartment->cashboxes()->orderBy('name')->get();
        $cashbox = $cashboxes->firstWhere('id', $request->integer('cashbox_id')) ?? $cashboxes->first();
        $from = $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? today()->toDateString();

        return view('site.accounting.cash-book', [
            'apartment' => $apartment, 'cashboxes' => $cashboxes, 'cashbox' => $cashbox, 'from' => $from, 'to' => $to,
            'book' => $cashbox ? $this->reports->cashBook($cashbox, $from, $to) : null,
        ]);
    }

    public function balanceSheet(Apartment $apartment)
    {
        return view('site.accounting.balance-sheet', ['apartment' => $apartment, 'sheet' => $this->reports->balanceSheet($apartment)]);
    }

    public function aging(Request $request, Apartment $apartment)
    {
        $asOf = $request->date('as_of') ? CarbonImmutable::instance($request->date('as_of')) : CarbonImmutable::today();

        return view('site.accounting.aging', ['apartment' => $apartment, 'aging' => $this->reports->aging($apartment, $asOf), 'asOf' => $asOf]);
    }
}
