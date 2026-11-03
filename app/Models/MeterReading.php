<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeterReading extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value' => 'decimal:3', 'consumption' => 'decimal:3', 'read_on' => 'date'];
    }
}
