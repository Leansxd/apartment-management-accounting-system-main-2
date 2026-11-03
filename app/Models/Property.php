<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Emlak portföyü ilanı */
class Property extends Model
{
    public const CATEGORIES = ['Villa', 'Apartment', 'House', 'Land', 'Office', 'Commercial'];
    public const STATUSES = ['active' => 'Yayında', 'sold' => 'Satıldı', 'passive' => 'Pasif'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'area' => 'decimal:2',
            'images' => 'array',
            'distances' => 'array',
            'features' => 'array',
            'facades' => 'array',
            'is_furnished' => 'boolean',
            'is_citizenship' => 'boolean',
            'is_residence_permit' => 'boolean',
            'is_credit_eligible' => 'boolean',
        ];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function canBeManagedBy(User $user): bool
    {
        return $user->is_super_admin || $this->created_by === $user->id;
    }
}
