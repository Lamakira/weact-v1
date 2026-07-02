<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Enums\FaceSubscriptionPlan;
use App\Mail\FaceSubscriptionActivatedMail;
use App\Mail\FaceSubscriptionExpiredMail;
use App\Mail\FaceSubscriptionRenewalReminderMail;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceSubscriptionMailRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_activated_mail_renders_starter_subject_and_body(): void
    {
        [$face, $subscription] = $this->makeFixture(plan: FaceSubscriptionPlan::Starter, paidAmount: 10000);

        $mail = new FaceSubscriptionActivatedMail(face: $face, subscription: $subscription);

        $envelope = $mail->envelope();
        $this->assertSame('Votre abonnement Starter est activé', $envelope->subject);

        $rendered = html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertRenderedContainsPremiumMediaSummary($rendered, FaceSubscriptionPlan::Starter);
        $this->assertStringContainsString('Starter', $rendered);
        $this->assertStringContainsString('votre 2ème photo d\'album', $rendered);
        $this->assertStringContainsString('votre vidéo de présentation', $rendered);
        $this->assertStringNotContainsString('Premium annuel', $rendered);
        $this->assertStringNotContainsString('photos 3-4', $rendered);
    }

    public function test_activated_mail_renders_pro_subject_and_body(): void
    {
        [$face, $subscription] = $this->makeFixture(plan: FaceSubscriptionPlan::Pro, paidAmount: 25000);

        $mail = new FaceSubscriptionActivatedMail(face: $face, subscription: $subscription);

        $envelope = $mail->envelope();
        $this->assertSame('Votre abonnement Pro est activé', $envelope->subject);

        $rendered = html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertRenderedContainsPremiumMediaSummary($rendered, FaceSubscriptionPlan::Pro);
        $this->assertStringContainsString('Pro', $rendered);
        $this->assertStringContainsString('vos photos 2 à 4 d\'album', $rendered);
        $this->assertStringContainsString('votre vidéo de jeu', $rendered);
        $this->assertStringNotContainsString('Premium annuel', $rendered);
        $this->assertStringNotContainsString('photos 3-4', $rendered);
    }

    public function test_activated_mail_renders_elite_subject_and_body(): void
    {
        [$face, $subscription] = $this->makeFixture(plan: FaceSubscriptionPlan::Elite, paidAmount: 50000);

        $mail = new FaceSubscriptionActivatedMail(face: $face, subscription: $subscription);

        $envelope = $mail->envelope();
        $this->assertSame('Votre abonnement Élite est activé', $envelope->subject);

        $rendered = html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertRenderedContainsPremiumMediaSummary($rendered, FaceSubscriptionPlan::Elite);
        $this->assertStringContainsString('Élite', $rendered);
        $this->assertStringContainsString('vos photos 2 à 6 d\'album', $rendered);
        $this->assertStringContainsString('vos 2 vidéos de jeu', $rendered);
        $this->assertStringContainsString('votre vidéo UGC', $rendered);
        $this->assertStringNotContainsString('Premium annuel', $rendered);
        $this->assertStringNotContainsString('photos 3-4', $rendered);
    }

    public function test_expired_mail_renders_starter_subject_and_body(): void
    {
        [$face, $subscription] = $this->makeFixture(plan: FaceSubscriptionPlan::Starter);

        $mail = new FaceSubscriptionExpiredMail(face: $face, subscription: $subscription);

        $envelope = $mail->envelope();
        $this->assertSame('Votre abonnement Starter a expiré', $envelope->subject);

        $rendered = html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertRenderedContainsPremiumMediaSummary($rendered, FaceSubscriptionPlan::Starter);
        $this->assertStringContainsString('Starter', $rendered);
        $this->assertStringContainsString('votre 2ème photo d\'album', $rendered);
        $this->assertStringNotContainsString('Premium annuel', $rendered);
        $this->assertStringNotContainsString('photos 3-4', $rendered);
    }

    public function test_expired_mail_renders_pro_subject_and_body(): void
    {
        [$face, $subscription] = $this->makeFixture(plan: FaceSubscriptionPlan::Pro);

        $mail = new FaceSubscriptionExpiredMail(face: $face, subscription: $subscription);

        $envelope = $mail->envelope();
        $this->assertSame('Votre abonnement Pro a expiré', $envelope->subject);

        $rendered = html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertRenderedContainsPremiumMediaSummary($rendered, FaceSubscriptionPlan::Pro);
        $this->assertStringContainsString('Pro', $rendered);
        $this->assertStringContainsString('vos photos 2 à 4 d\'album', $rendered);
        $this->assertStringContainsString('votre vidéo de jeu', $rendered);
        $this->assertStringNotContainsString('Premium annuel', $rendered);
        $this->assertStringNotContainsString('photos 3-4', $rendered);
    }

    public function test_expired_mail_renders_elite_subject_and_body(): void
    {
        [$face, $subscription] = $this->makeFixture(plan: FaceSubscriptionPlan::Elite);

        $mail = new FaceSubscriptionExpiredMail(face: $face, subscription: $subscription);

        $envelope = $mail->envelope();
        $this->assertSame('Votre abonnement Élite a expiré', $envelope->subject);

        $rendered = html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertRenderedContainsPremiumMediaSummary($rendered, FaceSubscriptionPlan::Elite);
        $this->assertStringContainsString('Élite', $rendered);
        $this->assertStringContainsString('vos photos 2 à 6 d\'album', $rendered);
        $this->assertStringContainsString('votre vidéo UGC', $rendered);
        $this->assertStringNotContainsString('Premium annuel', $rendered);
        $this->assertStringNotContainsString('photos 3-4', $rendered);
    }

    public function test_renewal_reminder_30d_mail_renders_starter_subject_and_body(): void
    {
        [$face, $subscription] = $this->makeFixture(plan: FaceSubscriptionPlan::Starter);

        $mail = new FaceSubscriptionRenewalReminderMail(face: $face, subscription: $subscription, daysRemaining: 30);

        $envelope = $mail->envelope();
        $this->assertSame('Votre abonnement Starter expire dans 30 jours', $envelope->subject);

        $rendered = html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertRenderedContainsPremiumMediaSummary($rendered, FaceSubscriptionPlan::Starter);
        $this->assertStringContainsString('Starter', $rendered);
        $this->assertStringContainsString('30 jours', $rendered);
        $this->assertStringContainsString('votre 2ème photo d\'album', $rendered);
        $this->assertStringNotContainsString('Premium annuel', $rendered);
        $this->assertStringNotContainsString('photos 3-4', $rendered);
    }

    public function test_renewal_reminder_30d_mail_renders_pro_subject_and_body(): void
    {
        [$face, $subscription] = $this->makeFixture(plan: FaceSubscriptionPlan::Pro);

        $mail = new FaceSubscriptionRenewalReminderMail(face: $face, subscription: $subscription, daysRemaining: 30);

        $envelope = $mail->envelope();
        $this->assertSame('Votre abonnement Pro expire dans 30 jours', $envelope->subject);

        $rendered = html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertRenderedContainsPremiumMediaSummary($rendered, FaceSubscriptionPlan::Pro);
        $this->assertStringContainsString('Pro', $rendered);
        $this->assertStringContainsString('30 jours', $rendered);
        $this->assertStringContainsString('vos photos 2 à 4 d\'album', $rendered);
        $this->assertStringContainsString('votre vidéo de jeu', $rendered);
        $this->assertStringNotContainsString('Premium annuel', $rendered);
        $this->assertStringNotContainsString('photos 3-4', $rendered);
    }

    public function test_renewal_reminder_30d_mail_renders_elite_subject_and_body(): void
    {
        [$face, $subscription] = $this->makeFixture(plan: FaceSubscriptionPlan::Elite);

        $mail = new FaceSubscriptionRenewalReminderMail(face: $face, subscription: $subscription, daysRemaining: 30);

        $envelope = $mail->envelope();
        $this->assertSame('Votre abonnement Élite expire dans 30 jours', $envelope->subject);

        $rendered = html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertRenderedContainsPremiumMediaSummary($rendered, FaceSubscriptionPlan::Elite);
        $this->assertStringContainsString('Élite', $rendered);
        $this->assertStringContainsString('30 jours', $rendered);
        $this->assertStringContainsString('vos photos 2 à 6 d\'album', $rendered);
        $this->assertStringContainsString('votre vidéo UGC', $rendered);
        $this->assertStringNotContainsString('Premium annuel', $rendered);
        $this->assertStringNotContainsString('photos 3-4', $rendered);
    }

    public function test_renewal_reminder_7d_mail_renders_starter_subject_and_body(): void
    {
        [$face, $subscription] = $this->makeFixture(plan: FaceSubscriptionPlan::Starter);

        $mail = new FaceSubscriptionRenewalReminderMail(face: $face, subscription: $subscription, daysRemaining: 7);

        $envelope = $mail->envelope();
        $this->assertSame('Votre abonnement Starter expire dans 7 jours', $envelope->subject);

        $rendered = html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertRenderedContainsPremiumMediaSummary($rendered, FaceSubscriptionPlan::Starter);
        $this->assertStringContainsString('Starter', $rendered);
        $this->assertStringContainsString('7 jours', $rendered);
        $this->assertStringContainsString('votre 2ème photo d\'album', $rendered);
        $this->assertStringNotContainsString('Premium annuel', $rendered);
    }

    public function test_renewal_reminder_7d_mail_renders_pro_subject_and_body(): void
    {
        [$face, $subscription] = $this->makeFixture(plan: FaceSubscriptionPlan::Pro);

        $mail = new FaceSubscriptionRenewalReminderMail(face: $face, subscription: $subscription, daysRemaining: 7);

        $envelope = $mail->envelope();
        $this->assertSame('Votre abonnement Pro expire dans 7 jours', $envelope->subject);

        $rendered = html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertRenderedContainsPremiumMediaSummary($rendered, FaceSubscriptionPlan::Pro);
        $this->assertStringContainsString('Pro', $rendered);
        $this->assertStringContainsString('7 jours', $rendered);
        $this->assertStringContainsString('vos photos 2 à 4 d\'album', $rendered);
        $this->assertStringNotContainsString('Premium annuel', $rendered);
    }

    public function test_renewal_reminder_7d_mail_renders_elite_subject_and_body(): void
    {
        [$face, $subscription] = $this->makeFixture(plan: FaceSubscriptionPlan::Elite);

        $mail = new FaceSubscriptionRenewalReminderMail(face: $face, subscription: $subscription, daysRemaining: 7);

        $envelope = $mail->envelope();
        $this->assertSame('Votre abonnement Élite expire dans 7 jours', $envelope->subject);

        $rendered = html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertRenderedContainsPremiumMediaSummary($rendered, FaceSubscriptionPlan::Elite);
        $this->assertStringContainsString('Élite', $rendered);
        $this->assertStringContainsString('7 jours', $rendered);
        $this->assertStringContainsString('vos photos 2 à 6 d\'album', $rendered);
        $this->assertStringContainsString('votre vidéo UGC', $rendered);
        $this->assertStringNotContainsString('Premium annuel', $rendered);
    }

    /**
     * @return array{0: Face, 1: FaceSubscription}
     */
    private function makeFixture(FaceSubscriptionPlan $plan, int $paidAmount = 25000): array
    {
        $face = Face::factory()->create(['prenom' => 'Aïcha']);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => "render-{$plan->value}@example.test",
        ]);

        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'plan' => $plan,
            'paid_amount' => $paidAmount,
            'expires_at' => now()->setDate(2027, 5, 15)->setTime(10, 0),
        ]);

        return [$face, $subscription];
    }

    private function assertRenderedContainsPremiumMediaSummary(string $rendered, FaceSubscriptionPlan $plan): void
    {
        $this->assertStringContainsString($plan->premiumMediaSummary(), $rendered);
    }
}
