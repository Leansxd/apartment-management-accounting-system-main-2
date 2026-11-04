<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'due_date' => 'date'];
    }

    public function options(): HasMany { return $this->hasMany(PollOption::class); }
    public function votes(): HasMany { return $this->hasMany(PollVote::class); }

    public function isOpen(): bool
    {
        return $this->is_active && (! $this->due_date || ! $this->due_date->copy()->endOfDay()->isPast());
    }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
}
