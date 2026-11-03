<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\SecurityEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Güvenlik & kargo: kargo/paket, otopark/araç ve ziyaretçi kayıtları */
class SecurityController extends Controller
{
    public function index(Request $request, Apartment $apartment)
    {
        $kind = array_key_exists((string) $request->query('kind'), SecurityEntry::KINDS) ? $request->query('kind') : 'cargo';

        return view('site.ops.security', [
            'apartment' => $apartment,
            'kind' => $kind,
            'entries' => $apartment->securityEntries()->where('kind', $kind)
                ->when($request->boolean('open'), fn ($q) => $q->whereIn('status', ['beklemede', 'aktif']))
                ->latest('id')->paginate(40)->withQueryString(),
            'openCounts' => $apartment->securityEntries()->whereIn('status', ['beklemede', 'aktif'])
                ->selectRaw('kind, COUNT(*) c')->groupBy('kind')->pluck('c', 'kind'),
        ]);
    }

    public function store(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(SecurityEntry::KINDS))],
            'unit_no' => ['nullable', 'string', 'max:20'],
            'full_name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:60'],
            'tracking_no' => ['nullable', 'string', 'max:60'],
            'plate' => ['nullable', 'string', 'max:15', 'required_if:kind,parking'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['plate'] = isset($data['plate']) ? mb_strtoupper($data['plate']) : null;
        $data['status'] = array_key_first(SecurityEntry::STATUSES[$data['kind']]);
        $apartment->securityEntries()->create($data);

        return redirect()->route('site.security.index', [$apartment, 'kind' => $data['kind']])->with('status', 'Kayıt eklendi.');
    }

    public function update(Request $request, Apartment $apartment, SecurityEntry $securityEntry)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(SecurityEntry::STATUSES[$securityEntry->kind]))],
        ]);
        $isOpen = in_array($data['status'], ['beklemede', 'aktif'], true);
        $securityEntry->update($data + ['closed_at' => $isOpen ? null : now()]);

        return back()->with('status', 'Durum güncellendi.');
    }

    public function destroy(Apartment $apartment, SecurityEntry $securityEntry)
    {
        $securityEntry->delete();

        return back()->with('status', 'Kayıt silindi.');
    }
}
