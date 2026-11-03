<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kasa hareketi. Yalnızca eklenir: düzenleme ve silme yoktur. */
class CashTransaction extends Model
{
    public const SUB_TYPES = [
        'collection' => 'Tahsilat',
        'advance' => 'Avans',
        'pos' => 'POS / Kredi Kartı',
        'invoice' => 'Fatura Ödemesi',
        'transfer_in' => 'Virman (Gelen)',
        'transfer_out' => 'Virman (Giden)',
        'general' => 'Genel',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'applied_to_debts' => 'decimal:2', 'to_advance' => 'decimal:2'];
    }

    public function cashbox(): BelongsTo { return $this->belongsTo(Cashbox::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class)->withTrashed(); }
    public function debt(): BelongsTo { return $this->belongsTo(Debt::class); }
    public function category(): BelongsTo { return $this->belongsTo(TransactionCategory::class); }
    public function performer(): BelongsTo { return $this->belongsTo(User::class, 'performed_by'); }

    public function isTransfer(): bool
    {
        return str_starts_with($this->sub_type, 'transfer');
    }
}
