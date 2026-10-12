<?php
$idMeta = (int)recibir_dato('idMeta');
$idAvance = (int)recibir_dato('idAvanceEdit');
$anio = (int)recibir_dato('periodo');
$usuario = (int)$tra['id_usuario'];
$transaccion = false;
try {
if ($idMeta <= 0 || $usuario <= 0 || !in_array($anio, array(2026, 2027, 2028))) throw new Exception('Datos invalidos');

$db->set_query('BEGIN');
$db->execute_query();
$transaccion = true;

$sql = "SELECT valor_real_$anio AS valor_meta,
    campo_fecha_avance, campo_cantidad, campo_hombres, campo_mujeres,
    campo_instituciones, campo_dpto_dist_col, campo_logros, campo_dificultades, campo_observaciones,
    campo_expediente, campo_resolucion, campo_excel, campo_pdf
    FROM planificacion.metas WHERE id_meta = $idMeta AND activo IS TRUE FOR UPDATE";
$db->set_query($sql);
$db->execute_query();
$meta = $db->get_array();
if (!$meta) throw new Exception('Meta inexistente');

$campos = array();
$hayCampo = false;
foreach (array('fecha_avance', 'cantidad', 'hombres', 'mujeres', 'instituciones', 'dpto_dist_col',
    'logros', 'dificultades', 'observaciones', 'expediente', 'resolucion', 'excel', 'pdf') as $campo) {
    $campos[$campo] = ($meta['campo_' . $campo] === true ||
        $meta['campo_' . $campo] === 't' || $meta['campo_' . $campo] === '1');
    if ($campos[$campo]) $hayCampo = true;
}
if (!$hayCampo) throw new Exception('La meta no tiene campos de carga habilitados');
if ($campos['cantidad'] && (!is_numeric($meta['valor_meta']) || (int)$meta['valor_meta'] <= 0)) {
    throw new Exception('La meta no tiene un valor proyectado para el año seleccionado');
}

$anterior = false;
if ($idAvance > 0) {
    $sql = "SELECT id_avance, cantidad, expediente, resolucion, excel_nombre, pdf_nombre
        FROM planificacion.avances
        WHERE id_avance = $idAvance AND id_meta = $idMeta AND id_usuario = $usuario
        AND activo IS TRUE";
    $db->set_query($sql);
    $db->execute_query();
    $anterior = $db->get_array();
    if (!$anterior) throw new Exception('Avance inexistente o sin permiso de edicion');
}

$set = array('anio = ' . $anio, 'fecha_registro = now()', 'id_usuario = ' . $usuario);
$fechaSQL = 'NULL';
if ($campos['fecha_avance']) {
    $fecha = recibir_dato('fechaAvance');
    $partes = explode('-', $fecha);
    if (count($partes) != 3 || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $fecha) ||
        !checkdate((int)$partes[1], (int)$partes[2], (int)$partes[0]) ||
        (int)$partes[0] != $anio) throw new Exception('Fecha del avance invalida para el periodo');
    $fechaSQL = "'$fecha'";
    $set[] = 'fecha_avance = ' . $fechaSQL;
}

$cantidadSQL = 'NULL';
$cantidad = null;
if ($campos['cantidad']) {
    $raw = recibir_dato('cantidad');
    if (!ctype_digit((string)$raw) || (float)$raw <= 0 || (float)$raw > 2147483647) throw new Exception('Cantidad invalida');
    $cantidad = (int)$raw;
    $cantidadSQL = (string)$cantidad;
    $set[] = 'cantidad = ' . $cantidadSQL;
} elseif ($anterior && $anterior['cantidad'] !== null) {
    $cantidad = (int)$anterior['cantidad'];
}

$valores = array();
foreach (array('hombres', 'mujeres', 'instituciones') as $campo) {
    $valores[$campo] = '0';
    if ($campos[$campo]) {
        $raw = recibir_dato($campo);
        if ($raw === null || $raw === '') $raw = 0;
        if (!ctype_digit((string)$raw) || (float)$raw > 2147483647) throw new Exception('Valor invalido en ' . $campo);
        $valores[$campo] = (string)(int)$raw;
        $set[] = $campo . ' = ' . $valores[$campo];
    }
}
$hayDesglose = $campos['hombres'] || $campos['mujeres'] || $campos['instituciones'];
$totalDesglose = ($campos['hombres'] ? (int)$valores['hombres'] : 0) +
    ($campos['mujeres'] ? (int)$valores['mujeres'] : 0) +
    ($campos['instituciones'] ? (int)$valores['instituciones'] : 0);
if ($campos['cantidad'] && $hayDesglose && $cantidad != $totalDesglose) {
    throw new Exception('La cantidad debe coincidir con la suma de hombres, mujeres e instituciones');
}

$idDptoSQL = 'NULL';
$idDistritoSQL = 'NULL';
$idColoniaSQL = 'NULL';
if ($campos['dpto_dist_col']) {
    $idDpto = recibir_dato('idDpto');
    $idDistrito = recibir_dato('idDistrito');
    $idColonia = recibir_dato('idColonia');
    if (!ctype_digit((string)$idDpto) || !ctype_digit((string)$idDistrito) ||
        !ctype_digit((string)$idColonia)) throw new Exception('Departamento, distrito o colonia invalido');
    $idDptoSQL = (string)(int)$idDpto;
    $idDistritoSQL = (string)(int)$idDistrito;
    $idColoniaSQL = (string)(int)$idColonia;
    $sql = "SELECT c.id_colonia FROM tierra.colonias c
        WHERE c.id_dpto = $idDptoSQL AND c.id_distrito = $idDistritoSQL
        AND c.id_colonia = $idColoniaSQL";
    $db->set_query($sql);
    $db->execute_query();
    if (!$db->get_array()) throw new Exception('La colonia no pertenece al departamento y distrito seleccionados');
    $set[] = 'id_dpto = ' . $idDptoSQL;
    $set[] = 'id_distrito = ' . $idDistritoSQL;
    $set[] = 'id_colonia = ' . $idColoniaSQL;
}

$texto = array();
foreach (array('logros' => 'logros', 'dificultades' => 'dificultades',
    'observaciones' => 'observacion', 'expediente' => 'expediente',
    'resolucion' => 'resolucion') as $campo => $dato) {
    $texto[$campo] = 'NULL';
    if ($campos[$campo]) {
        $raw = recibir_dato($dato);
        if ($raw !== null && trim($raw) !== '') {
            $texto[$campo] = "'" . pg_escape_string($raw) . "'";
        }
        $set[] = $campo . ' = ' . $texto[$campo];
    }
}

$archivoValor = array('excel' => 'NULL', 'pdf' => 'NULL');
$archivoNombre = array('excel' => 'NULL', 'pdf' => 'NULL');
$archivoTipo = array('excel' => 'NULL', 'pdf' => 'NULL');
foreach (array('excel', 'pdf') as $tipoArchivo) {
    if (!$campos[$tipoArchivo]) continue;
    $contenidoBase64 = recibir_dato($tipoArchivo . 'Archivo');
    if ($contenidoBase64 === null || $contenidoBase64 === '') continue;
    if (strlen($contenidoBase64) > 13982000) throw new Exception('El archivo supera el limite de 10 MB');
    $contenidoBase64 = str_replace(' ', '+', $contenidoBase64);
    $contenidoBinario = base64_decode($contenidoBase64, true);
    if ($contenidoBinario === false || strlen($contenidoBinario) == 0 || strlen($contenidoBinario) > 10485760) {
        throw new Exception('El archivo ' . strtoupper($tipoArchivo) . ' es invalido o supera el limite de 10 MB');
    }
    $nombreOriginal = recibir_dato($tipoArchivo . 'Nombre');
    $nombreOriginal = basename(str_replace('\\', '/', (string)$nombreOriginal));
    if ($nombreOriginal == '' || strlen($nombreOriginal) > 255) throw new Exception('Nombre de archivo invalido');
    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    if ($tipoArchivo == 'pdf') {
        if ($extension != 'pdf' || substr($contenidoBinario, 0, 5) !== '%PDF-') {
            throw new Exception('El archivo PDF seleccionado no es valido');
        }
        $mimeSeguro = 'application/pdf';
    } else {
        $firmaXls = substr($contenidoBinario, 0, 8) === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
        $firmaXlsx = substr($contenidoBinario, 0, 2) === 'PK';
        if (($extension != 'xls' && $extension != 'xlsx') ||
            ($extension == 'xls' && !$firmaXls) || ($extension == 'xlsx' && !$firmaXlsx)) {
            throw new Exception('El archivo Excel seleccionado no es valido');
        }
        $mimeSeguro = ($extension == 'xls') ? 'application/vnd.ms-excel'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
    $base64Seguro = pg_escape_string(base64_encode($contenidoBinario));
    $archivoValor[$tipoArchivo] = "decode('$base64Seguro', 'base64')";
    $archivoNombre[$tipoArchivo] = "'" . pg_escape_string($nombreOriginal) . "'";
    $archivoTipo[$tipoArchivo] = "'" . pg_escape_string($mimeSeguro) . "'";
    $set[] = $tipoArchivo . '_archivo = ' . $archivoValor[$tipoArchivo];
    $set[] = $tipoArchivo . '_nombre = ' . $archivoNombre[$tipoArchivo];
    $set[] = $tipoArchivo . '_tipo = ' . $archivoTipo[$tipoArchivo];
}

$hayDato = ($campos['cantidad'] && $cantidad !== null);
foreach (array('hombres', 'mujeres', 'instituciones') as $campo) {
    if ($campos[$campo] && (int)$valores[$campo] > 0) $hayDato = true;
}
$hayDato = $hayDato || $campos['dpto_dist_col'];
foreach (array('logros', 'dificultades', 'observaciones', 'expediente', 'resolucion') as $campo) {
    if ($campos[$campo] && $texto[$campo] != 'NULL') $hayDato = true;
}
foreach (array('excel', 'pdf') as $tipoArchivo) {
    if ($campos[$tipoArchivo] && ($archivoValor[$tipoArchivo] != 'NULL' ||
        ($anterior && !empty($anterior[$tipoArchivo . '_nombre'])))) $hayDato = true;
}
if (!$hayDato && !$anterior) throw new Exception('Debe completar al menos un dato del avance');

if ($cantidad !== null && is_numeric($meta['valor_meta']) && (int)$meta['valor_meta'] > 0) {
    $sql = "SELECT COALESCE(SUM(cantidad), 0) AS total FROM planificacion.avances
        WHERE id_meta = $idMeta AND anio = $anio AND activo IS TRUE";
    if ($idAvance > 0) $sql .= " AND id_avance <> $idAvance";
    $db->set_query($sql);
    $db->execute_query();
    $total = $db->get_array();
    if ((float)$total['total'] + $cantidad > (int)$meta['valor_meta']) {
        $disponible = max((int)$meta['valor_meta'] - (int)$total['total'], 0);
        throw new Exception('La cantidad supera el maximo anual. Puede cargar como maximo ' . $disponible . '.');
    }
}

if ($idAvance > 0) {
    $sql = "UPDATE planificacion.avances SET " . implode(', ', $set) . "
        WHERE id_avance = $idAvance AND id_meta = $idMeta AND id_usuario = $usuario
        AND activo IS TRUE";
} else {
    $sql = "INSERT INTO planificacion.avances
        (id_meta, anio, fecha_avance, cantidad, hombres, mujeres, instituciones,
         id_dpto, id_distrito, id_colonia, logros, dificultades, fecha_registro, id_usuario, observaciones,
         expediente, resolucion, excel_archivo, excel_nombre, excel_tipo,
         pdf_archivo, pdf_nombre, pdf_tipo, activo)
        VALUES ($idMeta, $anio, $fechaSQL, $cantidadSQL,
         " . $valores['hombres'] . ", " . $valores['mujeres'] . ", " . $valores['instituciones'] . ",
         $idDptoSQL, $idDistritoSQL, $idColoniaSQL,
         " . $texto['logros'] . ", " . $texto['dificultades'] . ", now(), $usuario,
         " . $texto['observaciones'] . ", " . $texto['expediente'] . ", " . $texto['resolucion'] . ",
         " . $archivoValor['excel'] . ", " . $archivoNombre['excel'] . ", " . $archivoTipo['excel'] . ",
         " . $archivoValor['pdf'] . ", " . $archivoNombre['pdf'] . ", " . $archivoTipo['pdf'] . ", TRUE)";
}
$db->set_query($sql);
$db->execute_query();
$db->set_query('COMMIT');
$db->execute_query();
$transaccion = false;
$resp['row'] = array('ok' => 1, 'mensaje' => 'Avance guardado correctamente');
$row = $resp['row'];
} catch (Exception $e) {
    if ($transaccion) {
        $db->set_query('ROLLBACK');
        $db->execute_query();
    }
    $resp['row'] = array('ok' => 0, 'mensaje' => $e->getMessage());
    $row = $resp['row'];
}
?>
