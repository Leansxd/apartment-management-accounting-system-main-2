<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommonArea extends Model
{
    protected $guarded = ['id'];

    // Veritabanı varsayılanlarıyla aynı: yeni oluşturulan nesne de doğru davranır
    protected $attributes = ['is_active' => true, 'requires_approval' => true, 'hourly_fee' => 0, 'open_time' => '08:00', 'close_time' => '22:00'];

    protected function casts(): array
    {
        return ['hourly_fee' => 'decimal:2', 'requires_approval' => 'boolean', 'is_active' => 'boolean'];
    }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
    public function reservations(): HasMany { return $this->hasMany(Reservation::class); }
}
