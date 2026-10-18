<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;

class FolderPolicy
{
    /**
     * Determine whether the user can view the folder.
     */
    public function view(User $user, Folder $folder): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create folders.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the folder.
     */
    public function delete(User $user, Folder $folder): bool
    {
        return $folder->created_by === $user->id
            || $user->isAdmin()
            || $user->isManagerOfProject($folder->project_id);
    }
}
