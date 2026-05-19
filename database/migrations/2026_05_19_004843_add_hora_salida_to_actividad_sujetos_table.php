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
            $table->timestamp('hora_salida')->nullable()->after('hora_asistencia')->comment('Hora de salida del evento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('actividad_sujetos', function (Blueprint $table) {
            $table->dropColumn('hora_salida');
        });
    }
};
