<?php

namespace App\Http\Controllers\Actividad;

use App\Helpers\AsistenciaHelper;
use App\Helpers\GeoHelper;
use App\Helpers\SujetoMapper;
use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\ActividadSujeto;
use App\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            $sujetoTypeClass = SujetoMapper::map($request->sujeto_type);

            $existente = ActividadSujeto::withTrashed()
                ->where('actividad_id', $actividad->id)
                ->where('sujeto_id', $request->sujeto_id)
                ->where('sujeto_type', $sujetoTypeClass)
                ->first();

            if ($existente && !$existente->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Este sujeto ya está asignado a la actividad.',
                ], 422);
            }

            if ($existente && $existente->trashed()) {
                $existente->restore();
                $existente->update([
                    'descripcion_ejecucion' => $request->descripcion_ejecucion,
                    'updated_by' => auth()->id(),
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Sujeto asignado correctamente',
                    'data' => $existente->load('sujeto'),
                ], 201);
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
                'data' => $asignacion->load('sujeto'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al asignar sujeto: ' . $e->getMessage());
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
            'evidencias.*' => 'string',
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
                'data' => $asignacion,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar ejecución: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo actualizar la información.'], 500);
        }
    }

    /**
     * Eliminar la asignación de un sujeto.
     */
    public function destroy(ActividadSujeto $asignacion): JsonResponse
    {
        try {
            // forceDelete para no chocar con el unique (actividad, sujeto)
            $asignacion->forceDelete();
            return response()->json([
                'status' => 'success',
                'message' => 'Sujeto desvinculado correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar asignación: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'No se pudo eliminar la vinculación.'], 500);
        }
    }

    /**
     * Camino B: Registro Manual por el Coordinador (Admin)
     */
    public function marcarAsistenciaManual(Request $request, Actividad $actividad): JsonResponse
    {
        $request->validate([
            'persona_id' => 'required|integer|exists:personas,id',
            'confirmar_salida' => 'sometimes|boolean',
        ]);

        if ($bloqueo = AsistenciaHelper::respuestaBloqueo($actividad, exigirVentana: false)) {
            return $bloqueo;
        }


        try {
            return DB::transaction(function () use ($request, $actividad) {
                $asignacion = ActividadSujeto::where('actividad_id', $actividad->id)
                    ->where('sujeto_id', $request->persona_id)
                    ->where('sujeto_type', Persona::class)
                    ->lockForUpdate()
                    ->first();

                if ($asignacion && $asignacion->hora_asistencia !== null) {
                    if ($asignacion->hora_salida !== null) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Esta persona ya ha registrado su ingreso y salida de este evento.',
                            'data' => $asignacion->load('sujeto'),
                        ], 422);
                    }

                    if (!$request->boolean('confirmar_salida')) {
                        return $this->respuestaConfirmacionSalida($asignacion);
                    }

                    $asignacion->update([
                        'hora_salida' => now(),
                        'updated_by' => auth()->id(),
                    ]);

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Salida registrada correctamente de forma manual.',
                        'tipo' => 'salida',
                        'data' => $asignacion->fresh()->load('sujeto'),
                    ]);
                }

                if ($asignacion) {
                    $asignacion->update([
                        'hora_asistencia' => now(),
                        'metodo_registro' => 'manual_admin',
                        'registrado_por' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);
                } else {
                    $asignacion = ActividadSujeto::create([
                        'actividad_id' => $actividad->id,
                        'sujeto_id' => $request->persona_id,
                        'sujeto_type' => Persona::class,
                        'hora_asistencia' => now(),
                        'metodo_registro' => 'manual_admin',
                        'registrado_por' => auth()->id(),
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Asistencia registrada manualmente.',
                    'tipo' => 'ingreso',
                    'data' => $asignacion->fresh()->load('sujeto'),
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error en asistencia manual: ' . $e->getMessage());
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
            'browser_fingerprint' => 'required|string|max:128',
            'confirmar_salida' => 'sometimes|boolean',
        ]);

        if ($bloqueo = AsistenciaHelper::respuestaBloqueo($actividad, exigirVentana: true)) {
            return $bloqueo;
        }

        try {
            $user = auth()->user();
            if (!$user || !$user->persona_id) {
                return response()->json(['status' => 'error', 'message' => 'Usuario no vinculado a una persona.'], 403);
            }

            if ($geo = $this->validarGeofencing($actividad, $request)) {
                return $geo;
            }

            return DB::transaction(function () use ($request, $actividad, $user) {
                $asignacion = ActividadSujeto::where('actividad_id', $actividad->id)
                    ->where('sujeto_id', $user->persona_id)
                    ->where('sujeto_type', Persona::class)
                    ->lockForUpdate()
                    ->first();

                if ($asignacion && $asignacion->hora_asistencia !== null) {
                    if ($asignacion->hora_salida !== null) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Ya has registrado tu ingreso y tu salida para este evento.',
                            'data' => $asignacion->load('sujeto'),
                        ], 422);
                    }

                    if (!$request->boolean('confirmar_salida')) {
                        return $this->respuestaConfirmacionSalida($asignacion);
                    }

                    if ($anti = $this->validarDispositivo($actividad, $request->browser_fingerprint, $user->persona_id)) {
                        return $anti;
                    }

                    $asignacion->update([
                        'hora_salida' => now(),
                        'updated_by' => $user->id,
                    ]);

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Salida registrada correctamente.',
                        'tipo' => 'salida',
                        'data' => $asignacion->fresh()->load('sujeto'),
                    ]);
                }

                if ($anti = $this->validarDispositivo($actividad, $request->browser_fingerprint, $user->persona_id)) {
                    return $anti;
                }

                if ($asignacion) {
                    $asignacion->update([
                        'hora_asistencia' => now(),
                        'metodo_registro' => 'qr_self_service',
                        'latitud_capturada' => $request->latitud_usuario,
                        'longitud_capturada' => $request->longitud_usuario,
                        'device_fingerprint' => $request->browser_fingerprint,
                        'updated_by' => $user->id,
                    ]);
                } else {
                    $asignacion = ActividadSujeto::create([
                        'actividad_id' => $actividad->id,
                        'sujeto_id' => $user->persona_id,
                        'sujeto_type' => Persona::class,
                        'hora_asistencia' => now(),
                        'metodo_registro' => 'qr_self_service',
                        'latitud_capturada' => $request->latitud_usuario,
                        'longitud_capturada' => $request->longitud_usuario,
                        'device_fingerprint' => $request->browser_fingerprint,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Asistencia registrada correctamente.',
                    'tipo' => 'ingreso',
                    'data' => $asignacion->fresh()->load('sujeto'),
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error en asistencia QR: ' . $e->getMessage());
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
            'browser_fingerprint' => 'required|string|max:128',
            'confirmar_salida' => 'sometimes|boolean',
        ]);

        if ($bloqueo = AsistenciaHelper::respuestaBloqueo($actividad, exigirVentana: true)) {
            return $bloqueo;
        }

        try {
            $persona = Persona::where('dni', $request->dni)->first();

            // Mensaje genérico para no enumerar DNIs existentes vs no autorizados
            $mensajeNoAutorizado = 'No se pudo autorizar este DNI para la actividad. Verifique el número o consulte con su responsable.';

            if (!$persona) {
                return response()->json(['status' => 'error', 'message' => $mensajeNoAutorizado], 403);
            }

            if ($geo = $this->validarGeofencing($actividad, $request)) {
                return $geo;
            }

            return DB::transaction(function () use ($request, $actividad, $persona, $mensajeNoAutorizado) {
                $asignacion = ActividadSujeto::where('actividad_id', $actividad->id)
                    ->where('sujeto_id', $persona->id)
                    ->where('sujeto_type', Persona::class)
                    ->lockForUpdate()
                    ->first();

                // Si la persona existe en el padrón pero no estaba en la lista, se vincula al marcar
                if (!$asignacion) {
                    $asignacion = ActividadSujeto::create([
                        'actividad_id' => $actividad->id,
                        'sujeto_id' => $persona->id,
                        'sujeto_type' => Persona::class,
                    ]);
                    $asignacion = ActividadSujeto::where('id', $asignacion->id)->lockForUpdate()->first();
                }

                if ($asignacion->hora_asistencia !== null) {
                    if ($asignacion->hora_salida !== null) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Ya has registrado tu ingreso y tu salida para este evento.',
                            'data' => $asignacion->load('sujeto'),
                        ], 422);
                    }

                    if (!$request->boolean('confirmar_salida')) {
                        return $this->respuestaConfirmacionSalida($asignacion);
                    }

                    if ($anti = $this->validarDispositivo($actividad, $request->browser_fingerprint, $persona->id)) {
                        return $anti;
                    }

                    $asignacion->update(['hora_salida' => now()]);

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Salida registrada correctamente por DNI.',
                        'tipo' => 'salida',
                        'data' => $asignacion->fresh()->load('sujeto'),
                    ]);
                }

                if ($anti = $this->validarDispositivo($actividad, $request->browser_fingerprint, $persona->id)) {
                    return $anti;
                }

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
                    'data' => $asignacion->fresh()->load('sujeto'),
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error en asistencia DNI: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Ocurrió un error al registrar la asistencia.'], 500);
        }
    }

    private function respuestaConfirmacionSalida(ActividadSujeto $asignacion): JsonResponse
    {
        return response()->json([
            'status' => 'confirmation_required',
            'message' => 'Ya tienes ingreso registrado. Confirma para marcar tu salida.',
            'tipo' => 'salida',
            'requires_confirmation' => true,
            'data' => $asignacion->load('sujeto'),
        ], 409);
    }

    private function validarGeofencing(Actividad $actividad, Request $request): ?JsonResponse
    {
        if (!$actividad->latitud || !$actividad->longitud) {
            return null;
        }

        $distancia = GeoHelper::calcularDistancia(
            (float) $actividad->latitud,
            (float) $actividad->longitud,
            (float) $request->latitud_usuario,
            (float) $request->longitud_usuario
        );

        $radio = $actividad->radio_asistencia_metros ?? 100;

        if ($distancia > $radio) {
            return response()->json([
                'status' => 'error',
                'message' => 'Estás fuera del radio permitido para marcar tu asistencia o salida.',
            ], 403);
        }

        return null;
    }

    private function validarDispositivo(Actividad $actividad, string $fingerprint, int $personaId): ?JsonResponse
    {
        $deviceUsado = ActividadSujeto::where('actividad_id', $actividad->id)
            ->where('device_fingerprint', $fingerprint)
            ->whereDate('hora_asistencia', now()->toDateString())
            ->where('sujeto_id', '!=', $personaId)
            ->exists();

        if ($deviceUsado) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este dispositivo ya fue usado para registrar la asistencia de otra persona hoy.',
            ], 403);
        }

        return null;
    }
}
