<?php
if (isset($url[1])) {
    $pagina = $url[1] ? $url[1] : 1;   ///Número de Página
} else {
    $pagina = 1;
}

$pagina_clientes = $pagina;

$proceso_actual = "clientes";
$busqueda = "";

if (isset($_SESSION['filtro'])) {
    if ($_SESSION['filtro'] == "") {
        $no_hacer = false;
    } else {
        if (isset($_SESSION['origen'])) {
            if ($_SESSION['origen'] == $proceso_actual) {
                $busqueda = $_SESSION['filtro'];
                $no_hacer = true;
            } else {
                $no_hacer = false;
                $_SESSION['origen'] = "";
                $_SESSION['filtro'] = "";
            }
        } else {
            $no_hacer = false;
            $_SESSION['origen'] = "";
            $_SESSION['filtro'] = "";
        }
    }
} else {
    $no_hacer = false;
}

$ruta_origen = "dashboard/";
$ruta_clientesnew = "clientesNew";
$orden = isset($_GET['orden']) ? $_GET['orden'] : '#';
$direccion = isset($_GET['dir']) ? $_GET['dir'] : 'ASC';

$registrosPorPagina = 10;

// === Recuperar el orden desde sesión y parsearlo ===
$ordenActivo = [];

if (!isset($clientes)) {
    echo "Problema con el Controlador de Address Class";
}

if (!isset($_SESSION['nav_clientes'])) {
    // Almacenar el nivel y página actual en la sesión
    $_SESSION['nav_clientes'] = [
        'pagina_clientes' => $pagina_clientes,
        'registrosPorPagina' => $registrosPorPagina
    ];
} else {
    if (isset($_SESSION['nav_clientes']['registrosPorPagina'])) {
        if (!is_numeric($_SESSION['nav_clientes']['registrosPorPagina'])) {
            $_SESSION['nav_clientes']['registrosPorPagina'] = $registrosPorPagina;
        }
        $registrosPorPagina = $_SESSION['nav_clientes']['registrosPorPagina'];
    }

    if (isset($_SESSION['nav_clientes']['orden'])) {
        $f_orden = $_SESSION['nav_clientes']['orden'];

        $ordenStr = $_SESSION['nav_clientes']['orden'];
        $partes = explode(',', $ordenStr);
        foreach ($partes as $parte) {
            if (strpos($parte, ':') !== false) {
                [$campo, $dir] = explode(':', $parte);
                $ordenActivo[trim($campo)] = trim($dir);
            }
        }
    }

    if (isset($_SESSION['nav_clientes']['pagina_clientes'])) {
        if (!is_numeric($_SESSION['nav_clientes']['pagina_clientes'])) {
            $_SESSION['nav_clientes']['pagina_clientes'] = $pagina_clientes;
        }
    } else {
        $_SESSION['nav_clientes'] = [
            'pagina_clientes' => $pagina_clientes
        ];
    }
}

$ruta_retorno = RUTA_APP . "/dashboard";

$no_hacer = true;
$ruta_clientes_ajax = APP_URL . "/app/ajax/clientesAjax.php";
$encabezado = PROJECT_ROOT . "/app/views/inc/encabezado.php";
$opcion = "clientes";


$cont_status = $clientes->contar_status();
error_log("Status: " . print_r($cont_status, true));
$count_activos = $cont_status['activos'] ?? 0;
$count_inactivos = $cont_status['inactivos'] ?? 0;
$count_todos = $count_activos + $count_inactivos;

$filtro_cto = 1;

$clase_f = "fa-solid fa-filter";
?>

<main>
    <?php
    require_once $encabezado;
    ?>

    <div class="containe-grid">
        <div class="containe-grid-01">
            <div class="container-filter">
                <div class="grid-titulo-item2">
                    <h2 class="titulo_form_filter">
                        <span class="<?php echo $clase_f; ?>">&nbsp</span>
                        Main Filter
                    </h2>
                </div>
            </div>
            <!-- Panel lateral izquierdo (15%) -->
            <div class="crud-sidebar-filters">
                <div class="filter-card">
                    <h3 class="filter-title">
                        <span class="filter-icon">👤</span>
                        Client Status
                    </h3>

                    <div class="radio-group">
                        <label class="radio-card active">
                            <input type="radio" id="filtro-activos" name="filtro_estado" value="activos" checked>
                            <span class="radio-indicator"></span>
                            <div class="radio-content">
                                <span class="radio-label">Active</span>
                                <span class="radio-count" id="count-activos"><?php echo $count_activos; ?></span>
                            </div>
                            <div class="radio-glow"></div>
                        </label>

                        <label class="radio-card">
                            <input type="radio" id="filtro-inactivos" name="filtro_estado" value="inactivos">
                            <span class="radio-indicator"></span>
                            <div class="radio-content">
                                <span class="radio-label">Inactive</span>
                                <span class="radio-count" id="count-inactivos"><?php echo $count_inactivos; ?></span>
                            </div>
                            <div class="radio-glow"></div>
                        </label>

                        <label class="radio-card">
                            <input type="radio" id="filtro-todos" name="filtro_estado" value="todos">
                            <span class="radio-indicator"></span>
                            <div class="radio-content">
                                <span class="radio-label">All</span>
                                <span class="radio-count" id="count-todos"><?php echo $count_todos; ?></span>
                            </div>
                            <div class="radio-glow"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="containe-grid-02">
            <div class="container pb-1 pt-1">
                <div id="datos_act" name="datos_act">
                    <?php
                    if ($no_hacer == false) {
                        $busca_frase = "";
                    } else {
                        $busca_frase = $busqueda;
                    }

                    $param_datos = [
                        $pagina_clientes,
                        $registrosPorPagina,
                        $url[0],
                        $busca_frase,
                        $ruta_retorno,
                        $orden,
                        $direccion,
                        $filtro_cto
                    ];
                    echo $clientes->listarclientesControlador($param_datos);
                    ?>
                </div>
            </div>
        </div>
        <div containe-grid-03></div>
    </div>

    <!-- Modal para seleccionar contrato -->
    <div class="modal fade" id="modalSeleccionarContrato" tabindex="-1" role="dialog" aria-labelledby="modalSeleccionarContratoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="modalSeleccionarContratoLabel">
                        <i class="fas fa-file-signature mr-2"></i>
                        Select Contract
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong id="clienteNombreModal"></strong> has multiple active contracts.
                        Please select one to view details.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Contract Number</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="listaContratosModal">
                                <!-- Los contratos se cargarán aquí dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Inyectar variables para búsqueda global -->
<script>
    window.CONFIG_BUSQUEDA = <?php echo json_encode([
                                    'modulo' => $proceso_actual ?? 'clientes',
                                    'pagina' => $pagina_clientes ?? 1,
                                    'registrosPorPagina' => $registrosPorPagina ?? 10,
                                    'url' => $url[0] ?? 'clientes',
                                    'orden' => $orden ?? '#',
                                    'direccion' => $direccion ?? 'ASC',
                                    'ruta_retorno' => $ruta_retorno ?? '/dashboard',
                                    'origen' => $proceso_actual
                                ]); ?>;
</script>

<script src="<?= RUTA_REAL ?>/app/views/inc/js/ajax-busqueda.js?v=<?= time() ?>"></script>
<script src="<?= RUTA_REAL ?>/app/views/inc/js/func_comm.js?v=<?= time() ?>"></script>
<script type="text/javascript">
    // Variables globales para el modal de contratos
    let clienteIdSeleccionado = null;
    let contratosCliente = [];

    const ruta_retorno = "<?php echo $ruta_retorno; ?>";
    const origen = "<?php echo $ruta_origen; ?>";

    // ============================================
    // NUEVO: SISTEMA DE FILTROS DE ESTADO
    // ============================================
    let filtroEstadoActual = 'activos';

    /**
     * Cambia el filtro de estado y recarga la tabla
     */
    function cambiarFiltroEstado(nuevoFiltro) {
        if (filtroEstadoActual === nuevoFiltro) return;

        console.log(`🔄 Filtro: ${filtroEstadoActual} → ${nuevoFiltro}`);
        filtroEstadoActual = nuevoFiltro;

        // Actualizar UI visual
        document.querySelectorAll('.radio-card').forEach(card => {
            card.classList.remove('active');
        });

        const radioSeleccionado = document.getElementById(`filtro-${nuevoFiltro}`);
        if (radioSeleccionado) {
            radioSeleccionado.closest('.radio-card').classList.add('active');
            radioSeleccionado.checked = true;
        }

        // Limpiar búsqueda de texto
        const filtroTexto = document.getElementById("txt_buscador");
        if (filtroTexto) filtroTexto.value = '';

        // Recargar tabla con nuevo filtro
        const pagina = window.CONFIG_BUSQUEDA?.pagina || 1;
        const registrosPorPagina = window.CONFIG_BUSQUEDA?.registrosPorPagina || 10;
        const urlOrigen = window.CONFIG_BUSQUEDA?.url || 'clientes';

        recargarTablaclientes(pagina, registrosPorPagina, urlOrigen, '');
    }

    /**
     * Inicializa los event listeners de los filtros de estado
     */
    function inicializarFiltrosEstado() {
        const radios = document.querySelectorAll('input[name="filtro_estado"]');

        radios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                cambiarFiltroEstado(e.target.value);
            });
        });

        // Marcar activo inicial
        const radioActivo = document.getElementById('filtro-activos');
        if (radioActivo) {
            radioActivo.closest('.radio-card').classList.add('active');
        }

        console.log('✅ Filtros de estado inicializados');
    }

    // ============================================
    // MODIFICACIÓN: recargarTablaclientes con filtro
    // ============================================

    /**
     * Recarga la tabla de clientes incluyendo el filtro de estado actual
     */
    async function recargarTablaclientes(pagina, registrosPorPagina, urlOrigen, busqueda = '') {
        try {
            console.log(`Recargando tabla: página=${pagina}, registros=${registrosPorPagina}, filtro_estado=${filtroEstadoActual}, búsqueda="${busqueda}"`);
            const res = await fetch('/app/ajax/clientesAjax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_clientes: 'listar_tabla',
                    pagina: pagina,
                    registros_por_pagina: registrosPorPagina,
                    url_origen: urlOrigen,
                    busca_frase: busqueda,
                    filtro_estado: filtroEstadoActual // ← NUEVO PARÁMETRO
                })
            });

            const html = await res.text();

            const wrapper = document.getElementById('tabla-clientes-wrapper');
            if (wrapper) {
                wrapper.outerHTML = html;
            } else {
                const datosAct = document.getElementById('datos_act');
                if (datosAct) datosAct.innerHTML = html;
            }

        } catch (err) {
            console.error('Error al recargar tabla de clientes:', err);
            await suiteAlertError('Error', 'Could not refresh the client list.');
        }
    }

    // ============================================
    // ELIMINACIÓN DE DIRECCIONES (sin cambios)
    // ====================================

    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.btn-eliminar-direccion');
        if (!btn) return;

        const idDireccion = btn.dataset.id;
        const pagina = parseInt(btn.dataset.pagina);
        const registrosPorPagina = parseInt(btn.dataset.registros);
        const urlOrigen = btn.dataset.url;

        if (!idDireccion || isNaN(pagina) || isNaN(registrosPorPagina)) {
            await suiteAlertError('Error', 'Missing data to delete address.');
            return;
        }

        const confirmado = await suiteConfirm(
            'Confirm Delete',
            'Are you sure you want to delete this address? This action cannot be undone.', {
                aceptar: 'Yes, delete',
                cancelar: 'Cancel'
            }
        );

        if (!confirmado) return;

        try {
            const res = await fetch('/app/ajax/clientesAjax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_clientes: 'eliminar',
                    id_direccion: idDireccion
                })
            });

            const data = await res.json();

            if (data.success) {
                const filtro_act = document.getElementById("txt_buscador");
                if (filtro_act) filtro_act.value = '';
                const valor = filtro_act?.value.trim();

                recargarTablaclientes(pagina, registrosPorPagina, urlOrigen, valor);
                await suiteAlertSuccess('Deleted', 'The address has been successfully removed.');
            } else {
                await suiteAlertError('Error', data.message || 'Could not delete the address.');
            }
        } catch (err) {
            console.error('Error al eliminar dirección:', err);
            await suiteAlertError('Connection Error', 'Could not connect to the server.');
        }

        // Control de MODAL por tener mas de un contrato asociado
        const btn_ctto = e.target.closest('.btn-ver-contratos');
        if (!btn_ctto) return;

        e.preventDefault();

        clienteIdSeleccionado = btn_ctto.dataset.clienteId;
        const clienteNombre = btn_ctto.dataset.clienteNombre;

        document.getElementById('clienteNombreModal').textContent = clienteNombre;

        cargarContratosCliente(clienteIdSeleccionado);

        $('#modalSeleccionarContrato').modal('show');
    });

    // ============================================
    // FUNCIONES DE CONTRATOS (sin cambios)
    // ============================================

    async function cargarContratosCliente(clienteId) {
        try {
            suiteLoading('show');

            const res = await fetch('/app/ajax/clientesAjax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_clientes: 'obtener_contratos_cliente',
                    id_cliente: clienteId
                })
            });

            const data = await res.json();

            if (data.success && data.contratos && data.contratos.length > 0) {
                contratosCliente = data.contratos;
                mostrarContratosEnModal(data.contratos);
            } else {
                document.getElementById('listaContratosModal').innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p>No contracts found</p>
                    </td>
                </tr>
            `;
            }

        } catch (err) {
            console.error('Error al cargar contratos:', err);
            await suiteAlertError('Error', 'Could not load contracts');
            document.getElementById('listaContratosModal').innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-danger">
                    Error loading contracts
                </td>
            </tr>
        `;
        } finally {
            suiteLoading('hide');
        }
    }

    function mostrarContratosEnModal(contratos) {
        const tbody = document.getElementById('listaContratosModal');
        tbody.innerHTML = '';

        contratos.forEach(contrato => {
            const fechaInicio = contrato.fecha_inicio ? new Date(contrato.fecha_inicio).toLocaleDateString() : 'N/A';
            const fechaFin = contrato.fecha_fin ? new Date(contrato.fecha_fin).toLocaleDateString() : 'N/A';

            const row = document.createElement('tr');
            row.innerHTML = `
            <td>
                <strong>${contrato.numero_contrato || 'N/A'}</strong>
            </td>
            <td>${fechaInicio}</td>
            <td>${fechaFin}</td>
            <td>
                <span class="badge badge-${getBadgeClass(contrato.status)}">
                    ${contrato.status || 'Active'}
                </span>
            </td>
            <td>
                <a href="${RUTA_APP}/contratosVista/contrato/${clienteIdSeleccionado}/${contrato.id_contrato}" 
                class="btn btn-sm btn-primary"
                title="View Contract Details">
                    <i class="fas fa-eye"></i> View
                </a>
            </td>
        `;
            tbody.appendChild(row);
        });
    }

    function getBadgeClass(status) {
        const statusLower = status ? status.toLowerCase() : 'active';
        const classes = {
            'active': 'success',
            'inactive': 'secondary',
            'pending': 'warning',
            'cancelled': 'danger',
            'expired': 'dark'
        };
        return classes[statusLower] || 'info';
    }

    // Cerrar modal al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            $('#modalSeleccionarContrato').modal('hide');
        }
    });

    // ============================================
    // INICIALIZACIÓN AL CARGAR EL DOM
    // ============================================

    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar filtros de estado (NUEVO)
        inicializarFiltrosEstado();
    });
</script>