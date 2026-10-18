<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    /**
     * Update the user's profile and account settings.
     */
    public function updateProfile(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        // 1. If a new avatar photo was uploaded, store it and assign the generated URL to the model column
        if ($avatar) {
            $user->avatar_url = $this->handleAvatarUpload($avatar, $user->avatar_url);
        }

        // 2. Fill the text fields coming from the request (name, phone, availability, etc.)
        $user->fill($data);
        $user->save();

        return $user->fresh();
    }

    /**
     * Handle avatar file upload and remove previous local file if it exists.
     */
    private function handleAvatarUpload(UploadedFile $file, ?string $oldAvatarUrl): string
    {
        if ($oldAvatarUrl && str_contains($oldAvatarUrl, '/storage/avatars/')) {
            $oldPath = str_replace('/storage/', '', parse_url($oldAvatarUrl, PHP_URL_PATH));
            Storage::disk('public')->delete($oldPath);
        }

        $path = $file->store('avatars', 'public');

        return Storage::disk('public')->url($path);
    }

    /**
     * Get user tasks grouped by status (todo, in_progress, done).
     *
     * @return array{todo: Collection, in_progress: Collection, done: Collection}
     */
    public function getUserTasks(User $user): array
    {
        $tasks = $user->assignedTasks()
            ->with('project')
            ->orderBy('due_date', 'asc')
            ->get();

        return [
            'todo' => $tasks->where('status.value', 'todo')->values(),
            'in_progress' => $tasks->where('status.value', 'in_progress')->values(),
            'done' => $tasks->where('status.value', 'done')->values(),
        ];
    }

    /**
     * Get files uploaded by the authenticated user.
     */
    public function getUserFiles(User $user): Collection
    {
        return $user->uploadedFiles()
            ->with('project')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get paginated user activities.
     */
    public function getUserActivities(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $user->activities()
            ->with('project')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
