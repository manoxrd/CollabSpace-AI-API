<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::where('email', 'mohanad.wahib@collabspace.ai')->first();
        $this->token = auth('api')->login($this->user);
    }

    public function test_user_can_list_projects_with_pagination_and_filters(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/projects?status=active');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'projects' => [
                        '*' => [
                            'id',
                            'name',
                            'category',
                            'start_date',
                            'deadline',
                            'status',
                            'priority',
                            'files_count',
                            'total_tasks_count',
                            'teams',
                        ],
                    ],
                    'pagination' => ['current_page', 'last_page', 'total'],
                ],
            ]);
    }

    public function test_user_can_search_projects(): void
    {
        $project = Project::first();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/projects?search='.urlencode($project->name));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.projects.0.id', $project->id);
    }

    public function test_user_can_create_project(): void
    {
        $team = Team::first();

        $payload = [
            'name' => 'CollabSpace Mobile Redesign',
            'category' => 'Mobile App',
            'description' => 'Revamping the mobile interface for seamless project tracking.',
            'start_date' => '2026-11-01',
            'deadline' => '2026-12-30',
            'priority' => 'high',
            'status' => 'active',
            'team_ids' => [$team->id],
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/projects', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'CollabSpace Mobile Redesign')
            ->assertJsonPath('data.category', 'Mobile App')
            ->assertJsonPath('data.priority', 'high');

        $this->assertDatabaseHas('projects', [
            'name' => 'CollabSpace Mobile Redesign',
            'category' => 'Mobile App',
        ]);
    }

    public function test_user_can_view_project_overview_sub_tab(): void
    {
        $project = Project::first();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/projects/{$project->id}/overview");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'header' => ['name', 'progress_percentage', 'progress_formatted'],
                    'product_details' => ['category', 'priority', 'total_tasks'],
                    'project_progress' => ['pending_tasks', 'in_progress_tasks', 'completed_tasks'],
                    'velocity_chart' => ['year', 'months'],
                    'team_members',
                ],
            ]);
    }

    public function test_user_can_view_project_teams_sub_tab(): void
    {
        $project = Project::first();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/projects/{$project->id}/teams");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'teams' => [
                        '*' => [
                            'team_id',
                            'name',
                            'completion_percentage',
                            'completion_formatted',
                            'members' => [
                                '*' => ['id', 'name', 'job_title', 'task_fraction'],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_user_can_assign_teams_to_project(): void
    {
        $project = Project::first();
        $team = Team::first();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/projects/{$project->id}/teams", [
                'team_ids' => [$team->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('project_team', [
            'project_id' => $project->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_user_can_view_and_upload_project_files(): void
    {
        Storage::fake('public');
        $project = Project::first();

        // 1. Upload file
        $file = UploadedFile::fake()->create('design_mockups.pdf', 1024, 'application/pdf');

        $uploadResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/projects/{$project->id}/files", [
                'file' => $file,
            ]);

        $uploadResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'design_mockups.pdf');

        // 2. View files list
        $listResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/projects/{$project->id}/files");

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'recent_files',
                    'all_files',
                    'pagination',
                ],
            ]);
    }

    public function test_user_can_update_project(): void
    {
        $project = Project::first();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson("/api/v1/projects/{$project->id}", [
                'name' => 'Updated Project Name',
                'priority' => 'low',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Project Name')
            ->assertJsonPath('data.priority', 'low');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name',
        ]);
    }

    public function test_user_can_delete_project(): void
    {
        $project = Project::create([
            'name' => 'Temp Project',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/v1/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_regular_team_member_cannot_create_project(): void
    {
        $teamMember = User::where('role', UserRole::TeamMember->value)->first();
        $memberToken = auth('api')->login($teamMember);

        $response = $this->withHeader('Authorization', 'Bearer '.$memberToken)
            ->postJson('/api/v1/projects', [
                'name' => 'Unauthorized Project',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
