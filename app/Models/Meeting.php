<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    public const TYPES = ['olagan' => 'Olağan Genel Kurul', 'olaganustu' => 'Olağanüstü Genel Kurul', 'yonetim' => 'Yönetim Kurulu'];
    public const STATUSES = ['planned' => 'Planlandı', 'held' => 'Yapıldı', 'cancelled' => 'İptal'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meets_at' => 'datetime'];
    }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
    public function attendances(): HasMany { return $this->hasMany(MeetingAttendance::class); }
    public function decisions(): HasMany { return $this->hasMany(Decision::class); }
}
