<?php

namespace App\Models;

use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Demirbaş. Değer, doğrusal (eşit tutarlı) amortisman ile azalır. */
class Asset extends Model
{
    public const CATEGORIES = ['Asansör', 'Jeneratör', 'Güvenlik Sistemi', 'Bahçe Ekipmanı', 'Havuz', 'Ofis', 'Diğer'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date', 'disposed_on' => 'date',
            'cost' => 'decimal:2', 'salvage_value' => 'decimal:2',
        ];
    }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }

    /** Birikmiş amortisman (kuruş). Hurda değeri aşılmaz; elden çıkarılan demirbaşta elden çıkış tarihine kadardır. */
    public function accumulatedDepreciation(?CarbonInterface $asOf = null): int
    {
        $end = $this->disposed_on ?? ($asOf ?? today());
        if ($end->lte($this->purchase_date) || $this->useful_life_years < 1) {
            return 0;
        }
        $depreciable = max(0, Money::cents($this->cost) - Money::cents($this->salvage_value));
        $months = min((int) floor($this->purchase_date->diffInMonths($end)), $this->useful_life_years * 12);

        return (int) round($depreciable * $months / ($this->useful_life_years * 12));
    }

    /** Net defter değeri (kuruş) */
    public function bookValue(?CarbonInterface $asOf = null): int
    {
        return Money::cents($this->cost) - $this->accumulatedDepreciation($asOf);
    }
}
