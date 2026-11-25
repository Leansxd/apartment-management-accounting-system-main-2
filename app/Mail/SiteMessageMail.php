<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Site yönetiminin sakinlere gönderdiği bildirim ve hatırlatma e-postası. İstek içinde beklenmez, kuyruktan gider. */
class SiteMessageMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> saniye */
    public array $backoff = [60, 300];

    public function __construct(public string $siteName, public string $subjectLine, public string $messageBody) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[{$this->siteName}] {$this->subjectLine}");
    }

    public function content(): Content
    {
        return new Content(text: 'emails.site-message');
    }
}
