<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public const DEFAULT_RELATIONS = [
        'project',
        'team',
        'creator',
        'reviewer',
        'assignees',
        'comments.user',
        'files',
    ];

    /**
     * List tasks with filters.
     */
    public function listTasks(array $filters = [], ?User $currentUser = null, int $perPage = 20): LengthAwarePaginator|Collection
    {
        $query = Task::query()
            ->with(self::DEFAULT_RELATIONS);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        if (! empty($filters['priority']) && $filters['priority'] !== 'all') {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['assignee_id'])) {
            $query->whereHas('assignees', fn ($q) => $q->where('users.id', $filters['assignee_id']));
        }

        if (! empty($filters['my_tasks']) && $currentUser) {
            $query->whereHas('assignees', fn ($q) => $q->where('users.id', $currentUser->id));
        }

        // Check if format is kanban (return all matching tasks as a Collection)
        if (($filters['format'] ?? null) === 'kanban') {
            return $query->orderBy('due_date', 'asc')->get();
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * Get tasks for project Kanban board.
     */
    public function getProjectKanban(Project $project): Collection
    {
        return $project->tasks()
            ->with(self::DEFAULT_RELATIONS)
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Create a new task.
     */
    public function createTask(array $data, User $creator): Task
    {
        return DB::transaction(function () use ($data, $creator) {
            $status = $data['status'] ?? TaskStatus::Todo->value;
            $progress = $data['progress_percentage'] ?? ($status === TaskStatus::Completed->value ? 100.00 : 0.00);

            $task = Task::create([
                'project_id' => $data['project_id'],
                'team_id' => $data['team_id'] ?? null,
                'created_by' => $creator->id,
                'reviewer_id' => $data['reviewer_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $status,
                'priority' => $data['priority'] ?? Priority::Medium->value,
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'completed_at' => $status === TaskStatus::Completed->value ? now() : null,
                'progress_percentage' => $progress,
            ]);

            if (! empty($data['assignee_ids'])) {
                $assignData = [];
                foreach ($data['assignee_ids'] as $uid) {
                    $assignData[$uid] = ['assigned_at' => now()];
                }
                $task->assignees()->attach($assignData);
            }

            // Log activity
            TaskActivity::create([
                'task_id' => $task->id,
                'user_id' => $creator->id,
                'type' => TaskActivityType::StatusChange->value,
                'comment' => "Created task with status {$status}",
                'old_status' => null,
                'new_status' => $status,
            ]);

            UserActivity::create([
                'user_id' => $creator->id,
                'project_id' => $task->project_id,
                'activity_type' => 'created_task',
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'description' => "Created task {$task->name}",
                'created_at' => now(),
            ]);

            $this->recalculateProjectProgress($task->project_id);

            return $task->load(self::DEFAULT_RELATIONS);
        });
    }

    /**
     * Get task details with all relations.
     */
    public function getTask(Task $task): Task
    {
        return $task->load([
            'project',
            'team',
            'creator',
            'reviewer',
            'assignees',
            'comments.user',
            'activities.user',
            'files.uploader',
        ]);
    }

    /**
     * Update task attributes.
     */
    public function updateTask(Task $task, array $data, User $user): Task
    {
        return DB::transaction(function () use ($task, $data, $user) {
            $newStatus = $data['status'] ?? null;
            if ($newStatus) {
                $this->applyStatusTransition($task, $newStatus, $user);
            }

            $updatePayload = array_filter([
                'project_id' => $data['project_id'] ?? null,
                'team_id' => array_key_exists('team_id', $data) ? $data['team_id'] : null,
                'name' => $data['name'] ?? null,
                'description' => array_key_exists('description', $data) ? $data['description'] : null,
                'priority' => $data['priority'] ?? null,
                'start_date' => array_key_exists('start_date', $data) ? $data['start_date'] : null,
                'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : null,
                'progress_percentage' => array_key_exists('progress_percentage', $data) ? $data['progress_percentage'] : null,
                'reviewer_id' => array_key_exists('reviewer_id', $data) ? $data['reviewer_id'] : null,
            ], fn ($v) => $v !== null);

            $task->update($updatePayload);

            if (isset($data['assignee_ids'])) {
                $assignData = [];
                foreach ($data['assignee_ids'] as $uid) {
                    $assignData[$uid] = ['assigned_at' => now()];
                }
                $task->assignees()->sync($assignData);
            }

            $this->recalculateProjectProgress($task->project_id);

            return $task->fresh(self::DEFAULT_RELATIONS);
        });
    }

    /**
     * Assign or remove task reviewer (must be a project manager).
     */
    public function assignReviewer(Task $task, ?int $reviewerId, User $user): Task
    {
        $task->reviewer_id = $reviewerId;
        $task->save();

        $newReviewer = $reviewerId ? User::find($reviewerId) : null;
        $comment = $newReviewer
            ? "Assigned {$newReviewer->name} as reviewer"
            : 'Removed task reviewer';

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => TaskActivityType::Comment->value,
            'comment' => $comment,
        ]);

        return $task->fresh(self::DEFAULT_RELATIONS);
    }

    /**
     * Drag-and-drop instant status update.
     */
    public function updateStatus(Task $task, string $newStatus, User $user): Task
    {
        return DB::transaction(function () use ($task, $newStatus, $user) {
            $this->applyStatusTransition($task, $newStatus, $user);

            return $task->fresh(self::DEFAULT_RELATIONS);
        });
    }

    /**
     * Centralized status transition handler.
     */
    private function applyStatusTransition(Task $task, string $newStatus, User $user): void
    {
        $oldStatus = $task->status?->value ?? $task->status;

        if ($oldStatus === $newStatus) {
            return;
        }

        $task->status = $newStatus;

        if ($newStatus === TaskStatus::Completed->value) {
            $task->completed_at = now();
            $task->progress_percentage = 100.00;

            UserActivity::create([
                'user_id' => $user->id,
                'project_id' => $task->project_id,
                'activity_type' => 'completed_task',
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'description' => "Completed task {$task->name}",
                'created_at' => now(),
            ]);
        } else {
            $task->completed_at = null;
            if ($newStatus === TaskStatus::Todo->value) {
                $task->progress_percentage = 0.00;
            }
        }

        $task->save();

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => TaskActivityType::StatusChange->value,
            'comment' => "Moved task from {$oldStatus} to {$newStatus}",
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        $this->recalculateProjectProgress($task->project_id);
    }

    /**
     * Sync task assignees.
     */
    public function syncAssignees(Task $task, array $assigneeIds, User $user): Task
    {
        $syncData = [];
        foreach ($assigneeIds as $uid) {
            $syncData[$uid] = ['assigned_at' => now()];
        }
        $task->assignees()->sync($syncData);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => TaskActivityType::Comment->value,
            'comment' => 'Updated task assignees',
        ]);

        return $task->fresh(['assignees']);
    }

    /**
     * Add comment to task.
     */
    public function addComment(Task $task, string $commentText, User $user): TaskComment
    {
        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'comment' => $commentText,
        ]);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => TaskActivityType::Comment->value,
            'comment' => $commentText,
        ]);

        return $comment->load('user');
    }

    /**
     * Delete task.
     */
    public function deleteTask(Task $task): bool
    {
        $projectId = $task->project_id;
        $deleted = $task->delete();

        $this->recalculateProjectProgress($projectId);

        return $deleted;
    }

    /**
     * Recalculate progress percentage for a project.
     */
    public function recalculateProjectProgress(int $projectId): void
    {
        Project::find($projectId)?->recalculateProgress();
    }
}
