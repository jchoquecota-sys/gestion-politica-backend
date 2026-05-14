# 📑 Guía de Implementación: Paginación y Filtros (Frontend)

Se ha implementado un sistema de paginación profesional en todos los módulos principales (`Personas`, `Bases`, `Sectores`, `Cargos`). Esta guía explica cómo consumir estos endpoints para que tu tabla o lista sea fluida y escalable.

---

## 📡 1. Estructura de la Respuesta

A diferencia de antes, los datos ya no vienen en un array simple dentro de `data`. Ahora la respuesta tiene dos partes clave:

```json
{
  "status": "success",
  "data": [ ... ], // Los registros de la página actual
  "meta": {
    "current_page": 1,
    "last_page": 16,
    "per_page": 15,
    "total": 237
  }
}
```

*   **`data`**: Array con los objetos formateados.
*   **`meta`**: Información necesaria para renderizar tu componente de paginación (botones "Anterior", "Siguiente", números de página).

---

## 🔍 2. Parámetros de Consulta (Query Params)

Puedes enviar los siguientes parámetros opcionales en el `GET`:

| Parámetro | Tipo | Descripción | Ejemplo |
| :--- | :--- | :--- | :--- |
| `page` | `int` | Número de página a solicitar. | `?page=2` |
| `per_page` | `int` | Cuántos registros quieres por página. | `?per_page=10` |
| `search` | `string` | Búsqueda global (Nombres, Apellidos, DNI). | `?search=Carlos` |
| `sort_by` | `string` | Campo para ordenar. | `?sort_by=nombres` |
| `sort_order`| `string` | Dirección: `asc` o `desc`. | `?sort_order=asc` |

**Ejemplo de URL completa:**
`GET /api/personas?page=1&per_page=15&search=mendoza&sort_by=nombres&sort_order=asc`

---

## 🚀 3. ¿Cómo integrarlo en React/Next.js?

Si usas una librería de tablas (como TanStack Table o un simple componente de UI), te recomendamos manejar el estado de la siguiente manera:

```javascript
const [pagination, setPagination] = useState({ page: 1, perPage: 15 });
const [search, setSearch] = useState("");

// Hook para cargar datos
useEffect(() => {
  const fetchData = async () => {
    const res = await axios.get('/api/personas', {
      params: {
        page: pagination.page,
        per_page: pagination.perPage,
        search: search
      }
    });
    
    setList(res.data.data);
    setTotalPages(res.data.meta.last_page);
    setTotalRecords(res.data.meta.total);
  };
  fetchData();
}, [pagination, search]);
```

---

## 💡 Tips Profesionales

1.  **Debounce en Búsqueda**: No dispares la petición en cada tecla que presione el usuario. Usa un `debounce` de 300ms-500ms para el campo `search`.
2.  **Carga Inicial**: Por defecto, la API siempre devolverá la página 1 con 15 registros si no envías parámetros.
3.  **Skeleton Screens**: Mientras `meta` y `data` están cargando, muestra una animación de carga para mejorar la experiencia de usuario (UX).
