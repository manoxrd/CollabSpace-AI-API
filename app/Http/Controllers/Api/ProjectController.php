<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\AssignMemberRequest;
use App\Http\Requests\Projects\AssignTeamsRequest;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Requests\Projects\UploadProjectFileRequest;
use App\Http\Resources\Projects\ProjectFileResource;
use App\Http\Resources\Projects\ProjectListResource;
use App\Http\Resources\Projects\ProjectOverviewResource;
use App\Http\Resources\Projects\ProjectTeamResource;
use App\Models\Project;
use App\Services\ProjectService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProjectService $projectService
    ) {}

    /**
     * List all projects with search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'priority', 'category', 'sort_by', 'sort_order']);
        $perPage = (int) $request->query('per_page', 15);

        $projects = $this->projectService->listProjects($filters, $perPage);

        return $this->success([
            'projects' => ProjectListResource::collection($projects),
            'pagination' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ],
        ], 'Projects retrieved successfully.');
    }

    /**
     * Create a new project.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('create', Project::class)) {
            return $this->error([], 'Forbidden. Only project managers or administrators can create projects.', 403);
        }

        $project = $this->projectService->createProject($request->validated(), $user);

        return $this->success(
            new ProjectListResource($project),
            'Project created successfully.',
            201
        );
    }

    /**
     * Show single project details.
     */
    public function show(Project $project): JsonResponse
    {
        $project = $this->projectService->getProject($project);

        return $this->success(
            new ProjectOverviewResource($project),
            'Project retrieved successfully.'
        );
    }

    /**
     * Update project attributes.
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('update', $project)) {
            return $this->error([], 'Forbidden. You do not have permission to update this project.', 403);
        }

        $updated = $this->projectService->updateProject($project, $request->validated());

        return $this->success(
            new ProjectListResource($updated),
            'Project updated successfully.'
        );
    }

    /**
     * Delete project (soft delete).
     */
    public function destroy(Project $project): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('delete', $project)) {
            return $this->error([], 'Forbidden. Only administrators can delete projects.', 403);
        }

        $this->projectService->deleteProject($project);

        return $this->success(null, 'Project deleted successfully.');
    }

    /**
     * Project Overview sub-tab details.
     */
    public function overview(Project $project): JsonResponse
    {
        $overview = $this->projectService->getProjectOverview($project);

        return $this->success(
            new ProjectOverviewResource($overview),
            'Project overview retrieved successfully.'
        );
    }

    /**
     * Project Teams sub-tab details.
     */
    public function teams(Project $project): JsonResponse
    {
        $teams = $this->projectService->getProjectTeams($project);

        return $this->success([
            'teams' => ProjectTeamResource::collection($teams)->additional(['project' => $project]),
        ], 'Project teams retrieved successfully.');
    }

    /**
     * Assign teams to project.
     */
    public function assignTeams(AssignTeamsRequest $request, Project $project): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('manageTeams', $project)) {
            return $this->error([], 'Forbidden. You do not have permission to assign teams to this project.', 403);
        }

        $updated = $this->projectService->assignTeams($project, $request->validated()['team_ids']);

        return $this->success(
            new ProjectListResource($updated),
            'Teams assigned to project successfully.'
        );
    }

    /**
     * Assign member to project.
     */
    public function assignMember(AssignMemberRequest $request, Project $project): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('manageMembers', $project)) {
            return $this->error([], 'Forbidden. You do not have permission to manage members for this project.', 403);
        }

        $validated = $request->validated();
        $updated = $this->projectService->assignMember(
            $project,
            $validated['user_id'],
            $validated['role'] ?? 'contributor'
        );

        return $this->success(
            new ProjectListResource($updated),
            'Member assigned to project successfully.'
        );
    }

    /**
     * Remove member from project.
     */
    public function removeMember(Project $project, int $userId): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('manageMembers', $project)) {
            return $this->error([], 'Forbidden. You do not have permission to manage members for this project.', 403);
        }

        $updated = $this->projectService->removeMember($project, $userId);

        return $this->success(
            new ProjectListResource($updated),
            'Member removed from project successfully.'
        );
    }

    /**
     * Project Files sub-tab list and upload info.
     */
    public function files(Request $request, Project $project): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $filesData = $this->projectService->getProjectFiles($project, $perPage);

        return $this->success([
            'recent_files' => ProjectFileResource::collection($filesData['recent_files']),
            'all_files' => ProjectFileResource::collection($filesData['all_files']),
            'pagination' => [
                'current_page' => $filesData['all_files']->currentPage(),
                'last_page' => $filesData['all_files']->lastPage(),
                'per_page' => $filesData['all_files']->perPage(),
                'total' => $filesData['all_files']->total(),
            ],
        ], 'Project files retrieved successfully.');
    }

    /**
     * Upload a file to project.
     */
    public function uploadFile(UploadProjectFileRequest $request, Project $project): JsonResponse
    {
        $user = auth('api')->user();
        $file = $this->projectService->uploadProjectFile(
            $project,
            $request->file('file'),
            $user,
            $request->input('folder_id'),
            $request->input('task_id')
        );

        return $this->success(
            new ProjectFileResource($file),
            'File uploaded successfully.',
            201
        );
    }
}
