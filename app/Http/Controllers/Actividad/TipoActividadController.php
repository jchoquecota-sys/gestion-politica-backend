<?php

namespace App\Http\Controllers\Actividad;

use App\Http\Controllers\Controller;
use App\Models\TipoActividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TipoActividadController extends Controller
{
    /**
     * Listar todos los tipos de actividad.
     *
     * GET /api/tipos-actividad
     * Permission: actividades:list
     */
    public function index(): JsonResponse
    {
        $tipos = TipoActividad::orderBy('nombre')->get()
            ->map(fn($t) => $this->formatResource($t));

        return response()->json([
            'status' => 'success',
            'data'   => $tipos,
        ]);
    }

    /**
     * Crear un nuevo tipo de actividad.
     *
     * POST /api/tipos-actividad
     * Permission: actividades:create
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre'      => 'required|string|max:100|unique:tipos_actividad,nombre',
            'descripcion' => 'nullable|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $tipo = TipoActividad::create($validated);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Tipo de actividad creado correctamente.',
                    'data'    => $this->formatResource($tipo),
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Error al crear tipo de actividad: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo crear el tipo de actividad.'], 500);
        }
    }

    /**
     * Actualizar un tipo de actividad.
     *
     * PUT /api/tipos-actividad/{tipoActividad}
     * Permission: actividades:edit
     */
    public function update(Request $request, TipoActividad $tipoActividad): JsonResponse
    {
        $validated = $request->validate([
            'nombre'      => "required|string|max:100|unique:tipos_actividad,nombre,{$tipoActividad->id}",
            'descripcion' => 'nullable|string|max:500',
        ]);

        try {
            $tipoActividad->update($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'Tipo de actividad actualizado correctamente.',
                'data'    => $this->formatResource($tipoActividad),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar tipo de actividad: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo actualizar el tipo de actividad.'], 500);
        }
    }

    /**
     * Eliminar un tipo de actividad.
     *
     * DELETE /api/tipos-actividad/{tipoActividad}
     * Permission: actividades:delete
     */
    public function destroy(TipoActividad $tipoActividad): JsonResponse
    {
        // Verificar que no tenga actividades asociadas antes de eliminar
        if ($tipoActividad->actividades()->count() > 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No se puede eliminar este tipo de actividad porque tiene actividades asociadas.',
            ], 409);
        }

        try {
            $tipoActividad->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Tipo de actividad eliminado correctamente.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar tipo de actividad: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo eliminar el tipo de actividad.'], 500);
        }
    }

    private function formatResource(TipoActividad $tipo): array
    {
        return [
            'id'          => $tipo->id,
            'nombre'      => $tipo->nombre,
            'descripcion' => $tipo->descripcion,
            'created_at'  => $tipo->created_at?->toDateTimeString(),
        ];
    }
}
