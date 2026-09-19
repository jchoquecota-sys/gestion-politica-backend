<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\Cargo;
use App\Models\Persona;
use App\Models\Base;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    /**
     * Listado de sectores para selects.
     * Respeta permisos del usuario — solo devuelve los sectores a los que tiene acceso.
     */
    public function sectores(Request $request): JsonResponse
    {
        $query = Sector::select('id', 'nombre');
        $user  = auth()->user();

        $hasGlobalAccess = $this->userCan($user, 'bases:list-all')
                        || $this->userCan($user, 'sectores:view')
                        || $this->userCan($user, 'personas:list-all')
                        || $this->userCan($user, 'actividades:manage-all');

        if (!$hasGlobalAccess) {
            $allowedSectors = $user->getAllowedSectorIds();
            $query->whereIn('id', $allowedSectors);
        }

        return response()->json(['data' => $query->orderBy('nombre')->get()]);
    }

    /**
     * Listado de roles para selects.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::select('id', 'name')->orderBy('name')->get();
        return response()->json(['data' => $roles]);
    }

    /**
     * Listado de cargos para selects.
     */
    public function cargos(): JsonResponse
    {
        $cargos = Cargo::select('id', 'nombre')->orderBy('nombre')->get();
        return response()->json(['data' => $cargos]);
    }

    /**
     * Listado de personas para selects.
     * Respeta permisos del usuario — solo devuelve personas a las que tiene acceso.
     */
    public function personas(): JsonResponse
    {
        $user  = auth()->user();
        $query = Persona::select('id', 'nombres', 'apellidos', 'dni');

        $hasGlobalAccess = $this->userCan($user, 'personas:list-all')
                        || $this->userCan($user, 'actividades:manage-all');

        $hasSectorAccess = $this->userCan($user, 'personas:list-only-sector')
                        || $this->userCan($user, 'actividades:manage-sector');

        $hasBaseAccess = $this->userCan($user, 'actividades:manage-base');

        // Aplicar filtro de scope si el usuario no tiene acceso global
        if (!$hasGlobalAccess) {
            if ($hasSectorAccess) {
                $allowedSectors = $user->getAllowedSectorIds();
                $query->where(function ($q) use ($allowedSectors) {
                    $q->whereHas('sectorPersonas', fn($sq) => $sq->whereIn('sector_id', $allowedSectors))
                      ->orWhereHas('basePersonas.base', fn($bq) => $bq->whereIn('sector_id', $allowedSectors));
                });
            } elseif ($hasBaseAccess) {
                $allowedBases = $user->getAllowedBaseIds();
                $query->whereHas('basePersonas', fn($bq) => $bq->whereIn('base_id', $allowedBases));
            } else {
                // Usuario raso: Solo puede verse a sí mismo
                if ($user->persona_id) {
                    $query->where('id', $user->persona_id);
                } else {
                    // Si el usuario no tiene persona vinculada, no devolverá resultados
                    $query->whereRaw('1 = 0');
                }
            }
        }

        $personas = $query->orderBy('nombres')->get()->map(fn($persona) => [
            'id'             => $persona->id,
            'nombre_completo'=> $persona->nombre_completo,
            'nombres'        => $persona->nombres,
            'apellidos'      => $persona->apellidos,
            'dni'            => $persona->dni,
        ]);

        return response()->json(['data' => $personas]);
    }

    /**
     * Listado de bases para selects.
     * Respeta permisos del usuario — solo devuelve bases de sectores permitidos.
     */
    public function bases(Request $request): JsonResponse
    {
        $query = Base::select('id', 'nombre', 'sector_id');
        $user  = auth()->user();

        $hasGlobalAccess = $this->userCan($user, 'bases:list-all')
                        || $this->userCan($user, 'personas:list-all')
                        || $this->userCan($user, 'actividades:manage-all');

        // Permiso real en el sistema: sectores:view (no existe sectores:list)
        $hasSectorAccess = $this->userCan($user, 'sectores:view')
                        || $this->userCan($user, 'personas:list-only-sector')
                        || $this->userCan($user, 'actividades:manage-sector');

        if ($request->has('sector_id')) {
            $query->where('sector_id', $request->sector_id);
        }

        if (!$hasGlobalAccess) {
            if ($hasSectorAccess) {
                $allowedSectors = $user->getAllowedSectorIds();
                $query->whereIn('sector_id', $allowedSectors);
            } else {
                $allowedBases = $user->getAllowedBaseIds();
                $query->whereIn('id', $allowedBases);
            }
        }

        return response()->json(['data' => $query->orderBy('nombre')->get()]);
    }

    /**
     * Comprueba permiso sin lanzar excepción si el nombre no existe en Spatie.
     */
    private function userCan(?User $user, string $permission): bool
    {
        if (!$user) {
            return false;
        }

        try {
            return $user->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }
}
