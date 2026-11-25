<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\FinanceService;
use App\Services\ModuleStatsService;
use App\Services\YearEndService;
use App\Support\SitePermissions;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ApartmentController extends Controller
{
    public function index(Request $request, ModuleStatsService $stats)
    {
        return view('sites.index', [
            'apartments' => $apartments = $request->user()->accessibleApartments()->withCount(['units', 'members', 'blocks'])->get(),
            'siteStats' => $stats->siteComparison($apartments),
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

        $apartment = Apartment::provision($data, $request->user());

        return redirect()->route('site.units.index', $apartment)->with('status', 'Site oluşturuldu. Şimdi daireleri ekleyin.');
    }

    public function edit(Request $request, Apartment $apartment)
    {
        $viewer = $request->user();
        $adminsQuery = User::where('role', 'admin')->orderBy('name');
        if (! $viewer->is_super_admin) {
            $siteIds = $viewer->accessibleApartments()->pluck('id');
            $adminsQuery->where(fn ($q) => $q
                ->whereIn('id', DB::table('apartment_manager')->whereIn('apartment_id', $siteIds)->select('user_id'))
                ->orWhereIn('id', Apartment::whereIn('id', $siteIds)->select('owner_id')));
        }

        return view('site.settings', [
            'apartment' => $apartment->load('managers', 'owner'),
            'admins' => $adminsQuery->get(['id', 'name', 'email']),
            'yearClosings' => $apartment->auditLogs()->where('action', 'YEAR_END_CLOSE')->latest('id')->get(),
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
    public function addManager(Request $request, Apartment $apartment, AuditTrail $audit)
    {
        $this->authorizeOwner($request, $apartment);
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('role', 'admin')],
            'role' => ['required', Rule::in(array_keys(Apartment::ROLES))],
        ]);
        $apartment->managers()->syncWithoutDetaching([$data['user_id'] => ['role' => $data['role']]]);
        $audit->log($apartment->id, 'ADD_MANAGER', 'USERS', "user:{$data['user_id']}", ['role' => $data['role']]);

        return back()->with('status', 'Yönetici eklendi.');
    }

    /** Yöneticinin bu sitedeki rolünü değiştirir (site sahibi her zaman tam yetkilidir). */
    public function updateManagerRole(Request $request, Apartment $apartment, User $user, AuditTrail $audit)
    {
        $this->authorizeOwner($request, $apartment);
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(Apartment::ROLES))]]);
        if ($user->id === $apartment->owner_id) {
            return back()->with('error', 'Site sahibinin rolü değiştirilemez.');
        }
        $current = $apartment->managers()->whereKey($user->id)->first();
        abort_unless($current !== null, 404);
        $apartment->managers()->updateExistingPivot($user->id, ['role' => $data['role']]);
        $audit->log($apartment->id, 'UPDATE_MANAGER_ROLE', 'USERS', "user:{$user->id}", ['changes' => ['role' => ['old' => $current->pivot->role, 'new' => $data['role']]]]);

        return back()->with('status', 'Rol güncellendi.');
    }

    public function removeManager(Request $request, Apartment $apartment, User $user, AuditTrail $audit)
    {
        $this->authorizeOwner($request, $apartment);
        if ($user->id === $apartment->owner_id) {
            return back()->with('error', 'Site sahibi yönetici listesinden çıkarılamaz.');
        }
        $apartment->managers()->detach($user);
        $audit->log($apartment->id, 'REMOVE_MANAGER', 'USERS', "user:{$user->id}", ['email' => $user->email]);

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
            foreach (['ledgerEntries', 'debtPayments', 'cashTransactions', 'bankLines', 'reservations', 'debts',
                'expenses', 'budgetItems', 'meterBills', 'meters', 'assets', 'commonAreas', 'meetings',
                'documents', 'siteNotifications', 'announcements', 'polls', 'decisions', 'tickets',
                'securityEntries', 'tasks', 'inventoryItems', 'staff', 'duesDefinitions', 'vendors',
                'members', 'unitGroups'] as $relation) {
                $apartment->{$relation}()->delete();
            }
            $apartment->units()->withTrashed()->forceDelete();
            $apartment->blocks()->withTrashed()->forceDelete();
            $apartment->cashboxes()->update(['balance' => 0, 'currency_balance' => 0]);
            $apartment->update(['closed_until' => null]);
        });
        Storage::disk('local')->deleteDirectory("documents/{$apartment->id}");
        Storage::disk('local')->deleteDirectory("tickets/{$apartment->id}");
        $finance->audit($apartment, $request->user(), 'RESET_APARTMENT', 'SETTINGS', null);

        return redirect()->route('site.units.index', $apartment)->with('status', 'Site verileri sıfırlandı.');
    }

    public function destroy(Request $request, Apartment $apartment)
    {
        $this->authorizeOwner($request, $apartment);
        $request->validate(['confirm' => ['required', Rule::in([$apartment->code])]],
            ['confirm.in' => 'Onay için site kodunu ('.$apartment->code.') yazın.']);
        $apartment->delete();
        Storage::disk('local')->deleteDirectory("documents/{$apartment->id}");
        Storage::disk('local')->deleteDirectory("tickets/{$apartment->id}");

        return redirect()->route('sites.index')->with('status', 'Site silindi.');
    }

    /** Dönemi kapatır veya kapalı dönemi geri açar; yalnızca site sahibi yapabilir ve geri açmak gerekçe ister. */
    public function closePeriod(Request $request, Apartment $apartment, AuditTrail $audit)
    {
        $this->authorizeOwner($request, $apartment);
        $data = $request->validate([
            'closed_until' => ['nullable', 'date', 'before:today'],
            'reason' => ['nullable', 'string', 'max:300'],
        ], ['closed_until.before' => 'Dönem yalnızca dünden önceki bir tarihe kadar kapatılabilir.']);

        $previous = $apartment->closed_until?->toDateString();
        $next = filled($data['closed_until'] ?? null) ? CarbonImmutable::parse($data['closed_until'])->toDateString() : null;
        $isReopening = $previous !== null && ($next === null || $next < $previous);
        if ($isReopening && blank($data['reason'] ?? null)) {
            return back()->withInput()->with('error', 'Kapalı dönemi geri açmak için gerekçe yazın.');
        }
        if ($next === $previous) {
            return back()->with('status', 'Dönem kapanış tarihi değişmedi.');
        }

        $apartment->update(['closed_until' => $next]);
        $audit->log($apartment->id, $isReopening ? 'REOPEN_PERIOD' : 'CLOSE_PERIOD', 'SETTINGS', $next ?? $previous, [
            'changes' => ['closed_until' => ['old' => $previous, 'new' => $next]], 'reason' => $data['reason'] ?? null,
        ]);

        return back()->with('status', $next ? "Dönem {$apartment->closed_until->format('d.m.Y')} tarihine kadar kapatıldı." : 'Dönem kapanışı kaldırıldı.');
    }

    public function audit(Request $request, Apartment $apartment)
    {
        return view('site.audit', [
            'apartment' => $apartment,
            'logs' => $apartment->auditLogs()->with('user')
                ->when($request->query('module'), fn ($q, $module) => $q->where('module', $module))
                ->when($request->integer('user_id'), fn ($q, $user) => $q->where('user_id', $user))
                ->when($this->dateQuery($request, 'from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                ->when($this->dateQuery($request, 'to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
                ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('action', 'like', "%{$s}%")->orWhere('reference', 'like', "%{$s}%")))
                ->latest('id')->paginate(10)->withQueryString(),
            'modules' => $apartment->auditLogs()->distinct()->orderBy('module')->pluck('module', 'module')->all(),
            'users' => User::whereIn('id', $apartment->auditLogs()->whereNotNull('user_id')->select('user_id'))->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    /** Muhasebeci ve izleyicinin modül bazında erişim seviyeleri (yalnızca site sahibi değiştirir). */
    public function updatePermissions(Request $request, Apartment $apartment, AuditTrail $audit)
    {
        $this->authorizeOwner($request, $apartment);
        $level = ['required', Rule::in(array_keys(SitePermissions::LEVELS))];
        $rules = [];
        foreach (SitePermissions::ROLES as $role) {
            foreach (array_keys(SitePermissions::MODULES) as $module) {
                $rules["permissions.{$role}.{$module}"] = $level;
            }
        }
        $permissions = $request->validate($rules)['permissions'];

        $before = SitePermissions::matrix($apartment);
        $apartment->update(['role_permissions' => $permissions]);
        $changes = [];
        foreach ($permissions as $role => $modules) {
            foreach ($modules as $module => $new) {
                if ($before[$role][$module] !== $new) {
                    $changes["{$role}.{$module}"] = ['old' => $before[$role][$module], 'new' => $new];
                }
            }
        }
        $audit->log($apartment->id, 'UPDATE_ROLE_PERMISSIONS', 'USERS', null, ['changes' => $changes]);

        return back()->with('status', 'Rol izinleri kaydedildi.');
    }

    /** Tamamlanmış yılı kapatır; yalnızca site sahibi. */
    public function closeYear(Request $request, Apartment $apartment, YearEndService $yearEnd)
    {
        $this->authorizeOwner($request, $apartment);
        $data = $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:'.(today()->year - 1)]]);

        return $this->attempt(function () use ($yearEnd, $apartment, $data, $request) {
            $yearEnd->close($apartment, (int) $data['year'], $request->user());

            return "{$data['year']} yılı kapatıldı.";
        }, '');
    }

    private function authorizeOwner(Request $request, Apartment $apartment): void
    {
        $user = $request->user();
        abort_unless($user->is_super_admin || $apartment->owner_id === $user->id, 403,
            'Bu işlemi yalnızca site sahibi yapabilir.');
    }
}
