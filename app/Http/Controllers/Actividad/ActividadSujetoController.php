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
}
