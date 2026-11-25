<?php

namespace App\Models;

use App\Support\Money;
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
        'fx_difference' => 'Kur Farkı',
    ];

    public const COLLECTION_TYPES = ['collection', 'pos', 'advance'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'applied_to_debts' => 'decimal:2',
            'to_advance' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'reversed_at' => 'datetime',
        ];
    }

    /** Döviz bilgisi verilmeyen hareketler TRY'dir: orijinal tutar TRY tutarıdır, kur 1'dir. */
    protected static function booted(): void
    {
        static::creating(function (CashTransaction $transaction): void {
            $transaction->currency ??= Cashbox::BASE_CURRENCY;
            $transaction->exchange_rate ??= 1;
            $transaction->original_amount ??= $transaction->amount;
        });
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

    /** Tahsilatı ödeyen sakin (seçilmediyse daire adına alınmıştır) */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'payer_member_id')->withTrashed();
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

    public function isForeign(): bool
    {
        return $this->currency !== Cashbox::BASE_CURRENCY;
    }

    /** Orijinal tutar ve kur: "100,00 $ × 30,5000". TRY harekette boştur. */
    public function originalLabel(): ?string
    {
        if (! $this->isForeign() || $this->isFxDifference()) {
            return null;
        }

        return Money::formatIn($this->original_amount, $this->currency).' × '.Money::formatRate($this->exchange_rate);
    }

    /** Kur farkı kaydı virmanın parçasıdır, tek başına iptal edilemez. */
    public function isFxDifference(): bool
    {
        return $this->sub_type === 'fx_difference';
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
