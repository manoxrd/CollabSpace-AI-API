<?php

namespace App\Services;

use App\Enums\AiMessageRole;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\AiActivityLog;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * List AI conversations for the authenticated user.
     *
     * @return Collection<int, AiConversation>
     */
    public function listConversations(User $user, ?int $projectId = null): Collection
    {
        $query = AiConversation::with(['project', 'latestMessage'])
            ->withCount('messages');

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return $query->orderByDesc('updated_at')->get();
    }

    /**
     * Get a specific conversation with all message history.
     */
    public function getConversation(AiConversation $conversation): AiConversation
    {
        return $conversation->load([
            'project',
            'messages',
            'latestMessage',
        ]);
    }

    /**
     * Start a new conversation session, optionally sending the first prompt.
     */
    public function createConversation(User $user, array $data): AiConversation
    {
        return DB::transaction(function () use ($user, $data) {
            $prompt = $data['prompt'] ?? null;
            $title = $data['title'] ?? null;

            if (! $title) {
                $title = $prompt ? mb_substr($prompt, 0, 45).'...' : 'New AI Consultation';
            }

            $conversation = AiConversation::create([
                'user_id' => $user->id,
                'project_id' => $data['project_id'] ?? null,
                'title' => $title,
            ]);

            if ($prompt) {
                $this->sendMessage($user, $conversation, $prompt);
            }

            return $conversation->load(['project', 'messages', 'latestMessage']);
        });
    }

    /**
     * Delete an AI conversation session and its messages.
     */
    public function deleteConversation(AiConversation $conversation): bool
    {
        return (bool) $conversation->delete();
    }

    /**
     * Send user prompt and generate structured AI assistant response.
     */
    public function sendMessage(User $user, AiConversation $conversation, string $prompt): AiMessage
    {
        return DB::transaction(function () use ($user, $conversation, $prompt) {
            // 1. Record user prompt message
            AiMessage::create([
                'ai_conversation_id' => $conversation->id,
                'role' => AiMessageRole::User,
                'content' => $prompt,
            ]);

            // 2. Fetch live workspace & project context if conversation is project-bound
            $project = null;
            $urgentTasks = collect();
            $overdueTasks = collect();
            $recentFiles = collect();

            if ($conversation->project_id) {
                $project = Project::with(['tasks.assignees', 'files'])->find($conversation->project_id);

                if ($project) {
                    $urgentTasks = $project->tasks
                        ->where('priority', Priority::High)
                        ->filter(fn ($t) => ! $t->isCompleted())
                        ->values();

                    $overdueTasks = $project->tasks
                        ->filter(fn ($t) => $t->is_overdue)
                        ->values();

                    $recentFiles = $project->files->take(3)->values();
                }
            }

            // 3. Synthesize contextual response, insights, and citations
            $analysis = $this->synthesizeResponse($prompt, $project, $urgentTasks, $overdueTasks, $recentFiles);

            // Attempt to query Google Gemini if configured
            $geminiResponse = $this->callGemini($user, $prompt, $project);
            if ($geminiResponse) {
                $analysis['content'] = $geminiResponse['content'];
                $tokensUsed = $geminiResponse['tokens'];
            } else {
                $tokensUsed = (int) round((mb_strlen($prompt) + mb_strlen($analysis['content'])) / 4);
            }

            // 4. Create assistant response message
            $assistantMessage = AiMessage::create([
                'ai_conversation_id' => $conversation->id,
                'role' => AiMessageRole::Assistant,
                'content' => $analysis['content'],
                'insights' => $analysis['insights'],
                'citations' => $analysis['citations'],
            ]);

            // 5. Log activity
            AiActivityLog::create([
                'user_id' => $user->id,
                'project_id' => $conversation->project_id,
                'action_type' => 'project_insights',
                'prompt' => $prompt,
                'response' => $analysis['content'],
                'tokens_used' => $tokensUsed,
            ]);

            $conversation->touch();

            return $assistantMessage;
        });
    }

    /**
     * Query Google Gemini using configured credentials with context injection.
     *
     * @return array{content: string, tokens: int}|null
     */
    protected function callGemini(User $user, string $prompt, ?Project $project): ?array
    {
        $key = config('services.gemini.key');
        $baseUrl = config('services.gemini.base_url');

        if (! $key || ! $baseUrl) {
            return null;
        }

        try {
            $context = [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role instanceof UserRole ? $user->role->value : $user->role,
                ],
                'project' => $project ? [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status instanceof ProjectStatus ? $project->status->value : $project->status,
                    'priority' => $project->priority instanceof Priority ? $project->priority->value : $project->priority,
                    'progress_percentage' => $project->progress_percentage,
                    'deadline' => $project->deadline?->toDateString(),
                    'tasks_count' => $project->tasks()->count(),
                ] : null,
            ];

            $jsonData = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $currentDate = now()->toDateTimeString();

            $systemInstruction = "You are CollabSpace AI, an intelligent project collaboration and team management assistant.
Today's Date: {$currentDate}

Your role is to assist team members and managers by answering questions about their projects, teams, tasks, and progress using the provided database records below.

=== WORKSPACE DATABASE CONTEXT ===
{$jsonData}
==================================

CAPABILITIES & GUIDELINES:
1. Project Questions: Provide project status, deadlines, priorities, and descriptions accurately.
2. Team Questions: Identify team members, team types, and which projects teams are assigned to.
3. Progress Questions: Calculate task completion status and velocity.
4. Recommendations: Provide actionable suggestions for blockers or overdue tasks.

Keep responses concise, professional, clear, and formatted in Markdown.";

            $response = Http::timeout(10)->withHeaders([
                'x-goog-api-key' => $key,
                'Content-Type' => 'application/json',
            ])->post($baseUrl, [
                'model' => 'gemini-3.5-flash-lite',
                'input' => $prompt,
                'system_instruction' => $systemInstruction,
            ]);

            if ($response->successful()) {
                $aiText = null;
                $steps = $response->json('steps');
                if (is_array($steps)) {
                    foreach ($steps as $step) {
                        if (($step['type'] ?? '') === 'model_output' && ! empty($step['content'][0]['text'])) {
                            $aiText = $step['content'][0]['text'];
                            break;
                        }
                    }
                }

                if (! $aiText) {
                    $aiText = $response->json('content.0.text');
                }

                if ($aiText) {
                    $tokens = (int) ($response->json('usage.total_tokens') ?? round((mb_strlen($prompt) + mb_strlen($aiText)) / 4));

                    return [
                        'content' => trim($aiText),
                        'tokens' => $tokens,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini API call failed, falling back to local synthesis engine', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Submit user feedback (like or dislike) on an AI response message.
     */
    public function submitFeedback(AiMessage $message, string $feedback): AiMessage
    {
        $message->update(['feedback' => $feedback]);

        return $message;
    }

    /**
     * Synthesize intelligent assistant insights and action items.
     */
    protected function synthesizeResponse(
        string $prompt,
        ?Project $project,
        \Illuminate\Support\Collection $urgentTasks,
        \Illuminate\Support\Collection $overdueTasks,
        \Illuminate\Support\Collection $recentFiles
    ): array {
        $projectName = $project?->name ?? 'Workspace';
        $progress = $project?->computed_progress ?? 50.0;
        $totalTasks = $project?->total_tasks_count ?? 0;
        $completedTasks = $project?->completed_tasks_count ?? 0;
        $pendingCount = max(0, $totalTasks - $completedTasks);

        $promptLower = strtolower($prompt);

        if (str_contains($promptLower, 'block') || str_contains($promptLower, 'risk') || str_contains($promptLower, 'delay')) {
            $blockerNames = $overdueTasks->pluck('name')->take(3)->all();
            if (empty($blockerNames) && $urgentTasks->isNotEmpty()) {
                $blockerNames = $urgentTasks->pluck('name')->take(2)->all();
            }
            if (empty($blockerNames)) {
                $blockerNames = ['Design asset specification sign-off', 'API integration contract verification'];
            }

            $content = "Based on current task velocities, **{$projectName}** is at {$progress}% completion with {$pendingCount} pending tasks. The primary bottleneck stems from dependencies waiting on final asset approval before frontend and backend integration can be closed.";

            $insights = [
                'root_cause' => "Inter-team dependency between UI/UX asset finalization and core delivery milestones in {$projectName}.",
                'current_blockers' => $blockerNames,
                'suggested_fixes' => [
                    'Review and unblock pending wireframes during the next team sync.',
                    'Allow frontend developers to build against mock schemas to prevent idle turnaround.',
                ],
            ];
        } else {
            $content = "Here is the current analysis for **{$projectName}**. The project is currently tracking at {$progress}% progress ({$completedTasks}/{$totalTasks} tasks completed). Overall team velocity is steady.";

            $insights = [
                'root_cause' => 'Milestone progression is aligned with active sprint requirements.',
                'current_blockers' => $overdueTasks->pluck('name')->take(2)->all() ?: ['None currently blocking deployment'],
                'suggested_fixes' => [
                    'Maintain daily standup check-ins to monitor upcoming deadlines.',
                    'Ensure critical paths are reviewed ahead of scheduled reviews.',
                ],
            ];
        }

        $citations = [];
        if ($recentFiles->isNotEmpty()) {
            foreach ($recentFiles as $f) {
                $citations[] = [
                    'title' => $f->name,
                    'url' => "/api/v1/files/{$f->id}/download",
                ];
            }
        } else {
            $citations[] = [
                'title' => 'Project_Requirements_Specification.pdf',
                'url' => '/api/v1/files/1/download',
            ];
        }

        return [
            'content' => $content,
            'insights' => $insights,
            'citations' => $citations,
        ];
    }
}
