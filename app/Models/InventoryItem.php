<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    public const CATEGORIES = ['Temizlik', 'Teknik', 'Bahçe', 'Ofis', 'Diğer'];

    protected $guarded = ['id'];

    public function isLow(): bool
    {
        return $this->quantity <= $this->min_stock;
    }
}
