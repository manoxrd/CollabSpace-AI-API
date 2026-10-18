<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UrgentTaskResource extends JsonResource
{
    /**
     * Transform the task into an array for dashboard urgent tasks.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'priority' => $this->priority?->value ?? $this->priority,
            'status' => $this->status?->value ?? $this->status,
            'progress_percentage' => (float) $this->progress_percentage,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'due_date_formatted' => $this->due_date_formatted ?? 'No due date',
            'is_due_today' => $this->is_due_today,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ]),
            'assigned_members' => $this->whenLoaded('assignees', fn () => $this->assignees->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar_url' => $u->avatar_url,
            ])),
        ];
    }
}
