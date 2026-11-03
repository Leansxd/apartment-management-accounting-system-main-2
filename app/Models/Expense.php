<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'invoice_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function category(): BelongsTo { return $this->belongsTo(TransactionCategory::class); }
    public function cashbox(): BelongsTo { return $this->belongsTo(Cashbox::class); }
}
