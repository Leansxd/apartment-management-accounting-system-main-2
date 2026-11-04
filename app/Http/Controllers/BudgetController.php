<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Services\AccountingReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Yıllık bütçe planı ve gerçekleşen karşılaştırması */
class BudgetController extends Controller
{
    public function index(Request $request, Apartment $apartment, AccountingReportService $reports)
    {
        $year = $request->integer('year') ?: (int) now()->year;

        return view('site.accounting.budget', [
            'apartment' => $apartment,
            'year' => $year,
            'budget' => $reports->budget($apartment, $year),
            'years' => range((int) now()->year + 1, (int) now()->year - 3),
        ]);
    }

    /** Kategori başına yıllık tutarları kaydeder (boş / 0 = bütçe yok) */
    public function save(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'amounts' => ['array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);
        $valid = $apartment->categories()->pluck('id')->all();
        foreach ((array) ($data['amounts'] ?? []) as $categoryId => $amount) {
            if (! in_array((int) $categoryId, $valid, true)) {
                continue;
            }
            if ($amount === null || (float) $amount <= 0) {
                $apartment->budgetItems()->where('year', $data['year'])->where('category_id', $categoryId)->delete();
            } else {
                $apartment->budgetItems()->updateOrCreate(['year' => $data['year'], 'category_id' => $categoryId], ['amount' => $amount]);
            }
        }

        return redirect()->route('site.budget.index', [$apartment, 'year' => $data['year']])->with('status', 'Bütçe kaydedildi.');
    }

    /** Bir önceki yılın bütçesini kopyalar (mevcut kalemlerin üzerine yazmaz) */
    public function copy(Request $request, Apartment $apartment)
    {
        $data = $request->validate(['year' => ['required', 'integer', 'between:2000,2100']]);
        $copied = 0;
        foreach ($apartment->budgetItems()->where('year', $data['year'] - 1)->get() as $item) {
            $copied += $apartment->budgetItems()->firstOrCreate(['year' => $data['year'], 'category_id' => $item->category_id], ['amount' => $item->amount])->wasRecentlyCreated;
        }

        return back()->with('status', $copied ? "{$copied} kalem kopyalandı." : 'Kopyalanacak yeni kalem yok.');
    }
}
