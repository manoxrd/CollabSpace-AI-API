<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_retrieve_dashboard_stats(): void
    {
        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'pending_tasks' => ['count', 'total_reference', 'fraction', 'percentage_change', 'trend'],
                    'in_progress_tasks' => ['count', 'total_reference', 'fraction', 'percentage_change', 'trend'],
                    'completed_tasks' => ['count', 'total_reference', 'fraction', 'percentage_change', 'trend'],
                    'completion_rate' => ['percentage', 'percentage_change', 'trend'],
                ],
            ]);
    }

    public function test_user_can_retrieve_project_overview_chart(): void
    {
        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $token = auth('api')->login($user);

        // All projects
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/dashboard/project-overview-chart?year=2026');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.project_name', 'All Projects')
            ->assertJsonCount(12, 'data.months')
            ->assertJsonStructure([
                'data' => [
                    'year',
                    'project_name',
                    'months' => [
                        '*' => ['month', 'month_number', 'completed_tasks', 'created_tasks', 'velocity_score'],
                    ],
                ],
            ]);
    }

    public function test_user_can_retrieve_active_projects(): void
    {
        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/dashboard/active-projects');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'projects' => [
                        '*' => [
                            'id',
                            'name',
                            'status',
                            'priority',
                            'task_fraction',
                            'completed_tasks_count',
                            'total_tasks_count',
                            'progress_percentage',
                        ],
                    ],
                ],
            ]);
    }

    public function test_user_can_retrieve_recent_files(): void
    {
        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/dashboard/recent-files');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'files' => [
                        '*' => [
                            'id',
                            'name',
                            'mime_type',
                            'size_formatted',
                            'project',
                        ],
                    ],
                ],
            ]);
    }

    public function test_user_can_retrieve_urgent_tasks(): void
    {
        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/dashboard/urgent-tasks');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'tasks' => [
                        '*' => [
                            'id',
                            'name',
                            'priority',
                            'status',
                            'progress_percentage',
                            'due_date_formatted',
                            'is_due_today',
                        ],
                    ],
                ],
            ]);
    }

    public function test_user_can_retrieve_team_overview(): void
    {
        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/dashboard/team-overview');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'team_members' => [
                        '*' => [
                            'id',
                            'name',
                            'avatar_url',
                            'job_title',
                            'role',
                            'task_fraction',
                            'completed_tasks_count',
                            'total_tasks_count',
                        ],
                    ],
                ],
            ]);
    }

    public function test_user_can_retrieve_upcoming_meetings(): void
    {
        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/dashboard/upcoming-meetings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'meetings' => [
                        '*' => [
                            'id',
                            'title',
                            'date_formatted',
                            'time_slot',
                            'status',
                            'participants',
                            'extra_participants_count',
                            'total_participants_count',
                        ],
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/v1/dashboard/stats');

        $response->assertStatus(401);
    }
}
