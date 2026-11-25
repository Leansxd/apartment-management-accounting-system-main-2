<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cashbox extends Model
{
    public const TYPES = ['cash' => 'Nakit Kasa', 'bank' => 'Banka Hesabı'];

    /** Raporlama para birimi: balance kolonu her kasada bu para birimi cinsindendir */
    public const BASE_CURRENCY = 'TRY';

    public const CURRENCIES = ['TRY' => 'Türk Lirası (₺)', 'USD' => 'ABD Doları ($)', 'EUR' => 'Euro (€)', 'GBP' => 'İngiliz Sterlini (£)'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:2', 'currency_balance' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function isForeign(): bool
    {
        return $this->currency !== self::BASE_CURRENCY;
    }

    /** Kasanın kendi para birimindeki mevcudu */
    public function nativeBalance(): string
    {
        return $this->isForeign() ? $this->currency_balance : $this->balance;
    }

    /** Seçim listelerinde kasa adı: döviz kasasında para birimiyle */
    public function labelWithCurrency(): string
    {
        return $this->isForeign() ? "{$this->name} ({$this->currency})" : $this->name;
    }

    /** Kasanın kendi para birimiyle biçimlenmiş mevcudu: "1.000,00 $" */
    public function formattedBalance(): string
    {
        return Money::formatIn($this->nativeBalance(), $this->currency);
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }
}
