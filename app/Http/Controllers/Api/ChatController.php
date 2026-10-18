<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ReplyChatMessageRequest;
use App\Http\Requests\Chat\StartDirectChatRequest;
use App\Http\Requests\Chat\StoreChatChannelRequest;
use App\Http\Requests\Chat\StoreChatMessageRequest;
use App\Http\Requests\Chat\UpdateChatMessageRequest;
use App\Http\Resources\Chat\ChatChannelResource;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Http\Resources\Chat\DirectInboxResource;
use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\Project;
use App\Services\ChatService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChatController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ChatService $chatService
    ) {}

    /**
     * Get direct 1-on-1 inbox chats for the authenticated user.
     */
    public function inbox(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $channels = $this->chatService->getDirectInbox($user);

        return $this->success(
            DirectInboxResource::collection($channels),
            'Direct inbox retrieved successfully.'
        );
    }

    /**
     * Start or open a direct 1-on-1 chat with a user.
     */
    public function startDirect(StartDirectChatRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $recipientId = (int) $request->validated('recipient_id');
        $channel = $this->chatService->getOrCreateDirectChannel($user, $recipientId);

        return $this->success(
            new DirectInboxResource($channel),
            'Direct chat ready.'
        );
    }

    /**
     * Get project channels (Public channel, department team channels).
     */
    public function projectChannels(Project $project): JsonResponse
    {
        $user = auth('api')->user();

        if (! $user->isAdmin() && ! $project->hasMember($user->id)) {
            return $this->error([], 'Forbidden. You are not a member of this project.', 403);
        }

        $channels = $this->chatService->getProjectChannels($user, $project);

        return $this->success(
            ChatChannelResource::collection($channels),
            'Project channels retrieved successfully.'
        );
    }

    /**
     * Create a new project channel.
     */
    public function createChannel(StoreChatChannelRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $project = Project::findOrFail($request->validated('project_id'));

        if (! $user->isAdmin() && ! $user->isManagerOfProject($project->id)) {
            return $this->error([], 'Forbidden. Only project managers or administrators can create project channels.', 403);
        }

        $channel = $this->chatService->createProjectChannel($user, $request->validated());

        return $this->success(
            new ChatChannelResource($channel),
            'Chat channel created successfully.',
            201
        );
    }

    /**
     * Get channel details.
     */
    public function showChannel(ChatChannel $channel): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('view', $channel)) {
            return $this->error([], 'Forbidden. You do not have permission to view this channel.', 403);
        }

        return $this->success(
            new ChatChannelResource($channel->load(['project', 'members', 'latestMessage.user'])),
            'Channel retrieved successfully.'
        );
    }

    /**
     * Get messages for a channel.
     */
    public function messages(Request $request, ChatChannel $channel): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('view', $channel)) {
            return $this->error([], 'Forbidden. You do not have permission to view messages in this channel.', 403);
        }

        $perPage = (int) $request->query('per_page', 50);
        $includeReplies = $request->boolean('include_replies', false);
        $messages = $this->chatService->getChannelMessages($channel, $perPage, $includeReplies);

        return $this->success([
            'messages' => ChatMessageResource::collection($messages),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ], 'Channel messages retrieved successfully.');
    }

    /**
     * Send a new message to a channel.
     */
    public function sendMessage(StoreChatMessageRequest $request, ChatChannel $channel): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('sendMessage', $channel)) {
            return $this->error([], 'Forbidden. You do not have permission to send messages in this channel.', 403);
        }

        $message = $this->chatService->sendMessage($user, $channel, $request->validated());

        return $this->success(
            new ChatMessageResource($message),
            'Message sent successfully.',
            201
        );
    }

    /**
     * Get thread replies for a message.
     */
    public function thread(ChatMessage $message): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('view', $message->channel)) {
            return $this->error([], 'Forbidden. You do not have permission to view this thread.', 403);
        }

        $thread = $this->chatService->getMessageThread($message);

        return $this->success([
            'parent' => new ChatMessageResource($thread['parent']),
            'replies' => ChatMessageResource::collection($thread['replies']),
        ], 'Message thread retrieved successfully.');
    }

    /**
     * Reply to a message thread.
     */
    public function reply(ReplyChatMessageRequest $request, ChatMessage $message): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('sendMessage', $message->channel)) {
            return $this->error([], 'Forbidden. You do not have permission to reply in this channel.', 403);
        }

        $reply = $this->chatService->replyToMessage($user, $message, $request->validated());

        return $this->success(
            new ChatMessageResource($reply),
            'Reply sent successfully.',
            201
        );
    }

    /**
     * Update an existing message.
     */
    public function updateMessage(UpdateChatMessageRequest $request, ChatMessage $message): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('update', $message)) {
            return $this->error([], 'Forbidden. You can only edit your own messages.', 403);
        }

        $updated = $this->chatService->updateMessage($message, $request->validated('message'));

        return $this->success(
            new ChatMessageResource($updated),
            'Message updated successfully.'
        );
    }

    /**
     * Delete a message.
     */
    public function deleteMessage(ChatMessage $message): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('delete', $message)) {
            return $this->error([], 'Forbidden. You do not have permission to delete this message.', 403);
        }

        $this->chatService->deleteMessage($message);

        return $this->success([], 'Message deleted successfully.');
    }

    /**
     * Mark channel messages as read for the authenticated user.
     */
    public function markAsRead(ChatChannel $channel): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('view', $channel)) {
            return $this->error([], 'Forbidden. You do not have permission to access this channel.', 403);
        }

        $this->chatService->markChannelAsRead($user, $channel);

        return $this->success([], 'Channel marked as read.');
    }

    /**
     * Toggle channel notifications mute.
     */
    public function toggleMute(ChatChannel $channel): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('view', $channel)) {
            return $this->error([], 'Forbidden. You do not have permission to access this channel.', 403);
        }

        $isMuted = $this->chatService->toggleChannelMute($user, $channel);

        return $this->success([
            'is_muted' => $isMuted,
        ], $isMuted ? 'Channel muted.' : 'Channel unmuted.');
    }
}
