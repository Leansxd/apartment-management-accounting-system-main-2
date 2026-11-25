<?php

namespace App\Services;

use App\Mail\SiteMessageMail;
use App\Models\Apartment;
use App\Models\Unit;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Site bildirimleri, e-postaları ve gecikmiş borç hatırlatmaları */
class ReminderService
{
    public function __construct(private ReportService $reports, private AuditTrail $audit) {}

    /**
     * Gecikmiş dairelere kendi borç tutarlarını içeren hatırlatma yazar.
     *
     * @param  Collection<int, array{unit: Unit, total: mixed}>  $overdue
     * @return array{units: int, queued: int, failed: int}
     */
    public function remind(Apartment $apartment, Collection $overdue, bool $sendEmail, ?int $by): array
    {
        $result = ['units' => $overdue->count(), 'queued' => 0, 'failed' => 0];
        foreach ($overdue as $row) {
            $body = "Sayın sakinimiz, {$row['unit']->label()} dairesine ait vadesi geçmiş ".Money::format($row['total'])
                .' tutarında borcunuz bulunmaktadır. Lütfen en kısa sürede ödeme yapınız.';
            $this->notify($apartment, $by, 'Ödeme Hatırlatması', $body, [$row['unit']->id], 'warning');
            if ($sendEmail) {
                $mail = $this->emailMembers($apartment, [$row['unit']->id], 'Ödeme Hatırlatması', $body);
                $result['queued'] += $mail['queued'];
                $result['failed'] += $mail['failed'];
            }
        }

        return $result;
    }

    /**
     * Otomatik hatırlatması açık sitede, aralık dolduysa gecikmiş dairelere hatırlatma gönderir.
     *
     * @return int|null gönderilen daire sayısı; zamanı gelmediyse null
     */
    public function runScheduled(Apartment $apartment): ?int
    {
        $lastRun = $apartment->reminder_last_run_at;
        if (! $apartment->reminder_auto_run
            || ($lastRun && now()->startOfDay()->lt($lastRun->copy()->startOfDay()->addDays($apartment->reminder_interval_days)))) {
            return null;
        }

        $overdue = $this->reports->overdueByUnit($apartment);
        if ($overdue->isEmpty()) {
            return 0;
        }

        $result = $this->remind($apartment, $overdue, $apartment->reminder_send_email, null);
        $apartment->update(['reminder_last_run_at' => now()]);
        $this->audit->log($apartment->id, 'AUTO_REMINDERS', 'COMMUNICATION', null, $result);

        return $result['units'];
    }

    /** @param  array<int,int>|null  $unitIds  null = tüm site */
    public function notify(Apartment $apartment, ?int $by, string $title, string $body, ?array $unitIds, string $type = 'info'): int
    {
        $base = ['title' => $title, 'body' => $body, 'type' => $type, 'created_by' => $by];
        if ($unitIds === null) {
            $apartment->siteNotifications()->create($base);

            return 1;
        }
        $unitIds = array_values(array_unique($unitIds));
        DB::transaction(fn () => collect($unitIds)->each(fn ($id) => $apartment->siteNotifications()->create($base + ['unit_id' => $id])));

        return count($unitIds);
    }

    /**
     * Hedef dairelerin (null = tüm site) e-posta adresi olan üyelerine gidecek e-postaları kuyruğa alır.
     * E-posta ayarı yoksa (MAIL_MAILER=log) mesajlar storage/logs içine yazılır; sıraya alma hatası işlemi bozmaz.
     *
     * @param  array<int,int>|null  $unitIds
     * @return array{queued: int, failed: int}
     */
    public function emailMembers(Apartment $apartment, ?array $unitIds, string $subject, string $body): array
    {
        $emails = $apartment->members()->whereNotNull('email')->where('email', '!=', '')
            ->when($unitIds !== null, fn ($q) => $q->whereIn('unit_id', $unitIds))->pluck('email')->unique()->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        $result = ['queued' => 0, 'failed' => 0];
        foreach ($emails as $email) {
            try {
                Mail::to($email)->queue(new SiteMessageMail($apartment->name, $subject, $body));
                $result['queued']++;
            } catch (Throwable $e) {
                report($e);
                $result['failed']++;
            }
        }

        return $result;
    }
}
