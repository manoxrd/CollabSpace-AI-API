<?php

namespace App\Http\Resources\Meetings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiMeetingSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'summary' => $this->summary,
            'key_points' => $this->key_points,
            'decisions_made' => $this->decisions_made,
            'action_items' => $this->action_items,
            'assigned_tasks' => $this->assigned_tasks,
            'risks' => $this->risks,
            'next_steps' => $this->next_steps,
            'generated_at' => $this->generated_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
