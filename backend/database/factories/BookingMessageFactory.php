<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingMessage>
 */
class BookingMessageFactory extends Factory
{
    protected $model = BookingMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'sender_id'  => User::factory(),
            'content'    => fake()->sentence(),
        ];
    }
}
