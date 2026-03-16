<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowBalanceMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public float $balance)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'AsanFinance Low Balance Alert');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.low-balance');
    }
}
