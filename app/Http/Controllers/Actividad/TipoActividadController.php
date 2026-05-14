<?php

namespace App\Http\Controllers\Actividad;

use App\Http\Controllers\Controller;
use App\Models\TipoActividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipoActividadController extends Controller
{
    public function index(): JsonResponse
    {
        $tipos = TipoActividad::all();
        return response()->json([
            'status' => 'success',
            'data' => $tipos->map(fn($t) => [
                'id' => $t->id,
                'nombre' => $t->nombre,
                'descripcion' => $t->descripcion
            ])
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string'
        ]);

        $tipo = TipoActividad::create($validated + ['created_by' => auth()->id()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tipo de actividad creado correctamente',
            'data' => $tipo
        ], 201);
    }
}
