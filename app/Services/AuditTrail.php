<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Apartment;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\BudgetItem;
use App\Models\Cashbox;
use App\Models\CommonArea;
use App\Models\Decision;
use App\Models\Document;
use App\Models\DuesDefinition;
use App\Models\InventoryItem;
use App\Models\Meeting;
use App\Models\Member;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Poll;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\SecurityEntry;
use App\Models\Staff;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Denetim izi: kayıt değişikliklerini (eski/yeni değerle) ve oturum olaylarını yazar.
 * Para hareketleri FinanceService içinde işlem detayıyla ayrıca kaydedildiği için burada izlenmez.
 */
class AuditTrail
{
    /** @var array<class-string<Model>, string> izlenen model => denetim modülü */
    public const MODELS = [
        User::class => 'USERS',
        Apartment::class => 'SETTINGS',
        Member::class => 'MEMBERS',
        Block::class => 'STRUCTURE',
        UnitGroup::class => 'STRUCTURE',
        Unit::class => 'STRUCTURE',
        Property::class => 'PORTFOLIO',
        DuesDefinition::class => 'DEBT',
        BudgetItem::class => 'FINANCE',
        Cashbox::class => 'FINANCE',
        TransactionCategory::class => 'FINANCE',
        Vendor::class => 'EXPENSE',
        Asset::class => 'OPERATIONS',
        InventoryItem::class => 'OPERATIONS',
        Meter::class => 'OPERATIONS',
        MeterReading::class => 'OPERATIONS',
        Staff::class => 'OPERATIONS',
        Task::class => 'OPERATIONS',
        Ticket::class => 'OPERATIONS',
        TicketMessage::class => 'OPERATIONS',
        Reservation::class => 'OPERATIONS',
        CommonArea::class => 'OPERATIONS',
        SecurityEntry::class => 'OPERATIONS',
        Document::class => 'OPERATIONS',
        Announcement::class => 'COMMUNICATION',
        Decision::class => 'COMMUNICATION',
        Poll::class => 'COMMUNICATION',
        Meeting::class => 'COMMUNICATION',
    ];

    /** Kendi başına anlam taşımayan, sistemin güncellediği alanlar */
    private const IGNORED_FIELDS = [
        'created_at', 'updated_at', 'last_login_at', 'remember_token', 'balance', 'currency_balance',
        'two_factor_last_timestamp', 'credit_balance', 'last_value', 'closed_until', 'reminder_last_run_at', 'last_run_at', 'last_run_period',
    ];

    private const MASKED_FIELDS = '/password|token|secret|recovery|id_number|invite_code/i';

    private const MAX_VALUE_LENGTH = 300;

    public static function watchModels(): void
    {
        foreach (array_keys(self::MODELS) as $model) {
            foreach (['created' => 'CREATE', 'updated' => 'UPDATE', 'deleted' => 'DELETE'] as $event => $prefix) {
                $model::{$event}(fn (Model $record) => app(self::class)->recordModelChange($record, $prefix));
            }
        }
    }

    /** @param array<string, mixed> $data */
    public function log(?int $apartmentId, string $action, string $module, ?string $reference = null, array $data = []): void
    {
        AuditLog::create([
            'apartment_id' => $apartmentId, 'user_id' => auth()->id(), 'action' => $action,
            'module' => $module, 'reference' => $reference, 'data' => $data,
        ]);
    }

    /**
     * Giriş, çıkış ve benzeri oturum olayları. Şifre hiçbir zaman yazılmaz.
     *
     * @param  array<string, mixed>  $data
     */
    public function logAuthentication(string $action, ?User $user, array $data = []): void
    {
        AuditLog::create([
            'user_id' => $user?->id, 'action' => $action, 'module' => 'AUTH',
            'reference' => $user ? "user:{$user->id}" : null, 'data' => $data,
        ]);
    }

    public function recordModelChange(Model $model, string $prefix): void
    {
        if (! config('audit.record_model_changes')) {
            return;
        }

        $data = $prefix === 'UPDATE' ? ['changes' => $this->changes($model)] : ['values' => $this->values($model)];
        if ($prefix === 'UPDATE' && $data['changes'] === []) {
            return;
        }

        $name = Str::snake(class_basename($model));
        AuditLog::create([
            'apartment_id' => $this->apartmentIdOf($model, $prefix),
            'user_id' => $model instanceof User && $prefix === 'DELETE' && $model->getKey() === auth()->id() ? null : auth()->id(),
            'action' => $prefix.'_'.mb_strtoupper($name),
            'module' => self::MODELS[$model::class],
            'reference' => "{$name}:{$model->getKey()}",
            'data' => $data,
        ]);
    }

    /** @return array<string, array{old: mixed, new: mixed}> */
    private function changes(Model $model): array
    {
        $changes = [];
        foreach ($model->getChanges() as $field => $value) {
            if (! in_array($field, self::IGNORED_FIELDS, true)) {
                $changes[$field] = ['old' => $this->clean($field, $model->getRawOriginal($field)), 'new' => $this->clean($field, $value)];
            }
        }

        return $changes;
    }

    /** @return array<string, mixed> */
    private function values(Model $model): array
    {
        $values = [];
        foreach ($model->getAttributes() as $field => $value) {
            if (! in_array($field, self::IGNORED_FIELDS, true) && $value !== null) {
                $values[$field] = $this->clean($field, $value);
            }
        }

        return $values;
    }

    private function clean(string $field, mixed $value): mixed
    {
        if (preg_match(self::MASKED_FIELDS, $field)) {
            return $value === null ? null : '***';
        }

        return is_string($value) ? Str::limit($value, self::MAX_VALUE_LENGTH) : $value;
    }

    /** Silinen sitenin numarası yabancı anahtarı bozacağı için boş bırakılır; kayıt yine de kalır. */
    private function apartmentIdOf(Model $model, string $prefix): ?int
    {
        if ($model instanceof Apartment) {
            return $prefix === 'DELETE' ? null : $model->getKey();
        }

        return $model->getAttributes()['apartment_id'] ?? null;
    }
}
