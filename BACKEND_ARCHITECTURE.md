# 🏗️ Guía de Arquitectura y Escalabilidad (Backend)

Esta guía detalla el estándar de desarrollo utilizado en este proyecto para asegurar que el sistema sea profesional, estructurado, robusto y fácil de mantener.

---

## 🏛️ Stack Tecnológico
*   **Framework:** Laravel 11+
*   **Autenticación:** Laravel Sanctum (Stateless API)
*   **Autorización:** Spatie Laravel Permission (Roles y Permisos consolidados)
*   **Arquitectura:** API-First (Responde siempre JSON, sin redirecciones de sesión)

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
Asegúrate de usar los traits de Spatie si el modelo necesita relacionarse directamente con roles/permisos (ej: `HasRoles` en el modelo `User`).

### 3. Permisos (Seeder)
Añade los nuevos permisos en `database/seeders/PermissionSeeder.php`. Siguiendo el **modelo unificado**, cada módulo cuenta con un permiso de lectura general `*:view` en lugar de dividir innecesariamente en `list` y `view`.
```php
// PermissionSeeder.php
private array $permissions = [
    // ...
    'modulo:view',   // Reemplaza list/view por un único permiso de acceso/lectura
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
*   **Transacciones:** Envuelve `store`, `update` y `destroy` en `DB::transaction` para asegurar la integridad referencial.
*   **Consistencia:** Usa un método privado (ej: `formatResource()`) o un Resource de API para devolver siempre la misma estructura de JSON.
*   **Inyección:** Usa Route Model Binding para inyectar los modelos directamente en los métodos.

### 6. Rutas (Routes)
Registra las rutas en `routes/api.php` dentro del grupo `auth:sanctum`. Usa el middleware de permisos unificado `*:view` para las consultas.
```php
Route::prefix('modulo')->name('api.modulo.')->group(function () {
    Route::get('/', [ModuloController::class, 'index'])->middleware('permission:modulo:view');
    Route::post('/', [ModuloController::class, 'store'])->middleware('permission:modulo:create');
    // ...
});
```

---

## 🔐 Estándares de Seguridad y Auth

### API-First Authentication
Se ha implementado un middleware personalizado en `app/Http/Middleware/Authenticate.php` que sobreescribe el comportamiento por defecto de Laravel.
*   **Nunca redirige a `/login`** (comportamiento web por defecto).
*   Si falla la autenticación, devuelve siempre un **401 Unauthorized** en JSON.
*   Se configura en `bootstrap/app.php` reemplazando el alias `auth`.

### Gate Bypass (Super Admin)
El rol `super-admin` está configurado en `AppServiceProvider.php` para saltarse todos los checks de permisos. Si un usuario tiene este rol, `can()` y `middleware('permission:...')` siempre retornarán `true`.

### Consolidación de Permisos (RBAC Simplificado)
Para mitigar la complejidad y evitar inconsistencias en la UI y API, eliminamos los permisos individuales de tipo `*:list` (listar tablas) y los unificamos con los permisos de tipo `*:view` (ver detalles). De esta manera:
- Si el usuario tiene acceso a la pantalla/módulo (`modulo:view`), puede realizar la petición index en la API.
- Se previene el error común de habilitar el acceso a una lista pero bloquear el acceso al detalle de un registro individual del mismo tipo.

---

## 📊 Paginación y Estandarización de Listas
Para garantizar el rendimiento a medida que crecen los datos, todos los endpoints de listado (`index`) deben implementar paginación profesional.

**Parámetros aceptados:**
*   `per_page`: Cantidad de registros por página (default: 15).
*   `page`: Número de página actual.
*   `search`: Término de búsqueda global (nombres, DNI, etc).
*   `sort_by`: Campo por el cual ordenar (ej: `created_at`).
*   `sort_order`: Dirección del orden (`asc` o `desc`).

**Estructura de Respuesta Estándar:**
```json
{
    "status": "success",
    "data": [...],
    "meta": {
        "current_page": 1,
        "last_page": 10,
        "per_page": 15,
        "total": 150
    }
}
```

---

## 🚀 Buenas Prácticas de Escalabilidad

1.  **Evitar el Guard 'sanctum' en Base de Datos**: Siempre usa el guard `web` en tus seeders y modelos (incluso usando Sanctum). Hemos forzado `protected $guard_name = 'web'` en el modelo `User` para evitar conflictos de mapeo de Spatie en APIs.
2.  **Lógica de Negocio Compleja**: Si un controlador empieza a tener métodos de más de 30-40 líneas, mueve esa lógica a una **Service Class** en `app/Services` o a una clase de Acción única (`app/Actions`).
3.  **Resources (Opcional)**: Para proyectos muy grandes, considera usar `JsonResource` de Laravel en lugar de métodos `formatResource()` manuales.
4.  **Filtros y Búsqueda**: Para listar recursos, usa Query Scopes en los modelos para manejar filtros, ordenamiento y búsquedas de forma limpia.
5.  **Soft Deletes y Trazabilidad (Audit)**: 
    *   **Por qué**: En un sistema profesional nunca se borra físicamente la información. El Soft Delete permite recuperar datos y mantener la integridad referencial histórica.
    *   **Automatización**: Se recomienda implementar un `BaseModel` o un Trait global (ej: `HasAuditFields`) que use los [Model Observers](https://laravel.com/docs/11.x/eloquent#observers) o los eventos `creating`, `updating` y `deleting` para asignar automáticamente los IDs de usuario (`auth()->id()`) a los campos `created_by`, `updated_by` y `deleted_by`.
    *   **Consultas**: Recuerda que Eloquent excluye los registros "borrados" por defecto. Usa `->withTrashed()` cuando necesites incluirlos en reportes o auditorías.

---

## 📦 Comandos de Mantenimiento

*   **Limpiar todo el sistema:** `php artisan optimize:clear`
*   **Actualizar permisos y roles base:** `php artisan db:seed --class=PermissionSeeder`
*   **Reconstrucción limpia de la DB:** `php artisan migrate:fresh --seed`
*   **Listar rutas API:** `php artisan route:list --path=api`
