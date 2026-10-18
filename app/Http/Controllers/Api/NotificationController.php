<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * List paginated notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $unreadOnly = $request->boolean('unread', false);
        $perPage = (int) $request->input('per_page', 15);

        $notifications = $this->notificationService->getNotifications(
            $request->user(),
            $unreadOnly,
            $perPage
        );

        $unreadCount = $this->notificationService->getUnreadCount($request->user());

        return $this->success([
            'notifications' => NotificationResource::collection($notifications)->response()->getData(true),
            'unread_count' => $unreadCount,
        ], 'Notifications retrieved successfully.');
    }

    /**
     * Get the current user's unread notifications count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $unreadCount = $this->notificationService->getUnreadCount($request->user());

        return $this->success([
            'unread_count' => $unreadCount,
        ], 'Unread count retrieved successfully.');
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->notificationService->markAsRead($request->user(), $id);

        return $this->success(
            new NotificationResource($notification),
            'Notification marked as read.'
        );
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return $this->success([
            'marked_count' => $count,
        ], 'All notifications marked as read.');
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->notificationService->deleteNotification($request->user(), $id);

        return $this->success([], 'Notification deleted successfully.');
    }
}
