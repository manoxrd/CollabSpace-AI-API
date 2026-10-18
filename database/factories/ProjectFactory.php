<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Alpha', 'SepetGo', 'Taskify Pro', 'Pet Website', 'HealthTrack AI', 'Food Delivery App']),
            'category' => fake()->randomElement(['SaaS', 'Web Platform', 'Mobile App', 'Cloud Infrastructure']),
            'description' => fake()->paragraph(2),
            'start_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'deadline' => fake()->dateTimeBetween('+1 month', '+6 months')->format('Y-m-d'),
            'priority' => fake()->randomElement(Priority::cases()),
            'status' => fake()->randomElement(ProjectStatus::cases()),
            'progress_percentage' => fake()->randomFloat(2, 10, 95),
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Active,
        ]);
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Done,
            'progress_percentage' => 100.00,
        ]);
    }
}
