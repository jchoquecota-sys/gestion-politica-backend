<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landing\UpdateLandingSettingRequest;
use App\Models\LandingSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class LandingSettingController extends Controller
{
    /**
     * Obtener la configuración actual de la landing page (para el panel admin).
     */
    public function show(): JsonResponse
    {
        $setting = LandingSetting::latest()->first();

        return response()->json([
            'status' => 'success',
            'data'   => $setting ? $this->formatResource($setting) : null,
        ]);
    }

    /**
     * Guardar/actualizar la configuración de la landing page.
     * Maneja archivos como multipart/form-data.
     * Invalida el caché público al guardar.
     */
    public function update(UpdateLandingSettingRequest $request): JsonResponse
    {
        $setting = LandingSetting::latest()->first() ?? new LandingSetting();

        $data = $request->only([
            'nombre_candidato',
            'cargo_candidatura',
            'eslogan',
            'biografia',
            'color_primario',
            'color_secundario',
            'meta_titulo',
            'meta_descripcion',
        ]);

        // Manejar redes sociales como JSON
        if ($request->has('redes_sociales')) {
            $data['redes_sociales'] = $request->redes_sociales;
        }

        // Manejar logo
        if ($request->hasFile('logo')) {
            if ($setting->logo_path) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $data['logo_path'] = $request->file('logo')
                ->store('landing/marca', 'public');
        }

        // Manejar foto principal
        if ($request->hasFile('foto_principal')) {
            if ($setting->foto_principal_path) {
                Storage::disk('public')->delete($setting->foto_principal_path);
            }
            $data['foto_principal_path'] = $request->file('foto_principal')
                ->store('landing/candidato', 'public');
        }

        // Manejar foto secundaria
        if ($request->hasFile('foto_secundaria')) {
            if ($setting->foto_secundaria_path) {
                Storage::disk('public')->delete($setting->foto_secundaria_path);
            }
            $data['foto_secundaria_path'] = $request->file('foto_secundaria')
                ->store('landing/candidato', 'public');
        }

        $data['updated_by'] = auth()->id();

        if (!$setting->exists) {
            $data['created_by'] = auth()->id();
        }

        $setting->fill($data)->save();

        // Invalidar el caché público para que los cambios se reflejen de inmediato
        Cache::forget('public_landing_data');

        return response()->json([
            'status'  => 'success',
            'message' => 'Configuración de la página guardada correctamente.',
            'data'    => $this->formatResource($setting),
        ]);
    }

    /**
     * Formatear recurso para la respuesta API.
     */
    private function formatResource(LandingSetting $setting): array
    {
        return [
            'id'                   => $setting->id,
            'nombre_candidato'     => $setting->nombre_candidato,
            'cargo_candidatura'    => $setting->cargo_candidatura,
            'eslogan'              => $setting->eslogan,
            'biografia'            => $setting->biografia,
            'logo_url'             => $setting->logo_path
                ? Storage::disk('public')->url($setting->logo_path)
                : null,
            'foto_principal_url'   => $setting->foto_principal_path
                ? Storage::disk('public')->url($setting->foto_principal_path)
                : null,
            'foto_secundaria_url'  => $setting->foto_secundaria_path
                ? Storage::disk('public')->url($setting->foto_secundaria_path)
                : null,
            'logo_path'            => $setting->logo_path,
            'foto_principal_path'  => $setting->foto_principal_path,
            'foto_secundaria_path' => $setting->foto_secundaria_path,
            'redes_sociales'       => $setting->redes_sociales ?? [],
            'color_primario'       => $setting->color_primario,
            'color_secundario'     => $setting->color_secundario,
            'meta_titulo'          => $setting->meta_titulo,
            'meta_descripcion'     => $setting->meta_descripcion,
            'updated_at'           => $setting->updated_at?->toDateTimeString(),
        ];
    }
}
