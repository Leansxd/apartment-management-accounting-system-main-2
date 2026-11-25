<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dairelere dağıtılmış sayaç (su, ısınma vb.) faturası */
class MeterBill extends Model
{
    public const METHODS = ['consumption' => 'Tüketime göre', 'area' => 'm² ye göre', 'equal' => 'Eşit'];

    protected $guarded = ['id'];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'total_amount' => 'decimal:2'];
    }
}
