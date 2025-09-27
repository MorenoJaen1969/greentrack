<!-- app/views/content/dashboard-view.php -->
<div class="dashboard-container">
    <!-- 30% - Carrusel de servicios -->
    <div class="carousel-wrapper">
        <div class="elem_row">
            <h3 class="carousel-title">
                Daily Itinirary
                <span id="fecha-display" style="color: #fffb23ff; margin: 0 8px;">-- --</span>
                <span id="hora-display" style="color: #4aff1cff;">--:--:--</span>
                <button id="btn-daily-status" class="button" style="margin-left: 10px;">
                    Daily Status
                </button>
                <button id="btn-select-client" class="button" style="margin-left: 10px;">
                    Select Client
                </button>
            </h3>
        </div>

        <!-- Contenedor del carrusel (igual a tu estructura) -->
        <div class="carrusel-container">
            <div class="contenedor-piso-detalles">
                <div class="carrusel" id="servicio-carrusel">
                    <div id="carrusel" class="dis_caja forma_caja">
                        <!-- Aquí se cargan las tarjetas o el mensaje de estado -->
                    </div>
                    <!-- Tarjetas generadas por JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- 70% - Mapa -->
    <div class="map-wrapper">
        <div id="live-map"></div>
    </div>
</div>

<!-- Modal: Daily Status Matrix -->
<div id="modal-daily-status" class="estilo_modal1">
    <div class="primer_nivel">
        <!-- Encabezado -->
        <div
            style="padding: 15px; background: #2196F3; color: white; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.2em;">Daily Status – Crew vs Clients</h3>
            <button id="close-daily-status"
                style="background: #f44336; color: white; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 18px; cursor: pointer;">✕</button>
        </div>
        <!-- Contenido -->
        <div id="matrix-container" style="flex: 1; overflow: auto; padding: 10px;">
            <table id="daily-status-matrix" class="tabla-matriz"
                style="width: 100%; border-collapse: collapse; font-size: 0.8em;">
                <thead>
                    <tr style="background: #f0f0f0;">
                        <th
                            style="border: 1px solid #ddd; padding: 8px; position: sticky; top: 0; left: 0; background: #e0e0e0; z-index: 3;">
                            Client</th>
                        <!-- Crews se insertarán aquí -->
                    </tr>
                </thead>
                <tbody id="matrix-body">
                    <!-- Filas de clientes -->
                </tbody>
                <tfoot id="matrix-footer">
                    <!-- Fila de totales -->
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div id="modal-select-client" class="segundo_proceso" style="display: none;">
    <div class="modal-contenedor" style="width: 50%;">
        <!-- Encabezado -->
        <div class="modal-header">
            <h3 class="titulo_modal">Select Client</h3>
            <button id="close-select-client" class="btn_cerrar">&times;</button>
        </div>
        <div id="lista-clientes" style="max-height: 50vh; overflow-y: auto;">
            <p>Loading...</p>
        </div>
    </div>
</div>

<!-- Formulario flotante sobre el mapa -->
<div id="form-flotante-historico" style="position: absolute; top: 40px; right: 40px; z-index: 10001; 
            width: 280px; height: auto; min-height: 80px; 
            background: rgba(0,0,0,0.7); color: #fff; border-radius: 10px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.2); 
            padding: 12px; cursor: move; display: flex; flex-direction: column; 
            font-family: Arial, sans-serif;">

    <!-- Título y botón Ver -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <span style="font-size: 1.1em; font-weight: bold;">Historical Route</span>
        <button id="btn-abrir-modal-historico" style="background: #2196F3; color: #fff; border: none; border-radius: 6px; 
                       padding: 6px 12px; cursor: pointer; font-size: 0.95em;">
            View
        </button>
    </div>

    <!-- Línea divisoria con margen -->
    <div style="width: 100%; height: 1px; background: #fff; margin: 6px 0; position: relative; overflow: hidden;">
        <div style="background: #fff; height: 100%; width: 90%; margin: 0 auto;"></div>
    </div>

    <!-- Contenedor de botones de vehículos -->
    <div id="contenedor-vehiculos-historico" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
        <!-- Los botones se insertarán aquí dinámicamente desde JS -->
        <!-- Ejemplo: -->
        <!-- <button class="btn-vehiculo-hist" style="background:#FF0000;">TRUCK 15</button> -->
    </div>
</div>

<!-- Modal avanzado para consulta histórica -->
<div id="modal-historico-ruta" class="modal-overlay"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">
    
    <div class="modal_hist"
         style="width: 96%; height: 90%; margin: 0% auto; background: #fff; border-radius: 10px; padding: 15px; display: flex; flex-direction: column; font-family: Arial, sans-serif;">

        <!-- Título y cierre -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #ddd;">
            <h3 style="margin: 0; font-size: 1.2em;">Historical Route & Services</h3>
            <button id="btn-cerrar-modal-historico"
                style="background: #f44336; color: #fff; border: none; border-radius: 50%; width: 32px; height: 32px; font-size: 18px; cursor: pointer;">✕</button>
        </div>

        <!-- Filtros: Fecha y Vehículo -->
        <div style="margin: 12px 0; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <label for="fecha-historico">Date:</label>
            <input type="date" id="fecha-historico">

            <label for="vehiculo-historico">Vehicle:</label>
            <select id="vehiculo-historico" style="min-width: 130px;"></select>

            <button id="btn-consultar-historico"
                style="background: #4CAF50; color: #fff; border: none; border-radius: 6px; padding: 6px 14px; cursor: pointer;">
                Accept
            </button>
        </div>

        <!-- Contenedor principal: Mapa + Panel servicios -->
        <div style="display: flex; flex: 1; gap: 15px; overflow: hidden;">

            <!-- Mapa (70%) -->
            <div class="cell-mapa" style="flex: 7; min-width: 300px;">
                <div id="mapa-historico"
                     style="width: 100%; height: 100%; background: #222; border-radius: 8px;">
                </div>
            </div>

            <!-- Panel lateral: Servicios (30%) -->
            <div class="cell-info" style="flex: 3; display: flex; flex-direction: column; min-width: 250px;">
                <div id="servicios-historico"
                    style="
                        flex: 1;
                        max-height: 100%;
                        overflow-y: auto;
                        overflow-x: hidden;
                        background: #f9f9f9;
                        border-radius: 6px;
                        padding: 8px;
                        font-size: 0.9em;
                        scrollbar-width: thin;
                        scrollbar-color: #888 #f1f1f1;
                    ">
                </div>
            </div>
        </div>

        <!-- Barra inferior: Controles, conteo, hora -->
        <div style="height: 8%; display: flex; align-items: center; gap: 15px; margin-top: 10px; flex-wrap: wrap;">

            <!-- Velocidad -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <label for="velocidad-historico">Speed:</label>
                <input type="range" id="velocidad-historico" min="100" max="2000" value="700" style="width: 150px;">
                <span id="velocidad-historico-valor" style="color: #2196F3; font-weight: bold;">700 ms</span>
            </div>

            <!-- Contador de servicios -->
            <div style="color: #555;">
                Services scheduled: <span id="cant_servicios" style="color: #2196F3; font-weight: bold;">0</span>
            </div>

            <!-- Botones de control -->
            <div style="display: flex; align-items: center; gap: 8px; margin-left: auto;">
                <button id="btn-reiniciar-ruta" title="Restart" style="...">🔄</button>
                <button id="btn-rapido-atras" title="Backward" style="...">⏪</button>
                <button id="btn-pausa-reanudar" title="Pause/Play" style="...">⏸️</button>
                <button id="btn-rapido-adelante" title="Forward" style="...">⏩</button>
                <span id="estado-reproduccion" style="font-size: 0.9em; color: #555;">Playing...</span>
            </div>

            <!-- Hora actual de animación -->
            <span id="hora-animacion" style="margin-left: 10px; color: #2196F3; font-weight: bold;">
                --:--:--
            </span>
        </div>
    </div>
</div>

<script>
    // Actualizar fecha y hora en tiempo real
    function actualizarReloj() {
        const ahora = new Date();

        // Formato: 15 Abr
        const opcionesFecha = { day: '2-digit', month: 'short' };
        const fecha = ahora.toLocaleDateString('es-ES', opcionesFecha)
            .replace('.', ''); // Quitar el punto de "abr."

        // Formato: hh:mm:ss
        const hora = ahora.toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });

        // Actualizar el DOM
        document.getElementById('fecha-display').textContent = fecha;
        document.getElementById('hora-display').textContent = hora;
    }

    // Actualizar cada segundo
    actualizarReloj();
    setInterval(actualizarReloj, 1000);



    console.log('Antes de cargar Datos Generales');


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
    document.addEventListener('DOMContentLoaded', cargarConfigYIniciar);

    // Drag & drop para el formulario flotante
    (function () {
        const form = document.getElementById('form-flotante-historico');
        let offsetX, offsetY, dragging = false;
        form.addEventListener('mousedown', function (e) {
            dragging = true;
            offsetX = e.clientX - form.offsetLeft;
            offsetY = e.clientY - form.offsetTop;
            form.style.transition = 'none';
        });
        document.addEventListener('mousemove', function (e) {
            if (dragging) {
                form.style.left = (e.clientX - offsetX) + 'px';
                form.style.top = (e.clientY - offsetY) + 'px';
            }
        });
        document.addEventListener('mouseup', function () {
            dragging = false;
            form.style.transition = '';
        });
    })();

    let animacionActiva = true;
    let animacionTimeout = null;
    let velocidadNormal = 700; // valor base
    let velocidadActual = velocidadNormal;
    let indiceActual = 0; // posición actual en el historial


    // Actualizar valor de velocidad
    const velocidadInput = document.getElementById('velocidad-historico');
    const velocidadValor = document.getElementById('velocidad-historico-valor');
    velocidadInput.oninput = () => { velocidadValor.textContent = velocidadInput.value + ' ms'; };


    // Cargar vehículos en el select del modal
    async function cargarTrucksHistorico() {
        const res = await fetch('/app/ajax/motor2Ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ modulo_motor2: 'obtener_trucks_activos_hoy' })
        });
        const data = await res.json();
        const select = document.getElementById('select-truck-historico');
        select.innerHTML = '';
        if (Array.isArray(data.trucks)) {
            data.trucks.forEach(truck => {
                const opt = document.createElement('option');
                opt.value = truck;
                opt.textContent = truck;
                select.appendChild(opt);
            });
        }
    }

</script>