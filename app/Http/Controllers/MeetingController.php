<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Decision;
use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Genel kurul / yönetim kurulu toplantıları: gündem, hazirun (katılım + vekil) ve toplantı yeter sayısı.
 * Kat Mülkiyeti Kanunu m.29: toplantı, kat maliklerinin sayı VE arsa payı çoğunluğuyla yapılır.
 */
class MeetingController extends Controller
{
    public function index(Apartment $apartment)
    {
        return view('site.comm.meetings', [
            'apartment' => $apartment,
            'meetings' => $apartment->meetings()->withCount('attendances')->orderByDesc('meets_at')->get(),
        ]);
    }

    public function store(Request $request, Apartment $apartment)
    {
        $meeting = $apartment->meetings()->create($this->data($request));

        return redirect()->route('site.meetings.show', [$apartment, $meeting])->with('status', 'Toplantı oluşturuldu.');
    }

    public function show(Apartment $apartment, Meeting $meeting)
    {
        $units = $apartment->units()->with('block', 'members')->get()->sortBy(fn ($u) => [$u->block?->name, (int) $u->unit_no])->values();
        $attendance = $meeting->attendances()->get()->keyBy('unit_id');

        return view('site.comm.meeting', [
            'apartment' => $apartment, 'meeting' => $meeting, 'units' => $units, 'attendance' => $attendance,
            'quorum' => $this->quorum($units, $attendance->keys()->all()),
            'decisions' => $meeting->decisions()->orderBy('decision_no')->get(),
            'nextNo' => $meeting->meets_at->year.'/'.str_pad((string) ($apartment->decisions()->whereYear('decided_on', $meeting->meets_at->year)->count() + 1), 3, '0', STR_PAD_LEFT),
        ]);
    }

    public function update(Request $request, Apartment $apartment, Meeting $meeting)
    {
        $meeting->update($this->data($request) + $request->validate([
            'status' => ['required', Rule::in(array_keys(Meeting::STATUSES))],
            'minutes' => ['nullable', 'string', 'max:20000'],
        ]));

        return back()->with('status', 'Toplantı güncellendi.');
    }

    /** Hazirun: işaretli daireler katılımcı sayılır; vekil ve katılımcı adı kaydedilir. */
    public function saveAttendance(Request $request, Apartment $apartment, Meeting $meeting)
    {
        $data = $request->validate([
            'present' => ['array'],
            'present.*' => [Rule::exists('units', 'id')->where('apartment_id', $apartment->id)],
            'name' => ['array'],
            'name.*' => ['nullable', 'string', 'max:120'],
            'proxy' => ['array'],
        ]);

        DB::transaction(function () use ($meeting, $data) {
            $meeting->attendances()->whereNotIn('unit_id', $data['present'] ?? [])->delete();
            foreach ($data['present'] ?? [] as $unitId) {
                $meeting->attendances()->updateOrCreate(['unit_id' => $unitId], [
                    'attendee_name' => $data['name'][$unitId] ?? null,
                    'is_proxy' => isset($data['proxy'][$unitId]),
                ]);
            }
        });

        return back()->with('status', 'Hazirun kaydedildi.');
    }

    public function storeDecision(Request $request, Apartment $apartment, Meeting $meeting)
    {
        $data = $request->validate([
            'decision_no' => ['required', 'string', 'max:20', Rule::unique('decisions')->where('apartment_id', $apartment->id)],
            'subject' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:10000'],
        ]);
        $apartment->decisions()->create($data + [
            'decided_on' => $meeting->meets_at->toDateString(), 'meeting_id' => $meeting->id,
            'attendees' => $meeting->attendances()->count().' daire katıldı',
        ]);

        return back()->with('status', 'Karar toplantıya eklendi.');
    }

    public function destroy(Apartment $apartment, Meeting $meeting)
    {
        $meeting->decisions()->update(['meeting_id' => null]);
        $meeting->delete();

        return redirect()->route('site.meetings.index', $apartment)->with('status', 'Toplantı silindi.');
    }

    /**
     * Toplantı yeter sayısı: katılan daire sayısı ve arsa payı toplamı, toplamın yarısından fazla olmalıdır.
     * Arsa payı girilmemişse yalnızca sayı çoğunluğu aranır.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Unit>  $units
     * @param  array<int, int>  $presentIds
     */
    public function quorum($units, array $presentIds): array
    {
        $present = $units->whereIn('id', $presentIds);
        $totalShare = (float) $units->sum('land_share');
        $presentShare = (float) $present->sum('land_share');
        $countOk = $units->count() > 0 && $present->count() * 2 > $units->count();
        $shareOk = $totalShare <= 0 || $presentShare * 2 > $totalShare;

        return [
            'count' => $present->count(), 'total' => $units->count(),
            'share' => $presentShare, 'total_share' => $totalShare,
            'count_ok' => $countOk, 'share_ok' => $shareOk, 'ok' => $countOk && $shareOk,
            'share_used' => $totalShare > 0,
        ];
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(Meeting::TYPES))],
            'meets_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:150'],
            'agenda' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
