<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List all users with their roles (paginated).
     *
     * GET /api/users
     * Permission: users:list
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['roles:id,name', 'persona']);

        // Búsqueda global
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtro por rol
        if ($request->filled('role')) {
            $query->role($request->role);
        }

        // Ordenamiento
        $sortBy    = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $allowed   = ['name', 'email', 'created_at'];
        if (in_array($sortBy, $allowed)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Paginación
        $perPage   = (int) $request->get('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => collect($paginator->items())->map(fn(User $u) => $this->formatUser($u)),
            'meta'   => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * Show a single user with roles and permissions.
     *
     * GET /api/users/{user}
     * Permission: users:view
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['roles:id,name', 'persona']);

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
                'name'       => $request->name,
                'email'      => $request->email,
                'password'   => Hash::make($request->password),
                'persona_id' => $request->persona_id,
            ]);

            if ($request->filled('roles')) {
                $user->assignRole($request->roles);
            }

            $user->load(['roles:id,name', 'persona']);

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

            if ($request->has('persona_id')) {
                $user->persona_id = $request->persona_id;
            }

            $user->save();

            if ($request->has('roles')) {
                $user->syncRoles($request->roles);
            }

            $user->load(['roles:id,name', 'persona']);

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

        // Prevent deletion of the last super-admin
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
            'persona'     => $user->persona ? [
                'id'              => $user->persona->id,
                'nombre_completo' => $user->persona->nombre_completo,
            ] : null,
            'created_at'  => $user->created_at->toISOString(),
        ];
    }
}
