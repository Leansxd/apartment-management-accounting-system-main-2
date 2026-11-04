<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApartmentController extends Controller
{
    public function index(Request $request)
    {
        return view('sites.index', [
            'apartments' => $request->user()->accessibleApartments()->withCount(['units', 'members', 'blocks'])->get(),
        ]);
    }

    /** Yeni site: oluşturan admin sahip ve yönetici olur; varsayılan kasa ve kategoriler açılır. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'alpha_dash', 'max:20', 'unique:apartments,code'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'blocks' => ['nullable', 'string', 'max:100'], // "A,B,C"
        ]);

        $apartment = DB::transaction(function () use ($data, $request) {
            $apartment = Apartment::create([
                'name' => $data['name'], 'code' => mb_strtoupper($data['code']),
                'address' => $data['address'] ?? null, 'city' => $data['city'] ?? null,
                'owner_id' => $request->user()->id,
            ]);
            $apartment->managers()->attach($request->user());
            $apartment->cashboxes()->create(['name' => 'Merkez Kasa', 'type' => 'cash']);
            foreach (['Aidat' => 'income', 'Yakıt' => 'income', 'Demirbaş' => 'income', 'Diğer Gelir' => 'income',
                'Elektrik' => 'expense', 'Su' => 'expense', 'Temizlik' => 'expense', 'Bakım Onarım' => 'expense',
                'Personel' => 'expense', 'Diğer Gider' => 'expense'] as $name => $type) {
                $apartment->categories()->create(['name' => $name, 'type' => $type]);
            }
            foreach (array_filter(array_map('trim', explode(',', $data['blocks'] ?? 'A'))) as $block) {
                $apartment->blocks()->create(['name' => mb_strtoupper($block)]);
            }

            return $apartment;
        });

        return redirect()->route('site.units.index', $apartment)->with('status', 'Site oluşturuldu. Şimdi daireleri ekleyin.');
    }

    public function edit(Apartment $apartment)
    {
        return view('site.settings', [
            'apartment' => $apartment->load('managers', 'owner'),
            'admins' => User::where('role', 'admin')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function update(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('apartments')->ignore($apartment)],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'tax_number' => ['nullable', 'string', 'max:20'],
        ]);
        $data['code'] = mb_strtoupper($data['code']);
        $data['allow_negative_cashbox'] = $request->boolean('allow_negative_cashbox');
        $apartment->update($data);

        return back()->with('status', 'Site bilgileri kaydedildi.');
    }

    /** Yönetici ekleme: yalnızca site sahibi veya süper admin (diğer yöneticiler listeyi değiştiremez) */
    public function addManager(Request $request, Apartment $apartment)
    {
        $this->authorizeOwner($request, $apartment);
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('role', 'admin')],
            'role' => ['required', Rule::in(array_keys(Apartment::ROLES))],
        ]);
        $apartment->managers()->syncWithoutDetaching([$data['user_id'] => ['role' => $data['role']]]);

        return back()->with('status', 'Yönetici eklendi.');
    }

    /** Yöneticinin bu sitedeki rolünü değiştirir (site sahibi her zaman tam yetkilidir). */
    public function updateManagerRole(Request $request, Apartment $apartment, User $user)
    {
        $this->authorizeOwner($request, $apartment);
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(Apartment::ROLES))]]);
        if ($user->id === $apartment->owner_id) {
            return back()->with('error', 'Site sahibinin rolü değiştirilemez.');
        }
        abort_unless($apartment->managers()->whereKey($user->id)->exists(), 404);
        $apartment->managers()->updateExistingPivot($user->id, ['role' => $data['role']]);

        return back()->with('status', 'Rol güncellendi.');
    }

    public function removeManager(Request $request, Apartment $apartment, User $user)
    {
        $this->authorizeOwner($request, $apartment);
        if ($user->id === $apartment->owner_id) {
            return back()->with('error', 'Site sahibi yönetici listesinden çıkarılamaz.');
        }
        $apartment->managers()->detach($user);

        return back()->with('status', 'Yönetici çıkarıldı.');
    }

    /**
     * Siteyi fabrika ayarlarına döndürür: tüm finans ve operasyon kayıtları silinir.
     * Denetim kayıtları korunur ve sıfırlama da bir denetim kaydı olarak yazılır.
     */
    public function reset(Request $request, Apartment $apartment, FinanceService $finance)
    {
        $this->authorizeOwner($request, $apartment);
        $request->validate(['confirm' => ['required', Rule::in([$apartment->code])]],
            ['confirm.in' => 'Onay için site kodunu ('.$apartment->code.') yazın.']);

        DB::transaction(function () use ($apartment) {
            foreach (['ledgerEntries', 'debtPayments', 'cashTransactions', 'debts', 'expenses', 'siteNotifications',
                'announcements', 'polls', 'decisions', 'tickets', 'securityEntries', 'tasks', 'meters',
                'inventoryItems', 'staff', 'duesDefinitions', 'vendors', 'members', 'unitGroups'] as $relation) {
                $apartment->{$relation}()->delete();
            }
            $apartment->units()->withTrashed()->forceDelete();
            $apartment->blocks()->withTrashed()->forceDelete();
            $apartment->cashboxes()->update(['balance' => 0]);
        });
        $finance->audit($apartment, $request->user(), 'RESET_APARTMENT', 'SETTINGS', null);

        return redirect()->route('site.units.index', $apartment)->with('status', 'Site verileri sıfırlandı.');
    }

    public function destroy(Request $request, Apartment $apartment)
    {
        $this->authorizeOwner($request, $apartment);
        $request->validate(['confirm' => ['required', Rule::in([$apartment->code])]],
            ['confirm.in' => 'Onay için site kodunu ('.$apartment->code.') yazın.']);
        $apartment->delete();

        return redirect()->route('sites.index')->with('status', 'Site silindi.');
    }

    public function audit(Apartment $apartment)
    {
        return view('site.audit', [
            'apartment' => $apartment,
            'logs' => $apartment->auditLogs()->with('user')->latest('id')->paginate(50),
        ]);
    }

    private function authorizeOwner(Request $request, Apartment $apartment): void
    {
        $user = $request->user();
        abort_unless($user->is_super_admin || $apartment->owner_id === $user->id, 403,
            'Bu işlemi yalnızca site sahibi yapabilir.');
    }
}
