<?php

namespace Tests\Feature;

use App\Enums\MeetingParticipantStatus;
use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Events\MeetingTranscriptAdded;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Services\GoogleMeetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $managerUser;

    protected Project $project;

    protected Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $this->managerUser = User::where('email', 'mohanad.wahib@collabspace.ai')->first();
        $this->project = Project::first();
        $this->meeting = Meeting::first();
    }

    protected function asUser(User $user): self
    {
        auth('api')->setUser($user);
        $token = auth('api')->tokenById($user->id);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_user_can_list_meetings_calendar(): void
    {
        $response = $this->asUser($this->user)
            ->getJson('/api/v1/meetings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'date',
                        'date_formatted',
                        'time_slot',
                        'meeting_link',
                        'status',
                        'project' => ['id', 'name'],
                        'creator' => ['id', 'name', 'avatar_url'],
                        'participants_preview',
                        'extra_participants_count',
                        'total_participants_count',
                        'transcripts_count',
                        'has_ai_summary',
                    ],
                ],
            ]);
    }

    public function test_user_can_filter_meetings_by_project(): void
    {
        $response = $this->asUser($this->user)
            ->getJson("/api/v1/meetings?project_id={$this->project->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        foreach ($response->json('data') as $meetingItem) {
            $this->assertEquals($this->project->id, $meetingItem['project']['id']);
        }
    }

    public function test_user_can_filter_my_meetings(): void
    {
        $response = $this->asUser($this->user)
            ->getJson('/api/v1/meetings?my_meetings=true');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_authorized_user_can_schedule_meeting(): void
    {
        $response = $this->asUser($this->managerUser)
            ->postJson('/api/v1/meetings', [
                'project_id' => $this->project->id,
                'title' => 'Design QA & Cross-Browser Testing',
                'description' => 'Review breakpoint layouts across Safari, Chrome, and Firefox.',
                'date' => now()->addDays(2)->toDateString(),
                'start_time' => '11:00:00',
                'end_time' => '12:00:00',
                'meeting_link' => 'https://meet.collabspace.ai/qa-session',
                'participant_ids' => [$this->user->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Design QA & Cross-Browser Testing')
            ->assertJsonPath('data.time_slot', '11:00 - 12:00');

        $this->assertDatabaseHas('meetings', [
            'title' => 'Design QA & Cross-Browser Testing',
            'project_id' => $this->project->id,
            'created_by' => $this->managerUser->id,
        ]);

        $newMeeting = Meeting::where('title', 'Design QA & Cross-Browser Testing')->first();
        $this->assertTrue($newMeeting->participants()->where('users.id', $this->managerUser->id)->exists());
        $this->assertTrue($newMeeting->participants()->where('users.id', $this->user->id)->exists());
    }

    public function test_schedule_meeting_validation_fails_on_missing_fields(): void
    {
        $response = $this->asUser($this->user)
            ->postJson('/api/v1/meetings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project_id', 'title', 'date', 'start_time', 'end_time']);
    }

    public function test_user_can_view_meeting_details(): void
    {
        $response = $this->asUser($this->user)
            ->getJson("/api/v1/meetings/{$this->meeting->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->meeting->id)
            ->assertJsonPath('data.title', $this->meeting->title)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'date',
                    'time_slot',
                    'project',
                    'creator',
                    'participants',
                    'ai_summary',
                ],
            ]);
    }

    public function test_non_participant_outsider_cannot_view_meeting(): void
    {
        $outsider = User::factory()->create([
            'role' => UserRole::TeamMember,
        ]);

        $response = $this->asUser($outsider)
            ->getJson("/api/v1/meetings/{$this->meeting->id}");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_meeting_creator_can_update_meeting(): void
    {
        $response = $this->asUser($this->managerUser)
            ->putJson("/api/v1/meetings/{$this->meeting->id}", [
                'title' => 'Design Review (Updated Time)',
                'start_time' => '15:00:00',
                'end_time' => '16:00:00',
                'status' => MeetingStatus::Completed->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Design Review (Updated Time)')
            ->assertJsonPath('data.time_slot', '15:00 - 16:00')
            ->assertJsonPath('data.status', MeetingStatus::Completed->value);

        $this->assertDatabaseHas('meetings', [
            'id' => $this->meeting->id,
            'title' => 'Design Review (Updated Time)',
            'status' => MeetingStatus::Completed->value,
        ]);
    }

    public function test_unauthorized_member_cannot_update_meeting(): void
    {
        $outsider = User::factory()->create([
            'role' => UserRole::TeamMember,
        ]);

        $response = $this->asUser($outsider)
            ->putJson("/api/v1/meetings/{$this->meeting->id}", [
                'title' => 'Hacked Title',
            ]);

        $response->assertStatus(403);
    }

    public function test_meeting_creator_can_delete_meeting(): void
    {
        $newMeeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->managerUser->id,
            'title' => 'Temporary Sync Meeting',
            'date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'status' => MeetingStatus::Scheduled,
        ]);

        $response = $this->asUser($this->managerUser)
            ->deleteJson("/api/v1/meetings/{$newMeeting->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('meetings', ['id' => $newMeeting->id]);
    }

    public function test_user_can_rsvp_to_meeting(): void
    {
        $response = $this->asUser($this->user)
            ->patchJson("/api/v1/meetings/{$this->meeting->id}/rsvp", [
                'status' => MeetingParticipantStatus::Accepted->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.my_rsvp_status', MeetingParticipantStatus::Accepted->value);

        $this->assertDatabaseHas('meeting_user', [
            'meeting_id' => $this->meeting->id,
            'user_id' => $this->user->id,
            'status' => MeetingParticipantStatus::Accepted->value,
        ]);
    }

    public function test_user_can_list_meeting_transcripts(): void
    {
        $response = $this->asUser($this->user)
            ->getJson("/api/v1/meetings/{$this->meeting->id}/transcripts");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'meeting_id',
                        'speaker_name',
                        'timestamp_offset',
                        'transcript_text',
                        'spoken_at',
                        'user',
                    ],
                ],
            ]);
    }

    public function test_user_can_add_live_transcript_line_and_broadcasts(): void
    {
        Event::fake([MeetingTranscriptAdded::class]);

        $response = $this->asUser($this->user)
            ->postJson("/api/v1/meetings/{$this->meeting->id}/transcripts", [
                'transcript_text' => 'We also need to ensure the color contrast passes WCAG AA guidelines.',
                'timestamp_offset' => '14:10',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.speaker_name', $this->user->name)
            ->assertJsonPath('data.transcript_text', 'We also need to ensure the color contrast passes WCAG AA guidelines.')
            ->assertJsonPath('data.timestamp_offset', '14:10');

        $this->assertDatabaseHas('meeting_transcripts', [
            'meeting_id' => $this->meeting->id,
            'user_id' => $this->user->id,
            'timestamp_offset' => '14:10',
        ]);

        // Assert participant is auto-marked as attended
        $this->assertDatabaseHas('meeting_user', [
            'meeting_id' => $this->meeting->id,
            'user_id' => $this->user->id,
            'status' => MeetingParticipantStatus::Attended->value,
        ]);

        Event::assertDispatched(MeetingTranscriptAdded::class);
    }

    public function test_user_can_retrieve_existing_ai_meeting_summary(): void
    {
        $response = $this->asUser($this->user)
            ->getJson("/api/v1/meetings/{$this->meeting->id}/ai-summary");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'meeting_id',
                    'summary',
                    'key_points',
                    'decisions_made',
                    'action_items',
                    'assigned_tasks',
                    'risks',
                    'next_steps',
                    'generated_at',
                ],
            ]);
    }

    public function test_user_can_generate_or_regenerate_ai_meeting_summary(): void
    {
        $response = $this->asUser($this->user)
            ->postJson("/api/v1/meetings/{$this->meeting->id}/ai-summary");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'meeting_id',
                    'summary',
                    'key_points',
                    'decisions_made',
                    'action_items',
                    'assigned_tasks',
                ],
            ]);

        $this->assertDatabaseHas('ai_meeting_summaries', [
            'meeting_id' => $this->meeting->id,
        ]);
    }

    public function test_authorized_member_can_authenticate_for_meeting_reverb_private_channel(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '12345',
            'broadcasting.connections.reverb.options.host' => 'localhost',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');

        $response = $this->asUser($this->user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-meeting.'.$this->meeting->id,
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['auth']);
    }

    public function test_unauthorized_user_cannot_authenticate_for_meeting_channel(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '12345',
            'broadcasting.connections.reverb.options.host' => 'localhost',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');

        $outsider = User::factory()->create([
            'role' => UserRole::TeamMember,
        ]);

        $response = $this->asUser($outsider)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-meeting.'.$this->meeting->id,
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(403);
    }

    public function test_meeting_auto_generates_google_meet_link_if_omitted(): void
    {
        $payload = [
            'project_id' => $this->project->id,
            'title' => 'Architecture Review',
            'description' => 'Review microservice breakdown',
            'date' => now()->addDays(2)->toDateString(),
            'start_time' => '14:00',
            'end_time' => '15:30',
        ];

        $response = $this->asUser($this->managerUser)
            ->postJson('/api/v1/meetings', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $meetingLink = $response->json('data.meeting_link');
        $this->assertNotNull($meetingLink);
        $this->assertStringStartsWith('https://meet.google.com/', $meetingLink);
    }

    public function test_generate_google_meet_space_endpoint(): void
    {
        $response = $this->asUser($this->user)
            ->postJson('/api/v1/meetings/google-meet-space', [
                'title' => 'Design Sync Call',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'meeting_link',
                    'meeting_code',
                    'provider',
                ],
            ]);

        $this->assertStringStartsWith('https://meet.google.com/', $response->json('data.meeting_link'));
    }

    public function test_google_meet_diagnostics_endpoint(): void
    {
        $response = $this->asUser($this->user)
            ->getJson('/api/v1/meetings/google-meet/diagnostics');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'client_id_configured',
                    'client_secret_configured',
                    'refresh_token_configured',
                    'access_token_configured',
                    'active_token_resolved',
                    'api_connection_status',
                ],
            ]);
    }

    public function test_google_meet_api_creates_real_space_when_token_available(): void
    {
        Http::fake([
            'https://meet.googleapis.com/v2/spaces' => Http::response([
                'name' => 'spaces/mock-space-123',
                'meetingUri' => 'https://meet.google.com/xyz-uvwx-rst',
                'meetingCode' => 'xyz-uvwx-rst',
            ], 200),
        ]);

        config()->set('services.google.meet_access_token', 'ya29.mock_access_token');

        $meetService = app(GoogleMeetService::class);
        $space = $meetService->createMeetingSpace('Sprint Review');

        $this->assertEquals('https://meet.google.com/xyz-uvwx-rst', $space['meeting_link']);
        $this->assertEquals('xyz-uvwx-rst', $space['meeting_code']);
        $this->assertEquals('google_meet_api', $space['provider']);
    }

    public function test_google_meet_service_auto_refreshes_token_and_creates_space(): void
    {
        config()->set('services.google.meet_access_token', null);
        config()->set('services.google.client_id', 'test_client_id');
        config()->set('services.google.client_secret', 'test_client_secret');
        config()->set('services.google.refresh_token', 'test_refresh_token');

        Cache::forget(GoogleMeetService::TOKEN_CACHE_KEY);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.refreshed_mock_token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'https://meet.googleapis.com/v2/spaces' => Http::response([
                'name' => 'spaces/live-space-456',
                'meetingUri' => 'https://meet.google.com/abc-defg-hij',
                'meetingCode' => 'abc-defg-hij',
            ], 200),
        ]);

        $meetService = app(GoogleMeetService::class);
        $space = $meetService->createMeetingSpace('Monthly All-Hands');

        $this->assertEquals('https://meet.google.com/abc-defg-hij', $space['meeting_link']);
        $this->assertEquals('google_meet_api', $space['provider']);
    }
}
