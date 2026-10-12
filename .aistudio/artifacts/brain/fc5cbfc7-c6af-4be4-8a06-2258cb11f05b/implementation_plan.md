# Incorporación de Reporte Excel por Avance en AvancesListar (planificacion_v8)

Este plan detalla la creación de la nueva versión `planificacion_v8` en el sistema institucional legacy SIRA, adaptando el motor de reporte tabular para generar un archivo Excel (`.xls`) nativo a partir de un `id_avance` seleccionado, e integrando su botón de acción dentro de la matriz de `AvancesListar.php`.

### Decisiones Críticas y Preferencias del Usuario

> [!IMPORTANT]
> - **Origen de datos y alcance**: El reporte Excel se genera a partir de la consulta detallada del avance específico (`id_avance`) y su meta asociada activa, aplicando la lógica legacy de formateo y renderizado de celdas HTML enviadas con cabeceras `application/octet-stream; filename=reporte_avance_{id}.xls`.
> - **Ubicación en UI**: Se incorpora como una columna de acción (`Excel`) en la matriz de `AvancesListar.php`, junto a los botones de `Editar` y `Anular`, utilizando un contenedor e icono estandarizado de `16x16 px`. Para registros anulados, la celda permanece vacía para preservar la integridad visual y evitar acciones sobre datos inactivos.
> - **Versionado**: Siguiendo la regla de redondeo hacia arriba, la versión base actual `planificacion_v7` se eleva a `planificacion_v8/` manteniendo íntegramente la compatibilidad y funcionalidad existente.

---

## 1. Visión General del Cambio

Permitir a los usuarios del módulo de planificación descargar directamente desde la grilla de avances un reporte formateado en Excel con los datos consolidados del avance (metadatos de la meta, objetivo estratégico, nivel, indicador, fechas, cantidades desglosadas, expediente, resolución, logros, dificultades y observaciones).

```
┌────────────────────────────────────────────────────────┐
│ planificacion.avancespoi_vista                         │
│ ┌────────────────────────────────────────────────────┐ │
│ │ Matriz AvancesListar.php                           │ │
│ │ ┌────────┬────────┬───────┬──────┬──────────┬────┐ │ │
│ │ │ Editar │ Anular │ Excel │  Año │ Cantidad │... │ │ │
│ │ ├────────┼────────┼───────┼──────┼──────────┼────┤ │ │
│ │ │ [edit] │ [del]  │ [xls] │ 2026 │      150 │... │ │ │
│ │ └────────┴────────┴───────┴──────┴──────────┴────┘ │ │
│ └───────────────────────┬────────────────────────────┘ │
└─────────────────────────┼──────────────────────────────┘
                          │ GET idAvance
                          ▼
┌────────────────────────────────────────────────────────┐
│ reporte_excel_avance.php (planificacion_v8)            │
│ 1. Valida id_avance y activo IS TRUE                   │
│ 2. Ejecuta consulta relacional y mapea campos/formatos │
│ 3. Estructura tabla HTML (estilo grilla SIRA)          │
│ 4. Emite headers application/octet-stream (.xls)       │
└────────────────────────────────────────────────────────┘
```

---

## 2. Experiencia de Usuario y Diseño Visual

1. **Columna de Acción en la Matriz**:
   - En `AvancesListar.php`, la cabecera agrega la columna `Excel` (o `Reporte`).
   - Para filas activas:
     ```html
     <div style="width:16px;height:16px;">
         <a href="./secciones/planificacion/reporte_excel_avance.php?idAvance={id}" target="_blank" title="Reporte Excel">
             <img src="fuentestra/imagenes/bot/16x16/excel.png" style="width:16px; height:16px;" />
         </a>
     </div>
     ```
   - Para filas anuladas: la celda permanece vacía sin enlaces ni imágenes activas, con el fondo gris de auditoría `#eeeeee`.

2. **Descarga Inmediata**:
   - El enlace abre en pestaña o descarga directa vía encabezados HTTP estándar de SIRA:
     ```php
     header("Content-type: application/octet-stream");
     header("Content-Disposition: attachment; filename=reporte_avance_{idAvance}.xls");
     header("Pragma: no-cache");
     header("Expires: 0");
     ```

---

## 3. Especificación Técnica y Arquitectura

### A. Nuevo Script: `reporte_excel_avance.php`
- Ubicado en `planificacion_v8/reporte_excel_avance.php`.
- Recibe y valida `idAvance` sanitizado como entero.
- Conecta a la base de datos PostgreSQL institucional (respetando la configuración y manejo de errores del sistema legacy).
- Consulta los datos del avance junto a la jerarquía:
  ```sql
  SELECT a.id_avance, a.id_meta, a.anio, a.cantidad, a.porcentaje_avance,
         TO_CHAR(a.fecha_avance, 'DD/MM/YYYY') AS fecha_avance,
         TO_CHAR(a.fecha_registro, 'DD/MM/YYYY HH24:MI:SS') AS fecha_registro,
         a.hombres, a.mujeres, a.instituciones,
         COALESCE(TRIM(d.dpto), '') AS departamento,
         COALESCE(TRIM(dis.distrito), '') AS distrito,
         COALESCE(TRIM(col.colonia), '') AS colonia,
         a.logros, a.dificultades, a.observaciones,
         a.expediente, a.resolucion, a.excel_nombre, a.pdf_nombre,
         m.denominacion AS meta, m.tipo_meta, m.responsable,
         oe.nombre AS objetivo_estrategico, ne.codigo AS nivel_estrategico
  FROM planificacion.avances a
  JOIN planificacion.metas m ON m.id_meta = a.id_meta AND m.activo IS TRUE
  JOIN planificacion.niveles_estrategicos ne ON ne.id_nivel = m.id_nivel
  JOIN planificacion.objetivos_estrategicos oe ON oe.id_oe = ne.id_oe
  LEFT JOIN tierra.dptos d ON d.id_dpto = a.id_dpto
  LEFT JOIN tierra.distritos dis ON dis.id_dpto = a.id_dpto AND dis.id_distrito = a.id_distrito
  LEFT JOIN tierra.colonias col ON col.id_dpto = a.id_dpto AND col.id_distrito = a.id_distrito AND col.id_colonia = a.id_colonia
  WHERE a.id_avance = :id_avance AND a.activo IS TRUE
  ```
- Aplica el formateo de datos adaptado del script institucional provisto:
  - Estructuración de cabeceras, títulos y metadatos institucionales.
  - Generación de grilla `<table>` con estilos compatibles para hojas de cálculo Excel (`border`, `bgColor`, `text-align`, formato numérico con separador de miles).
  - Emisión de cabeceras de descarga `reporte_avance_{idAvance}.xls`.

### B. Modificación en `AvancesListar.php`
- Desplazamiento de índices de columnas en `$matriz` para acomodar la tercera acción (`Excel` en columna 2, y los datos a partir de columna 3).
- Alineación centrada para celdas de acción (índices 0, 1, 2) e izquierda/derecha para las columnas de datos.
- Conservación de los flags de visibilidad dinámica (`campo_expediente`, `campo_resolucion`, `campo_excel`, `campo_pdf`).

### C. Creación y estructura de `planificacion_v8/`
- Duplicación de todos los archivos vigentes de `planificacion_v7/` a `planificacion_v8/`.
- Actualización de referencias internas (`planificacion_v7` -> `planificacion_v8` si hubiera rutas explícitas o en JS de inicialización).
- Generación de archivo comprimido `planificacion_v8.zip` para entrega formal.
- Codificación en **Windows-1252 / ANSI**.

---

## 4. Plan de Ejecución Paso a Paso

1. **Crear el directorio `planificacion_v8/` y sus subcarpetas (`rpc/`)**.
2. **Copiar y preparar la base de código desde `planificacion_v7/`**.
3. **Crear `planificacion_v8/reporte_excel_avance.php`** con la adaptación del generador Excel para un avance y meta activos.
4. **Modificar `planificacion_v8/rpc/AvancesListar.php`** para agregar la columna y botón de acción Excel (16x16 px).
5. **Generar el archivo comprimido `planificacion_v8.zip`**.
6. **Verificar codificación Windows-1252 y consistencia de sintaxis PHP 5.6**.
