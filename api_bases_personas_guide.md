# 📖 Guía de API Actualizada: Bases y Personas

---

## ✨ Nuevas Funcionalidades
- **Personal de Base**: Gestión independiente del personal de cada base (añadir, editar, eliminar, filtrar).
- **Cargo Participante**: Nuevo cargo base para miembros generales.
- **Vinculación desde Personas**: Al crear/editar una persona, ahora puedes vincularla directamente a una base o sector.
- **Membresías en GET Personas**: El detalle de una persona ahora incluye a qué bases y sectores pertenece.

---

## 📍 Módulo: Bases (`/api/bases`)

### CRUD de Bases (Sin cambios de interfaz)
| Método | URL | Permiso | Descripción |
|--------|-----|---------|-------------|
| `GET` | `/api/bases?sector_id={id}` | `bases:list` | Lista bases (sector_id obligatorio sin `list-all`) |
| `POST` | `/api/bases` | `bases:create` | Crea base con personal inicial opcional |
| `GET` | `/api/bases/{id}` | `bases:view` | Detalle completo de la base |
| `PUT` | `/api/bases/{id}` | `bases:edit` | Actualiza base y sincroniza personal |
| `DELETE` | `/api/bases/{id}` | `bases:delete` | Elimina base (soft delete) |

---

## 👥 Gestión de Personal por Base (`/api/bases/{base}/personal`)

> Este es el nuevo conjunto de endpoints para administrar el panel de personal de una base.

### 1. Listar Personal de una Base
- **URL:** `GET /api/bases/{base_id}/personal`
- **URL con filtro:** `GET /api/bases/{base_id}/personal?cargo_id={id}`
- **Permiso:** `bases:view`
- **Respuesta:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 5,
      "persona": {
        "id": 1,
        "nombre_completo": "Juan Pérez",
        "dni": "12345678",
        "celular": "999888777"
      },
      "cargo": { "id": 4, "nombre": "Participante" },
      "es_principal": false,
      "fecha_inicio": "2026-05-01",
      "observaciones": null
    }
  ]
}
```

### 2. Añadir Persona a la Base
- **URL:** `POST /api/bases/{base_id}/personal`
- **Permiso:** `bases:edit`
- **Body:**
```json
{
  "persona_id": 3,
  "cargo_id": 4,
  "es_principal": false,
  "fecha_inicio": "2026-05-13",
  "observaciones": "Nuevo miembro"
}
```
> ⚠️ Si `es_principal: true`, se desmarca automáticamente al responsable anterior.

### 3. Editar Asignación de Personal
- **URL:** `PUT /api/bases/{base_id}/personal/{asignacion_id}`
- **Permiso:** `bases:edit`
- **Body:** (mismos campos excepto `persona_id` que ya no cambia)
```json
{
  "cargo_id": 2,
  "es_principal": true,
  "fecha_inicio": "2026-05-01",
  "observaciones": "Promovido a coordinador"
}
```

### 4. Desvincular Persona de la Base
- **URL:** `DELETE /api/bases/{base_id}/personal/{asignacion_id}`
- **Permiso:** `bases:edit`

> 💡 El `asignacion_id` es el `id` del objeto de la lista de personal (NO el `persona_id`).

---

## 👤 Módulo: Personas (`/api/personas`) - Actualizado

### Nuevos campos en `store` y `update`

Al crear o editar una persona, puedes vincularla opcionalmente:

```json
{
  "nombres": "María",
  "apellidos": "García",
  "dni": "87654321",
  "celular": "987654321",
  "email": "maria@example.com",
  "direccion": "Av. Principal 456",

  "base_id": 2,
  "cargo_base_id": 4,

  "sector_id": 1,
  "cargo_sector_id": 1
}
```

> **Regla de validación:** Si envías `base_id`, también debes enviar `cargo_base_id` (y viceversa para sector).

### Respuesta de `GET /api/personas` y `GET /api/personas/{id}`

Ahora incluye los arrays `bases` y `sectores`:

```json
{
  "status": "success",
  "data": {
    "id": 1,
    "nombre_completo": "Juan Pérez",
    "dni": "12345678",
    "bases": [
      {
        "asignacion_id": 5,
        "base_id": 2,
        "base_nombre": "Base Norte",
        "sector_nombre": "Sector Centro",
        "cargo_id": 4,
        "cargo_nombre": "Participante",
        "es_principal": false,
        "fecha_inicio": "2026-05-01"
      }
    ],
    "sectores": [
      {
        "asignacion_id": 3,
        "sector_id": 1,
        "sector_nombre": "Sector Centro",
        "cargo_id": 1,
        "cargo_nombre": "Responsable",
        "es_principal": true,
        "fecha_inicio": "2026-01-01"
      }
    ]
  }
}
```

---

## 🧩 Implementación Frontend Sugerida

### Panel de Personal en Detalle de Base
```
BasePage
  ├── BaseInfo (nombre, sector, coordenadas)
  ├── ResponsableCard (del objeto `responsable`)
  └── PersonalPanel
        ├── FiltrosCargo (dropdown con lista de /api/cargos)
        ├── PersonalTable (datos de GET /bases/{id}/personal)
        └── AñadirPersonaDialog (POST /bases/{id}/personal)
              └── PersonaSelector (buscar de /api/personas)
```

### Al crear/editar Persona (Formulario)
Añadir sección "Vinculaciones" (opcional, colapsable):
```
┌─ Vinculación a Base ─────────────────────┐
│  Base: [Selector]   Cargo: [Selector]    │
└──────────────────────────────────────────┘
┌─ Vinculación a Sector ───────────────────┐
│  Sector: [Selector]  Cargo: [Selector]   │
└──────────────────────────────────────────┘
```

---

## 📋 Cargos Disponibles (actualizados)
| ID | Nombre | Uso |
|----|--------|-----|
| 1 | Responsable | Líder / encargado principal |
| 2 | Colaborador | Apoyo en actividades |
| 3 | Coordinador | Coordina subgrupos |
| 4 | Participante | Miembro general / afiliado |
