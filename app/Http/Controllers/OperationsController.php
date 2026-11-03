<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\InventoryItem;
use App\Models\Meter;
use App\Models\Staff;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Görevler, personel, stok/envanter ve sayaçlar */
class OperationsController extends Controller
{
    // ------------------------------------------------------------------ GÖREVLER

    public function tasks(Request $request, Apartment $apartment)
    {
        $status = $request->query('status');

        return view('site.ops.tasks', [
            'apartment' => $apartment,
            'tasks' => $apartment->tasks()->with('staff')
                ->when(array_key_exists((string) $status, Task::STATUSES), fn ($q) => $q->where('status', $status))
                ->orderByRaw("CASE status WHEN 'completed' THEN 1 ELSE 0 END")->orderBy('due_date')->paginate(50)->withQueryString(),
            'staff' => $apartment->staff()->where('status', 'Aktif')->orderBy('name')->get(),
            'status' => $status,
        ]);
    }

    public function storeTask(Request $request, Apartment $apartment)
    {
        $apartment->tasks()->create($request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'staff_id' => ['nullable', Rule::exists('staff', 'id')->where('apartment_id', $apartment->id)],
            'priority' => ['required', Rule::in(array_keys(Task::PRIORITIES))],
            'category' => ['required', Rule::in(array_keys(Task::CATEGORIES))],
            'due_date' => ['nullable', 'date'],
        ]));

        return back()->with('status', 'Görev oluşturuldu.');
    }

    public function updateTask(Request $request, Apartment $apartment, Task $task)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['completed_at'] = $data['status'] === 'completed' ? ($task->completed_at ?? now()) : null;
        $task->update($data);

        return back()->with('status', 'Görev güncellendi.');
    }

    public function destroyTask(Apartment $apartment, Task $task)
    {
        $task->delete();

        return back()->with('status', 'Görev silindi.');
    }

    // ------------------------------------------------------------------ PERSONEL

    public function staff(Apartment $apartment)
    {
        return view('site.ops.staff', [
            'apartment' => $apartment,
            'staff' => $apartment->staff()->withCount(['tasks' => fn ($q) => $q->where('status', '!=', 'completed')])->orderBy('name')->get(),
        ]);
    }

    public function storeStaff(Request $request, Apartment $apartment)
    {
        $apartment->staff()->create($this->staffData($request));

        return back()->with('status', 'Personel eklendi.');
    }

    public function updateStaff(Request $request, Apartment $apartment, Staff $staff)
    {
        $staff->update($this->staffData($request));

        return back()->with('status', 'Personel güncellendi.');
    }

    public function destroyStaff(Apartment $apartment, Staff $staff)
    {
        $staff->delete();

        return back()->with('status', 'Personel silindi.');
    }

    private function staffData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::in(Staff::ROLES)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'salary' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'start_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['Aktif', 'İzinde', 'Ayrıldı'])],
        ]);
    }

    // ------------------------------------------------------------------ STOK

    public function inventory(Apartment $apartment)
    {
        return view('site.ops.inventory', [
            'apartment' => $apartment,
            'items' => $apartment->inventoryItems()->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function storeItem(Request $request, Apartment $apartment)
    {
        $apartment->inventoryItems()->create($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(InventoryItem::CATEGORIES)],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'location' => ['required', 'string', 'max:60'],
        ]));

        return back()->with('status', 'Stok kalemi eklendi.');
    }

    /** Stok girişi / çıkışı. Miktar eksiye düşemez (atomik güncelleme). */
    public function adjustItem(Request $request, Apartment $apartment, InventoryItem $inventoryItem)
    {
        $delta = (int) $request->validate(['delta' => ['required', 'integer', 'not_in:0', 'between:-100000,100000']])['delta'];
        $updated = InventoryItem::whereKey($inventoryItem->id)
            ->where('quantity', '>=', max(0, -$delta))
            ->update(['quantity' => DB::raw('quantity + '.$delta), 'updated_at' => now()]);

        return $updated
            ? back()->with('status', 'Stok güncellendi.')
            : back()->with('error', 'Stok miktarı yetersiz.');
    }

    public function destroyItem(Apartment $apartment, InventoryItem $inventoryItem)
    {
        $inventoryItem->delete();

        return back()->with('status', 'Stok kalemi silindi.');
    }

    // ------------------------------------------------------------------ SAYAÇLAR

    public function meters(Apartment $apartment)
    {
        return view('site.ops.meters', [
            'apartment' => $apartment,
            'meters' => $apartment->meters()->with(['unit.block', 'readings' => fn ($q) => $q->latest('read_on')->limit(6)])
                ->orderBy('type')->orderBy('name')->get(),
            'units' => $apartment->units()->with('block')->get()->sortBy(fn ($u) => [$u->block?->name, (int) $u->unit_no]),
        ]);
    }

    public function storeMeter(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(Meter::TYPES)],
            'unit_id' => ['nullable', Rule::exists('units', 'id')->where('apartment_id', $apartment->id)],
            'serial_no' => ['nullable', 'string', 'max:60'],
            'initial_value' => ['required', 'numeric', 'min:0'],
        ]);
        $apartment->meters()->create($data + ['last_value' => $data['initial_value']]);

        return back()->with('status', 'Sayaç eklendi.');
    }

    /** Okuma kaydı: tüketim = yeni değer - son değer. Değer geri gidemez. */
    public function storeReading(Request $request, Apartment $apartment, Meter $meter)
    {
        $data = $request->validate([
            'value' => ['required', 'numeric', 'min:0'],
            'read_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        return DB::transaction(function () use ($meter, $data) {
            $meter = Meter::whereKey($meter->id)->lockForUpdate()->first();
            if ((float) $data['value'] < (float) $meter->last_value) {
                return back()->withInput()->with('error', "Okuma değeri son değerden ({$meter->last_value}) küçük olamaz.");
            }
            $meter->readings()->create($data + ['consumption' => round($data['value'] - $meter->last_value, 3)]);
            $meter->update(['last_value' => $data['value']]);

            return back()->with('status', 'Okuma kaydedildi.');
        });
    }

    public function destroyMeter(Apartment $apartment, Meter $meter)
    {
        $meter->delete();

        return back()->with('status', 'Sayaç silindi.');
    }
}
