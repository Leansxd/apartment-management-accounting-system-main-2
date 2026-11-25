<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Çift taraflı yevmiye satırı. Yalnızca eklenir; düzeltme ters kayıtla yapılır. */
class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    /** Yevmiye kayıt türlerinin ekrandaki adları */
    public const ENTRY_TYPES = [
        'accrual' => 'Tahakkuk', 'accrual_reversal' => 'Tahakkuk düzeltme', 'collection' => 'Tahsilat', 'credit_usage' => 'Avans mahsubu',
        'credit_usage_reversal' => 'Avans mahsubu iptali', 'expense' => 'Gider ödemesi', 'refund' => 'Avans iadesi', 'transfer' => 'Virman',
        'general' => 'Genel gelir / gider', 'opening' => 'Açılış bakiyesi', 'fx_difference' => 'Kur farkı', 'restructure' => 'Taksitlendirme',
        'reversal' => 'İptal (ters kayıt)', 'manual' => 'Elle fiş', 'manual_reversal' => 'Elle fiş iptali',
    ];

    public const CASH = 'Kasa/Banka';

    public const UNIT = 'Üye/Daire Hesabı';

    public const UNIT_ADVANCE = 'Üye Avans Hesabı';

    public const INCOME = 'Gelir Hesabı';

    public const EXPENSE = 'Gider Hesabı';

    public const OPENING = 'Açılış Bakiyesi Hesabı';

    public const FX_DIFFERENCE = 'Kur Farkı Hesabı';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    /**
     * Kaydın bağlı olduğu belgenin okunur adı. Referans iç biçimdedir (ör. cash_tx:1771, debt:2970); aynı işlemin
     * borç ve alacak satırları aynı referansı taşır.
     */
    public function referenceLabel(): string
    {
        [$kind, $id] = array_pad(explode(':', $this->reference, 2), 2, '');

        return match ($kind) {
            'cash_tx' => 'Makbuz M-'.str_pad($id, 6, '0', STR_PAD_LEFT),
            'debt' => "Borç kaydı #{$id}",
            'unit' => 'Avans mahsubu',
            'manual' => 'Elle fiş '.mb_strtoupper(substr($id, 0, 8)),
            default => $this->reference,
        };
    }

    /** Referans bir kasa hareketiyse onun id'si (makbuz / işlem belgesine bağlanmak için) */
    public function cashTransactionId(): ?int
    {
        return str_starts_with($this->reference, 'cash_tx:') ? (int) substr($this->reference, 8) : null;
    }
}
