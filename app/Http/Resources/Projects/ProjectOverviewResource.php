<?php

namespace App\Http\Resources\Projects;

use App\Enums\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectOverviewResource extends JsonResource
{
    /**
     * Transform the resource into an array for project overview.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tasks = $this->tasks;
        $totalTasks = $tasks->count();

        $pendingTasksCount = $tasks->where('status', TaskStatus::Todo)->count();
        $inProgressTasksCount = $tasks->where('status', TaskStatus::InProgress)->count();
        $inReviewTasksCount = $tasks->where('status', TaskStatus::InReview)->count();
        $completedTasksCount = $tasks->filter(fn ($t) => $t->isCompleted())->count();

        $progress = $this->computed_progress;

        // Compute monthly velocity chart for this project (Jan - Dec)
        $months = [];
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $year = now()->year;

        foreach ($monthNames as $idx => $mName) {
            $mNum = $idx + 1;
            $completedInMonth = $tasks->filter(function ($t) use ($mNum, $year) {
                if (! $t->completed_at) {
                    return false;
                }

                return $t->completed_at->year == $year && $t->completed_at->month == $mNum;
            })->count();

            $createdInMonth = $tasks->filter(function ($t) use ($mNum, $year) {
                return $t->created_at->year == $year && $t->created_at->month == $mNum;
            })->count();

            $months[] = [
                'month' => $mName,
                'month_number' => $mNum,
                'completed_tasks' => $completedInMonth,
                'created_tasks' => $createdInMonth,
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category ?? 'SaaS',
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'priority' => $this->priority?->value ?? $this->priority,
            'progress_percentage' => $progress,
            'progress_formatted' => "{$progress}% Completed",
            'header' => [
                'name' => $this->name,
                'progress_percentage' => $progress,
                'progress_formatted' => "{$progress}% Completed",
                'start_date_formatted' => $this->start_date ? 'Start Date: '.$this->start_date->format('M d') : null,
                'due_date_formatted' => $this->deadline ? 'Due Date: '.$this->deadline->format('M d') : null,
            ],
            'product_details' => [
                'category' => $this->category ?? 'SaaS',
                'start_date' => $this->start_date?->format('Y-m-d'),
                'start_date_formatted' => $this->start_date?->format('M d'),
                'due_date' => $this->deadline?->format('Y-m-d'),
                'due_date_formatted' => $this->deadline?->format('M d'),
                'priority' => ucfirst($this->priority?->value ?? (string) $this->priority),
                'total_tasks' => $totalTasks,
            ],
            'project_progress' => [
                'progress_percentage' => $progress,
                'pending_tasks' => [
                    'count' => $pendingTasksCount,
                    'fraction' => "{$pendingTasksCount}/{$totalTasks}",
                ],
                'in_progress_tasks' => [
                    'count' => $inProgressTasksCount + $inReviewTasksCount,
                    'fraction' => ($inProgressTasksCount + $inReviewTasksCount)."/{$totalTasks}",
                ],
                'completed_tasks' => [
                    'count' => $completedTasksCount,
                    'fraction' => "{$completedTasksCount}/{$totalTasks}",
                ],
            ],
            'velocity_chart' => [
                'year' => $year,
                'months' => $months,
            ],
            'team_members' => $this->members->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'avatar_url' => $member->avatar_url,
                'role' => $member->pivot?->role ?? $member->job_title ?? 'Team Member',
                'job_title' => $member->job_title,
            ]),
        ];
    }
}
