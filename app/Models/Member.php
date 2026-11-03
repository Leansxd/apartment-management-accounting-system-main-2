<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Daire sakini (mülk sahibi veya kiracı). Silinen üyenin davet kodu geçersiz olur ve
 * sakin, o dairenin kayıtlarını artık mobil API'den göremez.
 */
class Member extends Model
{
    use SoftDeletes;

    public const TYPES = ['owner' => 'Mülk Sahibi', 'tenant' => 'Kiracı'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['activated_at' => 'datetime'];
    }

    public function apartment(): BelongsTo { return $this->belongsTo(Apartment::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class)->withTrashed(); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Kriptografik rastgele davet kodu: SITEKODU-A5-XXXXXXXX (karışan 0/O ve 1/I harfleri yok). */
    public static function generateInviteCode(string $apartmentCode, string $unitLabel): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $clean = fn (string $s) => preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($s));

        do {
            $random = '';
            for ($i = 0; $i < 8; $i++) {
                $random .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = ($clean($apartmentCode) ?: 'APT').'-'.$clean($unitLabel).'-'.$random;
        } while (static::withTrashed()->where('invite_code', $code)->exists());

        return $code;
    }
}
