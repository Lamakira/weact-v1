<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Support\Push\PushAllowlist;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Forces a push decision for every in-app notification type emitted in app/.
 */
class PushAllowlistTest extends TestCase
{
    /**
     * Types created through `Notification::create([... 'type' => '...' ...])`,
     * `notifySafely(... type: '...')` and the two commands that build the type dynamically.
     *
     * @return list<string>
     */
    private function emittedTypes(): array
    {
        $types = [];

        foreach (File::allFiles(dirname(__DIR__, 3).'/app') as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = $file->getContents();

            // Notification::create([... 'type' => 'x' ...]) — type within the array literal.
            if (preg_match_all("/Notification::create\(\[(.{0,400}?)'type' => '([a-z0-9_]+)'/s", $source, $m)) {
                array_push($types, ...$m[2]);
            }

            // MissionPaymentService::notifySafely(userId: ..., type: 'x', ...)
            if (preg_match_all("/\btype: '([a-z0-9_]+)'/", $source, $m) && str_contains($source, 'notifySafely')) {
                array_push($types, ...$m[1]);
            }

            // Dynamic types: reconcile command result arrays and renewal reminders.
            if (str_contains($source, "'type' => \$notification['type']") || str_contains($source, "'type' => \$daysRemaining")) {
                preg_match_all("/'(candidature_reset_from_[a-z]+|mission_selection_reset_producer|face_subscription_renewal_reminder_[0-9a-z]+)'/", $source, $m);
                array_push($types, ...$m[1]);
            }
        }

        $types = array_values(array_unique($types));
        sort($types);

        return $types;
    }

    public function test_every_emitted_notification_type_is_allowed_or_explicitly_excluded(): void
    {
        $types = $this->emittedTypes();

        // Sanity: the scan actually found the codebase's types.
        $this->assertGreaterThan(40, count($types));
        $this->assertContains('booking_received', $types);
        $this->assertContains('mission_candidature_refunded', $types);
        $this->assertContains('candidature_reset_from_rejected', $types);
        $this->assertContains('face_subscription_renewal_reminder_7d', $types);

        $undecided = array_values(array_filter(
            $types,
            fn (string $type): bool => ! PushAllowlist::isAllowed($type) && ! PushAllowlist::isExcluded($type),
        ));

        $this->assertSame([], $undecided, 'Add these notification types to PushAllowlist::ALLOWED or ::EXCLUDED.');
    }

    public function test_non_literal_types_only_live_in_the_known_dynamic_files(): void
    {
        $dynamic = [];

        foreach (File::allFiles(dirname(__DIR__, 3).'/app') as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match("/Notification::create\(\[.{0,400}?'type' => (?!')/s", $file->getContents())) {
                $dynamic[] = $file->getFilename();
            }
        }

        // MissionPaymentService::notifySafely, the reconcile command (result arrays)
        // and the renewal reminder (30d/7d ternary). Any other dynamic type escapes the scan above.
        $known = [
            'MissionPaymentService.php',
            'ReconcileStaleSelectionsCommand.php',
            'RemindFaceSubscriptionRenewalsCommand.php',
        ];

        $this->assertSame([], array_values(array_diff($dynamic, $known)), 'A new dynamic notification type must be added to emittedTypes().');
    }

    public function test_product_decisions_on_the_edge_types(): void
    {
        $this->assertTrue(PushAllowlist::isAllowed('candidature_reset_from_accepted'));
        $this->assertTrue(PushAllowlist::isAllowed('face_subscription_renewal_reminder_7d'));

        foreach (['candidature_reset_from_rejected', 'face_subscription_renewal_reminder_30d', 'booking_rating_received'] as $type) {
            $this->assertTrue(PushAllowlist::isExcluded($type), $type);
            $this->assertFalse(PushAllowlist::isAllowed($type), $type);
        }
    }

    public function test_allowed_and_excluded_are_disjoint_and_only_reference_emitted_types(): void
    {
        $this->assertSame([], array_values(array_intersect_key(PushAllowlist::ALLOWED, PushAllowlist::EXCLUDED)));

        $emitted = $this->emittedTypes();
        $stale = array_values(array_diff(
            array_merge(array_keys(PushAllowlist::ALLOWED), array_keys(PushAllowlist::EXCLUDED)),
            $emitted,
        ));

        $this->assertSame([], $stale, 'These allowlist entries are not emitted anywhere in app/.');
    }

    public function test_every_allowed_type_has_a_known_french_title(): void
    {
        $titles = ['Booking', 'Mission', 'UGC', 'Paiement', 'Abonnement', 'Rappel'];

        foreach (PushAllowlist::ALLOWED as $type => $title) {
            $this->assertContains($title, $titles, $type);
        }
    }

    public function test_excluded_types_carry_a_reason(): void
    {
        foreach (PushAllowlist::EXCLUDED as $type => $reason) {
            $this->assertNotSame('', trim($reason), $type);
        }
    }
}
