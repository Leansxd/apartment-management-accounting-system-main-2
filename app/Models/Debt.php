<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    /** Borcun sorumlusu; boşsa daire geneli (malik ve kiracının ortak borcu) */
    public const RESPONSIBLES = ['owner' => 'Malik', 'tenant' => 'Kiracı'];

    /** Borç bir gider faturasının daireye yansıtılmasıysa o gider */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public const DISPLAY = [
        'paid' => 'Ödendi',
        'overdue' => 'Gecikmiş',
        'partial' => 'Kısmi Ödendi',
        'pending' => 'Bekliyor',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'legal_at' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'waived_late_fee_months' => 'array',
        ];
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    /** Ekranda gösterilecek durum: paid | overdue | partial | pending (veritabanında yalnızca pending/paid saklanır). */
    public function displayStatus(): string
    {
        if ($this->status === self::PAID || (float) $this->remaining_amount <= 0) {
            return 'paid';
        }
        if ($this->due_date && $this->due_date->copy()->endOfDay()->isPast()) {
            return 'overdue';
        }

        return (float) $this->remaining_amount < (float) $this->amount ? 'partial' : 'pending';
    }
}
