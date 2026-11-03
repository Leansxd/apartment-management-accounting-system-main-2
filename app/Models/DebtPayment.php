<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bir tahsilatın veya avans mahsubunun bir borca uygulanan kısmı */
class DebtPayment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function debt(): BelongsTo { return $this->belongsTo(Debt::class); }
}
