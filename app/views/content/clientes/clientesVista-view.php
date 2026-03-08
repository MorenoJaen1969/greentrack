<?php
// app/views/content/clientesVista-view.php
// Vista de detalle de cliente con mapa de direcciones (Sistema PLAT - Prefijo único)
if (isset($url[0])) {
    $proceso_actual = $url[0];
} else {
    $proceso_actual = "clientesVista";
}

if (isset($url[2])) {
    $id_cliente = $url[2];
} else {
    $id_cliente = 0;
}

if (isset($url[3])) {
    $pagina_origen = $url[3];
} else {
    $pagina_origen = 1;
}

if (isset($url[4])) {
    $ruta_retorno = RUTA_APP . "/" . $pagina_origen . "/" . $url[4];
} else {
    $ruta_retorno = RUTA_APP . "/dashboard";
}

$param_dat = [
    'id_cliente' => $id_cliente
];

$paquete = $clientes->consulta_registro($param_dat);

$row1 = $paquete['datos'];
$row2 = $paquete['direcciones'];

$id_tratamiento = $row1['id_tratamiento'];
$tratamiento = $row1['tratamiento'];
$id_cliente = $row1['id_cliente'];
$id_tipo_persona = $row1['id_tipo_persona'];
$descripcion = $row1['descripcion'];
$nombre = $row1['nombre'];
$apellido = $row1['apellido'];
$nombre_comercial = $row1['nombre_comercial'];
$telefono = $row1['telefono'];
$telefono2 = $row1['telefono2'];
$id_status = $row1['id_status'];
$status = $row1['status'];
$fecha_creacion = $row1['fecha_creacion'];
$notas = $row1['notas'];
$observaciones = $row1['observaciones'];
$fecha_status = $row1['fecha_status'];

$id_sexo = $row1['id_sexo'];
$cliente_foto = $row1['cliente_foto'];

if ($id_tipo_persona == 2) {
    $cliente = $nombre_comercial;
} else {
    $cliente = $tratamiento . " " . $nombre . " " . $apellido;
}

if (is_null($row1['email'])) {
    $email = "---";
} else {
    $email = $row1['email'];
}

if ($id_status == 2 || $id_status == 3 || $id_status == 5 || $id_status == 7) {
    $puede_editar = 'disabled';
} else {
    $puede_editar = '';
}

$ruta_img = '/app/views/img/uploads/fotos/' . $cliente_foto;
if ($id_tipo_persona == 1) {
    if ($id_sexo == 1) {
        $r_sin_img = '/app/views/fotos/default.png';
    } else {
        $r_sin_img = '/app/views/fotos/responsable-mujer.png';
    }
} else {
    $r_sin_img = '/app/views/fotos/compania.png';
}
if ($clientes->isFile($ruta_img)) {
    $imagen_mostrar = $ruta_img;
} else {
    $imagen_mostrar = $r_sin_img;
}

$modo_edicion = true;

$ruta_paises_ajax = RUTA_APP . "/app/ajax/paisesAjax.php";
$ruta_estados_ajax = RUTA_APP . "/app/ajax/estadosAjax.php";
$ruta_condados_ajax = RUTA_APP . "/app/ajax/condadosAjax.php";
$ruta_ciudades_ajax = RUTA_APP . "/app/ajax/ciudadesAjax.php";
$ruta_land_use_ajax = RUTA_APP . "/app/ajax/landUseAjax.php";
$ruta_suffix_ajax = RUTA_APP . "/app/ajax/streetSuffixAjax.php";

$ruta_status_ajax = RUTA_APP . "/app/ajax/statusAjax.php";
$ruta_tratamiento_ajax = RUTA_APP . "/app/ajax/tratamientoAjax.php";
$ruta_direcciones_ajax = RUTA_APP . "/app/ajax/direccionesAjax.php";
$ruta_cliente_ajax = RUTA_APP . "/app/ajax/clientesAjax.php";
$ruta_cliente = RUTA_APP . "/clientes/";
$encabezadoVista = PROJECT_ROOT . "/app/views/inc/encabezadoVista.php";
$opcion = "clientesVista";

?>

<main>
    <p hidden id="ruta_cliente"><?php echo $ruta_cliente; ?></p>
    <p hidden id="ruta_cliente_ajax"><?php echo $ruta_cliente_ajax; ?></p>

    <?php require_once $encabezadoVista; ?>

    <div class="form-container">
        <form class="FormularioAjax form-horizontal validate-form" action="<?php echo $ruta_cliente_ajax; ?>"
            method="POST" id="update_cliente" name="update_cliente" enctype="multipart/form-data" autocomplete="off">

            <input class="form-font" type="hidden" name="modulo_clientes" value="update_cliente">
            <input class="form-font" type="hidden" id="id_cliente" name="id_cliente" value="<?php echo $row1['id_cliente']; ?>">

            <div class="tab-container">
                <div class="tabs-gen">
                    <button type="button" class="tab-button active tablink" data-tab="tab1" onclick="openTab(event, 'tab1')">
                        Personal Data
                    </button>
                    <button type="button" class="tab-button tablink" data-tab="tab2" onclick="openTab(event, 'tab2')">
                        Addresses
                    </button>
                    <button type="button" class="tab-button tablink" data-tab="tab3" onclick="openTab(event, 'tab3')">
                        Notes and Observations
                    </button>
                </div>

                <!-- TAB 1: Personal Data -->
                <div id="tab1" class="tabcontent tab-link">
                    <!-- Contenido de datos personales (sin cambios) -->
                    <div class="form-group-ct-inline">
                        <h3>Personal Data</h3>
                        <h3 style="margin-left: auto; color: #6d1a72ff; font-weight: bold;">
                            <label class="ancho_label1">
                                <?php if ($id_status == 2 || $id_status == 3 || $id_status == 5 || $id_status == 7): ?>
                                    <?php echo $status;
                                    date('Y-m-d', strtotime($fecha_status)); ?> |
                                <?php endif; ?>
                                <?= htmlspecialchars($cliente) ?>
                            </label>
                        </h3>
                    </div>

                    <div class="forma01">
                        <div class="grupo_user10">
                            <div class="grupo_foto01">
                                <div class="form-group-ct-incolumn">
                                    <?php if ($id_tipo_persona == 1): ?>
                                        <div class="form-group-ct-inline">
                                            <div class="form-group-ct">
                                                <label for="id_tratamiento">Treatment</label>
                                                <select class="form-control form-font" id="id_tratamiento" name="id_tratamiento" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                                </select>
                                            </div>
                                            <div class="form-group-ct">
                                                <label for="nombre">Name</label>
                                                <input class="input" type="text" id="nombre" name="nombre" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}"
                                                    maxlength="40" value="<?php echo $nombre; ?>" required>
                                            </div>
                                            <div class="form-group-ct">
                                                <label for="apellido">Last Name</label>
                                                <input class="input" type="text" id="apellido" name="apellido" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}"
                                                    maxlength="40" value="<?php echo $apellido; ?>" required>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="form-group-ct">
                                            <label for="nombre_comercial">Business Name</label>
                                            <input class="input" type="text" id="nombre_comercial" name="nombre_comercial" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}"
                                                maxlength="40" value="<?php echo $nombre_comercial; ?>" required>
                                        </div>
                                        <label>Legal Representative</label>
                                        <div class="form-group-ct-inline">
                                            <div class="form-group-ct">
                                                <label for="id_tratamiento">Treatment</label>
                                                <select class="form-control form-font" id="id_tratamiento" name="id_tratamiento" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                                </select>
                                            </div>
                                            <div class="form-group-ct">
                                                <label for="nombre">Name</label>
                                                <input class="input" type="text" id="nombre" name="nombre" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}"
                                                    maxlength="40" value="<?php echo $nombre; ?>" required>
                                            </div>
                                            <div class="form-group-ct">
                                                <label for="apellido">Last Name</label>
                                                <input class="input" type="text" id="apellido" name="apellido" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}"
                                                    maxlength="40" value="<?php echo $apellido; ?>" required>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="form-group-ct-inline">
                                        <div class="form-group-ct">
                                            <label for="fecha_creacion">Creation Date</label>
                                            <input class="input" type="date" id="fecha_creacion" name="fecha_creacion"
                                                value="<?php echo $fecha_creacion; ?>" required>
                                        </div>
                                        <div class="form-group-ct">
                                            <label for="id_status">Status</label>
                                            <select class="form-control form-font" id="id_status" name="id_status" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grupo_foto02">
                                <div class="photo-container-2">
                                    <div class="photo-container_02 image-preview">
                                        <div id="foto-container" style="text-align: center; margin-bottom: 5px;">
                                            <div id="foto-preview" class="foto-cliente-edit">
                                                <img id="foto-img" class="foto-imagen-edit" src="<?php echo $imagen_mostrar; ?>" alt="Imagen previa">
                                            </div>

                                            <button type="button" id="btn-cambiar-foto" class="foto-boton">
                                                <span id="texto-btn-foto">Add Photo</span>
                                            </button>

                                            <!-- Input file OCULTO fuera del form pero vinculado -->
                                            <input type="file" id="input-foto-file" name="foto" accept="image/*"
                                                style="display: none;" form="ingreso_cliente">

                                            <input type="hidden" id="foto_nombre" name="cliente_foto" value="">

                                            <p style="margin-top: 10px; font-size: 0.85em; color: #666;">
                                                Max 2MB (JPG, PNG)
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="forma02">
                        <h3>Other data</h3>
                        <div class="form-group-ct-inline">
                            <div class="form-column" id="phone1" style="display: none; grid-template-columns: 1fr 1fr; gap: 15px; padding-left: 15px;">
                                <div class="form-group-ct">
                                    <label for="natural-tel1-display"><strong>Phone Primary</strong><br /><small>Format: +XX (123) 456-7890</small></label>
                                    <input class="campo-natural input telefono-mask"
                                        type="tel"
                                        id="natural-tel1-display"
                                        placeholder="+1 (234) 567-8900"
                                        autocomplete="off">
                                    <!-- Input hidden real que se envía al backend (solo números) -->
                                    <input type="hidden" name="natural_telefono1" id="natural-tel1" class="campo-natural">
                                    <small style="color: #666; font-size: 0.85em;">Format: +Country (Area) Number</small>
                                </div>
                                <div class="form-group-ct">
                                    <label for="natural-tel2-display"><strong>Phone Secondary</strong><br /><small>Format: +XX (123) 456-7890</small></label>
                                    <input class="campo-natural input telefono-mask"
                                        type="tel"
                                        id="natural-tel2-display"
                                        placeholder="+1 (234) 567-8900"
                                        autocomplete="off">
                                    <input type="hidden" name="natural_telefono2" id="natural-tel2" class="campo-natural">
                                </div>
                            </div>
                            <div class="form-column" id="phone2" style="display: none; grid-template-columns: 1fr 1fr; gap: 15px; padding-left: 15px;">
                                <div class="form-group-ct">
                                    <label for="juridica-tel1-display"><strong>Office Phone</strong><br /><small>Format: +XX (123) 456-7890</small></label>
                                    <input class="campo-juridica input telefono-mask"
                                        type="tel"
                                        id="juridica-tel1-display"
                                        placeholder="+1 (234) 567-8900"
                                        autocomplete="off">
                                    <input type="hidden" name="juridica_telefono1" id="juridica-tel1" class="campo-juridica">
                                    <small style="color: #666; font-size: 0.85em;">Format: +Country (Area) Number</small>
                                </div>
                                <div class="form-group-ct">
                                    <label for="juridica-tel2-display"><strong>Mobile / Direct</strong><br /><small>Format: +XX (123) 456-7890</small></label>
                                    <input class="campo-juridica input telefono-mask"
                                        type="tel"
                                        id="juridica-tel2-display"
                                        placeholder="+1 (234) 567-8900"
                                        autocomplete="off">
                                    <input type="hidden" name="juridica_telefono2" id="juridica-tel2" class="campo-juridica">
                                </div>
                            </div>
                            <div class="form-group-ct">
                                <label for="email">Email</label>
                                <input class="input" type="email" id="email" name="email" maxlength="70"
                                    value="<?php echo $email; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: Addresses con Sistema PLAT -->
                <div id="tab2" class="tabcontent tab-link" style="overflow-x: hidden; box-sizing: border-box;">
                    <div class="form-group-ct-inline">
                        <h3>Addresses</h3>
                        <h3 style="margin-left: auto; color: #6d1a72ff; font-weight: bold;">
                            <label class="ancho_label1">
                                <?php if ($id_status == 2 || $id_status == 3 || $id_status == 5 || $id_status == 7): ?>
                                    <?php echo $status;
                                    date('Y-m-d', strtotime($fecha_status)); ?> |
                                <?php endif; ?>
                                <?= htmlspecialchars($cliente) ?>
                            </label>
                        </h3>
                    </div>

                    <!-- Contenedor GRID con Sistema PLAT -->
                    <div class="plat-direcciones-container">

                        <!-- Columna Izquierda: Listbox de Direcciones -->
                        <div class="plat-direcciones-listbox">
                            <div class="plat-direcciones-panel">
                                <div class="plat-direcciones-header">
                                    <strong>Address List</strong>
                                    <button type="button" id="btn-add-address" class="btn btn-sm btn-primary plat-btn-add" onclick="platAbrirModal()">
                                        <i class="fas fa-plus"></i> Add Address
                                    </button>
                                </div>

                                <div id="plat-direcciones-lista" class="plat-lista-scroll">
                                    <?php if (!empty($row2)): ?>
                                        <?php foreach ($row2 as $index => $dir): ?>
                                            <div class="plat-direccion-item <?php echo $index === 0 ? 'plat-active' : ''; ?>"
                                                data-id="<?php echo $dir['id_direccion']; ?>"
                                                data-lat="<?php echo $dir['lat']; ?>"
                                                data-lng="<?php echo $dir['lng']; ?>"
                                                data-direccion="<?php echo htmlspecialchars($dir['direccion']); ?>"
                                                onclick="platSeleccionarDireccion(this)">
                                                <div class="plat-direccion-content">
                                                    <div class="plat-direccion-texto">
                                                        <?php echo htmlspecialchars($dir['direccion']); ?>
                                                    </div>
                                                    <div class="plat-direccion-coords">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                        <?php echo number_format($dir['lat'], 6); ?>,
                                                        <?php echo number_format($dir['lng'], 6); ?>
                                                    </div>
                                                </div>
                                                <div class="plat-direccion-status">
                                                    <button type="button" id="plat-boton-delete" onclick="platDeleteRutaModal('<?php echo $dir['id_direccion']; ?>')" class="plat-boton-delete">
                                                        <i class="fa-solid fa-trash-can"></i> Delete
                                                    </button>
                                                    <button type="button" id="plat-boton-ruta" onclick="platVistaRutaModal('<?php echo $dir['lat']; ?>', '<?php echo $dir['lng']; ?>')" class="plat-boton-ruta">
                                                        <i class="fas fa-route"></i> View Route
                                                    </button>
                                                    <span class="plat-status-dot plat-status-<?php echo strtolower($dir['status']) == 'active' ? 'active' : 'inactive'; ?>"></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="plat-sin-direcciones">
                                            <i class="fas fa-inbox"></i>
                                            <p>No addresses registered</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Columna Derecha: Mapa PLAT -->
                        <div class="plat-mapa-wrapper">
                            <div class="plat-mapa-panel">
                                <div class="plat-mapa-header">
                                    <strong>Location Map (Satellite View)</strong>
                                </div>
                                <div class="plat-mapa-container">
                                    <div id="plat-mapa-leaflet"></div>
                                </div>
                                <div class="plat-mapa-footer">
                                    <small>
                                        <strong>Selected Address:</strong>
                                        <span id="plat-direccion-actual" class="plat-texto-azul">
                                            <?php echo !empty($row2) ? htmlspecialchars($row2[0]['direccion']) : 'None'; ?>
                                        </span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: Notes -->
                <div id="tab3" class="tabcontent tab-link">
                    <div class="form-group-ct-inline">
                        <h3>Notes and Observations</h3>
                        <h3 style="margin-left: auto; color: #6d1a72ff; font-weight: bold;">
                            <label class="ancho_label1">
                                <?php if ($id_status == 2 || $id_status == 3 || $id_status == 5 || $id_status == 7): ?>
                                    <?php echo $status;
                                    date('Y-m-d', strtotime($fecha_status)); ?> |
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

            </div>

            <button type="submit" class="btn-submit" id="submitBtn" <?php echo $puede_editar; ?>>
                Save changes
            </button>
        </form>
    </div>
</main>

<!-- Incluir el modal -->
<?php include 'app/views/content/clientes/address-form.php'; ?>

<!-- Scripts necesarios -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script src="<?= RUTA_REAL ?>/app/views/inc/js/func_comm.js?v=<?= time() ?>"></script>
<script src="<?= RUTA_REAL ?>/app/views/inc/js/jquery.min.js"></script>
<script src="<?= RUTA_REAL ?>/app/views/inc/js/bootstrap.bundle.min.js"></script>

<!-- Script PLAT - Sistema Completo -->
<script>
    //============================================
    //VARIABLES GLOBALES
    //============================================
    //Configuración de APIs de Geocodificación
    const apis = {
        opencage: {
            nombre: 'OpenCage',
            url: 'https://api.opencagedata.com/geocode/v1/json',
            key: 'e9cfb998b3a84cbd932923b3cff0e96e',
            activa: true
        },
        geoapify: {
            nombre: 'GeoApify',
            url: 'https://api.geoapify.com/v1/geocode/search',
            key: '7064f603ca67459d916a909b74bca1cb',
            activa: true
        },
        locationiq: {
            nombre: 'LocationIQ',
            url: 'https://us1.locationiq.com/v1/search',
            key: 'pk.1472af9e389d1d577738a28c25b3e620',
            activa: true
        }
    };

    //Para el mapa del TAB 2(visualización)
    let platMapaInstancia = null;
    let platMarcadorInstancia = null;
    let platMapaInicializado = false;

    //Variables globales adicionales
    let platUltimaRutaLat = null;
    let platUltimaRutaLng = null;

    //Para el modal de nueva dirección
    let platModalMapa = null;
    let platModalMarcador = null;

    //Geografia
    const platRutaPaisesAjax = "<?php echo $ruta_paises_ajax; ?>";
    const platRutaEstadosAjax = "<?php echo $ruta_estados_ajax; ?>";
    const platRutaCondadosAjax = "<?php echo $ruta_condados_ajax; ?>";
    const platRutaCiudadesAjax = "<?php echo $ruta_ciudades_ajax; ?>";
    const platRutaSuffixAjax = "<?php echo $ruta_suffix_ajax; ?>";
    const platLandUseAjax = "<?php echo $ruta_land_use_ajax; ?>";
    const platRutaClienteAjax = "<?php echo $ruta_cliente_ajax; ?>";
    const platRutaDireccionesAjax = "<?php echo $ruta_direcciones_ajax; ?>";
    const platRutaStatusAjax = "<?php echo $ruta_status_ajax; ?>";
    const platRutaTratamientoAjax = "<?php echo $ruta_tratamiento_ajax; ?>";
    const platRutaRetorno = "<?php echo $ruta_retorno; ?>";

    //Referencias DOM
    const telefono1 = document.getElementById('phone1');
    const telefono2 = document.getElementById('phone2');
    const id_tipo_persona = "<?php echo $id_tipo_persona; ?>";
    let tipo = id_tipo_persona == 2 ? 'J' : 'N';

    //Foto elementos
    const btnCambiarFoto = document.getElementById('btn-cambiar-foto');
    const inputFotoFile = document.getElementById('input-foto-file');
    const fotoImg = document.getElementById('foto-img');
    const fotoPlaceholder = document.getElementById('foto-placeholder');
    const textoBtnFoto = document.getElementById('texto-btn-foto');
    const fotoNombre = document.getElementById('foto_nombre');
    const retorno = document.getElementById('retorno');

    //Frases
    let arreglo_frases_g = [
        "Are you sure",
        "Do you confirm that you want to save the changes you have made?",
        "Yes, Save",
        "Do not save",
        "Response error",
        "Do you confirm that you wish to delete the address you have indicated?",
        "Yes, Delete",
        "Do not Delete"
    ];

    //Coordenadas del HQ (punto de partida para rutas)
    const HQ_LAT = 30.3204272; // Ajusta según tu ubicación real
    const HQ_LNG = -95.4217815; // Ajusta según tu ubicación real

    //============================================
    //FUNCIONES DE PESTAÑAS
    //============================================
    function openTab(evt, tabName) {
        document.querySelectorAll(".tabcontent").forEach(el => el.style.display = "none");
        document.querySelectorAll(".tablink").forEach(btn => btn.classList.remove("active"));
        const tab = document.getElementById(tabName);
        if (tab) tab.style.display = "block";
        if (evt && evt.currentTarget) evt.currentTarget.classList.add("active");

        //Inicializar mapa del TAB 2 cuando se abre
        if (tabName === 'tab2') {
            setTimeout(() => platInicializarMapa(), 100);
        }
    }

    function validateTabs() {
        if (!validateTab(1)) return showTab(1) && false;
        if (!validateTab(2)) return showTab(2) && false;
        if (!validateTab(3)) return showTab(3) && false;
        return true;
    }

    function showTab(index) {
        ["tab1", "tab2", "tab3"].forEach((id, i) => {
            document.getElementById(id).style.display = (i + 1 === index) ? "block" : "none";
        });
        return false;
    }

    function validateTab(tabNumber) {
        const fields = document.querySelectorAll(`#tab${tabNumber} input[required], #tab${tabNumber} select[required]`);
        let isValid = true;
        for (let field of fields) {
            if (!field.value.trim()) {
                field.classList.add('error');
                if (isValid) field.focus();
                isValid = false;
            } else {
                field.classList.remove('error');
            }
        }
        return isValid;
    }

    //============================================
    //CARGA DE SELECTS (compatibilidad)
    //============================================
    async function cargar_id_status(id_status, ruta) {
        try {
            const res = await fetch(ruta, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_status: 'crear_select',
                    tabla: 'clientes',
                    id_status: id_status
                })
            });
            if (res.ok) document.getElementById('id_status').innerHTML = await res.text();
        } catch (err) {
            console.error("Error al cargar Status:", err);
        }
    }

    async function cargar_id_tratamiento(id_tratamiento, ruta) {
        try {
            const res = await fetch(ruta, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_tratamiento: 'crear_select',
                    id_tratamiento: id_tratamiento
                })
            });
            if (res.ok) document.getElementById('id_tratamiento').innerHTML = await res.text();
        } catch (err) {
            console.error("Error al cargar Tratamiento:", err);
        }
    }

    //============================================
    //GESTIÓN DE FOTO
    //============================================
    btnCambiarFoto.addEventListener('click', () => inputFotoFile.click());

    inputFotoFile.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            suiteAlertWarning('warning', 'File too large. Maximum 2MB allowed.');
            inputFotoFile.value = '';
            return;
        }

        if (!file.type.startsWith('image/')) {
            suiteAlertWarning('warning','Only image files allowed.');
            inputFotoFile.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            fotoImg.src = e.target.result;
            fotoImg.style.display = 'block';
            fotoPlaceholder.style.display = 'none';
            textoBtnFoto.textContent = 'Change Photo';
            const extension = file.name.split('.').pop();
            fotoNombre.value = `cliente_${Date.now()}.${extension}`;
        };
        reader.readAsDataURL(file);
    });

    //============================================
    //SISTEMA PLAT - MAPA DEL TAB 2 (VISUALIZACIÓN)
    //============================================
    function platInicializarMapa() {
        const contenedor = document.getElementById('plat-mapa-leaflet');
        if (!contenedor) return;

        if (typeof L === 'undefined') {
            platMostrarMapaFallback();
            return;
        }

        if (platMapaInstancia) {
            platMapaInstancia.remove();
            platMapaInstancia = null;
        }

        contenedor.innerHTML = '';
        contenedor.style.cssText = 'width:100%; height:100%; position:relative;';

        let platLat = 30.3119,
            platLng = -95.4589,
            platZoom = 13;
        const primeraDireccion = document.querySelector('.plat-direccion-item');

        if (primeraDireccion) {
            const latParsed = parseFloat(primeraDireccion.dataset.lat);
            const lngParsed = parseFloat(primeraDireccion.dataset.lng);
            if (!isNaN(latParsed) && !isNaN(lngParsed)) {
                platLat = latParsed;
                platLng = lngParsed;
                platZoom = 16;
            }
        }

        try {
            platMapaInstancia = L.map('plat-mapa-leaflet', {
                center: [platLat, platLng],
                zoom: platZoom,
                zoomControl: true,
                attributionControl: true
            });

            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri'
            }).addTo(platMapaInstancia);

            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
                attribution: '',
                maxZoom: 19,
                opacity: 0.7
            }).addTo(platMapaInstancia);

            platMarcadorInstancia = L.marker([platLat, platLng]).addTo(platMapaInstancia);

            setTimeout(() => {
                platMapaInstancia.invalidateSize();
                platMapaInicializado = true;
            }, 200);
        } catch (error) {
            platMostrarMapaFallback();
        }
    }

    function platMostrarMapaFallback() {
        const contenedor = document.getElementById('plat-mapa-leaflet');
        if (!contenedor) return;

        const primera = document.querySelector('.plat-direccion-item');
        const lat = primera ? parseFloat(primera.dataset.lat) || 30.3119 : 30.3119;
        const lng = primera ? parseFloat(primera.dataset.lng) || -95.4589 : -95.4589;

        contenedor.innerHTML = `<iframe width="100%" height="100%" style="border:0; border-radius:4px;" loading="lazy" allowfullscreen src="https://www.google.com/maps/embed?pb=!1m17!1m12!1m3!1d3456!2d${lng}!3d${lat}!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m2!1m1!2z${lat}N${Math.abs(lng)}W!5e0!3m2!1ses!2sus!4v1699999999999!5m2!1ses!2sus"></iframe>`;
        platMapaInicializado = true;
    }

    function platSeleccionarDireccion(elemento) {
        const lat = parseFloat(elemento.dataset.lat);
        const lng = parseFloat(elemento.dataset.lng);
        const direccion = elemento.dataset.direccion || 'Sin dirección';

        if (isNaN(lat) || isNaN(lng)) return;

        document.querySelectorAll('.plat-direccion-item').forEach(item => item.classList.remove('plat-active'));
        elemento.classList.add('plat-active');

        const spanDireccion = document.getElementById('plat-direccion-actual');
        if (spanDireccion) spanDireccion.textContent = direccion;

        if (platMapaInstancia && platMarcadorInstancia) {
            platMarcadorInstancia.setLatLng([lat, lng]);
            platMarcadorInstancia.bindPopup(`<div style="font-family:Arial; min-width:180px;"><div style="font-weight:bold; margin-bottom:5px;">📍 Location</div><div style="font-size:13px; color:#555;">${direccion}</div></div>`).openPopup();
            platMapaInstancia.setView([lat, lng], 16, {
                animate: true
            });
        }
    }

    //============================================
    //SISTEMA PLAT - MODAL DE NUEVA DIRECCIÓN
    //============================================
    function platAbrirModal() {
        const modal = document.getElementById('plat-modal-direccion');
        if (!modal) {
            console.error('Modal no encontrado');
            return;
        }

        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';

        //Resetear estado de botones
        platActualizarEstadoBotones();

        setTimeout(() => {
            platInicializarMapaModal();
            platCargarPaisesModal();
            platCargarSuffixModal();
            platLandUseModal();
        }, 300);
    }

    function platCerrarModalDireccion() {
        const modal = document.getElementById('plat-modal-direccion');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        if (platModalMapa) {
            platModalMapa.remove();
            platModalMapa = null;
            platModalMarcador = null;
        }

        platLimpiarFormularioModal();
    }

    function platInicializarMapaModal() {
        const wrapper = document.getElementById('plat-modal-mapa-wrapper');
        if (!wrapper) {
            console.error('ERROR: Wrapper del mapa no encontrado');
            return;
        }

        if (typeof L === 'undefined') {
            console.error('ERROR: Leaflet no cargado');
            wrapper.innerHTML = '<div style="padding:50px; text-align:center; color:red;">Error: Leaflet no disponible</div>';
            return;
        }

        if (platModalMapa) {
            platModalMapa.remove();
            platModalMapa = null;
            platModalMarcador = null;
        }

        wrapper.innerHTML = '';

        const mapDiv = document.createElement('div');
        mapDiv.id = 'plat-modal-mapa-interno';
        mapDiv.style.cssText = 'width:636px; height:466px; position:relative;';
        wrapper.appendChild(mapDiv);

        try {
            // Crear mapa en el div interno con dimensiones fijas
            platModalMapa = L.map(mapDiv, {
                center: [20, 0],
                zoom: 2,
                zoomControl: true,
                attributionControl: true,
                // Desactivar características que pueden causar redimensionamiento
                trackResize: false
            });

            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
                maxZoom: 19
            }).addTo(platModalMapa);

            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
                attribution: '',
                maxZoom: 19,
                opacity: 0.7
            }).addTo(platModalMapa);

            platModalMapa.on('click', function(e) {
                platColocarMarcadorModal(e.latlng.lat, e.latlng.lng);
            });

            platModalMapa.invalidateSize();
        } catch (error) {
            console.error('ERROR al crear mapa:', error);
            wrapper.innerHTML = '<div style="padding:50px; text-align:center; color:red;">Error al cargar mapa</div>';
        }
    }

    async function platColocarMarcadorModal(lat, lng) {
        if (!platModalMapa) return;

        if (platModalMarcador) {
            platModalMapa.removeLayer(platModalMarcador);
        }

        platModalMarcador = L.marker([lat, lng], {
            draggable: true,
            title: 'Drag to adjust exact location'
        }).addTo(platModalMapa);

        // === ACTUALIZAR COORDENADAS (tu código original) ===
        document.getElementById('plat-modal-lat').value = lat.toFixed(8);
        document.getElementById('plat-modal-lng').value = lng.toFixed(8);
        document.getElementById('plat-modal-display-lat').textContent = lat.toFixed(6);
        document.getElementById('plat-modal-display-lng').textContent = lng.toFixed(6);

        // === NUEVO: Obtener y mostrar ZIP ===
        await platActualizarZIP(lat, lng);  // ← Solo esta línea nueva

        platModalMarcador.bindPopup('<strong>📍 Selected location</strong><br>Drag to adjust').openPopup();

        // === EVENTO DRAG (tu código original + ZIP) ===
        platModalMarcador.on('dragend', async function(e) {  // ← agregar 'async' aquí
            const pos = e.target.getLatLng();
            document.getElementById('plat-modal-lat').value = pos.lat.toFixed(8);
            document.getElementById('plat-modal-lng').value = pos.lng.toFixed(8);
            document.getElementById('plat-modal-display-lat').textContent = pos.lat.toFixed(6);
            document.getElementById('plat-modal-display-lng').textContent = pos.lng.toFixed(6);
            
            // === NUEVO: Actualizar ZIP al arrastrar ===
            await platActualizarZIP(pos.lat, pos.lng);  // ← Solo esta línea nueva
        });

        // Al tener coordenadas, habilitar botón de guardar
        platActualizarEstadoBotones();
    }

    // ==========================================
    // FUNCIÓN: Actualizar UI con ZIP encontrado
    // ==========================================
    async function platActualizarZIP(lat, lng) {
        // Referencias a elementos
        const zipInput = document.getElementById('plat-modal-zip'); // campo oculto para guardar
        const zipDisplay = document.getElementById('plat-modal-zip-display'); // campo visible (input o div)
        
        // Mostrar estado "cargando" (solo si es un elemento de texto, no input)
        if (zipDisplay && zipDisplay.tagName !== 'INPUT' && zipDisplay.tagName !== 'TEXTAREA') {
            zipDisplay.textContent = '⏳ Getting ZIP...';
            zipDisplay.style.color = '#6d1a72';
        }
        
        // Obtener ZIP
        const result = await platObtenerZIPDesdeCoordenadas(lat, lng);
        
        // Actualizar según resultado
        if (result.success && result.zip) {
            // Actualizar campo oculto (siempre)
            if (zipInput) zipInput.value = result.zip;
            
            // Actualizar campo visible (si existe)
            if (zipDisplay) {
                if (zipDisplay.tagName === 'INPUT' || zipDisplay.tagName === 'TEXTAREA') {
                    zipDisplay.value = result.zip;
                } else {
                    zipDisplay.innerHTML = `📮 ZIP: <strong>${result.zip}</strong>`;
                    zipDisplay.style.color = 'green';
                }
            }
            console.log(`✅ ZIP encontrado: ${result.zip}`);
        } else {
            // Si no se encontró ZIP, mostrar mensaje (solo en elemento de texto)
            if (zipDisplay && zipDisplay.tagName !== 'INPUT' && zipDisplay.tagName !== 'TEXTAREA') {
                zipDisplay.textContent = '⚠️ ZIP not found';
                zipDisplay.style.color = 'orange';
            }
            console.log('⚠️ ZIP no encontrado para estas coordenadas');
        }
    }    
    
    // ==========================================
    // FUNCIÓN: Obtener ZIP desde coordenadas
    // ==========================================
    async function platObtenerZIPDesdeCoordenadas(lat, lng) {
        try {
            // Intentar con LocationIQ primero (tienes key)
            const url = `https://us1.locationiq.com/v1/reverse.php?key=${apis.locationiq.key}&lat=${lat}&lon=${lng}&format=json&addressdetails=1`;
            const response = await fetch(url);
            const data = await response.json();
            
            if (data?.address?.postcode) {
                return { success: true, zip: data.address.postcode };
            }
            
            // Fallback: Nominatim (gratis, sin key)
            const nomUrl = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1&zoom=18`;
            const nomRes = await fetch(nomUrl, {
                headers: { 'User-Agent': 'GreenTrack Consolidation Tool/1.0' }
            });
            const nomData = await nomRes.json();
            
            if (nomData?.address?.postcode) {
                return { success: true, zip: nomData.address.postcode };
            }
            
            return { success: false, zip: null };
            
        } catch (error) {
            console.warn('⚠️ Error obteniendo ZIP:', error);
            return { success: false, zip: null };
        }
    }    

    //============================================
    //SELECTS EN CASCADA DEL MODAL
    //============================================
    async function platCargarPaisesModal() {
        const select = document.getElementById('plat-modal-pais');
        if (!select) return;

        select.innerHTML = '<option value="">Charging...</option>';

        try {
            const res = await fetch(platRutaPaisesAjax, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_paises: 'data_direcciones'
                })
            });
            const result = await res.json();

            select.innerHTML = '<option value="">Select a country...</option>';
            if (result.success && result.data) {
                result.data.forEach(pais => {
                    const option = document.createElement('option');
                    option.value = pais.id_pais;
                    option.textContent = pais.nombre;
                    option.dataset.lat = pais.latitud_centro || 20;
                    option.dataset.lng = pais.longitud_centro || 0;
                    option.dataset.zoom = pais.zoom_default || 5;
                    option.title = pais.iso_code
                    select.appendChild(option);
                });
            }
            select.onchange = platOnPaisChangeModal;
        } catch (error) {
            select.innerHTML = '<option value="">Error loading</option>';
        }
    }

    async function platOnPaisChangeModal(e) {
        const select = e.target;
        const option = select.selectedOptions[0];

        if (!select.value) {
            platResetearSelectsModal(['state', 'county', 'city']);
            platActualizarEstadoBotones();
            return;
        }

        if (platModalMapa) {
            platModalMapa.flyTo([parseFloat(option.dataset.lat), parseFloat(option.dataset.lng)], parseInt(option.dataset.zoom), {
                animate: true,
                duration: 1.5
            });
        }

        await platCargarEstadosModal(select.value);
        platActualizarEstadoBotones();
    }

    async function platCargarEstadosModal(id_pais) {
        const select = document.getElementById('plat-modal-estado');
        platResetearSelectsModal(['state', 'county', 'city']);
        select.innerHTML = '<option value="">Charging...</option>';

        try {
            const response = await fetch(platRutaEstadosAjax, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_estados: 'data_direcciones',
                    id_pais: id_pais
                })
            });
            const result = await response.json();

            select.innerHTML = '<option value="">Select state...</option>';
            if (result.success && result.data) {
                result.data.forEach(estado => {
                    const option = document.createElement('option');
                    option.value = estado.id_estado;
                    option.textContent = estado.nombre;
                    option.dataset.lat = estado.latitud_centro;
                    option.dataset.lng = estado.longitud_centro;
                    option.dataset.zoom = estado.zoom_default || 7;
                    select.appendChild(option);
                });
                select.disabled = false;
            }
            select.onchange = platOnEstadoChangeModal;
        } catch (error) {}
    }

    async function platOnEstadoChangeModal(e) {
        const select = e.target;
        const option = select.selectedOptions[0];

        if (!select.value) {
            platResetearSelectsModal(['county', 'city']);
            platActualizarEstadoBotones();
            return;
        }

        if (platModalMapa) {
            platModalMapa.flyTo([parseFloat(option.dataset.lat), parseFloat(option.dataset.lng)], parseInt(option.dataset.zoom), {
                animate: true,
                duration: 1.5
            });
        }

        await platCargarMunicipiosModal(select.value);
        platActualizarEstadoBotones();
    }

    async function platCargarMunicipiosModal(id_estado) {
        const select = document.getElementById('plat-modal-municipio');
        platResetearSelectsModal(['county', 'city']);
        select.innerHTML = '<option value="">Charging...</option>';

        try {
            const response = await fetch(platRutaCondadosAjax, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_condados: 'data_direcciones',
                    id_estado: id_estado
                })
            });
            const result = await response.json();

            select.innerHTML = '<option value="">Select county...</option>';
            if (result.success && result.data) {
                result.data.forEach(mun => {
                    const option = document.createElement('option');
                    option.value = mun.id_condado;
                    option.textContent = mun.nombre;
                    option.dataset.lat = mun.latitud_centro;
                    option.dataset.lng = mun.longitud_centro;
                    option.dataset.zoom = mun.zoom_default || 10;
                    select.appendChild(option);
                });
                select.disabled = false;
            }
            select.onchange = platOnMunicipioChangeModal;
        } catch (error) {}
    }

    async function platOnMunicipioChangeModal(e) {
        const select = e.target;
        const option = select.selectedOptions[0];

        if (!select.value) {
            platResetearSelectsModal(['city']);
            platActualizarEstadoBotones();
            return;
        }

        if (platModalMapa) {
            platModalMapa.flyTo([parseFloat(option.dataset.lat), parseFloat(option.dataset.lng)], parseInt(option.dataset.zoom), {
                animate: true,
                duration: 1.5
            });
        }

        await platCargarCiudadesModal(select.value);
        platActualizarEstadoBotones();
    }

    async function platCargarCiudadesModal(id_condado) {
        const select = document.getElementById('plat-modal-ciudad');
        platResetearSelectsModal(['city']);
        select.innerHTML = '<option value="">Charging...</option>';

        try {
            const response = await fetch(platRutaCiudadesAjax, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_ciudades: 'data_direcciones',
                    id_condado: id_condado
                })
            });
            const result = await response.json();

            select.innerHTML = '<option value="">Select city...</option>';
            if (result.success && result.data) {
                result.data.forEach(ciudad => {
                    const option = document.createElement('option');
                    option.value = ciudad.id_ciudad;
                    option.textContent = ciudad.nombre;
                    option.dataset.lat = ciudad.latitud_centro;
                    option.dataset.lng = ciudad.longitud_centro;
                    option.dataset.zoom = ciudad.zoom_default || 13;
                    select.appendChild(option);
                });
                select.disabled = false;
            }
            select.onchange = platOnCiudadChangeModal;
        } catch (error) {}
    }

    async function platOnCiudadChangeModal(e) {
        const select = e.target;
        const option = select.selectedOptions[0];

        if (!select.value) {
            platResetearSelectsOthersModal(['land_use', 'suffix']);
            platActualizarEstadoBotones();
            return;
        }

        if (platModalMapa) {
            platModalMapa.flyTo([parseFloat(option.dataset.lat), parseFloat(option.dataset.lng)], parseInt(option.dataset.zoom) || 14, {
                animate: true,
                duration: 1.5
            });
        }

        await platOthersSelectModal();
        platActualizarEstadoBotones();
    }

    async function platOthersSelectModal() {
        const selectLandUse = document.getElementById('plat-modal-land-use');
        const selectSuffix = document.getElementById('plat-modal-suffix');
        const inputCalle = document.getElementById('plat-modal-calle');
        const inputNumero = document.getElementById('plat-modal-numero');
        const inputNickname = document.getElementById('plat-modal-nickname');

        selectSuffix.disabled = false;
        selectLandUse.disabled = false;
        inputCalle.disabled = false;
        inputNickname.disabled = false;

        //El número se habilita según el Land Use seleccionado
        platActualizarEstadoBotones();
    }

    async function platLandUseModal() {
        const select = document.getElementById('plat-modal-land-use');
        platResetearSelectsOthersModal(['land_use', 'suffix']);
        select.innerHTML = '<option value="">Charging...</option>';

        try {
            const res02 = await fetch(platLandUseAjax, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_landuse: 'data_direcciones'
                })
            });
            const result02 = await res02.json();

            select.innerHTML = '<option value="">Select a Land Use...</option>';
            if (result02.success && result02.data) {
                result02.data.forEach(landuse => {
                    const option = document.createElement('option');
                    option.value = landuse.id_cat_land_use;
                    option.dataset.req = landuse.requires_number;
                    option.textContent = landuse.name + ' (' + landuse.use_code + ')';
                    option.title = landuse.description;
                    select.appendChild(option);
                });
            }
            select.onchange = handleLocationChange;
        } catch (error) {
            console.log("error ", error);
            select.innerHTML = '<option value="">Error loading</option>';
        }
    }

    async function handleLocationChange(e) {
        const selected = e.target.selectedOptions[0];
        const requiresNumber = selected.dataset.req === "1";
        const numWrapper = document.getElementById('wrapper-numero');
        const inputNumero = document.getElementById('plat-modal-numero');

        if (requiresNumber) {
            numWrapper.style.visibility = 'visible';
            inputNumero.required = true;
            inputNumero.disabled = false;
        } else {
            numWrapper.style.visibility = 'hidden';
            inputNumero.value = '';
            inputNumero.required = false;
            inputNumero.disabled = true;
        }
        platActualizarEstadoBotones();
    }

    async function platCargarSuffixModal() {
        const select = document.getElementById('plat-modal-suffix');
        platResetearSelectsOthersModal(['suffix', 'land_use']);
        select.innerHTML = '<option value="">Charging...</option>';

        try {
            const res01 = await fetch(platRutaSuffixAjax, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_streetsuffix: 'data_direcciones'
                })
            });
            const result01 = await res01.json();

            select.innerHTML = '<option value="">Select a Suffix...</option>';
            if (result01.success && result01.data) {
                result01.data.forEach(streetsuffix => {
                    const option = document.createElement('option');
                    option.value = streetsuffix.id_cat_street_suffixes;
                    option.textContent = streetsuffix.full_name;
                    option.title = streetsuffix.abbreviation;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.log("error ", error);
            select.innerHTML = '<option value="">Error loading</option>';
        }
    }

    function platResetearSelectsModal(niveles) {
        const config = {
            state: {
                id: 'plat-modal-estado',
                texto: 'state'
            },
            county: {
                id: 'plat-modal-municipio',
                texto: 'county'
            },
            city: {
                id: 'plat-modal-ciudad',
                texto: 'city'
            }
        };

        niveles.forEach(nivel => {
            const conf = config[nivel];
            const select = document.getElementById(conf.id);
            if (select) {
                select.innerHTML = `<option value="">Select ${conf.texto}...</option>`;
                select.disabled = true;
            }
        });
    }

    function platResetearSelectsOthersModal(niveles) {
        const config = {
            suffix: {
                id: 'plat-modal-suffix',
                texto: 'suffix'
            },
            land_use: {
                id: 'plat-modal-land-use',
                texto: 'land use'
            }
        };

        niveles.forEach(nivel => {
            const conf = config[nivel];
            const select = document.getElementById(conf.id);
            if (select) {
                select.innerHTML = `<option value="">Select ${conf.texto}...</option>`;
                select.disabled = true;
            }
        });
    }

    function platLimpiarFormularioModal() {
        document.getElementById('plat-modal-pais').value = '';
        platResetearSelectsModal(['state', 'county', 'city']);
        platResetearSelectsOthersModal(['suffix', 'land_use']);

        ['calle', 'numero', 'nickname'].forEach(id => {
            const el = document.getElementById(`plat-modal-${id}`);
            if (el) {
                el.value = '';
                el.disabled = true;
            }
        });

        document.getElementById('plat-modal-lat').value = '';
        document.getElementById('plat-modal-lng').value = '';
        document.getElementById('plat-modal-display-lat').textContent = '--';
        document.getElementById('plat-modal-display-lng').textContent = '--';
        document.getElementById('plat-modal-display-address').innerHTML = '--';

        //Limpiar ruta si existe
        if(platRutaLayer && platModalMapa){
            platModalMapa.removeLayer(platRutaLayer);
            platRutaLayer = null;
        }
        if(platMarcadorHQ && platModalMapa){
            platModalMapa.removeLayer(platMarcadorHQ);
            platMarcadorHQ = null;
        }

        platActualizarEstadoBotones();
    }

    //============================================
    //CONTROL DE ESTADO DE BOTONES
    //============================================
    function platActualizarEstadoBotones() {
        const btnVerificar = document.getElementById('plat-btn-verificar');
        const btnGuardar = document.getElementById('plat-btn-guardar');
        const btnRuta = document.getElementById('plat-btn-ruta');

        //Obtener valores
        const pais = document.getElementById('plat-modal-pais')?.value;
        const estado = document.getElementById('plat-modal-estado')?.value;
        const condado = document.getElementById('plat-modal-municipio')?.value;
        const ciudad = document.getElementById('plat-modal-ciudad')?.value;
        const calle = document.getElementById('plat-modal-calle')?.value?.trim();
        const landUse = document.getElementById('plat-modal-land-use')?.value;
        const suffix = document.getElementById('plat-modal-suffix')?.value;

        const landUseSelect = document.getElementById('plat-modal-land-use');
        const requiresNumber = landUseSelect?.selectedOptions[0]?.dataset.req === "1";
        const numero = document.getElementById('plat-modal-numero')?.value?.trim();

        const lat = document.getElementById('plat-modal-lat')?.value;
        const lng = document.getElementById('plat-modal-lng')?.value;

        //Verificar si tenemos datos mínimos para geocodificar
        let datosMinimos = pais && estado && condado && ciudad && calle && landUse && suffix;
        if (datosMinimos && requiresNumber) {
            datosMinimos = datosMinimos && numero;
        }

        //Habilitar/deshabilitar botón Verificar
        if (btnVerificar) {
            btnVerificar.disabled = !datosMinimos;
        }

        //Habilitar/deshabilitar botón Guardar (requiere coordenadas)
        if (btnGuardar) {
            btnGuardar.disabled = !(datosMinimos && lat && lng);
        }

        //Habilitar/deshabilitar botón Ruta (requiere coordenadas)
        if (btnRuta) {
            btnRuta.disabled = !(datosMinimos && lat && lng);
        }
    }

    //============================================
    //GEOCODIFICACIÓN
    //============================================
    async function platVerificarDireccionModal() {
        await suiteLoading('show');
        const numero = document.getElementById('plat-modal-numero')?.value;
        const calle = document.getElementById('plat-modal-calle')?.value;
        const sufijoSelect = document.getElementById('plat-modal-suffix');
        const sufijoOption = sufijoSelect.options[sufijoSelect.selectedIndex];
        const sufijo = sufijoOption.text;
        const sufijoAbrev = sufijoOption.title;

        const id_ciudad1 = document.getElementById('plat-modal-ciudad');
        const id_ciudad = id_ciudad1.options[id_ciudad1.selectedIndex].text;
        const id_condado1 = document.getElementById('plat-modal-municipio');
        const id_condado = id_condado1.options[id_condado1.selectedIndex].text;
        const id_estado1 = document.getElementById('plat-modal-estado');
        const id_estado = id_estado1.options[id_estado1.selectedIndex].text;
        const id_pais1 = document.getElementById('plat-modal-pais');
        const id_pais = id_pais1.options[id_pais1.selectedIndex].text;

        const containe_address = document.getElementById('plat-modal-display-address');
        const direccion = numero + ' ' + calle + ' ' + sufijo + ', ' + id_ciudad + ', ' + id_condado + ', ' + id_estado + ', ' + id_pais;
        containe_address.innerHTML = direccion;

        await platGeocodificarDireccionRobusta(sufijoAbrev);
        suiteLoading('hide');
    }

    async function platGeocodificarDireccionRobusta(sufijoAbrev) {
        const numero = document.getElementById('plat-modal-numero')?.value?.trim();
        const calle = document.getElementById('plat-modal-calle')?.value?.trim();
        const ciudadSelect = document.getElementById('plat-modal-ciudad');
        const ciudad = ciudadSelect?.options[ciudadSelect.selectedIndex]?.text || '';
        const condadoSelect = document.getElementById('plat-modal-municipio');
        const condado = condadoSelect?.options[condadoSelect.selectedIndex]?.text || '';
        const estadoSelect = document.getElementById('plat-modal-estado');
        const estado = estadoSelect?.options[estadoSelect.selectedIndex]?.text || '';

        const containerAddress = document.getElementById('plat-modal-display-address');
        const direccionFormateada = `${numero} ${calle} ${sufijoAbrev}, ${ciudad}, ${condado}, ${estado}`;

        if (containerAddress) {
            containerAddress.innerHTML = `<strong>Address:</strong> ${direccionFormateada}<br><small>Searching coordinates...</small>`;
        }

        if (!calle) return;

        //ESTRATEGIA 1: Dirección con ABREVIATURA
        let query = `${numero} ${calle} ${sufijoAbrev}, ${ciudad}, ${estado}, USA`;
        console.log('Intento 1 (con abreviatura):', query);
        let coords = await platIntentarGeocodificar(query);

        //ESTRATEGIA 2: Sin sufijo
        if (!coords) {
            query = `${numero} ${calle}, ${ciudad}, ${estado}, USA`;
            console.log('Intento 2 (sin sufijo):', query);
            coords = await platIntentarGeocodificar(query);
        }

        //ESTRATEGIA 3: Solo calle y ciudad
        if (!coords) {
            query = `${calle} ${sufijoAbrev}, ${ciudad}, ${estado}, USA`;
            console.log('Intento 3 (sin número):', query);
            coords = await platIntentarGeocodificar(query);
        }

        //ESTRATEGIA 4: Multi-API
        if (!coords) {
            console.log('Nominatim no encontró, consultando APIs comerciales...');
            coords = await platGeocodificarMultiAPI(query);
        }

        if (coords && platModalMapa) {
            const zoom = coords.confianza === 'muy alta' ? 19 :
                coords.confianza === 'alta' ? 18 :
                coords.confianza === 'media' ? 17 : 16;

            platModalMapa.flyTo([coords.lat, coords.lng], zoom, {
                animate: true,
                duration: 1.5
            });
            platColocarMarcadorModal(coords.lat, coords.lng);

            const containerAddress = document.getElementById('plat-modal-display-address');
            if (containerAddress) {
                const mensajeConfianza = {
                    'muy alta': '✅ Location verified (3 APIs)',
                    'alta': '✅ Reliable location (2 APIs)',
                    'media': '⚠️ Approximate location',
                    'baja': '⚠️ Verify location manually'
                };

                containerAddress.innerHTML = `
                ${direccionFormateada}<br>
                <small style="color:${coords.confianza === 'muy alta' || coords.confianza === 'alta' ? 'green' : 'orange'}">
                    ${mensajeConfianza[coords.confianza] || '⚠️ Verify manually'}
                    ${coords.apisUsadas ? ` (${coords.apisUsadas} APIs)` : ''}
                    ${coords.diferenciaMetros ? ` - Precision: ±${coords.diferenciaMetros.toFixed(0)}m` : ''}
                </small>
            `;
            }

            document.getElementById('plat-modal-display-lat').textContent = coords.lat.toFixed(6);
            document.getElementById('plat-modal-display-lng').textContent = coords.lng.toFixed(6);
            document.getElementById('plat-modal-lat').value = coords.lat.toFixed(8);
            document.getElementById('plat-modal-lng').value = coords.lng.toFixed(8);

            //Actualizar estado de botones (ahora se puede guardar)
            platActualizarEstadoBotones();
        }

        //ESTRATEGIA 5: Fallback a coordenadas de ciudad
        if (!coords) {
            console.log('Fallback a coordenadas de ciudad');
            coords = platObtenerCoordenadasCiudad();
            if (coords) {
                platModalMapa.flyTo([coords.lat, coords.lng], 14, {
                    animate: true,
                    duration: 1.5
                });
                platColocarMarcadorModal(coords.lat, coords.lng);
                document.getElementById('plat-modal-display-lat').textContent = coords.lat.toFixed(6);
                document.getElementById('plat-modal-display-lng').textContent = coords.lng.toFixed(6);
                document.getElementById('plat-modal-lat').value = coords.lat.toFixed(8);
                document.getElementById('plat-modal-lng').value = coords.lng.toFixed(8);
                platActualizarEstadoBotones();
            }
        }
    }

    function platObtenerCoordenadasCiudad() {
        const ciudadSelect = document.getElementById('plat-modal-ciudad');
        if (!ciudadSelect?.value) return null;

        const option = ciudadSelect.options[ciudadSelect.selectedIndex];
        return {
            lat: parseFloat(option.dataset.lat),
            lng: parseFloat(option.dataset.lng),
            esExacta: false
        };
    }

    async function platIntentarGeocodificar(query) {
        try {
            await new Promise(resolve => setTimeout(resolve, 1100));
            const response = await fetch(
                `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&addressdetails=1`, {
                    headers: {
                        'User-Agent': 'GreenTrack Consolidation Tool/1.0',
                        'Accept-Language': 'en-US,en;q=0.9'
                    }
                }
            );

            if (!response.ok) {
                console.warn('Respuesta no OK:', response.status);
                return null;
            }

            const data = await response.json();
            if (data && data.length > 0) {
                return {
                    lat: parseFloat(data[0].lat),
                    lng: parseFloat(data[0].lon),
                    esExacta: true,
                    displayName: data[0].display_name
                };
            }
            return null;
        } catch (error) {
            console.error('Error geocodificando:', error);
            return null;
        }
    }

    async function platGeocodificarMultiAPI(query) {
        const resultados = [];
        const ordenAPIs = ['opencage', 'geoapify', 'locationiq'];

        for (const nombreAPI of ordenAPIs) {
            const config = apis[nombreAPI];
            if (!config.activa) continue;

            try {
                let coords = null;
                switch (nombreAPI) {
                    case 'opencage':
                        coords = await platIntentarOpenCage(query, config);
                        break;
                    case 'geoapify':
                        coords = await platIntentarGeoApify(query, config);
                        break;
                    case 'locationiq':
                        coords = await platIntentarLocationIQ(query, config);
                        break;
                }

                if (coords) {
                    resultados.push({
                        api: config.nombre,
                        lat: coords.lat,
                        lng: coords.lng,
                        esExacta: coords.esExacta
                    });
                    console.log(`✅ ${config.nombre}:`, coords.lat.toFixed(6), coords.lng.toFixed(6));
                }
            } catch (error) {
                console.warn(`❌ ${config.nombre}:`, error.message);
            }

            await new Promise(resolve => setTimeout(resolve, 300));
        }

        if (resultados.length === 0) {
            console.error('Ninguna API respondió');
            return null;
        }

        if (resultados.length === 1) {
            console.warn('Solo una API respondió, usando:', resultados[0].api);
            return {
                ...resultados[0],
                confianza: 'baja',
                apisUsadas: 1
            };
        }

        const mejorResultado = platEncontrarMayoria(resultados);
        console.log(`🏆 Mejor resultado por mayoría: ${mejorResultado.api} (confianza: ${mejorResultado.confianza})`);
        return mejorResultado;
    }

    function platEncontrarMayoria(resultados) {
        if (resultados.length === 2) {
            const distancia = platCalcularDistancia(resultados[0], resultados[1]);
            console.log(`Distancia entre APIs: ${distancia.toFixed(2)} metros`);

            if (distancia < 100) {
                return {
                    lat: (resultados[0].lat + resultados[1].lat) / 2,
                    lng: (resultados[0].lng + resultados[1].lng) / 2,
                    api: `${resultados[0].api}+${resultados[1].api}`,
                    confianza: 'alta',
                    esExacta: true,
                    apisUsadas: 2,
                    diferenciaMetros: distancia
                };
            }

            return {
                ...resultados[0],
                confianza: 'baja',
                apisUsadas: 2,
                diferenciaMetros: distancia,
                alternativa: resultados[1]
            };
        }

        const pares = [{
            a: 0,
            b: 1
        }, {
            a: 0,
            b: 2
        }, {
            a: 1,
            b: 2
        }];
        let mejorPar = null;
        let menorDistancia = Infinity;

        for (const par of pares) {
            const distancia = platCalcularDistancia(resultados[par.a], resultados[par.b]);
            console.log(`${resultados[par.a].api} vs ${resultados[par.b].api}: ${distancia.toFixed(2)}m`);

            if (distancia < menorDistancia) {
                menorDistancia = distancia;
                mejorPar = par;
            }
        }

        const ganador1 = resultados[mejorPar.a];
        const ganador2 = resultados[mejorPar.b];
        const perdedor = resultados[3 - mejorPar.a - mejorPar.b];

        console.log(`Mayoría: ${ganador1.api}+${ganador2.api} (distancia: ${menorDistancia.toFixed(2)}m)`);
        console.log(`Descartado: ${perdedor.api}`);

        return {
            lat: (ganador1.lat + ganador2.lat) / 2,
            lng: (ganador1.lng + ganador2.lng) / 2,
            api: `${ganador1.api}+${ganador2.api}`,
            confianza: menorDistancia < 100 ? 'muy alta' : menorDistancia < 500 ? 'alta' : 'media',
            esExacta: true,
            apisUsadas: 3,
            diferenciaMetros: menorDistancia,
            descartado: perdedor
        };
    }

    function platCalcularDistancia(punto1, punto2) {
        const R = 6371e3;
        const φ1 = punto1.lat * Math.PI / 180;
        const φ2 = punto2.lat * Math.PI / 180;
        const Δφ = (punto2.lat - punto1.lat) * Math.PI / 180;
        const Δλ = (punto2.lng - punto1.lng) * Math.PI / 180;

        const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
            Math.cos(φ1) * Math.cos(φ2) *
            Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

        return R * c;
    }

    async function platIntentarOpenCage(query, config) {
        const params = new URLSearchParams({
            q: query,
            key: config.key,
            limit: 1,
            no_annotations: 1
        });

        const response = await fetch(`${config.url}?${params}`);
        const data = await response.json();

        if (data.results && data.results.length > 0) {
            return {
                lat: parseFloat(data.results[0].geometry.lat),
                lng: parseFloat(data.results[0].geometry.lng),
                esExacta: true
            };
        }
        return null;
    }

    async function platIntentarGeoApify(query, config) {
        const params = new URLSearchParams({
            text: query,
            apiKey: config.key,
            limit: 1
        });

        const response = await fetch(`${config.url}?${params}`);
        const data = await response.json();

        if (data.features && data.features.length > 0) {
            const coords = data.features[0].geometry.coordinates;
            return {
                lat: coords[1],
                lng: coords[0],
                esExacta: true
            };
        }
        return null;
    }

    async function platIntentarLocationIQ(query, config) {
        const params = new URLSearchParams({
            q: query,
            key: config.key,
            format: 'json',
            limit: 1
        });

        const response = await fetch(`${config.url}?${params}`);
        const data = await response.json();

        if (Array.isArray(data) && data.length > 0) {
            return {
                lat: parseFloat(data[0].lat),
                lng: parseFloat(data[0].lon),
                esExacta: true
            };
        }
        return null;
    }

    //============================================
    //RUTAS/DIRECCIONES
    //============================================
    async function platVerRutaModal(){
        // ✅ Sanitizar inputs
        const lat = document.getElementById('plat-modal-lat')?.value?.trim();
        const lng = document.getElementById('plat-modal-lng')?.value?.trim();
        
        if(!lat || !lng){
            suiteAlertWarning('warning','No coordinates available');
            return;
        }
        
        const btnRuta = document.getElementById('plat-btn-ruta');

        if(platRutaLayer){
            //Verificar si coordenadas cambiaron respecto a la última ruta
            const coordsCambiaron = (lat !== platUltimaRutaLat || lng !== platUltimaRutaLng);
            
            if(!coordsCambiaron){
                //Misma posición → toggle off (limpiar)
                platResetearVistaRuta();
                btnRuta.innerHTML = '<i class="fas fa-route"></i> View Route';
                return;
            }
            //Coordenadas cambiaron → limpiar anterior y recalcular nueva
            platResetearVistaRuta();
        }

        btnRuta.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculating...';

        //Calcular nueva ruta (código existente...)
        btnRuta.innerHTML = '<i class="fas fa-undo"></i> Clear Route';
        
        //Guardar coordenadas de esta ruta
        platUltimaRutaLat = lat;
        platUltimaRutaLng = lng;

        btnRuta.disabled = true;
        
        try{
            //Llamar a LocationIQ Directions API
            const url = `https://us1.locationiq.com/v1/directions/driving/${HQ_LNG},${HQ_LAT};${lng},${lat}?key=${apis.locationiq.key}&steps=true&alternatives=false&geometries=polyline&overview=full&radiuses=50;50&approaches=curb;curb`;
            
            const response = await fetch(url);
            const data = await response.json();
            
            if(!data.routes || data.routes.length === 0){
                suiteAlertWarning('warning','No route found between HQ and destination');
                return;
            }
            
            // ✅ Auditoría de waypoints (snap to road)
            if(data.waypoints?.length >= 2) {
                const hqOriginal = {lat: HQ_LAT, lng: HQ_LNG};
                const hqSnapped = {lat: data.waypoints[0].location[1], lng: data.waypoints[0].location[0]};
                const desplazamiento = platCalcularDistancia(hqOriginal, hqSnapped);
                
                console.log(`📏 HQ desplazado por snap-to-road: ${desplazamiento.toFixed(1)} metros`);
                
                if(desplazamiento > 100) {
                    console.warn('⚠️ Desplazamiento significativo. Considera ajustar coordenadas del HQ o usar radiuses más estricto.');
                }
            }            
            
            const route = data.routes[0];
            const geometry = route.geometry; //Polyline codificado
            
            //Decodificar y dibujar
            const decodedCoords = platDecodificarPolyline(geometry);
            platDibujarRutaEnMapa(decodedCoords, parseFloat(lat), parseFloat(lng), 2);
            
            //Mostrar info de la ruta
            const distanciaKm = (route.distance / 1000).toFixed(1);
            const tiempoMin = Math.round(route.duration / 60);
            
            const containerAddress = document.getElementById('plat-modal-display-address');
            if(containerAddress){
                containerAddress.innerHTML += `<br><small style="color:#6d1a72;"><i class="fas fa-route"></i> Route: ${distanciaKm} km, ~${tiempoMin} min</small>`;
            }
            
        } catch(error){
            console.error('Error calculating route:', error);
            suiteAlertError('error','Error calculating route. Please try again.');
        } finally {
            btnRuta.innerHTML = '<i class="fas fa-route"></i> View Route';
            btnRuta.disabled = false;
        }
    }

    async function platDeleteRutaModal(id_direccion){
        const confirmado = await suiteConfirm(arreglo_frases_g[0], arreglo_frases_g[5] + " " + id_direccion, {
            aceptar: arreglo_frases_g[6],
            cancelar: arreglo_frases_g[7]
        });

        if (!confirmado) return;
    }

    async function platVistaRutaModal(latitud, longitud){
        const lat = latitud.trim();
        const lng = longitud.trim();
        
        if(!lat || !lng){
            suiteAlertWarning('warning','No coordinates available');
            return;
        }
        
        const btnRuta = document.getElementById('plat-boton-ruta');

        btnRuta.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculating...';
        
        btnRuta.disabled = true;
        
        try{
            //Llamar a LocationIQ Directions API
            const url = `https://us1.locationiq.com/v1/directions/driving/${HQ_LNG},${HQ_LAT};${lng},${lat}?key=${apis.locationiq.key}&steps=true&alternatives=false&geometries=polyline&overview=full&radiuses=50;50&approaches=curb;curb`;
            
            const response = await fetch(url);
            const data = await response.json();
            
            if(!data.routes || data.routes.length === 0){
                suiteAlertWarning('warning','No route found between HQ and destination');
                return;
            }
            
            // ✅ Auditoría de waypoints (snap to road)
            if(data.waypoints?.length >= 2) {
                const hqOriginal = {lat: HQ_LAT, lng: HQ_LNG};
                const hqSnapped = {lat: data.waypoints[0].location[1], lng: data.waypoints[0].location[0]};
                const desplazamiento = platCalcularDistancia(hqOriginal, hqSnapped);
                
                console.log(`📏 HQ desplazado por snap-to-road: ${desplazamiento.toFixed(1)} metros`);
                
                if(desplazamiento > 100) {
                    console.warn('⚠️ Desplazamiento significativo. Considera ajustar coordenadas del HQ o usar radiuses más estricto.');
                }
            }            
            
            const route = data.routes[0];
            const geometry = route.geometry; //Polyline codificado
            
            //Decodificar y dibujar
            const decodedCoords = platDecodificarPolyline(geometry);
            platDibujarRutaEnMapa(decodedCoords, parseFloat(lat), parseFloat(lng), 1);
            
            //Mostrar info de la ruta
            const distanciaKm = (route.distance / 1000).toFixed(1);
            const tiempoMin = Math.round(route.duration / 60);
            
            const containerAddress = document.getElementById('plat-direccion-actual');
            if(containerAddress){
                containerAddress.innerHTML += `<br><small style="color:#6d1a72;"><i class="fas fa-route"></i> Route: ${distanciaKm} km, ~${tiempoMin} min</small>`;
            }
            
        } catch(error){
            console.error('Error calculating route:', error);
            suiteAlertError('error','Error calculating route. Please try again.');
        } finally {
            btnRuta.innerHTML = '<i class="fas fa-route"></i> View Route';
            btnRuta.disabled = false;
        }
    }

    /**
     * Decodifica polyline codificado (algoritmo de Google)
     */
    function platDecodificarPolyline(encoded){
        const points = [];
        let index = 0, lat = 0, lng = 0;
        
        while(index < encoded.length){
            let b, shift = 0, result = 0;
            
            do {
                b = encoded.charCodeAt(index++) - 63;
                result |= (b & 0x1f) << shift;
                shift += 5;
            } while(b >= 0x20);
            
            const dlat = ((result & 1) ? ~(result >> 1) : (result >> 1));
            lat += dlat;
            
            shift = 0;
            result = 0;
            
            do {
                b = encoded.charCodeAt(index++) - 63;
                result |= (b & 0x1f) << shift;
                shift += 5;
            } while(b >= 0x20);
            
            const dlng = ((result & 1) ? ~(result >> 1) : (result >> 1));
            lng += dlng;
            
            points.push([lat * 1e-5, lng * 1e-5]);
        }
        
        return points;
    }

    /**
     * Dibuja la ruta en el mapa y ajusta vista
     */
    let platRutaLayer = null;
    let platMarcadorHQ = null;

    function platDibujarRutaEnMapa(coords, destLat, destLng, origen){
        if (origen == 1){
            if(!platMapaInstancia) return;
            platModalMapa = platMapaInstancia;
        }else{
            if(!platModalMapa) return;
        }
        
        //Limpiar ruta anterior si existe
        if(platRutaLayer){
            platModalMapa.removeLayer(platRutaLayer);
        }
        if(platMarcadorHQ){
            platModalMapa.removeLayer(platMarcadorHQ);
        }
        
        //Dibujar línea de ruta (azul, 4px de grosor)
        platRutaLayer = L.polyline(coords, {
            color: '#0066cc',
            weight: 4,
            opacity: 0.8,
            lineJoin: 'round'
        }).addTo(platModalMapa);
        
        //Marcador del HQ (verde, draggable para ajuste manual si es necesario)
        platMarcadorHQ = L.marker([HQ_LAT, HQ_LNG], {
            draggable: false,
            title: 'HQ - Starting Point'
        }).addTo(platModalMapa);
        
        platMarcadorHQ.bindPopup('<strong>🏢 HQ</strong><br>Starting Point').openPopup();
        
        //Ajustar vista para mostrar ambos puntos con padding
        const bounds = L.latLngBounds([
            [HQ_LAT, HQ_LNG],
            [destLat, destLng]
        ]);
        
        //Expandir bounds para incluir toda la ruta si es desviada
        if(coords.length > 0){
            coords.forEach(coord => bounds.extend(coord));
        }
        
        platModalMapa.fitBounds(bounds, {
            padding: [50, 50],    //Margen en píxeles
            maxZoom: 16,          //No hacer zoom más allá de 16
            animate: true,
            duration: 1.5
        });
        
        //El usuario puede hacer zoom in/out manualmente después
    }

    //Agregar al final del script principal, antes del cierre
    function platResetearVistaRuta(){
        if(platRutaLayer){
            platModalMapa.removeLayer(platRutaLayer);
            platRutaLayer = null;
        }
        if(platMarcadorHQ){
            platModalMapa.removeLayer(platMarcadorHQ);
            platMarcadorHQ = null;
        }
        //Volver a centrar en el destino original
        const lat = document.getElementById('plat-modal-lat')?.value?.trim();
        const lng = document.getElementById('plat-modal-lng')?.value?.trim();        
        if(lat && lng && platModalMapa){
            platModalMapa.setView([parseFloat(lat), parseFloat(lng)], 16);
        }
    }

    //============================================
    //GUARDAR DIRECCIÓN
    //============================================
    async function platGuardarDireccionModal() {
        const numero = document.getElementById('plat-modal-numero')?.value;
        const calle = document.getElementById('plat-modal-calle')?.value;
        
        const sufijoSelect = document.getElementById('plat-modal-suffix');
        const sufijoOption = sufijoSelect.options[sufijoSelect.selectedIndex];
        const sufijo = sufijoOption.text;
        const sufijoAbrev = sufijoOption.title;

        const paisSelect = document.getElementById('plat-modal-pais');
        const paisOption = paisSelect.options[paisSelect.selectedIndex];
        const paisAbrev = paisOption.title;

        const id_ciudad1 = document.getElementById('plat-modal-ciudad');
        const id_ciudad = id_ciudad1.options[id_ciudad1.selectedIndex].text;
        const id_condado1 = document.getElementById('plat-modal-municipio');
        const id_condado = id_condado1.options[id_condado1.selectedIndex].text;
        const id_estado1 = document.getElementById('plat-modal-estado');
        const id_estado = id_estado1.options[id_estado1.selectedIndex].text;
        const id_pais1 = document.getElementById('plat-modal-pais');
        const id_pais = id_pais1.options[id_pais1.selectedIndex].text;
        const zip = document.getElementById('plat-modal-zip')?.value;

        const direccion = numero + ' ' + calle + ' ' + sufijoAbrev + ', ' + id_ciudad + ', ' + id_condado + ', ' + id_estado + ', ' + zip + ', ' + paisAbrev;

        const datos = {
            id_cliente: document.getElementById('id_cliente')?.value,
            direccion: direccion,
            id_pais: document.getElementById('plat-modal-pais')?.value,
            id_estado: document.getElementById('plat-modal-estado')?.value,
            id_condado: document.getElementById('plat-modal-municipio')?.value,
            id_ciudad: document.getElementById('plat-modal-ciudad')?.value,
            id_land_use: document.getElementById('plat-modal-land-use')?.value,
            id_street_suffix: document.getElementById('plat-modal-suffix')?.value,
            calle: document.getElementById('plat-modal-calle')?.value,
            numero: document.getElementById('plat-modal-numero')?.value,
            nickname: document.getElementById('plat-modal-nickname')?.value,
            latitud: document.getElementById('plat-modal-lat')?.value,
            longitud: document.getElementById('plat-modal-lng')?.value,
            zip: document.getElementById('plat-modal-zip')?.value
        };

        const errores = [];
        if (!datos.id_pais) errores.push('Select a country');
        if (!datos.id_estado) errores.push('Select a state');
        if (!datos.id_condado) errores.push('Select a county');
        if (!datos.id_ciudad) errores.push('Select a city');
        if (!datos.id_land_use) errores.push('Select a Land Use');
        if (!datos.id_street_suffix) errores.push('Select a Street Suffix');
        if (!datos.calle?.trim()) errores.push('Enter the street name');

        const landUseSelect = document.getElementById('plat-modal-land-use');
        const requiresNumber = landUseSelect?.selectedOptions[0]?.dataset.req === "1";
        if (requiresNumber && !datos.numero?.trim()) errores.push('Enter the house number');

        if (!datos.latitud || !datos.longitud) errores.push('Verify the address to get coordinates');

        if (errores.length > 0) {
            await suiteAlertError('Error', 'Complete:\n\n' + errores.join('\n'));
            return;
        }

        try {
            const response = await fetch(platRutaDireccionesAjax, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_direcciones: 'guardar_direccion',
                    paquete: datos
                })
            });

            const result = await response.json();
            $msg = result.texto;

            if (result.success) {
                await suiteAlertSuccess('Success', '✅ Address saved successfully ' . $msg);
                platCerrarModalDireccion();
                location.reload();
            } else {
                await suiteAlertError('Error', '❌ Could not be saved: ' . $msg);
            }
        } catch (error) {
            await suiteAlertError('Error', '❌ Connection error');
        }
    }

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

    // ========== BOTON DE RETORNO ==========
    retorno.addEventListener('click', () => {
        window.location.href = platRutaRetorno;
    });

    //============================================
    //INICIALIZACIÓN PRINCIPAL
    //============================================
    document.addEventListener('DOMContentLoaded', async () => {
        suiteLoading('show');

        let id_status = "<?php echo $id_status; ?>";
        let id_tratamiento = "<?php echo $id_tratamiento; ?>";

        await cargar_id_status(id_status, platRutaStatusAjax);
        await cargar_id_tratamiento(id_tratamiento, platRutaTratamientoAjax);

        document.querySelectorAll('input[required], select[required]').forEach(field => {
            field.addEventListener('input', () => {
                field.classList.remove('error');
                const label = document.querySelector(`label[for="${field.id}"]`);
                if (label) label.classList.remove('error');
            });
        });

        document.getElementById("submitBtn")?.addEventListener("click", async function(event) {
            event.preventDefault();
            if (!validateTabs()) return;

            const confirmado = await suiteConfirm(arreglo_frases_g[0], arreglo_frases_g[1], {
                aceptar: arreglo_frases_g[2],
                cancelar: arreglo_frases_g[3]
            });

            if (!confirmado) return;

            suiteLoading('show');
            const form = document.getElementById("update_cliente");
            const data = new FormData(form);

            try {
                const res = await fetch(form.action, {
                    method: form.method,
                    body: data
                });
                const json = await res.json();

                if (json.tipo === 'success') {
                    await suiteAlertSuccess("Success", json.texto);
                    window.location.href = platRutaRetorno;
                } else {
                    await suiteAlertError("Error", json.texto);
                }
            } catch (err) {
                await suiteAlertError("Error", "Submission failed");
            }
        });

        openTab(null, 'tab1');

        const tel1 = "<?php echo $telefono; ?>";
        const tel2 = "<?php echo $telefono2; ?>";
        let telefonos = [];

        if (tipo === 'N') {
            telefono1.style.display = 'flex';
            telefonos = [document.getElementById('natural-tel1-display'), document.getElementById('natural-tel2-display')];
        } else {
            telefono2.style.display = 'flex';
            telefonos = [document.getElementById('juridica-tel1-display'), document.getElementById('juridica-tel2-display')];
        }

        let val1 = 1;
        for (let tel of telefonos) {
            aplicarFormato(tel, val1 === 1 ? tel1 : tel2);
            val1++;
        }

        document.querySelectorAll('.telefono-mask').forEach(input => {
            input.addEventListener('input', function() {
                aplicarFormato(this);
            });
        });

        //Listeners para actualizar estado de botones en tiempo real
        ['plat-modal-pais', 'plat-modal-estado', 'plat-modal-municipio', 'plat-modal-ciudad',
            'plat-modal-land-use', 'plat-modal-suffix', 'plat-modal-calle', 'plat-modal-numero'
        ].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', platActualizarEstadoBotones);
                el.addEventListener('input', platActualizarEstadoBotones);
            }
        });

        document.addEventListener('click', (e) => {
            const modal = document.getElementById('plat-modal-direccion');
            if (e.target === modal) platCerrarModalDireccion();
        });

        suiteLoading('hide');
    });
</script>