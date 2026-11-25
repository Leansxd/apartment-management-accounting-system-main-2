<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Sitenin daire, sakin, borç, talep ve giderlerinde arama. Kimlik numarası gibi gizli alanlar aranmaz. */
class SiteSearchController extends Controller
{
    private const LIMIT = 20;

    public function __invoke(Request $request, Apartment $apartment): View
    {
        $term = trim((string) $request->query('q'));
        $results = ['units' => collect(), 'members' => collect(), 'debts' => collect(), 'tickets' => collect(), 'expenses' => collect()];

        if (mb_strlen($term) >= 2) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $results = [
                'units' => $this->units($apartment, $term, $like),
                'members' => $apartment->members()->with('unit.block')
                    ->where(fn ($q) => $q->where('full_name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like))
                    ->orderBy('full_name')->limit(self::LIMIT)->get(),
                'debts' => $apartment->debts()->with('unit.block')->where('description', 'like', $like)->orderByDesc('due_date')->limit(self::LIMIT)->get(),
                'tickets' => $apartment->tickets()->where(fn ($q) => $q->where('subject', 'like', $like)->orWhere('unit_no', 'like', $like))->latest('id')->limit(self::LIMIT)->get(),
                'expenses' => $apartment->expenses()->where(fn ($q) => $q->where('description', 'like', $like)->orWhere('invoice_no', 'like', $like))->latest('id')->limit(self::LIMIT)->get(),
            ];
        }

        return view('site.search', ['apartment' => $apartment, 'term' => $term] + $results);
    }

    /** "12", "A" ve "A-12" biçimlerini bulur. */
    private function units(Apartment $apartment, string $term, string $like)
    {
        [$block, $number] = array_pad(preg_split('/[-\s\/]+/', $term, 2), 2, null);

        return $apartment->units()->with('block', 'members')
            ->where(function ($q) use ($like, $block, $number) {
                $q->where('unit_no', 'like', $like)->orWhereHas('block', fn ($b) => $b->where('name', 'like', $like));
                if ($number !== null) {
                    $q->orWhere(fn ($both) => $both->where('unit_no', $number)->whereHas('block', fn ($b) => $b->where('name', $block)));
                }
            })->limit(self::LIMIT)->get();
    }
}
