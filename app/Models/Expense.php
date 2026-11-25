<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Expense extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'invoice_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /** Ödenmemiş kalan tutar (KDV dahil) */
    public function remainingAmount(): float
    {
        return round((float) $this->total_amount - (float) $this->paid_amount, 2);
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status !== 'approved' && (float) $this->paid_amount > 0;
    }

    /** Bu giderin (ör. teknik servis faturası) yansıtıldığı daire borcu */
    public function rechargedDebt(): HasOne
    {
        return $this->hasOne(Debt::class);
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class);
    }
}
