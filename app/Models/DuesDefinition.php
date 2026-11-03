<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aidat / periyodik borç tanımı: seçilen dönem için hedef dairelere borç üretir. */
class DuesDefinition extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_active' => 'boolean', 'last_run_at' => 'datetime'];
    }

    public function category(): BelongsTo { return $this->belongsTo(TransactionCategory::class); }
}
