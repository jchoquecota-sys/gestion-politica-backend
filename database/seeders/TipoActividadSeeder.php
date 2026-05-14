<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoActividad;

class TipoActividadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipos = [
            [
                'nombre' => 'Mitin',
                'descripcion' => 'Acto público con fines políticos o informativos.',
            ],
            [
                'nombre' => 'Capacitación',
                'descripcion' => 'Sesiones de formación y desarrollo de habilidades.',
            ],
            [
                'nombre' => 'Reunión',
                'descripcion' => 'Encuentro formal para coordinar acciones o discutir temas específicos.',
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoActividad::firstOrCreate(
                ['nombre' => $tipo['nombre']],
                ['descripcion' => $tipo['descripcion']]
            );
        }

        $this->command->info('Tipos de actividad inicializados correctamente.');
    }
}
