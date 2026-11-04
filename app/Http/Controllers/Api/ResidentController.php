<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Apartment;
use App\Models\Debt;
use App\Models\Meeting;
use App\Models\Member;
use App\Models\Poll;
use App\Models\Reservation;
use App\Services\FinanceException;
use App\Services\ReservationService;
use App\Models\SiteNotification;
use App\Models\Ticket;
use App\Models\Unit;
use App\Services\ReportService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Mobil sakin uçları. Tüm sorgular, sakinin bağlı olduğu dairelerle (unit_ids) sınırlıdır. */
class ResidentController extends Controller
{
    /** Davet koduyla daireye bağlanma */
    public function redeem(Request $request)
    {
        $code = mb_strtoupper(trim((string) $request->validate([
            'invite_code' => ['required', 'string', 'min:6', 'max:64'],
        ])['invite_code']));
        $user = $request->user();

        $result = DB::transaction(function () use ($code, $user) {
            $member = Member::where('invite_code', $code)->lockForUpdate()->first();
            if (! $member) {
                return [404, ['message' => 'Davet kodu bulunamadı.']];
            }
            if ($member->user_id && $member->user_id !== $user->id) {
                return [409, ['message' => 'Bu davet kodu başka bir hesap tarafından kullanılmış.']];
            }
            $member->update(['user_id' => $user->id, 'activated_at' => $member->activated_at ?? now()]);
            $member->load('unit.block', 'apartment');

            return [200, [
                'success' => true,
                'apartment_id' => $member->apartment_id,
                'apartment_name' => $member->apartment->name,
                'unit_id' => $member->unit_id,
                'unit' => $member->unit->label(),
                'member_id' => $member->id,
            ]];
        });

        return response()->json($result[1], $result[0]);
    }

    public function debts(Request $request, Apartment $apartment)
    {
        return response()->json($apartment->debts()->whereIn('unit_id', $this->units($request))->with('unit.block')
            ->orderByDesc('due_date')->get()->map(fn (Debt $d) => [
                'id' => $d->id,
                'unit' => $d->unit->label(),
                'description' => $d->description,
                'category' => $d->category_name,
                'period' => $d->period,
                'due_date' => $d->due_date->toDateString(),
                'amount' => (float) $d->amount,
                'paid_amount' => (float) $d->paid_amount,
                'remaining_amount' => (float) $d->remaining_amount,
                'status' => $d->displayStatus(),
            ]));
    }

    public function payments(Request $request, Apartment $apartment)
    {
        return response()->json($apartment->cashTransactions()->effective()->whereIn('unit_id', $this->units($request))
            ->where('type', 'income')->latest('id')->limit(100)->get()->map(fn ($t) => [
                'id' => $t->id,
                'date' => $t->created_at->toIso8601String(),
                'description' => $t->description,
                'amount' => (float) $t->amount,
                'applied_to_debts' => (float) $t->applied_to_debts,
                'to_advance' => (float) $t->to_advance,
            ]));
    }

    public function statement(Request $request, Apartment $apartment, ReportService $reports)
    {
        $unitId = $request->integer('unit_id') ?: $this->units($request)[0];
        abort_unless(in_array($unitId, $this->units($request), true), 403);
        $statement = $reports->statement(Unit::findOrFail($unitId));
        $statement['rows'] = collect($statement['rows'])->map(fn ($r) => [
            'date' => $r['date']->toIso8601String(), 'description' => $r['description'],
            'debit' => $r['debit'], 'credit' => $r['credit'], 'balance' => $r['balance'],
        ]);

        return response()->json($statement);
    }

    public function announcements(Apartment $apartment)
    {
        return response()->json($apartment->announcements()->live()->latest('id')->limit(50)
            ->get(['id', 'title', 'content', 'priority', 'expires_on', 'created_at']));
    }

    /** Tüm siteye giden + sakinin dairelerine giden bildirimler, okundu bilgisiyle */
    public function notifications(Request $request, Apartment $apartment)
    {
        $userId = $request->user()->id;

        return response()->json($apartment->siteNotifications()
            ->where(fn ($q) => $q->whereNull('unit_id')->orWhereIn('unit_id', $this->units($request)))
            ->with(['readers' => fn ($q) => $q->whereKey($userId)])
            ->latest('id')->limit(100)->get()->map(fn (SiteNotification $n) => [
                'id' => $n->id, 'title' => $n->title, 'body' => $n->body, 'type' => $n->type,
                'created_at' => $n->created_at->toIso8601String(), 'is_read' => $n->readers->isNotEmpty(),
            ]));
    }

    public function markRead(Request $request, Apartment $apartment, int $notification)
    {
        $n = $apartment->siteNotifications()
            ->where(fn ($q) => $q->whereNull('unit_id')->orWhereIn('unit_id', $this->units($request)))
            ->findOrFail($notification);
        $n->readers()->syncWithoutDetaching([$request->user()->id => ['read_at' => now()]]);

        return response()->json(['success' => true]);
    }

    public function polls(Request $request, Apartment $apartment)
    {
        $units = $this->units($request);

        return response()->json($apartment->polls()->with(['options' => fn ($q) => $q->withCount('votes')])
            ->latest('id')->get()->map(fn (Poll $p) => [
                'id' => $p->id,
                'question' => $p->question,
                'due_date' => $p->due_date?->toDateString(),
                'is_open' => $p->isOpen(),
                'options' => $p->options->map(fn ($o) => ['id' => $o->id, 'text' => $o->text, 'votes' => $o->votes_count]),
                'my_vote_option_ids' => $p->votes()->whereIn('unit_id', $units)->pluck('poll_option_id'),
            ]));
    }

    /** Daire başına tek oy */
    public function vote(Request $request, Apartment $apartment, int $poll)
    {
        $poll = $apartment->polls()->findOrFail($poll);
        $units = $this->units($request);
        $data = $request->validate([
            'option_id' => ['required', Rule::exists('poll_options', 'id')->where('poll_id', $poll->id)],
            'unit_id' => ['nullable', Rule::in($units)],
        ]);
        if (! $poll->isOpen()) {
            return response()->json(['message' => 'Anket oylamaya kapalı.'], 422);
        }

        try {
            $poll->votes()->create(['poll_option_id' => $data['option_id'], 'unit_id' => $data['unit_id'] ?? $units[0],
                'user_id' => $request->user()->id]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Bu daire adına zaten oy kullanıldı.'], 409);
        }

        return response()->json(['success' => true], 201);
    }

    public function tickets(Request $request, Apartment $apartment)
    {
        return response()->json($apartment->tickets()->where('user_id', $request->user()->id)->latest('id')->limit(50)
            ->get(['id', 'subject', 'description', 'category', 'priority', 'status', 'response', 'unit_no', 'created_at']));
    }

    public function storeTicket(Request $request, Apartment $apartment)
    {
        $units = $this->units($request);
        $data = $request->validate([
            'unit_id' => ['nullable', Rule::in($units)],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'category' => ['required', Rule::in(Ticket::CATEGORIES)],
            'priority' => ['nullable', Rule::in(array_keys(Ticket::PRIORITIES))],
        ]);
        $unit = Unit::with('block')->findOrFail($data['unit_id'] ?? $units[0]);
        $ticket = $apartment->tickets()->create([
            'unit_id' => $unit->id, 'unit_no' => $unit->label(), 'user_id' => $request->user()->id,
            'subject' => $data['subject'], 'description' => $data['description'] ?? null,
            'category' => $data['category'], 'priority' => $data['priority'] ?? 'normal',
        ]);

        return response()->json($ticket, 201);
    }

    /** Rezervasyona açık ortak alanlar */
    public function areas(Apartment $apartment)
    {
        return response()->json($apartment->commonAreas()->where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'description', 'capacity', 'hourly_fee', 'open_time', 'close_time', 'requires_approval']));
    }

    /** Sakinin dairelerinin yakın geçmiş ve gelecek rezervasyonları */
    public function reservations(Request $request, Apartment $apartment)
    {
        return response()->json($apartment->reservations()->whereIn('unit_id', $this->units($request))->with('area:id,name')
            ->whereDate('date', '>=', today()->subDays(30))->orderByDesc('date')->get()->map(fn (Reservation $r) => [
                'id' => $r->id, 'area' => $r->area->name, 'date' => $r->date->toDateString(), 'start_time' => $r->start_time,
                'end_time' => $r->end_time, 'status' => $r->status, 'fee' => (float) $r->fee, 'can_cancel' => $r->isUpcoming(),
            ]));
    }

    public function storeReservation(Request $request, Apartment $apartment, ReservationService $service)
    {
        $units = $this->units($request);
        $data = $request->validate([
            'common_area_id' => ['required', Rule::exists('common_areas', 'id')->where('apartment_id', $apartment->id)],
            'unit_id' => ['nullable', Rule::in($units)],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $reservation = $service->book($apartment->commonAreas()->findOrFail($data['common_area_id']),
                Unit::with('block')->findOrFail($data['unit_id'] ?? $units[0]), $data['date'], $data['start_time'], $data['end_time'],
                $request->user(), $data['note'] ?? null);
        } catch (FinanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $reservation->id, 'status' => $reservation->status, 'fee' => (float) $reservation->fee], 201);
    }

    public function cancelReservation(Request $request, Apartment $apartment, int $reservation, ReservationService $service)
    {
        $reservation = $apartment->reservations()->whereIn('unit_id', $this->units($request))->findOrFail($reservation);
        if (! $reservation->isUpcoming()) {
            return response()->json(['message' => 'Başlamış veya kapanmış rezervasyon iptal edilemez.'], 422);
        }
        try {
            $service->cancel($reservation, $request->user());
        } catch (FinanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true]);
    }

    public function meetings(Apartment $apartment)
    {
        return response()->json($apartment->meetings()->where('status', '!=', 'cancelled')->orderByDesc('meets_at')->limit(30)->get()->map(fn ($m) => [
            'id' => $m->id, 'title' => $m->title, 'type' => Meeting::TYPES[$m->type], 'meets_at' => $m->meets_at->toIso8601String(),
            'location' => $m->location, 'agenda' => $m->agenda, 'status' => $m->status,
        ]));
    }

    /** @return array<int,int> */
    private function units(Request $request): array
    {
        return $request->attributes->get('unit_ids');
    }
}
