<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\InventoryItem;
use App\Models\Meter;
use App\Models\MeterBill;
use App\Models\MeterReading;
use App\Models\Staff;
use App\Models\Task;
use App\Services\AuditTrail;
use Carbon\Carbon;
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
                ->when($request->query('priority'), fn ($q, $priority) => $q->where('priority', $priority))
                ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
                ->when($request->integer('staff_id'), fn ($q, $staff) => $q->where('staff_id', $staff))
                ->when($request->boolean('overdue'), fn ($q) => $q->where('status', '!=', 'completed')->whereDate('due_date', '<', today()))
                ->orderByRaw("CASE status WHEN 'completed' THEN 1 ELSE 0 END")->orderBy('due_date')->paginate(10)->withQueryString(),
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

    public function staff(Request $request, Apartment $apartment)
    {
        return view('site.ops.staff', [
            'apartment' => $apartment,
            'staff' => $apartment->staff()->withCount(['tasks' => fn ($q) => $q->where('status', '!=', 'completed')])
                ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
                ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
                ->orderBy('name')->get(),
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

    public function inventory(Request $request, Apartment $apartment)
    {
        return view('site.ops.inventory', [
            'apartment' => $apartment,
            'items' => $apartment->inventoryItems()
                ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
                ->when($request->boolean('low'), fn ($q) => $q->whereColumn('quantity', '<=', 'min_stock'))
                ->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function storeItem(Request $request, Apartment $apartment)
    {
        $apartment->inventoryItems()->create($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(InventoryItem::CATEGORIES)],
            'quantity' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'unit' => ['required', 'string', 'max:20'],
            'min_stock' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'location' => ['required', 'string', 'max:60'],
        ]));

        return back()->with('status', 'Stok kalemi eklendi.');
    }

    /** Kalem bilgileri; miktar yalnızca stok girişi / çıkışıyla değişir. */
    public function updateItem(Request $request, Apartment $apartment, InventoryItem $inventoryItem)
    {
        $inventoryItem->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(InventoryItem::CATEGORIES)],
            'unit' => ['required', 'string', 'max:20'],
            'min_stock' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'location' => ['required', 'string', 'max:60'],
        ]));

        return back()->with('status', 'Stok kalemi güncellendi.');
    }

    /** Stok girişi / çıkışı. Miktar eksiye düşemez (atomik güncelleme). */
    public function adjustItem(Request $request, Apartment $apartment, InventoryItem $inventoryItem, AuditTrail $audit)
    {
        $delta = (int) $request->validate(['delta' => ['required', 'integer', 'not_in:0', 'between:-100000,100000']])['delta'];
        $updated = InventoryItem::whereKey($inventoryItem->id)
            ->where('quantity', '>=', max(0, -$delta))
            ->update(['quantity' => DB::raw('quantity + '.$delta), 'updated_at' => now()]);

        if ($updated) {
            $audit->log($apartment->id, 'ADJUST_STOCK', 'OPERATIONS', "inventory_item:{$inventoryItem->id}", ['name' => $inventoryItem->name, 'delta' => $delta]);
        }

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

    public function meters(Request $request, Apartment $apartment)
    {
        return view('site.ops.meters', [
            'apartment' => $apartment,
            'meters' => $apartment->meters()->with(['unit.block', 'readings' => fn ($q) => $q->latest('read_on')->limit(6)])
                ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
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
            'initial_value' => ['required', 'numeric', 'min:0', 'max:99999999999'],
        ]);
        $apartment->meters()->create($data + ['last_value' => $data['initial_value']]);

        return back()->with('status', 'Sayaç eklendi.');
    }

    /** Okuma kaydı: tüketim = yeni değer - son değer. Değer geri gidemez. */
    public function storeReading(Request $request, Apartment $apartment, Meter $meter)
    {
        $data = $request->validate([
            'value' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'read_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        return DB::transaction(function () use ($meter, $data) {
            $meter = Meter::whereKey($meter->id)->lockForUpdate()->first();
            if ((float) $data['value'] < (float) $meter->last_value) {
                return back()->withInput()->with('error', "Okuma değeri son değerden ({$meter->last_value}) küçük olamaz.");
            }
            // Tüketim okuma tarihine yazılır; önceki okumadan eski tarih, tüketimi yanlış döneme taşır
            $lastReadOn = $meter->readings()->max('read_on');
            if ($lastReadOn && $data['read_on'] < substr((string) $lastReadOn, 0, 10)) {
                return back()->withInput()->with('error', 'Okuma tarihi son okumadan ('.Carbon::parse($lastReadOn)->format('d.m.Y').') önce olamaz.');
            }
            $meter->readings()->create($data + ['consumption' => round($data['value'] - $meter->last_value, 3)]);
            $meter->update(['last_value' => $data['value']]);

            return back()->with('status', 'Okuma kaydedildi.');
        });
    }

    public function updateMeter(Request $request, Apartment $apartment, Meter $meter)
    {
        $meter->update($request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(Meter::TYPES)],
            'unit_id' => ['nullable', Rule::exists('units', 'id')->where('apartment_id', $apartment->id)],
            'serial_no' => ['nullable', 'string', 'max:60'],
        ]));

        return back()->with('status', 'Sayaç güncellendi.');
    }

    /** Tüketim zinciri bozulmasın diye yalnızca son okuma düzeltilebilir. */
    public function updateReading(Request $request, Apartment $apartment, Meter $meter, MeterReading $reading)
    {
        $data = $request->validate([
            'value' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'read_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        return DB::transaction(function () use ($meter, $reading, $data) {
            $meter = Meter::whereKey($meter->id)->lockForUpdate()->first();
            if ($error = $this->readingEditError($meter, $reading, $data['read_on'])) {
                return back()->withInput()->with('error', $error);
            }
            $previous = $this->previousReadingValue($meter, $reading);
            if ((float) $data['value'] < $previous) {
                return back()->withInput()->with('error', "Okuma değeri önceki değerden ({$previous}) küçük olamaz.");
            }
            $reading->update($data + ['consumption' => round($data['value'] - $previous, 3)]);
            $meter->update(['last_value' => $data['value']]);

            return back()->with('status', 'Okuma düzeltildi.');
        });
    }

    public function destroyReading(Apartment $apartment, Meter $meter, MeterReading $reading)
    {
        return DB::transaction(function () use ($meter, $reading) {
            $meter = Meter::whereKey($meter->id)->lockForUpdate()->first();
            if ($error = $this->readingEditError($meter, $reading, $reading->read_on->toDateString())) {
                return back()->with('error', $error);
            }
            $meter->update(['last_value' => $this->previousReadingValue($meter, $reading)]);
            $reading->delete();

            return back()->with('status', 'Okuma silindi.');
        });
    }

    private function readingEditError(Meter $meter, MeterReading $reading, string $newDate): ?string
    {
        if ($meter->readings()->max('id') !== $reading->id) {
            return 'Yalnızca sayacın son okuması değiştirilebilir.';
        }
        $isBilled = MeterBill::where('apartment_id', $meter->apartment_id)->where('meter_type', $meter->type)->where('method', 'consumption')
            ->where(fn ($q) => $q->where(fn ($d) => $d->whereDate('period_from', '<=', $reading->read_on)->whereDate('period_to', '>=', $reading->read_on))
                ->orWhere(fn ($d) => $d->whereDate('period_from', '<=', $newDate)->whereDate('period_to', '>=', $newDate)))
            ->exists();

        return $isBilled ? 'Bu okuma tüketime göre faturalandırılmış; önce faturayı iptal edin.' : null;
    }

    private function previousReadingValue(Meter $meter, MeterReading $reading): float
    {
        return (float) ($meter->readings()->whereKeyNot($reading->id)->orderByDesc('id')->value('value') ?? $meter->initial_value);
    }

    public function destroyMeter(Apartment $apartment, Meter $meter)
    {
        $meter->delete();

        return back()->with('status', 'Sayaç silindi.');
    }
}
