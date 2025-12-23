/**
 * motor2.js - Sistema de seguimiento GPS en tiempo real
 * Integración con Verizon FIM API vía motor2Controller
 * Usa solo datos reales, sin emulación
 * Autor: Mario Moreno
 * Fecha: Septiembre 2025
 */

// === Espacios globales para estado ===
window.gpsMarkers = {};         // Marcadores activos (truck → L.Marker)
window.gpsPolylines = {};       // Rutas dibujadas (truck → L.Polyline)

// Sede central: Sergio's Landscape
const SEDE_LATLNG = [30.3204272, -95.4217815];

// Buscar color para lectura
function getColorContraste(fondoHex) {
    // Convertir hex a RGB
    const hex = fondoHex.replace('#', '');
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);

    // Calcular brillo relativo (luminancia)
    const luminancia = (0.299 * r + 0.587 * g + 0.114 * b) / 255;

    // Devolver blanco si oscuro, negro si claro
    return luminancia > 0.5 ? '#000000' : '#FFFFFF';
}

function calcularDistanciaMetros(lat1, lng1, lat2, lng2) {
    const R = 6371e3;
    const φ1 = lat1 * Math.PI / 180;
    const φ2 = lat2 * Math.PI / 180;
    const Δφ = (lat2 - lat1) * Math.PI / 180;
    const Δλ = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(Δφ/2)*Math.sin(Δφ/2) +
                Math.cos(φ1)*Math.cos(φ2)*Math.sin(Δλ/2)*Math.sin(Δλ/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}
   
function startTypewriter(elementId, text = "Calculating stops...") {
    const el = document.getElementById(elementId);
    if (!el) return null;

    let i = 0;
    let isDeleting = false;
    const typingSpeed = 100;
    const deletingSpeed = 50;
    const pauseBeforeDelete = 1500;

    el.textContent = ''; // Limpiar contenido previo
    el.style.display = 'block';

    const type = () => {
        if (!isDeleting) {
            el.textContent = text.substring(0, i + 1);
            i++;
            if (i === text.length) {
                isDeleting = true;
                setTimeout(type, pauseBeforeDelete);
                return;
            }
        } else {
            el.textContent = text.substring(0, i - 1);
            i--;
            if (i === 0) {
                isDeleting = false;
            }
        }

        const speed = isDeleting ? deletingSpeed : typingSpeed;
        el.timeout = setTimeout(type, speed);
    };

    el.timeout = setTimeout(type, 0);

    // Devuelve una función para detener el efecto
    return () => {
        if (el.timeout) {
            clearTimeout(el.timeout);
            el.timeout = null;
        }
        el.textContent = '';
        el.style.display = 'none';
    };
}

/**
 * Inicia el motor de seguimiento GPS
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log('🟢 motor2.js cargado correctamente');
    if (typeof window.map !== 'undefined') {
        iniciarMotorGPS();
    } else {
        setTimeout(iniciarMotorGPS, 2000);
    }

    // === FUNCIÓN GLOBAL PARA ABRIR POPUP DE UN VEHÍCULO ===
    window.abrirPopupVehiculo = async function (truckId) {
        console.log(`Intentando abrir popup para vehículo: ${truckId}`);

        const formReferencia = document.getElementById('form-referencia-dia');

        if (!formReferencia) {
            console.error("❌ No se encontró el modal #form-referencia-dia");
            return;
        }
        formReferencia.style.display = 'flex';
        // === INICIAR EFECTO DE CARGA ===
        const stopTypewriter = startTypewriter('loading-typewriter', 'Calculating stops...');

        // Ocultar contenedor de resultados mientras carga
        const contenedorStops = document.getElementById('contenedor-stops');
        if (contenedorStops) contenedorStops.style.display = 'none';

        let veh_hist;
        try {

            // === CENTRAR EN EL MAPA ===
            const marker = window.gpsMarkers?.[truckId];
            if (marker) {
                window.map.flyTo(marker.getLatLng(), 14, { animate: true, duration: 1.2 });
                marker.openPopup();
            }

            // Centrar mapa suavemente
            window.map.flyTo(marker.getLatLng(), 14, {
                animate: true,
                duration: 1.2
            });

            // Forzar apertura del popup
            marker.openPopup();

            // === CONSTRUIR EL HISTORIAL ===
            veh_hist = await obtenerVehicHoy(truckId);

            // === DETENER EFECTO DE CARGA ===
            if (stopTypewriter) stopTypewriter();

            if (Array.isArray(veh_hist) || veh_hist.length === 0) {
                console.log(`🟡 No hay rutas para hoy del vehículo ${truckId}`);
                contenedorStops.innerHTML = '<p>There are no registered routes.</p>';
                contenedorStops.style.display = 'block';
                return;
            }

            console.log(`🚛 Rutas realizadas del Truck: ${truckId}`);
            llenarModalRutas(veh_hist, truckId);
            contenedorStops.style.display = 'block';

        } catch (error) {
            console.error("Error al cargar rutas del vehículo:", error);
            if (stopTypewriter) stopTypewriter();
            contenedorStops.innerHTML = '<p>Error loading routes.</p>';
            contenedorStops.style.display = 'block';
        }

    };

    // Iniciar GPS después
    if (typeof window.map !== 'undefined') {
        iniciarMotorGPS();
    } else {
        setTimeout(iniciarMotorGPS, 2000);
    }
});

function llenarModalRutas(rutas, truckId) {
    const contenedor = document.getElementById('contenedor-stops');
    if (!contenedor) return;

    if (rutas.length === 0) {
        contenedor.innerHTML = '<p>No hay rutas registradas para hoy.</p>';
        return;
    }
    contenedor.innerHTML = rutas;
}

async function iniciarMotorGPS() {
    try {
        if (!window.map) {
            await new Promise(resolve => {
                const checkMap = setInterval(() => {
                    if (window.map) {
                        clearInterval(checkMap);
                        resolve();
                    }
                }, 500);
            });
        }

        // === ESCUCHAR CIERRE DE POPUP ===
        if (!window._popupCloseListenerAdded) {
            window.map.on('popupclose', function () {
                const formReferencia = document.getElementById('form-referencia-dia');
                if (formReferencia && formReferencia.style.display === 'flex') {
                    formReferencia.style.display = 'none';

                    // Opcional: detener el efecto typewriter
                    const loadingEl = document.getElementById('loading-typewriter');
                    if (loadingEl && loadingEl.timeout) {
                        clearTimeout(loadingEl.timeout);
                        loadingEl.textContent = '';
                        loadingEl.style.display = 'none';
                    }

                    // Opcional: limpiar contenedor
                    const contenedorStops = document.getElementById('contenedor-stops');
                    if (contenedorStops) contenedorStops.innerHTML = '';
                }
            });
            window._popupCloseListenerAdded = true; // evitar duplicados
        }

        // Asegurar serviciosData (si viene de otro módulo)
        if (!window.serviciosData) {
            console.log('⏳ Esperando serviciosData...');
            await new Promise(resolve => {
                const checkServicios = setInterval(() => {
                    if (window.serviciosData) {
                        clearInterval(checkServicios);
                        resolve();
                    }
                }, 300);
            });
        }

        const trucks = await obtenerTrucksActivosHoy();
        if (!Array.isArray(trucks) || trucks.length === 0) {
            console.log('🟡 No hay trucks activos hoy');
            return;
        }
        console.log(`🚛 Trucks activos detectados: ${trucks.join(', ')}`);

        // Cargar y dibujar historial completo para cada truck
        let var_veces = 1;
        for (const truck of trucks) {
            //console.log('Inicio de Ruta de truck: ', truck, " Vuelta N°:", var_veces);
            var_veces++;
            await dibujarRutaCompleta(truck);
        }
    } catch (err) {
        console.error('❌ Error al iniciar motor GPS:', err);
    }
}

/**
 * Consulta trucks activos del día
 */
async function obtenerTrucksActivosHoy() {
    try {
        const res = await fetch('/app/ajax/motor2Ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ modulo_motor2: 'obtener_trucks_activos_hoy_excl' })
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        return Array.isArray(data.trucks) ? data.trucks : [];

    } catch (err) {
        console.error('🚨 Error al obtener trucks activos del día:', err.message);
        return [];
    }
}

/**
 * Consulta trucks activos del día
 */
async function obtenerVehicHoy(vehicle_id) {
    try {
        const res = await fetch('/app/ajax/motor2Ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(
                { 
                    modulo_motor2: 'obtener_ruta_hoy', 
                    vehicle_id: vehicle_id
                }
            )
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        return data.rutas;

    } catch (err) {
        console.error('🚨 Error al obtener rutas de la trucks del día:', err.message);
        return [];
    }
}

// El marcador inicial solo se crea si no hay historial válido
function crearMarcadorEnSede(truck, color = '#FF5722') {
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

    marker.bindPopup(`<b>${truck}</b><br>Pickup Truck • Sin historial`);
    window.gpsMarkers[truck] = marker;

    window.gpsPolylines[truck] = L.polyline([], {
        color: color,
        weight: 2,
        opacity: 0.8,
        dashArray: '6,4',
        lineCap: 'round',
        lineJoin: 'round'
    }).addTo(window.map);
}

// Dibuja la ruta completa y anima el marcador punto por punto
// Puedes cambiar este valor para ajustar la velocidad de animación entre puntos
window.ANIMACION_INTERVALO_MS = 500; // 0.5 segundos entre puntos

async function dibujarRutaCompleta(truck) {
    try {
        const res = await fetch('/app/ajax/motor2Ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                modulo_motor2: 'obtener_historial_gps',
                vehicle_id: truck
            })
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        let color = '#FF5722';
        const servicio = window.serviciosData?.find(s => s.truck === truck);
        if (servicio && servicio.crew_color_principal) {
            color = servicio.crew_color_principal;
        }

        if (!data || !Array.isArray(data.historial) || data.historial.length === 0) {
            crearMarcadorEnSede(truck, color);
            return;
        }

        const historialFiltrado = data.historial.filter(p =>
            typeof p.lat === 'number' && typeof p.lng === 'number' && !(p.lat === 0 && p.lng === 0)
        );

        if (historialFiltrado.length === 0) {
            const ahora = new Date();
            
            // Solo si ya pasaron las 7:00 AM → alerta de inactividad
            if (ahora.getHours() >= 7) {
                console.warn(`🚨 ${truck} no tiene datos GPS a las ${ahora.toLocaleTimeString()}. Posible inactividad.`);
                
                window.dispatchEvent(new CustomEvent('vehiculoSinGPS', {
                    detail: {
                        vehicle_id: truck,
                        hora_alerta: ahora.toISOString(),
                        mensaje: 'No GPS signal after 7:00 AM'
                    }
                }));
            }

            // Crear marcador en sede
            crearMarcadorEnSede(truck, color);
            return;
        }
        
        // Inicializar polilínea
        if (window.gpsPolylines[truck]) {
            window.gpsPolylines[truck].setLatLngs([]);
            window.gpsPolylines[truck].setStyle({ color: color, weight: 2, opacity: 1, dashArray: '2,6' });
        } else {
            window.gpsPolylines[truck] = L.polyline([], {
                color: color,
                weight: 2,
                opacity: 1,
                dashArray: '2,6'
            }).addTo(window.map);
        }

        // SVG original intacto (como lo tenías antes)
        const svgHtml = `
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="22" viewBox="0 0 122.88 57.75">
                <path d="M55.2,0.01h-2c-4.58,0-10.98-0.3-14.66,2.81C32.26,7.29,27.4,15.21,22.1,20.38 c-4.3,0.56-14.26,2.03-16.55,4.07C2.9,26.81,2.93,34.4,2.97,37.62c-4.92-0.1-2.93,11.81,0.26,12.49h6.85 c-4.4-26.18,32.92-22.94,27.3,0h38.19c-5.76-21.96,31.01-27.57,27.47-0.21c6.53-0.02,10.06-0.1,16.89,0 c2.71-0.62,2.97-2.13,2.97-5.75l-2.66-0.33l0.08-1.5c0.03-0.89,0.06-1.77,0.09-2.65c0.16-5.81,0.14-11.43-0.19-16.74H59.77V5.58 C59.87,1.86,58.24,0.12,55.2,0.01L55.2,0.01z M89.87,41.17c3.02,0,5.46,2.45,5.46,5.46s-2.45,5.46-5.46,5.46 c-3.02,0-5.46-2.45-5.46-5.46S86.85,41.17,89.87,41.17L89.87,41.17z M54.4,4.74h-8.8c-4.54,0-10.59,6.56-14.02,13.01 c-0.35,0.65-3.08,5.18-1.25,5.18H54.4v-0.69V5.44V4.74L54.4,4.74z M23.5,41.17c3.02,0,5.46,2.45,5.46,5.46s-2.45,5.46-5.46,5.46 c-3.02,0-5.46-2.45-5.46-5.46S20.48,41.17,23.5,41.17L23.5,41.17z M23.5,35.52c6.14,0,11.11,4.98,11.11,11.11 S29.64,57.75,23.5,57.75c-6.14,0-11.11-4.98-11.11-11.11S17.36,35.52,23.5,35.52L23.5,35.52z M89.87,35.52 c6.14,0,11.11,4.98,11.11,11.11s-4.98,11.11-11.11,11.11c-6.14,0-11.11-4.98-11.11-11.11S83.73,35.52,89.87,35.52L89.87,35.52z"
                    fill="${color}" />
            </svg>`;

        let marker;
        if (window.gpsMarkers[truck]) {
            marker = window.gpsMarkers[truck];
            marker.setLatLng([historialFiltrado[0].lat, historialFiltrado[0].lng]);
        } else {
            marker = L.marker([historialFiltrado[0].lat, historialFiltrado[0].lng], {
                icon: L.divIcon({
                    html: svgHtml,
                    className: `pickup-marker-${truck}`,
                    iconSize: [48, 22],
                    iconAnchor: [24, 11]
                }),
                title: truck
            }).addTo(window.map);

            marker.bindPopup('<b>Loading...</b>');
            window.gpsMarkers[truck] = marker;
        }

        // Variables de estado
        let indice = 0;
        let ultimoPunto = null;
        let inicioDetencion = null; // Momento en que comenzó a estar detenido
        let ultimaDeteccionCliente = false;
        let ultimaDeteccionSede = false;
        let servicioIniciado = false;
        let historialActual = [...historialFiltrado];

        function animarMarcador() {
            if (indice >= historialActual.length || indice < 0) return;

            const punto = historialActual[indice];
            marker.setLatLng([punto.lat, punto.lng]);

            // Actualizar polilínea
            const polyline = window.gpsPolylines[truck];
            const latlngs = polyline.getLatLngs();
            latlngs.push([punto.lat, punto.lng]);
            polyline.setLatLngs(latlngs);

            // === Verificar si está detenido (comparación aproximada) ===
            const estaDetenido = ultimoPunto &&
                calcularDistanciaMetros(punto.lat, punto.lng, ultimoPunto.lat, ultimoPunto.lng) <= 10;

            // Actualizar momento de detención
            if (estaDetenido && !inicioDetencion) {
                inicioDetencion = new Date();
            } else if (!estaDetenido) {
                inicioDetencion = null;
            }

            // === Obtener servicio asignado ===
            const servicio = window.serviciosData?.find(s => s.truck === truck);
            const crew_act = servicio?.crew_integrantes || [];

            // Formato Crew HTML
            const crewHtml = Array.isArray(crew_act) && crew_act.length > 0
                ? crew_act.map(member => {
                    const rol = member.responsabilidad === 'D'
                        ? '<span title="Driver" style="color:#FFD700;">🚚</span>'
                        : '<span title="Operator" style="color:#87CEEB;">🛠️</span>';
                    return `
                        <span style="
                            display: block;
                            background: #333; 
                            color: #fff; 
                            padding: 4px 6px; 
                            border-radius: 4px; 
                            font-size: 0.85em; 
                            margin: 2px 0;
                            white-space: nowrap;
                            text-align: center;
                        ">
                            ${rol} ${member.nombre_completo}
                        </span>
                    `;
                }).join('')
                : '<span style="color:#aaa; font-style:italic;">No crew assigned</span>';

            let popupMsg = `<b>${truck}</b><br>`;
            popupMsg += `<div style="margin-top:6px; font-size:0.85em;"><b>Crew:</b></div><div style="margin-top:2px;">${crewHtml}</div>`;

            // Si hay servicio, verificar geofencing
            if (servicio && typeof servicio.lat === 'number' && typeof servicio.lng === 'number') {
                const distanciaCliente = calcularDistanciaMetros(
                    punto.lat,
                    punto.lng,
                    servicio.lat,
                    servicio.lng
                );

                // Inicio de servicio
                if (distanciaCliente <= window.APP_CONFIG.umbral_metros && estaDetenido && !ultimaDeteccionCliente && !servicioIniciado) {
                    console.log(`🟢 ${truck} inició servicio en cliente ${servicio.cliente}`);
                    ultimaDeteccionCliente = true;
                    servicioIniciado = true;

                    window.dispatchEvent(new CustomEvent('servicioIniciado', {
                        detail: {
                            id_servicio: servicio.id_servicio,
                            id_cliente: servicio.id_cliente,
                            id_truck: truck,
                            lat: punto.lat,
                            lng: punto.lng,
                            hora: punto.timestamp
                        }
                    }));

                    popupMsg += `<br>✅ Servicing client: <b>${servicio.cliente}</b>`;
                }

                // Cierre en sede
                // const distanciaSede = calcularDistanciaMetros(
                //     punto.lat,
                //     punto.lng,
                //     SEDE_LATLNG[0],
                //     SEDE_LATLNG[1]
                // );

//                if (distanciaSede <= window.APP_CONFIG.umbral_metros && estaDetenido && !ultimaDeteccionSede && servicioIniciado) {

                const distanciaCliente_act = calcularDistanciaMetros(
                    punto.lat,
                    punto.lng,
                    servicio.lat,
                    servicio.lng
                );


                if (distanciaCliente_act > window.APP_CONFIG.umbral_metros && estaDetenido && !ultimaDeteccionSede && servicioIniciado) {
                    console.log(`🔴 ${truck} cerró servicio en sede`);
                    ultimaDeteccionSede = true;
                    servicioIniciado = false;

                    window.dispatchEvent(new CustomEvent('servicioCerrado', {
                        detail: {
                            id_servicio: servicio?.id_servicio,
                            id_cliente: servicio?.id_cliente,
                            id_truck: truck,
                            lat: punto.lat,
                            lng: punto.lng,
                            hora: punto.timestamp
                        }
                    }));

                    popupMsg += `<br>⏹️ Stopped at headquarters<br>Service finished`;
                }
            }

            // Estado general
            if (!popupMsg.includes('Servicing client') && !popupMsg.includes('Stopped at headquarters')) {
                if (estaDetenido && inicioDetencion) {
                    const diffSeg = Math.floor((new Date() - inicioDetencion) / 1000);
                    const mins = Math.floor(diffSeg / 60);
                    const segs = diffSeg % 60;
                    popupMsg += `<br>⏸️ Stopped.<br>Time: <b>${mins} min ${segs}s</b> in (${punto.lat.toFixed(6)}, ${punto.lng.toFixed(6)})`;
                } else if (estaDetenido){
                    popupMsg += `<br>⏸️ Stopped.<br>Time: in (${punto.lat.toFixed(6)}, ${punto.lng.toFixed(6)})`;
                } else {    
                    popupMsg += `<br>🚚 In transit...`;
                }
            }

            marker.setPopupContent(popupMsg);
            ultimoPunto = punto;
            indice++;
            setTimeout(animarMarcador, window.ANIMACION_INTERVALO_MS);
        }


        // Iniciar animación
        animarMarcador();

        // Polling continuo para nuevos puntos
        setInterval(async () => {
            try {
                const res = await fetch('/app/ajax/motor2Ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        modulo_motor2: 'obtener_historial_gps',
                        vehicle_id: truck
                    })
                });

                if (!res.ok) return;
                const data = await res.json();

                if (!data || !Array.isArray(data.historial)) return;

                const nuevos = data.historial.filter(p =>
                    typeof p.lat === 'number' && typeof p.lng === 'number' && !(p.lat === 0 && p.lng === 0)
                );

                if (nuevos.length <= historialActual.length) return;

                const nuevosPuntos = nuevos.slice(historialActual.length);
                historialActual.length = 0;
                Array.prototype.push.apply(historialActual, nuevos);

                // Reanudar animación desde último punto procesado
                for (const punto of nuevosPuntos) {
                    if (indice < historialActual.length) {
                        animarMarcador();
                        break;
                    }
                }
            } catch (err) {
                console.warn(`Error en polling para ${truck}:`, err.message);
            }
        }, 5000);

        // === INICIAR MONITOREO CONTINUO DE GEOFERENCIA ===
        if (typeof iniciarGeoferenciaContinua === 'function') {
            //console.log(`🟢 Geoferencia continua activada para ${truck}`);
            iniciarGeoferenciaContinua(truck, marker);
        } else {
            console.warn('🟡 iniciarGeoferenciaContinua no está definida');
        }

    } catch (err) {
        console.error(`❌ Error al dibujar ruta de ${truck}:`, err.message);
        crearMarcadorEnSede(truck);
    }
}

// === MODAL HISTÓRICO ===
let modalHistorico = document.getElementById('modal-historico-ruta');
let btnAbrirModal = document.getElementById('btn-abrir-modal-historico');
let btnCerrarModal = document.getElementById('btn-cerrar-modal-historico');
let formFlotante = document.getElementById('form-flotante-historico');

//let btnConsultarHistorico = document.getElementById('btn-consultar-historico');

let selectTruck = document.getElementById('vehiculo-historico');
let barraVelocidad = document.getElementById('barra-velocidad-historico');
let listaServicios = document.getElementById('lista-servicios-historico');
let fecha_calculo = document.getElementById('fecha-historico');

const vehicleColors = {}; // Ej: { "TRUCK 15": "#FF0000", "TRUCK 12": "#00FF00" }

// Verificar que todos los elementos existan
if (!modalHistorico || !btnAbrirModal || !btnCerrarModal) {
    console.warn('⚠️ No se encontraron elementos del modal. Saltando control de visibilidad.');
} else {
    // === 1. Al abrir el modal: ocultar el formulario flotante ===
    btnAbrirModal.addEventListener('click', () => {
        if (formFlotante) {
            formFlotante.style.display = 'none'; // Oculta el panel
        }
        modalHistorico.style.display = 'block';
        document.getElementById('fecha-historico').value = '';
        document.getElementById('vehiculo-historico').value = '';
    });

    // === 2. Al cerrar el modal: mostrar nuevamente el formulario flotante ===
    btnCerrarModal.addEventListener('click', () => {
        modalHistorico.style.display = 'none';
        if (formFlotante) {
            formFlotante.style.display = 'flex'; // O 'block' / 'inline-flex', según diseño
        }
    });

    // === 3. También cerrar con tecla ESC (mejora UX) ===
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalHistorico.style.display === 'block') {
            modalHistorico.style.display = 'none';
            if (formFlotante) {
                formFlotante.style.display = 'flex';
            }
        }
    });

    // === 4. Cerrar al hacer clic fuera del contenido del modal ===
    modalHistorico.addEventListener('click', (e) => {
        if (e.target === modalHistorico) {
            modalHistorico.style.display = 'none';
            if (formFlotante) {
                formFlotante.style.display = 'flex';
            }
        }
    });

    if (barraVelocidad) {
        barraVelocidad.oninput = (e) => {
            window.ANIMACION_INTERVALO_MS = parseInt(e.target.value, 10);
        };
    }
}

fecha_calculo.addEventListener('change', async function () {
    const fecha = this.value; // Formato YYYY-MM-DD

    if (!fecha) return;

    try {
        const response = await fetch('/app/ajax/serviciosAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                modulo_servicios: 'obtener_vehicles_por_fecha',
                fecha: fecha
            })
        });

        const data = await response.json();

        // Limpiar select
        const select = document.getElementById('vehiculo-historico');
        select.innerHTML = '<option value="">Select vehicle</option>';

        if (Array.isArray(data.trucks) && data.trucks.length > 0) {
            const seen = new Set(); // Para evitar duplicados

            data.trucks.forEach(truck => {
                const id = truck.vehicle_id;
                const color = truck.color || '#2196F3'; // Color por defecto si no viene

                // Evitar duplicados en el <select>
                if (!seen.has(id)) {
                    seen.add(id);

                    const option = document.createElement('option');
                    option.value = id;
                    option.textContent = id;
                    select.appendChild(option);
                }

                // === Guardar el color del vehículo SI NO está ya guardado ===
                // Como el color es fijo, solo lo guardamos una vez
                if (!(id in vehicleColors)) {
                    vehicleColors[id] = color;
                }
            });

        } else {

            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'There are no vehicles for this date.';
            select.appendChild(option);
        }

    } catch (err) {
        console.error('Error loading vehicles:', err);
        const select = document.getElementById('vehiculo-historico');
        select.innerHTML = '<option value="">Error loading</option>';
    }
});

/* if (btnConsultarHistorico) {
    btnConsultarHistorico.onclick = async () => {
        const fecha = fecha_calculo.value;
        const truck = selectTruck.value;
        // Consulta servicios históricos
        const resServicios = await fetch('/app/ajax/serviciosAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ modulo: 'listar', fecha, truck })
        });
        let servicios = [];
        if (resServicios.ok) {
            servicios = await resServicios.json();
        }
        listaServicios.innerHTML = '';
        if (servicios.length > 0) {
            servicios.forEach(s => {
                const li = document.createElement('li');
                li.textContent = `${s.cliente} - ${s.direccion}`;
                listaServicios.appendChild(li);
            });
        } else {
            listaServicios.innerHTML = '<li>No hay servicios para los parámetros seleccionados.</li>';
        }
        // Consulta y animación de ruta histórica
        const resRuta = await fetch('/app/ajax/motor2Ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ modulo_motor2: 'obtener_historial_gps', vehicle_id: truck, fecha })
        });
        if (resRuta.ok) {
            const data = await resRuta.json();
            if (data && Array.isArray(data.historial) && data.historial.length > 0) {
                // Animar ruta histórica en el mapa
                animarRutaHistorica(truck, data.historial);
            }
        }
    };
}
 */

// Consultar y mostrar ruta histórica y servicios
const btnConsultar = document.getElementById('btn-consultar-historico');

btnConsultar.onclick = async function () {
    const fecha = document.getElementById('fecha-historico').value;
    const vehiculo = document.getElementById('vehiculo-historico').value;

    if (!fecha || !vehiculo) {
        await suiteAlertError("Error", "Select date and vehicle");
        return;
    }

    // === Obtener color del vehículo (único, no depende de fecha) ===
    const color = vehicleColors[vehiculo] || '#2196F3'; // Azul por defecto si no existe

    // Variables locales para este mapa específico
    let marker = null;
    let polyline = null;
    let animacionActiva = false;
    let animacionTimeout = null;


    // === PASO 1: Limpiar mapa anterior si existe ===
    const mapaDiv = document.getElementById('mapa-historico');

    // Si tenemos una referencia al mapa, removerlo
    if (mapaDiv._leafletMap) {
        mapaDiv._leafletMap.remove(); // Elimina el mapa y libera recursos
        delete mapaDiv._leafletMap;
    }

    // Asegurarse de que el contenedor esté completamente limpio
    mapaDiv.innerHTML = '';

    // === PASO 2: Crear nuevo mapa ===
    const mapa = L.map(mapaDiv, {
        zoomAnimation: true,
        fadeAnimation: true,
        markerZoomAnimation: true,
        // ✅ Activar pan suave
        scrollWheelZoom: false // opcional: desactivar zoom con rueda en modal
    }).setView([30.3204272, -95.4217815], 12);


    mapaDiv._leafletMap = mapa; // Guardamos referencia local

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(mapa);

    // === MARCADOR FIJO: Sede de Sergio's Landscape (Estrella de David - Color uniforme) ===
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

    const sedeMarker = L.marker([30.3204272, -95.4217815], {
        icon: L.divIcon({
            html: starSvgUniform,
            className: 'sede-marker',
            iconSize: [32, 32],
            iconAnchor: [16, 16],
            popupAnchor: [0, -16]
        })
    }).addTo(mapa);

    sedeMarker.bindPopup(`
        <b>Sergio's Landscape</b><br>
        <span style="font-size: 0.9em; color: #555;">Headquarters • Starting Point</span>
    `);

    // Consultar historial GPS
    let fromTime = fecha + ' 00:00:00';
    let toTime = fecha + ' 23:59:59';
    let historial = [];
    try {
        const res = await fetch('/app/ajax/motor2Ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                modulo_motor2: 'obtener_historico_bd',
                vehicle_id: vehiculo,
                from_time: fromTime,
                to_time: toTime
            })
        });
        const data = await res.json();
        if (Array.isArray(data.historial)) historial = data.historial;

        //Visualiacion de datos en consola para depuración
        console.log(`Historic points for ${vehiculo} on ${fecha}:`, historial.length);
    } catch (err) { }

    // Dibujar ruta animada
    if (historial.length > 0) {
        // Suaviza el tiempo para los efetos visuales, solo cambio el arreglo de historial a historialFiltrado
        const umbralDistancia = 0.0001; // ~10 metros aprox
        const h_F_Modal = [];

        historial.forEach((punto, index) => {
            if (index === 0) {
                h_F_Modal.push(punto);
                return;
            }

            const anterior = h_F_Modal[h_F_Modal.length - 1];
            const dist = Math.sqrt(
                Math.pow(punto.lat - anterior.lat, 2) +
                Math.pow(punto.lng - anterior.lng, 2)
            );

            if (dist > umbralDistancia) {
                h_F_Modal.push(punto);
            }
        });


        // === INICIALIZAR MAPA Y ELEMENTOS ===
        const polyline = L.polyline([], { color: color, weight: 3 }).addTo(mapa);

        // Marcador inicial (sin moverlo aún)
        let marker = null;

        // Variables de control
        let animacionActiva = true;
        let animacionTimeout = null;
        let velocidadNormal = parseInt(document.getElementById('velocidad-historico').value) || 700;
        let velocidadActual = velocidadNormal;
        let indiceActual = 0; // Índice en h_F_Modal

        const svgHtml = `
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="22" viewBox="0 0 122.88 57.75">
                <path d="M55.2,0.01h-2c-4.58,0-10.98-0.3-14.66,2.81C32.26,7.29,27.4,15.21,22.1,20.38 c-4.3,0.56-14.26,2.03-16.55,4.07C2.9,26.81,2.93,34.4,2.97,37.62c-4.92-0.1-2.93,11.81,0.26,12.49h6.85 c-4.4-26.18,32.92-22.94,27.3,0h38.19c-5.76-21.96,31.01-27.57,27.47-0.21c6.53-0.02,10.06-0.1,16.89,0 c2.71-0.62,2.97-2.13,2.97-5.75l-2.66-0.33l0.08-1.5c0.03-0.89,0.06-1.77,0.09-2.65c0.16-5.81,0.14-11.43-0.19-16.74H59.77V5.58 C59.87,1.86,58.24,0.12,55.2,0.01L55.2,0.01z M89.87,41.17c3.02,0,5.46,2.45,5.46,5.46s-2.45,5.46-5.46,5.46 c-3.02,0-5.46-2.45-5.46-5.46S86.85,41.17,89.87,41.17L89.87,41.17z M54.4,4.74h-8.8c-4.54,0-10.59,6.56-14.02,13.01 c-0.35,0.65-3.08,5.18-1.25,5.18H54.4v-0.69V5.44V4.74L54.4,4.74z M23.5,41.17c3.02,0,5.46,2.45,5.46,5.46s-2.45,5.46-5.46,5.46 c-3.02,0-5.46-2.45-5.46-5.46S20.48,41.17,23.5,41.17L23.5,41.17z M23.5,35.52c6.14,0,11.11,4.98,11.11,11.11 S29.64,57.75,23.5,57.75c-6.14,0-11.11-4.98-11.11-11.11S17.36,35.52,23.5,35.52L23.5,35.52z M89.87,35.52 c6.14,0,11.11,4.98,11.11,11.11s-4.98,11.11-11.11,11.11c-6.14,0-11.11-4.98-11.11-11.11S83.73,35.52,89.87,35.52L89.87,35.52z" fill="${color}" />
            </svg>`;

        // Crear marcador en el primer punto
        const primerPunto = h_F_Modal[0];
        marker = L.marker([primerPunto.lat, primerPunto.lng], {
            icon: L.divIcon({
                html: svgHtml,
                className: `pickup-marker-${vehiculo}`,
                iconSize: [48, 22],
                iconAnchor: [24, 11]
            })
        }).addTo(mapa);

        // Centrar mapa en el primer punto
        mapa.setView([primerPunto.lat, primerPunto.lng], 14);

        // === FUNCIÓN PRINCIPAL DE ANIMACIÓN (con soporte para avance/retroceso) ===
        function iniciarAnimacion(direccion = 1) {
            clearTimeout(animacionTimeout);

            function animar() {
                if (!animacionActiva) return;

                const longitud = h_F_Modal.length;

                // Actualizar índice
                indiceActual += direccion;

                // Limitar rangos
                if (direccion > 0 && indiceActual >= longitud) {
                    indiceActual = longitud - 1;
                    animacionActiva = false;
                    document.getElementById('btn-pausa-reanudar').innerHTML = '▶️';
                    document.getElementById('estado-reproduccion').textContent = 'Finished';
                    return;
                }

                if (direccion < 0 && indiceActual < 0) {
                    indiceActual = 0;
                    animacionActiva = false;
                    document.getElementById('btn-pausa-reanudar').innerHTML = '▶️';
                    document.getElementById('estado-reproduccion').textContent = 'At start';
                    return;
                }

                // Obtener punto actual
                const punto = h_F_Modal[indiceActual];

                // Actualizar polilínea: todos los puntos hasta el actual
                const latlngs = h_F_Modal.slice(0, indiceActual + 1).map(p => [p.lat, p.lng]);
                polyline.setLatLngs(latlngs);

                // Mover marcador
                marker.setLatLng([punto.lat, punto.lng]);

                // Mostrar hora actual
                const timePart = punto.timestamp ? punto.timestamp.split(' ')[1].substring(0, 8) : '--:--:--';
                document.getElementById('hora-animacion').textContent = timePart;

                // Centrar mapa con animación cada ciertos pasos
                if (indiceActual % 3 === 0) {
                    mapa.panTo([punto.lat, punto.lng], {
                        animate: true,
                        duration: 0.3
                    });
                }

                // Siguiente paso
                animacionTimeout = setTimeout(animar, velocidadActual);
            }

            animar();
        }

        // === INICIAR ANIMACIÓN AL ABRIR ===
        iniciarAnimacion(1);

        // === EVENTOS DE LOS BOTONES ===

        // PAUSA / REANUDAR
        document.getElementById('btn-pausa-reanudar').addEventListener('click', function () {
            animacionActiva = !animacionActiva;

            if (animacionActiva) {
                this.innerHTML = '⏸️';
                document.getElementById('estado-reproduccion').textContent = 'Playing...';
                iniciarAnimacion(indiceActual < h_F_Modal.length - 1 ? 1 : -1);
            } else {
                this.innerHTML = '▶️';
                document.getElementById('estado-reproduccion').textContent = 'Paused';
                clearTimeout(animacionTimeout);
            }
        });

        // REINICIAR
        document.getElementById('btn-reiniciar-ruta').addEventListener('click', function () {
            animacionActiva = false;
            clearTimeout(animacionTimeout);

            indiceActual = 0;
            polyline.setLatLngs([]);

            const puntoInicio = h_F_Modal[0];
            marker.setLatLng([puntoInicio.lat, puntoInicio.lng]);
            mapa.setView([puntoInicio.lat, puntoInicio.lng], 14);

            document.getElementById('btn-pausa-reanudar').innerHTML = '⏸️';
            document.getElementById('estado-reproduccion').textContent = 'Restarted';
            document.getElementById('hora-animacion').textContent = puntoInicio.timestamp?.split(' ')[1] || '--:--:--';

            // Reiniciar animación hacia adelante
            animacionActiva = true;
            velocidadActual = velocidadNormal;
            iniciarAnimacion(1);
        });

        // AVANCE RÁPIDO (x2)
        document.getElementById('btn-rapido-adelante').addEventListener('click', function () {
            velocidadActual = velocidadNormal / 2;
            document.getElementById('estado-reproduccion').textContent = 'Fast forward (2x)';
            if (!animacionActiva) {
                animacionActiva = true;
                iniciarAnimacion(1);
            }
        });

        // RETROCESO RÁPIDO (x2)
        document.getElementById('btn-rapido-atras').addEventListener('click', function () {
            velocidadActual = velocidadNormal / 2;
            document.getElementById('estado-reproduccion').textContent = 'Rewind (2x)';
            if (!animacionActiva) {
                animacionActiva = true;
                iniciarAnimacion(-1);
            } else {
                animacionActiva = false;
                setTimeout(() => {
                    animacionActiva = true;
                    iniciarAnimacion(-1);
                }, 100);
            }
        });

        // ACTUALIZAR VELOCIDAD DESDE EL SLIDER
        document.getElementById('velocidad-historico').addEventListener('input', function () {
            velocidadNormal = parseInt(this.value) || 700;
            document.getElementById('velocidad-historico-valor').textContent = `${velocidadNormal} ms`;
            if (animacionActiva && velocidadActual === velocidadNormal) {
                velocidadActual = velocidadNormal;
            }
        });
    }

    // Consultar servicios a clientes para ese vehículo y fecha
    try {
        const res = await fetch('/app/ajax/serviciosAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                modulo_servicios: 'listar_individual',
                fecha: fecha,
                truck: vehiculo
            })
        });
        const data = await res.json();
        const serviciosDiv = document.getElementById('servicios-historico');

        if (Array.isArray(data) && data.length > 0) {
            document.getElementById('cant_servicios').innerHTML = data.length;

            data.forEach(s => {
                if (s.lat && s.lng) {
                    const marker_cli = L.marker([s.lat, s.lng], {
                        icon: L.divIcon({
                            html: `<div style="background:${color};width:16px;height:16px;border-radius:50%;border:2px solid black;box-shadow:0 0 5px rgba(0,0,0,0.5);"></div>`,
                            className: '',
                            iconSize: [16, 16],
                            iconAnchor: [8, 8]
                        })
                    });

                    // Guardar referencia
                    //window.mapMarkers[s.id_servicio] = marker_cli;

                    // Asignar ID al elemento
                    marker_cli.on('add', function () {
                        const iconElement = this.getElement();
                        if (iconElement) {
                            iconElement.id = `marker_cli-${s.id_servicio}`;
                            iconElement.style.transition = 'all 0.3s ease';
                        }
                    });

                    const colorTexto = getColorContraste(color); // → '#FFFFFF' (blanco)
                    marker_cli.addTo(mapa);
                    marker_cli.bindPopup(`
                        <b>${s.cliente}</b>
                        <br>${s.direccion || 'No address'}
                        <br><div class="tit_d_grid" style="
                            background:${color} !important; 
                            color:${colorTexto} !important;">
                        <b>Crew:</b> ${s.truck || 'N/A'}</div>`);
                }
            });

//ojo
            serviciosDiv.innerHTML = data.map(s => {
                // === Función para extraer hora de DATETIME ===
                const getHora = (datetimeStr) => {
                    if (!datetimeStr) return null;
                    const match = datetimeStr.match(/\d{2}:\d{2}:\d{2}/);
                    return match ? match[0] : null;
                };

                // === MOTOR 1: Datos operativos (planeado/manual) ===
                const horaInicioM1 = getHora(s.hora_aviso_usuario);
                const horaFinM1 = getHora(s.hora_finalizado);

                let duracionM1 = 'Not recorded';
                if (horaInicioM1 && horaFinM1) {
                    const timeToSeconds = (timeStr) => {
                        const [h, m, s] = timeStr.split(':').map(Number);
                        return h * 3600 + m * 60 + s;
                    };
                    const inicioSeg = timeToSeconds(horaInicioM1);
                    const finSeg = timeToSeconds(horaFinM1);
                    if (finSeg >= inicioSeg) {
                        const diffSeg = finSeg - inicioSeg;
                        const h = String(Math.floor(diffSeg / 3600)).padStart(2, '0');
                        const m = String(Math.floor((diffSeg % 3600) / 60)).padStart(2, '0');
                        const sec = String(diffSeg % 60).padStart(2, '0');
                        duracionM1 = `${h}:${m}:${sec}`;
                    }
                }

                // === MOTOR 2: Datos GPS (real/automático) ===
                const horaInicioM2 = getHora(s.hora_inicio_gps);
                const horaFinM2 = getHora(s.hora_fin_gps);

                let duracionM2 = 'Not recorded';
                if (horaInicioM2 && horaFinM2) {
                    const timeToSeconds = (timeStr) => {
                        const [h, m, s] = timeStr.split(':').map(Number);
                        return h * 3600 + m * 60 + s;
                    };
                    const inicioSeg = timeToSeconds(horaInicioM2);
                    const finSeg = timeToSeconds(horaFinM2);
                    if (finSeg >= inicioSeg) {
                        const diffSeg = finSeg - inicioSeg;
                        const h = String(Math.floor(diffSeg / 3600)).padStart(2, '0');
                        const m = String(Math.floor((diffSeg % 3600) / 60)).padStart(2, '0');
                        const sec = String(diffSeg % 60).padStart(2, '0');
                        duracionM2 = `${h}:${m}:${sec}`;
                    }
                }

                return `
                    <div style='margin-bottom:15px; border-bottom: 1px solid #eee; padding-bottom: 8px;'>
                        <b style="
                            display: block;
                            background: #333; 
                            color: #fff; 
                            padding: 4px 6px; 
                            border-radius: 4px; 
                            font-size: 1em; 
                            margin: 2px 0;
                            white-space: nowrap;
                            text-align: center;
                        ">${s.cliente}</b>
                        <span style="font-size:0.9em; color:#555;">(${s.direccion})</span>

                        <!-- COLUMNAS: Motor 1 vs Motor 2 -->
                        <div style="
                            display: flex;
                            justify-content: space-between;
                            margin-top: 8px;
                            font-size: 0.9em;
                            line-height: 1.5;
                        ">

                            <!-- MOTOR 1 -->
                            <div style="flex: 1; padding-right: 10px;">
                                <div><strong style="color:#1976D2;">Motor 1</strong></div>
                                <div>Planned Start: ${horaInicioM1 || '<span style="color:#999;">Not recorded</span>'}</div>
                                <div>Planned End: ${horaFinM1 || '<span style="color:#999;">Not recorded</span>'}</div>
                                <div style="color:#4CAF50;">Duration: ${duracionM1}</div>
                            </div>

                            <!-- SEPARADOR VERTICAL OPCIONAL -->
                            <div style="width:1px; background:#ddd; margin:0 10px;"></div>

                            <!-- MOTOR 2 -->
                            <div style="flex: 1; padding-left: 10px;">
                                <div><strong style="color:#FF5722;">Motor 2</strong></div>
                                <div>GPS Start: ${horaInicioM2 || '<span style="color:#999;">Not recorded</span>'}</div>
                                <div>GPS End: ${horaFinM2 || '<span style="color:#999;">Not recorded</span>'}</div>
                                <div style="color:#4CAF50;">Duration: ${duracionM2}</div>
                            </div>

                        </div>
                    </div>`;
            }).join('');

        } else {
            serviciosDiv.innerHTML = 'No services for this vehicle on this date.';
        }
    } catch (err) {
        console.error("Error loading services:", err);
        document.getElementById('servicios-historico').innerHTML = 'Error loading services.';
    }

};


document.getElementById('fecha-historico').addEventListener('change', function () {
    const mapaDiv = document.getElementById('mapa-historico');
    if (mapaDiv._leafletMap) {
        mapaDiv._leafletMap.remove();
        mapaDiv._leafletMap = null;
        mapaDiv.innerHTML = '';
    }
});

document.getElementById('vehiculo-historico').addEventListener('change', function () {
    const mapaDiv = document.getElementById('mapa-historico');
    if (mapaDiv._leafletMap) {
        mapaDiv._leafletMap.remove();
        mapaDiv._leafletMap = null;
        mapaDiv.innerHTML = '';
    }
});

// Función para animar ruta histórica en el mapa
function animarRutaHistorica(truck, historial) {
    if (!window.map) return;
    // Eliminar marcador y polilínea previos si existen
    if (window.gpsMarkers[truck]) {
        window.map.removeLayer(window.gpsMarkers[truck]);
        delete window.gpsMarkers[truck];
    }
    if (window.gpsPolylines[truck]) {
        window.map.removeLayer(window.gpsPolylines[truck]);
        delete window.gpsPolylines[truck];
    }
    let color = '#2196F3';
    let polyline = L.polyline([], {
        color: color,
        weight: 3,
        opacity: 1,
        dashArray: '2,6',
        lineCap: 'round',
        lineJoin: 'round'
    }).addTo(window.map);
    window.gpsPolylines[truck] = polyline;
    let marker = null;
    let indice = 0;
    function animar() {
        if (indice >= historial.length) return;
        const punto = historial[indice];
        
        // SVG original intacto (como lo tenías antes)
        const svgHtml = `
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="22" viewBox="0 0 122.88 57.75">
            <path d="M55.2,0.01h-2c-4.58,0-10.98-0.3-14.66,2.81C32.26,7.29,27.4,15.21,22.1,20.38 c-4.3,0.56-14.26,2.03-16.55,4.07C2.9,26.81,2.93,34.4,2.97,37.62c-4.92-0.1-2.93,11.81,0.26,12.49h6.85 c-4.4-26.18,32.92-22.94,27.3,0h38.19c-5.76-21.96,31.01-27.57,27.47-0.21c6.53-0.02,10.06-0.1,16.89,0 c2.71-0.62,2.97-2.13,2.97-5.75l-2.66-0.33l0.08-1.5c0.03-0.89,0.06-1.77,0.09-2.65c0.16-5.81,0.14-11.43-0.19-16.74H59.77V5.58 C59.87,1.86,58.24,0.12,55.2,0.01L55.2,0.01z M89.87,41.17c3.02,0,5.46,2.45,5.46,5.46c0,3.02-2.45,5.46-5.46,5.46 c-3.02,0-5.46-2.45-5.46-5.46C84.41,43.62,86.85,41.17,89.87,41.17L89.87,41.17z" fill="${color}" />
        </svg>`;

        if (!marker) {
            marker = L.marker([punto.lat, punto.lng], {
                icon: L.divIcon({
                    html: svgHtml,
                    className: `pickup-marker-historico-${truck}`,
                    iconSize: [48, 22],
                    iconAnchor: [24, 11]
                }),
                title: truck
            }).addTo(window.map);
            window.gpsMarkers[truck] = marker;
        } else {
            marker.setLatLng([punto.lat, punto.lng]);
        }
        const latlngs = polyline.getLatLngs();
        latlngs.push([punto.lat, punto.lng]);
        polyline.setLatLngs(latlngs);
        indice++;
        setTimeout(animar, window.ANIMACION_INTERVALO_MS);
    }
    animar();
}

/**
 * Monitorea continuamente la posición del vehículo para detectar:
 * - Llegada/salida de cliente (geofencing)
 * - Regreso a sede (cierre de servicio)
 * - Paradas prolongadas no planificadas
 */
function iniciarGeoferenciaContinua(truck, marker) {
    const CHECK_INTERVAL = 15000; // Cada 15 segundos

    // Variables de estado
    let ultimaPosicion = null;
    let inicioDetencion = null; // Momento en que comenzó a estar detenido
    let ultimaDeteccionCliente = false;
    let ultimaDeteccionSede = false;
    let servicioIniciado = false;

    // === NUEVO: Al iniciar, intentar reconstruir estado ===
    async function inicializarEstado() {
        try {
            // Obtener últimos 2 puntos para ver si estaba detenido
            const res = await fetch('/app/ajax/motor2Ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_motor2: 'obtener_ultimo_historial',
                    vehicle_id: truck,
                    limit: 2
                })
            });

            if (!res.ok) return;

            const data = await res.json();
            if (!Array.isArray(data.historial) || data.historial.length < 2) return;

            const [actual, anterior] = data.historial; // Último y penúltimo

            const lat = parseFloat(actual.lat);
            const lng = parseFloat(actual.lng);
            const dist = calcularDistanciaMetros(lat, lng, parseFloat(anterior.lat), parseFloat(anterior.lng));

            // Si no se movió → considerar que ya está detenido
            if (dist <= 10) {
                console.log(`🟢 ${truck} detectado como DETENIDO al iniciar`);
                ultimaPosicion = { lat: parseFloat(anterior.lat), lng: parseFloat(anterior.lng) };
                inicioDetencion = new Date(anterior.timestamp); // Usar timestamp real
            } else {
                ultimaPosicion = { lat, lng };
            }
        } catch (err) {
            console.warn(`No se pudo reconstruir estado para ${truck}`, err);
        }
    }

    // Iniciar monitoreo periódico
    setInterval(async () => {
        try {
            // Primera ejecución: inicializar estado
            if (!ultimaPosicion) {
                await inicializarEstado();
                return; // Saltar resto, se reinicia en próximo ciclo
            }            

            // === Obtener última posición real del vehículo ===
            const formData = new FormData();
            formData.append('modulo_motor2', 'obtener_ultima_posicion');
            formData.append('vehicle_id', truck);

            const res = await fetch('/app/ajax/motor2Ajax.php', {
                method: 'POST',
                body: formData
            });
            
            if (res.status === 204) {
                //console.log(`🟡 ${truck}: Sin señal GPS - usando estado anterior o sede`);
                
                // Opción 1: Mantener última posición conocida
                if (ultimaPosicion) {
                    // Continuar monitoreo desde último punto
                    const popupMsg = `<b>${truck}</b><br><span style="color:#f44336;">🔴 GPS Offline</span>`;
                    if (marker && marker.getPopup()) {
                        marker.setPopupContent(popupMsg);
                    }
                } 
                // Opción 2: Forzar regreso a sede solo si se requiere
                else {
                    const [lat, lng] = SEDE_LATLNG;
                    const popupMsg = `<b>${truck}</b><br><span style="color:#ff9800;">🏠 Inactive at HQ</span>`;
                    marker.setLatLng([lat, lng]);
                    if (marker.getPopup()) {
                        marker.setPopupContent(popupMsg);
                    }
                    ultimaPosicion = { lat, lng };
                }
                return; // Salir sin error
            }

            if (!res.ok) return;

            const data = await res.json();
            if (!data.lat || !data.lng) {
                console.warn(`🟡 ${truck}: No hay coordenadas válidas`);
                return;
            }

            const lat = parseFloat(data.lat);
            const lng = parseFloat(data.lng);
            const hora_ser = data.hora_ser;

            // === Verificar si está detenido ===
            const detenidoPorPosicion = ultimaPosicion &&
                calcularDistanciaMetros(lat, lng, ultimaPosicion.lat, ultimaPosicion.lng) <= 10;

            // Actualizar momento de detención
            if (detenidoPorPosicion && !inicioDetencion) {
                inicioDetencion = new Date();
            } else if (!detenidoPorPosicion) {
                inicioDetencion = null;
            }

            // Calcular tiempo detenido en minutos
            const tiempoDetenidoMin = inicioDetencion
                ? (new Date() - inicioDetencion) / 1000 / 60
                : 0;

            // Verificar si supera el umbral configurado
            const excedeUmbral = tiempoDetenidoMin >= window.APP_CONFIG.umbral_minutos;

            // Buscar servicio asignado
            const servicio = window.serviciosData?.find(s => s.truck === truck);

            // === OBTENER CREW SIEMPRE QUE HAYA SERVICIO ASIGNADO ===
            const crew_act = servicio?.crew_integrantes || [];
            const crewHtml = Array.isArray(crew_act) && crew_act.length > 0
                ? crew_act.map(member => {
                    const rol = member.responsabilidad === 'D'
                        ? '<span title="Driver" style="color:#FFD700;">🚚</span>'
                        : '<span title="Operator" style="color:#87CEEB;">🛠️</span>';
                    return `
                        <span style="
                            display: block;
                            background: #333; 
                            color: #fff; 
                            padding: 4px 6px; 
                            border-radius: 4px; 
                            font-size: 0.85em; 
                            margin: 2px 0;
                            white-space: nowrap;
                            text-align: center;
                        ">
                            ${rol} ${member.nombre_completo}
                        </span>
                    `;
                  }).join('')
                : '<span style="color:#aaa; font-style:italic;">No crew assigned</span>';

            // Construir mensaje del popup
            let popupMsg = `<b>${truck}</b><br>`;

            popupMsg += `<div style="margin-top:6px; font-size:0.85em;"><b>Crew:</b></div><div style="margin-top:2px;">${crewHtml}</div>`;

            // === 1. DETECCIÓN DE CLIENTE (Inicio de servicio) ===
            if (servicio && typeof servicio.lat === 'number' && typeof servicio.lng === 'number') {
                const distanciaCliente = calcularDistanciaMetros(lat, lng, servicio.lat, servicio.lng);

                // Inicio de servicio: cerca del cliente + detenido + tiempo suficiente
                if (distanciaCliente <= window.APP_CONFIG.umbral_metros && detenidoPorPosicion && excedeUmbral && !ultimaDeteccionCliente && !servicioIniciado) {
                    console.log(`🟢 ${truck} inició servicio en cliente ${servicio.cliente}`);
                    ultimaDeteccionCliente = true;
                    servicioIniciado = true;

                    // Disparar evento global
                    window.dispatchEvent(new CustomEvent('servicioIniciado', {
                        detail: {
                            id_servicio: servicio.id_servicio,
                            id_cliente: servicio.id_cliente,
                            id_truck: truck,
                            lat: lat,
                            lng: lng,
                            hora: hora_ser
                        }
                    }));

                    popupMsg += `<br>✅ Servicing client: <b>${servicio.cliente}</b>`;
                }

                // Fin detección si se aleja
                if (distanciaCliente > window.APP_CONFIG.umbral_metros) {
                    ultimaDeteccionCliente = false;
                }
            }

            // === 2. DETECCIÓN DE SEDE (Cierre de servicio) ===
            const distanciaSede = calcularDistanciaMetros(lat, lng, SEDE_LATLNG[0], SEDE_LATLNG[1]);

            if (distanciaSede <= window.APP_CONFIG.umbral_metros && detenidoPorPosicion && excedeUmbral && !ultimaDeteccionSede && servicioIniciado) {
                console.log(`🔴 ${truck} cerró servicio en sede`);
                ultimaDeteccionSede = true;
                servicioIniciado = false;

                window.dispatchEvent(new CustomEvent('servicioCerrado', {
                    detail: {
                        id_servicio: servicio?.id_servicio,
                        id_cliente: servicio?.id_cliente,
                        id_truck: truck,
                        lat: lat,
                        lng: lng,
                        hora: hora_ser
                    }
                }));

                popupMsg += `<br>⏹️ Stopped at headquarters<br>Service finished`;
            }

            if (distanciaSede > window.APP_CONFIG.umbral_metros) {
                ultimaDeteccionSede = false;
            }

            // === 3. PARADA OPERATIVA NO PLANIFICADA ===
            if (detenidoPorPosicion && excedeUmbral && !ultimaDeteccionCliente && !ultimaDeteccionSede) {
                // Solo disparar una vez al superar el umbral
                if (!window.estadoActividades?.[truck]?.paradaOperativaActiva) {
                    console.log(`🟡 ${truck} inició parada operativa en (${lat.toFixed(6)}, ${lng.toFixed(6)})`);

                    window.dispatchEvent(new CustomEvent('paradaOperativaIniciada', {
                        detail: {
                            vehicle_id: truck,
                            lat: lat,
                            lng: lng,
                            hora_inicio: hora_ser
                        }
                    }));

                    // Marcar como activa
                    window.estadoActividades = window.estadoActividades || {};
                    window.estadoActividades[truck] = {
                        ...window.estadoActividades[truck],
                        paradaOperativaActiva: true,
                        lat_inicio_parada: lat,
                        lng_inicio_parada: lng
                    };
                }
            }

            // === 4. FINALIZAR PARADA OPERATIVA ===
            if (!detenidoPorPosicion && window.estadoActividades?.[truck]?.paradaOperativaActiva) {
                console.log(`✅ ${truck} reanudó movimiento. Finalizando parada operativa.`);

                window.dispatchEvent(new CustomEvent('paradaOperativaFinalizada', {
                    detail: {
                        vehicle_id: truck,
                        lat_fin: lat,
                        lng_fin: lng,
                        hora_fin: hora_ser
                    }
                }));

                // Limpiar estado
                window.estadoActividades[truck].paradaOperativaActiva = false;
            }

            // === 5. ESTADO GENERAL EN POPUP (solo si no tiene estado específico) ===
            // if (!popupMsg.includes('Servicing client') && !popupMsg.includes('Stopped at headquarters')) {
            //     if (detenidoPorPosicion) {
            //         const min = tiempoDetenidoMin.toFixed(1);
            //         popupMsg += `<br>⏸️ Detenido (${min} min) en (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
            //     } else {
            //         popupMsg += `<br>🚚 In transit`;
            //     }
            // }

            if (detenidoPorPosicion && inicioDetencion) {
                const diffSeg = Math.floor((new Date() - inicioDetencion) / 1000);
                const mins = Math.floor(diffSeg / 60);
                const segs = diffSeg % 60;
                popupMsg += `Stopped.<br>Time: <b>${mins} min ${segs}s</b>`;
            } else {
                popupMsg += "In transit...";
            }

            // Actualizar popup del marcador
            if (marker && marker.getPopup()) {
                marker.setPopupContent(popupMsg);
            }

            // Guardar última posición
            ultimaPosicion = { lat, lng };

        } catch (err) {
            console.warn(`Error en geoferencia continua para ${truck}:`, err.message);
        }
    }, CHECK_INTERVAL);
}

// 1. Parada operativa iniciada
window.addEventListener('paradaOperativaIniciada', async (e) => {
    const { vehicle_id, lat, lng, hora_inicio } = e.detail;
    try {
        const res = await fetch('/app/ajax/paradasAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                modulo: 'iniciar_parada',
                vehicle_id: vehicle_id,
                lat: lat,
                lng: lng,
                hora_inicio: hora_inicio
            })
        });
        const data = await res.json();
        if (data.success) console.log(`✅ Parada operativa iniciada ID: ${data.id_parada}`);
    } catch (err) {
        console.error('❌ Error al registrar parada:', err);
    }
});

// 2. Parada operativa finalizada
window.addEventListener('paradaOperativaFinalizada', async (e) => {
    const { vehicle_id, lat_fin, lng_fin, hora_fin } = e.detail;
    try {
        const res = await fetch('/app/ajax/paradasAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                modulo: 'cerrar_parada',
                vehicle_id,
                lat_fin,
                lng_fin,
                hora_fin
            })
        });
        const data = await res.json();
        if (data.success) console.log(`✅ Parada operativa finalizada`);
    } catch (err) {
        console.error('❌ Error al cerrar parada:', err);
    }
});