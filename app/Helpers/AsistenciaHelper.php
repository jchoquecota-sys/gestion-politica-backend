<?php

namespace App\Helpers;

use App\Models\Actividad;
use Illuminate\Http\JsonResponse;

class AsistenciaHelper
{
    /** Horas antes de fecha_actividad en que se abre el check-in (QR/DNI). */
    public const VENTANA_ANTES_HORAS = 2;

    /** Horas después de fecha_actividad en que se cierra el check-in (QR/DNI). */
    public const VENTANA_DESPUES_HORAS = 6;

    /**
     * @return string|null Mensaje de error, o null si está permitido.
     */
    public static function motivoBloqueo(Actividad $actividad, bool $exigirVentana = true): ?string
    {
        if ($actividad->estado === 'borrador') {
            return 'Esta actividad aún está en borrador. La asistencia no está habilitada.';
        }

        if ($actividad->estado === 'cancelada') {
            return 'Esta actividad fue cancelada. No se puede registrar asistencia.';
        }

        if ($actividad->estado !== 'creada') {
            return 'La asistencia no está habilitada para el estado actual de la actividad.';
        }

        if ($exigirVentana && $actividad->fecha_actividad) {
            $inicio = $actividad->fecha_actividad->copy()->subHours(self::VENTANA_ANTES_HORAS);
            $fin = $actividad->fecha_actividad->copy()->addHours(self::VENTANA_DESPUES_HORAS);

            if (now()->lt($inicio)) {
                return 'La ventana de asistencia aún no abre. Podrás marcar desde '
                    . $inicio->timezone(config('app.timezone'))->format('d/m/Y H:i') . '.';
            }

            if (now()->gt($fin)) {
                return 'La ventana de asistencia ya cerró ('
                    . $fin->timezone(config('app.timezone'))->format('d/m/Y H:i') . ').';
            }
        }

        return null;
    }

    public static function respuestaBloqueo(Actividad $actividad, bool $exigirVentana = true): ?JsonResponse
    {
        $motivo = self::motivoBloqueo($actividad, $exigirVentana);

        if ($motivo === null) {
            return null;
        }

        return response()->json([
            'status' => 'error',
            'message' => $motivo,
            'asistencia_abierta' => false,
        ], 422);
    }

    public static function estaAbierta(Actividad $actividad, bool $exigirVentana = true): bool
    {
        return self::motivoBloqueo($actividad, $exigirVentana) === null;
    }
}
