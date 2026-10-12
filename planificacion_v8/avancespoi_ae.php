<?php
$idMeta = (int)recibir_dato('idMeta');
$idAvance = (int)recibir_dato('idAvanceEdit');
if ($idMeta <= 0) die('Debe abrir una meta desde la lista');
$sql = "SELECT id_meta, denominacion, tipo_meta, valor_real_2026, valor_real_2027, valor_real_2028,
        campo_fecha_avance, campo_cantidad, campo_hombres, campo_mujeres,
        campo_instituciones, campo_dpto_dist_col, campo_logros, campo_dificultades, campo_observaciones,
        campo_expediente, campo_resolucion, campo_excel, campo_pdf
        FROM planificacion.metas WHERE id_meta = $idMeta AND activo IS TRUE";
$db->set_query($sql);
$db->execute_query();
$meta = $db->get_array();
if (!$meta) die('Meta inexistente o anulada');
$acumulados = array(2026 => 0, 2027 => 0, 2028 => 0);
$sql = "SELECT anio, COALESCE(SUM(cantidad), 0) AS acumulado
    FROM planificacion.avances WHERE id_meta = $idMeta AND activo IS TRUE";
if ($idAvance > 0) $sql .= " AND id_avance <> $idAvance";
$sql .= " GROUP BY anio";
$db->set_query($sql);
$db->execute_query();
while ($total = $db->get_array()) {
    if (isset($acumulados[(int)$total['anio']])) $acumulados[(int)$total['anio']] = (int)$total['acumulado'];
}
$row = array('anio' => 2026, 'fecha_avance' => date('Y-m-d'), 'cantidad' => '', 'hombres' => 0, 'mujeres' => 0,
    'instituciones' => 0, 'id_dpto' => '', 'id_distrito' => '', 'id_colonia' => '', 'dpto' => '',
    'distrito' => '', 'colonia' => '', 'logros' => '', 'dificultades' => '', 'observaciones' => '',
    'expediente' => '', 'resolucion' => '', 'excel_nombre' => '', 'pdf_nombre' => '');
if ($idAvance > 0) {
    $sql = "SELECT a.anio, TO_CHAR(a.fecha_avance, 'YYYY-MM-DD') AS fecha_avance, a.cantidad,
        COALESCE(a.hombres, 0) AS hombres, COALESCE(a.mujeres, 0) AS mujeres,
        COALESCE(a.instituciones, 0) AS instituciones, a.id_dpto, a.id_distrito, a.id_colonia,
        COALESCE(d.dpto, '') AS dpto, COALESCE(dis.distrito, '') AS distrito,
        COALESCE(col.colonia, '') AS colonia, a.logros, a.dificultades, a.observaciones,
        COALESCE(a.expediente, '') AS expediente, COALESCE(a.resolucion, '') AS resolucion,
        COALESCE(a.excel_nombre, '') AS excel_nombre, COALESCE(a.pdf_nombre, '') AS pdf_nombre
        FROM planificacion.avances a
        LEFT JOIN tierra.dptos d ON d.id_dpto = a.id_dpto
        LEFT JOIN tierra.distritos dis ON dis.id_dpto = a.id_dpto AND dis.id_distrito = a.id_distrito
        LEFT JOIN tierra.colonias col ON col.id_dpto = a.id_dpto AND col.id_distrito = a.id_distrito
            AND col.id_colonia = a.id_colonia
        WHERE a.id_avance = $idAvance AND a.id_meta = $idMeta AND a.activo IS TRUE
        AND a.id_usuario = " . (int)$tra['id_usuario'];
    $db->set_query($sql);
    $db->execute_query();
    $registro = $db->get_array();
    if (!$registro) die('Avance inexistente o sin permiso de edicion');
    $row = $registro;
}
$opciones = '';
foreach (array(2026, 2027, 2028) as $anio) {
    $sel = ((int)$row['anio'] == $anio) ? ' selected="selected"' : '';
    $opciones .= '<option value="' . $anio . '"' . $sel . '>' . $anio . '</option>';
}
$resp['html'] = str_replace('{accion}', $idAvance ? 'Editar' : 'Agregar', $resp['html']);
$resp['html'] = str_replace('{idAvance}', $idAvance, $resp['html']);
$resp['html'] = str_replace('{idMeta}', $idMeta, $resp['html']);
$resp['html'] = str_replace('{nombreMeta}', htmlentities($meta['denominacion'], ENT_QUOTES, 'UTF-8'), $resp['html']);
$resp['html'] = str_replace('{tipoMeta}', htmlentities((string)$meta['tipo_meta'], ENT_QUOTES, 'UTF-8'), $resp['html']);
$resp['html'] = str_replace('{periodos}', $opciones, $resp['html']);
foreach (array('fecha_avance', 'cantidad', 'hombres', 'mujeres', 'instituciones', 'dpto_dist_col',
    'logros', 'dificultades', 'observaciones', 'expediente', 'resolucion', 'excel', 'pdf') as $campo) {
    $mostrar = ($meta['campo_' . $campo] === true || $meta['campo_' . $campo] === 't' || $meta['campo_' . $campo] === '1')
        ? ($campo == 'dpto_dist_col' ? 'table-row' : 'block') : 'none';
    $resp['html'] = str_replace('{mostrar_' . $campo . '}', $mostrar, $resp['html']);
}
$resp['html'] = str_replace('{fechaAvance}', htmlentities((string)$row['fecha_avance'], ENT_QUOTES, 'UTF-8'), $resp['html']);
foreach (array('cantidad', 'hombres', 'mujeres', 'instituciones', 'logros', 'dificultades') as $clave) {
    $resp['html'] = str_replace('{' . $clave . '}', htmlentities((string)$row[$clave], ENT_QUOTES, 'UTF-8'), $resp['html']);
}
$resp['html'] = str_replace('{observacion}', htmlentities((string)$row['observaciones'], ENT_QUOTES, 'UTF-8'), $resp['html']);
$resp['html'] = str_replace('{expediente}', htmlentities((string)$row['expediente'], ENT_QUOTES, 'UTF-8'), $resp['html']);
$resp['html'] = str_replace('{resolucion}', htmlentities((string)$row['resolucion'], ENT_QUOTES, 'UTF-8'), $resp['html']);
$excelNombre = htmlentities((string)$row['excel_nombre'], ENT_QUOTES, 'UTF-8');
$pdfNombre = htmlentities((string)$row['pdf_nombre'], ENT_QUOTES, 'UTF-8');
$resp['html'] = str_replace('{excelNombre}', $excelNombre, $resp['html']);
$resp['html'] = str_replace('{pdfNombre}', $pdfNombre, $resp['html']);
$excelActual = ($idAvance > 0 && $excelNombre != '')
    ? 'Archivo actual: <a href="./secciones/planificacion/descargar_archivo_avance.php?idAvance=' . $idAvance . '&tipo=excel" target="_blank">' . $excelNombre . '</a>' : '';
$pdfActual = ($idAvance > 0 && $pdfNombre != '')
    ? 'Archivo actual: <a href="./secciones/planificacion/descargar_archivo_avance.php?idAvance=' . $idAvance . '&tipo=pdf" target="_blank">' . $pdfNombre . '</a>' : '';
$resp['html'] = str_replace('{excelActual}', $excelActual, $resp['html']);
$resp['html'] = str_replace('{pdfActual}', $pdfActual, $resp['html']);
$resp['html'] = str_replace('{idDpto}', htmlentities((string)$row['id_dpto'], ENT_QUOTES, 'UTF-8'), $resp['html']);
$resp['html'] = str_replace('{idDistrito}', htmlentities((string)$row['id_distrito'], ENT_QUOTES, 'UTF-8'), $resp['html']);
$resp['html'] = str_replace('{idColonia}', htmlentities((string)$row['id_colonia'], ENT_QUOTES, 'UTF-8'), $resp['html']);
$resp['html'] = str_replace('{dpto}', htmlentities((string)$row['dpto'], ENT_QUOTES, 'UTF-8'), $resp['html']);
$resp['html'] = str_replace('{distrito}', htmlentities((string)$row['distrito'], ENT_QUOTES, 'UTF-8'), $resp['html']);
$resp['html'] = str_replace('{colonia}', htmlentities((string)$row['colonia'], ENT_QUOTES, 'UTF-8'), $resp['html']);
foreach (array(2026, 2027, 2028) as $anio) {
    $resp['html'] = str_replace('{maximo' . $anio . '}', (int)$meta['valor_real_' . $anio], $resp['html']);
    $resp['html'] = str_replace('{acumulado' . $anio . '}', (int)$acumulados[$anio], $resp['html']);
}
?>
