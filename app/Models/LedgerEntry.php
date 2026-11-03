<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Çift taraflı yevmiye satırı. Yalnızca eklenir; düzeltme ters kayıtla yapılır. */
class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    public const CASH = 'Kasa/Banka';
    public const UNIT = 'Üye/Daire Hesabı';
    public const UNIT_ADVANCE = 'Üye Avans Hesabı';
    public const INCOME = 'Gelir Hesabı';
    public const EXPENSE = 'Gider Hesabı';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }
}
