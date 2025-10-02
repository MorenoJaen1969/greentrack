// mobile.js - Frontend Ejecutivo para Don Sergio

let selectedDate = new Date(); // Por defecto, hoy

// === Cargar lista de camionetas activas ===
async function cargarCamionetas() {
    try {
        const res = await fetch('/app/ajax/motor2Ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ modulo_motor2: 'obtener_trucks_activos_hoy_color' })
        });
        const data = await res.json();
        const lista = document.getElementById('camionetas-list');

        if (!Array.isArray(data.trucks)) return;

        data.trucks.forEach(truck => {
            const div = document.createElement('div');
            div.className = 'camioneta-item';
            div.dataset.id = truck.truck;
            div.dataset.color = truck.color;
            div.innerHTML = `
                <div class="dot" style="background:${truck.color || '#2196F3'};"></div>
                ${truck.truck}
            `;
            div.onclick = () => verEstadoCamion(truck.truck, truck.color || '#2196F3');
            lista.appendChild(div);
        });

        // Auto-cargar primer camión
        if (data.trucks.length > 0) {
            verEstadoCamion(data.trucks[0].truck, data.trucks[0].color || '#2196F3');
        }

    } catch (err) {
        document.getElementById('estado-detallado').innerHTML = `<p>❌ Error al cargar flota</p>`;
    }
}

// === Mostrar estado detallado del camión seleccionado ===
async function verEstadoCamion(vehicle_id, color) {
    const detalle = document.getElementById('estado-detallado');
    detalle.innerHTML = `<p>Cargando ${vehicle_id}...</p>`;

    try {
        // ... [código anterior para obtener punto GPS] ...

        // === Obtener TODOS los servicios asignados a este camión ===
        const serviciosCamion = window.serviciosData?.filter(s => s.truck === vehicle_id) || [];

        let statusHtml = '';
        let clienteActivo = null;
        let distMinima = Infinity;

        for (const servicio of serviciosCamion) {
            if (typeof servicio.lat !== 'number') continue;
            const dist = calcularDistanciaMetros(punto.lat, punto.lng, servicio.lat, servicio.lng);
            if (dist <= window.APP_CONFIG.umbral_metros && dist < distMinima) {
                distMinima = dist;
                clienteActivo = servicio;
            }
        }

        if (clienteActivo) {
            statusHtml = `<div class="status-box">🟢 En servicio: ${clienteActivo.cliente}</div>`;
        } else if (serviciosCamion.length > 0) {
            statusHtml = `<div class="status-box">➡️ En ruta</div>`;
        } else {
            statusHtml = `<div class="status-box">📍 Sin servicios asignados</div>`;
        }

        const servicioParaCrew = clienteActivo || serviciosCamion[0] || null;
        const crew = Array.isArray(servicioParaCrew?.crew_integrantes) ? servicioParaCrew.crew_integrantes : [];

        const crewHtml = crew.map(m => 
            `<div class="crew-item">${m.responsabilidad === 'D' ? '🚚' : '🛠️'} ${m.nombre_completo}</div>`
        ).join('');

        // === Generar lista de servicios como BOTONES ===
        const serviciosHtml = serviciosCamion.length > 0
            ? serviciosCamion.map(s => `
                <div class="servicio-boton" onclick="abrirModalServicio(${s.id_servicio}, '${s.cliente.replace(/'/g, "\\'")}', '${s.direccion ? s.direccion.replace(/'/g, "\\'") : 'Sin dirección'}')">
                    <h4>${s.cliente}</h4>
                    <small>${s.direccion || 'Sin dirección'}</small>
                </div>
              `).join('')
            : '<p style="color:#888;">No hay servicios programados para hoy.</p>';

        // === Actualizar interfaz ===
        detalle.innerHTML = `
            <p><small>Último registro: ${new Date().toLocaleTimeString()}</small></p>

            ${statusHtml}

            <p><b>Crew:</b></p>
            ${crewHtml || '<i>No asignado</i>'}

            <hr>
            <h4>📋 Servicios del día (${serviciosCamion.length})</h4>
            ${serviciosHtml}
        `;

        // === Marcar como seleccionado visualmente ===
        document.querySelectorAll('.camioneta-item').forEach(el => {
            el.classList.remove('selected');
            el.style.color = '#333';
        });
        
        const div = document.querySelector(`.camioneta-item[data-id="${vehicle_id}"]`);
        if (div) {
            div.classList.add('selected');
        }

    } catch (err) {
        console.error(`Error en verEstadoCamion(${vehicle_id}):`, err);
        detalle.innerHTML = `<p>🔴 Sin señal o error de conexión</p>`;
    }
}

function abrirCalendario() {
    const input = document.createElement('input');
    input.type = 'date';
    input.value = formatDateForInput(selectedDate);

    input.onchange = function () {
        const nuevaFecha = input.value;
        if (nuevaFecha) {
            selectedDate = new Date(nuevaFecha);
            actualizarEtiquetaFecha();
            // Aquí puedes recargar servicios según la fecha si lo deseas
        }
    };

    input.click(); // Abrir selector de fecha
}

// Actualiza el texto "Active Fleet Today" o con fecha
function actualizarEtiquetaFecha() {
    const label = document.getElementById('fleet-date-label');
    if (!label) return;

    if (isToday(selectedDate)) {
        label.textContent = 'Active Fleet Today';
    } else {
        const fechaFormateada = selectedDate.toISOString().split('T')[0];
        label.textContent = `Active Fleet ${fechaFormateada}`;
    }
}

// Ayuda: ¿es hoy?
function isToday(date) {
    const today = new Date();
    return date.setHours(0,0,0,0) === today.setHours(0,0,0,0);
}

// Formato YYYY-MM-DD para <input type="date">
function formatDateForInput(date) {
    return date.toISOString().split('T')[0];
}

function cerrarModalServicio() {
    const modal = document.getElementById('modal-servicio');
    if (modal) modal.remove();
}

// === Abrir modal de opciones por servicio ===
function abrirModalServicio(id_servicio, cliente, direccion) {
    const modalHtml = `
        <div id="modal-servicio" style="
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        ">
            <div style="
                background: white;
                padding: 20px;
                border-radius: 8px;
                width: 90%;
                max-width: 400px;
                text-align: center;
                font-family: -apple-system, sans-serif;
            ">
                <h3>🔧 Opciones del Servicio</h3>
                <p><strong>${cliente}</strong></p>
                <p style="font-size:0.9em; color:#555;">${direccion}</p>
                
                <button class="action-btn" style="
                    background:#FF9800;
                    color:white;
                    border:none;
                    padding:12px 20px;
                    margin:10px 5px;
                    border-radius:6px;
                    font-size:1em;
                    cursor:pointer;
                    width:100%;
                " onclick="reprogramarServicio(${id_servicio})">
                    🔄 Reprogramar
                </button>
                
                <button class="action-btn" style="
                    background:#f44336;
                    color:white;
                    border:none;
                    padding:12px 20px;
                    margin:10px 5px;
                    border-radius:6px;
                    font-size:1em;
                    cursor:pointer;
                    width:100%;
                " onclick="cancelarServicio(${id_servicio})">
                    ❌ Cancelar
                </button>
                
                <button style="
                    background:#607d8b;
                    color:white;
                    border:none;
                    padding:10px 15px;
                    margin-top:15px;
                    border-radius:6px;
                    cursor:pointer;
                    width:100%;
                " onclick="cerrarModalServicio()">
                    Cerrar
                </button>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function cerrarModalServicio() {
    const modal = document.getElementById('modal-servicio');
    if (modal) modal.remove();
}

function reprogramarServicio(id_servicio) {
    alert(`Reprogramar servicio ${id_servicio}`);
    cerrarModalServicio();
}

function cancelarServicio(id_servicio) {
    if (confirm(`¿Cancelar servicio ${id_servicio}?`)) {
        alert(`Servicio ${id_servicio} cancelado`);
        cerrarModalServicio();
    }
}

// === Calcular distancia entre dos puntos (Haversine) ===
function calcularDistanciaMetros(lat1, lng1, lat2, lng2) {
    const R = 6371e3;
    const φ1 = lat1 * Math.PI / 180;
    const φ2 = lat2 * Math.PI / 180;
    const Δφ = (lat2 - lat1) * Math.PI / 180;
    const Δλ = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(Δφ/2)*Math.sin(Δφ/2) + Math.cos(φ1)*Math.cos(φ2)*Math.sin(Δλ/2)*Math.sin(Δλ/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

function getLocalDateISO() {
    return new Date().toLocaleDateString('sv-SE', { timeZone: 'America/Chicago' });
}

// Resultado: "2025-10-02"
// === Inicializar serviciosData ===
window.serviciosData = [];
async function cargarServiciosData() {
    try {
        const res = await fetch('/app/ajax/serviciosAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ modulo_servicios: 'listar_para_geoferencia', fecha: getLocalDateISO() })
        });
        window.serviciosData = await res.json();

    } catch (err) {
        console.warn('No se pudo cargar serviciosData');
    }
}

// === Iniciar al cargar ===
(async () => {
    await cargarServiciosData();
    await cargarCamionetas();

    // Actualizar cada 30 segundos
    setInterval(() => {
        const active = document.querySelector('.camioneta-item[style*="background: rgb(33, 150, 243)"]');
        if (active) verEstadoCamion(active.dataset.id, active.dataset.color);
    }, 30000);
})();

// Llama esto al cargar
actualizarEtiquetaFecha();

const fechaEnviar = formatDateForInput(selectedDate); // Ej: "2025-09-25"

fetch('/app/ajax/serviciosAjax.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        modulo_servicios: 'listar_para_geoferencia',
        fecha: fechaEnviar
    })
});