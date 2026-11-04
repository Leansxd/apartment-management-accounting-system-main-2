<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Member;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Sakinler. Mobil hesap, sakinin davet koduyla bağlanır (bkz. Api\ResidentController::redeem). */
class MemberController extends Controller
{
    public function index(Request $request, Apartment $apartment)
    {
        $search = trim((string) $request->query('q'));

        return view('site.members', [
            'apartment' => $apartment,
            'members' => $apartment->members()->with('unit.block', 'user')
                ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
                ->latest()->paginate(50)->withQueryString(),
            'units' => $apartment->units()->with('block')->get()->sortBy(fn ($u) => [$u->block?->name, (int) $u->unit_no]),
            'search' => $search,
        ]);
    }

    public function store(Request $request, Apartment $apartment)
    {
        $data = $this->validated($request, $apartment);
        // Aynı dairede aynı türde (ör. iki mülk sahibi) aktif üye olamaz
        if ($apartment->members()->where('unit_id', $data['unit_id'])->where('type', $data['type'])->exists()) {
            return back()->withInput()->with('error', 'Bu dairede aynı türde kayıtlı bir üye zaten var.');
        }

        $unit = $apartment->units()->with('block')->findOrFail($data['unit_id']);
        $member = $apartment->members()->create($data + [
            'invite_code' => Member::generateInviteCode($apartment->code, $unit->label()),
        ]);

        return back()->with('status', "Üye eklendi. Davet kodu: {$member->invite_code}");
    }

    public function show(Apartment $apartment, Member $member, ReportService $reports)
    {
        $member->load('unit.block', 'user');
        $unit = $member->unit;

        return view('site.member', [
            'apartment' => $apartment,
            'member' => $member,
            'statement' => $reports->statement($unit),
            'debts' => $unit->debts()->latest('due_date')->get(),
            'payments' => $unit->cashTransactions()->effective()->where('type', 'income')->latest('id')->limit(30)->get(),
        ]);
    }

    public function update(Request $request, Apartment $apartment, Member $member)
    {
        // Daire ve hesap bağlantısı buradan değiştirilemez (davet kodu / sakin erişimi tutarlılığı için)
        $data = $this->validated($request, $apartment, withUnit: false);
        $member->update($data);

        return back()->with('status', 'Üye bilgileri güncellendi.');
    }

    /** Yeni davet kodu üretir; eski kod hemen geçersiz olur. */
    public function regenerateInvite(Apartment $apartment, Member $member)
    {
        $member->update(['invite_code' => Member::generateInviteCode($apartment->code, $member->unit->label())]);

        return back()->with('status', "Yeni davet kodu: {$member->invite_code}");
    }

    /** Mobil hesap bağlantısını kaldırır: sakin bu dairenin kayıtlarını artık göremez. */
    public function unlink(Apartment $apartment, Member $member)
    {
        $member->update(['user_id' => null, 'activated_at' => null,
            'invite_code' => Member::generateInviteCode($apartment->code, $member->unit->label())]);

        return back()->with('status', 'Mobil hesap bağlantısı kaldırıldı, yeni davet kodu üretildi.');
    }

    /**
     * Daireden ayrılış / devir: üye geçmişe taşınır (silinmez), davet kodu geçersiz olur ve sakinin
     * mobil erişimi hemen biter. Yeni sakin için aynı daireye yeni üye eklenir; eski borçlar daireye aittir.
     */
    public function destroy(Request $request, Apartment $apartment, Member $member)
    {
        $data = $request->validate([
            'moved_out_on' => ['nullable', 'date', 'before_or_equal:today'],
            'move_out_reason' => ['nullable', 'string', 'max:150'],
        ]);
        $member->update([
            'invite_code' => null,
            'moved_out_on' => $data['moved_out_on'] ?? today(),
            'move_out_reason' => $data['move_out_reason'] ?? null,
        ]);
        $member->delete();

        return redirect()->route('site.members.index', $apartment)->with('status', 'Üye geçmişe taşındı (daireden ayrıldı).');
    }

    /** Daireden ayrılmış (geçmiş) üyeler */
    public function history(Apartment $apartment)
    {
        return view('site.members-history', [
            'apartment' => $apartment,
            'members' => $apartment->members()->onlyTrashed()->with('unit.block')->orderByDesc('moved_out_on')->orderByDesc('deleted_at')->paginate(50),
        ]);
    }

    private function validated(Request $request, Apartment $apartment, bool $withUnit = true): array
    {
        return $request->validate(array_filter([
            'unit_id' => $withUnit ? ['required', Rule::exists('units', 'id')->where('apartment_id', $apartment->id)->whereNull('deleted_at')] : null,
            'type' => ['required', Rule::in(array_keys(Member::TYPES))],
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'id_number' => ['nullable', 'digits:11'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'moved_in_on' => ['nullable', 'date'],
        ]));
    }
}
