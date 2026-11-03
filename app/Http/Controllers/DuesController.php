<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\DuesDefinition;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Aidat ve borçlandırma motoru: tanımlar + dönem çalıştırma */
class DuesController extends Controller
{
    public function index(Apartment $apartment)
    {
        return view('site.finance.dues', [
            'apartment' => $apartment,
            'definitions' => $apartment->duesDefinitions()->with('category')->latest()->get(),
            'blocks' => $apartment->blocks()->orderBy('name')->get(),
            'units' => $apartment->units()->with('block')->get()->sortBy(fn ($u) => [$u->block?->name, (int) $u->unit_no]),
            'categories' => $apartment->categories()->where('type', 'income')->orderBy('name')->get(),
            'unitCount' => $apartment->units()->count(),
        ]);
    }

    public function store(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'calculation' => ['required', Rule::in(['fixed', 'sqm'])],
            'target_type' => ['required', Rule::in(['all', 'block', 'unit'])],
            'block_id' => ['required_if:target_type,block', 'nullable', Rule::exists('blocks', 'id')->where('apartment_id', $apartment->id)],
            'unit_id' => ['required_if:target_type,unit', 'nullable', Rule::exists('units', 'id')->where('apartment_id', $apartment->id)],
            'category_id' => ['nullable', Rule::exists('transaction_categories', 'id')->where('apartment_id', $apartment->id)],
            'due_day' => ['required', 'integer', 'between:1,31'],
        ]);

        $apartment->duesDefinitions()->create([
            'name' => $data['name'],
            'amount' => $data['amount'],
            'calculation' => $data['calculation'],
            'target_type' => $data['target_type'],
            'target_id' => match ($data['target_type']) {
                'block' => $data['block_id'],
                'unit' => $data['unit_id'],
                default => null,
            },
            'category_id' => $data['category_id'] ?? null,
            'due_day' => $data['due_day'],
        ]);

        return back()->with('status', 'Tanım eklendi.');
    }

    public function toggle(Apartment $apartment, DuesDefinition $duesDefinition)
    {
        $duesDefinition->update(['is_active' => ! $duesDefinition->is_active]);

        return back()->with('status', $duesDefinition->is_active ? 'Tanım aktifleştirildi.' : 'Tanım durduruldu.');
    }

    public function destroy(Apartment $apartment, DuesDefinition $duesDefinition)
    {
        $duesDefinition->delete();

        return back()->with('status', 'Tanım silindi (üretilmiş borçlar korunur).');
    }

    /** Seçilen dönem için borç üretir. Aynı dönem tekrar çalıştırılırsa mükerrer borç yazılmaz. */
    public function run(Request $request, Apartment $apartment, FinanceService $finance)
    {
        $data = $request->validate(['period' => ['required', 'date_format:Y-m']]);

        return $this->attempt(function () use ($apartment, $data, $finance, $request) {
            $r = $finance->generateDues($apartment, $data['period'], $request->user());
            $msg = "{$data['period']}: {$r['created']} borç oluşturuldu, {$r['skipped']} zaten vardı.";
            if ($r['skipped_no_area']) {
                $msg .= ' m² bilgisi olmadığı için atlanan: '.implode(', ', array_unique($r['skipped_no_area'])).'.';
            }
            if ($r['failed']) {
                $msg .= ' Hatalı: '.collect($r['failed'])->map(fn ($f) => "{$f['unit']} ({$f['message']})")->implode(', ');
            }

            return $msg;
        }, '');
    }
}
