<?php

namespace App\Http\Controllers\Sector;

use App\Http\Controllers\Controller;
use App\Models\Base;
use App\Models\BasePersona;
use App\Http\Requests\Sector\StoreBaseRequest;
use App\Http\Requests\Sector\UpdateBaseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BaseController extends Controller
{
    /**
     * Listar bases con filtrado por permisos.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Base::with([
            'sector',
            'basePersonas.persona',
            'basePersonas.cargo',
            'creator'
        ]);

        // Verificamos el permiso especial
        if (!auth()->user()->hasPermissionTo('bases:list-all')) {
            // Si no tiene el permiso de listar todo, el sector_id es obligatorio
            if (!$request->has('sector_id')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Debe proporcionar un sector_id para listar las bases.'
                ], 403);
            }
            $query->where('sector_id', $request->sector_id);
        } elseif ($request->has('sector_id')) {
            // Si tiene permiso pero envía sector_id, también filtramos
            $query->where('sector_id', $request->sector_id);
        }

        $bases = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $bases->map(fn($b) => $this->formatResource($b))
        ]);
    }

    /**
     * Crear una nueva base.
     */
    public function store(StoreBaseRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $base = Base::create($request->validated());

                if ($request->has('personas')) {
                    foreach ($request->personas as $personaData) {
                        BasePersona::create([
                            'base_id' => $base->id,
                            'persona_id' => $personaData['persona_id'],
                            'cargo_id' => $personaData['cargo_id'],
                            'es_principal' => $personaData['es_principal'] ?? false,
                            'fecha_inicio' => $personaData['fecha_inicio'] ?? now(),
                            'observaciones' => $personaData['observaciones'] ?? null,
                        ]);
                    }
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Base creada exitosamente',
                    'data' => $this->formatResource($base->load(['basePersonas.persona', 'basePersonas.cargo']))
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error("Error al crear base: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo crear la base'
            ], 500);
        }
    }

    /**
     * Ver detalle de una base.
     */
    public function show(Base $base): JsonResponse
    {
        $base->load(['sector', 'basePersonas.persona', 'basePersonas.cargo', 'creator', 'updater']);

        return response()->json([
            'status' => 'success',
            'data' => $this->formatResource($base)
        ]);
    }

    /**
     * Actualizar una base.
     */
    public function update(UpdateBaseRequest $request, Base $base): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request, $base) {
                // Actualizar datos básicos de la base
                $base->update($request->validated());

                // Actualizar personal (Sincronización manual para manejar SoftDeletes y Auditoría)
                if ($request->has('personas')) {
                    // Eliminamos lógicamente las asignaciones actuales
                    $base->basePersonas()->delete();

                    foreach ($request->personas as $personaData) {
                        // Restauramos o creamos la asignación
                        BasePersona::withTrashed()->updateOrCreate(
                            [
                                'base_id' => $base->id,
                                'persona_id' => $personaData['persona_id']
                            ],
                            [
                                'cargo_id' => $personaData['cargo_id'],
                                'es_principal' => $personaData['es_principal'] ?? false,
                                'fecha_inicio' => $personaData['fecha_inicio'] ?? null,
                                'observaciones' => $personaData['observaciones'] ?? null,
                                'deleted_at' => null, // Restaurar
                                'deleted_by' => null  // Limpiar quién lo borró
                            ]
                        );
                    }
                }

                // Refrescar la relación explícitamente antes de formatear
                $base->unsetRelation('basePersonas');
                $base->load(['sector', 'basePersonas.persona', 'basePersonas.cargo', 'creator', 'updater']);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Base actualizada exitosamente',
                    'data' => $this->formatResource($base)
                ]);
            });
        } catch (\Exception $e) {
            Log::error("Error al actualizar base: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo actualizar la base',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar (soft delete) una base.
     */
    public function destroy(Base $base): JsonResponse
    {
        try {
            return DB::transaction(function () use ($base) {
                $base->delete();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Base eliminada correctamente'
                ]);
            });
        } catch (\Exception $e) {
            Log::error("Error al eliminar base: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo eliminar la base'
            ], 500);
        }
    }

    /**
     * Formatear el recurso para JSON.
     */
    private function formatResource(Base $base): array
    {
        // Búsqueda más flexible del responsable
        $responsable = $base->basePersonas->where('es_principal', true)->first() 
                    ?? $base->basePersonas->where('es_principal', 1)->first()
                    ?? $base->basePersonas->first(fn($bp) => $bp->es_principal);

        return [
            'id' => $base->id,
            'nombre' => $base->nombre,
            'descripcion' => $base->descripcion,
            'direccion' => $base->direccion,
            'coordenadas' => [
                'lat' => $base->latitud,
                'lng' => $base->longitud,
            ],
            'sector' => [
                'id' => $base->sector->id,
                'nombre' => $base->sector->nombre,
            ],
            'responsable' => $responsable ? [
                'id' => $responsable->persona->id,
                'nombre_completo' => $responsable->persona->nombre_completo,
                'cargo' => $responsable->cargo->nombre ?? 'N/A',
                'fecha_inicio' => $responsable->fecha_inicio,
            ] : null,
            'equipo' => $base->basePersonas->map(fn($bp) => [
                'id' => $bp->persona->id,
                'nombre_completo' => $bp->persona->nombre_completo,
                'cargo' => $bp->cargo->nombre ?? 'N/A',
                'cargo_id' => $bp->cargo_id,
                'es_principal' => (bool)$bp->es_principal,
                'fecha_inicio' => $bp->fecha_inicio,
                'observaciones' => $bp->observaciones
            ]),
            'auditoria' => [
                'creado_por' => $base->creator->name ?? 'Sistema',
                'creado_el' => $base->created_at->toDateTimeString(),
                'actualizado_por' => $base->updater->name ?? null,
                'actualizado_el' => $base->updated_at->toDateTimeString(),
            ]
        ];
    }
}
