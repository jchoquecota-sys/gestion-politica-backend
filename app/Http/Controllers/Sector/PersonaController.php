<?php

namespace App\Http\Controllers\Sector;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use App\Models\BasePersona;
use App\Models\SectorPersona;
use App\Http\Requests\Sector\StorePersonaRequest;
use App\Http\Requests\Sector\UpdatePersonaRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Exportar padrón a CSV UTF-8 con BOM (Excel respeta ñ y acentos).
     */
    public function export(Request $request): StreamedResponse|JsonResponse
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

        if ($request->filled('sector_id')) {
            $sectorId = (int) $request->sector_id;
            if (!$user->hasPermissionTo('personas:list-all')
                && !in_array($sectorId, $user->getAllowedSectorIds(), true)) {
                return response()->json(['status' => 'error', 'message' => 'No tiene permiso para filtrar por este sector.'], 403);
            }
            $query->where(function ($q) use ($sectorId) {
                $q->whereHas('sectorPersonas', fn ($sq) => $sq->where('sector_id', $sectorId))
                    ->orWhereHas('basePersonas.base', fn ($bq) => $bq->where('sector_id', $sectorId));
            });
        }

        if ($request->filled('base_id')) {
            $baseId = (int) $request->base_id;
            $query->whereHas('basePersonas', fn ($bq) => $bq->where('base_id', $baseId));
        }

        if ($request->filled('search')) {
            $search = (string) $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombres', 'like', "%{$search}%")
                    ->orWhere('apellidos', 'like', "%{$search}%")
                    ->orWhere('dni', 'like', "%{$search}%");
            });
        }

        $query->orderBy('apellidos')->orderBy('nombres');
        $filename = 'padron-personas-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            // BOM UTF-8: sin esto Excel en Windows muestra "QuiÃ±onez" en lugar de "Quiñonez"
            fwrite($out, "\xEF\xBB\xBF");

            // Separador ; compatible con Excel en español
            fputcsv($out, ['Nombres', 'Apellidos', 'DNI', 'Celular', 'Email'], ';');

            $query->chunk(500, function ($personas) use ($out) {
                foreach ($personas as $persona) {
                    fputcsv($out, [
                        (string) $persona->nombres,
                        (string) $persona->apellidos,
                        (string) ($persona->dni ?? ''),
                        (string) ($persona->celular ?? ''),
                        (string) ($persona->email ?? ''),
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Crear una nueva persona.
     */
    public function store(StorePersonaRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
    public function update(UpdatePersonaRequest $request, Persona $persona): JsonResponse
    {
        $this->checkPersonaAccess($persona);

        $validated = $request->validated();

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
     * Importar personas desde CSV.
     * Columnas aceptadas (flexible): nombres, apellidos, dni, celular
     * o "nombres y apellidos" / nombre_completo.
     * replace=1 reemplaza el padrón activo (soft-delete + insert).
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            // No usar mimes:csv — en Windows el MIME suele ser application/vnd.ms-excel
            'file' => 'required|file|max:5120',
            'replace' => 'sometimes',
        ]);

        $uploaded = $request->file('file');
        $ext = strtolower((string) $uploaded->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'txt'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'El archivo debe ser CSV (.csv o .txt).',
            ], 422);
        }

        $replace = filter_var($request->input('replace', true), FILTER_VALIDATE_BOOLEAN);
        $path = $uploaded->getRealPath();
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return response()->json(['status' => 'error', 'message' => 'No se pudo leer el archivo CSV.'], 422);
        }

        // Excel (Windows) suele exportar en Windows-1252; normalizamos a UTF-8
        // para conservar ñ, tildes y diéresis en nombres/apellidos.
        $csv = $this->csvContentsToUtf8($raw);

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return response()->json(['status' => 'error', 'message' => 'No se pudo leer el archivo CSV.'], 422);
        }
        fwrite($handle, $csv);
        rewind($handle);

        $delimiter = ',';
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return response()->json(['status' => 'error', 'message' => 'El CSV está vacío.'], 422);
        }
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        }
        if (substr_count($firstLine, "\t") > substr_count($firstLine, $delimiter)) {
            $delimiter = "\t";
        }
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            return response()->json(['status' => 'error', 'message' => 'No se pudo leer la cabecera del CSV.'], 422);
        }

        // Solo normalizar cabeceras para mapear columnas; los datos conservan acentos.
        $header = array_map(function ($h) {
            $h = mb_strtolower(trim((string) $h), 'UTF-8');
            $h = str_replace(
                ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'],
                ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'u', 'n'],
                $h
            );
            return preg_replace('/\s+/', '_', $h);
        }, $header);

        $map = [
            'nombres' => null,
            'apellidos' => null,
            'dni' => null,
            'celular' => null,
            'nombre_completo' => null,
        ];
        foreach ($header as $i => $col) {
            if (in_array($col, ['nombres', 'nombre'], true)) {
                $map['nombres'] = $i;
            } elseif (in_array($col, ['apellidos', 'apellido'], true)) {
                $map['apellidos'] = $i;
            } elseif (in_array($col, ['dni', 'documento', 'doc'], true)) {
                $map['dni'] = $i;
            } elseif (in_array($col, ['celular', 'telefono', 'tel', 'phone', 'movil'], true)) {
                $map['celular'] = $i;
            } elseif (in_array($col, ['nombres_y_apellidos', 'nombre_completo', 'nombres_apellidos', 'nombre'], true)
                || str_contains($col, 'nombres_y_apellidos')
                || str_contains($col, 'nombre_completo')) {
                $map['nombre_completo'] = $i;
            }
        }

        // Si no hay cabeceras reconocidas, asumir orden: N°, NOMBRES Y APELLIDOS, DNI, CELULAR
        if ($map['dni'] === null && $map['nombres'] === null && $map['nombre_completo'] === null) {
            if (count($header) >= 4) {
                $map['nombre_completo'] = 1;
                $map['dni'] = 2;
                $map['celular'] = 3;
            } elseif (count($header) >= 3) {
                $map['nombre_completo'] = 0;
                $map['dni'] = 1;
                $map['celular'] = 2;
            }
        }

        $rows = [];
        $seenDni = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $nombres = $map['nombres'] !== null
                ? $this->sanitizeUtf8Name((string) ($data[$map['nombres']] ?? ''))
                : '';
            $apellidos = $map['apellidos'] !== null
                ? $this->sanitizeUtf8Name((string) ($data[$map['apellidos']] ?? ''))
                : '';
            if (($nombres === '' || $apellidos === '') && $map['nombre_completo'] !== null) {
                $full = $this->sanitizeUtf8Name((string) ($data[$map['nombre_completo']] ?? ''));
                // Saltar si es fila de índice solo numérico
                if ($full === '' || preg_match('/^\d+$/u', $full)) {
                    continue;
                }
                $parts = preg_split('/\s+/u', $full) ?: [];
                if (count($parts) === 1) {
                    $nombres = $parts[0];
                    $apellidos = 'NOMBRE';
                } else {
                    $nombres = array_shift($parts);
                    $apellidos = implode(' ', $parts);
                }
            }

            if ($nombres === '' && $apellidos === '') {
                continue;
            }
            if ($nombres === '') {
                $nombres = 'SIN';
            }
            if ($apellidos === '') {
                $apellidos = 'NOMBRE';
            }

            $dni = $map['dni'] !== null ? preg_replace('/\D+/', '', (string) ($data[$map['dni']] ?? '')) : '';
            $dni = $dni !== '' ? substr($dni, 0, 8) : null;
            if ($dni !== null) {
                if (isset($seenDni[$dni])) {
                    continue;
                }
                $seenDni[$dni] = true;
            }

            $celular = $map['celular'] !== null ? preg_replace('/\D+/', '', (string) ($data[$map['celular']] ?? '')) : '';
            $celular = $celular !== '' ? substr($celular, 0, 15) : null;

            $rows[] = [
                'nombres' => mb_substr($nombres, 0, 150, 'UTF-8'),
                'apellidos' => mb_substr($apellidos, 0, 150, 'UTF-8'),
                'dni' => $dni,
                'celular' => $celular,
            ];
        }
        fclose($handle);

        if (count($rows) === 0) {
            return response()->json(['status' => 'error', 'message' => 'No se encontraron filas válidas en el CSV.'], 422);
        }

        try {
            $inserted = 0;
            DB::transaction(function () use ($rows, $replace, &$inserted) {
                $now = now();
                $personaClass = Persona::class;

                if ($replace) {
                    DB::table('users')->whereNotNull('persona_id')->update(['persona_id' => null]);

                    DB::table('actividad_sujetos')
                        ->where('sujeto_type', $personaClass)
                        ->whereNull('deleted_at')
                        ->update(['deleted_at' => $now, 'updated_at' => $now]);

                    if (\Illuminate\Support\Facades\Schema::hasTable('base_personas')) {
                        DB::table('base_personas')->delete();
                    }
                    if (\Illuminate\Support\Facades\Schema::hasTable('sector_personas')) {
                        DB::table('sector_personas')->delete();
                    }

                    DB::table('personas')->whereNull('deleted_at')->update([
                        'deleted_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('personas')
                        ->whereNotNull('deleted_at')
                        ->whereNotNull('dni')
                        ->update(['dni' => null, 'email' => null]);
                }

                foreach ($rows as $row) {
                    if (!$replace && !empty($row['dni'])) {
                        $exists = Persona::withTrashed()->where('dni', $row['dni'])->first();
                        if ($exists) {
                            if ($exists->trashed()) {
                                $exists->restore();
                            }
                            $exists->update([
                                'nombres' => $row['nombres'],
                                'apellidos' => $row['apellidos'],
                                'celular' => $row['celular'],
                                'updated_by' => auth()->id(),
                            ]);
                            continue;
                        }
                    }

                    DB::table('personas')->insert([
                        'dni' => $row['dni'],
                        'nombres' => $row['nombres'],
                        'apellidos' => $row['apellidos'],
                        'celular' => $row['celular'],
                        'created_by' => auth()->id(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $inserted++;
                }
            });

            return response()->json([
                'status' => 'success',
                'message' => $replace
                    ? "Padrón reemplazado. Se importaron {$inserted} personas."
                    : "Importación completada. Se agregaron/actualizaron registros ({$inserted} nuevos).",
                'data' => [
                    'inserted' => $inserted,
                    'total_filas' => count($rows),
                    'replace' => $replace,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error importando personas CSV: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo importar el CSV.'], 500);
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

    /**
     * Normaliza el contenido del CSV a UTF-8 (Excel Windows-1252 / ISO-8859-1 / UTF-16).
     */
    private function csvContentsToUtf8(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            return substr($raw, 3);
        }

        if (str_starts_with($raw, "\xFF\xFE")) {
            $converted = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');

            return $converted === false ? $raw : $converted;
        }

        if (str_starts_with($raw, "\xFE\xFF")) {
            $converted = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');

            return $converted === false ? $raw : $converted;
        }

        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        foreach (['Windows-1252', 'ISO-8859-1'] as $enc) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', $enc);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        $fallback = @iconv('Windows-1252', 'UTF-8//TRANSLIT', $raw);

        return $fallback !== false ? $fallback : $raw;
    }

    /**
     * Limpia espacios y normaliza Unicode NFC sin quitar ñ ni acentos.
     */
    private function sanitizeUtf8Name(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = (string) mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if (is_string($normalized) && $normalized !== '') {
                $value = $normalized;
            }
        }

        // Quitar caracteres de control, conservar letras (incl. ñ/á), números, espacios y signos comunes.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
