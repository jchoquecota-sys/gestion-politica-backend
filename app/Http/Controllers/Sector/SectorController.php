<?php

namespace App\Http\Controllers\Sector;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\SectorPersona;
use App\Http\Requests\Sector\StoreSectorRequest;
use App\Http\Requests\Sector\UpdateSectorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SectorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $sectores = Sector::with([
            'sectorPersonas.persona', 
            'sectorPersonas.cargo',
            'creator'
        ])->get();

        return response()->json([
            'status' => 'success',
            'data' => $sectores->map(fn($s) => $this->formatResource($s))
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSectorRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $sector = Sector::create($request->validated());

                if ($request->has('personas')) {
                    foreach ($request->personas as $personaData) {
                        SectorPersona::create([
                            'sector_id' => $sector->id,
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
                    'message' => 'Sector creado exitosamente',
                    'data' => $this->formatResource($sector->load(['sectorPersonas.persona', 'sectorPersonas.cargo']))
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error("Error al crear sector: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo crear el sector',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Sector $sectore): JsonResponse
    {
        $sectore->load([
            'sectorPersonas.persona', 
            'sectorPersonas.cargo', 
            'creator', 
            'updater'
        ]);
        
        return response()->json([
            'status' => 'success',
            'data' => $this->formatResource($sectore)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSectorRequest $request, Sector $sectore): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request, $sectore) {
                // Actualizar datos básicos
                $sectore->update($request->validated());

                // Sincronizar personal
                if ($request->has('personas')) {
                    $sectore->sectorPersonas()->delete(); // Soft delete actual

                    foreach ($request->personas as $personaData) {
                        SectorPersona::withTrashed()->updateOrCreate(
                            [
                                'sector_id' => $sectore->id,
                                'persona_id' => $personaData['persona_id']
                            ],
                            [
                                'cargo_id' => $personaData['cargo_id'],
                                'es_principal' => $personaData['es_principal'] ?? false,
                                'fecha_inicio' => $personaData['fecha_inicio'] ?? null,
                                'observaciones' => $personaData['observaciones'] ?? null,
                                'deleted_at' => null,
                                'deleted_by' => null
                            ]
                        );
                    }
                }

                // Refrescar relación explícitamente
                $sectore->unsetRelation('sectorPersonas');
                $sectore->load(['sectorPersonas.persona', 'sectorPersonas.cargo', 'creator', 'updater']);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Sector actualizado exitosamente',
                    'data' => $this->formatResource($sectore)
                ]);
            });
        } catch (\Exception $e) {
            Log::error("Error al actualizar sector: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo actualizar el sector',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sector $sectore): JsonResponse
    {
        try {
            return DB::transaction(function () use ($sectore) {
                $sectore->delete(); // Soft delete

                return response()->json([
                    'status' => 'success',
                    'message' => 'Sector eliminado (soft delete) exitosamente'
                ]);
            });
        } catch (\Exception $e) {
            Log::error("Error al eliminar sector: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo eliminar el sector'
            ], 500);
        }
    }

    /**
     * Formatear el recurso para una respuesta JSON consistente.
     */
    private function formatResource(Sector $sector): array
    {
        $responsable = $sector->sectorPersonas->where('es_principal', true)->first()
                    ?? $sector->sectorPersonas->where('es_principal', 1)->first()
                    ?? $sector->sectorPersonas->first(fn($sp) => $sp->es_principal);

        return [
            'id' => $sector->id,
            'nombre' => $sector->nombre,
            'descripcion' => $sector->descripcion,
            'codigo' => $sector->codigo,
            'referencia_ubicacion' => $sector->referencia_ubicacion,
            'responsable' => $responsable ? [
                'id' => $responsable->persona->id,
                'nombre_completo' => $responsable->persona->nombre_completo,
                'cargo' => $responsable->cargo->nombre ?? 'N/A',
                'fecha_inicio' => $responsable->fecha_inicio,
            ] : null,
            'equipo' => $sector->sectorPersonas->map(fn($sp) => [
                'id' => $sp->persona->id,
                'nombre_completo' => $sp->persona->nombre_completo,
                'cargo' => $sp->cargo->nombre ?? 'N/A',
                'cargo_id' => $sp->cargo_id,
                'es_principal' => (bool)$sp->es_principal,
                'fecha_inicio' => $sp->fecha_inicio,
                'observaciones' => $sp->observaciones
            ]),
            'auditoria' => [
                'creado_por' => $sector->creator->name ?? 'Sistema',
                'creado_el' => $sector->created_at->toDateTimeString(),
                'actualizado_por' => $sector->updater->name ?? null,
                'actualizado_el' => $sector->updated_at->toDateTimeString(),
            ]
        ];
    }
}
