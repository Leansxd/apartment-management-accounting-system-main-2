<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    public const CATEGORIES = ['Sözleşme', 'Fatura', 'Karar / Tutanak', 'Yönetim Planı', 'Sigorta', 'Teknik', 'Diğer'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_on' => 'date'];
    }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function humanSize(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1, ',', '.').' MB'
            : number_format(max(1, $this->size / 1024), 0, ',', '.').' KB';
    }
}
