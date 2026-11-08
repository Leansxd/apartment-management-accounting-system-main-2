<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Debt;
use App\Models\Member;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Kayıtlı kullanıcılar. Süper admin herkesi görür; admin yalnızca kendi sitelerinin
 * sakinlerini ve o sitelerin yöneticilerini görür (siteler arası veri sızıntısı yok).
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));
        $role = $request->query('role');
        $viewer = $request->user();
        $viewerSiteIds = $viewer->accessibleApartments()->pluck('id');

        return view('users.index', [
            'users' => $this->visibleUsers($viewer)
                ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
                ->when(in_array($role, ['admin', 'user'], true), fn ($q) => $q->where('role', $role))
                ->with(['memberships' => fn ($q) => $q->whereIn('apartment_id', $viewerSiteIds)->with('apartment:id,name', 'unit.block')])
                ->orderBy('name')->paginate(50)->withQueryString(),
            'search' => $search,
            'role' => $role,
            'sitesWithUnits' => $viewer->is_super_admin ? Apartment::orderBy('name')->with('units.block')->get(['id', 'name']) : collect(),
        ]);
    }

    public function show(Request $request, User $user)
    {
        abort_unless($this->visibleUsers($request->user())->whereKey($user->id)->exists(), 404);
        $siteIds = $request->user()->accessibleApartments()->pluck('id');

        $memberships = $user->memberships()->whereIn('apartment_id', $siteIds)->with('apartment', 'unit.block')->get();
        $debts = Debt::whereIn('unit_id', $memberships->pluck('unit_id'))->with('unit.block')->latest('due_date')->limit(50)->get();

        return view('users.show', [
            'user' => $user,
            'memberships' => $memberships,
            'debts' => $debts,
            'openDebt' => $debts->where('status', Debt::PENDING)->sum('remaining_amount'),
            'managed' => $user->accessibleApartments()->whereIn('id', $siteIds)->get(),
        ]);
    }

    /** Süper admin: panel yöneticisi veya bir daireye bağlı sakin hesabı oluşturur. */
    public function store(Request $request)
    {
        $isResident = $request->input('role') === 'user';
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'unit_id' => [Rule::requiredIf($isResident), 'nullable', 'integer', Rule::exists('units', 'id')->whereNull('deleted_at')],
            'member_type' => [Rule::requiredIf($isResident), 'nullable', Rule::in(array_keys(Member::TYPES))],
        ], ['unit_id.required' => 'Sakin için bir daire seçin.', 'member_type.required' => 'Sakinin türünü seçin.']);

        $unit = $isResident ? Unit::findOrFail($data['unit_id']) : null;
        $existingMember = $unit ? Member::where('unit_id', $unit->id)->where('type', $data['member_type'])->first() : null;
        if ($existingMember?->user_id) {
            return back()->withInput($request->except('password'))->with('error', 'Bu dairede bu türde kayıtlı sakinin zaten bir hesabı var.');
        }

        DB::transaction(function () use ($data, $unit, $existingMember) {
            $user = User::create(Arr::only($data, ['name', 'email', 'password']));
            $user->forceFill(['role' => $data['role']])->save();

            if ($existingMember) {
                $existingMember->update(['user_id' => $user->id, 'activated_at' => now()]);
            } elseif ($unit) {
                Member::create([
                    'apartment_id' => $unit->apartment_id, 'unit_id' => $unit->id, 'user_id' => $user->id,
                    'type' => $data['member_type'], 'full_name' => $data['name'], 'email' => $data['email'], 'activated_at' => now(),
                ]);
            }
        });

        return back()->with('status', 'Kullanıcı oluşturuldu.');
    }

    /** Süper admin: rol değiştirir. Kendi rolünü düşüremez. */
    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', Rule::in(['admin', 'user', 'super'])]]);
        if ($user->is($request->user())) {
            return back()->with('error', 'Kendi rolünüzü değiştiremezsiniz.');
        }
        $user->forceFill([
            'role' => $data['role'] === 'user' ? 'user' : 'admin',
            'is_super_admin' => $data['role'] === 'super',
        ])->save();

        return back()->with('status', 'Rol güncellendi.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Kendi hesabınızı silemezsiniz.');
        }
        $user->tokens()->delete();
        $user->delete(); // üyelik kayıtlarında user_id boşalır, sakinin erişimi biter

        return redirect()->route('users.index')->with('status', 'Kullanıcı silindi.');
    }

    private function visibleUsers(User $viewer): Builder
    {
        if ($viewer->is_super_admin) {
            return User::query();
        }
        $siteIds = $viewer->accessibleApartments()->pluck('id');

        return User::query()->where(fn ($q) => $q
            ->whereIn('id', Member::whereIn('apartment_id', $siteIds)->whereNotNull('user_id')->select('user_id'))
            ->orWhereIn('id', \DB::table('apartment_manager')->whereIn('apartment_id', $siteIds)->select('user_id'))
            ->orWhere('id', $viewer->id));
    }
}
