<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eliminar duplicados activos (conserva el de menor id)
        $duplicates = DB::table('actividad_sujetos')
            ->select('actividad_id', 'sujeto_id', 'sujeto_type', DB::raw('MIN(id) as keep_id'))
            ->whereNull('deleted_at')
            ->groupBy('actividad_id', 'sujeto_id', 'sujeto_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            // Hard-delete duplicados para no chocar con el unique + soft deletes
            DB::table('actividad_sujetos')
                ->where('actividad_id', $dup->actividad_id)
                ->where('sujeto_id', $dup->sujeto_id)
                ->where('sujeto_type', $dup->sujeto_type)
                ->whereNull('deleted_at')
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('actividad_sujetos', function (Blueprint $table) {
            $table->unique(
                ['actividad_id', 'sujeto_id', 'sujeto_type'],
                'actividad_sujetos_unique_asignacion'
            );
            $table->index('device_fingerprint', 'actividad_sujetos_device_fp_index');
        });
    }

    public function down(): void
    {
        Schema::table('actividad_sujetos', function (Blueprint $table) {
            $table->dropUnique('actividad_sujetos_unique_asignacion');
            $table->dropIndex('actividad_sujetos_device_fp_index');
        });
    }
};
