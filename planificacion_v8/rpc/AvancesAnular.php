<?php
$idAvance = (int)recibir_dato('idAvance');
$idMeta = (int)recibir_dato('idMeta');
$usuario = (int)$tra['id_usuario'];
$transaccion = false;
try {
    if ($idAvance <= 0 || $idMeta <= 0 || $usuario <= 0) throw new Exception('Avance invalido');
    $db->set_query('BEGIN');
    $db->execute_query();
    $transaccion = true;

    $sql = "SELECT a.id_avance, a.activo FROM planificacion.avances a
        JOIN planificacion.metas m ON m.id_meta = a.id_meta AND m.activo IS TRUE
        WHERE a.id_avance = $idAvance AND a.id_meta = $idMeta
        AND a.id_usuario = $usuario FOR UPDATE";
    $db->set_query($sql);
    $db->execute_query();
    $registro = $db->get_array();
    if (!$registro) throw new Exception('Avance inexistente o sin permiso para anular');
    if ($registro['activo'] === false || $registro['activo'] === 'f' || $registro['activo'] === '0') {
        throw new Exception('El avance ya se encuentra anulado');
    }

    $sql = "UPDATE planificacion.avances SET activo = FALSE
        WHERE id_avance = $idAvance AND id_meta = $idMeta AND id_usuario = $usuario";
    $db->set_query($sql);
    $db->execute_query();
    $db->set_query('COMMIT');
    $db->execute_query();
    $transaccion = false;
    $resp['row'] = array('ok' => 1, 'mensaje' => 'Avance anulado correctamente');
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
