<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meetings\RsvpMeetingRequest;
use App\Http\Requests\Meetings\StoreMeetingRequest;
use App\Http\Requests\Meetings\StoreTranscriptRequest;
use App\Http\Requests\Meetings\UpdateMeetingRequest;
use App\Http\Resources\Meetings\AiMeetingSummaryResource;
use App\Http\Resources\Meetings\MeetingResource;
use App\Http\Resources\Meetings\MeetingTranscriptResource;
use App\Models\Meeting;
use App\Services\MeetingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MeetingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MeetingService $meetingService
    ) {}

    /**
     * Get meetings calendar list with month/project filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $filters = $request->only(['month', 'year', 'project_id', 'status', 'my_meetings']);

        $meetings = $this->meetingService->listMeetings($user, $filters);

        return $this->success(
            MeetingResource::collection($meetings),
            'Meetings retrieved successfully.'
        );
    }

    /**
     * Schedule a new meeting.
     */
    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('create', Meeting::class)) {
            return $this->error([], 'Forbidden. You do not have permission to schedule meetings.', 403);
        }

        $meeting = $this->meetingService->createMeeting($user, $request->validated());

        return $this->success(
            new MeetingResource($meeting),
            'Meeting scheduled successfully.',
            201
        );
    }

    /**
     * Provision a Google Meet space / video meeting room link.
     */
    public function generateGoogleMeetSpace(Request $request): JsonResponse
    {
        $title = $request->input('title', 'CollabSpace Meeting');
        $space = $this->meetingService->createGoogleMeetSpace($title);

        return $this->success($space, 'Google Meet space created successfully.');
    }

    /**
     * Diagnostic health check for Google Meet API integration.
     */
    public function googleMeetDiagnostics(): JsonResponse
    {
        $diagnostics = $this->meetingService->testGoogleMeetConnection();

        return $this->success($diagnostics, 'Google Meet diagnostics completed.');
    }

    /**
     * Get meeting details including project, attendees, transcripts and AI summary.
     */
    public function show(Meeting $meeting): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('view', $meeting)) {
            return $this->error([], 'Forbidden. You do not have permission to view this meeting.', 403);
        }

        $meetingDetail = $this->meetingService->getMeeting($meeting);

        return $this->success(
            new MeetingResource($meetingDetail),
            'Meeting details retrieved successfully.'
        );
    }

    /**
     * Update an existing meeting.
     */
    public function update(UpdateMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('update', $meeting)) {
            return $this->error([], 'Forbidden. You can only update meetings you created or manage.', 403);
        }

        $updated = $this->meetingService->updateMeeting($meeting, $request->validated());

        return $this->success(
            new MeetingResource($updated),
            'Meeting updated successfully.'
        );
    }

    /**
     * Delete/cancel a meeting.
     */
    public function destroy(Meeting $meeting): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('delete', $meeting)) {
            return $this->error([], 'Forbidden. You do not have permission to delete this meeting.', 403);
        }

        $this->meetingService->deleteMeeting($meeting);

        return $this->success([], 'Meeting deleted successfully.');
    }

    /**
     * RSVP to a meeting (accepted, declined, attended).
     */
    public function rsvp(RsvpMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('rsvp', $meeting)) {
            return $this->error([], 'Forbidden. You cannot RSVP to this meeting.', 403);
        }

        $updated = $this->meetingService->rsvp($user, $meeting, $request->validated('status'));

        return $this->success(
            new MeetingResource($updated),
            'RSVP status updated successfully.'
        );
    }

    /**
     * Get transcripts for a meeting.
     */
    public function transcripts(Meeting $meeting): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('view', $meeting)) {
            return $this->error([], 'Forbidden. You do not have permission to view transcripts for this meeting.', 403);
        }

        $transcripts = $this->meetingService->getTranscripts($meeting);

        return $this->success(
            MeetingTranscriptResource::collection($transcripts),
            'Meeting transcripts retrieved successfully.'
        );
    }

    /**
     * Add a live transcript line to a meeting.
     */
    public function addTranscript(StoreTranscriptRequest $request, Meeting $meeting): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('addTranscript', $meeting)) {
            return $this->error([], 'Forbidden. You do not have permission to add transcripts to this meeting.', 403);
        }

        $transcript = $this->meetingService->addTranscript($user, $meeting, $request->validated());

        return $this->success(
            new MeetingTranscriptResource($transcript),
            'Transcript line added successfully.',
            201
        );
    }

    /**
     * Get AI Summary for a meeting.
     */
    public function aiSummary(Meeting $meeting): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('view', $meeting)) {
            return $this->error([], 'Forbidden. You do not have permission to view this summary.', 403);
        }

        $summary = $meeting->aiSummary;

        if (! $summary) {
            return $this->error([], 'AI summary not found for this meeting.', 404);
        }

        return $this->success(
            new AiMeetingSummaryResource($summary),
            'AI meeting summary retrieved successfully.'
        );
    }

    /**
     * Generate or regenerate AI Summary for a meeting.
     */
    public function generateAiSummary(Meeting $meeting): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('generateAiSummary', $meeting)) {
            return $this->error([], 'Forbidden. You do not have permission to generate AI summaries for this meeting.', 403);
        }

        $summary = $this->meetingService->generateAiSummary($meeting);

        return $this->success(
            new AiMeetingSummaryResource($summary),
            'AI meeting summary generated successfully.'
        );
    }
}
