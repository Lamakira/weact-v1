<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\BookingStatus;
use App\Enums\MissionStatus;
use PHPUnit\Framework\TestCase;

/**
 * Les listes d'ordre de tri (FIELD()) doivent couvrir TOUS les cas : un nouveau
 * statut oublié ferait échouer ce test au lieu d'être trié en tête de liste.
 */
class StatusLifecycleOrderTest extends TestCase
{
    public function test_booking_lifecycle_order_lists_every_case_exactly_once(): void
    {
        $order = array_map(fn (BookingStatus $s) => $s->value, BookingStatus::lifecycleOrder());

        $this->assertCount(count(BookingStatus::cases()), $order);
        $this->assertEqualsCanonicalizing(BookingStatus::values(), $order);
        $this->assertSame($order, array_values(array_unique($order)));
    }

    public function test_mission_lifecycle_order_lists_every_case_exactly_once(): void
    {
        $order = array_map(fn (MissionStatus $s) => $s->value, MissionStatus::lifecycleOrder());

        $this->assertCount(count(MissionStatus::cases()), $order);
        $this->assertEqualsCanonicalizing(array_column(MissionStatus::cases(), 'value'), $order);
    }
}
