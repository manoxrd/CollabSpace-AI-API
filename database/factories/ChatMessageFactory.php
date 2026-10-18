<?php

namespace Database\Factories;

use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chat_channel_id' => ChatChannel::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'message' => fake()->sentence(12),
            'is_edited' => false,
            'edited_at' => null,
        ];
    }
}
