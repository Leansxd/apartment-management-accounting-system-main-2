<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Services\AccountingReportService;
use App\Support\SpreadsheetExport;
use Illuminate\Http\Request;

/** Yıllık bütçe planı ve gerçekleşen karşılaştırması */
class BudgetController extends Controller
{
    public function index(Request $request, Apartment $apartment, AccountingReportService $reports)
    {
        $year = $request->integer('year') ?: (int) now()->year;
        $years = range((int) now()->year + 1, (int) now()->year - 5);

        // Karşılaştırma yılları seçilen yıldan öncekilerdir. Form gönderildiyse (cmp) seçim aynen alınır, yoksa bir önceki yıl varsayılandır.
        $compare = $request->boolean('cmp') ? collect((array) $request->query('compare', [])) : collect([$year - 1]);
        $compare = $compare->map(fn ($y) => (int) $y)->filter(fn (int $y) => $y >= 2000 && $y < $year)->unique()->sortDesc()->take(4)->values()->all();

        $budget = $reports->budget($apartment, $year);
        $comparison = $reports->budgetComparison($apartment, $year, $compare);

        if (in_array($request->query('format'), ['xlsx', 'csv'], true)) {
            $columns = ['type' => 'Tür', 'category' => 'Kategori', 'planned' => 'Bütçe', 'accrued' => 'Tahakkuk', 'actual' => 'Gerçekleşen', 'diff' => 'Fark', 'pct' => 'Gerçekleşme'];
            foreach ($compare as $y) {
                $columns["h{$y}"] = "{$y} Gerçekleşen";
            }
            if ($compare !== []) {
                $columns['change'] = "{$compare[0]}'e göre fark";
                $columns['change_pct'] = "{$compare[0]}'e göre %";
            }

            $rows = collect(['income' => 'Gelir', 'expense' => 'Gider'])->flatMap(fn ($label, $type) => $comparison['rows'][$type]->map(function (array $r) use ($label, $compare) {
                $row = [
                    'type' => $label, 'category' => $r['category']->name, 'planned' => (float) $r['planned'],
                    'accrued' => $r['accrued'] === null ? '' : (float) $r['accrued'], 'actual' => (float) $r['actual'],
                    'diff' => $r['planned'] ? (float) $r['diff'] : '', 'pct' => $r['pct'] === null ? '' : '%'.$r['pct'],
                ];
                foreach ($compare as $y) {
                    $row["h{$y}"] = (float) $r['history'][$y]['actual'];
                }

                return $row + ($compare === [] ? [] : [
                    'change' => (float) $r['change'], 'change_pct' => $r['change_pct'] === null ? '' : '%'.$r['change_pct'],
                ]);
            }));

            return SpreadsheetExport::download("{$apartment->code}-butce-{$year}.xlsx", $columns, $rows, null, [
                'title' => "Bütçe ve Gerçekleşen {$year}",
                'period' => $compare === [] ? null : 'Karşılaştırma yılları: '.implode(', ', $compare),
            ]);
        }

        return view('site.accounting.budget', [
            'apartment' => $apartment,
            'year' => $year,
            'budget' => $budget,
            'comparison' => $comparison,
            'compare' => $compare,
            'years' => $years,
            'insights' => $reports->budgetInsights($comparison, $year, $compare),
            'monthly' => $reports->budgetMonthly($apartment, $year),
            'previousMonthly' => $compare === [] ? null : $reports->budgetMonthly($apartment, $compare[0]),
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
