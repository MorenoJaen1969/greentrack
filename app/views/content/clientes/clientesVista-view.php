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
$id_status = $row1['id_status'];
$status = $row1['status'];
$fecha_creacion = $row1['fecha_creacion'];
$notas = $row1['notas'];
$observaciones = $row1['observaciones'];
$fecha_status = $row1['fecha_status'];

$id_sexo = $row1['id_sexo'];
$cliente_foto = $row1['cliente_foto'];
$telefono = $row1['telefono'];

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

$modo_edicion = true;

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
            <input class="form-font" type="hidden" id="id_cliente" name="id_cliente" value="<?php echo $row1['id']; ?>">

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
                                    <?php echo $status; date('Y-m-d', strtotime($fecha_status)); ?> |
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
                                    <div class="photo-container_01 upload-btn">
                                        <label for="fileInput" class="custom-file-label">Select Image</label>
                                        <input type="file" multiple="" id="fileInput" name="fileInput" accept="image/*" onchange="previewImage(event)">
                                    </div>
                                    <div class="photo-container_02 image-preview">
                                        <?php
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
                                            echo '<img class="is-rounded" src="' . $ruta_img . '" alt="Imagen previa" id="imagePreview" name="imagePreview">';
                                        } else {
                                            echo '<img class="is-rounded" src="' . $r_sin_img . '" alt="Imagen previa" id="imagePreview">';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="forma02">
                        <h3>Other data</h3>
                        <div class="form-group-ct-inline">
                            <div class="form-group-ct">
                                <label for="telefono">Enter your phone number<br/><small>Format: +XX (123) 456-7890</small></label>
                                <input class="input" type="tel" id="telefono" name="telefono"
                                    pattern="\+\d{1,4}(\s|\-)?(\(\d+\))?(\s|\-)?\d{3,4}(\s|\-)?\d{3,4}(\s|\-)?\d{0,4}"
                                    placeholder="+XX (XXX) XXX-XXXX"
                                    value="<?php echo $telefono; ?>" required>
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
                                    <?php echo $status; date('Y-m-d', strtotime($fecha_status)); ?> | 
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
                                    <button type="button" class="btn btn-sm btn-primary plat-btn-add" onclick="abrirModalNuevaDireccion()">
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
                                    <?php echo $status; date('Y-m-d', strtotime($fecha_status)); ?> |
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

<!-- Scripts -->
<script src="<?= RUTA_REAL ?>/app/views/inc/js/func_comm.js?v=<?= time() ?>"></script>
<script src="<?= RUTA_REAL ?>/app/views/inc/js/jquery.min.js"></script>
<script src="<?= RUTA_REAL ?>/app/views/inc/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<!-- Script PLAT - Sistema de Mapa Aislado -->
<script>
/**
 * SISTEMA PLAT - Mapa de Direcciones de Cliente
 * Prefijo único: plat- (evita conflictos con otros mapas del sistema)
 */

// ============================================
// VARIABLES GLOBALES PLAT
// ============================================
let platMapaInstancia = null;
let platMarcadorInstancia = null;
let platMapaInicializado = false;

const platRutaClienteAjax = "<?php echo $ruta_cliente_ajax; ?>";
const platRutaStatusAjax = "<?php echo $ruta_status_ajax; ?>";
const platRutaTratamientoAjax = "<?php echo $ruta_tratamiento_ajax; ?>";
const platRutaRetorno = "<?php echo $ruta_retorno . "/" . $pagina_retorno; ?>";

// ============================================
// FRASES GLOBALES (compatibilidad)
// ============================================
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
// FUNCIONES DE PESTAÑAS (compatibilidad)
// ============================================
function openTab(evt, tabName) {
    document.querySelectorAll(".tabcontent").forEach(el => el.style.display = "none");
    document.querySelectorAll(".tablink").forEach(btn => btn.classList.remove("active"));
    
    const tab = document.getElementById(tabName);
    if (tab) {
        tab.style.display = "block";
    }
    
    if (evt && evt.currentTarget) {
        evt.currentTarget.classList.add("active");
    }

    // Inicializar mapa PLAT cuando se abre tab2
    if (tabName === 'tab2') {
        console.log('🔄 PLAT: Abriendo pestaña de direcciones...');
        setTimeout(() => {
            if (!platMapaInicializado) {
                platInicializarMapa();
            } else if (platMapaInstancia) {
                platMapaInstancia.invalidateSize();
            }
        }, 100);
    }
}

// ============================================
// FUNCIONES DE VALIDACIÓN (compatibilidad)
// ============================================
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
    const errorDiv = document.querySelector('#form-errors');
    if (errorDiv) {
        errorDiv.style.display = 'block';
        errorDiv.textContent = 'Please correct the errors in the form.';
    }
    return false;
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

function previewImage(event) {
    const reader = new FileReader();
    const imageField = document.getElementById("imagePreview");
    reader.onload = function() {
        if (reader.readyState === 2) {
            imageField.src = reader.result;
        }
    }
    reader.readAsDataURL(event.target.files[0]);
}

// ============================================
// CARGA DE SELECTS (compatibilidad)
// ============================================
async function cargar_id_status(id_status, ruta) {
    try {
        const res = await fetch(ruta, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
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
            headers: { 'Content-Type': 'application/json' },
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

// ============================================
// SISTEMA PLAT - MAPA
// ============================================

function platInicializarMapa() {
    console.log('🚀 PLAT: Iniciando mapa...');
    
    const contenedorId = 'plat-mapa-leaflet';
    const contenedor = document.getElementById(contenedorId);
    
    if (!contenedor) {
        console.error('❌ PLAT: Contenedor no encontrado');
        return;
    }

    // Verificar Leaflet
    if (typeof L === 'undefined') {
        console.error('❌ PLAT: Leaflet no cargado');
        platMostrarMapaFallback();
        return;
    }

    // Destruir instancia anterior
    if (platMapaInstancia) {
        try {
            platMapaInstancia.remove();
        } catch (e) {}
        platMapaInstancia = null;
        platMarcadorInstancia = null;
    }

    // Limpiar contenedor
    contenedor.innerHTML = '';
    contenedor.style.cssText = 'width:100%;height:100%;position:relative;';

    // Obtener coordenadas
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

    // Crear mapa
    try {
        platMapaInstancia = L.map(contenedorId, {
            center: [platLat, platLng],
            zoom: platZoom,
            zoomControl: true,
            attributionControl: true
        });

        // Capa satélite Esri
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri',
            maxZoom: 19
        }).addTo(platMapaInstancia);

        // Capa de etiquetas
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
            attribution: '',
            maxZoom: 19,
            opacity: 0.7
        }).addTo(platMapaInstancia);

        // Marcador
        platMarcadorInstancia = L.marker([platLat, platLng]).addTo(platMapaInstancia);

        // Forzar redimensionamiento
        setTimeout(() => {
            platMapaInstancia.invalidateSize();
            platMapaInicializado = true;
            console.log('✅ PLAT: Mapa inicializado');
        }, 200);

    } catch (error) {
        console.error('❌ PLAT: Error creando mapa:', error);
        platMostrarMapaFallback();
    }
}

function platMostrarMapaFallback() {
    const contenedor = document.getElementById('plat-mapa-leaflet');
    if (!contenedor) return;
    
    const primera = document.querySelector('.plat-direccion-item');
    const lat = primera ? parseFloat(primera.dataset.lat) || 30.3119 : 30.3119;
    const lng = primera ? parseFloat(primera.dataset.lng) || -95.4589 : -95.4589;
    
    contenedor.innerHTML = `
        <iframe 
            width="100%" 
            height="100%" 
            style="border:0;border-radius:4px;" 
            loading="lazy" 
            allowfullscreen 
            src="https://www.google.com/maps/embed?pb=!1m17!1m12!1m3!1d3456!2d${lng}!3d${lat}!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m2!1m1!2z${lat}N${Math.abs(lng)}W!5e0!3m2!1ses!2sus!4v1699999999999!5m2!1ses!2sus">
        </iframe>
    `;
    platMapaInicializado = true;
    console.log('✅ PLAT: Mapa fallback cargado');
}

function platSeleccionarDireccion(elemento) {
    const lat = parseFloat(elemento.dataset.lat);
    const lng = parseFloat(elemento.dataset.lng);
    const direccion = elemento.dataset.direccion || 'Sin dirección';

    if (isNaN(lat) || isNaN(lng)) {
        console.error('PLAT: Coordenadas inválidas');
        return;
    }

    // Actualizar UI
    document.querySelectorAll('.plat-direccion-item').forEach(item => {
        item.classList.remove('plat-active');
    });
    elemento.classList.add('plat-active');

    // Actualizar texto
    const spanDireccion = document.getElementById('plat-direccion-actual');
    if (spanDireccion) {
        spanDireccion.textContent = direccion;
    }

    // Actualizar mapa
    if (platMapaInstancia && platMarcadorInstancia) {
        platMarcadorInstancia.setLatLng([lat, lng]);
        platMarcadorInstancia.bindPopup(`
            <div style="font-family:Arial;min-width:180px;">
                <div style="font-weight:bold;margin-bottom:5px;">📍 Location</div>
                <div style="font-size:13px;color:#555;">${direccion}</div>
            </div>
        `).openPopup();
        platMapaInstancia.setView([lat, lng], 16, { animate: true });
    }
}

function abrirModalNuevaDireccion() {
    alert('Function to add new address - implement modal here');
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

    // Eventos de limpieza de errores
    document.querySelectorAll('input[required], select[required]').forEach(field => {
        field.addEventListener('input', () => {
            field.classList.remove('error');
            const label = document.querySelector(`label[for="${field.id}"]`);
            if (label) label.classList.remove('error');
        });
    });

    // Submit del formulario
    document.getElementById("submitBtn")?.addEventListener("click", async function(event) {
        suiteLoading('show');
        event.preventDefault();
        
        if (!validateTabs()) return;

        const confirmado = await suiteConfirm(
            arreglo_frases_g[0],
            arreglo_frases_g[1], {
                aceptar: arreglo_frases_g[2],
                cancelar: arreglo_frases_g[3]
            }
        );
        
        if (!confirmado) return;

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
            console.error("Error:", err);
            await suiteAlertError("Error", "Submission failed");
        }
    });

    openTab(null, 'tab1');
    suiteLoading('hide');
});
</script>