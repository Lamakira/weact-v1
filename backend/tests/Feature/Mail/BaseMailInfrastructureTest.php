<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BaseMailInfrastructureTest extends TestCase
{
    #[Test]
    public function test_base_mail_is_abstract_and_cannot_be_instantiated(): void
    {
        $reflection = new \ReflectionClass(BaseMail::class);

        $this->assertTrue(
            $reflection->isAbstract(),
            'BaseMail must be abstract so subclasses set their own content/subject.'
        );
    }

    #[Test]
    public function test_envelope_is_final_so_subclasses_cannot_drop_shared_reply_to(): void
    {
        $envelope = new \ReflectionMethod(BaseMail::class, 'envelope');

        $this->assertTrue(
            $envelope->isFinal(),
            'BaseMail::envelope() must be final to prevent subclasses from silently dropping the shared replyTo contract.'
        );
    }

    #[Test]
    public function test_base_mail_subclass_uses_shared_layout_and_reply_to(): void
    {
        Mail::fake();

        $mail = new class extends BaseMail
        {
            protected function subjectLine(): string
            {
                return 'Infrastructure test';
            }

            public function content(): Content
            {
                return new Content(
                    view: 'emails.layouts.base',
                    with: ['title' => 'Infrastructure test'],
                );
            }
        };

        Mail::to('recipient@example.com')->queue($mail);

        Mail::assertQueued(BaseMail::class, function (BaseMail $queued): bool {
            $envelope = $queued->envelope();

            return $envelope->subject === 'Infrastructure test'
                && in_array(
                    config('mail.from.address'),
                    array_map(fn ($addr) => $addr->address, $envelope->replyTo),
                    true,
                );
        });
    }

    #[Test]
    public function test_shared_layout_renders_brand_header_and_footer(): void
    {
        $html = view('emails.layouts.base')->render();

        $this->assertStringContainsString('#198496', $html, 'Layout must use WEACT brand color.');
        $this->assertStringContainsString('WEACT', $html, 'Layout must include brand mark in header.');
        $this->assertStringContainsString('mailto:'.config('mail.contact_to'), $html, 'Layout must include unsubscribe mailto link.');
        $this->assertStringContainsString('/mentions-legales', $html, 'Layout must include legal mentions link.');
    }
}
