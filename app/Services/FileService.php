<?php

namespace App\Services;

use App\Models\File;
use App\Models\Folder;
use App\Models\Project;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileService
{
    public const DEFAULT_FILE_RELATIONS = ['uploader', 'project', 'folder', 'task'];

    public const DEFAULT_FOLDER_RELATIONS = ['children', 'files.uploader', 'creator', 'project'];

    /**
     * Get single file with relations.
     */
    public function getFile(File $file): File
    {
        return $file->load(self::DEFAULT_FILE_RELATIONS);
    }

    /**
     * Download a file and increment download counter.
     */
    public function downloadFile(File $file): StreamedResponse
    {
        $disk = $file->disk ?? 'public';

        if (! Storage::disk($disk)->exists($file->path)) {
            abort(404, 'The requested file does not exist on disk.');
        }

        $file->increment('downloads_count');

        return Storage::disk($disk)->download($file->path, $file->name);
    }

    /**
     * Delete file from disk and database.
     */
    public function deleteFile(File $file): bool
    {
        $disk = $file->disk ?? 'public';

        if ($file->path && Storage::disk($disk)->exists($file->path)) {
            Storage::disk($disk)->delete($file->path);
        }

        return (bool) $file->delete();
    }

    /**
     * Create a folder within a project.
     */
    public function createFolder(Project $project, User $user, array $data): Folder
    {
        $folder = Folder::create([
            'project_id' => $project->id,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'created_by' => $user->id,
        ]);

        UserActivity::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'activity_type' => 'created_folder',
            'subject_type' => Folder::class,
            'subject_id' => $folder->id,
            'description' => "Created folder {$folder->name} in project {$project->name}",
            'created_at' => now(),
        ]);

        return $folder->load(self::DEFAULT_FOLDER_RELATIONS);
    }

    /**
     * Get folder with its contents.
     */
    public function getFolder(Folder $folder): Folder
    {
        return $folder->load(self::DEFAULT_FOLDER_RELATIONS);
    }

    /**
     * Delete a folder and its contained files.
     */
    public function deleteFolder(Folder $folder): bool
    {
        // Delete all files inside this folder from disk
        foreach ($folder->files as $file) {
            $disk = $file->disk ?? 'public';
            if ($file->path && Storage::disk($disk)->exists($file->path)) {
                Storage::disk($disk)->delete($file->path);
            }
            $file->delete();
        }

        return (bool) $folder->delete();
    }
}
