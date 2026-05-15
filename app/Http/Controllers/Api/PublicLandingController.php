<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Base;
use App\Models\LandingSetting;
use App\Models\Persona;
use App\Models\Sector;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublicLandingController extends Controller
{
    /**
     * Endpoint público principal de la landing page.
     * Agrega: configuración del candidato + KPIs + mapa + noticias + calendario.
     * Usa caché de 1 hora para soportar alto tráfico.
     */
    public function index(): JsonResponse
    {
        $data = Cache::remember('public_landing_data', 3600, function () {

            // ─── 1. Configuración del candidato ──────────────────────────────────
            $setting = LandingSetting::latest()->first();
            $candidate = $setting ? [
                'nombre_candidato'    => $setting->nombre_candidato,
                'cargo_candidatura'   => $setting->cargo_candidatura,
                'eslogan'             => $setting->eslogan,
                'biografia'           => $setting->biografia,
                'logo_url'            => $this->buildStorageUrl($setting->logo_path),
                'foto_principal_url'  => $this->buildStorageUrl($setting->foto_principal_path),
                'foto_secundaria_url' => $this->buildStorageUrl($setting->foto_secundaria_path),
                'redes_sociales'      => $setting->redes_sociales ?? [],
                'color_primario'      => $setting->color_primario,
                'color_secundario'    => $setting->color_secundario,
            ] : null;

            // ─── 2. KPIs y estadísticas globales ─────────────────────────────────
            $stats = [
                'total_simpatizantes' => Persona::count(),
                'total_bases'         => Base::count(),
                'total_sectores'      => Sector::count(),
                'total_actividades'   => Actividad::where('es_publica', true)->where('estado', 'creada')->count(),
            ];

            // ─── 3. Distribución de simpatizantes por sector ──────────────────────
            $distribucionSectores = Sector::withCount('personas')
                ->get()
                ->map(fn($s) => [
                    'name'  => $s->nombre,
                    'value' => $s->personas_count,
                ]);

            // ─── 4. Crecimiento mensual de simpatizantes ──────────────────────────
            $crecimientoMensual = Persona::select(
                DB::raw("DATE_FORMAT(created_at, '%b') as mes_label"),
                DB::raw('MONTH(created_at) as mes_num'),
                DB::raw('count(*) as total')
            )
            ->groupBy('mes_num', 'mes_label')
            ->orderBy('mes_num')
            ->get()
            ->map(fn($p) => ['mes' => $p->mes_label, 'total' => $p->total]);

            // ─── 5. Mapa de bases (solo nombre, sector, coordenadas — sin datos privados) ──
            $mapaBase = Base::with('sector')
                ->whereNotNull('latitud')
                ->whereNotNull('longitud')
                ->get()
                ->map(fn($b) => [
                    'id'       => $b->id,
                    'nombre'   => $b->nombre,
                    'lat'      => $b->latitud,
                    'lng'      => $b->longitud,
                    'sector'   => $b->sector?->nombre,
                    'direccion' => $b->direccion,
                ]);

            // ─── 6. Noticias: actividades públicas pasadas (o anunciadas) ─────────
            $noticias = Actividad::where('es_publica', true)
                ->where('estado', 'creada')
                ->with('tipoActividad')
                ->orderBy('fecha_actividad', 'desc')
                ->take(9)
                ->get()
                ->map(fn($a) => [
                    'id'               => $a->id,
                    'titulo'           => $a->titulo,
                    'descripcion'      => $a->descripcion,
                    'fecha_actividad'  => $a->fecha_actividad?->toDateTimeString(),
                    'tipo'             => $a->tipoActividad?->nombre,
                    'foto_portada_url' => $this->buildStorageUrl($a->foto_portada_path),
                ]);

            // ─── 7. Calendario: próximas actividades públicas ─────────────────────
            $calendario = Actividad::where('es_publica', true)
                ->where('estado', 'creada')
                ->where('fecha_actividad', '>=', now())
                ->with('tipoActividad')
                ->orderBy('fecha_actividad', 'asc')
                ->take(12)
                ->get()
                ->map(fn($a) => [
                    'id'              => $a->id,
                    'titulo'          => $a->titulo,
                    'descripcion'     => $a->descripcion,
                    'fecha_actividad' => $a->fecha_actividad?->toDateTimeString(),
                    'tipo'            => $a->tipoActividad?->nombre,
                ]);

            return [
                'candidate'            => $candidate,
                'stats'                => $stats,
                'distribucion_sectores' => $distribucionSectores->values()->all(),
                'crecimiento_mensual'  => $crecimientoMensual->values()->all(),
                'mapa_bases'           => $mapaBase->values()->all(),
                'noticias'             => $noticias->values()->all(),
                'calendario'           => $calendario->values()->all(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * Construye la URL pública de un archivo de Storage.
     */
    private function buildStorageUrl(?string $path): ?string
    {
        if (!$path) return null;
        return Storage::disk('public')->url($path);
    }
}
