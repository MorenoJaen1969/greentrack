<?php
if (isset($url[0])) {
    $proceso_actual = $url[0];
} else {
    $proceso_actual = "contratosVista";
}
if (isset($url[1])) {
    $id_contrato = $url[1];
} else {
    $id_contrato = 0;
}
if (isset($url[2])) {
    $ruta_retorno = RUTA_APP . "/" . $url[2];
} else {
    $ruta_retorno = RUTA_APP . "/dashboard";
}
if (isset($url[3])) {
    $pagina_retorno = $url[3];
} else {
    $pagina_retorno = 1;
}

$param_dat = [
    'id_contrato' => $id_contrato
];

$row1 = $contratos->contratoCompleto_contrato($param_dat);

if (is_null($row1['id_frecuencia_servicio'])) {
    $id_frecuencia_servicio = 1;
} else {
    $id_frecuencia_servicio = $row1['id_frecuencia_servicio'];
}

$id_contrato = $row1['id_contrato'];
$contrato_origen = $row1['contrato_origen'];

$id_cliente = $row1['id_cliente'];
if (is_null($row1['apellido'])) {
    $cliente = $row1['nombre'];
} else {
    $cliente = trim($row1['nombre'] . " " . $row1['apellido']);
}

if (is_null($row1['telefono'])) {
    $telefono = "---";
} else {
    $telefono = $contratos->formatearTelefono($row1['telefono']);
}

if (is_null($row1['email'])) {
    $email = "---";
} else {
    $email = $row1['email'];
}

$id_direccion = $row1['id_direccion'];
$direccion = $row1['direccion'];

if (is_null($row1['nom_contrato'])) {
    $nom_contrato = $cliente."'s contract, unidentified";
} else {
    $nom_contrato = $row1['nom_contrato'];
}

if (is_null($row1['retraso_invierno'])) {
    $retraso_invierno = false;
} else {
    $retraso_invierno = $row1['retraso_invierno'];
}

if (is_null($row1['costo'])) {
    $costo = 0.00;
} else {
    $costo = $contratos->verifica_num($row1['costo']);
}

if (is_null($row1['num_semanas'])) {
    $num_semanas = 0;
} else {
    $num_semanas = $row1['num_semanas'];
}

if (is_null($row1['fecha_ini'])) {
    $fecha_ini = date("Y-m-d");
} else {
    $timestamp = strtotime($row1['fecha_ini']);
    $fecha_ini = date("Y-m-d", $timestamp);
}

if (is_null($row1['fecha_fin'])) {
    $fecha_fin = date("Y-m-d");
} else {
    $timestamp = strtotime($row1['fecha_fin']);
    $fecha_fin = date("Y-m-d", $timestamp);
}

if (is_null($row1['notas'])) {
    $notas = "";
} else {
    $notas = $row1['notas'];
}

if (is_null($row1['observaciones'])) {
    $observaciones = "";
} else {
    $observaciones = $row1['observaciones'];
}

if (is_null($row1['id_frecuencia_pago'])) {
    $id_frecuencia_pago = 0;
} else {
    $id_frecuencia_pago = $row1['id_frecuencia_pago'];
}

$id_status = $row1['id_status'];
$color_status = $row1['color'];
$id_ruta = $row1['id_ruta'];
$id_area = $row1['id_area'];
$fecha_cancelacion = $row1['fecha_cancelacion'];

if ($row1['id_status'] == 21 || $row1['id_status'] == 22) {
    $puede_editar = 'disabled';
    $sit_edicion = "readonly";
} else {
    $puede_editar = '';
    $sit_edicion = "required";
}

if (is_null($row1['id_dia_semana'])) {
    $id_dia_semana = 0;
} else {
    $id_dia_semana = $row1['id_dia_semana'];
}

if (is_null($row1['secondary_day'])) {
    $secondary_day = 0;
} else {
    $secondary_day = $row1['secondary_day'];
}

if (is_null($row1['day_work'])) {
    $day_work = "";
} else {
    $day_work = $row1['day_work'];
}

if (is_null($row1['tiempo_servicio'])) {
    $tiempo_servicio = "00:45:00";
} else {
    $tiempo_servicio = $row1['tiempo_servicio'];
}

//if ($total_servicios > 0) {
//    $modo_edicion = false;
//} else {
$modo_edicion = true;
//}

$result_status = $status->consultar_status('contratos');

$ruta_status_ajax = RUTA_APP . "/app/ajax/statusAjax.php";
$ruta_ruta_ajax = RUTA_APP . "/app/ajax/rutas_mapaAjax.php";
$ruta_frec_pago_ajax = RUTA_APP . "/app/ajax/frec_pagoAjax.php";
$ruta_clientes_ajax = RUTA_APP . "/app/ajax/clientesAjax.php";
$ruta_direcciones_ajax = RUTA_APP . "/app/ajax/direccionesAjax.php";
$ruta_dia_semana_ajax = RUTA_APP . "/app/ajax/dia_semanaAjax.php";
$ruta_servicios_ajax = RUTA_APP . "/app/ajax/serviciosAjax.php";
$ruta_frecuencia_servicio_ajax = RUTA_APP . "/app/ajax/frec_servicioAjax.php";
$ruta_facturacion_ajax = RUTA_APP . "/app/ajax/facturacionAjax.php";
$ruta_areas_ajax = RUTA_APP . "/app/ajax/areasAjax.php";

$ruta_contratos_ajax = RUTA_APP . "/app/ajax/contratosAjax.php";
$ruta_contratos = RUTA_APP . "/contratos/";
$encabezadoVista = PROJECT_ROOT . "/app/views/inc/encabezadoVista.php";
$opcion = "contratosVista";
?>

<main>
    <p hidden id="ruta_contrato"><?php echo $ruta_contratos; ?></p>
    <p hidden id="ruta_contrato_ajax"><?php echo $ruta_contratos_ajax; ?></p>

    <?php
    require_once $encabezadoVista;
    ?>

    <div class="form-container">
        <form class="FormularioAjax form-horizontal validate-form" action="<?php echo $ruta_contratos_ajax; ?>"
            method="POST" id="update_contrato" name="update_contrato" enctype="multipart/form-data" autocomplete="off">

            <input class="form-font" type="hidden" name="modulo_contratos" value="update_contrato">
            <input class="form-font" type="hidden" id="id_contrato" name="id_contrato"
                value="<?php echo $row1['id_contrato']; ?>">

            <div class="tab-container">
                <div class="tabs-gen">
                    <button type="button" class="tab-button tablink" data-tab="tab1" onclick="openTab(event, 'tab1')">
                        Customer Identification
                    </button>
                    <button type="button" class="tab-button active tablink" data-tab="tab2"
                        onclick="openTab(event, 'tab2')">
                        Contract Details
                    </button>
                    <button type="button" class="tab-button tablink" data-tab="tab3" onclick="openTab(event, 'tab3')">
                        Notes and Observations
                    </button>
                    <button type="button" class="tab-button tablink" data-tab="tab4" onclick="openTab(event, 'tab4')">
                        Distribution of services
                    </button>
                    <button type="button" class="tab-button tablink" data-tab="tab5" onclick="openTab(event, 'tab5')">
                        Payment details
                    </button>
                </div>

                <div id="tab1" class="tabcontent tab-link" style="display:none">
                    <!-- Formulario del Arrendatario -->
                    <h3>Customer Details</h3>

                    <div class="forma01">
                        <div class="form-group-ct-inline">
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="id_cliente1">Customer code:</label>
                                <input type="text" id="id_cliente1"
                                    value="<?php echo $contratos->ceros($id_cliente); ?>" placeholder="Customer Coding"
                                    readonly>
                            </div>

                            <div class="form-group-ct">
                                <div class="form-group-ct">
                                    <label for="id_cliente" class="ancho_label1">Customers:</label>
                                    <select class="form-control-co form-font" id="id_cliente" name="id_cliente" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                        <!-- Se llenará con JS -->
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="forma02">
                        <div class="form-group-ct-inline">
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="telefono">Phone:</label>
                                <div class="telefono-call">
                                    <div class="telefono-display">
                                        <div class="telefono-info">
                                            <span class="bandera"></span>
                                            <input class="numero-formateado" type="text" id="telefono" name="telefono"
                                                value="<?php echo $telefono; ?>" readonly>
                                        </div>
                                        <div class="accion-telefono">
                                            <button class="btn-llamar is-small">
                                                <span class="fa-solid fa-phone-volume"></span>
                                            </button>
                                        </div>
                                        <div class="accion-whatsapp">
                                            <button class="btn-whatsapp is-small">
                                                <span class="fa-brands fa-whatsapp"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="email">Email:</label>
                                <input type="email" id="email" name="email" value="<?php echo $email; ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <h3>Related Address</h3>
                    <div class="forma01">
                        <div class="form-group-ct-inline">
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="id_direccion1">Address code:</label>
                                <input type="text" id="id_direccion1"
                                    value="<?php echo $contratos->ceros($id_direccion); ?>" placeholder="Address Coding"
                                    readonly>
                            </div>

                            <div class="form-group-ct">
                                <div class="form-group-ct">
                                    <label for="id_direccion" class="ancho_label1">Address:</label>
                                    <select class="form-control-co form-font" id="id_direccion" name="id_direccion"
                                        <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                        <!-- Se llenará con JS -->
                                    </select>
                                </div>
                            </div>

                            <div class="form-group-ct">
                                <div class="form-group-ct">
                                    <label for="id_ruta" class="ancho_label1">Associated Route:</label>
                                    <select class="form-control-co form-font" id="id_ruta" name="id_ruta" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                        <!-- Se llenará con JS -->
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tab2" class="tabcontent tab-link"  style="display:none">
                    <!-- Formulario del Local alquilado -->
                    <div class="form-group-ct-inline">
                        <h3>Contract Characteristics</h3>
                        <h3 style="margin-left: auto; color: #6d1a72ff; font-weight: bold;">
                            <label class="ancho_label1">
                                <?php if ($id_status == 22 && !empty($fecha_cancelacion)): ?>
                                    Canceled <?= date('Y-m-d', strtotime($fecha_cancelacion)) ?> | 
                                <?php endif; ?>
                                <?= htmlspecialchars($cliente) ?>
                            </label>
                        </h3>
                    </div>

                    <div class="forma01">
                        <div class="grid_geo">
                            <div class="grid_geo_01">
                                <div class="form-group-ct-inline">
                                    <div class="form-group-ct">
                                        <div class="form-group-ct-inline">
                                            <label class="ancho_label1" for="id_contrato_a">Code:</label>
                                            <input class="form-font" type="text" id="id_contrato_a" name="id_contrato_a"
                                                value="<?php echo $contratos->ceros($id_contrato); ?>"
                                                placeholder="Contract coding" readonly>
                                        </div>    
                                        <div class="form-group-ct-inline">
                                            <label class="ancho_label1" for="nom_contrato">Contract Name:</label>
                                            <input class="form-font" type="text" id="nom_contrato" name="nom_contrato"
                                                value="<?php echo $nom_contrato; ?>"
                                                placeholder="Contract name">
                                        </div>    
                                        <div class="form-group-ct-inline">
                                            <label class="ancho_label1" for="contrato_origen">Origin code:</label>
                                            <input class="form-font" type="text" id="contrato_origen" name="contrato_origen"
                                                value="<?php echo $contrato_origen; ?>"
                                                placeholder="Code Number of the Printed Contract">
                                        </div>    
                                        <div class="form-group-ct-inline">
                                            <label for="id_status" class="ancho_label1">Current Status:</label>
                                            <select class="form-control-co form-font" id="id_status" name="id_status" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                                <!-- Se llenará con JS -->
                                            </select>
                                        </div>
                                        <div class="form-group-ct-inline grid_vert">
                                            <div class="form-group-ct grid_vert_01">
                                                <label class="ancho_label5" for="tiempo_servicio">Time required to perform the service:</label>
                                                <input 
                                                    type="text" 
                                                    class="form-font time-hhmmss" 
                                                    id="tiempo_servicio" 
                                                    name="tiempo_servicio"
                                                    placeholder="HH:mm:ss"
                                                    maxlength="8"
                                                    value="<?php echo htmlspecialchars($tiempo_servicio); ?>"
                                                >
                                            </div>
                                            <div class="form-group-ct grid_vert_02">
                                                <label class="ancho_label5" for="retraso_invierno">Use extended hours for Winter:</label>
                                                <div class="switch-container">
                                                    <span class="switch-label off">OFF</span>
                                                    <input 
                                                        type="checkbox" 
                                                        id="retraso_invierno" 
                                                        name="retraso_invierno"
                                                        class="switch-input"
                                                        value="1"
                                                        <?php echo ($retraso_invierno == 1 ? 'checked' : ''); ?>>
                                                    <label for="retraso_invierno" class="switch-track"></label>
                                                    <span class="switch-label on">ON</span>
                                                </div>
                                            </div>                                            
                                        </div>
                                        <div class="form-group-ct-inline">
                                            <label for="id_area" class="ancho_label1">Service Area:</label>
                                            <select class="form-control-co form-font" id="id_area" name="id_area" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                                <!-- Se llenará con JS -->
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid_geo_02">
                                <div class="form-group-ct">
                                    <label for="id_dia_semana" class="ancho_label5">Primary Workday:</label>
                                    <select class="form-control-co form-especial" id="id_dia_semana"
                                        name="id_dia_semana" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                        <!-- Se llenará con JS -->
                                    </select>
                                </div>

                                <div class="form-group-ct">
                                    <label for="secondary_day" class="ancho_label5">Secondary Workday:</label>
                                    <select class="form-control-co" id="secondary_day"
                                        name="secondary_day" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                        <!-- Se llenará con JS -->
                                    </select>
                                </div>

                            </div>
                        </div>
                    </div>

                    <h3>Control dates</h3>

                    <div class="forma01">
                        <div class="form-group-ct-inline">
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="fecha_ini">Start Date:</label>
                                <input type="date" id="fecha_ini" name="fecha_ini" value="<?php echo $fecha_ini; ?>">
                            </div>
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="fecha_fin">End Date:</label>
                                <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
                            </div>

                            <div class="form-group-ct">
                                <label class="ancho_label1 widthField" for="num_semanas">Number of services in the period:</label>
                                <input type="number" id="num_semanas" name="num_semanas" value="<?php echo $num_semanas; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="forma02">
                        <div class="form-group-ct-inline">
                            <div class="form-group-ct">
                                <div class="form-group-ct">
                                    <label for="id_frecuencia_servicio" class="ancho_label1 widthField">Service Frequency:</label>
                                    <select class="form-control-co form-font" id="id_frecuencia_servicio" name="id_frecuencia_servicio" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                        <!-- Se llenará con JS -->
                                    </select>
                                </div>
                            </div>

                            <div class="form-group-ct">
                                <div class="form-group-ct">
                                    <label for="id_frecuencia_pago" class="ancho_label1 widthField">Payment Frequency:</label>
                                    <select class="form-control-co form-font" id="id_frecuencia_pago"
                                        name="id_frecuencia_pago" <?php echo !$modo_edicion ? 'disabled' : ''; ?>
                                        required>
                                        <!-- Se llenará con JS -->
                                    </select>
                                </div>
                            </div>

                            <div class="form-group-ct">
                                <label class="ancho_label1 widthField" for="costo">Payment Amount:</label>
                                <input class="alinea_der" type='currency' id="costo" name="costo"
                                    value="<?php echo $costo; ?>" placeholder="Payment for the period" />
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tab3" class="tabcontent tab-link" style="display:none">
                    <!-- Formulario del detalle del contrato -->
                    <div class="form-group-ct-inline">
                        <h3>Notes and Comments</h3>
                        <h3 style="margin-left: auto; color: #6d1a72ff; font-weight: bold;">
                            <label class="ancho_label1">
                                <?php if ($id_status == 22 && !empty($fecha_cancelacion)): ?>
                                    Canceled <?= date('Y-m-d', strtotime($fecha_cancelacion)) ?> | 
                                <?php endif; ?>
                                <?= htmlspecialchars($cliente) ?>
                            </label>
                        </h3>
                    </div>

                    <div class="forma01">
                        <div class="form-group-ct">
                            <label class="ancho_label1" for="notas">Notes:</label>
                            <textarea class="format_textarea" id="notas" name="notas" placeholder="Add notes"></textarea>
                        </div>
                        <div class="form-group-ct">
                            <label class="ancho_label1" for="observaciones">Comments:</label>
                            <textarea class="format_textarea" id="observaciones" name="observaciones" placeholder="Add comments"></textarea>
                        </div>
                    </div>
                </div>

                <div id="tab4" class="tabcontent tab-link" style="display:none">
                    <!-- Formulario del detalle del contrato -->
                    <div class="form-group-ct-inline">
                        <h3>Distribution of Services</h3>
                        <h3 style="margin-left: auto; color: #6d1a72ff; font-weight: bold;">
                            <label class="ancho_label1">
                                <?php if ($id_status == 22 && !empty($fecha_cancelacion)): ?>
                                    Canceled <?= date('Y-m-d', strtotime($fecha_cancelacion)) ?> - 
                                <?php endif; ?>
                                <?= htmlspecialchars($cliente) ?>
                            </label>
                        </h3>
                    </div>

                    <!-- Tab Distribution of Services -->
                    <div id="distribution-tab" class="table-container" style="height: 60vh;">
                        <div id="loading" class="loading">Loading services...</div>
                        <table id="servicesGrid" class="excel-style">
                            <thead>
                                <tr>
                                    <th class="f_th">Month</th>
                                    <th class="f_th">Week 1</th>
                                    <th class="f_th">Week 2</th>
                                    <th class="f_th">Week 3</th>
                                    <th class="f_th">Week 4</th>
                                    <th class="f_th">Week 5</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Se llena con JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="tab5" class="tabcontent tab-link" style="display:none">
                    <!-- Formulario de facturacion de Servicios-->
                    <div class="form-group-ct-inline">
                        <h3>Payment details</h3>
                        <h3 style="margin-left: auto; color: #6d1a72ff; font-weight: bold;">
                            <label class="ancho_label1">
                                <?php if ($id_status == 22 && !empty($fecha_cancelacion)): ?>
                                    Canceled <?= date('Y-m-d', strtotime($fecha_cancelacion)) ?> | 
                                <?php endif; ?>
                                <?= htmlspecialchars($cliente) ?>
                            </label>
                        </h3>
                    </div>
                    <!-- Tab Distribution of Facturacion -->
                    <div id="facturacion-tab" class="tab-content" style="height: 60vh;">
                        <div class="facturacion-layout">
                            <!-- Panel izquierdo: lista de facturas -->
                            <div class="panel-facturas">
                                <h3>Invoices</h3>
                                <div id="facturas-lista" class="facturas-grid">
                                    <!-- Se llena con JS -->
                                </div>
                            </div>

                            <!-- Panel derecho: detalles -->
                            <div class="panel-detalles">
                                <h3>Invoice Details</h3>
                                <div id="detalles-contenedor">
                                    <p class="mensaje-inicial">Select an invoice to view its details.</p>

                                    <!-- Contenedor de la tabla con scroll -->
                                    <div id="tabla-detalles-wrapper" style="display: none; height: 350px; overflow-y: auto; border: 1px solid #ddd;">
                                        <div id="loadingFact" class="loading">Loading services...</div>
                                        <table class="excel-style tabla_sc">
                                            <thead>
                                                <tr>
                                                    <th class="text_center">Service</th>
                                                    <th class="text_center">Concept</th>
                                                    <th class="text_derecha">Total</th>                                            
                                                </tr>
                                            </thead>
                                        </table>

                                        <!-- Cuerpo con scroll -->
                                        <div id="cuerpo-detalles-scroll" style="overflow-y: auto; max-height: 300px;">
                                            <table class="excel-style tabla_sc">
                                                <tbody id="cuerpo-detalles">
                                                    <!-- Se llena con JS -->
                                                </tbody>
                                            </table>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>                    
                </div>

                <button type="submit" class="btn-submit" id="submitBtn" <?php echo $puede_editar; ?>>
                    Save changes
                </button>
            </div>
        </form>
    </div>
</main>

<!-- El Modal Cambio de situación de Contratos-->
<section class="modal_fade" id="modal_cont">
    <div class="modal-dialog-div">
        <div class="modal-header">
            <div class="modal-recuadro_titulo">
                <h1 class="modal-title" id="modalTitulo">
                    Change of contract status
                </h1>
            </div>
        </div>
        <div class="modal-content">
            <form class="formato_modal" id="formProducto">
                <div class="modal-body">
                    <div class="tipo_accion" id="sub_tit_modal"></div>
                    <div class="form-formato-01">
                        <div class="form-container-01">
                            <div class="elem_modal" id="iden_cont"></div>
                        </div>
                    </div>
                </div>
                <div class="modal_fade_footer">
                    <button class="modal_fade__close btn btn-secundary formato_de_fuente">Cancel</button>
                    <button type="submit" class="btn btn-primary formato_de_fuente">Save Change</button>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Modal contenedor -->
<div id="serviceModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <span class="modal-close" onclick="closeServiceModal()">&times;</span>
        <div id="modalBody">
            <!-- Aquí se cargará el contenido del servicio -->
            <p>Cargando...</p>
        </div>
    </div>
</div>


<script src="<?= RUTA_REAL ?>/app/views/inc/js/func_comm.js?v=<?= time() ?>"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js?v=<?= time() ?>"></script>
<script type="text/javascript">
    let arreglo_frases_g = [];
    var p01 = "Are you sure";
    var p02 = "Do you confirm that you want to save the changes you have made?";
    var p03 = "Yes, Save";
    var p04 = "Do not save";
    var p05 = "Response error";

    arreglo_frases_g = [
        p01,
        p02,
        p03,
        p04,
        p05
    ];

    let r_retorno = "<?php echo $ruta_retorno . "/" . $pagina_retorno; ?>";

    const ruta_frecuencia_servicio_ajax = "<?php echo $ruta_frecuencia_servicio_ajax; ?>";
    const ruta_contratos_ajax = "<?php echo $ruta_contratos_ajax; ?>";
    const ruta_status_ajax = "<?php echo $ruta_status_ajax; ?>";
    const ruta_areas_ajax = "<?php echo $ruta_areas_ajax; ?>";
    const ruta_ruta_ajax = "<?php echo $ruta_ruta_ajax; ?>";
    const ruta_frec_pago_ajax = "<?php echo $ruta_frec_pago_ajax; ?>";
    const ruta_clientes_ajax = "<?php echo $ruta_clientes_ajax; ?>";
    const ruta_direcciones_ajax = "<?php echo $ruta_direcciones_ajax; ?>";
    const ruta_dia_semana_ajax = "<?php echo $ruta_dia_semana_ajax; ?>";
    const ruta_servicios_ajax = "<?php echo $ruta_servicios_ajax; ?>";
    const ruta_facturacion_ajax = "<?php echo $ruta_facturacion_ajax; ?>";
    
    const ruta_retorno = r_retorno;

    // Variable global para mantener las facturas
    let facturasGlobales = [];

    // === ABRIR PESTAÑAS ===
    function openTab(evt, tabName) {
        document.querySelectorAll(".tabcontent").forEach(el => el.style.display = "none");
        document.querySelectorAll(".tablink").forEach(btn => btn.classList.remove("active"));
        const tab = document.getElementById(tabName);
        const submitBtn = document.getElementById('submitBtn');
        tab.style.display = "block";
        if (evt) evt.currentTarget.classList.add("active");

        if (tabName === 'tab4' || tabName === 'tab5' ) {
            submitBtn.style.display = 'none';
        } else {
            submitBtn.style.display = 'block';
        }
    }

    // === VALIDACIÓN DE FORMULARIO ===
    function validateTabs() {
        if (!validateTab(1)) return showTab(1) && false;
        if (!validateTab(2)) return showTab(2) && false;
        if (!validateTab(3)) return showTab(3) && false;
        if (!validateTab(4)) return showTab(4) && false;
        return true;
    }

    function showTab(index) {
        ["tab1", "tab2", "tab3", "tab4", "tab5"].forEach((id, i) => {
            document.getElementById(id).style.display = (i + 1 === index) ? "block" : "none";
        });
        const errorDiv = document.querySelector('#form-errors');
        errorDiv.style.display = 'block';
        errorDiv.textContent = 'Please correct the errors in the form.';
    }

    function validateTab(tabNumber) {
        const fields = document.querySelectorAll(`#tab${tabNumber} input[required], #tab${tabNumber} select[required]`);
        let isValid = true;
        for (let field of fields) {
            const label = document.querySelector(`label[for="${field.id}"]`);
            if (!field.value.trim()) {
                field.classList.add('error');
                if (label) label.classList.add('error');
                if (isValid) field.focus();
                isValid = false;
            } else {
                field.classList.remove('error');
                if (label) label.classList.remove('error');
            }
        }
        return isValid;
    }

    // --- CONFIGURACIÓN ---
    const ID_CONTRATO = "<?php echo $id_contrato; ?>"; // Inyectado desde PHP Blade o similar

    // Fecha actual en formato YYYY-MM-DD
    const TODAY = new Date().toISOString().split('T')[0];

    async function loadAndRenderFacturacion(){
        try {
            const id_contrato = "<?php echo $row1['id_contrato'];?>";
            const res = await fetch(ruta_facturacion_ajax, 
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_facturacion: 'distribucion_facturacion',
                        id_contrato: id_contrato
                    })
                }
            );

            if (res.ok) {
                const respuesta = await res.json();
                const facturas = respuesta.facturas || [];

                renderGridF(facturas);
                document.getElementById('loadingFact').style.display = 'none';
            }
        } catch (err) {
            console.error('Error al cargar facturacion TAB5:', err);
            document.getElementById('loadingFact').textContent = 'Error loading';
        }
    }   

    async function loadAndRenderServices() {
        try {
            const res = await fetch(ruta_contratos_ajax,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_contratos: 'distribucion_servicios',
                        id_contrato: ID_CONTRATO
                    })
                }
            );
            if (res.ok) {
                const respuesta = await res.json();
                const services = respuesta.services || [];
                const all_status = respuesta.all_status || [];

                renderGrid(services, all_status);
                document.getElementById('loading').style.display = 'none';
            }
        } catch (err) {
            console.error('Error al cargar servicios:', err);
            document.getElementById('loading').textContent = 'Error loading';
        }
    }

    // Función principal para renderizar facturas
    function renderGridF(facturas) {
        facturasGlobales = facturas;
        const contenedor = document.getElementById('facturas-lista');
        contenedor.innerHTML = '';

        if (facturas.length === 0) {
            contenedor.innerHTML = '<p style="text-align: center; color: #7f8c8d;">No invoices are available.</p>';
            return;
        }

        // Ordenar por fecha de vencimiento (más recientes primero)
        const facturasOrdenadas = [...facturas].sort((a, b) => 
            new Date(b.fecha_vencimiento) - new Date(a.fecha_vencimiento)
        );

        facturasOrdenadas.forEach(factura => {
            const item = document.createElement('div');
            item.className = 'factura-item';
            item.dataset.idFactura = factura.id_factura;

            // Formatear fechas
            const periodo = `${formatDate(factura.periodo_inicio)} - ${formatDate(factura.periodo_fin)}`;
            
            item.innerHTML = `
                <div class="grid_fact">
                    <div class="grid_fact_01">
                        <div class="factura-id">
                            <span class="numero-factura">Invoice # ${factura.id_factura}</span>
                        </div>
                        <div class="factura-periodo">${periodo}</div>
                        <div class="factura-monto">$${parseFloat(factura.monto_total).toFixed(2)}</div>
                        <div class="factura-estado estado-${factura.estado}">${factura.estado}</div>
                    </div>
                    <div class="grid_fact_02">
                        <div>${factura.concepto}</div>
                    </div>
                    <div class="grid_fact_03">
                        <div>${factura.asiento ? `# Journal Entry ${factura.asiento}` : '—' }</div>
                    </div>
                </div>
            `;

            // Evento de clic
            item.addEventListener('click', () => {
                // Remover clase selected de todos
                document.querySelectorAll('.factura-item').forEach(el => {
                    el.classList.remove('selected');
                });
                // Añadir a este
                item.classList.add('selected');
                // Mostrar detalles
                mostrarDetallesFactura(factura);
            });

            contenedor.appendChild(item);
        });

        // Seleccionar la primera factura automáticamente
        if (facturasOrdenadas.length > 0) {
            const primerItem = contenedor.querySelector('.factura-item');
            if (primerItem) {
                primerItem.classList.add('selected');
                mostrarDetallesFactura(facturasOrdenadas[0]);
            }
        }
    }

    // Función para mostrar detalles
    function mostrarDetallesFactura(factura) {
        const contenedor = document.getElementById('detalles-contenedor');
        const wrapper = document.getElementById('tabla-detalles-wrapper');
        const cuerpo = document.getElementById('cuerpo-detalles');
        const mensaje = contenedor.querySelector('.mensaje-inicial');

        mensaje.style.display = 'none';
        wrapper.style.display = 'block';
        cuerpo.innerHTML = '';

        if (!factura.detalle_f || factura.detalle_f.length === 0) {
            cuerpo.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">No details are available.</td></tr>';
            return;
        }

        factura.detalle_f.forEach(detalle => {
            const fila = document.createElement('tr');
            fila.innerHTML = `
                <td style="text-align: center;">
                    ${detalle.id_servicio 
                        ? `<span class="service-number-link" 
                                onclick="viewServiceDetail('${detalle.id_servicio}')" 
                                style="font-size: 3.5em; font-weight: bold; cursor: pointer; color: #2c3e50; text-decoration: underline;">
                                ${detalle.id_servicio}
                            </span>`
                        : '—'
                    }
                </td>
                <td>${detalle.concepto}</td>
                <td>$${parseFloat(detalle.subtotal).toFixed(2)}</td>
            `;
            cuerpo.appendChild(fila);
        });
    }

    // Función auxiliar para formatear fechas
    function formatDate(dateString) {
        if (!dateString || dateString === '0000-00-00') return '—';
        
        // Dividir la cadena "YYYY-MM-DD"
        const [year, month, day] = dateString.split('-').map(Number);
        
        // Crear fecha en la zona local (no UTC)
        const date = new Date(year, month - 1, day); // Meses en JS son 0-11
        
        // Formatear en inglés (mm/dd/yyyy)
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    }

    function renderGrid(services, all_status) {
        // Filtrar servicios: excluir los posteriores a fecha_cancelacion
        const filteredServices = services.filter(s => {
            if (!s.fecha_cancelacion) return true; // no está cancelado → incluir
            const serviceDate = new Date(s.service_date);
            const cancelDate = new Date(s.fecha_cancelacion);
            return serviceDate <= cancelDate; // solo incluir si es <= fecha_cancelacion
        });

        // Agrupar por mes (clave: YYYY-MM) 
        const grouped = {};

        filteredServices.forEach(s => {
            const d = new Date(s.service_date);
            const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            if (!grouped[key]) grouped[key] = [];
            grouped[key].push(s);
        });

        const tbody = document.querySelector('#servicesGrid tbody');
        tbody.innerHTML = '';

        Object.keys(grouped)
            .sort()
            .forEach(monthKey => {
                const [year, month] = monthKey.split('-').map(Number);
                const firstDay = new Date(year, month - 1, 1);
                const monthName = firstDay.toLocaleString('en-US', { 
                    month: 'long', 
                    year: 'numeric' 
                });

                const row = document.createElement('tr');
                const th = document.createElement('td');
                th.textContent = monthName;
                th.style.backgroundColor = '#f9f9f9';
                th.style.fontWeight = 'bold';
                th.style.textAlign = 'center';
                th.style.fontSize = '1.5em';
                row.appendChild(th);

                // Crear 5 celdas vacías
                const cells = Array(5).fill().map(() => {
                    const td = document.createElement('td');
                    td.classList.add('service-cell', 'empty');
                    return td;
                });

                // Asignar servicios a semanas
                grouped[monthKey].forEach(s => {
                    const weekIndex = getWeekIndexInMonth(new Date(s.service_date), new Date(year, month - 1, 1));
                    if (weekIndex < 5) {
                        cells[weekIndex].classList.remove('empty');
                        cells[weekIndex].innerHTML = buildServiceCell(s, all_status);
                    }
                });

                cells.forEach(cell => row.appendChild(cell));
                tbody.appendChild(row);
            });
    }

    function getWeekIndexInMonth(date, firstDayOfMonth) {
        const startOfWeek1 = new Date(firstDayOfMonth);
        startOfWeek1.setDate(firstDayOfMonth.getDate() - firstDayOfMonth.getDay()); // domingo inicio
        const diffDays = Math.floor((date - startOfWeek1) / (24 * 60 * 60 * 1000));
        return Math.floor(diffDays / 7);
    }

    function buildServiceCell(service, all_status) {
        // Buscar el objeto de estado correspondiente
        const statusInfo = all_status.find(st => st.id_status == service.id_status) || 
                        { status: 'Unknown', color: '#95a5a6' };

        const isPast = service.service_date < TODAY;
        const isEditable = !isPast && (service.id_status == 37); // asumiendo 37 = Activo

        const dateClass = isPast ? 'past' : 'editable';
        const badgeClass = isPast ? 'service-number-badge past' : 'service-number-badge';

        // --- Parsear fecha ---
        const parts = service.service_date.split('-');
        const dateObj = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' });

        let html = `
            <div class="elemnto_hor">
                <span class="${badgeClass}">${service.num_servicio}</span>
                <span class="service-date ${dateClass}" data-id="${service.id}">${service.service_date} (${dayName})</span>
            </div>
        `;

        if (service.is_done || [38, 39].includes(service.id_status)) {
            html += `<div class="elemnto_hor_for">`;
            if (service.service_number) {
                html += `<span class="service-number" onclick="viewServiceDetail('${service.service_number}')">${service.service_number}</span>`;
            }
            // ✅ Mostrar estado con color de fondo
            html += `<span class="service-status-badge" style="background-color: ${statusInfo.color}; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.85em; margin-left: 6px;">
                        ${statusInfo.status}
                    </span>`;
            html += `</div>`;
        } else {
            if (isEditable) {
                html += `<div class="elemnto_hor_for">
                            <button class="mark-done" onclick="markAsDone(${service.id})">Reschedule</button>
                        </div>`;
            } else {
                html += `<div class="elemnto_hor_for">`;
                if (service.service_number) {
                    html += `<span class="service-number" onclick="viewServiceDetail('${service.service_number}')">${service.service_number}</span>`;
                }
                // ✅ Mostrar estado incluso si no está hecho
                html += `<span class="service-status-badge" style="background-color: ${statusInfo.color}; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.85em; margin-left: 6px;">
                            ${statusInfo.status}
                        </span>`;
                html += `</div>`;
            }
        }

        return `<div>${html}</div>`;
    }

    async function viewServiceDetail(serviceNumber) {
        suiteLoading('show');
        const modal = document.getElementById('serviceModal');
        const modalBody = document.getElementById('modalBody');
        const ruta = ruta_servicios_ajax;
        modalBody.innerHTML = '<p>Loading service details...</p>';
        modal.style.display = 'flex';

        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_servicios: 'datos_servicio',
                        id_servicio: serviceNumber
                    })
                }
            );
            if (res.ok) modalBody.innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Servicio:", err); }
        suiteLoading('hide');
    }

    function closeServiceModal() {
        document.getElementById('serviceModal').style.display = 'none';
    }

    // Cerrar modal al hacer clic fuera del contenido
    document.getElementById('serviceModal').addEventListener('click', (e) => {
        if (e.target.id === 'serviceModal') {
            closeServiceModal();
        }
    });

    function markAsDone(serviceId) {
        if (!confirm('¿Marcar este servicio como completado?')) return;

        fetch(`${ruta_contratos_ajax}/${serviceId}/complete`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content') })
        })
            .then(res => res.json())
            .then(() => loadAndRenderServices())
            .catch(err => alert('Error al marcar como completado'));
    }

    async function cargar_id_frecuencia_servicio(id_frecuencia_servicio, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_frecuencia_servicio: 'crear_select',
                        id_frecuencia_servicio: id_frecuencia_servicio
                    })
                }
            );
            if (res.ok) document.getElementById('id_frecuencia_servicio').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Frecuencia de Servicio:", err); }
    }

    async function cargar_id_status(id_status, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_status: 'crear_select',
                        tabla: 'contratos',
                        id_status: id_status
                    })
                }
            );
            if (res.ok) document.getElementById('id_status').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Status:", err); }
    }

    async function cargar_id_area(id_area, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_areas: 'crear_select',
                        id_area: id_area
                    })
                }
            );
            if (res.ok) document.getElementById('id_area').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Area:", err); }
    }

    async function cargar_id_ruta(id_ruta, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_rutas: 'crear_select',
                        id_ruta: id_ruta
                    })
                }
            );
            if (res.ok) document.getElementById('id_ruta').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Ruta:", err); }
    }

    async function cargar_id_frecuencia_pago(id_frecuencia_pago, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_frecuencia_pago: 'crear_select',
                        id_frecuencia_pago: id_frecuencia_pago
                    })
                }
            );
            if (res.ok) document.getElementById('id_frecuencia_pago').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Fecuencia de Pago:", err); }
    }

    async function cargar_id_cliente(id_cliente, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_clientes: 'crear_select',
                        id_cliente: id_cliente
                    })
                }
            );
            if (res.ok) document.getElementById('id_cliente').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Clientes:", err); }
    }

    async function cargar_id_direccion(id_direccion, id_cliente, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_direcciones: 'crear_select',
                        id_cliente: id_cliente,
                        id_direccion: id_direccion
                    })
                }
            );
            if (res.ok) document.getElementById('id_direccion').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Clientes:", err); }
    }

    async function cargar_id_dia_semana(id_dia_semana, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_dia_semana: 'crear_select',
                        id_dia_semana: id_dia_semana
                    })
                }
            );
            if (res.ok) document.getElementById('id_dia_semana').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Clientes:", err); }
    }

    async function cargar_secondary_day(secondary_day, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_dia_semana: 'crear_select_two_d',
                        secondary_day: secondary_day
                    })
                }
            );
            if (res.ok) document.getElementById('secondary_day').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Work day:", err); }
    }

    // === ENVÍO DEL FORMULARIO PRINCIPAL ===
    document.addEventListener('DOMContentLoaded', async () => {
        suiteLoading('show');

        try {
            const id_contrato = "<?php echo $row1['id_contrato'];?>";
            const res = await fetch(ruta_facturacion_ajax, 
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_facturacion: 'generar_facturacion',
                        id_contrato: id_contrato
                    })
                }
            );

            if (res.ok) {
                const respuesta = await res.json();
                if (respuesta.success !== true) {
                    await suiteAlertError("Error", respuesta.mess);
                }
            }
        } catch (err) { 
            console.error("Error al cargar facturacion:", err); 
        }

        openTab(null, 'tab1');

        const timeInput = document.getElementById('tiempo_servicio');
        if (!timeInput) return;

        timeInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Elimina todo lo que no sea número

            // Limitar a 6 dígitos (HHmmss)
            if (value.length > 6) {
                value = value.slice(0, 6);
            }

            // Formatear como HH:mm:ss
            let formatted = '';
            if (value.length >= 1) formatted += value.slice(0, 2); // HH
            if (value.length >= 3) formatted += ':' + value.slice(2, 4); // :mm
            if (value.length >= 5) formatted += ':' + value.slice(4, 6); // :ss

            e.target.value = formatted;
        });

        // Validación al enviar el formulario (opcional)
        const form = timeInput.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const val = timeInput.value;
                const regex = /^([0-1][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/;
                if (val && !regex.test(val)) {
                    alert('Please enter a valid time in HH:mm:ss format (e.g., 01:30:45)');
                    e.preventDefault();
                    timeInput.focus();
                }
            });
        }        

        let id_frecuencia_servicio = "<?php echo $id_frecuencia_servicio; ?>"
        await cargar_id_frecuencia_servicio(id_frecuencia_servicio, ruta_frecuencia_servicio_ajax);

        let id_status = "<?php echo $id_status; ?>"
        await cargar_id_status(id_status, ruta_status_ajax);

        let id_area = "<?php echo $id_area; ?>"
        await cargar_id_area(id_area, ruta_areas_ajax);

        let id_ruta = "<?php echo $id_ruta; ?>"
        await cargar_id_ruta(id_ruta, ruta_ruta_ajax);

        let id_frecuencia_pago = "<?php echo $id_frecuencia_pago; ?>"
        await cargar_id_frecuencia_pago(id_frecuencia_pago, ruta_frec_pago_ajax);

        let id_cliente = "<?php echo $id_cliente; ?>"
        await cargar_id_cliente(id_cliente, ruta_clientes_ajax);

        let id_direccion = "<?php echo $id_direccion; ?>"
        await cargar_id_direccion(id_direccion, id_cliente, ruta_direcciones_ajax);

        let id_dia_semana = "<?php echo $id_dia_semana; ?>"
        await cargar_id_dia_semana(id_dia_semana, ruta_dia_semana_ajax);

        let secondary_day = "<?php echo $secondary_day; ?>"
        await cargar_secondary_day(secondary_day, ruta_dia_semana_ajax);

        loadAndRenderServices();

        // === LIMPIAR ERRORES AL ESCRIBIR ===
        document.querySelectorAll('input[required], select[required]').forEach(field => {
            field.addEventListener('input', () => {
                field.classList.remove('error');
                const label = document.querySelector(`label[for="${field.id}"]`);
                if (label) label.classList.remove('error');
            });
        });

        // === MANEJO DEL BOTÓN PRINCIPAL ===
        document.getElementById("submitBtn")?.addEventListener("click", async function (event) {
            event.preventDefault();
            if (!validateTabs()) return;

            const confirmado = await suiteConfirm(
                arreglo_frases_g[0],
                arreglo_frases_g[1],
                { 
                    aceptar: arreglo_frases_g[2], 
                    cancelar: arreglo_frases_g[3] 
                }
            );
            if (!confirmado) return;

            const form = document.getElementById("update_contrato");
            const data = new FormData(form);

            try {
                const res = await fetch(form.action, 
                    { 
                        method: form.method, 
                        body: data
                    }
                );
                const text = await res.text();
                const json = JSON.parse(text);
                if (json.tipo === 'success') {
                    // ✅ Reiniciar el estado de cambios tras guardar con éxito

                    await suiteAlertSuccess("Success", json.texto);
                    window.location.href = ruta_retorno;
                } else {
                    await suiteAlertError("Error", json.texto);
                }
            } catch (err) {
                console.error("Error en submit:", err);
                await suiteAlertError("Error", "Submission failed: " + err.message);
            }
        });

        loadAndRenderServices();
        loadAndRenderFacturacion();

        suiteLoading('hide');
    });
</script>