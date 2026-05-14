<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\Cargo;
use App\Models\Persona;
use App\Models\Base;
use Spatie\Permission\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    public function sectores(Request $request): JsonResponse
    {
        $query = Sector::select('id', 'nombre');
        $user = auth()->user();

        $hasGlobalAccess = $user->hasPermissionTo('bases:list-all') 
                        || $user->hasPermissionTo('sectores:list')
                        || $user->hasPermissionTo('personas:list-all');

        // Filtrar sectores permitidos si el usuario no tiene permisos globales
        if (!$hasGlobalAccess) {
            $allowedSectors = $user->getAllowedSectorIds();
            $query->whereIn('id', $allowedSectors);
        }

        return response()->json(['data' => $query->orderBy('nombre')->get()]);
    }

    public function roles(): JsonResponse
    {
        $roles = Role::select('id', 'name')->orderBy('name')->get();
        return response()->json(['data' => $roles]);
    }

    public function cargos(): JsonResponse
    {
        $cargos = Cargo::select('id', 'nombre')->orderBy('nombre')->get();
        return response()->json(['data' => $cargos]);
    }

    public function personas(): JsonResponse
    {
        $personas = Persona::select('id', 'nombres', 'apellidos', 'dni')
            ->orderBy('nombres')
            ->get()
            ->map(function ($persona) {
                return [
                    'id' => $persona->id,
                    'nombre_completo' => $persona->nombre_completo,
                    'dni' => $persona->dni
                ];
            });

        return response()->json(['data' => $personas]);
    }

    public function bases(Request $request): JsonResponse
    {
        $query = Base::select('id', 'nombre', 'sector_id');

        if ($request->has('sector_id')) {
            $query->where('sector_id', $request->sector_id);
        }

        $user = auth()->user();
        $hasGlobalAccess = $user->hasPermissionTo('bases:list-all') 
                        || $user->hasPermissionTo('personas:list-all');

        if (!$hasGlobalAccess) {
            $allowedSectors = $user->getAllowedSectorIds();
            $query->whereIn('sector_id', $allowedSectors);
        }

        return response()->json(['data' => $query->orderBy('nombre')->get()]);
    }
}
