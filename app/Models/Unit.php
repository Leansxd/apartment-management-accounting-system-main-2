<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['gross_area' => 'decimal:2', 'land_share' => 'decimal:4', 'credit_balance' => 'decimal:2'];
    }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
    public function block(): BelongsTo { return $this->belongsTo(Block::class)->withTrashed(); }
    public function members(): HasMany { return $this->hasMany(Member::class); }
    public function debts(): HasMany { return $this->hasMany(Debt::class); }
    public function cashTransactions(): HasMany { return $this->hasMany(CashTransaction::class); }
    public function groups(): BelongsToMany { return $this->belongsToMany(UnitGroup::class, 'unit_group_unit'); }

    /** Ör. "A-5" */
    public function label(): string
    {
        return trim(($this->block?->name ?? '').'-'.$this->unit_no, '-');
    }
}
