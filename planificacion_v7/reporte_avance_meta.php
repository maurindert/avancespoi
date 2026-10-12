<?php
header('Content-Type: text/html; charset=Windows-1252');
$idMeta = isset($_GET['idMeta']) ? (int)$_GET['idMeta'] : 0;
if ($idMeta <= 0) die('Meta invalida');

try {
    $db = new PDO(
        'pgsql:host=10.10.53.215;dbname=siradev',
        'indert',
        'Indert*25'
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Error de conexion: ' . $e->getMessage());
}

$sql = "SELECT m.id_meta, m.denominacion, m.tipo_meta, m.responsable,
        m.meta_2026, m.meta_2027, m.meta_2028,
        m.valor_real_2026, m.valor_real_2027, m.valor_real_2028,
        m.campo_fecha_avance, m.campo_cantidad, m.campo_hombres, m.campo_mujeres,
        m.campo_instituciones, m.campo_dpto_dist_col, m.campo_logros,
        m.campo_dificultades, m.campo_observaciones, m.campo_expediente,
        m.campo_resolucion, m.campo_excel, m.campo_pdf,
        n.codigo AS nivel, o.nombre AS objetivo,
        COALESCE((SELECT string_agg(i.descripcion, ' / ' ORDER BY i.id_indicador)
            FROM planificacion.indicadores i WHERE i.id_meta = m.id_meta), '') AS indicador
    FROM planificacion.metas m
    JOIN planificacion.niveles_estrategicos n ON n.id_nivel = m.id_nivel
    JOIN planificacion.objetivos_estrategicos o ON o.id_oe = n.id_oe
    WHERE m.id_meta = :id_meta AND m.activo IS TRUE";
$stmt = $db->prepare($sql);
$stmt->execute(array(':id_meta' => $idMeta));
$meta = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$meta) die('Meta inexistente o anulada');

$campos = array();
foreach (array('fecha_avance', 'cantidad', 'hombres', 'mujeres', 'instituciones',
    'dpto_dist_col', 'logros', 'dificultades', 'observaciones', 'expediente',
    'resolucion', 'excel', 'pdf') as $campo) {
    $valor = $meta['campo_' . $campo];
    $campos[$campo] = ($valor === true || $valor === 't' || $valor === '1' || $valor === 1);
}

$resumen = array();
foreach (array(2026, 2027, 2028) as $anio) {
    $resumen[$anio] = array(
        'anio' => $anio,
        'planificado' => (int)$meta['valor_real_' . $anio],
        'cantidad' => 0,
        'hombres' => 0,
        'mujeres' => 0,
        'instituciones' => 0,
        'registros' => 0
    );
}

$sql = "SELECT a.anio, COUNT(*) AS registros,
        COALESCE(SUM(a.cantidad), 0) AS cantidad,
        COALESCE(SUM(a.hombres), 0) AS hombres,
        COALESCE(SUM(a.mujeres), 0) AS mujeres,
        COALESCE(SUM(a.instituciones), 0) AS instituciones
    FROM planificacion.avances a
    WHERE a.id_meta = :id_meta AND a.activo IS TRUE
    GROUP BY a.anio
    ORDER BY a.anio";
$stmt = $db->prepare($sql);
$stmt->execute(array(':id_meta' => $idMeta));
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $anio = (int)$row['anio'];
    if (!isset($resumen[$anio])) {
        $resumen[$anio] = array('anio' => $anio, 'planificado' => 0, 'cantidad' => 0,
            'hombres' => 0, 'mujeres' => 0, 'instituciones' => 0, 'registros' => 0);
    }
    $resumen[$anio]['cantidad'] = (int)$row['cantidad'];
    $resumen[$anio]['hombres'] = (int)$row['hombres'];
    $resumen[$anio]['mujeres'] = (int)$row['mujeres'];
    $resumen[$anio]['instituciones'] = (int)$row['instituciones'];
    $resumen[$anio]['registros'] = (int)$row['registros'];
}
ksort($resumen);

$anios = array();
$planificado = array();
$ejecutado = array();
$hombres = array();
$mujeres = array();
$instituciones = array();
$registros = array();
$totalPlanificado = 0;
$totalEjecutado = 0;
$totalRegistros = 0;
foreach ($resumen as $row) {
    $anios[] = (string)$row['anio'];
    $planificado[] = (int)$row['planificado'];
    $ejecutado[] = (int)$row['cantidad'];
    $hombres[] = (int)$row['hombres'];
    $mujeres[] = (int)$row['mujeres'];
    $instituciones[] = (int)$row['instituciones'];
    $registros[] = (int)$row['registros'];
    $totalPlanificado += (int)$row['planificado'];
    $totalEjecutado += (int)$row['cantidad'];
    $totalRegistros += (int)$row['registros'];
}
$porcentaje = $totalPlanificado > 0 ? round(($totalEjecutado * 100) / $totalPlanificado, 2) : 0;

$ubicaciones = array();
$totalesUbicacion = array();
if ($campos['dpto_dist_col']) {
    $sql = "SELECT TRIM(d.dpto) || ' / ' || TRIM(dis.distrito) || ' / ' || TRIM(col.colonia) AS ubicacion,
            COUNT(*) AS registros, COALESCE(SUM(a.cantidad), 0) AS cantidad
        FROM planificacion.avances a
        JOIN tierra.dptos d ON d.id_dpto = a.id_dpto
        JOIN tierra.distritos dis ON dis.id_dpto = a.id_dpto AND dis.id_distrito = a.id_distrito
        JOIN tierra.colonias col ON col.id_dpto = a.id_dpto AND col.id_distrito = a.id_distrito
            AND col.id_colonia = a.id_colonia
        WHERE a.id_meta = :id_meta AND a.activo IS TRUE
        GROUP BY d.dpto, dis.distrito, col.colonia
        ORDER BY d.dpto, dis.distrito, col.colonia";
    $stmt = $db->prepare($sql);
    $stmt->execute(array(':id_meta' => $idMeta));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ubicaciones[] = $row['ubicacion'];
        $totalesUbicacion[] = $campos['cantidad'] ? (int)$row['cantidad'] : (int)$row['registros'];
    }
}

$sql = "SELECT a.id_avance, a.anio, TO_CHAR(a.fecha_avance, 'DD/MM/YYYY') AS fecha_avance,
        COALESCE(a.cantidad, 0) AS cantidad, COALESCE(a.hombres, 0) AS hombres,
        COALESCE(a.mujeres, 0) AS mujeres, COALESCE(a.instituciones, 0) AS instituciones,
        COALESCE(TRIM(d.dpto), '') AS departamento,
        COALESCE(TRIM(dis.distrito), '') AS distrito,
        COALESCE(TRIM(col.colonia), '') AS colonia,
        COALESCE(a.logros, '') AS logros, COALESCE(a.dificultades, '') AS dificultades,
        COALESCE(a.observaciones, '') AS observaciones,
        COALESCE(a.expediente, '') AS expediente, COALESCE(a.resolucion, '') AS resolucion,
        COALESCE(a.excel_nombre, '') AS excel_nombre, COALESCE(a.pdf_nombre, '') AS pdf_nombre
    FROM planificacion.avances a
    LEFT JOIN tierra.dptos d ON d.id_dpto = a.id_dpto
    LEFT JOIN tierra.distritos dis ON dis.id_dpto = a.id_dpto AND dis.id_distrito = a.id_distrito
    LEFT JOIN tierra.colonias col ON col.id_dpto = a.id_dpto AND col.id_distrito = a.id_distrito
        AND col.id_colonia = a.id_colonia
    WHERE a.id_meta = :id_meta AND a.activo IS TRUE
    ORDER BY a.anio DESC, a.fecha_avance DESC, a.id_avance DESC";
$stmt = $db->prepare($sql);
$stmt->execute(array(':id_meta' => $idMeta));
$detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="Windows-1252" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reporte Dinamico - <?php echo htmlentities($meta['denominacion'], ENT_QUOTES, 'UTF-8'); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --azul:#3b82f6; --verde:#10b981; --naranja:#f59e0b; --borde:#dbe4ee; --texto:#0f172a; }
        * { box-sizing:border-box; }
        body { margin:0; padding:28px; font-family:Arial,sans-serif; color:var(--texto); background:#99dfff; }
        .container { max-width:1200px; margin:0 auto; }
        .barra { display:flex; justify-content:flex-end; margin-bottom:14px; }
        .boton { border:0; border-radius:6px; padding:11px 16px; background:#64748b; color:#fff; font-weight:bold; cursor:pointer; }
        .cabecera,.tarjeta { background:#fff; border:1px solid var(--borde); border-radius:12px; padding:20px; margin-bottom:18px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
        h1 { margin:0 0 8px; font-size:25px; }
        h2 { margin:0 0 16px; font-size:18px; }
        .subtitulo { color:#64748b; line-height:1.5; }
        .kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:18px; }
        .kpi { background:#fff; border:1px solid var(--borde); border-top:4px solid var(--azul); border-radius:10px; padding:16px; }
        .kpi.valor-verde { border-top-color:var(--verde); }
        .kpi.valor-naranja { border-top-color:var(--naranja); }
        .kpi strong { display:block; font-size:25px; }
        .kpi span { color:#64748b; font-size:13px; }
        .graficos { display:grid; grid-template-columns:repeat(auto-fit,minmax(420px,1fr)); gap:18px; }
        .grafico { min-height:350px; }
        .grafico canvas { max-height:300px; }
        .tabla { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th { padding:10px; text-align:left; background:#f1f5f9; white-space:nowrap; }
        td { padding:10px; border-bottom:1px solid var(--borde); vertical-align:top; }
        .vacio { color:#64748b; text-align:center; padding:25px; }
        @media (max-width:700px) { body { padding:12px; } .graficos { grid-template-columns:1fr; } }
        @media print { body { padding:0; background:#fff; font-size:10px; } .no-print { display:none!important; } .cabecera,.tarjeta,.kpi { box-shadow:none; break-inside:avoid; } .graficos { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="container">
    <div class="barra no-print"><button class="boton" onclick="window.print();">Imprimir</button></div>
    <div class="cabecera">
        <h1>Reporte Dinamico - Avances "<?php echo htmlentities($meta['denominacion'], ENT_QUOTES, 'UTF-8'); ?>"</h1>
        <div class="subtitulo">
            <b>Objetivo:</b> <?php echo htmlentities($meta['objetivo'], ENT_QUOTES, 'UTF-8'); ?><br />
            <b>Nivel:</b> <?php echo htmlentities($meta['nivel'], ENT_QUOTES, 'UTF-8'); ?> |
            <b>Indicador:</b> <?php echo htmlentities($meta['indicador'], ENT_QUOTES, 'UTF-8'); ?> |
            <b>Tipo:</b> <?php echo htmlentities($meta['tipo_meta'], ENT_QUOTES, 'UTF-8'); ?><br />
            <b>Responsable:</b> <?php echo htmlentities($meta['responsable'], ENT_QUOTES, 'UTF-8'); ?> |
            <b>Fecha del reporte:</b> <?php echo date('d/m/Y'); ?>
        </div>
    </div>

    <div class="kpis">
        <div class="kpi"><strong><?php echo $totalRegistros; ?></strong><span>Avances registrados</span></div>
        <div class="kpi valor-verde" style="display:<?php echo $campos['cantidad'] ? 'block' : 'none'; ?>;"><strong><?php echo number_format($totalEjecutado,0,',','.'); ?></strong><span>Total ejecutado</span></div>
        <div class="kpi valor-naranja" style="display:<?php echo $campos['cantidad'] ? 'block' : 'none'; ?>;"><strong><?php echo $porcentaje; ?>%</strong><span>Avance total</span></div>
    </div>

    <div class="graficos">
        <div class="tarjeta grafico" style="display:<?php echo $campos['cantidad'] ? 'block' : 'none'; ?>;"><h2>Planificado y ejecutado por a�o</h2><canvas id="graficoCantidad"></canvas></div>
        <div class="tarjeta grafico" style="display:<?php echo $campos['fecha_avance'] ? 'block' : 'none'; ?>;"><h2>Registros de avance por a�o</h2><canvas id="graficoFechas"></canvas></div>
        <div class="tarjeta grafico" style="display:<?php echo ($campos['hombres'] || $campos['mujeres'] || $campos['instituciones']) ? 'block' : 'none'; ?>;"><h2>Desglose acumulado por a�o</h2><canvas id="graficoDesglose"></canvas></div>
        <div class="tarjeta grafico" style="display:<?php echo $campos['dpto_dist_col'] ? 'block' : 'none'; ?>;">
            <h2><?php echo $campos['cantidad'] ? 'Cantidad por ubicacion' : 'Registros por ubicacion'; ?></h2>
            <?php if (count($ubicaciones) > 0): ?><canvas id="graficoUbicacion"></canvas><?php else: ?><div class="vacio">No existen ubicaciones cargadas para esta meta.</div><?php endif; ?>
        </div>
    </div>

    <div class="tarjeta">
        <h2>Detalle de avances</h2>
        <div class="tabla"><table>
            <thead><tr>
                <th>A�o</th>
                <th style="display:<?php echo $campos['fecha_avance'] ? 'table-cell' : 'none'; ?>;">Fecha</th>
                <th style="display:<?php echo $campos['cantidad'] ? 'table-cell' : 'none'; ?>;">Cantidad</th>
                <th style="display:<?php echo $campos['hombres'] ? 'table-cell' : 'none'; ?>;">Hombres</th>
                <th style="display:<?php echo $campos['mujeres'] ? 'table-cell' : 'none'; ?>;">Mujeres</th>
                <th style="display:<?php echo $campos['instituciones'] ? 'table-cell' : 'none'; ?>;">Instituciones</th>
                <th style="display:<?php echo $campos['dpto_dist_col'] ? 'table-cell' : 'none'; ?>;">Departamento / Distrito / Colonia</th>
                <th style="display:<?php echo $campos['logros'] ? 'table-cell' : 'none'; ?>;">Logros</th>
                <th style="display:<?php echo $campos['dificultades'] ? 'table-cell' : 'none'; ?>;">Dificultades</th>
                <th style="display:<?php echo $campos['observaciones'] ? 'table-cell' : 'none'; ?>;">Observaciones</th>
                <th style="display:<?php echo $campos['expediente'] ? 'table-cell' : 'none'; ?>;">Expediente</th>
                <th style="display:<?php echo $campos['resolucion'] ? 'table-cell' : 'none'; ?>;">Resoluci�n</th>
                <th style="display:<?php echo $campos['excel'] ? 'table-cell' : 'none'; ?>;">Excel</th>
                <th style="display:<?php echo $campos['pdf'] ? 'table-cell' : 'none'; ?>;">PDF</th>
            </tr></thead>
            <tbody>
            <?php if (count($detalle) == 0): ?><tr><td colspan="14" class="vacio">No existen avances registrados para esta meta.</td></tr><?php endif; ?>
            <?php foreach ($detalle as $row): ?>
                <tr>
                    <td><?php echo (int)$row['anio']; ?></td>
                    <td style="display:<?php echo $campos['fecha_avance'] ? 'table-cell' : 'none'; ?>;"><?php echo htmlentities($row['fecha_avance'],ENT_QUOTES,'UTF-8'); ?></td>
                    <td style="display:<?php echo $campos['cantidad'] ? 'table-cell' : 'none'; ?>;"><?php echo number_format((int)$row['cantidad'],0,',','.'); ?></td>
                    <td style="display:<?php echo $campos['hombres'] ? 'table-cell' : 'none'; ?>;"><?php echo (int)$row['hombres']; ?></td>
                    <td style="display:<?php echo $campos['mujeres'] ? 'table-cell' : 'none'; ?>;"><?php echo (int)$row['mujeres']; ?></td>
                    <td style="display:<?php echo $campos['instituciones'] ? 'table-cell' : 'none'; ?>;"><?php echo (int)$row['instituciones']; ?></td>
                    <td style="display:<?php echo $campos['dpto_dist_col'] ? 'table-cell' : 'none'; ?>;"><?php echo htmlentities(trim($row['departamento'].' / '.$row['distrito'].' / '.$row['colonia'],' /'),ENT_QUOTES,'UTF-8'); ?></td>
                    <td style="display:<?php echo $campos['logros'] ? 'table-cell' : 'none'; ?>;"><?php echo nl2br(htmlentities($row['logros'],ENT_QUOTES,'UTF-8')); ?></td>
                    <td style="display:<?php echo $campos['dificultades'] ? 'table-cell' : 'none'; ?>;"><?php echo nl2br(htmlentities($row['dificultades'],ENT_QUOTES,'UTF-8')); ?></td>
                    <td style="display:<?php echo $campos['observaciones'] ? 'table-cell' : 'none'; ?>;"><?php echo nl2br(htmlentities($row['observaciones'],ENT_QUOTES,'UTF-8')); ?></td>
                    <td style="display:<?php echo $campos['expediente'] ? 'table-cell' : 'none'; ?>;"><?php echo htmlentities($row['expediente'],ENT_QUOTES,'UTF-8'); ?></td>
                    <td style="display:<?php echo $campos['resolucion'] ? 'table-cell' : 'none'; ?>;"><?php echo htmlentities($row['resolucion'],ENT_QUOTES,'UTF-8'); ?></td>
                    <td style="display:<?php echo $campos['excel'] ? 'table-cell' : 'none'; ?>;"><?php if ($row['excel_nombre'] != ''): ?><a href="descargar_archivo_avance.php?idAvance=<?php echo (int)$row['id_avance']; ?>&amp;tipo=excel" target="_blank"><?php echo htmlentities($row['excel_nombre'],ENT_QUOTES,'UTF-8'); ?></a><?php endif; ?></td>
                    <td style="display:<?php echo $campos['pdf'] ? 'table-cell' : 'none'; ?>;"><?php if ($row['pdf_nombre'] != ''): ?><a href="descargar_archivo_avance.php?idAvance=<?php echo (int)$row['id_avance']; ?>&amp;tipo=pdf" target="_blank"><?php echo htmlentities($row['pdf_nombre'],ENT_QUOTES,'UTF-8'); ?></a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div>
<script>
var anios = <?php echo json_encode($anios); ?>;
var opcionesComunes = {responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}};
<?php if ($campos['cantidad']): ?>
new Chart(document.getElementById('graficoCantidad'),{type:'bar',data:{labels:anios,datasets:[{label:'Planificado',data:<?php echo json_encode($planificado); ?>,backgroundColor:'#94a3b8'},{label:'Ejecutado',data:<?php echo json_encode($ejecutado); ?>,backgroundColor:'#3b82f6'}]},options:opcionesComunes});
<?php endif; ?>
<?php if ($campos['fecha_avance']): ?>
new Chart(document.getElementById('graficoFechas'),{type:'line',data:{labels:anios,datasets:[{label:'Registros',data:<?php echo json_encode($registros); ?>,borderColor:'#10b981',backgroundColor:'rgba(16,185,129,.18)',fill:true,tension:.2}]},options:opcionesComunes});
<?php endif; ?>
<?php if ($campos['hombres'] || $campos['mujeres'] || $campos['instituciones']): ?>
var desglose=[];
<?php if ($campos['hombres']): ?>desglose.push({label:'Hombres',data:<?php echo json_encode($hombres); ?>,backgroundColor:'#3b82f6'});<?php endif; ?>
<?php if ($campos['mujeres']): ?>desglose.push({label:'Mujeres',data:<?php echo json_encode($mujeres); ?>,backgroundColor:'#ec4899'});<?php endif; ?>
<?php if ($campos['instituciones']): ?>desglose.push({label:'Instituciones',data:<?php echo json_encode($instituciones); ?>,backgroundColor:'#f59e0b'});<?php endif; ?>
new Chart(document.getElementById('graficoDesglose'),{type:'bar',data:{labels:anios,datasets:desglose},options:opcionesComunes});
<?php endif; ?>
<?php if ($campos['dpto_dist_col'] && count($ubicaciones) > 0): ?>
new Chart(document.getElementById('graficoUbicacion'),{type:'bar',data:{labels:<?php echo json_encode($ubicaciones); ?>,datasets:[{label:'<?php echo $campos['cantidad'] ? 'Cantidad' : 'Registros'; ?>',data:<?php echo json_encode($totalesUbicacion); ?>,backgroundColor:'#8b5cf6'}]},options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}}}});
<?php endif; ?>
</script>
</body>
</html>
