<?php

namespace Tests\Feature;

use App\Enums\ChatChannelType;
use App\Enums\UserRole;
use App\Events\ChatMessageSent;
use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $this->otherUser = User::where('email', 'mohanad.wahib@collabspace.ai')->first();
    }

    protected function asUser(User $user): self
    {
        auth('api')->setUser($user);
        $token = auth('api')->tokenById($user->id);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_user_can_list_direct_inbox(): void
    {
        $response = $this->asUser($this->user)
            ->getJson('/api/v1/chat/inbox');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'channel_type',
                        'participant' => [
                            'id',
                            'name',
                            'email',
                            'job_title',
                            'avatar_url',
                            'availability_status',
                        ],
                        'last_message' => [
                            'id',
                            'message',
                            'sender_id',
                            'is_sender',
                            'created_at',
                            'time_ago',
                        ],
                        'unread_count',
                        'is_muted',
                    ],
                ],
            ]);
    }

    public function test_user_can_start_direct_chat_with_another_user(): void
    {
        $targetUser = User::where('email', 'sara.hassan@collabspace.ai')->first();

        // 1. First call creates the channel
        $response = $this->asUser($this->user)
            ->postJson('/api/v1/chat/direct', [
                'recipient_id' => $targetUser->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.participant.id', $targetUser->id);

        $channelId = $response->json('data.id');
        $this->assertDatabaseHas('chat_channels', [
            'id' => $channelId,
            'type' => ChatChannelType::Direct->value,
        ]);

        // 2. Second call returns the existing channel without duplicate creation
        $repeatResponse = $this->asUser($this->user)
            ->postJson('/api/v1/chat/direct', [
                'recipient_id' => $targetUser->id,
            ]);

        $repeatResponse->assertStatus(200)
            ->assertJsonPath('data.id', $channelId);
    }

    public function test_user_cannot_start_direct_chat_with_themselves(): void
    {
        $response = $this->asUser($this->user)
            ->postJson('/api/v1/chat/direct', [
                'recipient_id' => $this->user->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['recipient_id']);
    }

    public function test_project_member_can_list_project_channels(): void
    {
        $project = Project::first();

        $response = $this->asUser($this->user)
            ->getJson("/api/v1/chat/projects/{$project->id}/channels");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'type',
                        'project_id',
                        'members_count',
                        'unread_count',
                        'is_muted',
                    ],
                ],
            ]);
    }

    public function test_non_project_member_cannot_list_project_channels(): void
    {
        // Create an isolated outsider user
        $outsider = User::factory()->create([
            'role' => UserRole::TeamMember,
        ]);
        $project = Project::first();

        $response = $this->asUser($outsider)
            ->getJson("/api/v1/chat/projects/{$project->id}/channels");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_project_manager_can_create_project_channel(): void
    {
        $project = Project::first();

        $response = $this->asUser($this->otherUser)
            ->postJson('/api/v1/chat/channels', [
                'project_id' => $project->id,
                'name' => 'Backend Development Room',
                'type' => ChatChannelType::ProjectTeam->value,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Backend Development Room')
            ->assertJsonPath('data.type', ChatChannelType::ProjectTeam->value);

        $this->assertDatabaseHas('chat_channels', [
            'name' => 'Backend Development Room',
            'project_id' => $project->id,
        ]);
    }

    public function test_team_member_cannot_create_project_channel(): void
    {
        $teamMember = User::where('email', 'sara.hassan@collabspace.ai')->first();
        $project = Project::first();

        $response = $this->asUser($teamMember)
            ->postJson('/api/v1/chat/channels', [
                'project_id' => $project->id,
                'name' => 'Unauthorized Channel',
                'type' => ChatChannelType::ProjectTeam->value,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_user_can_view_channel_messages(): void
    {
        $channel = ChatChannel::where('type', ChatChannelType::ProjectPublic)->first();

        $response = $this->asUser($this->user)
            ->getJson("/api/v1/chat/channels/{$channel->id}/messages");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'messages' => [
                        '*' => [
                            'id',
                            'chat_channel_id',
                            'message',
                            'is_reply',
                            'sender' => [
                                'id',
                                'name',
                                'avatar_url',
                                'is_me',
                            ],
                            'created_at',
                            'created_at_formatted',
                        ],
                    ],
                    'pagination',
                ],
            ]);
    }

    public function test_user_can_send_message_to_channel(): void
    {
        $channel = ChatChannel::where('type', ChatChannelType::ProjectPublic)->first();

        $response = $this->asUser($this->user)
            ->postJson("/api/v1/chat/channels/{$channel->id}/messages", [
                'message' => 'Here is the latest project design update!',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'Here is the latest project design update!')
            ->assertJsonPath('data.sender.id', $this->user->id)
            ->assertJsonPath('data.sender.is_me', true);

        $this->assertDatabaseHas('chat_messages', [
            'chat_channel_id' => $channel->id,
            'user_id' => $this->user->id,
            'message' => 'Here is the latest project design update!',
        ]);
    }

    public function test_user_can_send_message_with_attachment_upload(): void
    {
        Storage::fake('public');
        $channel = ChatChannel::where('type', ChatChannelType::ProjectPublic)->first();
        $fakeFile = UploadedFile::fake()->create('Design_Mockup.png', 500, 'image/png');

        $response = $this->asUser($this->user)
            ->post("/api/v1/chat/channels/{$channel->id}/messages", [
                'message' => 'Sharing the new UI mockups.',
                'file' => $fakeFile,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'Sharing the new UI mockups.')
            ->assertJsonCount(1, 'data.attachments');

        $this->assertDatabaseHas('files', [
            'name' => 'Design_Mockup.png',
            'uploaded_by' => $this->user->id,
        ]);
    }

    public function test_user_can_reply_to_message_thread(): void
    {
        $channel = ChatChannel::where('type', ChatChannelType::ProjectPublic)->first();
        $parentMessage = ChatMessage::where('chat_channel_id', $channel->id)->whereNull('parent_id')->first();

        // 1. Post reply
        $response = $this->asUser($this->user)
            ->postJson("/api/v1/chat/messages/{$parentMessage->id}/reply", [
                'message' => 'Acknowledged! Looks fantastic.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.parent_id', $parentMessage->id)
            ->assertJsonPath('data.is_reply', true);

        // 2. Fetch thread
        $threadResponse = $this->asUser($this->user)
            ->getJson("/api/v1/chat/messages/{$parentMessage->id}/thread");

        $threadResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.parent.id', $parentMessage->id)
            ->assertJsonStructure([
                'data' => [
                    'parent',
                    'replies' => [
                        '*' => [
                            'id',
                            'message',
                            'sender',
                        ],
                    ],
                ],
            ]);
    }

    public function test_author_can_edit_their_message(): void
    {
        $channel = ChatChannel::where('type', ChatChannelType::ProjectPublic)->first();
        $message = ChatMessage::create([
            'chat_channel_id' => $channel->id,
            'user_id' => $this->user->id,
            'message' => 'Original text before editing.',
        ]);

        $response = $this->asUser($this->user)
            ->patchJson("/api/v1/chat/messages/{$message->id}", [
                'message' => 'Updated message text after editing.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'Updated message text after editing.')
            ->assertJsonPath('data.is_edited', true);

        $this->assertDatabaseHas('chat_messages', [
            'id' => $message->id,
            'message' => 'Updated message text after editing.',
            'is_edited' => true,
        ]);
    }

    public function test_non_author_cannot_edit_another_users_message(): void
    {
        $channel = ChatChannel::where('type', ChatChannelType::ProjectPublic)->first();
        $message = ChatMessage::create([
            'chat_channel_id' => $channel->id,
            'user_id' => $this->otherUser->id,
            'message' => 'Mohanad message.',
        ]);

        $response = $this->asUser($this->user)
            ->patchJson("/api/v1/chat/messages/{$message->id}", [
                'message' => 'Attempted hijack.',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_author_or_admin_can_delete_message(): void
    {
        $channel = ChatChannel::where('type', ChatChannelType::ProjectPublic)->first();
        $message = ChatMessage::create([
            'chat_channel_id' => $channel->id,
            'user_id' => $this->user->id,
            'message' => 'Message to delete.',
        ]);

        $response = $this->asUser($this->user)
            ->deleteJson("/api/v1/chat/messages/{$message->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('chat_messages', [
            'id' => $message->id,
        ]);
    }

    public function test_user_can_mark_channel_as_read(): void
    {
        $channel = ChatChannel::where('type', ChatChannelType::ProjectPublic)->first();

        $response = $this->asUser($this->user)
            ->postJson("/api/v1/chat/channels/{$channel->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Channel marked as read.');
    }

    public function test_user_can_toggle_channel_mute(): void
    {
        $channel = ChatChannel::where('type', ChatChannelType::ProjectPublic)->first();

        // 1. Mute
        $response = $this->asUser($this->user)
            ->postJson("/api/v1/chat/channels/{$channel->id}/mute");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_muted', true);

        // 2. Unmute
        $unmuteResponse = $this->asUser($this->user)
            ->postJson("/api/v1/chat/channels/{$channel->id}/mute");

        $unmuteResponse->assertStatus(200)
            ->assertJsonPath('data.is_muted', false);
    }

    public function test_chat_message_sent_event_is_dispatched_as_queued_broadcast(): void
    {
        Event::fake([
            ChatMessageSent::class,
        ]);

        $channel = ChatChannel::where('type', ChatChannelType::ProjectPublic)->first();

        $this->asUser($this->user)
            ->postJson("/api/v1/chat/channels/{$channel->id}/messages", [
                'message' => 'Real-time test message!',
            ]);

        Event::assertDispatched(ChatMessageSent::class, function ($event) use ($channel) {
            $isQueued = $event instanceof ShouldBroadcast;
            $matchesChannel = $event->broadcastOn()[0]->name === 'private-chat.channel.'.$channel->id;

            return $isQueued && $matchesChannel && $event->message->message === 'Real-time test message!';
        });
    }

    public function test_authorized_member_can_authenticate_for_reverb_private_channel(): void
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

        $channel = ChatChannel::where('type', ChatChannelType::ProjectPublic)->first();

        $response = $this->asUser($this->user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-chat.channel.'.$channel->id,
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['auth']);
    }

    public function test_unauthorized_user_cannot_authenticate_for_private_channel(): void
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
        $channel = ChatChannel::where('type', ChatChannelType::ProjectPublic)->first();

        $response = $this->asUser($outsider)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-chat.channel.'.$channel->id,
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(403);
    }
}
