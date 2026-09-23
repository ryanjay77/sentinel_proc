<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HighRiskAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly array $alerts,   // array of alert rows from the scan
        public readonly int   $scanTotal // total processes scanned
    ) {}

    public function envelope(): Envelope
    {
        $count = count($this->alerts);
        return new Envelope(
            subject: "[SentinelProc] {$count} HIGH Risk Process" . ($count > 1 ? 'es' : '') . ' Detected',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.high-risk-alert',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
