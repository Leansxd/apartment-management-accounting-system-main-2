<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityEntry extends Model
{
    public const KINDS = ['cargo' => 'Kargo & Paket', 'parking' => 'Otopark & Araç', 'visitor' => 'Ziyaretçi Takibi'];

    /** Tür başına izin verilen durumlar; ilk değer açılış durumudur. */
    public const STATUSES = [
        'cargo' => ['beklemede' => 'Beklemede', 'teslim-edildi' => 'Teslim Edildi', 'iptal' => 'İptal Edildi'],
        'parking' => ['aktif' => 'Otoparkta', 'ayrildi' => 'Ayrıldı'],
        'visitor' => ['aktif' => 'İçeride', 'ayrildi' => 'Ayrıldı'],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->kind][$this->status] ?? $this->status;
    }
}
