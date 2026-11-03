<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meter extends Model
{
    public const TYPES = ['Elektrik', 'Su', 'Doğalgaz', 'Isınma'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['initial_value' => 'decimal:3', 'last_value' => 'decimal:3'];
    }

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class)->withTrashed(); }
    public function readings(): HasMany { return $this->hasMany(MeterReading::class); }
}
