<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\Debt;
use App\Models\Unit;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Raporlar ve özetler. Toplamlar veritabanında hesaplanır (kayıt sayısı sınırı yoktur).
 */
class ReportService
{
    /**
     * Finansal özet. Tarih aralığı verilirse hareket toplamları o aralıkla sınırlanır;
     * kasa bakiyeleri ve açık alacaklar her zaman günceldir.
     */
    public function summary(Apartment $apartment, ?string $from = null, ?string $to = null): array
    {
        $tx = $apartment->cashTransactions()->operating() // iptaller ve virmanlar gelir/gider değildir
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));

        $byType = (clone $tx)->selectRaw('type, SUM(amount) total')->groupBy('type')->pluck('total', 'type');

        return [
            'balance' => (float) $apartment->cashboxes()->sum('balance'),
            'income' => (float) ($byType['income'] ?? 0),
            'expense' => (float) ($byType['expense'] ?? 0),
            'pos' => (float) (clone $tx)->where('sub_type', 'pos')->sum('amount'),
            'receivables' => (float) $apartment->debts()->where('status', Debt::PENDING)->sum('remaining_amount'),
            'overdue' => (float) $apartment->debts()->where('status', Debt::PENDING)
                ->whereDate('due_date', '<', today())->sum('remaining_amount'),
            'advances' => (float) $apartment->units()->sum('credit_balance'),
            'pending_expenses' => (float) $apartment->expenses()->where('status', 'pending')->sum('total_amount'),
        ];
    }

    /** Tahakkuk edilen / tahsil edilen ve tahsilat oranı */
    public function collectionPerformance(Apartment $apartment, ?string $from = null, ?string $to = null): array
    {
        $row = $apartment->debts()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->selectRaw('COALESCE(SUM(amount),0) accrued, COALESCE(SUM(paid_amount),0) collected')->first();

        $accrued = (float) $row->accrued;
        $collected = (float) $row->collected;

        return [
            'accrued' => $accrued,
            'collected' => $collected,
            'rate' => $accrued > 0 ? round($collected / $accrued * 100, 1) : 0,
        ];
    }

    /** Son N ayın aylık gelir / gider toplamları (virmanlar hariç), boş aylar dahil */
    public function monthly(Apartment $apartment, int $months = 6): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);
        $rows = $apartment->cashTransactions()->operating()
            ->where('created_at', '>=', $start)
            ->get(['type', 'amount', 'created_at'])
            ->groupBy(fn ($t) => $t->created_at->format('Y-m'));

        $result = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->addMonths($i);
            $items = $rows->get($month->format('Y-m'), collect());
            $result[] = [
                'label' => $month->locale('tr')->translatedFormat('M Y'),
                'income' => (float) $items->where('type', 'income')->sum('amount'),
                'expense' => (float) $items->where('type', 'expense')->sum('amount'),
            ];
        }

        return $result;
    }

    /**
     * Daire bazında gecikmiş borçlar (en çok borçlu önce).
     *
     * @return Collection<int, array{unit:Unit, total:float, count:int, oldest:string}>
     */
    public function overdueByUnit(Apartment $apartment): Collection
    {
        return $apartment->debts()
            ->where('status', Debt::PENDING)
            ->whereDate('due_date', '<', today())
            ->with('unit.block', 'unit.members')
            ->orderBy('due_date')
            ->get()
            ->groupBy('unit_id')
            ->map(fn (Collection $debts) => [
                'unit' => $debts->first()->unit,
                'total' => (float) $debts->sum('remaining_amount'),
                'count' => $debts->count(),
                'oldest' => $debts->first()->due_date->format('d.m.Y'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Daire bazında bakiye listesi: toplam borç, ödenen, kalan, avans ve net bakiye.
     *
     * @return Collection<int, array>
     */
    public function balanceList(Apartment $apartment): Collection
    {
        $debts = $apartment->debts()
            ->selectRaw('unit_id, SUM(amount) accrued, SUM(paid_amount) paid, SUM(remaining_amount) remaining')
            ->selectRaw('SUM(CASE WHEN status = ? AND due_date < ? THEN remaining_amount ELSE 0 END) overdue',
                [Debt::PENDING, today()->toDateString()])
            ->groupBy('unit_id')->get()->keyBy('unit_id');

        return $apartment->units()->with(['block', 'members'])->get()
            ->sortBy(fn (Unit $u) => [$u->block?->name, (int) $u->unit_no])
            ->map(function (Unit $unit) use ($debts) {
                $d = $debts->get($unit->id);
                $remaining = (float) ($d->remaining ?? 0);
                $credit = (float) $unit->credit_balance;

                return [
                    'unit' => $unit,
                    'owner' => $unit->members->firstWhere('type', 'owner'),
                    'tenant' => $unit->members->firstWhere('type', 'tenant'),
                    'accrued' => (float) ($d->accrued ?? 0),
                    'paid' => (float) ($d->paid ?? 0),
                    'remaining' => $remaining,
                    'overdue' => (float) ($d->overdue ?? 0),
                    'credit' => $credit,
                    'net' => round($remaining - $credit, 2), // + borçlu, - alacaklı
                ];
            })->values();
    }

    /** Daire hesap ekstresi: borç ve ödemeler tarih sırasıyla, yürüyen bakiyeyle */
    public function statement(Unit $unit, ?string $from = null, ?string $to = null): array
    {
        $debts = $unit->debts()->get();
        $payments = DB::table('debt_payments')->where('unit_id', $unit->id)->get();
        $advances = $unit->cashTransactions()->effective()->where('type', 'income')->where('to_advance', '>', 0)->get();

        $lines = collect();
        foreach ($debts as $d) {
            $lines->push(['date' => $d->created_at, 'description' => $d->description, 'debit' => (float) $d->amount,
                'credit' => 0.0, 'due' => $d->due_date]);
        }
        foreach ($payments as $p) {
            // Avans mahsubu bakiyeyi değiştirmez: avans, girdiği anda zaten alacak olarak yazıldı.
            $isCreditUsage = $p->type === 'credit_usage';
            $lines->push(['date' => CarbonImmutable::parse($p->created_at),
                'description' => $isCreditUsage ? 'Avans mahsubu: '.Money::format($p->amount) : 'Tahsilat',
                'debit' => 0.0, 'credit' => $isCreditUsage ? 0.0 : (float) $p->amount, 'due' => null]);
        }
        foreach ($advances as $t) {
            $lines->push(['date' => $t->created_at, 'description' => 'Avans (fazla ödeme)', 'debit' => 0.0,
                'credit' => (float) $t->to_advance, 'due' => null]);
        }

        $balance = 0;
        $opening = 0;
        $rows = [];
        foreach ($lines->sortBy(fn ($l) => $l['date']->getTimestamp())->values() as $line) {
            $effect = Money::cents($line['debit']) - Money::cents($line['credit']);
            if ($from && $line['date']->toDateString() < $from) {
                $opening += $effect;
                $balance += $effect;
                continue;
            }
            if ($to && $line['date']->toDateString() > $to) {
                continue;
            }
            $balance += $effect;
            $rows[] = $line + ['balance' => $balance / 100];
        }

        return [
            'opening' => $opening / 100,
            'rows' => $rows,
            'closing' => $balance / 100,
            'open_debt' => (float) $unit->debts()->where('status', Debt::PENDING)->sum('remaining_amount'),
            'credit' => (float) $unit->credit_balance,
        ];
    }
}
