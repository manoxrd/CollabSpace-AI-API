<?php

namespace App\Http\Resources\Projects;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectListResource extends JsonResource
{
    /**
     * Transform the resource into an array for project listings.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'start_date_formatted' => $this->start_date?->format('M d, Y'),
            'deadline' => $this->deadline?->format('Y-m-d'),
            'deadline_formatted' => $this->deadline?->format('M d, Y'),
            'status' => $this->status?->value ?? $this->status,
            'priority' => $this->priority?->value ?? $this->priority,
            'progress_percentage' => $this->computed_progress,
            'files_count' => $this->files_count,
            'files_count_formatted' => "{$this->files_count} Files",
            'total_tasks_count' => $this->total_tasks_count,
            'completed_tasks_count' => $this->completed_tasks_count,
            'teams' => $this->teams->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
            ]),
            'members' => $this->members->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
                'role' => $user->pivot?->role ?? 'contributor',
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
