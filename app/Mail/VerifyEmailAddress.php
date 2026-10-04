<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The "confirm your email" message (spec 014, AC10). Sent immediately: the user is waiting. */
final class VerifyEmailAddress extends Mailable
{
    use Queueable;

    public function __construct(public readonly string $firstName, public readonly string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Confirm your email for Sortd'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.verify-email');
    }
}
