<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Apartment;
use App\Models\Decision;
use App\Models\Poll;
use App\Services\AuditTrail;
use App\Services\ReminderService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Duyurular, anketler, karar defteri, bildirimler ve gecikmiş borç hatırlatmaları */
class CommunicationController extends Controller
{
    // ------------------------------------------------------------------ DUYURU

    public function announcements(Apartment $apartment)
    {
        return view('site.comm.announcements', [
            'apartment' => $apartment,
            'announcements' => $apartment->announcements()->latest('id')->paginate(10),
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

    public function updateAnnouncement(Request $request, Apartment $apartment, Announcement $announcement)
    {
        $announcement->update($request->validate([
            'title' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['normal', 'high'])],
            'expires_on' => ['nullable', 'date'],
        ]));

        return back()->with('status', 'Duyuru güncellendi.');
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

    /** Oy kullanılmış ankette soru ve seçenekler sabitlenir, yalnızca bitiş tarihi değişir. */
    public function updatePoll(Request $request, Apartment $apartment, Poll $poll)
    {
        if ($poll->votes()->exists()) {
            $poll->update($request->validate(['due_date' => ['nullable', 'date']]));

            return back()->with('status', 'Bitiş tarihi güncellendi.');
        }

        $data = $request->validate([
            'question' => ['required', 'string', 'max:200'],
            'due_date' => ['nullable', 'date'],
            'options' => ['required', 'string'],
        ]);
        $options = collect(preg_split('/\r?\n/', $data['options']))->map(fn ($o) => trim($o))->filter()->unique()->values();
        if ($options->count() < 2 || $options->count() > 10) {
            return back()->withInput()->with('error', 'Anket 2 ile 10 arasında seçenek içermelidir.');
        }

        DB::transaction(function () use ($poll, $data, $options) {
            $poll->update(['question' => $data['question'], 'due_date' => $data['due_date'] ?? null]);
            $poll->options()->delete();
            $options->each(fn ($text) => $poll->options()->create(['text' => mb_substr($text, 0, 150)]));
        });

        return back()->with('status', 'Anket güncellendi.');
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

    public function decisions(Request $request, Apartment $apartment)
    {
        return view('site.comm.decisions', [
            'apartment' => $apartment,
            'decisions' => $apartment->decisions()
                ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('subject', 'like', "%{$s}%")->orWhere('decision_no', 'like', "%{$s}%")))
                ->orderByDesc('decided_on')->orderByDesc('id')->get(),
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

    public function updateDecision(Request $request, Apartment $apartment, Decision $decision)
    {
        $decision->update($request->validate([
            'decision_no' => ['required', 'string', 'max:20', Rule::unique('decisions')->where('apartment_id', $apartment->id)->ignore($decision->id)],
            'decided_on' => ['required', 'date'],
            'subject' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:10000'],
            'attendees' => ['nullable', 'string', 'max:500'],
        ]));

        return back()->with('status', 'Karar güncellendi.');
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
            'notifications' => $apartment->siteNotifications()->with('unit.block')->withCount('readers')->latest('id')->paginate(10),
            'blocks' => $apartment->blocks()->orderBy('name')->get(),
            'units' => $apartment->units()->with('block')->get()->sortBy(fn ($u) => [$u->block?->name, (int) $u->unit_no]),
        ]);
    }

    /** Hedef: tüm site (tek kayıt), bir blok / borçlular / seçili daireler (daire başına kayıt) */
    public function sendNotification(Request $request, Apartment $apartment, ReminderService $reminders, AuditTrail $audit)
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
        $count = $reminders->notify($apartment, $request->user()->id, $data['title'], $data['body'], $unitIds);
        $mail = $request->boolean('send_email') ? $reminders->emailMembers($apartment, $unitIds, $data['title'], $data['body']) : null;
        $audit->log($apartment->id, 'SEND_NOTIFICATION', 'COMMUNICATION', null, ['title' => $data['title'], 'target' => $data['target'], 'count' => $count, 'emails_queued' => $mail['queued'] ?? 0]);

        return back()->with('status', $count ? "Bildirim gönderildi ({$count} kayıt)."
            .($mail ? " E-posta: {$mail['queued']} sıraya alındı".($mail['failed'] ? ", {$mail['failed']} hatalı" : '').'.' : '') : 'Hedefte daire bulunamadı.');
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
    public function sendReminders(Request $request, Apartment $apartment, ReportService $reports, ReminderService $reminders, AuditTrail $audit)
    {
        $selected = collect($request->validate(['unit_ids' => ['required', 'array'], 'unit_ids.*' => ['integer']])['unit_ids']);
        $overdue = $reports->overdueByUnit($apartment)->filter(fn ($row) => $selected->contains($row['unit']->id));
        $sendEmail = $request->boolean('send_email');

        $result = $reminders->remind($apartment, $overdue, $sendEmail, $request->user()->id);
        $audit->log($apartment->id, 'SEND_REMINDERS', 'COMMUNICATION', null, $result);

        return back()->with('status', $overdue->count().' daireye hatırlatma gönderildi.'
            .($sendEmail ? " E-posta: {$result['queued']} sıraya alındı".($result['failed'] ? ", {$result['failed']} hatalı" : '').'.' : ''));
    }

    public function reminderSettings(Request $request, Apartment $apartment)
    {
        $data = $request->validate(['reminder_interval_days' => ['required', 'integer', 'min:1', 'max:60']]);
        $apartment->update($data + [
            'reminder_auto_run' => $request->boolean('reminder_auto_run'),
            'reminder_send_email' => $request->boolean('reminder_send_email'),
        ]);

        return back()->with('status', 'Otomatik hatırlatma ayarları kaydedildi.');
    }
}
