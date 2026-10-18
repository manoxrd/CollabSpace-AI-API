<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any projects.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        return $user->isProjectManager();
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->isManagerOfProject($project->id);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->isAdmin() || ($user->isProjectManager() && $project->created_by === $user->id);
    }

    /**
     * Determine whether the user can assign/manage teams for the project.
     */
    public function manageTeams(User $user, Project $project): bool
    {
        return $user->isManagerOfProject($project->id);
    }

    /**
     * Determine whether the user can assign/manage members for the project.
     */
    public function manageMembers(User $user, Project $project): bool
    {
        return $user->isManagerOfProject($project->id);
    }

    /**
     * Determine whether the user can upload files to the project.
     */
    public function uploadFile(User $user, Project $project): bool
    {
        return true;
    }
}
