<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List all users with their roles.
     *
     * GET /api/users
     * Permission: users:list
     */
    public function index(): JsonResponse
    {
        $users = User::with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->formatUser($user));

        return response()->json(['data' => $users]);
    }

    /**
     * Show a single user with roles and permissions.
     *
     * GET /api/users/{user}
     * Permission: users:view
     */
    public function show(User $user): JsonResponse
    {
        $user->load('roles:id,name');

        return response()->json(['data' => $this->formatUser($user)]);
    }

    /**
     * Create a new user and assign roles.
     *
     * POST /api/users
     * Permission: users:create
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
            ]);

            if ($request->filled('roles')) {
                $user->assignRole($request->roles);
            }

            $user->load('roles:id,name');

            return response()->json(
                [
                    'message' => 'Usuario creado exitosamente.',
                    'data'    => $this->formatUser($user),
                ],
                JsonResponse::HTTP_CREATED
            );
        });
    }

    /**
     * Update user details and roles.
     *
     * PUT /api/users/{user}
     * Permission: users:edit
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        return DB::transaction(function () use ($request, $user) {
            if ($request->filled('name')) {
                $user->name = $request->name;
            }

            if ($request->filled('email')) {
                $user->email = $request->email;
            }

            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            $user->save();

            if ($request->has('roles')) {
                $user->syncRoles($request->roles);
            }

            $user->load('roles:id,name');

            return response()->json([
                'message' => 'Usuario actualizado exitosamente.',
                'data'    => $this->formatUser($user),
            ]);
        });
    }

    /**
     * Delete a user.
     *
     * DELETE /api/users/{user}
     * Permission: users:delete
     */
    public function destroy(User $user): JsonResponse
    {
        // Prevent self-deletion
        if (auth()->id() === $user->id) {
            return response()->json(
                ['message' => 'No puedes eliminar tu propio usuario.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        // Prevent deletion of the initial super-admin if needed
        if ($user->hasRole('super-admin') && User::role('super-admin')->count() <= 1) {
            return response()->json(
                ['message' => 'No se puede eliminar el último super-admin.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $user->delete();

        return response()->json(['message' => 'Usuario eliminado exitosamente.']);
    }

    /**
     * Format user for consistent API response.
     */
    private function formatUser(User $user): array
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'roles'       => $user->roles->pluck('name')->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'created_at'  => $user->created_at->toISOString(),
        ];
    }
}
