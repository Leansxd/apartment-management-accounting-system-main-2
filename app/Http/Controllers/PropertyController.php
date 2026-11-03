<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Emlak portföyü: ilanlar. Her admin ilanları görür; yalnızca ilanı ekleyen veya süper admin düzenler. */
class PropertyController extends Controller
{
    public const FEATURES = ['Havuz', 'Otopark', 'Asansör', 'Güvenlik', 'Spor Salonu', 'Sauna', 'Jeneratör', 'Bahçe',
        'Klima', 'Doğalgaz', 'Deniz Manzarası', 'Çocuk Parkı'];
    public const FACADES = ['Kuzey', 'Güney', 'Doğu', 'Batı'];
    public const DISTANCES = ['Deniz', 'Merkez', 'Havalimanı', 'Hastane', 'Okul'];

    public function index(Request $request)
    {
        $category = $request->query('category');

        return view('properties.index', [
            'properties' => Property::with('creator')
                ->when(in_array($category, Property::CATEGORIES, true), fn ($q) => $q->where('category', $category))
                ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('location', 'like', "%{$s}%")))
                ->latest()->paginate(24)->withQueryString(),
            'category' => $category,
        ]);
    }

    public function create()
    {
        return view('properties.form', ['property' => new Property(['category' => 'Apartment', 'is_credit_eligible' => true])]);
    }

    public function store(Request $request)
    {
        $property = new Property($this->validated($request) + ['created_by' => $request->user()->id]);
        $property->images = $this->storeImages($request, []);
        $property->save();

        return redirect()->route('properties.index')->with('status', 'İlan eklendi.');
    }

    public function edit(Request $request, Property $property)
    {
        abort_unless($property->canBeManagedBy($request->user()), 403);

        return view('properties.form', ['property' => $property]);
    }

    public function update(Request $request, Property $property)
    {
        abort_unless($property->canBeManagedBy($request->user()), 403);
        $property->fill($this->validated($request));
        $keep = array_values(array_intersect($property->images ?? [], (array) $request->input('keep_images', [])));
        foreach (array_diff($property->images ?? [], $keep) as $removed) {
            Storage::disk('public')->delete($removed);
        }
        $property->images = $this->storeImages($request, $keep);
        $property->save();

        return redirect()->route('properties.index')->with('status', 'İlan güncellendi.');
    }

    public function destroy(Request $request, Property $property)
    {
        abort_unless($property->canBeManagedBy($request->user()), 403);
        Storage::disk('public')->delete($property->images ?? []);
        $property->delete();

        return redirect()->route('properties.index')->with('status', 'İlan silindi.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:150'],
            'category' => ['required', Rule::in(Property::CATEGORIES)],
            'status' => ['required', Rule::in(array_keys(Property::STATUSES))],
            'description' => ['nullable', 'string', 'max:10000'],
            'video_url' => ['nullable', 'url', 'max:255'],
            'agent_name' => ['nullable', 'string', 'max:120'],
            'agent_phone' => ['nullable', 'string', 'max:30'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'balconies' => ['nullable', 'integer', 'min:0', 'max:50'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'floor' => ['nullable', 'string', 'max:10'],
            'total_floors' => ['nullable', 'integer', 'min:0', 'max:200'],
            'year_built' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 5)],
            'features' => ['array'],
            'features.*' => [Rule::in(self::FEATURES)],
            'facades' => ['array'],
            'facades.*' => [Rule::in(self::FACADES)],
            'distances' => ['array'],
            'distances.*' => ['nullable', 'string', 'max:30'],
            'images' => ['array', 'max:10'],
            'images.*' => ['image', 'max:5120'],
        ]);
        foreach (['is_furnished', 'is_citizenship', 'is_residence_permit', 'is_credit_eligible'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        $data['features'] = $data['features'] ?? [];
        $data['facades'] = $data['facades'] ?? [];
        $data['distances'] = array_filter(array_intersect_key($data['distances'] ?? [], array_flip(self::DISTANCES)));
        unset($data['images']);

        return $data;
    }

    /** Yüklenen görseller storage/app/public/properties altına kaydedilir */
    private function storeImages(Request $request, array $existing): array
    {
        foreach ($request->file('images', []) as $file) {
            $existing[] = $file->store('properties', 'public');
        }

        return array_slice($existing, 0, 10);
    }
}
