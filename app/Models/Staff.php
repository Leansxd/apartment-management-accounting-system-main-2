<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    public const ROLES = ['Güvenlik', 'Temizlik', 'Teknik', 'Bahçe', 'Yönetim', 'Diğer'];

    protected $table = 'staff';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['salary' => 'decimal:2', 'start_date' => 'date'];
    }

    public function tasks(): HasMany { return $this->hasMany(Task::class); }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
}
