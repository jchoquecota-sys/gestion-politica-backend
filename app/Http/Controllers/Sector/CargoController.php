<?php

namespace App\Http\Controllers\Sector;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CargoController extends Controller
{
    /**
     * Listar todos los cargos.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Cargo::with(['creator', 'updater']);

        // Búsqueda
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('nombre', 'like', "%{$search}%");
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'nombre');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => collect($paginator->items())->map(fn($c) => $this->formatResource($c)),
            'meta'   => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ]
        ]);
    }

    /**
     * Crear un nuevo cargo.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:cargos,nombre',
            'descripcion' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $cargo = Cargo::create($validated);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Cargo registrado correctamente',
                    'data' => $this->formatResource($cargo)
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error("Error al crear cargo: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo registrar el cargo'
            ], 500);
        }
    }

    /**
     * Ver detalle de un cargo.
     */
    public function show(Cargo $cargo): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->formatResource($cargo->load(['creator', 'updater']))
        ]);
    }

    /**
     * Actualizar un cargo existente.
     */
    public function update(Request $request, Cargo $cargo): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => "required|string|max:255|unique:cargos,nombre,{$cargo->id}",
            'descripcion' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($validated, $cargo) {
                $cargo->update($validated);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Cargo actualizado correctamente',
                    'data' => $this->formatResource($cargo)
                ]);
            });
        } catch (\Exception $e) {
            Log::error("Error al actualizar cargo: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo actualizar el cargo'
            ], 500);
        }
    }

    /**
     * Eliminar (soft delete) un cargo.
     */
    public function destroy(Cargo $cargo): JsonResponse
    {
        // Evitar eliminar cargos que están siendo usados en la tabla pivote
        if ($cargo->sectorPersonas()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se puede eliminar el cargo porque tiene personas asignadas en algún sector.'
            ], 422);
        }

        try {
            return DB::transaction(function () use ($cargo) {
                $cargo->delete();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Cargo eliminado correctamente'
                ]);
            });
        } catch (\Exception $e) {
            Log::error("Error al eliminar cargo: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo eliminar el cargo'
            ], 500);
        }
    }

    /**
     * Formatear el recurso para JSON.
     */
    private function formatResource(Cargo $cargo): array
    {
        return [
            'id' => $cargo->id,
            'nombre' => $cargo->nombre,
            'descripcion' => $cargo->descripcion,
            'auditoria' => [
                'creado_por' => $cargo->creator->name ?? 'Sistema',
                'creado_el' => $cargo->created_at->toDateTimeString(),
                'actualizado_por' => $cargo->updater->name ?? null,
                'actualizado_el' => $cargo->updated_at->toDateTimeString(),
            ]
        ];
    }
}
