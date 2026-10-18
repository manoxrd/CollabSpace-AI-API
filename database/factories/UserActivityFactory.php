<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserActivity>
 */
class UserActivityFactory extends Factory
{
    protected $model = UserActivity::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'activity_type' => fake()->randomElement(['completed_task', 'updated_design', 'joined_meeting', 'commented']),
            'subject_type' => null,
            'subject_id' => null,
            'project_id' => Project::factory(),
            'description' => fake()->sentence(),
            'properties' => ['browser' => 'Chrome', 'os' => 'Windows'],
            'created_at' => now(),
        ];
    }
}
