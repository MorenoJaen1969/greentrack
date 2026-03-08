<?php
// app/views/content/rutas_mapa-view.php
// Ruta Mapa - GreenTrack Live (Conroe Style)
// Versión 2.1 - Con correcciones completas

$ruta_rutas_mapa_ajax = RUTA_APP . "/app/ajax/rutas_mapaAjax.php";
?>

<!-- Contenedor Principal -->
<div id="rutas-map-container" class="principal">

    <!-- Barra de título superior -->
    <div id="rutas-title-bar" style="background-color: #2c3e50; color: white; padding: 10px; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0; padding-left: 40px; font-size: 1.5em;">Ruta Map - GreenTrack Live</h2>
        <div>
            <button id="btn-volver" style="margin-right: 10px; padding: 5px 10px;">Back</button>
            <span>Hey there, <?php echo $_SESSION['user_name'] ?? 'Partner'; ?>!</span>
        </div>
    </div>

    <!-- Contenedor principal con panel lateral y mapa -->
    <div style="display: flex; flex: 1; overflow: hidden; height: calc(100vh - 100px);">

        <!-- Panel Lateral -->
        <div id="rutas-sidebar" style="width: 300px; background-color: #ecf0f1; padding: 15px; overflow-y: auto; border-right: 1px solid #bdc3c7; display: flex; flex-direction: column;">

            <!-- Indicador de Estado -->
            <div id="estado-indicador" style="text-align: center; margin-bottom: 15px; padding: 8px; border-radius: 4px; font-weight: bold; font-size: 0.9em; background-color: #95a5a6; color: white;">
                Ready
            </div>

            <!-- Sección: Crear/Editar Ruta -->
            <div id="seccion-ruta-builder" style="margin-bottom: 15px;">
                <h4>Route Builder</h4>

                <label for="input-nombre-ruta">Route Name:</label>
                <input type="text" id="input-nombre-ruta" placeholder="Like 'Monday Route - Conroe'"
                    style="width: 100%; padding: 5px; margin-bottom: 10px;" disabled>

                <label for="input-color-ruta">Route Color:</label>
                <input type="color" id="input-color-ruta" value="#3498db"
                    style="width: 100%; margin-bottom: 10px; height: 40px; cursor: pointer;" disabled>

                <div id="builder-buttons">
                    <button id="btn-crear-ruta" style="width: 100%; padding: 10px; margin-bottom: 5px; background-color: #27ae60; color: white; border: none; cursor: pointer;">Create New Route</button>
                    <button id="btn-cancelar-accion" style="width: 100%; padding: 10px; margin-bottom: 10px; background-color: #95a5a6; color: white; border: none; cursor: pointer; display: none;">Cancel</button>
                    <button id="btn-guardar-ruta" style="width: 100%; padding: 10px; margin-bottom: 5px; background-color: #f39c12; color: white; border: none; cursor: pointer; display: none;">Save Route</button>
                </div>
            </div>

            <hr style="margin: 15px 0;">

            <!-- Sección: Rutas Existentes -->
            <div id="seccion-rutas-existentes" style="flex: 1; display: flex; flex-direction: column; min-height: 150px;">
                <h4>Existing Routes</h4>
                <div id="rutas-lista" style="flex: 1; overflow-y: auto; border: 1px solid #ccc; padding: 5px; background-color: #fff;">
                    <p style="color: #7f8c8d; font-style: italic;">Loading routes...</p>
                </div>
            </div>

            <hr style="margin: 15px 0;">

            <!-- Sección: Zonas Seleccionadas (solo en creación/edición) -->
            <div id="seccion-zonas-seleccionadas" style="display: none;">
                <h4>Selected Zones (<span id="contador-zonas">0</span>)</h4>
                <div id="zonas-seleccionadas-lista" style="max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 5px; background-color: #fff;">
                </div>
            </div>

        </div>

        <!-- Contenedor del Mapa -->
        <div id="mapa_geo" style="flex: 1; position: relative; z-index: 1;"></div>

    </div>

    <!-- Footer -->
    <div id="rutas-footer" style="background-color: #bdc3c7; padding: 5px; text-align: center; font-size: 0.9em;">
        GreenTrack Live — Built for Conroe, TX
    </div>

</div>

<!-- Formulario Movible (creado dinámicamente) -->

<!-- Scripts -->
<script>
    // ============================================
    // CONFIGURACIÓN Y CONSTANTES
    // ============================================

    const RUTA_AJAX = "<?php echo $ruta_rutas_mapa_ajax; ?>";
    const HQ_COORDS = [30.3204272, -95.4217815];
    const HQ_ICON_SVG = `
    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="-16 -16 32 32">
        <polygon points="0,-14 12,7 -12,7" fill="#0066FF" stroke="#003d99" stroke-width="1"/>
        <polygon points="0,14 12,-7 -12,-7" fill="#0066FF" stroke="#003d99" stroke-width="1"/>
    </svg>
`;

    // Estados del sistema
    const ESTADOS = {
        NEUTRO: 'NEUTRO', // Inicial, sin ruta seleccionada
        ADDRESSES: 'ADDRESSES', // Mostrando todas las direcciones
        RUTA_VER: 'RUTA_VER', // Viendo ruta existente (solo lectura)
        RUTA_EDITAR: 'RUTA_EDITAR' // Editando ruta existente
    };

    // ============================================
    // VARIABLES GLOBALES
    // ============================================

    let map = null;
    let estadoActual = ESTADOS.NEUTRO;
    let rutaActual = null; // Ruta cargada (en VER o EDITAR)
    let zonasData = new Map(); // Cache de zonas (id -> datos)
    let direccionesRutaActual = []; // Direcciones de la ruta activa

    // LayerGroups
    let capaZonas = null;
    let capaDireccionesRuta = null; // Direcciones de ruta actual (colores)
    let capaDireccionesLibres = null; // Direcciones sin ruta (rojas)
    let capaTodasDirecciones = null; // Para modo ADDRESSES
    let marcadorHQ = null;

    // Creación de ruta (estado temporal)
    let rutaEnConstruccion = {
        nombre: '',
        color: '#3498db',
        zonas: new Map() // id_zona -> {direcciones: Set(id_direccion)}
    };

    // ============================================
    // INICIALIZACIÓN
    // ============================================

    document.addEventListener('DOMContentLoaded', function() {
        inicializarMapa();
        configurarEventListeners();
        cargarRutasExistentes();
        cargarTodasZonas();
    });

    function inicializarMapa() {
        const contenedor = document.getElementById('mapa_geo');
        if (!contenedor) {
            console.error('Contenedor del mapa no encontrado');
            return;
        }

        // Crear mapa centrado en HQ
        map = L.map(contenedor).setView(HQ_COORDS, 13);

        // Capa base (ESRI por defecto, configurable)
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri',
            maxZoom: 20
        }).addTo(map);

        // Inicializar LayerGroups
        capaZonas = L.layerGroup().addTo(map);
        capaDireccionesRuta = L.layerGroup();
        capaDireccionesLibres = L.layerGroup();
        capaTodasDirecciones = L.layerGroup();

        // Agregar HQ
        marcadorHQ = L.marker(HQ_COORDS, {
            icon: L.divIcon({
                html: HQ_ICON_SVG,
                className: 'sede-marker',
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            })
        }).addTo(map).bindPopup('<b>Sergio\'s Landscape</b><br>Headquarters • Starting Point');

        // Control personalizado: Botón Addresses
        const btnAddresses = L.control({
            position: 'topleft'
        });
        btnAddresses.onAdd = function() {
            const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
            div.innerHTML = '<button id="leaflet-btn-addresses" style="padding: 6px 10px; background: white; border: 2px solid rgba(0,0,0,0.2); border-radius: 4px; cursor: pointer; font-weight: bold;">📍 Addresses</button>';
            L.DomEvent.disableClickPropagation(div);
            return div;
        };
        btnAddresses.addTo(map);

        // Evento del botón Addresses
        document.getElementById('leaflet-btn-addresses').addEventListener('click', toggleModoAddresses);
    }

    // ============================================
    // GESTIÓN DE ESTADOS
    // ============================================

    function cambiarEstado(nuevoEstado, datos = {}) {
        console.log(`🔄 Transición: ${estadoActual} → ${nuevoEstado}`);

        const estadoAnterior = estadoActual;
        estadoActual = nuevoEstado;

        // Limpiar según transición
        if (estadoAnterior === ESTADOS.ADDRESSES && nuevoEstado !== ESTADOS.ADDRESSES) {
            limpiarModoAddresses();
        }

        if (estadoAnterior === ESTADOS.RUTA_VER && nuevoEstado === ESTADOS.NEUTRO) {
            limpiarRutaActual();
        }

        // Configurar nuevo estado
        switch (nuevoEstado) {
            case ESTADOS.NEUTRO:
                configurarUI_Neutro();
                break;
            case ESTADOS.ADDRESSES:
                configurarUI_Addresses();
                break;
            case ESTADOS.RUTA_VER:
                rutaActual = datos.ruta || null;
                configurarUI_RutaVer();
                break;
            case ESTADOS.RUTA_EDITAR:
                configurarUI_RutaEditar();
                break;
        }

        actualizarIndicadorEstado();
    }

    // ============================================
    // CONFIGURACIONES DE UI POR ESTADO
    // ============================================

    function configurarUI_Neutro() {
        // Limpiar selecciones
        rutaEnConstruccion.zonas.clear();
        rutaEnConstruccion.nombre = '';

        // Resetear inputs
        document.getElementById('input-nombre-ruta').value = '';
        document.getElementById('input-nombre-ruta').disabled = true;
        document.getElementById('input-color-ruta').disabled = true;

        // Botones
        document.getElementById('btn-crear-ruta').style.display = 'block';
        document.getElementById('btn-crear-ruta').textContent = 'Create New Route';
        document.getElementById('btn-crear-ruta').onclick = iniciarCreacionRuta;        
        document.getElementById('btn-cancelar-accion').style.display = 'none';
        document.getElementById('btn-guardar-ruta').style.display = 'none';

        // Ocultar sección zonas seleccionadas
        document.getElementById('seccion-zonas-seleccionadas').style.display = 'none';

        // Limpiar formulario movible si existe
        destruirFormularioMovible();

        // Resetear visual de zonas
        capaZonas.eachLayer(layer => {
            layer.setStyle({
                color: '#3388ff',
                weight: 1,
                fillOpacity: 0.1,
                fillColor: '#3388ff'
            });
            layer.off('click');
            layer.on('click', () => {
                // En neutro, mostrar popup informativo
                layer.openPopup();
            });
        });

        // Limpiar capas de direcciones
        map.removeLayer(capaDireccionesRuta);
        map.removeLayer(capaDireccionesLibres);
        capaDireccionesRuta.clearLayers();
        capaDireccionesLibres.clearLayers();
    }

    function configurarUI_Addresses() {
        // Cargar todas las direcciones con contrato
        cargarTodasDireccionesAddresses();

        // Botón Addresses activo
        const btn = document.getElementById('leaflet-btn-addresses');
        if (btn) btn.style.backgroundColor = '#a3d2a3';

        // Zonas en modo decorativo (no coloreadas, clickeables para popup)
        capaZonas.eachLayer(layer => {
            layer.setStyle({
                color: '#3388ff',
                weight: 1,
                fillOpacity: 0.05,
                fillColor: '#3388ff'
            });
        });
    }

    function configurarUI_RutaVer() {
        if (!rutaActual) return;

        // Inputs con datos de ruta (deshabilitados)
        document.getElementById('input-nombre-ruta').value = rutaActual.nombre_ruta;
        document.getElementById('input-nombre-ruta').disabled = true;
        document.getElementById('input-color-ruta').value = rutaActual.color_ruta;
        document.getElementById('input-color-ruta').disabled = true;

        // Botones: Update y Delete visibles, Save oculto
        document.getElementById('btn-crear-ruta').style.display = 'none';
        document.getElementById('btn-cancelar-accion').style.display = 'none';
        document.getElementById('btn-guardar-ruta').style.display = 'block';
        document.getElementById('btn-guardar-ruta').textContent = 'Update This Route';
        document.getElementById('btn-guardar-ruta').style.backgroundColor = '#f39c12';
        document.getElementById('btn-guardar-ruta').onclick = entrarModoEdicion;

        // Ocultar sección zonas seleccionadas (no aplica en modo ver)
        document.getElementById('seccion-zonas-seleccionadas').style.display = 'none';

        // Cargar visual de ruta
        mostrarRutaEnMapa(rutaActual, 'tenue');

        // Crear formulario movible
        crearFormularioMovible(rutaActual);
    }

    function configurarUI_RutaEditar() {
        if (!rutaActual) return;

        // Habilitar inputs
        document.getElementById('input-nombre-ruta').disabled = false;
        document.getElementById('input-color-ruta').disabled = false;

        // Botones: Save y Cancel visibles
        document.getElementById('btn-crear-ruta').style.display = 'none';
        document.getElementById('btn-cancelar-accion').style.display = 'block';
        document.getElementById('btn-guardar-ruta').style.display = 'block';
        document.getElementById('btn-guardar-ruta').textContent = '💾 Save Changes';
        document.getElementById('btn-guardar-ruta').style.backgroundColor = '#27ae60';
        document.getElementById('btn-guardar-ruta').onclick = guardarCambiosRuta;

        // Mostrar sección de zonas para edición
        document.getElementById('seccion-zonas-seleccionadas').style.display = 'block';
        actualizarListaZonasSeleccionadas();

        // Cargar direcciones libres además de las de la ruta
        cargarDireccionesLibres();

        // IMPORTANTE: Configurar interacción de zonas
        const idsZonasEnRuta = rutaActual.zonas.map(z => z.id_zona);
        
        capaZonas.eachLayer(layer => {
            const estaEnRuta = idsZonasEnRuta.includes(layer.zonaId);
            
            // Limpiar eventos previos
            layer.off('click');
            
            if (estaEnRuta) {
                // Zona en ruta: click para EDITAR sus direcciones
                layer.setStyle({
                    color: rutaActual.color_ruta,
                    weight: 3,
                    fillOpacity: 0.4
                });
                
                layer.on('click', (e) => {
                    L.DomEvent.stopPropagation(e);
                    abrirModalZonaEdicion(layer);
                });
                
            } else {
                // Zona NO en ruta: click para AGREGAR
                layer.setStyle({
                    color: '#3388ff',
                    weight: 2,
                    fillOpacity: 0.2
                });
                
                layer.on('click', (e) => {
                    L.DomEvent.stopPropagation(e);
                    abrirModalZonaNueva(layer);
                });
            }
        });
        
        // Recrear formulario con botones de eliminar
        crearFormularioMovible(rutaActual);
        
        actualizarIndicadorEstado();
    }

    // ============================================
    // CARGA DE DATOS (AJAX)
    // ============================================

    async function cargarRutasExistentes() {
        try {
            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_rutas: 'listar_rutas'
                })
            });
            const data = await response.json();

            const container = document.getElementById('rutas-lista');
            if (data.success && data.data.length > 0) {
                container.innerHTML = '';
                data.data.forEach(ruta => {
                    const div = document.createElement('div');
                    div.className = 'ruta-item';
                    div.style.cssText = 'padding: 8px; cursor: pointer; border-bottom: 1px solid #eee; display: flex; align-items: center;';
                    div.innerHTML = `<span style="display: inline-block; width: 12px; height: 12px; background: ${ruta.color_ruta}; margin-right: 8px; border-radius: 2px;"></span>
                                <div>
                                    <div style="font-weight: bold;">${ruta.nombre_ruta}</div>
                                    <div style="font-size: 0.85em; color: #666;">${ruta.total_direcciones} addresses</div>
                                </div>`;
                    div.onclick = () => seleccionarRutaExistente(ruta.id_ruta);
                    container.appendChild(div);
                });
            } else {
                container.innerHTML = '<p style="color: #7f8c8d; font-style: italic;">No routes found</p>';
            }
        } catch (err) {
            console.error('Error cargando rutas:', err);
        }
    }
 
    async function cargarTodasZonas() {
        try {
            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_rutas: 'listar_todas_zonas'
                })
            });
            const data = await response.json();

            if (data.success) {
                capaZonas.clearLayers();
                zonasData.clear();

                data.data.forEach(zona => {
                    const bounds = [
                        [zona.lat_sw, zona.lng_sw],
                        [zona.lat_ne, zona.lng_ne]
                    ];
                    const rect = L.rectangle(bounds, {
                        color: '#3388ff',
                        weight: 1,
                        fillOpacity: 0.1,
                        fillColor: '#3388ff'
                    }).addTo(capaZonas);

                    rect.zonaId = zona.id_zona;
                    rect.zonaNombre = zona.nombre_zona;
                
                    // POPUP CON INFORMACIÓN GEOGRÁFICA COMPLETA
                    const popupContent = `
                        <div style="min-width: 220px; font-family: Arial, sans-serif;">
                            <h4 style="margin: 0 0 10px 0; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 6px; font-size: 1.1em;">
                                📍 ${zona.nombre_zona}
                            </h4>
                            <table style="font-size: 0.9em; width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 3px 0; color: #7f8c8d; width: 80px;"><b>Zone ID:</b></td>
                                    <td style="padding: 3px 0;">${zona.id_zona}</td>
                                </tr>
                                ${zona.ciudad ? `
                                <tr>
                                    <td style="padding: 3px 0; color: #7f8c8d;"><b>City:</b></td>
                                    <td style="padding: 3px 0;">${zona.ciudad}</td>
                                </tr>` : ''}
                                ${zona.condado ? `
                                <tr>
                                    <td style="padding: 3px 0; color: #7f8c8d;"><b>County:</b></td>
                                    <td style="padding: 3px 0;">${zona.condado}</td>
                                </tr>` : ''}
                                ${zona.estado ? `
                                <tr>
                                    <td style="padding: 3px 0; color: #7f8c8d;"><b>State:</b></td>
                                    <td style="padding: 3px 0;">${zona.estado} ${zona.estado_abrev ? `(${zona.estado_abrev})` : ''}</td>
                                </tr>` : ''}
                                ${zona.pais ? `
                                <tr>
                                    <td style="padding: 3px 0; color: #7f8c8d;"><b>Country:</b></td>
                                    <td style="padding: 3px 0;">${zona.pais} ${zona.codigo_iso2 ? `<span style="font-size: 0.85em; color: #95a5a6;">(${zona.codigo_iso2})</span>` : ''}</td>
                                </tr>` : ''}
                            </table>
                            <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #ecf0f1; font-size: 0.8em; color: #7f8c8d; font-family: monospace;">
                                <div style="margin-bottom: 3px;">
                                    <b>SW:</b> ${parseFloat(zona.lat_sw).toFixed(6)}, ${parseFloat(zona.lng_sw).toFixed(6)}
                                </div>
                                <div>
                                    <b>NE:</b> ${parseFloat(zona.lat_ne).toFixed(6)}, ${parseFloat(zona.lng_ne).toFixed(6)}
                                </div>
                            </div>
                        </div>
                    `;
                    
                    rect.bindPopup(popupContent);
                zonasData.set(zona.id_zona, zona);
            });
        }
    } catch (err) {
        console.error('Error cargando zonas:', err);
        // Fallback: cargar zonas básicas si el endpoint nuevo no existe
        cargarTodasZonasBasico();
    }
}

    // Fallback si el endpoint completo no existe aún
    async function cargarTodasZonasBasico() {
        try {
            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({modulo_rutas: 'listar_todas_zonas'})
            });
            const data = await response.json();
            
            if (data.success) {
                capaZonas.clearLayers();
                zonasData.clear();
                
                data.data.forEach(zona => {
                    const bounds = [[zona.lat_sw, zona.lng_sw], [zona.lat_ne, zona.lng_ne]];
                    const rect = L.rectangle(bounds, {
                        color: '#3388ff',
                        weight: 1,
                        fillOpacity: 0.1,
                        fillColor: '#3388ff'
                    }).addTo(capaZonas);
                    
                    rect.zonaId = zona.id_zona;
                    rect.zonaNombre = zona.nombre_zona;
                    
                    // Popup básico
                    rect.bindPopup(`
                        <div style="min-width: 200px;">
                            <h4 style="margin: 0 0 8px 0; color: #2c3e50; border-bottom: 1px solid #3498db; padding-bottom: 4px;">
                                ${zona.nombre_zona}
                            </h4>
                            <table style="font-size: 0.9em; width: 100%;">
                                <tr><td><b>Zone ID:</b></td><td>${zona.id_zona}</td></tr>
                                ${zona.county ? `<tr><td><b>County:</b></td><td>${zona.county}</td></tr>` : ''}
                                ${zona.state ? `<tr><td><b>State:</b></td><td>${zona.state}</td></tr>` : ''}
                                ${zona.country ? `<tr><td><b>Country:</b></td><td>${zona.country}</td></tr>` : ''}
                                <tr><td colspan="2" style="padding-top: 8px; border-top: 1px solid #eee; font-size: 0.85em; color: #7f8c8d;">
                                    📍 ${zona.lat_sw?.toFixed(4)}, ${zona.lng_sw?.toFixed(4)}<br>
                                    📍 ${zona.lat_ne?.toFixed(4)}, ${zona.lng_ne?.toFixed(4)}
                                </td></tr>
                            </table>
                        </div>
                    `);
                    
                    zonasData.set(zona.id_zona, zona);
                });
            }
        } catch (err) {
            console.error('Error cargando zonas básicas:', err);
        }
    }

    async function cargarTodasDireccionesAddresses() {
        try {
            mostrarLoading(true);

            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_rutas: 'listar_todas_direcciones_contrato'
                })
            });
            const data = await response.json();

            capaTodasDirecciones.clearLayers();

            if (data.success) {
                data.data.forEach(dir => {
                    if (!dir.lat || !dir.lng) return;

                    const esLibre = dir.es_libre;
                    const color = esLibre ? '#de1111' : (dir.color_ruta || '#999');
                    const borde = esLibre ? '#ffff00' : color;

                    const circle = L.circle([dir.lat, dir.lng], {
                        radius: 75,
                        color: borde,
                        fillColor: color,
                        fillOpacity: 0.7,
                        weight: esLibre ? 3 : 2
                    }).addTo(capaTodasDirecciones);

                    const popupContent = esLibre 
                        ? `<b>${dir.cliente_nombre}</b><br>${dir.direccion}<br><em style="color: #e74c3c;">⚠️ Free - No route</em>`
                        : `<b>${dir.cliente_nombre}</b><br>${dir.direccion}<br><em style="color: ${dir.color_ruta};">● ${dir.nombre_ruta || 'Assigned'}</em>`;

                    circle.bindPopup(popupContent);
                });

                if (!map.hasLayer(capaTodasDirecciones)) {
                    map.addLayer(capaTodasDirecciones);
                }
            }
        } catch (err) {
            console.error('Error cargando direcciones:', err);
            suiteAlertError('Error', 'Could not load addresses');
        } finally {
            mostrarLoading(false);
        }
    }

    async function cargarDireccionesLibres() {
        try {
            const idRutaExcluir = rutaActual ? rutaActual.id_ruta : null;

            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_rutas: 'listar_direcciones_libres',
                    id_ruta_actual: idRutaExcluir
                })
            });
            const data = await response.json();

            capaDireccionesLibres.clearLayers();

            if (data.success) {
                data.data.forEach(dir => {
                    if (!dir.lat || !dir.lng) return;

                    const circle = L.circle([dir.lat, dir.lng], {
                        radius: 75,
                        color: '#ffff00', // Borde amarillo
                        fillColor: '#de1111', // Rojo
                        fillOpacity: 0.7,
                        weight: 2
                    }).addTo(capaDireccionesLibres);

                    circle.direccionId = dir.id_direccion;
                    circle.bindPopup(`
                    <b>${dir.cliente_nombre}</b><br>
                    ${dir.direccion}<br>
                    <em style="color: #e74c3c;">⚠️ Free - Ready to add</em>
                `);
                });

                if (!map.hasLayer(capaDireccionesLibres)) {
                    map.addLayer(capaDireccionesLibres);
                }
            }
        } catch (err) {
            console.error('Error cargando direcciones libres:', err);
        }
    }

    async function seleccionarRutaExistente(idRuta) {
        if (estadoActual === ESTADOS.ADDRESSES) {
            limpiarModoAddresses();
        }

        try {
            mostrarLoading(true);

            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_rutas: 'obtener_ruta',
                    id_ruta: idRuta
                })
            });
            const data = await response.json();

            if (data.success) {
                cambiarEstado(ESTADOS.RUTA_VER, {
                    ruta: data.data
                });
            } else {
                suiteAlertError('Error', data.error || 'Could not load route');
            }
        } catch (err) {
            console.error('Error cargando ruta:', err);
            suiteAlertError('Error', 'Failed to load route');
        } finally {
            mostrarLoading(false);
        }
    }

    // ============================================
    // VISUALIZACIÓN DE RUTA
    // ============================================

    function mostrarRutaEnMapa(ruta, modo = 'tenue') {
        // Limpiar capas previas
        capaDireccionesRuta.clearLayers();

        // Pintar zonas de la ruta
        const idsZonasRuta = ruta.zonas.map(z => z.id_zona);

        capaZonas.eachLayer(layer => {
            const enRuta = idsZonasRuta.includes(layer.zonaId);

            if (enRuta) {
                const opacity = modo === 'tenue' ? 0.15 : 0.35;
                const weight = modo === 'tenue' ? 2 : 3;

                layer.setStyle({
                    color: ruta.color_ruta,
                    weight: weight,
                    fillOpacity: opacity,
                    fillColor: ruta.color_ruta
                });
            } else {
                // Otras zonas: muy tenues
                layer.setStyle({
                    color: '#aaaaaa',
                    weight: 1,
                    fillOpacity: 0.03,
                    fillColor: '#aaaaaa'
                });
            }
        });

        // Mostrar direcciones de la ruta
        const todasLasDirecciones = ruta.zonas.flatMap(z =>
            z.direcciones.map(d => ({
                ...d,
                id_zona: z.id_zona
            }))
        );

        todasLasDirecciones.forEach(dir => {
            if (!dir.lat || !dir.lng) return;

            const circle = L.circle([dir.lat, dir.lng], {
                radius: 75,
                color: '#ccff9f', // Borde claro
                fillColor: ruta.color_ruta, // Color de ruta
                fillOpacity: 0.7,
                weight: 2
            }).addTo(capaDireccionesRuta);

            circle.direccionId = dir.id_direccion;
            circle.bindPopup(`
            <b>${dir.cliente_nombre}</b><br>
            ${dir.direccion}<br>
            <em>Zone: ${ruta.zonas.find(z => z.id_zona === dir.id_zona)?.nombre_zona || 'Unknown'}</em>
        `);
        });

        if (!map.hasLayer(capaDireccionesRuta)) {
            map.addLayer(capaDireccionesRuta);
        }

        // Ajustar vista a los bounds de la ruta
        if (todasLasDirecciones.length > 0) {
            const lats = todasLasDirecciones.map(d => d.lat);
            const lngs = todasLasDirecciones.map(d => d.lng);
            const bounds = [
                [Math.min(...lats), Math.min(...lngs)],
                [Math.max(...lats), Math.max(...lngs)]
            ];
            map.fitBounds(bounds, {
                padding: [50, 50]
            });
        }
    }

    // ============================================
    // FORMULARIO MOVIBLE
    // ============================================

    function crearFormularioMovible(ruta) {
        // Destruir si existe
        destruirFormularioMovible();

        // Crear contenedor
        const form = document.createElement('div');
        form.id = 'formulario-movible';
        form.style.cssText = `
        position: fixed;
        top: 120px;
        right: 20px;
        width: 340px;
        max-height: 75vh;
        background: ${hexToRgba(ruta.color_ruta, 0.08)};
        border: 2px solid ${ruta.color_ruta};
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        z-index: 10000;
        font-family: Arial, sans-serif;
        display: flex;
        flex-direction: column;
    `;

        // Header draggable
        const header = document.createElement('div');
        header.style.cssText = `
        background: white;
        padding: 12px;
        border-bottom: 1px solid #eee;
        border-radius: 6px 6px 0 0;
        cursor: move;
        display: flex;
        justify-content: space-between;
        align-items: center;
    `;

        // Calcular tiempo total
        const todasDirs = ruta.zonas.flatMap(z => z.direcciones);
        const tiempoTotal = todasDirs.reduce((sum, d) => sum + (parseInt(d.tiempo_servicio) || 0), 0);
        const tiempoFormateado = minutosAHorasMinutos(tiempoTotal);

        header.innerHTML = `
        <div>
            <h4 style="margin: 0; color: #2c3e50; font-size: 1.1em;">${ruta.nombre_ruta}</h4>
            <span style="font-size: 0.85em; color: #e74c3c;">⏱️ Total: ${tiempoFormateado}</span>
        </div>
        <button id="btn-reordenar" style="padding: 6px 12px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85em;">↕️ Reorder</button>
    `;

        // Lista de direcciones
        const lista = document.createElement('div');
        lista.id = 'lista-direcciones-formulario';
        lista.style.cssText = `
        padding: 10px;
        max-height: calc(75vh - 80px);
        overflow-y: auto;
        background: ${hexToRgba(ruta.color_ruta, 0.03)};
    `;

        // Renderizar direcciones
        let html = '';
        todasDirs.forEach((dir, index) => {
            const tiempo = dir.tiempo_servicio ? `${dir.tiempo_servicio} min` : '';
            const zonaNombre = ruta.zonas.find(z => z.direcciones.some(d => d.id_direccion === dir.id_direccion))?.nombre_zona || '';
            
            // BOTÓN ELIMINAR SOLO EN MODO EDICIÓN
            const btnEliminar = estadoActual === ESTADOS.RUTA_EDITAR ? `
                <button class="btn-eliminar-dir" data-id="${dir.id_direccion}" style="
                    background: #e74c3c;
                    color: white;
                    border: none;
                    border-radius: 3px;
                    padding: 4px 8px;
                    font-size: 0.75em;
                    cursor: pointer;
                    margin-left: 8px;
                    flex-shrink: 0;
                " title="Remove from route">×</button>
            ` : '';

            html += `
            <div class="direccion-item" data-id="${dir.id_direccion}" style="
                padding: 10px;
                margin-bottom: 6px;
                background: white;
                border-radius: 4px;
                border-left: 3px solid ${ruta.color_ruta};
                cursor: pointer;
                transition: background 0.2s;
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
            " onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 600; font-size: 0.95em; margin-bottom: 2px;">${index + 1}. ${dir.cliente_nombre}</div>
                    <div style="font-size: 0.85em; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${dir.direccion}</div>
                    ${tiempo ? `<div style="font-size: 0.8em; color: #27ae60; margin-top: 2px;">⏱️ ${tiempo}</div>` : ''}
                    ${zonaNombre ? `<div style="font-size: 0.75em; color: #95a5a6; margin-top: 2px;">📍 ${zonaNombre}</div>` : ''}
                </div>
                ${btnEliminar}
            </div>
        `;
        });

    lista.innerHTML = html || '<p style="color: #7f8c8d; text-align: center; padding: 20px;">No addresses in this route</p>';

        form.appendChild(header);
        form.appendChild(lista);
        document.body.appendChild(form);

        // Eventos
        configurarDragFormulario(form, header);

        // Evento reordenar
        document.getElementById('btn-reordenar').addEventListener('click', abrirModalReordenamiento);
        
        // Eventos de direcciones
        lista.querySelectorAll('.direccion-item').forEach(item => {
            item.addEventListener('click', (e) => {
                if (e.target.closest('.btn-eliminar-dir')) return;
                const idDir = parseInt(item.dataset.id);
                zoomADireccion(idDir);
            });
        });

        // Eventos de eliminar (solo en edición)
        if (estadoActual === ESTADOS.RUTA_EDITAR) {
            lista.querySelectorAll('.btn-eliminar-dir').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const idDir = parseInt(btn.dataset.id);
                    
                    suiteConfirm('Remove Zone', 'Remove this address from the route?\n\nThis action cannot be undone.').then(async (confirmar) => {
                        if (confirmar) {
                            await eliminarDireccionDeRuta(idDir);
                        }
                    });
                });
            });
        }
    }

    function destruirFormularioMovible() {
        const existente = document.getElementById('formulario-movible');
        if (existente) existente.remove();
    }

    function configurarDragFormulario(form, header) {
        let isDragging = false;
        let startX, startY, startRight, startTop;

        header.addEventListener('mousedown', (e) => {
            if (e.target.tagName === 'BUTTON' || e.target.closest('button')) return;
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            const rect = form.getBoundingClientRect();
            startRight = window.innerWidth - rect.right;
            startTop = rect.top;
            header.style.cursor = 'grabbing';
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            form.style.right = `${startRight - dx}px`;
            form.style.top = `${startTop + dy}px`;
        });

        document.addEventListener('mouseup', () => {
            isDragging = false;
            header.style.cursor = 'move';
        });
    }

    // ============================================
    // MODALES DE INTERACCIÓN
    // ============================================
    async function abrirModalReordenamiento() {
        if (!rutaActual) return;
        
        // Obtener todas las direcciones de la ruta en orden actual
        const todasDirs = rutaActual.zonas.flatMap(z => 
            z.direcciones.map(d => ({
                ...d,
                nombre_zona: z.nombre_zona
            }))
        );
        
        if (todasDirs.length === 0) {
            suiteAlertWarning('Info', 'No addresses to reorder');
            return;
        }
        
        if (todasDirs.length === 1) {
            suiteAlertWarning('Info', 'Need at least 2 addresses to reorder');
            return;
        }

        // Crear copia mutable
        let direccionesOrden = [...todasDirs];
        
        const resultado = await new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.6); z-index: 20000;
                display: flex; justify-content: center; align-items: center;
            `;
            
            const modal = document.createElement('div');
            modal.style.cssText = `
                background: white; border-radius: 8px; width: 90%; max-width: 550px;
                max-height: 85vh; display: flex; flex-direction: column;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            `;
            
            // Header
            const header = document.createElement('div');
            header.style.cssText = 'padding: 16px; border-bottom: 2px solid #3498db; background: #f8f9fa;';
            header.innerHTML = `
                <h3 style="margin: 0; color: #2c3e50;">↕️ Reorder Route Addresses</h3>
                <p style="margin: 5px 0 0 0; font-size: 0.85em; color: #7f8c8d;">
                    Use ↑ ↓ arrows to change stop order. Click Save when done.
                </p>
            `;
            
            // Lista de direcciones
            const lista = document.createElement('div');
            lista.style.cssText = 'padding: 10px 16px; max-height: 55vh; overflow-y: auto;';

            
            function renderLista() {
                let html = '';
                direccionesOrden.forEach((dir, index) => {
                    const tiempo = dir.tiempo_servicio ? `⏱️ ${dir.tiempo_servicio} min` : '';
                    const isFirst = index === 0;
                    const isLast = index === direccionesOrden.length - 1;
                    
                    html += `
                        <div style="
                            display: flex; align-items: center; padding: 12px;
                            margin-bottom: 8px; background: #f8f9fa;
                            border-radius: 6px; border-left: 4px solid ${rutaActual.color_ruta};
                            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                        ">
                            <span style="
                                font-weight: bold; color: #3498db; margin-right: 12px;
                                min-width: 30px; font-size: 1.1em;
                            ">${index + 1}</span>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 600; color: #2c3e50;">${dir.cliente_nombre}</div>
                                <div style="font-size: 0.85em; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${dir.direccion}</div>
                                ${tiempo ? `<div style="font-size: 0.8em; color: #27ae60; margin-top: 2px;">${tiempo}</div>` : ''}
                                <div style="font-size: 0.75em; color: #95a5a6; margin-top: 2px;">📍 ${dir.nombre_zona}</div>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 4px; margin-left: 10px;">
                                <button class="btn-up" data-idx="${index}" ${isFirst ? 'disabled' : ''} style="
                                    padding: 6px 10px; background: ${isFirst ? '#bdc3c7' : '#3498db'};
                                    color: white; border: none; border-radius: 4px;
                                    cursor: ${isFirst ? 'not-allowed' : 'pointer'}; font-size: 0.9em;
                                ">↑</button>
                                <button class="btn-down" data-idx="${index}" ${isLast ? 'disabled' : ''} style="
                                    padding: 6px 10px; background: ${isLast ? '#bdc3c7' : '#3498db'};
                                    color: white; border: none; border-radius: 4px;
                                    cursor: ${isLast ? 'not-allowed' : 'pointer'}; font-size: 0.9em;
                                ">↓</button>
                            </div>
                        </div>
                    `;
                });
                lista.innerHTML = html;
                
                // Re-adjuntar eventos
                lista.querySelectorAll('.btn-up:not([disabled])').forEach(btn => {
                    btn.onclick = () => {
                        const idx = parseInt(btn.dataset.idx);
                        [direccionesOrden[idx], direccionesOrden[idx - 1]] = [direccionesOrden[idx - 1], direccionesOrden[idx]];
                        renderLista();
                    };
                });
                
                lista.querySelectorAll('.btn-down:not([disabled])').forEach(btn => {
                    btn.onclick = () => {
                    const idx = parseInt(btn.dataset.idx);
                    [direccionesOrden[idx], direccionesOrden[idx + 1]] = [direccionesOrden[idx + 1], direccionesOrden[idx]];
                    renderLista();
                    };
                });
            }
            
            renderLista();
            
            // Footer
            const footer = document.createElement('div');
            footer.style.cssText = 'padding: 16px; border-top: 1px solid #eee; text-align: right; background: #f8f9fa;';
            footer.innerHTML = `
                <button id="btn-cancelar-reorden" style="margin-right: 10px; padding: 8px 16px; background: #95a5a6; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button id="btn-guardar-reorden" style="padding: 8px 16px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">💾 Save Order</button>
            `;
            
            modal.appendChild(header);
            modal.appendChild(lista);
            modal.appendChild(footer);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);
            
            // Eventos
            document.getElementById('btn-cancelar-reorden').onclick = () => {
                document.body.removeChild(overlay);
                resolve(null);
            };
            
            document.getElementById('btn-guardar-reorden').onclick = () => {
                const nuevoOrden = direccionesOrden.map(d => d.id_direccion);
                document.body.removeChild(overlay);
                resolve(nuevoOrden);
            };
        });
        
        if (!resultado) return; // Cancelado
        
        // Guardar nuevo orden
        try {
            mostrarLoading(true);
            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    modulo_rutas: 'actualizar_orden_direcciones',
                    id_ruta: rutaActual.id_ruta,
                    orden_direcciones: resultado
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                suiteAlertSuccess('Success', 'Route order updated successfully');
                // Recargar ruta para reflejar cambios
                await seleccionarRutaExistente(rutaActual.id_ruta);
                cambiarEstado(ESTADOS.RUTA_EDITAR);
            } else {
                throw new Error(data.error || 'Failed to update order');
            }
        } catch (err) {
            suiteAlertError('Error', err.message);
        } finally {
            mostrarLoading(false);
        }
    }


    async function seleccionarZonaParaNuevaRuta(layer) {
        const idZona = layer.zonaId;
        const nombreZona = layer.zonaNombre;

        // Verificar si ya está seleccionada
        if (rutaEnConstruccion.zonas.has(idZona)) {
            // Deseleccionar
            rutaEnConstruccion.zonas.delete(idZona);
            layer.setStyle({
                color: '#3388ff',
                weight: 2,
                fillOpacity: 0.2
            });
            actualizarListaZonasSeleccionadas();
            return;
        }

        // Cargar direcciones de la zona
        try {
            mostrarLoading(true);
            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_rutas: 'obtener_direcciones_zona',
                    id_zona: idZona
                })
            });
            const data = await response.json();
            mostrarLoading(false);

            const direcciones = data.success ? data.data : {
                libres: [],
                en_otra_ruta: [],
                en_ruta_actual: []
            };

            // Si no hay direcciones libres ni en otra ruta, agregar zona vacía
            const totalDisponibles = direcciones.libres.length + direcciones.en_otra_ruta.length;

            if (totalDisponibles === 0) {
                // Zona vacía (solo para navegación)
                rutaEnConstruccion.zonas.set(idZona, {
                    direcciones: new Set()
                });
                layer.setStyle({
                    color: '#e74c3c',
                    weight: 3,
                    fillOpacity: 0.3
                });
                actualizarListaZonasSeleccionadas();
                return;
            }

            // Mostrar modal de selección
            const seleccionadas = await abrirModalSeleccionDirecciones(direcciones, nombreZona, 'crear');

            if (seleccionadas !== null) {
                // Agregar zona con direcciones seleccionadas
                rutaEnConstruccion.zonas.set(idZona, {
                    direcciones: new Set(seleccionadas)
                });
                layer.setStyle({
                    color: '#e74c3c',
                    weight: 3,
                    fillOpacity: 0.3
                });
                actualizarListaZonasSeleccionadas();
            }

        } catch (err) {
            mostrarLoading(false);
            console.error('Error:', err);
            suiteAlertError('Error', 'Could not load zone addresses');
        }
    }

    async function abrirModalZonaNueva(layer) {
        // Similar a seleccionarZonaParaNuevaRuta pero agrega a ruta existente
        const idZona = layer.zonaId;
        const nombreZona = layer.zonaNombre;
        
        try {
            mostrarLoading(true);
            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    modulo_rutas: 'obtener_direcciones_zona',
                    id_zona: idZona,
                    id_ruta_actual: rutaActual.id_ruta
                })
            });
            const data = await response.json();
            mostrarLoading(false);
            
            const direcciones = data.success ? data.data : {libres: [], en_otra_ruta: [], en_ruta_actual: []};
            
            // Si no hay direcciones disponibles, agregar zona vacía directamente
            if (direcciones.libres.length === 0 && direcciones.en_otra_ruta.length === 0) {

                suiteConfirm('Add Zone', `Zone "${nombreZona}" has no available addresses.\n\nAdd it for navigation only?`).then(async (confirmar) => {
                    if (confirmar) {
                        await agregarZonaARuta(idZona, []);
                    }
                });
                return;
            }
            
            // Mostrar modal
            const seleccionadas = await abrirModalSeleccionDirecciones(direcciones, nombreZona, 'editar_agregar');
            
            if (seleccionadas !== null && seleccionadas.length > 0) {
                await agregarZonaARuta(idZona, seleccionadas);
            } else if (seleccionadas !== null && seleccionadas.length === 0) {
                // Agregar zona vacía si no seleccionó ninguna pero confirmó
                await agregarZonaARuta(idZona, []);
            }
            
        } catch (err) {
            mostrarLoading(false);
            console.error('Error:', err);
            suiteAlertError('Error', 'Could not load zone addresses');
        }
    }

    async function abrirModalZonaEdicion(layer) {
        const idZona = layer.zonaId;
        const nombreZona = layer.zonaNombre;
        
        // Encontrar direcciones de esta zona en la ruta actual
        const zonaEnRuta = rutaActual.zonas.find(z => z.id_zona === idZona);
        const idsEnRuta = zonaEnRuta ? zonaEnRuta.direcciones.map(d => d.id_direccion) : [];
        
        try {
            mostrarLoading(true);
            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    modulo_rutas: 'obtener_direcciones_zona',
                    id_zona: idZona,
                    id_ruta_actual: rutaActual.id_ruta
                })
            });
            const data = await response.json();
            mostrarLoading(false);
            
            // Construir lista para modal de edición
            const direcciones = data.success ? data.data : {libres: [], en_otra_ruta: [], en_ruta_actual: []};
            
            // Marcar las que ya están en la ruta como "checked pero disabled"
            // Las libres como "seleccionables"
            // Las de otras rutas como "info only"
            
            const resultado = await abrirModalEdicionZona(direcciones, nombreZona, idsEnRuta, idZona);            

            if (resultado.accion === 'eliminar_zona_completa') {
                await eliminarZonaCompleta(resultado);
            } 
            else if (resultado && resultado.cambios) {
                // Aplicar cambios: quitar las deseleccionadas, agregar las nuevas
                await aplicarCambiosZona(idZona, resultado);
            }
            
        } catch (err) {
            mostrarLoading(false);
            console.error('Error:', err);
            suiteAlertError('Error', 'Could not edit zone');
        }
    }

    async function eliminarZonaCompleta({ idZona, nombreZona, idsDirecciones }) {
        
        // Confirmación única aquí
        const mensaje = idsDirecciones.length 
            ? `Remove zone "${nombreZona}" and ${idsDirecciones.length} addresses from route?`
            : `Remove empty zone "${nombreZona}" from route?`;
        
        const confirmar = await suiteConfirm('Delete Zone', mensaje);
        if (!confirmar) return;

        mostrarLoading(true);
        
        try {
            // 1. ELIMINAR DIRECCIONES (usando tu función existente, sin recargas)
            for (const idDir of idsDirecciones) {
                await eliminarDireccionDeRuta(idDir, false); // ← false = no recargar
            }
            
            // 2. ELIMINAR ZONA (usando tu función existente)
            await quitarZonaDeRuta(idZona);
            
            // 3. Recargar UNA VEZ al final
            await seleccionarRutaExistente(rutaActual.id_ruta);
            
            suiteAlertSuccess('Success', `Zone "${nombreZona}" deleted`, 'success');
            
        } catch (err) {
            console.error('Error eliminando zona:', err);
            suiteAlertError('Error', 'Failed to delete zone: ' + err.message);
        } finally {
            mostrarLoading(false);
        }
    }

    function abrirModalSeleccionDirecciones(direcciones, nombreZona, modo) {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 20000;
            display: flex; justify-content: center; align-items: center;
        `;

            const modal = document.createElement('div');
            modal.style.cssText = `
            background: white; border-radius: 8px; width: 90%; max-width: 500px;
            max-height: 80vh; display: flex; flex-direction: column;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        `;

            // Header
            const header = document.createElement('div');
            header.style.cssText = 'padding: 16px; border-bottom: 2px solid #3498db; background: #f8f9fa;';
            header.innerHTML = `
            <h3 style="margin: 0; color: #2c3e50;">${nombreZona}</h3>
            <p style="margin: 5px 0 0 0; font-size: 0.85em; color: #7f8c8d;">
                Select addresses to include in the route
            </p>
        `;

            // Controles
            const controls = document.createElement('div');
            controls.style.cssText = 'padding: 10px 16px; border-bottom: 1px solid #eee;';
            controls.innerHTML = `
            <button id="btn-select-all" style="margin-right: 8px; padding: 4px 10px; background: #3498db; color: white; border: none; border-radius: 3px; cursor: pointer;">Select All</button>
            <button id="btn-deselect-all" style="padding: 4px 10px; background: #95a5a6; color: white; border: none; border-radius: 3px; cursor: pointer;">Deselect All</button>
        `;

            // Lista
            const lista = document.createElement('div');
            lista.style.cssText = 'padding: 10px 16px; max-height: 50vh; overflow-y: auto;';

            let html = '';

            // Direcciones libres (seleccionables)
            if (direcciones.libres.length > 0) {
                html += `<div style="margin-bottom: 15px;"><h4 style="margin: 0 0 8px 0; color: #27ae60; font-size: 0.95em;">✓ Available Addresses (${direcciones.libres.length})</h4>`;
                direcciones.libres.forEach(dir => {
                    html += crearItemDireccionModal(dir, true, false);
                });
                html += '</div>';
            }

            // Direcciones en otra ruta (disabled, informativo)
            if (direcciones.en_otra_ruta.length > 0) {
                html += `<div style="margin-bottom: 15px; opacity: 0.7;"><h4 style="margin: 0 0 8px 0; color: #95a5a6; font-size: 0.95em;">🚫 In Other Routes (${direcciones.en_otra_ruta.length})</h4>`;
                direcciones.en_otra_ruta.slice(0, 3).forEach(dir => {
                    html += crearItemDireccionModal(dir, false, true);
                });
                if (direcciones.en_otra_ruta.length > 3) {
                    html += `<div style="text-align: center; color: #7f8c8d; font-size: 0.85em;">... and ${direcciones.en_otra_ruta.length - 3} more</div>`;
                }
                html += '</div>';
            }

            // Direcciones ya en esta ruta (si aplica, disabled)
            if (direcciones.en_ruta_actual && direcciones.en_ruta_actual.length > 0) {
                html += `<div style="margin-bottom: 15px;"><h4 style="margin: 0 0 8px 0; color: #f39c12; font-size: 0.95em;">⭐ Already in This Route (${direcciones.en_ruta_actual.length})</h4>`;
                direcciones.en_ruta_actual.forEach(dir => {
                    html += crearItemDireccionModal(dir, true, true, true);
                });
                html += '</div>';
            }

            lista.innerHTML = html || '<p style="color: #7f8c8d; text-align: center;">No addresses available in this zone</p>';

            // Footer
            const footer = document.createElement('div');
            footer.style.cssText = 'padding: 16px; border-top: 1px solid #eee; text-align: right;';
            footer.innerHTML = `
            <button id="btn-cancelar-modal" style="margin-right: 10px; padding: 8px 16px; background: #95a5a6; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
            <button id="btn-confirmar-modal" style="padding: 8px 16px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Confirm</button>
        `;

            modal.appendChild(header);
            modal.appendChild(controls);
            modal.appendChild(lista);
            modal.appendChild(footer);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            // Eventos
            document.getElementById('btn-select-all').onclick = () => {
                lista.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach(cb => cb.checked = true);
            };

            document.getElementById('btn-deselect-all').onclick = () => {
                lista.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach(cb => cb.checked = false);
            };

            document.getElementById('btn-cancelar-modal').onclick = () => {
                document.body.removeChild(overlay);
                resolve(null);
            };

            document.getElementById('btn-confirmar-modal').onclick = () => {
                const seleccionadas = Array.from(lista.querySelectorAll('input[type="checkbox"]:checked:not(:disabled)'))
                    .map(cb => parseInt(cb.value));
                document.body.removeChild(overlay);
                resolve(seleccionadas);
            };

            overlay.onclick = (e) => {
                if (e.target === overlay) {
                    document.body.removeChild(overlay);
                    resolve(null);
                }
            };
        });
    }

    function crearItemDireccionModal(dir, checked, disabled) {
        const bgColor = disabled ? '#ecf0f1' : '#d5f5d5';
        const borderColor = disabled ? '#95a5a6' : '#27ae60';
        const badge = disabled ? `<span style="color:#95a5a6; font-size:0.8em;">[${dir.nombre_ruta || 'Other route'}]</span>` : '';

        return `
        <div style="padding: 10px; margin-bottom: 6px; background: ${bgColor}; border-radius: 4px; border-left: 3px solid ${borderColor}; ${disabled ? 'opacity: 0.7;' : ''}">
            <label style="display: flex; align-items: start; cursor: ${disabled ? 'not-allowed' : 'pointer'};">
                <input type="checkbox" value="${dir.id_direccion}" 
                    ${checked ? 'checked' : ''} 
                    ${disabled ? 'disabled' : ''} 
                    style="margin-right: 10px; margin-top: 3px;">
                <div style="flex: 1;">
                    <strong>${dir.cliente_nombre}</strong> ${badge}<br>
                    <small style="color: #555;">${dir.direccion}</small>
                </div>
            </label>
        </div>
    `;
    }

    function abrirModalEdicionZona(direcciones, nombreZona, idsEnRutaActual, idZona) {
        return new Promise((resolve) => {
            // Similar a abrirModalSeleccionDirecciones pero con lógica diferente:
            // - Las de la ruta actual: checked, pueden desmarcarse (para quitar)
            // - Las libres: unchecked, pueden marcarse (para agregar)
            // - Las de otras rutas: disabled
            
            // TODO: Implementar o reutilizar abrirModalSeleccionDirecciones con modo 'edicion'
            // Por ahora, simplificación: usar el mismo modal con ajustes
            
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.5); z-index: 20000;
                display: flex; justify-content: center; align-items: center;
            `;
            
            const modal = document.createElement('div');
            modal.style.cssText = `
                background: white; border-radius: 8px; width: 90%; max-width: 500px;
                max-height: 80vh; display: flex; flex-direction: column;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            `;
            
            const header = document.createElement('div');
            header.style.cssText = 'padding: 16px; border-bottom: 2px solid #f39c12; background: #f8f9fa;';
            header.innerHTML = `
                <h3 style="margin: 0; color: #2c3e50;">✏️ Edit: ${nombreZona}</h3>
                <p style="margin: 5px 0 0 0; font-size: 0.85em; color: #7f8c8d;">
                    Uncheck to remove from route. Check available addresses to add.
                </p>
            `;
            
            const lista = document.createElement('div');
            lista.style.cssText = 'padding: 10px 16px; max-height: 50vh; overflow-y: auto;';
            
            let html = '';
            
            // Direcciones en esta ruta (pueden quitarse)
            const enRuta = direcciones.en_ruta_actual || [];
            if (enRuta.length > 0) {
                html += `<div style="margin-bottom: 15px;"><h4 style="margin: 0 0 8px 0; color: #f39c12; font-size: 0.95em;">⭐ In This Route (uncheck to remove)</h4>`;
                enRuta.forEach(dir => {
                    html += `
                        <div style="padding: 10px; margin-bottom: 6px; background: #fef9e7; border-radius: 4px; border-left: 3px solid #f39c12;">
                            <label style="display: flex; align-items: start; cursor: pointer;">
                                <input type="checkbox" class="dir-en-ruta" value="${dir.id_direccion}" checked 
                                    data-accion="mantener" style="margin-right: 10px; margin-top: 3px;">
                                <div style="flex: 1;">
                                    <strong>${dir.cliente_nombre}</strong><br>
                                    <small style="color: #555;">${dir.direccion}</small>
                                </div>
                            </label>
                        </div>
                    `;
                });
                html += '</div>';
            }
            
            // Direcciones libres (pueden agregarse)
            if (direcciones.libres.length > 0) {
                html += `<div style="margin-bottom: 15px;"><h4 style="margin: 0 0 8px 0; color: #27ae60; font-size: 0.95em;">✓ Available to Add</h4>`;
                direcciones.libres.forEach(dir => {
                    html += `
                        <div style="padding: 10px; margin-bottom: 6px; background: #d5f5d5; border-radius: 4px; border-left: 3px solid #27ae60;">
                            <label style="display: flex; align-items: start; cursor: pointer;">
                                <input type="checkbox" class="dir-libre" value="${dir.id_direccion}" 
                                    data-accion="agregar" style="margin-right: 10px; margin-top: 3px;">
                                <div style="flex: 1;">
                                    <strong>${dir.cliente_nombre}</strong><br>
                                    <small style="color: #555;">${dir.direccion}</small>
                                </div>
                            </label>
                        </div>
                    `;
                });
                html += '</div>';
            }
            
            // Direcciones en otras rutas (info only)
            if (direcciones.en_otra_ruta.length > 0) {
                html += `<div style="margin-bottom: 15px; opacity: 0.6;"><h4 style="margin: 0 0 8px 0; color: #95a5a6; font-size: 0.95em;">🚫 In Other Routes</h4>`;
                direcciones.en_otra_ruta.slice(0, 3).forEach(dir => {
                    html += `
                        <div style="padding: 8px; margin-bottom: 6px; background: #ecf0f1; border-radius: 4px;">
                            <strong>${dir.cliente_nombre}</strong><br>
                            <small>${dir.direccion}</small><br>
                            <em style="font-size: 0.8em; color: #7f8c8d;">${dir.nombre_ruta || 'Other route'}</em>
                        </div>
                    `;
                });
                if (direcciones.en_otra_ruta.length > 3) {
                    html += `<div style="text-align: center; color: #7f8c8d; font-size: 0.85em;">... and ${direcciones.en_otra_ruta.length - 3} more</div>`;
                }
                html += '</div>';
            }
            
            lista.innerHTML = html || '<p style="color: #7f8c8d; text-align: center;">No addresses in this zone</p>';
            
            const footer = document.createElement('div');
            footer.style.cssText = 'padding: 16px; border-top: 1px solid #eee; text-align: right;';
            footer.innerHTML = `
                <button id="btn-borrar-zona" style="margin-right: 10px; padding: 8px 16px; background: #ee1212; color: white; border: none; border-radius: 4px; cursor: pointer;">Delete Zone</button>
                <button id="btn-cancelar-edicion" style="margin-right: 10px; padding: 8px 16px; background: #95a5a6; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button id="btn-aplicar-edicion" style="padding: 8px 16px; background: #f39c12; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Apply Changes</button>
            `;
            
            modal.appendChild(header);
            modal.appendChild(lista);
            modal.appendChild(footer);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);
            
            // Eventos

            // BOTÓN BORRAR ZONA - Sin confirmación, solo cierra y delega
            document.getElementById('btn-borrar-zona').onclick = () => {
                document.body.removeChild(overlay);
                resolve({
                    accion: 'eliminar_zona_completa',  // ← nuevo tipo
                    idZona: idZona,                     // ← ahora sí lo tenemos
                    nombreZona: nombreZona,
                    idsDirecciones: idsEnRutaActual     // ← para eliminar primero
                });
            };

            // BOTÓN CANCELAR
            document.getElementById('btn-cancelar-edicion').onclick = () => {
                document.body.removeChild(overlay);
                resolve(null);
            };
            
            // BOTÓN APLICAR CAMBIOS (igual que antes)
            document.getElementById('btn-aplicar-edicion').onclick = () => {
                const aQuitar = Array.from(lista.querySelectorAll('.dir-en-ruta:not(:checked)')).map(cb => parseInt(cb.value));
                const aAgregar = Array.from(lista.querySelectorAll('.dir-libre:checked')).map(cb => parseInt(cb.value));
                
                document.body.removeChild(overlay);
                resolve({
                    accion: 'modificar_direcciones',
                    quitar: aQuitar,
                    agregar: aAgregar
                });
            };
        });
    }

    // ============================================
    // ACCIONES DE GUARDADO
    // ============================================

    async function guardarNuevaRuta() {
        const nombre = document.getElementById('input-nombre-ruta').value.trim();
        const color = document.getElementById('input-color-ruta').value;

        if (!nombre) {
            suiteAlertWarning('Required', 'Please enter a route name');
            return;
        }

        if (rutaEnConstruccion.zonas.size === 0) {
            suiteAlertWarning('Required', 'Please select at least one zone');
            return;
        }

        // Construir payload
        const zonasPayload = [];
        rutaEnConstruccion.zonas.forEach((data, idZona) => {
            zonasPayload.push({
                id_zona: idZona,
                direcciones_ids: Array.from(data.direcciones)
            });
        });

        try {
            mostrarLoading(true);
            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_rutas: 'crear_ruta',
                    nombre_ruta: nombre,
                    color_ruta: color,
                    zonas: zonasPayload
                })
            });
            const data = await response.json();

            if (data.success) {
                suiteAlertSuccess('Success', 'Route created successfully!');
                cambiarEstado(ESTADOS.NEUTRO);
                cargarRutasExistentes();
            } else {
                throw new Error(data.error || 'Failed to create route');
            }
        } catch (err) {
            suiteAlertError('Error', err.message);
        } finally {
            mostrarLoading(false);
        }
    }


    async function agregarZonaARuta(idZona, direccionesIds) {
        // Implementar llamada a backend para agregar zona y direcciones a ruta existente
        // Usar actualizar_ruta_completa con zonas_agregar
        try {
            mostrarLoading(true);
            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    modulo_rutas: 'actualizar_ruta_completa',
                    id_ruta: rutaActual.id_ruta,
                    nombre_ruta: document.getElementById('input-nombre-ruta').value || rutaActual.nombre_ruta,
                    color_ruta: document.getElementById('input-color-ruta').value || rutaActual.color_ruta,
                    cambios: {
                        zonas_agregar: [{id_zona: idZona, direcciones_ids: direccionesIds}],
                        zonas_quitar: [],
                        direcciones_agregar: [],
                        direcciones_quitar: []
                    }
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                suiteAlertSuccess('Success', 'Zone added to route', 'success');
                // Recargar ruta
                await seleccionarRutaExistente(rutaActual.id_ruta);
                cambiarEstado(ESTADOS.RUTA_EDITAR);
            } else {
                throw new Error(data.error || 'Failed to add zone');
            }
        } catch (err) {
            suiteAlertError('Error', err.message);
        } finally {
            mostrarLoading(false);
        }
    }

    async function aplicarCambiosZona(idZona, cambios) {
        // cambios = {quitar: [], agregar: []}
        try {
            mostrarLoading(true);
            
            // Construir payload de direcciones a agregar con id_zona
            const dirsAgregar = cambios.agregar.map(idDir => ({
                id_direccion: idDir,
                id_zona: idZona
            }));
            
            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    modulo_rutas: 'actualizar_ruta_completa',
                    id_ruta: rutaActual.id_ruta,
                    nombre_ruta: document.getElementById('input-nombre-ruta').value || rutaActual.nombre_ruta,
                    color_ruta: document.getElementById('input-color-ruta').value || rutaActual.color_ruta,
                    cambios: {
                        zonas_agregar: [],
                        zonas_quitar: [],
                        direcciones_agregar: dirsAgregar,
                        direcciones_quitar: cambios.quitar
                    }
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                suiteAlertSuccess('Success', 'Zone updated successfully');
                await seleccionarRutaExistente(rutaActual.id_ruta);
                cambiarEstado(ESTADOS.RUTA_EDITAR);
            } else {
                throw new Error(data.error || 'Failed to update zone');
            }
        } catch (err) {
            suiteAlertError('Error', err.message);
        } finally {
            mostrarLoading(false);
        }
    }

    async function eliminarDireccionDeRuta(idDireccion, recargar = true) {
        if (!rutaActual) return;
        
        try {
            mostrarLoading(true);
            
            // Usar el endpoint de actualización batch con solo direcciones_quitar
            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    modulo_rutas: 'actualizar_ruta_completa',
                    id_ruta: rutaActual.id_ruta,
                    nombre_ruta: rutaActual.nombre_ruta,
                    color_ruta: rutaActual.color_ruta,
                    cambios: {
                        zonas_agregar: [],
                        zonas_quitar: [],
                        direcciones_agregar: [],
                        direcciones_quitar: [idDireccion]
                    }
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (recargar) {
                    // Recargar la ruta para refrescar vista
                    await seleccionarRutaExistente(rutaActual.id_ruta);
                    // Volver a modo edición
                    cambiarEstado(ESTADOS.RUTA_EDITAR);
                    suiteAlertSuccess('Success', 'Address removed successfully', 'success');
                }
                return true; // ← éxito para el batch
            } else {
                throw new Error(data.error || 'Failed to remove address');
            }
        } catch (err) {
            console.error('Error eliminando dirección:', err);
            suiteAlertError('Error', err.message);
        } finally {
            mostrarLoading(false);
        }
    }    

    async function quitarZonaDeRuta(idZona) {
        console.log('🗑️ Intentando quitar zona:', idZona, 'de ruta:', rutaActual?.id_ruta);
        
        suiteConfirm('Remove Zone', `Remove zone from route?\n\nZone ID: ${idZona}\nRoute: ${rutaActual?.nombre_ruta}`).then(async (confirmar) => {
            if (!confirmar) {
                return;
            }
        });

        try {
            mostrarLoading(true);
            
            const payload = {
                modulo_rutas: 'actualizar_ruta_completa',
                id_ruta: rutaActual.id_ruta,
                nombre_ruta: document.getElementById('input-nombre-ruta').value || rutaActual.nombre_ruta,
                color_ruta: document.getElementById('input-color-ruta').value || rutaActual.color_ruta,
                cambios: {
                    zonas_agregar: [],
                    zonas_quitar: [parseInt(idZona)], // Asegurar que sea número
                    direcciones_agregar: [],
                    direcciones_quitar: []
                }
            };
            
            console.log('📤 Enviando payload:', JSON.stringify(payload, null, 2));
            
            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            
            const data = await response.json();
            console.log('📥 Respuesta:', data);
            
            if (data.success) {
                suiteAlertSuccess('Success','✅ Zone removed successfully!');
                await seleccionarRutaExistente(rutaActual.id_ruta);
                cambiarEstado(ESTADOS.RUTA_EDITAR);
            } else {
                throw new Error(data.error || 'Failed to remove zone');
            }
        } catch (err) {
            console.error('❌ Error:', err);
            suiteAlertError('Error: ' + err.message);
        } finally {
            mostrarLoading(false);
        }
    }

    async function guardarCambiosRuta() {
        // Guardar cambios generales de la ruta (nombre, color)
        // Los cambios de zonas/direcciones se hacen en tiempo real via los modales
        try {
            mostrarLoading(true);
            const response = await fetch(RUTA_AJAX, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    modulo_rutas: 'actualizar_ruta_completa',
                    id_ruta: rutaActual.id_ruta,
                    nombre_ruta: document.getElementById('input-nombre-ruta').value,
                    color_ruta: document.getElementById('input-color-ruta').value,
                    cambios: {
                        zonas_agregar: [],
                        zonas_quitar: [],
                        direcciones_agregar: [],
                        direcciones_quitar: []
                    }
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                suiteAlertSuccess('Success', 'Route saved successfully');
                await seleccionarRutaExistente(rutaActual.id_ruta);
                // Quedarse en modo ver (no edición)
            } else {
                throw new Error(data.error || 'Failed to save route');
            }
        } catch (err) {
            suiteAlertError('Error', err.message);
        } finally {
            mostrarLoading(false);
        }
    }

    // ============================================
    // UTILIDADES Y HELPERS
    // ============================================
    function toggleModoAddresses() {
        if (estadoActual === ESTADOS.ADDRESSES) {
            cambiarEstado(ESTADOS.NEUTRO);
        } else {
            // Si hay ruta cargada, limpiarla primero
            if (estadoActual === ESTADOS.RUTA_VER || estadoActual === ESTADOS.RUTA_EDITAR) {
                cambiarEstado(ESTADOS.NEUTRO);
            }
            cambiarEstado(ESTADOS.ADDRESSES);
        }
    }

    function iniciarCreacionRuta() {
        if (estadoActual === ESTADOS.ADDRESSES) {
            cambiarEstado(ESTADOS.NEUTRO);
        }

        // Habilitar inputs
        document.getElementById('input-nombre-ruta').disabled = false;
        document.getElementById('input-color-ruta').disabled = false;
        document.getElementById('input-nombre-ruta').focus();

        // Mostrar controles de creación
        document.getElementById('btn-crear-ruta').textContent = 'Save New Route';
        document.getElementById('btn-crear-ruta').onclick = guardarNuevaRuta;
        document.getElementById('btn-cancelar-accion').style.display = 'block';
        document.getElementById('seccion-zonas-seleccionadas').style.display = 'block';

        // Habilitar selección de zonas
        capaZonas.eachLayer(layer => {
            layer.setStyle({
                color: '#3388ff',
                weight: 2,
                fillOpacity: 0.2
            });
            layer.off('click');
            layer.on('click', () => seleccionarZonaParaNuevaRuta(layer));
        });

        // Cargar direcciones libres para mostrar en rojo
        cargarDireccionesLibres();

        actualizarIndicadorEstado('Creating Route - Select zones');
    }

    function entrarModoEdicion() {
        cambiarEstado(ESTADOS.RUTA_EDITAR);
    }

    function cancelarAccion() {
        if (estadoActual === ESTADOS.RUTA_EDITAR && rutaActual) {
            // Volver a modo ver de la misma ruta
            cambiarEstado(ESTADOS.RUTA_VER, {
                ruta: rutaActual
            });
        } else {
            // Volver a neutro
            cambiarEstado(ESTADOS.NEUTRO);
        }
    }

    function actualizarListaZonasSeleccionadas() {
        const lista = document.getElementById('zonas-seleccionadas-lista');
        const contador = document.getElementById('contador-zonas');

        lista.innerHTML = '';
        contador.textContent = rutaEnConstruccion.zonas.size;

        rutaEnConstruccion.zonas.forEach((data, idZona) => {
            const zonaInfo = zonasData.get(idZona);
            const nombre = zonaInfo ? zonaInfo.nombre_zona : `Zone ${idZona}`;
            const numDirs = data.direcciones.size;

            const item = document.createElement('div');
            item.style.cssText = 'padding: 6px; margin-bottom: 4px; background: white; border-radius: 3px; font-size: 0.9em; display: flex; justify-content: space-between; align-items: center;';
            item.innerHTML = `
            <span>${nombre} ${numDirs > 0 ? `(${numDirs} addr)` : '(nav only)'}</span>
            <button style="background: #e74c3c; color: white; border: none; border-radius: 3px; padding: 2px 6px; cursor: pointer; font-size: 0.8em;">×</button>
        `;

            item.querySelector('button').onclick = () => {
                rutaEnConstruccion.zonas.delete(idZona);
                // Resetear visual de la zona en el mapa
                capaZonas.eachLayer(layer => {
                    if (layer.zonaId === idZona) {
                        layer.setStyle({
                            color: '#3388ff',
                            weight: 2,
                            fillOpacity: 0.2
                        });
                    }
                });
                actualizarListaZonasSeleccionadas();
            };

            lista.appendChild(item);
        });
    }

    function actualizarListaZonasEdicion() {
        // Similar a actualizarListaZonasSeleccionadas pero para modo edición
        const lista = document.getElementById('zonas-seleccionadas-lista');
        const contador = document.getElementById('contador-zonas');
        
        if (!rutaActual || !rutaActual.zonas) {
            lista.innerHTML = '';
            contador.textContent = '0';
            return;
        }
        
        lista.innerHTML = '';
        contador.textContent = rutaActual.zonas.length;
        
        rutaActual.zonas.forEach(zona => {
            const numDirs = zona.direcciones ? zona.direcciones.length : 0;
            const item = document.createElement('div');
            item.style.cssText = 'padding: 6px; margin-bottom: 4px; background: white; border-radius: 3px; font-size: 0.9em; display: flex; justify-content: space-between; align-items: center;';
            item.innerHTML = `
                <span>${zona.nombre_zona} (${numDirs} addr)</span>
                <button class="btn-quitar-zona" data-id="${zona.id_zona}" style="background: #e74c3c; color: white; border: none; border-radius: 3px; padding: 2px 6px; cursor: pointer; font-size: 0.8em;">×</button>
            `;
            
            item.querySelector('.btn-quitar-zona').onclick = async () => {
                suiteConfirm('Remove Zone', `Remove zone "${zona.nombre_zona}" from route?`).then(async (confirmar) => {
                    if (confirmar) {
                        await quitarZonaDeRuta(zona.id_zona);
                    }
                });
            };
            
            lista.appendChild(item);
        });
    }


    function actualizarIndicadorEstado(texto = null) {
        const indicador = document.getElementById('estado-indicador');
        const textos = {
            [ESTADOS.NEUTRO]: {
                text: 'Ready',
                bg: '#95a5a6'
            },
            [ESTADOS.ADDRESSES]: {
                text: 'Viewing All Addresses',
                bg: '#9b59b6'
            },
            [ESTADOS.RUTA_VER]: {
                text: `Viewing: ${rutaActual?.nombre_ruta || 'Route'}`,
                bg: '#3498db'
            },
            [ESTADOS.RUTA_EDITAR]: {
                text: `Editing: ${rutaActual?.nombre_ruta || 'Route'}`,
                bg: '#f39c12'
            }
        };

        const config = textos[estadoActual] || {
            text: texto || 'Ready',
            bg: '#95a5a6'
        };
        indicador.textContent = config.text;
        indicador.style.backgroundColor = config.bg;
    }

    function limpiarModoAddresses() {
        map.removeLayer(capaTodasDirecciones);
        capaTodasDirecciones.clearLayers();
        const btn = document.getElementById('leaflet-btn-addresses');
        if (btn) btn.style.backgroundColor = 'white';
    }

    function limpiarRutaActual() {
        rutaActual = null;
        capaDireccionesRuta.clearLayers();
        map.removeLayer(capaDireccionesRuta);
        map.removeLayer(capaDireccionesLibres);
        capaDireccionesLibres.clearLayers();
        destruirFormularioMovible();
    }

    function zoomADireccion(idDireccion) {
        // Buscar en capa de ruta
        let encontrada = null;
        capaDireccionesRuta.eachLayer(layer => {
            if (layer.direccionId === idDireccion) {
                encontrada = layer;
            }
        });

        if (encontrada) {
            map.setView(encontrada.getLatLng(), 17);
            encontrada.openPopup();

            // Highlight temporal
            const originalFill = encontrada.options.fillColor;
            encontrada.setStyle({
                fillColor: '#ffff00',
                fillOpacity: 1,
                radius: 100
            });
            setTimeout(() => {
                encontrada.setStyle({
                    fillColor: originalFill,
                    fillOpacity: 0.7,
                    radius: 75
                });
            }, 1500);
        }
    }

    function configurarEventListeners() {
        document.getElementById('btn-volver').onclick = () => {
            window.location.href = '<?= RUTA_REAL ?>/dashboard';
        };

        document.getElementById('btn-crear-ruta').onclick = iniciarCreacionRuta;
        document.getElementById('btn-cancelar-accion').onclick = cancelarAccion;
    }

    function hexToRgba(hex, alpha) {
        hex = hex.replace('#', '');
        if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function minutosAHorasMinutos(totalMinutos) {
        if (!totalMinutos || totalMinutos <= 0) return '00:00';
        const horas = Math.floor(totalMinutos / 60);
        const minutos = totalMinutos % 60;
        return `${horas.toString().padStart(2, '0')}:${minutos.toString().padStart(2, '0')}`;
    }

    function mostrarLoading(mostrar) {
        // Implementar o usar suiteLoading si existe
        if (mostrar) {
            console.log('⏳ Loading...');
        } else {
            console.log('✅ Ready');
        }
    }

    async function confirmarEliminarDireccion(idDireccion) {
        // TODO: Confirmar y eliminar dirección de ruta
        console.log('TODO: Eliminar dirección', idDireccion);
    }

</script>