<?php

declare(strict_types=1);

namespace Tests\Feature\Whatsapp;

use App\Support\Push\PushAllowlist;
use App\Support\WhatsappAllowlist;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Force une décision WhatsApp pour chaque type de notification in-app émis dans app/
 * (même garde-fou que Tests\Feature\Push\PushAllowlistTest).
 */
class WhatsappAllowlistTest extends TestCase
{
    /**
     * Types créés par `Notification::create([... 'type' => '...' ...])`, `notifySafely(... type: '...')`
     * et les deux commandes qui construisent le type dynamiquement.
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

            if (preg_match_all("/Notification::create\(\[(.{0,400}?)'type' => '([a-z0-9_]+)'/s", $source, $m)) {
                array_push($types, ...$m[2]);
            }

            if (preg_match_all("/\btype: '([a-z0-9_]+)'/", $source, $m) && str_contains($source, 'notifySafely')) {
                array_push($types, ...$m[1]);
            }

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
        $this->assertContains('new_candidature', $types);
        $this->assertContains('candidature_accepted', $types);

        $undecided = array_values(array_filter(
            $types,
            fn (string $type): bool => ! WhatsappAllowlist::isAllowed($type) && ! WhatsappAllowlist::isExcluded($type),
        ));

        $this->assertSame([], $undecided, 'Add these notification types to WhatsappAllowlist::ALLOWED or ::EXCLUDED.');
    }

    public function test_allowed_and_excluded_are_disjoint_and_only_reference_emitted_types(): void
    {
        $this->assertSame([], array_values(array_intersect_key(WhatsappAllowlist::ALLOWED, WhatsappAllowlist::EXCLUDED)));

        $stale = array_values(array_diff(
            array_merge(array_keys(WhatsappAllowlist::ALLOWED), array_keys(WhatsappAllowlist::EXCLUDED)),
            $this->emittedTypes(),
        ));

        $this->assertSame([], $stale, 'These allowlist entries are not emitted anywhere in app/.');
    }

    public function test_the_allowed_set_is_exactly_the_eight_decided_triggers(): void
    {
        $this->assertSame([
            'booking_received' => ['weact_booking_request', 'Face'],
            'booking_response_reminder' => ['weact_booking_reminder', 'Face'],
            'booking_paid' => ['weact_payment_secured', 'Face'],
            'ugc_commission_paid' => ['weact_payment_secured', 'Face'],
            'candidature_accepted' => ['weact_mission_place_paid', 'Face'],
            'booking_accepted' => ['weact_booking_accepted', 'Producer'],
            'booking_refused' => ['weact_booking_declined', 'Producer'],
            'new_candidature' => ['weact_new_candidatures', 'Producer'],
        ], WhatsappAllowlist::ALLOWED);
    }

    public function test_every_allowed_type_points_to_a_declared_template_and_a_known_role(): void
    {
        foreach (WhatsappAllowlist::ALLOWED as $type => [$template, $role]) {
            $this->assertArrayHasKey($template, config('whatsapp.templates'), $type);
            $this->assertContains($role, [WhatsappAllowlist::FACE, WhatsappAllowlist::PRODUCER], $type);
        }
    }

    public function test_every_whatsapp_type_is_also_a_push_type_and_excluded_types_carry_a_reason(): void
    {
        foreach (array_keys(WhatsappAllowlist::ALLOWED) as $type) {
            $this->assertTrue(PushAllowlist::isAllowed($type), $type);
        }

        foreach (WhatsappAllowlist::EXCLUDED as $type => $reason) {
            $this->assertNotSame('', trim($reason), $type);
        }
    }

    public function test_money_and_trust_decisions_on_the_edge_types(): void
    {
        // Pas de WhatsApp pour le paiement côté Producteur ni pour les annonces de missions.
        $this->assertSame(WhatsappAllowlist::FACE, WhatsappAllowlist::roleFor('booking_paid'));
        $this->assertTrue(WhatsappAllowlist::isExcluded('booking_wallet_credited'));
        $this->assertTrue(WhatsappAllowlist::isExcluded('mission_payment_confirmed'));
        $this->assertTrue(WhatsappAllowlist::isExcluded('candidature_rejected'));
        $this->assertNull(WhatsappAllowlist::templateFor('booking_cancelled'));
    }
}
