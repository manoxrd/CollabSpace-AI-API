<?php

namespace App\Policies;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can view any tasks.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create tasks.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, Task $task): bool
    {
        if ($user->isManagerOfProject($task->project_id)) {
            return true;
        }

        if ($task->created_by === $user->id) {
            return true;
        }

        return $task->assignees()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): bool
    {
        if ($user->isManagerOfProject($task->project_id)) {
            return true;
        }

        return $task->created_by === $user->id;
    }

    /**
     * Determine whether the user can assign/remove reviewers.
     */
    public function assignReviewer(User $user, Task $task): bool
    {
        return $user->isManagerOfProject($task->project_id);
    }

    /**
     * Determine whether the user can sync task assignees.
     */
    public function assignMembers(User $user, Task $task): bool
    {
        if ($user->isManagerOfProject($task->project_id)) {
            return true;
        }

        return $task->created_by === $user->id;
    }

    /**
     * Determine whether the user can change task status.
     * Guard: If a task has a designated reviewer and is moving to Completed,
     * only the assigned reviewer, a project manager, or admin can approve completion.
     */
    public function changeStatus(User $user, Task $task, string $newStatus): bool
    {
        if ($newStatus === TaskStatus::Completed->value && $task->reviewer_id) {
            return $task->reviewer_id === $user->id || $user->isManagerOfProject($task->project_id);
        }

        if ($user->isManagerOfProject($task->project_id)) {
            return true;
        }

        if ($task->created_by === $user->id) {
            return true;
        }

        return $task->assignees()->where('users.id', $user->id)->exists();
    }
}
