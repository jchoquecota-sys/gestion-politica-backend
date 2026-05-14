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
use Illuminate\Support\Facades\Storage;

class PersonaController extends Controller
{
    /**
     * Listar todas las personas.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Persona::query();
        $user = auth()->user();

        if (!$user->hasPermissionTo('personas:list-all')) {
            if ($user->hasPermissionTo('personas:list-only-sector')) {
                $allowedSectors = $user->getAllowedSectorIds();
                $query->where(function ($q) use ($allowedSectors, $user) {
                    $q->whereHas('sectorPersonas', function ($sq) use ($allowedSectors) {
                        $sq->whereIn('sector_id', $allowedSectors);
                    })->orWhereHas('basePersonas.base', function ($bq) use ($allowedSectors) {
                        $bq->whereIn('sector_id', $allowedSectors);
                    })->orWhere('created_by', $user->id);
                });
            } else {
                $allowedBases = $user->getAllowedBaseIds();
                $query->where(function ($q) use ($allowedBases, $user) {
                    $q->whereHas('basePersonas', function ($bq) use ($allowedBases) {
                        $bq->whereIn('base_id', $allowedBases);
                    })->orWhere('created_by', $user->id);
                });
            }
        }

        // Filtros explícitos
        if ($request->has('sector_id')) {
            $sectorId = (int) $request->sector_id;
            if (!$user->hasPermissionTo('personas:list-all')) {
                if (!in_array($sectorId, $user->getAllowedSectorIds())) {
                    return response()->json(['status' => 'error', 'message' => 'No tiene permiso para filtrar por este sector.'], 403);
                }
            }
            $query->where(function($q) use ($sectorId) {
                $q->whereHas('sectorPersonas', fn($sq) => $sq->where('sector_id', $sectorId))
                  ->orWhereHas('basePersonas.base', fn($bq) => $bq->where('sector_id', $sectorId));
            });
        }

        if ($request->has('base_id')) {
            $baseId = (int) $request->base_id;
            if (!$user->hasPermissionTo('personas:list-all')) {
                if ($user->hasPermissionTo('personas:list-only-sector')) {
                    $base = \App\Models\Base::find($baseId);
                    if (!$base || !in_array($base->sector_id, $user->getAllowedSectorIds())) {
                        return response()->json(['status' => 'error', 'message' => 'No tiene permiso para filtrar por esta base.'], 403);
                    }
                } else {
                    if (!in_array($baseId, $user->getAllowedBaseIds())) {
                        return response()->json(['status' => 'error', 'message' => 'No tiene permiso para filtrar por esta base.'], 403);
                    }
                }
            }
            $query->whereHas('basePersonas', fn($bq) => $bq->where('base_id', $baseId));
        }

        // 3. Búsqueda Global (Opcional)
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombres', 'like', "%{$search}%")
                  ->orWhere('apellidos', 'like', "%{$search}%")
                  ->orWhere('dni', 'like', "%{$search}%");
            });
        }

        // 4. Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $allowedSorts = ['nombres', 'apellidos', 'dni', 'created_at'];
        
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // 5. Paginación
        $perPage = $request->get('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => collect($paginator->items())->map(fn($p) => $this->formatResource($p)),
            'meta'   => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ]
        ]);
    }

    /**
     * Crear una nueva persona.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombres'          => 'required|string|max:255',
            'apellidos'        => 'required|string|max:255',
            'dni'              => 'nullable|string|max:8|unique:personas,dni',
            'celular'          => 'nullable|string|max:15',
            'email'            => 'nullable|email|unique:personas,email',
            'direccion'        => 'nullable|string',
            'fecha_nacimiento' => 'nullable|date',
            'foto'             => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            if ($request->hasFile('foto')) {
                $path = $request->file('foto')->store('personas', 'public');
                $validated['foto_path'] = $path;
            }

            $persona = Persona::create($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'Persona registrada correctamente',
                'data'    => $this->formatResource($persona)
            ], 201);
        } catch (\Exception $e) {
            Log::error("Error al crear persona: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo registrar la persona.'], 500);
        }
    }

    /**
     * Ver detalle de una persona.
     */
    public function show(Persona $persona): JsonResponse
    {
        $this->checkPersonaAccess($persona);

        return response()->json([
            'status' => 'success',
            'data'   => $this->formatResource($persona)
        ]);
    }

    /**
     * Actualizar datos de una persona.
     */
    public function update(Request $request, Persona $persona): JsonResponse
    {
        $this->checkPersonaAccess($persona);

        $validated = $request->validate([
            'nombres'          => 'required|string|max:255',
            'apellidos'        => 'required|string|max:255',
            'dni'              => "nullable|string|max:8|unique:personas,dni,{$persona->id}",
            'celular'          => 'nullable|string|max:15',
            'email'            => "nullable|email|unique:personas,email,{$persona->id}",
            'direccion'        => 'nullable|string',
            'fecha_nacimiento' => 'nullable|date',
            'foto'             => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            if ($request->hasFile('foto')) {
                // Eliminar foto anterior si existe
                if ($persona->foto_path && Storage::disk('public')->exists($persona->foto_path)) {
                    Storage::disk('public')->delete($persona->foto_path);
                }
                
                $path = $request->file('foto')->store('personas', 'public');
                $validated['foto_path'] = $path;
            }

            $persona->update($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'Datos actualizados correctamente',
                'data'    => $this->formatResource($persona)
            ]);
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
        $this->checkPersonaAccess($persona);

        $persona->delete();
        return response()->json([
            'status'  => 'success',
            'message' => 'Persona eliminada correctamente'
        ]);
    }

    /**
     * Check if the user has access to manage this persona.
     */
    private function checkPersonaAccess(Persona $persona): void
    {
        $user = auth()->user();

        if ($user->hasPermissionTo('personas:list-all')) {
            return;
        }

        // Siempre puede acceder si es el creador (útil para cuando recién la crea y aún no la vincula)
        if ($persona->created_by === $user->id) {
            return;
        }

        if ($user->hasPermissionTo('personas:list-only-sector')) {
            $allowedSectors = $user->getAllowedSectorIds();
            $persona->loadMissing(['sectorPersonas', 'basePersonas.base']);
            
            $hasSector = $persona->sectorPersonas->whereIn('sector_id', $allowedSectors)->isNotEmpty();
            $hasBaseInSector = $persona->basePersonas->filter(function($bp) use ($allowedSectors) {
                return $bp->base && in_array($bp->base->sector_id, $allowedSectors);
            })->isNotEmpty();

            if (!$hasSector && !$hasBaseInSector) {
                abort(response()->json(['status' => 'error', 'message' => 'No tiene permiso para gestionar esta persona.'], 403));
            }
        } else {
            $allowedBases = $user->getAllowedBaseIds();
            $persona->loadMissing('basePersonas');
            $hasBase = $persona->basePersonas->whereIn('base_id', $allowedBases)->isNotEmpty();

            if (!$hasBase) {
                abort(response()->json(['status' => 'error', 'message' => 'No tiene permiso para gestionar esta persona.'], 403));
            }
        }
    }

    /**
     * Formatear el recurso.
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
            'foto_path'       => $persona->foto_path,
            'foto_url'        => $persona->foto_path ? Storage::disk('public')->url($persona->foto_path) : null,
            'created_at'      => $persona->created_at?->toDateTimeString(),
        ];
    }
}
