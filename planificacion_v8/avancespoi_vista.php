<?php
$idMeta = (int)recibir_dato('idMeta');
if ($idMeta <= 0) die('Meta invalida');
$sql = "SELECT m.denominacion AS meta, m.tipo_meta, n.codigo AS nivel, o.nombre AS objetivo,
    COALESCE((SELECT string_agg(i.descripcion, ' / ' ORDER BY i.id_indicador)
    FROM planificacion.indicadores i WHERE i.id_meta = m.id_meta), '') AS indicador
    FROM planificacion.metas m
    JOIN planificacion.niveles_estrategicos n ON n.id_nivel = m.id_nivel
    JOIN planificacion.objetivos_estrategicos o ON o.id_oe = n.id_oe
    WHERE m.id_meta = $idMeta AND m.activo IS TRUE";
$db->set_query($sql);
$db->execute_query();
$row = $db->get_array();
if (!$row) die('Meta inexistente o anulada');
$resp['html'] = str_replace('{idMeta}', $idMeta, $resp['html']);
$resp['html'] = str_replace('{nombreMeta}', htmlentities($row['meta'], ENT_QUOTES, 'UTF-8'), $resp['html']);
foreach (array('meta', 'tipo', 'nivel', 'objetivo', 'indicador') as $campo) {
    $clave = $campo == 'tipo' ? 'tipo_meta' : $campo;
    $resp['html'] = str_replace('{' . $campo . '}', htmlentities($row[$clave], ENT_QUOTES, 'UTF-8'), $resp['html']);
}
$sql = "SELECT DISTINCT anio FROM planificacion.avances
    WHERE id_meta = $idMeta AND activo IS TRUE ORDER BY anio";
$db->set_query($sql);
$db->execute_query();
$anios = array(2026 => 2026, 2027 => 2027, 2028 => 2028);
while ($r = $db->get_array()) $anios[(int)$r['anio']] = (int)$r['anio'];
ksort($anios);
$opciones = '<option value="">Todos</option>';
foreach ($anios as $anio) $opciones .= '<option value="' . $anio . '">' . $anio . '</option>';
$resp['html'] = str_replace('{anios}', $opciones, $resp['html']);
?>
