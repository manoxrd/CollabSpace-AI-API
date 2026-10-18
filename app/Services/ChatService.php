<?php

namespace App\Services;

use App\Enums\ChatChannelType;
use App\Events\ChatMessageSent;
use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\File;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ChatService
{
    /**
     * Get direct 1-on-1 inbox chats for the authenticated user.
     *
     * @return Collection<int, ChatChannel>
     */
    public function getDirectInbox(User $user): Collection
    {
        return ChatChannel::direct()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->with([
                'members',
                'latestMessage.user',
                'latestMessage.attachments',
            ])
            ->get()
            ->sortByDesc(fn ($channel) => $channel->latestMessage?->created_at ?? $channel->created_at)
            ->values();
    }

    /**
     * Start or retrieve an existing 1-on-1 direct chat channel between two users.
     */
    public function getOrCreateDirectChannel(User $user, int $recipientId): ChatChannel
    {
        // Check if channel already exists between these two users
        $existingChannel = ChatChannel::direct()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->whereHas('members', fn ($q) => $q->where('users.id', $recipientId))
            ->with(['members', 'latestMessage.user', 'latestMessage.attachments'])
            ->first();

        if ($existingChannel) {
            return $existingChannel;
        }

        return DB::transaction(function () use ($user, $recipientId) {
            $recipient = User::findOrFail($recipientId);

            $channel = ChatChannel::create([
                'project_id' => null,
                'name' => "Direct: {$user->name} & {$recipient->name}",
                'type' => ChatChannelType::Direct,
                'created_by' => $user->id,
            ]);

            $channel->members()->attach([
                $user->id => ['last_read_at' => now(), 'is_muted' => false],
                $recipientId => ['last_read_at' => null, 'is_muted' => false],
            ]);

            return $channel->load(['members', 'latestMessage.user', 'latestMessage.attachments']);
        });
    }

    /**
     * Get project channels (Public channel, team channels).
     *
     * @return Collection<int, ChatChannel>
     */
    public function getProjectChannels(User $user, Project $project): Collection
    {
        $channels = $project->chatChannels()
            ->projectChannels()
            ->with(['project', 'members', 'latestMessage.user', 'latestMessage.attachments'])
            ->get();

        // Ensure user is attached in pivot so last_read_at can be tracked
        foreach ($channels as $channel) {
            if (! $channel->members->contains('id', $user->id)) {
                $channel->members()->attach($user->id, [
                    'last_read_at' => now(),
                    'is_muted' => false,
                ]);
            }
        }

        return $channels;
    }

    /**
     * Create a new project channel.
     */
    public function createProjectChannel(User $user, array $data): ChatChannel
    {
        return DB::transaction(function () use ($user, $data) {
            $project = Project::findOrFail($data['project_id']);

            $channel = ChatChannel::create([
                'project_id' => $project->id,
                'name' => $data['name'],
                'type' => $data['type'] ?? ChatChannelType::ProjectPublic,
                'created_by' => $user->id,
            ]);

            // Add creator with immediate read mark
            $channel->members()->attach($user->id, [
                'last_read_at' => now(),
                'is_muted' => false,
            ]);

            // If public project channel, auto-join all project members
            if ($channel->type === ChatChannelType::ProjectPublic) {
                $memberIds = $project->members()->pluck('users.id')->diff([$user->id])->all();
                if (! empty($memberIds)) {
                    $pivotData = [];
                    foreach ($memberIds as $mId) {
                        $pivotData[$mId] = ['last_read_at' => null, 'is_muted' => false];
                    }
                    $channel->members()->attach($pivotData);
                }
            }

            return $channel->load(['project', 'members', 'latestMessage.user']);
        });
    }

    /**
     * Get paginated messages for a channel.
     */
    public function getChannelMessages(ChatChannel $channel, int $perPage = 50, bool $includeReplies = false): LengthAwarePaginator
    {
        $query = $channel->messages()
            ->with(['user', 'attachments', 'parent.user'])
            ->withCount('replies');

        if (! $includeReplies) {
            // By default, show top-level messages (replies accessed via thread)
            $query->whereNull('parent_id');
        }

        return $query->orderBy('created_at', 'asc')->paginate($perPage);
    }

    /**
     * Send a new message to a channel with optional attachments.
     */
    public function sendMessage(User $user, ChatChannel $channel, array $data): ChatMessage
    {
        return DB::transaction(function () use ($user, $channel, $data) {
            $attachmentIds = $data['attachment_ids'] ?? [];

            // Handle direct file upload
            if (isset($data['file']) && $data['file'] instanceof UploadedFile) {
                $uploaded = $data['file'];
                $path = $uploaded->store("chat/{$channel->id}/attachments", 'public');

                $file = File::create([
                    'project_id' => $channel->project_id,
                    'name' => $uploaded->getClientOriginalName(),
                    'path' => $path,
                    'disk' => 'public',
                    'mime_type' => $uploaded->getClientMimeType(),
                    'size' => $uploaded->getSize(),
                    'uploaded_by' => $user->id,
                ]);

                $attachmentIds[] = $file->id;
            }

            $message = ChatMessage::create([
                'chat_channel_id' => $channel->id,
                'user_id' => $user->id,
                'parent_id' => $data['parent_id'] ?? null,
                'message' => $data['message'] ?? '',
                'is_edited' => false,
            ]);

            if (! empty($attachmentIds)) {
                $message->attachments()->sync(array_unique($attachmentIds));
            }

            // Update sender's read pointer and touch channel
            $channel->members()->syncWithoutDetaching([
                $user->id => ['last_read_at' => now()],
            ]);
            $channel->touch();

            // Broadcast real-time event
            try {
                event(new ChatMessageSent($message));
            } catch (\Throwable $e) {
                // Ignore broadcast transport errors during testing/offline
            }

            return $message->load(['user', 'attachments', 'parent.user']);
        });
    }

    /**
     * Get all replies for a parent message thread.
     *
     * @return array{parent: ChatMessage, replies: Collection<int, ChatMessage>}
     */
    public function getMessageThread(ChatMessage $message): array
    {
        $parent = $message->load(['user', 'attachments', 'channel']);

        $replies = $message->replies()
            ->with(['user', 'attachments'])
            ->orderBy('created_at', 'asc')
            ->get();

        return [
            'parent' => $parent,
            'replies' => $replies,
        ];
    }

    /**
     * Post a reply to an existing message thread.
     */
    public function replyToMessage(User $user, ChatMessage $parentMessage, array $data): ChatMessage
    {
        $data['parent_id'] = $parentMessage->id;

        return $this->sendMessage($user, $parentMessage->channel, $data);
    }

    /**
     * Edit message content.
     */
    public function updateMessage(ChatMessage $message, string $newContent): ChatMessage
    {
        $message->update([
            'message' => $newContent,
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        return $message->fresh(['user', 'attachments', 'parent.user']);
    }

    /**
     * Delete message.
     */
    public function deleteMessage(ChatMessage $message): bool
    {
        return DB::transaction(function () use ($message) {
            $message->attachments()->detach();

            return $message->delete();
        });
    }

    /**
     * Mark all messages in a channel as read for the user.
     */
    public function markChannelAsRead(User $user, ChatChannel $channel): void
    {
        $channel->members()->syncWithoutDetaching([
            $user->id => ['last_read_at' => now()],
        ]);
    }

    /**
     * Toggle notifications mute for the user on this channel.
     */
    public function toggleChannelMute(User $user, ChatChannel $channel): bool
    {
        $currentMute = $channel->isMutedFor($user);
        $newMute = ! $currentMute;

        $channel->members()->syncWithoutDetaching([
            $user->id => ['is_muted' => $newMute],
        ]);

        return $newMute;
    }
}
