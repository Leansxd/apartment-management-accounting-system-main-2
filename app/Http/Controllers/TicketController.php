<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Destek talepleri / arıza bildirimleri (panelden veya mobil uygulamadan açılır) */
class TicketController extends Controller
{
    public function index(Request $request, Apartment $apartment)
    {
        $status = $request->query('status');

        return view('site.ops.tickets', [
            'apartment' => $apartment,
            'tickets' => $apartment->tickets()->with('unit.block', 'user')
                ->when(array_key_exists((string) $status, Ticket::STATUSES), fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(40)->withQueryString(),
            'counts' => $apartment->tickets()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'units' => $apartment->units()->with('block')->get()->sortBy(fn ($u) => [$u->block?->name, (int) $u->unit_no]),
            'status' => $status,
        ]);
    }

    public function store(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'unit_id' => ['nullable', Rule::exists('units', 'id')->where('apartment_id', $apartment->id)],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'category' => ['required', Rule::in(Ticket::CATEGORIES)],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
        ]);
        $unit = isset($data['unit_id']) ? $apartment->units()->with('block')->find($data['unit_id']) : null;
        $apartment->tickets()->create($data + ['unit_no' => $unit?->label()]);

        return back()->with('status', 'Talep oluşturuldu.');
    }

    public function update(Request $request, Apartment $apartment, Ticket $ticket)
    {
        $ticket->update($request->validate([
            'status' => ['required', Rule::in(array_keys(Ticket::STATUSES))],
            'response' => ['nullable', 'string', 'max:3000'],
        ]));

        return back()->with('status', 'Talep güncellendi.');
    }
}
