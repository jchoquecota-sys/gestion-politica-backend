<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * List all roles with their associated permissions.
     *
     * GET /api/roles
     * Permission: roles:list
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->formatRole($role));

        return response()->json(['data' => $roles]);
    }

    /**
     * Show a single role with its permissions.
     *
     * GET /api/roles/{role}
     * Permission: roles:view
     */
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions:id,name');

        return response()->json(['data' => $this->formatRole($role)]);
    }

    /**
     * Create a new role and optionally assign permissions to it.
     *
     * POST /api/roles
     * Permission: roles:create
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            // We explicitly set guard_name to 'web' to avoid conflicts
            // with Sanctum which might try to use 'sanctum' as guard name.
            $role = Role::create([
                'name' => $request->name,
                'guard_name' => 'web'
            ]);

            if ($request->filled('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            $role->load('permissions:id,name');

            return response()->json(
                [
                    'message' => 'Rol creado exitosamente.',
                    'data' => $this->formatRole($role)
                ],
                JsonResponse::HTTP_CREATED
            );
        });
    }

    /**
     * Update a role's name and/or its assigned permissions.
     *
     * PUT /api/roles/{role}
     * Permission: roles:edit
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->preventSuperAdminModification($role);

        return DB::transaction(function () use ($request, $role) {
            if ($request->filled('name')) {
                $role->update(['name' => $request->name]);
            }

            if ($request->has('permissions')) {
                // Ensure sync happens on the web guard
                $role->syncPermissions($request->permissions);
            }

            $role->load('permissions:id,name');

            return response()->json(
                [
                    'message' => 'Rol actualizado exitosamente.',
                    'data' => $this->formatRole($role)
                ]
            );
        });
    }

    /**
     * Delete a role.
     *
     * DELETE /api/roles/{role}
     * Permission: roles:delete
     */
    public function destroy(Role $role): JsonResponse
    {
        $this->preventSuperAdminModification($role);

        $role->delete();

        return response()->json(['message' => 'Rol eliminado exitosamente.']);
    }

    /**
     * Return all available permissions so the frontend can build
     * a permission picker when creating or editing roles.
     *
     * GET /api/roles/permissions
     * Permission: roles:assign-permissions
     */
    public function availablePermissions(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name');

        return response()->json(['data' => $permissions]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Normalize a role into a consistent API response shape.
     */
    private function formatRole(Role $role): array
    {
        return [
            'id'          => $role->id,
            'name'        => $role->name,
            'permissions' => $role->permissions->pluck('name')->sort()->values(),
        ];
    }

    /**
     * Prevent any modification to the super-admin role through the API.
     */
    private function preventSuperAdminModification(Role $role): void
    {
        if ($role->name === 'super-admin') {
            abort(
                JsonResponse::HTTP_FORBIDDEN,
                'El rol "super-admin" es un rol del sistema y no puede modificarse.'
            );
        }
    }
}
