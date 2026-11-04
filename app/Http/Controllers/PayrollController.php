<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Services\PayrollService;
use Illuminate\Http\Request;

/** Personel maaş bordrosu: dönem için gider kayıtları üretir; ödeme Gider Yönetimi'nden kasa seçilerek yapılır. */
class PayrollController extends Controller
{
    public function index(Request $request, Apartment $apartment, PayrollService $payroll)
    {
        $period = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('period')) ? $request->query('period') : now()->format('Y-m');
        $rows = collect($payroll->statusFor($apartment, $period));

        return view('site.ops.payroll', [
            'apartment' => $apartment,
            'period' => $period,
            'rows' => $rows,
            'cashboxes' => $apartment->cashboxes()->orderBy('name')->get(),
            'payable' => (float) $rows->sum(fn ($r) => $r['expense'] === null && $r['staff']->status === 'Aktif' ? $r['staff']->salary : 0),
            'total' => (float) $rows->sum(fn ($r) => $r['expense']?->total_amount ?? 0),
            'paid' => (float) $rows->sum(fn ($r) => $r['expense']?->status === 'approved' ? $r['expense']->total_amount : 0),
        ]);
    }

    public function generate(Request $request, Apartment $apartment, PayrollService $payroll)
    {
        $period = $request->validate(['period' => ['required', 'date_format:Y-m']])['period'];

        return $this->attempt(function () use ($payroll, $apartment, $period, $request) {
            $r = $payroll->generate($apartment, $period, $request->user());

            return "{$period} bordrosu: {$r['created']} maaş kaydı oluşturuldu, {$r['skipped']} zaten vardı.";
        }, '');
    }
}
