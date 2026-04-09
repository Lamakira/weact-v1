<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\ValueObjects\BookingPricing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Booking::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dateDebut = fake()->dateTimeBetween('+1 week', '+3 months');
        $dateFin = fake()->dateTimeBetween($dateDebut, (clone $dateDebut)->modify('+1 week'));
        $dureeHeures = fake()->randomElement([4, 6, 8, 10, 16, 24]);
        $tarifBase = fake()->numberBetween(20000, 200000);
        $pricing = new BookingPricing($tarifBase);

        return [
            'uuid' => fake()->uuid(),
            'face_id' => User::factory()->state([
                'userable_type' => Face::class,
                'userable_id' => Face::factory(),
            ]),
            'producer_id' => User::factory()->state([
                'userable_type' => Producer::class,
                'userable_id' => Producer::factory(),
            ]),
            'status' => BookingStatus::Pending,
            'accepted_at' => null,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'duree_heures' => $dureeHeures,
            'type_contenu' => fake()->randomElement([
                'Publicité',
                'Film',
                'Clip musical',
                'Court-métrage',
                'Shooting photo',
                'Contenu réseaux sociaux',
            ]),
            'message' => fake()->optional(0.7)->paragraph(),
            'tarif_base' => $pricing->baseTarif,
            'montant_total_producteur' => $pricing->totalProducerPays,
            'montant_face_recoit' => $pricing->faceReceives,
        ];
    }

    /**
     * Indicate that the booking is in pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Pending,
        ]);
    }

    /**
     * Indicate that the booking is accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Indicate that the booking is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Paid,
        ]);
    }

    /**
     * Indicate that the booking is refused.
     */
    public function refused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Refused,
        ]);
    }

    /**
     * Indicate that the booking is in_progress (paid, pending mutual confirmation).
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::InProgress,
        ]);
    }

    /**
     * Indicate that the booking is confirmed by the Face (waiting for Producer).
     */
    public function confirmedByFace(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::ConfirmedByFace,
        ]);
    }

    /**
     * Indicate that the booking is confirmed by the Producer (waiting for Face).
     */
    public function confirmedByProducer(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::ConfirmedByProducer,
        ]);
    }

    /**
     * Indicate that the booking is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Completed,
        ]);
    }

    /**
     * Indicate that the booking was cancelled by the producer.
     */
    public function cancelledByProducer(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::CancelledByProducer,
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the booking was cancelled by the face.
     */
    public function cancelledByFace(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::CancelledByFace,
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the booking is marked as no-show.
     */
    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::NoShow,
        ]);
    }

    /**
     * Indicate that the booking has a Fedapay transaction attached.
     */
    public function withFedapayTransaction(): static
    {
        return $this->state(fn (array $attributes) => [
            'fedapay_transaction_id' => fake()->randomNumber(8),
            'payment_mode' => fake()->randomElement(['mtn', 'moov']),
        ]);
    }
}
