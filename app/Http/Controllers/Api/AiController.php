<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\FeedbackAiMessageRequest;
use App\Http\Requests\Ai\SendAiMessageRequest;
use App\Http\Requests\Ai\StoreAiConversationRequest;
use App\Http\Resources\Ai\AiConversationResource;
use App\Http\Resources\Ai\AiMessageResource;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\AiService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AiController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AiService $aiService
    ) {}

    /**
     * List user's AI conversations with optional project filter.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $projectId = $request->filled('project_id') ? (int) $request->query('project_id') : null;

        $conversations = $this->aiService->listConversations($user, $projectId);

        return $this->success(
            AiConversationResource::collection($conversations),
            'AI conversations retrieved successfully.'
        );
    }

    /**
     * Start a new AI consultation session.
     */
    public function store(StoreAiConversationRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $conversation = $this->aiService->createConversation($user, $request->validated());

        return $this->success(
            new AiConversationResource($conversation),
            'AI conversation created successfully.',
            201
        );
    }

    /**
     * Get an AI conversation with chronological message history.
     */
    public function show(AiConversation $conversation): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('view', $conversation)) {
            return $this->error([], 'Forbidden. You do not have permission to view this conversation.', 403);
        }

        $detail = $this->aiService->getConversation($conversation);

        return $this->success(
            new AiConversationResource($detail),
            'AI conversation retrieved successfully.'
        );
    }

    /**
     * Delete an AI conversation.
     */
    public function destroy(AiConversation $conversation): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('delete', $conversation)) {
            return $this->error([], 'Forbidden. You do not have permission to delete this conversation.', 403);
        }

        $this->aiService->deleteConversation($conversation);

        return $this->success([], 'AI conversation deleted successfully.');
    }

    /**
     * Send user prompt to conversation and receive contextual AI response.
     */
    public function sendMessage(SendAiMessageRequest $request, AiConversation $conversation): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('sendMessage', $conversation)) {
            return $this->error([], 'Forbidden. You do not have permission to post in this conversation.', 403);
        }

        $assistantMessage = $this->aiService->sendMessage($user, $conversation, $request->validated('content'));

        return $this->success(
            new AiMessageResource($assistantMessage),
            'AI response generated successfully.',
            201
        );
    }

    /**
     * Submit feedback rating (like or dislike) for an AI response.
     */
    public function feedback(FeedbackAiMessageRequest $request, AiMessage $message): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('feedback', $message)) {
            return $this->error([], 'Forbidden. You do not have permission to rate this message.', 403);
        }

        $updated = $this->aiService->submitFeedback($message, $request->validated('feedback'));

        return $this->success(
            new AiMessageResource($updated),
            'Feedback submitted successfully.'
        );
    }
}
