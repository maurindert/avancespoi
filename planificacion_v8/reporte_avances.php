<?php
header('Content-Type: text/html; charset=Windows-1252');

require_once '../../fuentestra/funciones/php/fecha.php';
// -----------------------------
// CONEXI�N PDO
// -----------------------------
try {
  $db = new PDO(
    "pgsql:host=10.10.53.215;dbname=siradev",
    "indert",
    "Indert*25"
  );
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  die("Error de conexi�n: " . $e->getMessage());
}

// -----------------------------
// CONSULTA GERENCIAL
// -----------------------------
$sql = "
    SELECT 
        oe.nombre AS objetivo,
        m.denominacion AS meta,
        m.valor_real_2026 AS planificado,
        COALESCE(SUM(a.cantidad), 0) AS ejecutado,
        ROUND(
            (COALESCE(SUM(a.cantidad),0) * 100.0 / NULLIF(m.valor_real_2026,0)),
            2
        ) AS porcentaje_avance,
        m.responsable
    FROM planificacion.metas m
    JOIN planificacion.niveles_estrategicos ne 
        ON ne.id_nivel = m.id_nivel
    JOIN planificacion.objetivos_estrategicos oe 
        ON oe.id_oe = ne.id_oe
    LEFT JOIN planificacion.avances a 
        ON a.id_meta = m.id_meta
       AND a.activo IS TRUE
    WHERE m.activo IS TRUE
    GROUP BY 
        oe.nombre,
        m.denominacion,
        m.valor_real_2026,
        m.responsable
    ORDER BY 
        oe.nombre,
        m.denominacion
";

$stmt = $db->prepare($sql);
$stmt->execute();

// ARRAYS & KPIs
$labels = [];
$planificado = [];
$ejecutado = [];
$porcentaje = [];
$responsable = [];

$total = 0;
$suma = 0;
$criticas = 0;
$intermedias = 0;
$excelentes = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $labels[] = $row['meta'];
  $planificado[] = (float) $row['planificado'];
  $ejecutado[] = (float) $row['ejecutado'];
  $porcentaje[] = (float) $row['porcentaje_avance'];
  $responsable[] = $row['responsable'];

  $total++;
  $suma += $row['porcentaje_avance'];

  if ($row['porcentaje_avance'] < 50) {
    $criticas++;
  } elseif ($row['porcentaje_avance'] < 90) {
    $intermedias++;
  } else {
    $excelentes++;
  }
}

$promedioGeneral = ($total > 0) ? round($suma / $total, 2) : 0;

?>

<!DOCTYPE html>
<html lang="es">

  <head>
    <meta charset="Windows-1252">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Gerencial de Avances</title>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
      :root {
       /* --bg-main: #f8fafc; */
        --bg-main: #99DFFF;
        --card-bg: #ffffff;
        --text-primary: #0f172a;
        --text-secondary: #64748b;
        --border-color: #e2e8f0;

        --color-danger: #ef4444;
        --color-warning: #f59e0b;
        --color-success: #10b981;
        --color-info: #3b82f6;
      }

      body {
        font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background-color: var(--bg-main);
        color: var(--text-primary);
        margin: 0;
        padding: 30px;
      }

      .container {
        max-width: 1200px;
        margin: 0 auto;
      }

      .header {
        margin-bottom: 25px;
      }

      .header h2 {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 700;
      }

      /* KPIs Layout */
      .kpis-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 30px;
      }

      .kpi-card {
        background: var(--card-bg);
        padding: 20px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
        border-top: 4px solid var(--border-color);
      }

      .kpi-card.info {
        border-top-color: var(--color-info);
      }

      .kpi-card.danger {
        border-top-color: var(--color-danger);
      }

      .kpi-card.warning {
        border-top-color: var(--color-warning);
      }

      .kpi-card.success {
        border-top-color: var(--color-success);
      }

      .kpi-value {
        font-size: 1.8rem;
        font-weight: 700;
        line-height: 1.2;
      }

      .kpi-label {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-top: 6px;
        font-weight: 500;
      }

      /* Dashboard Cards */
      .section-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 24px;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        margin-bottom: 30px;
      }

      .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-top: 0;
        margin-bottom: 20px;
      }

      /* Chart Container */
      .chart-wrapper {
        position: relative;
        width: 100%;
        min-height: 300px;
      }

      /* Table Design */
      .table-wrapper {
        overflow-x: auto;
      }

      table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 0.9rem;
      }

      th {
        background-color: #f1f5f9;
        color: var(--text-secondary);
        font-weight: 600;
        padding: 12px 16px;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
      }

      td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color);
      }

      tr:hover {
        background-color: #f8fafc;
      }

      .text-right {
        text-align: right;
      }

      .text-center {
        text-align: center;
      }

      /* Progress Bar Inside Table */
      .progress-container {
        display: flex;
        align-items: center;
        gap: 10px;
      }

      .progress-bar-bg {
        flex-grow: 1;
        background-color: #e2e8f0;
        height: 8px;
        border-radius: 4px;
        overflow: hidden;
      }

      .progress-bar-fill {
        height: 100%;
        border-radius: 4px;
      }

      .progress-text {
        font-weight: 600;
        min-width: 50px;
        text-align: right;
      }

      .badge {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 20px;
        font-weight: 600;
      }

      .bg-danger {
        background: #fee2e2;
        color: #991b1b;
      }

      .bg-warning {
        background: #fef3c7;
        color: #92400e;
      }

      .bg-success {
        background: #d1fae5;
        color: #065f46;
      }

      /* Botones de acci�n */
      .btn-action {
        padding: 15px 18px;
        border-radius: 6px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        font-size: 18px;
        transition: background 0.2s;
      }

      .btn-pdf {
        background-color: #2563eb;
        color: white;
      }

      .btn-pdf:hover {
        background-color: #1d4ed8;
      }

      .btn-print {
        background-color: #64748b;
        color: white;
      }

      .btn-print:hover {
        background-color: #475569;
      }

      /* Configuraci�n de la hoja al imprimir */
      @page {
        size: A4 portrait;
        margin: 12mm 10mm;
      }

      /* REGLAS COMPARTIDAS: Impresi�n f�sica (@media print) y Exportaci�n a PDF (.pdf-mode) */
      /* REGLAS COMPARTIDAS: Impresi�n f�sica (@media print) y Exportaci�n a PDF (.pdf-mode) */
@media print,
.pdf-mode {
  body {
    background-color: #ffffff !important;
    padding: 0 !important;
    font-size: 11px !important;
  }

  .container {
    max-width: 100% !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  .header {
    margin-bottom: 12px !important;
  }

  .header h2 {
    font-size: 1.3rem !important;
  }

  .kpis-grid {
    gap: 8px !important;
    margin-bottom: 15px !important;
  }

  .kpi-card {
    padding: 8px 12px !important;
    border-top-width: 3px !important;
  }

  .kpi-value {
    font-size: 1.3rem !important;
  }

  .kpi-label {
    font-size: 0.7rem !important;
    margin-top: 2px !important;
  }

  .section-card {
    box-shadow: none !important;
    border: 1px solid #cbd5e1 !important;
    padding: 12px !important;
    margin-bottom: 15px !important;
    page-break-inside: avoid;
    break-inside: avoid;
  }

  .section-title {
    font-size: 0.95rem !important;
    margin-bottom: 10px !important;
  }

  /* Ajuste de contenedor del gr�fico */
  .chart-wrapper {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  table {
    font-size: 0.75rem !important;
  }

  th {
    padding: 6px 8px !important;
    background-color: #f1f5f9 !important;
  }

  td {
    padding: 4px 8px !important;
  }

  .progress-container {
    gap: 6px !important;
  }

  .progress-bar-bg {
    height: 6px !important;
  }

  .badge {
    padding: 2px 5px !important;
    font-size: 15px !important;
  }

  .no-print {
    display: none !important;
  }
}
    </style>
  </head>

  <body>

    <div class="container">
        <div class="no-print" style="display: flex; gap: 10px; justify-content: space-between;">
          <!--<button onclick="descargarPDF()" class="btn-action btn-pdf">&#128229; Descargar PDF</button>-->
          <span></span>
          <button onclick="window.print()" class="btn-action btn-print">&#128424; Imprimir</button>
        </div>
        <br><br>
      <!-- ENCABEZADO -->
        <div class="header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2 style="display: flex; justify-content: space-between; align-items: center; text-transform: capitalize; margin: 0; width: 100%;">
                <span>Reporte Gerencial de Avances POI (2026)</span>
                <span style="font-weight: inherit;">
                    <?php echo htmlentities(formatear_fecha('ahora', 'l, d *de* F *de* Y'), ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </h2>
      </div>

      <!-- KPIs -->
      <div class="kpis-grid">
        <div class="kpi-card info">
          <div class="kpi-value"><?= $total ?></div>
          <div class="kpi-label">Total Metas</div>
        </div>

        <div class="kpi-card info">
          <div class="kpi-value"><?= $promedioGeneral ?>%</div>
          <div class="kpi-label">Promedio General</div>
        </div>

        <div class="kpi-card danger">
          <div class="kpi-value" style="color: var(--color-danger);"><?= $criticas ?></div>
          <div class="kpi-label">Cr�ticas (&lt;50%)</div>
        </div>

        <div class="kpi-card warning">
          <div class="kpi-value" style="color: var(--color-warning);"><?= $intermedias ?></div>
          <div class="kpi-label">Intermedias (50% - 89%)</div>
        </div>

        <div class="kpi-card success">
          <div class="kpi-value" style="color: var(--color-success);"><?= $excelentes ?></div>
          <div class="kpi-label">Excelentes (&ge;90%)</div>
        </div>
      </div>

      <!-- GR�FICO -->
      <div class="section-card">
        <h3 class="section-title">Nivel de Avance por Meta</h3>
        <div class="chart-wrapper">
          <canvas id="graficoAvance"></canvas>
        </div>
      </div>

      <!-- TABLA -->
      <div class="section-card">
        <h3 class="section-title">Detalle de Ejecuci�n</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Meta</th>
                <th class="text-right">Planificado</th>
                <th class="text-right">Ejecutado</th>
                <th style="width: 250px;">% Avance</th>
                <th>Responsable</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($labels as $i => $meta): ?>
                <?php
                $val = $porcentaje[$i];
                if ($val < 50) {
                  $colorHex = 'var(--color-danger)';
                  $badgeClass = 'bg-danger';
                } elseif ($val < 90) {
                  $colorHex = 'var(--color-warning)';
                  $badgeClass = 'bg-warning';
                } else {
                  $colorHex = 'var(--color-success)';
                  $badgeClass = 'bg-success';
                }
                ?>
                <tr>
                  <td style="font-size:20px;"><?= htmlentities($meta, ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="text-right" style="font-size:20px;"><?= number_format($planificado[$i], 0, ',', '.') ?></td>
                  <td class="text-right" style="font-size:20px;"><?= number_format($ejecutado[$i], 0, ',', '.') ?></td>
                  <td>
                    <div class="progress-container">
                      <div class="progress-bar-bg">
                        <div class="progress-bar-fill"
                          style="width: <?= min($val, 100) ?>%; background-color: <?= $colorHex ?>; font-size:20px;">
                        </div>
                      </div>
                      <span class="progress-text badge <?= $badgeClass ?>" style="font-size:20px;"><?= $val ?>%</span>
                    </div>
                  </td>
                  <td style="color: var(--text-secondary); font-size:20px;"><?= htmlentities($responsable[$i], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

<script>
  const labels = <?= json_encode($labels) ?>;
  const data = <?= json_encode($porcentaje) ?>;

  const wrapper = document.querySelector('.chart-wrapper');
  const alturaPantalla = (labels.length * 45) + 100; 
  wrapper.style.height = `${alturaPantalla}px`;

  const barColors = data.map(v => {
    if (v < 50) return '#ef4444';
    if (v < 90) return '#f59e0b';
    return '#10b981';
  });

  const formatearLabels = (arregloLabels) => {
    return arregloLabels.map(label => {
      const maxCaracteres = 22;
      if (label.length <= maxCaracteres) return label;

      const palabras = label.split(' ');
      let linea1 = '';
      let linea2 = '';

      for (let palabra of palabras) {
        if ((linea1 + ' ' + palabra).trim().length <= maxCaracteres || linea1 === '') {
          linea1 += (linea1 ? ' ' : '') + palabra;
        } else {
          linea2 += (linea2 ? ' ' : '') + palabra;
        }
      }
      return [linea1, linea2];
    });
  };

  const miGrafico = new Chart(document.getElementById('graficoAvance'), {
    type: 'bar',
    data: {
      labels: formatearLabels(labels),
      datasets: [{
        label: '% Avance',
        data: data,
        backgroundColor: barColors,
        borderRadius: 4,
        borderSkipped: true,
        barPercentage: 0.5,
        categoryPercentage: 0.8
      }]
    },
    options: {
      clip:false,
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      layout: {
        padding: {
          top: 10,
          bottom: 10,
          left: 10,
          right: 120 // <--- Margen de seguridad para meter el 100% dentro de la tarjeta
        }
      },
      plugins: {
        legend: { display: false }
      },
      scales: {
        x: {
          beginAtZero: true,
          min: 0,
          max: 100,
          ticks: {
            stepSize: 10,      // Escala exacta: 0%, 10%, 20%, ... 100%
            autoSkip: false,   // <--- OBLIGA a Chart.js a mostrar siempre el tick del 100%
            callback: (value) => value + '%',
            font: { size: 12 }
          },
          grid: { color: '#f1f5f9' }
        },
        y: {
          grid: { display: true },
          ticks: {
            autoSkip: false,
            font: { size: 12 }
          }
        }
      }
    }
  });

  window.addEventListener('beforeprint', () => {
    miGrafico.resize();
  });

  window.addEventListener('afterprint', () => {
    miGrafico.resize();
  });
</script>

  </body>

</html>
