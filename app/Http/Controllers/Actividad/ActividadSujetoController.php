<?php

namespace App\Http\Controllers\Actividad;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\ActividadSujeto;
use App\Models\Base;
use App\Models\Persona;
use App\Models\Sector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ActividadSujetoController extends Controller
{
    /**
     * Asignar un sujeto a una actividad.
     */
    public function store(Request $request, Actividad $actividad): JsonResponse
    {
        $request->validate([
            'sujeto_id' => 'required|integer',
            'sujeto_type' => 'required|string|in:persona,base,sector',
            'descripcion_ejecucion' => 'nullable|string',
        ]);

        try {
            $sujetoTypeClass = $this->mapSujetoType($request->sujeto_type);

            // Verificar si ya está asignado
            $exists = ActividadSujeto::where('actividad_id', $actividad->id)
                ->where('sujeto_id', $request->sujeto_id)
                ->where('sujeto_type', $sujetoTypeClass)
                ->exists();

            if ($exists) {
                return response()->json(['status' => 'error', 'message' => 'Este sujeto ya está asignado a la actividad.'], 422);
            }

            $asignacion = ActividadSujeto::create([
                'actividad_id' => $actividad->id,
                'sujeto_id' => $request->sujeto_id,
                'sujeto_type' => $sujetoTypeClass,
                'descripcion_ejecucion' => $request->descripcion_ejecucion,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Sujeto asignado correctamente',
                'data' => $asignacion->load('sujeto')
            ], 201);
        } catch (\Exception $e) {
            Log::error("Error al asignar sujeto: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo realizar la asignación.'], 500);
        }
    }

    /**
     * Actualizar la ejecución y evidencias de un sujeto asignado.
     */
    public function update(Request $request, ActividadSujeto $asignacion): JsonResponse
    {
        $request->validate([
            'descripcion_ejecucion' => 'nullable|string',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'string' // Rutas de archivos ya subidos
        ]);

        try {
            $asignacion->update([
                'descripcion_ejecucion' => $request->descripcion_ejecucion,
                'evidencias' => $request->evidencias,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Información de ejecución actualizada',
                'data' => $asignacion
            ]);
        } catch (\Exception $e) {
            Log::error("Error al actualizar ejecución: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo actualizar la información.'], 500);
        }
    }

    /**
     * Eliminar la asignación de un sujeto.
     */
    public function destroy(ActividadSujeto $asignacion): JsonResponse
    {
        try {
            $asignacion->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Sujeto desvinculado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error("Error al eliminar asignación: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo eliminar la vinculación.'], 500);
        }
    }

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
     * Camino B: Registro Manual por el Coordinador (Admin)
     */
    public function marcarAsistenciaManual(Request $request, Actividad $actividad): JsonResponse
    {
        $request->validate([
            'persona_id' => 'required|integer|exists:personas,id',
        ]);

        try {
            // Verificar si ya existe registro para esta persona en esta actividad
            $asignacion = ActividadSujeto::where('actividad_id', $actividad->id)
                ->where('sujeto_id', $request->persona_id)
                ->where('sujeto_type', Persona::class)
                ->first();

            if ($asignacion && $asignacion->hora_asistencia !== null) {
                if ($asignacion->hora_salida !== null) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Esta persona ya ha registrado su ingreso y salida de este evento.',
                        'data' => $asignacion->load('sujeto')
                    ], 422);
                }

                $asignacion->update([
                    'hora_salida' => now(),
                    'updated_by' => auth()->id(),
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Salida registrada correctamente de forma manual.',
                    'tipo' => 'salida',
                    'data' => $asignacion->load('sujeto')
                ]);
            }

            $asignacion = ActividadSujeto::updateOrCreate(
                [
                    'actividad_id' => $actividad->id,
                    'sujeto_id' => $request->persona_id,
                    'sujeto_type' => Persona::class,
                ],
                [
                    'hora_asistencia' => now(),
                    'metodo_registro' => 'manual_admin',
                    'registrado_por' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]
            );

            // Si fue creado en este momento, setear created_by
            if ($asignacion->wasRecentlyCreated) {
                $asignacion->update(['created_by' => auth()->id()]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Asistencia registrada manualmente.',
                'tipo' => 'ingreso',
                'data' => $asignacion->load('sujeto')
            ]);
        } catch (\Exception $e) {
            Log::error("Error en asistencia manual: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo registrar la asistencia.'], 500);
        }
    }

    /**
     * Camino A: Auto-registro QR (Simpatizante)
     */
    public function marcarAsistenciaQR(Request $request, Actividad $actividad): JsonResponse
    {
        $request->validate([
            'latitud_usuario' => 'required|numeric',
            'longitud_usuario' => 'required|numeric',
            'browser_fingerprint' => 'required|string',
        ]);

        try {
            $user = auth()->user();
            if (!$user || !$user->persona_id) {
                return response()->json(['status' => 'error', 'message' => 'Usuario no vinculado a una persona.'], 403);
            }

            // 1. Regla Anti-Casa (Geofencing)
            if ($actividad->latitud && $actividad->longitud) {
                $distancia = $this->calcularDistancia(
                    $actividad->latitud,
                    $actividad->longitud,
                    $request->latitud_usuario,
                    $request->longitud_usuario
                );

                $radio = $actividad->radio_asistencia_metros ?? 100;

                if ($distancia > $radio) {
                    return response()->json([
                        'status' => 'error', 
                        'message' => 'Estás fuera del radio permitido para marcar tu asistencia o salida.'
                    ], 403);
                }
            }

            // Verificar si ya existe registro de asistencia para esta persona
            $asignacion = ActividadSujeto::where('actividad_id', $actividad->id)
                ->where('sujeto_id', $user->persona_id)
                ->where('sujeto_type', Persona::class)
                ->first();

            if ($asignacion && $asignacion->hora_asistencia !== null) {
                if ($asignacion->hora_salida !== null) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Ya has registrado tu ingreso y tu salida para este evento.',
                        'data' => $asignacion->load('sujeto')
                    ], 422);
                }

                // 2. Regla Anti-Amigo para Salida (Device Locking)
                $deviceUsado = ActividadSujeto::where('actividad_id', $actividad->id)
                    ->where('device_fingerprint', $request->browser_fingerprint)
                    ->whereDate('hora_asistencia', now()->toDateString())
                    ->where('sujeto_id', '!=', $user->persona_id)
                    ->exists();

                if ($deviceUsado) {
                    return response()->json([
                        'status' => 'error', 
                        'message' => 'Este dispositivo ya fue usado para registrar la asistencia de otra persona hoy.'
                    ], 403);
                }

                // Registrar Salida
                $asignacion->update([
                    'hora_salida' => now(),
                    'updated_by' => $user->id,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Salida registrada correctamente.',
                    'tipo' => 'salida',
                    'data' => $asignacion->load('sujeto')
                ]);
            }

            // 2. Regla Anti-Amigo para Ingreso (Device Locking)
            $deviceUsado = ActividadSujeto::where('actividad_id', $actividad->id)
                ->where('device_fingerprint', $request->browser_fingerprint)
                ->whereDate('hora_asistencia', now()->toDateString())
                ->where('sujeto_id', '!=', $user->persona_id)
                ->exists();

            if ($deviceUsado) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Este dispositivo ya fue usado para registrar la asistencia de otra persona hoy.'
                ], 403);
            }

            // 3. Registrar Ingreso
            $asignacion = ActividadSujeto::updateOrCreate(
                [
                    'actividad_id' => $actividad->id,
                    'sujeto_id' => $user->persona_id,
                    'sujeto_type' => Persona::class,
                ],
                [
                    'hora_asistencia' => now(),
                    'metodo_registro' => 'qr_self_service',
                    'latitud_capturada' => $request->latitud_usuario,
                    'longitud_capturada' => $request->longitud_usuario,
                    'device_fingerprint' => $request->browser_fingerprint,
                    'updated_by' => $user->id,
                ]
            );

            if ($asignacion->wasRecentlyCreated) {
                $asignacion->update(['created_by' => $user->id]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Asistencia registrada correctamente.',
                'tipo' => 'ingreso',
                'data' => $asignacion->load('sujeto')
            ]);

        } catch (\Exception $e) {
            Log::error("Error en asistencia QR: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Ocurrió un error al registrar la asistencia.'], 500);
        }
    }

    /**
     * Camino C: Registro Público por DNI (Sin Login)
     */
    public function marcarAsistenciaDNI(Request $request, Actividad $actividad): JsonResponse
    {
        $request->validate([
            'dni' => 'required|string|max:20',
            'latitud_usuario' => 'required|numeric',
            'longitud_usuario' => 'required|numeric',
            'browser_fingerprint' => 'required|string',
        ]);

        try {
            // Buscar la persona por DNI
            $persona = Persona::where('dni', $request->dni)->first();
            
            if (!$persona) {
                return response()->json(['status' => 'error', 'message' => 'DNI no encontrado en nuestros registros. Consulte con su responsable.'], 404);
            }

            // 1. Regla Anti-Casa (Geofencing)
            if ($actividad->latitud && $actividad->longitud) {
                $distancia = $this->calcularDistancia(
                    $actividad->latitud,
                    $actividad->longitud,
                    $request->latitud_usuario,
                    $request->longitud_usuario
                );

                $radio = $actividad->radio_asistencia_metros ?? 100;

                if ($distancia > $radio) {
                    return response()->json([
                        'status' => 'error', 
                        'message' => 'Estás fuera del radio permitido para marcar tu asistencia o salida.'
                    ], 403);
                }
            }

            // Verificar si ya existe en la lista de participantes de la actividad
            $asignacion = ActividadSujeto::where('actividad_id', $actividad->id)
                ->where('sujeto_id', $persona->id)
                ->where('sujeto_type', Persona::class)
                ->first();

            // RESTRICCIÓN SOLICITADA: Para DNI, la persona DEBE estar en la lista de sujetos previamente.
            if (!$asignacion) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Su DNI no está autorizado. Debe estar pre-registrado en la lista de participantes de esta actividad.'
                ], 403);
            }

            // Lógica de Salida
            if ($asignacion->hora_asistencia !== null) {
                if ($asignacion->hora_salida !== null) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Ya has registrado tu ingreso y tu salida para este evento.',
                        'data' => $asignacion->load('sujeto')
                    ], 422);
                }

                // 2. Regla Anti-Amigo para Salida (Device Locking)
                $deviceUsado = ActividadSujeto::where('actividad_id', $actividad->id)
                    ->where('device_fingerprint', $request->browser_fingerprint)
                    ->whereDate('hora_asistencia', now()->toDateString())
                    ->where('sujeto_id', '!=', $persona->id)
                    ->exists();

                if ($deviceUsado) {
                    return response()->json([
                        'status' => 'error', 
                        'message' => 'Este dispositivo ya fue usado para registrar la asistencia de otra persona hoy.'
                    ], 403);
                }

                // Registrar Salida
                $asignacion->update([
                    'hora_salida' => now(),
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Salida registrada correctamente por DNI.',
                    'tipo' => 'salida',
                    'data' => $asignacion->load('sujeto')
                ]);
            }

            // Lógica de Ingreso
            // 2. Regla Anti-Amigo para Ingreso (Device Locking)
            $deviceUsado = ActividadSujeto::where('actividad_id', $actividad->id)
                ->where('device_fingerprint', $request->browser_fingerprint)
                ->whereDate('hora_asistencia', now()->toDateString())
                ->where('sujeto_id', '!=', $persona->id)
                ->exists();

            if ($deviceUsado) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Este dispositivo ya fue usado para registrar la asistencia de otra persona hoy.'
                ], 403);
            }

            // 3. Registrar Ingreso (Solo actualizamos porque ya confirmamos que existe la asignación)
            $asignacion->update([
                'hora_asistencia' => now(),
                'metodo_registro' => 'qr_self_service',
                'latitud_capturada' => $request->latitud_usuario,
                'longitud_capturada' => $request->longitud_usuario,
                'device_fingerprint' => $request->browser_fingerprint,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Asistencia registrada correctamente por DNI.',
                'tipo' => 'ingreso',
                'data' => $asignacion->load('sujeto')
            ]);

        } catch (\Exception $e) {
            Log::error("Error en asistencia DNI: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Ocurrió un error al registrar la asistencia.'], 500);
        }
    }

    /**
     * Calcula la distancia en metros entre dos coordenadas GPS (Fórmula de Haversine)
     */
    private function calcularDistancia($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Radio de la tierra en metros

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }
}
