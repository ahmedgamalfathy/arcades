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
            new Middleware('auth:api'),
            new Middleware('permission:notifications', only:['notifications']),
            new Middleware('permission:auth_unread_notifications', only:['auth_unread_notifications']),
            new Middleware('permission:auth_read_notifications', only:['auth_read_notifications']),
            new Middleware('permission:auth_read_notification', only:['auth_read_notification']),
            new Middleware('permission:auth_delete_notifications', only:['auth_delete_notifications']),
            new Middleware('permission:auth_delete_notification', only:['auth_delete_notification']),
            new Middleware('tenant'),
        ];
    }
    public function notifications()
    {
        $user=auth('api')->user();
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
       $user=auth('api')->user();
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
        $user=auth('api')->user();
        if(count($user->unreadNotifications)>0){
            $user->unreadNotifications->markAsRead();
            return ApiResponse::success([],'All Notification marked as read successfully!');
        }else {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }
    }
    public function auth_read_notification($id)
    {
        $notification = DB::table('notifications')->where('id', $id)->first();
        if (isset($notification)) {
            if ($notification->read_at != null) {
                return ApiResponse::success([],'notification has been marked as read already');
            }
            DB::table('notifications')
                ->where('id', $id)
                ->update(['read_at' => now()]); 
            $notificationfind = DB::table('notifications')->where('id', $id)->first();
            return ApiResponse::success(new NotificationResource($notificationfind));
        } else {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }
    }
    public function auth_delete_notifications()
    {
       $user=User::find(auth('api')->user()->id);
       if(count($user->notifications)>0){
        $user->notifications()->delete();
       }else {
        return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
       }
       return ApiResponse::success([],'delete notifications');
    }
    public function auth_delete_notification($id)
    {
        $notification = DB::table('notifications')->where('id', $id)->first();
        if (isset($notification)) {
            $notification->delete();
            return ApiResponse::success([],__('crud.deleted'));
        } else {
            return ApiResponse::error(__('crud.not_found'),[],HttpStatusCode::NOT_FOUND);
        }
    }
}
