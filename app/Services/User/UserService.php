<?php

namespace App\Services\User;

use App\Enums\Media\MediaTypeEnum;
use App\Enums\StatusEnum;
use App\Filters\User\FilterUser;
use App\Filters\User\FilterUserRole;
use App\Models\Media\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Services\Upload\UploadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserService
{
    protected $uploadService;

    protected $mediaService;

    protected $roleAssignmentService;

    public function __construct(
        UploadService $uploadService,
        MediaService $mediaService,
        RoleAssignmentService $roleAssignmentService
    ) {
        $this->uploadService = $uploadService;
        $this->mediaService = $mediaService;
        $this->roleAssignmentService = $roleAssignmentService;
    }

    public function allUsers(Request $request)
    {
        $auth = Auth::user();
        $perPage = $request->query('perPage', 10);
        /* $currentUserRole = $auth->getRoleNames()[0]; */
        $user = QueryBuilder::for(User::query()->managedBy($auth))
            ->defaultSort('-created_at')
            ->allowedFilters([
                AllowedFilter::custom('search', new FilterUser), // Add a custom search filter
                AllowedFilter::exact('isActive', 'is_active'),
                // AllowedFilter::custom('role', new FilterUserRole()),
            ])
            ->whereNot('id', $auth->id)
            // ->whereNull('database_name')
            ->orderBy('created_at', 'desc')
            ->cursorPaginate($perPage);

        return $user;

    }

    public function createUser(array $userData): User
    {
        return DB::transaction(function () use ($userData) {
            $superAdmin = Auth::user();
            $role = $this->roleAssignmentService->findAssignableRole($superAdmin, $userData['roleId'] ?? null);
            // User::whereHas('roles', function ($query) {
            //     $query->where('name', 'super admin');
            // })->first();
            $mediaId = null;
            if (isset($userData['mediaFile']) && $userData['mediaFile'] instanceof UploadedFile) {
                $media = $this->mediaService->createMedia([
                    'path' => $userData['mediaFile'],
                    'type' => MediaTypeEnum::PHOTO,
                    'category' => null,
                ]);
                $mediaId = $media->id;
            } elseif (isset($userData['mediaId'])) {
                $mediaId = $userData['mediaId'];
            }
            $finalEmail = $userData['email'].$superAdmin->app_key;
            if (User::where('email', $finalEmail)->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['هذا الايميل موجود من قبل '],
                ]);
            }
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'].$superAdmin->app_key,
                'password' => $userData['password'],
                'is_active' => isset($userData['isActive']) ? StatusEnum::from($userData['isActive'])->value : StatusEnum::ACTIVE,
                'media_id' => $mediaId,
                'user_id' => $superAdmin->user_id ?? $superAdmin->id,
                'database_name' => $superAdmin->database_name ?? null,
                'database_username' => $superAdmin->database_username ?? null,
                'database_password' => $superAdmin->database_password ?? null,
            ]);

            $user->assignRole($role);

            return $user;
        });

    }

    public function editUser(int $userId)
    {
        return $this->managedUsersQuery()
            ->with('roles')
            ->findOrFail($userId);
    }

    public function updateUser(int $userId, array $userData)
    {
        return DB::transaction(function () use ($userId, $userData) {
            $superAdmin = Auth::user();
            //  User::whereHas('roles', function ($query) {
            //     $query->where('name', 'super admin');
            // })->first();
            $user = $this->managedUsersQuery()->findOrFail($userId);
            $role = $this->roleAssignmentService->findAssignableRole($superAdmin, $userData['roleId'] ?? null);
            //  $finalEmail = $userData['email'] . $superAdmin->app_key;
            //     if (User::where('email', $finalEmail)->exists()) {
            //         throw ValidationException::withMessages([
            //             'email' => ['This email is already taken.'],
            //         ]);
            //     }
            $oldMediaId = $user->media_id;
            $mediaId = $oldMediaId;
            if (! empty($userData['mediaFile']) && $userData['mediaFile'] instanceof UploadedFile) {
                $media = $this->mediaService->createMedia([
                    'path' => $userData['mediaFile'],
                    'type' => MediaTypeEnum::PHOTO,
                    'category' => null,
                ]);
                $mediaId = $media->id;
                if ($oldMediaId) {
                    $this->mediaService->deleteMedia($oldMediaId);
                }
            } elseif (! empty($userData['mediaId']) && $userData['mediaId'] != $oldMediaId) {
                $mediaId = $userData['mediaId'];
                if ($oldMediaId) {
                    $this->mediaService->deleteMedia($oldMediaId);
                }
            } else {
                $mediaId = $oldMediaId;
            }
            $user->name = $userData['name'];
            $user->email = $userData['email'].$superAdmin->app_key;
            if (isset($userData['password'])) {
                $user->password = $userData['password'];
            }
            $user->is_active = isset($userData['isActive']) ? StatusEnum::from($userData['isActive'])->value : StatusEnum::ACTIVE;

            if (isset($userData['password'])) {
                $user->password = $userData['password'];
            }
            $user->media_id = $mediaId;
            $user->user_id = $superAdmin->user_id ?? $superAdmin->id;
            $user->save();
            $user->syncRoles($role);

            return $user;
        });

    }

    public function deleteUser(int $userId)
    {
        return DB::transaction(function () use ($userId) {
            $user = $this->managedUsersQuery()->findOrFail($userId);
            // if ($user->media_id) {
            //     $media = Media::on('tenant')->find($user->media_id);
            //     if ($media) {
            //         $this->mediaService->deleteMedia($media->id);
            //     }
            // }
            $user->delete();

            return $user;
        });

    }

    public function restoreUser(int $userId)
    {
        $user = $this->managedUsersQuery()
            ->onlyTrashed()
            ->findOrFail($userId);
        $user->restore();

        return $user;
    }

    public function forceDeleteUser(int $userId)
    {
        $user = $this->managedUsersQuery()
            ->withTrashed()
            ->findOrFail($userId);
        if ($user->media_id) {
            $media = Media::on('tenant')->find($user->media_id);
            if ($media) {
                $this->mediaService->deleteMedia($media->id);
            }
        }
        $user->forceDelete();
    }

    public function changeUserStatus(int $userId, int $isActive): void
    {
        DB::transaction(function () use ($userId, $isActive): void {
            $user = $this->managedUsersQuery()->findOrFail($userId);
            $user->update(['is_active' => StatusEnum::from($isActive)->value]);
        });
    }

    public function changePassword(array $data)
    {
        $user = $this->managedUsersQuery()
            ->where('email', $data['email'])
            ->first();
        if (! $user) {
            throw new ModelNotFoundException;
        }
        if (! Hash::check($data['currentPassword'], $user->password)) {
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

    private function managedUsersQuery(): Builder
    {
        return User::query()->managedBy(Auth::user());
    }
}
