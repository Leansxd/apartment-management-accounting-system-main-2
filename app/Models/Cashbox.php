<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cashbox extends Model
{
    public const TYPES = ['cash' => 'Nakit Kasa', 'bank' => 'Banka Hesabı'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:2'];
    }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
    public function transactions(): HasMany { return $this->hasMany(CashTransaction::class); }
}
