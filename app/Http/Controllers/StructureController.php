<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Block;
use App\Models\Debt;
use App\Models\Unit;
use App\Models\UnitGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Bloklar, daireler (üniteler) ve daire grupları */
class StructureController extends Controller
{
    public function index(Apartment $apartment)
    {
        return view('site.units', [
            'apartment' => $apartment,
            'blocks' => $apartment->blocks()->withCount('units')->orderBy('name')->get(),
            'units' => $apartment->units()->with(['block', 'members'])->get()
                ->sortBy(fn (Unit $u) => [$u->block?->name, (int) $u->unit_no, $u->unit_no]),
            'groups' => $apartment->unitGroups()->withCount('units')->get(),
        ]);
    }

    public function storeBlock(Request $request, Apartment $apartment)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:30',
            Rule::unique('blocks')->where('apartment_id', $apartment->id)->whereNull('deleted_at')]]);
        $apartment->blocks()->create(['name' => mb_strtoupper($data['name'])]);

        return back()->with('status', 'Blok eklendi.');
    }

    public function destroyBlock(Apartment $apartment, Block $block)
    {
        if ($block->units()->exists()) {
            return back()->with('error', 'İçinde daire bulunan blok silinemez. Önce daireleri silin.');
        }
        $block->delete();

        return back()->with('status', 'Blok silindi.');
    }

    public function storeUnit(Request $request, Apartment $apartment)
    {
        $data = $this->validateUnit($request, $apartment);
        $apartment->units()->create($data);

        return back()->with('status', 'Daire eklendi.');
    }

    /** Bir bloğa 1..N arası daireleri tek seferde ekler (var olan numaralar atlanır) */
    public function bulkUnits(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'block_id' => ['required', Rule::exists('blocks', 'id')->where('apartment_id', $apartment->id)->whereNull('deleted_at')],
            'from' => ['required', 'integer', 'min:1', 'max:5000'],
            'to' => ['required', 'integer', 'gte:from', 'max:5000'],
            'gross_area' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);
        if ($data['to'] - $data['from'] > 500) {
            return back()->with('error', 'Tek seferde en fazla 500 daire eklenebilir.');
        }
        $existing = $apartment->units()->where('block_id', $data['block_id'])->pluck('unit_no')->all();
        $created = 0;
        for ($no = $data['from']; $no <= $data['to']; $no++) {
            if (in_array((string) $no, $existing, true)) {
                continue;
            }
            $apartment->units()->create(['block_id' => $data['block_id'], 'unit_no' => (string) $no,
                'gross_area' => $data['gross_area'] ?? null]);
            $created++;
        }

        return back()->with('status', "{$created} daire eklendi.");
    }

    public function updateUnit(Request $request, Apartment $apartment, Unit $unit)
    {
        $unit->update($this->validateUnit($request, $apartment, $unit));

        return back()->with('status', 'Daire güncellendi.');
    }

    public function destroyUnit(Apartment $apartment, Unit $unit)
    {
        if ($unit->debts()->where('status', Debt::PENDING)->exists()) {
            return back()->with('error', 'Açık borcu olan daire silinemez.');
        }
        if ((float) $unit->credit_balance > 0) {
            return back()->with('error', 'Avans bakiyesi bulunan daire silinemez. Önce avansı iade edin veya mahsup edin.');
        }
        if ($unit->members()->exists()) {
            return back()->with('error', 'Dairede kayıtlı üye var. Önce üyeyi silin.');
        }
        $unit->delete();

        return back()->with('status', 'Daire silindi.');
    }

    public function storeGroup(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'details' => ['nullable', 'string', 'max:255'],
            'unit_ids' => ['array'],
            'unit_ids.*' => [Rule::exists('units', 'id')->where('apartment_id', $apartment->id)],
        ]);
        $group = $apartment->unitGroups()->create(['name' => $data['name'], 'details' => $data['details'] ?? null]);
        $group->units()->sync($data['unit_ids'] ?? []);

        return back()->with('status', 'Grup oluşturuldu.');
    }

    public function destroyGroup(Apartment $apartment, UnitGroup $unitGroup)
    {
        $unitGroup->delete();

        return back()->with('status', 'Grup silindi.');
    }

    private function validateUnit(Request $request, Apartment $apartment, ?Unit $unit = null): array
    {
        $data = $request->validate([
            'block_id' => ['required', Rule::exists('blocks', 'id')->where('apartment_id', $apartment->id)->whereNull('deleted_at')],
            'unit_no' => ['required', 'string', 'max:20'],
            'floor' => ['nullable', 'string', 'max:10'],
            'usage_type' => ['required', Rule::in(['Konut', 'İşyeri', 'Depo', 'Otopark'])],
            'status' => ['required', Rule::in(['Dolu', 'Boş'])],
            'gross_area' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'land_share' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ]);

        $duplicate = $apartment->units()->where('block_id', $data['block_id'])->where('unit_no', $data['unit_no'])
            ->when($unit, fn ($q) => $q->whereKeyNot($unit->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['unit_no' => 'Bu blokta aynı numaralı daire zaten var.']);
        }

        return $data;
    }
}
