<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            // Controla si la actividad es visible en la landing page pública
            $table->boolean('es_publica')->default(false)->after('estado');
            // Foto de portada para representar la actividad en noticias/calendario
            $table->string('foto_portada_path')->nullable()->after('es_publica');
        });
    }

    public function down(): void
    {
        Schema::table('actividades', function (Blueprint $table) {
            $table->dropColumn(['es_publica', 'foto_portada_path']);
        });
    }
};
