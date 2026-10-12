<?php
$idMeta = (int)recibir_dato('idMeta');
if ($idMeta <= 0) die('Meta invalida');
$anio = (int)recibir_dato('filtroAnio');
$buscar = trim(recibir_dato('cadenaBuscar'));
$mostrarAnuladosDato = recibir_dato('mostrarAnulados');
$mostrarAnulados = ($mostrarAnuladosDato === true || $mostrarAnuladosDato === '1' || $mostrarAnuladosDato === 1);

$sql = "SELECT campo_expediente, campo_resolucion, campo_excel, campo_pdf
    FROM planificacion.metas WHERE id_meta = $idMeta AND activo IS TRUE";
$db->set_query($sql);
$db->execute_query();
$meta = $db->get_array();
if (!$meta) die('Meta inexistente o anulada');
$campoHabilitado = array();
foreach (array('expediente', 'resolucion', 'excel', 'pdf') as $campo) {
    $valor = $meta['campo_' . $campo];
    $campoHabilitado[$campo] = ($valor === true || $valor === 't' || $valor === '1' || $valor === 1);
}

$sql = "SELECT a.id_avance, a.id_meta, a.anio, a.cantidad, a.porcentaje_avance,
    TO_CHAR(a.fecha_avance, 'DD/MM/YYYY') AS fecha_avance,
    TO_CHAR(a.fecha_registro, 'DD/MM/YYYY HH24:MI:SS') AS fecha_registro,
    a.observaciones, a.hombres, a.mujeres, a.instituciones, a.logros, a.dificultades,
    a.expediente, a.resolucion, a.excel_nombre, a.pdf_nombre, a.id_usuario, a.activo
    FROM planificacion.avances a WHERE a.id_meta = $idMeta";
if (!$mostrarAnulados) $sql .= " AND a.activo IS TRUE";
if ($anio > 0) $sql .= " AND a.anio = $anio";
if ($buscar != '') {
    $buscar = pg_escape_string($buscar);
    $sql .= " AND (a.observaciones ILIKE '%$buscar%' OR a.logros ILIKE '%$buscar%'
        OR a.dificultades ILIKE '%$buscar%' OR a.expediente ILIKE '%$buscar%'
        OR a.resolucion ILIKE '%$buscar%' OR a.excel_nombre ILIKE '%$buscar%'
        OR a.pdf_nombre ILIKE '%$buscar%')";
}
$sql .= ' ORDER BY a.activo DESC, a.anio DESC, a.fecha_avance DESC, a.fecha_registro DESC, a.id_avance DESC';
$db->set_query($sql);
$db->execute_query();

$matriz = array();
$titulos = array('Editar', 'Anular', 'A�o', 'Cantidad', 'Hombres', 'Mujeres', 'Instituciones',
    '% Equivalente', 'Fecha Avance', 'Fecha Registro', 'Logros', 'Dificultades', 'Observaciones');
if ($campoHabilitado['expediente']) $titulos[] = 'Expediente';
if ($campoHabilitado['resolucion']) $titulos[] = 'Resoluci�n';
if ($campoHabilitado['excel']) $titulos[] = 'Excel';
if ($campoHabilitado['pdf']) $titulos[] = 'PDF';
foreach ($titulos as $i => $titulo) {
    $matriz[0][$i]['html'] = $titulo;
    $matriz[0][$i]['style'] = 'text-align:left;';
}

$c = 0;
while ($row = $db->get_array()) {
    $c++;
    $id = (int)$row['id_avance'];
    $activo = ($row['activo'] === true || $row['activo'] === 't' || $row['activo'] === '1' || $row['activo'] === 1);
    $esPropio = ((int)$row['id_usuario'] == (int)$tra['id_usuario']);
    $matriz[$c][0]['html'] = ($activo && $esPropio)
        ? '<div style="width:16px;height:16px;"><a href="javascript:cargarSeccion(\'planificacion\',\'avancespoi_ae\',\'idMeta=' . $idMeta . '&idAvanceEdit=' . $id . '\',\'ventana\');" title="Editar"><img src="fuentestra/imagenes/bot/16x16/edit.png" style="width:16px; height:16px;" /></a></div>' : '';
    $matriz[$c][1]['html'] = ($activo && $esPropio)
        ? '<div style="width:16px;height:16px;"><a class="permiso_AvancesAnular" href="javascript:dialogo(\'El avance quedar� anulado y dejar� de incluirse en los c�lculos y reportes. �Desea continuar?\',\'si:anularAvance(' . $id . ');|no\',\'eliminar_campos\');" title="Anular"><img src="fuentestra/imagenes/bot/16x16/delete.png" style="width:16px; height:16px;" /></a></div>' : '';
    $columna = 2;
    foreach (array('anio', 'cantidad', 'hombres', 'mujeres', 'instituciones', 'porcentaje_avance',
        'fecha_avance', 'fecha_registro', 'logros', 'dificultades', 'observaciones') as $clave) {
        $matriz[$c][$columna]['html'] = htmlentities((string)$row[$clave], ENT_QUOTES, 'UTF-8');
        $columna++;
    }
    if ($campoHabilitado['expediente']) {
        $matriz[$c][$columna]['html'] = htmlentities((string)$row['expediente'], ENT_QUOTES, 'UTF-8');
        $columna++;
    }
    if ($campoHabilitado['resolucion']) {
        $matriz[$c][$columna]['html'] = htmlentities((string)$row['resolucion'], ENT_QUOTES, 'UTF-8');
        $columna++;
    }
    foreach (array('excel', 'pdf') as $tipoArchivo) {
        if (!$campoHabilitado[$tipoArchivo]) continue;
        $nombre = htmlentities((string)$row[$tipoArchivo . '_nombre'], ENT_QUOTES, 'UTF-8');
        if ($activo && $nombre != '') {
            $matriz[$c][$columna]['html'] = '<a href="./secciones/planificacion/descargar_archivo_avance.php?idAvance=' . $id . '&tipo=' . $tipoArchivo . '" target="_blank">' . $nombre . '</a>';
        } else {
            $matriz[$c][$columna]['html'] = $nombre;
        }
        $columna++;
    }
    $estilo = $activo ? '' : 'background-color:#eeeeee;color:#777;';
    for ($i = 0; $i < $columna; $i++) {
        $matriz[$c][$i]['style'] = (($i == 0 || $i == 1) ? 'text-align:center;' : 'text-align:left;') . $estilo;
    }
}
$resp['html'] = armarLista($matriz);
?>
