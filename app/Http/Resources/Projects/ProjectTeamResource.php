<?php

namespace App\Http\Resources\Projects;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTeamResource extends JsonResource
{
    /**
     * Transform the resource into an array for project team overview.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $project = $this->additional['project'] ?? null;
        $projectTasks = $project && $project->relationLoaded('tasks')
            ? $project->tasks
            : ($project ? $project->tasks()->with('assignees')->get() : collect());

        $teamTasks = $projectTasks->where('team_id', $this->id);
        $totalTeamTasks = $teamTasks->count();
        $completedTeamTasks = $teamTasks->filter(fn ($t) => $t->isCompleted())->count();

        $completionPercentage = $totalTeamTasks > 0
            ? round(($completedTeamTasks / $totalTeamTasks) * 100)
            : 0;

        return [
            'team_id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'completion_percentage' => $completionPercentage,
            'completion_formatted' => "{$completionPercentage}% Completed",
            'leader' => $this->leader ? [
                'id' => $this->leader->id,
                'name' => $this->leader->name,
                'avatar_url' => $this->leader->avatar_url,
                'job_title' => $this->leader->job_title,
                'role' => 'Leader',
            ] : null,
            'members' => $this->members->map(function ($member) use ($projectTasks) {
                $userTasks = $projectTasks->filter(fn ($t) => $t->assignees->contains('id', $member->id));
                $totalUserTasks = $userTasks->count();
                $completedUserTasks = $userTasks->filter(fn ($t) => $t->isCompleted())->count();

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'avatar_url' => $member->avatar_url,
                    'job_title' => $member->job_title,
                    'role_in_team' => $member->pivot?->role_in_team ?? 'member',
                    'task_fraction' => "{$completedUserTasks}/{$totalUserTasks}",
                    'completed_tasks_count' => $completedUserTasks,
                    'total_tasks_count' => $totalUserTasks,
                ];
            }),
        ];
    }
}
