<?php

namespace App\Http\Controllers\API\V1\Dashboard\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Resources\Notification\AllNotificationResource;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Models\Setting\Param\Param;
use App\Models\User;
class NotificationController extends Controller implements HasMiddleware
{
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
        $notifications = DB::connection('tenant')->table('notifications')
        ->where('notifiable_id', $user->id) 
        ->whereNull('read_at')
        ->orderByDesc('id')
        ->cursorPaginate(10);
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
       $notifications= DB::connection('tenant')->table('notifications')->where('notifiable_id',$user->id)
       ->orderByDesc('id')
       ->whereNull('read_at')->cursorPaginate();
        if ($notifications) {
                return ApiResponse::success(new AllNotificationResource($notifications));
        }else{
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }
    }
    public function auth_read_notifications()
    {
        $user=auth()->user();
        $unreadNotifications = DB::connection('tenant')->table('notifications')
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at');

        if($unreadNotifications->count() > 0){
            $unreadNotifications->update(['read_at' => now()]);
            return ApiResponse::success([],'All Notification marked as read successfully!');
        }else {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }
    }
    public function auth_read_notification($id)
    {
        $notification = DB::connection('tenant')->table('notifications')
            ->where('id', $id)
            ->where('notifiable_id', auth()->id())
            ->first();
        if (isset($notification)) {
            if ($notification->read_at != null) {
                return ApiResponse::success([],'notification has been marked as read already');
            }
            DB::connection('tenant')->table('notifications')
                ->where('id', $id)
                ->where('notifiable_id', auth()->id())
                ->update(['read_at' => now()]); 
            $notificationfind = DB::connection('tenant')->table('notifications')->where('id', $id)->first();
            return ApiResponse::success(new NotificationResource($notificationfind));
        } else {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }
    }
    public function auth_delete_notifications()
    {
       $user = auth()->user();
       $notifications = DB::connection('tenant')->table('notifications')
           ->where('notifiable_id', $user->id);

       if($notifications->count() > 0){
        $notifications->delete();
       }else {
        return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
       }
       return ApiResponse::success([],'delete notifications');
    }
    public function auth_delete_notification($id)
    {
        $notification = DB::connection('tenant')->table('notifications')
            ->where('id', $id)
            ->where('notifiable_id', auth()->id())
            ->first();
        if (isset($notification)) {
            DB::connection('tenant')->table('notifications')
                ->where('id', $id)
                ->where('notifiable_id', auth()->id())
                ->delete();
            return ApiResponse::success([],__('crud.deleted'));
        } else {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }
    }
}
