<?php

namespace App\Services\User;

use App\Models\User;
use App\Enums\StatusEnum;
use App\Models\Media\Media;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Filters\User\FilterUser;
use Database\Seeders\MediaSeeder;
use Illuminate\Http\UploadedFile;
use App\Enums\Media\MediaTypeEnum;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Filters\User\FilterUserRole;
use App\Services\Media\MediaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\QueryBuilder;
use App\Services\Upload\UploadService;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\Storage;
use App\Enums\ResponseCode\HttpStatusCode;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserService{
    protected $uploadService;
    protected $mediaService;
    public function __construct(UploadService $uploadService , MediaService $mediaService)
    {
        $this->uploadService = $uploadService;
        $this->mediaService = $mediaService;
    }

    public function allUsers(Request $request)
    {
        $auth = Auth::user();
        $perPage= $request->query('perPage',10);
        /*$currentUserRole = $auth->getRoleNames()[0];*/
        $user = QueryBuilder::for(User::class)
           ->defaultSort('-created_at')
            ->allowedFilters([
                AllowedFilter::custom('search', new FilterUser()), // Add a custom search filter
                AllowedFilter::exact('isActive', 'is_active'),
                // AllowedFilter::custom('role', new FilterUserRole()),
            ])
            ->whereNot('id', $auth->id)
            ->where('user_id',$auth->id)
            ->whereNull('database_name')
            ->orderBy('created_at', 'desc')
            ->cursorPaginate($perPage);

        return $user;

    }

    public function createUser(array $userData): User
    {
        $superAdmin =Auth::user();
        // User::whereHas('roles', function ($query) {
        //     $query->where('name', 'super admin');
        // })->first();
            $mediaId = null;
            if (isset($userData['mediaFile']) && $userData['mediaFile'] instanceof UploadedFile) {
                $media = $this->mediaService->createMedia([
                    'path' => $userData['mediaFile'],
                    'type' => MediaTypeEnum::PHOTO,
                    'category'=>null,
                ]);
                $mediaId = $media->id;
            }
            elseif (isset($userData['mediaId'])) {
                $mediaId = $userData['mediaId'];
            }
            $finalEmail = $userData['email'] . $superAdmin->app_key;
            if (User::where('email', $finalEmail)->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['This email is already taken.'],
                ]);
            }
        $user = User::create([
            'name' => $userData['name'],
            'email' => $userData['email'].$superAdmin->app_key ,
            'password' => $userData['password'],
            'is_active' => isset($userData['isActive']) ? StatusEnum::from($userData['isActive'])->value : StatusEnum::ACTIVE,
            'media_id' => $mediaId,
            'user_id'=>$superAdmin->user_id ?? $superAdmin->id,
        ]);

        $role = Role::find($userData['roleId']);
        $user->assignRole($role->id);

        return $user;

    }

    public function editUser(int $userId)
    {
        $user= User::with('roles')->find($userId);
        if(!$user){
          throw new ModelNotFoundException();
        }
        return $user;
    }

    public function updateUser(int $userId, array $userData)
    {
        $superAdmin =Auth::user();
        //  User::whereHas('roles', function ($query) {
        //     $query->where('name', 'super admin');
        // })->first();
        $user = User::find($userId);
        //  $finalEmail = $userData['email'] . $superAdmin->app_key;
        //     if (User::where('email', $finalEmail)->exists()) {
        //         throw ValidationException::withMessages([
        //             'email' => ['This email is already taken.'],
        //         ]);
        //     }
        $oldMediaId = $user->media_id;
        $mediaId = $oldMediaId;
        if (!empty($userData['mediaFile']) && $userData['mediaFile'] instanceof UploadedFile) {
                $media = $this->mediaService->createMedia([
                    'path' => $userData['mediaFile'],
                    'type' => MediaTypeEnum::PHOTO,
                    'category'=>null,
                ]);
                $mediaId = $media->id;
                if ($oldMediaId) {
                $this->mediaService->deleteMedia($oldMediaId);
                }
        }
        elseif (!empty($userData['mediaId']) && $userData['mediaId'] != $oldMediaId) {
                $mediaId = $userData['mediaId'];
                if ($oldMediaId) {
                    $this->mediaService->deleteMedia($oldMediaId);
                }
        }else{
            $mediaId = $oldMediaId;
        }
        $user->name = $userData['name'];
        $user->email = $userData['email'].$superAdmin->app_key;
        if(isset($userData['password'])){
            $user->password = $userData['password'];
        }
        $user->is_active = isset($userData['isActive']) ? StatusEnum::from($userData['isActive'])->value : StatusEnum::ACTIVE;

        if(isset($userData['password'])){
            $user->password = $userData['password'];
        }
        $user->media_id =$mediaId;
        $user->user_id = $superAdmin->user_id ?? $superAdmin->id;
        $user->save();
        $role = Role::find($userData['roleId']);
        $user->syncRoles($role->id);

        return $user;

    }


    public function deleteUser(int $userId)
    {
        $user = User::find($userId);
        if(!$user){
          throw new ModelNotFoundException();
        }
        if ($user->media_id) {
            $media = Media::on('tenant')->find($user->media_id);
            if ($media) {
                $this->mediaService->deleteMedia($media->id);
            }
        }
        $user->delete();

    }

    public function changeUserStatus(int $userId, int $isActive): void
    {
        User::where('id', $userId)->update(['is_active' => StatusEnum::from($isActive)->value]);
    }
    public function changePassword(array $data){
        $user = user::where('email',$data['email'])->first();
        if(!$user){
            throw new ModelNotFoundException();
        }
       if (!Hash::check($data['currentPassword'], $user->password)) {
        throw ValidationException::withMessages([
            'currentPassword' => __('passwords.current_password_error'),
        ]);
        }
        // Update password securely
        $user->update([
            'password' => Hash::make($data['password']),
        ]);
        $user->tokens()->delete();
        return $user;
    }


}
