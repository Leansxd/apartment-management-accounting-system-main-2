<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\CommonArea;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Ortak alanlar (tanım) ve rezervasyonlar */
class ReservationController extends Controller
{
    public function __construct(private ReservationService $service)
    {
    }

    public function index(Request $request, Apartment $apartment)
    {
        $status = array_key_exists((string) $request->query('status'), Reservation::STATUSES) ? $request->query('status') : null;

        return view('site.ops.reservations', [
            'apartment' => $apartment,
            'areas' => $apartment->commonAreas()->orderBy('name')->get(),
            'reservations' => $apartment->reservations()->with('area', 'unit.block', 'user')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->orderByDesc('date')->orderByDesc('start_time')->paginate(30)->withQueryString(),
            'units' => $apartment->units()->with('block')->get()->sortBy(fn ($u) => [$u->block?->name, (int) $u->unit_no])->values(),
            'pending' => $apartment->reservations()->where('status', 'pending')->count(),
            'status' => $status,
        ]);
    }

    public function store(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'common_area_id' => ['required', Rule::exists('common_areas', 'id')->where('apartment_id', $apartment->id)],
            'unit_id' => ['required', Rule::exists('units', 'id')->where('apartment_id', $apartment->id)],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->attempt(fn () => $this->service->book(
            $apartment->commonAreas()->findOrFail($data['common_area_id']), $apartment->units()->with('block')->findOrFail($data['unit_id']),
            $data['date'], $data['start_time'], $data['end_time'], $request->user(), $data['note'] ?? null, forceApprove: true,
        ), 'Rezervasyon oluşturuldu ve onaylandı.');
    }

    public function approve(Request $request, Apartment $apartment, Reservation $reservation)
    {
        return $this->attempt(fn () => $this->service->approve($reservation, $request->user()), 'Rezervasyon onaylandı.');
    }

    public function reject(Request $request, Apartment $apartment, Reservation $reservation)
    {
        return $this->attempt(fn () => $this->service->reject($reservation, $request->user()), 'Rezervasyon reddedildi.');
    }

    public function cancel(Request $request, Apartment $apartment, Reservation $reservation)
    {
        return $this->attempt(fn () => $this->service->cancel($reservation, $request->user()), 'Rezervasyon iptal edildi.');
    }

    public function storeArea(Request $request, Apartment $apartment)
    {
        $apartment->commonAreas()->create($this->areaData($request));

        return back()->with('status', 'Ortak alan eklendi.');
    }

    public function updateArea(Request $request, Apartment $apartment, CommonArea $commonArea)
    {
        $commonArea->update($this->areaData($request));

        return back()->with('status', 'Ortak alan güncellendi.');
    }

    /** Geçmiş rezervasyonu olan alan silinmez, pasife alınır. */
    public function destroyArea(Apartment $apartment, CommonArea $commonArea)
    {
        if ($commonArea->reservations()->exists()) {
            $commonArea->update(['is_active' => false]);

            return back()->with('status', 'Alanın rezervasyon geçmişi var; silinmek yerine pasife alındı.');
        }
        $commonArea->delete();

        return back()->with('status', 'Ortak alan silindi.');
    }

    private function areaData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:200'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'hourly_fee' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'open_time' => ['required', 'date_format:H:i'],
            'close_time' => ['required', 'date_format:H:i', 'after:open_time'],
        ]);
        $data['hourly_fee'] ??= 0;

        return $data + ['requires_approval' => $request->boolean('requires_approval'), 'is_active' => $request->boolean('is_active')];
    }
}
