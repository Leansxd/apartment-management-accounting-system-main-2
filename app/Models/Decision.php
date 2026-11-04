<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Karar defteri kaydı */
class Decision extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_on' => 'date'];
    }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
}
