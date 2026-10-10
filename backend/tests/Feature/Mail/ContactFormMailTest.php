<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\ContactFormMail;
use Tests\TestCase;

class ContactFormMailTest extends TestCase
{
    public function test_contact_mail_renders_with_the_visitor_subject(): void
    {
        $mail = new ContactFormMail(
            senderName: 'Aïcha K.',
            senderEmail: 'aicha@example.com',
            messageSubject: 'Question sur les tarifs',
            senderMessage: 'Bonjour, comment fonctionnent les paliers ?',
        );

        $html = $mail->render();

        $this->assertStringContainsString('Question sur les tarifs', $html);
        $this->assertStringContainsString('aicha@example.com', $html);
        $this->assertStringContainsString('comment fonctionnent les paliers', $html);
        $mail->assertHasSubject('[WEACT Contact] Question sur les tarifs');
    }
}
