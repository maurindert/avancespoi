function inicializar_planificacion_avancespoi_ae() {
    var filas = '#trFechaAvance, #trCantidad, #trHombres, #trMujeres, #trInstituciones, #trDptoDistCol, #trLogros, #trDificultades, #trObservaciones, #trExpediente, #trResolucion, #trExcel, #trPdf';
    $(filas).each(function () {
        if ($(this).css('display') == 'none') {
            $(this).find('input, textarea').prop('disabled', true);
        }
    });

    $('#hombres, #mujeres, #instituciones').each(function () {
        if ($(this).val() === '') $(this).val(0);
    });
    if ($('#trCantidad').css('display') == 'none' && $('#cantidad').val() === '') $('#cantidad').val(0);

    $('#periodos').on('change', function () {
        var periodo = $(this).val();
        var maximo = parseInt($('#maximoMeta' + periodo).val(), 10) || 0;
        var acumulado = parseInt($('#acumuladoMeta' + periodo).val(), 10) || 0;
        var disponible = Math.max(maximo - acumulado, 0);
        if (maximo > 0 && $('#trCantidad').css('display') != 'none') {
            $('#cantidadDisponible').html('Disponible para el a�o ' + periodo + ': <b>' + disponible + '</b> de ' + maximo);
        } else if ($('#trCantidad').css('display') != 'none') {
            $('#cantidadDisponible').html('<b>La meta no tiene un valor proyectado para este a�o.</b>');
        } else {
            $('#cantidadDisponible').html('');
        }
    }).trigger('change');

    var hayHombres = $('#trHombres').css('display') != 'none';
    var hayMujeres = $('#trMujeres').css('display') != 'none';
    var hayInstituciones = $('#trInstituciones').css('display') != 'none';
    if ($('#trCantidad').css('display') != 'none' && (hayHombres || hayMujeres || hayInstituciones)) {
        $('#cantidad').prop('readonly', true);
        $('#hombres, #mujeres, #instituciones').on('input change', function () {
            var total = 0;
            if (hayHombres) total += parseInt($('#hombres').val(), 10) || 0;
            if (hayMujeres) total += parseInt($('#mujeres').val(), 10) || 0;
            if (hayInstituciones) total += parseInt($('#instituciones').val(), 10) || 0;
            $('#cantidad').val(total);
        });
        $('#hombres').trigger('change');
    }

    if ($('#trDptoDistCol').css('display') != 'none') {
        if ($('#tierRecibosAgEd_idDpto').val() != '') {
            $('#tierRecibosAgEd_idDistrito').prop('disabled', false);
            $('#tierRecibosAgEd_mostrarOcultarLupaDistrito').show();
        }
        if ($('#tierRecibosAgEd_idDistrito').val() != '') {
            $('#tierRecibosAgEd_idColonia').prop('disabled', false);
            $('#tierRecibosAgEd_mostrarOcultarLupaColonia').show();
        }
    }
}

function agregarEditarAvances() {
    var datos = {};
    datos['idAvanceEdit'] = $('#idAvanceEdit').val();
    datos['idMeta'] = $('#idMetaAvance').val();
    datos['periodo'] = $('#periodos').val();
    datos['fechaAvance'] = $('#fechaAvance').val();
    datos['hombres'] = $('#hombres').val() || 0;
    datos['mujeres'] = $('#mujeres').val() || 0;
    datos['instituciones'] = $('#instituciones').val() || 0;
    datos['cantidad'] = $('#cantidad').val() || 0;
    datos['logros'] = $('#logros').val();
    datos['dificultades'] = $('#dificultades').val();
    datos['observacion'] = $('#observacion').val();
    datos['expediente'] = $('#expediente').val();
    datos['resolucion'] = $('#resolucion').val();
    datos['dpto'] = $('#tierRecibosAgEd_idDptoHtml').text().trim();
    datos['distrito'] = $('#tierRecibosAgEd_idDistritoHtml').text().trim();
    datos['colonia'] = $('#tierRecibosAgEd_idColoniaHtml').text().trim();
    datos['idDpto'] = $('#tierRecibosAgEd_idDpto').val();
    datos['idDistrito'] = $('#tierRecibosAgEd_idDistrito').val();
    datos['idColonia'] = $('#tierRecibosAgEd_idColonia').val();

    if (!datos.idMeta || !datos.periodo) {
        dialogo('Debe indicar la meta y el periodo.');
        return;
    }
    if ($('#trFechaAvance').css('display') != 'none' &&
        (!datos.fechaAvance || datos.fechaAvance.substring(0, 4) != datos.periodo)) {
        dialogo('La fecha del avance debe pertenecer al periodo seleccionado.');
        return;
    }
    if ($('#trCantidad').css('display') != 'none' &&
        (!/^[0-9]+$/.test(datos.cantidad) || Number(datos.cantidad) <= 0 ||
        Number(datos.cantidad) > 2147483647)) {
        dialogo('La cantidad debe ser un entero mayor a cero.');
        return;
    }
    for (var i = 0, campos = ['hombres', 'mujeres', 'instituciones'], filas = ['trHombres', 'trMujeres', 'trInstituciones']; i < campos.length; i++) {
        var valor = datos[campos[i]];
        if ($('#' + filas[i]).css('display') != 'none' &&
            (!/^[0-9]+$/.test(valor) || Number(valor) > 2147483647)) {
            dialogo('Ingrese un valor entero no negativo en ' + campos[i] + '.');
            return;
        }
    }
    if ($('#trDptoDistCol').css('display') != 'none' &&
        (datos['dpto'] == '' || datos['distrito'] == '' || datos['colonia'] == '' ||
        datos['idDpto'] == '' || datos['idDistrito'] == '' || datos['idColonia'] == '')) {
        dialogo('Debe seleccionar el departamento, distrito y colonia.');
        return;
    }
    if ($('#trCantidad').css('display') != 'none') {
        var maximo = parseInt($('#maximoMeta' + datos['periodo']).val(), 10) || 0;
        var acumulado = parseInt($('#acumuladoMeta' + datos['periodo']).val(), 10) || 0;
        var cantidad = parseInt(datos['cantidad'], 10) || 0;
        if (maximo <= 0) {
            dialogo('La meta no tiene un valor proyectado para el a�o ' + datos['periodo'] + '.');
            return;
        }
        if (acumulado + cantidad > maximo) {
            var disponible = Math.max(maximo - acumulado, 0);
            dialogo('La cantidad ingresada (' + cantidad + ') supera el m�ximo disponible para el a�o ' +
                datos['periodo'] + '. Ya se registraron ' + acumulado + ' de ' + maximo +
                '. Puede cargar como m�ximo ' + disponible + '.');
            $('#cantidad').focus().select();
            return;
        }
    }
    var archivos = [];
    var archivoExcel = document.getElementById('archivoExcel');
    var archivoPdf = document.getElementById('archivoPdf');
    if ($('#trExcel').css('display') != 'none' && archivoExcel && archivoExcel.files && archivoExcel.files.length > 0) {
        if (!/\.(xls|xlsx)$/i.test(archivoExcel.files[0].name)) {
            dialogo('El archivo Excel debe tener extensi�n .xls o .xlsx.');
            return;
        }
        archivos.push({archivo: archivoExcel.files[0], prefijo: 'excel'});
    }
    if ($('#trPdf').css('display') != 'none' && archivoPdf && archivoPdf.files && archivoPdf.files.length > 0) {
        if (!/\.pdf$/i.test(archivoPdf.files[0].name)) {
            dialogo('El archivo PDF debe tener extensi�n .pdf.');
            return;
        }
        archivos.push({archivo: archivoPdf.files[0], prefijo: 'pdf'});
    }
    for (var a = 0; a < archivos.length; a++) {
        if (archivos[a].archivo.size > 10485760) {
            dialogo('Cada archivo puede tener un tama�o m�ximo de 10 MB.');
            return;
        }
    }
    if (archivos.length == 0) {
        enviarDatosAvance(datos);
        return;
    }

    var pendientes = archivos.length;
    var falloArchivo = false;
    for (var j = 0; j < archivos.length; j++) {
        (function (item) {
            var lector = new FileReader();
            lector.onload = function (evento) {
                if (falloArchivo) return;
                var resultado = String(evento.target.result || '');
                var separador = resultado.indexOf(',');
                if (separador < 0) {
                    falloArchivo = true;
                    dialogo('No se pudo preparar el archivo seleccionado.');
                    return;
                }
                datos[item.prefijo + 'Archivo'] = resultado.substring(separador + 1);
                datos[item.prefijo + 'Nombre'] = item.archivo.name;
                datos[item.prefijo + 'Tipo'] = item.archivo.type || 'application/octet-stream';
                pendientes--;
                if (pendientes == 0) enviarDatosAvance(datos);
            };
            lector.onerror = function () {
                if (!falloArchivo) dialogo('No se pudo leer el archivo seleccionado.');
                falloArchivo = true;
            };
            lector.readAsDataURL(item.archivo);
        })(archivos[j]);
    }
}

function enviarDatosAvance(datos) {
    $('#submitAvance').prop('disabled', true);
    rowRPC = new Array();
    rpcSeccion('planificacion', 'AvancesAgregarEditar', datos, 'fn:posAgregarEditarAvances();');
}

function posAgregarEditarAvances() {
    if (typeof rowRPC != 'undefined' && rowRPC && String(rowRPC['ok']) == '0') {
        $('#submitAvance').prop('disabled', false);
        dialogo(rowRPC['mensaje']);
        return;
    }
    listarAvancesMeta();
    $('#fechaAvance, #cantidad, #logros, #dificultades, #observacion, #expediente, #resolucion, #archivoExcel, #archivoPdf').val('');
    $('#hombres, #mujeres, #instituciones').val(0);
    $('#tierRecibosAgEd_idDpto, #tierRecibosAgEd_idDistrito, #tierRecibosAgEd_idColonia').val('');
    $('#tierRecibosAgEd_idDptoHtml, #tierRecibosAgEd_idDistritoHtml, #tierRecibosAgEd_idColoniaHtml').html('');
    $('#maximoMeta2026, #maximoMeta2027, #maximoMeta2028, #acumuladoMeta2026, #acumuladoMeta2027, #acumuladoMeta2028').val('');
    $('#excelActualNombre, #pdfActualNombre').val('');
    $('#submitAvance').prop('disabled', false);
    rowRPC = new Array();
    cerrarVentana();
}

function seleccionarDepartamentoTierraRecibos(id) {
    var datos = new Array();
    datos['idDpto'] = id;
    rpcSeccion('tierra', 'coloniaDenominarDpto', datos, 'fn:posSeleccionarDepartamentoTierraRecibos();');
}

function verificarCampoDptoTierraRecibos() {
    if ($('#tierRecibosAgEd_idDpto').val() != '') activarDesactivarDistritoTierraRecibos(true);
}

function activarDesactivarDistritoTierraRecibos(trigger) {
    if (trigger) {
        $('#tierRecibosAgEd_idDistrito').prop('disabled', false);
        $('#tierRecibosAgEd_mostrarOcultarLupaDistrito').show();
        $('#tierRecibosAgEd_idDistrito').focus();
    } else {
        $('#tierRecibosAgEd_idDistrito').prop('disabled', true);
        $('#tierRecibosAgEd_mostrarOcultarLupaDistrito').hide();
    }
}

function posSeleccionarDepartamentoTierraRecibos() {
    var row = rowRPC;
    $('#tierRecibosAgEd_idDpto').val(row['idDpto']);
    $('#tierRecibosAgEd_idDptoHtml').html(row['dpto']);
    activarDesactivarDistritoTierraRecibos(true);
}

function verificarCampoDistritoTierraRecibos() {
    if ($('#tierRecibosAgEd_idDistrito').val() != '') activarDesactivarColoniaTierraRecibos(true);
}

function denominarDistritoTierraRecibos() {
    if ($('#tierRecibosAgEd_idDpto').val() != '' && $('#tierRecibosAgEd_idDistrito').val() != '') {
        var codigo = $('#tierRecibosAgEd_idDpto').val() + '.' + $('#tierRecibosAgEd_idDistrito').val();
        denominar('tierra', 'distritoDenominar', codigo, 'tierRecibosAgEd_idDistritoHtml');
    }
}

function denominarColoniaTierraRecibos() {
    if ($('#tierRecibosAgEd_idDpto').val() != '' && $('#tierRecibosAgEd_idDistrito').val() != '' &&
        $('#tierRecibosAgEd_idColonia').val() != '') {
        var codigo = $('#tierRecibosAgEd_idDpto').val() + '.' + $('#tierRecibosAgEd_idDistrito').val() + '.' +
            $('#tierRecibosAgEd_idColonia').val();
        denominar('tierra', 'coloniaDenominar', codigo, 'tierRecibosAgEd_idColoniaHtml');
    }
}

function verificarCampoColoniaTierraRecibos() {
    return $('#tierRecibosAgEd_idColonia').val() != '';
}

function seleccionarDistritoTierraRecibos() {
    var idDpto = $('#tierRecibosAgEd_idDpto').val();
    cargarSeccion('tierra', 'distritos_lista',
        'accionPosSeleccionar=id:tierRecibosAgEd_idDistrito,tierRecibosAgEd_idDistritoHtml&idDpto=' + idDpto,
        'ventana');
    activarDesactivarColoniaTierraRecibos(true);
}

function activarDesactivarColoniaTierraRecibos(trigger) {
    if (trigger) {
        $('#tierRecibosAgEd_idColonia').prop('disabled', false);
        $('#tierRecibosAgEd_mostrarOcultarLupaColonia').show();
        $('#tierRecibosAgEd_idColonia').focus();
    } else {
        $('#tierRecibosAgEd_idColonia').prop('disabled', true);
        $('#tierRecibosAgEd_mostrarOcultarLupaColonia').hide();
    }
}

function seleccionarColoniaTierraRecibos() {
    var idDpto = $('#tierRecibosAgEd_idDpto').val();
    var idDistrito = $('#tierRecibosAgEd_idDistrito').val();
    cargarSeccion('tierra', 'colonias_lista',
        'accionPosSeleccionar=id:tierRecibosAgEd_idColonia,tierRecibosAgEd_idColoniaHtml&idDpto=' + idDpto +
        '&idDistrito=' + idDistrito,
        'ventana');
}
