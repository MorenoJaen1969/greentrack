<?php
// app/views/content/rutas_mapa-view.php
// Ruta Mapa - Inglés de Texas (Conroe Style)

// Verificar si el usuario tiene permisos para acceder a esta vista
// if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
//     exit("Access denied, partner.");
// }

$ruta_rutas_mapa_ajax = RUTA_APP . "/app/ajax/rutas_mapaAjax.php";
$ruta_direcciones_ajax = RUTA_APP . "/app/ajax/direccionesAjax.php";
?>

<!-- Contenedor Principal -->
<div id="rutas-map-container" class="principal">
    <!-- Barra de título superior -->
    <div id="rutas-title-bar"
        style="background-color: #2c3e50; color: white; padding: 10px; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0; padding-left: 40px; font-size: 1.5em;">Ruta Map - GreenTrack Live</h2>
        <div>
            <!-- Botón para volver o menú de usuario -->
            <button id="btn-volver" style="margin-right: 10px; padding: 5px 10px;">Back</button>
            <span>Hey there, <?php echo $_SESSION['user_name'] ?? 'Partner'; ?>!</span>
        </div>
    </div>

    <!-- Contenedor principal con panel lateral y mapa -->
    <div style="display: flex; flex: 1; overflow: hidden;">

        <!-- Panel Lateral (Opciones, Lista de Rutas, etc.) -->
        <div id="rutas-sidebar"
            style="width: 300px; background-color: #ecf0f1; padding: 15px; overflow-y: auto; border-right: 1px solid #bdc3c7;">
            <h4>Route Builder</h4>
            <div id="modo-indicador"
                style="text-align: right; margin-bottom: 10px; font-weight: bold; font-size: 1.2em; color: #2c3e50;">
            </div>
            <div id="rutas-controls">
                <label for="input-nombre-ruta">Route Name:</label>
                <div style="position: relative; margin-bottom: 10px;">
                    <input type="text" id="input-nombre-ruta" placeholder="Like 'Monday Rute - Conroe'"
                        style="width: 100%; padding: 5px; margin-bottom: 0;" disabled>
                    <div id="tooltip-nombre-ruta" style="display: none; position: absolute; top: 100%; left: 90px; 
                                background: #e9dc29ff; color: #000000ff; padding: 6px 10px; 
                                border-radius: 4px; font-size: 0.85em; z-index: 10; 
                                margin-top: 5px; white-space: nowrap;">
                        ➤ Enter a name for your new route
                        <div style="position: absolute; top: -6px; left: 10px; 
                                    width: 0; height: 0; 
                                    border-left: 6px solid transparent;
                                    border-right: 6px solid transparent;
                                    border-bottom: 6px solid #3498db;"></div>
                    </div>
                </div>

                <label for="input-color-ruta">Route Color:</label>
                <input type="color" id="input-color-ruta" value="#3498db" title="Click to pick a route color"
                    style="width: 100%; margin-bottom: 10px; height: 40px; cursor: pointer; opacity: 0.6;" disabled>
                <div id="guia-color-ruta"
                    style="font-size: 0.8em; color: #7f8c8d; margin-bottom: 15px; font-style: italic;">
                    🎨 Click 'Create New Route' to enable color picker
                </div>

                <button id="btn-crear-ruta"
                    style="width: 100%; padding: 10px; margin-bottom: 5px; background-color: #27ae60; color: white; border: none; cursor: pointer;">Create
                    New Route</button>
                <button id="btn-cancelar-accion"
                    style="width: 100%; padding: 10px; margin-bottom: 10px; background-color: #95a5a6; color: white; border: none; cursor: pointer; display: none;">Cancel
                    Action</button>
                <button id="btn-actualizar-ruta"
                    style="width: 100%; padding: 10px; margin-bottom: 5px; background-color: #f39c12; color: white; border: none; cursor: pointer; display: none;">Update
                    This Route</button>
                <button id="btn-eliminar-ruta"
                    style="width: 100%; padding: 10px; margin-bottom: 10px; background-color: #e74c3c; color: white; border: none; cursor: pointer; display: none;">Delete
                    This One</button>

                <hr>

                <h4>Selected Zones (<span id="contador-zonas">0</span>)</h4>
                <div id="zonas-seleccionadas-lista"
                    style="max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 5px; background-color: #fff;">
                    <!-- Selected zones will show up here -->
                </div>

                <hr>

                <h4>Existing Routes</h4>
                <div id="rutas-lista"
                    style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 5px; background-color: #fff;">
                    <!-- Routes will load here -->
                    <p>Loading routes...</p>
                </div>
            </div>
        </div>

        <!-- Contenedor del Mapa Leaflet -->
        <div id="mapa_geo" style="flex: 1; z-index: 1;"></div>

    </div>

    <!-- Pie de página opcional -->
    <div id="rutas-footer" style="background-color: #bdc3c7; padding: 5px; text-align: center; font-size: 0.9em;">
        GreenTrack Live — Built for Conroe, TX
    </div>

</div>

<!-- Script Incrustado (JavaScript) -->
<script>
    // ============================================
    // VARIABLES GLOBALES Y CONFIGURACIÓN
    // ============================================
    
    let direccionesSeleccionadas = new Set();

    // Variables de Ruta (ya definidas en PHP)
    const ruta_rutas_mapa_ajax = "<?php echo $ruta_rutas_mapa_ajax; ?>";
    const ruta_direcciones_ajax = "<?php echo $ruta_direcciones_ajax; ?>";

    // Inicializar variables globales para el mapa y elementos Leaflet
    let map = null;
    let zonasLayerGroup = null;
    let rutasLayerGroup = null;
    let direccionesLayerGroup = null;
    let direccionesRutaLayerGroup = null;
    let direccionesLibresLayerGroup = null;
    let direccionesAsignadasLayerGroup = null;

    let zonasSeleccionadas = new Set();
    let modoEdicionRuta = null;
    let modoGuardarRuta = null;
    let rutaActual = null;

    let direccionesCargadas = false;
    let _actualizandoEstado = false;
    let btnToggleDirecciones = null;

    // Variables para controlar el formulario de direcciones
    let formularioZonaActiva = null;
    let zonaActivaId = null;
    let tTiempo = "00:00:00";

    // ============================================
    // NUEVO: SISTEMA DE MODOS DE VISUALIZACIÓN
    // ============================================
    const MODO_VISUALIZACION = {
        VER: 'ver',           // Solo lectura - mapa deshabilitado
        EDITAR: 'editar'      // Edición activa - mapa habilitado
    };
    let modoVisualizacion = MODO_VISUALIZACION.VER;
    let mapaDeshabilitado = true;

    // Coordenadas del Headquarter (HQ) - Conroe, TX
    const HQ_LAT = 30.3204272;
    const HQ_LNG = -95.4217815;
    const HQ_COORDS = [HQ_LAT, HQ_LNG];

    // ============================================
    // FUNCIONES AUXILIARES
    // ============================================

    function suiteAlertSuccess(titulo, mensaje) {
        return mostrarSuiteAlert('success', titulo, mensaje);
    }

    function suiteAlertError(titulo, mensaje) {
        return mostrarSuiteAlert('error', titulo, mensaje);
    }

    function suiteAlertWarning(titulo, mensaje) {
        return mostrarSuiteAlert('warning', titulo, mensaje);
    }

    function suiteAlertInfo(titulo, mensaje) {
        return mostrarSuiteAlert('info', titulo, mensaje);
    }

    function suiteConfirm(titulo, mensaje, opcionesConfirm = {}) {
        const opciones = {
            botones: [
                { texto: opcionesConfirm.cancelar || 'Nah, forget it', tipo: 'secondary', valor: false },
                { texto: opcionesConfirm.aceptar || 'Yep, do it', tipo: 'primary', valor: true }
            ]
        };
        return mostrarSuiteAlert('warning', titulo, mensaje, opciones);
    }

    function mostrarTooltipNombre(mostrar = true) {
        const tooltip = document.getElementById('tooltip-nombre-ruta');
        if (tooltip) {
            tooltip.style.display = mostrar ? 'block' : 'none';
        }
    }

    function mostrarSuiteAlert(tipo, titulo, mensaje, opciones = {}) {
        return new Promise((resolve) => {
            const alertaExistente = document.querySelector('.alerta-overlay');
            if (alertaExistente) {
                alertaExistente.remove();
            }

            const overlay = document.createElement('div');
            overlay.className = 'alerta-overlay';

            const alerta = document.createElement('div');
            alerta.className = 'suite-alerta-box';

            const header = document.createElement('div');
            header.className = `alerta-header ${tipo}`;

            const icon = document.createElement('div');
            icon.className = 'alerta-icon';

            const iconos = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };
            icon.textContent = iconos[tipo] || 'ℹ';

            const title = document.createElement('h2');
            title.className = 'alerta-title';
            title.textContent = titulo;

            const body = document.createElement('div');
            body.className = 'alerta-body';

            const message = document.createElement('p');
            message.className = 'alerta-message';
            message.textContent = mensaje;

            const actions = document.createElement('div');
            actions.className = 'alerta-actions';

            const botones = opciones.botones || [{ texto: 'Alright', tipo: 'primary' }];

            botones.forEach((btn, index) => {
                const button = document.createElement('button');
                button.className = `btn-alerta btn-alerta-${btn.tipo}`;
                button.textContent = btn.texto;
                button.onclick = () => {
                    overlay.classList.remove('activo');
                    setTimeout(() => {
                        overlay.remove();
                        resolve(btn.valor || index);
                    }, 300);
                };
                actions.appendChild(button);
            });

            header.appendChild(icon);
            header.appendChild(title);
            body.appendChild(message);
            body.appendChild(actions);
            alerta.appendChild(header);
            alerta.appendChild(body);
            overlay.appendChild(alerta);

            document.body.appendChild(overlay);

            setTimeout(() => {
                overlay.classList.add('activo');
                alerta.classList.add('alerta-pulse');
            }, 10);

            const teclaEscape = (e) => {
                if (e.key === 'Escape') {
                    overlay.classList.remove('activo');
                    setTimeout(() => {
                        overlay.remove();
                        document.removeEventListener('keydown', teclaEscape);
                        resolve(null);
                    }, 300);
                }
            };
            document.addEventListener('keydown', teclaEscape);

            if (opciones.cerrarClickFuera !== false) {
                overlay.onclick = (e) => {
                    if (e.target === overlay) {
                        overlay.classList.remove('activo');
                        setTimeout(() => {
                            overlay.remove();
                            resolve(null);
                        }, 300);
                    }
                };
            }
        });
    }

    function minutosToHHMMSS(totalMinutos) {
        if (totalMinutos == null || totalMinutos < 0) {
            return "00:00:00";
        }

        const totalSegundos = Math.round(totalMinutos * 60);
        const horas = Math.floor(totalSegundos / 3600);
        const minutos = Math.floor((totalSegundos % 3600) / 60);
        const segundos = totalSegundos % 60;

        const pad = (num) => String(num).padStart(2, '0');
        return `${pad(horas)}:${pad(minutos)}:${pad(segundos)}`;
    }

    function suiteLoading(action = 'show') {
        const existingLoading = document.getElementById('suite-loading');

        if (action === 'show') {
            if (existingLoading) {
                existingLoading.style.display = 'flex';
                return;
            }

            const loadingOverlay = document.createElement('div');
            loadingOverlay.id = 'suite-loading';
            loadingOverlay.className = 'suite-loading-overlay';

            const svgHTML = `
                <svg class="infinity-svg" viewBox="-40 -20 480 160" preserveAspectRatio="xMidYMid meet">
                    <defs>
                        <linearGradient id="glassGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#00d4aa" />
                            <stop offset="50%" stop-color="#0099ff" />
                            <stop offset="100%" stop-color="#00d4aa" />
                        </linearGradient>
                    </defs>
                    <path class="infinity-path" 
                        d="M 200,60 
                            C 100,0 100,120 200,60 
                            C 300,120 300,0 200,60 
                            Z" 
                        fill="none" 
                        stroke="url(#glassGradient)" 
                        stroke-width="20" 
                        stroke-linecap="round"/>
                </svg>
            `;

            loadingOverlay.innerHTML = svgHTML;
            document.body.appendChild(loadingOverlay);
            document.body.classList.add('suite-loading-active');

        } else if (action === 'hide') {
            if (existingLoading) {
                existingLoading.remove();
                document.body.classList.remove('suite-loading-active');
            }
        }
    }

    function hexToRgba(hex, alpha = 0.3) {
        hex = hex.replace('#', '');
        if (hex.length === 3) {
            hex = hex.split('').map(c => c + c).join('');
        }
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    suiteLoading.while = async function (promise) {
        this('show');
        try {
            const result = await promise;
            return result;
        } finally {
            this('hide');
        }
    };

    // ============================================
    // NUEVO: CONTROL DE ESTADOS DEL MAPA
    // ============================================

    function deshabilitarMapa() {
        console.log('🔒 Modo VER: Navegación activa, elementos deshabilitados');
        mapaDeshabilitado = true;
        
        if (!map) return;
        
        // ============================================
        // 1. DESHABILITAR CLICKS EN ZONAS
        // ============================================
        zonasLayerGroup.eachLayer(layer => {
            layer.off('click');  // Remover cualquier handler de click
            layer.setStyle({ 
                interactive: false,  // No responder a mouse events
                cursor: 'default' 
            });
        });
        
        // ============================================
        // 2. DESHABILITAR CLICKS EN DIRECCIONES/MARKERS
        // ============================================
        direccionesLayerGroup.eachLayer(layer => {
            layer.off('click');     // Remover click
            layer.off('mouseover'); // Opcional: remover hover si lo tienes
            layer.setStyle({ 
                interactive: false,  // No responder a mouse
                cursor: 'default' 
            });
            // Cerrar popup si está abierto
            layer.closePopup();
        });
        
        // También deshabilitar en otras capas de direcciones si existen
        if (direccionesRutaLayerGroup) {
            direccionesRutaLayerGroup.eachLayer(layer => {
                layer.off('click');
                layer.setStyle({ interactive: false });
                layer.closePopup();
            });
        }
        if (direccionesLibresLayerGroup) {
            direccionesLibresLayerGroup.eachLayer(layer => {
                layer.off('click');
                layer.setStyle({ interactive: false });
                layer.closePopup();
            });
        }
        
        // ============================================
        // 3. VISUAL: Indicar elementos no clickeables
        // ============================================
        zonasLayerGroup.eachLayer(layer => {
            const enRuta = rutaActual?.zonas?.some(z => z.id_zona === layer.zonaId);
            layer.setStyle({
                fillOpacity: enRuta ? 0.15 : 0.03,  // Muy transparente
                weight: enRuta ? 1 : 0.5,
                color: enRuta ? rutaActual?.color_ruta || '#3498db' : '#aaaaaa'
            });
        });
        
        // Atenuar direcciones visualmente
        direccionesLayerGroup.eachLayer(layer => {
            layer.setStyle({
                opacity: 0.6,
                fillOpacity: 0.4
            });
        });
        
        // ============================================
        // 4. INDICADOR VISUAL
        // ============================================
        const indicador = document.getElementById('modo-indicador');
        if (indicador) {
            indicador.textContent = '👁️ View Mode - Navigate only';
            indicador.style.backgroundColor = '#e8f4f8';
            indicador.style.padding = '6px';
            indicador.style.borderRadius = '4px';
        }
        
        console.log('✅ Zoom/Pan: ACTIVO | Zonas/Direcciones: INACTIVAS');
    }

    /**
     * Modo EDITAR: Habilitar interacción con elementos
     */
    function habilitarMapa() {
        console.log('🔓 Modo EDITAR: Todos los elementos habilitados');
        mapaDeshabilitado = false;
        
        if (!map) return;
        
        // ============================================
        // 1. HABILITAR CLICKS EN ZONAS
        // ============================================
        zonasLayerGroup.eachLayer(layer => {
            layer.setStyle({ 
                interactive: true, 
                cursor: 'pointer' 
            });
            
            // Re-asignar handler de click
            layer.on('click', function(e) {
                if (modoEdicionRuta === 'editar' && modoVisualizacion === MODO_VISUALIZACION.EDITAR) {
                    seleccionarZonaConModal(layer);
                }
            });
        });
        
        // ============================================
        // 2. HABILITAR CLICKS EN DIRECCIONES/MARKERS
        // ============================================
        direccionesLayerGroup.eachLayer(layer => {
            layer.setStyle({ 
                interactive: true,
                cursor: 'pointer' 
            });
            
            // Re-asignar handler para abrir popup
            layer.on('click', function(e) {
                L.DomEvent.stopPropagation(e);  // Evitar que el click llegue al mapa
                this.openPopup();
            });
        });
        
        // Rehabilitar en otras capas
        if (direccionesRutaLayerGroup) {
            direccionesRutaLayerGroup.eachLayer(layer => {
                layer.setStyle({ interactive: true });
                layer.on('click', function(e) {
                    L.DomEvent.stopPropagation(e);
                    this.openPopup();
                });
            });
        }
        if (direccionesLibresLayerGroup) {
            direccionesLibresLayerGroup.eachLayer(layer => {
                layer.setStyle({ interactive: true });
                layer.on('click', function(e) {
                    L.DomEvent.stopPropagation(e);
                    this.openPopup();
                });
            });
        }
        
        // ============================================
        // 3. VISUAL: Restaurar apariencia normal
        // ============================================
        zonasLayerGroup.eachLayer(layer => {
            const enRuta = rutaActual?.zonas?.some(z => z.id_zona === layer.zonaId);
            layer.setStyle({
                fillOpacity: enRuta ? 0.4 : 0.1,
                weight: enRuta ? 2 : 1,
                color: enRuta ? rutaActual?.color_ruta || '#3498db' : '#3388ff'
            });
        });
        
        // Restaurar opacidad de direcciones
        direccionesLayerGroup.eachLayer(layer => {
            layer.setStyle({
                opacity: 1,
                fillOpacity: 0.7
            });
        });
        
        // ============================================
        // 4. INDICADOR VISUAL
        // ============================================
        const indicador = document.getElementById('modo-indicador');
        if (indicador) {
            indicador.textContent = '✏️ Edit Mode - Click zones or addresses';
            indicador.style.backgroundColor = '#fef5d4';
            indicador.style.padding = '6px';
            indicador.style.borderRadius = '4px';
        }
        
        suiteAlertInfo('Edit Mode Active', 'You can now click zones or addresses to modify this route.');
    }

    function filtrarMarkersPorZona(idZona = null) {
        console.log('Filtrando markers. Zona específica:', idZona);
        
        const direccionesDeRuta = rutaActual?.zonas?.flatMap(z => 
            z.direcciones.map(d => ({...d, id_zona: z.id_zona}))
        ) || [];
        
        const idsDireccionesRuta = new Set(direccionesDeRuta.map(d => d.id_direccion));
        
        direccionesLayerGroup.eachLayer(layer => {
            const dirId = layer.direccionId;
            const esDeRuta = idsDireccionesRuta.has(dirId);
            const dirInfo = direccionesDeRuta.find(d => d.id_direccion === dirId);
            const zonaDeDir = dirInfo?.id_zona;
            
            if (idZona === null) {
                if (esDeRuta) {
                    layer.setStyle({ opacity: 1, fillOpacity: 0.7 });
                } else {
                    layer.setStyle({ opacity: 0.2, fillOpacity: 0.1 });
                }
            } else {
                if (zonaDeDir === idZona) {
                    layer.setStyle({ opacity: 1, fillOpacity: 0.9, weight: 3 });
                    layer.bringToFront();
                } else if (esDeRuta) {
                    layer.setStyle({ opacity: 0.4, fillOpacity: 0.3 });
                } else {
                    layer.setStyle({ opacity: 0.1, fillOpacity: 0.05 });
                }
            }
        });
    }

    // ============================================
    // CONFIGURACIÓN Y CARGA INICIAL
    // ============================================

    async function cargarConfigYIniciar() {
        try {
            const response = await fetch('/app/ajax/datosgeneralesAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_DG: 'datos_para_gps'
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            const DEFAULT_CONFIG = {
                mapa_base: 'ESRI',
                umbral_metros: 150,
                umbral_minutos: 5
            };

            if (data.error) {
                console.warn('⚠️ Error en datosgeneralesAjax:', data.error);
            } else {
                window.APP_CONFIG = window.APP_CONFIG || {};

                if (data.success && data.config) {
                    window.APP_CONFIG = {
                        mapa_base: data.config.mapa_base || DEFAULT_CONFIG.mapa_base,
                        umbral_metros: parseInt(data.config.umbral_metros, 10) || DEFAULT_CONFIG.umbral_metros,
                        umbral_minutos: parseInt(data.config.umbral_minutos, 10) || DEFAULT_CONFIG.umbral_minutos
                    };

                    if (isNaN(window.APP_CONFIG.umbral_metros)) {
                        window.APP_CONFIG.umbral_metros = DEFAULT_CONFIG.umbral_metros;
                    }
                    if (isNaN(window.APP_CONFIG.umbral_minutos)) {
                        window.APP_CONFIG.umbral_minutos = DEFAULT_CONFIG.umbral_minutos;
                    }
                } else {
                    console.warn('⚠️ Config no recibida o error:', data.error);
                    window.APP_CONFIG = { ...DEFAULT_CONFIG };
                }

                console.log('✅ APP_CONFIG final:', window.APP_CONFIG);
                window.dispatchEvent(new Event('configListo'));
            }

            console.log('✅ mapa_base cargado:', window.APP_CONFIG.mapa_base);
        } catch (err) {
            console.error('❌ Error al cargar datos generales:', err.message);
            window.APP_CONFIG.mapa_base = 'ESRI';
            window.APP_CONFIG.umbral_metros = 150;
            window.APP_CONFIG.umbral_minutos = 5;
            window.dispatchEvent(new Event('configListo'));
        }
    }

    // ============================================
    // FUNCIONES DE CARGA DE DATOS
    // ============================================

    async function cargarDireccionesDeZonaenRutas(id_ruta) {
        try {
            suiteLoading('show');

            const response = await fetch(ruta_rutas_mapa_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_rutas: 'obtener_clientes',
                    id_ruta: id_ruta
                })
            });

            const data = await response.json();
            const direcciones = data.success ? data.data : [];
            console.log("Direcciones recibidas:", direcciones.length);

            if (!map.hasLayer(direccionesLayerGroup)) {
                map.addLayer(direccionesLayerGroup);
            }

            if (rutaActual?.zonas && direcciones.length > 0) {
                console.log("Asociando direcciones y creando markers...");
                
                function puntoEnZona(lat, lng, zona) {
                    const lt = parseFloat(lat), ln = parseFloat(lng);
                    return (
                        lt >= parseFloat(zona.lat_sw) && 
                        lt <= parseFloat(zona.lat_ne) && 
                        ln >= parseFloat(zona.lng_sw) && 
                        ln <= parseFloat(zona.lng_ne)
                    );
                }
                
                rutaActual.zonas.forEach(z => z.direcciones = []);
                
                const colorRuta = rutaActual.color_ruta || "#3498db";
                
                direcciones.forEach(dir => {
                    if (!dir.lat || !dir.lng) {
                        console.warn('Dirección sin coordenadas:', dir);
                        return;
                    }
                    
                    let zonaAsignada = null;
                    for (const zona of rutaActual.zonas) {
                        if (puntoEnZona(dir.lat, dir.lng, zona)) {
                            zonaAsignada = zona;
                            break;
                        }
                    }
                    
                    if (!zonaAsignada) {
                        console.warn('Dirección fuera de zonas:', dir.cliente_nombre);
                        return;
                    }
                    
                    const circle = L.circle([parseFloat(dir.lat), parseFloat(dir.lng)], {
                        radius: 75,
                        color: "#ccff9fff",
                        fillColor: colorRuta,
                        fillOpacity: 0.7,
                        weight: 2
                    }).addTo(direccionesLayerGroup);
                    
                    window.direccionesMarkers[dir.id_direccion] = circle;
                    
                    circle.direccionId = dir.id_direccion;
                    circle.bindPopup(`
                        <b>${dir.cliente_nombre || '—'}</b><br>
                        ${dir.direccion}<br>
                        <em>Zone: ${zonaAsignada.nombre_zona}</em>
                    `);
                    
                    zonaAsignada.direcciones.push({
                        id_direccion: dir.id_direccion,
                        cliente_nombre: dir.cliente_nombre,
                        direccion: dir.direccion,
                        tiempo_servicio: dir.tiempo_servicio || 0,
                        lat: parseFloat(dir.lat),
                        lng: parseFloat(dir.lng)
                    });
                    
                    console.log(`✅ Marker creado para ${dir.cliente_nombre} (ID: ${dir.id_direccion})`);
                });
                
                console.log("Markers registrados:", Object.keys(window.direccionesMarkers).length);
                actualizarTotalTiempo();
            }

            const lista = document.getElementById('lista-direcciones-zona');
            if (!lista) return;

            const todasLasDirecciones = rutaActual?.zonas?.flatMap(z => z.direcciones) || [];

            if (todasLasDirecciones.length === 0) {
                lista.innerHTML = '<p style="color:#7f8c8d; font-style:italic;">No addresses assigned.</p>';
            } else {
                lista.innerHTML = todasLasDirecciones.map(dir => `
                    <div class="direccion-item-en-formulario elem_direcc" data-id="${dir.id_direccion}">
                        <span>
                            <strong>${dir.cliente_nombre || '—'}</strong><br>
                            <small>${dir.direccion}</small><br>
                            <em class="ele_hora">⏱️ ${dir.tiempo_servicio || 0} min</em>
                        </span>
                        <button class="btn-eliminar-direccion" 
                            data-id="${dir.id_direccion}" 
                            style="background:#e74c3c; color:white; border:none; border-radius:4px; padding:2px 8px; font-size:0.85em; cursor:pointer;">
                            Remove
                        </button>
                    </div>
                `).join('');

                lista.querySelectorAll('.direccion-item-en-formulario').forEach(item => {
                    item.addEventListener('click', (e) => {
                        if (e.target.closest('.btn-eliminar-direccion')) return;

                        const idDireccion = parseInt(item.dataset.id);
                        console.log('Click en dirección ID:', idDireccion);
                        
                        const marker = window.direccionesMarkers?.[idDireccion];
                        
                        let zonaDeDir = null;
                        for (const zona of rutaActual.zonas) {
                            if (zona.direcciones.some(d => d.id_direccion === idDireccion)) {
                                zonaDeDir = zona.id_zona;
                                break;
                            }
                        }

                        if (marker) {
                            map.setView(marker.getLatLng(), 17);
                            map.closePopup();
                            marker.openPopup();
                            
                            if (modoVisualizacion === MODO_VISUALIZACION.VER) {
                                filtrarMarkersPorZona(zonaDeDir);
                                setTimeout(() => {
                                    filtrarMarkersPorZona(null);
                                }, 3000);
                            }
                            
                            const originalFill = marker.options.fillColor;
                            marker.setStyle({ fillColor: '#ffff00', fillOpacity: 1, radius: 100 });
                            setTimeout(() => {
                                marker.setStyle({ 
                                    fillColor: originalFill, 
                                    fillOpacity: 0.7,
                                    radius: 75 
                                });
                            }, 800);
                            
                        } else {
                            console.error('❌ Marcador no encontrado para ID:', idDireccion);
                            suiteAlertWarning('Not found', 'Address marker not available on map.');
                        }
                    });
                });

                lista.querySelectorAll('.btn-eliminar-direccion').forEach(btn => {
                    btn.addEventListener('click', async (e) => {
                        e.stopPropagation();
                        const idDireccion = btn.dataset.id;
                        let idZona = null;
                        for (const zona of rutaActual.zonas) {
                            if (zona.direcciones.some(d => d.id_direccion == idDireccion)) {
                                idZona = zona.id_zona;
                                break;
                            }
                        }
                        await eliminarDireccionDeZona(idZona, idDireccion);
                        cargarDireccionesDeZonaenRutas(id_ruta);
                    });
                });
            }

        } catch (err) {
            console.error("Error cargando direcciones:", err);
            suiteAlertError('Uh oh!', 'Failed to load addresses.');
        } finally {
            suiteLoading('hide');
        }
    }

    async function cargarDireccionesDeZona(idZona, nombreZona) {
        try {
            suiteLoading('show');

            const response = await fetch("<?php echo RUTA_APP; ?>/app/ajax/zonasAjax.php", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_zonas: 'listar_direcciones_de_zona',
                    id_zona: idZona
                })
            });

            const data = await response.json();
            const direcciones = data.success ? data.data : [];

            const lista = document.getElementById('lista-direcciones-zona');
            if (!lista) return;

            if (direcciones.length === 0) {
                lista.innerHTML = '<p style="color:#7f8c8d; font-style:italic;">No addresses assigned.</p>';
            } else {
                lista.innerHTML = direcciones.map(dir => `
                    <div style="padding: 6px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                        <span><strong>${dir.cliente_nombre || '—'}</strong><br><small>${dir.direccion}</small></span>
                        <button class="btn-eliminar-direccion" data-id="${dir.id_direccion}" style="background:#e74c3c; color:white; border:none; border-radius:4px; padding:2px 8px; font-size:0.85em; cursor:pointer;">
                            Remove
                        </button>
                    </div>
                `).join('');

                lista.querySelectorAll('.btn-eliminar-direccion').forEach(btn => {
                    btn.addEventListener('click', async (e) => {
                        e.stopPropagation();
                        const idDireccion = btn.dataset.id;
                        await eliminarDireccionDeZona(idZona, idDireccion);
                        cargarDireccionesDeZona(idZona, nombreZona);
                    });
                });
            }

        } catch (err) {
            console.error("Error al cargar direcciones de la zona:", err);
            suiteAlertError('Uh oh!', 'Failed to load zone addresses.');
        } finally {
            suiteLoading('hide');
        }
    }

    async function eliminarDireccionDeZona(idZona, idDireccion) {
        const confirmar = await suiteConfirm(
            'Remove Address?',
            'Are you sure you want to remove this address from the zone?',
            { aceptar: 'Yes, Remove', cancelar: 'Keep It' }
        );
        if (!confirmar) return;

        try {
            suiteLoading('show');
            const response = await fetch("<?php echo RUTA_APP; ?>/app/ajax/zonasAjax.php", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_zonas: 'eliminar_direccion_de_zona',
                    id_zona: idZona,
                    id_direccion: idDireccion
                })
            });
            const data = await response.json();
            if (data.success) {
                suiteAlertSuccess('Removed!', 'Address removed from zone.');
                if (rutaActual) {
                    cargarZonasRuta(rutaActual.id_ruta);
                }
            } else {
                throw new Error(data.error || 'Failed to remove address.');
            }
            actualizarTotalTiempo();
        } catch (err) {
            suiteAlertError('Dang it!', err.message);
        } finally {
            suiteLoading('hide');
        }
    }

    // ============================================
    // FUNCIONES DE GESTIÓN DE RUTAS
    // ============================================

    function actualizarEstadoInterfaz() {
        if (_actualizandoEstado) return;
        _actualizandoEstado = true;

        try {
            const btnCrear = document.getElementById('btn-crear-ruta');
            const btnCancelar = document.getElementById('btn-cancelar-accion');
            const inputNombre = document.getElementById('input-nombre-ruta');
            const inputColor = document.getElementById('input-color-ruta');
            const guiaColor = document.getElementById('guia-color-ruta');
            const btnActualizar = document.getElementById('btn-actualizar-ruta');
            const btnEliminar = document.getElementById('btn-eliminar-ruta');
            const modoIndicador = document.getElementById('modo-indicador');

            if (modoEdicionRuta === 'crear') {
                modoIndicador.textContent = '🟢 Creating Route';
                modoIndicador.style.backgroundColor = '#d5f5d5';
                modoIndicador.style.padding = '6px';
                modoIndicador.style.borderRadius = '4px';
            } else if (modoEdicionRuta === 'editar') {
                modoIndicador.textContent = '✏️ Editing Route';
                modoIndicador.style.backgroundColor = '#fef5d4';
                modoIndicador.style.padding = '6px';
                modoIndicador.style.borderRadius = '4px';
            } else if (modoEdicionRuta === 'eliminar') {
                modoIndicador.textContent = '🗑️ Deleting Route';
                modoIndicador.style.backgroundColor = '#fadbd8';
                modoIndicador.style.padding = '6px';
                modoIndicador.style.borderRadius = '4px';
            } else {
                modoIndicador.textContent = '';
                modoIndicador.style.backgroundColor = 'transparent';
                modoIndicador.style.padding = '0';
                modoIndicador.style.borderRadius = '0';
            }

            if (modoEdicionRuta === null) {
                zonasLayerGroup.eachLayer(layer => {
                    layer.setStyle({ color: "#3388ff", weight: 1, fillOpacity: 0.1 });
                });
                zonasSeleccionadas.clear();
                actualizarListaZonasSeleccionadas();
                direccionesSeleccionadas.clear();                
            }

            if (modoEdicionRuta === 'crear' || modoEdicionRuta === 'editar') {
                btnCancelar.style.display = 'block';
                btnCrear.textContent = (modoEdicionRuta === 'crear' && zonasSeleccionadas.size > 0)
                    ? 'Save Route'
                    : 'Create New Route';
                btnActualizar.style.display = (modoEdicionRuta === 'editar') ? 'block' : 'none';
                btnEliminar.style.display = (modoEdicionRuta === 'editar') ? 'block' : 'none';
            } else {
                btnCancelar.style.display = 'none';
                btnCrear.textContent = 'Create New Route';
                btnActualizar.style.display = 'none';
                btnEliminar.style.display = 'none';
            }

            const enModoEdicion = (modoEdicionRuta === 'crear' || modoEdicionRuta === 'editar');
            inputNombre.disabled = !enModoEdicion;
            inputColor.disabled = !enModoEdicion;
            inputColor.style.opacity = enModoEdicion ? '1' : '0.6';
            guiaColor.textContent = enModoEdicion
                ? "🎨 Select a color for your route"
                : "🎨 Click 'Create New Route' to enable color picker";
            mostrarTooltipNombre(enModoEdicion && modoEdicionRuta === 'crear' && zonasSeleccionadas.size === 0);

            if (btnToggleDirecciones && map.hasLayer(direccionesLayerGroup)) {
                btnToggleDirecciones.style.backgroundColor = '#fff';
                map.removeLayer(direccionesLayerGroup);
            }
            btnCrear.textContent = (modoEdicionRuta === 'crear' && zonasSeleccionadas.size > 0)
                ? 'Save Route'
                : 'Create New Route';
        } finally {
            _actualizandoEstado = false;
        }
    }

    function inicializarMapa() {
        const contenedorId = 'mapa_geo';
        const contEl = document.getElementById(contenedorId);

        console.log('[initMapa] checking container:', contenedorId, '->', contEl);

        if (!contEl) {
            console.warn('❌ No se encontró el contenedor del mapa');
            return;
        }

        if (window.map) {
            try {
                window.map.remove();
            } catch (err) {
                console.warn('⚠️ Error al remover window.map existente:', err);
            }
            delete window.map;
        }

        try {
            const mapInstance = L.map(contEl).setView(HQ_COORDS, 13);

            const tipo = (window.APP_CONFIG && window.APP_CONFIG.mapa_base) ? window.APP_CONFIG.mapa_base.toUpperCase() : 'ESRI';
            console.log('🌍 Inicializando mapa con capa:', tipo);

            switch (tipo) {
                case 'OSM':
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(mapInstance);
                    break;
                case 'ESRI':
                    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                        attribution: 'Tiles &copy; Esri',
                        maxZoom: 20
                    }).addTo(mapInstance);
                    break;
                default:
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap'
                    }).addTo(mapInstance);
            }

            window.map = mapInstance;
            map = window.map;

            console.log('✅ Mapa inicializado con éxito.');
        } catch (err) {
            console.error('❌ Error al inicializar L.map:', err);
        }
    }

    function restablecerEstiloZonas() {
        zonasLayerGroup.eachLayer(function (layer) {
            layer.setStyle({ color: "#3388ff", weight: 1, fillOpacity: 0.1 });
        });
    }

    function ocultarFormularioZona() {
        if (formularioZonaActiva) {
            formularioZonaActiva.remove();
            formularioZonaActiva = null;
            zonaActivaId = null;
        }
    }

    function abrirModalOrdenarDirecciones(direcciones) {
        return new Promise((resolve) => {
            if (!Array.isArray(direcciones) || direcciones.length === 0) {
                resolve(null);
                return;
            }

            // Crear overlay
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0; left: 0;
                width: 100%; height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 20000;
                display: flex;
                justify-content: center;
                align-items: center;
            `;

            // Crear modal
            const modal = document.createElement('div');
            modal.style.cssText = `
                background: white;
                border-radius: 8px;
                width: 90%;
                max-width: 500px;
                max-height: 80vh;
                overflow: hidden;
                font-family: Arial, sans-serif;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            `;

            // Título
            const header = document.createElement('div');
            header.innerHTML = '<h3 style="margin: 0; padding: 16px; font-size: 1.1em; color: #2c3e50;">Reorder Route Addresses</h3>';
            modal.appendChild(header);

            // Contenedor de lista
            const lista = document.createElement('div');
            lista.style.cssText = `
                padding: 0 16px;
                max-height: 50vh;
                overflow-y: auto;
            `;
            modal.appendChild(lista);

            // ✅ CREAR FOOTER CON BOTONES DIRECTAMENTE (no innerHTML)
            const footer = document.createElement('div');
            footer.style.cssText = `
                padding: 16px;
                text-align: right;
                border-top: 1px solid #eee;
            `;

            // Botón Cancelar - creado con createElement
            const btnCancelar = document.createElement('button');
            btnCancelar.id = 'btn-cancelar-orden';
            btnCancelar.textContent = 'Cancel';
            btnCancelar.style.cssText = `
                margin-right: 10px;
                padding: 6px 12px;
                background: #95a5a6;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            `;

            // Botón Guardar - creado con createElement
            const btnGuardar = document.createElement('button');
            btnGuardar.id = 'btn-guardar-orden';
            btnGuardar.textContent = 'Save Order';
            btnGuardar.style.cssText = `
                padding: 6px 12px;
                background: #27ae60;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            `;

            // Agregar botones al footer
            footer.appendChild(btnCancelar);
            footer.appendChild(btnGuardar);
            modal.appendChild(footer);

            // Copia mutable del array
            let items = direcciones.slice().map((dir, index) => ({
                id_direccion: parseInt(dir.id_direccion),
                cliente_nombre: dir.cliente_nombre || '—',
                direccion: dir.direccion || '—',
                tiempo_servicio: parseInt(dir.tiempo_servicio) || 0,
                _originalIndex: index
            }));

            console.log('Modal abierto. Items iniciales:', items.map(i => i.id_direccion));

            // Función renderizar
            function renderLista() {
                lista.innerHTML = '';
                
                items.forEach((item, index) => {
                    const itemEl = document.createElement('div');
                    itemEl.style.cssText = `
                        display: flex;
                        align-items: center;
                        padding: 10px 0;
                        border-bottom: 1px solid #f0f0f0;
                    `;

                    const info = document.createElement('div');
                    info.style.flex = '1';
                    info.innerHTML = `
                        <strong>${item.cliente_nombre}</strong><br>
                        <small style="color: #555;">${item.direccion}</small><br>
                        <em style="color: #27ae60; font-size: 0.85em;">⏱️ ${item.tiempo_servicio} min</em>
                    `;

                    const btns = document.createElement('div');
                    btns.style.cssText = 'display: flex; gap: 6px; margin-left: 12px;';

                    // Botón SUBIR
                    const btnUp = document.createElement('button');
                    btnUp.textContent = '↑';
                    btnUp.style.cssText = `
                        width: 28px; height: 28px;
                        background: #bdc3c7;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        font-weight: bold;
                    `;
                    btnUp.disabled = (index === 0);
                    
                    btnUp.onclick = () => {
                        if (index > 0) {
                            console.log(`Moviendo ítem ${index} hacia arriba`);
                            const temp = items[index - 1];
                            items[index - 1] = items[index];
                            items[index] = temp;
                            console.log('Nuevo orden:', items.map(i => i.id_direccion));
                            renderLista();
                        }
                    };

                    // Botón BAJAR
                    const btnDown = document.createElement('button');
                    btnDown.textContent = '↓';
                    btnDown.style.cssText = `
                        width: 28px; height: 28px;
                        background: #bdc3c7;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        font-weight: bold;
                    `;
                    btnDown.disabled = (index === items.length - 1);
                    
                    btnDown.onclick = () => {
                        if (index < items.length - 1) {
                            console.log(`Moviendo ítem ${index} hacia abajo`);
                            const temp = items[index + 1];
                            items[index + 1] = items[index];
                            items[index] = temp;
                            console.log('Nuevo orden:', items.map(i => i.id_direccion));
                            renderLista();
                        }
                    };

                    btns.appendChild(btnUp);
                    btns.appendChild(btnDown);
                    itemEl.appendChild(info);
                    itemEl.appendChild(btns);
                    lista.appendChild(itemEl);
                });
            }

            renderLista();

            // ✅ EVENTOS DIRECTOS SOBRE LAS VARIABLES (no getElementById)
            btnCancelar.onclick = () => {
                console.log('Modal cancelado');
                document.body.removeChild(overlay);
                resolve(null);
            };

            btnGuardar.onclick = () => {
                const idsOrdenados = items.map(item => item.id_direccion);
                console.log('Guardando orden:', idsOrdenados);
                document.body.removeChild(overlay);
                resolve(idsOrdenados);
            };

            // Cerrar al hacer clic fuera
            overlay.onclick = (e) => {
                if (e.target === overlay) {
                    document.body.removeChild(overlay);
                    resolve(null);
                }
            };

            // Agregar al DOM
            modal.appendChild(footer);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);
        });
    }

    function crearFormularioFlotante(nombre_ruta = "", color_ruta = "white") {
        console.log('>>> INICIANDO crearFormularioFlotante');
        
        if (formularioZonaActiva) {
            console.log('Eliminando formulario previo');
            formularioZonaActiva.remove();
            formularioZonaActiva = null;
        }

        formularioZonaActiva = document.createElement('div');
        formularioZonaActiva.id = 'formulario-zona-flotante-activo';
        formularioZonaActiva.className = 'formulario-zona-flotante';
        formularioZonaActiva.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            width: 320px;
            max-height: 70vh;
            display: flex;
            flex-direction: column;
            background: ${color_ruta};
            border: 2px solid #3498db;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            z-index: 10000;
            font-family: Arial, sans-serif;
            cursor: default;
        `;

        let totalTiempo = 0;
        if (rutaActual?.zonas) {
            rutaActual.zonas.forEach(z => {
                if (z.direcciones) {
                    z.direcciones.forEach(d => {
                        totalTiempo += parseInt(d.tiempo_servicio) || 0;
                    });
                }
            });
        }
        tTiempo = minutosToHHMMSS(totalTiempo);

        const header = document.createElement('div');
        header.id = 'formulario-header-drag';
        header.style.cssText = `
            background: white;
            padding: 15px;
            border-bottom: 1px solid #eee;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: move;
            user-select: none;
        `;
        
        const titleSection = document.createElement('div');
        titleSection.style.flex = '1';
        titleSection.innerHTML = `
            <h4 style="margin:0; color:#2c3e50; font-size:1.1em; pointer-events: none;">
                Zone Addresses ${nombre_ruta}
            </h4>
            <span id="total-tiempo-ruta" style="font-weight:normal; font-size:0.9em; color:#e74c3c; pointer-events: none;">
                Total: ${tTiempo}
            </span>
        `;
        
        const btnContainer = document.createElement('div');
        btnContainer.style.cssText = `
            margin-left: 10px;
            flex-shrink: 0;
        `;
        
        const btnReordenar = document.createElement('button');
        btnReordenar.id = 'btn-reordenar';
        btnReordenar.type = 'button';
        btnReordenar.innerHTML = '↕️ Reorder';
        btnReordenar.style.cssText = `
            background: #3498db;
            color: white;
            border: 2px solid #2980b9;
            border-radius: 4px;
            padding: 8px 16px;
            font-size: 0.9em;
            font-weight: bold;
            cursor: pointer;
            pointer-events: auto !important;
            position: relative;
            z-index: 10001;
            display: block;
        `;
        
        btnReordenar.addEventListener('click', function handlerReorder(e) {
            console.log('╔════════════════════════════════════╗');
            console.log('║  🎯 CLICK REORDER DETECTADO        ║');
            console.log('╚════════════════════════════════════╝');
            
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            console.log('rutaActual:', rutaActual);
            
            if (!rutaActual?.zonas) {
                suiteAlertWarning('Hold up!', 'No route loaded to reorder.');
                return;
            }
            
            procesarReordenamiento();
        }, false);
        
        btnContainer.appendChild(btnReordenar);
        header.appendChild(titleSection);
        header.appendChild(btnContainer);

        const listaContenedor = document.createElement('div');
        listaContenedor.id = 'lista-direcciones-zona';
        listaContenedor.style.cssText = `
            padding: 10px 15px 15px 15px;
            max-height: calc(70vh - 80px);
            overflow-y: auto;
            background: ${color_ruta};
        `;

        formularioZonaActiva.appendChild(header);
        formularioZonaActiva.appendChild(listaContenedor);
        document.body.appendChild(formularioZonaActiva);

        let isDragging = false;
        let startX, startY, startRight, startTop;
        
        header.addEventListener('mousedown', function iniciarDrag(e) {
            if (e.target === btnReordenar || 
                e.target.closest('#btn-reordenar') ||
                e.target.closest('button')) {
                console.log('Click en botón detectado - NO iniciar drag');
                return;
            }
            
            console.log('Iniciando drag desde header');
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            
            const rect = formularioZonaActiva.getBoundingClientRect();
            startRight = window.innerWidth - rect.right;
            startTop = rect.top;
            
            header.style.cursor = 'grabbing';
            e.preventDefault();
        });
        
        const moverDrag = function(e) {
            if (!isDragging) return;
            
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            
            formularioZonaActiva.style.right = `${startRight - dx}px`;
            formularioZonaActiva.style.top = `${startTop + dy}px`;
        };
        
        const finalizarDrag = function() {
            if (isDragging) {
                isDragging = false;
                header.style.cursor = 'move';
                console.log('Drag finalizado');
            }
        };
        
        document.addEventListener('mousemove', moverDrag);
        document.addEventListener('mouseup', finalizarDrag);
        
        formularioZonaActiva.addEventListener('remove', function() {
            document.removeEventListener('mousemove', moverDrag);
            document.removeEventListener('mouseup', finalizarDrag);
        });

        console.log('>>> Formulario creado exitosamente');
    }

    async function procesarReordenamiento() {
        console.log('>>> procesarReordenamiento iniciado');
        
        try {
            // Recopilar direcciones de todas las zonas
            const todasDirecciones = [];
            
            rutaActual.zonas.forEach((zona, idx) => {
                if (zona.direcciones && Array.isArray(zona.direcciones)) {
                    zona.direcciones.forEach(dir => {
                        todasDirecciones.push({
                            id_direccion: dir.id_direccion,
                            cliente_nombre: dir.cliente_nombre || '—',
                            direccion: dir.direccion || '—',
                            tiempo_servicio: dir.tiempo_servicio || 0
                        });
                    });
                }
            });
            
            console.log('Total direcciones para reordenar:', todasDirecciones.length);
            
            if (todasDirecciones.length === 0) {
                suiteAlertWarning('Empty', 'No addresses to reorder.');
                return;
            }
            
            // ✅ ABRIR MODAL Y ESPERAR RESULTADO
            const nuevoOrden = await abrirModalOrdenarDirecciones(todasDirecciones);
            
            // ✅ VERIFICAR RESULTADO
            console.log('Resultado recibido del modal:', nuevoOrden);
            
            if (!nuevoOrden || !Array.isArray(nuevoOrden) || nuevoOrden.length === 0) {
                console.log('No hay orden para guardar (usuario canceló)');
                return;
            }
            
            // Comparar con orden original para verificar cambios
            const ordenOriginal = todasDirecciones.map(d => d.id_direccion);
            const huboCambios = JSON.stringify(ordenOriginal) !== JSON.stringify(nuevoOrden);
            
            console.log('Orden original:', ordenOriginal);
            console.log('Nuevo orden:', nuevoOrden);
            console.log('¿Hubo cambios?', huboCambios);
            
            if (!huboCambios) {
                suiteAlertInfo('No Changes', 'The order was not modified.');
                return;
            }
            
            // Enviar al backend
            suiteLoading('show');
            
            const response = await fetch(ruta_rutas_mapa_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_rutas: 'actualizar_orden_direcciones',
                    id_ruta: rutaActual.id_ruta,
                    orden_direcciones: nuevoOrden
                })
            });
            
            const data = await response.json();
            suiteLoading('hide');
            
            if (data.success) {
                suiteAlertSuccess('Saved!', 'Route order updated.');
                await cargarZonasRuta(rutaActual.id_ruta);
            } else {
                throw new Error(data.error || 'Failed to save');
            }
            
        } catch (error) {
            console.error('Error:', error);
            suiteLoading('hide');
            suiteAlertError('Error', error.message);
        }
    }

    // ============================================
    // INICIALIZACIÓN DEL SISTEMA
    // ============================================

    document.addEventListener('DOMContentLoaded', function () {
        function onConfigReady() {
            window.removeEventListener('configListo', onConfigReady);

            let tries = 0;
            const maxTries = 5;

            const tryInit = function () {
                tries++;
                const contenedorEl = document.getElementById('mapa_geo');

                if (!contenedorEl) {
                    if (tries < maxTries) {
                        setTimeout(tryInit, 200);
                        return;
                    } else {
                        console.error('⛔ Contenedor #mapa_geo no encontrado');
                        return;
                    }
                }

                try {
                    inicializarMapa();
                } catch (err) {
                    console.error('❌ Error al llamar inicializarMapa():', err);
                    return;
                }

                map = window.map;
                if (!map) {
                    console.error('❌ Mapa no inicializado');
                    return;
                }

                console.log('✅ Mapa creado. Referencia global `map` OK.');

                const starSvgUniform = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="-16 -16 32 32">
                        <polygon points="0,-14 12,7 -12,7" fill="#0066FF" stroke="#0066FF" stroke-width="1"/>
                        <polygon points="0,14 12,-7 -12,-7" fill="#0066FF" stroke="#0066FF" stroke-width="1"/>
                    </svg>`;

                L.marker(HQ_COORDS, {
                    icon: L.divIcon({
                        html: starSvgUniform,
                        className: 'sede-marker',
                        iconSize: [32, 32],
                        iconAnchor: [16, 16],
                        popupAnchor: [0, -16]
                    })
                }).addTo(map).bindPopup(`
                    <b>Sergio's Landscape</b><br>
                    <span style="font-size: 0.9em; color: #555;">Headquarters • Starting Point</span>
                `);

                zonasLayerGroup = L.layerGroup().addTo(map);
                rutasLayerGroup = L.layerGroup().addTo(map);
                direccionesLayerGroup = L.layerGroup();
                direccionesRutaLayerGroup = L.layerGroup();
                direccionesLibresLayerGroup = L.layerGroup();
                direccionesAsignadasLayerGroup = L.layerGroup();

                window.direccionesMarkers = {};

                cargarZonas();
                cargarRutas();

                // ============================================
                // EVENTOS DE BOTONES PRINCIPALES
                // ============================================

                document.getElementById('btn-crear-ruta').addEventListener('click', function () {
                    if (modoEdicionRuta === null && zonasSeleccionadas.size === 0) {
                        iniciarCreacionRuta();
                    } else {
                        crearRuta();
                    }
                });

                // ============================================
                // NUEVO: BOTÓN UPDATE CON TOGGLE VER/EDITAR
                // ============================================
                document.getElementById('btn-actualizar-ruta').addEventListener('click', function () {
                    console.log('Click en Update. Estado actual:', modoVisualizacion);
                    
                    if (modoVisualizacion === MODO_VISUALIZACION.VER) {
                        // Cambiar a MODO EDITAR
                        modoVisualizacion = MODO_VISUALIZACION.EDITAR;
                        modoEdicionRuta = 'editar';
                        
                        this.textContent = '💾 Save Changes';
                        this.style.backgroundColor = '#27ae60';
                        
                        document.getElementById('input-nombre-ruta').disabled = false;
                        document.getElementById('input-color-ruta').disabled = false;
                        document.getElementById('btn-cancelar-accion').style.display = 'block';
                        
                        habilitarMapa();
                        
                        zonasSeleccionadas.clear();
                        rutaActual?.zonas?.forEach(z => zonasSeleccionadas.add(z.id_zona));
                        actualizarListaZonasSeleccionadas();
                        
                    } else {
                        // Estamos en EDITAR, cambios
                        actualizarRuta().then(() => {
                            modoVisualizacion = MODO_VISUALIZACION.VER;
                            modoEdicionRuta = null;
                            
                            this.textContent = 'Update This Route';
                            this.style.backgroundColor = '#f39c12';
                            
                            document.getElementById('input-nombre-ruta').disabled = true;
                            document.getElementById('input-color-ruta').disabled = true;
                            document.getElementById('btn-cancelar-accion').style.display = 'none';
                            
                            deshabilitarMapa();
                            cargarZonasRuta(rutaActual.id_ruta);
                        });
                    }
                });

                document.getElementById('btn-eliminar-ruta').addEventListener('click', iniciarEliminacionRuta);
                
                document.getElementById('btn-volver').addEventListener('click', function () {
                    window.location.href = '<?= RUTA_REAL ?>/dashboard';
                });

                document.getElementById('btn-cancelar-accion').addEventListener('click', function () {
                    modoEdicionRuta = null;
                    modoVisualizacion = MODO_VISUALIZACION.VER;
                    rutaActual = null;
                    ocultarFormularioZona();

                    if (map.hasLayer(direccionesRutaLayerGroup)) {
                        map.removeLayer(direccionesRutaLayerGroup);
                        direccionesRutaLayerGroup.clearLayers();
                    }
                    if (map.hasLayer(direccionesLibresLayerGroup)) {
                        map.removeLayer(direccionesLibresLayerGroup);
                        direccionesLibresLayerGroup.clearLayers();
                    }

                    actualizarEstadoInterfaz();
                    direccionesLayerGroup.clearLayers();
                    window.direccionesMarkers = {};

                    zonasLayerGroup.eachLayer(layer => {
                        layer.setStyle({ color: "#3388ff", weight: 1, fillOpacity: 0.1 });
                    });
                    zonasSeleccionadas.clear();
                    actualizarListaZonasSeleccionadas();

                    if (btnToggleDirecciones) {
                        btnToggleDirecciones.style.backgroundColor = '#fff';
                    }

                    deshabilitarMapa();
                    suiteAlertInfo('Action Canceled', 'Back to neutral state.');
                });

                // Botón Toggle Direcciones
                const btnToggleDirecciones = L.DomUtil.create('button', 'leaflet-bar leaflet-control leaflet-control-custom');
                btnToggleDirecciones.title = 'Toggle Addresses';
                btnToggleDirecciones.innerHTML = 'Addresses';
                btnToggleDirecciones.style.backgroundColor = '#fff';
                btnToggleDirecciones.style.border = '1px solid #ccc';
                btnToggleDirecciones.style.borderRadius = '4px';
                btnToggleDirecciones.style.padding = '5px 10px';
                btnToggleDirecciones.style.cursor = 'pointer';

                btnToggleDirecciones.addEventListener('click', function () {
                    if (map.hasLayer(direccionesLibresLayerGroup)) {
                        map.removeLayer(direccionesLibresLayerGroup);
                        btnToggleDirecciones.style.backgroundColor = '#fff';
                    } else {
                        modoEdicionRuta = null;
                        rutaActual = null;
                        zonasSeleccionadas.clear();
                        actualizarListaZonasSeleccionadas();

                        zonasLayerGroup.eachLayer(layer => {
                            layer.setStyle({ color: "#3388ff", weight: 1, fillOpacity: 0.1 });
                        });

                        document.getElementById('btn-crear-ruta').style.display = 'block';
                        document.getElementById('btn-actualizar-ruta').style.display = 'none';
                        document.getElementById('btn-eliminar-ruta').style.display = 'none';
                        document.getElementById('btn-cancelar-accion').style.display = 'none';

                        suiteLoading('show');
                        cargarDirecciones().then(() => {
                            if (!map.hasLayer(direccionesLibresLayerGroup)) {
                                map.addLayer(direccionesLibresLayerGroup);
                            }
                            btnToggleDirecciones.style.backgroundColor = '#a3d2a3';
                            if (!direccionesCargadas) {
                                const btnCrear = document.getElementById('btn-crear-ruta');
                                btnCrear.disabled = false;
                                btnCrear.style.opacity = '1';
                                btnCrear.title = '';
                                direccionesCargadas = true;
                            }
                        }).catch(err => {
                            suiteLoading('hide');
                            suiteAlertError("Uh oh!", "Failed to load addresses.");
                        }).finally(() => {
                            suiteLoading('hide');
                        });
                    }
                });

                const customControlContainer = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                L.DomEvent.disableClickPropagation(customControlContainer);
                customControlContainer.appendChild(btnToggleDirecciones);

                const CustomControl = L.Control.extend({
                    options: { position: 'topleft' },
                    onAdd: function () {
                        return customControlContainer;
                    }
                });

                map.addControl(new CustomControl());
            };

            tryInit();
        }

        document.getElementById('btn-crear-ruta').disabled = true;
        document.getElementById('btn-crear-ruta').style.opacity = '0.6';
        document.getElementById('btn-crear-ruta').title = 'Enable addresses first';

        window.addEventListener('configListo', onConfigReady);
        cargarConfigYIniciar();
    });

    // ============================================
    // FUNCIONES DE ACTUALIZACIÓN Y CÁLCULO
    // ============================================

    function actualizarTotalTiempo() {
        let total = 0;
        if (rutaActual?.zonas) {
            rutaActual.zonas.forEach(z => {
                z.direcciones?.forEach(d => {
                    total += parseInt(d.tiempo_servicio) || 0;
                });
            });
        }
        tTiempo = minutosToHHMMSS(total);
        console.log('Tiempo calculado: ', tTiempo);

        const span = document.getElementById('total-tiempo-ruta');
        if (span) span.textContent = `Total: ${tTiempo}`;
    }

    // ============================================
    // FUNCIONES DEL MAPA Y LÓGICA DE RUTAS
    // ============================================

    async function cargarZonas() {
        try {
            suiteLoading('show');
            const response = await fetch(ruta_rutas_mapa_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_rutas: 'listar_todas_zonas' })
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const data = await response.json();
            if (data.success) {
                zonasLayerGroup.clearLayers();

                data.data.forEach(zona => {
                    const bounds = [
                        [zona.lat_sw, zona.lng_sw],
                        [zona.lat_ne, zona.lng_ne]
                    ];
                    const rect = L.rectangle(bounds, {
                        color: "#ffffffff",
                        weight: 1,
                        fillOpacity: 0.1
                    }).addTo(zonasLayerGroup);

                    rect.zonaId = zona.id_zona;
                    rect.zonaNombre = zona.nombre_zona;

                    if (modoEdicionRuta === null) {
                        rect.bindPopup(`<b>Zona:</b> ${zona.nombre_zona}<br>ID: ${zona.id_zona}`);
                    }

                    rect.on('click', function (e) {
                        if (modoEdicionRuta === 'crear' || modoEdicionRuta === 'editar') {
                            seleccionarZonaConModal(rect);
                        } else {
                            zonaActivaId = rect.zonaId;
                            cargarDireccionesDeZona(rect.zonaId, rect.zonaNombre);
                        }
                    });
                });
            } else {
                throw new Error(data.error || 'Something ain\'t right with the zones.');
            }
        } catch (err) {
            console.error("Error loading zones:", err);
            suiteAlertError('Uh oh!', 'Could not load zones: ' + err.message);
        } finally {
            suiteLoading('hide');
        }
    }

    async function cargarDirecciones() {
        try {
            suiteLoading('show');
            direccionesLibresLayerGroup.clearLayers();

            const response = await fetch(ruta_direcciones_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_direcciones: 'listar_direcciones_con_coordenadas' })
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const data = await response.json();
            if (data.success) {
                data.data.forEach(dir => {
                    if (dir.lat && dir.lng) {
                        const circle = L.circle([dir.lat, dir.lng], {
                            radius: 75,
                            color: "#ccff9fff",
                            fillColor: "#de1111ff",
                            fillOpacity: 0.7,
                            weight: 1
                        }).addTo(direccionesLibresLayerGroup);

                        if (!map.hasLayer(direccionesLibresLayerGroup)) {
                            map.addLayer(direccionesLibresLayerGroup);
                        }

                        circle.direccionId = dir.id_direccion;
                        circle.direccionNombre = dir.cliente_nombre || 'Anonymous Customer';
                        circle.direccionTexto = dir.direccion;
                        circle.bindPopup(`<b>Client:</b> ${circle.direccionNombre}<br><b>Address:</b> ${circle.direccionTexto}<br>ID: ${circle.direccionId}`);
                    }
                });
                console.log("Direcciones cargadas y dibujadas.");
            } else {
                throw new Error(data.error || 'Couldn\'t pull the addresses, partner.');
            }
        } catch (err) {
            console.error("Error loading addresses:", err);
            suiteAlertError('Uh oh!', 'Could not load addresses: ' + err.message);
            throw err;
        } finally {
            suiteLoading('hide');
        }
        return true;
    }

    async function cargarRutas() {
        try {
            suiteLoading('show');
            const response = await fetch(ruta_rutas_mapa_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_rutas: 'listar_rutas' })
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const data = await response.json();
            if (data.success) {
                rutasLayerGroup.clearLayers();

                const listaRutas = document.getElementById('rutas-lista');
                listaRutas.innerHTML = '';

                data.data.forEach(ruta => {
                    const rutaDiv = document.createElement('div');
                    rutaDiv.className = 'ruta-item';
                    rutaDiv.innerHTML = `<span style="color: ${ruta.color_ruta}">■</span> ${ruta.nombre_ruta} (${ruta.total_direcciones} assoc. addrs.)`;
                    rutaDiv.onclick = () => cargarZonasRuta(ruta.id_ruta);
                    listaRutas.appendChild(rutaDiv);
                });
            } else {
                throw new Error(data.error || 'No routes found out here.');
            }
        } catch (err) {
            console.error("Error loading routes:", err);
            suiteAlertError('Dang it!', 'Could not load routes: ' + err.message);
        } finally {
            suiteLoading('hide');
        }
    }

    async function cargarZonasRuta(id_ruta) {
        window.direccionesMarkers = {};
        
        try {
            suiteLoading('show');
            console.log("🔍 [cargarZonasRuta] Iniciando carga de ruta ID:", id_ruta);

            const response = await fetch(ruta_rutas_mapa_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    modulo_rutas: 'obtener_ruta', 
                    id_ruta: id_ruta 
                })
            });
            
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Route not found.');
            }

            const ruta = data.data;
            console.log("✅ [cargarZonasRuta] Ruta recibida:", ruta);
            console.log("Zonas en ruta:", ruta.zonas?.length || 0);
            console.log(">>> Esta es la data con su orden respectivo", ruta)

            if (ruta.zonas && Array.isArray(ruta.zonas)) {
                ruta.zonas.forEach(zona => {
                    zona.direcciones = [];
                    console.log(`Zona ${zona.nombre_zona} inicializada (sin direcciones aún)`);
                });
            }

            direccionesLayerGroup.clearLayers();

            const colorRuta = ruta.color_ruta || "#3498db";
            zonasLayerGroup.eachLayer(layer => {
                const enRuta = ruta.zonas?.some(z => z.id_zona === layer.zonaId);
                layer.setStyle({
                    color: enRuta ? colorRuta : "#97ff17ff",
                    weight: enRuta ? 2 : 1,
                    fillOpacity: enRuta ? 0.4 : 0.1
                });
                if (enRuta) layer.bringToFront();
            });

            document.getElementById('input-nombre-ruta').value = ruta.nombre_ruta;
            document.getElementById('input-color-ruta').value = ruta.color_ruta;
            document.getElementById('btn-crear-ruta').style.display = 'none';
            document.getElementById('btn-actualizar-ruta').style.display = 'block';
            document.getElementById('btn-eliminar-ruta').style.display = 'block';

            zonasSeleccionadas.clear();
            ruta.zonas?.forEach(z => zonasSeleccionadas.add(z.id_zona));
            actualizarListaZonasSeleccionadas();

            rutaActual = { ...ruta, zonas: ruta.zonas };

            const nuevoColor = hexToRgba(colorRuta);
            crearFormularioFlotante(ruta.nombre_ruta, nuevoColor);
            await cargarDireccionesDeZonaenRutas(id_ruta);

            zonasLayerGroup.eachLayer(layer => {
                if (layer.getPopup()) layer.unbindPopup();
            });

            // ============================================
            // INICIAR SIEMPRE EN MODO VER (DESHABILITADO)
            // ============================================
            modoVisualizacion = MODO_VISUALIZACION.VER;
            modoEdicionRuta = null;
            deshabilitarMapa();

            const btnActualizar = document.getElementById('btn-actualizar-ruta');
            btnActualizar.textContent = 'Update This Route';
            btnActualizar.style.backgroundColor = '#f39c12';
            btnActualizar.style.display = 'block';

            document.getElementById('btn-cancelar-accion').style.display = 'none';
            document.getElementById('input-nombre-ruta').disabled = true;
            document.getElementById('input-color-ruta').disabled = true;

            actualizarEstadoInterfaz();
            actualizarTotalTiempo();

            console.log('✅ Ruta cargada en modo VER (solo lectura)');

        } catch (err) {
            console.error("Error loading route:", err);
            suiteAlertError('Uh oh!', 'Could not load route: ' + err.message);
        } finally {
            suiteLoading('hide');
        }
    }

    function iniciarCreacionRuta() {
        modoEdicionRuta = 'crear';
        modoVisualizacion = MODO_VISUALIZACION.EDITAR;
        ocultarFormularioZona();
        document.getElementById('input-nombre-ruta').value = '';
        document.getElementById('input-color-ruta').value = '#3498db';
        actualizarEstadoInterfaz();
        habilitarMapa();
        suiteAlertInfo('New Route', 'Click on zones to build your route. Don\'t forget to connect back to HQ!');
    }

    function iniciarActualizacionRuta() {
        if (!rutaActual) {
            suiteAlertWarning('Hold up!', 'You ain\'t selected no route to update.');
            return;
        }

        zonasLayerGroup.eachLayer(layer => {
            if (layer.getPopup()) {
                layer.unbindPopup();
            }
        });

        modoEdicionRuta = 'editar';
        suiteAlertInfo('Edit Mode', 'Click zones to add or remove \'em from this route.');

        actualizarEstadoInterfaz();

        zonasSeleccionadas.clear();
        rutaActual.zonas.forEach(z => zonasSeleccionadas.add(z.id_zona));
        actualizarListaZonasSeleccionadas();
        zonasLayerGroup.eachLayer(layer => {
            const enRuta = rutaActual.zonas.some(z => z.id_zona === layer.zonaId);
            layer.setStyle({
                color: enRuta ? "#e74c3c" : "#3388ff",
                weight: enRuta ? 2 : 1,
                fillOpacity: enRuta ? 0.3 : 0.1
            });
        });
    }

    function iniciarEliminacionRuta() {
        if (!rutaActual) {
            suiteAlertWarning('Whoa there!', 'You gotta pick a route first.');
            return;
        }
        modoEdicionRuta = 'eliminar';
        suiteConfirm('Delete Route?', `You sure you wanna delete "${rutaActual.nombre_ruta}"? You can't undo this.`)
            .then(result => {
                if (result) {
                    eliminarRuta(rutaActual.id_ruta);
                } else {
                    modoEdicionRuta = 'editar';
                }
            });
    }

    function actualizarListaZonasSeleccionadas() {
        const lista = document.getElementById('zonas-seleccionadas-lista');
        lista.innerHTML = '';
        document.getElementById('contador-zonas').textContent = zonasSeleccionadas.size;

        zonasSeleccionadas.forEach(id => {
            let nombreZona = 'Zone ' + id;
            zonasLayerGroup.eachLayer(function (layer) {
                if (layer.zonaId === id) {
                    nombreZona = layer.zonaNombre;
                }
            });

            const item = document.createElement('div');
            item.className = 'zona-item';
            item.textContent = nombreZona;
            item.onclick = () => {
                zonasSeleccionadas.delete(id);
                actualizarListaZonasSeleccionadas();
                zonasLayerGroup.eachLayer(function (layer) {
                    if (layer.zonaId === id) {
                        layer.setStyle({ color: "#3388ff", weight: 1, fillOpacity: 0.1 });
                    }
                });
            };
            lista.appendChild(item);
        });
    }

    async function crearRuta() {
        const nombre = document.getElementById('input-nombre-ruta').value.trim();
        const color = document.getElementById('input-color-ruta').value;
        const zonas_ids = Array.from(zonasSeleccionadas);

        if (!nombre) {
            suiteAlertWarning('Hold up!', 'Gimme a name for this route, partner.');
            return;
        }
        if (zonas_ids.length === 0) {
            suiteAlertWarning('Wait a minute!', 'You gotta pick at least one zone before you save.');
            return;
        }

        try {
            suiteLoading('show');
            const response = await fetch(ruta_rutas_mapa_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_rutas: 'crear_ruta',
                    nombre_ruta: nombre,
                    color_ruta: color,
                    direcciones_ids: zonas_ids
                })
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const data = await response.json();
            if (data.success) {
                suiteAlertSuccess('Sweet!', data.message);
                iniciarCreacionRuta();
                cargarRutas();
                cargarZonas();
            } else {
                throw new Error(data.error || 'Something went sideways with the save.');
            }
        } catch (err) {
            console.error("Error creating route:", err);
            suiteAlertError('Dang it!', 'Could not create route: ' + err.message);
        } finally {
            suiteLoading('hide');
        }
    }

    async function actualizarRuta() {
        if (!rutaActual) {
            suiteAlertWarning('Hold up!', 'You ain\'t selected no route to update.');
            return;
        }

        const id_ruta = rutaActual.id_ruta;
        const nombre = document.getElementById('input-nombre-ruta').value.trim();
        const color = document.getElementById('input-color-ruta').value;
        const direccionesArray = Array.from(zonasSeleccionadas);

        if (!nombre) {
            suiteAlertWarning('Hold up!', 'Gimme a name for this route, partner.');
            return;
        }
        if (direccionesArray.length === 0) {
            suiteAlertWarning('Wait a minute!', 'You gotta pick at least one zone before you save.');
            return;
        }

        try {
            suiteLoading('show');

            const response = await fetch(ruta_rutas_mapa_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_rutas: 'actualizar_ruta',
                    id_ruta: id_ruta,
                    nombre_ruta: nombre,
                    color_ruta: color,
                    direcciones_ids: direccionesArray
                })
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const data = await response.json();
            if (data.success) {
                suiteAlertSuccess('Sweet!', data.message);
                await cargarZonasRuta(id_ruta);
                await cargarRutas();
                await cargarZonas();
            } else {
                throw new Error(data.error || 'Something went sideways with the save.');
            }
        } catch (err) {
            console.error("Error updating route:", err);
            suiteAlertError('Dang it!', 'Could not update route: ' + err.message);
        } finally {
            suiteLoading('hide');
        }
    }

    async function eliminarRuta(id_ruta) {
        try {
            suiteLoading('show');
            const response = await fetch(ruta_rutas_mapa_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_rutas: 'eliminar_ruta',
                    id_ruta: id_ruta
                })
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const data = await response.json();
            if (data.success) {
                suiteAlertSuccess('Done!', data.message);
                iniciarCreacionRuta();
                cargarRutas();
                cargarZonas();
            } else {
                throw new Error(data.error || 'Could not delete that route.');
            }
        } catch (err) {
            console.error("Error deleting route:", err);
            suiteAlertError('Dang it!', 'Could not delete route: ' + err.message);
        } finally {
            suiteLoading('hide');
        }
    }

    function abrirModalSeleccionDirecciones(direcciones, nombreZona, zonaId) {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.style = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 19999;
            `;

            const modal = document.createElement('div');
            modal.style = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                padding: 20px;
                border-radius: 8px;
                max-width: 500px;
                max-height: 70vh;
                overflow-y: auto;
                z-index: 20000;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                font-family: Arial, sans-serif;
            `;

            const titleBar = document.createElement('div');
            titleBar.style = `display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;`;

            const title = document.createElement('h3');
            title.innerHTML = `Select addresses for:<br><em style="color:#2980b9;">${nombreZona}</em>`;
            title.style.margin = '0';

            const btnDeselectAll = document.createElement('button');
            btnDeselectAll.textContent = 'Deselect All';
            btnDeselectAll.style = `
                background: #e74c3c;
                color: white;
                border: none;
                border-radius: 4px;
                padding: 4px 8px;
                font-size: 0.8em;
                cursor: pointer;
            `;
            btnDeselectAll.onclick = () => {
                const checkboxes = modal.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(cb => cb.checked = false);
            };

            titleBar.appendChild(title);
            titleBar.appendChild(btnDeselectAll);

            const lista = document.createElement('div');
            lista.id = 'lista-direcciones-modal';
            lista.style = "margin: 10px 0; max-height: 300px; overflow-y: auto;";

            direcciones.forEach(dir => {
                const item = document.createElement('div');
                item.style = "padding: 10px; border-bottom: 1px solid #eee; display: flex; align-items: start;";
                item.innerHTML = `
                    <input type="checkbox" id="dir-${dir.id_direccion}" value="${dir.id_direccion}" checked style="margin-top: 4px;">
                    <label for="dir-${dir.id_direccion}" style="margin-left: 10px; font-size: 0.95em; flex: 1;">
                        <strong>${dir.cliente_nombre || '—'}</strong><br>
                        <span style="color: #555; font-size: 0.9em;">${dir.direccion}</span>
                    </label>
                `;
                lista.appendChild(item);
            });

            const actions = document.createElement('div');
            actions.style = "text-align: right; margin-top: 15px;";
            actions.innerHTML = `
                <button id="btn-cancelar-direcciones" style="margin-right: 10px; padding: 6px 12px; background: #95a5a6; color: white; border: none; border-radius: 4px;">Cancel</button>
                <button id="btn-confirmar-direcciones" style="padding: 6px 12px; background: #27ae60; color: white; border: none; border-radius: 4px;">Confirm</button>
            `;

            modal.appendChild(titleBar);
            modal.appendChild(lista);
            modal.appendChild(actions);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            document.getElementById('btn-cancelar-direcciones').onclick = () => {
                document.body.removeChild(overlay);
                resolve(null);
            };

            document.getElementById('btn-confirmar-direcciones').onclick = () => {
                const checkboxes = modal.querySelectorAll('input[type="checkbox"]:checked');
                const ids = Array.from(checkboxes).map(cb => parseInt(cb.value));
                document.body.removeChild(overlay);
                resolve(ids);
            };

            overlay.onclick = (e) => {
                if (e.target === overlay) {
                    document.body.removeChild(overlay);
                    resolve(null);
                }
            };
        });
    }

    async function seleccionarZonaConModal(zone) {
        // PROTECCIÓN: Solo en modo edición
        if (modoVisualizacion !== MODO_VISUALIZACION.EDITAR) {
            console.log('Ignorando click en zona - modo VER activo');
            return;
        }

        const bounds = zone.getBounds();
        const sw = bounds.getSouthWest();
        const ne = bounds.getNorthEast();

        if (zonasSeleccionadas.has(zone.zonaId)) {
            zonasSeleccionadas.delete(zone.zonaId);
            zone.setStyle({ color: "#3388ff", weight: 1, fillOpacity: 0.1 });

            const response = await fetch(ruta_direcciones_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_direcciones: 'listar_direcciones_en_area',
                    lat_sw: sw.lat,
                    lng_sw: sw.lng,
                    lat_ne: ne.lat,
                    lng_ne: ne.lng
                })
            });
            const data = await response.json();
            if (data.success) {
                data.data.forEach(dir => {
                    direccionesSeleccionadas.delete(dir.id_direccion);
                });
            }

            actualizarListaZonasSeleccionadas();
            return;
        }

        try {
            suiteLoading('show');

            const response = await fetch("<?php echo RUTA_APP; ?>/app/ajax/direccionesAjax.php", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_direcciones: 'listar_direcciones_en_area',
                    lat_sw: sw.lat,
                    lng_sw: sw.lng,
                    lat_ne: ne.lat,
                    lng_ne: ne.lng
                })
            });

            const data = await response.json();
            suiteLoading('hide');

            if (!data.success) throw new Error(data.error || 'Failed to load addresses in zone');

            const direcciones = data.data || [];

            if (direcciones.length === 0) {
                zonasSeleccionadas.add(zone.zonaId);
                zone.setStyle({ color: "#e74c3c", weight: 2, fillOpacity: 0.3 });
                actualizarListaZonasSeleccionadas();
                return;
            }

            const idsSeleccionados = await abrirModalSeleccionDirecciones(direcciones, zone.zonaNombre, zone.zonaId);

            if (idsSeleccionados === null) {
                return;
            }

            if (idsSeleccionados.length === 0) {
                suiteAlertInfo('Noted!', `Zone "${zone.zonaNombre}" added without addresses.`);
                zonasSeleccionadas.add(zone.zonaId);
                zone.setStyle({ color: "#e74c3c", weight: 2, fillOpacity: 0.3 });
                actualizarListaZonasSeleccionadas();
                return;
            }

            suiteLoading('show');
            const saveResponse = await fetch("<?php echo RUTA_APP; ?>/app/ajax/zonasAjax.php", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_zonas: 'crear_zona',
                    lat_sw: sw.lat,
                    lng_sw: sw.lng,
                    lat_ne: ne.lat,
                    lng_ne: ne.lng,
                    ids_direcciones: idsSeleccionados,
                    nombre_zona: zone.zonaNombre,
                    id_ruta: modoEdicionRuta === 'editar' ? rutaActual.id_ruta : null
                })
            });

            const saveData = await saveResponse.json();
            suiteLoading('hide');

            if (!saveData.success) throw new Error(saveData.error || 'Failed to link addresses');

            zonasSeleccionadas.add(zone.zonaId);
            zone.setStyle({ color: "#e74c3c", weight: 2, fillOpacity: 0.3 });
            actualizarListaZonasSeleccionadas();

            zonaActivaId = zone.zonaId;
            cargarDireccionesDeZona(zone.zonaId, zone.zonaNombre);

            suiteAlertSuccess('Yeehaw!', `Zone "${zone.zonaNombre}" linked with ${idsSeleccionados.length} address(es).`);

        } catch (err) {
            suiteLoading('hide');
            console.error('Error en selección de zona:', err);
            suiteAlertError('Dang it!', 'Could not process zone: ' + err.message);
        }
    }
</script>

