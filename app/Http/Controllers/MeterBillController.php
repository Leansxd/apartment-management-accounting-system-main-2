<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Meter;
use App\Models\MeterBill;
use App\Services\FinanceException;
use App\Services\MeterBillService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Sayaç faturası dağıtımı: önizleme ve dairelere borç olarak yazma */
class MeterBillController extends Controller
{
    public function index(Apartment $apartment)
    {
        return view('site.ops.meter-bills', [
            'apartment' => $apartment,
            'bills' => $apartment->meterBills()->latest('id')->limit(30)->get(),
            'preview' => null,
            'input' => [],
        ]);
    }

    public function store(Request $request, Apartment $apartment, MeterBillService $service)
    {
        $data = $request->validate([
            'meter_type' => ['required', Rule::in(Meter::TYPES)],
            'method' => ['required', Rule::in(array_keys(MeterBill::METHODS))],
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'total_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'description' => ['required', 'string', 'max:150'],
            'due_date' => ['required', 'date', 'after_or_equal:period_to'],
        ]);

        try {
            if ($request->input('action') === 'preview') {
                $rows = $service->preview($apartment, $data['meter_type'], $data['method'], $data['period_from'], $data['period_to'], Money::parse($data['total_amount']));

                return view('site.ops.meter-bills', ['apartment' => $apartment, 'bills' => $apartment->meterBills()->latest('id')->limit(30)->get(), 'preview' => $rows, 'input' => $data]);
            }
            $result = $service->distribute($apartment, $data, $request->user());
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('site.meter-bills.index', $apartment)->with('status', "Fatura {$result['created']} daireye dağıtıldı.");
    }
}
