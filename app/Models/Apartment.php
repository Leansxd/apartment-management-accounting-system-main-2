<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Apartment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['allow_negative_cashbox' => 'boolean'];
    }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function managers(): BelongsToMany { return $this->belongsToMany(User::class, 'apartment_manager'); }

    public function blocks(): HasMany { return $this->hasMany(Block::class); }
    public function units(): HasMany { return $this->hasMany(Unit::class); }
    public function unitGroups(): HasMany { return $this->hasMany(UnitGroup::class); }
    public function members(): HasMany { return $this->hasMany(Member::class); }
    public function categories(): HasMany { return $this->hasMany(TransactionCategory::class); }
    public function cashboxes(): HasMany { return $this->hasMany(Cashbox::class); }
    public function debts(): HasMany { return $this->hasMany(Debt::class); }
    public function cashTransactions(): HasMany { return $this->hasMany(CashTransaction::class); }
    public function debtPayments(): HasMany { return $this->hasMany(DebtPayment::class); }
    public function ledgerEntries(): HasMany { return $this->hasMany(LedgerEntry::class); }
    public function vendors(): HasMany { return $this->hasMany(Vendor::class); }
    public function expenses(): HasMany { return $this->hasMany(Expense::class); }
    public function duesDefinitions(): HasMany { return $this->hasMany(DuesDefinition::class); }
    public function auditLogs(): HasMany { return $this->hasMany(AuditLog::class); }
    public function staff(): HasMany { return $this->hasMany(Staff::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function inventoryItems(): HasMany { return $this->hasMany(InventoryItem::class); }
    public function meters(): HasMany { return $this->hasMany(Meter::class); }
    public function securityEntries(): HasMany { return $this->hasMany(SecurityEntry::class); }
    public function tickets(): HasMany { return $this->hasMany(Ticket::class); }
    public function announcements(): HasMany { return $this->hasMany(Announcement::class); }
    public function polls(): HasMany { return $this->hasMany(Poll::class); }
    public function decisions(): HasMany { return $this->hasMany(Decision::class); }
    public function siteNotifications(): HasMany { return $this->hasMany(SiteNotification::class); }

    /** Panel kullanıcısı bu apartmanı yönetebilir mi? (süper admin, sahip veya yönetici listesi) */
    public function isManagedBy(User $user): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return $user->isAdmin()
            && ($this->owner_id === $user->id || $this->managers()->whereKey($user->id)->exists());
    }
}
