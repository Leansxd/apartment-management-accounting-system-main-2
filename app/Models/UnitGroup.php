<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UnitGroup extends Model
{
    protected $guarded = ['id'];

    public function units(): BelongsToMany { return $this->belongsToMany(Unit::class, 'unit_group_unit'); }
}
