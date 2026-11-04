<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bankadan içe aktarılan ekstre satırı: eşleştirilip kasa hareketine (tahsilat / gider) dönüştürülür. */
class BankStatementLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['line_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
    public function cashbox(): BelongsTo { return $this->belongsTo(Cashbox::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class)->withTrashed(); }
    public function cashTransaction(): BelongsTo { return $this->belongsTo(CashTransaction::class); }
}
