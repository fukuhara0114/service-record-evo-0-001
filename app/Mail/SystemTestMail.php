<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SystemTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $sentAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Service Record] Gmail SMTP テスト送信',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.system-test',
        );
    }
}
