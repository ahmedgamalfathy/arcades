<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function unreadForUser(int $userId, int $perPage = 10)
    {
        return DB::connection('tenant')->table('notifications')
            ->where('notifiable_id', $userId)
            ->whereNull('read_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function allUnreadForUser(int $userId)
    {
        return DB::connection('tenant')->table('notifications')
            ->where('notifiable_id', $userId)
            ->whereNull('read_at')
            ->orderByDesc('id')
            ->cursorPaginate();
    }

    public function markAllAsReadForUser(int $userId): bool
    {
        $query = DB::connection('tenant')->table('notifications')
            ->where('notifiable_id', $userId)
            ->whereNull('read_at');

        if ($query->count() === 0) {
            return false;
        }

        $query->update(['read_at' => now()]);

        return true;
    }

    public function markAsReadForUser(int $userId, int|string $notificationId): array
    {
        $notification = DB::connection('tenant')->table('notifications')
            ->where('id', $notificationId)
            ->where('notifiable_id', $userId)
            ->first();

        if (!$notification) {
            return ['status' => 'not_found', 'notification' => null];
        }

        if ($notification->read_at !== null) {
            return ['status' => 'already_read', 'notification' => $notification];
        }

        DB::connection('tenant')->table('notifications')
            ->where('id', $notificationId)
            ->where('notifiable_id', $userId)
            ->update(['read_at' => now()]);

        $notification = DB::connection('tenant')->table('notifications')
            ->where('id', $notificationId)
            ->first();

        return ['status' => 'read', 'notification' => $notification];
    }

    public function deleteAllForUser(int $userId): bool
    {
        $query = DB::connection('tenant')->table('notifications')
            ->where('notifiable_id', $userId);

        if ($query->count() === 0) {
            return false;
        }

        $query->delete();

        return true;
    }

    public function deleteForUser(int $userId, int|string $notificationId): bool
    {
        $notification = DB::connection('tenant')->table('notifications')
            ->where('id', $notificationId)
            ->where('notifiable_id', $userId)
            ->first();

        if (!$notification) {
            return false;
        }

        DB::connection('tenant')->table('notifications')
            ->where('id', $notificationId)
            ->where('notifiable_id', $userId)
            ->delete();

        return true;
    }
}
