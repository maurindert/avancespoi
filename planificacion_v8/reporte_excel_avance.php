<?php
set_time_limit(0);

// SIRA legacy - Reporte Excel de Avance POI
$idAvance = isset($_GET['idAvance']) ? (int)$_GET['idAvance'] : 0;
$idMeta = isset($_GET['idMeta']) ? (int)$_GET['idMeta'] : 0;

if ($idAvance <= 0 && $idMeta <= 0) {
    die('Identificador invalido para el reporte');
}

// Conexion a base de datos (entorno legacy SIRA / fallback PDO)
$dbCon = null;
if (isset($db) && is_object($db) && method_exists($db, 'set_query')) {
    $modoDb = 'sira';
} else {
    try {
        $dbCon = new PDO(
            'pgsql:host=10.10.53.215;dbname=siradev',
            'indert',
            'Indert*25'
        );
        $dbCon->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $modoDb = 'pdo';
    } catch (PDOException $e) {
        die('Error de conexion con la base de datos: ' . $e->getMessage());
    }
}

// Consulta de avance(s) y meta asociada activa
$condicion = ($idAvance > 0) ? "a.id_avance = $idAvance" : "a.id_meta = $idMeta";

$sql = "
    SELECT
        a.id_avance,
        a.id_meta,
        a.anio,
        a.cantidad,
        a.porcentaje_avance,
        TO_CHAR(a.fecha_avance, 'DD/MM/YYYY') AS fecha_avance,
        TO_CHAR(a.fecha_registro, 'DD/MM/YYYY HH24:MI:SS') AS fecha_registro,
        COALESCE(a.hombres, 0) AS hombres,
        COALESCE(a.mujeres, 0) AS mujeres,
        COALESCE(a.instituciones, 0) AS instituciones,
        COALESCE(TRIM(d.dpto), '') AS departamento,
        COALESCE(TRIM(dis.distrito), '') AS distrito,
        COALESCE(TRIM(col.colonia), '') AS colonia,
        COALESCE(a.logros, '') AS logros,
        COALESCE(a.dificultades, '') AS dificultades,
        COALESCE(a.observaciones, '') AS observaciones,
        COALESCE(a.expediente, '') AS expediente,
        COALESCE(a.resolucion, '') AS resolucion,
        COALESCE(a.excel_nombre, '') AS excel_nombre,
        COALESCE(a.pdf_nombre, '') AS pdf_nombre,
        a.activo,
        m.denominacion AS meta_nombre,
        m.tipo_meta,
        m.responsable,
        m.valor_real_2026,
        m.valor_real_2027,
        m.valor_real_2028,
        m.campo_fecha_avance,
        m.campo_cantidad,
        m.campo_hombres,
        m.campo_mujeres,
        m.campo_instituciones,
        m.campo_dpto_dist_col,
        m.campo_logros,
        m.campo_dificultades,
        m.campo_observaciones,
        m.campo_expediente,
        m.campo_resolucion,
        m.campo_excel,
        m.campo_pdf,
        oe.nombre AS objetivo_estrategico,
        ne.codigo AS nivel_estrategico
    FROM planificacion.avances a
    JOIN planificacion.metas m ON m.id_meta = a.id_meta AND m.activo IS TRUE
    JOIN planificacion.niveles_estrategicos ne ON ne.id_nivel = m.id_nivel
    JOIN planificacion.objetivos_estrategicos oe ON oe.id_oe = ne.id_oe
    LEFT JOIN tierra.dptos d ON d.id_dpto = a.id_dpto
    LEFT JOIN tierra.distritos dis ON dis.id_dpto = a.id_dpto AND dis.id_distrito = a.id_distrito
    LEFT JOIN tierra.colonias col ON col.id_dpto = a.id_dpto AND col.id_distrito = a.id_distrito AND col.id_colonia = a.id_colonia
    WHERE $condicion AND a.activo IS TRUE
    ORDER BY a.anio DESC, a.fecha_avance DESC, a.id_avance DESC
";

$filas = array();
if ($modoDb == 'sira') {
    $db->set_query($sql);
    $db->execute_query();
    while ($r = $db->get_array()) {
        $filas[] = $r;
    }
} else {
    $stmt = $dbCon->prepare($sql);
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (count($filas) == 0) {
    die('Registro no encontrado o perteneciente a un avance/meta anulado');
}

$primeraFila = $filas[0];

// Configuracion de campos dinamicos segun banderas de la meta
$campoHabilitado = array();
foreach (array('fecha_avance', 'cantidad', 'hombres', 'mujeres', 'instituciones',
    'dpto_dist_col', 'logros', 'dificultades', 'observaciones', 'expediente',
    'resolucion', 'excel', 'pdf') as $c) {
    $val = $primeraFila['campo_' . $c];
    $campoHabilitado[$c] = ($val === true || $val === 't' || $val === '1' || $val === 1);
}

// Definicion de columnas de la grilla (alias, formato, alineacion, sumatoria)
$vCampos = array();
$vCampos[] = array('campo' => 'id_avance', 'alias' => 'ID Avance', 'formato' => 'numero', 'alineacion' => 'center', 'sumatoria' => 'f');
$vCampos[] = array('campo' => 'anio', 'alias' => 'Año', 'formato' => 'numero', 'alineacion' => 'center', 'sumatoria' => 'f');

if ($campoHabilitado['fecha_avance']) {
    $vCampos[] = array('campo' => 'fecha_avance', 'alias' => 'Fecha Avance', 'formato' => 'fecha1', 'alineacion' => 'center', 'sumatoria' => 'f');
}
if ($campoHabilitado['cantidad']) {
    $vCampos[] = array('campo' => 'cantidad', 'alias' => 'Cantidad', 'formato' => 'miles', 'alineacion' => 'right', 'sumatoria' => 't');
}
if ($campoHabilitado['hombres']) {
    $vCampos[] = array('campo' => 'hombres', 'alias' => 'Hombres', 'formato' => 'miles', 'alineacion' => 'right', 'sumatoria' => 't');
}
if ($campoHabilitado['mujeres']) {
    $vCampos[] = array('campo' => 'mujeres', 'alias' => 'Mujeres', 'formato' => 'miles', 'alineacion' => 'right', 'sumatoria' => 't');
}
if ($campoHabilitado['instituciones']) {
    $vCampos[] = array('campo' => 'instituciones', 'alias' => 'Instituciones', 'formato' => 'miles', 'alineacion' => 'right', 'sumatoria' => 't');
}
$vCampos[] = array('campo' => 'porcentaje_avance', 'alias' => '% Avance', 'formato' => 'decimales', 'alineacion' => 'right', 'sumatoria' => 'f');

if ($campoHabilitado['dpto_dist_col']) {
    $vCampos[] = array('campo' => 'departamento', 'alias' => 'Departamento', 'formato' => 'texto', 'alineacion' => 'left', 'sumatoria' => 'f');
    $vCampos[] = array('campo' => 'distrito', 'alias' => 'Distrito', 'formato' => 'texto', 'alineacion' => 'left', 'sumatoria' => 'f');
    $vCampos[] = array('campo' => 'colonia', 'alias' => 'Colonia', 'formato' => 'texto', 'alineacion' => 'left', 'sumatoria' => 'f');
}
if ($campoHabilitado['expediente']) {
    $vCampos[] = array('campo' => 'expediente', 'alias' => 'Expediente', 'formato' => 'texto', 'alineacion' => 'left', 'sumatoria' => 'f');
}
if ($campoHabilitado['resolucion']) {
    $vCampos[] = array('campo' => 'resolucion', 'alias' => 'Resolución', 'formato' => 'texto', 'alineacion' => 'left', 'sumatoria' => 'f');
}
if ($campoHabilitado['logros']) {
    $vCampos[] = array('campo' => 'logros', 'alias' => 'Logros', 'formato' => 'texto', 'alineacion' => 'left', 'sumatoria' => 'f');
}
if ($campoHabilitado['dificultades']) {
    $vCampos[] = array('campo' => 'dificultades', 'alias' => 'Dificultades', 'formato' => 'texto', 'alineacion' => 'left', 'sumatoria' => 'f');
}
if ($campoHabilitado['observaciones']) {
    $vCampos[] = array('campo' => 'observaciones', 'alias' => 'Observaciones', 'formato' => 'texto', 'alineacion' => 'left', 'sumatoria' => 'f');
}
if ($campoHabilitado['excel']) {
    $vCampos[] = array('campo' => 'excel_nombre', 'alias' => 'Archivo Excel', 'formato' => 'texto', 'alineacion' => 'left', 'sumatoria' => 'f');
}
if ($campoHabilitado['pdf']) {
    $vCampos[] = array('campo' => 'pdf_nombre', 'alias' => 'Archivo PDF', 'formato' => 'texto', 'alineacion' => 'left', 'sumatoria' => 'f');
}
$vCampos[] = array('campo' => 'fecha_registro', 'alias' => 'Fecha Registro', 'formato' => 'fecha2', 'alineacion' => 'center', 'sumatoria' => 'f');

// Construccion de la grilla de reporte (patron institucional SIRA)
$cantCol = count($vCampos);
$grilla = array();

// Fila 0: Encabezados
for ($i = 0; $i < $cantCol; $i++) {
    $grilla[0][$i]['info'] = $vCampos[$i]['alias'];
    $grilla[0][$i]['alineacion'] = $vCampos[$i]['alineacion'];
}

// Filas de datos
$cFila = 0;
$gAc = array();
$bExisteSumatoria = 0;

foreach ($filas as $row) {
    $cFila++;
    for ($i = 0; $i < $cantCol; $i++) {
        $campoNombre = $vCampos[$i]['campo'];
        $infoCelda = isset($row[$campoNombre]) ? $row[$campoNombre] : '';

        if ($vCampos[$i]['formato'] == 'texto') {
            // texto plano
        } else if ($vCampos[$i]['formato'] == 'numero') {
            if ($infoCelda == '') { $infoCelda = 0; }
        } else if ($vCampos[$i]['formato'] == 'fecha1') {
            $infoCelda = substr($infoCelda, 0, 10);
        } else if ($vCampos[$i]['formato'] == 'fecha2') {
            $infoCelda = substr($infoCelda, 0, 19);
        } else if ($vCampos[$i]['formato'] == 'miles') {
            if ($infoCelda == '') { $infoCelda = 0; }
            $infoCelda = number_format(doubleval($infoCelda), 0, ',', '.');
        } else if ($vCampos[$i]['formato'] == 'decimales') {
            if ($infoCelda == '') { $infoCelda = 0; }
            $infoCelda = number_format(doubleval($infoCelda), 2, ',', '.');
        }

        if ($vCampos[$i]['sumatoria'] == 't') {
            $valorNum = isset($row[$campoNombre]) ? (float)$row[$campoNombre] : 0;
            if (!isset($gAc[$i])) { $gAc[$i] = 0; }
            $gAc[$i] += $valorNum;
            $bExisteSumatoria = 1;
        }

        $grilla[$cFila][$i]['info'] = $infoCelda;
        $grilla[$cFila][$i]['alineacion'] = $vCampos[$i]['alineacion'];
    }
}

// Fila de sumatoria si hay mas de un registro
if ($bExisteSumatoria == 1 && count($filas) > 1) {
    $cFila++;
    $bColspanTotalGral = 0;
    $cColspanTotalGral = 0;
    for ($i = 0; $i < $cantCol; $i++) {
        if ($vCampos[$i]['sumatoria'] == 't') {
            $infoCelda = isset($gAc[$i]) ? $gAc[$i] : 0;
            if ($vCampos[$i]['formato'] == 'miles') {
                $infoCelda = number_format($infoCelda, 0, ',', '.');
            } else if ($vCampos[$i]['formato'] == 'decimales') {
                $infoCelda = number_format($infoCelda, 2, ',', '.');
            }
            $bColspanTotalGral = 1;
        } else {
            if ($bColspanTotalGral == 0) {
                $cColspanTotalGral++;
            }
            $infoCelda = ' ';
        }
        $grilla[$cFila][$i]['info'] = $infoCelda;
        $grilla[$cFila][$i]['alineacion'] = $vCampos[$i]['alineacion'];
    }
    $grilla[$cFila][0]['info'] = 'Total general';
    $grilla[$cFila][0]['colspan'] = $cColspanTotalGral;
}

// Generacion de salida HTML para Excel
$colspanCabecera = $cantCol;

$tbHtml = '<table border="1" cellpadding="4" cellspacing="0">';
$tbHtml .= '<tr><th colspan="' . $colspanCabecera . '" style="background-color:#0284c7; color:#ffffff; font-size:14pt; text-align:center;">INSTITUTO NACIONAL DE DESARROLLO RURAL Y DE LA TIERRA - INDERT</th></tr>';
$tbHtml .= '<tr><th colspan="' . $colspanCabecera . '" style="background-color:#38bdf8; color:#0f172a; font-size:12pt; text-align:center;">SISTEMA INSTITUCIONAL SIRA - REPORTE DE AVANCES POI</th></tr>';
$tbHtml .= '<tr><td colspan="' . $colspanCabecera . '" style="background-color:#f8fafc; font-size:10pt;"><b>Objetivo Estratégico:</b> ' . htmlspecialchars($primeraFila['objetivo_estrategico'], ENT_QUOTES, 'UTF-8') . '<br /><b>Nivel Estratégico:</b> ' . htmlspecialchars($primeraFila['nivel_estrategico'], ENT_QUOTES, 'UTF-8') . '<br /><b>Meta:</b> ' . htmlspecialchars($primeraFila['meta_nombre'], ENT_QUOTES, 'UTF-8') . '<br /><b>Responsable:</b> ' . htmlspecialchars($primeraFila['responsable'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
$tbHtml .= '<tr><td colspan="' . $colspanCabecera . '" style="background-color:#ffffff; height:10px;"></td></tr>';

// Cabecera de la tabla
$tbHtml .= '<tr>';
for ($c = 0; $c < $cantCol; $c++) {
    $tbHtml .= '<th style="background-color:#dedede; font-weight:bold; text-align:' . $grilla[0][$c]['alineacion'] . '; border:1px solid #999;">' . htmlspecialchars($grilla[0][$c]['info'], ENT_QUOTES, 'UTF-8') . '</th>';
}
$tbHtml .= '</tr>';

// Filas de datos
for ($f = 1; $f <= $cFila; $f++) {
    $esUltimaSumatoria = ($bExisteSumatoria == 1 && count($filas) > 1 && $f == $cFila);
    $bgFila = $esUltimaSumatoria ? 'background-color:#dededf; font-weight:bold;' : ($f % 2 == 0 ? 'background-color:#f9f9f9;' : 'background-color:#ffffff;');
    $tbHtml .= '<tr style="' . $bgFila . '">';

    $skipCols = 0;
    for ($c = 0; $c < $cantCol; $c++) {
        if ($skipCols > 0) {
            $skipCols--;
            continue;
        }

        $colspanAttr = '';
        if (isset($grilla[$f][$c]['colspan']) && $grilla[$f][$c]['colspan'] > 1) {
            $colspanAttr = ' colspan="' . $grilla[$f][$c]['colspan'] . '"';
            $skipCols = $grilla[$f][$c]['colspan'] - 1;
        }

        $alin = isset($grilla[$f][$c]['alineacion']) ? $grilla[$f][$c]['alineacion'] : 'left';
        $infoVal = isset($grilla[$f][$c]['info']) ? $grilla[$f][$c]['info'] : '';

        $tbHtml .= '<td' . $colspanAttr . ' style="text-align:' . $alin . '; border:1px solid #ccc;">' . htmlspecialchars((string)$infoVal, ENT_QUOTES, 'UTF-8') . '</td>';
    }
    $tbHtml .= '</tr>';
}

$tbHtml .= '</table>';

// Descarga en formato Excel (.xls)
$nombreDescarga = ($idAvance > 0) ? 'reporte_avance_' . $idAvance . '.xls' : 'reporte_avances_meta_' . $idMeta . '.xls';

header("Content-type: application/octet-stream");
header("Content-Disposition: attachment; filename=" . $nombreDescarga);
header("Pragma: no-cache");
header("Expires: 0");

echo $tbHtml;
exit;
?>
