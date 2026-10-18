<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['UI/UX Team', 'Frontend Team', 'Backend Team', 'Marketing Team', 'QA Team', 'DevOps Team']),
            'description' => fake()->sentence(8),
            'leader_id' => User::factory(),
            'created_by' => User::factory(),
        ];
    }
}
