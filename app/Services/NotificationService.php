<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

class NotificationService
{
    /**
     * Get paginated notifications for the specified user.
     */
    public function getNotifications(User $user, bool $unreadOnly = false, int $perPage = 15): LengthAwarePaginator
    {
        $query = $user->notifications()->latest();

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get the count of unread notifications for the user.
     */
    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(User $user, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return $notification;
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead(User $user): int
    {
        $unread = $user->unreadNotifications;
        $count = $unread->count();
        $unread->markAsRead();

        return $count;
    }

    /**
     * Delete a notification.
     */
    public function deleteNotification(User $user, string $id): void
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $notification->delete();
    }
}
