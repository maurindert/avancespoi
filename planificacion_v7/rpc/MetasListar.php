<?php

    /*
     * Listado de metas del POI.
     *
     * Compatible con:
     * - PHP 5.6
     * - PostgreSQL 10
     * - Framework legacy SIRA
     */

    // ============================================================
    // 1. RECIBIR FILTROS
    // ============================================================

    $cadenaBuscar    = recibir_dato('cadenaBuscar');
    $filtroOE        = recibir_dato('filtroOE');
    $filtroIndicador = recibir_dato('filtroIndicador');
    $filtroMeta      = recibir_dato('filtroMeta');
    $filtroAnio      = recibir_dato('filtroAnio');
    $mostrarAnuladosDato = recibir_dato('mostrarAnulados');
    $mostrarAnulados = ($mostrarAnuladosDato === true || $mostrarAnuladosDato === '1' || $mostrarAnuladosDato === 1);
    $condicionMetasActivas = $mostrarAnulados ? '' : ' AND m.activo IS TRUE ';


    // ============================================================
    // 1.1 NORMALIZAR IDS
    // ============================================================

    if ($filtroOE != '') {
        $filtroOE = (int)$filtroOE;
    }

    if ($filtroIndicador != '') {
        $filtroIndicador = (int)$filtroIndicador;
    }

    if ($filtroMeta != '') {
        $filtroMeta = (int)$filtroMeta;
    }

    if ($filtroAnio != '') {
        $filtroAnio = (int)$filtroAnio;
    }


    // ============================================================
    // 1.2 VALIDAR RELACIONES ENTRE LOS FILTROS
    // ============================================================
    //
    // Jerarqua de prioridad:
    //
    // OE
    //   -> Indicador
    //       -> Meta
    //
    // Si cambia un filtro superior y una seleccin inferior ya
    // no pertenece a l, se elimina la seleccin incompatible.
    // ============================================================


    // ------------------------------------------------------------
    // Validar INDICADOR contra OE
    // ------------------------------------------------------------

    if ($filtroOE != '' && $filtroIndicador != '') {

        $sqlValidarIndicador = "
            SELECT
                i.id_indicador
            FROM planificacion.indicadores i

            JOIN planificacion.metas m
                ON m.id_meta = i.id_meta

            JOIN planificacion.niveles_estrategicos n
                ON n.id_nivel = m.id_nivel

            WHERE i.id_indicador = " . (int)$filtroIndicador . "
              AND n.id_oe = " . (int)$filtroOE . "
              $condicionMetasActivas
        ";

        $db->set_query($sqlValidarIndicador);
        $db->execute_query();

        $rowValidarIndicador = $db->get_array();

        if (!$rowValidarIndicador) {
            $filtroIndicador = '';
            $filtroMeta = '';
        }
    }


    // ------------------------------------------------------------
    // Validar META contra OE
    // ------------------------------------------------------------

    if ($filtroOE != '' && $filtroMeta != '') {

        $sqlValidarMetaOE = "
            SELECT
                m.id_meta
            FROM planificacion.metas m

            JOIN planificacion.niveles_estrategicos n
                ON n.id_nivel = m.id_nivel

            WHERE m.id_meta = " . (int)$filtroMeta . "
              AND n.id_oe = " . (int)$filtroOE . "
              $condicionMetasActivas
        ";

        $db->set_query($sqlValidarMetaOE);
        $db->execute_query();

        $rowValidarMetaOE = $db->get_array();

        if (!$rowValidarMetaOE) {
            $filtroMeta = '';
        }
    }


    // ------------------------------------------------------------
    // Validar META contra INDICADOR
    // ------------------------------------------------------------

    if ($filtroIndicador != '' && $filtroMeta != '') {

        $sqlValidarMetaIndicador = "
            SELECT
                i.id_indicador
            FROM planificacion.indicadores i

            JOIN planificacion.metas m
                ON m.id_meta = i.id_meta

            WHERE i.id_indicador = " . (int)$filtroIndicador . "
              AND i.id_meta = " . (int)$filtroMeta . "
              $condicionMetasActivas
        ";

        $db->set_query($sqlValidarMetaIndicador);
        $db->execute_query();

        $rowValidarMetaIndicador = $db->get_array();

        if (!$rowValidarMetaIndicador) {
            $filtroMeta = '';
        }
    }


    // ============================================================
    // 2. ARMAR OPCIONES DE LOS FILTROS
    // ============================================================


    // ============================================================
    // 2.a OBJETIVOS ESTRATEGICOS
    // ============================================================
    //
    // Si existe indicador o meta seleccionada, solamente se
    // muestran los OE relacionados con esas selecciones.
    // ============================================================

    $sqlOE = "
        SELECT DISTINCT
            o.id_oe,
            o.nombre

        FROM planificacion.objetivos_estrategicos o

        JOIN planificacion.niveles_estrategicos n
            ON n.id_oe = o.id_oe

        JOIN planificacion.metas m
            ON m.id_nivel = n.id_nivel

        WHERE 1 = 1
        $condicionMetasActivas
    ";


    if ($filtroIndicador != '') {

        $sqlOE .= "
            AND EXISTS (
                SELECT 1
                FROM planificacion.indicadores io
                WHERE io.id_meta = m.id_meta
                  AND io.id_indicador = " . (int)$filtroIndicador . "
            )
        ";
    }


    if ($filtroMeta != '') {

        $sqlOE .= "
            AND m.id_meta = " . (int)$filtroMeta . "
        ";
    }


    $sqlOE .= "
        ORDER BY o.id_oe ASC
    ";


    $db->set_query($sqlOE);
    $db->execute_query();


    $opciones_oe = '<option value="">Todos</option>';


    while ($rowOE = $db->get_array()) {

        $textoRaw = trim($rowOE['nombre']);

        $textoUtf8 = mb_check_encoding($textoRaw, 'UTF-8')
                   ? $textoRaw
                   : utf8_encode($textoRaw);


        $selected = (
            $filtroOE != ''
            && $filtroOE == $rowOE['id_oe']
        )
        ? 'selected="selected"'
        : '';


        $opciones_oe .=
            '<option value="' . (int)$rowOE['id_oe'] . '" ' . $selected . '>'
            . htmlentities($textoUtf8, ENT_QUOTES, 'UTF-8')
            . '</option>';
    }



    // ============================================================
    // 2.b INDICADORES
    // ============================================================
    //
    // Un indicador pertenece a una meta.
    //
    // Si existe:
    // - OE seleccionado     -> solo indicadores del OE
    // - Meta seleccionada   -> solo indicadores de esa meta
    // ============================================================

    $sqlInd = "
        SELECT DISTINCT
            i.id_indicador,
            i.descripcion

        FROM planificacion.indicadores i

        JOIN planificacion.metas m
            ON m.id_meta = i.id_meta

        JOIN planificacion.niveles_estrategicos n
            ON n.id_nivel = m.id_nivel

        WHERE 1 = 1
        $condicionMetasActivas
    ";


    if ($filtroOE != '') {

        $sqlInd .= "
            AND n.id_oe = " . (int)$filtroOE . "
        ";
    }


    if ($filtroMeta != '') {

        $sqlInd .= "
            AND m.id_meta = " . (int)$filtroMeta . "
        ";
    }


    $sqlInd .= "
        ORDER BY i.id_indicador ASC
    ";


    $db->set_query($sqlInd);
    $db->execute_query();


    $opciones_ind = '<option value="">Todos</option>';


    while ($rowInd = $db->get_array()) {

        $textoRaw = trim($rowInd['descripcion']);

        $textoUtf8 = mb_check_encoding($textoRaw, 'UTF-8')
                   ? $textoRaw
                   : utf8_encode($textoRaw);


        $selected = (
            $filtroIndicador != ''
            && $filtroIndicador == $rowInd['id_indicador']
        )
        ? 'selected="selected"'
        : '';


        $opciones_ind .=
            '<option value="' . (int)$rowInd['id_indicador'] . '" ' . $selected . '>'
            . htmlentities($textoUtf8, ENT_QUOTES, 'UTF-8')
            . '</option>';
    }



    // ============================================================
    // 2.c METAS
    // ============================================================
    //
    // Si existe:
    // - OE seleccionado        -> solo metas de ese OE
    // - Indicador seleccionado -> solo meta(s) del indicador
    // ============================================================

    $sqlMetas = "
        SELECT DISTINCT
            m.id_meta,
            m.denominacion

        FROM planificacion.metas m

        JOIN planificacion.niveles_estrategicos n
            ON n.id_nivel = m.id_nivel

        WHERE 1 = 1
        $condicionMetasActivas
    ";


    if ($filtroOE != '') {

        $sqlMetas .= "
            AND n.id_oe = " . (int)$filtroOE . "
        ";
    }


    if ($filtroIndicador != '') {

        $sqlMetas .= "
            AND EXISTS (
                SELECT 1
                FROM planificacion.indicadores im
                WHERE im.id_meta = m.id_meta
                  AND im.id_indicador = " . (int)$filtroIndicador . "
            )
        ";
    }


    $sqlMetas .= "
        ORDER BY m.id_meta ASC
    ";


    $db->set_query($sqlMetas);
    $db->execute_query();


    $opciones_metas = '<option value="">Todos</option>';


    while ($rowMeta = $db->get_array()) {

        $textoRaw = trim($rowMeta['denominacion']);

        $textoUtf8 = mb_check_encoding($textoRaw, 'UTF-8')
                   ? $textoRaw
                   : utf8_encode($textoRaw);


        $selected = (
            $filtroMeta != ''
            && $filtroMeta == $rowMeta['id_meta']
        )
        ? 'selected="selected"'
        : '';


        $opciones_metas .=
            '<option value="' . (int)$rowMeta['id_meta'] . '" ' . $selected . '>'
            . htmlentities($textoUtf8, ENT_QUOTES, 'UTF-8')
            . '</option>';
    }

    // Una fila por meta, aunque aun no tenga avances.
    $sql = "
        SELECT m.id_meta, m.denominacion AS meta, m.tipo_meta, m.activo,
               o.nombre AS objetivo, n.codigo AS nivel,
               COALESCE(ind.indicador, '') AS indicador,
               m.valor_real_2026, m.valor_real_2027, m.valor_real_2028,
               COALESCE(av.cantidad_registros, 0) AS cantidad_registros,
               COALESCE(av.ejecutado_2026, 0) AS ejecutado_2026,
               COALESCE(av.ejecutado_2027, 0) AS ejecutado_2027,
               COALESCE(av.ejecutado_2028, 0) AS ejecutado_2028
        FROM planificacion.metas m
        JOIN planificacion.niveles_estrategicos n ON n.id_nivel = m.id_nivel
        JOIN planificacion.objetivos_estrategicos o ON o.id_oe = n.id_oe
        LEFT JOIN (
            SELECT id_meta, string_agg(descripcion, ' / ' ORDER BY id_indicador) AS indicador
            FROM planificacion.indicadores GROUP BY id_meta
        ) ind ON ind.id_meta = m.id_meta
        LEFT JOIN (
            SELECT id_meta, COUNT(*) AS cantidad_registros,
                   SUM(CASE WHEN anio = 2026 THEN cantidad ELSE 0 END) AS ejecutado_2026,
                   SUM(CASE WHEN anio = 2027 THEN cantidad ELSE 0 END) AS ejecutado_2027,
                   SUM(CASE WHEN anio = 2028 THEN cantidad ELSE 0 END) AS ejecutado_2028
            FROM planificacion.avances WHERE activo IS TRUE GROUP BY id_meta
        ) av ON av.id_meta = m.id_meta AND m.activo IS TRUE
    ";
    $where = array();
    if (!$mostrarAnulados) $where[] = 'm.activo IS TRUE';
    if ($filtroOE != '') $where[] = 'o.id_oe = ' . (int)$filtroOE;
    if ($filtroIndicador != '') $where[] = 'EXISTS (SELECT 1 FROM planificacion.indicadores fi WHERE fi.id_meta = m.id_meta AND fi.id_indicador = ' . (int)$filtroIndicador . ')';
    if ($filtroMeta != '') $where[] = 'm.id_meta = ' . (int)$filtroMeta;
    if ($filtroAnio != '') {
        $anio = (int)$filtroAnio;
        if ($anio == 2026 || $anio == 2027 || $anio == 2028) {
            $where[] = "(m.meta_$anio IS NOT NULL OR m.valor_real_$anio IS NOT NULL OR EXISTS (SELECT 1 FROM planificacion.avances af WHERE af.id_meta = m.id_meta AND af.anio = $anio AND af.activo IS TRUE))";
        } else {
            $where[] = "EXISTS (SELECT 1 FROM planificacion.avances af WHERE af.id_meta = m.id_meta AND af.anio = $anio AND af.activo IS TRUE)";
        }
    }
    if (trim($cadenaBuscar) != '') {
        $buscar = pg_escape_string($cadenaBuscar);
        $where[] = "(m.denominacion ILIKE '%$buscar%' OR o.nombre ILIKE '%$buscar%' OR n.codigo ILIKE '%$buscar%' OR EXISTS (SELECT 1 FROM planificacion.indicadores bi WHERE bi.id_meta = m.id_meta AND bi.descripcion ILIKE '%$buscar%'))";
    }
    if (count($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY o.id_oe, n.codigo, m.denominacion, m.id_meta';
    $db->set_query($sql);
    $db->execute_query();

    $matriz = array();
    $titulos = array('Ver', 'Anular', 'Objetivo', 'Nivel', 'Indicador', 'Meta', 'Tipo', 'Avances',
        'Porcentaje Total Avanzado (A�o 2026)', 'Porcentaje Total Avanzado (A�o 2027)',
        'Porcentaje Total Avanzado (A�o 2028)');
    foreach ($titulos as $i => $titulo) {
        $matriz[0][$i]['html'] = $titulo;
        $matriz[0][$i]['style'] = 'text-align:left;';
    }
    $contador = 0;
    while ($row = $db->get_array()) {
        $contador++;
        $id = (int)$row['id_meta'];
        $activo = ($row['activo'] === true || $row['activo'] === 't' || $row['activo'] === '1' || $row['activo'] === 1);
        $anioColor = ($filtroAnio == 2026 || $filtroAnio == 2027 || $filtroAnio == 2028)
            ? (int)$filtroAnio : (int)date('Y');
        $estiloFila = '';
        if ($anioColor >= 2026 && $anioColor <= 2028 && (int)$row['valor_real_' . $anioColor] > 0) {
            $porcentaje = ((float)$row['ejecutado_' . $anioColor] * 100) / (int)$row['valor_real_' . $anioColor];
            if ($porcentaje < 70) {
                $estiloFila = 'background-color:#ffcdd2;';
            } elseif ($porcentaje < 90) {
                $estiloFila = 'background-color:#fff9c4;';
            } else {
                $estiloFila = 'background-color:#c8e6c9;';
            }
        }
        $matriz[$contador][0]['html'] = $activo
            ? '<div style="width:16px;height:16px;"><a href="javascript:cargarSeccion(\'planificacion\',\'avancespoi_vista\',\'idMeta=' . $id . '\');" title="Ver avances"><img src="fuentestra/imagenes/bot/16x16/search.png" style="width:16px; height:16px;" /></a></div>' : '';
        $matriz[$contador][1]['html'] = $activo
            ? '<div style="width:16px;height:16px;"><a class="permiso_MetasAnular" href="javascript:dialogo(\'La meta quedar� anulada y dejar� de incluirse en los c�lculos y reportes. �Desea continuar?\',\'si:anularMeta(' . $id . ');|no\',\'eliminar_campos\');" title="Anular"><img src="fuentestra/imagenes/bot/16x16/delete.png" style="width:16px; height:16px;" /></a></div>' : '';
        $matriz[$contador][2]['html'] = htmlentities($row['objetivo'], ENT_QUOTES, 'UTF-8');
        $matriz[$contador][3]['html'] = htmlentities($row['nivel'], ENT_QUOTES, 'UTF-8');
        $matriz[$contador][4]['html'] = htmlentities($row['indicador'], ENT_QUOTES, 'UTF-8');
        $nombreMeta = htmlentities($row['meta'], ENT_QUOTES, 'UTF-8');
        $matriz[$contador][5]['html'] = $activo
            ? '<a href="javascript:cargarSeccion(\'planificacion\',\'avancespoi_vista\',\'idMeta=' . $id . '\');">' . $nombreMeta . '</a>' : $nombreMeta;
        $matriz[$contador][6]['html'] = htmlentities($row['tipo_meta'], ENT_QUOTES, 'UTF-8');
        $matriz[$contador][7]['html'] = $activo ? (int)$row['cantidad_registros'] : 'Anulada';
        foreach (array(2026, 2027, 2028) as $indice => $anio) {
            $valor = $row['valor_real_' . $anio];
            if (!$activo) {
                $matriz[$contador][8 + $indice]['html'] = 'Anulada';
            } elseif ($valor === null || (int)$valor <= 0) {
                $matriz[$contador][8 + $indice]['html'] = 'Sin meta';
            } else {
                $avance = ((float)$row['ejecutado_' . $anio] * 100) / (int)$valor;
                $color = ($avance < 70) ? 'red' : (($avance < 90) ? '#d48806' : 'green');
                $matriz[$contador][8 + $indice]['html'] = '<strong style="color:' . $color . ';">'
                    . number_format($avance, 2, ',', '.') . '%</strong>';
            }
        }
        if (!$activo) $estiloFila = 'background-color:#eeeeee;color:#777;';
        for ($columna = 0; $columna <= 10; $columna++) {
            $matriz[$contador][$columna]['style'] = 'text-align:'
                . (($columna == 2 || $columna == 4 || $columna == 5 || $columna == 6) ? 'left;' : 'center;')
                . $estiloFila;
        }
    }

    $scriptActualizarDropdown = "<script type=\"text/javascript\">
        $('#filtroOE').html(" . json_encode($opciones_oe) . ").val(" . json_encode($filtroOE) . ");
        $('#filtroIndicador').html(" . json_encode($opciones_ind) . ").val(" . json_encode($filtroIndicador) . ");
        $('#filtroMeta').html(" . json_encode($opciones_metas) . ").val(" . json_encode($filtroMeta) . ");
        $('#filtroAnio').val(" . json_encode($filtroAnio) . ");
    </script>";
    $resp['html'] = armarLista($matriz) . $scriptActualizarDropdown;
?>
