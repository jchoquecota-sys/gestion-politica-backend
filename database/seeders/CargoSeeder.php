<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CargoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cargos = [
            ['nombre' => 'Responsable', 'descripcion' => 'Encargado principal del sector'],
            ['nombre' => 'Colaborador', 'descripcion' => 'Apoyo en las actividades del sector'],
            ['nombre' => 'Coordinador', 'descripcion' => 'Coordina actividades específicas'],
            ['nombre' => 'Participante', 'descripcion' => 'Miembro general o afiliado a la base/sector'],
        ];

        foreach ($cargos as $cargo) {
            \App\Models\Cargo::updateOrCreate(
                ['nombre' => $cargo['nombre']],
                ['descripcion' => $cargo['descripcion']]
            );
        }
    }
}
