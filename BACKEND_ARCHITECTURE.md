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
Crea la migración para la nueva entidad incluyendo siempre los campos de trazabilidad y Soft Deletes.
```bash
php artisan make:migration create_modulo_table
```
**Campos Estándar:**
```php
$table->id();
// ... campos propios del módulo
$table->softDeletes(); // deleted_at
$table->unsignedBigInteger('created_by')->nullable();
$table->unsignedBigInteger('updated_by')->nullable();
$table->unsignedBigInteger('deleted_by')->nullable();
$table->timestamps(); // created_at, updated_at
```

### 2. Modelo (Model)
Define el modelo incluyendo el trait de SoftDeletes y las relaciones de auditoría.
```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Modulo extends Model {
    use SoftDeletes;

    protected $fillable = [
        'nombre', 
        'created_by', 
        'updated_by', 
        'deleted_by'
    ];

    // Relaciones de trazabilidad
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function deleter() { return $this->belongsTo(User::class, 'deleted_by'); }
}
```
Asegúrate de usar los traits de Spatie si el modelo necesita permisos (ej: `HasRoles`).

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
5.  **Soft Deletes y Trazabilidad (Audit)**: 
    *   **Por qué**: En un sistema profesional nunca se borra físicamente la información. El Soft Delete permite recuperar datos y mantener la integridad referencial histórica.
    *   **Automatización**: Se recomienda implementar un `BaseModel` o un Trait global (ej: `HasAuditFields`) que use los [Model Observers](https://laravel.com/docs/11.x/eloquent#observers) o los eventos `creating`, `updating` y `deleting` para asignar automáticamente los IDs de usuario (`auth()->id()`) a los campos `created_by`, `updated_by` y `deleted_by`.
    *   **Consultas**: Recuerda que Eloquent excluye los registros "borrados" por defecto. Usa `->withTrashed()` cuando necesites incluirlos en reportes o auditorías.

---

## 📦 Comandos de Mantenimiento

*   **Limpiar todo el sistema:** `php artisan optimize:clear`
*   **Actualizar permisos:** `php artisan db:seed --class=PermissionSeeder`
*   **Listar rutas API:** `php artisan route:list --path=api`
