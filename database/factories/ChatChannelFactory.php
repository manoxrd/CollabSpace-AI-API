<?php

namespace Database\Factories;

use App\Enums\ChatChannelType;
use App\Models\ChatChannel;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatChannel>
 */
class ChatChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->randomElement(['Public Channel', 'Frontend Team Channel', 'Backend Team Channel', 'UI/UX Channel']),
            'type' => ChatChannelType::ProjectPublic,
            'created_by' => User::factory(),
        ];
    }

    public function direct(): static
    {
        return $this->state(fn () => [
            'project_id' => null,
            'type' => ChatChannelType::Direct,
            'name' => 'Direct Message',
        ]);
    }
}
