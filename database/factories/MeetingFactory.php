<?php

namespace Database\Factories;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'title' => fake()->randomElement(['Design Review', 'Sprint Planning Meeting', 'UX Feedback Session', 'Architecture Alignment', 'Weekly Team Sync']),
            'description' => fake()->sentence(10),
            'date' => fake()->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'meeting_link' => 'https://meet.collabspace.ai/'.fake()->slug(2),
            'status' => MeetingStatus::Scheduled,
        ];
    }
}
