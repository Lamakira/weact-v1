<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

abstract class BaseMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Build the envelope with the WEACT sender identity.
     *
     * Intentionally `final`: subclasses extend via `subjectLine()` so the shared
     * `replyTo` contract cannot be dropped by mistake when overriding.
     */
    final public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine(),
            replyTo: [$this->replyToAddress()],
        );
    }

    /**
     * Subject line for this mailable. Each concrete mailable must provide its own.
     */
    abstract protected function subjectLine(): string;

    /**
     * Reply-to address shared across every cycle-mission email.
     * Exposed as a protected method so a subclass can override for
     * domain-specific reply-to (ex. contact@ for contact form) if needed.
     */
    protected function replyToAddress(): string
    {
        return (string) config('mail.from.address');
    }
}
