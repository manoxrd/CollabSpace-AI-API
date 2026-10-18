<?php

namespace App\Services;

use App\Enums\MeetingParticipantStatus;
use App\Enums\MeetingStatus;
use App\Events\MeetingTranscriptAdded;
use App\Models\AiMeetingSummary;
use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MeetingService
{
    public const DEFAULT_RELATIONS = [
        'project',
        'creator',
        'participants',
        'aiSummary',
    ];

    public function __construct(
        protected GoogleMeetService $googleMeetService = new GoogleMeetService
    ) {}

    /**
     * Generate an authentic Google Meet space room link.
     *
     * @return array{meeting_link: string, meeting_code: string, provider: string}
     */
    public function createGoogleMeetSpace(string $title = 'CollabSpace Meeting'): array
    {
        return $this->googleMeetService->createMeetingSpace($title);
    }

    /**
     * Run diagnostic check on Google Meet API integration.
     *
     * @return array<string, mixed>
     */
    public function testGoogleMeetConnection(): array
    {
        return $this->googleMeetService->testConnection();
    }

    /**
     * List meetings matching calendar and user filters.
     *
     * @return Collection<int, Meeting>
     */
    public function listMeetings(User $user, array $filters = []): Collection
    {
        $query = Meeting::with(self::DEFAULT_RELATIONS)
            ->withCount('transcripts');

        // Month filter (e.g. '2025-10' or separate month/year)
        if (! empty($filters['month'])) {
            try {
                $parsedDate = Carbon::parse($filters['month']);
                $query->whereYear('date', $parsedDate->year)
                    ->whereMonth('date', $parsedDate->month);
            } catch (\Throwable $e) {
                // If invalid date format, ignore month filter
            }
        } elseif (! empty($filters['year'])) {
            $query->whereYear('date', (int) $filters['year']);
        }

        // Project filter
        if (! empty($filters['project_id'])) {
            $query->where('project_id', (int) $filters['project_id']);
        }

        // Status filter
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $statusVal = $filters['status'] instanceof MeetingStatus ? $filters['status']->value : $filters['status'];
            $query->where('status', $statusVal);
        }

        // My meetings filter
        if (! empty($filters['my_meetings']) && filter_var($filters['my_meetings'], FILTER_VALIDATE_BOOLEAN)) {
            $query->forUser($user->id);
        }

        // Non-admin visibility check: only show public project meetings or meetings where user is creator/participant/project member
        if (! $user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('participants', fn ($sub) => $sub->where('users.id', $user->id))
                    ->orWhereHas('project.members', fn ($sub) => $sub->where('users.id', $user->id));
            });
        }

        return $query->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
    }

    /**
     * Get meeting detail with all relationships.
     */
    public function getMeeting(Meeting $meeting): Meeting
    {
        return $meeting->load([
            'project',
            'creator',
            'participants',
            'aiSummary',
            'transcripts.user',
        ]);
    }

    /**
     * Schedule a new meeting with invited participants.
     */
    public function createMeeting(User $creator, array $data): Meeting
    {
        return DB::transaction(function () use ($creator, $data) {
            $meetingLink = $data['meeting_link'] ?? null;
            if (empty($meetingLink)) {
                $meetSpace = $this->googleMeetService->createMeetingSpace($data['title']);
                $meetingLink = $meetSpace['meeting_link'];
            }

            $meeting = Meeting::create([
                'project_id' => $data['project_id'],
                'created_by' => $creator->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'meeting_link' => $meetingLink,
                'status' => MeetingStatus::Scheduled,
            ]);

            // Sync participants: creator is accepted, invited users are invited
            $pivotData = [
                $creator->id => ['status' => MeetingParticipantStatus::Accepted->value],
            ];

            if (! empty($data['participant_ids'])) {
                foreach ($data['participant_ids'] as $pId) {
                    if ((int) $pId !== $creator->id) {
                        $pivotData[(int) $pId] = ['status' => MeetingParticipantStatus::Invited->value];
                    }
                }
            }

            $meeting->participants()->sync($pivotData);

            return $meeting->load(self::DEFAULT_RELATIONS);
        });
    }

    /**
     * Update an existing meeting.
     */
    public function updateMeeting(Meeting $meeting, array $data): Meeting
    {
        return DB::transaction(function () use ($meeting, $data) {
            $meeting->update(array_filter([
                'title' => $data['title'] ?? null,
                'description' => array_key_exists('description', $data) ? $data['description'] : null,
                'date' => $data['date'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'meeting_link' => array_key_exists('meeting_link', $data) ? $data['meeting_link'] : null,
                'status' => $data['status'] ?? null,
            ], fn ($v) => ! is_null($v)));

            if (isset($data['participant_ids'])) {
                $existing = $meeting->participants()->pluck('meeting_user.status', 'users.id')->all();
                $pivotData = [];

                foreach ($data['participant_ids'] as $pId) {
                    $pId = (int) $pId;
                    $pivotData[$pId] = [
                        'status' => $existing[$pId] ?? MeetingParticipantStatus::Invited->value,
                    ];
                }

                // Preserve creator
                if (! isset($pivotData[$meeting->created_by])) {
                    $pivotData[$meeting->created_by] = [
                        'status' => $existing[$meeting->created_by] ?? MeetingParticipantStatus::Accepted->value,
                    ];
                }

                $meeting->participants()->sync($pivotData);
            }

            return $meeting->load(self::DEFAULT_RELATIONS);
        });
    }

    /**
     * Delete meeting.
     */
    public function deleteMeeting(Meeting $meeting): bool
    {
        return DB::transaction(function () use ($meeting) {
            $meeting->participants()->detach();
            $meeting->transcripts()->delete();
            $meeting->aiSummary()?->delete();

            return (bool) $meeting->delete();
        });
    }

    /**
     * RSVP to a meeting.
     */
    public function rsvp(User $user, Meeting $meeting, MeetingParticipantStatus|string $status): Meeting
    {
        $statusVal = $status instanceof MeetingParticipantStatus ? $status->value : $status;

        $meeting->participants()->syncWithoutDetaching([
            $user->id => ['status' => $statusVal],
        ]);

        return $meeting->load(self::DEFAULT_RELATIONS);
    }

    /**
     * Get meeting transcripts in chronological order.
     *
     * @return Collection<int, MeetingTranscript>
     */
    public function getTranscripts(Meeting $meeting): Collection
    {
        return $meeting->transcripts()
            ->with('user')
            ->orderBy('spoken_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Add a live transcript line and broadcast real-time event.
     */
    public function addTranscript(User $user, Meeting $meeting, array $data): MeetingTranscript
    {
        $spokenAt = isset($data['spoken_at']) ? Carbon::parse($data['spoken_at']) : now();
        $offset = $data['timestamp_offset'] ?? $spokenAt->format('H:i');

        $transcript = MeetingTranscript::create([
            'meeting_id' => $meeting->id,
            'user_id' => $user->id,
            'speaker_name' => $data['speaker_name'] ?? $user->name,
            'timestamp_offset' => $offset,
            'transcript_text' => $data['transcript_text'],
            'spoken_at' => $spokenAt,
        ]);

        // Auto-mark participant as attended if they spoke in meeting
        $meeting->participants()->syncWithoutDetaching([
            $user->id => ['status' => MeetingParticipantStatus::Attended->value],
        ]);

        // Broadcast real-time event over Reverb
        try {
            event(new MeetingTranscriptAdded($transcript));
        } catch (\Throwable $e) {
            // Ignore broadcast failure during test / offline
        }

        return $transcript->load('user');
    }

    /**
     * Generate or regenerate AI Summary for the meeting based on transcripts.
     */
    public function generateAiSummary(Meeting $meeting): AiMeetingSummary
    {
        $transcripts = $this->getTranscripts($meeting);
        $project = $meeting->project;

        // 1. Attempt to generate via Google Gemini
        $geminiSummary = $this->callGeminiMeetingSummary($meeting, $transcripts);
        if ($geminiSummary) {
            return AiMeetingSummary::updateOrCreate(
                ['meeting_id' => $meeting->id],
                [
                    'summary' => $geminiSummary['summary'],
                    'key_points' => array_values(array_slice($geminiSummary['key_points'] ?? [], 0, 5)),
                    'decisions_made' => array_values(array_slice($geminiSummary['decisions_made'] ?? [], 0, 4)),
                    'action_items' => array_values(array_slice($geminiSummary['action_items'] ?? [], 0, 4)),
                    'assigned_tasks' => array_values(array_slice($geminiSummary['assigned_tasks'] ?? [], 0, 4)),
                    'risks' => array_values(array_slice($geminiSummary['risks'] ?? [], 0, 3)),
                    'next_steps' => array_values(array_slice($geminiSummary['next_steps'] ?? [], 0, 4)),
                    'generated_at' => now(),
                ]
            );
        }

        // 2. Deterministic Fallback Synthesis
        $keyPoints = [];
        $decisionsMade = [];
        $actionItems = [];
        $assignedTasks = [];
        $risks = [];
        $nextSteps = [];

        if ($transcripts->isNotEmpty()) {
            $summaryText = "The team convened for '{$meeting->title}' regarding {$project?->name}. Several discussions occurred across {$transcripts->count()} dialogue segments.";

            foreach ($transcripts as $t) {
                $text = trim($t->transcript_text);
                $speaker = $t->speaker_name;

                if (str_contains(strtolower($text), 'agree') || str_contains(strtolower($text), 'approve') || str_contains(strtolower($text), 'final')) {
                    $decisionsMade[] = "Agreed with {$speaker}: \"{$text}\"";
                } elseif (str_contains(strtolower($text), 'risk') || str_contains(strtolower($text), 'delay') || str_contains(strtolower($text), 'blocker') || str_contains(strtolower($text), 'issue')) {
                    $risks[] = "Identified by {$speaker}: {$text}";
                } elseif (str_contains(strtolower($text), 'next') || str_contains(strtolower($text), 'step') || str_contains(strtolower($text), 'sync') || str_contains(strtolower($text), 'follow up')) {
                    $nextSteps[] = "{$speaker}: {$text}";
                } else {
                    $keyPoints[] = "{$speaker} noted: {$text}";
                }

                $actionItems[] = "Follow up on point raised by {$speaker}";
                $assignedTasks[] = "Action item for {$speaker}";
            }
        } else {
            $summaryText = "Overview session for '{$meeting->title}'. Objectives and milestones were aligned for project {$project?->name}.";
            $keyPoints[] = "Meeting scheduled for {$meeting->date_formatted} ({$meeting->time_slot}).";
            $keyPoints[] = "Scope defined as: {$meeting->description}";
            $decisionsMade[] = 'Confirmed project scope and schedule.';
            $actionItems[] = 'Attendees to prepare documentation before the next sync.';
            $nextSteps[] = 'Follow up with assigned team members.';
        }

        // Keep lists concise and distinct for summary presentation
        $keyPoints = array_values(array_unique(array_slice($keyPoints, 0, 5)));
        $decisionsMade = array_values(array_unique(array_slice($decisionsMade, 0, 4)));
        $actionItems = array_values(array_unique(array_slice($actionItems, 0, 4)));
        $assignedTasks = array_values(array_unique(array_slice($assignedTasks, 0, 4)));
        $risks = array_values(array_unique(array_slice($risks, 0, 3)));
        $nextSteps = array_values(array_unique(array_slice($nextSteps, 0, 4)));

        return AiMeetingSummary::updateOrCreate(
            ['meeting_id' => $meeting->id],
            [
                'summary' => $summaryText,
                'key_points' => $keyPoints,
                'decisions_made' => $decisionsMade,
                'action_items' => $actionItems,
                'assigned_tasks' => $assignedTasks,
                'risks' => $risks,
                'next_steps' => $nextSteps,
                'generated_at' => now(),
            ]
        );
    }

    /**
     * Synthesize meeting summary using Google Gemini.
     *
     * @param  Collection<int, MeetingTranscript>  $transcripts
     */
    protected function callGeminiMeetingSummary(Meeting $meeting, Collection $transcripts): ?array
    {
        $key = config('services.gemini.key');
        $baseUrl = config('services.gemini.base_url');

        if (! $key || ! $baseUrl || $transcripts->isEmpty()) {
            return null;
        }

        try {
            $transcriptText = $transcripts->map(fn ($t) => "[{$t->timestamp_offset}] {$t->speaker_name}: {$t->transcript_text}")->implode("\n");

            $systemInstruction = 'You are CollabSpace AI Meeting Assistant.
Analyze the provided meeting dialogue transcript.
Return ONLY valid raw JSON (no backticks, no code blocks, no explanation) with these keys:
{
  "summary": "Executive summary paragraph",
  "key_points": ["point 1", "point 2"],
  "decisions_made": ["decision 1"],
  "action_items": ["action 1"],
  "assigned_tasks": ["task 1"],
  "risks": ["risk 1"],
  "next_steps": ["step 1"]
}';

            $response = Http::timeout(12)->withHeaders([
                'x-goog-api-key' => $key,
                'Content-Type' => 'application/json',
            ])->post($baseUrl, [
                'model' => 'gemini-3.5-flash-lite',
                'input' => "Meeting: {$meeting->title}\n\nTranscript:\n{$transcriptText}",
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
                    if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $aiText, $matches)) {
                        $parsed = json_decode($matches[1], true);
                    } else {
                        $parsed = json_decode($aiText, true);
                    }

                    if (is_array($parsed) && ! empty($parsed['summary'])) {
                        return $parsed;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini Meeting Summary API call failed, falling back to local synthesis engine', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
