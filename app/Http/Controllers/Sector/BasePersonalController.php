<?php

namespace App\Http\Controllers\Sector;

use App\Http\Controllers\Controller;
use App\Models\Base;
use App\Models\BasePersona;
use App\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gestiona el personal asociado a una base específica.
 * Endpoints anidados bajo /api/bases/{base}/personal
 */
class BasePersonalController extends Controller
{
    /**
     * Listar todo el personal de una base, con filtro opcional por cargo.
     */
    public function index(Request $request, Base $base): JsonResponse
    {
        $this->checkSectorAccess($base);

        $query = $base->basePersonas()
            ->with(['persona', 'cargo']);

        if ($request->has('cargo_id')) {
            $query->where('cargo_id', $request->cargo_id);
        }

        $personal = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $personal->map(fn($bp) => $this->formatAsignacion($bp))
        ]);
    }

    /**
     * Añadir una persona a la base.
     */
    public function store(Request $request, Base $base): JsonResponse
    {
        $this->checkSectorAccess($base);

        $validated = $request->validate([
            'persona_id'    => 'required|exists:personas,id',
            'cargo_id'      => 'required|exists:cargos,id',
            'es_principal'  => 'nullable|boolean',
            'fecha_inicio'  => 'nullable|date',
            'observaciones' => 'nullable|string',
        ]);

        // Verificar que la persona no esté ya activa en esta base
        $existe = BasePersona::where('base_id', $base->id)
            ->where('persona_id', $validated['persona_id'])
            ->whereNull('deleted_at')
            ->exists();

        if ($existe) {
            return response()->json([
                'status' => 'error',
                'message' => 'Esta persona ya está asignada a la base.'
            ], 422);
        }

        try {
            return DB::transaction(function () use ($validated, $base) {
                // Si se marca como principal, quitar el principal actual
                if (!empty($validated['es_principal']) && $validated['es_principal']) {
                    $base->basePersonas()->where('es_principal', true)->update(['es_principal' => false]);
                }

                $asignacion = BasePersona::create([
                    'base_id'       => $base->id,
                    'persona_id'    => $validated['persona_id'],
                    'cargo_id'      => $validated['cargo_id'],
                    'es_principal'  => $validated['es_principal'] ?? false,
                    'fecha_inicio'  => $validated['fecha_inicio'] ?? now()->toDateString(),
                    'observaciones' => $validated['observaciones'] ?? null,
                ]);

                $asignacion->load(['persona', 'cargo']);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Persona añadida a la base correctamente.',
                    'data'    => $this->formatAsignacion($asignacion)
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error("Error al añadir personal a base: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo añadir a la persona.'], 500);
        }
    }

    /**
     * Actualizar la asignación de una persona en la base.
     */
    public function update(Request $request, Base $base, BasePersona $asignacion): JsonResponse
    {
        $this->checkSectorAccess($base);
        $this->verificarPertenencia($asignacion, $base);

        $validated = $request->validate([
            'cargo_id'      => 'required|exists:cargos,id',
            'es_principal'  => 'nullable|boolean',
            'fecha_inicio'  => 'nullable|date',
            'observaciones' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($validated, $asignacion, $base) {
                if (!empty($validated['es_principal']) && $validated['es_principal']) {
                    $base->basePersonas()
                        ->where('id', '!=', $asignacion->id)
                        ->where('es_principal', true)
                        ->update(['es_principal' => false]);
                }

                $asignacion->update($validated);
                $asignacion->load(['persona', 'cargo']);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Asignación actualizada correctamente.',
                    'data'    => $this->formatAsignacion($asignacion)
                ]);
            });
        } catch (\Exception $e) {
            Log::error("Error al actualizar personal de base: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo actualizar la asignación.'], 500);
        }
    }

    /**
     * Eliminar (soft delete) una asignación.
     */
    public function destroy(Base $base, BasePersona $asignacion): JsonResponse
    {
        $this->checkSectorAccess($base);
        $this->verificarPertenencia($asignacion, $base);

        try {
            $asignacion->delete();
            return response()->json([
                'status'  => 'success',
                'message' => 'Persona desvinculada de la base correctamente.'
            ]);
        } catch (\Exception $e) {
            Log::error("Error al eliminar personal de base: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo desvincular a la persona.'], 500);
        }
    }

    private function verificarPertenencia(BasePersona $asignacion, Base $base): void
    {
        abort_if($asignacion->base_id !== $base->id, 404, 'La asignación no pertenece a esta base.');
    }

    /**
     * Check if the user has access to the base's sector.
     */
    private function checkSectorAccess(Base $base): void
    {
        if (!auth()->user()->hasPermissionTo('bases:list-all')) {
            if (!in_array($base->sector_id, auth()->user()->getAllowedSectorIds())) {
                abort(response()->json([
                    'status' => 'error',
                    'message' => 'No tiene permiso para gestionar personal de esta base.'
                ], 403));
            }
        }
    }

    private function formatAsignacion(BasePersona $bp): array
    {
        return [
            'id'            => $bp->id,
            'persona'       => [
                'id'             => $bp->persona->id,
                'nombre_completo'=> $bp->persona->nombre_completo,
                'dni'            => $bp->persona->dni,
                'celular'        => $bp->persona->celular,
            ],
            'cargo'         => [
                'id'     => $bp->cargo->id,
                'nombre' => $bp->cargo->nombre,
            ],
            'es_principal'  => (bool) $bp->es_principal,
            'fecha_inicio'  => $bp->fecha_inicio?->format('Y-m-d'),
            'observaciones' => $bp->observaciones,
        ];
    }
}
