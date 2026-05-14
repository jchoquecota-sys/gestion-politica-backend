<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Sube un archivo al almacenamiento y retorna su path y URL.
     * Útil para evidencias de actividades, documentos de personas, etc.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,pdf,doc,docx|max:5120', // 5MB max
            'folder' => 'nullable|string|max:50'
        ]);

        try {
            $folder = $request->get('folder', 'uploads');
            $path = $request->file('file')->store($folder, 'public');
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'path' => $path,
                    'url'  => Storage::disk('public')->url($path),
                    'name' => $request->file('file')->getClientOriginalName(),
                    'mime' => $request->file('file')->getClientMimeType(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo subir el archivo.'
            ], 500);
        }
    }
}
