<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_settings', function (Blueprint $table) {
            $table->id();

            // Información del candidato
            $table->string('nombre_candidato')->default('');
            $table->string('cargo_candidatura')->nullable(); // Ej: "Candidato a Alcalde"
            $table->string('eslogan')->nullable();
            $table->text('biografia')->nullable();

            // Fotografías
            $table->string('foto_principal_path')->nullable(); // Foto hero/principal
            $table->string('foto_secundaria_path')->nullable(); // Foto secundaria (acción, campaña)

            // Redes sociales (JSON: { facebook, instagram, tiktok, twitter, whatsapp })
            $table->json('redes_sociales')->nullable();

            // Configuración visual
            $table->string('color_primario')->default('#1e40af'); // Azul político por defecto
            $table->string('color_secundario')->default('#dc2626'); // Rojo por defecto

            // Meta SEO
            $table->string('meta_titulo')->nullable();
            $table->text('meta_descripcion')->nullable();

            // Trazabilidad
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_settings');
    }
};
