<?php
$idAvance = isset($_GET['idAvance']) ? (int)$_GET['idAvance'] : 0;
$tipo = isset($_GET['tipo']) ? strtolower(trim($_GET['tipo'])) : '';
if ($idAvance <= 0 || ($tipo != 'excel' && $tipo != 'pdf')) {
    http_response_code(400);
    die('Solicitud de archivo invalida');
}

try {
    $db = new PDO(
        'pgsql:host=10.10.53.215;dbname=siradev',
        'indert',
        'Indert*25'
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die('No fue posible acceder al archivo');
}

$columnaArchivo = ($tipo == 'excel') ? 'excel_archivo' : 'pdf_archivo';
$columnaNombre = ($tipo == 'excel') ? 'excel_nombre' : 'pdf_nombre';
$columnaMime = ($tipo == 'excel') ? 'excel_tipo' : 'pdf_tipo';
$sql = "SELECT a.$columnaNombre AS nombre, a.$columnaMime AS mime,
        encode(a.$columnaArchivo, 'base64') AS contenido
    FROM planificacion.avances a
    JOIN planificacion.metas m ON m.id_meta = a.id_meta AND m.activo IS TRUE
    WHERE a.id_avance = :id_avance AND a.activo IS TRUE
    AND a.$columnaArchivo IS NOT NULL";
$stmt = $db->prepare($sql);
$stmt->execute(array(':id_avance' => $idAvance));
$archivo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$archivo) {
    http_response_code(404);
    die('Archivo inexistente o perteneciente a un registro anulado');
}

$contenido = base64_decode($archivo['contenido'], true);
if ($contenido === false) {
    http_response_code(500);
    die('El archivo almacenado no es valido');
}
$nombre = basename(str_replace('\\', '/', $archivo['nombre']));
$nombreCabecera = str_replace(array("\r", "\n", '"'), '', $nombre);
$mime = trim($archivo['mime']) != '' ? $archivo['mime'] : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($contenido));
header('Content-Disposition: attachment; filename="' . $nombreCabecera . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
echo $contenido;
exit;
?>
