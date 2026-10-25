<?php

namespace FreshCart\Operations\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OperationsAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $alertTitle,
        public ?string $alertBody = null,
        public ?string $alertUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[FreshCart] '.$this->alertTitle);
    }

    public function content(): Content
    {
        return new Content(view: 'operations::emails.alert');
    }
}
