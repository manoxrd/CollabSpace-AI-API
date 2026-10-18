<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\MeetingInviteNotification;
use App\Notifications\TaskAssignedNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $this->token = auth('api')->login($this->user);
    }

    public function test_user_can_list_notifications_with_unread_count(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'notifications' => [
                        'data' => [
                            '*' => [
                                'id',
                                'type',
                                'title',
                                'message',
                                'data',
                                'read_at',
                                'is_read',
                                'created_at',
                            ],
                        ],
                    ],
                    'unread_count',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.unread_count'));
    }

    public function test_user_can_filter_unread_notifications(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/notifications?unread=1');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data.notifications.data');
        foreach ($items as $item) {
            $this->assertFalse($item['is_read']);
            $this->assertNull($item['read_at']);
        }
    }

    public function test_user_can_get_unread_count(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'unread_count',
                ],
            ]);
    }

    public function test_user_can_mark_single_notification_as_read(): void
    {
        $unreadNotification = $this->user->unreadNotifications()->first();
        $this->assertNotNull($unreadNotification);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson("/api/v1/notifications/{$unreadNotification->id}/read");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($response->json('data.read_at'));
        $this->assertNotNull($unreadNotification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['marked_count'],
            ]);

        $this->assertEquals(0, $this->user->unreadNotifications()->count());
    }

    public function test_user_can_delete_notification(): void
    {
        $notification = $this->user->notifications()->first();
        $this->assertNotNull($notification);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/v1/notifications/{$notification->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull($this->user->notifications()->where('id', $notification->id)->first());
    }

    public function test_user_cannot_access_or_modify_another_users_notification(): void
    {
        $otherUser = User::where('email', 'mohanad.wahib@collabspace.ai')->first();
        $otherNotification = $otherUser->notifications()->first();
        $this->assertNotNull($otherNotification);

        // Trying to mark another user's notification as read
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson("/api/v1/notifications/{$otherNotification->id}/read");

        $response->assertNotFound();

        // Trying to delete another user's notification
        $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/v1/notifications/{$otherNotification->id}");

        $deleteResponse->assertNotFound();
    }

    public function test_notification_dispatches_via_database_and_mail(): void
    {
        Notification::fake();

        $task = Task::first();
        $meeting = Meeting::first();

        $this->user->notify(new TaskAssignedNotification($task, 'Admin'));
        $this->user->notify(new MeetingInviteNotification($meeting, 'Admin'));

        Notification::assertSentTo(
            $this->user,
            TaskAssignedNotification::class,
            function (TaskAssignedNotification $notification) {
                return in_array('database', $notification->via($this->user)) &&
                    in_array('mail', $notification->via($this->user));
            }
        );

        Notification::assertSentTo(
            $this->user,
            MeetingInviteNotification::class,
            function (MeetingInviteNotification $notification) {
                return in_array('database', $notification->via($this->user)) &&
                    in_array('mail', $notification->via($this->user));
            }
        );
    }
}
