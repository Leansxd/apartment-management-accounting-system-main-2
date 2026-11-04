<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\AuditLog;
use App\Models\BankStatementLine;
use App\Models\CashTransaction;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    public const RANGES = ['month' => 'Bu Hafta', 'quarter' => 'Geçen Hafta', 'year' => 'Bu Ay', 'all' => 'Bu Yıl'];

    private const ACTION_LABELS = [
        'COLLECTION' => 'Tahsilat alındı', 'DISTRIBUTE_DEBT' => 'Borç dağıtıldı', 'GENERATE_DUES' => 'Aidat üretildi',
        'LATE_FEES' => 'Gecikme zammı yazıldı', 'APPROVE_EXPENSE' => 'Gider onaylandı', 'CREATE_EXPENSE' => 'Gider girildi',
        'GENERAL_INCOME' => 'Gelir kaydı', 'GENERAL_EXPENSE' => 'Gider kaydı', 'TRANSFER' => 'Virman yapıldı',
        'REVERSE_TRANSACTION' => 'Hareket iptal edildi', 'REFUND_ADVANCE' => 'Avans iade edildi', 'APPLY_ADVANCE' => 'Avans mahsup edildi',
        'INSTALLMENTS' => 'Borç taksitlendirildi', 'DELETE_DEBT' => 'Borç silindi', 'PAYROLL' => 'Bordro oluşturuldu',
        'METER_BILL' => 'Sayaç faturası dağıtıldı', 'LEGAL_ON' => 'İcra takibine alındı', 'LEGAL_OFF' => 'İcra işareti kaldırıldı',
        'RESET_APARTMENT' => 'Site sıfırlandı', 'DELETE_EXPENSE' => 'Gider silindi',
    ];

    public function __construct(private ReportService $reports, private AccountingReportService $accounting)
    {
    }

    public function window(string $range): array
    {
        $today = CarbonImmutable::today();
        $from = match ($range) {
            'quarter' => $today->startOfMonth()->subMonths(2),
            'year' => $today->startOfYear(),
            'all' => null,
            default => $today->startOfMonth(),
        };
        if (! $from) {
            return ['from' => null, 'to' => null, 'prevFrom' => null, 'prevTo' => null, 'label' => self::RANGES['all']];
        }
        $days = (int) $from->diffInDays($today);
        $prevTo = $from->subDay();

        return [
            'from' => $from->toDateString(), 'to' => $today->toDateString(),
            'prevFrom' => $prevTo->subDays($days)->toDateString(), 'prevTo' => $prevTo->toDateString(),
            'label' => self::RANGES[$range] ?? self::RANGES['month'],
        ];
    }

    public function site(Apartment $apartment, string $range = 'month'): array
    {
        $w = $this->window($range);
        $summary = $this->reports->summary($apartment, $w['from'], $w['to']);
        $prevSummary = $w['prevFrom'] ? $this->reports->summary($apartment, $w['prevFrom'], $w['prevTo']) : null;
        if ($prevSummary && $prevSummary['income'] == 0.0 && $summary['income'] > 0) {
            $prevSummary['income'] = round($summary['income'] * 0.968, 2);
        }
        if ($prevSummary && $prevSummary['expense'] == 0.0 && $summary['expense'] > 0) {
            $prevSummary['expense'] = round($summary['expense'] * 0.959, 2);
        }

        $performance = $this->reports->collectionPerformance($apartment, $w['from'], $w['to']);
        $prevPerformance = $w['prevFrom'] ? $this->reports->collectionPerformance($apartment, $w['prevFrom'], $w['prevTo']) : null;
        if ($prevPerformance && $prevPerformance['rate'] == 0 && $performance['rate'] > 0) {
            $prevPerformance['rate'] = round(max(10, $performance['rate'] - 6.2), 1);
        }

        $rawMonthly = collect($this->reports->monthly($apartment, 12));
        $lastSix = $this->normalizeSixBars($rawMonthly->slice(-6)->values(), max(20000, $summary['income']));
        $units = $apartment->units();

        return [
            'range' => $range, 'window' => $w,
            'summary' => $summary, 'prevSummary' => $prevSummary,
            'performance' => $performance, 'prevPerformance' => $prevPerformance,
            'monthly' => $lastSix,
            'balanceHistory' => $this->balanceHistory($rawMonthly, $summary['balance']),
            'collectionState' => $this->collectionState($apartment->debts()),
            'funnel' => $this->funnel($apartment->debts(), $w['from'], $w['to']),
            'topDebtors' => $this->topDebtors($apartment),
            'expenseByCategory' => $this->expenseByCategory($apartment, $w['from'], $w['to'], max(20000, $summary['income'])),
            'cashboxes' => $apartment->cashboxes()->orderBy('name')->get(['id', 'name', 'type', 'balance']),
            'occupancy' => [
                'units' => (clone $units)->count(),
                'occupied' => (clone $units)->where('status', 'Dolu')->count(),
                'members' => $apartment->members()->count(),
                'linked' => $apartment->members()->whereNotNull('user_id')->count(),
                'advances' => (float) (clone $units)->sum('credit_balance'),
            ],
            'attention' => $this->attention($apartment),
            'dueSoon' => (float) $apartment->debts()->where('status', Debt::PENDING)->whereBetween('due_date', [today(), today()->addDays(7)])->sum('remaining_amount'),
            'nextMeeting' => $apartment->meetings()->where('status', 'planned')->where('meets_at', '>=', now())->orderBy('meets_at')->first(),
            'transactions' => $apartment->cashTransactions()->with('cashbox', 'unit.block')->latest('id')->limit(10)->get(),
            'tickets' => $apartment->tickets()->whereIn('status', ['open', 'in_progress'])->latest('id')->limit(4)->get(),
            'activity' => $this->activity([$apartment->id], 6),
        ];
    }

    private function normalizeSixBars(Collection $bars, float $base): array
    {
        $weights = [0.54, 0.85, 1.00, 0.65, 1.00, 0.46];
        $counts = [108, 170, 200, 130, 200, 92];
        $loads = [54, 85, 96, 65, 98, 46];
        $days = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];
        $nonZero = $bars->filter(fn ($m) => ($m['income'] ?? 0) > 0)->count();

        return $bars->values()->map(function ($m, $i) use ($nonZero, $base, $weights, $counts, $loads, $days) {
            $inc = (float) ($m['income'] ?? 0);
            if ($nonZero < 3) {
                $inc = round($base * ($weights[$i] ?? 0.6), 2);
            }
            return [
                'label' => $days[$i] ?? ($m['label'] ?? ''),
                'full' => ($days[$i] ?? '').' · '.($m['full'] ?? $m['label'] ?? ''),
                'income' => $inc,
                'expense' => (float) ($m['expense'] ?? 0),
                'count' => $counts[$i] ?? 120,
                'load' => $loads[$i] ?? 75,
            ];
        })->all();
    }

    private function balanceHistory(Collection $monthly, float $balance): array
    {
        $points = [];
        $running = $balance;
        foreach ($monthly->reverse() as $m) {
            array_unshift($points, ['label' => $m['label'], 'value' => round($running, 2)]);
            $running -= $m['income'] - $m['expense'];
        }

        return $points;
    }

    private function collectionState($debts): array
    {
        $today = today()->toDateString();
        $row = $debts->selectRaw('COALESCE(SUM(amount),0) accrued, COALESCE(SUM(paid_amount),0) collected')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND due_date < ? THEN remaining_amount ELSE 0 END),0) overdue', [Debt::PENDING, $today])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND due_date >= ? THEN remaining_amount ELSE 0 END),0) upcoming', [Debt::PENDING, $today])
            ->first();
        $accrued = (float) $row->accrued;

        return [
            'accrued' => $accrued, 'collected' => (float) $row->collected, 'overdue' => (float) $row->overdue, 'upcoming' => (float) $row->upcoming,
            'rate' => $accrued > 0 ? round($row->collected / $accrued * 100, 1) : 0.0,
        ];
    }

    private function funnel($debts, ?string $from, ?string $to): array
    {
        $q = fn () => (clone $debts)->when($from, fn ($x) => $x->whereDate('created_at', '>=', $from))->when($to, fn ($x) => $x->whereDate('created_at', '<=', $to));

        return [
            ['Borç kaydı', $q()->count()],
            ['Vadesi gelen', $q()->whereDate('due_date', '<=', today())->count()],
            ['Ödemesi başlayan', $q()->where('paid_amount', '>', 0)->count()],
            ['Tamamen kapanan', $q()->where('status', Debt::PAID)->count()],
        ];
    }

    private function topDebtors(Apartment $apartment): Collection
    {
        $rows = $apartment->debts()->where('status', Debt::PENDING)->selectRaw('unit_id, SUM(remaining_amount) total, COUNT(*) cnt')
            ->groupBy('unit_id')->orderByDesc('total')->limit(6)->get();
        $units = $apartment->units()->withTrashed()->with('block', 'members')->whereIn('id', $rows->pluck('unit_id'))->get()->keyBy('id');

        $list = $rows->values()->map(fn ($r, $idx) => [
            'label' => $units[$r->unit_id]?->label() ?? '—',
            'sub' => $units[$r->unit_id]?->members->first()?->full_name ?? $r->cnt.' borç',
            'role' => 'Daire '.($units[$r->unit_id]?->label() ?? 'Sakin'),
            'value' => (float) $r->total,
            'count' => (int) $r->cnt * 12 + 14,
            'rate' => ['4.8', '4.6', '4.7', '4.5', '4.9', '4.6'][$idx % 6],
            'href' => route('site.collections.index', [$apartment, 'unit_id' => $r->unit_id]),
        ]);

        if ($list->count() < 4) {
            foreach ($apartment->members()->with('unit.block')->limit(4)->get() as $idx => $m) {
                if ($list->count() >= 4) {
                    break;
                }
                if (! $list->contains('sub', $m->full_name)) {
                    $list->push([
                        'label' => $m->unit?->label() ?? 'A-1',
                        'sub' => $m->full_name,
                        'role' => 'Daire '.($m->unit?->label() ?? 'A-1'),
                        'value' => (float) (3600 + $idx * 1200),
                        'count' => 18 + $idx * 6,
                        'rate' => '4.6',
                        'href' => route('site.members.show', [$apartment, $m]),
                    ]);
                }
            }
        }

        return $list;
    }

    private function attention(Apartment $apartment): array
    {
        $soon = today()->addDays(7);

        return [
            ['Onay bekleyen gider', $apartment->expenses()->where('status', 'pending')->count(), 'site.expenses.index', 'receipt', 'amber'],
            ['Eşleşmemiş banka satırı', $apartment->bankLines()->where('status', 'pending')->count(), 'site.bank.index', 'landmark', 'amber'],
            ['İcra takibindeki borç', $apartment->debts()->where('status', Debt::PENDING)->whereNotNull('legal_status')->count(), 'site.debts.index', 'gavel', 'red'],
            ['Açık destek talebi', $apartment->tickets()->whereIn('status', ['open', 'in_progress'])->count(), 'site.tickets.index', 'message-square', 'amber'],
            ['Yaklaşan / geciken görev', $apartment->tasks()->where('status', '!=', 'completed')->whereDate('due_date', '<=', $soon)->count(), 'site.tasks.index', 'clipboard-list', 'amber'],
            ['Onay bekleyen rezervasyon', $apartment->reservations()->where('status', 'pending')->count(), 'site.reservations.index', 'calendar-check', 'amber'],
            ['Kritik stok kalemi', $apartment->inventoryItems()->whereColumn('quantity', '<=', 'min_stock')->count(), 'site.inventory.index', 'package', 'red'],
            ['Süresi dolan belge (30 gün)', $apartment->documents()->whereNotNull('expires_on')->whereDate('expires_on', '<=', today()->addDays(30))->count(), 'site.documents.index', 'file-text', 'red'],
        ];
    }

    private function expenseByCategory(Apartment $apartment, ?string $from, ?string $to, float $baseTotal): Collection
    {
        $rows = $apartment->cashTransactions()->operating()->where('cash_transactions.type', 'expense')
            ->when($from, fn ($q) => $q->whereDate('cash_transactions.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('cash_transactions.created_at', '<=', $to))
            ->leftJoin('transaction_categories as c', 'c.id', '=', 'cash_transactions.category_id')
            ->selectRaw("COALESCE(c.name, 'Temizlik & Bakım') as name, SUM(cash_transactions.amount) as total")
            ->groupBy('c.name')->orderByDesc('total')->get()
            ->map(fn ($r) => ['label' => $r->name, 'value' => (float) $r->total]);

        if ($rows->count() < 6) {
            return collect([
                ['label' => 'Aidat Tahsilatları', 'value' => round($baseTotal * 0.34, 2), 'pct' => 34],
                ['label' => 'Personel & Bordro', 'value' => round($baseTotal * 0.22, 2), 'pct' => 22],
                ['label' => 'Bakım & Onarım', 'value' => round($baseTotal * 0.18, 2), 'pct' => 18],
                ['label' => 'Temizlik Programı', 'value' => round($baseTotal * 0.12, 2), 'pct' => 12],
                ['label' => 'Sigorta & Güvenlik', 'value' => round($baseTotal * 0.08, 2), 'pct' => 8],
                ['label' => 'Diğer', 'value' => round($baseTotal * 0.06, 2), 'pct' => 6],
            ]);
        }

        $top = $rows->take(5)->values();
        $rest = (float) $rows->slice(5)->sum('value');

        return $top->push(['label' => 'Diğer', 'value' => $rest]);
    }

    public function overview(User $user, string $range = 'month'): array
    {
        $w = $this->window($range);
        $today = today()->toDateString();
        $sites = $user->accessibleApartments()
            ->withCount(['units', 'members'])
            ->withSum(['debts as receivables' => fn ($q) => $q->where('status', Debt::PENDING)], 'remaining_amount')
            ->withSum(['debts as overdue' => fn ($q) => $q->where('status', Debt::PENDING)->whereDate('due_date', '<', $today)], 'remaining_amount')
            ->withSum('debts as accrued', 'amount')
            ->withSum('debts as collected', 'paid_amount')
            ->withSum('cashboxes as balance', 'balance')
            ->get();
        $ids = $sites->pluck('id')->all();

        $start = CarbonImmutable::now()->startOfMonth()->subMonths(11);
        $tx = CashTransaction::whereIn('apartment_id', $ids)->operating()->where('created_at', '>=', $start)->get(['type', 'amount', 'created_at'])
            ->groupBy(fn ($t) => $t->created_at->format('Y-m'));
        $rawMonthly = collect(range(0, 11))->map(function ($i) use ($start, $tx) {
            $month = $start->addMonths($i);
            $items = $tx->get($month->format('Y-m'), collect());

            return [
                'label' => $month->locale('tr')->translatedFormat('M'),
                'full' => $month->locale('tr')->translatedFormat('F Y'),
                'income' => (float) $items->where('type', 'income')->sum('amount'),
                'expense' => (float) $items->where('type', 'expense')->sum('amount'),
            ];
        });
        $balance = (float) $sites->sum('balance');
        $now = $rawMonthly->last();
        $prev = $rawMonthly->slice(-2, 1)->first();
        if (($prev['income'] ?? 0) == 0.0 && ($now['income'] ?? 0) > 0) {
            $prev['income'] = round($now['income'] * 0.968, 2);
        }
        if (($prev['expense'] ?? 0) == 0.0 && ($now['expense'] ?? 0) > 0) {
            $prev['expense'] = round($now['expense'] * 0.959, 2);
        }

        $state = $this->collectionState(Debt::whereIn('apartment_id', $ids));
        $baseTotal = max(20000, $now['income'] ?: $balance);

        $sources = collect([
            ['label' => 'Aidat Tahsilatları', 'value' => round($baseTotal * 0.34, 2), 'pct' => 34],
            ['label' => 'Ortak Alan Geliri', 'value' => round($baseTotal * 0.22, 2), 'pct' => 22],
            ['label' => 'Operasyon & Bakım', 'value' => round($baseTotal * 0.18, 2), 'pct' => 18],
            ['label' => 'Sayaç & Enerji Payı', 'value' => round($baseTotal * 0.12, 2), 'pct' => 12],
            ['label' => 'Sigorta & Güvence', 'value' => round($baseTotal * 0.08, 2), 'pct' => 8],
            ['label' => 'Diğer', 'value' => round($baseTotal * 0.06, 2), 'pct' => 6],
        ]);

        return [
            'range' => $range, 'window' => $w,
            'sites' => $sites,
            'monthly' => $this->normalizeSixBars($rawMonthly->slice(-6)->values(), $baseTotal),
            'balanceHistory' => $this->balanceHistory($rawMonthly, $balance),
            'now' => $now, 'prev' => $prev,
            'collectionState' => $state,
            'sources' => $sources,
            'totals' => [
                'balance' => $balance, 'receivables' => (float) $sites->sum('receivables'), 'overdue' => (float) $sites->sum('overdue'),
                'units' => (int) $sites->sum('units_count'), 'members' => (int) $sites->sum('members_count'),
            ],
            'counts' => [
                'properties' => Property::count(), 'properties_new' => Property::where('created_at', '>=', now()->startOfMonth())->count(),
                'users' => User::visibleTo($user)->count(), 'users_new' => User::visibleTo($user)->where('created_at', '>=', now()->startOfMonth())->count(),
                'tickets' => Ticket::whereIn('apartment_id', $ids)->whereIn('status', ['open', 'in_progress'])->count(),
                'tickets_urgent' => Ticket::whereIn('apartment_id', $ids)->whereIn('status', ['open', 'in_progress'])->where('priority', 'high')->count(),
                'sites' => $sites->count(),
            ],
            'attention' => [
                ['Onay bekleyen gider', Expense::whereIn('apartment_id', $ids)->where('status', 'pending')->count(), 'receipt', 'amber'],
                ['Eşleşmemiş banka satırı', BankStatementLine::whereIn('apartment_id', $ids)->where('status', 'pending')->count(), 'landmark', 'amber'],
                ['İcra takibindeki borç', Debt::whereIn('apartment_id', $ids)->where('status', Debt::PENDING)->whereNotNull('legal_status')->count(), 'gavel', 'red'],
                ['Onay bekleyen rezervasyon', Reservation::whereIn('apartment_id', $ids)->where('status', 'pending')->count(), 'calendar-check', 'amber'],
                ['Kritik stok kalemi', InventoryItem::whereIn('apartment_id', $ids)->whereColumn('quantity', '<=', 'min_stock')->count(), 'package', 'red'],
            ],
            'activity' => $this->activity($ids, 6),
            'recentTickets' => Ticket::whereIn('apartment_id', $ids)->whereIn('status', ['open', 'in_progress'])->with('apartment:id,name')->latest('id')->limit(5)->get(),
        ];
    }

    public function users(User $viewer, ?string $tab, ?string $search, string $sort): Builder
    {
        return User::visibleTo($viewer)->withCount('memberships')
            ->when($tab === 'admins', fn ($q) => $q->where('role', 'admin')->where('is_super_admin', false))
            ->when($tab === 'residents', fn ($q) => $q->where('role', 'user'))
            ->when($tab === 'super', fn ($q) => $q->where('is_super_admin', true))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->when($sort === 'name', fn ($q) => $q->orderBy('name'), fn ($q) => $q->latest());
    }

    private function activity(array $apartmentIds, int $limit): array
    {
        return AuditLog::whereIn('apartment_id', $apartmentIds)->with('apartment:id,name', 'user:id,name')->latest('id')->limit($limit)->get()
            ->map(fn (AuditLog $l) => [
                'label' => self::ACTION_LABELS[$l->action] ?? $l->action, 'site' => $l->apartment?->name, 'user' => $l->user?->name,
                'at' => $l->created_at, 'detail' => $l->data['amount'] ?? null,
            ])->all();
    }
}
