# Gestión Política - Backend API

API robusta construida con Laravel 11 para la gestión política, control de asistencia geográfica (geofencing), gestión de bases/sectores, auditoría automática de registros y seguridad basada en roles y permisos (RBAC).

---

## 🚀 Requisitos del Sistema

- **PHP**: ^8.2 (Recomendado PHP 8.3+)
- **Composer**: ^2.0
- **Base de Datos**: MySQL / MariaDB (u otro compatible con Eloquent)
- **Extensiones de PHP necesarias**: `pdo_mysql`, `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `xml`

---

## 🛠️ Instalación y Configuración

Sigue estos pasos para levantar el entorno local de desarrollo:

### 1. Clonar el repositorio e instalar dependencias

```bash
composer install
```

### 2. Configurar el archivo de entorno

Copia el archivo de plantilla `.env.example` a `.env`:

```bash
cp .env.example .env
```

Abre `.env` y configura los accesos a tu base de datos local y demás variables del entorno:

```env
APP_NAME="Gestión Política"
APP_ENV=local
APP_KEY=base64:xxxx...
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestion_politica
DB_USERNAME=root
DB_PASSWORD=root
```

### 3. Generar la clave de la aplicación

```bash
php artisan key:generate
```

### 4. Ejecutar migraciones e inicializar datos (Seeders)

Este comando creará todas las tablas de la base de datos e inicializará el catálogo base de permisos, roles del sistema (`super-admin`), tipos de actividades y datos de prueba:

```bash
php artisan migrate:fresh --seed
```

### 5. Configurar el enlace de almacenamiento (Storage)

Para visualizar correctamente las fotos de portada de actividades y logotipos, genera el enlace simbólico del storage:

```bash
php artisan storage:link
```

### 6. Levantar el servidor local

```bash
php artisan serve
```

La API estará disponible por defecto en `http://127.0.0.1:8000`.

---

## 🔐 Control de Acceso Basado en Roles (RBAC)

El proyecto utiliza un sistema granular de control de acceso implementado con `spatie/laravel-permission`:

- **Bypass de Super-Admin**: El rol `super-admin` tiene privilegios absolutos sobre todos los módulos del sistema sin necesidad de asignación explícita de permisos individuales. Esto se define globalmente en `App\Providers\AppServiceProvider`.
- **Estructura Unificada de Permisos**: La nomenclatura estándar para los permisos sigue el formato `{modulo}:{accion}` (ej. `roles:view`, `personas:create`).
  - Nota: Para optimizar la arquitectura, se consolidaron los permisos de visualización en la acción unificada `:view` (eliminando la distinción obsoleta con `:list`).
- **Protección de Rutas**: Las rutas de la API en `routes/api.php` están protegidas a nivel de middleware mediante `permission:{modulo}:{accion}`.

---

## 🧪 Pruebas Unitarias y de Integración (Pest PHP)

La suite de pruebas automatizadas está construida sobre **Pest PHP**. Incluye pruebas unitarias para aislar lógica pura de negocio y pruebas de integración (Feature) para validar el comportamiento del framework y la base de datos.

### Base de Datos para Pruebas

Para evitar conflictos de drivers en entornos locales que carecen de SQLite, la suite está configurada en `phpunit.xml` para utilizar una base de datos MySQL dedicada llamada `gestion_politica_testing`.

Asegúrate de tener creada la base de datos antes de correr los tests:

```sql
CREATE DATABASE IF NOT EXISTS gestion_politica_testing;
```

### Ejecución de Pruebas

Para ejecutar la suite completa de pruebas:

```bash
./vendor/bin/pest
```

### Detalle de la Cobertura de Tests

#### 🔹 Pruebas Unitarias (`tests/Unit/`)
- **`GeoHelperTest`**: Valida el motor de cálculo de distancias por geofencing (Fórmula Haversine) con distancias cortas (100m) y de largo alcance (Lima a Cusco).
- **`SujetoMapperTest`**: Valida que la correspondencia dinámica entre nombres de sujetos y clases Eloquent (`Persona`, `Base`, `Sector`) sea robusta e insensible a mayúsculas/minúsculas.
- **`AuditFieldsTest`**: Utiliza reflexión de clases PHP para garantizar que los modelos clave del negocio implementen correctamente el trait `HasAuditFields` y sus relaciones de auditoría sin necesidad de tocar la base de datos.

#### 🔹 Pruebas de Integración / Feature (`tests/Feature/`)
- **`RbacSecurityTest`**: Valida que las rutas API bloqueen usuarios no autenticados (`401`), restrinjan accesos no autorizados (`403`), permitan accesos válidos (`200`) e integren correctamente el rol `super-admin`.
- **`ActividadAsistenciaTest`**: Prueba el flujo completo de asistencia (QR y DNI público):
  - Registro exitoso en rango de geofencing.
  - Bloqueo por geofencing fuera de rango.
  - Bloqueo de duplicado de dispositivo en un mismo día (Regla Anti-Amigo por browser fingerprint).
  - Bloqueo de DNI no pre-registrado.
  - Registro de DNI pre-registrado en rango.
- **`AuditFieldsIntegrationTest`**: Valida la persistencia física en base de datos de los campos de auditoría (`created_by`, `updated_by`, `deleted_by`) durante operaciones de creación, edición y eliminación de registros.

---

## 🛠️ Buenas Prácticas del Proyecto

1. **Campos de Auditoría Automáticos**: Cualquier modelo que requiera registrar quién lo creó, editó o eliminó (Soft Delete) debe usar el trait `App\Traits\HasAuditFields`.
2. **Geofencing Limpio**: Toda lógica de geolocalización o cálculo de coordenadas debe delegarse en `App\Helpers\GeoHelper`.
3. **Mapeo Eloquent Dinámico**: Si necesitas resolver un tipo de sujeto proveniente de API a su correspondiente clase o namespace Eloquent, delega en `App\Helpers\SujetoMapper`.
