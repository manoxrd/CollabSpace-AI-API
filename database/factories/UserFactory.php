<?php

namespace Database\Factories;

use App\Enums\UserAvailability;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => fake()->randomElement(UserRole::cases()),
            'job_title' => fake()->randomElement(['UI/UX Designer', 'Frontend Engineer', 'Backend Engineer', 'QA Specialist', 'Marketing Lead', 'DevOps Specialist']),
            'years_of_experience' => fake()->numberBetween(1, 12),
            'phone' => fake()->phoneNumber(),
            'avatar_url' => 'https://i.pravatar.cc/150?u='.fake()->unique()->uuid(),
            'availability_status' => fake()->randomElement(UserAvailability::cases()),
            'about' => fake()->paragraph(2),
            'hours_worked' => fake()->numberBetween(20, 600),
            'current_team_project' => 'CollabSpace - Web Platform',
            'status' => 'active',
            'joined_at' => fake()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Admin,
        ]);
    }

    public function projectManager(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::ProjectManager,
        ]);
    }

    public function teamMember(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::TeamMember,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }
}
