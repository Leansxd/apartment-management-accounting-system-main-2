<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'expires_on' => 'date'];
    }

    /** Yayında olanlar: aktif ve süresi dolmamış */
    public function scopeLive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()));
    }
}
