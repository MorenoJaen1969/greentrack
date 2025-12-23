<?php
if (isset($url[0])) {
    $proceso_actual = $url[0];
} else {
    $proceso_actual = "direccionesVista";
}
if (isset($url[1])) {
    $id_direccion = $url[1];
} else {
    $id_direccion = 0;
}
if (isset($url[2])) {
    $id_address_clas = $url[2];
} else {
    $id_address_clas = 0;
}
if (isset($url[3])) {
    $ruta_retorno = RUTA_APP . "/" . $url[3] ;
} else {
    $ruta_retorno = RUTA_APP . "/dashboard";
}
if (isset($url[4])) {
    $pagina_retorno = $url[4];
} else {
    $pagina_retorno = 1;
}

if (isset($url[5])) {
    $elem01 = $url[5];
} else {
    $elem01 = "";
}

if (isset($url[6])) {
    $elem02 = $url[6];
} else {
    $elem02 = "";
}

$row = $direcciones->direccionesCompleto($id_direccion, $id_address_clas);
$clasif_address = $address_clas->consultar_address_clas();

$total_servicios = $direcciones->cant_servicios($id_direccion);

if ($total_servicios > 0) {
    $modo_edicion = false;
} else {
    $modo_edicion = true;
}

$id_address_clas = $row['id_address_clas'];
$id_cliente = $row['id_cliente'];

$tit_address_clas = "";
foreach ($clasif_address as $value) {
    if ($value['id_address_clas'] == $id_address_clas) {
        $tit_address_clas = $value['address_clas'];
        break;
    }
}

$id_address_type = $row['id_address_type'];

$id_status = $row['id_status'];

$id_pais = $row['id_pais'];
$id_estado = $row['id_estado'];
$id_condado = $row['id_condado'];
$id_ciudad = $row['id_ciudad'];
$id_zip = $row['id_zip'];
$id_geofence = $row['id_geofence'];
$geofence_data = $row['geofence_data'];

$ruta_direcciones_ajax = RUTA_APP . "/app/ajax/direccionesAjax.php";

$ruta_paises_ajax = RUTA_APP . "/app/ajax/paisesAjax.php";
$ruta_estados_ajax = RUTA_APP . "/app/ajax/estadosAjax.php";
$ruta_condados_ajax = RUTA_APP . "/app/ajax/condadosAjax.php";
$ruta_ciudades_ajax = RUTA_APP . "/app/ajax/ciudadesAjax.php";
$ruta_zip_ajax = RUTA_APP . "/app/ajax/zipAjax.php";

$ruta_address_clas_ajax = RUTA_APP . "/app/ajax/address_clasAjax.php";
$ruta_address_type_ajax = RUTA_APP . "/app/ajax/address_typeAjax.php";
$ruta_clientes_ajax = RUTA_APP . "/app/ajax/clientesAjax.php";
$ruta_status_ajax = RUTA_APP . "/app/ajax/statusAjax.php";

$ruta_direcciones = RUTA_APP . "/direcciones/";
$encabezadoVista = PROJECT_ROOT . "/app/views/inc/encabezadoVista.php";
$opcion = "direccionesVista";
?>

<main>
    <p hidden id="ruta_direccion"><?php echo $ruta_direcciones; ?></p>
    <p hidden id="ruta_direccion_ajax"><?php echo $ruta_direcciones_ajax; ?></p>

    <?php
    require_once $encabezadoVista;
    ?>

    <div class="form-container">
        <form class="FormularioAjax form-horizontal validate-form" action="<?php echo $ruta_direcciones_ajax; ?>"
            method="POST" id="update_direccion" name="update_direccion" enctype="multipart/form-data"
            autocomplete="off">
            <input class="form-font" type="hidden" name="modulo_direcciones" value="update_direccion">
            <input class="form-font" type="hidden" id="id_direccion" name="id_direccion" value="<?php echo $row['id_direccion']; ?>">

            <div class="tab-container">
                <div class="tabs-gen">
                    <button type="button" class="tab-button active tablink" data-tab="tab1"
                        onclick="openTab(event, 'tab1')">
                        Address Details
                    </button>
                    <button type="button" class="tab-button tablink" data-tab="tab2" onclick="openTab(event, 'tab2')">
                        Notes and Observations
                    </button>
                    <button type="button" class="tab-button tablink" data-tab="tab3" onclick="openTab(event, 'tab3')">
                        Geofence
                    </button>
                </div>

                <div id="tab1" class="tabcontent">
                    <div class="form-group-ct-inline">
                        <h3>Location</h3>
                        <?php
                            if ($total_servicios> 0){
                        ?>
                            <h3 style="margin-left: auto; color: #6d1a72ff; font-weight: bold;"><label class="ancho_label1"><?php echo htmlspecialchars($total_servicios); ?> services have been performed at this address</label></h3>
                        <?php
                            }
                        ?>

                    </div>
    
                    <div class="forma01 form-font">
                        <div class="form-group-ct">
                            <!-- Dirección principal -->
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="direccion">Address:</label>
                                <input class="form-font" type="text" id="direccion" name="direccion"
                                    value="<?php echo htmlspecialchars($row['direccion']); ?>"
                                    placeholder="Geographic location"
                                    <?php echo !$modo_edicion ? 'disabled' : ''; ?>
                                    required>
                            </div>
                        </div>
                        <!-- Coordenadas -->
                        <div class="grid_geo">
                            <div class="grid_geo_01">
                                <div class="form-group-ct-inline">
                                    <div class="form-group-ct">
                                        <label for="lat" class="ancho_label1">Latitude:</label>
                                        <input class="form-font" type="number" step="any" id="lat" name="lat"
                                            value="<?php echo $row['lat'] ?? ''; ?>" placeholder="e.g. 30.3204272"
                                            <?php echo !$modo_edicion ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="form-group-ct">
                                        <label for="lng" class="ancho_label1">Longitude:</label>
                                        <input class="form-font" type="number" step="any" id="lng" name="lng"
                                            value="<?php echo $row['lng'] ?? ''; ?>" placeholder="e.g. -95.4217815"
                                            <?php echo !$modo_edicion ? 'disabled' : ''; ?>>
                                    </div>
                                <?php if (!empty($row['id_zona'])): ?>
                                    <div class="form-group-ct">
                                        <label class="ancho_label1">Assigned Zone:</label>
                                        <span class="form-font">
                                            <?php
                                            // Obtener nombre de la zona (puedes hacerlo vía AJAX o en PHP si lo cargas)
                                            echo $row['zona_nombre'];
                                            ?>
                                        </span>
                                    </div>
                                <?php endif; ?>                                    
                                </div>

                                <div class="form-group-ct-inline">
                                    <div class="form-group-ct">
                                        <label for="id_pais" class="ancho_label1">Country</label>
                                        <select class="form-control-co form-font" id="id_pais" name="id_pais" 
                                            <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                            <!-- Se llenará con JS -->
                                        </select>
                                    </div>
                                    <div class="form-group-ct">
                                        <label for="id_estado" class="ancho_label1">State</label>
                                        <select class="form-control-co form-font" id="id_estado" name="id_estado"
                                            <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                            <!-- Se llenará con JS -->
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group-ct-inline">
                                    <div class="form-group-ct">
                                        <label for="id_condado" class="ancho_label1">County</label>
                                        <select class="form-control-co form-font" id="id_condado" name="id_condado"
                                            <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                            <!-- Se llenará con JS -->
                                        </select>
                                    </div>
                                    <div class="form-group-ct">
                                        <label for="id_ciudad" class="ancho_label1">City</label>
                                        <select class="form-control-co form-font" id="id_ciudad" name="id_ciudad"
                                            <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                            <!-- Se llenará con JS -->
                                        </select>
                                    </div>

                                    <div class="form-group-ct">
                                        <label for="id_zip" class="ancho_label1">Postal Code</label>
                                        <select class="form-control-co form-font" id="id_zip" name="id_zip"
                                            <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                            <!-- Se llenará con JS -->
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="grid_geo_02">
                                <!-- Botón de verificación -->
                                <button 
                                    type="button" 
                                    id="btn-verificar-direccion"
                                    class="btn-verificar-direccion"
                                    title="Verify address using geocoding API"
                                    <?php echo !$modo_edicion ? 'disabled' : ''; ?>                                    
                                >
                                    <span class="btn-text">Verify Address</span>
                                    <img 
                                        src="/app/views/img/map.png" 
                                        alt="Geocode icon" 
                                        class="btn-icon"
                                    />
                                </button>
                            </div>
                        </div>
                    </div>

                    <h3>Clasification</h3>
                    <div class="forma02">
                        <div class="form-group-ct-inline">
                            <!-- Clasificación: Cliente o Proveedor -->
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="id_address_clas">Address Classification:</label>
                                <select class="form-control-co form-font" id="id_address_clas" name="id_address_clas" 
                                    <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                    <!-- Se llenará con JS -->
                                </select>
                            </div>

                            <!-- Relación: Cliente o Proveedor (condicional) -->
                            <div class="form-group-ct" id="bloque-cliente">
                                <label class="ancho_label1" for="id_cliente"><?php echo $tit_address_clas; ?>:</label>
                                <select class="form-control-co form-font" id="id_cliente" name="id_cliente" 
                                    <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                    <option value="">-- Select Client --</option>
                                    <!-- Opción: cargar vía AJAX o desde PHP -->
                                </select>
                            </div>
                            <div class="form-group-ct" id="bloque-proveedor" style="display:none;">
                                <label class="ancho_label1" for="id_proveedor">Provider:</label>
                                <select class="form-control-co form-font" id="id_proveedor" name="id_proveedor"
                                    <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                    <option value="">-- Select Provider --</option>
                                    <!-- Opción: cargar vía AJAX o desde PHP -->
                                </select>
                            </div>

                            <!-- Contrato (opcional) -->
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="id_contrato">Contract:</label>
                                <select class="form-control-co form-font" id="id_contrato" name="id_contrato"
                                    <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                    <option value="">-- Optional --</option>
                                    <!-- Puedes cargar contratos relacionados -->
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="forma03">
                        <div class="form-group-ct-inline">
                            <!-- Tipo de dirección -->
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="id_address_type">Address Type:</label>
                                <select class="form-control-co form-font" id="id_address_type" name="id_address_type"
                                    <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                </select>
                            </div>

                            <!-- Estado del registro -->
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="id_status">Status:</label>
                                <select class="form-control-co form-font" id="id_status" name="id_status"
                                    <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tab2" class="tabcontent" style="display:none">
                    <!-- Formulario de Destrezas -->
                    <h3>Notes and Observations</h3>
                    <div class="forma01">
                        <h3>Notes</h3>
                        <div class="form-group-ct">
                            <label class="ancho_label1" for="notas">Note:</label>
                            <textarea id="notas" name="notas">
                                <?php echo isset($row['notas']) ? htmlspecialchars(trim($row['notas'])) : ''; ?>
                            </textarea>
                        </div>
                        <div class="form-group-ct">
                            <label class="ancho_label1" for="observaciones">Observations:</label>
                            <textarea id="observaciones" name="observaciones">
                                <?php echo isset($row['observaciones']) ? htmlspecialchars(trim($row['observaciones'])) : ''; ?> 
                            </textarea>
                        </div>
                    </div>
                </div>

                <div id="tab3" class="tabcontent" style="display:none">
                    <div class="form-group-ct-inline">
                        <h3>Geofence</h3>
                        <h3 style="margin-left: auto; color: #20a905ff; font-weight: bold;"><label class="ancho_label1"><?php echo htmlspecialchars($row['direccion']); ?></label></h3>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                        <input class="form-font" type="hidden" id="id_geofence" value="<?php echo $id_geofence; ?>">
                        <input type="hidden" id="geofence-data-exists" value="<?php echo htmlspecialchars($geofence_data ?? ''); ?>">

                        <!-- Botones principales (siempre visibles) -->
                        <button type="button" id="btn-dibujar-circulo" class="button is-info is-small">Draw Circle</button>
                        <button type="button" id="btn-dibujar-poligono" class="button is-warning is-small">Draw Polygon</button>
                        <button type="button" id="btn-borrar-geofence" class="button is-danger is-small">Clear Geofence</button>

                        <!-- Botones de modo edición (inicialmente ocultos) -->
                        <button type="button" id="btn-guardar-geofence" class="button is-success is-small" style="display:none;">Save Geofence</button>
                        <button type="button" id="btn-cancelar-geofence" class="button is-light is-small" style="display:none;">Cancel</button>

                        <!-- Mensaje de estado (a la derecha) -->
                        <span id="info-geofence" style="margin-left: auto; color: #2196F3; font-weight: bold;"></span>
                    </div>

                    <div class="map-wrapper">
                        <div id="mapa-geofence" style="width: 100%; height: 100%;"></div>                         
                    </div>
                        
                    <input class="form-font" type="hidden" id="geofence-data" name="geofence_data" value="">
                </div>
            </div>

            <button type="button" class="btn-submit" id="submitBtn">
                Save Change
            </button>
        </form>
        <div id="form-errors" style="color: red; display: none;"></div>
    </div>
</main>


<script src="<?= RUTA_REAL ?>/app/views/inc/js/func_comm.js?v=<?= time() ?>"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js?v=<?= time() ?>"></script>
<script type="text/javascript">
    const formularios_ajax = document.querySelectorAll(".FormularioAjax");
    
    const elem01 = "<?php echo $elem01?>";
    const elem02 = "<?php echo $elem02?>";

    let r_retorno = "";
    if (elem01.trim().length === 0 && elem02.trim().length === 0 ) {
        r_retorno = "<?php echo $ruta_retorno . "/" . $pagina_retorno; ?>";
    } else {
        r_retorno = "<?php echo $ruta_retorno . "/" . $pagina_retorno. "/"; ?>" + elem01 + "/" + elem02;
    }
    const ruta_retorno = r_retorno;
    const ruta_paises_ajax = "<?php echo $ruta_paises_ajax; ?>";
    const ruta_estados_ajax = "<?php echo $ruta_estados_ajax; ?>";
    const ruta_condados_ajax = "<?php echo $ruta_condados_ajax; ?>";
    const ruta_ciudades_ajax = "<?php echo $ruta_ciudades_ajax; ?>";
    const ruta_zip_ajax = "<?php echo $ruta_zip_ajax; ?>";
    const ruta_address_clas_ajax = "<?php echo $ruta_address_clas_ajax; ?>";
    const ruta_address_type_ajax = "<?php echo $ruta_address_type_ajax; ?>";
    const ruta_clientes_ajax = "<?php echo $ruta_clientes_ajax; ?>";
    const ruta_status_ajax = "<?php echo $ruta_status_ajax; ?>";

    let modoGeofenceActivo = false;
    let mapaGeofenceIniciado = false;
    let mapaGeofence = null;
    let geofenceLayer = null;
    let dibujando = false;
    let polygonPoints = [];
    let polyline = null;
    const arreglo_frases_g = ["Are you sure", "Do you want to update the data of the current record?", "Yes, perform", "Do not cancel", "Response error"];

    // === ABRIR PESTAÑAS ===
    function openTab(evt, tabName) {
        document.querySelectorAll(".tabcontent").forEach(el => el.style.display = "none");
        document.querySelectorAll(".tablink").forEach(btn => btn.classList.remove("active"));
        const tab = document.getElementById(tabName);
        const submitBtn = document.getElementById('submitBtn');
        tab.style.display = "block";
        if (evt) evt.currentTarget.classList.add("active");

        if (tabName === 'tab3') {
            submitBtn.style.display = 'none';
            setTimeout(() => iniciarMapaGeofence(), 150);
        } else {
            submitBtn.style.display = 'block';
        }
    }

    // === INICIAR MAPA ===
    function iniciarMapaGeofence() {
        const lat = parseFloat(document.getElementById('lat')?.value || 30.3204272);
        const lng = parseFloat(document.getElementById('lng')?.value || -95.4217815);
        if (!mapaGeofenceIniciado) {
            crearMapaGeofence(lat, lng);
            cargarGeofenceExistente();
            configurarEventosDibujo();
            mapaGeofenceIniciado = true;
        } else {
            reajustarMapaGeofence(lat, lng);
        }
    }

    function crearMapaGeofence(lat, lng) {
        const container = document.getElementById('mapa-geofence');
        container.style.visibility = 'hidden';
        mapaGeofence = L.map('mapa-geofence', { center: [lat, lng], zoom: 16, preferCanvas: true });
        L.control.zoom({ position: 'topright' }).addTo(mapaGeofence);
        L.control.scale({ imperial: false, position: 'bottomright' }).addTo(mapaGeofence);

        const tipoMapa = (window.APP_CONFIG?.mapa_base || 'ESRI').toUpperCase();
        const tileLayer = tipoMapa === 'OSM'
            ? L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 })
            : L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, detectRetina: true });
        tileLayer.addTo(mapaGeofence);

        geofenceLayer = L.featureGroup().addTo(mapaGeofence);
        L.marker([lat, lng]).addTo(mapaGeofence).bindPopup('Ubicación actual');

        setTimeout(() => {
            container.style.visibility = 'visible';
            mapaGeofence.invalidateSize();
        }, 200);
    }

    function reajustarMapaGeofence(lat, lng) {
        if (mapaGeofence) {
            const container = document.getElementById('mapa-geofence');
            container.style.visibility = 'hidden';
            setTimeout(() => {
                container.style.visibility = 'visible';
                mapaGeofence.setView([lat, lng]);
                setTimeout(() => mapaGeofence.invalidateSize(), 50);
            }, 50);
        }
    }
    

    // === CONTROL DE BOTONES ===
    function activarModoGeofence() {
        modoGeofenceActivo = true;
        document.getElementById('btn-dibujar-circulo').disabled = true;
        document.getElementById('btn-dibujar-poligono').disabled = true;
        document.getElementById('btn-borrar-geofence').disabled = true;
        document.getElementById('btn-guardar-geofence').style.display = 'inline-block';
        document.getElementById('btn-cancelar-geofence').style.display = 'inline-block';
    }

    function desactivarModoGeofence() {
        modoGeofenceActivo = false;
        document.getElementById('btn-dibujar-circulo').disabled = false;
        document.getElementById('btn-dibujar-poligono').disabled = false;
        document.getElementById('btn-borrar-geofence').disabled = false;
        document.getElementById('btn-guardar-geofence').style.display = 'none';
        document.getElementById('btn-cancelar-geofence').style.display = 'none';
        document.getElementById('info-geofence').textContent = '';
    }

    // === EVENTOS DE DIBUJO ===
    function configurarEventosDibujo() {
        // Botones principales
        document.getElementById('btn-dibujar-circulo').addEventListener('click', () => {
            if (modoGeofenceActivo) return;
            dibujando = 'circle';
            activarModoGeofence();
            document.getElementById('info-geofence').textContent = 'Click en el mapa para colocar el centro del círculo.';
        });

        document.getElementById('btn-dibujar-poligono').addEventListener('click', () => {
            if (modoGeofenceActivo) return;
            dibujando = 'polygon';
            polygonPoints = [];
            activarModoGeofence();
            document.getElementById('info-geofence').textContent = 'Haga clic en el mapa para comenzar a dibujar. Doble clic para terminar.';
        });

        // === BOTÓN CLEAR GEOFENCE CON CONFIRMACIÓN ===
        document.getElementById('btn-borrar-geofence')?.addEventListener('click', async () => {
            // Solo permitir si no estamos en modo dibujo activo
            if (modoGeofenceActivo) return;

            const confirmado = await suiteConfirm(
                "Clear Geofence",
                "Are you sure you want to delete the current geofence?",
                {
                    aceptar: "Yes, delete",
                    cancelar: "Cancel"
                }
            );
            if (!confirmado) return;

            // Limpiar capa de geofence
            geofenceLayer?.clearLayers();

            // Limpiar campo oculto
            document.getElementById('geofence-data').value = '';

            guardarGeofenceAjax();

            // Actualizar mensaje
            document.getElementById('info-geofence').textContent = '✅ Geofence deleted successfully.';

            // Opcional: limpiar variables de dibujo parcial
            limpiarDibujoParcial();
            dibujando = false;
        });

        // Botones de edición
        document.getElementById('btn-cancelar-geofence').addEventListener('click', () => {
            geofenceLayer?.clearLayers();
            document.getElementById('geofence-data').value = '';
            limpiarDibujoParcial();
            dibujando = false;
            desactivarModoGeofence();
        });

        document.getElementById('btn-guardar-geofence').addEventListener('click', guardarGeofenceAjax);

        // Eventos del mapa
        mapaGeofence.on('click', (e) => {
            if (dibujando === 'circle') {
                dibujarCirculo(e.latlng);
                document.getElementById('info-geofence').textContent = '✅ Círculo creado. Puedes ajustar el tamaño.';
            } else if (dibujando === 'polygon') {
                agregarPuntoPoligono(e.latlng);
            }
        });

        mapaGeofence.on('dblclick', () => {
            if (dibujando === 'polygon' && polygonPoints.length >= 3) {
                finalizarPoligono();
                document.getElementById('info-geofence').textContent = '✅ Polígono creado. Puedes ajustar la forma.';
            }
        });
    }

    // === DIBUJO ===
    function dibujarCirculo(latlng) {
        geofenceLayer.clearLayers();
        const radius = 100; // o el valor que desees
        const circle = L.circle(latlng, { 
            radius: radius, 
            color: '#2196F3', 
            fillColor: '#2196F3',
            fillOpacity: 0.2 
        }).addTo(geofenceLayer);
        
        // ✅ Guardar círculo como Point + radius en properties
        const geojson = {
            type: "Feature",
            geometry: {
                type: "Point",
                coordinates: [latlng.lng, latlng.lat] // GeoJSON usa [lng, lat]
            },
            properties: {
                radius: radius,
                type: "circle"
            }
        };
        
        document.getElementById('geofence-data').value = JSON.stringify(geojson);
        document.getElementById('info-geofence').textContent = '✅ Círculo creado. Puedes ajustar el tamaño.';
        dibujando = false;
    }

    function agregarPuntoPoligono(latlng) {
        polygonPoints.push(latlng);
        L.circleMarker(latlng, { radius: 6, color: 'red', fillColor: 'red', fillOpacity: 0.8 }).addTo(mapaGeofence);
        if (polyline) mapaGeofence.removeLayer(polyline);
        if (polygonPoints.length > 1) {
            polyline = L.polyline(polygonPoints, { color: 'red', dashArray: '5, 5', opacity: 0.8 }).addTo(mapaGeofence);
        }
    }

    function finalizarPoligono() {
        if (polyline) {
            mapaGeofence.removeLayer(polyline);
            polyline = null;
        }
        const polygon = L.polygon(polygonPoints, { color: '#FF9800', fillColor: '#FF9800', fillOpacity: 0.3 }).addTo(geofenceLayer);
        guardarGeofence();
        polygonPoints = [];
        dibujando = false;
    }

    function limpiarDibujoParcial() {
        if (polyline) {
            mapaGeofence.removeLayer(polyline);
            polyline = null;
        }
        polygonPoints = [];
    }

    // === GUARDAR GEOFENCE ===
    function guardarGeofence() {
        const layers = geofenceLayer.getLayers();
        let geojson = null;

        for (let layer of layers) {
            if (layer instanceof L.Polygon && !(layer instanceof L.Rectangle)) {
                geojson = layer.toGeoJSON();
                break;
            }
            if (layer instanceof L.Circle) {
                const center = layer.getLatLng();
                const radius = layer.getRadius();
                geojson = {
                    type: "Feature",
                    geometry: { type: "Point", coordinates: [center.lng, center.lat] },
                    properties: { radius: radius, type: "circle" }
                };
                break;
            }
        }

        if (geojson) {
            document.getElementById('geofence-data').value = JSON.stringify(geojson);
        } else {
            document.getElementById('geofence-data').value = '';
        }
    }

    /**
     * Extrae el código postal de EE.UU. asumiendo que está al final de la dirección.
     * Evita confundir números de calle (como 12590) con el ZIP.
     *
     * @param {string} direccion
     * @returns {string|null}
     */
    function extraerZipDeDireccion(direccion) {
        // Buscar todos los candidatos
        const coincidencias = [...direccion.matchAll(/\b(\d{5})(?:-\d{4})?\b/g)];
        
        if (coincidencias.length === 0) {
            return null;
        }

        // Devolver el ÚLTIMO encontrado
        const ultima = coincidencias[coincidencias.length - 1];
        return ultima[1];
    }

    async function guardarGeofenceAjax() {
        // Validar que haya una geofence válida
        const layers = geofenceLayer?.getLayers() || [];
        let geofenceValida = false;

        for (let layer of layers) {
            if (layer instanceof L.Circle || (layer instanceof L.Polygon && !(layer instanceof L.Rectangle))) {
                geofenceValida = true;
                break;
            }
        }

        let opcion_act = true;

        if (!geofenceValida) {
            //Borrar geofence actual
            opcion_act = false;
            //suiteAlertError("Error", "There is no valid geofence to save. Please complete the drawing.");
            //return;
        }

        if (opcion_act == true){
            // Guardar y enviar
            guardarGeofence(); // asegura que geofence-data esté actualizado
            const id_direccion = document.getElementById('id_direccion').value;
            const geofenceData = document.getElementById('geofence-data').value;

            try {
                const res = await fetch('<?php echo $ruta_direcciones_ajax; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        modulo_direcciones: 'guardar_geofence',
                        id_direccion: id_direccion,
                        geofence_data: geofenceData,
                        accion: 'guardar'
                    })
                });
                const json = await res.json();
                if (json.tipo === 'success') {
                    await suiteAlertSuccess("Success", "Geofence saved successfully");
                    document.getElementById('info-geofence').textContent = '✅ Geofence successfully saved.';
                    setTimeout(() => desactivarModoGeofence(), 1500);
                } else {
                    await suiteAlertError("Error", json.texto);
                }
            } catch (err) {
                await suiteAlertError("Error", "Failed to save geofence: " + err.message);
            }
        } else {
            const id_direccion = document.getElementById('id_direccion').value;
            const id_geofence = document.getElementById('id_geofence').value;
            try {
                const res = await fetch('<?php echo $ruta_direcciones_ajax; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        modulo_direcciones: 'guardar_geofence',
                        id_direccion: id_direccion,
                        id_geofence: id_geofence,
                        accion: 'modificar'
                    })
                });
                const json = await res.json();
                if (json.tipo === 'success') {
                    await suiteAlertSuccess("Success", "Geofence updated");
                    document.getElementById('info-geofence').textContent = '✅ Geofence updated.';
                    setTimeout(() => desactivarModoGeofence(), 1500);
                } else {
                    await suiteAlertError("Error", json.texto);
                }
            } catch (err) {
                await suiteAlertError("Error", "Failed to save geofence: " + err.message);
            }

        }
    }


    // === CARGAR GEOFENCE EXISTENTE ===
    function cargarGeofenceExistente() {
        const geofenceGuardada = <?php echo json_encode($geofence_data ?? null); ?>;
        if (geofenceGuardada) {
            try {
                const geojson = JSON.parse(geofenceGuardada);
                let layer;

                if (geojson.properties?.type === 'circle' && geojson.properties.radius) {
                    // ✅ Reconstruir círculo
                    const coords = geojson.geometry.coordinates;
                    const latlng = L.latLng(coords[1], coords[0]); // [lng, lat] → [lat, lng]
                    layer = L.circle(latlng, { radius: geojson.properties.radius }).addTo(geofenceLayer);
                } else {
                    // Polígono u otro
                    layer = L.geoJSON(geojson).addTo(geofenceLayer);
                }

                document.getElementById('geofence-data').value = geofenceGuardada;
                document.getElementById('info-geofence').textContent = 'Geofence cargada.';
                mapaGeofence.fitBounds(geofenceLayer.getBounds());
            } catch (e) {
                console.warn('Geofence inválida:', e);
            }
        }
    }

    // === VALIDACIÓN DE FORMULARIO ===
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

    // === ENVÍO DEL FORMULARIO PRINCIPAL ===
    document.addEventListener('DOMContentLoaded', async () => {
        openTab(null, 'tab1');

        // === CARGA DE DATOS ===
        let id_pais = "<?php echo $id_pais; ?>";
        let id_estado = "<?php echo $id_estado; ?>";
        let id_condado = "<?php echo $id_condado; ?>";
        let id_ciudad = "<?php echo $id_ciudad; ?>";
        let id_zip = "<?php echo $id_zip; ?>";

        await cargarPais(id_pais, ruta_paises_ajax);
        if (id_pais) await cargarEstados(id_pais, id_estado, ruta_estados_ajax);
        if (id_estado) await cargarCondados(id_estado, id_condado, ruta_condados_ajax);
        if (id_condado) await cargarCiudades(id_condado, id_ciudad, ruta_ciudades_ajax);
        if (id_ciudad) await cargarZips(id_ciudad, id_zip, ruta_zip_ajax);

        // === BOTÓN DE VERIFICAR DIRECCIÓN ===
        const inputDireccion = document.getElementById('direccion');
        const btnVerificar = document.getElementById('btn-verificar-direccion');
        const actualizarEstadoBoton = () => {
            btnVerificar.disabled = inputDireccion.value.trim().length < 5;
        };
        inputDireccion.addEventListener('input', actualizarEstadoBoton);
        inputDireccion.addEventListener('paste', () => setTimeout(actualizarEstadoBoton, 10));
        btnVerificar.addEventListener('click', async () => {

            const direccion = inputDireccion.value.trim();
            if (direccion.length < 5) return;

            // 👇 Mostrar indicador de carga
            suiteLoading('show');

            try {
                $apiKey = "pk.1472af9e389d1d577738a28c25b3e620"
                const data_req = {
                    modulo_direcciones: 'obtener_direccion', 
                    apikey: $apiKey,
                    direccion: direccion
                };

                const resp = await fetch('<?php echo $ruta_direcciones_ajax; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data_req)
                });
                    
                const zip_sugerido = extraerZipDeDireccion(direccion);

                const data = await resp.json();
                if (data.success) {
                    const address_data = data.data[0];
                    const det_address = address_data.address;
                    document.getElementById('direccion').value = address_data.display_name;
                    document.getElementById('lat').value = address_data.lat;
                    document.getElementById('lng').value = address_data.lon;

                    const datos_geo = {
                        pais_dato: det_address.country,
                        codigo_iso2_dato: det_address.country_code,
                        estado_dato: det_address.state,
                        condado_dato:det_address.county,
                        ciudad_dato: det_address.city,
                        zip_dato: det_address.postcode,
                        zip_sugerido: zip_sugerido
                    }
                    const res = await fetch('<?php echo $ruta_direcciones_ajax; ?>', { 
                        method: 'POST', 
                        headers: { 'Content-Type': 'application/json' }, 
                        body: JSON.stringify(
                            { 
                                modulo_direcciones: 'obtener_codigos', 
                                datos_geo: datos_geo ,
                                lat: address_data.lat,
                                lng: address_data.lon
                            }
                        ) 
                    });
                    const data_id = await res.json();

                    if (data_id.success) {
                        const geo = data_id.ids;

                        id_pais = geo.id_pais;
                        id_estado = geo.id_estado;
                        id_condado = geo.id_condado;
                        id_ciudad = geo.id_ciudad;
                        id_zip = geo.id_zip;
                        zip_usado = geo.zip_usado

                        // Después de obtener los IDs
                        const zipCorregido = data_id.ids.zip_usado || address_data.address.postcode;

                        // Reemplazar el ÚLTIMO grupo de 5 dígitos (el ZIP) por el corregido
                        const direccionOriginal = address_data.display_name;
                        const partes = direccionOriginal.split(/(\b\d{5}\b)/);
                        // Encuentra el último índice que es un ZIP (5 dígitos)
                        let ultimoIndiceZip = -1;
                        for (let i = partes.length - 1; i >= 0; i--) {
                            if (/^\d{5}$/.test(partes[i])) {
                                ultimoIndiceZip = i;
                                break;
                            }
                        }
                        if (ultimoIndiceZip !== -1) {
                            partes[ultimoIndiceZip] = zipCorregido;
                            document.getElementById('direccion').value = partes.join('');
                        } else {
                            // Si no hay ZIP, simplemente usa la dirección original (poco probable)
                            document.getElementById('direccion').value = direccionOriginal;
                        }

                        await cargarPais(id_pais, ruta_paises_ajax);
                        await cargarEstados(id_pais, id_estado, ruta_estados_ajax);
                        await cargarCondados(id_estado, id_condado, ruta_condados_ajax);
                        await cargarCiudades(id_condado, id_ciudad, ruta_ciudades_ajax);
                        await cargarZips(id_ciudad, id_zip, ruta_zip_ajax);
                    }
                    suiteLoading('hide');
                } else {
                    suiteLoading('hide');
                    suiteAlertError('Error', 'Address not found or invalid.');
                }
            } catch (err) {
                suiteLoading('hide');
                suiteAlertError('Error', 'Failed to verify address.');
                console.error('Error al verificar dirección:', err);
            }
        });

        // === CARGA DE SELECTS ADICIONALES ===
        const id_address_clas = "<?php echo $id_address_clas; ?>";
        const id_address_type = "<?php echo $id_address_type; ?>";
        const id_cliente = "<?php echo $id_cliente; ?>";
        const id_status = "<?php echo $id_status; ?>";
        await cargar_id_address_clas(id_address_clas, ruta_address_clas_ajax);
        await cargar_id_address_type(id_address_clas, id_address_type, ruta_address_type_ajax);
        await cargar_id_cliente(id_cliente, ruta_clientes_ajax);
        await cargar_id_status(id_status, ruta_status_ajax);

        // === LÓGICA CLIENTE/PROVEEDOR ===
        const clasificacion = document.getElementById('id_address_clas');
        const bloqueCliente = document.getElementById('bloque-cliente');
        const bloqueProveedor = document.getElementById('bloque-proveedor');
        function toggleRelacion() {
            if (clasificacion.value == '1') {
                bloqueCliente.style.display = 'block';
                bloqueProveedor.style.display = 'none';
            } else if (clasificacion.value == '3') {
                bloqueCliente.style.display = 'none';
                bloqueProveedor.style.display = 'block';
            } else {
                bloqueCliente.style.display = 'none';
                bloqueProveedor.style.display = 'none';
            }
        }
        clasificacion?.addEventListener('change', toggleRelacion);
        toggleRelacion();

        // === EVENTOS DE CASCADA ===
        document.getElementById('id_pais')?.addEventListener('change', async function() {
            const id_pais = this.value;
            if (id_pais) {
                await cargarEstados(id_pais, '', ruta_estados_ajax);
                document.getElementById('id_condado').innerHTML = '<option value="">-- Select County --</option>';
                document.getElementById('id_ciudad').innerHTML = '<option value="">-- Select City --</option>';
                document.getElementById('id_zip').innerHTML = '<option value="">-- Select Postal Code --</option>';
            }
        });

        document.getElementById('id_estado')?.addEventListener('change', async function() {
            const id_estado = this.value;
            if (id_estado) {
                await cargarCondados(id_estado, '', ruta_condados_ajax);
                document.getElementById('id_ciudad').innerHTML = '<option value="">-- Select City --</option>';
                document.getElementById('id_zip').innerHTML = '<option value="">-- Select Postal Code --</option>';
            }
        });

        document.getElementById('id_condado')?.addEventListener('change', async function() {
            const id_condado = this.value;
            if (id_condado) {
                await cargarCiudades(id_condado, '', ruta_ciudades_ajax);
                document.getElementById('id_zip').innerHTML = '<option value="">-- Select Postal Code --</option>';
            }
        });

        document.getElementById('id_ciudad')?.addEventListener('change', async function() {
            const id_ciudad = this.value;
            if (id_ciudad) {
                await cargarZips(id_ciudad, '', ruta_zip_ajax);
            }
        });

        // === LIMPIAR ERRORES AL ESCRIBIR ===
        document.querySelectorAll('input[required], select[required]').forEach(field => {
            field.addEventListener('input', () => {
                field.classList.remove('error');
                const label = document.querySelector(`label[for="${field.id}"]`);
                if (label) label.classList.remove('error');
            });
        });

        // === MANEJO DEL BOTÓN PRINCIPAL ===
        document.getElementById("submitBtn")?.addEventListener("click", async function(event) {
            event.preventDefault();
            if (!validateTabs()) return;

            const confirmado = await suiteConfirm(
                arreglo_frases_g[0],
                arreglo_frases_g[1],
                { aceptar: arreglo_frases_g[2], cancelar: arreglo_frases_g[3] }
            );
            if (!confirmado) return;

            const form = document.getElementById("update_direccion");
            const data = new FormData(form);

            try {
                const res = await fetch(form.action, { method: form.method, body: data });
                const text = await res.text();
                const json = JSON.parse(text);
                if (json.tipo === 'success') {
                    // ✅ Reiniciar el estado de cambios tras guardar con éxito
//                    formInitialData = captureFormState();
//                    formHasUnsavedChanges = false;

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

        // Verificar si ya existe una geofence
        const geofenceExists = document.getElementById('geofence-data-exists').value.trim() !== '';

        if (geofenceExists) {
            document.getElementById('btn-dibujar-circulo').disabled = true;
            document.getElementById('btn-dibujar-poligono').disabled = true;
            // Opcional: agregar tooltip o mensaje
            document.getElementById('info-geofence').textContent = 'Geofence existente. Borre la actual para crear una nueva.';
        }

    });

    // === FUNCIONES DE CARGA DE SELECTS ===
    async function cargarPais(id_pais, ruta) {
        try {
            const res = await fetch(ruta, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ modulo_paises: 'crear_select', id_pais: id_pais }) });
            if (res.ok) document.getElementById('id_pais').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar países:", err); }
    }

    async function cargarEstados(id_pais, id_estado_seleccionado = '', ruta) {
        try {
            const res = await fetch(ruta, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ modulo_estados: 'crear_select', id_pais: id_pais, id_estado: id_estado_seleccionado }) });
            if (res.ok) document.getElementById('id_estado').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar estados:", err); }
    }

    async function cargarCondados(id_estado, id_condado_seleccionado = '', ruta) {
        try {
            const res = await fetch(ruta, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ modulo_condados: 'crear_select', id_estado: id_estado, id_condado: id_condado_seleccionado }) });
            if (res.ok) document.getElementById('id_condado').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar condados:", err); }
    }

    async function cargarCiudades(id_condado, id_ciudad_seleccionado = '', ruta) {
        try {
            const res = await fetch(ruta, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ modulo_ciudades: 'crear_select', id_condado: id_condado, id_ciudad: id_ciudad_seleccionado }) });
            if (res.ok) document.getElementById('id_ciudad').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar ciudades:", err); }
    }

    async function cargarZips(id_ciudad, id_zip_seleccionado = '', ruta) {
        try {
            const res = await fetch(ruta, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ modulo_zips: 'crear_select', id_ciudad: id_ciudad, id_zip: id_zip_seleccionado }) });
            if (res.ok) document.getElementById('id_zip').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar códigos postales:", err); }
    }

    async function cargar_id_address_clas(id_address_clas, ruta) {
        try {
            const res = await fetch(ruta, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ modulo_address_clas: 'crear_select', id_address_clas: id_address_clas }) });
            if (res.ok) document.getElementById('id_address_clas').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Address Clas:", err); }
    }

    async function cargar_id_address_type(id_address_clas, id_address_type, ruta) {
        try {
            const res = await fetch(ruta, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ modulo_address_type: 'crear_select', id_address_clas: id_address_clas, id_address_type: id_address_type }) });
            if (res.ok) document.getElementById('id_address_type').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Address Type:", err); }
    }

    async function cargar_id_cliente(id_cliente, ruta) {
        try {
            const res = await fetch(ruta, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ modulo_clientes: 'crear_select', id_cliente: id_cliente }) });
            if (res.ok) document.getElementById('id_cliente').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Clients:", err); }
    }

    async function cargar_id_status(id_status, ruta) {
        try {
            const res = await fetch(ruta, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ modulo_status: 'crear_select', tabla: 'direcciones', id_status: id_status }) });
            if (res.ok) document.getElementById('id_status').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Status:", err); }
    }
</script>