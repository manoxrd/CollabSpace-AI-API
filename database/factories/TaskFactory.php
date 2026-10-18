<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'team_id' => null,
            'created_by' => User::factory(),
            'name' => fake()->randomElement([
                'Creat Wireframes',
                'User Research',
                'Design System Updates',
                'Mobile Responsiveness',
                'Accessibility Audit',
                'Implement Auth API',
                'Build Kanban Drag-and-Drop',
                'AI Meeting Summary Integration',
                'Database Indexing Optimization',
                'Profile Settings Form Validation',
            ]),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(TaskStatus::cases()),
            'priority' => fake()->randomElement(Priority::cases()),
            'start_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'due_date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'progress_percentage' => fake()->randomFloat(2, 0, 100),
        ];
    }

    public function todo(): static
    {
        return $this->state(fn () => [
            'status' => TaskStatus::Todo,
            'progress_percentage' => 0.00,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => TaskStatus::InProgress,
            'progress_percentage' => fake()->randomFloat(2, 20, 75),
        ]);
    }

    public function inReview(): static
    {
        return $this->state(fn () => [
            'status' => TaskStatus::InReview,
            'progress_percentage' => 90.00,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
            'progress_percentage' => 100.00,
        ]);
    }
}
