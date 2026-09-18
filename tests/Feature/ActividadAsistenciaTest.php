<?php

use App\Models\Actividad;
use App\Models\ActividadSujeto;
use App\Models\Persona;
use App\Models\TipoActividad;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->tipo = TipoActividad::create([
        'nombre' => 'Test Mitin',
        'descripcion' => 'Descripción del mitin de prueba'
    ]);

    // Ventana abierta: fecha ahora, estado creada
    $this->actividad = Actividad::create([
        'titulo' => 'Gran Mitin de Prueba',
        'descripcion' => 'Mitin para probar geofencing',
        'fecha_actividad' => now(),
        'tipo_actividad_id' => $this->tipo->id,
        'estado' => 'creada',
        'es_publica' => true,
        'latitud' => -12.046374,
        'longitud' => -77.042793,
        'radio_asistencia_metros' => 100
    ]);

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

    $response = $this->postJson(route('api.asistencias.self', $this->actividad->id), [
        'latitud_usuario' => -12.046374,
        'longitud_usuario' => -77.042793,
        'browser_fingerprint' => 'fp_juan'
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Asistencia registrada correctamente.');

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
    $persona1 = Persona::create(['dni' => '11111111', 'nombres' => 'Persona', 'apellidos' => 'Uno']);
    $user1 = User::factory()->create(['persona_id' => $persona1->id]);
    $user1->givePermissionTo($this->permisoSelf);

    Sanctum::actingAs($user1);

    $this->postJson(route('api.asistencias.self', $this->actividad->id), [
        'latitud_usuario' => -12.046374,
        'longitud_usuario' => -77.042793,
        'browser_fingerprint' => 'fingerprint_compartido'
    ])->assertStatus(200);

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

test('it rejects DNI check-in if the person does not exist in the roster', function () {
    $response = $this->postJson(route('api.public.actividades.asistencia.dni', $this->actividad->id), [
        'dni' => '00000000',
        'latitud_usuario' => -12.046374,
        'longitud_usuario' => -77.042793,
        'browser_fingerprint' => 'fp_desconocido'
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'No se pudo autorizar este DNI para la actividad. Verifique el número o consulte con su responsable.');
});

test('it registers DNI check-in creating assignment if person exists but was not pre-registered', function () {
    $persona = Persona::create([
        'dni' => '87654321',
        'nombres' => 'Carlos',
        'apellidos' => 'Mendoza'
    ]);

    $response = $this->postJson(route('api.public.actividades.asistencia.dni', $this->actividad->id), [
        'dni' => '87654321',
        'latitud_usuario' => -12.046374,
        'longitud_usuario' => -77.042793,
        'browser_fingerprint' => 'fp_carlos'
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('tipo', 'ingreso');

    $this->assertDatabaseHas('actividad_sujetos', [
        'actividad_id' => $this->actividad->id,
        'sujeto_id' => $persona->id,
        'sujeto_type' => Persona::class,
        'metodo_registro' => 'qr_self_service',
    ]);
});

test('it registers DNI check-in successfully if the person is pre-registered and in range', function () {
    $persona = Persona::create([
        'dni' => '55555555',
        'nombres' => 'María',
        'apellidos' => 'Ramos'
    ]);

    ActividadSujeto::create([
        'actividad_id' => $this->actividad->id,
        'sujeto_id' => $persona->id,
        'sujeto_type' => Persona::class,
    ]);

    $response = $this->postJson(route('api.public.actividades.asistencia.dni', $this->actividad->id), [
        'dni' => '55555555',
        'latitud_usuario' => -12.046374,
        'longitud_usuario' => -77.042793,
        'browser_fingerprint' => 'fp_maria'
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Asistencia registrada correctamente por DNI.');

    $this->assertDatabaseHas('actividad_sujetos', [
        'actividad_id' => $this->actividad->id,
        'sujeto_id' => $persona->id,
        'sujeto_type' => Persona::class,
        'metodo_registro' => 'qr_self_service',
        'device_fingerprint' => 'fp_maria'
    ]);
});

test('it rejects check-in when activity is cancelled', function () {
    $this->actividad->update(['estado' => 'cancelada']);

    $persona = Persona::create(['dni' => '99999999', 'nombres' => 'Ana', 'apellidos' => 'Ruiz']);
    $user = User::factory()->create(['persona_id' => $persona->id]);
    $user->givePermissionTo($this->permisoSelf);
    Sanctum::actingAs($user);

    $this->postJson(route('api.asistencias.self', $this->actividad->id), [
        'latitud_usuario' => -12.046374,
        'longitud_usuario' => -77.042793,
        'browser_fingerprint' => 'fp_ana'
    ])->assertStatus(422)
        ->assertJsonPath('status', 'error');
});

test('it rejects check-in outside attendance window', function () {
    $this->actividad->update(['fecha_actividad' => now()->addDays(3)]);

    $persona = Persona::create(['dni' => '88888888', 'nombres' => 'Luis', 'apellidos' => 'Diaz']);
    $user = User::factory()->create(['persona_id' => $persona->id]);
    $user->givePermissionTo($this->permisoSelf);
    Sanctum::actingAs($user);

    $this->postJson(route('api.asistencias.self', $this->actividad->id), [
        'latitud_usuario' => -12.046374,
        'longitud_usuario' => -77.042793,
        'browser_fingerprint' => 'fp_luis'
    ])->assertStatus(422)
        ->assertJsonPath('status', 'error');
});

test('it requires confirmation before registering exit', function () {
    $persona = Persona::create(['dni' => '77777777', 'nombres' => 'Eva', 'apellidos' => 'Soto']);
    $user = User::factory()->create(['persona_id' => $persona->id]);
    $user->givePermissionTo($this->permisoSelf);
    Sanctum::actingAs($user);

    $payload = [
        'latitud_usuario' => -12.046374,
        'longitud_usuario' => -77.042793,
        'browser_fingerprint' => 'fp_eva'
    ];

    $this->postJson(route('api.asistencias.self', $this->actividad->id), $payload)
        ->assertStatus(200)
        ->assertJsonPath('tipo', 'ingreso');

    $this->postJson(route('api.asistencias.self', $this->actividad->id), $payload)
        ->assertStatus(409)
        ->assertJsonPath('requires_confirmation', true);

    $this->postJson(route('api.asistencias.self', $this->actividad->id), [
        ...$payload,
        'confirmar_salida' => true,
    ])->assertStatus(200)
        ->assertJsonPath('tipo', 'salida');
});
