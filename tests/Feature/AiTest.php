<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Enums\UserRole;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected AiConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $this->project = Project::first();
        $this->conversation = AiConversation::where('user_id', $this->user->id)->first();
    }

    protected function asUser(User $user): self
    {
        auth('api')->setUser($user);
        $token = auth('api')->tokenById($user->id);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_user_can_list_ai_conversations(): void
    {
        $response = $this->asUser($this->user)
            ->getJson('/api/v1/ai/conversations');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'project_id',
                        'project_name',
                        'messages_count',
                        'last_message' => [
                            'id',
                            'role',
                            'content',
                            'feedback',
                            'insights',
                            'citations',
                        ],
                        'created_at',
                    ],
                ],
            ]);
    }

    public function test_user_can_filter_ai_conversations_by_project(): void
    {
        $response = $this->asUser($this->user)
            ->getJson("/api/v1/ai/conversations?project_id={$this->project->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        foreach ($response->json('data') as $item) {
            $this->assertEquals($this->project->id, $item['project_id']);
        }
    }

    public function test_user_can_start_ai_conversation(): void
    {
        $response = $this->asUser($this->user)
            ->postJson('/api/v1/ai/conversations', [
                'project_id' => $this->project->id,
                'title' => 'Design Sprint Bottleneck Analysis',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Design Sprint Bottleneck Analysis')
            ->assertJsonPath('data.project_id', $this->project->id);

        $this->assertDatabaseHas('ai_conversations', [
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'title' => 'Design Sprint Bottleneck Analysis',
        ]);
    }

    public function test_user_can_start_ai_conversation_with_initial_prompt(): void
    {
        $response = $this->asUser($this->user)
            ->postJson('/api/v1/ai/conversations', [
                'project_id' => $this->project->id,
                'prompt' => 'What are the top blockers in Project Alpha?',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.messages_count', 2);

        $this->assertDatabaseHas('ai_conversations', [
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
        ]);
    }

    public function test_user_can_view_ai_conversation_details(): void
    {
        $response = $this->asUser($this->user)
            ->getJson("/api/v1/ai/conversations/{$this->conversation->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->conversation->id)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'project_id',
                    'project_name',
                    'messages_count',
                    'messages' => [
                        '*' => [
                            'id',
                            'role',
                            'content',
                            'feedback',
                            'insights',
                            'citations',
                        ],
                    ],
                ],
            ]);
    }

    public function test_outsider_cannot_view_another_users_ai_conversation(): void
    {
        $outsider = User::factory()->create([
            'role' => UserRole::TeamMember,
        ]);

        $response = $this->asUser($outsider)
            ->getJson("/api/v1/ai/conversations/{$this->conversation->id}");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_user_can_send_prompt_and_receive_insights_and_citations(): void
    {
        $response = $this->asUser($this->user)
            ->postJson("/api/v1/ai/conversations/{$this->conversation->id}/messages", [
                'content' => 'What are the main delivery risks and dependencies?',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', AiMessageRole::Assistant->value)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'role',
                    'content',
                    'insights' => [
                        'root_cause',
                        'current_blockers',
                        'suggested_fixes',
                    ],
                    'citations',
                ],
            ]);

        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $this->conversation->id,
            'role' => AiMessageRole::User->value,
            'content' => 'What are the main delivery risks and dependencies?',
        ]);

        $this->assertDatabaseHas('ai_activity_logs', [
            'user_id' => $this->user->id,
            'action_type' => 'project_insights',
        ]);
    }

    public function test_user_can_submit_feedback_like_on_ai_message(): void
    {
        $assistantMsg = AiMessage::where('ai_conversation_id', $this->conversation->id)
            ->where('role', AiMessageRole::Assistant)
            ->first();

        $response = $this->asUser($this->user)
            ->patchJson("/api/v1/ai/messages/{$assistantMsg->id}/feedback", [
                'feedback' => 'like',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.feedback', 'like');

        $this->assertDatabaseHas('ai_messages', [
            'id' => $assistantMsg->id,
            'feedback' => 'like',
        ]);
    }

    public function test_user_can_submit_feedback_dislike_on_ai_message(): void
    {
        $assistantMsg = AiMessage::where('ai_conversation_id', $this->conversation->id)
            ->where('role', AiMessageRole::Assistant)
            ->first();

        $response = $this->asUser($this->user)
            ->patchJson("/api/v1/ai/messages/{$assistantMsg->id}/feedback", [
                'feedback' => 'dislike',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.feedback', 'dislike');

        $this->assertDatabaseHas('ai_messages', [
            'id' => $assistantMsg->id,
            'feedback' => 'dislike',
        ]);
    }

    public function test_feedback_validation_fails_on_invalid_value(): void
    {
        $assistantMsg = AiMessage::where('ai_conversation_id', $this->conversation->id)
            ->where('role', AiMessageRole::Assistant)
            ->first();

        $response = $this->asUser($this->user)
            ->patchJson("/api/v1/ai/messages/{$assistantMsg->id}/feedback", [
                'feedback' => 'great_job',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['feedback']);
    }

    public function test_outsider_cannot_rate_another_users_ai_message(): void
    {
        $outsider = User::factory()->create([
            'role' => UserRole::TeamMember,
        ]);

        $assistantMsg = AiMessage::where('ai_conversation_id', $this->conversation->id)
            ->where('role', AiMessageRole::Assistant)
            ->first();

        $response = $this->asUser($outsider)
            ->patchJson("/api/v1/ai/messages/{$assistantMsg->id}/feedback", [
                'feedback' => 'like',
            ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_ai_conversation(): void
    {
        $tempConversation = AiConversation::create([
            'user_id' => $this->user->id,
            'title' => 'Temporary Conversation',
        ]);

        $response = $this->asUser($this->user)
            ->deleteJson("/api/v1/ai/conversations/{$tempConversation->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('ai_conversations', ['id' => $tempConversation->id]);
    }
}
