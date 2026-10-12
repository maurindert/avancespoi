function inicializar_planificacion_metas_lista(){    
    listarMetas();
}

function listarMetas(){    
    var datos = {};
    var valorOE;
    var valorIndicador;
    var valorMeta;
    var valorAnio;

    datos['cadenaBuscar'] = $('#listaMetasCadenaBuscar').val();

    /*
     * Se evita el operador ?? para mantener compatibilidad
     * con navegadores/entornos JavaScript antiguos del SIRA.
     */
    valorOE = $('#filtroOE').val();
    valorIndicador = $('#filtroIndicador').val();
    valorMeta = $('#filtroMeta').val();
    valorAnio = $('#filtroAnio').val();

    datos['filtroOE'] = (valorOE != null) ? valorOE : '';
    datos['filtroIndicador'] = (valorIndicador != null) ? valorIndicador : '';
    datos['filtroMeta'] = (valorMeta != null) ? valorMeta : '';
    datos['filtroAnio'] = (valorAnio != null) ? valorAnio : '';
    datos['mostrarAnulados'] = $('#listaMetasMostrarAnulados').is(':checked') ? 1 : 0;

    rpcSeccion('planificacion','MetasListar',datos,'id:listaMetas');
}

function anularMeta(idMeta) {
    var datos = {};
    datos['idMeta'] = idMeta;
    rowRPC = new Array();
    rpcSeccion('planificacion', 'MetasAnular', datos, 'fn:posAnularMeta();');
}

function posAnularMeta() {
    if (typeof rowRPC != 'undefined' && rowRPC && String(rowRPC['ok']) == '0') {
        dialogo(rowRPC['mensaje']);
        return;
    }
    listarMetas();
    dialogo('La meta fue anulada correctamente.', null, 'basura');
    rowRPC = new Array();
}
