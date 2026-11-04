<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Apartment;
use App\Models\Decision;
use App\Models\Poll;
use App\Services\ReportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/** Duyurular, anketler, karar defteri, bildirimler ve gecikmiş borç hatırlatmaları */
class CommunicationController extends Controller
{
    // ------------------------------------------------------------------ DUYURU

    public function announcements(Apartment $apartment)
    {
        return view('site.comm.announcements', [
            'apartment' => $apartment,
            'announcements' => $apartment->announcements()->latest('id')->paginate(30),
        ]);
    }

    public function storeAnnouncement(Request $request, Apartment $apartment)
    {
        $apartment->announcements()->create($request->validate([
            'title' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['normal', 'high'])],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]) + ['created_by' => $request->user()->id]);

        return back()->with('status', 'Duyuru yayınlandı.');
    }

    public function toggleAnnouncement(Apartment $apartment, Announcement $announcement)
    {
        $announcement->update(['is_active' => ! $announcement->is_active]);

        return back()->with('status', $announcement->is_active ? 'Duyuru yayına alındı.' : 'Duyuru yayından kaldırıldı.');
    }

    public function destroyAnnouncement(Apartment $apartment, Announcement $announcement)
    {
        $announcement->delete();

        return back()->with('status', 'Duyuru silindi.');
    }

    // ------------------------------------------------------------------ ANKET

    public function polls(Apartment $apartment)
    {
        return view('site.comm.polls', [
            'apartment' => $apartment,
            'polls' => $apartment->polls()->with(['options' => fn ($q) => $q->withCount('votes')])->withCount('votes')->latest('id')->get(),
            'unitCount' => $apartment->units()->count(),
        ]);
    }

    public function storePoll(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:200'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'options' => ['required', 'string'], // her satıra bir seçenek
        ]);
        $options = collect(preg_split('/\r?\n/', $data['options']))->map(fn ($o) => trim($o))->filter()->unique()->values();
        if ($options->count() < 2 || $options->count() > 10) {
            return back()->withInput()->with('error', 'Anket 2 ile 10 arasında seçenek içermelidir.');
        }

        DB::transaction(function () use ($apartment, $data, $options) {
            $poll = $apartment->polls()->create(['question' => $data['question'], 'due_date' => $data['due_date'] ?? null]);
            $options->each(fn ($text) => $poll->options()->create(['text' => mb_substr($text, 0, 150)]));
        });

        return back()->with('status', 'Anket oluşturuldu.');
    }

    public function togglePoll(Apartment $apartment, Poll $poll)
    {
        $poll->update(['is_active' => ! $poll->is_active]);

        return back()->with('status', $poll->is_active ? 'Anket açıldı.' : 'Anket kapatıldı.');
    }

    public function destroyPoll(Apartment $apartment, Poll $poll)
    {
        $poll->delete();

        return back()->with('status', 'Anket silindi.');
    }

    // ------------------------------------------------------------------ KARAR DEFTERİ

    public function decisions(Apartment $apartment)
    {
        return view('site.comm.decisions', [
            'apartment' => $apartment,
            'decisions' => $apartment->decisions()->orderByDesc('decided_on')->orderByDesc('id')->get(),
            'nextNo' => now()->year.'/'.str_pad((string) ($apartment->decisions()->whereYear('decided_on', now()->year)->count() + 1), 3, '0', STR_PAD_LEFT),
        ]);
    }

    public function storeDecision(Request $request, Apartment $apartment)
    {
        $apartment->decisions()->create($request->validate([
            'decision_no' => ['required', 'string', 'max:20', Rule::unique('decisions')->where('apartment_id', $apartment->id)],
            'decided_on' => ['required', 'date'],
            'subject' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:10000'],
            'attendees' => ['nullable', 'string', 'max:500'],
        ]));

        return back()->with('status', 'Karar kaydedildi.');
    }

    public function destroyDecision(Apartment $apartment, Decision $decision)
    {
        $decision->delete();

        return back()->with('status', 'Karar silindi.');
    }

    // ------------------------------------------------------------------ BİLDİRİM

    public function notifications(Apartment $apartment)
    {
        return view('site.comm.notifications', [
            'apartment' => $apartment,
            'notifications' => $apartment->siteNotifications()->with('unit.block')->withCount('readers')->latest('id')->paginate(40),
            'blocks' => $apartment->blocks()->orderBy('name')->get(),
            'units' => $apartment->units()->with('block')->get()->sortBy(fn ($u) => [$u->block?->name, (int) $u->unit_no]),
        ]);
    }

    /** Hedef: tüm site (tek kayıt), bir blok / borçlular / seçili daireler (daire başına kayıt) */
    public function sendNotification(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'target' => ['required', Rule::in(['all', 'block', 'debtors', 'units'])],
            'block_id' => ['required_if:target,block', 'nullable', Rule::exists('blocks', 'id')->where('apartment_id', $apartment->id)],
            'unit_ids' => ['required_if:target,units', 'array'],
            'unit_ids.*' => [Rule::exists('units', 'id')->where('apartment_id', $apartment->id)],
        ]);

        $unitIds = match ($data['target']) {
            'all' => null,
            'block' => $apartment->units()->where('block_id', $data['block_id'])->pluck('id')->all(),
            'debtors' => $apartment->debts()->where('status', 'pending')->whereDate('due_date', '<', today())->distinct()->pluck('unit_id')->all(),
            'units' => $data['unit_ids'],
        };
        $count = $this->notify($apartment, $request->user()->id, $data['title'], $data['body'], $unitIds);
        $mail = $request->boolean('send_email') ? $this->emailMembers($apartment, $unitIds, $data['title'], $data['body']) : null;

        return back()->with('status', $count ? "Bildirim gönderildi ({$count} kayıt)."
            .($mail ? " E-posta: {$mail['sent']} gönderildi".($mail['failed'] ? ", {$mail['failed']} hatalı" : '').'.' : '') : 'Hedefte daire bulunamadı.');
    }

    /**
     * Hedef dairelerin (null = tüm site) e-posta adresi olan üyelerine e-posta gönderir.
     * E-posta ayarı yoksa (MAIL_MAILER=log) mesajlar storage/logs içine yazılır; gönderim hatası işlemi bozmaz.
     *
     * @param  array<int,int>|null  $unitIds
     * @return array{sent: int, failed: int}
     */
    private function emailMembers(Apartment $apartment, ?array $unitIds, string $subject, string $body): array
    {
        $emails = $apartment->members()->whereNotNull('email')->where('email', '!=', '')
            ->when($unitIds !== null, fn ($q) => $q->whereIn('unit_id', $unitIds))->pluck('email')->unique()->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        $result = ['sent' => 0, 'failed' => 0];
        foreach ($emails as $email) {
            try {
                Mail::raw("{$body}\n\n— {$apartment->name} Yönetimi", fn ($m) => $m->to($email)->subject("[{$apartment->name}] {$subject}"));
                $result['sent']++;
            } catch (\Throwable $e) {
                report($e);
                $result['failed']++;
            }
        }

        return $result;
    }

    // ------------------------------------------------------------------ AKILLI HATIRLATICI

    public function reminders(Apartment $apartment, ReportService $reports)
    {
        return view('site.comm.reminders', [
            'apartment' => $apartment,
            'overdue' => $reports->overdueByUnit($apartment),
        ]);
    }

    /** Seçilen gecikmiş dairelere kendi borç tutarlarını içeren hatırlatma gönderir */
    public function sendReminders(Request $request, Apartment $apartment, ReportService $reports)
    {
        $selected = collect($request->validate(['unit_ids' => ['required', 'array'], 'unit_ids.*' => ['integer']])['unit_ids']);
        $overdue = $reports->overdueByUnit($apartment)->filter(fn ($row) => $selected->contains($row['unit']->id));

        $mail = ['sent' => 0, 'failed' => 0];
        foreach ($overdue as $row) {
            $body = "Sayın sakinimiz, {$row['unit']->label()} dairesine ait vadesi geçmiş ".Money::format($row['total'])
                .' tutarında borcunuz bulunmaktadır. Lütfen en kısa sürede ödeme yapınız.';
            $this->notify($apartment, $request->user()->id, 'Ödeme Hatırlatması', $body, [$row['unit']->id], 'warning');
            if ($request->boolean('send_email')) {
                $r = $this->emailMembers($apartment, [$row['unit']->id], 'Ödeme Hatırlatması', $body);
                $mail['sent'] += $r['sent'];
                $mail['failed'] += $r['failed'];
            }
        }

        return back()->with('status', $overdue->count().' daireye hatırlatma gönderildi.'
            .($request->boolean('send_email') ? " E-posta: {$mail['sent']} gönderildi".($mail['failed'] ? ", {$mail['failed']} hatalı" : '').'.' : ''));
    }

    /** @param  array<int,int>|null  $unitIds  null = tüm site */
    private function notify(Apartment $apartment, int $by, string $title, string $body, ?array $unitIds, string $type = 'info'): int
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
}
