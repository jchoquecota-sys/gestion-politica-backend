<?php

namespace App\Http\Controllers\Sector;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use App\Models\BasePersona;
use App\Models\SectorPersona;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PersonaController extends Controller
{
    /**
     * Listar todas las personas con sus bases y sectores asociados.
     */
    public function index(): JsonResponse
    {
        $personas = Persona::with([
            'basePersonas.base.sector',
            'basePersonas.cargo',
            'sectorPersonas.sector',
            'sectorPersonas.cargo',
        ])->get();

        return response()->json([
            'status' => 'success',
            'data' => $personas->map(fn($p) => $this->formatResource($p))
        ]);
    }

    /**
     * Crear una nueva persona con vinculaciones opcionales.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombres'       => 'required|string|max:255',
            'apellidos'     => 'required|string|max:255',
            'dni'           => 'nullable|string|max:8|unique:personas,dni',
            'celular'       => 'nullable|string|max:15',
            'email'         => 'nullable|email|unique:personas,email',
            'direccion'     => 'nullable|string',
            'fecha_nacimiento' => 'nullable|date',

            // Vinculación opcional a una base
            'base_id'       => 'nullable|exists:bases,id',
            'cargo_base_id' => 'nullable|required_with:base_id|exists:cargos,id',

            // Vinculación opcional a un sector
            'sector_id'       => 'nullable|exists:sectores,id',
            'cargo_sector_id' => 'nullable|required_with:sector_id|exists:cargos,id',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $persona = Persona::create([
                    'nombres'          => $validated['nombres'],
                    'apellidos'        => $validated['apellidos'],
                    'dni'              => $validated['dni'] ?? null,
                    'celular'          => $validated['celular'] ?? null,
                    'email'            => $validated['email'] ?? null,
                    'direccion'        => $validated['direccion'] ?? null,
                    'fecha_nacimiento' => $validated['fecha_nacimiento'] ?? null,
                ]);

                if (!empty($validated['base_id'])) {
                    BasePersona::create([
                        'base_id'    => $validated['base_id'],
                        'persona_id' => $persona->id,
                        'cargo_id'   => $validated['cargo_base_id'],
                        'es_principal' => false,
                        'fecha_inicio' => now()->toDateString(),
                    ]);
                }

                if (!empty($validated['sector_id'])) {
                    SectorPersona::create([
                        'sector_id'  => $validated['sector_id'],
                        'persona_id' => $persona->id,
                        'cargo_id'   => $validated['cargo_sector_id'],
                        'es_principal' => false,
                        'fecha_inicio' => now()->toDateString(),
                    ]);
                }

                $persona->load([
                    'basePersonas.base.sector',
                    'basePersonas.cargo',
                    'sectorPersonas.sector',
                    'sectorPersonas.cargo',
                ]);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Persona registrada correctamente',
                    'data'    => $this->formatResource($persona)
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error("Error al crear persona: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo registrar la persona.'], 500);
        }
    }

    /**
     * Ver detalle de una persona con todas sus vinculaciones.
     */
    public function show(Persona $persona): JsonResponse
    {
        $persona->load([
            'basePersonas.base.sector',
            'basePersonas.cargo',
            'sectorPersonas.sector',
            'sectorPersonas.cargo',
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $this->formatResource($persona)
        ]);
    }

    /**
     * Actualizar datos de una persona con vinculaciones opcionales.
     */
    public function update(Request $request, Persona $persona): JsonResponse
    {
        $validated = $request->validate([
            'nombres'       => 'required|string|max:255',
            'apellidos'     => 'required|string|max:255',
            'dni'           => "nullable|string|max:8|unique:personas,dni,{$persona->id}",
            'celular'       => 'nullable|string|max:15',
            'email'         => "nullable|email|unique:personas,email,{$persona->id}",
            'direccion'     => 'nullable|string',
            'fecha_nacimiento' => 'nullable|date',

            // Vinculación opcional a una base
            'base_id'       => 'nullable|exists:bases,id',
            'cargo_base_id' => 'nullable|required_with:base_id|exists:cargos,id',

            // Vinculación opcional a un sector
            'sector_id'       => 'nullable|exists:sectores,id',
            'cargo_sector_id' => 'nullable|required_with:sector_id|exists:cargos,id',
        ]);

        try {
            return DB::transaction(function () use ($validated, $persona) {
                $persona->update([
                    'nombres'          => $validated['nombres'],
                    'apellidos'        => $validated['apellidos'],
                    'dni'              => $validated['dni'] ?? $persona->dni,
                    'celular'          => $validated['celular'] ?? $persona->celular,
                    'email'            => $validated['email'] ?? $persona->email,
                    'direccion'        => $validated['direccion'] ?? $persona->direccion,
                    'fecha_nacimiento' => $validated['fecha_nacimiento'] ?? $persona->fecha_nacimiento,
                ]);

                // Vincular a base (solo si no estaba ya)
                if (!empty($validated['base_id'])) {
                    BasePersona::withTrashed()->updateOrCreate(
                        ['base_id' => $validated['base_id'], 'persona_id' => $persona->id],
                        [
                            'cargo_id'    => $validated['cargo_base_id'],
                            'es_principal'=> false,
                            'fecha_inicio'=> now()->toDateString(),
                            'deleted_at'  => null,
                            'deleted_by'  => null,
                        ]
                    );
                }

                // Vincular a sector (solo si no estaba ya)
                if (!empty($validated['sector_id'])) {
                    SectorPersona::withTrashed()->updateOrCreate(
                        ['sector_id' => $validated['sector_id'], 'persona_id' => $persona->id],
                        [
                            'cargo_id'    => $validated['cargo_sector_id'],
                            'es_principal'=> false,
                            'fecha_inicio'=> now()->toDateString(),
                            'deleted_at'  => null,
                            'deleted_by'  => null,
                        ]
                    );
                }

                $persona->load([
                    'basePersonas.base.sector',
                    'basePersonas.cargo',
                    'sectorPersonas.sector',
                    'sectorPersonas.cargo',
                ]);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Datos actualizados correctamente',
                    'data'    => $this->formatResource($persona)
                ]);
            });
        } catch (\Exception $e) {
            Log::error("Error al actualizar persona: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo actualizar la persona.'], 500);
        }
    }

    /**
     * Eliminar (soft delete) una persona.
     */
    public function destroy(Persona $persona): JsonResponse
    {
        $persona->delete();
        return response()->json([
            'status'  => 'success',
            'message' => 'Persona eliminada correctamente'
        ]);
    }

    /**
     * Formatear el recurso con sus vinculaciones.
     */
    private function formatResource(Persona $persona): array
    {
        return [
            'id'              => $persona->id,
            'nombre_completo' => $persona->nombre_completo,
            'nombres'         => $persona->nombres,
            'apellidos'       => $persona->apellidos,
            'dni'             => $persona->dni,
            'celular'         => $persona->celular,
            'email'           => $persona->email,
            'direccion'       => $persona->direccion,
            'fecha_nacimiento'=> $persona->fecha_nacimiento,
            'bases'           => $persona->basePersonas->map(fn($bp) => [
                'asignacion_id' => $bp->id,
                'base_id'       => $bp->base->id,
                'base_nombre'   => $bp->base->nombre,
                'sector_nombre' => $bp->base->sector->nombre ?? null,
                'cargo_id'      => $bp->cargo_id,
                'cargo_nombre'  => $bp->cargo->nombre,
                'es_principal'  => (bool) $bp->es_principal,
                'fecha_inicio'  => $bp->fecha_inicio?->format('Y-m-d'),
            ]),
            'sectores'        => $persona->sectorPersonas->map(fn($sp) => [
                'asignacion_id' => $sp->id,
                'sector_id'     => $sp->sector->id,
                'sector_nombre' => $sp->sector->nombre,
                'cargo_id'      => $sp->cargo_id,
                'cargo_nombre'  => $sp->cargo->nombre,
                'es_principal'  => (bool) $sp->es_principal,
                'fecha_inicio'  => $sp->fecha_inicio?->format('Y-m-d'),
            ]),
            'created_at'      => $persona->created_at?->toDateTimeString(),
        ];
    }
}
