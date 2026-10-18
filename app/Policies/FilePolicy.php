<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;

class FilePolicy
{
    /**
     * Determine whether the user can view the file.
     */
    public function view(User $user, File $file): bool
    {
        return true;
    }

    /**
     * Determine whether the user can download the file.
     */
    public function download(User $user, File $file): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the file.
     */
    public function delete(User $user, File $file): bool
    {
        return $file->uploaded_by === $user->id
            || $user->isAdmin()
            || ($file->project_id && $user->isManagerOfProject($file->project_id));
    }
}
