<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::with(['roles', 'warehouses']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request): void {
                $q->where('name', $request->input('role'));
            });
        }

        return UserResource::collection($query->latest()->paginate(15));
    }

    /**
     * Store a newly created user and assign role/warehouses.
     */
    public function store(UserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $role = $data['role'];
        $warehouseIds = $data['warehouse_ids'] ?? [];

        $user = DB::transaction(function () use ($data, $role, $warehouseIds): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => $data['is_active'] ?? true,
            ]);

            $user->assignRole($role);
            $user->warehouses()->sync($warehouseIds);

            return $user;
        });

        $user->load(['roles', 'warehouses']);

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): UserResource
    {
        $user->load(['roles', 'warehouses']);

        return new UserResource($user);
    }

    /**
     * Update the specified user.
     */
    public function update(UserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        $updatedUser = DB::transaction(function () use ($user, $data): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->load('roles');
            $willBeActive = (bool) ($data['is_active'] ?? $lockedUser->is_active);
            $newRole = $data['role'];

            $this->ensureAnActiveAdministratorRemains($lockedUser, $newRole, $willBeActive);

            $updateData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => $willBeActive,
            ];
            $passwordChanged = filled($data['password'] ?? null);
            if ($passwordChanged) {
                $updateData['password'] = Hash::make($data['password']);
            }

            $lockedUser->update($updateData);
            $lockedUser->syncRoles([$newRole]);

            if (array_key_exists('warehouse_ids', $data)) {
                $lockedUser->warehouses()->sync($data['warehouse_ids'] ?? []);
            }

            if (! $willBeActive || $passwordChanged) {
                $lockedUser->tokens()->delete();
            }

            return $lockedUser->refresh()->load(['roles', 'warehouses']);
        });

        return new UserResource($updatedUser);
    }

    /**
     * Deactivate user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->load('roles');
            $this->ensureAnActiveAdministratorRemains($lockedUser, $lockedUser->roles->first()?->name ?? '', false);
            $lockedUser->update(['is_active' => false]);
            $lockedUser->tokens()->delete();
        });

        if ((int) $request->user()->id === (int) $user->id) {
            $request->user()->tokens()->delete();
        }

        return response()->json([
            'message' => 'User account deactivated successfully.',
        ]);
    }

    /**
     * List available roles for form selection.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::pluck('name');

        return response()->json($roles);
    }

    private function ensureAnActiveAdministratorRemains(User $user, string $newRole, bool $willBeActive): void
    {
        if (! $user->hasRole('Administrator') || ! $user->is_active || ($newRole === 'Administrator' && $willBeActive)) {
            return;
        }

        $activeAdministrators = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', 'Administrator'))
            ->lockForUpdate()
            ->count();

        if ($activeAdministrators <= 1) {
            throw ValidationException::withMessages([
                'role' => 'The last active administrator cannot be deactivated or demoted.',
            ]);
        }
    }
}
