<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    public const CATEGORIES = ['Temizlik', 'Teknik', 'Bahçe', 'Ofis', 'Diğer'];

    protected $guarded = ['id'];

    public function isLow(): bool
    {
        return $this->quantity <= $this->min_stock;
    }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
}
