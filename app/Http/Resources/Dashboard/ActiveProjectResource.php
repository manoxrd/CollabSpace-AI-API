<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActiveProjectResource extends JsonResource
{
    /**
     * Transform the active project into an array for dashboard presentation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'status' => $this->status?->value ?? $this->status,
            'priority' => $this->priority?->value ?? $this->priority,
            'task_fraction' => "{$this->completed_tasks_count}/{$this->total_tasks_count}",
            'completed_tasks_count' => $this->completed_tasks_count,
            'total_tasks_count' => $this->total_tasks_count,
            'progress_percentage' => $this->computed_progress,
            'deadline' => $this->deadline?->format('Y-m-d'),
        ];
    }
}
