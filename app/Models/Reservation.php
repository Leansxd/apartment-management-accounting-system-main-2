<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    public const STATUSES = ['pending' => 'Onay bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi', 'cancelled' => 'İptal'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'fee' => 'decimal:2'];
    }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
    public function area(): BelongsTo { return $this->belongsTo(CommonArea::class, 'common_area_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class)->withTrashed(); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function debt(): BelongsTo { return $this->belongsTo(Debt::class); }

    /** Başlangıcı geçmemiş ve iptal/ret olmamış rezervasyon */
    public function isUpcoming(): bool
    {
        return in_array($this->status, ['pending', 'approved'], true)
            && $this->date->copy()->setTimeFromTimeString($this->start_time)->isFuture();
    }
}
