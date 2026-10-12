<?php
$idMeta = (int)recibir_dato('idMeta');
$transaccion = false;
try {
    if ($idMeta <= 0) throw new Exception('Meta invalida');
    $db->set_query('BEGIN');
    $db->execute_query();
    $transaccion = true;

    $sql = "SELECT id_meta, activo FROM planificacion.metas
        WHERE id_meta = $idMeta FOR UPDATE";
    $db->set_query($sql);
    $db->execute_query();
    $registro = $db->get_array();
    if (!$registro) throw new Exception('Meta inexistente');
    if ($registro['activo'] === false || $registro['activo'] === 'f' || $registro['activo'] === '0') {
        throw new Exception('La meta ya se encuentra anulada');
    }

    $db->set_query("UPDATE planificacion.metas SET activo = FALSE WHERE id_meta = $idMeta");
    $db->execute_query();
    $db->set_query('COMMIT');
    $db->execute_query();
    $transaccion = false;
    $resp['row'] = array('ok' => 1, 'mensaje' => 'Meta anulada correctamente');
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
