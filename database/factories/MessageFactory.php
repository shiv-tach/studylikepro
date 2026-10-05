<?php

namespace Database\Factories;

use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => null,
            'type' => MessageType::Text,
            'body' => fake()->sentence(),
        ];
    }

    public function from(User $sender): static
    {
        return $this->state(fn () => ['sender_id' => $sender->id]);
    }

    public function system(string $body): static
    {
        return $this->state(fn () => [
            'type' => MessageType::System,
            'sender_id' => null,
            'body' => $body,
        ]);
    }
}
