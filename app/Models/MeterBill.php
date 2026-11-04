<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Dairelere dağıtılmış sayaç (su, ısınma vb.) faturası */
class MeterBill extends Model
{
    public const METHODS = ['consumption' => 'Tüketime göre', 'area' => 'm² ye göre', 'equal' => 'Eşit'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'total_amount' => 'decimal:2'];
    }
}
