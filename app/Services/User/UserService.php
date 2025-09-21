<?php

namespace App\Services\User;

use App\Models\User;
use App\Enums\StatusEnum;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Filters\User\FilterUser;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use App\Filters\User\FilterUserRole;
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

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    public function allUsers()
    {
        $auth = Auth::user();
        /*$currentUserRole = $auth->getRoleNames()[0];*/
        $user = QueryBuilder::for(User::class)
           ->defaultSort('-created_at')
            ->allowedFilters([
                AllowedFilter::custom('search', new FilterUser()), // Add a custom search filter
                AllowedFilter::exact('isActive', 'is_active'),
                AllowedFilter::custom('role', new FilterUserRole()),
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
            $avatarPath =  $this->uploadService->uploadFile($userData['avatar'], 'avatars');
        }

        $user = User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => $userData['password'],
            'is_active' => isset($userData['isActive']) ? StatusEnum::from($userData['isActive'])->value : StatusEnum::ACTIVE,
            'avatar' => $avatarPath,
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

        if(isset($userData['avatar']) && $userData['avatar'] instanceof UploadedFile){
            $avatarPath =  $this->uploadService->uploadFile($userData['avatar'], 'avatars');
        }
        $user = User::find($userId);
        $user->name = $userData['name'];
        $user->email = $userData['email'];
        if(isset($userData['password'])){
            $user->password = $userData['password'];
        }
        $user->is_active = isset($userData['isActive']) ? StatusEnum::from($userData['isActive'])->value : StatusEnum::ACTIVE;
        if($avatarPath){
            if($user->avatar){
                Storage::disk('public')->delete($user->getRawOriginal('avatar'));
            }
            $user->avatar = $avatarPath;
        }
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
        if($user->avatar){
            Storage::disk('public')->delete($user->getRawOriginal('avatar'));
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
