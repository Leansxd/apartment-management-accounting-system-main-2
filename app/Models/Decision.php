<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Karar defteri kaydı */
class Decision extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_on' => 'date'];
    }
}
