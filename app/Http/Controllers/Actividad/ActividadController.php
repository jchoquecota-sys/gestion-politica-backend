<?php

namespace App\Http\Controllers\Actividad;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\ActividadSujeto;
use App\Models\Base;
use App\Models\Persona;
use App\Models\Sector;
use App\Http\Requests\Actividad\StoreActividadRequest;
use App\Http\Requests\Actividad\UpdateActividadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class ActividadController extends Controller
{
    /**
     * Listar actividades con filtros de permisos.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = Actividad::with(['tipoActividad', 'sujetos.sujeto']);

        // 1. Aplicar filtros de permisos
        if (!$user->hasPermissionTo('actividades:manage-all')) {
            if ($user->hasPermissionTo('actividades:manage-sector')) {
                $allowedSectors = $user->getAllowedSectorIds();
                $allowedBases = Base::whereIn('sector_id', $allowedSectors)->pluck('id')->toArray();
                
                $query->whereHas('sujetos', function ($q) use ($allowedSectors, $allowedBases) {
                    $q->where(function ($sq) use ($allowedSectors, $allowedBases) {
                        // Sector sujeto
                        $sq->where(function($ss) use ($allowedSectors) {
                            $ss->where('sujeto_type', Sector::class)
                               ->whereIn('sujeto_id', $allowedSectors);
                        })
                        // Base sujeto
                        ->orWhere(function($sb) use ($allowedBases) {
                            $sb->where('sujeto_type', Base::class)
                               ->whereIn('sujeto_id', $allowedBases);
                        })
                        // Persona sujeto (en sector o base permitida)
                        ->orWhere(function($sp) use ($allowedSectors, $allowedBases) {
                            $sp->where('sujeto_type', Persona::class)
                               ->whereHasMorph('sujeto', [Persona::class], function($pq) use ($allowedSectors, $allowedBases) {
                                   $pq->whereHas('sectorPersonas', fn($ssp) => $ssp->whereIn('sector_id', $allowedSectors))
                                      ->orWhereHas('basePersonas', fn($bp) => $bp->whereIn('base_id', $allowedBases));
                               });
                        });
                    });
                })->orWhere('created_by', $user->id);
            } elseif ($user->hasPermissionTo('actividades:manage-base')) {
                $allowedBases = $user->getAllowedBaseIds();
                
                $query->whereHas('sujetos', function ($q) use ($allowedBases) {
                    $q->where(function ($sq) use ($allowedBases) {
                        // Base sujeto
                        $sq->where(function($sb) use ($allowedBases) {
                            $sb->where('sujeto_type', Base::class)
                               ->whereIn('sujeto_id', $allowedBases);
                        })
                        // Persona sujeto (en base permitida)
                        ->orWhere(function($sp) use ($allowedBases) {
                            $sp->where('sujeto_type', Persona::class)
                               ->whereHasMorph('sujeto', [Persona::class], function($pq) use ($allowedBases) {
                                   $pq->whereHas('basePersonas', fn($bp) => $bp->whereIn('base_id', $allowedBases));
                               });
                        });
                    });
                })->orWhere('created_by', $user->id);
            } else {
                // Si no tiene permisos de gestión, solo ve las que creó (opcional, según requerimiento)
                $query->where('created_by', $user->id);
            }
        }

        // 2. Filtros adicionales explícitos
        if ($request->has('sector_id') && $request->sector_id) {
            $sectorId = (int) $request->sector_id;
            // Verificar si tiene permiso para filtrar por este sector
            if (!$user->hasPermissionTo('actividades:manage-all')) {
                if (!in_array($sectorId, $user->getAllowedSectorIds())) {
                    abort(response()->json(['status' => 'error', 'message' => 'No tiene permiso para filtrar por este sector.'], 403));
                }
            }
            $basesIds = Base::where('sector_id', $sectorId)->pluck('id')->toArray();
            
            $query->whereHas('sujetos', function($q) use ($sectorId, $basesIds) {
                $q->where(function($sq) use ($sectorId, $basesIds) {
                    $sq->where(fn($ss) => $ss->where('sujeto_type', Sector::class)->where('sujeto_id', $sectorId))
                       ->orWhere(fn($sb) => $sb->where('sujeto_type', Base::class)->whereIn('sujeto_id', $basesIds))
                       ->orWhere(fn($sp) => $sp->where('sujeto_type', Persona::class)->whereHasMorph('sujeto', [Persona::class], function($pq) use ($sectorId, $basesIds) {
                           $pq->whereHas('sectorPersonas', fn($ssp) => $ssp->where('sector_id', $sectorId))
                              ->orWhereHas('basePersonas', fn($bp) => $bp->whereIn('base_id', $basesIds));
                       }));
                });
            });
        } elseif ($request->has('base_id') && $request->base_id) {
            $baseId = (int) $request->base_id;
            // Verificar si tiene permiso para filtrar por esta base
            if (!$user->hasPermissionTo('actividades:manage-all')) {
                if ($user->hasPermissionTo('actividades:manage-sector')) {
                    $base = Base::find($baseId);
                    if (!$base || !in_array($base->sector_id, $user->getAllowedSectorIds())) {
                        abort(response()->json(['status' => 'error', 'message' => 'No tiene permiso para filtrar por esta base.'], 403));
                    }
                } else {
                    if (!in_array($baseId, $user->getAllowedBaseIds())) {
                        abort(response()->json(['status' => 'error', 'message' => 'No tiene permiso para filtrar por esta base.'], 403));
                    }
                }
            }
            $query->whereHas('sujetos', function($q) use ($baseId) {
                $q->where(function($sq) use ($baseId) {
                    $sq->where(fn($sb) => $sb->where('sujeto_type', Base::class)->where('sujeto_id', $baseId))
                       ->orWhere(fn($sp) => $sp->where('sujeto_type', Persona::class)->whereHasMorph('sujeto', [Persona::class], function($pq) use ($baseId) {
                           $pq->whereHas('basePersonas', fn($bp) => $bp->where('base_id', $baseId));
                       }));
                });
            });
        }

        if ($request->has('tipo_actividad_id')) {
            $query->where('tipo_actividad_id', $request->tipo_actividad_id);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        // 3. Ordenamiento y Paginación
        $sortBy = $request->get('sort_by', 'fecha_actividad');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => collect($paginator->items())->map(fn($a) => $this->formatResource($a)),
            'meta'   => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ]
        ]);
    }

    /**
     * Crear una nueva actividad.
     */
    public function store(StoreActividadRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            try {
                $fotoPortadaPath = null;
                if ($request->hasFile('foto_portada')) {
                    $fotoPortadaPath = $request->file('foto_portada')->store('actividades/portadas', 'public');
                }

                $actividad = Actividad::create([
                    'titulo'            => $request->titulo,
                    'descripcion'       => $request->descripcion,
                    'fecha_actividad'   => $request->fecha_actividad,
                    'tipo_actividad_id' => $request->tipo_actividad_id,
                    'estado'            => $request->estado,
                    'es_publica'        => $request->boolean('es_publica', false),
                    'foto_portada_path' => $fotoPortadaPath,
                    'created_by'        => auth()->id(),
                ]);

                $sujetos = $request->sujetos;
                if (is_string($sujetos)) {
                    $sujetos = json_decode($sujetos, true);
                }

                if (is_array($sujetos)) {
                    foreach ($sujetos as $sujetoData) {
                        ActividadSujeto::create([
                            'actividad_id' => $actividad->id,
                            'sujeto_id' => $sujetoData['sujeto_id'],
                            'sujeto_type' => $this->mapSujetoType($sujetoData['sujeto_type']),
                            'descripcion_ejecucion' => $sujetoData['descripcion_ejecucion'] ?? null,
                            'evidencias' => $sujetoData['evidencias'] ?? null,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }

                Cache::forget('public_landing_data');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Actividad creada correctamente',
                    'data' => $this->formatResource($actividad->load(['tipoActividad', 'sujetos.sujeto']))
                ], 201);
            } catch (\Exception $e) {
                Log::error("Error al crear actividad: " . $e->getMessage());
                return response()->json(['status' => 'error', 'message' => 'No se pudo crear la actividad.'], 500);
            }
        });
    }

    /**
     * Ver detalle de una actividad.
     */
    public function show(Actividad $actividad): JsonResponse
    {
        $this->checkAccess($actividad);
        $actividad->load(['tipoActividad', 'sujetos.sujeto']);

        return response()->json([
            'status' => 'success',
            'data' => $this->formatResource($actividad)
        ]);
    }

    /**
     * Actualizar una actividad.
     */
    public function update(UpdateActividadRequest $request, Actividad $actividad): JsonResponse
    {
        $this->checkAccess($actividad);

        return DB::transaction(function () use ($request, $actividad) {
            try {
                $updateData = $request->only([
                    'titulo', 'descripcion', 'fecha_actividad',
                    'tipo_actividad_id', 'estado'
                ]);

                if ($request->hasFile('foto_portada')) {
                    // Eliminar foto anterior si existe
                    if ($actividad->foto_portada_path) {
                        Storage::disk('public')->delete($actividad->foto_portada_path);
                    }
                    $updateData['foto_portada_path'] = $request->file('foto_portada')->store('actividades/portadas', 'public');
                }

                $actividad->update($updateData);

                // Actualizar campo booleano por separado para evitar problemas con `only()`
                if ($request->has('es_publica')) {
                    $actividad->es_publica = $request->boolean('es_publica');
                    $actividad->save();
                }

                $sujetos = $request->sujetos;
                if (is_string($sujetos)) {
                    $sujetos = json_decode($sujetos, true);
                }

                if (is_array($sujetos)) {
                    // Por simplicidad, reemplazamos los sujetos. En una app real podrías hacer un sync.
                    $actividad->sujetos()->delete();
                    foreach ($sujetos as $sujetoData) {
                        ActividadSujeto::create([
                            'actividad_id' => $actividad->id,
                            'sujeto_id' => $sujetoData['sujeto_id'],
                            'sujeto_type' => $this->mapSujetoType($sujetoData['sujeto_type']),
                            'descripcion_ejecucion' => $sujetoData['descripcion_ejecucion'] ?? null,
                            'evidencias' => $sujetoData['evidencias'] ?? null,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }

                Cache::forget('public_landing_data');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Actividad actualizada correctamente',
                    'data' => $this->formatResource($actividad->load(['tipoActividad', 'sujetos.sujeto']))
                ]);
            } catch (\Exception $e) {
                Log::error("Error al actualizar actividad: " . $e->getMessage());
                return response()->json(['status' => 'error', 'message' => 'No se pudo actualizar la actividad.'], 500);
            }
        });
    }

    /**
     * Eliminar una actividad.
     */
    public function destroy(Actividad $actividad): JsonResponse
    {
        $this->checkAccess($actividad);

        try {
            $actividad->delete();
            Cache::forget('public_landing_data');
            return response()->json([
                'status' => 'success',
                'message' => 'Actividad eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error("Error al eliminar actividad: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo eliminar la actividad.'], 500);
        }
    }

    /**
     * Mapear string a clase para el polimorfismo.
     */
    private function mapSujetoType(string $type): string
    {
        return match ($type) {
            'persona' => Persona::class,
            'base' => Base::class,
            'sector' => Sector::class,
            default => throw new \InvalidArgumentException("Tipo de sujeto no válido"),
        };
    }

    /**
     * Verificar acceso a una actividad específica.
     */
    private function checkAccess(Actividad $actividad): void
    {
        $user = auth()->user();
        if ($user->hasPermissionTo('actividades:manage-all') || $actividad->created_by === $user->id) {
            return;
        }

        // Lógica de validación similar al index pero para un solo registro
        // Para simplificar, si el index ya filtra, el show/update/destroy debería ser consistente.
        // Implementamos un check rápido:
        $hasAccess = false;
        $actividad->load('sujetos.sujeto');

        if ($user->hasPermissionTo('actividades:manage-sector')) {
            $allowedSectors = $user->getAllowedSectorIds();
            foreach ($actividad->sujetos as $as) {
                if ($as->sujeto_type === Sector::class && in_array($as->sujeto_id, $allowedSectors)) $hasAccess = true;
                if ($as->sujeto_type === Base::class && in_array($as->sujeto->sector_id, $allowedSectors)) $hasAccess = true;
                if ($as->sujeto_type === Persona::class) {
                    $p = $as->sujeto;
                    if ($p->sectorPersonas()->whereIn('sector_id', $allowedSectors)->exists()) $hasAccess = true;
                    if ($p->basePersonas()->whereHas('base', fn($b) => $b->whereIn('sector_id', $allowedSectors))->exists()) $hasAccess = true;
                }
                if ($hasAccess) break;
            }
        } elseif ($user->hasPermissionTo('actividades:manage-base')) {
            $allowedBases = $user->getAllowedBaseIds();
            foreach ($actividad->sujetos as $as) {
                if ($as->sujeto_type === Base::class && in_array($as->sujeto_id, $allowedBases)) $hasAccess = true;
                if ($as->sujeto_type === Persona::class) {
                    $p = $as->sujeto;
                    if ($p->basePersonas()->whereIn('base_id', $allowedBases)->exists()) $hasAccess = true;
                }
                if ($hasAccess) break;
            }
        }

        if (!$hasAccess) {
            abort(response()->json(['status' => 'error', 'message' => 'No tiene permiso para acceder a esta actividad.'], 403));
        }
    }

    /**
     * Formatear el recurso para la respuesta API.
     */
    private function formatResource(Actividad $actividad): array
    {
        return [
            'id'               => $actividad->id,
            'titulo'           => $actividad->titulo,
            'descripcion'      => $actividad->descripcion,
            'fecha_actividad'  => $actividad->fecha_actividad?->toDateTimeString(),
            'tipo_actividad'   => [
                'id'     => $actividad->tipoActividad?->id,
                'nombre' => $actividad->tipoActividad?->nombre,
            ],
            'estado'           => $actividad->estado,
            'es_publica'       => (bool) $actividad->es_publica,
            'foto_portada_path' => $actividad->foto_portada_path,
            'foto_portada_url' => $actividad->foto_portada_path
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($actividad->foto_portada_path)
                : null,
            'sujetos' => $actividad->sujetos->map(fn($as) => [
                'id'                   => $as->id,
                'sujeto_id'            => $as->sujeto_id,
                'sujeto_type'          => strtolower(class_basename($as->sujeto_type)),
                'nombre_sujeto'        => $this->getSujetoName($as),
                'descripcion_ejecucion' => $as->descripcion_ejecucion,
                'evidencias'           => collect($as->evidencias)->map(function($ev) {
                    if (is_string($ev)) {
                        return [
                            'path' => $ev,
                            'url'  => \Illuminate\Support\Facades\Storage::disk('public')->url($ev)
                        ];
                    }
                    return $ev;
                }),
            ]),
            'created_at' => $actividad->created_at?->toDateTimeString(),
        ];
    }

    private function getSujetoName(ActividadSujeto $as): string
    {
        $sujeto = $as->sujeto;
        if (!$sujeto) return 'N/A';
        
        if ($sujeto instanceof Persona) return $sujeto->nombre_completo;
        if ($sujeto instanceof Base || $sujeto instanceof Sector) return $sujeto->nombre;
        
        return 'N/A';
    }
}
