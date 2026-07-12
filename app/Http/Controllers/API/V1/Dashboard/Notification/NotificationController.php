<?php

namespace App\Http\Controllers\API\V1\Dashboard\Notification;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Resources\Notification\AllNotificationResource;
use App\Http\Resources\Notification\NotificationResource;
use App\Services\Notification\NotificationService;

class NotificationController extends Controller implements HasMiddleware
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:notifications', only:['notifications']),
            new Middleware('permission:auth_unread_notifications', only:['auth_unread_notifications']),
            new Middleware('permission:auth_read_notifications', only:['auth_read_notifications']),
            new Middleware('permission:auth_read_notification', only:['auth_read_notification']),
            new Middleware('permission:auth_delete_notifications', only:['auth_delete_notifications']),
            new Middleware('permission:auth_delete_notification', only:['auth_delete_notification']),
        ];
    }
    public function notifications()
    {
        $user=auth()->user();
        $notifications = $this->notificationService->unreadForUser($user->id);

        if($notifications){
           return ApiResponse::success(new AllNotificationResource($notifications));
        }else
        {
                return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }
    }
    public function auth_unread_notifications()
    {
       $user=auth()->user();
       $notifications= $this->notificationService->allUnreadForUser($user->id);

        if ($notifications) {
                return ApiResponse::success(new AllNotificationResource($notifications));
        }else{
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }
    }
    public function auth_read_notifications()
    {
        $user=auth()->user();

        if($this->notificationService->markAllAsReadForUser($user->id)){
            return ApiResponse::success([],'All Notification marked as read successfully!');
        }else {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }
    }
    public function auth_read_notification($id)
    {
        $result = $this->notificationService->markAsReadForUser(auth()->id(), $id);

        if ($result['status'] === 'not_found') {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }

        if ($result['status'] === 'already_read') {
            return ApiResponse::success([],'notification has been marked as read already');
        }

        return ApiResponse::success(new NotificationResource($result['notification']));
    }

    public function auth_delete_notifications()
    {
       $user = auth()->user();

       if($this->notificationService->deleteAllForUser($user->id)){
           return ApiResponse::success([],'delete notifications');
       }else {
           return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
       }
    }

    public function auth_delete_notification($id)
    {
        if ($this->notificationService->deleteForUser(auth()->id(), $id)) {
            return ApiResponse::success([],__('crud.deleted'));
        } else {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }
    }
}
