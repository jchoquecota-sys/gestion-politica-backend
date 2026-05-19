<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('actividad_sujetos', function (Blueprint $table) {
            $table->timestamp('hora_asistencia')->nullable()->comment('Si es null, no ha asistido');
            $table->enum('metodo_registro', ['manual_admin', 'qr_self_service'])->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            
            $table->decimal('latitud_capturada', 10, 8)->nullable();
            $table->decimal('longitud_capturada', 11, 8)->nullable();
            
            $table->string('device_fingerprint')->nullable()->comment('Hash único del navegador/dispositivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('actividad_sujetos', function (Blueprint $table) {
            $table->dropForeign(['registrado_por']);
            $table->dropColumn([
                'hora_asistencia', 
                'metodo_registro', 
                'registrado_por', 
                'latitud_capturada', 
                'longitud_capturada', 
                'device_fingerprint'
            ]);
        });
    }
};
