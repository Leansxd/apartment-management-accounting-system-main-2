<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\Asset;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Meeting;
use App\Models\Reservation;
use App\Models\SecurityEntry;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rapor Merkezi'nin analiz ve operasyon raporları. Her rapor [sütunlar, satırlar, toplamlar] döner;
 * para değerleri float, diğerleri metin / tam sayıdır. Tüm sorgular verilen siteyle sınırlıdır.
 */
class ModuleReportService
{
    /** anahtar => [başlık, grup, açıklama] */
    public const TYPES = [
        'monthly-collection' => ['Aylık Tahsilat Performansı', 'Finans Analizi', 'Vadesi her ayda olan borçların tahsil durumu ve oranı'],
        'cash-flow' => ['Aylık Nakit Akışı', 'Finans Analizi', 'Aylık gelir, gider, net ve kümülatif akış'],
        'expense-category' => ['Kategori Bazında Gider', 'Finans Analizi', 'Gider kategorilerinin tutarı, işlem adedi ve payı'],
        'vendor-spend' => ['Firma Bazında Harcama', 'Finans Analizi', 'Firmalara ödenen ve bekleyen fatura toplamları'],
        'block-comparison' => ['Blok Karşılaştırması', 'Finans Analizi', 'Bloklar arasında tahakkuk, tahsilat ve gecikme'],
        'top-debtors' => ['En Borçlu Daireler', 'Tahsilat ve Borç', 'Gecikmiş borca göre sıralı daireler ve iletişim bilgisi'],
        'advances' => ['Avans Listesi', 'Tahsilat ve Borç', 'Avansı olan daireler ve açık borçları'],
        'late-fees' => ['Gecikme Zammı Raporu', 'Tahsilat ve Borç', 'Yazılan zamlar ve tahsil durumları'],
        'legal' => ['İcra Takibindeki Borçlar', 'Tahsilat ve Borç', 'Hukuki takibe alınmış açık borçlar'],
        'tickets' => ['Destek Talepleri', 'Operasyon', 'Talepler, öncelik, durum ve çözüm süresi'],
        'tasks' => ['Görev Raporu', 'Operasyon', 'Görevler, sorumlular ve gecikme durumu'],
        'reservations' => ['Rezervasyon Raporu', 'Operasyon', 'Ortak alan kullanımı ve ücretleri'],
        'security' => ['Güvenlik Kayıtları', 'Operasyon', 'Kargo, otopark ve ziyaretçi hareketleri'],
        'inventory' => ['Stok Durumu', 'Operasyon', 'Stok miktarları ve kritik seviyeler'],
        'assets' => ['Demirbaş Listesi', 'Operasyon', 'Maliyet, birikmiş amortisman ve net değer'],
        'meter-consumption' => ['Sayaç Tüketimi', 'Operasyon', 'Sayaç başına okuma ve toplam tüketim'],
        'payroll' => ['Personel ve Bordro', 'Operasyon', 'Personel maaşları ve yıl içi ödemeler'],
        'poll-results' => ['Anket Sonuçları', 'İletişim ve Yönetim', 'Seçenek bazında oy ve oranlar'],
        'meetings' => ['Toplantı Katılımı', 'İletişim ve Yönetim', 'Toplantılar, katılım ve alınan kararlar'],
    ];

    /** Tarih aralığı uygulanan raporlar */
    public const DATED = ['monthly-collection', 'cash-flow', 'expense-category', 'vendor-spend', 'late-fees', 'tickets', 'tasks', 'reservations', 'security', 'meter-consumption', 'meetings'];

    /** Blok filtresi anlamlı olmayan raporlar */
    public const WITHOUT_BLOCK = ['cash-flow', 'expense-category', 'vendor-spend', 'block-comparison', 'tasks', 'security', 'inventory', 'assets', 'meter-consumption', 'payroll', 'poll-results', 'meetings'];

    public function __construct(private ReportService $reports) {}

    /** @return array{0: array<string,string>, 1: Collection, 2: array<string,mixed>|null} */
    public function build(Apartment $apartment, string $type, ?string $from, ?string $to, ?int $blockId): array
    {
        $unitIds = $blockId ? $apartment->units()->where('block_id', $blockId)->pluck('id') : null;

        return match ($type) {
            'monthly-collection' => $this->monthlyCollection($apartment, $from, $to, $unitIds),
            'cash-flow' => $this->cashFlow($apartment, $from, $to),
            'expense-category' => $this->expenseCategory($apartment, $from, $to),
            'vendor-spend' => $this->vendorSpend($apartment, $from, $to),
            'block-comparison' => $this->blockComparison($apartment),
            'top-debtors' => $this->topDebtors($apartment, $unitIds),
            'advances' => $this->advances($apartment, $unitIds),
            'late-fees' => $this->debtList($apartment, $apartment->debts()->where('kind', 'late_fee'), $from, $to, $unitIds),
            'legal' => $this->debtList($apartment, $apartment->debts()->where('status', Debt::PENDING)->whereNotNull('legal_status'), null, null, $unitIds),
            'tickets' => $this->tickets($apartment, $from, $to, $unitIds),
            'tasks' => $this->tasks($apartment, $from, $to),
            'reservations' => $this->reservations($apartment, $from, $to, $unitIds),
            'security' => $this->security($apartment, $from, $to),
            'inventory' => $this->inventory($apartment),
            'assets' => $this->assets($apartment),
            'meter-consumption' => $this->meterConsumption($apartment, $from, $to),
            'payroll' => $this->payroll($apartment),
            'poll-results' => $this->pollResults($apartment),
            'meetings' => $this->meetings($apartment, $from, $to),
            default => abort(404),
        };
    }

    private function monthlyCollection(Apartment $apartment, ?string $from, ?string $to, ?Collection $unitIds): array
    {
        $rows = $apartment->debts()
            ->when($unitIds, fn ($q) => $q->whereIn('unit_id', $unitIds))
            ->when($from, fn ($q) => $q->whereDate('due_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('due_date', '<=', $to))
            ->orderBy('due_date')->get(['due_date', 'amount', 'paid_amount', 'remaining_amount'])
            ->groupBy(fn (Debt $d) => $d->due_date->format('Y-m'))
            ->map(function (Collection $debts, string $month) {
                $amount = round((float) $debts->sum('amount'), 2);
                $paid = round((float) $debts->sum('paid_amount'), 2);

                return ['month' => $month, 'count' => $debts->count(), 'amount' => $amount, 'paid' => $paid,
                    'remaining' => round((float) $debts->sum('remaining_amount'), 2), 'rate' => $this->rate($paid, $amount)];
            })->values();

        return [['month' => 'Dönem', 'count' => 'Borç Adedi', 'amount' => 'Tahakkuk', 'paid' => 'Tahsil Edilen', 'remaining' => 'Kalan', 'rate' => 'Tahsil Oranı'],
            $rows, $this->totals($rows, ['amount', 'paid', 'remaining'], 'month') + ['rate' => $this->rate($rows->sum('paid'), $rows->sum('amount'))]];
    }

    private function cashFlow(Apartment $apartment, ?string $from, ?string $to): array
    {
        $cumulative = 0.0;
        $rows = $apartment->cashTransactions()->operating()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderBy('created_at')->get(['type', 'amount', 'created_at'])
            ->groupBy(fn ($t) => $t->created_at->format('Y-m'))
            ->map(function (Collection $items, string $month) use (&$cumulative) {
                $income = round((float) $items->where('type', 'income')->sum('amount'), 2);
                $expense = round((float) $items->where('type', 'expense')->sum('amount'), 2);
                $cumulative = round($cumulative + $income - $expense, 2);

                return ['month' => $month, 'income' => $income, 'expense' => $expense, 'net' => round($income - $expense, 2), 'cumulative' => $cumulative];
            })->values();

        return [['month' => 'Dönem', 'income' => 'Gelir', 'expense' => 'Gider', 'net' => 'Net', 'cumulative' => 'Kümülatif'],
            $rows, $this->totals($rows, ['income', 'expense', 'net'], 'month')];
    }

    private function expenseCategory(Apartment $apartment, ?string $from, ?string $to): array
    {
        $groups = $apartment->cashTransactions()->operating()->where('cash_transactions.type', 'expense')
            ->when($from, fn ($q) => $q->whereDate('cash_transactions.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('cash_transactions.created_at', '<=', $to))
            ->leftJoin('transaction_categories as c', 'c.id', '=', 'cash_transactions.category_id')
            ->groupBy('c.name')->selectRaw("COALESCE(c.name, 'Kategorisiz') as category, SUM(cash_transactions.amount) total, COUNT(*) cnt")
            ->orderByDesc('total')->get();
        $sum = (float) $groups->sum('total');
        $rows = $groups->map(fn ($g) => ['category' => $g->category, 'count' => (int) $g->cnt, 'total' => round((float) $g->total, 2), 'share' => $this->rate((float) $g->total, $sum)])->values();

        return [['category' => 'Kategori', 'count' => 'İşlem', 'total' => 'Tutar', 'share' => 'Pay'], $rows, $this->totals($rows, ['total'], 'category')];
    }

    private function vendorSpend(Apartment $apartment, ?string $from, ?string $to): array
    {
        $expenses = $apartment->expenses()->whereNotNull('vendor_id')->with('vendor')
            ->when($from, fn ($q) => $q->whereDate('invoice_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('invoice_date', '<=', $to))->get();
        $rows = $expenses->groupBy('vendor_id')->map(fn (Collection $items) => [
            'vendor' => $items->first()->vendor?->name, 'count' => $items->count(),
            'paid' => round((float) $items->sum('paid_amount'), 2),
            'pending' => round((float) $items->where('status', 'pending')->sum(fn (Expense $e) => $e->remainingAmount()), 2),
            'last' => $items->max('invoice_date')?->format('d.m.Y'),
        ])->sortByDesc('paid')->values();

        return [['vendor' => 'Firma', 'count' => 'Fatura', 'paid' => 'Ödenen', 'pending' => 'Bekleyen', 'last' => 'Son Fatura'], $rows, $this->totals($rows, ['paid', 'pending'], 'vendor')];
    }

    private function blockComparison(Apartment $apartment): array
    {
        $rows = $this->reports->blockComparison($apartment)->map(fn ($b) => [
            'block' => $b['block'], 'units' => $b['units'], 'accrued' => $b['accrued'], 'paid' => $b['paid'],
            'remaining' => $b['remaining'], 'overdue' => $b['overdue'], 'rate' => '%'.number_format($b['rate'], 1, ',', '.'),
        ])->values();

        return [['block' => 'Blok', 'units' => 'Daire', 'accrued' => 'Tahakkuk', 'paid' => 'Tahsil Edilen', 'remaining' => 'Kalan', 'overdue' => 'Gecikmiş', 'rate' => 'Tahsil Oranı'],
            $rows, $this->totals($rows, ['accrued', 'paid', 'remaining', 'overdue'], 'block')];
    }

    private function topDebtors(Apartment $apartment, ?Collection $unitIds): array
    {
        $rows = $this->reports->overdueByUnit($apartment)
            ->when($unitIds, fn ($list) => $list->filter(fn ($r) => $unitIds->contains($r['unit']->id)))
            ->values()->map(function ($r, int $i) {
                $member = $r['unit']->members->first();

                return ['_links' => ['unit' => $r['unit'], 'member' => $member], 'rank' => $i + 1, 'unit' => $r['unit']->label(), 'member' => $member?->full_name, 'phone' => $member?->phone,
                    'count' => $r['count'], 'oldest' => $r['oldest'], 'total' => round($r['total'], 2)];
            });

        return [['rank' => 'Sıra', 'unit' => 'Daire', 'member' => 'Sakin', 'phone' => 'Telefon', 'count' => 'Borç Adedi', 'oldest' => 'En Eski Vade', 'total' => 'Gecikmiş'],
            $rows, $this->totals($rows, ['total'], 'rank')];
    }

    private function advances(Apartment $apartment, ?Collection $unitIds): array
    {
        $units = $apartment->units()->with('block', 'members')->where('credit_balance', '>', 0)
            ->when($unitIds, fn ($q) => $q->whereIn('id', $unitIds))->get();
        $open = $apartment->debts()->where('status', Debt::PENDING)->whereIn('unit_id', $units->pluck('id'))
            ->groupBy('unit_id')->selectRaw('unit_id, SUM(remaining_amount) total')->pluck('total', 'unit_id');
        $rows = $units->sortByDesc(fn (Unit $u) => (float) $u->credit_balance)->map(fn (Unit $u) => [
            '_links' => ['unit' => $u, 'member' => $u->members->first()], 'unit' => $u->label(), 'member' => $u->members->first()?->full_name,
            'credit' => (float) $u->credit_balance, 'open' => (float) ($open[$u->id] ?? 0),
        ])->values();

        return [['unit' => 'Daire', 'member' => 'Sakin', 'credit' => 'Avans', 'open' => 'Açık Borç'], $rows, $this->totals($rows, ['credit', 'open'], 'unit')];
    }

    private function debtList(Apartment $apartment, $query, ?string $from, ?string $to, ?Collection $unitIds): array
    {
        $rows = $query->with('unit.block', 'unit.members')
            ->when($unitIds, fn ($q) => $q->whereIn('unit_id', $unitIds))
            ->when($from, fn ($q) => $q->whereDate('due_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('due_date', '<=', $to))
            ->orderByDesc('due_date')->get()->map(fn (Debt $d) => [
                '_links' => ['unit' => $d->unit, 'member' => $d->unit?->members->first()], 'unit' => $d->unit?->label(), 'member' => $d->unit?->members->first()?->full_name, 'description' => $d->description,
                'due' => $d->due_date->format('d.m.Y'), 'note' => $d->legal_note, 'amount' => (float) $d->amount,
                'paid' => (float) $d->paid_amount, 'remaining' => (float) $d->remaining_amount, 'status' => Debt::DISPLAY[$d->displayStatus()],
            ])->values();

        return [['unit' => 'Daire', 'member' => 'Sakin', 'description' => 'Açıklama', 'due' => 'Vade', 'note' => 'Not', 'amount' => 'Tutar', 'paid' => 'Ödenen', 'remaining' => 'Kalan', 'status' => 'Durum'],
            $rows, $this->totals($rows, ['amount', 'paid', 'remaining'], 'unit')];
    }

    private function tickets(Apartment $apartment, ?string $from, ?string $to, ?Collection $unitIds): array
    {
        $rows = $apartment->tickets()->with('unit.block')
            ->when($unitIds, fn ($q) => $q->whereIn('unit_id', $unitIds))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')->get()->map(fn (Ticket $t) => [
                'date' => $t->created_at->format('d.m.Y'), '_links' => ['unit' => $t->unit], 'unit' => $t->unit?->label() ?? $t->unit_no, 'subject' => $t->subject, 'category' => $t->category,
                'priority' => Ticket::PRIORITIES[$t->priority] ?? $t->priority, 'status' => Ticket::STATUSES[$t->status] ?? $t->status,
                'hours' => in_array($t->status, ['resolved', 'closed'], true) ? (int) $t->created_at->diffInHours($t->updated_at) : '',
            ])->values();

        return [['date' => 'Tarih', 'unit' => 'Daire', 'subject' => 'Konu', 'category' => 'Kategori', 'priority' => 'Öncelik', 'status' => 'Durum', 'hours' => 'Çözüm (saat)'], $rows, null];
    }

    private function tasks(Apartment $apartment, ?string $from, ?string $to): array
    {
        $rows = $apartment->tasks()->with('staff')
            ->when($from, fn ($q) => $q->whereDate('due_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('due_date', '<=', $to))
            ->orderBy('due_date')->get()->map(fn (Task $t) => [
                'title' => $t->title, 'staff' => $t->staff?->name, 'category' => Task::CATEGORIES[$t->category] ?? $t->category,
                'priority' => Task::PRIORITIES[$t->priority] ?? $t->priority, 'due' => $t->due_date?->format('d.m.Y'),
                'status' => Task::STATUSES[$t->status] ?? $t->status,
                'late' => $t->status !== 'completed' && $t->due_date?->isPast() ? 'Gecikti' : '',
            ])->values();

        return [['title' => 'Görev', 'staff' => 'Sorumlu', 'category' => 'Kategori', 'priority' => 'Öncelik', 'due' => 'Son Tarih', 'status' => 'Durum', 'late' => 'Gecikme'], $rows, null];
    }

    private function reservations(Apartment $apartment, ?string $from, ?string $to, ?Collection $unitIds): array
    {
        $rows = $apartment->reservations()->with('area', 'unit.block')
            ->when($unitIds, fn ($q) => $q->whereIn('unit_id', $unitIds))
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->orderByDesc('date')->get()->map(fn (Reservation $r) => [
                'date' => $r->date->format('d.m.Y'), 'time' => $r->start_time.' - '.$r->end_time, 'area' => $r->area?->name,
                '_links' => ['unit' => $r->unit], 'unit' => $r->unit?->label(), 'status' => Reservation::STATUSES[$r->status] ?? $r->status, 'fee' => (float) $r->fee,
            ])->values();

        return [['date' => 'Tarih', 'time' => 'Saat', 'area' => 'Alan', 'unit' => 'Daire', 'status' => 'Durum', 'fee' => 'Ücret'], $rows, $this->totals($rows, ['fee'], 'date')];
    }

    private function security(Apartment $apartment, ?string $from, ?string $to): array
    {
        $rows = $apartment->securityEntries()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')->get()->map(fn (SecurityEntry $e) => [
                'date' => $e->created_at->format('d.m.Y H:i'), 'kind' => SecurityEntry::KINDS[$e->kind] ?? $e->kind, 'unit' => $e->unit_no,
                'name' => $e->full_name, 'detail' => trim($e->company.' '.$e->tracking_no.' '.$e->plate), 'status' => $e->status,
            ])->values();

        return [['date' => 'Giriş', 'kind' => 'Tür', 'unit' => 'Daire', 'name' => 'Ad', 'detail' => 'Detay', 'status' => 'Durum'], $rows, null];
    }

    private function inventory(Apartment $apartment): array
    {
        $rows = $apartment->inventoryItems()->orderBy('category')->orderBy('name')->get()->map(fn (InventoryItem $i) => [
            'name' => $i->name, 'category' => $i->category, 'location' => $i->location, 'quantity' => $i->quantity.' '.$i->unit,
            'min' => $i->min_stock, 'status' => $i->quantity === 0 ? 'Tükendi' : ($i->isLow() ? 'Kritik' : 'Yeterli'),
        ])->values();

        return [['name' => 'Ad', 'category' => 'Kategori', 'location' => 'Konum', 'quantity' => 'Miktar', 'min' => 'Asgari', 'status' => 'Durum'], $rows, null];
    }

    private function assets(Apartment $apartment): array
    {
        $rows = $apartment->assets()->orderBy('name')->get()->map(fn (Asset $a) => [
            'name' => $a->name, 'category' => $a->category, 'purchase' => $a->purchase_date->format('d.m.Y'), 'life' => $a->useful_life_years,
            'cost' => (float) $a->cost, 'depreciation' => $a->accumulatedDepreciation() / 100, 'book' => $a->bookValue() / 100,
            'status' => $a->status === 'active' ? 'Kullanımda' : 'Elden çıkarıldı',
        ])->values();

        return [['name' => 'Demirbaş', 'category' => 'Kategori', 'purchase' => 'Alış', 'life' => 'Ömür (yıl)', 'cost' => 'Maliyet', 'depreciation' => 'Amortisman', 'book' => 'Net Değer', 'status' => 'Durum'],
            $rows, $this->totals($rows, ['cost', 'depreciation', 'book'], 'name')];
    }

    private function meterConsumption(Apartment $apartment, ?string $from, ?string $to): array
    {
        $totals = DB::table('meter_readings')
            ->whereIn('meter_id', $apartment->meters()->select('id'))
            ->when($from, fn ($q) => $q->whereDate('read_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('read_on', '<=', $to))
            ->groupBy('meter_id')->selectRaw('meter_id, COUNT(*) readings, SUM(consumption) consumption, MAX(read_on) last_read')
            ->get()->keyBy('meter_id');
        $rows = $apartment->meters()->with('unit.block')->orderBy('type')->orderBy('name')->get()->map(fn ($m) => [
            '_links' => ['unit' => $m->unit], 'name' => $m->name, 'type' => $m->type, 'unit' => $m->unit?->label() ?? 'Ortak', 'readings' => (int) ($totals[$m->id]->readings ?? 0),
            'consumption' => number_format((float) ($totals[$m->id]->consumption ?? 0), 3, ',', '.'),
            'last' => isset($totals[$m->id]) ? date('d.m.Y', strtotime($totals[$m->id]->last_read)) : '',
        ])->values();

        return [['name' => 'Sayaç', 'type' => 'Tür', 'unit' => 'Daire', 'readings' => 'Okuma', 'consumption' => 'Tüketim', 'last' => 'Son Okuma'], $rows, null];
    }

    private function payroll(Apartment $apartment): array
    {
        $paid = $apartment->expenses()->whereNotNull('staff_id')->where('status', 'approved')->where('period', 'like', now()->year.'-%')
            ->groupBy('staff_id')->selectRaw('staff_id, SUM(total_amount) total, COUNT(*) months')->get()->keyBy('staff_id');
        $rows = $apartment->staff()->orderBy('name')->get()->map(fn ($s) => [
            'name' => $s->name, 'role' => $s->role, 'status' => $s->status, 'salary' => (float) $s->salary,
            'months' => (int) ($paid[$s->id]->months ?? 0), 'paid' => (float) ($paid[$s->id]->total ?? 0),
        ])->values();

        return [['name' => 'Personel', 'role' => 'Görev', 'status' => 'Durum', 'salary' => 'Maaş', 'months' => 'Ödenen Ay', 'paid' => 'Bu Yıl Ödenen'], $rows, $this->totals($rows, ['salary', 'paid'], 'name')];
    }

    private function pollResults(Apartment $apartment): array
    {
        $rows = $apartment->polls()->with(['options' => fn ($q) => $q->withCount('votes')])->latest('id')->get()->flatMap(function ($poll) {
            $total = $poll->options->sum('votes_count');

            return $poll->options->map(fn ($o) => ['question' => $poll->question, 'option' => $o->text, 'votes' => $o->votes_count, 'share' => $this->rate($o->votes_count, $total)]);
        })->values();

        return [['question' => 'Anket', 'option' => 'Seçenek', 'votes' => 'Oy', 'share' => 'Oran'], $rows, null];
    }

    private function meetings(Apartment $apartment, ?string $from, ?string $to): array
    {
        $units = $apartment->units()->count();
        $rows = $apartment->meetings()->withCount('attendances', 'decisions')
            ->when($from, fn ($q) => $q->whereDate('meets_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('meets_at', '<=', $to))
            ->orderByDesc('meets_at')->get()->map(fn (Meeting $m) => [
                'date' => $m->meets_at->format('d.m.Y H:i'), 'title' => $m->title, 'type' => Meeting::TYPES[$m->type] ?? $m->type,
                'status' => Meeting::STATUSES[$m->status] ?? $m->status, 'present' => $m->attendances_count,
                'rate' => $this->rate($m->attendances_count, $units), 'decisions' => $m->decisions_count,
            ])->values();

        return [['date' => 'Tarih', 'title' => 'Toplantı', 'type' => 'Tür', 'status' => 'Durum', 'present' => 'Katılan Daire', 'rate' => 'Katılım', 'decisions' => 'Karar'], $rows, null];
    }

    private function rate(float|int $part, float|int $whole): string
    {
        return $whole > 0 ? '%'.number_format($part / $whole * 100, 1, ',', '.') : '—';
    }

    /** @param list<string> $keys */
    private function totals(Collection $rows, array $keys, string $labelKey): array
    {
        return [$labelKey => 'TOPLAM'] + collect($keys)->mapWithKeys(fn (string $key) => [$key => round((float) $rows->sum($key), 2)])->all();
    }
}
