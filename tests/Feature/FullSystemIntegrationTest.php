<?php

namespace Tests\Feature;

use App\Models\ChatChannel;
use App\Models\File;
use App\Models\Folder;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FullSystemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected string $memberToken;

    protected string $pmToken;

    protected string $adminToken;

    protected User $member;

    protected User $pm;

    protected User $admin;

    protected Project $project;

    protected Task $task;

    protected ChatChannel $channel;

    protected Meeting $meeting;

    protected File $file;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed entire database exactly like production
        $this->seed(DatabaseSeeder::class);

        // Users from seed
        $this->member = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $this->pm = User::where('email', 'mohanad.wahib@collabspace.ai')->first();
        $this->admin = User::where('email', 'admin@collabspace.ai')->first();

        // Issue JWT tokens
        $this->memberToken = auth('api')->login($this->member);
        $this->pmToken = auth('api')->tokenById($this->pm->id);
        $this->adminToken = auth('api')->tokenById($this->admin->id);

        // Seeded models
        $this->project = Project::first();
        $this->task = Task::first();
        $this->channel = ChatChannel::first();
        $this->meeting = Meeting::first();
        $this->file = File::first();
    }

    public function test_1_authentication_endpoints(): void
    {
        // 1. Current user
        $meRes = $this->withHeader('Authorization', 'Bearer '.$this->memberToken)
            ->getJson('/api/v1/profile');
        $meRes->assertOk()->assertJsonPath('data.user.email', 'mohamed.salah@collabspace.ai');

        // 2. Token refresh
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'mohamed.salah@collabspace.ai',
            'password' => 'password',
        ]);
        $refreshToken = $loginRes->json('data.auth.raw_refresh_token');

        $refreshRes = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);
        $refreshRes->assertOk()->assertJsonStructure(['data' => ['auth' => ['access_token']]]);
    }

    public function test_2_dashboard_endpoints(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->memberToken];

        // Stats summary
        $this->withHeaders($headers)
            ->getJson('/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Project overview chart
        $this->withHeaders($headers)
            ->getJson('/api/v1/dashboard/project-overview-chart')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Active projects
        $this->withHeaders($headers)
            ->getJson('/api/v1/dashboard/active-projects')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Recent files
        $this->withHeaders($headers)
            ->getJson('/api/v1/dashboard/recent-files')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Urgent tasks
        $this->withHeaders($headers)
            ->getJson('/api/v1/dashboard/urgent-tasks')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Team overview
        $this->withHeaders($headers)
            ->getJson('/api/v1/dashboard/team-overview')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Upcoming meetings
        $this->withHeaders($headers)
            ->getJson('/api/v1/dashboard/upcoming-meetings')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_3_profile_and_settings_endpoints(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->memberToken];

        // Profile details
        $this->withHeaders($headers)
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Mohamed Salah');

        // Profile files
        $this->withHeaders($headers)
            ->getJson('/api/v1/profile/files')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Profile activities
        $this->withHeaders($headers)
            ->getJson('/api/v1/profile/activities')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Profile tasks
        $this->withHeaders($headers)
            ->getJson('/api/v1/profile/tasks')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Profile update
        $this->withHeaders($headers)
            ->putJson('/api/v1/profile', [
                'phone' => '+20 109-999-8888',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.phone', '+20 109-999-8888');
    }

    public function test_4_projects_endpoints(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->memberToken];
        $projectId = $this->project->id;

        // Projects list
        $this->withHeaders($headers)
            ->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Project detail
        $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectId}")
            ->assertOk()
            ->assertJsonPath('data.id', $projectId);

        // Project Overview sub-tab
        $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectId}/overview")
            ->assertOk()
            ->assertJsonStructure(['data' => ['header', 'product_details', 'project_progress']]);

        // Project Teams sub-tab
        $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectId}/teams")
            ->assertOk()
            ->assertJsonPath('success', true);

        // Project Files sub-tab
        $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectId}/files")
            ->assertOk()
            ->assertJsonStructure(['data' => ['recent_files', 'all_files']]);

        // Project Kanban tasks
        $this->withHeaders($headers)
            ->getJson("/api/v1/projects/{$projectId}/tasks/kanban")
            ->assertOk()
            ->assertJsonStructure(['data' => ['columns' => ['todo', 'in_progress', 'in_review', 'completed']]]);
    }

    public function test_5_tasks_endpoints(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->memberToken];
        $taskId = $this->task->id;

        // Tasks list
        $this->withHeaders($headers)
            ->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Global Kanban
        $this->withHeaders($headers)
            ->getJson('/api/v1/tasks?format=kanban')
            ->assertOk()
            ->assertJsonStructure(['data' => ['columns' => ['todo', 'in_progress', 'in_review', 'completed']]]);

        // Task detail
        $this->withHeaders($headers)
            ->getJson("/api/v1/tasks/{$taskId}")
            ->assertOk()
            ->assertJsonPath('data.id', $taskId);

        // Add task comment
        $commentRes = $this->withHeaders($headers)
            ->postJson("/api/v1/tasks/{$taskId}/comments", [
                'comment' => 'Reviewing the color contrast ratios.',
            ]);
        $commentRes->assertCreated();

        // View task detail with embedded comments
        $this->withHeaders($headers)
            ->getJson("/api/v1/tasks/{$taskId}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['comments']]);

        // Update task status (Kanban drag and drop)
        $this->actingAs($this->pm, 'api')
            ->patchJson("/api/v1/tasks/{$taskId}/status", [
                'status' => 'in_progress',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_6_chat_endpoints(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->memberToken];
        $channelId = $this->channel->id;

        // Inbox
        $this->withHeaders($headers)
            ->getJson('/api/v1/chat/inbox')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Project channels
        $this->withHeaders($headers)
            ->getJson("/api/v1/chat/projects/{$this->project->id}/channels")
            ->assertOk();

        // Channel messages
        $this->withHeaders($headers)
            ->getJson("/api/v1/chat/channels/{$channelId}/messages")
            ->assertOk();

        // Post message
        $sendRes = $this->withHeaders($headers)
            ->postJson("/api/v1/chat/channels/{$channelId}/messages", [
                'message' => 'Hello team, the design specs are published.',
            ]);
        $sendRes->assertCreated();
        $msgId = $sendRes->json('data.id');

        // Thread replies
        $this->withHeaders($headers)
            ->postJson("/api/v1/chat/messages/{$msgId}/reply", [
                'message' => 'Noted, looks fantastic!',
            ])
            ->assertCreated();

        // Mark channel as read
        $this->withHeaders($headers)
            ->postJson("/api/v1/chat/channels/{$channelId}/read")
            ->assertOk();
    }

    public function test_7_meetings_endpoints(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->memberToken];
        $meetingId = $this->meeting->id;

        // Calendar overview
        $this->withHeaders($headers)
            ->getJson('/api/v1/meetings?month=12&year=2026')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Meeting details
        $this->withHeaders($headers)
            ->getJson("/api/v1/meetings/{$meetingId}")
            ->assertOk()
            ->assertJsonPath('data.id', $meetingId);

        // Transcripts stream
        $this->withHeaders($headers)
            ->getJson("/api/v1/meetings/{$meetingId}/transcripts")
            ->assertOk();

        // Append transcript
        $this->withHeaders($headers)
            ->postJson("/api/v1/meetings/{$meetingId}/transcripts", [
                'speaker_name' => 'Mohamed Salah',
                'transcript_text' => 'All responsive breakpoints are finalized.',
                'timestamp_label' => '14:20',
            ])
            ->assertCreated();

        // AI Summary
        $this->withHeaders($headers)
            ->getJson("/api/v1/meetings/{$meetingId}/ai-summary")
            ->assertOk()
            ->assertJsonStructure(['data' => ['key_points', 'decisions_made', 'action_items']]);

        // RSVP
        $this->withHeaders($headers)
            ->patchJson("/api/v1/meetings/{$meetingId}/rsvp", [
                'status' => 'accepted',
            ])
            ->assertOk()
            ->assertJsonPath('data.my_rsvp_status', 'accepted');
    }

    public function test_8_ask_ai_endpoints(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->memberToken];

        // Conversations list
        $this->withHeaders($headers)
            ->getJson('/api/v1/ai/conversations')
            ->assertOk();

        // Start conversation
        $createRes = $this->withHeaders($headers)
            ->postJson('/api/v1/ai/conversations', [
                'project_id' => $this->project->id,
                'title' => 'Design Sprint Assistance',
            ]);
        $createRes->assertCreated();
        $convId = $createRes->json('data.id');

        // Post message & receive live context + insights + citations
        $msgRes = $this->withHeaders($headers)
            ->postJson("/api/v1/ai/conversations/{$convId}/messages", [
                'content' => 'What are the current blockers for the Alpha project?',
            ]);
        $msgRes->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'content',
                    'role',
                    'insights' => ['root_cause', 'current_blockers', 'suggested_fixes'],
                ],
            ]);
        $botMsgId = $msgRes->json('data.id');

        // Feedback thumbs up
        $this->withHeaders($headers)
            ->patchJson("/api/v1/ai/messages/{$botMsgId}/feedback", [
                'feedback' => 'like',
            ])
            ->assertOk()
            ->assertJsonPath('data.feedback', 'like');
    }

    public function test_9_reports_and_analytics_endpoints(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->memberToken];

        // Summary KPI cards
        $this->withHeaders($headers)
            ->getJson('/api/v1/reports/summary')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'task_completion' => ['rate', 'change'],
                    'projects_completion' => ['rate', 'change'],
                    'teams_activity' => ['rate', 'change'],
                ],
            ]);

        // Deadline comparison chart
        $this->withHeaders($headers)
            ->getJson('/api/v1/reports/charts/deadline-comparison')
            ->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['label', 'on_time', 'delayed']]]);

        // Project progress area chart
        $this->withHeaders($headers)
            ->getJson('/api/v1/reports/charts/project-progress')
            ->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['label', 'progress']]]);

        // Worker activity line chart
        $this->withHeaders($headers)
            ->getJson('/api/v1/reports/charts/worker-activity')
            ->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['label', 'activity_count', 'rate']]]);

        // List generated reports
        $this->withHeaders($headers)
            ->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Generate report
        Storage::fake('public');
        $genRes = $this->withHeaders($headers)
            ->postJson('/api/v1/reports', [
                'report_type' => 'task_completion',
                'project_id' => $this->project->id,
            ]);
        $genRes->assertCreated()
            ->assertJsonPath('data.report_type', 'task_completion');
    }

    public function test_10_files_and_folders_endpoints(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->memberToken];

        // View file details
        $this->withHeaders($headers)
            ->getJson("/api/v1/files/{$this->file->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->file->id);

        // Download file
        Storage::fake('public');
        Storage::disk('public')->put('projects/1/test_doc.pdf', 'Document content');
        $testFile = File::create([
            'project_id' => $this->project->id,
            'name' => 'test_doc.pdf',
            'path' => 'projects/1/test_doc.pdf',
            'disk' => 'public',
            'downloads_count' => 0,
            'uploaded_by' => $this->member->id,
        ]);

        $this->withHeaders($headers)
            ->get("/api/v1/files/{$testFile->id}/download")
            ->assertOk();

        // Create folder
        $folderRes = $this->withHeaders($headers)
            ->postJson("/api/v1/projects/{$this->project->id}/folders", [
                'name' => 'Production Assets',
            ]);
        $folderRes->assertCreated()
            ->assertJsonPath('data.name', 'Production Assets');
        $folderId = $folderRes->json('data.id');

        // View folder
        $this->withHeaders($headers)
            ->getJson("/api/v1/folders/{$folderId}")
            ->assertOk()
            ->assertJsonPath('data.id', $folderId);
    }

    public function test_11_in_app_notifications(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->memberToken];

        // 1. List notifications
        $listRes = $this->withHeaders($headers)
            ->getJson('/api/v1/notifications');
        $listRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['notifications', 'unread_count']]);

        // 2. Unread count
        $countRes = $this->withHeaders($headers)
            ->getJson('/api/v1/notifications/unread-count');
        $countRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['unread_count']]);

        // 3. Mark all as read
        $readAllRes = $this->withHeaders($headers)
            ->postJson('/api/v1/notifications/read-all');
        $readAllRes->assertOk()
            ->assertJsonPath('success', true);
    }
}
