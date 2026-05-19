# Guía Profesional de Implementación: Sistema de Asistencias Híbrido

Esta guía establece la arquitectura para un sistema de registro dinámico (On-Demand) que soporta dos modalidades complementarias y altamente seguras:

1. **Control por Organizador (Manual Rápido)**: Un administrador registra a los asistentes en puerta mediante un buscador.
2. **Auto-registro Seguro (QR + Geofencing)**: Los simpatizantes escanean un código QR impreso en el local. El sistema valida su identidad y comprueba por GPS que están físicamente en el lugar antes de validar la asistencia.

---

## 1. Diseño de Base de Datos (Backend)

Para soportar la validación por geolocalización, necesitamos añadir campos tanto a la actividad como a la tabla de asistencias.

### 1.1. Modificación a la tabla `actividades`
Añadimos campos de ubicación para definir el "Geofence" del evento.
```php
Schema::table('actividades', function (Blueprint $table) {
    $table->decimal('latitud', 10, 8)->nullable();
    $table->decimal('longitud', 11, 8)->nullable();
    $table->integer('radio_asistencia_metros')->default(100)->comment('Radio para auto-registro QR');
});
```

### 1.2. Evolución de `actividad_sujetos` (Unified Attendance)
En lugar de una tabla nueva, añadimos campos de validación a la tabla intermedia existente. Esto permite que una persona "invitada" pase a ser "asistente" simplemente llenando estos campos.

```php
Schema::table('actividad_sujetos', function (Blueprint $table) {
    // Marcador de asistencia
    $table->timestamp('hora_asistencia')->nullable()->comment('Si es null, no ha asistido');
    
    // Metadatos de seguridad
    $table->enum('metodo_registro', ['manual_admin', 'qr_self_service'])->nullable();
    $table->foreignId('registrado_por')->nullable()->constrained('users');
    
    // Evidencia GPS para QR (Anti-Casa)
    $table->decimal('latitud_capturada', 10, 8)->nullable();
    $table->decimal('longitud_capturada', 11, 8)->nullable();
    
    // Blindaje Anti-Amigo (Device Locking)
    $table->string('device_fingerprint')->nullable()->comment('Hash único del navegador/dispositivo');
});
```

### 1.3. Configuración de "Sesiones Eternas" (Cero Fricción)
Para que el simpatizante no tenga que loguearse cada vez, modificamos `config/sanctum.php` o el tiempo de vida de la sesión:
- **Expiration**: Configurar `expiration => 525600` (1 año en minutos).
- **Persistent Storage**: Asegurar que en el Frontend el token se guarde en `localStorage`.

---

### 1.3. Matriz de Permisos (Spatie)
Para asegurar el sistema, definimos los siguientes permisos en el `RoleSeeder`:
- `actividades:asistencia-manual`: Permite a organizadores buscar personas y marcarlas como presentes.
- `actividades:asistencia-self`: Permite a cualquier usuario logueado (simpatizante) marcar su propia asistencia vía QR.
- `actividades:asistencia-reporte`: Permite ver y exportar la lista de presentes.

---

## 2. Lógica de Negocio (Controladores)

Necesitamos dos endpoints distintos para cada método de registro, ya que la validación y la seguridad son diferentes.

### 2.1. Rutas API (`api.php`)
```php
Route::prefix('actividades/{actividad}')->group(function () {
    // Endpoints de Gestión de Participantes (Sujetos)
    Route::get('/sujetos', [ActividadSujetoController::class, 'index']); 
    
    // Registro Manual por Admin (Marca como asistido a un sujeto existente o crea uno)
    Route::post('/asistencias/admin', [ActividadSujetoController::class, 'marcarAsistenciaManual']); 
    
    // Auto-Registro QR (Crea/Actualiza el sujeto con validación GPS)
    Route::post('/asistencias/self-register', [ActividadSujetoController::class, 'marcarAsistenciaQR']); 
});
```

### 2.2. Lógica: `marcarAsistenciaManual` (Protección de Admin)
- **Middleware**: `permission:actividades:asistencia-manual`
- **Input**: `persona_id`.
- **Lógica**: El sistema confía en el organizador. Se registra quién hizo la acción (`registrado_por = auth()->id()`).

### 2.3. Lógica: `marcarAsistenciaQR` (Blindajes de Oro)
- **Middleware**: `auth:sanctum`
- **Input**: `latitud_usuario`, `longitud_usuario`, `browser_fingerprint`.
- **Regla Anti-Amigo**: 
  - Antes de guardar, el sistema verifica: `ActividadSujeto::where('actividad_id', $id)->where('device_fingerprint', $request->browser_fingerprint)->exists()`.
  - Si ya existe un registro con ese "dedo digital" hoy, se bloquea: *"Este dispositivo ya fue usado para marcar asistencia hoy"*.
- **Regla Anti-Casa (Geofencing)**: 
  - El backend calcula la distancia Haversine. Si es > 100m (o el radio definido), se deniega. No se confía en validaciones de JS.
- **Acción**: Se registra la asistencia usando `auth()->user()->persona_id`.

---

## 3. Dinámica del Sistema (Frontend)

La UI se optimiza para que el proceso tome menos de 5 segundos.

### 3.1. Camino A: El Simpatizante se Autogestiona (Web)
1. **Escaneo Directo**: El usuario escanea el QR estático de la pared.
2. **One-Touch Check-in**: 
   - El sistema valida la sesión (Eternal Session). 
   - Solicita permisos de GPS automáticamente.
   - Botón gigante: **"Confirmar mi Asistencia"**.
3. **Validación Invisible**: El frontend envía el `fingerprint` generado por librerías como `FingerprintJS` para el bloqueo Anti-Amigo.

### 3.2. Camino B: El Coordinador Asiste (Mesa de Control)
El coordinador usa su panel de control en modo "Escaneo":
1. **Escaneo de DNI**: La cámara del coordinador lee el código de barras PDF417 del DNI físico del simpatizante.
2. **Búsqueda Inteligente**: Si el DNI está dañado, usa la barra de búsqueda manual (debounce 300ms) por número de documento.
3. **Registro Forzado**: El coordinador marca la asistencia sin validación GPS (ya que él es el garante de que la persona está ahí).

---

## 4. Ventajas de la Arquitectura Híbrida

1. **Anti-Cuello de Botella**: Si llegan 200 personas de golpe, un solo organizador tardaría mucho en registrarlos a mano. El QR impreso en tamaño A3 permite que 50 personas escaneen y se registren simultáneamente.
2. **Inclusividad**: El registro manual del administrador asegura que las personas mayores o sin smartphone también queden registradas.
3. **Cero Fraudes Remotos**: La validación estricta de GPS (Geofencing) en el backend garantiza matemáticamente que el auto-registro solo ocurre en el lugar de los hechos. La auditoría de coordenadas guardadas en la BD sirve de evidencia.
