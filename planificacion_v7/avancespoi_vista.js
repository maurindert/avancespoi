function inicializar_planificacion_avancespoi_vista() {
    listarAvancesMeta();
}
function listarAvancesMeta() {
    var datos = {};
    datos['idMeta'] = $('#vistaIdMeta').val();
    datos['cadenaBuscar'] = $('#vistaCadenaBuscar').val();
    datos['filtroAnio'] = $('#vistaAnio').val();
    datos['mostrarAnulados'] = $('#vistaMostrarAnulados').is(':checked') ? 1 : 0;
    rpcSeccion('planificacion', 'AvancesListar', datos, 'id:listaAvancesMeta');
}

function anularAvance(idAvance) {
    var datos = {};
    datos['idAvance'] = idAvance;
    datos['idMeta'] = $('#vistaIdMeta').val();
    rowRPC = new Array();
    rpcSeccion('planificacion', 'AvancesAnular', datos, 'fn:posAnularAvance();');
}

function posAnularAvance() {
    if (typeof rowRPC != 'undefined' && rowRPC && String(rowRPC['ok']) == '0') {
        dialogo(rowRPC['mensaje']);
        return;
    }
    listarAvancesMeta();
    dialogo('El avance fue anulado correctamente.', null, 'basura');
    rowRPC = new Array();
}
