<?php

use App\Models\Actividad;
use App\Models\ActividadSujeto;
use App\Models\Persona;
use App\Models\TipoActividad;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Crear tipo de actividad obligatorio
    $this->tipo = TipoActividad::create([
        'nombre' => 'Test Mitin',
        'descripcion' => 'Descripción del mitin de prueba'
    ]);

    // Crear actividad con geofencing (Plaza de Armas de Lima: -12.046374, -77.042793, radio: 100m)
    $this->actividad = Actividad::create([
        'titulo' => 'Gran Mitin de Prueba',
        'descripcion' => 'Mitin para probar geofencing',
        'fecha_actividad' => now()->addDays(2),
        'tipo_actividad_id' => $this->tipo->id,
        'estado' => 'creada',
        'es_publica' => true,
        'latitud' => -12.046374,
        'longitud' => -77.042793,
        'radio_asistencia_metros' => 100
    ]);

    // Crear permiso de auto-registro
    $this->permisoSelf = Permission::create([
        'name' => 'actividades:asistencia-self',
        'guard_name' => 'web'
    ]);
});

test('it registers QR check-in successfully when user is in range', function () {
    $persona = Persona::create([
        'dni' => '12345678',
        'nombres' => 'Juan',
        'apellidos' => 'Perez'
    ]);

    $user = User::factory()->create(['persona_id' => $persona->id]);
    $user->givePermissionTo($this->permisoSelf);

    Sanctum::actingAs($user);

    // Enviar coordenadas exactas de la actividad (Plaza de Armas de Lima)
    $response = $this->postJson(route('api.asistencias.self', $this->actividad->id), [
        'latitud_usuario' => -12.046374,
        'longitud_usuario' => -77.042793,
        'browser_fingerprint' => 'fp_juan'
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Asistencia registrada correctamente.');

    // Verificar en base de datos
    $this->assertDatabaseHas('actividad_sujetos', [
        'actividad_id' => $this->actividad->id,
        'sujeto_id' => $persona->id,
        'sujeto_type' => Persona::class,
        'device_fingerprint' => 'fp_juan',
        'metodo_registro' => 'qr_self_service'
    ]);
});

test('it rejects QR check-in when user is out of range', function () {
    $persona = Persona::create([
        'dni' => '12345678',
        'nombres' => 'Juan',
        'apellidos' => 'Perez'
    ]);

    $user = User::factory()->create(['persona_id' => $persona->id]);
    $user->givePermissionTo($this->permisoSelf);

    Sanctum::actingAs($user);

    // Coordenadas lejanas (ej: Miraflores, aprox 9km de distancia)
    $response = $this->postJson(route('api.asistencias.self', $this->actividad->id), [
        'latitud_usuario' => -12.1221,
        'longitud_usuario' => -77.0298,
        'browser_fingerprint' => 'fp_juan'
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Estás fuera del radio permitido para marcar tu asistencia o salida.');
});

test('it blocks duplicate check-in with same fingerprint (Anti-Amigo rule)', function () {
    // Crear Persona 1 y Registrar asistencia
    $persona1 = Persona::create(['dni' => '11111111', 'nombres' => 'Persona', 'apellidos' => 'Uno']);
    $user1 = User::factory()->create(['persona_id' => $persona1->id]);
    $user1->givePermissionTo($this->permisoSelf);

    Sanctum::actingAs($user1);

    $this->postJson(route('api.asistencias.self', $this->actividad->id), [
        'latitud_usuario' => -12.046374,
        'longitud_usuario' => -77.042793,
        'browser_fingerprint' => 'fingerprint_compartido'
    ])->assertStatus(200);

    // Intentar registrar Persona 2 usando el mismo fingerprint
    $persona2 = Persona::create(['dni' => '22222222', 'nombres' => 'Persona', 'apellidos' => 'Dos']);
    $user2 = User::factory()->create(['persona_id' => $persona2->id]);
    $user2->givePermissionTo($this->permisoSelf);

    Sanctum::actingAs($user2);

    $response = $this->postJson(route('api.asistencias.self', $this->actividad->id), [
        'latitud_usuario' => -12.046374,
        'longitud_usuario' => -77.042793,
        'browser_fingerprint' => 'fingerprint_compartido'
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Este dispositivo ya fue usado para registrar la asistencia de otra persona hoy.');
});

test('it rejects DNI check-in if the person is not pre-registered in the activity', function () {
    $persona = Persona::create([
        'dni' => '87654321',
        'nombres' => 'Carlos',
        'apellidos' => 'Mendoza'
    ]);

    // Intentar marcar por DNI sin asignación previa
    $response = $this->postJson(route('api.public.actividades.asistencia.dni', $this->actividad->id), [
        'dni' => '87654321',
        'latitud_usuario' => -12.046374,
        'longitud_usuario' => -77.042793,
        'browser_fingerprint' => 'fp_carlos'
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Su DNI no está autorizado. Debe estar pre-registrado en la lista de participantes de esta actividad.');
});

test('it registers DNI check-in successfully if the person is pre-registered and in range', function () {
    $persona = Persona::create([
        'dni' => '55555555',
        'nombres' => 'María',
        'apellidos' => 'Ramos'
    ]);

    // Pre-registrar en la actividad
    ActividadSujeto::create([
        'actividad_id' => $this->actividad->id,
        'sujeto_id' => $persona->id,
        'sujeto_type' => Persona::class,
    ]);

    // Marcar asistencia pública por DNI
    $response = $this->postJson(route('api.public.actividades.asistencia.dni', $this->actividad->id), [
        'dni' => '55555555',
        'latitud_usuario' => -12.046374,
        'longitud_usuario' => -77.042793,
        'browser_fingerprint' => 'fp_maria'
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Asistencia registrada correctamente por DNI.');

    // Verificar en base de datos que se haya guardado la hora de asistencia y el método
    $this->assertDatabaseHas('actividad_sujetos', [
        'actividad_id' => $this->actividad->id,
        'sujeto_id' => $persona->id,
        'sujeto_type' => Persona::class,
        'metodo_registro' => 'qr_self_service',
        'device_fingerprint' => 'fp_maria'
    ]);
});
