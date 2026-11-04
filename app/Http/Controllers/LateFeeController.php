<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Services\FinanceService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Gecikme zammı: ayarlar, önizleme ve hesaplayıp borç olarak yazma */
class LateFeeController extends Controller
{
    public function index(Request $request, Apartment $apartment, FinanceService $finance)
    {
        $asOf = $request->date('as_of') ? CarbonImmutable::instance($request->date('as_of')) : CarbonImmutable::today();
        $items = $finance->pendingLateFees($apartment, $asOf);

        return view('site.finance.late-fees', [
            'apartment' => $apartment,
            'asOf' => $asOf,
            'byUnit' => $items->groupBy(fn ($i) => $i['debt']->unit_id)->map(fn ($rows) => [
                'unit' => $rows->first()['debt']->unit,
                'count' => $rows->count(),
                'total' => $rows->sum('amount') / 100,
            ])->sortByDesc('total')->values(),
            'total' => $items->sum('amount') / 100,
            'existing' => [
                'count' => $apartment->debts()->where('kind', 'late_fee')->count(),
                'sum' => (float) $apartment->debts()->where('kind', 'late_fee')->sum('amount'),
            ],
        ]);
    }

    public function settings(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'late_fee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'late_fee_grace_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);
        $apartment->update($data + ['late_fee_enabled' => $request->boolean('late_fee_enabled')]);

        return back()->with('status', 'Gecikme zammı ayarları kaydedildi.');
    }

    public function run(Request $request, Apartment $apartment, FinanceService $finance)
    {
        $asOf = CarbonImmutable::parse($request->validate(['as_of' => ['required', 'date', 'before_or_equal:today']])['as_of']);
        $result = $finance->runLateFees($apartment, $asOf, $request->user());

        return back()->with('status', $result['created']
            ? "{$result['created']} gecikme zammı borcu yazıldı (toplam ".Money::format($result['total'] / 100).').'
            : 'Yazılacak yeni gecikme zammı yok.');
    }
}
