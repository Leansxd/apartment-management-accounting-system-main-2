<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Demirbaş kaydı ve amortisman takibi */
class AssetController extends Controller
{
    public function index(Apartment $apartment)
    {
        $assets = $apartment->assets()->with('vendor')->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")->orderBy('name')->get();
        $active = $assets->where('status', 'active');

        return view('site.ops.assets', [
            'apartment' => $apartment,
            'assets' => $assets,
            'vendors' => $apartment->vendors()->where('is_active', true)->orderBy('name')->get(),
            'cost' => (float) $active->sum('cost'),
            'depreciation' => $active->sum(fn (Asset $a) => $a->accumulatedDepreciation()) / 100,
            'book' => $active->sum(fn (Asset $a) => $a->bookValue()) / 100,
        ]);
    }

    public function store(Request $request, Apartment $apartment)
    {
        $apartment->assets()->create($this->validated($request, $apartment));

        return back()->with('status', 'Demirbaş eklendi.');
    }

    public function update(Request $request, Apartment $apartment, Asset $asset)
    {
        $asset->update($this->validated($request, $apartment));

        return back()->with('status', 'Demirbaş güncellendi.');
    }

    /** Hurdaya ayırma / satış: amortisman bu tarihte durur ve toplam değerlere girmez. */
    public function dispose(Request $request, Apartment $apartment, Asset $asset)
    {
        $data = $request->validate(['disposed_on' => ['required', 'date', 'after_or_equal:'.$asset->purchase_date->toDateString(), 'before_or_equal:today']]);
        $asset->update(['status' => 'disposed', 'disposed_on' => $data['disposed_on']]);

        return back()->with('status', 'Demirbaş elden çıkarıldı.');
    }

    public function destroy(Apartment $apartment, Asset $asset)
    {
        $asset->delete();

        return back()->with('status', 'Demirbaş silindi.');
    }

    private function validated(Request $request, Apartment $apartment): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(Asset::CATEGORIES)],
            'serial_no' => ['nullable', 'string', 'max:60'],
            'location' => ['nullable', 'string', 'max:80'],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'cost' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'useful_life_years' => ['required', 'integer', 'between:1,50'],
            'salvage_value' => ['nullable', 'numeric', 'min:0', 'lte:cost'],
            'vendor_id' => ['nullable', Rule::exists('vendors', 'id')->where('apartment_id', $apartment->id)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $data['salvage_value'] ??= 0;

        return $data;
    }
}
