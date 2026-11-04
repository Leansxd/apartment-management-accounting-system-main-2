<?php

namespace App\Services;

use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ortak alan rezervasyonu: çalışma saati ve çakışma kontrolü, saatlik ücret ve ücretin daire borcuna yazılması.
 * Onay gerektirmeyen alanlarda rezervasyon doğrudan onaylanır.
 */
class ReservationService
{
    public function __construct(private FinanceService $finance)
    {
    }

    public function book(CommonArea $area, Unit $unit, string $date, string $start, string $end, ?User $by = null, ?string $note = null, bool $forceApprove = false): Reservation
    {
        if (! $area->is_active) {
            throw new FinanceException('Bu alan şu an rezervasyona kapalı.');
        }
        if ($area->apartment_id !== $unit->apartment_id) {
            throw new FinanceException('Alan ve daire aynı siteye ait olmalıdır.');
        }
        if ($end <= $start) {
            throw new FinanceException('Bitiş saati başlangıçtan sonra olmalıdır.');
        }
        if ($start < $area->open_time || $end > $area->close_time) {
            throw new FinanceException("Alan {$area->open_time}–{$area->close_time} saatleri arasında kullanılabilir.");
        }
        if (CarbonImmutable::parse("{$date} {$start}")->isPast()) {
            throw new FinanceException('Geçmiş bir zamana rezervasyon yapılamaz.');
        }

        return DB::transaction(function () use ($area, $unit, $date, $start, $end, $by, $note, $forceApprove) {
            // Aynı alanın kayıtları kilitlenir: eşzamanlı iki istek çakışan rezervasyon açamaz
            CommonArea::whereKey($area->id)->lockForUpdate()->first();
            $clash = Reservation::where('common_area_id', $area->id)->whereDate('date', $date)
                ->whereIn('status', ['pending', 'approved'])->where('start_time', '<', $end)->where('end_time', '>', $start)->exists();
            if ($clash) {
                throw new FinanceException('Seçilen saat aralığında alan dolu.');
            }

            $hours = (strtotime("1970-01-01 {$end}") - strtotime("1970-01-01 {$start}")) / 3600;
            $fee = (int) round(Money::cents($area->hourly_fee) * $hours);
            $reservation = Reservation::create([
                'apartment_id' => $area->apartment_id, 'common_area_id' => $area->id, 'unit_id' => $unit->id, 'user_id' => $by?->id,
                'date' => $date, 'start_time' => $start, 'end_time' => $end, 'fee' => Money::decimal($fee),
                'note' => $note, 'status' => 'pending',
            ]);

            return ($forceApprove || ! $area->requires_approval) ? $this->approve($reservation, $by) : $reservation;
        });
    }

    /** Onaylar; ücret varsa daireye borç yazar. */
    public function approve(Reservation $reservation, ?User $by = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $by) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($reservation->status === 'approved') {
                return $reservation;
            }
            if ($reservation->status !== 'pending') {
                throw new FinanceException('Yalnızca onay bekleyen rezervasyon onaylanabilir.');
            }
            $fee = Money::cents($reservation->fee);
            if ($fee > 0) {
                $category = $reservation->apartment->categories()->firstOrCreate(['name' => 'Ortak Alan Kullanımı', 'type' => 'income']);
                $unit = $reservation->unit()->with('block')->first();
                $this->finance->accrue($unit, [
                    'description' => "Rezervasyon: {$reservation->area->name} {$reservation->date->format('d.m.Y')} {$reservation->start_time}-{$reservation->end_time}",
                    'amount' => $fee, 'due_date' => $reservation->date->toDateString(), 'period' => $reservation->date->format('Y-m'),
                    'category_id' => $category->id, 'category_name' => $category->name,
                ], "reservation:{$reservation->id}");
                $reservation->debt_id = $reservation->apartment->debts()->where('idempotency_key', "reservation:{$reservation->id}")->value('id');
            }
            $reservation->update(['status' => 'approved', 'decided_by' => $by?->id, 'debt_id' => $reservation->debt_id]);

            return $reservation;
        });
    }

    public function reject(Reservation $reservation, ?User $by = null): void
    {
        if ($reservation->status !== 'pending') {
            throw new FinanceException('Yalnızca onay bekleyen rezervasyon reddedilebilir.');
        }
        $reservation->update(['status' => 'rejected', 'decided_by' => $by?->id]);
    }

    /** İptal eder; ödenmemiş ücret borcu ters kayıtla silinir, ödenmişse iptal edilemez. */
    public function cancel(Reservation $reservation, ?User $by = null): void
    {
        DB::transaction(function () use ($reservation, $by) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if (! in_array($reservation->status, ['pending', 'approved'], true)) {
                throw new FinanceException('Bu rezervasyon zaten kapalı.');
            }
            if ($reservation->debt_id && ($debt = $reservation->debt)) {
                $this->finance->deleteDebt($debt, $by); // ödeme yapılmışsa FinanceException fırlatır
            }
            $reservation->update(['status' => 'cancelled', 'decided_by' => $by?->id, 'debt_id' => null]);
        });
    }
}
