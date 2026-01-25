<div class="dashboard-container">
    <!-- 30% - Carrusel de servicios -->
    <div class="carousel-wrapper">
        <div class="elem_row">
            <h3 class="carousel-title">
                <div class="grid-t-p">
                    <div class="grid-t-p_01">
                        Daily Itinirary
                        <span id="fecha-display" style="color: #fffb23ff; margin: 0 8px;">-- --</span>
                        <span id="hora-display" style="color: #4aff1cff;">--:--:--</span>
                    </div>
                    <div class="grid-t-p_02">
                        <button id="btn-daily-status" class="button" style="margin-left: 10px;">
                            Daily Status
                        </button>
                        <button id="btn-select-client" class="button" style="margin-left: 10px;">
                            Select Customer
                        </button>
                    </div>
                    <div class="grid-t-p_03">
                        <button id="btn-select-despacho" class="button-despacho" style="margin-left: 10px;">
                            Dispatch Service
                        </button>
                    </div>
                </div>
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

<!-- Modal: Para Seleccionar Cliente -->
<div id="modal-select-client" class="segundo_proceso" style="display: none;">
    <div class="modal-contenedor" style="width: 50%;">
        <!-- Encabezado -->
        <div class="modal-header">
            <h3 class="titulo_modal">Select Customer</h3>
            <button id="close-select-client" class="btn_cerrar">&times;</button>
        </div>
        <div id="lista-clientes" style="max-height: 50vh; overflow-y: auto;">
            <p>Loading...</p>
        </div>
    </div>
</div>

<!-- Modal: Para Mostrar despacho -->
<div id="modal-select-despacho" class="segundo_proceso" style="display: none;">
    <div class="modal-contenedor_despacho">
        <!-- Encabezado -->
        <div class="modal-header">
            <h3 class="titulo_modal">Service Dispatch</h3>
            <!-- <button id="btn-zona_c">🔄 Crear Zonas Cuadriculas</button> -->
            <button id="close-select-despacho" class="btn_cerrar">&times;</button>
        </div>

        <!-- Contenedor de los dos paneles -->
        <div class="containerServicio">
            <div id="panel_servicio" class="panel1">
                <!-- Panel: Servicios Asignados (HOY) -->
                <div class="lista_despacho">
                    <div class="titServicios1">
                        <h4 id="tit_tipo_servicio" style="margin: 0; color: #2c7;">Pre-Assigned</h4>
                        <div>
                            <label for="fecha-despacho">This dispatch is for the day:</label>
                            <input type="date" id="fecha-despacho" class="formatoFecha">
                        </div>
                    </div>
                    <div id="lista-asignados" class="panel-scroll vista_det">
                        <p>Loading...</p>
                    </div>
                </div>

                <div id="pie_panel_izquierdo" class="pie_despacho caja_resumen pie_izq-preservicio">
                    <!-- Botón de guardar (opcional, para futura implementación) -->
                    <div id="totalRegistros" class="pie_izq-preservicio_01"></div>
                    <div id="htmlTotalTiempo" class="pie_izq-preservicio_02"></div>
                    <div class="pie_izq-preservicio_03">
                        <button id="reordenar" class="btn-accion">Reorder</button>
                        <button id="btn-guardar-despacho" class="btn-accion">Save Dispatch</button>
                    </div>
                </div>
            </div>

            <!-- Botones de control central -->
            <div class="titServicios2">
                <div class="boxes">
                    <button id="btn-mover-a-no-asignados" disabled title="Remove from Today">➡️</button>
                    <button id="btn-mover-a-asignados" disabled title="Add to Today">⬅️</button>
                </div>
            </div>

            <div id="panel_sinServicio" class="panel1">
                <!-- Panel: Servicios NO Asignados -->
                <div class="lista_despacho">
                    <div class="titServicios1">
                        <h4 style="margin-top: 0; color: #a33;">Available for Assignment</h4>
                        <!-- Dentro del panel derecho -->
                        <div class="elemento_hor searchBTN">
                            <input type="text" id="buscar-no-asignados"
                                placeholder="Search for client, address, or frequency..."
                                class="cajaSearch">
                            <button id="btn-next-coincidencia" class="botonSearch">🔍</button>
                        </div>
                    </div>

                    <div id="lista-no-asignados" class="panel-scroll vista_det">
                        <p>Loading...</p>
                    </div>
                </div>

                <div id="pie_panel_derecho" class="pie_despacho caja_resumen">
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Formulario flotante sobre el mapa -->
<div id="form-flotante-historico" class="form_float">

    <!-- Título y botón Ver -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <span style="font-size: 1.1em; font-weight: bold;">Historical Route</span>
        <button id="btn-abrir-modal-historico" style="background: #2196F3; color: #fff; border: none; border-radius: 6px; 
                    padding: 6px 12px; cursor: pointer; font-size: 0.95em;">
            View
        </button>
        <!-- <button id="btn-reconciliar-historico">🔄 Reconcile Historical Data</button>         -->
        <!-- <button id="btn-reconciliar-historico-completo">🔄 Reconcile Historical Data</button>         -->
    </div>

    <!-- Línea divisoria con margen -->
    <div style="width: 100%; height: 1px; background: #fff; margin: 6px 0; position: relative; overflow: hidden;">
        <div style="background: #fff; height: 100%; width: 90%; margin: 0 auto;"></div>
    </div>
    <div>Vehicles with Assigned Services</div>

    <!-- Contenedor de botones de vehículos Activos (Con servicios)-->
    <div id="contenedor-vehiculos-historico" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; width: 100%;">
    </div>

    <!-- Línea divisoria con margen -->
    <div style="width: 100%; height: 1px; background: #fff; margin: 6px 0; position: relative; overflow: hidden;">
        <div style="background: #fff; height: 100%; width: 90%; margin: 0 auto;"></div>
    </div>
    <div>Vehicles without Assigned Services</div>

    <!-- Contenedor de botones de vehículos Activos (Sin servicios)-->
    <div id="contenedor-vehiculos-sin-servicio" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; width: 100%;">
    </div>

</div>

<!-- Formulario flotante sobre el mapa Para datos del dia -->
<div id="form-referencia-dia" class="form_refer">
    <!-- Título y botón Ver -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <div class="elem_column">
            <span class="tit_form">Pass through day</span>
            <span id="form_veh_act" class="sub_form"></span>
            <div id="loading-typewriter"></div>
        </div>
    </div>

    <!-- Línea divisoria con margen -->
    <div style="width: 100%; height: 1px; background: #fff; margin: 6px 0; position: relative; overflow: hidden;">
        <div style="background: #fff; height: 100%; width: 90%; margin: 0 auto;"></div>
    </div>

    <!-- Contenedor de botones de vehículos -->
    <div id="contenedor-stops" class="contenedor-stops">
    </div>
</div>

<!-- Modal avanzado para consulta histórica -->
<div id="modal-historico-ruta" class="modal-overlay"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">

    <div class="modal_hist"
        style="width: 96%; height: 90%; margin: 0% auto; background: #fff; border-radius: 10px; padding: 15px; display: flex; flex-direction: column; font-family: Arial, sans-serif;">

        <!-- Título y cierre -->
        <div
            style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #ddd;">
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
                <div id="mapa-historico" style="width: 100%; height: 100%; background: #222; border-radius: 8px;">
                </div>
            </div>

            <!-- Panel lateral: Servicios (30%) -->
            <div class="cell-info" style="flex: 3; display: flex; flex-direction: column; min-width: 250px;">
                <div id="servicios-historico" style="
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
                <button id="btn-reiniciar-ruta" title="Restart">🔄</button>
                <button id="btn-rapido-atras" title="Backward">⏪</button>
                <button id="btn-pausa-reanudar" title="Pause/Play">⏸️</button>
                <button id="btn-rapido-adelante" title="Forward">⏩</button>
                <span id="estado-reproduccion" style="font-size: 0.9em; color: #555;">Playing...</span>
            </div>

            <!-- Hora actual de animación -->
            <span id="hora-animacion" style="margin-left: 10px; color: #2196F3; font-weight: bold;">
                --:--:--
            </span>
        </div>
    </div>
</div>

<!-- Modal de Detalles de Servicio -->
<div id="modal-servicio" class="modal-overlay_gps" style="display:none;">
    <div class="modal-contenedor">
        <button id="close_modal_servicio" class="modal-cerrar1">✕</button>
        <div class="modal-grid-3x2">
            <!-- Información principal -->
            <div class="modal-info" style="grid-column: 1; grid-row: 1 / 3;">
                <h3>Service Details</h3>
                <table class="tabla-detalles" id="tabla-detalles-servicio"></table>
                <h4>Crew Members</h4>
                <div class="crew-detalle-lista" id="crew-detalle-lista"></div>
                <div id="ultima-nota-servicio"></div>
            </div>
            <!-- Acciones -->
            <div class="modal-acciones" style="grid-column: 2; grid-row: 1;">
                <h4>Actions</h4>
                <button id="btn-inicio-actividades" class="btn-accion btn-inicio">Start of activities</button>
                <button id="btn-finalizar-servicio" class="btn-accion btn-finalizar">Processed</button>
                <button id="btn-replanificar-servicio" class="btn-accion btn-replanificar">Rescheduled</button>
                <button id="btn-cancelar-servicio" class="btn-accion btn-cancelar">Cancelled</button>
            </div>
            <!-- Notas y campos dinámicos -->
            <div class="modal-notas" style="grid-column: 2; grid-row: 2;">
                <div id="bloque-hora-inicio" class="bloque-hora-inicio" style="display:none; margin-bottom:8px;">
                    <label><strong>Start time:</strong></label>
                    <input type="time" id="input-hora-inicio" class="input-hora input-hora-inicio">
                </div>
                <div id="bloque-nota-inicio" class="bloque-nota-inicio" style="display:none; margin-bottom:8px;">
                    <label><strong>Start note:</strong></label>
                    <textarea id="input-nota-inicio" class="input-notas nota-inicio" readonly></textarea>
                </div>
                <div id="bloque-hora-fin" class="bloque-hora-fin" style="display:none; margin-bottom:8px;">
                    <label><strong>End time:</strong></label>
                    <input type="time" id="input-hora-fin" class="input-hora input-hora-fin">
                </div>
                <div id="bloque-nota-final" class="bloque-nota-final" style="display:none; margin-bottom:8px;">
                    <label><strong>Final note:</strong></label>
                    <textarea id="input-nota-final" class="input-notas nota-final" placeholder="Add a note..."></textarea>
                </div>
                <div id="acciones-notas" class="acciones-notas" style="display:none;">
                    <button id="btn-guardar-notas" class="btn-guardar-notas">Save</button>
                    <button id="btn-cancelar-notas" class="btn-cancelar-notas">Cancel</button>
                </div>
            </div>
            <!-- Historial -->
            <div class="modal-historial" style="grid-column: 1 / 3; grid-row: 3; height: 200px; overflow-y: auto;">
                <h4>Service History</h4>
                <table class="tabla-historial">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Truck</th>
                            <th>Status</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody id="historial-servicio">
                        <tr>
                            <td colspan="4">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal contenedor -->
<div id="selectModal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 300px; padding: 20px; border-radius: 8px; background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <div id="dat_cliente"></div>
    
        <h3 style="margin-top: 0; font-size: 1.2em;">Select an option</h3>

        <select id="modalSelect" style="width: 100%; padding: 8px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px;">
            <!-- Las opciones se llenarán desde JS -->
        </select>

        <div style="text-align: right; margin-top: 16px;">
            <button id="btnCancelar" style="padding: 6px 12px; margin-right: 8px; background: #f0f0f0; border: 1px solid #ccc; border-radius: 4px; cursor: pointer;">
                Cancel
            </button>
            <button id="btnAceptar" style="padding: 6px 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Ok
            </button>
        </div>
    </div>
</div>

<!-- Fondo oscuro -->
<div id="modalOverlay" class="modal-overlay-bg" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;"></div>

<script>
    function sumarUnDiaYEvitarSabado(fecha) {
        // Crear una copia para no modificar la original
        const nuevaFecha = new Date(fecha);

        // Sumar 1 día
        nuevaFecha.setDate(nuevaFecha.getDate() + 1);

        // Verificar si es sábado (getDay() devuelve 6 para sábado)
        if (nuevaFecha.getDay() === 6) {
            // Si es sábado, sumar 1 día más (para hacerlo domingo)
            nuevaFecha.setDate(nuevaFecha.getDate() + 1);
        }

        return nuevaFecha;
    }

    // Actualizar fecha y hora en tiempo real
    function actualizarReloj() {
        const ahora = new Date();

        // Formato: 15 Abr
        const opcionesFecha = {
            day: '2-digit',
            month: 'short'
        };
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

    // Leer la hora de cierre desde el backend (en formato HH:mm)
    const horaCierre = '<?php echo HORA_CIERRE_SESION; ?>';
    let tiempoInactividad = null;
    let umbralMs = null;

    if (horaCierre) {
        // Convertir hora de cierre a timestamp de hoy
        const [horas, minutos] = horaCierre.split(':').map(Number);
        const hoy = new Date();
        const cierreHoy = new Date(hoy);
        cierreHoy.setHours(horas, minutos + 30, 0, 0); // +30 minutos

        // Si ya pasó la hora de cierre, no activar el temporizador
        if (cierreHoy > hoy) {
            umbralMs = cierreHoy.getTime() - hoy.getTime();
            console.log('⏰ Temporizador de inactividad activado. Cierre programado en:', umbralMs / 60000, 'minutos');
            iniciarTemporizadorInactividad(umbralMs);
        }
    }

    function iniciarTemporizadorInactividad(ms) {
        // Reiniciar temporizador en cada actividad
        const reiniciar = () => {
            if (tiempoInactividad) clearTimeout(tiempoInactividad);
            tiempoInactividad = setTimeout(cerrarSesion, ms);
        };

        // Eventos que reinician el temporizador
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(evento => {
            document.addEventListener(evento, reiniciar, true);
        });

        // Iniciar el temporizador
        reiniciar();
    }

    function cerrarSesion() {
        console.log('🔒 Cerrando sesión por inactividad');
        // Opcional: notificar al backend
        fetch('/app/ajax/usuariosAjax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                modulo_usuarios: 'cerrar_sesion'
            })
        }).finally(() => {
            window.location.href = '/'; // Redirigir al login
        });
    }

    console.log('Antes de cargar Datos Generales');

    async function cargarConfigYIniciar() {
        try {
            const response = await fetch('/app/ajax/datosgeneralesAjax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
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
                    window.APP_CONFIG = {
                        ...DEFAULT_CONFIG
                    };
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
    (function() {
        const form = document.getElementById('form-flotante-historico');
        let offsetX, offsetY, dragging = false;
        form.addEventListener('mousedown', function(e) {
            dragging = true;
            offsetX = e.clientX - form.offsetLeft;
            offsetY = e.clientY - form.offsetTop;
            form.style.transition = 'none';
        });
        document.addEventListener('mousemove', function(e) {
            if (dragging) {
                form.style.left = (e.clientX - offsetX) + 'px';
                form.style.top = (e.clientY - offsetY) + 'px';
            }
        });
        document.addEventListener('mouseup', function() {
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
    velocidadInput.oninput = () => {
        velocidadValor.textContent = velocidadInput.value + ' ms';
    };


    // Cargar vehículos en el select del modal
    async function cargarTrucksHistorico() {
        const res = await fetch('/app/ajax/motor2Ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                modulo_motor2: 'obtener_trucks_activos_hoy'
            })
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

    // Dispatch date logic: prevent actions when selected date is today or earlier
    (function() {
        window.dispatchConfig = window.dispatchConfig || {
            dateIsProcessed: false
        };

        function parseDateOnly(dateStr) {
            if (!dateStr) return null;
            // Create date at midnight local time
            const d = new Date(dateStr + 'T00:00:00');
            if (isNaN(d.getTime())) return null;
            d.setHours(0, 0, 0, 0);
            return d;
        }

        function isDateProcessed(dateStr) {
            const sel = parseDateOnly(dateStr);
            if (!sel) return false;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            // Services on or before today are considered processed
            return sel.getTime() <= today.getTime();
        }

        function updateDispatchState(dateStr) {
            const processed = isDateProcessed(dateStr);
            window.dispatchConfig.dateIsProcessed = processed;

            const btnNo = document.getElementById('btn-mover-a-no-asignados');
            const btnYes = document.getElementById('btn-mover-a-asignados');
            if (btnNo) btnNo.disabled = processed;
            if (btnYes) btnYes.disabled = processed;

            const panel = document.getElementById('panel_sinServicio');
            if (panel) {
                if (processed) {
                    panel.style.cursor = 'not-allowed';
                } else {
                    panel.style.cursor = '';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const fecha = document.getElementById('fecha-despacho');
            if (fecha) {
                fecha.addEventListener('change', function(e) {
                    updateDispatchState(e.target.value);
                });
                // initial
                updateDispatchState(fecha.value);
            }

            // Prevent drag behavior on panel_sinServicio when processed
            const panel = document.getElementById('panel_sinServicio');
            if (panel) {
                // Block pointer/drag events when processed
                const blockIfProcessed = function(e) {
                    if (window.dispatchConfig && window.dispatchConfig.dateIsProcessed) {
                        e.preventDefault();
                        e.stopPropagation();
                        return false;
                    }
                };

                panel.addEventListener('mousedown', blockIfProcessed, {
                    passive: false
                });
                panel.addEventListener('touchstart', blockIfProcessed, {
                    passive: false
                });
                panel.addEventListener('dragstart', blockIfProcessed, {
                    passive: false
                });
            }
        });
    })();
</script>