<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Services\FinanceException;
use App\Services\ReportService;
use App\Services\ReservationService;
use App\Services\ResidentService;
use App\Services\TicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Web sakin paneli. Site altındaki tüm sorgular, sakinin bağlı olduğu dairelerle (unit_ids) sınırlıdır. */
class ResidentPortalController extends Controller
{
    public function showJoin(): View
    {
        return view('portal.join');
    }

    /** Davet koduyla hesap açar. Kod geçersizse hesap da oluşmaz. */
    public function join(Request $request, ResidentService $residents): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'invite_code' => ['required', 'string', 'min:6', 'max:64'],
        ], ['email.unique' => 'Bu e-posta kayıtlı. Giriş yapıp davet kodunuzu panelden girebilirsiniz.']);

        try {
            $user = DB::transaction(function () use ($data, $residents) {
                $user = User::create(collect($data)->only(['name', 'email', 'phone', 'password'])->all());
                $residents->redeemInvite($data['invite_code'], $user);

                return $user;
            });
        } catch (FinanceException $e) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['invite_code' => $e->getMessage()]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.home')->with('status', 'Hesabınız oluşturuldu.');
    }

    /** Tek sitesi olan sakin doğrudan o sitenin özetine gider */
    public function home(Request $request): View|RedirectResponse
    {
        $apartments = Apartment::whereIn('id', $request->user()->memberships()->select('apartment_id'))->orderBy('name')->get();
        if ($apartments->count() === 1) {
            return redirect()->route('portal.overview', $apartments->first());
        }

        return view('portal.home', ['apartments' => $apartments]);
    }

    public function redeem(Request $request, ResidentService $residents): RedirectResponse
    {
        $code = $request->validate(['invite_code' => ['required', 'string', 'min:6', 'max:64']])['invite_code'];

        try {
            $member = $residents->redeemInvite($code, $request->user());
        } catch (FinanceException $e) {
            return back()->withErrors(['invite_code' => $e->getMessage()]);
        }

        return redirect()->route('portal.overview', $member->apartment_id)->with('status', 'Daireniz hesabınıza bağlandı.');
    }

    public function account(): View
    {
        return view('portal.account');
    }

    public function overview(Request $request, Apartment $apartment): View
    {
        $debts = $this->residentRecords($request, $apartment->debts())->where('status', 'pending');

        return view('portal.overview', [
            'apartment' => $apartment,
            'openTotal' => (clone $debts)->sum('remaining_amount'),
            'overdueTotal' => (clone $debts)->whereDate('due_date', '<', today())->sum('remaining_amount'),
            'nextDebts' => (clone $debts)->with('unit.block')->orderBy('due_date')->limit(5)->get(),
            'announcements' => $apartment->announcements()->live()->latest('id')->limit(5)->get(),
            'notifications' => $this->notificationsQuery($request, $apartment)->latest('id')->limit(5)->get(),
        ]);
    }

    public function debts(Request $request, Apartment $apartment, ReportService $reports): View
    {
        $unitIds = $this->unitIds($request);
        $unitId = $request->integer('unit_id') ?: $unitIds[0];
        abort_unless(in_array($unitId, $unitIds, true), 403);

        return view('portal.debts', [
            'apartment' => $apartment,
            'units' => Unit::with('block')->whereIn('id', $unitIds)->get(),
            'unitId' => $unitId,
            'debts' => $apartment->debts()->where('unit_id', $unitId)->orderByDesc('due_date')->paginate(10)->withQueryString(),
            'statement' => $reports->statement(Unit::findOrFail($unitId)),
        ]);
    }

    public function notifications(Request $request, Apartment $apartment): View
    {
        $userId = $request->user()->id;

        return view('portal.notifications', [
            'apartment' => $apartment,
            'notifications' => $this->notificationsQuery($request, $apartment)
                ->with(['readers' => fn ($q) => $q->whereKey($userId)])->latest('id')->paginate(10),
            'announcements' => $apartment->announcements()->live()->latest('id')->limit(20)->get(),
        ]);
    }

    public function markRead(Request $request, Apartment $apartment, int $notification): RedirectResponse
    {
        $this->notificationsQuery($request, $apartment)->findOrFail($notification)
            ->readers()->syncWithoutDetaching([$request->user()->id => ['read_at' => now()]]);

        return back();
    }

    public function polls(Request $request, Apartment $apartment): View
    {
        return view('portal.polls', [
            'apartment' => $apartment,
            'polls' => $apartment->polls()->with(['options' => fn ($q) => $q->withCount('votes')])->latest('id')->get(),
            'votedPollIds' => $apartment->polls()->whereHas('votes', fn ($q) => $q->whereIn('unit_id', $this->unitIds($request)))->pluck('id')->all(),
            'units' => Unit::with('block')->whereIn('id', $this->unitIds($request))->get(),
        ]);
    }

    public function vote(Request $request, Apartment $apartment, int $poll, ResidentService $residents): RedirectResponse
    {
        $poll = $apartment->polls()->findOrFail($poll);
        $unitIds = $this->unitIds($request);
        $data = $request->validate([
            'option_id' => ['required', Rule::exists('poll_options', 'id')->where('poll_id', $poll->id)],
            'unit_id' => ['nullable', Rule::in($unitIds)],
        ]);

        return $this->attempt(fn () => $residents->castVote($poll, (int) $data['option_id'], (int) ($data['unit_id'] ?? $unitIds[0]), $request->user()),
            'Oyunuz kaydedildi.');
    }

    public function tickets(Request $request, Apartment $apartment): View
    {
        return view('portal.tickets', [
            'apartment' => $apartment,
            'tickets' => $apartment->tickets()->with('messages.user')->where('user_id', $request->user()->id)->latest('id')->paginate(10),
            'units' => Unit::with('block')->whereIn('id', $this->unitIds($request))->get(),
        ]);
    }

    public function storeTicket(Request $request, Apartment $apartment, ResidentService $residents, TicketService $tickets): RedirectResponse
    {
        $unitIds = $this->unitIds($request);
        $data = $request->validate([
            'unit_id' => ['nullable', Rule::in($unitIds)],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'category' => ['required', Rule::in(Ticket::CATEGORIES)],
            'priority' => ['nullable', Rule::in(array_keys(Ticket::PRIORITIES))],
            'photo' => TicketService::PHOTO_RULES,
        ]);
        $ticket = $residents->openTicket($apartment, Unit::with('block')->findOrFail($data['unit_id'] ?? $unitIds[0]), $request->user(), $data);
        if ($request->hasFile('photo')) {
            $tickets->addMessage($ticket, $request->user(), false, null, $request->file('photo'));
        }

        return back()->with('status', 'Talebiniz yönetime iletildi.');
    }

    public function storeTicketMessage(Request $request, Apartment $apartment, int $ticket, TicketService $tickets): RedirectResponse
    {
        $ticket = $apartment->tickets()->where('user_id', $request->user()->id)->findOrFail($ticket);
        $data = $request->validate([
            'body' => ['nullable', 'required_without:photo', 'string', 'max:3000'],
            'photo' => TicketService::PHOTO_RULES,
        ]);

        return $this->attempt(fn () => $tickets->addMessage($ticket, $request->user(), false, $data['body'] ?? null, $request->file('photo')), 'Mesajınız gönderildi.');
    }

    public function ticketAttachment(Request $request, Apartment $apartment, int $ticket, int $message, TicketService $tickets)
    {
        return $tickets->attachmentResponse($apartment->tickets()->where('user_id', $request->user()->id)->findOrFail($ticket)->messages()->findOrFail($message));
    }

    public function reservations(Request $request, Apartment $apartment): View
    {
        return view('portal.reservations', [
            'apartment' => $apartment,
            'areas' => $apartment->commonAreas()->where('is_active', true)->orderBy('name')->get(),
            'reservations' => $this->residentRecords($request, $apartment->reservations())->with('area:id,name')
                ->whereDate('date', '>=', today()->subDays(30))->orderByDesc('date')->get(),
            'units' => Unit::with('block')->whereIn('id', $this->unitIds($request))->get(),
        ]);
    }

    public function storeReservation(Request $request, Apartment $apartment, ReservationService $service): RedirectResponse
    {
        $unitIds = $this->unitIds($request);
        $data = $request->validate([
            'common_area_id' => ['required', Rule::exists('common_areas', 'id')->where('apartment_id', $apartment->id)],
            'unit_id' => ['nullable', Rule::in($unitIds)],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->attempt(fn () => $service->book($apartment->commonAreas()->findOrFail($data['common_area_id']),
            Unit::with('block')->findOrFail($data['unit_id'] ?? $unitIds[0]), $data['date'], $data['start_time'], $data['end_time'],
            $request->user(), $data['note'] ?? null), 'Rezervasyon talebiniz alındı.');
    }

    public function cancelReservation(Request $request, Apartment $apartment, int $reservation, ReservationService $service): RedirectResponse
    {
        $reservation = $this->residentRecords($request, $apartment->reservations())->findOrFail($reservation);
        if (! $reservation->isUpcoming()) {
            return back()->with('error', 'Başlamış veya kapanmış rezervasyon iptal edilemez.');
        }

        return $this->attempt(fn () => $service->cancel($reservation, $request->user()), 'Rezervasyon iptal edildi.');
    }

    public function meetings(Apartment $apartment): View
    {
        return view('portal.meetings', [
            'apartment' => $apartment,
            'meetings' => $apartment->meetings()->where('status', '!=', 'cancelled')->orderByDesc('meets_at')->limit(30)->get(),
        ]);
    }

    /** @return array<int, int> */
    private function unitIds(Request $request): array
    {
        return $request->attributes->get('unit_ids');
    }

    private function residentRecords(Request $request, HasMany $records): HasMany
    {
        return $records->whereIn('unit_id', $this->unitIds($request));
    }

    /** Tüm siteye giden ve sakinin dairelerine giden bildirimler */
    private function notificationsQuery(Request $request, Apartment $apartment): HasMany
    {
        return $apartment->siteNotifications()
            ->where(fn (Builder $q) => $q->whereNull('unit_id')->orWhereIn('unit_id', $this->unitIds($request)));
    }
}
