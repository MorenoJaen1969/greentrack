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
                        <button id="reporte" class="btn-accion">Report</button>
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

<!-- MODAL PRINCIPAL -->
<div id="serModalOverlay" class="ser-modal-overlay">
    <div class="ser-modal-container">
        
        <!-- HEADER -->
        <div class="ser-modal-header">
            <h3 style="margin:0;">Resource Allocation by Route</h3>
            <span style="cursor:pointer; font-size:1.2rem;" onclick="closeSerModal()">&times;</span>
        </div>

        <!-- BODY (3 COLUMNAS) -->
        <div class="ser-modal-body">
            
            <!-- COLUMNA 1: LISTA DE RUTAS -->
            <div class="ser-col-routes">
                <div class="ser-col-header">Available Routes</div>
                <ul class="ser-route-list" id="serRouteListContainer">
                    <!-- Se llena con JS -->
                </ul>
            </div>

            <!-- COLUMNA 2: FORMULARIO -->
            <div class="ser-col-form">
                <div id="serFormContent" style="display:none;">
                    <h2 id="serActiveRouteTitle" style="margin-top:0; color:#333;">Configuring Route...</h2>
                    <p style="color:#666; font-size:0.9rem; margin-bottom:20px;">Select the resources for this specific path.</p>

                    <!-- Driver Select -->
                    <div class="ser-form-section">
                        <h3>1. Assign Driver</h3>
                        <div id="serDriverDisplay" class="ser-selected-display" onclick="toggleSerDropdown('serDriverDropdown')">
                            <span style="color:#999;">Select Driver...</span>
                            <span>▼</span>
                        </div>
                        <div id="serDriverDropdown" class="ser-custom-select">
                            <!-- Opciones JS -->
                        </div>
                        <input type="hidden" id="serDriverId">
                    </div>

                    <!-- Crew List -->
                    <div class="ser-form-section">
                        <h3>2. Assign Crew</h3>
                        <div class="ser-crew-list" id="serCrewListContainer">
                            <!-- Checkboxes JS -->
                        </div>
                        <input type="hidden" id="serCrewIds">
                    </div>

                    <!-- Truck Select -->
                    <div class="ser-form-section">
                        <h3>3. Assign Truck</h3>
                        <div id="serTruckDisplay" class="ser-selected-display" onclick="toggleSerDropdown('serTruckDropdown')">
                            <span style="color:#999;">Select Truck...</span>
                            <span>▼</span>
                        </div>
                        <div id="serTruckDropdown" class="ser-custom-select">
                            <!-- Opciones JS -->
                        </div>
                        <input type="hidden" id="serTruckId">
                    </div>
                </div>
                <div id="serNoSelectionMsg" style="text-align:center; padding-top:50px; color:#999;">
                    Select a route from the left list to begin.
                </div>
            </div>

            <!-- COLUMNA 3: RESUMEN (PREVIEW) -->
            <div class="ser-col-preview">
                <div class="ser-col-header">Assignment Summary</div>
                <div id="serPreviewContainer">
                    <!-- Tarjetas de resumen dinámicas -->
                </div>
            </div>

        </div>

        <!-- FOOTER -->
        <div class="ser-modal-footer">
            <button class="ser-btn ser-btn-cancel" onclick="closeSerModal()">Cancel All</button>
            <button class="ser-btn ser-btn-save" id="serFinalSaveBtn" onclick="saveAllRoutes()">Save Changes</button>
        </div>
    </div>
</div>


<!-- Fondo oscuro -->
<div id="modalOverlay" class="modal-overlay-bg" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;"></div>

<script>
    let currentRouteId = null;
    let van = 0;
    let resolveModalPromise = null;
    
    // Varioble para el tipo de reporte a ser visualizado
    // 0 = Preservicio, 1 = Servicio sin procesar, 2 = Servicio Porcesado
    //
    let tipo_de_reporte = 0;

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

    // --- FUNCIONES DE RENDERIZADO ---

    function renderCustomSelect(containerId, data, isMulti = false) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';

        if (isMulti) {
            // Renderizado para Crew (Multiselect con checkboxes)
            data.forEach(item => {
                const div = document.createElement('div');
                div.className = 'ser-checkbox-item';
                div.innerHTML = `
                    <input type="checkbox" id="crew_${item.id}" value="${item.id}" data-name="${item.name}">
                    <img src="${item.img}" class="ser-option-img" alt="${item.name}">
                    <span class="ser-option-text">${item.name}</span>
                `;
                container.appendChild(div);
            });
        } else {
            // Renderizado para Driver y Truck (Click para seleccionar)
            data.forEach(item => {
                const div = document.createElement('div');
                div.className = 'ser-option-item';
                div.onclick = function() {
                    // Actualizar visualmente el contenedor
                    container.innerHTML = `
                        <div style="display:flex; align-items:center;">
                            <img src="${item.img}" class="ser-option-img">
                            <span class="ser-option-text">${item.name}</span>
                        </div>
                    `;
                    // Guardar valor en el input hidden
                    const hiddenInput = document.getElementById(containerId.replace('Select', 'Value'));
                    hiddenInput.value = item.id; // O item.name según necesites
                };
                div.innerHTML = `
                    <img src="${item.img}" class="ser-option-img">
                    <span class="ser-option-text">${item.name}</span>
                `;
                container.appendChild(div);
            });
        }
    }

    // ============================================
    // CARGA DE SELECTS (compatibilidad)
    // ============================================
    async function cargar_drivers(id_crew, ruta) {
        try {
            const res = await fetch(ruta, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_status: 'crear_select'
                })
            });
            const data = await res.json();            
            if (data.error) {
                console.error("Error en respuesta de Drivers:", data.error);
                return [];
            } else {
                return data.drivers || [];
            }
        } catch (err) {
            console.error("Error al cargar Drivers:", err);
        }
    }

    // ============================================
    // CARGA DE SELECTS (RUTAS)
    // ============================================
    async function cargar_rutas(id_crew, ruta) {
        try {
            const res = await fetch(ruta, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_rutas: 'crear_select_dia',
                    id_ruta: 0,
                    fecha_proceso: document.getElementById('fecha-despacho').value
                })
            });

            if (res.ok) {
                const data = await res.json();
                const rutasOriginales = data.resulta.rutas || [];

                // --- AQUÍ ESTÁ LA MAGIA ---
                // Transformamos el array del servidor al formato del Modal
                const rutasFormateadas = rutasOriginales.map(r => ({
                    id: r.id_ruta,             // Mapeamos id_ruta a id
                    name: r.nombre_ruta,       // Mapeamos nombre_ruta a name
                    status: 'pending',         // Valor por defecto
                    driver: null,              // Valor por defecto
                    crew: [],                  // Valor por defecto (array vacío)
                    truck: null                // Valor por defecto
                }));
                // ---------------------------

                return rutasFormateadas; 
            }                
        } catch (err) { 
            console.error("Error al cargar Rutas:", err); 
            return []; // Retornar array vacío en caso de error para evitar roturas
        }
    }

    // ============================================
    // CARGA DE SELECTS (DRIVERS)
    // ============================================
    async function cargar_drivers(ruta, tipo) {
        try {
            const res = await fetch(ruta, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_crew: 'cargar_drivers',
                    tipo: tipo
                })
            });

            if (res.ok) {
                const data = await res.json();
                const driversOriginales = data.data || [];

                // --- AQUÍ ESTÁ LA MAGIA ---
                // Transformamos el array del servidor al formato del Modal
                const driversFormateadas = driversOriginales.map(d => ({
                    id: d.id,           // Mapeamos id_ruta a id
                    name: d.name,       // Mapeamos nombre_ruta a name
                    img: d.img          // Valor por defecto
                }));
                // ---------------------------

                return driversFormateadas; 
            }                
        } catch (err) { 
            console.error("Error al cargar Rutas:", err); 
            return []; // Retornar array vacío en caso de error para evitar roturas
        }
    }

    // ============================================
    // CARGA DE SELECTS (TRUCKS)
    // ============================================
    async function cargar_trucks(ruta) {
        try {
            const res = await fetch(ruta, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_vehiculos: 'cargar_trucks'
                })
            });

            if (res.ok) {
                const data = await res.json();
                const trucksOriginales = data.data || [];

                // --- AQUÍ ESTÁ LA MAGIA ---
                // Transformamos el array del servidor al formato del Modal
                const trucksFormateadas = trucksOriginales.map(d => ({
                    id: d.id,           // Mapeamos id_ruta a id
                    name: d.name,       // Mapeamos nombre_ruta a name
                    img: d.img          // Valor por defecto
                }));
                // ---------------------------

                return trucksFormateadas; 
            }                
        } catch (err) { 
            console.error("Error al cargar Trucks:", err); 
            return []; // Retornar array vacío en caso de error para evitar roturas
        }
    }

    // --- LÓGICA DEL MODAL ---
    async function openModal() {
        return new Promise((resolve, reject) => {
            resolveModalPromise = resolve;
            
            // Llamar a una función async interna
            inicializarModal().then(() => {
                // Éxito - el modal está listo
            }).catch((err) => {
                console.error("Error al inicializar modal:", err);
                suiteAlertError("Error", "Error loading data for the modal.");
                reject(err);
            });
        });
    }

    async function inicializarModal() {
        // 1. Cargar datos
        window.routesDB = (await cargar_rutas(null, 'app/ajax/rutas_mapaAjax.php')) || [];
        window.driversDB = (await cargar_drivers('app/ajax/crewAjax.php', 1)) || [];
        window.crewDB = (await cargar_drivers('app/ajax/crewAjax.php', 2)) || [];
        window.trucksDB = (await cargar_trucks('app/ajax/vehiculosAjax.php')) || [];

        // 2. Mostrar Modal
        document.getElementById('serModalOverlay').style.display = 'flex';

        // 3. Renderizar
        setTimeout(() => {
            renderRouteList();
            renderDropdowns();
            renderCrewList();
            currentRouteId = null;
            document.getElementById('serFormContent').style.display = 'none';
            document.getElementById('serNoSelectionMsg').style.display = 'block';
            document.getElementById('serPreviewContainer').innerHTML = '';
        }, 100);
    }

    function closeSerModal() {
        // Validar si hay cambios pendientes antes de cerrar por cancelación
        if (currentRouteId) {
            validateAndSaveCurrentState();
        }
        document.getElementById('serModalOverlay').style.display = 'none';
        currentRouteId = null;

        // Resolver la promesa para indicar que el modal cerró
        if (resolveModalPromise) {
            resolveModalPromise();
            resolveModalPromise = null;
        }
    }    

    // --- 2. RENDERIZADO DE LISTA DE RUTAS (Usar window.routesDB) ---
    function renderRouteList() {
        const container = document.getElementById('serRouteListContainer');
        if (!container) return;
        container.innerHTML = '';
        
        if (!window.routesDB || window.routesDB.length === 0) {
            container.innerHTML = '<li style="padding:15px; color:#999;">No routes available</li>';
            return;
        }

        window.routesDB.forEach(route => {
            const li = document.createElement('li');
            li.className = `ser-route-item ${route.status === 'completed' ? 'completed' : ''} ${currentRouteId === route.id ? 'active' : ''}`;
            li.onclick = () => selectRoute(route.id);
            
            // Mostrar nombre del driver asignado si existe
            let driverName = '';
            if (route.driver && window.driversDB) {
                const d = window.driversDB.find(dr => dr.id == route.driver);
                if (d) driverName = `<span style="font-size:0.75rem; color:#007bff;">👤 ${d.name}</span>`;
            }

            li.innerHTML = `
                <div class="ser-route-info">
                    <h4>${route.name}</h4>
                    ${driverName}
                </div>
                <div class="ser-route-status" title="${route.status === 'completed' ? 'Complete' : 'Pending'}"></div>
            `;
            container.appendChild(li);
        });
    }

    // --- 3. RENDERIZADO DE DROPDOWNS (Drivers y Trucks) ---
    function renderDropdowns() {
        // Drivers
        const dContainer = document.getElementById('serDriverDropdown');
        if (dContainer && window.driversDB) {
            dContainer.innerHTML = '';
            window.driversDB.forEach(d => {
                const div = document.createElement('div');
                div.className = 'ser-option';
                div.innerHTML = `<img src="${d.img}"><span>${d.name}</span>`;
                div.onclick = () => selectSerOption('driver', d.id, d.name, d.img);
                dContainer.appendChild(div);
            });
        }

        // Trucks
        const tContainer = document.getElementById('serTruckDropdown');
        if (tContainer && window.trucksDB) {
            tContainer.innerHTML = '';
            window.trucksDB.forEach(t => {
                const div = document.createElement('div');
                div.className = 'ser-option';
                div.innerHTML = `<img src="${t.img}"><span>${t.name}</span>`;
                div.onclick = () => selectSerOption('truck', t.id, t.name, t.img);
                tContainer.appendChild(div);
            });
        }
    }

    // --- 4. RENDERIZADO DE CREW LIST (Checkboxes) ---
    function renderCrewList() {
        const cContainer = document.getElementById('serCrewListContainer');
        if (!cContainer || !window.crewDB) return;
        
        cContainer.innerHTML = '';
        window.crewDB.forEach(c => {
            const div = document.createElement('div');
            div.className = 'ser-crew-item';
            // Aseguramos que el value sea string para comparación consistente
            div.innerHTML = `
                <input type="checkbox" value="${String(c.id)}" onchange="updateCrewState()">
                <img src="${c.img}">
                <span>${c.name}</span>
            `;
            cContainer.appendChild(div);
        });
    }

    // --- 5. SELECCIÓN DE RUTA (Con validación de datos) ---
    function selectRoute(routeId) {
        if (!window.routesDB) return;

        // 1. Guardar estado anterior si existe
        if (currentRouteId) {
            validateAndSaveCurrentState();
        }

        // 2. Actualizar ID actual
        currentRouteId = routeId;
        const routeData = window.routesDB.find(r => r.id == routeId); // Comparación flexible

        if (!routeData) return;

        // 3. UI Switch
        document.getElementById('serNoSelectionMsg').style.display = 'none';
        document.getElementById('serFormContent').style.display = 'block';
        document.getElementById('serActiveRouteTitle').innerText = `Configuring: ${routeData.name}`;

        // 4. Cargar datos en el formulario
        loadFormData(routeData);
        
        // 5. Actualizar Preview y Lista
        updatePreview(routeData);
        renderRouteList();
    }

    // --- 6. CARGAR DATOS EN FORMULARIO ---
    function loadFormData(routeData) {
        if (!routeData) return;

        // --- DRIVER (Búsqueda estricta en driversDB) ---
        const driverDisplay = document.getElementById('serDriverDisplay');
        const driverIdInput = document.getElementById('serDriverId');
        
        // Reset
        driverIdInput.value = '';
        driverDisplay.innerHTML = `<span style="color:#999;">Select Driver...</span><span>▼</span>`;

        if (routeData.driver) {
            // Buscamos EXCLUSIVAMENTE en window.driversDB
            const driver = window.driversDB ? window.driversDB.find(d => String(d.id) === String(routeData.driver)) : null;
            
            if (driver) {
                driverIdInput.value = driver.id;
                // Mostramos datos del DRIVER, no del truck
                driverDisplay.innerHTML = `
                    <div style="display:flex;align-items:center;">
                        <img src="${driver.img}" class="ser-preview-img-sm">
                        <span>${driver.name}</span>
                    </div><span>▼</span>`;
            }
        }

        // --- TRUCK (Búsqueda estricta en trucksDB) ---
        const truckDisplay = document.getElementById('serTruckDisplay');
        const truckIdInput = document.getElementById('serTruckId');
        
        // Reset
        truckIdInput.value = '';
        truckDisplay.innerHTML = `<span style="color:#999;">Select Truck...</span><span>▼</span>`;

        if (routeData.truck) {
            // Buscamos EXCLUSIVAMENTE en window.trucksDB
            const truck = window.trucksDB ? window.trucksDB.find(t => String(t.id) === String(routeData.truck)) : null;
            
            if (truck) {
                truckIdInput.value = truck.id;
                // Mostramos datos del TRUCK
                truckDisplay.innerHTML = `
                    <div style="display:flex;align-items:center;">
                        <img src="${truck.img}" class="ser-preview-img-sm">
                        <span>${truck.name}</span>
                    </div><span>▼</span>`;
            }
        }

        // --- CREW (Checkboxes) ---
        const routeCrewIds = Array.isArray(routeData.crew) ? routeData.crew.map(String) : [];
        const checkboxes = document.querySelectorAll('.ser-crew-item input');
        checkboxes.forEach(cb => {
            // Comparación estricta de strings
            cb.checked = routeCrewIds.includes(String(cb.value));
        });
        document.getElementById('serCrewIds').value = routeCrewIds.join(',');
    }

    // --- 7. VALIDACIÓN AL CAMBIAR DE RUTA ---
    function validateAndSaveCurrentState() {
        if (!window.routesDB || !currentRouteId) return;
        
        const routeIndex = window.routesDB.findIndex(r => r.id == currentRouteId);
        if (routeIndex === -1) return;

        const driverId = document.getElementById('serDriverId').value;
        const truckId = document.getElementById('serTruckId').value;
        const crewIds = Array.from(document.querySelectorAll('.ser-crew-item input:checked')).map(cb => cb.value);

        // Guardar en el objeto de datos
        window.routesDB[routeIndex].driver = driverId;
        window.routesDB[routeIndex].truck = truckId;
        window.routesDB[routeIndex].crew = crewIds;

        // Evaluar completitud
        const isComplete = driverId && truckId && crewIds.length > 0;
        window.routesDB[routeIndex].status = isComplete ? 'completed' : 'pending';
    }

    // ============================================
    // VALIDACIÓN DE CIERRE (REGLAS DE NEGOCIO)
    // ============================================
    function validacionDeCierre() {
        const resultado = {
            valido: true,
            errores: [],
            advertencias: [],
            info: []
        };

        if (!Array.isArray(window.routesDB) || window.routesDB.length === 0) {
            resultado.errores.push("No routes available to assign.");
            resultado.valido = false;
            return resultado;
        }

        const asignacionesPorDriver = {};
        const asignacionesPorTruck = {};
        let rutasPendientes = 0;
        let rutasSinCrew = 0;

        window.routesDB.forEach(paquete => {
            if (paquete.status === 'pending') rutasPendientes++;

            if (paquete.driver) {
                if (!asignacionesPorDriver[paquete.driver]) asignacionesPorDriver[paquete.driver] = [];
                asignacionesPorDriver[paquete.driver].push({
                    rutaId: paquete.id,
                    rutaNombre: paquete.name,
                    truck: paquete.truck,
                    crew: paquete.crew || []
                });

                if (paquete.truck) {
                    if (!asignacionesPorTruck[paquete.truck]) asignacionesPorTruck[paquete.truck] = [];
                    asignacionesPorTruck[paquete.truck].push({
                        rutaId: paquete.id,
                        rutaNombre: paquete.name,
                        driver: paquete.driver
                    });
                }
            }

            if (!paquete.crew || paquete.crew.length === 0) rutasSinCrew++;
        });

        // Regla 1 y 4: Driver múltiple
        for (const driverId in asignacionesPorDriver) {
            const rutasDelDriver = asignacionesPorDriver[driverId];
            if (rutasDelDriver.length > 1) {
                const nombresRutas = rutasDelDriver.map(r => r.rutaNombre).join(', ');
                
                // Buscar nombre real del driver para el mensaje
                const driverReal = window.driversDB ? window.driversDB.find(d => String(d.id) === String(driverId)) : null;
                const driverNombre = driverReal ? driverReal.name : `ID ${driverId}`;

                const primerTruck = rutasDelDriver[0].truck;
                const primerCrew = JSON.stringify(rutasDelDriver[0].crew.sort());
                let inconsistente = false;
                let detallesInconsistencia = [];

                for (let i = 1; i < rutasDelDriver.length; i++) {
                    if (rutasDelDriver[i].truck !== primerTruck) {
                        inconsistente = true;
                        // Buscar nombre del truck para el error
                        const truckReal = window.trucksDB ? window.trucksDB.find(t => String(t.id) === String(rutasDelDriver[i].truck)) : null;
                        const truckNombre = truckReal ? truckReal.name : `ID ${rutasDelDriver[i].truck}`;
                        detallesInconsistencia.push(`Truck (${truckName}) different in ${rutasDelDriver[i].rutaNombre}`);
                    }
                    if (JSON.stringify(rutasDelDriver[i].crew.sort()) !== primerCrew) {
                        inconsistente = true;
                        detallesInconsistencia.push(`Different CREW in ${rutasDelDriver[i].rutaNombre}`);
                    }
                }

                if (inconsistente) {
                    resultado.errores.push(
                        `⛔ Driver "${driverNombre}" is in ${rutasDelDriver.length} routes (${nombresRutas}), but the resources do not match: ${detallesInconsistencia.join('; ')}.`
                    );
                    resultado.valido = false;
                } else {
                    resultado.advertencias.push(
                        `⚠️ Driver "${driverNombre}" is assigned to ${rutasDelDriver.length} routes: ${nombresRutas}. Do you confirm this driver will perform all these routes with the same equipment?`
                    );
                }
            }
        }

        // Regla 2: Truck con dos drivers
        for (const truckId in asignacionesPorTruck) {
            const rutasDelTruck = asignacionesPorTruck[truckId];
            if (rutasDelTruck.length > 1) {
                const driversUnicos = [...new Set(rutasDelTruck.map(r => r.driver))];
                if (driversUnicos.length > 1) {
                    const nombresRutas = rutasDelTruck.map(r => r.rutaNombre).join(', ');
                    // Buscar nombre del truck
                    const truckReal = window.trucksDB ? window.trucksDB.find(t => String(t.id) === String(truckId)) : null;
                    const truckNombre = truckReal ? truckReal.name : `ID ${truckId}`;
                    
                    resultado.errores.push(
                        `⛔ Truck "${truckNombre}" is assigned to multiple drivers (${driversUnicos.length}) in the routes: ${nombresRutas}.`
                    );
                    resultado.valido = false;
                }
            }
        }

        if (rutasSinCrew > 0) {
            resultado.advertencias.push(`⚠️ There are ${rutasSinCrew} route(s) without an assigned Crew.`);
        }
        if (rutasPendientes > 0) {
            resultado.advertencias.push(`⚠️ You have ${rutasPendientes} pending (incomplete) route(s).`);
        }

        return resultado;
    }

    // --- INTERACCIONES DE FORMULARIO ---
    function toggleSerDropdown(id) {
        const el = document.getElementById(id);
        const isVisible = el.classList.contains('show');
        // Cerrar todos primero
        document.querySelectorAll('.ser-custom-select').forEach(d => d.classList.remove('show'));
        if (!isVisible) el.classList.add('show');
    }

    function selectSerOption(type, id, name, img) {
        if (type === 'driver') {
            document.getElementById('serDriverId').value = id;
            document.getElementById('serDriverDisplay').innerHTML = `<div style="display:flex;align-items:center;"><img src="${img}"><span>${name}</span></div><span>▼</span>`;
        } else if (type === 'truck') {
            document.getElementById('serTruckId').value = id;
            document.getElementById('serTruckDisplay').innerHTML = `<div style="display:flex;align-items:center;"><img src="${img}"><span>${name}</span></div><span>▼</span>`;
        }
        toggleSerDropdown(type === 'driver' ? 'serDriverDropdown' : 'serTruckDropdown');
        updatePreviewFromForm(); // Actualizar preview en tiempo real
    }

    function updateCrewState() {
        const checked = Array.from(document.querySelectorAll('.ser-crew-item input:checked')).map(cb => cb.value);
        document.getElementById('serCrewIds').value = checked.join(',');
        updatePreviewFromForm();
    }

    // --- 8. ACTUALIZAR PREVIEW (CORREGIDO) ---
    function updatePreviewFromForm() {
        if (!currentRouteId || !window.routesDB) return;
        
        const dId = document.getElementById('serDriverId').value;
        const tId = document.getElementById('serTruckId').value;
        const cIds = document.getElementById('serCrewIds').value.split(',').filter(id => id);
        
        const routeData = window.routesDB.find(r => r.id == currentRouteId);
        if (!routeData) return;

        const tempData = {
            id: currentRouteId,
            name: routeData.name,
            driver: dId,
            truck: tId,
            crew: cIds
        };
        
        updatePreview(tempData);
    }

    // --- 9. RENDERIZAR TARJETA DE PREVIEW (CORREGIDO PRINCIPAL) ---
    function updatePreview(data) {
        const container = document.getElementById('serPreviewContainer');
        if (!container) return;

        // --- Búsqueda blindada por tipo de entidad ---
        // Driver busca en driversDB
        const driver = window.driversDB ? window.driversDB.find(d => String(d.id) === String(data.driver)) : null;
        
        // Truck busca en trucksDB
        const truck = window.trucksDB ? window.trucksDB.find(t => String(t.id) === String(data.truck)) : null;
        
        // Crew busca en crewDB
        const crewIds = Array.isArray(data.crew) ? data.crew.map(String) : [];
        const crew = window.crewDB ? window.crewDB.filter(c => crewIds.includes(String(c.id))) : [];

        // --- Construcción del HTML ---
        let html = `
            <div class="ser-preview-card">
                <div class="ser-preview-title">${data.name}</div>
                
                <!-- FILA DRIVER -->
                <div class="ser-preview-row">
                    <span class="ser-preview-label">Driver:</span>
                    <span class="ser-preview-val">
                        ${driver 
                            ? `<img src="${driver.img}" class="ser-preview-img-sm"> ${driver.name}` 
                            : '<span style="color:#999">Unassigned</span>'}
                    </span>
                </div>

                <!-- FILA TRUCK -->
                <div class="ser-preview-row">
                    <span class="ser-preview-label">Truck:</span>
                    <span class="ser-preview-val">
                        ${truck 
                            ? `<img src="${truck.img}" class="ser-preview-img-sm"> ${truck.name}` 
                            : '<span style="color:#999">Unassigned</span>'}
                    </span>
                </div>

                <!-- FILA CREW -->
                <div class="ser-preview-row" style="align-items:flex-start;">
                    <span class="ser-preview-label">Crew:</span>
                    <span class="ser-preview-val">
                        ${crew.length > 0 
                            ? crew.map(c => `<div style="margin-bottom:4px;"><img src="${c.img}" class="ser-preview-img-sm"> ${c.name}</div>`).join('') 
                            : '<span style="color:#999">Unassigned</span>'}
                    </span>
                </div>
            </div>
        `;
        container.innerHTML = html;
    }

    async function saveAllRoutes() {
        if (currentRouteId) validateAndSaveCurrentState();

        const validacion = validacionDeCierre();

        // Si hay ERRORES críticos, detener y mostrar (NO cierra el modal)
        if (validacion.errores.length > 0) {
            const mensajeError = validacion.errores.join('\n\n');
            await suiteAlertError("Error", "❌ VALIDACIÓN FALLIDA:\n\n" + mensajeError);
            return; 
        }

        // Si hay ADVERTENCIAS, requerir confirmación (NO cierra el modal si cancela)
        if (validacion.advertencias.length > 0) {
            const mensajeAdvertencia = validacion.advertencias.join('\n\n');
            const confirmado = await suiteConfirm(
                "⚠️ WARNINGS",
                `${mensajeAdvertencia} \n\nDo you want to continue and save?`
            );
            if (!confirmado) return; 
        }

        try {
            const res = await fetch('app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_servicios: 'guardar_servicios',
                    fecha: document.getElementById('fecha-despacho').value,
                    rutas: window.routesDB
                })
            });
            const data = await res.json();
            if (!data.success) {
                await suiteAlertError("Error", "The services could not be saved: " + data.message);
            } else {
                await suiteAlertSuccess('Success', "✅ Configuration saved successfully.");
            }
            closeSerModal(); // ÚNICO PUNTO DE CIERRE EXITOSO
            
        } catch (err) {
            console.error("Error al guardar:", err);
            await suiteAlertError("Error", "No se pudo guardar la configuración: " + err.message);
        }
    }    

    // ==================================================  //
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