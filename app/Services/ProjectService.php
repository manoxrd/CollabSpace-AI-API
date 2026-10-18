<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Models\File;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public const DEFAULT_RELATIONS = ['teams', 'members', 'files', 'tasks'];

    /**
     * List projects with search and filters.
     */
    public function listProjects(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::query()
            ->with(self::DEFAULT_RELATIONS)
            ->withCount(['files', 'tasks']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority']) && $filters['priority'] !== 'all') {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['name', 'start_date', 'deadline', 'priority', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new project.
     */
    public function createProject(array $data, User $creator): Project
    {
        return DB::transaction(function () use ($data, $creator) {
            $project = Project::create([
                'name' => $data['name'],
                'category' => $data['category'] ?? 'SaaS',
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'deadline' => $data['deadline'] ?? null,
                'priority' => $data['priority'] ?? Priority::Medium->value,
                'status' => $data['status'] ?? ProjectStatus::Active->value,
                'progress_percentage' => 0.00,
                'created_by' => $creator->id,
            ]);

            // Always add creator as manager
            $project->members()->attach($creator->id, [
                'role' => 'manager',
                'joined_at' => now(),
            ]);

            // Attach assigned teams & their members
            if (! empty($data['team_ids'])) {
                $this->syncTeamMembers($project, $data['team_ids']);
            }

            // Attach explicit members if specified
            if (! empty($data['member_ids'])) {
                $existingMemberIds = $project->members()->pluck('users.id')->all();
                foreach ($data['member_ids'] as $memberId) {
                    if (! in_array($memberId, $existingMemberIds)) {
                        $project->members()->attach($memberId, [
                            'role' => 'contributor',
                            'joined_at' => now(),
                        ]);
                    }
                }
            }

            // Log activity
            UserActivity::create([
                'user_id' => $creator->id,
                'project_id' => $project->id,
                'activity_type' => 'created_project',
                'subject_type' => Project::class,
                'subject_id' => $project->id,
                'description' => "Created project {$project->name}",
                'created_at' => now(),
            ]);

            return $project->load(self::DEFAULT_RELATIONS);
        });
    }

    /**
     * Get project with all relationships.
     */
    public function getProject(Project $project): Project
    {
        return $project->load(self::DEFAULT_RELATIONS);
    }

    /**
     * Update project.
     */
    public function updateProject(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            $project->update(array_filter([
                'name' => $data['name'] ?? null,
                'category' => $data['category'] ?? null,
                'description' => array_key_exists('description', $data) ? $data['description'] : null,
                'start_date' => array_key_exists('start_date', $data) ? $data['start_date'] : null,
                'deadline' => array_key_exists('deadline', $data) ? $data['deadline'] : null,
                'priority' => $data['priority'] ?? null,
                'status' => $data['status'] ?? null,
                'progress_percentage' => $data['progress_percentage'] ?? null,
            ], fn ($v) => $v !== null));

            if (isset($data['team_ids'])) {
                $teamsData = [];
                foreach ($data['team_ids'] as $teamId) {
                    $teamsData[$teamId] = ['assigned_at' => now()];
                }
                $project->teams()->sync($teamsData);
            }

            if (isset($data['member_ids'])) {
                // Keep manager roles
                $existingRoles = $project->members()->pluck('role', 'user_id')->toArray();
                $syncData = [];
                foreach ($data['member_ids'] as $memberId) {
                    $syncData[$memberId] = [
                        'role' => $existingRoles[$memberId] ?? 'contributor',
                        'joined_at' => now(),
                    ];
                }
                $project->members()->sync($syncData);
            }

            return $project->fresh(self::DEFAULT_RELATIONS);
        });
    }

    /**
     * Soft delete project.
     */
    public function deleteProject(Project $project): bool
    {
        return $project->delete();
    }

    /**
     * Get Project Overview data.
     */
    public function getProjectOverview(Project $project): Project
    {
        return $project->load(['tasks', 'members', 'teams']);
    }

    /**
     * Get Project Teams data for teams overview sub-tab.
     */
    public function getProjectTeams(Project $project)
    {
        $project->loadMissing(['tasks.assignees']);

        // Load teams assigned to project or all department teams with project filter
        $teams = $project->teams()->with(['leader', 'members'])->get();

        if ($teams->isEmpty()) {
            // Fallback: If no teams assigned specifically, fetch standard department teams
            $teams = Team::with(['leader', 'members'])->get();
        }

        return $teams;
    }

    /**
     * Assign teams to project.
     */
    public function assignTeams(Project $project, array $teamIds): Project
    {
        $this->syncTeamMembers($project, $teamIds);

        return $project->fresh(['teams', 'members']);
    }

    /**
     * Assign a single member to project.
     */
    public function assignMember(Project $project, int $userId, string $role = 'contributor'): Project
    {
        $project->members()->syncWithoutDetaching([
            $userId => [
                'role' => $role,
                'joined_at' => now(),
            ],
        ]);

        return $project->fresh(['members']);
    }

    /**
     * Remove member from project.
     */
    public function removeMember(Project $project, int $userId): Project
    {
        $project->members()->detach($userId);

        return $project->fresh(['members']);
    }

    /**
     * Get Project Files (recent + all).
     */
    public function getProjectFiles(Project $project, int $perPage = 20): array
    {
        $allFiles = $project->files()
            ->with('uploader')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $recentFiles = $project->files()
            ->with('uploader')
            ->orderByDesc('created_at')
            ->take(6)
            ->get();

        return [
            'recent_files' => $recentFiles,
            'all_files' => $allFiles,
        ];
    }

    /**
     * Upload a file to the project.
     */
    public function uploadProjectFile(Project $project, UploadedFile $uploadedFile, User $uploader, ?int $folderId = null, ?int $taskId = null): File
    {
        $path = $uploadedFile->store("projects/{$project->id}/files", 'public');

        $file = File::create([
            'project_id' => $project->id,
            'folder_id' => $folderId,
            'task_id' => $taskId,
            'name' => $uploadedFile->getClientOriginalName(),
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $uploadedFile->getClientMimeType(),
            'size' => $uploadedFile->getSize(),
            'downloads_count' => 0,
            'uploaded_by' => $uploader->id,
        ]);

        UserActivity::create([
            'user_id' => $uploader->id,
            'project_id' => $project->id,
            'activity_type' => 'uploaded_file',
            'subject_type' => File::class,
            'subject_id' => $file->id,
            'description' => "Uploaded file {$file->name} to project {$project->name}",
            'created_at' => now(),
        ]);

        return $file->load('uploader');
    }

    /**
     * Attach teams and add their members as contributors to the project.
     *
     * @param  array<int>  $teamIds
     */
    protected function syncTeamMembers(Project $project, array $teamIds): void
    {
        $teamsData = [];
        foreach ($teamIds as $teamId) {
            $teamsData[$teamId] = ['assigned_at' => now()];
        }
        $project->teams()->syncWithoutDetaching($teamsData);

        $teamMemberIds = DB::table('team_user')
            ->whereIn('team_id', $teamIds)
            ->pluck('user_id')
            ->unique();

        $existingMemberIds = $project->members()->pluck('users.id')->all();

        foreach ($teamMemberIds as $memberId) {
            if (! in_array($memberId, $existingMemberIds)) {
                $project->members()->attach($memberId, [
                    'role' => 'contributor',
                    'joined_at' => now(),
                ]);
            }
        }
    }
}
