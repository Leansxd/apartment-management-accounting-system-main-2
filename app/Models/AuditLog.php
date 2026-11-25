<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** İstekten gelen kayıtlar IP ve tarayıcı bilgisiyle yazılır; zamanlanmış görevlerde bu bilgi yoktur. */
    protected static function booted(): void
    {
        static::creating(function (AuditLog $log): void {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return;
            }
            $log->ip_address ??= request()->ip();
            $log->user_agent ??= Str::limit((string) request()->userAgent(), 250, '') ?: null;
        });
    }

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }
}
