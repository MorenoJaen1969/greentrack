<?php
// app/views/content/clientesVista-view.php
// Vista de detalle de cliente con mapa de direcciones (Sistema PLAT - Prefijo único)

if (isset($url[0])) {
    $proceso_actual = $url[0];
} else {
    $proceso_actual = "clientesVista";
}

if (isset($url[1])) {
    $ruta_retorno = RUTA_APP . "/" . $url[1];
} else {
    $ruta_retorno = RUTA_APP . "/dashboard";
}

if (isset($url[2])) {
    $id_cliente = $url[2];
} else {
    $id_cliente = 0;
}

if (isset($url[3])) {
    $pagina_retorno = $url[3];
} else {
    $pagina_retorno = 1;
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
    // ============================================
    // VARIABLES GLOBALES
    // ============================================
    // Configuración de APIs de Geocodificación
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
            url: 'https://us1.locationiq.com/v1/search.php',
            key: 'pk.1472af9e389d1d577738a28c25b3e620',
            activa: true
        }
    };
    
    // Para el mapa del TAB 2 (visualización)
    let platMapaInstancia = null;
    let platMarcadorInstancia = null;
    let platMapaInicializado = false;
    
    // Para el modal de nueva dirección
    let platModalMapa = null;
    let platModalMarcador = null;

    // Geografia
    const platRutaPaisesAjax = "<?php echo $ruta_paises_ajax; ?>";
    const platRutaEstadosAjax = "<?php echo $ruta_estados_ajax; ?>";
    const platRutaCondadosAjax = "<?php echo $ruta_condados_ajax; ?>";
    const platRutaCiudadesAjax = "<?php echo $ruta_ciudades_ajax; ?>";
    const platRutaSuffixAjax = "<?php echo $ruta_suffix_ajax; ?>";
    const platLandUseAjax = "<?php echo $ruta_land_use_ajax; ?>";

    const platRutaClienteAjax = "<?php echo $ruta_cliente_ajax; ?>";
    const platRutaStatusAjax = "<?php echo $ruta_status_ajax; ?>";
    const platRutaTratamientoAjax = "<?php echo $ruta_tratamiento_ajax; ?>";
    const platRutaRetorno = "<?php echo $ruta_retorno . "/" . $pagina_retorno; ?>";

    // Referencias DOM
    const telefono1 = document.getElementById('phone1');
    const telefono2 = document.getElementById('phone2');
    const id_tipo_persona = "<?php echo $id_tipo_persona; ?>";
    let tipo = id_tipo_persona == 2 ? 'J' : 'N';

    // Foto elementos
    const btnCambiarFoto = document.getElementById('btn-cambiar-foto');
    const inputFotoFile = document.getElementById('input-foto-file');
    const fotoImg = document.getElementById('foto-img');
    const fotoPlaceholder = document.getElementById('foto-placeholder');
    const textoBtnFoto = document.getElementById('texto-btn-foto');
    const fotoNombre = document.getElementById('foto_nombre');

    // Frases
    let arreglo_frases_g = [
        "Are you sure",
        "Do you confirm that you want to save the changes you have made?",
        "Yes, Save",
        "Do not save",
        "Response error"
    ];

    const ruta_cliente_ajax = platRutaClienteAjax;
    const ruta_status_ajax = platRutaStatusAjax;
    const ruta_tratamiento_ajax = platRutaTratamientoAjax;
    const ruta_retorno = platRutaRetorno;

    // ============================================
    // FUNCIONES DE PESTAÑAS
    // ============================================
    function openTab(evt, tabName) {
        document.querySelectorAll(".tabcontent").forEach(el => el.style.display = "none");
        document.querySelectorAll(".tablink").forEach(btn => btn.classList.remove("active"));

        const tab = document.getElementById(tabName);
        if (tab) tab.style.display = "block";
        if (evt && evt.currentTarget) evt.currentTarget.classList.add("active");

        // Inicializar mapa del TAB 2 cuando se abre
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

    // ============================================
    // CARGA DE SELECTS (compatibilidad)
    // ============================================
    async function cargar_id_status(id_status, ruta) {
        try {
            const res = await fetch(ruta, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_status: 'crear_select', tabla: 'clientes', id_status: id_status })
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
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_tratamiento: 'crear_select', id_tratamiento: id_tratamiento })
            });
            if (res.ok) document.getElementById('id_tratamiento').innerHTML = await res.text();
        } catch (err) {
            console.error("Error al cargar Tratamiento:", err);
        }
    }

    // ============================================
    // GESTIÓN DE FOTO
    // ============================================
    btnCambiarFoto.addEventListener('click', () => inputFotoFile.click());

    inputFotoFile.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            alert('File too large. Maximum 2MB allowed.');
            inputFotoFile.value = '';
            return;
        }
        if (!file.type.startsWith('image/')) {
            alert('Only image files allowed.');
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

    // ============================================
    // SISTEMA PLAT - MAPA DEL TAB 2 (VISUALIZACIÓN)
    // ============================================
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
        contenedor.style.cssText = 'width:100%;height:100%;position:relative;';

        let platLat = 30.3119, platLng = -95.4589, platZoom = 13;
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
                attribution: 'Tiles &copy; Esri',
                maxZoom: 19
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

        contenedor.innerHTML = `<iframe width="100%" height="100%" style="border:0;border-radius:4px;" loading="lazy" allowfullscreen src="https://www.google.com/maps/embed?pb=!1m17!1m12!1m3!1d3456!2d${lng}!3d${lat}!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m2!1m1!2z${lat}N${Math.abs(lng)}W!5e0!3m2!1ses!2sus!4v1699999999999!5m2!1ses!2sus"></iframe>`;
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
            platMarcadorInstancia.bindPopup(`<div style="font-family:Arial;min-width:180px;"><div style="font-weight:bold;margin-bottom:5px;">📍 Location</div><div style="font-size:13px;color:#555;">${direccion}</div></div>`).openPopup();
            platMapaInstancia.setView([lat, lng], 16, { animate: true });
        }
    }

    // ============================================
    // SISTEMA PLAT - MODAL DE NUEVA DIRECCIÓN
    // ============================================
    
    function platAbrirModal() {
        const modal = document.getElementById('plat-modal-direccion');
        if (!modal) {
            console.error('Modal no encontrado');
            return;
        }
        
        // Mostrar modal
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        
        // Esperar a que el DOM se renderice completamente
        setTimeout(() => {
            platInicializarMapaModal();
            platLandUseModal();
            platCargarSuffixModal();

            platCargarPaisesModal();
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
        
        // Verificar que Leaflet esté cargado
        if (typeof L === 'undefined') {
            console.error('ERROR: Leaflet no cargado');
            wrapper.innerHTML = '<div style="padding: 50px; text-align: center; color: red;">Error: Leaflet no disponible</div>';
            return;
        }
        
        // Destruir instancia anterior si existe
        if (platModalMapa) {
            platModalMapa.remove();
            platModalMapa = null;
            platModalMarcador = null;
        }
        
        // Limpiar contenedor
        wrapper.innerHTML = '';
        
        // IMPORTANTE: Crear un div interno con dimensiones exactas
        // Esto es crucial para que Leaflet no se expanda
        const mapDiv = document.createElement('div');
        mapDiv.id = 'plat-modal-mapa-interno';
        mapDiv.style.cssText = 'width: 636px; height: 466px; position: relative;';
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
            
            // CAPA 1: Esri World Imagery (Satellite View)
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
                maxZoom: 19
            }).addTo(platModalMapa);
            
            // CAPA 2: Esri World Boundaries and Places (Street names overlay)
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
                attribution: '',
                maxZoom: 19,
                opacity: 0.7
            }).addTo(platModalMapa);
            
            // Evento de clic para colocar marcador
            platModalMapa.on('click', function(e) {
                platColocarMarcadorModal(e.latlng.lat, e.latlng.lng);
            });
            
            // Forzar actualización de tamaño
            platModalMapa.invalidateSize();
            
        } catch (error) {
            console.error('ERROR al crear mapa:', error);
            wrapper.innerHTML = '<div style="padding: 50px; text-align: center; color: red;">Error al cargar mapa</div>';
        }
    }

    function platColocarMarcadorModal(lat, lng) {
        if (!platModalMapa) return;
        
        // Remover marcador anterior
        if (platModalMarcador) {
            platModalMapa.removeLayer(platModalMarcador);
        }
        
        // Crear nuevo marcador
        platModalMarcador = L.marker([lat, lng], { 
            draggable: true,
            title: 'Ubicación'
        }).addTo(platModalMapa);
        
        // Actualizar campos
        document.getElementById('plat-modal-lat').value = lat.toFixed(8);
        document.getElementById('plat-modal-lng').value = lng.toFixed(8);
        document.getElementById('plat-modal-display-lat').textContent = lat.toFixed(6);
        document.getElementById('plat-modal-display-lng').textContent = lng.toFixed(6);
        
        // Popup
        platModalMarcador.bindPopup('<strong>📍 Ubicación seleccionada</strong><br>Arrastre para ajustar').openPopup();
        
        // Evento al arrastrar
        platModalMarcador.on('dragend', function(e) {
            const pos = e.target.getLatLng();
            document.getElementById('plat-modal-lat').value = pos.lat.toFixed(8);
            document.getElementById('plat-modal-lng').value = pos.lng.toFixed(8);
            document.getElementById('plat-modal-display-lat').textContent = pos.lat.toFixed(6);
            document.getElementById('plat-modal-display-lng').textContent = pos.lng.toFixed(6);
        });
    }

    // ============================================
    // SELECTS EN CASCADA DEL MODAL
    // ============================================
    async function platCargarPaisesModal() {
        const select = document.getElementById('plat-modal-pais');
        if (!select) return;
        select.innerHTML = '<option value="">Charging...</option>';
        
        try {
            const res = await fetch(platRutaPaisesAjax, 
                { 
                    method: 'POST', 
                    headers: { 
                        'Content-Type': 'application/json' 
                    }, 
                    body: JSON.stringify(
                        { 
                            modulo_paises: 'data_direcciones'
                        }
                    ) 
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
            return;
        }
        if (platModalMapa) {
            platModalMapa.flyTo([parseFloat(option.dataset.lat), parseFloat(option.dataset.lng)], parseInt(option.dataset.zoom), { animate: true, duration: 1.5 });
        }
        await platCargarEstadosModal(select.value);
    }

    async function platCargarEstadosModal(id_pais) {
        const select = document.getElementById('plat-modal-estado');
        platResetearSelectsModal(['state', 'county', 'city']);
        select.innerHTML = '<option value="">Charging...</option>';
        
        try {
            const response = await fetch(platRutaEstadosAjax, 
                { 
                    method: 'POST', 
                    headers: { 
                        'Content-Type': 'application/json' 
                    }, 
                    body: JSON.stringify(
                        { 
                            modulo_estados: 'data_direcciones',
                            id_pais: id_pais
                        }
                    ) 
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
            return;
        }
        if (platModalMapa) {
            platModalMapa.flyTo([parseFloat(option.dataset.lat), parseFloat(option.dataset.lng)], parseInt(option.dataset.zoom), { animate: true, duration: 1.5 });
        }
        await platCargarMunicipiosModal(select.value);
    }

    async function platCargarMunicipiosModal(id_estado) {
        const select = document.getElementById('plat-modal-municipio');
        platResetearSelectsModal(['county', 'city']);
        select.innerHTML = '<option value="">Charging...</option>';
        
        try {
            const response = await fetch(platRutaCondadosAjax, 
                { 
                    method: 'POST', 
                    headers: { 
                        'Content-Type': 'application/json' 
                    }, 
                    body: JSON.stringify(
                        { 
                            modulo_condados: 'data_direcciones',
                            id_estado: id_estado
                        }
                    ) 
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
            return;
        }
        if (platModalMapa) {
            platModalMapa.flyTo([parseFloat(option.dataset.lat), parseFloat(option.dataset.lng)], parseInt(option.dataset.zoom), { animate: true, duration: 1.5 });
        }
        await platCargarCiudadesModal(select.value);
    }

    async function platCargarCiudadesModal(id_condado) {
        const select = document.getElementById('plat-modal-ciudad');
        platResetearSelectsModal(['city']);
        select.innerHTML = '<option value="">Charging...</option>';
        try {
            const response = await fetch(platRutaCiudadesAjax,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(
                        {
                            modulo_ciudades: 'data_direcciones',
                            id_condado: id_condado
                        }
                    )
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
            return;
        }
        if (platModalMapa) {
            platModalMapa.flyTo([parseFloat(option.dataset.lat), parseFloat(option.dataset.lng)], parseInt(option.dataset.zoom) || 14, { animate: true, duration: 1.5 });
        }
        await platOthersSelectModal();
    }

    async function platOthersSelectModal(){
        const selectLandUse = document.getElementById('plat-modal-land-use');
        const selectSuffix = document.getElementById('plat-modal-suffix');

        // Se habilitan los demas SELECT
        selectSuffix.disabled = false;
        selectLandUse.disabled = false;
    }

    async function platLandUseModal() {
        const select = document.getElementById('plat-modal-land-use');
        platResetearSelectsOthersModal(['land_use', 'suffix']);
        select.innerHTML = '<option value="">Charging...</option>';

        try{
            const res02 = await fetch(platLandUseAjax,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(
                        {
                            modulo_landuse: 'data_direcciones'
                        }
                    )
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
                //select.disabled = false;
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
        
        if(requiresNumber) {
            numWrapper.style.visibility = 'visible';
            document.getElementById('plat-modal-numero').required = true;
        } else {
            numWrapper.style.visibility = 'hidden';
            document.getElementById('plat-modal-numero').value = ''; // Limpiamos para evitar "inventos"
        }
        platResetearSelectsInputModal(['calle', 'numero', 'nickname'], false);
    }
    
    async function platCargarSuffixModal() {
        const select = document.getElementById('plat-modal-suffix');
        platResetearSelectsOthersModal(['suffix', 'land_use']);
        select.innerHTML = '<option value="">Charging...</option>';

        try {
            const res01 = await fetch(platRutaSuffixAjax,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(
                        {
                            modulo_streetsuffix: 'data_direcciones'
                        }
                    )
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
            state: { id: 'plat-modal-estado', texto: 'state' },
            county: { id: 'plat-modal-municipio', texto: 'county' },
            city: { id: 'plat-modal-ciudad', texto: 'city' }
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
            suffix: { id: 'plat-modal-suffix', texto: 'suffix' },
            land_use: { id: 'plat-modal-land-use', texto: 'land use' }
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

    function platResetearSelectsInputModal(niveles, accion) {
        const config = {
            calle: { id: 'plat-modal-calle'},
            numero: { id: 'plat-modal-numero'},
            nickname: { id: 'plat-modal-nickname'}
        };
        niveles.forEach(nivel => {
            const conf = config[nivel];
            const select = document.getElementById(conf.id);
            if (select) {
                select.disabled = accion;
            }
        });
    }

    function platLimpiarFormularioModal() {
        document.getElementById('plat-modal-pais').value = '';
        platResetearSelectsModal(['state', 'county', 'city']);
        ['calle', 'numero', 'colonia', 'cp', 'referencias'].forEach(id => {
            const el = document.getElementById(`plat-modal-${id}`);
            if (el) el.value = '';
        });
        document.getElementById('plat-modal-principal').checked = false;
        document.getElementById('plat-modal-lat').value = '';
        document.getElementById('plat-modal-lng').value = '';
        document.getElementById('plat-modal-display-lat').textContent = '--';
        document.getElementById('plat-modal-display-lng').textContent = '--';
    }

    async function platVerificarDireccionModal() {
        await platVerificarDireccionModal()
    }

    /**
     * Geocodifica la dirección completa y centra el mapa
     */
    async function platVerificarDireccionModal() {
        // Obtener valores
        const numero = document.getElementById('plat-modal-numero')?.value;
        const calle = document.getElementById('plat-modal-calle')?.value;

        // ← CORREGIDO: Obtener el option seleccionado y su title (abreviatura)
        const sufijoSelect = document.getElementById('plat-modal-suffix');
        const sufijoOption = sufijoSelect.options[sufijoSelect.selectedIndex];
        const sufijo = sufijoOption.text;           // Nombre completo (ej: "Lane")
        const sufijoAbrev = sufijoOption.title;     // ← Abreviatura (ej: "Ln")

        const id_condado1 = document.getElementById('plat-modal-municipio');
        const id_condado = id_condado1.options[id_condado1.selectedIndex].text;

        const id_estado1 = document.getElementById('plat-modal-estado');
        const id_estado = id_estado1.options[id_estado1.selectedIndex].text;

        const id_pais1 = document.getElementById('plat-modal-pais');
        const id_pais = id_pais1.options[id_pais1.selectedIndex].text;

        // Mostrar en frontend (nombre completo para el usuario)
        const containe_address = document.getElementById('plat-modal-display-address');
        const direccion = numero + ' ' + calle + ' ' + sufijo + ', ' + id_condado + ', ' + id_estado + ', ' + id_pais;
        containe_address.innerHTML = direccion;
        
        // ← PASAR LA ABREVIATURA a la geocodificación
        await platGeocodificarDireccionRobusta(sufijoAbrev);
    }

    /**
     * Geocodifica usando la abreviatura del sufijo
     */
    async function platGeocodificarDireccionRobusta(sufijoAbrev) {
        // Obtener valores
        const numero = document.getElementById('plat-modal-numero')?.value?.trim();
        const calle = document.getElementById('plat-modal-calle')?.value?.trim();
        
        const ciudadSelect = document.getElementById('plat-modal-ciudad');
        const ciudad = ciudadSelect?.options[ciudadSelect.selectedIndex]?.text || '';
        
        const condadoSelect = document.getElementById('plat-modal-municipio');
        const condado = condadoSelect?.options[condadoSelect.selectedIndex]?.text || '';
        
        const estadoSelect = document.getElementById('plat-modal-estado');
        const estado = estadoSelect?.options[estadoSelect.selectedIndex]?.text || '';
        
        // Mostrar dirección en frontend
        const containerAddress = document.getElementById('plat-modal-display-address');
        const direccionFormateada = `${numero} ${calle} ${sufijoAbrev}, ${ciudad}, ${condado}, ${estado}`;
        if (containerAddress) {
            containerAddress.innerHTML = `<strong>Dirección:</strong> ${direccionFormateada}<br><small>Buscando coordenadas...</small>`;
        }
        
        if (!calle || !numero) return;
        
        // ESTRATEGIA 1: Dirección con ABREVIATURA (más probable que funcione)
        let query = `${numero} ${calle} ${sufijoAbrev}, ${ciudad}, ${estado}, USA`;
        console.log('Intento 1 (con abreviatura):', query);
        let coords = await platIntentarGeocodificar(query);
        
        // ESTRATEGIA 2: Sin sufijo (solo número y calle)
        if (!coords) {
            query = `${numero} ${calle}, ${ciudad}, ${estado}, USA`;
            console.log('Intento 2 (sin sufijo):', query);
            coords = await platIntentarGeocodificar(query);
        }
        
        // ESTRATEGIA 3: Solo calle y ciudad (sin número)
        if (!coords) {
            query = `${calle} ${sufijoAbrev}, ${ciudad}, ${estado}, USA`;
            console.log('Intento 3 (sin número):', query);
            coords = await platIntentarGeocodificar(query);
        }
    
        // ESTRATEGIA 4: Otras API's
        // Si Nominatim falla, usar multi-API
        if (!coords) {
            // ← USAR LAS APIs EN CASCADA
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
            
            // Mostrar info de confianza al usuario
            const containerAddress = document.getElementById('plat-modal-display-address');
            if (containerAddress) {
                const mensajeConfianza = {
                    'muy alta': '✅ Ubicación verificada (3 APIs)',
                    'alta': '✅ Ubicación confiable (2 APIs)',
                    'media': '⚠️ Ubicación aproximada',
                    'baja': '⚠️ Verificar ubicación manualmente'
                };
                
                containerAddress.innerHTML = `
                    ${direccionFormateada}<br>
                    <small style="color: ${coords.confianza === 'muy alta' || coords.confianza === 'alta' ? 'green' : 'orange'}">
                        ${mensajeConfianza[coords.confianza] || '⚠️ Verificar manualmente'}
                        ${coords.apisUsadas ? ` (${coords.apisUsadas} APIs)` : ''}
                        ${coords.diferenciaMetros ? ` - Precisión: ±${coords.diferenciaMetros.toFixed(0)}m` : ''}
                    </small>
                `;
            }
            
            // Actualizar coordenadas
            document.getElementById('plat-modal-display-lat').textContent = coords.lat.toFixed(6);
            document.getElementById('plat-modal-display-lng').textContent = coords.lng.toFixed(6);
            document.getElementById('plat-modal-lat').value = coords.lat.toFixed(8);
            document.getElementById('plat-modal-lng').value = coords.lng.toFixed(8);
        }


        // ESTRATEGIA 5: Fallback a coordenadas de ciudad
        if (!coords) {
            console.log('Fallback a coordenadas de ciudad');
            coords = platObtenerCoordenadasCiudad();
        }
    }

    /**
     * Obtiene coordenadas de la ciudad seleccionada como fallback
     */
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

    /**
     * Intenta geocodificar una query específica
     */
    async function platIntentarGeocodificar(query) {
        try {
            // Delay para respetar límites de Nominatim (1 request/segundo)
            await new Promise(resolve => setTimeout(resolve, 1100));
            
            const response = await fetch(
                `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&addressdetails=1`,
                {
                    headers: {
                        'User-Agent': 'GreenTrack Consolidation Tool/1.0',
                        'Accept-Language': 'en-US,en;q=0.9' // Preferir resultados en inglés
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

    /**
     * Consulta las 3 APIs y determina la coordenada más confiable
     * por mayoría de votos (las 2 más cercanas entre sí)
     */
    async function platGeocodificarMultiAPI(query) {
        const resultados = [];
        const ordenAPIs = ['opencage', 'geoapify', 'locationiq'];
        
        // Consultar TODAS las APIs activas
        for (const nombreAPI of ordenAPIs) {
            const config = apis[nombreAPI];
            if (!config.activa) continue;
            
            try {
                let coords = null;
                switch(nombreAPI) {
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
            
            // Pequeño delay entre requests
            await new Promise(resolve => setTimeout(resolve, 300));
        }
        
        // Si ninguna respondió, retornar null
        if (resultados.length === 0) {
            console.error('Ninguna API respondió');
            return null;
        }
        
        // Si solo una respondió, usar esa con advertencia
        if (resultados.length === 1) {
            console.warn('Solo una API respondió, usando:', resultados[0].api);
            return {
                ...resultados[0],
                confianza: 'baja',
                apisUsadas: 1
            };
        }
        
        // Si dos o más respondieron, encontrar las más cercanas entre sí
        const mejorResultado = platEncontrarMayoria(resultados);
        
        console.log(`🎯 Mejor resultado por mayoría: ${mejorResultado.api} (confianza: ${mejorResultado.confianza})`);
        return mejorResultado;
    }

    /**
     * Encuentra el par de coordenadas más cercanas entre sí
     * y retorna el promedio de ellas o el más confiable
     */
    function platEncontrarMayoria(resultados) {
        // Si solo hay 2, comparar directamente
        if (resultados.length === 2) {
            const distancia = platCalcularDistancia(resultados[0], resultados[1]);
            console.log(`Distancia entre APIs: ${distancia.toFixed(2)} metros`);
            
            // Si están muy cerca (< 100m), promediar
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
            
            // Si están lejos, usar el primero pero marcar baja confianza
            return {
                ...resultados[0],
                confianza: 'baja',
                apisUsadas: 2,
                diferenciaMetros: distancia,
                alternativa: resultados[1]
            };
        }
        
        // Si hay 3, comparar todos los pares
        const pares = [
            { a: 0, b: 1 },
            { a: 0, b: 2 },
            { a: 1, b: 2 }
        ];
        
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
        
        // Las 2 APIs más cercanas forman la mayoría
        const ganador1 = resultados[mejorPar.a];
        const ganador2 = resultados[mejorPar.b];
        const perdedor = resultados[3 - mejorPar.a - mejorPar.b]; // El índice restante
        
        console.log(`Mayoría: ${ganador1.api} + ${ganador2.api} (distancia: ${menorDistancia.toFixed(2)}m)`);
        console.log(`Descartado: ${perdedor.api}`);
        
        // Promedio de las dos más cercanas
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

    /**
     * Calcula distancia en metros entre dos coordenadas (fórmula de Haversine)
     */
    function platCalcularDistancia(punto1, punto2) {
        const R = 6371e3; // Radio de la Tierra en metros
        const φ1 = punto1.lat * Math.PI / 180;
        const φ2 = punto2.lat * Math.PI / 180;
        const Δφ = (punto2.lat - punto1.lat) * Math.PI / 180;
        const Δλ = (punto2.lng - punto1.lng) * Math.PI / 180;

        const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                Math.cos(φ1) * Math.cos(φ2) *
                Math.sin(Δλ/2) * Math.sin(Δλ/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

        return R * c; // Distancia en metros
    }

    // Implementaciones específicas de cada API:

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
                lat: coords[1],  // GeoApify devuelve [lng, lat]
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

    async function platGuardarDireccionModal() {
        const datos = {
            id_cliente: document.getElementById('id_cliente')?.value,
            id_pais: document.getElementById('plat-modal-pais')?.value,
            id_estado: document.getElementById('plat-modal-estado')?.value,
            id_condado: document.getElementById('plat-modal-municipio')?.value,
            id_ciudad: document.getElementById('plat-modal-ciudad')?.value,
            calle: document.getElementById('plat-modal-calle')?.value,
            numero: document.getElementById('plat-modal-numero')?.value,
            colonia: document.getElementById('plat-modal-colonia')?.value,
            codigo_postal: document.getElementById('plat-modal-cp')?.value,
            referencias: document.getElementById('plat-modal-referencias')?.value,
            es_principal: document.getElementById('plat-modal-principal')?.checked,
            latitud: document.getElementById('plat-modal-lat')?.value,
            longitud: document.getElementById('plat-modal-lng')?.value
        };

        const errores = [];
        if (!datos.id_pais) errores.push('Select a country');
        if (!datos.id_estado) errores.push('Select a state');
        if (!datos.id_condado) errores.push('Select a county');
        if (!datos.id_ciudad) errores.push('Select a city');
        if (!datos.calle?.trim()) errores.push('Enter the street');
        if (!datos.numero?.trim()) errores.push('Enter the number');
        if (!datos.latitud || !datos.longitud) errores.push('Click on the map to locate the address');

        if (errores.length > 0) {
            alert('Complete:\n\n' + errores.join('\n'));
            return;
        }

        try {
            const response = await fetch('/api/geographic.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(datos)
            });
            const result = await response.json();
            
            if (result.success) {
                alert('✅ Address saved');
                platCerrarModalDireccion();
                location.reload();
            } else {
                alert('❌ Error: ' + (result.error || 'It could not be saved'));
            }
        } catch (error) {
            alert('❌ Connection error');
        }
    }

    // ============================================
    // FORMATEO DE TELÉFONOS
    // ============================================
    function aplicarFormato(input, valorManual = null) {
        if (!input) return;
        if (valorManual !== null) input.value = valorManual;
        
        let numeros = input.value.replace(/\D/g, '');
        let formateado = '';
        
        if (numeros.length > 0) {
            if (numeros.length <= 2) formateado = '+' + numeros;
            else if (numeros.length <= 5) formateado = `+${numeros.substring(0, 2)} (${numeros.substring(2)}`;
            else if (numeros.length <= 8) formateado = `+${numeros.substring(0, 2)} (${numeros.substring(2, 5)}) ${numeros.substring(5)}`;
            else if (numeros.length <= 12) formateado = `+${numeros.substring(0, 2)} (${numeros.substring(2, 5)}) ${numeros.substring(5, 8)}-${numeros.substring(8)}`;
            else formateado = `+${numeros.substring(0, 2)} (${numeros.substring(2, 5)}) ${numeros.substring(5, 8)}-${numeros.substring(8, 12)}-${numeros.substring(12)}`;
        }
        
        input.value = formateado;
        const hiddenId = input.id.replace('-display', '');
        const hidden = document.getElementById(hiddenId);
        if (hidden) hidden.value = numeros;
    }

    // ============================================
    // INICIALIZACIÓN PRINCIPAL
    // ============================================
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
                const res = await fetch(form.action, { method: form.method, body: data });
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
            input.addEventListener('input', function() { aplicarFormato(this); });
        });

        // Cerrar modal al hacer clic fuera
        document.addEventListener('click', (e) => {
            const modal = document.getElementById('plat-modal-direccion');
            if (e.target === modal) platCerrarModalDireccion();
        });

        suiteLoading('hide');
    });
</script>