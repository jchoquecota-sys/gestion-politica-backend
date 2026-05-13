# 🏗️ Guía de Arquitectura y Escalabilidad (Backend)

Esta guía detalla el estándar de desarrollo utilizado en este proyecto para asegurar que el sistema sea profesional, escalable y fácil de mantener.

---

## 🏛️ Stack Tecnológico
*   **Framework:** Laravel 11+
*   **Autenticación:** Laravel Sanctum (Stateless API)
*   **Autorización:** Spatie Laravel Permission (Roles y Permisos)
*   **Arquitectura:** API-First (Responde siempre JSON, sin redirecciones)

---

## 🛠️ Flujo para Implementar un Nuevo Módulo

Sigue estos pasos en orden para mantener la consistencia en todo el proyecto:

### 1. Base de Datos (Migration)
Crea la migración para la nueva entidad.
```bash
php artisan make:migration create_modulo_table
```

### 2. Modelo (Model)
Define el modelo con sus `fillable`, `casts` y relaciones. Si el modelo necesita permisos propios (ej: un Usuario), asegúrate de usar los traits de Spatie si corresponde.

### 3. Permisos (Seeder)
Añade los nuevos permisos en `database/seeders/PermissionSeeder.php`.
```php
// PermissionSeeder.php
private array $permissions = [
    // ...
    'modulo:list',
    'modulo:create',
    'modulo:edit',
    'modulo:delete',
];
```
Luego ejecuta: `php artisan db:seed --class=PermissionSeeder` para darlos de alta.

### 4. Validación (Form Requests)
Crea siempre Form Requests separados para `Store` y `Update`. No valides directamente en el controlador.
```bash
php artisan make:request Modulo/StoreModuloRequest
php artisan make:request Modulo/UpdateModuloRequest
```

### 5. Controlador (Controller)
El controlador debe seguir estas reglas:
*   **Transacciones:** Envuelve `store`, `update` y `destroy` en `DB::transaction` para asegurar la integridad de los datos.
*   **Consistencia:** Usa un método privado (ej: `formatResource()`) para devolver siempre la misma estructura de JSON.
*   **Inyección:** Usa Route Model Binding para inyectar los modelos directamente en los métodos.

### 6. Rutas (Routes)
Registra las rutas en `routes/api.php` dentro del grupo `auth:sanctum`.
```php
Route::prefix('modulo')->name('api.modulo.')->group(function () {
    Route::get('/', [ModuloController::class, 'index'])->middleware('permission:modulo:list');
    Route::post('/', [ModuloController::class, 'store'])->middleware('permission:modulo:create');
    // ...
});
```

---

## 🔐 Estándares de Seguridad y Auth

### API-First Authentication
Se ha implementado un middleware personalizado en `app/Http/Middleware/Authenticate.php` que sobreescribe el comportamiento por defecto de Laravel.
*   **Nunca redirige a `/login`**.
*   Si falla la autenticación, devuelve siempre un **401 Unauthorized** en JSON.
*   Se configura en `bootstrap/app.php` reemplazando el alias `auth`.

### Gate Bypass (Super Admin)
El rol `super-admin` está configurado en `AppServiceProvider.php` para saltarse todos los checks de permisos. Si un usuario tiene este rol, `can()` y `middleware('permission:...')` siempre retornarán `true`.

---

## 🚀 Buenas Prácticas de Escalabilidad

1.  **Evitar el Guard 'sanctum' en Base de Datos**: Siempre usa el guard `web` en tus seeders y modelos (incluso usando Sanctum). Hemos forzado `protected $guard_name = 'web'` en el modelo `User` para evitar conflictos.
2.  **Lógica de Negocio Compleja**: Si un controlador empieza a tener métodos de más de 30-40 líneas, mueve esa lógica a una **Service Class** en `app/Services`.
3.  **Resources (Opcional)**: Para proyectos muy grandes, considera usar `JsonResource` de Laravel en lugar de métodos `formatResource()` manuales.
4.  **Filtros y Búsqueda**: Para listar recursos, usa Query Scopes en los modelos para manejar filtros, ordenamiento y búsquedas de forma limpia.

---

## 📦 Comandos de Mantenimiento

*   **Limpiar todo el sistema:** `php artisan optimize:clear`
*   **Actualizar permisos:** `php artisan db:seed --class=PermissionSeeder`
*   **Listar rutas API:** `php artisan route:list --path=api`
