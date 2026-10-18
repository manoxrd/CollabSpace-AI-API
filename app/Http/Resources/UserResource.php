<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Single unified resource for all user and profile responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value ?? $this->role,
            'job_title' => $this->job_title,
            'years_of_experience' => $this->years_of_experience,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'availability_status' => $this->availability_status?->value ?? $this->availability_status,
            'about' => $this->about,
            'hours_worked' => $this->hours_worked,
            'current_team_project' => $this->current_team_project,
            'status' => $this->status,
            'joined_at' => $this->joined_at?->format('M Y'), // e.g. "Apr 2022"
            'stats' => [
                'completed_tasks_count' => $this->completed_assigned_tasks_count,
                'total_tasks_count' => $this->assigned_tasks_count,
                'completion_rate' => $this->task_completion_rate,
                'hours_worked' => $this->hours_worked,
            ],
            'recent_activities' => $this->recent_activities->map(fn ($act) => [
                'id' => $act->id,
                'activity_type' => $act->activity_type,
                'description' => $act->description,
                'created_at' => $act->created_at?->toISOString(),
                'time_ago' => $act->created_at?->diffForHumans(),
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
