<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'sender_type' => User::class,
            'content' => fake()->paragraph(),
            'read_at' => null,
        ];
    }

    /**
     * Indicate that the message has been read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the message is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * Create message with short content.
     */
    public function short(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => fake()->sentence(),
        ]);
    }

    /**
     * Create message with long content.
     */
    public function long(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => fake()->paragraphs(3, true),
        ]);
    }

    /**
     * Create message for a specific conversation.
     */
    public function forConversation(Conversation $conversation): static
    {
        return $this->state(fn (array $attributes) => [
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * Create message from a specific user.
     */
    public function fromUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_id' => $user->id,
            'sender_type' => User::class,
        ]);
    }

    /**
     * Create a message with a realistic greeting.
     */
    public function greeting(): static
    {
        $greetings = [
            'Bonjour ! Je suis ravi(e) de travailler avec vous sur cette mission.',
            'Salut ! J\'ai hâte de commencer notre collaboration.',
            'Bonjour, merci d\'avoir accepté ma candidature !',
            'Hello ! Quand pouvons-nous discuter des détails de la mission ?',
        ];

        return $this->state(fn (array $attributes) => [
            'content' => fake()->randomElement($greetings),
        ]);
    }

    /**
     * Create a message about mission details.
     */
    public function missionDetails(): static
    {
        $details = [
            'Pouvez-vous me donner plus de détails sur le lieu du tournage ?',
            'À quelle heure devrai-je être présent(e) le jour du tournage ?',
            'Y a-t-il un dress code particulier pour la mission ?',
            'Combien de temps durera la séance de tournage ?',
        ];

        return $this->state(fn (array $attributes) => [
            'content' => fake()->randomElement($details),
        ]);
    }
}
