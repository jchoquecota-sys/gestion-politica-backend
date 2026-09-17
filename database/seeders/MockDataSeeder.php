<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class MockDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now();

        // 1. Preparar directorios de Storage Público
        Storage::disk('public')->makeDirectory('actividades/portadas');
        Storage::disk('public')->makeDirectory('landing/candidato');
        Storage::disk('public')->makeDirectory('landing/marca');

        // 2. Rutas de origen en la carpeta de seeders
        $imgActividad = database_path('seeders/demo_images/actividad_demo.jpg');
        $imgCandidatoPrincipal = database_path('seeders/demo_images/candidato_principal.jpg');
        $imgCandidatoSecundaria = database_path('seeders/demo_images/candidato_secundaria.jpg');
        $imgLogo = database_path('seeders/demo_images/logo.png');

        // 3. Copiar imágenes y definir las rutas a guardar en DB
        $pathActividad = null;
        if (File::exists($imgActividad)) {
            Storage::disk('public')->put('actividades/portadas/actividad_demo.jpg', File::get($imgActividad));
            $pathActividad = 'actividades/portadas/actividad_demo.jpg';
        }

        $pathCandidatoPrincipal = null;
        if (File::exists($imgCandidatoPrincipal)) {
            Storage::disk('public')->put('landing/candidato/candidato_principal.jpg', File::get($imgCandidatoPrincipal));
            $pathCandidatoPrincipal = 'landing/candidato/candidato_principal.jpg';
        }

        $pathCandidatoSecundaria = null;
        if (File::exists($imgCandidatoSecundaria)) {
            Storage::disk('public')->put('landing/candidato/candidato_secundaria.jpg', File::get($imgCandidatoSecundaria));
            $pathCandidatoSecundaria = 'landing/candidato/candidato_secundaria.jpg';
        }

        $pathLogo = null;
        if (File::exists($imgLogo)) {
            Storage::disk('public')->put('landing/marca/logo.png', File::get($imgLogo));
            $pathLogo = 'landing/marca/logo.png';
        }

        // SECTORES
        $sectores = [
            ['id' => 1, 'nombre' => 'Tacna Cercado', 'descripcion' => 'Centro neurálgico', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'nombre' => 'G. Albarracín', 'descripcion' => 'Distrito más poblado', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'nombre' => 'Alto de la Alianza', 'descripcion' => 'Distrito norte de Tacna', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'nombre' => 'Ciudad Nueva', 'descripcion' => 'Cono norte denso', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'nombre' => 'Pocollay', 'descripcion' => 'Zona residencial/campiña', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'nombre' => 'Calana', 'descripcion' => 'Rural campestre', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'nombre' => 'Pachía', 'descripcion' => 'Turismo termal', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'nombre' => 'Ite/Sama', 'descripcion' => 'Zonas agrícolas lejanas', 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('sectores')->insertOrIgnore($sectores);

        // BASES
        $bases = [
            ['id' => 1, 'nombre' => 'Base Bolognesi', 'sector_id' => 1, 'latitud' => -18.0130, 'longitud' => -70.2510, 'direccion' => 'Av. Bolognesi', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'nombre' => 'Base Leguía', 'sector_id' => 1, 'latitud' => -18.0180, 'longitud' => -70.2560, 'direccion' => 'Av. Leguía', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'nombre' => 'Base Vigil', 'sector_id' => 1, 'latitud' => -18.0100, 'longitud' => -70.2450, 'direccion' => 'Av. Pinto', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'nombre' => 'Base Viñani I', 'sector_id' => 2, 'latitud' => -18.0550, 'longitud' => -70.2350, 'direccion' => 'Viñani Mz A', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'nombre' => 'Base San Francisco', 'sector_id' => 2, 'latitud' => -18.0420, 'longitud' => -70.2450, 'direccion' => 'Av. Municipal', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'nombre' => 'Base C. Nueva Alta', 'sector_id' => 4, 'latitud' => -17.9850, 'longitud' => -70.2400, 'direccion' => 'Av. Internacional', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'nombre' => 'Base Pocollay', 'sector_id' => 5, 'latitud' => -18.0050, 'longitud' => -70.2280, 'direccion' => 'Plaza Pocollay', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'nombre' => 'Base Calana', 'sector_id' => 6, 'latitud' => -17.9800, 'longitud' => -70.2000, 'direccion' => 'Av. Los Angeles', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9, 'nombre' => 'Base Ite', 'sector_id' => 8, 'latitud' => -17.8800, 'longitud' => -70.5000, 'direccion' => 'Carr. Panamericana', 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('bases')->insertOrIgnore($bases);

        // PERSONAS
        $personas = [
            ['id' => 1, 'dni' => '70000101', 'nombres' => 'Juan', 'apellidos' => 'Mamani', 'created_at' => '2026-01-05', 'updated_at' => $now],
            ['id' => 2, 'dni' => '70000102', 'nombres' => 'Rosa', 'apellidos' => 'Vargas', 'created_at' => '2026-01-12', 'updated_at' => $now],
            ['id' => 3, 'dni' => '70000103', 'nombres' => 'Luis', 'apellidos' => 'Flores', 'created_at' => '2026-01-25', 'updated_at' => $now],
            ['id' => 4, 'dni' => '70000104', 'nombres' => 'Ana', 'apellidos' => 'Quispe', 'created_at' => '2026-02-02', 'updated_at' => $now],
            ['id' => 5, 'dni' => '70000105', 'nombres' => 'Jose', 'apellidos' => 'Apaza', 'created_at' => '2026-02-05', 'updated_at' => $now],
            ['id' => 6, 'dni' => '70000106', 'nombres' => 'Carmen', 'apellidos' => 'Pari', 'created_at' => '2026-02-10', 'updated_at' => $now],
            ['id' => 7, 'dni' => '70000107', 'nombres' => 'Pedro', 'apellidos' => 'Calle', 'created_at' => '2026-02-15', 'updated_at' => $now],
            ['id' => 8, 'dni' => '70000108', 'nombres' => 'Marta', 'apellidos' => 'Luna', 'created_at' => '2026-02-20', 'updated_at' => $now],
            ['id' => 9, 'dni' => '70000109', 'nombres' => 'Jorge', 'apellidos' => 'Soto', 'created_at' => '2026-02-28', 'updated_at' => $now],
            ['id' => 10, 'dni' => '70000110', 'nombres' => 'Silvia', 'apellidos' => 'Mendoza', 'created_at' => '2026-03-05', 'updated_at' => $now],
            ['id' => 11, 'dni' => '70000111', 'nombres' => 'Carlos', 'apellidos' => 'Ramos', 'created_at' => '2026-03-15', 'updated_at' => $now],
            ['id' => 12, 'dni' => '70000112', 'nombres' => 'Betty', 'apellidos' => 'Paredes', 'created_at' => '2026-03-25', 'updated_at' => $now],
            ['id' => 13, 'dni' => '70000113', 'nombres' => 'Sonia', 'apellidos' => 'Guzman', 'created_at' => '2026-04-10', 'updated_at' => $now],
            ['id' => 14, 'dni' => '70000114', 'nombres' => 'Hugo', 'apellidos' => 'Pari', 'created_at' => '2026-04-20', 'updated_at' => $now],
            ['id' => 15, 'dni' => '70000115', 'nombres' => 'Clara', 'apellidos' => 'Diaz', 'created_at' => '2026-05-01', 'updated_at' => $now],
            ['id' => 16, 'dni' => '70000116', 'nombres' => 'Piero', 'apellidos' => 'Salinas', 'created_at' => '2026-05-05', 'updated_at' => $now],
            ['id' => 17, 'dni' => '70000117', 'nombres' => 'Saul', 'apellidos' => 'Muñoz', 'created_at' => '2026-05-10', 'updated_at' => $now],
            ['id' => 18, 'dni' => '70000118', 'nombres' => 'Gina', 'apellidos' => 'Moreno', 'created_at' => '2026-05-12', 'updated_at' => $now],
            ['id' => 19, 'dni' => '70000119', 'nombres' => 'Oscar', 'apellidos' => 'Vargas', 'created_at' => '2026-05-13', 'updated_at' => $now],
            ['id' => 20, 'dni' => '70000120', 'nombres' => 'Elena', 'apellidos' => 'Ruiz', 'created_at' => '2026-05-14', 'updated_at' => $now],
        ];
        DB::table('personas')->insertOrIgnore($personas);

        // BASE PERSONAS
        if (DB::table('base_personas')->count() === 0) {
            $basePersonas = [
                ['base_id' => 1, 'persona_id' => 1, 'cargo_id' => 3, 'es_principal' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 1, 'persona_id' => 2, 'cargo_id' => 5, 'es_principal' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 1, 'persona_id' => 3, 'cargo_id' => 5, 'es_principal' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 1, 'persona_id' => 4, 'cargo_id' => 5, 'es_principal' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 1, 'persona_id' => 5, 'cargo_id' => 5, 'es_principal' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 1, 'persona_id' => 6, 'cargo_id' => 5, 'es_principal' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 1, 'persona_id' => 7, 'cargo_id' => 5, 'es_principal' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 1, 'persona_id' => 8, 'cargo_id' => 5, 'es_principal' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 1, 'persona_id' => 9, 'cargo_id' => 5, 'es_principal' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 1, 'persona_id' => 10, 'cargo_id' => 5, 'es_principal' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 4, 'persona_id' => 11, 'cargo_id' => 3, 'es_principal' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 4, 'persona_id' => 12, 'cargo_id' => 5, 'es_principal' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 4, 'persona_id' => 13, 'cargo_id' => 5, 'es_principal' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 4, 'persona_id' => 14, 'cargo_id' => 5, 'es_principal' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 4, 'persona_id' => 15, 'cargo_id' => 5, 'es_principal' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['base_id' => 9, 'persona_id' => 16, 'cargo_id' => 3, 'es_principal' => 1, 'created_at' => $now, 'updated_at' => $now],
            ];
            DB::table('base_personas')->insert($basePersonas);
        }

        // ACTIVIDADES
        $actividades = [
            ['id' => 1, 'titulo' => 'Caminata Bolognesi', 'descripcion' => 'Inaugural', 'fecha_actividad' => '2026-01-10', 'tipo_actividad_id' => 1, 'estado' => 'creada', 'es_publica' => 1, 'foto_portada_path' => $pathActividad, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'titulo' => 'Taller Viñani', 'descripcion' => 'Formación', 'fecha_actividad' => '2026-02-15', 'tipo_actividad_id' => 2, 'estado' => 'creada', 'es_publica' => 0, 'foto_portada_path' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'titulo' => 'Asamblea Centro', 'descripcion' => 'Estrategia', 'fecha_actividad' => '2026-03-20', 'tipo_actividad_id' => 3, 'estado' => 'cancelada', 'es_publica' => 0, 'foto_portada_path' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'titulo' => 'Mitin Juventudes', 'descripcion' => 'Gran evento', 'fecha_actividad' => '2026-05-25', 'tipo_actividad_id' => 1, 'estado' => 'creada', 'es_publica' => 1, 'foto_portada_path' => $pathActividad, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'titulo' => 'Reunión Ite', 'descripcion' => 'Planificación', 'fecha_actividad' => '2026-05-28', 'tipo_actividad_id' => 3, 'estado' => 'borrador', 'es_publica' => 0, 'foto_portada_path' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'titulo' => 'Brigada Calana', 'descripcion' => 'Salud', 'fecha_actividad' => '2026-05-30', 'tipo_actividad_id' => 2, 'estado' => 'borrador', 'es_publica' => 0, 'foto_portada_path' => null, 'created_at' => $now, 'updated_at' => $now],
        ];
        DB::table('actividades')->insertOrIgnore($actividades);

        // ACTIVIDAD SUJETOS
        if (DB::table('actividad_sujetos')->count() === 0) {
            $actividadSujetos = [
                ['actividad_id' => 1, 'sujeto_id' => 1, 'sujeto_type' => 'App\\Models\\Sector', 'descripcion_ejecucion' => 'Participación de todo el sector Cercado', 'evidencias' => null, 'created_at' => $now, 'updated_at' => $now],
                ['actividad_id' => 1, 'sujeto_id' => 1, 'sujeto_type' => 'App\\Models\\Base', 'descripcion_ejecucion' => 'La base organizó la logística', 'evidencias' => null, 'created_at' => $now, 'updated_at' => $now],
                ['actividad_id' => 1, 'sujeto_id' => 2, 'sujeto_type' => 'App\\Models\\Base', 'descripcion_ejecucion' => 'Apoyo en convocatoria', 'evidencias' => null, 'created_at' => $now, 'updated_at' => $now],
                ['actividad_id' => 1, 'sujeto_id' => 1, 'sujeto_type' => 'App\\Models\\Persona', 'descripcion_ejecucion' => 'Discurso inaugural', 'evidencias' => null, 'created_at' => $now, 'updated_at' => $now],
                ['actividad_id' => 2, 'sujeto_id' => 4, 'sujeto_type' => 'App\\Models\\Base', 'descripcion_ejecucion' => 'Sede principal del evento', 'evidencias' => null, 'created_at' => $now, 'updated_at' => $now],
                ['actividad_id' => 2, 'sujeto_id' => 5, 'sujeto_type' => 'App\\Models\\Base', 'descripcion_ejecucion' => 'Participantes del taller', 'evidencias' => null, 'created_at' => $now, 'updated_at' => $now],
                ['actividad_id' => 4, 'sujeto_id' => 4, 'sujeto_type' => 'App\\Models\\Sector', 'descripcion_ejecucion' => 'Movilización general del cono norte', 'evidencias' => null, 'created_at' => $now, 'updated_at' => $now],
                ['actividad_id' => 4, 'sujeto_id' => 6, 'sujeto_type' => 'App\\Models\\Base', 'descripcion_ejecucion' => 'Coordinación de jóvenes', 'evidencias' => null, 'created_at' => $now, 'updated_at' => $now],
                ['actividad_id' => 5, 'sujeto_id' => 9, 'sujeto_type' => 'App\\Models\\Base', 'descripcion_ejecucion' => 'Revisión de planes agrícolas', 'evidencias' => null, 'created_at' => $now, 'updated_at' => $now],
                ['actividad_id' => 6, 'sujeto_id' => 6, 'sujeto_type' => 'App\\Models\\Sector', 'descripcion_ejecucion' => 'Brigada de salud rural', 'evidencias' => null, 'created_at' => $now, 'updated_at' => $now],
                ['actividad_id' => 6, 'sujeto_id' => 8, 'sujeto_type' => 'App\\Models\\Base', 'descripcion_ejecucion' => 'Atención en posta médica local', 'evidencias' => null, 'created_at' => $now, 'updated_at' => $now],
            ];
            DB::table('actividad_sujetos')->insert($actividadSujetos);
        }

        // SECTOR PERSONAS
        if (DB::table('sector_personas')->count() === 0) {
            $sectorPersonas = [
                ['sector_id' => 1, 'persona_id' => 1, 'cargo_id' => 1, 'es_principal' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['sector_id' => 2, 'persona_id' => 4, 'cargo_id' => 1, 'es_principal' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['sector_id' => 4, 'persona_id' => 6, 'cargo_id' => 1, 'es_principal' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['sector_id' => 5, 'persona_id' => 10, 'cargo_id' => 1, 'es_principal' => 1, 'created_at' => $now, 'updated_at' => $now],
            ];
            DB::table('sector_personas')->insert($sectorPersonas);
        }

        // LANDING SETTINGS
        if (DB::table('landing_settings')->count() === 0) {
            DB::table('landing_settings')->insert([
                'nombre_candidato' => 'Jhonson Mamani Velasquez',
                'cargo_candidatura' => 'Candidato a Alcalde de Alto de la Alianza 2026',
                'eslogan' => 'Gestión transparente y lucha contra la corrupción',
                'biografia' => 'Jhonson Mamani Velasquez, candidato a la alcaldía del distrito de Alto de la Alianza. Comprometido con una gestión transparente, la lucha contra la corrupción y el desarrollo de nuestra comunidad.',
                'logo_path' => $pathLogo,
                'foto_principal_path' => $pathCandidatoPrincipal,
                'foto_secundaria_path' => $pathCandidatoSecundaria,
                'redes_sociales' => json_encode([
                    'facebook' => 'https://www.facebook.com/profile.php?id=61584765587862',
                    'instagram' => 'https://instagram.com/jhonsonmamani',
                    'tiktok' => 'https://tiktok.com/@jhonsonmamani',
                    'whatsapp' => '987654321'
                ]),
                'color_primario' => '#E31B23',
                'color_secundario' => '#1A1A1A',
                'meta_titulo' => 'Jhonson Mamani Velasquez — Candidato a Alcalde de Alto de la Alianza',
                'meta_descripcion' => 'Página oficial de la campaña de Jhonson Mamani Velasquez a la alcaldía de Alto de la Alianza. Gestión transparente y lucha contra la corrupción.',
                'created_at' => $now,
                'updated_at' => $now
            ]);
        }
    }
}
