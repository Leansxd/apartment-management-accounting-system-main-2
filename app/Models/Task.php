<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    public const STATUSES = ['pending' => 'Bekliyor', 'in_progress' => 'Devam Ediyor', 'completed' => 'Tamamlandı'];
    public const PRIORITIES = ['low' => 'Düşük', 'medium' => 'Orta', 'high' => 'Yüksek'];
    public const CATEGORIES = ['maintenance' => 'Bakım', 'cleaning' => 'Temizlik', 'security' => 'Güvenlik', 'other' => 'Diğer'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function staff(): BelongsTo { return $this->belongsTo(Staff::class); }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
}
