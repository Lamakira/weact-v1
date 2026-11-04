<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/**
 * Code à 6 chiffres de signature d'une autorisation d'utilisation de l'image.
 * Envoyé en synchrone : l'échec d'envoi doit remonter à l'utilisateur.
 */
final class SignatureCodeMail extends BaseMail
{
    public function __construct(
        public readonly string $code,
        public readonly int $ttlMinutes,
        public readonly string $signerName,
        public readonly bool $forGuardian,
        public readonly ?string $guardianName = null,
    ) {}

    protected function subjectLine(): string
    {
        return 'Votre code de signature WeAct';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signature-code',
            with: [
                'code' => $this->code,
                'ttlMinutes' => $this->ttlMinutes,
                'signerName' => $this->signerName,
                'forGuardian' => $this->forGuardian,
                'guardianName' => $this->guardianName,
            ],
        );
    }
}
