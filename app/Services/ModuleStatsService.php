<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\Asset;
use App\Models\Cashbox;
use App\Models\Debt;
use App\Models\Member;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\SecurityEntry;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sayfaların üstündeki özetler. Her metot aynı biçimi döner:
 * array{metrics: list<array{label:string, value:string, hint:?string, tone:?string}>, panels: list<array>}
 * Toplamlar veritabanında hesaplanır; yalnızca sayfanın ait olduğu sitenin kayıtları okunur.
 */
class ModuleStatsService
{
    /** Görünüm => özeti üreten metot (AppServiceProvider view composer ile bağlanır) */
    public const VIEWS = [
        'site.dashboard' => 'siteDashboard',
        'site.finance.overview' => 'overview',
        'site.finance.debts' => 'debts',
        'site.finance.collections' => 'collections',
        'site.finance.cashboxes' => 'cashboxes',
        'site.finance.expenses' => 'expenses',
        'site.finance.vendors' => 'vendors',
        'site.finance.bank' => 'bank',
        'site.finance.dues' => 'dues',
        'site.finance.late-fees' => 'lateFees',
        'site.finance.advances' => 'advances',
        'site.finance.ledger' => 'ledger',
        'site.finance.income-expense' => 'incomeExpense',
        'site.accounting.trial-balance' => 'trialBalance',
        'site.accounting.cash-book' => 'cashBook',
        'site.accounting.balance-sheet' => 'balanceSheet',
        'site.accounting.aging' => 'aging',
        'site.accounting.budget' => 'budget',
        'site.units' => 'units',
        'site.members' => 'members',
        'site.members-history' => 'memberHistory',
        'site.audit' => 'audit',
        'site.ops.tasks' => 'tasks',
        'site.ops.tickets' => 'tickets',
        'site.ops.security' => 'security',
        'site.ops.staff' => 'staff',
        'site.ops.inventory' => 'inventory',
        'site.ops.meters' => 'meters',
        'site.ops.meter-bills' => 'meterBills',
        'site.ops.payroll' => 'payroll',
        'site.ops.assets' => 'assets',
        'site.ops.documents' => 'documents',
        'site.ops.reservations' => 'reservations',
        'site.comm.announcements' => 'announcements',
        'site.comm.polls' => 'polls',
        'site.comm.decisions' => 'decisions',
        'site.comm.meetings' => 'meetings',
        'site.comm.notifications' => 'notifications',
        'site.comm.reminders' => 'reminders',
    ];

    public function __construct(private ReportService $reports) {}

    // ------------------------------------------------------------------ PANOLAR VE SİTELER

    public function siteDashboard(Apartment $apartment): array
    {
        $units = $apartment->units()->count();
        $occupied = $apartment->units()->where('status', 'Dolu')->count();
        $members = $apartment->members()->count();
        $linked = $apartment->members()->whereNotNull('user_id')->count();
        $thisMonth = $this->reports->collectionPerformance($apartment, $this->monthStart());
        $previous = $this->reports->collectionPerformance($apartment, $this->monthStart(1), $this->monthEnd(1));
        $blocks = $this->reports->blockComparison($apartment);

        return $this->summary([
            $this->metric('Doluluk', $this->percent($occupied, $units), "{$occupied} / {$units} daire"),
            $this->metric('Sakin', $this->number($members), $this->number($linked).' mobil bağlı'),
            $this->metric('Bu ay tahsilat oranı', '%'.$this->decimal($thisMonth['rate']), 'Geçen ay %'.$this->decimal($previous['rate'])),
            $this->metric('Bu ay tahakkuk', tl($thisMonth['accrued']), tl($thisMonth['collected']).' tahsil edildi'),
            $this->metric('Açık talep', $this->number($apartment->tickets()->whereIn('status', ['open', 'in_progress'])->count())),
            $this->metric('Geciken görev', $this->number($this->overdueTasks($apartment)->count())),
        ], [
            $this->bars('Blok bazında tahsilat oranı', $blocks->mapWithKeys(fn ($b) => [$b['block'] => $b['rate']]), 'percent'),
            $this->bars('Blok bazında gecikmiş alacak', $blocks->mapWithKeys(fn ($b) => [$b['block'] => $b['overdue']])),
            $this->topDebtors($apartment),
        ]);
    }

    /** Yöneticinin erişebildiği tüm sitelerin karşılaştırması */
    public function portfolio(User $user): array
    {
        $apartments = $user->accessibleApartments()->withCount('units')->get();
        $stats = $this->siteComparison($apartments);
        $names = $apartments->pluck('name', 'id');
        $series = fn (string $key) => $stats->mapWithKeys(fn ($row, $id) => [$names[$id] => $row[$key]]);
        $accrued = $stats->sum('accrued');
        $collected = $stats->sum('collected');

        return $this->summary([
            $this->metric('Site', $this->number($apartments->count())),
            $this->metric('Toplam daire', $this->number($apartments->sum('units_count'))),
            $this->metric('Portföy tahsilat oranı', $this->percent($collected, $accrued), tl($collected).' tahsil edildi'),
            $this->metric('Toplam gecikmiş', tl($stats->sum('overdue')), null, $stats->sum('overdue') > 0 ? 'bad' : null),
            $this->metric('Toplam kasa', tl($stats->sum('cash'))),
            $this->metric('Açık talep', $this->number($stats->sum('open_tickets'))),
        ], [
            $this->bars('Site bazında tahsilat oranı', $series('rate'), 'percent'),
            $this->bars('Site bazında gecikmiş alacak', $series('overdue')),
            $this->bars('Site bazında kasa', $series('cash')),
        ]);
    }

    /**
     * Site başına karşılaştırma değerleri.
     *
     * @param  Collection<int, Apartment>  $apartments
     * @return Collection<int, array{accrued:float, collected:float, overdue:float, rate:float, cash:float, open_tickets:int}>
     */
    public function siteComparison(Collection $apartments): Collection
    {
        $ids = $apartments->pluck('id');
        $debts = Debt::whereIn('apartment_id', $ids)->groupBy('apartment_id')
            ->selectRaw('apartment_id, SUM(amount) accrued, SUM(paid_amount) collected')
            ->selectRaw('SUM(CASE WHEN status = ? AND due_date < ? THEN remaining_amount ELSE 0 END) overdue', [Debt::PENDING, today()->toDateString()])
            ->get()->keyBy('apartment_id');
        $cash = Cashbox::whereIn('apartment_id', $ids)->groupBy('apartment_id')->selectRaw('apartment_id, SUM(balance) total')->pluck('total', 'apartment_id');
        $tickets = Ticket::whereIn('apartment_id', $ids)->whereIn('status', ['open', 'in_progress'])
            ->groupBy('apartment_id')->selectRaw('apartment_id, COUNT(*) total')->pluck('total', 'apartment_id');

        return $ids->mapWithKeys(function (int $id) use ($debts, $cash, $tickets) {
            $accrued = (float) ($debts[$id]->accrued ?? 0);
            $collected = (float) ($debts[$id]->collected ?? 0);

            return [$id => [
                'accrued' => $accrued, 'collected' => $collected, 'overdue' => (float) ($debts[$id]->overdue ?? 0),
                'rate' => $accrued > 0 ? round($collected / $accrued * 100, 1) : 0.0,
                'cash' => (float) ($cash[$id] ?? 0), 'open_tickets' => (int) ($tickets[$id] ?? 0),
            ]];
        });
    }

    public function users(User $viewer): array
    {
        $visible = fn (): Builder => User::query()->visibleTo($viewer);
        $total = $visible()->count();
        $admins = $visible()->where('role', 'admin')->count();
        $active = $visible()->where('last_login_at', '>=', now()->subDays(30))->count();

        return $this->summary([
            $this->metric('Toplam kullanıcı', $this->number($total)),
            $this->metric('Yönetici', $this->number($admins)),
            $this->metric('Sakin', $this->number($total - $admins)),
            $this->metric('Son 30 gün aktif', $this->number($active), $this->percent($active, $total).' oranında'),
            $this->metric('Hiç giriş yapmayan', $this->number($visible()->whereNull('last_login_at')->count())),
            $this->metric('Bu ay yeni', $this->number($visible()->where('created_at', '>=', $this->monthStart())->count())),
        ]);
    }

    public function properties(): array
    {
        $active = Property::where('status', 'active');

        return $this->summary([
            $this->metric('Toplam ilan', $this->number(Property::count())),
            $this->metric('Yayında', $this->number((clone $active)->count())),
            $this->metric('Satıldı', $this->number(Property::where('status', 'sold')->count())),
            $this->metric('Ortalama fiyat', tl((float) (clone $active)->avg('price')), 'Yayındaki ilanlar'),
            $this->metric('Portföy değeri', tl((float) (clone $active)->sum('price'))),
            $this->metric('Bu ay eklenen', $this->number(Property::where('created_at', '>=', $this->monthStart())->count())),
        ], [
            $this->bars('Kategoriye göre ilan', Property::groupBy('category')->selectRaw('category, COUNT(*) total')->pluck('total', 'category'), 'count'),
            $this->bars('Konuma göre ilan', Property::whereNotNull('location')->groupBy('location')->selectRaw('location, COUNT(*) total')->pluck('total', 'location'), 'count'),
        ]);
    }

    public function units(Apartment $apartment): array
    {
        $total = $apartment->units()->count();
        $occupied = $apartment->units()->where('status', 'Dolu')->count();
        $debtors = $apartment->debts()->where('status', Debt::PENDING)->distinct()->count('unit_id');
        $byBlock = $apartment->units()->join('blocks', 'blocks.id', '=', 'units.block_id')->groupBy('blocks.name')
            ->selectRaw('blocks.name, COUNT(*) total')->pluck('total', 'name');

        return $this->summary([
            $this->metric('Toplam daire', $this->number($total), $this->number($apartment->blocks()->count()).' blok'),
            $this->metric('Doluluk', $this->percent($occupied, $total), ($total - $occupied).' boş daire'),
            $this->metric('Toplam alan', $this->decimal((float) $apartment->units()->sum('gross_area')).' m²'),
            $this->metric('Toplam arsa payı', $this->decimal((float) $apartment->units()->sum('land_share'))),
            $this->metric('Borçlu daire', $this->number($debtors), $this->percent($debtors, $total).' oranında', $debtors > 0 ? 'warn' : 'good'),
            $this->metric('Avanslı daire', $this->number($apartment->units()->where('credit_balance', '>', 0)->count())),
        ], [
            $this->bars('Bloklara göre daire', $byBlock, 'count'),
            $this->bars('Kullanım türüne göre', $apartment->units()->groupBy('usage_type')->selectRaw('usage_type, COUNT(*) total')->pluck('total', 'usage_type'), 'count'),
            $this->topDebtors($apartment),
        ]);
    }

    public function members(Apartment $apartment): array
    {
        $total = $apartment->members()->count();
        $owners = $apartment->members()->where('type', 'owner')->count();
        $linked = $apartment->members()->whereNotNull('user_id')->count();
        $noContact = $apartment->members()->where(fn ($q) => $q->whereNull('phone')->orWhere('phone', ''))->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))->count();
        $byBlock = $apartment->members()->join('units', 'units.id', '=', 'members.unit_id')->join('blocks', 'blocks.id', '=', 'units.block_id')
            ->groupBy('blocks.name')->selectRaw('blocks.name, COUNT(*) total')->pluck('total', 'name');

        return $this->summary([
            $this->metric('Toplam sakin', $this->number($total)),
            $this->metric('Mülk sahibi', $this->number($owners)),
            $this->metric('Kiracı', $this->number($total - $owners)),
            $this->metric('Mobil bağlı', $this->percent($linked, $total), $this->number($linked).' sakin'),
            $this->metric('İletişimsiz', $this->number($noContact), 'Telefon ve e-posta yok', $noContact > 0 ? 'warn' : null),
            $this->metric('Bu ay eklenen', $this->number($apartment->members()->where('members.created_at', '>=', $this->monthStart())->count())),
        ], [
            $this->bars('Bloklara göre sakin', $byBlock, 'count'),
            $this->bars('Sakin türü', collect(['Mülk Sahibi' => $owners, 'Kiracı' => $total - $owners]), 'count'),
        ]);
    }

    public function memberHistory(Apartment $apartment): array
    {
        $history = fn (): HasMany => $apartment->members()->onlyTrashed();

        return $this->summary([
            $this->metric('Ayrılan sakin', $this->number($history()->count())),
            $this->metric('Bu yıl ayrılan', $this->number($history()->whereYear('moved_out_on', now()->year)->count())),
            $this->metric('Son ayrılış', $history()->max('moved_out_on') ? date('d.m.Y', strtotime((string) $history()->max('moved_out_on'))) : '—'),
        ], [
            $this->bars('Ayrılış nedeni', $history()->whereNotNull('move_out_reason')->groupBy('move_out_reason')
                ->selectRaw('move_out_reason, COUNT(*) total')->pluck('total', 'move_out_reason'), 'count'),
        ]);
    }

    /** Tek dairenin finans özeti: üye ve hesap ekstresi sayfaları */
    public function unit(Unit $unit): array
    {
        $row = $unit->debts()->selectRaw('COALESCE(SUM(amount),0) accrued, COALESCE(SUM(paid_amount),0) paid')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN remaining_amount ELSE 0 END),0) open_total', [Debt::PENDING])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND due_date < ? THEN remaining_amount ELSE 0 END),0) overdue', [Debt::PENDING, today()->toDateString()])
            ->first();
        $payments = $unit->cashTransactions()->effective()->where('type', 'income');
        $lastPayment = (clone $payments)->max('created_at');

        return $this->summary([
            $this->metric('Toplam tahakkuk', tl($row->accrued)),
            $this->metric('Ödenen', tl($row->paid), $this->percent((float) $row->paid, (float) $row->accrued).' oranında'),
            $this->metric('Açık borç', tl($row->open_total)),
            $this->metric('Gecikmiş', tl($row->overdue), null, $row->overdue > 0 ? 'bad' : 'good'),
            $this->metric('Avans', tl($unit->credit_balance)),
            $this->metric('Son ödeme', $lastPayment ? date('d.m.Y', strtotime((string) $lastPayment)) : '—', $this->number((clone $payments)->count()).' tahsilat'),
        ], [
            $this->bars('Kategoriye göre açık borç', $unit->debts()->where('status', Debt::PENDING)->groupBy('category_name')
                ->selectRaw('category_name, SUM(remaining_amount) total')->pluck('total', 'category_name')),
        ]);
    }

    public function audit(Apartment $apartment): array
    {
        $logs = fn (): HasMany => $apartment->auditLogs();

        return $this->summary([
            $this->metric('Toplam kayıt', $this->number($logs()->count())),
            $this->metric('Son 24 saat', $this->number($logs()->where('created_at', '>=', now()->subDay())->count())),
            $this->metric('Son 30 gün', $this->number($logs()->where('created_at', '>=', now()->subDays(30))->count())),
            $this->metric('İşlem yapan kullanıcı', $this->number($logs()->whereNotNull('user_id')->distinct()->count('user_id'))),
        ], [
            $this->bars('Modüle göre işlem', $logs()->groupBy('module')->selectRaw('module, COUNT(*) total')->pluck('total', 'module'), 'count'),
            $this->bars('En sık işlemler', $logs()->groupBy('action')->selectRaw('action, COUNT(*) total')->pluck('total', 'action'), 'count'),
        ]);
    }

    // ------------------------------------------------------------------ FİNANS

    public function overview(Apartment $apartment): array
    {
        $months = $this->reports->monthly($apartment, 12);
        $current = $months[11];
        $previous = $months[10];
        $net = $current['income'] - $current['expense'];
        $performance = $this->reports->collectionPerformance($apartment, $this->monthStart());
        $yearNet = collect($months)->sum('income') - collect($months)->sum('expense');

        return $this->summary([
            $this->metric('Bu ay gelir', tl($current['income']), $this->change($current['income'], $previous['income'])),
            $this->metric('Bu ay gider', tl($current['expense']), $this->change($current['expense'], $previous['expense'])),
            $this->metric('Bu ay net', tl($net), null, $net < 0 ? 'bad' : 'good'),
            $this->metric('Bu ay tahsilat oranı', '%'.$this->decimal($performance['rate']), tl($performance['accrued']).' tahakkuk'),
            $this->metric('12 ay net', tl($yearNet), null, $yearNet < 0 ? 'bad' : 'good'),
            $this->metric('Toplam avans', tl($apartment->units()->sum('credit_balance'))),
        ], [
            $this->monthlyTrend('Son 12 ay gelir ve gider', $months),
            $this->bars('Bu yıl gider kategorileri', $this->expenseByCategory($apartment)),
            $this->topDebtors($apartment),
        ]);
    }

    public function debts(Apartment $apartment): array
    {
        $today = today();
        $row = $apartment->debts()->selectRaw('COALESCE(SUM(amount),0) accrued, COALESCE(SUM(paid_amount),0) paid')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN remaining_amount ELSE 0 END),0) open_total', [Debt::PENDING])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END),0) open_count', [Debt::PENDING])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND due_date < ? THEN remaining_amount ELSE 0 END),0) overdue', [Debt::PENDING, $today->toDateString()])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND due_date < ? THEN 1 ELSE 0 END),0) overdue_count', [Debt::PENDING, $today->toDateString()])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND legal_status IS NOT NULL THEN remaining_amount ELSE 0 END),0) legal', [Debt::PENDING])
            ->first();
        $dueSoon = $apartment->debts()->where('status', Debt::PENDING)->whereBetween('due_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])->sum('remaining_amount');

        return $this->summary([
            $this->metric('Açık borç', tl($row->open_total), $this->number((int) $row->open_count).' kayıt'),
            $this->metric('Gecikmiş', tl($row->overdue), $this->number((int) $row->overdue_count).' kayıt', $row->overdue > 0 ? 'bad' : 'good'),
            $this->metric('30 gün içinde vadeli', tl($dueSoon)),
            $this->metric('Tahsil oranı', $this->percent((float) $row->paid, (float) $row->accrued), tl($row->paid).' tahsil edildi'),
            $this->metric('İcra takibinde', tl($row->legal), null, $row->legal > 0 ? 'warn' : null),
            $this->metric('Toplam tahakkuk', tl($row->accrued)),
        ], [
            $this->bars('Kategoriye göre açık borç', $apartment->debts()->where('status', Debt::PENDING)->groupBy('category_name')
                ->selectRaw('category_name, SUM(remaining_amount) total')->pluck('total', 'category_name')),
            $this->bars('Blok bazında gecikmiş', $this->reports->blockComparison($apartment)->mapWithKeys(fn ($b) => [$b['block'] => $b['overdue']])),
            $this->topDebtors($apartment),
        ]);
    }

    public function collections(Apartment $apartment): array
    {
        $collected = fn () => $apartment->cashTransactions()->effective()->whereIn('sub_type', ['collection', 'pos', 'advance']);
        $thisMonth = fn () => $collected()->where('cash_transactions.created_at', '>=', $this->monthStart());
        $total = (float) $thisMonth()->sum('amount');
        $previous = (float) $collected()->whereBetween('created_at', [$this->monthStart(1), $this->monthEnd(1).' 23:59:59'])->sum('amount');
        $count = $thisMonth()->count();
        $payingUnits = $thisMonth()->distinct()->count('unit_id');
        $units = $apartment->units()->count();
        $rows = $collected()->where('created_at', '>=', $this->monthStart(5))->get(['amount', 'created_at'])->groupBy(fn ($t) => $t->created_at->format('Y-m'));

        return $this->summary([
            $this->metric('Bu ay tahsilat', tl($total), $this->change($total, $previous)),
            $this->metric('Bugün', tl($collected()->whereDate('created_at', today())->sum('amount'))),
            $this->metric('İşlem sayısı', $this->number($count), 'Bu ay'),
            $this->metric('Ortalama tahsilat', tl($count ? $total / $count : 0)),
            $this->metric('Ödeme yapan daire', $this->number($payingUnits), $this->percent($payingUnits, $units).' oranında'),
            $this->metric('Bu ay avansa giden', tl($thisMonth()->sum('to_advance'))),
        ], [
            $this->trend('Son 6 ay tahsilat', ['Tahsilat'], $this->months(6)->map(fn ($m) => ['label' => $m['label'], 'values' => [(float) ($rows->get($m['key'])?->sum('amount') ?? 0)]])->all()),
            $this->bars('Bu ay kasaya göre', $thisMonth()->join('cashboxes', 'cashboxes.id', '=', 'cash_transactions.cashbox_id')->groupBy('cashboxes.name')
                ->selectRaw('cashboxes.name, SUM(cash_transactions.amount) total')->pluck('total', 'name')),
        ]);
    }

    public function cashboxes(Apartment $apartment): array
    {
        $boxes = $apartment->cashboxes()->get();
        $month = fn (string $type) => (float) $apartment->cashTransactions()->operating()->where('type', $type)->where('created_at', '>=', $this->monthStart())->sum('amount');
        $income = $month('income');
        $expense = $month('expense');

        return $this->summary([
            $this->metric('Toplam likidite', tl($boxes->sum('balance'))),
            $this->metric('Nakit', tl($boxes->where('type', 'cash')->sum('balance'))),
            $this->metric('Banka', tl($boxes->where('type', 'bank')->sum('balance'))),
            $this->metric('Bu ay giren', tl($income)),
            $this->metric('Bu ay çıkan', tl($expense)),
            $this->metric('Bu ay net akış', tl($income - $expense), null, $income < $expense ? 'bad' : 'good'),
        ], [
            $this->monthlyTrend('Son 12 ay nakit akışı', $this->reports->monthly($apartment, 12)),
            $this->bars('Kasa bakiyeleri', $boxes->pluck('balance', 'name')),
        ]);
    }

    public function expenses(Apartment $apartment): array
    {
        $year = fn () => $apartment->expenses()->where('status', 'approved')->whereYear('approved_at', now()->year);
        $pending = $apartment->expenses()->where('status', 'pending');
        $approvedThisMonth = (float) $apartment->expenses()->where('status', 'approved')->where('approved_at', '>=', $this->monthStart())->sum('total_amount');
        $yearTotal = (float) $year()->sum('total_amount');
        $byVendor = $year()->join('vendors', 'vendors.id', '=', 'expenses.vendor_id')->groupBy('vendors.name')
            ->selectRaw('vendors.name, SUM(expenses.total_amount) total')->pluck('total', 'name');

        return $this->summary([
            $this->metric('Onay bekleyen', tl((clone $pending)->sum('total_amount')), $this->number((clone $pending)->count()).' gider', (clone $pending)->exists() ? 'warn' : null),
            $this->metric('Bu ay ödenen', tl($approvedThisMonth)),
            $this->metric('Bu yıl ödenen', tl($yearTotal)),
            $this->metric('Bu yıl KDV', tl($yearTotal - (float) $year()->sum('amount'))),
            $this->metric('Aylık ortalama', tl($yearTotal / max(1, now()->month))),
            $this->metric('Bu yıl fatura', $this->number($year()->count())),
        ], [
            $this->bars('Bu yıl gider kategorileri', $this->expenseByCategory($apartment)),
            $this->bars('Bu yıl en çok ödenen firmalar', $byVendor),
        ]);
    }

    public function vendors(Apartment $apartment): array
    {
        $approved = fn () => $apartment->expenses()->where('status', 'approved')->whereNotNull('vendor_id');
        $totals = $approved()->join('vendors', 'vendors.id', '=', 'expenses.vendor_id')->groupBy('vendors.name')
            ->selectRaw('vendors.name, SUM(expenses.total_amount) total')->pluck('total', 'name');

        return $this->summary([
            $this->metric('Aktif firma', $this->number($apartment->vendors()->where('is_active', true)->count())),
            $this->metric('Toplam ödenen', tl($totals->sum())),
            $this->metric('Bu yıl ödenen', tl($approved()->whereYear('approved_at', now()->year)->sum('total_amount'))),
            $this->metric('Bekleyen fatura', tl($apartment->expenses()->where('status', 'pending')->whereNotNull('vendor_id')->sum(DB::raw('total_amount - paid_amount')))),
            $this->metric('En çok çalışılan', $totals->sortDesc()->keys()->first() ?? '—'),
        ], [$this->bars('Firmalara göre ödeme', $totals)]);
    }

    public function bank(Apartment $apartment): array
    {
        $lines = fn () => $apartment->bankLines();
        $counts = $lines()->groupBy('status')->selectRaw('status, COUNT(*) total')->pluck('total', 'status');
        $pendingIn = $lines()->where('status', 'pending')->where('amount', '>', 0);
        $unmatched = (clone $pendingIn)->whereNull('unit_id')->count();

        return $this->summary([
            $this->metric('Bekleyen satır', $this->number($counts['pending'] ?? 0), null, ($counts['pending'] ?? 0) > 0 ? 'warn' : null),
            $this->metric('İşlenen satır', $this->number($counts['posted'] ?? 0)),
            $this->metric('Yok sayılan', $this->number($counts['ignored'] ?? 0)),
            $this->metric('Bekleyen giriş', tl((clone $pendingIn)->sum('amount'))),
            $this->metric('Eşleşmeyen giriş', $this->number($unmatched), 'Daire seçilmeli', $unmatched > 0 ? 'warn' : null),
            $this->metric('Yüklenen ekstre', $this->number($lines()->distinct()->count('batch'))),
        ]);
    }

    public function dues(Apartment $apartment): array
    {
        $period = now()->format('Y-m');
        $definitions = $apartment->duesDefinitions()->where('is_active', true)->get();
        $units = $apartment->units()->get(['id', 'block_id', 'gross_area']);
        $expected = $definitions->sum(function ($definition) use ($units) {
            $targets = match ($definition->target_type) {
                'block' => $units->where('block_id', $definition->target_id),
                'unit' => $units->where('id', $definition->target_id),
                default => $units,
            };

            return $definition->calculation === 'sqm' ? (float) $definition->amount * (float) $targets->sum('gross_area') : (float) $definition->amount * $targets->count();
        });
        $rows = $apartment->debts()->whereNotNull('dues_definition_id')->where('period', '>=', now()->subMonthsNoOverflow(5)->format('Y-m'))
            ->groupBy('period')->selectRaw('period, SUM(amount) accrued, SUM(paid_amount) paid')->get()->keyBy('period');
        $current = $rows->get($period);

        return $this->summary([
            $this->metric('Aktif tanım', $this->number($definitions->count())),
            $this->metric('Aylık beklenen', tl($expected)),
            $this->metric('Bu dönem tahakkuk', tl($current->accrued ?? 0)),
            $this->metric('Bu dönem tahsilat', tl($current->paid ?? 0), $this->percent((float) ($current->paid ?? 0), (float) ($current->accrued ?? 0)).' oranında'),
            $this->metric('Son çalıştırma', $definitions->max('last_run_period') ?? '—'),
            $this->metric('Otomatik üretim', $apartment->dues_auto_run ? 'Açık' : 'Kapalı', null, $apartment->dues_auto_run ? 'good' : null),
        ], [
            $this->trend('Son 6 dönem aidat', ['Tahakkuk', 'Tahsilat'], $this->months(6)->map(fn ($m) => ['label' => $m['label'], 'values' => [
                (float) ($rows->get($m['key'])->accrued ?? 0), (float) ($rows->get($m['key'])->paid ?? 0),
            ]])->all()),
        ]);
    }

    public function lateFees(Apartment $apartment): array
    {
        $fees = fn () => $apartment->debts()->where('kind', 'late_fee');
        $accrued = (float) $fees()->sum('amount');
        $collected = (float) $fees()->sum('paid_amount');

        return $this->summary([
            $this->metric('Yazılan zam', tl($accrued), $this->number($fees()->count()).' kalem'),
            $this->metric('Tahsil edilen', tl($collected), $this->percent($collected, $accrued).' oranında'),
            $this->metric('Kalan zam', tl($fees()->where('status', Debt::PENDING)->sum('remaining_amount'))),
            $this->metric('Bu ay yazılan', tl($fees()->where('created_at', '>=', $this->monthStart())->sum('amount'))),
            $this->metric('Zam alan daire', $this->number($fees()->distinct()->count('unit_id'))),
            $this->metric('Otomatik yazım', $apartment->late_fee_auto_run ? 'Açık' : 'Kapalı', null, $apartment->late_fee_auto_run ? 'good' : null),
        ]);
    }

    public function advances(Apartment $apartment): array
    {
        $units = $apartment->units()->with('block')->where('credit_balance', '>', 0)->get();
        $withDebt = $apartment->debts()->where('status', Debt::PENDING)->whereIn('unit_id', $units->pluck('id'))->distinct()->count('unit_id');

        return $this->summary([
            $this->metric('Toplam avans', tl($units->sum('credit_balance'))),
            $this->metric('Avanslı daire', $this->number($units->count())),
            $this->metric('Ortalama avans', tl($units->count() ? $units->sum('credit_balance') / $units->count() : 0)),
            $this->metric('Mahsup edilebilir', $this->number($withDebt), 'Açık borcu olan daire', $withDebt > 0 ? 'warn' : null),
        ], [$this->bars('En yüksek avanslar', $units->mapWithKeys(fn (Unit $u) => [$u->label() => (float) $u->credit_balance]))]);
    }

    public function ledger(Apartment $apartment): array
    {
        $entries = fn () => $apartment->ledgerEntries();

        return $this->summary([
            $this->metric('Kayıt sayısı', $this->number($entries()->count())),
            $this->metric('Bu ay borç', tl($entries()->where('created_at', '>=', $this->monthStart())->sum('debit'))),
            $this->metric('Bu ay alacak', tl($entries()->where('created_at', '>=', $this->monthStart())->sum('credit'))),
            $this->metric('Kullanılan hesap', $this->number($entries()->distinct()->count('account_name'))),
        ], [$this->bars('Hesaba göre borç hareketi', $entries()->groupBy('account_name')->selectRaw('account_name, SUM(debit) total')->pluck('total', 'account_name'))]);
    }

    public function incomeExpense(Apartment $apartment, array $data): array
    {
        $income = (float) $data['income']->sum('total');
        $expense = (float) $data['expense']->sum('total');

        return $this->summary([
            $this->metric('Toplam gelir', tl($income), $this->number($data['income']->sum('count')).' hareket'),
            $this->metric('Toplam gider', tl($expense), $this->number($data['expense']->sum('count')).' hareket'),
            $this->metric('Net sonuç', tl($income - $expense), null, $income < $expense ? 'bad' : 'good'),
            $this->metric('Gider / gelir', $this->percent($expense, $income)),
        ], [
            $this->bars('Gelir kaynakları', $data['income']->map(fn ($row) => $row['total'])),
            $this->bars('Gider kalemleri', $data['expense']->map(fn ($row) => $row['total'])),
        ]);
    }

    // ------------------------------------------------------------------ MUHASEBE

    public function trialBalance(Apartment $apartment, array $data): array
    {
        $tb = $data['tb'];
        $difference = round($tb['debit'] - $tb['credit'], 2);

        return $this->summary([
            $this->metric('Hesap grubu', $this->number(count($tb['groups']))),
            $this->metric('Alt hesap', $this->number($tb['rows']->count())),
            $this->metric('Denge farkı', tl($difference), $difference === 0.0 ? 'Mizan dengede' : 'Mizan dengede değil', $difference === 0.0 ? 'good' : 'bad'),
        ], [$this->bars('Hesap grubuna göre borç toplamı', collect($tb['groups'])->map(fn ($group) => $group['debit']))]);
    }

    public function cashBook(Apartment $apartment, array $data): array
    {
        $book = $data['book'];
        if (! $book) {
            return $this->summary();
        }
        $rows = collect($book['rows']);

        return $this->summary([
            $this->metric('Hareket sayısı', $this->number($rows->count())),
            $this->metric('Giriş adedi', $this->number($rows->where('in', '>', 0)->count())),
            $this->metric('Çıkış adedi', $this->number($rows->where('out', '>', 0)->count())),
            $this->metric('Dönem net', tl($book['in'] - $book['out']), null, $book['in'] < $book['out'] ? 'bad' : 'good'),
            $this->metric('En büyük giriş', tl($rows->max('in') ?? 0)),
            $this->metric('En büyük çıkış', tl($rows->max('out') ?? 0)),
        ]);
    }

    public function balanceSheet(Apartment $apartment, array $data): array
    {
        $sheet = $data['sheet'];

        return $this->summary([
            $this->metric('Net varlık', tl($sheet['net']), null, $sheet['net'] < 0 ? 'bad' : 'good'),
            $this->metric('Cari oran', $sheet['liabilities_total'] > 0 ? $this->decimal($sheet['assets_total'] / $sheet['liabilities_total']) : '—', 'Varlık / yükümlülük'),
            $this->metric('Gecikmiş alacak payı', $this->percent((float) $sheet['overdue'], (float) $sheet['receivables'])),
            $this->metric('Nakit / varlık', $this->percent((float) $sheet['cash_total'], (float) $sheet['assets_total'])),
        ], [
            $this->bars('Varlık dağılımı', collect(['Kasa ve banka' => $sheet['cash_total'], 'Alacaklar' => $sheet['receivables'], 'Demirbaşlar' => $sheet['fixed_assets']])),
            $this->bars('Yükümlülükler', collect(['Avanslar' => $sheet['advances'], 'Ödenecekler' => $sheet['payables']])),
        ]);
    }

    public function aging(Apartment $apartment, array $data): array
    {
        $aging = $data['aging'];
        $total = (float) $aging['totals']['total'];
        $late = $total - (float) $aging['totals']['current'];

        return $this->summary([
            $this->metric('Borçlu daire', $this->number($aging['rows']->count())),
            $this->metric('Vadesi geçen pay', $this->percent($late, $total), tl($late)),
            $this->metric('90+ gün payı', $this->percent((float) $aging['totals']['d90p'], $total), null, $aging['totals']['d90p'] > 0 ? 'bad' : 'good'),
            $this->metric('İcradaki daire', $this->number($aging['rows']->where('legal', true)->count())),
            $this->metric('Daire başı ortalama', tl($aging['rows']->count() ? $total / $aging['rows']->count() : 0)),
        ], [$this->bars('Vade dağılımı', collect($aging['buckets'])->mapWithKeys(fn ($label, $key) => [$label => $aging['totals'][$key]]))]);
    }

    public function budget(Apartment $apartment, array $data): array
    {
        $t = $data['budget']['totals'];
        $over = $data['budget']['expense']->filter(fn ($row) => $row['pct'] !== null && $row['pct'] > 100);

        return $this->summary([
            $this->metric('Gelir gerçekleşme', $this->percent((float) $t['income_actual'], (float) $t['income_planned'])),
            $this->metric('Gider gerçekleşme', $this->percent((float) $t['expense_actual'], (float) $t['expense_planned'])),
            $this->metric('Planlanan net', tl($t['income_planned'] - $t['expense_planned'])),
            $this->metric('Gerçekleşen net', tl($t['income_actual'] - $t['expense_actual']), null, $t['income_actual'] < $t['expense_actual'] ? 'bad' : 'good'),
            $this->metric('Bütçeyi aşan kalem', $this->number($over->count()), $over->pluck('category.name')->first(), $over->isNotEmpty() ? 'bad' : 'good'),
        ], [
            $this->bars('Gider gerçekleşme oranları', $data['budget']['expense']->filter(fn ($row) => $row['pct'] !== null)->mapWithKeys(fn ($row) => [$row['category']->name => $row['pct']]), 'percent'),
            $this->bars('Gelir gerçekleşme oranları', $data['budget']['income']->filter(fn ($row) => $row['pct'] !== null)->mapWithKeys(fn ($row) => [$row['category']->name => $row['pct']]), 'percent'),
        ]);
    }

    // ------------------------------------------------------------------ OPERASYON

    public function tasks(Apartment $apartment): array
    {
        $tasks = fn () => $apartment->tasks();
        $total = $tasks()->count();
        $done = $tasks()->where('status', 'completed')->count();
        $overdue = $this->overdueTasks($apartment)->count();
        $byStaff = $tasks()->where('tasks.status', '!=', 'completed')->join('staff', 'staff.id', '=', 'tasks.staff_id')->groupBy('staff.name')
            ->selectRaw('staff.name, COUNT(*) total')->pluck('total', 'name');

        return $this->summary([
            $this->metric('Açık görev', $this->number($total - $done)),
            $this->metric('Geciken', $this->number($overdue), null, $overdue > 0 ? 'bad' : 'good'),
            $this->metric('Yüksek öncelik', $this->number($tasks()->where('status', '!=', 'completed')->where('priority', 'high')->count())),
            $this->metric('Bu ay tamamlanan', $this->number($tasks()->where('completed_at', '>=', $this->monthStart())->count())),
            $this->metric('Tamamlanma oranı', $this->percent($done, $total)),
            $this->metric('Atanmamış', $this->number($tasks()->where('status', '!=', 'completed')->whereNull('staff_id')->count())),
        ], [
            $this->bars('Kategoriye göre açık görev', $this->labelled($tasks()->where('status', '!=', 'completed')->groupBy('category')->selectRaw('category, COUNT(*) total')->pluck('total', 'category'), Task::CATEGORIES), 'count'),
            $this->bars('Personele göre açık görev', $byStaff, 'count'),
        ]);
    }

    public function tickets(Apartment $apartment): array
    {
        $tickets = fn () => $apartment->tickets();
        $total = $tickets()->count();
        $closed = $tickets()->whereIn('status', ['resolved', 'closed']);
        $hours = (clone $closed)->latest('id')->limit(200)->get(['created_at', 'updated_at'])->avg(fn ($t) => $t->created_at->diffInHours($t->updated_at));
        $open = fn () => $tickets()->whereIn('status', ['open', 'in_progress']);
        $urgent = $open()->where('priority', 'high')->count();

        return $this->summary([
            $this->metric('Açık talep', $this->number($open()->count())),
            $this->metric('Acil açık', $this->number($urgent), null, $urgent > 0 ? 'bad' : 'good'),
            $this->metric('Bu ay açılan', $this->number($tickets()->where('created_at', '>=', $this->monthStart())->count())),
            $this->metric('Çözülme oranı', $this->percent((clone $closed)->count(), $total)),
            $this->metric('Ortalama çözüm', $hours === null ? '—' : $this->number((int) round($hours)).' saat', 'Son 200 talep'),
            $this->metric('Toplam talep', $this->number($total)),
        ], [
            $this->bars('Kategoriye göre talep', $tickets()->groupBy('category')->selectRaw('category, COUNT(*) total')->pluck('total', 'category'), 'count'),
            $this->bars('Önceliğe göre açık talep', $this->labelled($open()->groupBy('priority')->selectRaw('priority, COUNT(*) total')->pluck('total', 'priority'), Ticket::PRIORITIES), 'count'),
        ]);
    }

    public function security(Apartment $apartment): array
    {
        $entries = fn () => $apartment->securityEntries();
        $open = fn () => $entries()->whereIn('status', ['beklemede', 'aktif']);

        return $this->summary([
            $this->metric('Bugün giriş', $this->number($entries()->whereDate('created_at', today())->count())),
            $this->metric('Bu ay giriş', $this->number($entries()->where('created_at', '>=', $this->monthStart())->count())),
            $this->metric('Bekleyen kargo', $this->number($open()->where('kind', 'cargo')->count())),
            $this->metric('Aktif araç', $this->number($open()->where('kind', 'parking')->count())),
            $this->metric('İçerideki ziyaretçi', $this->number($open()->where('kind', 'visitor')->count())),
        ], [
            $this->bars('Bu ay kayıt türleri', $this->labelled($entries()->where('created_at', '>=', $this->monthStart())->groupBy('kind')->selectRaw('kind, COUNT(*) total')->pluck('total', 'kind'), SecurityEntry::KINDS), 'count'),
            $this->bars('Kargo firmaları', $entries()->where('kind', 'cargo')->whereNotNull('company')->groupBy('company')->selectRaw('company, COUNT(*) total')->pluck('total', 'company'), 'count'),
        ]);
    }

    public function staff(Apartment $apartment): array
    {
        $active = $apartment->staff()->where('status', 'Aktif')->get();
        $salaried = $active->where('salary', '>', 0);

        return $this->summary([
            $this->metric('Aktif personel', $this->number($active->count())),
            $this->metric('Aylık maaş yükü', tl($salaried->sum('salary'))),
            $this->metric('Ortalama maaş', tl($salaried->count() ? $salaried->sum('salary') / $salaried->count() : 0)),
            $this->metric('Açık iş', $this->number($apartment->tasks()->where('status', '!=', 'completed')->whereNotNull('staff_id')->count())),
        ], [
            $this->bars('Göreve göre personel', $active->countBy('role'), 'count'),
            $this->bars('Göreve göre maaş', $active->groupBy('role')->map(fn ($group) => (float) $group->sum('salary'))),
        ]);
    }

    public function inventory(Apartment $apartment): array
    {
        $items = $apartment->inventoryItems()->get();
        $low = $items->filter->isLow();
        $empty = $items->where('quantity', 0);

        return $this->summary([
            $this->metric('Stok kalemi', $this->number($items->count())),
            $this->metric('Kritik seviye', $this->number($low->count()), null, $low->isNotEmpty() ? 'warn' : 'good'),
            $this->metric('Tükenen', $this->number($empty->count()), null, $empty->isNotEmpty() ? 'bad' : 'good'),
            $this->metric('Toplam adet', $this->number($items->sum('quantity'))),
            $this->metric('Sağlıklı oran', $this->percent($items->count() - $low->count(), $items->count())),
        ], [
            $this->bars('Kategoriye göre kalem', $items->countBy('category'), 'count'),
            $this->bars('Kritik kalemler (kalan)', $low->pluck('quantity', 'name'), 'count'),
        ]);
    }

    public function meters(Apartment $apartment): array
    {
        $meters = $apartment->meters()->get();
        $readings = fn () => DB::table('meter_readings')->join('meters', 'meters.id', '=', 'meter_readings.meter_id')->where('meters.apartment_id', $apartment->id);
        $readThisMonth = $readings()->whereDate('read_on', '>=', $this->monthStart())->distinct()->count('meter_id');
        $missing = $meters->count() - $readThisMonth;

        return $this->summary([
            $this->metric('Sayaç', $this->number($meters->count())),
            $this->metric('Bu ay okunan', $this->number($readThisMonth)),
            $this->metric('Okunmayı bekleyen', $this->number(max(0, $missing)), null, $missing > 0 ? 'warn' : 'good'),
            $this->metric('Toplam okuma', $this->number($readings()->count())),
        ], [
            $this->bars('Türe göre sayaç', $meters->countBy('type'), 'count'),
            $this->bars('Son 12 ay tüketim', $readings()->whereDate('read_on', '>=', $this->monthStart(11))->groupBy('meters.type')
                ->selectRaw('meters.type, SUM(consumption) total')->pluck('total', 'type'), 'count'),
        ]);
    }

    public function meterBills(Apartment $apartment): array
    {
        $bills = fn () => $apartment->meterBills();

        return $this->summary([
            $this->metric('Dağıtılan fatura', $this->number($bills()->count())),
            $this->metric('Toplam tutar', tl($bills()->sum('total_amount'))),
            $this->metric('Bu yıl', tl($bills()->whereYear('period_to', now()->year)->sum('total_amount'))),
            $this->metric('Son fatura', $bills()->max('period_to') ? date('d.m.Y', strtotime((string) $bills()->max('period_to'))) : '—'),
        ], [$this->bars('Türe göre fatura', $bills()->groupBy('meter_type')->selectRaw('meter_type, SUM(total_amount) total')->pluck('total', 'meter_type'))]);
    }

    public function payroll(Apartment $apartment, array $data): array
    {
        $salaries = fn () => $apartment->expenses()->whereNotNull('staff_id');
        $rows = $salaries()->where('period', '>=', now()->subMonthsNoOverflow(5)->format('Y-m'))->where('status', 'approved')
            ->groupBy('period')->selectRaw('period, SUM(total_amount) total')->pluck('total', 'period');
        $yearPaid = (float) $salaries()->where('status', 'approved')->where('period', 'like', now()->year.'-%')->sum('total_amount');
        $pending = $salaries()->where('status', 'pending');

        return $this->summary([
            $this->metric('Bu yıl ödenen', tl($yearPaid)),
            $this->metric('Ödeme bekleyen', tl((clone $pending)->sum('total_amount')), $this->number((clone $pending)->count()).' bordro', (clone $pending)->exists() ? 'warn' : null),
            $this->metric('Aylık ortalama', tl($yearPaid / max(1, now()->month))),
            $this->metric('Dönemde personel', $this->number($data['rows']->count()), $data['period']),
        ], [$this->trend('Son 6 ay ödenen maaş', ['Maaş'], $this->months(6)->map(fn ($m) => ['label' => $m['label'], 'values' => [(float) ($rows[$m['key']] ?? 0)]])->all())]);
    }

    public function assets(Apartment $apartment): array
    {
        $assets = $apartment->assets()->get();
        $active = $assets->where('status', 'active');
        $cost = (float) $active->sum('cost');
        $depreciation = $active->sum(fn (Asset $a) => $a->accumulatedDepreciation()) / 100;

        return $this->summary([
            $this->metric('Kullanımda', $this->number($active->count())),
            $this->metric('Elden çıkarılan', $this->number($assets->count() - $active->count())),
            $this->metric('Amortisman oranı', $this->percent($depreciation, $cost)),
            $this->metric('Ömrü dolan', $this->number($active->filter(fn (Asset $a) => $a->purchase_date->copy()->addYears($a->useful_life_years)->isPast())->count())),
        ], [$this->bars('Kategoriye göre net değer', $active->groupBy('category')->map(fn ($group) => $group->sum(fn (Asset $a) => $a->bookValue()) / 100))]);
    }

    public function documents(Apartment $apartment): array
    {
        $documents = fn () => $apartment->documents();
        $expired = $documents()->whereNotNull('expires_on')->whereDate('expires_on', '<', today())->count();
        $soon = $documents()->whereNotNull('expires_on')->whereBetween('expires_on', [today()->toDateString(), today()->addDays(30)->toDateString()])->count();

        return $this->summary([
            $this->metric('Belge', $this->number($documents()->count())),
            $this->metric('Kullanılan alan', $this->decimal($documents()->sum('size') / 1048576).' MB'),
            $this->metric('Süresi dolan', $this->number($expired), null, $expired > 0 ? 'bad' : 'good'),
            $this->metric('30 günde dolacak', $this->number($soon), null, $soon > 0 ? 'warn' : null),
            $this->metric('Bu ay yüklenen', $this->number($documents()->where('created_at', '>=', $this->monthStart())->count())),
        ], [$this->bars('Kategoriye göre belge', $documents()->groupBy('category')->selectRaw('category, COUNT(*) total')->pluck('total', 'category'), 'count')]);
    }

    public function reservations(Apartment $apartment): array
    {
        $reservations = fn () => $apartment->reservations();
        $approved = fn () => $reservations()->where('reservations.status', 'approved');
        $pending = $reservations()->where('status', 'pending')->count();

        return $this->summary([
            $this->metric('Onay bekleyen', $this->number($pending), null, $pending > 0 ? 'warn' : null),
            $this->metric('Yaklaşan', $this->number($approved()->whereDate('date', '>=', today())->count())),
            $this->metric('Bu ay', $this->number($reservations()->whereDate('date', '>=', $this->monthStart())->whereDate('date', '<=', $this->monthEnd())->count())),
            $this->metric('Ücret geliri', tl($approved()->sum('fee'))),
            $this->metric('Aktif alan', $this->number($apartment->commonAreas()->where('is_active', true)->count())),
        ], [
            $this->bars('Alana göre rezervasyon', $approved()->join('common_areas', 'common_areas.id', '=', 'reservations.common_area_id')->groupBy('common_areas.name')
                ->selectRaw('common_areas.name, COUNT(*) total')->pluck('total', 'name'), 'count'),
            $this->bars('Duruma göre', $this->labelled($reservations()->groupBy('status')->selectRaw('status, COUNT(*) total')->pluck('total', 'status'), Reservation::STATUSES), 'count'),
        ]);
    }

    // ------------------------------------------------------------------ İLETİŞİM

    public function announcements(Apartment $apartment): array
    {
        $announcements = fn () => $apartment->announcements();
        $live = $announcements()->live()->count();

        return $this->summary([
            $this->metric('Yayında', $this->number($live)),
            $this->metric('Yayında olmayan', $this->number($announcements()->count() - $live)),
            $this->metric('Bu ay eklenen', $this->number($announcements()->where('created_at', '>=', $this->monthStart())->count())),
            $this->metric('Toplam', $this->number($announcements()->count())),
        ]);
    }

    public function polls(Apartment $apartment): array
    {
        $polls = $apartment->polls()->withCount('votes')->latest('id')->get();
        $units = $apartment->units()->count();
        $participation = $polls->avg('votes_count');

        return $this->summary([
            $this->metric('Açık anket', $this->number($polls->filter->isOpen()->count())),
            $this->metric('Toplam anket', $this->number($polls->count())),
            $this->metric('Toplam oy', $this->number($polls->sum('votes_count'))),
            $this->metric('Ortalama katılım', $units ? $this->percent((float) $participation, $units) : '—', "{$units} daire üzerinden"),
        ], [$this->bars('Son anketlerde katılım', $polls->mapWithKeys(fn ($poll) => [$poll->question => $units ? round($poll->votes_count / $units * 100, 1) : 0]), 'percent')]);
    }

    public function decisions(Apartment $apartment): array
    {
        $decisions = fn () => $apartment->decisions();
        $years = $decisions()->get(['decided_on'])->countBy(fn ($d) => $d->decided_on->format('Y'))->sortKeysDesc();

        return $this->summary([
            $this->metric('Toplam karar', $this->number($decisions()->count())),
            $this->metric('Bu yıl', $this->number($years[now()->format('Y')] ?? 0)),
            $this->metric('Toplantıya bağlı', $this->number($decisions()->whereNotNull('meeting_id')->count())),
        ], [$this->bars('Yıllara göre karar', $years, 'count')]);
    }

    public function meetings(Apartment $apartment): array
    {
        $meetings = $apartment->meetings()->withCount('attendances')->get();
        $held = $meetings->where('status', 'held');
        $units = $apartment->units()->count();
        $next = $meetings->where('status', 'planned')->filter(fn ($m) => $m->meets_at->isFuture())->sortBy('meets_at')->first();

        return $this->summary([
            $this->metric('Planlanan', $this->number($meetings->where('status', 'planned')->count())),
            $this->metric('Yapılan', $this->number($held->count())),
            $this->metric('Sonraki toplantı', $next?->meets_at->format('d.m.Y') ?? '—'),
            $this->metric('Ortalama katılım', $units && $held->isNotEmpty() ? $this->percent((float) $held->avg('attendances_count'), $units) : '—'),
            $this->metric('Bu yıl karar', $this->number($apartment->decisions()->whereYear('decided_on', now()->year)->count())),
        ]);
    }

    public function notifications(Apartment $apartment): array
    {
        $notifications = fn () => $apartment->siteNotifications();

        return $this->summary([
            $this->metric('Gönderilen', $this->number($notifications()->count())),
            $this->metric('Son 30 gün', $this->number($notifications()->where('created_at', '>=', now()->subDays(30))->count())),
            $this->metric('Okunma', $this->number(DB::table('notification_reads')->whereIn('notification_id', $notifications()->select('id'))->count()), 'Toplam okuma'),
            $this->metric('Tüm siteye giden', $this->number($notifications()->whereNull('unit_id')->count())),
        ], [$this->bars('Türe göre bildirim', $notifications()->groupBy('type')->selectRaw('type, COUNT(*) total')->pluck('total', 'type'), 'count')]);
    }

    public function reminders(Apartment $apartment): array
    {
        $overdue = $this->reports->overdueByUnit($apartment);
        $reachable = $overdue->filter(fn ($row) => $row['unit']->members->contains(fn (Member $m) => filled($m->email)));

        return $this->summary([
            $this->metric('Gecikmiş daire', $this->number($overdue->count())),
            $this->metric('Gecikmiş toplam', tl($overdue->sum('total')), null, $overdue->isNotEmpty() ? 'bad' : 'good'),
            $this->metric('Daire başı ortalama', tl($overdue->count() ? $overdue->sum('total') / $overdue->count() : 0)),
            $this->metric('E-posta ulaşılabilir', $this->number($reachable->count()), ($overdue->count() - $reachable->count()).' dairede e-posta yok'),
            $this->metric('Bu ay hatırlatma', $this->number($apartment->siteNotifications()->where('title', 'Ödeme Hatırlatması')->where('created_at', '>=', $this->monthStart())->count())),
        ]);
    }

    // ------------------------------------------------------------------ YARDIMCILAR

    private function overdueTasks(Apartment $apartment): HasMany
    {
        return $apartment->tasks()->where('status', '!=', 'completed')->whereNotNull('due_date')->whereDate('due_date', '<', today());
    }

    /** @return Collection<string, float> kategori adı => bu yılki gider */
    private function expenseByCategory(Apartment $apartment): Collection
    {
        return $apartment->cashTransactions()->operating()->where('cash_transactions.type', 'expense')
            ->where('cash_transactions.created_at', '>=', now()->startOfYear())
            ->leftJoin('transaction_categories as c', 'c.id', '=', 'cash_transactions.category_id')
            ->groupBy('c.name')->selectRaw("COALESCE(c.name, 'Kategorisiz') as category, SUM(cash_transactions.amount) total")
            ->pluck('total', 'category');
    }

    private function topDebtors(Apartment $apartment): array
    {
        return $this->bars('En borçlu daireler (gecikmiş)', $this->reports->overdueByUnit($apartment)->mapWithKeys(fn ($row) => [$row['unit']->label() => $row['total']]));
    }

    /** @param  array<string, string>  $labels  anahtar => görünen ad */
    private function labelled(Collection $values, array $labels): Collection
    {
        return $values->mapWithKeys(fn ($value, $key) => [$labels[$key] ?? $key => $value]);
    }

    private function summary(array $metrics = [], array $panels = []): array
    {
        return ['metrics' => $metrics, 'panels' => $panels];
    }

    private function metric(string $label, string $value, ?string $hint = null, ?string $tone = null): array
    {
        return ['label' => $label, 'value' => $value, 'hint' => $hint, 'tone' => $tone];
    }

    /** En büyük $limit değeri yatay çubuk olarak döner. @param 'money'|'count'|'percent' $format */
    private function bars(string $title, Collection $values, string $format = 'money', int $limit = 6): array
    {
        $values = $values->map(fn ($value) => (float) $value)->filter(fn (float $value) => $value > 0)->sortDesc()->take($limit);
        $peak = max(1, $format === 'percent' ? 100 : (float) $values->max());

        return ['type' => 'bars', 'title' => $title, 'rows' => $values->map(fn (float $value, $label) => [
            'label' => (string) $label,
            'display' => match ($format) {
                'count' => $this->number((int) round($value)),
                'percent' => '%'.$this->decimal($value),
                default => tl($value),
            },
            'share' => min(100, round($value / $peak * 100, 1)),
        ])->values()->all()];
    }

    /** @param list<array{label:string, values:list<float>}> $months */
    private function trend(string $title, array $series, array $months, string $format = 'money'): array
    {
        return ['type' => 'trend', 'title' => $title, 'series' => $series, 'months' => array_values($months), 'format' => $format];
    }

    private function monthlyTrend(string $title, array $months): array
    {
        return $this->trend($title, ['Gelir', 'Gider'], array_map(fn ($m) => ['label' => $m['label'], 'values' => [$m['income'], $m['expense']]], $months));
    }

    /** @return Collection<int, array{key:string, label:string}> eskiden yeniye */
    private function months(int $count): Collection
    {
        return collect(range($count - 1, 0))->map(function (int $back) {
            $month = now()->startOfMonth()->subMonths($back);

            return ['key' => $month->format('Y-m'), 'label' => $month->locale('tr')->translatedFormat('M y')];
        });
    }

    private function monthStart(int $monthsAgo = 0): string
    {
        return now()->startOfMonth()->subMonths($monthsAgo)->toDateString();
    }

    private function monthEnd(int $monthsAgo = 0): string
    {
        return now()->startOfMonth()->subMonths($monthsAgo)->endOfMonth()->toDateString();
    }

    private function number(int|float $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private function decimal(float $value): string
    {
        return number_format($value, 1, ',', '.');
    }

    private function percent(float|int $part, float|int $whole): string
    {
        return $whole > 0 ? '%'.$this->decimal($part / $whole * 100) : '—';
    }

    private function change(float $current, float $previous): string
    {
        if ($previous <= 0) {
            return 'Geçen ay veri yok';
        }
        $change = ($current - $previous) / $previous * 100;

        return 'Geçen aya göre '.($change >= 0 ? '+' : '').$this->decimal($change).'%';
    }
}
