<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\ReportStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::TeamMember,
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
    }

    public function test_authenticated_user_can_retrieve_report_summary_kpis(): void
    {
        $project = Project::factory()->create([
            'status' => ProjectStatus::Done,
            'progress_percentage' => 100,
        ]);

        Task::factory()->count(4)->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Completed,
        ]);

        Task::factory()->count(2)->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Todo,
        ]);

        UserActivity::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/reports/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'task_completion' => ['rate', 'change', 'total_tasks', 'completed_tasks'],
                    'projects_completion' => ['rate', 'change', 'total_projects', 'completed_projects'],
                    'teams_activity' => ['rate', 'change', 'total_members', 'active_members'],
                ],
            ]);

        $this->assertEquals(67, $response->json('data.task_completion.rate'));
        $this->assertEquals(6, $response->json('data.task_completion.total_tasks'));
        $this->assertEquals(4, $response->json('data.task_completion.completed_tasks'));
    }

    public function test_user_can_retrieve_task_deadline_comparison_chart_data(): void
    {
        $project = Project::factory()->create();

        Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Completed,
            'due_date' => now()->addDay(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/reports/charts/deadline-comparison?days=7');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['date', 'label', 'on_time', 'delayed', 'overdue', 'pending'],
                ],
            ]);

        $this->assertCount(7, $response->json('data'));
    }

    public function test_user_can_retrieve_projects_progress_chart_data(): void
    {
        Project::factory()->create([
            'status' => ProjectStatus::Active,
            'progress_percentage' => 60,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/reports/charts/project-progress');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['label', 'progress', 'active_projects'],
                ],
            ]);

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_user_can_retrieve_worker_activity_chart_data_monthly_and_weekly(): void
    {
        UserActivity::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'created_at' => now(),
        ]);

        // Monthly
        $monthlyResponse = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/reports/charts/worker-activity?interval=monthly');

        $monthlyResponse->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['label', 'activity_count', 'rate'],
                ],
            ]);

        // Weekly
        $weeklyResponse = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/reports/charts/worker-activity?interval=weekly');

        $weeklyResponse->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['label', 'activity_count', 'rate'],
                ],
            ]);

        $this->assertCount(7, $weeklyResponse->json('data'));
    }

    public function test_user_can_generate_detailed_task_completion_report(): void
    {
        Storage::fake('public');

        $project = Project::factory()->create();
        Task::factory()->count(3)->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Completed,
        ]);

        $payload = [
            'report_type' => 'task_completion',
            'project_id' => $project->id,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->toDateString(),
            'format' => 'json',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/reports', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.report_type', 'task_completion')
            ->assertJsonPath('data.status', ReportStatus::Completed->value)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'report_type',
                    'report_type_label',
                    'status',
                    'metrics_data' => [
                        'type',
                        'total_tasks',
                        'completed_tasks',
                        'completion_rate_percentage',
                    ],
                ],
            ]);

        $reportId = $response->json('data.id');
        $this->assertDatabaseHas('reports', [
            'id' => $reportId,
            'generated_by' => $this->user->id,
            'report_type' => 'task_completion',
        ]);

        $report = Report::find($reportId);
        $this->assertNotNull($report->file_path);
        Storage::disk('public')->assertExists($report->file_path);
    }

    public function test_user_can_generate_worker_activity_report(): void
    {
        Storage::fake('public');

        $worker = User::factory()->create();
        UserActivity::factory()->count(4)->create([
            'user_id' => $worker->id,
            'activity_type' => 'completed_task',
        ]);

        $payload = [
            'report_type' => 'worker_activity',
            'user_id' => $worker->id,
            'format' => 'json',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/reports', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.report_type', 'worker_activity')
            ->assertJsonPath('data.metrics_data.total_activities', 4);
    }

    public function test_user_can_generate_project_progress_report(): void
    {
        Storage::fake('public');

        $project = Project::factory()->create([
            'name' => 'Alpha App',
            'progress_percentage' => 85,
        ]);

        $payload = [
            'report_type' => 'project_progress',
            'project_id' => $project->id,
            'format' => 'json',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/reports', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.report_type', 'project_progress')
            ->assertJsonPath('data.metrics_data.average_progress', 85);
    }

    public function test_report_generation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/reports', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['report_type']);
    }

    public function test_user_can_list_generated_reports_with_filter(): void
    {
        Report::factory()->create([
            'generated_by' => $this->user->id,
            'report_type' => 'task_completion',
        ]);

        Report::factory()->create([
            'generated_by' => $this->user->id,
            'report_type' => 'worker_activity',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/reports?report_type=task_completion');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'report_type', 'status', 'metrics_data'],
                ],
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('task_completion', $response->json('data.0.report_type'));
    }

    public function test_user_can_view_single_report_details(): void
    {
        $report = Report::factory()->create([
            'generated_by' => $this->user->id,
            'report_type' => 'task_completion',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $report->id)
            ->assertJsonPath('data.report_type', 'task_completion');
    }

    public function test_unauthorized_user_cannot_view_others_private_report(): void
    {
        $otherUser = User::factory()->create([
            'role' => UserRole::TeamMember,
        ]);

        $report = Report::factory()->create([
            'generated_by' => $otherUser->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/reports/{$report->id}");

        $response->assertForbidden();
    }

    public function test_user_can_delete_own_generated_report(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('reports/sample.json', '{}');

        $report = Report::factory()->create([
            'generated_by' => $this->user->id,
            'file_path' => 'reports/sample.json',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/reports/{$report->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('reports', ['id' => $report->id]);
        Storage::disk('public')->assertMissing('reports/sample.json');
    }

    public function test_unauthorized_user_cannot_delete_others_report(): void
    {
        $otherUser = User::factory()->create([
            'role' => UserRole::TeamMember,
        ]);

        $report = Report::factory()->create([
            'generated_by' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/reports/{$report->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('reports', ['id' => $report->id]);
    }
}
