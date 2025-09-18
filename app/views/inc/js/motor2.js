/**
 * motor2.js - Sistema de seguimiento GPS en tiempo real
 * Integración con Verizon FIM API vía motor2Controller
 * Usa solo datos reales, sin emulación
 * Autor: Mario Moreno
 * Fecha: Septiembre 2025
 */

// === Espacios globales para estado ===
window.gpsMarkers = {};   // Marcadores activos (truck → L.Marker)
window.gpsPolylines = {}; // Rutas dibujadas (truck → L.Polyline)

// Sede central: Sergio's Landscape
const SEDE_LATLNG = [30.3204272, -95.4217815];

/**
 * Inicia el motor de seguimiento GPS
 * 1. Espera a que el DOM y el mapa estén listos
 * 2. Obtiene los trucks activos del día
 * 3. Inicia polling periódico
 */
document.addEventListener('DOMContentLoaded', () => {
    console.log('🟢 motor2.js cargado correctamente');

    if (typeof window.map !== 'undefined') {
        iniciarMotorGPS();
    } else {
        console.warn('⚠️ Mapa no disponible aún. Reintentando en 2 segundos...');
        setTimeout(iniciarMotorGPS, 2000);
    }
});

async function iniciarMotorGPS() {
    try {
        // Asegurar que el mapa esté disponible
        if (!window.map) {
            console.warn('⚠️ Mapa no disponible. Esperando...');
            await new Promise(resolve => {
                const checkMap = setInterval(() => {
                    if (window.map) {
                        clearInterval(checkMap);
                        resolve();
                    }
                }, 500);
            });
        }

        const trucks = await obtenerTrucksActivosHoy();

        if (!Array.isArray(trucks) || trucks.length === 0) {
            console.log('🟡 No hay trucks activos hoy');
            return;
        }

        console.log(`🚛 Trucks activos detectados: ${trucks.join(', ')}`);

        // Crear todos los marcadores primero
        for (const truck of trucks) {
            if (!window.gpsMarkers[truck]) {
                crearMarcadorInicial(truck);
            }
        }

        // Luego iniciar el polling
        actualizarPosiciones(trucks);
        setInterval(() => actualizarPosiciones(trucks), 10000);

    } catch (err) {
        console.error('❌ Error al iniciar motor GPS:', err);
    }
}

/**
 * Consulta al backend para obtener los trucks con servicios activos hoy
 * @returns {Promise<string[]>} Lista de vehicle_id
 */
async function obtenerTrucksActivosHoy() {
    try {
        const res = await fetch('/app/ajax/motor2Ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                modulo_motor2: 'obtener_trucks_activos_hoy'
            })
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();
        return Array.isArray(data.trucks) ? data.trucks : [];

    } catch (err) {
        console.error('🚨 Error al obtener trucks activos del día:', err.message);
        return []; // Continuar sin fallar
    }
}

/**
 * Crea un marcador inicial en la sede
 * @param {string} truck - Nombre del vehículo
 */
function crearMarcadorInicial(truck) {
    console.log(`🔧 Creando marcador para: ${truck}`);

    // === Buscar el color del crew desde serviciosData ===
    const servicio = window.serviciosData?.find(s => s.truck === truck);
    const color = servicio?.crew_color_principal || '#FF5722'; // Fallback si no se encuentra

    // SVG del pickup con color dinámico
    const svgHtml = `
    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="22" viewBox="0 0 122.88 57.75">
        <path d="M55.2,0.01h-2c-4.58,0-10.98-0.3-14.66,2.81C32.26,7.29,27.4,15.21,22.1,20.38 c-4.3,0.56-14.26,2.03-16.55,4.07C2.9,26.81,2.93,34.4,2.97,37.62c-4.92-0.1-2.93,11.81,0.26,12.49h6.85 c-4.4-26.18,32.92-22.94,27.3,0h38.19c-5.76-21.96,31.01-27.57,27.47-0.21c6.53-0.02,10.06-0.1,16.89,0 c2.71-0.62,2.97-2.13,2.97-5.75l-2.66-0.33l0.08-1.5c0.03-0.89,0.06-1.77,0.09-2.65c0.16-5.81,0.14-11.43-0.19-16.74H59.77V5.58 C59.87,1.86,58.24,0.12,55.2,0.01L55.2,0.01z M89.87,41.17c3.02,0,5.46,2.45,5.46,5.46s-2.45,5.46-5.46,5.46 c-3.02,0-5.46-2.45-5.46-5.46S86.85,41.17,89.87,41.17L89.87,41.17z M54.4,4.74h-8.8c-4.54,0-10.59,6.56-14.02,13.01 c-0.35,0.65-3.08,5.18-1.25,5.18H54.4v-0.69V5.44V4.74L54.4,4.74z M23.5,41.17c3.02,0,5.46,2.45,5.46,5.46s-2.45,5.46-5.46,5.46 c-3.02,0-5.46-2.45-5.46-5.46S20.48,41.17,23.5,41.17L23.5,41.17z M23.5,35.52c6.14,0,11.11,4.98,11.11,11.11 S29.64,57.75,23.5,57.75c-6.14,0-11.11-4.98-11.11-11.11S17.36,35.52,23.5,35.52L23.5,35.52z M89.87,35.52 c6.14,0,11.11,4.98,11.11,11.11s-4.98,11.11-11.11,11.11c-6.14,0-11.11-4.98-11.11-11.11S83.73,35.52,89.87,35.52L89.87,35.52z"
            fill="${color}" />
    </svg>`;

    const marker = L.marker(SEDE_LATLNG, {
        icon: L.divIcon({
            html: svgHtml,
            className: `pickup-marker-${truck}`,
            iconSize: [48, 22],
            iconAnchor: [24, 11]
        }),
        title: truck
    }).addTo(window.map);

    marker.bindPopup(`<b>${truck}</b><br>Pickup Truck • En movimiento`);

    // Guardar referencia
    window.gpsMarkers[truck] = marker;

    // Crear polilínea con color del crew y estilo punteado
    window.gpsPolylines[truck] = L.polyline([], {
        color: color,
        weight: 2,           // 2px de ancho
        opacity: 0.8,
        dashArray: '6,4',    // Línea punteada (dot-dash)
        lineCap: 'round',
        lineJoin: 'round'
    }).addTo(window.map);
}

/**
 * Actualiza las posiciones de todos los trucks activos
 * @param {string[]} trucks - Lista de vehicles IDs
 */
async function actualizarPosiciones(trucks) {
    for (const truck of trucks) {
        try {
            const data = await obtenerCoordenadaReal(truck);

            if (data && typeof data.lat === 'number' && typeof data.lng === 'number') {
                moverMarcador(truck, data);
            } else {
                console.warn(`🟡 Sin coordenadas válidas para ${truck}`);
            }

        } catch (err) {
            console.error(`🚨 Error al procesar ${truck}:`, err.message);
        }
    }
}

/**
 * Consulta coordenada real desde el backend
 * @param {string} vehicle_id
 * @returns {Promise<Object|null>}
 */
async function obtenerCoordenadaReal(vehicle_id) {
    try {
        const res = await fetch('/app/ajax/motor2Ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                modulo_motor2: 'obtener_gps_verizon',
                vehicle_id: vehicle_id
            })
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();
        return data.error ? null : data;

    } catch (err) {
        console.warn(`❌ Fallo al consultar ${vehicle_id}:`, err.message);
        return null;
    }
}

/**
 * Mueve el marcador y actualiza su estado visual
 * @param {string} truck
 * @param {Object} data - { lat, lng, speed, course, timestamp }
 */
function moverMarcador(truck, data) {
    const latlng = [data.lat, data.lng];
    
    // === Validación crítica: ¿existe el marcador? ===
    if (!window.gpsMarkers || !window.gpsMarkers[truck]) {
        console.warn(`🟡 Marcador no encontrado para ${truck}. Se crea uno nuevo.`);
        
        // Si no existe, lo creamos ahora
        crearMarcadorInicial(truck);
        
        // Validar nuevamente después de crear
        if (!window.gpsMarkers[truck]) {
            console.error(`❌ No se pudo crear el marcador para ${truck}`);
            return;
        }
    }

    const marker = window.gpsMarkers[truck];
    const polyline = window.gpsPolylines[truck];

    // Actualizar posición
    marker.setLatLng(latlng);

    // Rotar ícono según dirección
    const iconElement = marker.getElement();
    if (iconElement && typeof data.course === 'number') {
        iconElement.style.transform = `rotate(${data.course}deg)`;
    }

    // Actualizar popup
    const hora = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    marker.setPopupContent(`
        <b>${truck}</b><br>
        📍 ${data.lat.toFixed(6)}, ${data.lng.toFixed(6)}<br>
        ⚡ Speed: ${data.speed || 'N/A'} mph<br>
        🕒 ${hora}
    `);

    // Actualizar ruta
    if (polyline) {
        const coords = polyline.getLatLngs();
        coords.push(latlng);

        // Limitar historial a 100 puntos
        if (coords.length > 100) {
            coords.shift();
        }

        polyline.setLatLngs(coords);
    }
}