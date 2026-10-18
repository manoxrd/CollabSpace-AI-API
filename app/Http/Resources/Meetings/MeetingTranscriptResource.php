<?php

namespace App\Http\Resources\Meetings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingTranscriptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'speaker_name' => $this->speaker_name,
            'timestamp_offset' => $this->timestamp_offset,
            'transcript_text' => $this->transcript_text,
            'spoken_at' => $this->spoken_at?->toISOString(),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->avatar_url,
                'job_title' => $this->user->job_title,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
