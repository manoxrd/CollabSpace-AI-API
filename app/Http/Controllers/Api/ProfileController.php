<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\FileResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\UserResource;
use App\Services\ProfileService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProfileService $profileService
    ) {}

    /**
     * Get the authenticated user's profile and stats.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user('api') ?? auth('api')->user();

        return $this->success([
            'user' => new UserResource($user),
        ], 'Profile retrieved successfully.');
    }

    /**
     * Update the authenticated user's profile and settings.
     * Updates personal info, email, phone, availability, job title, current team/project, and experience.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user('api') ?? auth('api')->user();

        $updated = $this->profileService->updateProfile(
            $user,
            $request->validated(),
            $request->file('avatar')
        );

        return $this->success([
            'user' => new UserResource($updated),
        ], 'Profile updated successfully.');
    }

    /**
     * Get the authenticated user's tasks grouped by status.
     */
    public function tasks(Request $request): JsonResponse
    {
        $user = $request->user('api') ?? auth('api')->user();
        $tasks = $this->profileService->getUserTasks($user);

        return $this->success([
            'todo' => TaskResource::collection($tasks['todo']),
            'in_progress' => TaskResource::collection($tasks['in_progress']),
            'done' => TaskResource::collection($tasks['done']),
        ], 'User tasks retrieved successfully.');
    }

    /**
     * Get the files uploaded by the authenticated user.
     */
    public function files(Request $request): JsonResponse
    {
        $user = $request->user('api') ?? auth('api')->user();
        $files = $this->profileService->getUserFiles($user);

        return $this->success([
            'files' => FileResource::collection($files),
        ], 'User files retrieved successfully.');
    }

    /**
     * Get user activities history.
     */
    public function activities(Request $request): JsonResponse
    {
        $user = $request->user('api') ?? auth('api')->user();
        $activities = $this->profileService->getUserActivities($user, (int) $request->query('per_page', 15));

        return $this->success($activities, 'User activities retrieved successfully.');
    }
}
