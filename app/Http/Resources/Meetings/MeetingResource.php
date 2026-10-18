<?php

namespace App\Http\Resources\Meetings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authUserId = auth('api')->id();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'date' => $this->date?->format('Y-m-d'),
            'date_formatted' => $this->date_formatted,
            'start_time' => $this->formatted_start_time,
            'end_time' => $this->formatted_end_time,
            'time_slot' => $this->time_slot,
            'meeting_link' => $this->meeting_link,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'project' => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null,
            'creator' => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'avatar_url' => $this->creator->avatar_url,
                'job_title' => $this->creator->job_title,
            ] : null,
            'my_rsvp_status' => $authUserId ? $this->getParticipantStatus($authUserId) : null,
            'participants_preview' => $this->participants_preview,
            'extra_participants_count' => $this->extra_participants_count,
            'total_participants_count' => $this->total_participants_count,
            'participants' => $this->participants->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'email' => $p->email,
                'avatar_url' => $p->avatar_url,
                'job_title' => $p->job_title,
                'status' => $p->pivot?->status,
            ]),
            'transcripts_count' => $this->transcripts_count,
            'has_ai_summary' => $this->has_ai_summary,
            'ai_summary' => new AiMeetingSummaryResource($this->whenLoaded('aiSummary')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
