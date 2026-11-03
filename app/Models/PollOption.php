<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollOption extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function votes(): HasMany { return $this->hasMany(PollVote::class); }
}
