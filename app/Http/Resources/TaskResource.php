<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'priority' => $this->priority?->value ?? $this->priority,
            'progress_percentage' => (float) ($this->progress_percentage ?? 0),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'due_date_formatted' => $this->due_date_formatted,
            'is_due_today' => $this->is_due_today,
            'is_overdue' => $this->is_overdue,
            'completed_at' => $this->completed_at?->toISOString(),
            'project' => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null,
            'team' => $this->team ? [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ] : null,
            'assignees' => $this->assignees->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
                'job_title' => $user->job_title,
            ]),
            'reviewer' => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
                'avatar_url' => $this->reviewer->avatar_url,
                'job_title' => $this->reviewer->job_title,
                'role' => $this->reviewer->role?->value ?? $this->reviewer->role,
            ] : null,
            'comments_count' => $this->comments_count,
            'files_count' => $this->files_count,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
