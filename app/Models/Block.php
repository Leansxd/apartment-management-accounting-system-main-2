<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Block extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
    public function units(): HasMany { return $this->hasMany(Unit::class); }
}
