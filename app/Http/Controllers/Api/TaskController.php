<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\AssignTaskReviewerRequest;
use App\Http\Requests\Tasks\StoreTaskCommentRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\SyncTaskAssigneesRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Http\Resources\Tasks\KanbanBoardResource;
use App\Http\Resources\Tasks\TaskDetailResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TaskService $taskService
    ) {}

    /**
     * List tasks (Global Tasks Page - supports list or kanban format).
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $filters = $request->only(['search', 'project_id', 'team_id', 'priority', 'status', 'assignee_id', 'my_tasks', 'format']);
        $perPage = (int) $request->query('per_page', 20);

        $result = $this->taskService->listTasks($filters, $user, $perPage);

        if (($filters['format'] ?? null) === 'kanban') {
            return $this->success(
                new KanbanBoardResource($result),
                'Kanban tasks retrieved successfully.'
            );
        }

        return $this->success([
            'tasks' => TaskResource::collection($result),
            'pagination' => [
                'current_page' => $result->currentPage(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ], 'Tasks retrieved successfully.');
    }

    /**
     * Get 4-column Kanban board for a specific project.
     */
    public function projectKanban(Project $project): JsonResponse
    {
        $tasks = $this->taskService->getProjectKanban($project);

        return $this->success(
            new KanbanBoardResource($tasks),
            'Project Kanban board retrieved successfully.'
        );
    }

    /**
     * Create a new task.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $task = $this->taskService->createTask($request->validated(), $user);

        return $this->success(
            new TaskResource($task),
            'Task created successfully.',
            201
        );
    }

    /**
     * Get full task details with comments, files, and activity history.
     */
    public function show(Task $task): JsonResponse
    {
        $task = $this->taskService->getTask($task);

        return $this->success(
            new TaskDetailResource($task),
            'Task details retrieved successfully.'
        );
    }

    /**
     * Update task attributes.
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('update', $task)) {
            return $this->error([], 'Forbidden. You do not have permission to update this task.', 403);
        }

        $updated = $this->taskService->updateTask($task, $request->validated(), $user);

        return $this->success(
            new TaskResource($updated),
            'Task updated successfully.'
        );
    }

    /**
     * Instant drag-and-drop status update.
     */
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): JsonResponse
    {
        $user = auth('api')->user();
        $status = $request->validated()['status'];

        if (! Gate::forUser($user)->allows('changeStatus', [$task, $status])) {
            return $this->error([], 'Forbidden. Only the designated reviewer or a project manager can approve this task as completed.', 403);
        }

        $updated = $this->taskService->updateStatus($task, $status, $user);

        return $this->success(
            new TaskResource($updated),
            'Task status updated successfully.'
        );
    }

    /**
     * Sync task assignees.
     */
    public function syncAssignees(SyncTaskAssigneesRequest $request, Task $task): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('assignMembers', $task)) {
            return $this->error([], 'Forbidden. You do not have permission to assign members to this task.', 403);
        }

        $updated = $this->taskService->syncAssignees($task, $request->validated()['assignee_ids'], $user);

        return $this->success(
            new TaskResource($updated),
            'Task assignees synced successfully.'
        );
    }

    /**
     * Assign or remove task reviewer (must be a project manager).
     */
    public function assignReviewer(AssignTaskReviewerRequest $request, Task $task): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('assignReviewer', $task)) {
            return $this->error([], 'Forbidden. You do not have permission to assign a reviewer to this task.', 403);
        }

        $updated = $this->taskService->assignReviewer($task, $request->validated()['reviewer_id'] ?? null, $user);

        return $this->success(
            new TaskResource($updated),
            'Task reviewer updated successfully.'
        );
    }

    /**
     * Add comment to task.
     */
    public function addComment(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        $user = auth('api')->user();
        $comment = $this->taskService->addComment($task, $request->validated()['comment'], $user);

        return $this->success([
            'id' => $comment->id,
            'comment' => $comment->comment,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
            ],
            'created_at' => $comment->created_at?->toISOString(),
            'time_ago' => $comment->created_at?->diffForHumans(),
        ], 'Comment added successfully.', 201);
    }

    /**
     * Soft delete task.
     */
    public function destroy(Task $task): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('delete', $task)) {
            return $this->error([], 'Forbidden. You do not have permission to delete this task.', 403);
        }

        $this->taskService->deleteTask($task);

        return $this->success(null, 'Task deleted successfully.');
    }
}
