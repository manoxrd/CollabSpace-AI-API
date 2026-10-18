<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $this->token = auth('api')->login($this->user);
    }

    public function test_user_can_list_tasks_in_list_format(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/tasks');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'tasks' => [
                        '*' => [
                            'id',
                            'name',
                            'status',
                            'priority',
                            'progress_percentage',
                            'due_date_formatted',
                            'assignees',
                        ],
                    ],
                    'pagination',
                ],
            ]);
    }

    public function test_user_can_list_tasks_in_kanban_format(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/tasks?format=kanban');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'columns' => [
                        'todo' => ['title', 'status', 'count', 'tasks'],
                        'in_progress' => ['title', 'status', 'count', 'tasks'],
                        'in_review' => ['title', 'status', 'count', 'tasks'],
                        'completed' => ['title', 'status', 'count', 'tasks'],
                    ],
                    'meta' => ['total_tasks', 'todo_count', 'in_progress_count', 'in_review_count', 'completed_count'],
                ],
            ]);
    }

    public function test_user_can_get_project_kanban_board(): void
    {
        $project = Project::first();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/projects/{$project->id}/tasks/kanban");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'columns' => [
                        'todo' => ['title', 'count', 'tasks'],
                        'in_progress' => ['title', 'count', 'tasks'],
                        'in_review' => ['title', 'count', 'tasks'],
                        'completed' => ['title', 'count', 'tasks'],
                    ],
                    'meta' => ['total_tasks'],
                ],
            ]);
    }

    public function test_user_can_create_task(): void
    {
        $project = Project::first();
        $assignee = User::first();

        $payload = [
            'project_id' => $project->id,
            'name' => 'Figma Dashboard Layout Prototyping',
            'description' => 'Create pixel-perfect high-fidelity mockups for dashboard widgets.',
            'priority' => 'high',
            'status' => 'todo',
            'start_date' => '2026-10-20',
            'due_date' => '2026-10-25',
            'assignee_ids' => [$assignee->id],
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/tasks', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Figma Dashboard Layout Prototyping')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status', 'todo');

        $this->assertDatabaseHas('tasks', [
            'name' => 'Figma Dashboard Layout Prototyping',
            'project_id' => $project->id,
        ]);
    }

    public function test_task_service_creates_task_with_default_priority(): void
    {
        $project = Project::first();
        $taskService = app(TaskService::class);

        $task = $taskService->createTask([
            'project_id' => $project->id,
            'name' => 'Default Priority Task',
        ], $this->user);

        $this->assertEquals(Priority::Medium, $task->priority);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'priority' => Priority::Medium->value,
        ]);
    }

    public function test_user_can_view_task_details(): void
    {
        $task = Task::first();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'status',
                    'priority',
                    'assignees',
                    'comments',
                    'activities',
                    'files',
                ],
            ]);
    }

    public function test_user_can_update_task_status_via_drag_and_drop(): void
    {
        $task = Task::first();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson("/api/v1/tasks/{$task->id}/status", [
                'status' => TaskStatus::Completed->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', TaskStatus::Completed->value);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Completed->value,
        ]);

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'new_status' => TaskStatus::Completed->value,
        ]);
    }

    public function test_user_can_sync_task_assignees(): void
    {
        $project = Project::first();
        $task = Task::create([
            'project_id' => $project->id,
            'created_by' => $this->user->id,
            'name' => 'Assignable Task',
            'status' => 'todo',
            'priority' => 'medium',
        ]);
        $assignee = User::first();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/tasks/{$task->id}/assignees", [
                'assignee_ids' => [$assignee->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('task_user', [
            'task_id' => $task->id,
            'user_id' => $assignee->id,
        ]);
    }

    public function test_user_can_add_comment_to_task(): void
    {
        $task = Task::first();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/tasks/{$task->id}/comments", [
                'comment' => 'Completed the initial wireframes. Ready for review!',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.comment', 'Completed the initial wireframes. Ready for review!');

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'comment' => 'Completed the initial wireframes. Ready for review!',
        ]);
    }

    public function test_user_can_delete_task(): void
    {
        $project = Project::first();
        $task = Task::create([
            'project_id' => $project->id,
            'created_by' => $this->user->id,
            'name' => 'Task to be deleted',
            'status' => 'todo',
            'priority' => 'low',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/v1/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_project_manager_can_assign_project_manager_as_reviewer(): void
    {
        $task = Task::first();
        $manager = User::where('role', UserRole::ProjectManager->value)->first();
        $managerToken = auth('api')->login($manager);

        $response = $this->withHeader('Authorization', 'Bearer '.$managerToken)
            ->postJson("/api/v1/tasks/{$task->id}/reviewer", [
                'reviewer_id' => $manager->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reviewer.id', $manager->id);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'reviewer_id' => $manager->id,
        ]);
    }

    public function test_team_member_cannot_assign_reviewer(): void
    {
        $task = Task::first();
        // Regular team member token
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/tasks/{$task->id}/reviewer", [
                'reviewer_id' => 1,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_assign_regular_team_member_as_reviewer(): void
    {
        $task = Task::first();
        $manager = User::where('role', UserRole::ProjectManager->value)->first();
        $managerToken = auth('api')->login($manager);

        $member = User::where('role', UserRole::TeamMember->value)->first();

        $response = $this->withHeader('Authorization', 'Bearer '.$managerToken)
            ->postJson("/api/v1/tasks/{$task->id}/reviewer", [
                'reviewer_id' => $member->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['reviewer_id']);
    }

    public function test_only_reviewer_or_manager_can_complete_task_with_reviewer(): void
    {
        $manager = User::where('role', UserRole::ProjectManager->value)->first();
        $task = Task::first();
        $task->update(['reviewer_id' => $manager->id, 'status' => TaskStatus::InReview->value]);

        // Mohamed Salah (team member, not reviewer or manager) tries to complete
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson("/api/v1/tasks/{$task->id}/status", [
                'status' => TaskStatus::Completed->value,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        // Assigned reviewer (manager) approves and completes
        $managerToken = auth('api')->login($manager);
        $approvedResponse = $this->withHeader('Authorization', 'Bearer '.$managerToken)
            ->patchJson("/api/v1/tasks/{$task->id}/status", [
                'status' => TaskStatus::Completed->value,
            ]);

        $approvedResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', TaskStatus::Completed->value);
    }
}
