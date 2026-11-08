<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kasa hareketi. Düzenlenmez ve silinmez: yanlış kayıt, ters hareketle (iptal) düzeltilir.
 * İptal edilen hareket reversed_at ile işaretlenir; iptali de sub_type = 'reversal' bir harekettir.
 */
class CashTransaction extends Model
{
    public const SUB_TYPES = [
        'collection' => 'Tahsilat',
        'advance' => 'Avans',
        'pos' => 'POS / Kredi Kartı',
        'invoice' => 'Gider Ödemesi',
        'refund' => 'Avans İadesi',
        'transfer_in' => 'Virman (Gelen)',
        'transfer_out' => 'Virman (Giden)',
        'reversal' => 'İptal (Ters Kayıt)',
        'general' => 'Genel',
        'opening' => 'Açılış Bakiyesi',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'applied_to_debts' => 'decimal:2',
            'to_advance' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    /** Banka metni veya iptal nedeni eklenen açıklamalar kolon sınırını (255) aşmasın. */
    protected function description(): Attribute
    {
        return Attribute::set(fn (string $value): string => mb_substr($value, 0, 255));
    }

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /** İptal edilmemiş ve iptal kaydı olmayan hareketler: toplamlar yalnızca bunlardan hesaplanır. */
    public function scopeEffective(Builder $query): void
    {
        $query->whereNull('reversed_at')->where('sub_type', '!=', 'reversal');
    }

    /** Geçerli gelir/gider hareketleri: kasalar arası virmanlar ve açılış bakiyeleri gelir veya gider sayılmaz. */
    public function scopeOperating(Builder $query): void
    {
        $query->effective()->where('sub_type', 'not like', 'transfer%')->where('sub_type', '!=', 'opening');
    }

    public function isTransfer(): bool
    {
        return str_starts_with($this->sub_type, 'transfer');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    /** Makbuz numarası: M-000123 */
    public function receiptNo(): string
    {
        return 'M-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }
}
