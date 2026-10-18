<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'generated_by' => User::factory(),
            'project_id' => null,
            'user_id' => null,
            'report_type' => fake()->randomElement(['task_completion', 'worker_activity', 'project_progress']),
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->toDateString(),
            'file_path' => 'reports/test_report.json',
            'metrics_data' => [
                'completion_rate' => 75,
                'total_items' => 20,
                'completed_items' => 15,
            ],
            'status' => ReportStatus::Completed,
        ];
    }
}
