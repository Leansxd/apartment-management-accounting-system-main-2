<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\CashTransaction;
use App\Models\Member;
use App\Services\AuditTrail;
use App\Services\ModuleStatsService;
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
                ->when(array_key_exists((string) $request->query('type'), Member::TYPES), fn ($q) => $q->where('type', $request->query('type')))
                ->when($request->integer('block_id'), fn ($q, $block) => $q->whereHas('unit', fn ($u) => $u->where('block_id', $block)))
                ->when($request->query('mobile') === 'linked', fn ($q) => $q->whereNotNull('user_id'))
                ->when($request->query('mobile') === 'waiting', fn ($q) => $q->whereNull('user_id'))
                ->latest()->paginate(10)->withQueryString(),
            'units' => $apartment->units()->with('block')->get()->sortBy(fn ($u) => [$u->block?->name, (int) $u->unit_no]),
            'blocks' => $apartment->blocks()->orderBy('name')->get(),
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

        return back()->with('status', "Üye eklendi. Davet kodu: {$member->invite_code}".$this->markOccupiedForTenant($member));
    }

    public function show(Apartment $apartment, Member $member, ReportService $reports, ModuleStatsService $stats)
    {
        $member->load('unit.block', 'user');
        $unit = $member->unit;

        return view('site.member', [
            'apartment' => $apartment,
            'member' => $member,
            'statement' => $reports->statement($unit),
            'moduleSummary' => $stats->unit($unit),
            'debts' => $unit->debts()->latest('due_date')->get(),
            'payments' => $unit->cashTransactions()->effective()->where('type', 'income')->with('payer')->latest('id')->limit(30)->get(),
            'canPurge' => ! $this->isPayer($member),
        ]);
    }

    /** Kiracı, daireyi kullanan kişidir: "Boş" görünen daireye kiracı girince durum "Dolu" yapılır. */
    private function markOccupiedForTenant(Member $member): string
    {
        $unit = $member->unit;
        if ($member->type !== 'tenant' || ! $unit || $unit->status === 'Dolu') {
            return '';
        }
        $unit->update(['status' => 'Dolu']);

        return ' Daire durumu "Dolu" yapıldı.';
    }

    public function update(Request $request, Apartment $apartment, Member $member)
    {
        // Daire ve hesap bağlantısı buradan değiştirilemez (davet kodu / sakin erişimi tutarlılığı için)
        $data = $this->validated($request, $apartment, withUnit: false);
        $member->update($data);

        return back()->with('status', 'Üye bilgileri güncellendi.'.$this->markOccupiedForTenant($member));
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
        $members = $apartment->members()->onlyTrashed()->with('unit.block')->orderByDesc('moved_out_on')->orderByDesc('deleted_at')->paginate(10);

        return view('site.members-history', [
            'apartment' => $apartment,
            'members' => $members,
            'payerIds' => CashTransaction::whereIn('payer_member_id', $members->pluck('id'))->distinct()->pluck('payer_member_id')->all(),
        ]);
    }

    /** Yanlışlıkla geçmişe taşınan üyeyi listeye geri alır. Mobil hesabı bağlıysa erişimi geri gelir, değilse yeni davet kodu üretilir. */
    public function restore(Apartment $apartment, Member $member, AuditTrail $audit)
    {
        abort_unless($member->trashed(), 404);
        $unit = $member->unit;
        if (! $unit || $unit->trashed()) {
            return back()->with('error', 'Üyenin dairesi silinmiş; geri alınamaz.');
        }
        if ($apartment->members()->where('unit_id', $unit->id)->where('type', $member->type)->exists()) {
            return back()->with('error', 'Bu dairede aynı türde kayıtlı bir üye var; geri almak için önce onu geçmişe taşıyın.');
        }

        $member->restore();
        $member->update([
            'moved_out_on' => null, 'move_out_reason' => null,
            'invite_code' => $member->user_id ? null : Member::generateInviteCode($apartment->code, $unit->label()),
        ]);
        $audit->log($apartment->id, 'RESTORE_MEMBER', 'MEMBERS', "member:{$member->id}", ['name' => $member->full_name, 'unit' => $unit->label()]);

        return redirect()->route('site.members.show', [$apartment, $member])
            ->with('status', 'Üye listeye geri alındı.'.$this->markOccupiedForTenant($member));
    }

    /**
     * Yanlış girilen üye kaydını kalıcı siler (geçmişte de görünmez). Tahsilatlarda ödeyen olarak geçen üye silinemez:
     * makbuzlarda kimin ödediği kaybolurdu; böyle bir üye geçmişe taşınır.
     */
    public function purge(Apartment $apartment, Member $member, AuditTrail $audit)
    {
        if ($this->isPayer($member)) {
            return back()->with('error', 'Bu kişi tahsilatlarda ödeyen olarak geçiyor; kalıcı silinemez. "Daireden ayrıldı / devir" ile geçmişe taşıyın.');
        }
        $name = $member->full_name;
        $wasArchived = $member->trashed();
        $audit->log($apartment->id, 'PURGE_MEMBER', 'MEMBERS', "member:{$member->id}",
            ['name' => $name, 'unit' => $member->unit?->label(), 'type' => $member->type, 'archived' => $member->trashed()]);
        $member->forceDelete();

        return redirect()->route($wasArchived ? 'site.members.history' : 'site.members.index', $apartment)
            ->with('status', "{$name} kaydı kalıcı olarak silindi.");
    }

    private function isPayer(Member $member): bool
    {
        return CashTransaction::where('payer_member_id', $member->id)->exists();
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
