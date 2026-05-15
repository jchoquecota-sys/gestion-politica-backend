<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Base;
use App\Models\Persona;
use App\Models\Sector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Obtener estadísticas generales para el dashboard.
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = auth()->user();
        $hasFullAccess = $user->hasPermissionTo('dashboard:view-all');
        $allowedSectorIds = $user->getAllowedSectorIds();

        // 1. KPIs Básicos
        $totalSimpatizantes = Persona::when(!$hasFullAccess, function($q) use ($allowedSectorIds) {
            return $q->whereHas('sectorPersonas', function($sq) use ($allowedSectorIds) {
                $sq->whereIn('sector_id', $allowedSectorIds);
            });
        })->count();

        $totalBases = Base::when(!$hasFullAccess, function($q) use ($allowedSectorIds) {
            return $q->whereIn('sector_id', $allowedSectorIds);
        })->count();

        $totalActividades = Actividad::count();

        // 2. Actividades por Estado
        $actividadesPorEstado = Actividad::select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->get();

        // 3. Simpatizantes por Sector (Distribución)
        $simpatizantesPorSector = Sector::when(!$hasFullAccess, function($q) use ($allowedSectorIds) {
            return $q->whereIn('id', $allowedSectorIds);
        })
        ->withCount('personas')
        ->get()
        ->map(fn($s) => [
            'name' => $s->nombre,
            'value' => $s->personas_count
        ]);

        // 4. Crecimiento de Simpatizantes (Mensual)
        $crecimientoSimpatizantes = Persona::select(
            DB::raw("DATE_FORMAT(created_at, '%b') as mes_label"),
            DB::raw('MONTH(created_at) as mes_num'),
            DB::raw('count(*) as total')
        )
        ->groupBy('mes_num', 'mes_label')
        ->orderBy('mes_num')
        ->get()
        ->map(fn($p) => [
            'mes' => $p->mes_label,
            'total' => $p->total
        ]);

        // 5. Actividades por Mes (Crecimiento)
        $actividadesPorMes = Actividad::select(
            DB::raw("DATE_FORMAT(fecha_actividad, '%b') as mes_label"),
            DB::raw('MONTH(fecha_actividad) as mes_num'),
            DB::raw('count(*) as total')
        )
        ->groupBy('mes_num', 'mes_label')
        ->orderBy('mes_num')
        ->get()
        ->map(fn($a) => [
            'month' => $a->mes_label,
            'total' => $a->total
        ]);

        // 6. Personas por Base
        $personasPorBase = Base::when(!$hasFullAccess, function($q) use ($allowedSectorIds) {
            return $q->whereIn('sector_id', $allowedSectorIds);
        })
        ->withCount('personas')
        ->orderBy('personas_count', 'desc')
        ->take(10) // Mostrar las top 10 bases para no saturar
        ->get()
        ->map(fn($b) => [
            'base' => $b->nombre,
            'total' => $b->personas_count
        ]);

        $totalSectores = Sector::count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => [
                    'total_personas' => $totalSimpatizantes,
                    'total_bases' => $totalBases,
                    'total_sectores' => $totalSectores,
                    'total_actividades' => $totalActividades,
                ],
                'actividades_estados' => $actividadesPorEstado,
                'distribucion_sectores' => $simpatizantesPorSector,
                'crecimiento_mensual' => $crecimientoSimpatizantes,
                'actividades_crecimiento' => $actividadesPorMes,
                'personas_por_base' => $personasPorBase,
            ]
        ]);
    }

    /**
     * Obtener coordenadas de bases para el mapa.
     */
    public function getMapData(Request $request): JsonResponse
    {
        $user = auth()->user();
        $hasFullAccess = $user->hasPermissionTo('dashboard:view-all');
        $allowedSectorIds = $user->getAllowedSectorIds();

        $bases = Base::when(!$hasFullAccess, function($q) use ($allowedSectorIds) {
            return $q->whereIn('sector_id', $allowedSectorIds);
        })
        ->with(['sector', 'personas' => function($q) {
            $q->wherePivot('es_principal', true);
        }])
        ->get()
        ->map(fn($b) => [
            'id' => $b->id,
            'nombre' => $b->nombre,
            'lat' => $b->latitud,
            'lng' => $b->longitud,
            'sector' => $b->sector?->nombre,
            'responsable' => $b->personas->first()?->nombre_completo ?? 'Sin responsable asignado',
            'direccion' => $b->direccion
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $bases
        ]);
    }
}
