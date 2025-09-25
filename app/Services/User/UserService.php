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

    public function allUsers()
    {
        $auth = Auth::user();
        /*$currentUserRole = $auth->getRoleNames()[0];*/
        $user = QueryBuilder::for(User::class)
           ->defaultSort('-created_at')
            ->allowedFilters([
                AllowedFilter::custom('search', new FilterUser()), // Add a custom search filter
                // AllowedFilter::exact('isActive', 'is_active'),
                // AllowedFilter::custom('role', new FilterUserRole()),
            ])
            ->whereNot('id', $auth->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $user;

    }

    public function createUser(array $userData): User
    {
        $avatarPath = null;
        if(isset($userData['avatar']) && $userData['avatar'] instanceof UploadedFile){
            $media = $this->mediaService->createMedia([
                'path'     => $userData['avatar'],
                'type'     => MediaTypeEnum::PHOTO->value,
                'category' => null,
            ]);
        }

        $user = User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => $userData['password'],
            'is_active' => isset($userData['isActive']) ? StatusEnum::from($userData['isActive'])->value : StatusEnum::ACTIVE,
            'media_id' =>$media?->id
        ]);

        $role = Role::find($userData['roleId']);
        $user->assignRole($role->id);

        return $user;

    }

    public function editUser(int $userId)
    {
        $user= User::with('roles')->findOrFail($userId);
        if(!$user){
          throw new ModelNotFoundException();
        }
        return $user;
    }

    public function updateUser(int $userId, array $userData)
    {

        $avatarPath = null;
        $user = User::find($userId);
        if(isset($userData['avatar']) && $userData['avatar'] instanceof UploadedFile){
            if ($user->media_id) {
                $media = $this->mediaService->updateMedia($user->media_id, [
                    'path'     => $userData['avatar'],
                    'type'     => MediaTypeEnum::PHOTO->value,
                    'category' => null,
                ]);
            } else {
                $media = $this->mediaService->createMedia([
                    'path'     => $userData['avatar'],
                    'type'     => MediaTypeEnum::PHOTO->value,
                    'category' => null,
                ]);
            }
        }
        $user->name = $userData['name'];
        $user->email = $userData['email'];
        if(isset($userData['password'])){
            $user->password = $userData['password'];
        }
        $user->is_active = isset($userData['isActive']) ? StatusEnum::from($userData['isActive'])->value : StatusEnum::ACTIVE;
        if($avatarPath){
            if(isset($user->media)){
                Storage::disk('public')->delete($user->media->path);
                $user->media->delete();

                $media =Media::find($user->media->id);
                $media->path =$avatarPath;
                $media->save();
            }
            $media = Media::create([
                'path' => $avatarPath,
                'type' => MediaTypeEnum::PHOTO->value,
                'category' => null,
            ]);
        }

        if(isset($userData['password'])){
            $user->password = $userData['password'];
        }
        $user->media_id =$media->id ??null;
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
         $this->mediaService->deleteMedia($user->media->id);
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
