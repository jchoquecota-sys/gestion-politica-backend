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
            ['nombre' => 'Responsable', 'descripcion' => 'Encargado principal de la base o sector'],
            ['nombre' => 'Secretario', 'descripcion' => 'Encargado de actas y documentos'],
            ['nombre' => 'Coordinador', 'descripcion' => 'Coordina actividades específicas de la base'],
            ['nombre' => 'Tesorero', 'descripcion' => 'Encargado de los fondos de la base'],
            ['nombre' => 'Simpatizante', 'descripcion' => 'Miembro general de la base'],
        ];

        foreach ($cargos as $cargo) {
            \App\Models\Cargo::updateOrCreate(
                ['nombre' => $cargo['nombre']],
                ['descripcion' => $cargo['descripcion']]
            );
        }
    }
}
