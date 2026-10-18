<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberOverviewResource extends JsonResource
{
    /**
     * Transform the member into an array for dashboard team overview.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $total = $this->assigned_tasks_count;
        $completed = $this->completed_assigned_tasks_count;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
            'job_title' => $this->job_title,
            'role' => $this->role?->value ?? $this->role,
            'is_leader' => (bool) ($this->is_leader ?? false),
            'task_fraction' => "{$completed}/{$total}",
            'completed_tasks_count' => $completed,
            'total_tasks_count' => $total,
        ];
    }
}
