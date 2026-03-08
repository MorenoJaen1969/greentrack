<?php
// app/views/content/contratosNew-view.php

$proceso_actual = "contratosNew";
$ruta_retorno = RUTA_APP . "/contratos";
$pagina_retorno = 1;

$modo_edicion = true;
$id_status = 18;
$id_cliente = 0;

$puede_editar = '';
$sit_edicion = "required";

$ruta_clientes_ajax = RUTA_APP . "/app/ajax/clientesAjax.php";
$ruta_status_ajax = RUTA_APP . "/app/ajax/statusAjax.php";
$ruta_direcciones_ajax = RUTA_APP . "/app/ajax/direccionesAjax.php";
$ruta_contratos_ajax = RUTA_APP . "/app/ajax/contratosAjax.php";
$ruta_dia_semana_ajax = RUTA_APP . "/app/ajax/dia_semanaAjax.php";
$ruta_areas_ajax = RUTA_APP . "/app/ajax/areasAjax.php";
$ruta_ruta_ajax = RUTA_APP . "/app/ajax/rutas_mapaAjax.php";
$ruta_frecuencia_servicio_ajax = RUTA_APP . "/app/ajax/frec_servicioAjax.php";
$ruta_frec_pago_ajax = RUTA_APP . "/app/ajax/frec_pagoAjax.php";
$ruta_contratos = RUTA_APP . "/contratos/";
$encabezadoNew = PROJECT_ROOT . "/app/views/inc/encabezadoNew.php";
$opcion = "contratosNew";

$cont_status = $clientes->contar_with_without();
$contar_with = $cont_status['NoContracts'] ?? 0;
$contar_without = $cont_status['WithContracts'] ?? 0;
$count_todos = $cont_status['All'] ?? 0;
$tipo_persona = 1;

$id_dia_semana = 2;
$secondary_day = 5;
$id_area = 1;
$id_ruta = 4;
$id_frecuencia_servicio = 1;
$id_frecuencia_pago = 1;

$fecha_ini_obj = new DateTime(); // Fecha hoy
$fecha_ini = $fecha_ini_obj->format('Y-m-d'); // Formato para el input

$fecha_fin_obj = (clone $fecha_ini_obj)->modify('+1 year');
$fecha_fin = $fecha_fin_obj->format('Y-m-d');

error_log("Fechas: " . $fecha_ini . ", " . $fecha_fin);

$clase_f = "fa-solid fa-filter";

$ruta_retorno = "/contratos";
?>

<main>
    <?php require_once $encabezadoNew; ?>

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
                        Clients according to Contracts
                    </h3>

                    <div class="radio-group">
                        <label class="radio-card active">
                            <input type="radio" id="filtro-NoContracts" name="filtro_estado" value="NoContracts" checked>
                            <span class="radio-indicator"></span>
                            <div class="radio-content">
                                <span class="radio-label">No Contracts</span>
                                <span class="radio-count" id="count-activos"><?php echo $contar_with; ?></span>
                            </div>
                            <div class="radio-glow"></div>
                        </label>

                        <label class="radio-card">
                            <input type="radio" id="filtro-WithContracts" name="filtro_estado" value="WithContracts">
                            <span class="radio-indicator"></span>
                            <div class="radio-content">
                                <span class="radio-label">With Contracts</span>
                                <span class="radio-count" id="count-inactivos"><?php echo $contar_without; ?></span>
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
            <div class="form-container">
                <!-- FORMULARIO PRINCIPAL -->
                <form class="FormularioAjax form-horizontal" action="<?php echo $ruta_contratos_ajax; ?>"
                    method="POST" id="ingreso_contrato" name="ingreso_contrato" enctype="multipart/form-data" autocomplete="off">

                    <input type="hidden" name="modulo_contratos" value="registrar_contrato">
                    <input type="hidden" id="id_tipo_persona" name="id_tipo_persona" value="">

                    <div class="tab-container">
                        <div class="tabs-gen">
                            <button type="button" class="tab-button tablink" data-tab="tab1" onclick="openTab(event, 'tab1')">
                                Contract Creation
                            </button>
                        </div>
                    </div>

                    <div id="tab1" class="tabcontent tab-link" style="display:none">
                        <div class="form-group-ct-inline">
                            <h3 style="margin-bottom: 0px">Customer Details</h3>
                            <h3 style="margin-left: auto; color: #6d1a72ff; font-weight: bold; width: 40vw; margin-bottom: 0px">
                                <div class="form-group-ct-inline">
                                    <div class="form-group-ct" style="margin-bottom: 0px">
                                        <div class="form-group-ct-inline">
                                            <label for="id_status" class="ancho_label1" style="width: 100%;">Status of the New Contract</label>
                                            <select class="form-control-co form-font" id="id_status" name="id_status" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                                <!-- Se llenará con JS -->
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </h3>
                        </div>

                        <div class="grid_header_int" style="grid-template-columns: 30% 70%; width: 100%; padding-left: 0px; margin-bottom: 10px; height: 56vh;">
                            <div class="grid_header_int01" style="border-radius: 10px;">
                                <div class="forma01" style="border-top-left-radius: 10px; border-top-right-radius: 10px;">
                                    <h2 style="font-size: 150%;">Customer</h2>
                                    <label style="width: 100%;" for="id_cliente1">Customer code:</label>
                                    <input class="input" type="text" id="id_cliente1"
                                        placeholder="Customer Coding"
                                        readonly>

                                    <div>
                                        <label for="id_cliente" class="ancho_label1">Customers:</label>
                                        <!-- Contenedor del select personalizado -->
                                        <div class="ser-select-foto" id="ser-select-customer">
                                            <!-- Campo visible (lo que ve el usuario) -->
                                            <div class="ser-select-trigger" tabindex="0">
                                                <span id="ser-id_cliente" class="ser-select-selected">Select a customer...</span>
                                                <span class="ser-select-arrow">▼</span>
                                            </div>
                                            <!-- Lista desplegable de opciones -->
                                            <ul class="ser-select-options">
                                                <!-- Las opciones se generan con JS -->
                                            </ul>
                                            <!-- Input oculto para enviar el valor en formularios -->
                                            <input type="hidden" name="id_cliente" id="ser-select-driver-value">
                                            
                                        </div>
                                    </div>
                                    <div class="form-column" id="phone1" style="display: none; grid-template-columns: 1fr 1fr; gap: 10px;">
                                        <div class="form-group-ct-inline" style="gap: 5px;">
                                            <div class="form-group-ct" style="padding-right: 0px; margin-bottom: 0px;">
                                                <label for="natural-tel1-display"><strong>Phone Primary</strong></label>
                                                <input class="campo-natural input telefono-mask"
                                                    type="tel"
                                                    id="natural-tel1-display"
                                                    placeholder="+1 (234) 567-8900"
                                                    autocomplete="off"
                                                    style="margin: auto; padding: 0px; text-align: center;"
                                                    readonly>
                                                <!-- Input hidden real que se envía al backend (solo números) -->
                                                <input type="hidden" name="natural_telefono1" id="natural-tel1" class="campo-natural">
                                            </div>
                                            <div class="form-group-ct" style="padding-right: 0px; margin-bottom: 0px;">
                                                <label for="natural-tel2-display"><strong>Phone Secondary</strong></label>
                                                <input class="campo-natural input telefono-mask"
                                                    type="tel"
                                                    id="natural-tel2-display"
                                                    placeholder="+1 (234) 567-8900"
                                                    autocomplete="off"
                                                    style="margin: auto; padding: 0px; text-align: center;"
                                                    readonly>
                                                <input type="hidden" name="natural_telefono2" id="natural-tel2" class="campo-natural">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-column" id="phone2" style="display: none; grid-template-columns: 1fr 1fr; gap: 10px;">
                                        <div class="form-group-ct-inline" style="gap: 5px;">
                                            <div class="form-group-ct" style="padding-right: 0px; margin-bottom: 0px;">
                                                <label for="juridica-tel1-display"><strong>Office Phone</strong></label>
                                                <input class="campo-juridica input telefono-mask"
                                                    type="tel"
                                                    id="juridica-tel1-display"
                                                    placeholder="+1 (234) 567-8900"
                                                    autocomplete="off"
                                                    style="margin: auto; padding: 0px; text-align: center;"
                                                    readonly>
                                                <input type="hidden" name="juridica_telefono1" id="juridica-tel1" class="campo-juridica">
                                            </div>
                                            <div class="form-group-ct" style="padding-right: 0px; margin-bottom: 0px;">
                                                <label for="juridica-tel2-display"><strong>Mobile / Direct</strong></label>
                                                <input class="campo-juridica input telefono-mask"
                                                    type="tel"
                                                    id="juridica-tel2-display"
                                                    placeholder="+1 (234) 567-8900"
                                                    autocomplete="off"
                                                    style="margin: auto; padding: 0px; text-align: center;"
                                                    readonly>
                                                <input type="hidden" name="juridica_telefono2" id="juridica-tel2" class="campo-juridica">
                                            </div>
                                        </div>
                                    </div>

                                    <label class="ancho_label1" for="email">Email:</label>
                                    <input class="input" type="email" id="email" name="email" value="" readonly>
                                </div>

                                <div class="forma01" style="border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
                                    <h2 style="font-size: 150%;">Related Address</h2>
                                    <label class="ancho_label1" style="width: 100%;" for="id_direccion1">Address code:</label>
                                    <input class="input" type="text" id="id_direccion1"
                                        value="" placeholder="Address Coding"
                                        readonly>

                                    <div class="form-group-ct-inline">
                                        <div class="form-group-ct" style="margin-bottom: 0px">
                                            <label for="id_direccion" class="ancho_label1">Address:</label>
                                            <select class="form-control form-font" id="id_direccion" name="id_direccion"
                                                <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                                <!-- Se llenará con JS -->
                                            </select>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="grid_header_int02" style="border-radius: 10px;">
                                <div class="grid_de3_int" style="width: 100%; padding-left: 0px; margin-bottom: 10px;">
                                    <div class="grid_de3_int01" style="width: 100%; padding-left: 0px; margin-bottom: 10px;">
                                        <h2 style="font-size: 150%; padding: 10px;">Contract Parameters</h2>
                                        <div style="width: 100%; padding-left: 10px; margin-bottom: 0px;">
                                            <div class="form-group-ct-inline">
                                                <div class="form-group-ct">
                                                    <div class="form-group-ct-inline">
                                                        <label class="ancho_label1" for="nom_contrato">Contract Name:</label>
                                                        <input class="input" type="text" id="nom_contrato" name="nom_contrato"
                                                            style="font-weight: 700;"
                                                            value=""
                                                            placeholder="Contract name">
                                                    </div>
                                                    <div class="form-group-ct-inline">
                                                        <div class="control-minutos">
                                                            <label class="ancho_label5" for="tiempo_servicio">Time required to perform the service:</label>
                                                            <div class="form-group-ct-inline">
                                                                <input
                                                                    type="number"
                                                                    id="tiempo_servicio"
                                                                    name="tiempo_servicio"
                                                                    value="45"
                                                                    min="0"
                                                                    max="240"
                                                                    step="1"
                                                                    style="text-align: center; font-size: 2rem; width: 5vw; margin-left: 1rem;"
                                                                    <?php echo !$modo_edicion ? 'disabled' : ''; ?>>
                                                                <span style="font-size: 2rem;">minutes</span>
                                                            </div>

                                                        </div>
                                                        <div style="width: 54%;">
                                                            <label class="ancho_label5" for="retraso_invierno">Use extended hours for Winter:</label>
                                                            <div class="switch-container">
                                                                <span class="switch-label off">OFF</span>
                                                                <input
                                                                    type="checkbox"
                                                                    id="retraso_invierno"
                                                                    name="retraso_invierno"
                                                                    class="switch-input"
                                                                    value="1"
                                                                    checked>
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

                                                    <div class="form-group-ct-inline">
                                                        <div class="form-group-ct-inline">
                                                            <div>
                                                                <label class="ancho_label1 widthField" for="num_semanas" style="width: 100% !important;">Number of services in the period:</label>
                                                                <div class="form-group-ct-inline">
                                                                    <input
                                                                        type="number" 
                                                                        id="num_semanas" 
                                                                        name="num_semanas" 
                                                                        value="45"
                                                                        min="0"
                                                                        max="52"
                                                                        step="1"
                                                                        style="text-align: center; font-size: 2rem; width: 6vw; margin-left: 1rem;"
                                                                        <?php echo !$modo_edicion ? 'disabled' : ''; ?>>
                                                                    <span style="font-size: 2rem;">Services</span>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label class="ancho_label5" for="costo">Payment Amount:</label>
                                                                <div class="form-group-ct-inline">
                                                                    <input
                                                                        type="number"
                                                                        id="costo"
                                                                        name="costo"
                                                                        value="0"
                                                                        min="0"
                                                                        max="2000"
                                                                        step="1"
                                                                        style="text-align: center; font-size: 2rem; width: 7vw; margin-left: 1rem;"
                                                                        <?php echo !$modo_edicion ? 'disabled' : ''; ?>>
                                                                    <span style="font-size: 2rem;">Dollars</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid_de3_int02">
                                        <div style="padding: 10px;">
                                            <!-- ÁREA DE FOTO PREVIEW -->
                                            <div id="foto-container" style="text-align: center; margin-bottom: 5px;">
                                                <div id="foto-preview" class="foto-cliente">
                                                    <span id="foto-placeholder" style="color: #999; font-size: 3em;">📷</span>
                                                    <img id="foto-img" class="foto-imagen">
                                                </div>

                                                <button type="button" id="btn-cambiar-foto" class="foto-boton">
                                                    <span id="texto-btn-foto">Add Photo</span>
                                                </button>

                                                <!-- Input file OCULTO fuera del form pero vinculado -->
                                                <input type="file" id="input-foto-file" name="foto" accept="image/*"
                                                    style="display: none;" form="ingreso_contrato">

                                                <input type="hidden" id="foto_nombre" name="contrato_foto" value="">

                                                <p style="margin-top: 10px; font-size: 0.85em; color: #666;">
                                                    Max 2MB (JPG, PNG)
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid_de3_int03">
                                        <div class="grid_geo" style="grid-template-columns: 40% 60%;">
                                            <div class="grid_geo_01" style="padding-left:10px">
                                                <div class="form-group-ct">
                                                    <label for="id_dia_semana" class="ancho_label5">Primary Workday:</label>
                                                    <select class="form-control-co form-especial" id="id_dia_semana"
                                                        name="id_dia_semana" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                                        <!-- Se llenará con JS -->
                                                    </select>
                                                </div>

                                                <div class="form-group-ct">
                                                    <label for="secondary_day" class="ancho_label5">Secondary Workday:</label>
                                                    <select class="form-control-co" style="font-size: 2rem" id="secondary_day"
                                                        name="secondary_day" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                                        <!-- Se llenará con JS -->
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="grid_geo_02">
                                                <div class="form-group-ct-inline">
                                                    <div class="form-group-ct" style="margin-bottom: 0px">
                                                        <label for="id_ruta" class="ancho_label1" style="width: 100%;">Associated Route:</label>
                                                        <select class="form-control form-font" id="id_ruta" name="id_ruta" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                                            <!-- Se llenará con JS -->
                                                        </select>
                                                    </div>
                                                </div>

                                                <div>
                                                    <div class="form-group-ct-inline">
                                                        <div class="form-group-ct">
                                                            <label class="ancho_label1" style="width: 100%" for="fecha_ini">Start Date:</label>
                                                            <input type="date" style="text-align: center; font-size: 2rem;" id="fecha_ini" name="fecha_ini" value="<?php echo $fecha_ini; ?>">
                                                        </div>
                                                        <div class="form-group-ct">
                                                            <label class="ancho_label1" style="width: 100%" for="fecha_fin">End Date:</label>
                                                            <input type="date" style="text-align: center; font-size: 2rem;" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="form-group-ct-inline">
                                                        <div class="form-group-ct">
                                                            <div class="form-group-ct" style="margin-bottom: 0px">
                                                                <label for="id_frecuencia_servicio" class="ancho_label1 widthField">Service Frequency:</label>
                                                                <select class="form-control-co form-font" id="id_frecuencia_servicio" name="id_frecuencia_servicio" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                                                    <!-- Se llenará con JS -->
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div class="form-group-ct">
                                                            <div class="form-group-ct" style="margin-bottom: 0px">
                                                                <label for="id_frecuencia_pago" class="ancho_label1 widthField">Payment Frequency:</label>
                                                                <select class="form-control-co form-font" id="id_frecuencia_pago"
                                                                    name="id_frecuencia_pago" <?php echo !$modo_edicion ? 'disabled' : ''; ?>
                                                                    required>
                                                                    <!-- Se llenará con JS -->
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit" id="submitBtn" <?php echo $puede_editar; ?>>
                        Register Contract
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
    // ============================================
    // FLUJO CREACIÓN DE contrato - OPCIÓN B
    // ============================================

    let arreglo_frases_g = [];
    var p01 = "Are you sure";
    var p02 = "Are you confirming that you want to save the current record?";
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

    // ==========================================
    // VARIABLE DE MÓDULO (para evitar globales implícitos)
    // ==========================================
    let _opcionesSelect = [];
    let _selectAPI = null; // Para guardar referencia del API retornado

    const platRutaRetorno = "<?php echo $ruta_retorno . "/" . $pagina_retorno; ?>";
    const ruta_contratos_ajax = "<?php echo $ruta_contratos_ajax; ?>";
    const ruta_clientes_ajax = "<?php echo $ruta_clientes_ajax; ?>";
    const ruta_status_ajax = "<?php echo $ruta_status_ajax; ?>";
    const ruta_direcciones_ajax = "<?php echo $ruta_direcciones_ajax; ?>";
    const ruta_dia_semana_ajax = "<?php echo $ruta_dia_semana_ajax; ?>";
    const ruta_areas_ajax = "<?php echo $ruta_areas_ajax; ?>";
    const ruta_ruta_ajax = "<?php echo $ruta_ruta_ajax; ?>";
    const ruta_frecuencia_servicio_ajax = "<?php echo $ruta_frecuencia_servicio_ajax; ?>";    
    const ruta_frec_pago_ajax = "<?php echo $ruta_frec_pago_ajax; ?>";

    let tipoPersonaSeleccionado = null;

    // Referencias DOM
    const submitBtn = document.getElementById('submitBtn');
    const retorno = document.getElementById('retorno');

    // Foto elementos
    const btnCambiarFoto = document.getElementById('btn-cambiar-foto');
    const inputFotoFile = document.getElementById('input-foto-file');
    const fotoPreview = document.getElementById('foto-preview');
    const fotoImg = document.getElementById('foto-img');
    const fotoPlaceholder = document.getElementById('foto-placeholder');
    const textoBtnFoto = document.getElementById('texto-btn-foto');
    const fotoNombre = document.getElementById('foto_nombre');

    //Referencias DOM
    const telefono1 = document.getElementById('phone1');
    const telefono2 = document.getElementById('phone2');
    const id_tipo_persona = document.getElementById('id_tipo_persona');

    // ============================================
    // SISTEMA DE FILTROS DE ESTADO
    // ============================================
    let filtroEstadoActual = 'NoContracts';
    let filtro_estado = 'NoContracts'; // ← TU nombre, no 'filtro-clientes'    

    const CAMPOS_CRITICOS = {
        field_contract: ['id_cliente', 'id_direccion', 'id_area', 'nombre', 'fecha_ini', 'fecha_fin', 'id_status', 'id_frecuencia_servicio', 'id_dia_semana', 'secondary_day', 'num_semanas', 'id_ruta', 'tiempo_servicio', 'retraso_invierno', 'renovar', 'costo', 'id_frecuencia_pago']
    };
    //'id_truck', 'mensual_calendario'

    // Mapeo de nombres de frontend a backend
    const MAPEO_CAMPOS = {
        id_cliente: 'id_cliente',
        id_direccion: 'id_direccion',
        id_area: 'id_area',
        nombre: 'nombre',
        fecha_ini: 'fecha_ini',
        fecha_fin: 'fecha_fin',
        id_status: 'id_status',
        id_frecuencia_servicio: 'id_frecuencia_servicio',
        id_dia_semana: 'id_dia_semana',
        secondary_day: 'secondary_day',
        num_semanas: 'num_semanas',
        id_ruta: 'id_ruta',
        tiempo_servicio: 'tiempo_servicio',
        costo: 'costo', 
        id_frecuencia_pago: 'id_frecuencia_pago', 
        retraso_invierno: 'retraso_invierno',
        renovar: 'renovar'
    };

    // === ABRIR PESTAÑAS ===
    function openTab(evt, tabName) {
        document.querySelectorAll(".tabcontent").forEach(el => el.style.display = "none");
        document.querySelectorAll(".tablink").forEach(btn => btn.classList.remove("active"));

        const tab = document.getElementById(tabName);

        tab.style.display = "block";
        if (evt) evt.currentTarget.classList.add("active");
    }

    // === VALIDACIÓN DE FORMULARIO ===
    function validateTabs() {
        if (!validateTab(1)) return showTab(1) && false;
        return true;
    }

    function showTab(index) {
        ["tab1"].forEach((id, i) => {
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


    // ========== GESTIÓN DE FOTO ==========
    btnCambiarFoto.addEventListener('click', () => {
        inputFotoFile.click();
    });

    inputFotoFile.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;

        // Validar tamaño (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('File too large. Maximum 2MB allowed.');
            inputFotoFile.value = '';
            return;
        }

        // Validar tipo
        if (!file.type.startsWith('image/')) {
            alert('Only image files allowed.');
            inputFotoFile.value = '';
            return;
        }

        // Mostrar preview
        const reader = new FileReader();
        reader.onload = (e) => {
            fotoImg.src = e.target.result;
            fotoImg.style.display = 'block';
            fotoPlaceholder.style.display = 'none';
            textoBtnFoto.textContent = 'Change Photo';

            // Generar nombre único para el campo hidden (tu backend usará $_FILES['foto'])
            const extension = file.name.split('.').pop();
            const nombreUnico = `contrato_${Date.now()}.${extension}`;
            fotoNombre.value = nombreUnico;
        };
        reader.readAsDataURL(file);
    });

    // ========== BOTON DE RETORNO ==========
    retorno.addEventListener('click', () => {
        window.location.href = platRutaRetorno;
    });

    // ========== CARGA INICIAL ==========
    /**
     * Inicializa un select personalizado con fotos
     * @param {Function} onChange - Callback cuando se selecciona una opción
     */
    async function serInicializarSelectFoto(ruta, onChange = null) {
        // Si ya está inicializado, solo recargar datos (NO reinicializar)
        if (_selectAPI && typeof _selectAPI.refresh === 'function') {
            return await _selectAPI.refresh(ruta);
        }

        try {
            // 1. Fetch inicial de datos
            const res02 = await fetch(ruta, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_clientes: 'solo_datos',
                    filtro: filtroEstadoActual
                })
            });
            const result02 = await res02.json();

            if (!result02.success || !result02.data) {
                console.error('❌ Error cargando datos:', result02);
                return null;
            }

            _opcionesSelect = result02.data; // ✅ Declaración correcta

            // 2. Referencias al DOM (una sola vez)
            const container = document.getElementById('ser-select-customer');
            if (!container) {
                console.error(`❌ Contenedor 'ser-select-customer' no encontrado`);
                return null;
            }

            const trigger = container.querySelector('.ser-select-trigger');
            const selectedSpan = container.querySelector('.ser-select-selected');
            const optionsList = container.querySelector('.ser-select-options');
            const hiddenInput = container.querySelector('input[type="hidden"]');


            // 3. Función para renderizar opciones (REUTILIZABLE)
            function renderOpciones(lista) {
                optionsList.innerHTML = '';

                if (!lista || lista.length === 0) {
                    optionsList.innerHTML = '<li class="ser-select-option disabled">Sin resultados</li>';
                    return;
                }

                lista.forEach(opcion => {
                    let foto_def = null;
                    let nom_cliente = null;

                    // Tu lógica de fotos y nombres (intacta ✅)
                    if (!opcion['contrato_foto'] || opcion['contrato_foto'].trim().length === 0) {
                        switch (opcion['sexo']) {
                            case 'Male':
                                foto_def = '/app/views/fotos/responsable.png';
                                nom_cliente = opcion['abreviatura'] + ' ' + opcion['cliente'];
                                break;
                            case 'Female':
                                foto_def = '/app/views/fotos/responsable-mujer.png';
                                nom_cliente = opcion['abreviatura'] + ' ' + opcion['cliente'];
                                break;
                            default:
                                foto_def = '/app/views/fotos/compania.png';
                                nom_cliente = opcion['cliente'];
                                break;
                        }
                    } else {
                        foto_def = '/app/views/img/uploads/fotos/' + opcion['contrato_foto'];
                        nom_cliente = (opcion['tipo_persona'] === 'Natural Person') ?
                            opcion['abreviatura'] + ' ' + opcion['cliente'] :
                            opcion['cliente'];
                    }

                    // Tu lógica de "With Previous Contracts" (corregida ✅)
                    if (filtroEstadoActual === 'NoContracts') {
                        if (opcion['total_contratos'] > 0) {
                            nom_cliente += "<br>With Previous Contracts (" + opcion['total_contratos'] + ")";
                        } else {
                            nom_cliente += "<br>New Client";
                        }
                    }

                    // Crear elemento li
                    const li = document.createElement('li');
                    li.className = 'ser-select-option';
                    li.dataset.id = opcion['id_cliente'];
                    li.innerHTML = `
                            <img src="${foto_def}" alt="${opcion['cliente']}" onerror="this.src='https://via.placeholder.com/32?text=👤'">
                            <div class="ser-info">
                                <span class="ser-nombre">${nom_cliente}</span>
                            </div>
                        `;
                    optionsList.appendChild(li);
                });
            }

            // 4. EVENTOS (se adjuntan UNA SOLA VEZ)

            // Click en trigger: abrir/cerrar
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                container.classList.toggle('open');
            });

            // Click fuera: cerrar
            document.addEventListener('click', (e) => {
                if (!container.contains(e.target)) {
                    container.classList.remove('open');
                }
            });

            // Teclado: accesibilidad
            trigger.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    container.classList.toggle('open');
                }
                if (e.key === 'Escape') {
                    container.classList.remove('open');
                }
            });

            // ✅ EVENT DELEGATION para opciones (evita listeners por cada li)
            optionsList.addEventListener('click', (e) => {
                const li = e.target.closest('.ser-select-option');
                if (!li || li.classList.contains('disabled')) return;

                const id_cliente = li.dataset.id;
                const opcion = _opcionesSelect.find(o => o.id_cliente == id_cliente);
                if (!opcion) return;

                // Remover selección previa visual
                optionsList.querySelectorAll('.ser-select-option').forEach(el =>
                    el.classList.remove('selected')
                );
                li.classList.add('selected');

                // Actualizar trigger (reconstruir nom_cliente para el trigger)
                let foto_def = opcion['contrato_foto'] ?
                    '/app/views/img/uploads/fotos/' + opcion['contrato_foto'] :
                    (opcion['sexo'] === 'Male' ? '/app/views/fotos/responsable.png' :
                        opcion['sexo'] === 'Female' ? '/app/views/fotos/responsable-mujer.png' :
                        '/app/views/fotos/compania.png');

                let nom_trigger = (opcion['tipo_persona'] === 'Natural Person') ?
                    opcion['abreviatura'] + ' ' + opcion['cliente'] :
                    opcion['cliente'];

                if (filtroEstadoActual === 'NoContracts' && opcion['total_contratos'] > 0) {
                    nom_trigger += "<br>With Previous Contracts (" + opcion['total_contratos'] + ")";
                }

                selectedSpan.innerHTML = `<img src="${foto_def}">${nom_trigger}`;

                if (hiddenInput) hiddenInput.value = id_cliente;
                container.classList.remove('open');

                if (typeof onChange === 'function') onChange(opcion);
            });

            // 5. Render inicial
            renderOpciones(_opcionesSelect);

            // 6. API PÚBLICA (con refresh que recarga datos, no solo re-renderiza)
            _selectAPI = {
                refresh: async function(nuevaRuta, nuevoFiltro = null) {
                    try {
                        const res = await fetch(nuevaRuta, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                modulo_clientes: 'solo_datos',
                                filtro: nuevoFiltro || filtroEstadoActual
                            })
                        });
                        const result = await res.json();
                        if (result.success && result.data) {
                            _opcionesSelect = result.data;
                            renderOpciones(_opcionesSelect);
                            return true;
                        }
                        return false;
                    } catch (error) {
                        console.error('❌ Error en refresh:', error);
                        return false;
                    }
                },
                setSelected: function(id_cliente) {
                    const li = optionsList.querySelector(`.ser-select-option[data-id="${id_cliente}"]`);
                    if (li) li.click();
                },
                getValue: function() {
                    return hiddenInput?.value || null;
                },
                reset: function() {
                    selectedSpan.textContent = 'Seleccionar...';
                    if (hiddenInput) hiddenInput.value = '';
                    optionsList.querySelectorAll('.ser-select-option').forEach(el =>
                        el.classList.remove('selected')
                    );
                }
            };

            return _selectAPI;

        } catch (error) {
            console.error('❌ Error en serInicializarSelectFoto:', error);
            return null;
        }
    }

    async function cargar_id_status(id_status, ruta) {
        try {
            const res = await fetch(ruta, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_status: 'crear_select',
                    tabla: 'contratos',
                    id_status: id_status
                })
            });
            if (res.ok) {
                document.getElementById('id_status').innerHTML = await res.text();
            }
        } catch (err) {
            console.error("Error al cargar Status:", err);
        }
    }

    async function cargar_id_dia_semana(id_dia_semana, ruta) {
        try {
            const res = await fetch(ruta, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_dia_semana: 'crear_select',
                    id_dia_semana: id_dia_semana
                })
            });
            if (res.ok) document.getElementById('id_dia_semana').innerHTML = await res.text();
        } catch (err) {
            console.error("Error al cargar Clientes:", err);
        }
    }

    async function cargar_secondary_day(secondary_day, ruta) {
        try {
            const res = await fetch(ruta, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_dia_semana: 'crear_select_two_d',
                    secondary_day: secondary_day
                })
            });
            if (res.ok) document.getElementById('secondary_day').innerHTML = await res.text();
        } catch (err) {
            console.error("Error al cargar Work day:", err);
        }
    }

    async function cargar_id_area(id_area, ruta) {
        try {
            const res = await fetch(ruta, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_areas: 'crear_select',
                    id_area: id_area
                })
            });
            if (res.ok) document.getElementById('id_area').innerHTML = await res.text();
        } catch (err) {
            console.error("Error al cargar Area:", err);
        }
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

    // ============================================
    // UTILIDADES VISUALES
    // ============================================

    function marcarError(elemento, nombreCampo) {
        if (!elemento) return;

        // Agregar clase de error
        elemento.classList.add('input-error');
        elemento.style.border = '2px solid #e74c3c';
        elemento.style.backgroundColor = '#fdf2f2';

        // Agregar atributo para identificación
        elemento.dataset.errorValidacion = 'true';
    }

    function limpiarErroresVisuales() {
        document.querySelectorAll('.input-error, [data-error-validacion]').forEach(el => {
            el.classList.remove('input-error');
            el.style.border = '';
            el.style.backgroundColor = '';
            delete el.dataset.errorValidacion;
        });
    }

    function enfocarCampo(elemento) {
        if (typeof elemento === 'string') {
            elemento = document.getElementById(elemento);
        }
        if (elemento && elemento.focus) {
            elemento.focus();
            elemento.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
    }

    // ============================================
    // FUNCIÓN AUXILIAR PARA RECARGAR DATOS
    // ============================================

    async function cargarDatosIniciales() {
        let id_status = "<?php echo $id_status; ?>";
        let id_cliente = "<?php echo $id_cliente; ?>";
        let id_dia_semana = "<?php echo $id_dia_semana; ?>"
        let secondary_day = "<?php echo $secondary_day; ?>"
        let id_area = "<?php echo $id_area; ?>"
        let id_ruta = "<?php echo $id_ruta; ?>"
        let id_frecuencia_servicio = "<?php echo $id_frecuencia_servicio; ?>"
        let id_frecuencia_pago = "<?php echo $id_frecuencia_pago; ?>"

        await serInicializarSelectFoto(ruta_clientes_ajax)
        await cargar_id_status(id_status, ruta_status_ajax);
        await cargar_id_dia_semana(id_dia_semana, ruta_dia_semana_ajax);
        await cargar_secondary_day(secondary_day, ruta_dia_semana_ajax);
        await cargar_id_area(id_area, ruta_areas_ajax);
        await cargar_id_ruta(id_ruta, ruta_ruta_ajax);
        await cargar_id_frecuencia_servicio(id_frecuencia_servicio, ruta_frecuencia_servicio_ajax);
        await cargar_id_frecuencia_pago(id_frecuencia_pago, ruta_frec_pago_ajax);
    }

    // ========== VALIDACION DE DATOS ==========
    async function validarDatos() {
        // Limpiar errores previos
        limpiarErroresVisuales();
        
        const data = {};
        const camposAdvertencia = [];
        
        // ========== RECOLECTAR DATOS COMUNES ==========
        
        data.id_tipo_persona = document.getElementById('id_tipo_persona').value || '1';
        data.id_status = document.getElementById('id_status').value || '1';
        
        // Foto
        const inputFoto = document.getElementById('input-foto-file');
        data.foto = inputFoto.files[0] || null;
        data.contrato_foto = document.getElementById('foto_nombre').value || '';
        
        // ========== VALIDAR SEGÚN TIPO ==========
        
        const resultado = await validarDatosForm(data, camposAdvertencia);
        if (resultado.error) return { error: true, campo: resultado.campo };
        
        // ========== MANEJAR ADVERTENCIAS ==========
        
        if (camposAdvertencia.length > 0) {
            const listaCampos = camposAdvertencia.map(c => `• ${c.label}`).join('\n');
            const confirmado = await suiteConfirm(
                'warning',
                `The following fields are empty, but you can continue.:\n\n${listaCampos}\n\nDo you wish to proceed?`,
                {
                    aceptar: 'Continue',
                    cancelar: 'revise'
                }                
            );
            
            if (!confirmado) {
                // Enfocar el primer campo de advertencia
                enfocarCampo(camposAdvertencia[0].id);
                return { error: true, canceladoPorUsuario: true };
            }
        }
        
        // ========== MAPEAR A NOMBRES DE BACKEND ==========
        
        const dataFinal = {};
        for (const [key, value] of Object.entries(data)) {
            const nombreBackend = MAPEO_CAMPOS[key] || key;
            dataFinal[nombreBackend] = value;
        }
        
        return { error: false, data: dataFinal };
    }

    // ============================================
    // VALIDACIÓN ESPECÍFICA
    // ============================================
    async function validarDatosForm(data, camposAdvertencia) {
        const campos = {
            id_tipo_persona: { 
                el: document.getElementById('id_tipo_persona'), 
                label: 'Person Type',
                critico: false 
            },
            id_status: { 
                el: document.getElementById('id_status'), 
                label: 'Status',
                critico: true 
            },
            id_cliente: { 
                el: document.getElementById('ser-select-driver-value'), 
                label: 'Select Customer',
                critico: true 
            },
            id_direccion: { 
                el: document.getElementById('id_direccion'), 
                label: 'Select Address',
                critico: true 
            },
            nom_contrato: { 
                el: document.getElementById('nom_contrato'), 
                label: 'Contract Name',
                critico: false 
            },
            tiempo_servicio: { 
                el: document.getElementById('tiempo_servicio'), 
                label: 'Service Time',
                critico: false 
            },
            retraso_invierno: { 
                el: document.getElementById('retraso_invierno'), 
                label: 'Extended Winter',
                critico: false
            },
            id_area: { 
                el: document.getElementById('id_area'), 
                label: 'Work Area',
                critico: false
            },
            num_semanas: { 
                el: document.getElementById('num_semanas'), 
                label: 'Number Services',
                critico: false
            },
            costo: { 
                el: document.getElementById('costo'), 
                label: 'Cost',
                critico: false
            },
            id_dia_semana: { 
                el: document.getElementById('id_dia_semana'), 
                label: 'Work day',
                critico: false
            },
            secondary_day: { 
                el: document.getElementById('secondary_day'), 
                label: 'Secondary Work day',
                critico: false
            },
            id_ruta: { 
                el: document.getElementById('id_ruta'), 
                label: 'Grid Zone',
                critico: false
            },
            fecha_ini: { 
                el: document.getElementById('fecha_ini'), 
                label: 'Start Service',
                critico: false
            },
            fecha_fin: { 
                el: document.getElementById('fecha_fin'), 
                label: 'Finished Service',
                critico: false
            },
            id_frecuencia_servicio: { 
                el: document.getElementById('id_frecuencia_servicio'), 
                label: 'Service Frequency',
                critico: false
            },
            id_frecuencia_pago: { 
                el: document.getElementById('id_frecuencia_pago'), 
                label: 'Payment Frequency',
                critico: false
            }
        };
        
        for (const [key, config] of Object.entries(campos)) {
            const valor = config.el ? config.el.value.trim() : (config.default || '');
            data[key] = valor;
            // Validar críticos
            if (config.critico && !valor) {
                marcarError(config.el, config.label);
                await suiteAlertError('Validation Error', `The ${config.label} field is required`);
                enfocarCampo(config.el);
                return { error: true, campo: key };
            }
            
            // Validación personalizada para teléfonos
            if (config.validar && valor && !config.validar(valor)) {
                marcarError(config.el, config.label);
                await suiteAlertError('Validation Error', `The ${config.label} is invalid (minimum 10 digits)`);
                // Enfocar el input visible correspondiente
                const displayInput = document.getElementById(config.el.id + '-display');
                enfocarCampo(displayInput);
                return { error: true, campo: key };
            }

            // Acumular advertencias
            if (!config.critico && !valor && config.el && config.el.id) {
                camposAdvertencia.push({ 
                    id: config.el.id + '-display', 
                    label: config.label,
                    key: key 
                });
            }
        }        
        return { error: false };
    }
    

    // ============================================
    // FUNCIÓN DE ENVÍO (Ejemplo)
    // ============================================

    async function enviarDatosBackend(data) {
        suiteLoading('show');

        try {
            const form = document.getElementById("ingreso_contrato");
            const formData = new FormData(form);

            // ✅ CORRECCIÓN: Actualizar/Agregar los campos procesados/mapeados
            for (const [key, value] of Object.entries(data)) {
                if (value instanceof File) {
                    formData.set(key, value); // set reemplaza si existe
                } else {
                    formData.set(key, value);
                }
            }

            const response = await fetch(form.action, {
                method: form.method,
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText || 'Bad Request'}`);
            }

            const result = await response.json();

            suiteLoading('hide');
            if (result.success) {
                await suiteAlertSuccess('Success', 'Contract successfully registered');
                window.location.href = platRutaRetorno;
            } else {
                throw new Error(result.message || 'Server error');
            }

        } catch (error) {
            suiteLoading('hide');
            console.error('Error en envío:', error);
            await suiteAlertError('Error', "Submission failed: " + error.message);
        }
    }

    // === MANEJO DEL BOTÓN PRINCIPAL ===
    document.getElementById('ingreso_contrato').addEventListener('submit', async function(e) {
        // PREVENIR SIEMPRE el submit nativo primero
        e.preventDefault();
        e.stopImmediatePropagation(); // ← AGREGADO: Detener otros listeners
        
        const resultado = await validarDatos();

        // Si hay error crítico o usuario canceló, detener
        if (resultado.error) {
            console.log('Validación fallida:', resultado.campo || 'Cancelado por usuario');
            return false;
        }
        
        // Éxito: resultado.data contiene el arreglo final mapeado
        console.log('Datos validados listos para envío:', resultado.data);
        
        // Aquí continúas con el envío al backend
        await enviarDatosBackend(resultado.data);
    });

    /**
     * Formatea el número mientras el usuario escribe
     * Formato: +X (XXX) XXX-XXXX o +XX (XXX) XXX-XXXX
     */
    function formatearTelefono(e) {
        const input = e.target;
        const hiddenInputId = input.id.replace('-display', '');
        const hiddenInput = document.getElementById(hiddenInputId);

        // Obtener solo los números del valor actual
        let numeros = input.value.replace(/\D/g, '');

        // Limitar a 15 dígitos (estándar E.164)
        numeros = numeros.substring(0, 15);

        // Guardar solo números en el campo hidden (lo que se envía al backend)
        if (hiddenInput) {
            hiddenInput.value = numeros;
        }

        // Formatear para visualización
        let formateado = '';

        if (numeros.length === 0) {
            formateado = '';
        } else if (numeros.length <= 2) {
            // Código de país: +X o +XX
            formateado = '+' + numeros;
        } else if (numeros.length <= 5) {
            // +XX (X
            formateado = '+' + numeros.substring(0, 2) + ' (' + numeros.substring(2);
        } else if (numeros.length <= 8) {
            // +XX (XXX) XX
            formateado = '+' + numeros.substring(0, 2) + ' (' + numeros.substring(2, 5) + ') ' + numeros.substring(5);
        } else if (numeros.length <= 12) {
            // +XX (XXX) XXX-XXXX
            formateado = '+' + numeros.substring(0, 2) + ' (' + numeros.substring(2, 5) + ') ' +
                numeros.substring(5, 8) + '-' + numeros.substring(8);
        } else {
            // +XX (XXX) XXX-XXXX-XXXX (extensiones largas)
            formateado = '+' + numeros.substring(0, 2) + ' (' + numeros.substring(2, 5) + ') ' +
                numeros.substring(5, 8) + '-' + numeros.substring(8, 12) + '-' + numeros.substring(12);
        }

        // Actualizar el input visible
        input.value = formateado;
    }

    /**
     * Maneja el backspace para borrar correctamente
     */
    function manejarBackspace(e) {
        if (e.key === 'Backspace') {
            const input = e.target;
            const cursorPos = input.selectionStart;

            // Si el cursor está justo después de un carácter de formato, moverlo antes
            const charAntes = input.value.substring(cursorPos - 1, cursorPos);
            if ([' ', '(', ')', '-', '+'].includes(charAntes)) {
                e.preventDefault();
                input.setSelectionRange(cursorPos - 1, cursorPos - 1);
            }
        }
    }

    /**
     * Validación al salir del campo
     */
    function validarTelefonoCompleto(e) {
        const input = e.target;
        const hiddenInputId = input.id.replace('-display', '');
        const hiddenInput = document.getElementById(hiddenInputId);
        const numeros = hiddenInput.value;

        // Validar longitud mínima (código país + área + número = mínimo 10 dígitos)
        if (numeros.length > 0 && numeros.length < 10) {
            input.style.border = '2px solid #e74c3c';
            input.title = 'Phone number too short. Minimum 10 digits.';
        } else {
            input.style.border = '';
            input.title = '';
        }
    }

    // ==========================================
    // CAMBIO DE RADIO (TU nombre: filtro_estado)
    // ==========================================
    document.querySelectorAll('input[name="filtro_estado"]').forEach(radio => {
        radio.addEventListener('change', function(e) {
            if (e.target.checked) {
                filtro_estado = e.target.value;
                filtroEstadoActual = filtro_estado;

                // Actualizar UI visual
                document.querySelectorAll('.radio-card').forEach(card => {
                    card.classList.remove('active');
                });

                const radioSeleccionado = document.getElementById(`filtro-${filtro_estado}`);
                if (radioSeleccionado) {
                    radioSeleccionado.closest('.radio-card').classList.add('active');
                    radioSeleccionado.checked = true;
                }

                console.log('🔵 [RADIO] Cambio a:', filtro_estado);
                serInicializarSelectFoto(ruta_clientes_ajax); // ← Misma ruta
            }
        });
    });

    //============================================
    //FORMATEO DE TELÉFONOS
    //============================================
    function aplicarFormato(input, valorManual = null) {
        if (!input) return;
        if (valorManual !== null) input.value = valorManual;

        let numeros = input.value.replace(/\D/g, '');
        let formateado = '';

        if (numeros.length > 0) {
            if (numeros.length <= 2) formateado = '+' + numeros;
            else if (numeros.length <= 5) formateado = `+${numeros.substring(0,2)}(${numeros.substring(2)}`;
            else if (numeros.length <= 8) formateado = `+${numeros.substring(0,2)}(${numeros.substring(2,5)})${numeros.substring(5)}`;
            else if (numeros.length <= 12) formateado = `+${numeros.substring(0,2)}(${numeros.substring(2,5)})${numeros.substring(5,8)}-${numeros.substring(8)}`;
            else formateado = `+${numeros.substring(0,2)}(${numeros.substring(2,5)})${numeros.substring(5,8)}-${numeros.substring(8,12)}`;
        }

        input.value = formateado;

        const hiddenId = input.id.replace('-display', '');
        const hidden = document.getElementById(hiddenId);
        if (hidden) hidden.value = numeros;
    }

    async function configurar_frontend(opcionSeleccionada) {

        // Formato de Teléfonos
        const tel1 = opcionSeleccionada['telefono'];
        const tel2 = opcionSeleccionada['telefono2'];
        let telefonos = [];

        const telefono1 = document.getElementById('phone1');
        const telefono2 = document.getElementById('phone2');

        if (opcionSeleccionada['tipo_persona'] == 'Natural Person') {
            id_tipo_persona.value = 1;
            telefono1.style.display = 'flex';
            telefonos = [document.getElementById('natural-tel1-display'), document.getElementById('natural-tel2-display')];
        } else {
            id_tipo_persona.value = 2;
            telefono2.style.display = 'flex';
            telefonos = [document.getElementById('juridica-tel1-display'), document.getElementById('juridica-tel2-display')];
        }

        let val1 = 1;
        for (let tel of telefonos) {
            aplicarFormato(tel, val1 === 1 ? tel1 : tel2);
            val1++;
        }

        // Formato de Codigo
        const id_cliente1 = document.getElementById('id_cliente1');
        
        const response = await fetch(ruta_contratos_ajax, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                modulo_contratos: 'ceros',
                valor: opcionSeleccionada['id_cliente']
            })
        });
        const result02 = await response.json();
        if (!result02.success || !result02.dato) {
            console.error('❌ Error cargando ceros:', result02);
            return null;
        }

        id_cliente1.value = result02.dato;

        // Formato de EMAIL
        const email = document.getElementById('email');
        email.value = opcionSeleccionada['email'];

        // Formato de Direcciones
        let id_direccion = '';
        try {
            const resp1 = await fetch(ruta_direcciones_ajax, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_direcciones: 'contar_direcciones',
                    id_cliente: opcionSeleccionada['id_cliente'],
                })
            });
            const cant_direcciones = await resp1.json();
            if (!cant_direcciones.success || !cant_direcciones.direcciones) {
                console.error('❌ Error cargando cantidad de direcciones:', cant_direcciones);
                return null;
            }

            id_direccion = cant_direcciones['id_direccion'];
            if (id_direccion != '') {
                document.getElementById('id_direccion1').value = id_direccion;
            }
        } catch (err) {
            console.error("Error al cargar Direcciones:", err);
        }

        try {
            const resp = await fetch(ruta_direcciones_ajax, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_direcciones: 'crear_select',
                    id_cliente: opcionSeleccionada['id_cliente'],
                    id_direccion: id_direccion
                })
            });
            const direcciones = await resp.text();
            document.getElementById('id_direccion').innerHTML = direcciones;

        } catch (err) {
            console.error("Error al cargar Direcciones:", err);
        }

        // Formato de nombre del contrato
        const nom_contrato = document.getElementById('nom_contrato');
        nom_contrato.value = opcionSeleccionada['cliente'];
    }

    /**
     * Inicializa los event listeners de los filtros de estado
     */
    async function inicializarFiltrosEstado() {
        serInicializarSelectFoto(ruta_clientes_ajax); // ← PON TU RUTA REAL AQUÍ

        window.selectCustomerAPI = await serInicializarSelectFoto(
            ruta_clientes_ajax,
            (opcionSeleccionada) => {
                configurar_frontend(opcionSeleccionada);

                console.log('✅ Cliente seleccionado:', opcionSeleccionada);
            }
        );

        // Configurar listeners con TU nombre de selector
        configurarListenersRadio();
    }

    // ==========================================
    // LISTENERS PARA RADIOS (TU nombre: filtro_estado)
    // ==========================================
    function configurarListenersRadio() {
        const radios = document.querySelectorAll('input[name="filtro_estado"]');

        radios.forEach(radio => {
            // ✅ Asegurar que se vean habilitados (solo visual)
            radio.disabled = false;
            radio.style.opacity = '1';
            radio.style.cursor = 'pointer';

            // Si hay un label asociado, también asegurarlo
            const label = radio.parentElement;
            if (label && label.tagName === 'LABEL') {
                label.style.opacity = '1';
                label.style.cursor = 'pointer';
                label.style.color = 'inherit';
            }

            radio.addEventListener('change', async function(e) {
                if (!e.target.checked) return;

                const nuevoFiltro = e.target.value;
                console.log('🔄 Cambio de filtro:', filtro_estado, '→', nuevoFiltro);

                // Actualizar TU variable global
                filtro_estado = nuevoFiltro;

                // Refresh con TU variable
                if (window.selectCustomerAPI && typeof window.selectCustomerAPI.refresh === 'function') {
                    const exito = await window.selectCustomerAPI.refresh(
                        ruta_clientes_ajax,
                        filtro_estado
                    );

                    if (exito) {
                        window.selectCustomerAPI.reset();
                    }
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', async () => {
        // Inicializar filtros de estado
        inicializarFiltrosEstado();

        // Cargar status
        await cargarDatosIniciales();

        openTab(null, 'tab1');
    });
</script>