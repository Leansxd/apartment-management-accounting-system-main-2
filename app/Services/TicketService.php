<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Destek talebi yazışması: mesaj, fotoğraf eki ve yönetim yanıtı bildirimi */
class TicketService
{
    /** @var array<int, string> */
    public const PHOTO_RULES = ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

    public function __construct(private ReminderService $reminders) {}

    public function addMessage(Ticket $ticket, ?User $sender, bool $isStaff, ?string $body, ?UploadedFile $photo = null): TicketMessage
    {
        if ($ticket->status === 'closed') {
            throw new FinanceException('Kapanmış talebe mesaj yazılamaz.', 422);
        }
        if (blank($body) && ! $photo) {
            throw new FinanceException('Mesaj veya fotoğraf ekleyin.', 422);
        }

        $message = $ticket->messages()->create([
            'apartment_id' => $ticket->apartment_id, 'user_id' => $sender?->id, 'is_staff' => $isStaff, 'body' => $body,
            'attachment_path' => $photo?->store("tickets/{$ticket->apartment_id}", 'local'),
            'attachment_name' => $photo?->getClientOriginalName(),
        ]);

        if ($isStaff && $ticket->unit_id) {
            $this->reminders->notify($ticket->apartment, $sender?->id, 'Talebinize yanıt verildi', $ticket->subject, [$ticket->unit_id]);
        }
        if (! $isStaff && $ticket->status === 'resolved') {
            $ticket->update(['status' => 'open']);
        }

        return $message;
    }

    public function attachmentResponse(TicketMessage $message): StreamedResponse
    {
        abort_unless($message->attachment_path && Storage::disk('local')->exists($message->attachment_path), 404, 'Dosya bulunamadı.');

        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name);
    }

    public function delete(Ticket $ticket): void
    {
        Storage::disk('local')->delete($ticket->messages()->whereNotNull('attachment_path')->pluck('attachment_path')->all());
        $ticket->delete();
    }
}
