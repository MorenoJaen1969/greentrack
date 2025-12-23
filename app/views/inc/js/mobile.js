// mobile.js - Frontend Ejecutivo para Don Sergio

// === Variables globales ===

/**
 * Devuelve la fecha actual en formato 'YYYY-MM-DD' según America/Chicago
 */
function getTodayInChicago() {
    const now = new Date();
    const tzOffset = -5 * 60; // CDT (UTC-5)
    const stdOffset = -6 * 60; // CST (UTC-6)

    // Detectar si es horario de verano en Chicago (DST)
    const chicagoStd = new Date(now.toLocaleString("en-US", { timeZone: "America/Chicago" }));
    const isDST = chicagoStd.getMonth() !== 0 || chicagoStd.getDate() !== 1;
    const offsetMinutes = isDST ? tzOffset : stdOffset;

    // Convertir a minutos desde UTC y aplicar offset de CT
    const utcMinutes = now.getTime() / 60000;
    const localMinutes = utcMinutes + offsetMinutes;
    const todayInCT = new Date(localMinutes * 60000);

    return todayInCT.toISOString().split('T')[0]; // Ej: "2025-10-03"
}

// === Variables globales ===
const dia_hoy = getTodayInChicago();
let selectedDate = dia_hoy;

// Mostrar solo para depuración
console.log('📅 Fecha del día operativo en CT:', selectedDate);

// Al inicio de mobile.js
let rutaPoints = [];
let currentPointIndex = 0;
let playbackInterval = null;
let isPlaying = false;
let markerVehiculo = null;
let layerClientes = null;
const estadoCamion = {}; // Estado persistente por vehículo

// Coordenadas de la sede
const HQ = {
    lat: 30.3204272,
    lng: -95.4217815
};

// Asegurar APP_CONFIG
if (!window.APP_CONFIG) {
    window.APP_CONFIG = {
        mapa_base: 'ESRI',
        umbral_metros: 150,
        umbral_minutos: 5,
        umbral_course: 10
    };
}

/**
 * Calcula el tiempo total en sitio: 
 * (tiempo real detenido desde GPS) + (tiempo que llevas observando)
 */
function calcularTiempoEnSitio(tiempoInicioGPS, tiempoObservadoSegundos, serverTime) {
    if (!(tiempoInicioGPS instanceof Date) || isNaN(tiempoInicioGPS.getTime())) {
        console.warn('Invalid tiempoInicioGPS');
        return { realMin: 0, observadoMin: 0, totalMin: 0 };
    }

    if (!(serverTime instanceof Date) || isNaN(serverTime.getTime())) {
        console.warn('Invalid serverTime');
        return { realMin: 0, observadoMin: 0, totalMin: 0 };
    }

    const diffMsReal = serverTime - tiempoInicioGPS;
    const realMin = diffMsReal / (1000 * 60);
    const observadoMin = tiempoObservadoSegundos / 60;
    const totalMin = Math.floor(realMin + observadoMin);

    return {
        realMin: parseFloat(realMin.toFixed(2)),
        observadoMin: parseFloat(observadoMin.toFixed(2)),
        totalMin
    };
}

// === Cargar lista de camionetas activas ===
async function cargarCamionetas(fecha_act) {
    try {
        const res = await fetch('/app/ajax/motor2Ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                token: '<?php echo $_SESSION["user_access_key"]; ?>',
                modulo_motor2: 'obtener_trucks_activos_hoy_color',
                fecha_proc: fecha_act
            })
        });
        const data = await res.json();
        const lista = document.getElementById('camionetas-list');
        if (!Array.isArray(data.trucks)) return;

        // Limpiar lista
        lista.innerHTML = '';

        data.trucks.forEach(truck => {
            const div = document.createElement('div');
            div.className = 'camioneta-item';
            div.dataset.id = truck.truck;
            div.dataset.color = truck.color;

            // Botón de mapa integrado
            div.innerHTML = `
                <div class="truck-info">
                    <div class="dot" style="background:${truck.color || '#2196F3'};"></div>
                   <span>${truck.truck}</span>
                </div>
                <button 
                    class="btn-map" 
                    onclick="verMapa('${truck.truck}', event)"
                    title="View map for ${truck.truck}"
                >
                    🗺️
                </button>
            `;

            if (esFechaDeHoy()) {
                div.onclick = () => verEstadoCamion(truck.truck, truck.color || '#2196F3');
            } else {
                div.onclick = () => verEstadoCamion_pasado(truck.truck, truck.color || '#2196F3');
            }
            lista.appendChild(div);
        });

        // Auto-cargar primer camión
        if (data.trucks.length > 0) {
            if (esFechaDeHoy()) {
                verEstadoCamion(data.trucks[0].truck, data.trucks[0].color || '#2196F3');
            } else {
                verEstadoCamion_pasado(data.trucks[0].truck, data.trucks[0].color || '#2196F3');
            }
        }

    } catch (err) {
        document.getElementById('estado-detallado').innerHTML = `<p>❌ Error loading fleet</p>`;
    }
}

async function verEstadoCamion_pasado(vehicle_id, color, tiempo_act) {
    const detalle = document.getElementById('estado-detallado');
    detalle.innerHTML = `<p>Loading ${vehicle_id}...</p>`;

    try {
        // === 1. Obtener servicios asignados ===
        const serviciosCamion = window.serviciosData?.filter(s => s.truck === vehicle_id) || [];

        // === 2. Si está detenido, buscar cliente cercano ===
        let clienteCercano = null;
        const umbralMetros = window.APP_CONFIG?.umbral_metros || 150;

        // === 5. Determinar estado inicial ===
        let statusHtml = '';

        statusHtml = `<div class="status-box">📍 Past situation</div>`;

        // Crew
        const servicioParaCrew = clienteCercano || serviciosCamion[0] || null;
        const crew = Array.isArray(servicioParaCrew?.crew_integrantes)
            ? servicioParaCrew.crew_integrantes
            : [];
        const crewHtml = crew.map(m =>
            `<div class="crew-item">${m.responsabilidad === 'D' ? '🚚' : '🛠️'} ${m.nombre_completo}</div>`
        ).join('');

        // Servicios (solo conteo visual, sin datos)
        const serviciosHtml = serviciosCamion.length > 0
            ? serviciosCamion.map(s => {
                return `
                    <div class="servicio-hist">
                        <h4 style="
                            background: ${color};
                            color: white;
                            margin: 0;
                            padding: 6px 10px;
                            border-radius: 6px 6px 0 0;
                            font-size: 1.1em;
                            font-weight: bold;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            gap: 8px;
                        ">
                            <span>${s.cliente}</span>
                        </h4>
                        <small style="
                            display: block;
                            padding: 8px 10px;
                            border-bottom: 1px solid #eee;
                        ">${s.direccion || 'No address'}</small>
                        ${renderMotorGridGridHTML(s)}
                    </div>
                `;
            }).join('')
            : '<p style="color:#888;">There were no related services for the day.</p>';

        // === 6. Actualizar interfaz inicial ===
        detalle.innerHTML = `
            <p><small>Last record: Past situation</small></p>

            ${statusHtml}

            <p><b>Crew:</b></p>
            ${crewHtml}

            <hr>
            <h4>📋 Services of the day (${serviciosCamion.length})</h4>
            ${serviciosHtml}
        `;

        // Marcar como seleccionado
        document.querySelectorAll('.camioneta-item').forEach(el => {
            el.classList.remove('selected');
        });
        const div = document.querySelector(`.camioneta-item[data-id="${vehicle_id}"]`);
        if (div) div.classList.add('selected');

        // === INICIAR SEGUIMIENTO ACTIVO ===
        if (window.seguimientoInterval) {
            clearInterval(window.seguimientoInterval);
        }

        tiempoObservadoSegundos = 0;

        // Inicializar estado del camión
        if (!estadoCamion[vehicle_id]) {
            estadoCamion[vehicle_id] = {
                enSede: false,
                tiempoEnSede: 0,
                ultimaPosicion: { lat, lng }
            };
        }
    }
    catch (err) {
    }
}

async function obtenerDatosIniciales(vehicle_id) {
    // === 1. Obtener último punto GPS del vehículo ===
    const res = await fetch('/app/ajax/motor2Ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            token: '<?php echo $_SESSION["user_access_key"]; ?>',
            modulo_motor2: 'obtener_ultimo_punto_truck',
            truck: vehicle_id
        })
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
}

function mostrarEstadoInicial(detalle, vehicle_id, color, data) {
    const { puntos, server_time } = data.gps;
    const puntoActual = puntos[0];
    const lastRecordTime = new Date(puntoActual.timestamp);
    const serverTime = new Date(server_time);
    const diffMin = (serverTime - lastRecordTime) / (1000 * 60);

    const serviciosCamion = data.servicios || [];
    const crew = Array.isArray(data.crew) ? data.crew : [];

    const estadoParadaHtml = diffMin >= 5
        ? `<p style="color:#2E7D32; font-size:0.9em;">🟢 Stopped for ${Math.floor(diffMin)} min</p>`
        : `<p style="color:#FF8F00; font-size:0.9em;">⏩ Active or recently updated</p>`;

    detalle.innerHTML = `
        <p><small>Last record: ${formatTimeCT(lastRecordTime)}</small></p>
        <p><small>Server time: ${formatTimeCT(serverTime)}</small></p>
        ${estadoParadaHtml}

        <p style="color:#0066CC; font-size:0.9em;" id="contador-seccion">
            👁️ Watching: <span id="tiempo-observado">0m 0s</span><br>
            📊 Not reporting for: <span id="total-on-site">${Math.floor(diffMin)} min</span>
        </p>

        <!-- Status, Crew, Servicios -->
        ${renderStatusHtml(puntoActual, diffMin)}
        <p><b>Crew:</b></p>
        ${renderCrewHtml(crew)}
        <hr>
        <h4>📋 Services of the day</h4>
        ${renderServiciosHtml(serviciosCamion, color)}
    `;
}

function actualizarEstadoGPS(detalle, gpsData, vehicle_id) {
    const { puntos, server_time } = gpsData;
    const puntoActual = puntos[0];
    const lastRecordTime = new Date(puntoActual.timestamp);
    const serverTime = new Date(server_time);
    const diffMin = (serverTime - lastRecordTime) / (1000 * 60);

    const totalEl = document.getElementById('total-on-site');
    if (totalEl) {
        totalEl.textContent = `${Math.floor(diffMin)} min`;
    }

    const statusBox = document.querySelector('.status-box');
    if (statusBox && diffMin >= 5) {
        statusBox.innerHTML = '🟡 Stopped<br><small>Location not at client</small>';
        statusBox.style.background = '#FFC107';
        statusBox.style.color = 'black';
    } else if (statusBox) {
        statusBox.innerHTML = '➡️ En route';
        statusBox.style.background = '';
        statusBox.style.color = '';
    }
}

function actualizarContadoresVisuales(detalle, segundos) {
    const watchingEl = document.getElementById('tiempo-observado');
    if (watchingEl) {
        const mins = Math.floor(segundos / 60);
        const segs = segundos % 60;
        watchingEl.textContent = `${mins}m ${segs}s`;
    }
}

/**
 * Muestra el estado detallado del camión seleccionado
 * - Usa última posición GPS
 * - Evalúa si está detenido
 * - Determina estado solo por comportamiento (sin servicios)
 */
async function verEstadoCamion_propuesta(vehicle_id, color) {
    const detalle = document.getElementById('estado-detallado');
    detalle.innerHTML = `<p>Loading ${vehicle_id}...</p>`;

    // Detener seguimiento previo
    if (window.seguimientoInterval) {
        clearInterval(window.seguimientoInterval);
    }

    let tiempoObservadoSegundos = 0;

    try {
        // === 1. Carga inicial: obtener todo (GPS + servicios + crew) ===
        const data = await obtenerDatosIniciales(vehicle_id);

        if (!data || !data.gps || data.gps.cantidad === 0) {
            throw new Error('NoGPS');
        }

        // Mostrar datos iniciales
        mostrarEstadoInicial(detalle, vehicle_id, color, data);

        // Marcar como seleccionado
        marcarSeleccionado(vehicle_id);

        // === 2. Iniciar seguimiento activo (solo GPS) ===
        window.seguimientoInterval = setInterval(async () => {
            try {
                const gpsData = await obtenerUltimoPunto(vehicle_id);
                if (!gpsData || !gpsData.success || gpsData.cantidad === 0) return;

                // Actualizar solo el bloque crítico
                actualizarEstadoGPS(detalle, gpsData, vehicle_id);

                // Incrementar tiempo de observación
                tiempoObservadoSegundos += 5;
                actualizarContadoresVisuales(detalle, tiempoObservadoSegundos);

            } catch (err) {
                console.warn(`Seguimiento ${vehicle_id}:`, err.message);
            }
        }, 5000);

    } catch (err) {
        console.error(`Error in verEstadoCamion(${vehicle_id}):`, err);

        // Caso: error en GPS
        detalle.innerHTML = `
            <div class="status-box" style="background:#FF9800; color:black;">
                ⚠️ GPS signal not available
            </div>
            <p><small>Unable to determine location.</small></p>

            <p><b>Crew:</b></p>
            <i>Not assigned</i>

            <hr>
            <h4>📋 Services of the day (?)</h4>
            <p style="color:#888;">Services assigned: unknown</p>
        `;

        document.querySelectorAll('.camioneta-item').forEach(el => {
            el.classList.remove('selected');
        });
        const div = document.querySelector(`.camioneta-item[data-id="${vehicle_id}"]`);
        if (div) div.classList.add('selected');
    }
}

async function verEstadoCamion(vehicle_id, color, tiempo_act) {
    const detalle = document.getElementById('estado-detallado');
    detalle.innerHTML = `<p>Loading ${vehicle_id}...</p>`;

    // Detener seguimiento previo
    if (window.seguimientoInterval) {
        clearInterval(window.seguimientoInterval);
        window.seguimientoInterval = null;
    }

    let tiempoObservadoSegundos = 0;

    try {
        const data = await obtenerDatosIniciales(vehicle_id);

        let estadoParadaHtml = '';
        let estaDetenido = '';
        let lat = 0;
        let lng = 0;
        let lastRecordTime = "0000-00-00 00:00:00";
        let diffMs = null;
        let diffMin = 0;

        if (data.cantidad > 0) {
            const { puntos, server_time } = data;
            const puntoActual = puntos[0];
            const puntoAnterior = puntos[1] || null;

            // Validar coordenadas
            lat = parseFloat(puntoActual.lat);
            lng = parseFloat(puntoActual.lng);
            if (isNaN(lat) || isNaN(lng)) throw new Error('InvalidGPS');

            // === 2. Convertir tiempos a objetos Date ===
            const serverTime = new Date(server_time);
            lastRecordTime = new Date(puntoActual.timestamp);

            if (isNaN(lastRecordTime.getTime()) || isNaN(serverTime.getTime())) {
                throw new Error('InvalidTimestamp');
            }

            // Calcular diferencia en minutos

            diffMs = serverTime - lastRecordTime;
            diffMin = diffMs / (1000 * 60);

            // Formatear hora legible
            const formatTime = (date) => {
                const h = String(date.getHours()).padStart(2, '0');
                const m = String(date.getMinutes()).padStart(2, '0');
                const s = String(date.getSeconds()).padStart(2, '0');
                return `${h}:${m}:${s}`;
            };

            // === 3. Evaluar si está detenido ===
            const umbralMinutos = window.APP_CONFIG?.umbral_minutos || 5;
            const estaDetenidoPorTiempo = diffMin >= umbralMinutos;

            const estaDetenidoPorMovimiento = puntoAnterior &&
                parseFloat(puntoActual.speed) <= 1 &&
                Math.abs(parseFloat(puntoActual.course) - parseFloat(puntoAnterior.course)) < window.APP_CONFIG.umbral_course;

            estaDetenido = estaDetenidoPorTiempo || estaDetenidoPorMovimiento;

            if (estaDetenido) {
                estadoParadaHtml = `
                        <p style="color:#2E7D32; font-size:0.9em;">
                            🟢 Stopped for ${Math.floor(diffMin)} min<br>
                            <small>Last signal: ${formatTimeCT(lastRecordTime)}</small>
                        </p>
                    `;
            } else {
                estadoParadaHtml = `
                        <p style="color:#FF8F00; font-size:0.9em;">
                            ⏩ Active or recently updated
                        </p>
                    `;
            }
        } else {
            const server_time = data.server_time;
            const serverTime = new Date(server_time);
            estaDetenido = true;
        }
        //}

        // === 4. Obtener servicios asignados ===
        const serviciosCamion = window.serviciosData?.filter(s => s.truck === vehicle_id) || [];
        // === 5. Si está detenido, buscar cliente cercano ===
        let clienteCercano = null;
        const umbralMetros = window.APP_CONFIG?.umbral_metros || 150;

        if (estaDetenido && serviciosCamion.length > 0) {
            let distMinima = Infinity;
            for (const servicio of serviciosCamion) {


                if (typeof servicio.lat !== 'number') continue;
                const dist = calcularDistanciaMetros(lat, lng, servicio.lat, servicio.lng);
                if (dist <= umbralMetros && dist < distMinima) {
                    distMinima = dist;
                    clienteCercano = servicio;
                }
            }
        }

        // === 6. Evaluar si está en sede ===
        const distToHQ = calcularDistanciaMetros(lat, lng, HQ.lat, HQ.lng);

        const estaEnSedeInicial = distToHQ <= window.APP_CONFIG.umbral_metros;

        // === 5. Determinar estado inicial ===
        let statusHtml = '';

        if (estaEnSedeInicial) {
            statusHtml = `<div class="status-box">📍 Near headquarters</div>`;
        } else if (estaDetenido) {
            statusHtml = `<div class="status-box" style="background:#FFC107; color:black;">🟡 Stopped<br><small>Location not at client</small></div>`;
        } else {
            statusHtml = `<div class="status-box">➡️ En route</div>`;
        }

        // Crew
        const servicioParaCrew = clienteCercano || serviciosCamion[0] || null;
        const crew = Array.isArray(servicioParaCrew?.crew_integrantes)
            ? servicioParaCrew.crew_integrantes
            : [];
        const crewHtml = crew.map(m =>
            `<div class="crew-item">${m.responsabilidad === 'D' ? '🚚' : '🛠️'} ${m.nombre_completo}</div>`
        ).join('');

        // Servicios (solo conteo visual, sin datos)
        const serviciosHtml = serviciosCamion.length > 0
            ? serviciosCamion.map(s => {
                const clienteEsc = s.cliente.replace(/'/g, "\\'");
                const direccionEsc = (s.direccion || 'No address').replace(/'/g, "\\'");
                return `
                    <div class="servicio-boton" onclick="abrirModalServicio(${s.id_servicio}, '${clienteEsc}', '${direccionEsc}')">
                        <h4 style="
                            background: ${color};
                            color: white;
                            margin: 0;
                            padding: 6px 10px;
                            border-radius: 6px 6px 0 0;
                            font-size: 1.1em;
                            font-weight: bold;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            gap: 8px;
                        ">
                            <span>${s.cliente}</span>
                            <span style="font-size: 1.2em; opacity: 0.9;">⚙️</span>
                        </h4>
                        <small style="
                            display: block;
                            padding: 8px 10px;
                            border-bottom: 1px solid #eee;
                        ">${s.direccion || 'No address'}</small>
                        ${renderMotorGridGridHTML(s)}
                    </div>
                `;
            }).join('')
            : '<p style="color:#888;">No services scheduled for today.</p>';

        // === 6. Actualizar interfaz inicial ===
        detalle.innerHTML = `
            <p><small>Last record: ${formatTimeCT(lastRecordTime) || '—'}</small></p>
            ${estadoParadaHtml || ''}

            <!-- Contadores dinámicos -->
            <p style="color:#0066CC; font-size:0.9em;" id="contador-seccion">
                👁️ Watching: <span id="tiempo-observado">0m 0s</span><br>
                📊 Total on site: <span id="total-on-site">${Math.floor(diffMin)} min</span>
            </p>

            ${statusHtml}

            <p><b>Crew:</b></p>
            ${crewHtml}

            <hr>
            <h4>📋 Services of the day (?)</h4>
            ${serviciosHtml}
        `;

        // Marcar como seleccionado
        document.querySelectorAll('.camioneta-item').forEach(el => {
            el.classList.remove('selected');
        });

        const div = document.querySelector(`.camioneta-item[data-id="${vehicle_id}"]`);

        if (div) div.classList.add('selected');

        // === INICIAR SEGUIMIENTO ACTIVO ===
        if (window.seguimientoInterval) {
            clearInterval(window.seguimientoInterval);
        }

        tiempoObservadoSegundos = 0;

        // Inicializar estado del camión
        if (!estadoCamion[vehicle_id]) {
            estadoCamion[vehicle_id] = {
                enSede: false,
                tiempoEnSede: 0,
                ultimaPosicion: { lat, lng }
            };
        }

        const estado = estadoCamion[vehicle_id];
        const UMBRAL_SEGUNDOS_SALIDA = 15 * 60; // 15 minutos

        window.seguimientoInterval = setInterval(async () => {
            //console.log('✅ [Seguimiento] Intervalo activo para:', vehicle_id);
            try {
                const res = await fetch('/app/ajax/motor2Ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        token: '<?php echo $_SESSION["user_access_key"]; ?>',
                        modulo_motor2: 'obtener_ultimo_punto_truck',
                        truck: vehicle_id
                    })
                });

                if (res.status === 204 || !res.ok) {
                    console.warn('❌ Respuesta no válida:', res.status);
                    return;
                }

                const data = await res.json();
                //console.log('📡 Datos recibidos:', data); // 👈 Ver aquí si hay respuesta

                if (!data?.success || !Array.isArray(data.puntos) || data.puntos.length === 0) {
                    console.warn('⚠️ Sin puntos GPS:', data);
                    return;
                }

                const pto = data.puntos[0];
                const lat = parseFloat(pto.lat);
                const lng = parseFloat(pto.lng);
                if (isNaN(lat) || isNaN(lng)) return;

                const serverTime = new Date(data.server_time);
                const lastRecordTime = new Date(pto.timestamp);
                if (isNaN(lastRecordTime.getTime()) || isNaN(serverTime.getTime())) return;

                // Evaluar movimiento
                const distMov = calcularDistanciaMetros(estado.ultimaPosicion.lat, estado.ultimaPosicion.lng, lat, lng);
                const haMovido = distMov > 10;
                const speed = pto.speed > 1;

                // Actualizar última posición
                estado.ultimaPosicion = { lat, lng };

                // Evaluar proximidad a sede
                const distToHQ = calcularDistanciaMetros(lat, lng, HQ.lat, HQ.lng);
                const enSedeAhora = distToHQ <= window.APP_CONFIG.umbral_metros;

                if (enSedeAhora) {
                    if (!estado.enSede) {
                        estado.enSede = true;
                        estado.tiempoEnSede = 0;
                    } else {
                        estado.tiempoEnSede += 5;
                    }
                } else {
                    estado.enSede = false;
                    estado.tiempoEnSede = 0;
                }

                // Actualizar contadores
                tiempoObservadoSegundos += 1;
                const diffMs = serverTime - lastRecordTime;
                const diffMin = diffMs / (1000 * 60);

                const watchingEl = document.getElementById('tiempo-observado');
                const totalEl = document.getElementById('total-on-site');
                const contadorSeccion = document.getElementById('contador-seccion');
                const statusBox = document.querySelector('.status-box');

                if (!statusBox) return;

                // === DECISIÓN SOLO POR VEHÍCULO ===
                if (estado.enSede && estado.tiempoEnSede >= UMBRAL_SEGUNDOS_SALIDA) {
                    // ✅ Fin de jornada
                    statusBox.innerHTML = '✅ End of day — Stationed at headquarters';
                    statusBox.style.background = '#4CAF50';
                    statusBox.style.color = 'white';
                    if (contadorSeccion) contadorSeccion.style.display = 'none';
                }
                else if (enSedeAhora) {
                    // 📍 Aún no termina
                    statusBox.innerHTML = '📍 Near headquarters';
                    if (contadorSeccion) contadorSeccion.style.display = 'block';
                }
                else if (haMovido || speed) {
                    // 🚗 Se movió → reanudar
                    estado.enSede = false;
                    estado.tiempoEnSede = 0;
                    if (statusBox.innerText.includes('End of day')) {
                        statusBox.innerHTML = '➡️ En route';
                        statusBox.style.background = '';
                        statusBox.style.color = '';
                    }
                    if (contadorSeccion) contadorSeccion.style.display = 'block';
                }

                const totalMinutosEnSitio = diffMin + (tiempoObservadoSegundos / 60);

                // Actualizar contadores visuales
                if (watchingEl && contadorSeccion && contadorSeccion.style.display !== 'none') {
                    const mins = Math.floor(tiempoObservadoSegundos / 60);
                    const segs = tiempoObservadoSegundos % 60;
                    watchingEl.textContent = `${mins}m ${segs}s`;
                    if (totalEl) totalEl.textContent = `${Math.floor(totalMinutosEnSitio)} min`;
                }
                // Tiempo total estimado en sitio = tiempo desde último GPS + tiempo que llevas observando
            } catch (err) {
                console.warn(`Seguimiento ${vehicle_id}:`, err.message);
            }
        }, 1000);
    }
    catch (err) {
        console.error(`Error in verEstadoCamion(${vehicle_id}):`, err);

        // Caso: error en GPS
        detalle.innerHTML = `
            <div class="status-box" style="background:#FF9800; color:black;">
                ⚠️ GPS signal not available
            </div>
            <p><small>Unable to determine location.</small></p>

            <p><b>Crew:</b></p>
            <i>Not assigned</i>

            <hr>
            <h4>📋 Services of the day (?)</h4>
            <p style="color:#888;">Services assigned: unknown</p>
        `;

        document.querySelectorAll('.camioneta-item').forEach(el => {
            el.classList.remove('selected');
        });
        const div = document.querySelector(`.camioneta-item[data-id="${vehicle_id}"]`);
        if (div) div.classList.add('selected');
    }
}

/**
 * Formatea a HH:mm en CT desde string ISO o objeto Date
 * Acepta: string (ISO o con espacio), Date, null, undefined
 */
function formatTimeCT(input) {
    // Casos nulos o vacíos
    if (!input) return null;

    let date = null;

    // Si es string, intentar parsear
    if (typeof input === 'string') {
        const clean = input.trim().replace(' ', 'T');
        if (clean === '' || clean === '0000-00-00 00:00:00' || clean === '0000-00-00T00:00:00') {
            return null;
        }
        date = new Date(clean);
    }
    // Si es objeto Date
    else if (input instanceof Date) {
        date = input;
    }
    // Cualquier otro tipo (array, object, etc.)
    else {
        return null;
    }

    // Validar que sea una fecha válida
    if (isNaN(date.getTime())) {
        return null;
    }

    try {
        return date.toLocaleTimeString([], {
            timeZone: 'America/Chicago',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    } catch (e) {
        return null;
    }
}

/**
 * Calcula duración en HH:mm:ss entre dos timestamps (ISO o similares)
 * Usa CT y valida entradas
 */
function calcularDuracion_fin(inicio, fin) {
    if (!inicio) return null;

    const parseDate = (iso) => {
        if (!iso || typeof iso !== 'string' || iso === '' || iso === '0000-00-00 00:00:00') return null;
        const clean = iso.trim().replace(' ', 'T');
        const date = new Date(clean);
        return isNaN(date.getTime()) ? null : date;
    };

    const start = parseDate(inicio);
    const end = fin ? parseDate(fin) : new Date(); // ahora

    if (!start) return null;
    if (!end) return null;

    const diffSec = Math.floor((end - start) / 1000);
    if (diffSec < 0) return '00:00:00';

    const h = String(Math.floor(diffSec / 3600)).padStart(2, '0');
    const m = String(Math.floor((diffSec % 3600) / 60)).padStart(2, '0');
    const s = String(diffSec % 60).padStart(2, '0');

    return `${h}:${m}:${s}`;
}

// ======================
// renderMotorGridGridHTML - Cuadrícula 2x2 con barra de título azul marino
// ======================
function renderMotorGridGridHTML(servicio) {
    const calcularDuracion = calcularDuracion_fin(servicio.hora_aviso_usuario, servicio.hora_finalizado);

    const getSupervisionStatus = () => {
        if (servicio.supervision_inicio) {
            return { text: '✔️ Validated', time: formatTimeCT(servicio.supervision_inicio) };
        } else if (servicio.estado === 'culminado' && !haExpiradoPlazo(servicio.fin_servicio)) {
            return { text: '🔍 Awaiting supervision', time: '—' };
        } else if (servicio.estado === 'culminado') {
            return { text: '⚠️ Not supervised', time: '—' };
        }
        return { text: 'Waiting for data', time: '—' };
    };

    const sup = getSupervisionStatus();

    const headerBg = '#001F3F';   // Azul marino
    const headerColor = '#FFFFFF';
    let html = ``;

    html = `
        <div class="motor-grid-container grid_motores">
            <!-- Motor 1 -->
            <div class="grid_motores_1" data-motor="1" data-servicio="${servicio.id_servicio}" style="background: #f1f8ff; border: 1px solid #bbdefb; border-radius: 6px; padding: 0; min-height: 70px; overflow: hidden;">
                <div class="motor-header" style="background: ${headerBg}; color: ${headerColor}; padding: 4px 6px; font-size: 0.9em; text-align: center; font-weight: bold;">🔧 Motor 1 - Office</div>`;
    if (servicio.hora_aviso_usuario !== null || servicio.hora_finalizado !== null) {
        html += `
                <div class="motor-body" style="padding: 6px;">
                    <div style="line-height:1.4; font-size:0.9em;">`;
        if (servicio.hora_aviso_usuario !== null) {
            html += `
                        <div><strong>Start: </strong> ${formatTimeCT(servicio.hora_aviso_usuario) || '—'}</div>`;
        } else {
            html += `
                        <div class="tit-aling-row"><strong>Start: </strong><div class="tit-det"> Missing Service Startup Charge</div></div>`;
        }
        if (servicio.hora_finalizado !== null) {
            html += `
                        <div><strong>End: </strong> ${formatTimeCT(servicio.hora_finalizado) || '—'}</div>`;
        } else {
            html += `
                        <div class="tit-aling-row"><strong>End: </strong><div class="tit-det"> End of Service details were not uploaded</div></div>`;
        }
        if (servicio.hora_aviso_usuario !== null && servicio.hora_finalizado !== null) {
            html += `
                        <div><strong>Duration: </strong> ${calcularDuracion_fin(servicio.hora_aviso_usuario, servicio.hora_finalizado)}</div>`;
        } else {
            html += `
                        <div class="tit-aling-row"><strong>Duration: </strong><div class="tit-det"> Missing data to calculate Time</div></div>`;
        }
        html += `
                    </div>`;
    } else {
        html += `
                <div style="text-align: center; color: #777; font-weight: normal; font-size: 0.9em; margin: 4px 0;">Waiting for data to calculate`;
    }
    html += `
                </div>
            </div>`;
    html += `
            <!-- Motor 2 -->
            <div class="grid_motores_2" data-motor="2" data-servicio="${servicio.id_servicio}" style="background: #f1f8e9; border: 1px solid #c5e1a5; border-radius: 6px; padding: 0; min-height: 70px; overflow: hidden;">
                <div class="motor-header" style="background: ${headerBg}; color: ${headerColor}; padding: 4px 6px; font-size: 0.9em; text-align: center; font-weight: bold;">📡 Motor 2 - GPS + Geo</div>`;
    if (servicio.hora_inicio_gps !== null || servicio.hora_fin_gps !== null) {
        html += `
                <div class="motor-body" style="padding: 6px;">
                    <div style="line-height:1.4; font-size:0.9em;">`;
        if (servicio.hora_inicio_gps !== null) {
            html += `
                        <div><strong>Start: </strong> ${formatTimeCT(servicio.hora_inicio_gps) || '—'}</div>`;
        } else {
            html += `
                        <div class="tit-aling-row"><strong>Start: </strong><div class="tit-det"> Missing Service Startup Charge</div></div>`;
        }
        if (servicio.hora_fin_gps !== null) {
            html += `
                        <div><strong>End: </strong> ${formatTimeCT(servicio.hora_fin_gps) || '—'}</div>`;
        } else {
            html += `
                        <div class="tit-aling-row"><strong>End: </strong><div class="tit-det"> End of Service details were not uploaded</div></div>`;
        }
        if (servicio.hora_inicio_gps !== null && servicio.hora_fin_gps !== null) {
            html += `
                        <div><strong>Dur:</strong> ${calcularDuracion_fin(servicio.hora_inicio_gps, servicio.hora_fin_gps)}</div>`;
        } else {
            html += `
                        <div class="tit-aling-row"><strong>Duration: </strong><div class="tit-det"> Missing data to calculate Time</div></div>`;
        }
        html += `
                    </div>`;
    } else {
        html += `
                <div style="text-align: center; color: #777; font-weight: normal; font-size: 0.9em; margin: 4px 0;">Waiting for data to calculate`
    }
        html += `
                </div>            
            </div>

            <!-- Motor 3 -->
            <div class="grid_motores_3" data-motor="3" data-servicio="${servicio.id_servicio}" style="background: #fff8e1; border: 1px solid #ffe082; border-radius: 6px; padding: 0; min-height: 70px; overflow: hidden;">
                <div class="motor-header" style="background: ${headerBg}; color: ${headerColor}; padding: 4px 6px; font-size: 0.9em; text-align: center; font-weight: bold;">⚙️ Motor 3 - Driver</div>
                <div class="motor-body" style="padding: 6px;">
                    <div style="text-align: center; color: #777; font-weight: normal; font-size: 0.9em; margin: 4px 0;">Waiting for data to calculate</div>
                </div>
            </div>

            <!-- Motor 4 -->
            <div class="grid_motores_4" data-motor="4" data-servicio="${servicio.id_servicio}" style="background: #ede7f6; border: 1px solid #9fa8da; border-radius: 6px; padding: 0; min-height: 70px; overflow: hidden;">
                <div class="motor-header" style="background: ${headerBg}; color: ${headerColor}; padding: 4px 6px; font-size: 0.9em; text-align: center; font-weight: bold;">👁️ Motor 4 - Supervisor</div>
                <div class="motor-body" style="padding: 6px;">
                    <div style="line-height:1.4; font-size:0.9em;">
                        <div><strong>Status:</strong> 
                            <span style="font-weight:bold; color:${sup.text.includes('Validated') ? '#2E7D32' :
            sup.text.includes('Awaiting') ? '#FF8F00' :
                sup.text.includes('Not supervised') ? '#C62828' : '#777'
        };">${sup.text}</span>
                        </div>
                        ${sup.time !== '—' ? `<div><strong>Hour:</strong> ${sup.time}</div>` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
    console.log(html);
    return html;
}

function verMapa(vehicle_id, event) {
    event.stopPropagation();

    const modal = document.getElementById('modal-mapa');
    const titulo = document.getElementById('titulo-mapa');
    titulo.textContent = `📍 ${vehicle_id} - Map`;

    modal.style.display = 'flex';

    // Esperar a que el modal sea visible antes de cargar el mapa
    setTimeout(() => {
        inicializarMapa(vehicle_id);
    }, 100);
}

function cerrarMapa() {
    const modal = document.getElementById('modal-mapa');
    modal.style.display = 'none';

    // Limpiar mapa para liberar memoria
    if (window.mapaCamion) {
        window.mapaCamion.off();
        window.mapaCamion.remove();
        window.mapaCamion = null;
    }
}

function agregarClientesAlMapa() {
    if (!window.mapaCamion || !window.serviciosData) return;

    // Limpiar capa anterior
    if (layerClientes) {
        window.mapaCamion.removeLayer(layerClientes);
    }

    layerClientes = L.layerGroup().addTo(window.mapaCamion);

    const servicios = window.serviciosData.filter(s => s.truck === vehicle_id_actual);

    servicios.forEach(servicio => {
        let lat = null;
        let lng = null;
        if (typeof servicio.lat !== 'number') {
            lat = parseFloat(servicio.lat);
            lng = parseFloat(servicio.lng);
        } else {
            lat = servicio.lat;
            lng = servicio.lng;
        }
        if (lat === 0 || lat === null) return;

        L.circleMarker([lat, lng], {
            radius: 8,
            color: '#FF5722',
            fillColor: '#FFC107',
            fillOpacity: 0.8,
            weight: 2
        }).addTo(layerClientes)
            .bindPopup(`
            <b>Cliente:</b> ${servicio.cliente}<br>
            <b>Dirección:</b> ${servicio.direccion}
        `);
    });
}

function iniciarReproduccion() {
    if (currentPointIndex >= rutaPoints.length || isPlaying) return;

    document.getElementById('btn-play-pause').textContent = '⏸️ Pause';

    playbackInterval = setInterval(() => {
        const point = rutaPoints[currentPointIndex];

        // Actualizar marcador del vehículo
        if (!markerVehiculo) {
            markerVehiculo = L.marker([point.lat, point.lng], {
                icon: L.divIcon({
                    html: '🚛',
                    className: 'custom-marker',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                })
            }).addTo(window.mapaCamion);
        } else {
            markerVehiculo.setLatLng([point.lat, point.lng]);
        }

        // Centrar en el vehículo (opcional)
        // window.mapaCamion.panTo([point.lat, point.lng]);

        // Actualizar tiempo
        const time = new Date(point.timestamp).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });
        document.getElementById('tiempo-actual').textContent = time;

        // Avanzar
        currentPointIndex++;

        if (currentPointIndex >= rutaPoints.length) {
            detenerReproduccion();
        }
    }, 200); // 200ms entre puntos → ajustable
}

function detenerReproduccion() {
    if (playbackInterval) {
        clearInterval(playbackInterval);
        playbackInterval = null;
    }
    isPlaying = false;
    document.getElementById('btn-play-pause').textContent = '▶️ Play';
}

function togglePlayPause() {
    if (isPlaying) {
        detenerReproduccion();
    } else {
        isPlaying = true;
        if (currentPointIndex >= rutaPoints.length) {
            resetRuta();
        }
        iniciarReproduccion();
    }
}

function resetRuta() {
    detenerReproduccion();
    currentPointIndex = 0;
    document.getElementById('tiempo-actual').textContent = '--:--';
    if (markerVehiculo) {
        window.mapaCamion.removeLayer(markerVehiculo);
        markerVehiculo = null;
    }
}

async function inicializarMapa(vehicle_id) {
    console.log("Inicia inicializarMapa");
    vehicle_id_actual = vehicle_id; // ← Para uso en otras funciones

    if (window.mapaCamion) {
        window.mapaCamion.off();
        window.mapaCamion.remove();
    }

    try {
        const puntos = await obtenerRutaDelDia(vehicle_id, selectedDate);
        rutaPoints = puntos;

        if (puntos.length === 0) {
            alert('No hay datos GPS para esta fecha.');
            cerrarMapa();
            return;
        }

        const centro = puntos[Math.floor(puntos.length / 2)];
        window.mapaCamion = L.map('mapa-container').setView([centro.lat, centro.lng], 13);

        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri'
        }).addTo(window.mapaCamion);

        // Dibujar ruta completa (línea tenue)
        if (puntos.length > 1) {
            L.polyline(puntos.map(p => [p.lat, p.lng]), {
                color: '#1976D2',
                weight: 3,
                opacity: 0.5
            }).addTo(window.mapaCamion);
        }

        // Agregar clientes
        console.log("Antes de agregar Clientes");
        agregarClientesAlMapa();

        // Iniciar en modo pausa
        resetRuta();
        document.getElementById('tiempo-actual').textContent =
            new Date(puntos[0].timestamp).toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });

    } catch (err) {
        console.error('Error:', err);
        alert('No se pudo cargar la ruta.');
    }
}

async function inicializarMapa_anterior(vehicle_id) {
    vehicle_id_actual = vehicle_id; // ← Para uso en otras funciones

    // Limpiar mapa anterior si existe
    if (window.mapaCamion) {
        window.mapaCamion.off();
        window.mapaCamion.remove();
    }

    try {
        let lat, lng, lastTime;

        // === 1. Obtener RUTA completa del día ===
        const puntos = await obtenerRutaDelDia(vehicle_id, selectedDate);
        rutaPoints = puntos; // ← Guardar globalmente

        if (puntos.length > 0) {
            // Usar último punto como posición actual
            const ultimo = puntos[puntos.length - 1];
            lat = ultimo.lat;
            lng = ultimo.lng;
            lastTime = new Date(ultimo.timestamp).toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
        } else {
            // Fallback: sede
            lat = HQ.lat;
            lng = HQ.lng;
            lastTime = 'No data';
        }

        // === 2. Crear mapa ===
        window.mapaCamion = L.map('mapa-container').setView([lat, lng], 14);

        // Capa base (ESRI)
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri'
        }).addTo(window.mapaCamion);

        // === 3. Dibujar ruta del día ===
        if (puntos.length > 1) {
            const rutaLatLngs = puntos.map(p => [p.lat, p.lng]);

            L.polyline(rutaLatLngs, {
                color: '#1976D2',
                weight: 5,
                opacity: 0.8,
                lineCap: 'round'
            }).addTo(window.mapaCamion);

            // Marcador del inicio (primer punto)
            L.circleMarker([puntos[0].lat, puntos[0].lng], {
                radius: 6,
                color: '#FF8F00',
                fillColor: '#FFC107',
                fillOpacity: 1,
                weight: 2
            }).addTo(window.mapaCamion)
                .bindPopup('Inicio de jornada');

            // Marcador del final (último punto)
            const ultimo = puntos[puntos.length - 1];
            L.circleMarker([ultimo.lat, ultimo.lng], {
                radius: 6,
                color: '#D32F2F',
                fillColor: '#F44336',
                fillOpacity: 1,
                weight: 2
            }).addTo(window.mapaCamion)
                .bindPopup('Última posición');
        }

        // === 4. Marcador del vehículo (si hay datos)
        if (puntos.length > 0) {
            const ultimo = puntos[puntos.length - 1];
            const popupHtml = `
                <b>${vehicle_id}</b><br>
                🕒 Último registro: ${lastTime}<br>
                📍 ${ultimo.lat.toFixed(6)}, ${ultimo.lng.toFixed(6)}<br>
                ⚡ Velocidad: ${ultimo.speed} km/h
            `;

            L.marker([ultimo.lat, ultimo.lng], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="
                        background: #1976D2;
                        color: white;
                        width: 30px;
                        height: 30px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-weight: bold;
                        box-shadow: 0 0 5px rgba(0,0,0,0.3);">
                        🚛
                    </div>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                })
            }).addTo(window.mapaCamion)
                .bindPopup(popupHtml);
        }

        // === 5. Sede central ===
        L.marker([HQ.lat, HQ.lng], {
            icon: L.divIcon({
                className: 'hq-marker',
                html: '🏠',
                iconSize: [25, 25],
                iconAnchor: [12, 12]
            })
        }).addTo(window.mapaCamion)
            .bindPopup('Sede Central');

        // Centrar y ajustar vista
        if (puntos.length > 0) {
            const bounds = L.latLngBounds(puntos.map(p => [p.lat, p.lng]));
            window.mapaCamion.fitBounds(bounds, { padding: [50, 50] });
        } else {
            window.mapaCamion.setView([lat, lng], 15);
        }

    } catch (err) {
        console.error('Error al cargar mapa:', err);
        alert('No se pudo cargar el mapa. Intenta de nuevo.');
    }
}

/**
 * Obtiene todos los puntos GPS de un camión en una fecha específica
 */
async function obtenerRutaDelDia(vehicle_id, fecha) {
    try {
        const res = await fetch('/app/ajax/motor2Ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                token: '<?php echo $_SESSION["user_access_key"]; ?>',
                modulo_motor2: 'obtener_historico_bd',
                vehicle_id: vehicle_id,
                from_time: fecha,    // "YYYY-MM-DD"
                to_time: fecha
            })
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (data?.success && Array.isArray(data.historial)) {
            return data.historial.map(p => ({
                lat: parseFloat(p.lat),
                lng: parseFloat(p.lng),
                timestamp: p.timestamp,
                speed: p.speed,
                course: p.course
            })).filter(p => !isNaN(p.lat) && !isNaN(p.lng));
        }

        return [];
    } catch (err) {
        console.error('Error al cargar ruta:', err);
        return [];
    }
}

// Función auxiliar
function haExpiradoPlazo(fechaFin) {
    if (!fechaFin) return true;
    const fin = new Date(fechaFin);
    const ahora = dia_hoy;
    const limite = new Date(fin.getTime() + (2 * 60 * 60 * 1000)); // +2 horas
    return ahora > limite;
}

// === Otras funciones ===
function actualizarEtiquetaFecha(total) {
    const label = document.getElementById('fleet-date-label');
    if (!label) return;

    if (isToday(selectedDate)) {
        label.textContent = `Active Fleet Today Total Services: (${total})`;
    } else {
        label.textContent = `Active Fleet ${selectedDate} Total Services: (${total})`;
    }
}

function isToday(date) {
    // Si `date` es Date → convertir a string YYYY-MM-DD
    const dateString = date instanceof Date
        ? date.toISOString().split('T')[0]
        : date;

    return dateString === dia_hoy;
}

function formatDateForInput(date) {
    return date.toISOString().split('T')[0];
}

function cerrarModalServicio() {
    const modal = document.getElementById('modal-servicio');
    if (modal) modal.remove();
}

function abrirModalServicio(id_servicio, cliente, direccion) {
    const modalHtml = `
        <div id="modal-servicio" style="
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); display: flex; justify-content: center;
            align-items: center; z-index: 9999; padding: 20px; box-sizing: border-box;
        ">
            <div class="modal-content" style="
                background: white; padding: 20px; border-radius: 12px;
                width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto;
                position: relative; box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                text-align: center; font-family: -apple-system, sans-serif;
            ">
                <h3 style="margin: 0; padding: 16px; font-size: 1.2em; font-weight: 600; border-bottom: 1px solid #eee;">🔧 Service Options</h3>
                <p><strong>${cliente}</strong></p>
                <p style="font-size:0.9em; color:#555;">${direccion}</p>
                <button onclick="cerrarModalServicio()" style="
                    position: absolute; top: 12px; right: 12px; width: 30px; height: 30px;
                    background: #f1f1f1; border: none; border-radius: 50%; font-size: 1.4em;
                    line-height: 1; color: #999; cursor: pointer; display: flex;
                    align-items: center; justify-content: center; font-weight: bold;
                " title="Close">×</button>
                <button onclick="reprogramarServicio(${id_servicio})" style="
                    background:#FF9800; color:white; border:none; padding:12px 20px;
                    margin:10px 5px; border-radius:6px; font-size:1em; cursor:pointer; width:100%;
                ">🔄 Reschedule</button>
                <button onclick="cancelarServicio(${id_servicio})" style="
                    background:#f44336; color:white; border:none; padding:12px 20px;
                    margin:10px 5px; border-radius:6px; font-size:1em; cursor:pointer; width:100%;
                ">❌ Cancel</button>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function reprogramarServicio(id_servicio) {
    alert(`Reschedule service ${id_servicio}`);
    cerrarModalServicio();
}

function cancelarServicio(id_servicio) {
    if (confirm(`Cancel service ${id_servicio}?`)) {
        alert(`Service ${id_servicio} canceled`);
        cerrarModalServicio();
    }
}

function calcularDistanciaMetros(lat1, lng1, lat2, lng2) {
    const R = 6371e3;
    const φ1 = lat1 * Math.PI / 180;
    const φ2 = lat2 * Math.PI / 180;
    const Δφ = (lat2 - lat1) * Math.PI / 180;
    const Δλ = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) + Math.cos(φ1) * Math.cos(φ2) * Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}


function ajustarAlturaContainer() {
    const container = document.querySelector('.container');
    if (!container) return;
    const alturaDisponible = window.innerHeight;
    const headerHeight = document.querySelector('.mobile-header')?.offsetHeight || 60;
    container.style.height = `${alturaDisponible - headerHeight}px`;
}

function getLocalDateISO() {
    const now = dia_hoy;
    // Convertir a America/Chicago (soporta DST automáticamente)
    const options = {
        timeZone: 'America/Chicago',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    };
    const parts = new Intl.DateTimeFormat('sv-SE', options).formatToParts(now);
    const year = parts.find(p => p.type === 'year').value;
    const month = parts.find(p => p.type === 'month').value;
    const day = parts.find(p => p.type === 'day').value;
    return `${year}-${month}-${day}`; // Ej: "2025-10-03"
}

function formatTimeInCT(input) {
    // Convertir entrada a objeto Date válido
    let date;

    if (input instanceof Date) {
        date = input;
    } else if (typeof input === 'string' && /^\d{4}-\d{2}-\d{2}/.test(input)) {
        // Si es string "YYYY-MM-DD", crear fecha en CT a las 00:00
        date = new Date(input + 'T00:00:00');
    } else if (typeof input === 'string') {
        // Intentar parsear cualquier otro formato
        date = new Date(input);
    } else {
        return '—';
    }

    // Validar que sea una fecha válida
    if (isNaN(date.getTime())) {
        console.warn('Invalid date:', input);
        return '—';
    }

    const options = {
        timeZone: 'America/Chicago',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    };

    try {
        return new Intl.DateTimeFormat('es-ES', options).format(date);
    } catch (e) {
        console.error('Error formatting date:', e);
        return '—';
    }
}

window.addEventListener('load', ajustarAlturaContainer);
window.addEventListener('resize', () => setTimeout(ajustarAlturaContainer, 150));
window.addEventListener('orientationchange', () => setTimeout(ajustarAlturaContainer, 150));

// === Inicializar serviciosData ===
window.serviciosData = [];
async function cargarServiciosData() {
    try {
        const fechaEnviar = selectedDate;

        const res = await fetch('/app/ajax/mobileAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                token: '<?php echo $_SESSION["user_access_key"]; ?>',
                modulo_mobiles: 'listar_para_geoferencia',
                fecha: fechaEnviar 
            })
        });
        const data = await res.json();
        window.serviciosData = Array.isArray(data) ? data : [];

        // 🔢 Contar el número de servicios cargados (para hoy)
        const total_servicios = window.serviciosData.length;

        // 📣 Llamar a la función que actualiza la etiqueta
        actualizarEtiquetaFecha(total_servicios);
    } catch (err) {
        console.warn('Failed to load serviciosData');

        // ⚠️ Aseguramos que también en error se llame, con 0
        actualizarEtiquetaFecha(0);
    }
}

function esFechaDeHoy() {
    return selectedDate === dia_hoy;
}

document.getElementById('calendar-input').addEventListener('change', function () {
    if (this.value) {
        selectedDate = this.value;
        const lista_columno2 = document.getElementById('camionetas-list');
        lista_columno2.innerHTML = ``

        cargarServiciosData();
        cargarCamionetas(selectedDate);

        let tiempo_act = false;
        if (esFechaDeHoy()) {
            tiempo_act = true;
            const active = document.querySelector('.camioneta-item.selected');
            if (active) verEstadoCamion(active.dataset.id, active.dataset.color, tiempo_act);
        } else {
            tiempo_act = false;
            const active = document.querySelector('.camioneta-item.selected');
            if (active) verEstadoCamion_pasado(active.dataset.id, active.dataset.color);
        }

    }
});

// === Iniciar al cargar ===
window.addEventListener('configListo', () => { 
    cargarServiciosData();
    cargarCamionetas(selectedDate);

    const active = document.querySelector('.camioneta-item.selected');
    if (active) verEstadoCamion(active.dataset.id, active.dataset.color, true);
});

// 1. Abrir modal al hacer click en "Where is it?"
const menuDondeEsta = document.getElementById('menu-donde-esta'); 
const btnMessages = document.getElementById('btn-messages'); 
const modalDondeEsta = document.getElementById('modal-donde-esta');
const selectVehiculo = document.getElementById('select-vehiculo-donde-esta');
const closeModalDondeEsta = document.getElementById('close_modal_donde_esta');
const close_chat = document.getElementById('close_chat');
const infoVehiculo = document.getElementById('info-vehiculo-donde-esta');

if (menuDondeEsta && modalDondeEsta && selectVehiculo && closeModalDondeEsta && infoVehiculo) {
    menuDondeEsta.onclick = async function (e) {
        e.preventDefault();
        infoVehiculo.innerHTML = '';
        modalDondeEsta.style.display = 'flex';

        // Cargar vehículos del día
        const res = await fetch('/app/ajax/mobileAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ modulo_mobiles: 'listar_vehiculos' })
        });

        const resp = await res.json();

        if (resp.success) {
            console.log(`✅ Vehiculos encontrados (${resp.data.length}):`, resp.data);

            const vehiculos = resp.data;

            selectVehiculo.innerHTML = '';

            const optDefault = document.createElement('option');
            optDefault.value = '';
            optDefault.textContent = 'Select a vehicle';
            optDefault.disabled = true;
            optDefault.selected = true;
            selectVehiculo.appendChild(optDefault);

            if (vehiculos && vehiculos.length) {
                vehiculos.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.id_truck;
                    opt.textContent = v.nombre + (v.placa ? ' (' + v.placa + ')' : '');
                    opt.setAttribute('data-nombre', v.nombre); // <--- Aquí
                    opt.setAttribute('data-color', v.color); // <--- Aquí
                    selectVehiculo.appendChild(opt);
                });
            } else {
                selectVehiculo.innerHTML = '<option>No vehicles found</option>';
            }
        } else {
            console.warn(`⚠️ Error updating GPS time: ${resp.error}`);
        }

    };
}

// 2. Al seleccionar un vehículo, mostrar info y marker
document.getElementById('select-vehiculo-donde-esta').onchange = async function () {
    const select = this;
    const selectedOption = select.options[select.selectedIndex];
    const nombreVehiculo = selectedOption.getAttribute('data-nombre');
    const colorVehiculo = selectedOption.getAttribute('data-color') || '#2196F3';
    // Ahora puedes usar nombreVehiculo
    console.log('Nombre del vehículo:', nombreVehiculo);

    const idtruck = this.value;
    const infoDiv = document.getElementById('info-vehiculo-donde-esta');
    infoDiv.innerHTML = 'Loading...';

    const data = {
        modulo_motor2: 'info_vehiculo',
        id_truck: nombreVehiculo
    };

    // Consultar si el vehículo tiene servicio activo
    const resp = await fetch('/app/ajax/motor2Ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });

    const vdata = await resp.json();

    // Si no está en servicio, consultar a verizon
    if (vdata && vdata.lat && vdata.lng) {

        // Suponiendo que ya tienes lat y lng del vehículo
        const latVehiculo = vdata.lat;
        const lngVehiculo = vdata.lng;

        // 1. Verificar si está en la sede
        const sedeLat = 30.3204272;
        const sedeLng = -95.4217815;
        const distanciaSede = calcularDistanciaMetros(latVehiculo, lngVehiculo, sedeLat, sedeLng);

        let html = `
            <div style="
                background: ${colorVehiculo};
                color: #fff;
                font-size: 1.2em;
                font-weight: bold;
                padding: 12px 18px;
                border-radius: 8px 8px 0 0;
                margin-bottom: 18px;
                letter-spacing: 1px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            ">
                Vehicle: ${nombreVehiculo}
            </div>
            <div style="padding: 18px 12px 12px 12px; background: #f9f9f9; border-radius: 0 0 8px 8px;">
                <div style="margin-bottom: 12px;">
                    <b>Location:</b> <span style="color:#0066cc">${vdata.lat}, ${vdata.lng}</span>
                </div>
        `;

        if (distanciaSede <= window.APP_CONFIG.umbral_metros) {
            html += `
                <div style="margin-bottom: 12px;">
                    <b>Location:</b> <span style="color:#388e3c;">At headquarters</span>
                </div>
                <div style="margin-bottom: 12px;">
                    <b>Distance:</b> ${Math.round(distanciaSede)} meters
                </div>
            `;
        } else {
            // 1. Consultar propiedades
            const resProps = await fetch('/app/ajax/mobileAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_mobiles: 'listar_propiedades' })
            });
            const propsResp = await resProps.json();
            let propiedadEncontrada = null;

            let dist = null;

            if (propsResp.success && Array.isArray(propsResp.data)) {
                for (const prop of propsResp.data) {
                    dist = calcularDistanciaMetros(
                        latVehiculo, lngVehiculo,
                        parseFloat(prop.lat), parseFloat(prop.lng)
                    );
                    if (dist <= window.APP_CONFIG.umbral_metros) {
                        propiedadEncontrada = prop;
                        break;
                    }
                }
            }

            if (propiedadEncontrada) {
                html += `
                    <div style="margin-bottom: 12px;">
                        <b>Property:</b> <span style="color:#1565c0;">${propiedadEncontrada.cliente}</span>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <b>Address:</b> ${propiedadEncontrada.direccion}
                    </div>
                    <div style="margin-bottom: 12px;">
                        <b>Distance:</b> ${Math.round(dist)} meters
                    </div>
                `;
            } else {
                $apiKey = "pk.1472af9e389d1d577738a28c25b3e620"
                $lat = latVehiculo;
                $lng = lngVehiculo;

                const data = {
                    modulo_motor2: 'obtener_direccion',
                    apikey: $apiKey,
                    lat: $lat,
                    lng: $lng
                };

                const resp = await fetch('/app/ajax/motor2Ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const locationIQ = await resp.json();

                if (locationIQ && locationIQ.direccion && locationIQ.data) {
                    html += `
                        <div style="margin-bottom: 12px;">
                            <b>Address:</b> <span style="color:#388e3c;">${locationIQ.direccion}</span>
                        </div>
                    `;
                } else {
                    html += `
                        <div style="margin-bottom: 12px;">
                            <b>Location:</b> <span style="color:#c62828;">Not at any registered Location</span>
                        </div>
                    `;
                }
            }
        }
        html += `</div>`;
        infoDiv.innerHTML = html;

        if (window.mapa) {
            if (window.markerDondeEsta) window.mapa.removeLayer(window.markerDondeEsta);
            window.markerDondeEsta = L.marker([vdata.lat, vdata.lng], { icon: L.icon({ iconUrl: '/img/marker-donde-esta.png', iconSize: [32, 32] }) }).addTo(window.mapa);
            window.mapa.setView([vdata.lat, vdata.lng], 16);
            setTimeout(() => {
                if (window.markerDondeEsta) window.mapa.removeLayer(window.markerDondeEsta);
            }, 10 * 60 * 1000);
        }
    } else {
        infoDiv.innerHTML = 'No location found for this vehicle.';
    }
};

// 3. Botón cerrar modal
document.getElementById('close_modal_donde_esta').onclick = function () {
    document.getElementById('modal-donde-esta').style.display = 'none';
    if (window.markerDondeEsta && window.mapa) {
        window.mapa.removeLayer(window.markerDondeEsta);
    }
};

close_chat.onclick = function () {
    document.getElementById('chat-modal').style.display = 'none';
};
