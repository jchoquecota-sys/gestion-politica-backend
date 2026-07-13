<?php

use App\Models\Sector;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('it assigns created_by on model creation when authenticated', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $sector = Sector::create([
        'nombre' => 'Sector Norte',
        'descripcion' => 'Sector de prueba norte',
        'codigo' => 'SEC-NORTE'
    ]);

    expect($sector->created_by)->toBe($user->id);
});

test('it assigns updated_by on model updating when authenticated', function () {
    $creator = User::factory()->create();
    $updater = User::factory()->create();

    // Crear por el creador
    Sanctum::actingAs($creator);
    $sector = Sector::create([
        'nombre' => 'Sector Sur',
        'descripcion' => 'Sector de prueba sur',
        'codigo' => 'SEC-SUR'
    ]);

    // Actualizar por el actualizador
    Sanctum::actingAs($updater);
    $sector->update([
        'nombre' => 'Sector Sur Modificado'
    ]);

    expect($sector->created_by)->toBe($creator->id)
        ->and($sector->updated_by)->toBe($updater->id);
});

test('it assigns deleted_by on model deletion when authenticated', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $sector = Sector::create([
        'nombre' => 'Sector Este',
        'descripcion' => 'Sector de prueba este',
        'codigo' => 'SEC-ESTE'
    ]);

    // Eliminar (Soft delete)
    $sector->delete();

    // Recargar desde base de datos incluyendo los eliminados
    $deletedSector = Sector::withTrashed()->find($sector->id);

    expect($deletedSector->deleted_by)->toBe($user->id);
});
