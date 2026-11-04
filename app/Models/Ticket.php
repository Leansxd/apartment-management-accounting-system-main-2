<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    public const STATUSES = ['open' => 'Açık', 'in_progress' => 'İşlemde', 'resolved' => 'Çözüldü', 'closed' => 'Kapandı'];
    public const CATEGORIES = ['Genel', 'Teknik', 'Temizlik', 'Güvenlik', 'Bahçe'];
    public const PRIORITIES = ['low' => 'Düşük', 'normal' => 'Normal', 'high' => 'Acil'];

    protected $guarded = ['id'];

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class)->withTrashed(); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
}
