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
    // Variables de Ruta (ya definidas en PHP)
    const ruta_rutas_mapa_ajax = "<?php echo $ruta_rutas_mapa_ajax; ?>";
    const ruta_direcciones_ajax = "<?php echo $ruta_direcciones_ajax; ?>";

    // Inicializar variables globales para el mapa y elementos Leaflet
    let map = null;
    let zonasLayerGroup = null;
    let rutasLayerGroup = null;

    let direccionesLayerGroup = null;
    let direccionesRutaLayerGroup = false;

    //let cuadriculaLayerGroup = null; // Agrupar la cuadrícula de 1km
    let zonasSeleccionadas = new Set();
    let modoEdicionRuta = null;
    let rutaActual = null;

    let direccionesCargadas = false;
    let _actualizandoEstado = false;

    let btnToggleDirecciones = null;

    // Variables para controlar el formulario de direcciones
    let formularioZonaActiva = null;
    let zonaActivaId = null;

    // Indicador de si la cuadrícula está activa
    //let cuadriculaActiva = false;

    // Coordenadas del Headquarter (HQ) - Conroe, TX
    const HQ_LAT = 30.3204272;
    const HQ_LNG = -95.4217815;
    const HQ_COORDS = [HQ_LAT, HQ_LNG];

    // --- Funciones auxiliares ---

    // Mostrar alertas con estilo de Texas
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

    // Modal de confirmación
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
            // Eliminar alerta existente
            const alertaExistente = document.querySelector('.alerta-overlay');
            if (alertaExistente) {
                alertaExistente.remove();
            }

            // Crear elementos de la alerta
            const overlay = document.createElement('div');
            overlay.className = 'alerta-overlay';

            const alerta = document.createElement('div');
            alerta.className = 'suite-alerta-box';

            const header = document.createElement('div');
            header.className = `alerta-header ${tipo}`;

            const icon = document.createElement('div');
            icon.className = 'alerta-icon';

            // Iconos según el tipo
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

            // Botones según opciones
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

            // Construir la alerta
            header.appendChild(icon);
            header.appendChild(title);
            body.appendChild(message);
            body.appendChild(actions);
            alerta.appendChild(header);
            alerta.appendChild(body);
            overlay.appendChild(alerta);

            // Añadir al documento
            document.body.appendChild(overlay);

            // Mostrar con animación
            setTimeout(() => {
                overlay.classList.add('activo');
                alerta.classList.add('alerta-pulse');
            }, 10);

            // Cerrar con Escape
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

            // Cerrar haciendo clic fuera de la alerta
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

            // SVG del INFINITO HORIZONTAL correcto
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
        // Eliminar el símbolo # si existe
        hex = hex.replace('#', '');
        // Asegurar que tenga 6 caracteres
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
            // Asignar valor por defecto
            const DEFAULT_CONFIG = {
                mapa_base: 'ESRI',
                umbral_metros: 150,
                umbral_minutos: 5
            };

            if (data.error) {
                console.warn('⚠️ Error en datosgeneralesAjax:', data.error);
            } else {
                // === Variable global para almacenar configuración crítica ===
                window.APP_CONFIG = window.APP_CONFIG || {};
                // Guardar en configuración global
                // Dentro del fetch, tras obtener y hacer .json() a la respuesta

                if (data.success && data.config) {
                    window.APP_CONFIG = {
                        mapa_base: data.config.mapa_base || DEFAULT_CONFIG.mapa_base,
                        umbral_metros: parseInt(data.config.umbral_metros, 10) || DEFAULT_CONFIG.umbral_metros,
                        umbral_minutos: parseInt(data.config.umbral_minutos, 10) || DEFAULT_CONFIG.umbral_minutos
                    };

                    // Validar NaN
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
                // ✅ Solo ahora dispara el evento
                window.dispatchEvent(new Event('configListo'));

            }

            console.log('✅ mapa_base cargado:', window.APP_CONFIG.mapa_base);
        } catch (err) {
            console.error('❌ Error al cargar datos generales:', err.message);
            // Fallback
            window.APP_CONFIG.mapa_base = 'ESRI';
            window.APP_CONFIG.umbral_metros = 150;
            window.APP_CONFIG.umbral_minutos = 5;
            window.dispatchEvent(new Event('configListo'));
        }
    }

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
            console.log("Direcciones de la Zona: ", direcciones);
            cargarDirecciones();

            // Crear HTML de la lista
            const lista = document.getElementById('lista-direcciones-zona');
            if (!lista) return;

            if (direcciones.length === 0) {
                lista.innerHTML = '<p style="color:#7f8c8d; font-style:italic;">No addresses assigned.</p>';
            } else {
                lista.innerHTML = direcciones.map(dir => `
                    <div style="padding: 6px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                        <span><strong>${dir.cliente_nombre || '—'}</strong><br><small>${dir.direccion}</small></span>
                        <button class="btn-eliminar-direccion" data-id="${dir.id_direccion}" data-id_zona="${dir.id_zona}" data-nom_zona="${dir.nombre_zona}" style="background:#e74c3c; color:white; border:none; border-radius:4px; padding:2px 8px; font-size:0.85em; cursor:pointer;">
                            Remove
                        </button>
                    </div>
                `).join('');

                // Asignar listeners a los botones
                lista.querySelectorAll('.btn-eliminar-direccion').forEach(btn => {
                    btn.addEventListener('click', async (e) => {
                        e.stopPropagation();
                        const idDireccion = btn.dataset.id;
                        const idZona = btn.dataset.id_zona;
                        const nombreZona = btn.dataset.nom_zona;
                        await eliminarDireccionDeZona(idZona, idDireccion);
                        cargarDireccionesDeZona(idZona, nombreZona); // Recargar
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

            // Crear HTML de la lista
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

                // Asignar listeners a los botones
                lista.querySelectorAll('.btn-eliminar-direccion').forEach(btn => {
                    btn.addEventListener('click', async (e) => {
                        e.stopPropagation();
                        const idDireccion = btn.dataset.id;
                        await eliminarDireccionDeZona(idZona, idDireccion);
                        cargarDireccionesDeZona(idZona, nombreZona); // Recargar
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
                // 👇 Recargar la ruta COMPLETA (zonas + direcciones actualizadas)
                if (rutaActual) {
                    cargarZonasRuta(rutaActual.id_ruta);
                }
            } else {
                throw new Error(data.error || 'Failed to remove address.');
            }
        } catch (err) {
            suiteAlertError('Dang it!', err.message);
        } finally {
            suiteLoading('hide');
        }
    }

    function actualizarEstadoInterfaz() {
        if (_actualizandoEstado) return; // 👈 Evita recursión
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

            // 1. Restablecer estilos de zonas SI NO hay edición activa
            if (modoEdicionRuta === null) {
                zonasLayerGroup.eachLayer(layer => {
                    layer.setStyle({ color: "#3388ff", weight: 1, fillOpacity: 0.1 });
                });
                zonasSeleccionadas.clear();
                actualizarListaZonasSeleccionadas();
            }

            // 2. Actualizar botones según el modo
            if (modoEdicionRuta === 'crear' || modoEdicionRuta === 'editar') {
                // Modo edición → mostrar Cancel y ocultar Crear

                btnCancelar.style.display = 'block';
                // Actualizar texto del botón principal
                btnCrear.textContent = (modoEdicionRuta === 'crear' && zonasSeleccionadas.size > 0)
                    ? 'Save Route'
                    : 'Create New Route';

                btnActualizar.style.display = (modoEdicionRuta === 'editar') ? 'block' : 'none';
                btnEliminar.style.display = (modoEdicionRuta === 'editar') ? 'block' : 'none';
            } else {
                // Modo neutro → mostrar Crear, ocultar los demás
                btnCancelar.style.display = 'none';
                btnCrear.textContent = 'Create New Route';
                btnActualizar.style.display = 'none';
                btnEliminar.style.display = 'none';
            }

            // 3. Habilitar/deshabilitar inputs y tooltip
            const enModoEdicion = (modoEdicionRuta === 'crear' || modoEdicionRuta === 'editar');
            inputNombre.disabled = !enModoEdicion;
            inputColor.disabled = !enModoEdicion;
            inputColor.style.opacity = enModoEdicion ? '1' : '0.6';
            guiaColor.textContent = enModoEdicion
                ? "🎨 Select a color for your route"
                : "🎨 Click 'Create New Route' to enable color picker";
            mostrarTooltipNombre(enModoEdicion && modoEdicionRuta === 'crear' && zonasSeleccionadas.size === 0);


            // 4. Restablecer color del botón Addresses
            if (btnToggleDirecciones && map.hasLayer(direccionesLayerGroup)) {
                btnToggleDirecciones.style.backgroundColor = '#fff';
                map.removeLayer(direccionesLayerGroup);
            }
            // 4. Actualizar texto del botón "Crear"
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
        console.log('[initMapa] document.querySelectorAll("#mapa_geo").length =', document.querySelectorAll('#mapa_geo').length);
        if (contEl) console.log('[initMapa] contEl.isConnected =', contEl.isConnected, ', parentNode =', contEl.parentNode);

        if (!contEl) {
            console.warn('❌ No se encontró el contenedor del mapa (id="mapa_geo")');
            return;
        }

        // Si ya existe un mapa, eliminarlo antes de crear uno nuevo
        if (window.map) {
            try {
                window.map.remove();
            } catch (err) {
                console.warn('⚠️ Error al remover window.map existente:', err);
            }
            delete window.map;
        }

        // Diagnostic: comprobar qué devuelve L.DomUtil.get si está disponible
        try {
            if (L && L.DomUtil && typeof L.DomUtil.get === 'function') {
                console.log('[initMapa] L.DomUtil.get("mapa_geo") =>', L.DomUtil.get(contenedorId));
            }
        } catch (e) {
            console.warn('[initMapa] L.DomUtil.get threw:', e);
        }

        try {
            // Pasar el elemento DOM directamente a L.map en lugar del id
            const mapInstance = L.map(contEl).setView(HQ_COORDS, 13);

            // Elegir capa base según configuración ya cargada
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

            // Guardar referencia global
            window.map = mapInstance;
            map = window.map; // asignar también a la variable global usada por el resto del script

            console.log('✅ Mapa inicializado con éxito (mapInstance).');
        } catch (err) {
            console.error('❌ Error al inicializar L.map(contenedor):', err);
            // si quieres, re-lanzamos para capturar en tu try externo; por ahora lo dejamos logueado
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

    function crearFormularioFlotante(nombre_ruta = "", color_ruta = "white") {
        if (formularioZonaActiva) formularioZonaActiva.remove();

        formularioZonaActiva = L.DomUtil.create('div', 'formulario-zona-flotante');
        formularioZonaActiva.style = `
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
            cursor: move;
            `;

        // Encabezado fijo con fondo blanco
        const header = document.createElement('div');
        header.style = `
            background: white;
            padding: 15px 15px 10px 15px;
            border-bottom: 1px solid #eee;
            border-radius: 8px 8px 0 0;
        `;
        header.innerHTML = `<h4 style="margin:0; color:#2c3e50; font-size:1.1em;">Zone Addresses ${nombre_ruta}</h4>`;

        // Contenedor desplazable para la lista
        const listaContenedor = document.createElement('div');
        listaContenedor.id = 'lista-direcciones-zona';
        listaContenedor.style = `
            padding: 10px 15px 15px 15px;
            max-height: calc(70vh - 80px);
            overflow-y: auto;
        `;

        formularioZonaActiva.appendChild(header);
        formularioZonaActiva.appendChild(listaContenedor);

        // Hacerlo movible
        let isDragging = false;
        let offsetX, offsetY;

        formularioZonaActiva.addEventListener('mousedown', (e) => {
            if (e.target.classList.contains('btn-eliminar-direccion')) return;
            isDragging = true;
            offsetX = e.clientX - formularioZonaActiva.getBoundingClientRect().left;
            offsetY = e.clientY - formularioZonaActiva.getBoundingClientRect().top;
            formularioZonaActiva.style.cursor = 'grabbing';
            e.preventDefault();
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            // Mover manteniendo alineación derecha relativa
            const newRight = window.innerWidth - e.clientX - offsetX;
            const newTop = e.clientY - offsetY;
            formularioZonaActiva.style.right = `${newRight}px`;
            formularioZonaActiva.style.top = `${newTop}px`;
        });

        document.addEventListener('mouseup', () => {
            isDragging = false;
            if (formularioZonaActiva) {
                formularioZonaActiva.style.cursor = 'move';
            }
        });

        document.body.appendChild(formularioZonaActiva);
        console.log('✅ Se creo el Formulario de Direcciones con éxito (formularioZonaActiva).');
    }

    // --- Inicialización del Mapa (robusta) ---
    document.addEventListener('DOMContentLoaded', function () {
        // Handler que inicializa el mapa cuando la configuración esté lista
        function onConfigReady() {
            window.removeEventListener('configListo', onConfigReady);

            // Intentar inicializar el mapa, con varios reintentos si el contenedor no existe aún
            let tries = 0;
            const maxTries = 5;

            const tryInit = function () {
                tries++;
                const contenedorEl = document.getElementById('mapa_geo');

                console.log(`🔎 [map-init] intento ${tries} - document.readyState=${document.readyState} - contenedorEl=`, contenedorEl);

                if (!contenedorEl) {
                    if (tries < maxTries) {
                        console.warn('⚠️ [map-init] contenedor #map no encontrado, reintentando en 200ms...');
                        setTimeout(tryInit, 200);
                        return;
                    } else {
                        console.error('⛔ [map-init] contenedor #map NO encontrado tras varios intentos; abortando inicialización del mapa.');
                        return;
                    }
                }

                // Llamar a la función central que crea el L.map y guarda window.map
                try {
                    inicializarMapa(); // esta función contiene `const map = L.map(contenedor)...` y guardará `window.map`
                } catch (err) {
                    console.error('❌ Error al llamar inicializarMapa():', err);
                    return;
                }

                // Recojo la referencia global creada por inicializarMapa()
                map = window.map;
                if (!map) {
                    console.error('❌ inicializarMapa NO dejó window.map definido. Abortando setup adicional.');
                    return;
                }

                console.log('✅ Mapa creado. Referencia global `map` OK.');

                // === MARCADOR FIJO: Sede ===
                const starSvgUniform = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="-16 -16 32 32">
                    <!-- Triángulo hacia arriba -->
                    <polygon
                        points="0,-14 12,7 -12,7"
                        fill="#0066FF"
                        stroke="#0066FF"
                        stroke-width="1"
                    />
                    <!-- Triángulo hacia abajo -->
                    <polygon
                        points="0,14 12,-7 -12,-7"
                        fill="#0066FF"
                        stroke="#0066FF"
                        stroke-width="1"
                    />
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

                // Inicializar grupos de capas
                zonasLayerGroup = L.layerGroup().addTo(map);
                rutasLayerGroup = L.layerGroup().addTo(map);

                direccionesLayerGroup = L.layerGroup(); // Se añadirá/quitara según necesidad
                direccionesRutaLayerGroup = L.layerGroup(); // Solo para direcciones de la ruta cargada                
                direccionesLibresLayerGroup = L.layerGroup();

                direccionesAsignadasLayerGroup = L.layerGroup();
                //cuadriculaLayerGroup = L.layerGroup(); // Se añadirá/quitara según estado

                // Cargar zonas existentes
                cargarZonas();

                // Cargar rutas existentes
                cargarRutas();

                // UI buttons & controls (declarar y añadir)
                // --- Eventos de Interfaz ---
                document.getElementById('btn-crear-ruta').addEventListener('click', function () {
                    if (modoEdicionRuta === null && zonasSeleccionadas.size === 0) {
                        iniciarCreacionRuta(); // entra en modo
                    } else {
                        crearRuta(); // guarda
                    }
                });
                document.getElementById('btn-crear-ruta').textContent = zonasSeleccionadas.size > 0 ? 'Save Route' : 'Create New Route';

                document.getElementById('btn-actualizar-ruta').addEventListener('click', iniciarActualizacionRuta);
                document.getElementById('btn-eliminar-ruta').addEventListener('click', iniciarEliminacionRuta);
                document.getElementById('btn-volver').addEventListener('click', function () {
                    window.location.href = '<?= RUTA_REAL ?>/dashboard';
                });

                document.getElementById('btn-cancelar-accion').addEventListener('click', function () {
                    modoEdicionRuta = null;
                    rutaActual = null;
                    ocultarFormularioZona();

                    // Limpiar rutas y direcciones de ruta
                    if (map.hasLayer(direccionesRutaLayerGroup)) {
                        map.removeLayer(direccionesRutaLayerGroup);
                        direccionesRutaLayerGroup.clearLayers();
                    }
                    // ✅ Solo limpiar las direcciones LIBRES si están activas
                    if (map.hasLayer(direccionesLibresLayerGroup)) {
                        map.removeLayer(direccionesLibresLayerGroup);
                        direccionesLibresLayerGroup.clearLayers();
                    }



                    // ✅ Pero mantener las direcciones de la ruta solo si estás en modo edición
                    // Como modoEdicionRuta = null, no hay ruta activa → puedes limpiar direccionesLayerGroup
                    // if (map.hasLayer(direccionesLayerGroup)) {
                    //     map.removeLayer(direccionesLayerGroup);
                    //     direccionesLayerGroup.clearLayers();
                    // }

                    actualizarEstadoInterfaz();

                    direccionesLayerGroup.clearLayers(); // limpia en caso de reuso

                    // ✅ 2. Restablecer estilo de todas las zonas
                    zonasLayerGroup.eachLayer(layer => {
                        layer.setStyle({ color: "#3388ff", weight: 1, fillOpacity: 0.1 });
                    });
                    zonasSeleccionadas.clear();
                    actualizarListaZonasSeleccionadas();

                    // ✅ 3. Restablecer botón Addresses
                    if (btnToggleDirecciones) {
                        btnToggleDirecciones.style.backgroundColor = '#fff';
                    }

                    suiteAlertInfo('Action Canceled', 'Back to neutral state.');
                });

                // Botón para activar/desactivar direcciones
                const btnToggleDirecciones = L.DomUtil.create('button', 'leaflet-bar leaflet-control leaflet-control-custom');
                btnToggleDirecciones.title = 'Toggle Addresses';
                btnToggleDirecciones.innerHTML = 'Addresses'; // Puedes usar un ícono si prefieres
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
                        // ✅ 1. Forzar estado neutro ANTES de mostrar direcciones
                        modoEdicionRuta = null;
                        rutaActual = null;
                        zonasSeleccionadas.clear();
                        actualizarListaZonasSeleccionadas();

                        // Restablecer estilo de zonas
                        zonasLayerGroup.eachLayer(layer => {
                            layer.setStyle({ color: "#3388ff", weight: 1, fillOpacity: 0.1 });
                        });

                        // Restablecer botones
                        document.getElementById('btn-crear-ruta').style.display = 'block';
                        document.getElementById('btn-actualizar-ruta').style.display = 'none';
                        document.getElementById('btn-eliminar-ruta').style.display = 'none';
                        document.getElementById('btn-cancelar-accion').style.display = 'none';

                        // ✅ 2. Cargar SOLO direcciones libres
                        suiteLoading('show');
                        cargarDirecciones().then(() => {
                            // ✅ Bien: siempre asegúrate de que esté en el mapa
                            if (!map.hasLayer(direccionesLibresLayerGroup)) {
                                map.addLayer(direccionesLibresLayerGroup);
                            }
                            btnToggleDirecciones.style.backgroundColor = '#a3d2a3';
                            // Habilitar botón crear ruta si es la primera vez
                            if (!direccionesCargadas) {
                                const btnCrear = document.getElementById('btn-crear-ruta');
                                btnCrear.disabled = false;
                                btnCrear.style.opacity = '1';
                                btnCrear.title = '';
                                direccionesCargadas = true;
                            }
                            map.addLayer(direccionesLibresLayerGroup); // ✅
                            btnToggleDirecciones.style.backgroundColor = '#a3d2a3';

                        }).catch(err => {
                            suiteLoading('hide');
                            suiteAlertError("Uh oh!", "Failed to load addresses.");
                        }).finally(() => {
                            suiteLoading('hide');
                        });
                    }
                });

                // Añadir botón al control del mapa (arriba a la izquierda por defecto)
                //const customControlContainer = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                //customControlContainer.appendChild(btnToggleCuadricula);
                //map.addControl(L.control({ position: 'topleft' }).addTo(map).getContainer().appendChild(customControlContainer));

                // Crear contenedor para controles personalizados
                const customControlContainer = L.DomUtil.create('div', 'leaflet-bar leaflet-control');

                // Evitar que los clicks en los botones propaguen al mapa (para poder clicar botones sin mover el mapa)
                L.DomEvent.disableClickPropagation(customControlContainer);

                // Añadir los botones al contenedor
                customControlContainer.appendChild(btnToggleDirecciones);

                // Crear un control válido de Leaflet y devolver el contenedor desde onAdd
                const CustomControl = L.Control.extend({
                    options: { position: 'topleft' },
                    onAdd: function () {
                        return customControlContainer;
                    }
                });

                // Añadir el control al mapa correctamente
                map.addControl(new CustomControl());

                // Añadir botón de direcciones al mismo contenedor
                customControlContainer.appendChild(btnToggleDirecciones);
            };

            // arranque del intento
            tryInit();
        } // end onConfigReady

        // Deshabilitar botón al inicio
        document.getElementById('btn-crear-ruta').disabled = true;
        document.getElementById('btn-crear-ruta').style.opacity = '0.6';
        document.getElementById('btn-crear-ruta').title = 'Enable addresses first';

        // Registrar el listener ANTES de disparar la carga (evita perder el evento)
        window.addEventListener('configListo', onConfigReady);

        // Iniciar la carga de configuración (async)
        cargarConfigYIniciar();
    });

    // --- Funciones del Mapa y Lógica de Rutas ---

    // Función para dibujar la cuadrícula de 1 km en el área visible
    function dibujarCuadricula() {
        // Limpiar la capa de cuadrícula antes de redibujar
        //cuadriculaLayerGroup.clearLayers();

        // Obtener los límites del mapa visible
        const bounds = map.getBounds();
        const southWest = bounds.getSouthWest();
        const northEast = bounds.getNorthEast();

        // Tamaño aproximado de 1 km en grados (varía con la latitud)
        // Aproximadamente 0.009 grados de latitud = 1 km
        // Aproximadamente 0.012 grados de longitud = 1 km en Conroe, TX
        const kmInLat = 0.009;
        const kmInLng = 0.012;

        // Calcular el rango de celdas a dibujar
        const startLat = Math.floor(southWest.lat / kmInLat) * kmInLat;
        const endLat = Math.ceil(northEast.lat / kmInLat) * kmInLat;
        const startLng = Math.floor(southWest.lng / kmInLng) * kmInLng;
        const endLng = Math.ceil(northEast.lng / kmInLng) * kmInLng;

        // Dibujar rectángulos para cada celda
        for (let lat = startLat; lat <= endLat; lat += kmInLat) {
            for (let lng = startLng; lng <= endLng; lng += kmInLng) {
                const boundsCelda = [
                    [lat, lng],
                    [lat + kmInLat, lng + kmInLng]
                ];
                // Usar L.rectangle en lugar de L.polygon para cuadrados perfectos
                // const celda = L.rectangle(boundsCelda, {
                //     color: "#95a5a6", // Gris claro
                //     weight: 1,
                //     fillOpacity: 0.05, // Muy baja opacidad
                //     interactive: false // No hacerla clickable
                // }).addTo(cuadriculaLayerGroup);
            }
        }
    }

    // Función para cargar zonas desde el backend y dibujarlas
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

                    // Solo mostrar popup en modo neutro (fuera de edición)
                    if (modoEdicionRuta === null) {
                        rect.bindPopup(`<b>Zona:</b> ${zona.nombre_zona}<br>ID: ${zona.id_zona}<br>Coords: (${zona.lat_sw}, ${zona.lng_sw}) to (${zona.lat_ne}, ${zona.lng_ne})`);
                    }

                    // En cargarZonas(), al crear cada rectángulo:
                    rect.on('click', function (e) {
                        // En modo neutro: mostrar formulario con direcciones ya asignadas
                        if (modoEdicionRuta === 'crear' || modoEdicionRuta === 'editar') {
                            // En modo edición: abrir modal para asignar direcciones
                            seleccionarZonaConModal(rect);
                        } else {
                            zonaActivaId = rect.zonaId;
                            cargarDireccionesDeZona(rect.zonaId, rect.zonaNombre);
                        }
                    });
                });
            } else {
                throw new Error(data.error || 'Something ain’t right with the zones.');
            }
        } catch (err) {
            console.error("Error loading zones:", err);
            suiteAlertError('Uh oh!', 'Could not load zones: ' + err.message);
        } finally {
            suiteLoading('hide');
        }
    }

    // Función para cargar direcciones existentes y dibujarlas como marcadores
    async function cargarDirecciones() {
        try {
            suiteLoading('show');
            // Limpiar capa antes de cargar nuevas direcciones
            direccionesLibresLayerGroup.clearLayers();

            const response = await fetch(ruta_direcciones_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_direcciones: 'listar_direcciones_con_coordenadas' }) // Ajusta según tu endpoint
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const data = await response.json();
            if (data.success) {
                // data.data debe contener [{ id_direccion, direccion, lat, lng, cliente_nombre }, ...]
                data.data.forEach(dir => {
                    if (dir.lat && dir.lng) { // Asegurar que tenga coordenadas
                        // Crear un círculo en lugar de un marcador estándar
                        const circle = L.circle([dir.lat, dir.lng], {
                            radius: 75, // Radio en metros
                            color: "#ccff9fff", // Color del borde
                            fillColor: "#de1111ff", // Color de relleno
                            fillOpacity: 0.7,
                            weight: 1
                        }).addTo(direccionesLibresLayerGroup);

                        if (!map.hasLayer(direccionesLibresLayerGroup)) {
                            map.addLayer(direccionesLibresLayerGroup);
                        }

                        // Asociar datos de la dirección al círculo para futuras referencias
                        circle.direccionId = dir.id_direccion;
                        circle.direccionNombre = dir.cliente_nombre || 'Cliente Anónimo';
                        circle.direccionTexto = dir.direccion;

                        // Popup con información
                        circle.bindPopup(`<b>Client:</b> ${circle.direccionNombre}<br><b>Address:</b> ${circle.direccionTexto}<br>ID: ${circle.direccionId}`);
                    }
                });
                console.log("Direcciones cargadas y dibujadas.");
            } else {
                throw new Error(data.error || 'Couldn’t pull the addresses, partner.');
            }
        } catch (err) {
            console.error("Error loading addresses:", err);
            suiteAlertError('Uh oh!', 'Could not load addresses: ' + err.message);
            throw err; // 👈 Propagar error para el .catch()
        } finally {
            suiteLoading('hide');
        }
        // ✅ Devolver explícitamente (aunque es async, ya devuelve Promise)
        return true;
    }

    // Función para cargar rutas existentes
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
                    rutaDiv.innerHTML = `<span style="color: ${ruta.color_ruta}">■</span> ${ruta.nombre_ruta} (${ruta.id_ruta})`;
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

    // Función para cargar las zonas de una ruta específica
    async function cargarZonasRuta(id_ruta) {
        try {
            suiteLoading('show');
            console.log("🔍 [cargarZonasRuta] Iniciando carga de ruta ID:", id_ruta);
            // ocultarFormularioZona();

            const response = await fetch(ruta_rutas_mapa_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_rutas: 'obtener_ruta', id_ruta: id_ruta })
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();

            if (data.success) {
                const ruta = data.data;
                console.log("✅ [cargarZonasRuta] Ruta recibida:", ruta);

                const nombre_ruta = ruta.nombre_ruta;
                const color_ruta = ruta.color_ruta;
                const id_ruta = ruta.id_ruta;

                // 1. Limpiar capas de direcciones
                if (map.hasLayer(direccionesRutaLayerGroup)) {
                    map.removeLayer(direccionesRutaLayerGroup);
                }

                direccionesLayerGroup.clearLayers();  // 👈 Limpia la capa correcta
                //direccionesAsignadasLayerGroup.clearLayers();

                // 2. Colorear zonas
                zonasLayerGroup.eachLayer(layer => {
                    const enRuta = ruta.zonas.some(z => z.id_zona === layer.zonaId);
                    layer.setStyle({
                        color: enRuta ? ruta.color_ruta : "#97ff17ff",
                        weight: enRuta ? 2 : 1,
                        fillOpacity: enRuta ? 0.4 : 0.1
                    });
                    if (enRuta) layer.bringToFront();
                });

                // 3. Dibujar SOLO las direcciones asignadas a esta ruta
                let contadorDirecciones = 0;
                ruta.zonas.forEach(zona => {
                    if (zona.direcciones && Array.isArray(zona.direcciones)) {
                        zona.direcciones.forEach(dir => {
                            if (dir.lat && dir.lng) {
                                const circle = L.circle([dir.lat, dir.lng], {
                                    radius: 75,
                                    color: "#ccff9fff",
                                    fillColor: ruta.color_ruta || "#de1111ff",
                                    fillOpacity: 0.7,
                                    weight: 2
                                }).addTo(direccionesLayerGroup);
                                circle.direccionId = dir.id_direccion;
                                circle.direccionNombre = dir.cliente_nombre || '—';
                                circle.direccionTexto = dir.direccion;
                                circle.bindPopup(`<b>${dir.cliente_nombre}</b><br>${dir.direccion}<br><em>Zone: ${zona.nombre_zona}</em>`);
                                contadorDirecciones++;
                            }
                        });
                    }
                });

                console.log(`📍 [cargarZonasRuta] Direcciones dibujadas en capa: ${contadorDirecciones}`);


                // ✅ 4. CARGAR Y DIBUJAR DIRECCIONES LIBRES (no asignadas)
                try {
                    const responseLibres = await fetch(ruta_direcciones_ajax, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ modulo_direcciones: 'listar_direcciones_con_coordenadas' })
                    });
                    const dataLibres = await responseLibres.json();
                    if (dataLibres.success && dataLibres.data) {
                        dataLibres.data.forEach(dir => {
                            if (dir.lat && dir.lng) {
                                // ✅ Dibujar en ROJO (libres)
                                const circle = L.circle([dir.lat, dir.lng], {
                                    radius: 75,
                                    color: "#ccff9fff",
                                    fillColor: "#de1111ff", // Rojo: no asignada
                                    fillOpacity: 0.7,
                                    weight: 1
                                }).addTo(direccionesLayerGroup);
                                circle.direccionId = dir.id_direccion;
                                circle.direccionNombre = dir.cliente_nombre || '—';
                                circle.direccionTexto = dir.direccion;
                                circle.bindPopup(`<b>${dir.cliente_nombre}</b><br>${dir.direccion}<br><em>Not assigned to any route</em>`);
                            }
                        });
                    }
                } catch (err) {
                    console.warn("No se cargaron direcciones libres:", err);
                }

                // 4. Asegurar que la capa esté en el mapa
                if (!map.hasLayer(direccionesLayerGroup)) {
                    map.addLayer(direccionesLayerGroup);
                    console.log("✅ [cargarZonasRuta] Capa de direcciones AÑADIDA al mapa.");
                } else {
                    map.removeLayer(direccionesLayerGroup);
                    console.log("ℹ️ [cargarZonasRuta] Capa de direcciones YA estaba en el mapa.");
                    map.addLayer(direccionesLayerGroup);
                    console.log("✅ [cargarZonasRuta] Capa de direcciones AÑADIDA al mapa.");
                }


                // 5. Actualizar UI
                document.getElementById('input-nombre-ruta').value = ruta.nombre_ruta;
                document.getElementById('input-color-ruta').value = ruta.color_ruta;
                document.getElementById('btn-crear-ruta').style.display = 'none';
                document.getElementById('btn-actualizar-ruta').style.display = 'block';
                document.getElementById('btn-eliminar-ruta').style.display = 'block';
                zonasSeleccionadas.clear();
                ruta.zonas.forEach(z => zonasSeleccionadas.add(z.id_zona));
                actualizarListaZonasSeleccionadas();
                modoEdicionRuta = 'editar';
                rutaActual = ruta;

                const nuevo_color = hexToRgba(color_ruta);
                crearFormularioFlotante(nombre_ruta, nuevo_color);
                cargarDireccionesDeZonaenRutas(id_ruta);

                // Limpiar popups de todas las zonas al entrar en modo edición
                zonasLayerGroup.eachLayer(layer => {
                    if (layer.getPopup()) {
                        layer.unbindPopup();
                    }
                });

                modoEdicionRuta = 'editar';
                rutaActual = ruta;
                actualizarEstadoInterfaz();

            } else {
                throw new Error(data.error || 'Route not found.');
            }
        } catch (err) {
            console.error("Error loading route:", err);
            suiteAlertError('Uh oh!', 'Could not load route: ' + err.message);
        } finally {
            suiteLoading('hide');
        }
    }

    // Función para iniciar el modo de creación de ruta
    function iniciarCreacionRuta() {
        modoEdicionRuta = 'crear';
        ocultarFormularioZona();
        document.getElementById('input-nombre-ruta').value = '';
        document.getElementById('input-color-ruta').value = '#3498db';
        actualizarEstadoInterfaz();
        if (btnToggleDirecciones) {
            btnToggleDirecciones.style.backgroundColor = '#fff';
        }
        suiteAlertInfo('New Route', 'Click on zones to build your route. Don’t forget to connect back to HQ!');
    }

    // Función para iniciar el modo de actualización de ruta
    function iniciarActualizacionRuta() {
        if (!rutaActual) {
            suiteAlertWarning('Hold up!', 'You ain’t selected no route to update.');
            return;
        }

        // Limpiar popups de todas las zonas al entrar en modo edición
        zonasLayerGroup.eachLayer(layer => {
            if (layer.getPopup()) {
                layer.unbindPopup();
            }
        });

        modoEdicionRuta = 'editar';
        suiteAlertInfo('Edit Mode', 'Click zones to add or remove ’em from this route.');
        actualizarRuta();
    }

    // Función para iniciar el modo de eliminación de ruta
    function iniciarEliminacionRuta() {
        if (!rutaActual) {
            suiteAlertWarning('Whoa there!', 'You gotta pick a route first.');
            return;
        }
        modoEdicionRuta = 'eliminar';
        suiteConfirm('Delete Route?', `You sure you wanna delete "${rutaActual.nombre_ruta}"? You can’t undo this.`)
            .then(result => {
                if (result) {
                    eliminarRuta(rutaActual.id_ruta);
                } else {
                    modoEdicionRuta = 'editar';
                }
            });
    }

    // Función para actualizar la lista de zonas seleccionadas en el sidebar
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

    // Función para crear una nueva ruta
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
                    zonas_ids: zonas_ids
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

    // Función para actualizar una ruta existente
    async function actualizarRuta() {
        if (!rutaActual) {
            suiteAlertWarning('Hold up!', 'You ain’t selected no route to update.');
            return;
        }

        const id_ruta = rutaActual.id_ruta;
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
                    modulo_rutas: 'actualizar_ruta',
                    id_ruta: id_ruta,
                    nombre_ruta: nombre,
                    color_ruta: color,
                    zonas_ids: zonas_ids
                })
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const data = await response.json();
            if (data.success) {
                suiteAlertSuccess('Sweet!', data.message);
                await cargarZonasRuta(id_ruta);
                // ✅ Recargar SOLO la lista de rutas (el panel lateral)
                await cargarRutas(); // ← Esto actualiza el listado con nombre y color nuevos
                // ✅ Volver a cargar las zonas (por si cambiaron)
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

    // Función para eliminar una ruta existente
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

            // Contenedor del título y botón de selección
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

            // Event listeners
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

    // === SELECCIÓN DE ZONA CON MODAL DE DIRECCIONES ===
    async function seleccionarZonaConModal(zone) {
        const bounds = zone.getBounds();
        const sw = bounds.getSouthWest();
        const ne = bounds.getNorthEast();

        // Verificar si ya está seleccionada → permitir deselección inmediata
        if (zonasSeleccionadas.has(zone.zonaId)) {
            zonasSeleccionadas.delete(zone.zonaId);
            zone.setStyle({ color: "#3388ff", weight: 1, fillOpacity: 0.1 });
            actualizarListaZonasSeleccionadas();
            return;
        }

        try {
            suiteLoading('show');

            // Consultar direcciones en el área
            const response = await fetch("<?php echo RUTA_APP; ?>/app/ajax/zonasAjax.php", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_zonas: 'listar_direcciones_en_area',
                    lat_sw: sw.lat,
                    lng_sw: sw.lng,
                    lat_ne: ne.lat,
                    lng_ne: ne.lng
                })
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Failed to load addresses in zone');

            const direcciones = data.data || [];

            // Si no hay direcciones, seleccionar directamente
            if (direcciones.length === 0) {
                zonasSeleccionadas.add(zone.zonaId);
                zone.setStyle({ color: "#e74c3c", weight: 2, fillOpacity: 0.3 });
                actualizarListaZonasSeleccionadas();
                suiteLoading('hide');
                return;
            }
            // Cierra suiteLoading
            suiteLoading('hide');

            // Abrir modal para seleccionar direcciones
            const idsSeleccionados = await abrirModalSeleccionDirecciones(direcciones, zone.zonaNombre, zone.zonaId);

            if (idsSeleccionados === null) {
                // Cancelado
                return;
            }

            // Si no se seleccionan direcciones, la zona se añade sin asociaciones
            if (idsSeleccionados.length === 0) {
                suiteAlertInfo('Noted!', `Zone "${zone.zonaNombre}" added without addresses.`);
                zonasSeleccionadas.add(zone.zonaId);
                zone.setStyle({ color: "#e74c3c", weight: 2, fillOpacity: 0.3 });
                actualizarListaZonasSeleccionadas();
                return;
            }

            // Guardar asociación en backend
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
                    nombre_zona: zone.zonaNombre
                })
            });

            const saveData = await saveResponse.json();
            suiteLoading('hide');

            if (!saveData.success) throw new Error(saveData.error || 'Failed to link addresses');

            // Ahora sí, seleccionar la zona
            zonasSeleccionadas.add(zone.zonaId);
            zone.setStyle({ color: "#e74c3c", weight: 2, fillOpacity: 0.3 });
            actualizarListaZonasSeleccionadas();

            // Mostrar formulario flotante con las direcciones de la zona
            // console.log("Antes de crearFormularioFlotante");            
            //     crearFormularioFlotante();
            // console.log("Despues de crearFormularioFlotante");            
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