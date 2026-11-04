<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAttendance extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_proxy' => 'boolean'];
    }

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class)->withTrashed(); }
}
