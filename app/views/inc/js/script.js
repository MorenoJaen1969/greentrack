/**
 * GREENTRACK LIVE – DASHBOARD FINAL
 * Autor: Mario Moreno
 * Fecha: Agosto 2025
 * 
 * Funcionalidades:
 * - Carrusel dinámico con reposicionamiento físico
 * - Mapa Leaflet con marcadores fijos
 * - Seguimiento en tiempo real del CREW desde GPS real
 * - Modal clickeable con pausa del carrusel
 * - Historial de cliente y acciones en tiempo real
 * - Daily Status con RESUME y colores
 * - Select Client para abrir servicio directamente
 * - Polling si no hay servicios del día
 * - Todo en inglés
 */

// Función para aplicar efecto visual al marcador
// Al inicio de tu script, después de crear el mapa
window.mapMarkers = {};

// === Estado de actividades en curso ===
window.estadoActividades = {}; // truck → { tipo: 'servicio' | 'parada', id_registro: int, inicio: Date }

// Función para encontrar el contenedor del mapa
function encontrarContenedorMapa() {
    // Buscar por diferentes selectores comunes
    const posiblesSelectores = ['#map', '#map-container', '.map', '.leaflet-container', '#leaflet-map'];

    for (let selector of posiblesSelectores) {
        const elemento = document.querySelector(selector);
        if (elemento) {
            return elemento;
        }
    }

    // Si no encuentra por selectores, buscar el primer div con clases de Leaflet
    const divs = document.querySelectorAll('div');
    for (let div of divs) {
        if (div.className && (div.className.includes('leaflet') || div.className.includes('map'))) {
            return div;
        }
    }

    // Último recurso: usar el body
    return document.body;
}

function crearEfectoRadar(idServicio) {
    // Verificar que el marcador exista
    if (!window.mapMarkers || !window.mapMarkers[idServicio]) {
        console.warn(`Marcador no encontrado para servicio ${idServicio}`);
        return;
    }

    try {
        // Obtener coordenadas del marcador
        const marker = window.mapMarkers[idServicio];
        const latlng = marker.getLatLng();

        // Convertir a coordenadas de pantalla
        const punto = window.map.latLngToContainerPoint(latlng);

        // Encontrar el contenedor del mapa
        const contenedorMapa = encontrarContenedorMapa();

        // Crear tres circunferencias con diferentes características
        for (let i = 0; i < 3; i++) {
            setTimeout(() => {
                const onda = document.createElement('div');
                onda.style.cssText = `
                    position: absolute;
                    border: 4px solid #0066cc;  /* Azul oscuro más grueso */
                    border-radius: 50%;
                    pointer-events: none;
                    left: ${punto.x}px;
                    top: ${punto.y}px;
                    width: 0px;
                    height: 0px;
                    opacity: 0.9;
                    transform: translate(-50%, -50%);
                    z-index: 999;
                    box-shadow: 0 0 10px #0066cc; /* Sombra azul oscuro */
                `;

                // Añadir al contenedor del mapa
                contenedorMapa.appendChild(onda);

                // Animación de crecimiento
                let size = 0;
                const maxSize = 120;
                const interval = setInterval(() => {
                    size += 5;
                    onda.style.width = size + 'px';
                    onda.style.height = size + 'px';
                    onda.style.opacity = 0.9 - (size / maxSize);

                    if (size >= maxSize) {
                        clearInterval(interval);
                        if (onda.parentNode) {
                            onda.parentNode.removeChild(onda);
                        }
                    }
                }, 30);

            }, i * 200); // Espaciar las circunferencias en el tiempo
        }

    } catch (error) {
        console.error('Error al crear efecto radar:', error);
    }
}

function destacarMarcadorEnMapa_3(idServicio) {
    // esto es un Color
    const markerId = `marker-${idServicio}`;
    const markerElement = document.getElementById(markerId);

    if (markerElement) {
        // Guardar color original
        const colorOriginal = markerElement.style.background || '#ff0000';

        // Efecto de cambio de color
        let cambios = 0;
        const maxCambios = 8;

        const intervaloColor = setInterval(() => {
            if (cambios >= maxCambios) {
                markerElement.style.background = colorOriginal;
                markerElement.style.transition = 'background 0.3s ease';
                clearInterval(intervaloColor);
                return;
            }

            if (cambios % 2 === 0) {
                markerElement.style.background = '#ffff00'; // Amarillo brillante
            } else {
                markerElement.style.background = '#ff00ff'; // Magenta
            }

            markerElement.style.transition = 'background 0.3s ease';
            cambios++;
        }, 300);
    }
}

function destacarMarcadorEnMapa_2(idServicio) {
    // esto es un Pulso
    const markerId = `marker-${idServicio}`;
    const markerElement = document.getElementById(markerId);

    if (markerElement) {
        // Agregar clase CSS para animación de pulso
        markerElement.style.animation = 'pulsoMarker 2s ease-in-out';
        markerElement.style.zIndex = '1000';

        // Crear estilo CSS dinámico si no existe
        if (!document.getElementById('animacion-pulso-marker')) {
            const style = document.createElement('style');
            style.id = 'animacion-pulso-marker';
            style.textContent = `
                @keyframes pulsoMarker {
                    0% { transform: scale(1); box-shadow: 0 0 5px rgba(0,0,0,0.5); }
                    50% { transform: scale(1.6); box-shadow: 0 0 20px rgba(255,255,0,0.8), 0 0 30px rgba(255,255,0,0.6); }
                    100% { transform: scale(1); box-shadow: 0 0 5px rgba(0,0,0,0.5); }
                }
            `;
            document.head.appendChild(style);
        }

        // Limpiar después de la animación
        setTimeout(() => {
            if (markerElement) {
                markerElement.style.animation = '';
                markerElement.style.zIndex = '1';
            }
        }, 2000);
    }
}

function destacarMarcadorEnMapa(idServicio) {
    const markerId = `marker-${idServicio}`;
    const markerElement = document.getElementById(markerId);

    if (markerElement) {
        // Guardar estilos originales
        const estiloOriginal = {
            transform: markerElement.style.transform || 'scale(1)',
            boxShadow: markerElement.style.boxShadow || '0 0 5px rgba(0,0,0,0.5)',
            zIndex: markerElement.style.zIndex || '1'
        };

        // Aplicar efecto de destello/intermitencia
        let intermitencias = 0;
        const maxIntermitencias = 6; // 3 ciclos completos

        const intervaloDestello = setInterval(() => {
            if (intermitencias >= maxIntermitencias) {
                // Restaurar estilos originales
                markerElement.style.transform = estiloOriginal.transform;
                markerElement.style.boxShadow = estiloOriginal.boxShadow;
                markerElement.style.zIndex = estiloOriginal.zIndex;
                markerElement.style.transition = 'all 0.3s ease';
                clearInterval(intervaloDestello);
                return;
            }

            if (intermitencias % 2 === 0) {
                // Efecto "encendido" - más grande y brillante
                markerElement.style.transform = 'scale(1.8)';
                markerElement.style.boxShadow = '0 0 15px rgba(255,255,255,0.8), 0 0 30px yellow';
                markerElement.style.zIndex = '1000';
            } else {
                // Efecto "apagado" - volver a normal
                markerElement.style.transform = 'scale(1)';
                markerElement.style.boxShadow = estiloOriginal.boxShadow;
                markerElement.style.zIndex = estiloOriginal.zIndex;
            }

            markerElement.style.transition = 'all 0.3s ease';
            intermitencias++;
        }, 400); // Cambia cada 400ms
    }
}

// === Función reutilizable para procesar servicios ===
function procesarYSistema(serviciosRaw) {
    const servicios = serviciosRaw.map(s => ({
        ...s,
        lat: typeof s.lat === 'string' ? parseFloat(s.lat) : s.lat,
        lng: typeof s.lng === 'string' ? parseFloat(s.lng) : s.lng
    }));

    const serviciosValidos = servicios.filter(s =>
        typeof s.lat === 'number' &&
        typeof s.lng === 'number' &&
        !isNaN(s.lat) &&
        !isNaN(s.lng)
    );

    if (serviciosValidos.length === 0) {
        console.error("🔴 No hay servicios con coordenadas válidas después de conversión");
        return;
    }

    window.serviciosData = serviciosValidos;
    carrusel.datos = serviciosValidos;
    actualizarEspacio();

    let indiceCarruselGlobal = 0;
    insertarTarjeta(serviciosValidos[indiceCarruselGlobal]);
    indiceCarruselGlobal = (indiceCarruselGlobal + 1) % serviciosValidos.length;

    if (carrusel.intervalo) clearInterval(carrusel.intervalo);
    carrusel.intervalo = setInterval(() => {
        insertarTarjeta(serviciosValidos[indiceCarruselGlobal]);
        indiceCarruselGlobal = (indiceCarruselGlobal + 1) % serviciosValidos.length;
    }, config.intervaloCarrusel);

    serviciosValidos.forEach(s => {
        if (s.lat && s.lng) {
            const marker = L.marker([s.lat, s.lng], {
                icon: L.divIcon({
                    html: `
                        <div style="background:${s.crew_color_principal};width:16px;height:16px;border-radius:50%;border:2px solid black;box-shadow:0 0 5px rgba(0,0,0,0.5);"></div>`,
                    className: '',
                    iconSize: [16, 16],
                    iconAnchor: [8, 8]
                })
            });

            window.mapMarkers[s.id_servicio] = marker;

            marker.on('add', function () {
                const iconElement = this.getElement();
                if (iconElement) {
                    iconElement.id = `marker-${s.id_servicio}`;
                    iconElement.style.transition = 'all 0.3s ease';
                }
            });

            marker.addTo(window.map);
            marker.bindPopup(`<b>${s.cliente}</b><br>${s.direccion || 'No address'}<br><div class="tit_d_grid"><b>Crew:</b> ${s.truck || 'N/A'}</div>`);
        }
    });

    const event = new Event('serviciosCargados');
    document.dispatchEvent(event);
    console.log("✅ Evento 'serviciosCargados' lanzado");
}

function inicializarMapa() {
    const contenedor = 'live-map';
    if (!document.getElementById(contenedor)) {
        console.warn('❌ No se encontró el contenedor del mapa');
        return;
    }

    // Si ya existe un mapa, eliminarlo antes de crear uno nuevo
    if (window.map) {
        window.map.remove();
        delete window.map;
    }

    const map = L.map(contenedor).setView([30.3204272, -95.4217815], 12);

    // ✅ Asegurar que APP_CONFIG existe
    if (!window.APP_CONFIG || !window.APP_CONFIG.mapa_base) {
        console.warn('⚠️ APP_CONFIG o mapa_base no definido. Usando ESRI por defecto');
        window.APP_CONFIG = window.APP_CONFIG || {};
        window.APP_CONFIG.mapa_base = 'ESRI'; // valor por defecto
    }

    const tipo = window.APP_CONFIG.mapa_base.toUpperCase(); // ahora seguro

    console.log('🌍 Inicializando mapa con capa:', tipo);

    switch (tipo) {
        case 'OSM':
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            break;

        case 'ESRI':
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri &mdash; Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community',
                maxZoom: 20
            }).addTo(map);
            break;

        default:
            console.warn(`⚠️ Tipo de mapa desconocido: ${tipo}. Usando OSM por defecto.`);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);
            break;
    }

    // Desactivar zoom con rueda
    map.scrollWheelZoom.disable();

    // Guardar referencia global
    window.map = map;

    console.log('✅ Mapa inicializado con éxito');
}

function ajustarMarcadoresCercanos(serviciosValidos) {
    const umbralMetros = 5; // Considerar "cercanos" si están a menos de 5m
    const grupos = new Map(); // Clave: lat,lng redondeado → valor: array de servicios

    // === 1. Agrupar servicios por posición similar ===
    serviciosValidos.forEach(s => {
        if (!s.lat || !s.lng) return;

        // Redondear coordenadas para agrupar puntos similares (~10 cm)
        const latKey = Math.round(s.lat * 1e6);
        const lngKey = Math.round(s.lng * 1e6);
        const key = `${latKey},${lngKey}`;

        if (!grupos.has(key)) {
            grupos.set(key, []);
        }
        grupos.get(key).push(s);
    });

    // === 2. Para cada grupo con múltiples vehículos, aplicar offset ===
    grupos.forEach((grupo, key) => {
        if (grupo.length <= 1) return; // No necesita ajuste

        const [latKey, lngKey] = key.split(',').map(Number);
        const latBase = latKey / 1e6;
        const lngBase = lngKey / 1e6;

        // Distribuir en un círculo pequeño (radio ~3 metros)
        const radioMetros = 3;
        const anguloPorVehiculo = (2 * Math.PI) / grupo.length;

        grupo.forEach((servicio, index) => {
            const angulo = index * anguloPorVehiculo;
            const { lat, lng } = calcularOffset(latBase, lngBase, angulo, radioMetros);

            // Actualizar temporalmente las coordenadas del marcador
            servicio.lat_offset = lat;
            servicio.lng_offset = lng;
        });
    });
}

// === Ayuda: Calcular nuevo punto a cierta distancia y ángulo ===
function calcularOffset(lat, lng, bearing, distanceMeters) {
    const R = 6371000; // Radio de la Tierra en metros
    const δ = distanceMeters / R;
    const θ = bearing;

    const φ1 = lat * Math.PI / 180;
    const λ1 = lng * Math.PI / 180;

    const φ2 = Math.asin(
        Math.sin(φ1) * Math.cos(δ) +
        Math.cos(φ1) * Math.sin(δ) * Math.cos(θ)
    );

    let λ2 = λ1 + Math.atan2(
        Math.sin(θ) * Math.sin(δ) * Math.cos(φ1),
        Math.cos(δ) - Math.sin(φ1) * Math.sin(φ2)
    );

    λ2 = (λ2 + 3 * Math.PI) % (2 * Math.PI) - Math.PI; // Normalizar a -180..180

    return {
        lat: φ2 * 180 / Math.PI,
        lng: λ2 * 180 / Math.PI
    };
}

document.addEventListener('DOMContentLoaded', async () => {
    // === Sincronizar cambios con backend cuando se detecta inicio/cierre por geofencing ===
    async function sincronizarEstadoGPS(id_servicio, tipo, hora) {
        // tipo: 'inicio' o 'fin'
        try {
            const data = {
                modulo_servicios: 'actualizar_hora_gps',
                id_servicio: id_servicio
            };

            if (tipo === 'inicio') {
                data.hora_inicio_gps = hora;
            } else if (tipo === 'fin') {
                data.hora_fin_gps = hora;
                // Calcular duración y enviar tiempo_servicio
                const servicio = window.serviciosData?.find(s => s.id_servicio == id_servicio);

                let inicio = servicio?.hora_inicio_gps || servicio?.hora_aviso_usuario;
                if (inicio && hora) {
                    const t1 = new Date(inicio);
                    const t2 = new Date(hora);
                    let diff = Math.abs(t2 - t1) / 1000; // segundos
                    const h = String(Math.floor(diff / 3600)).padStart(2, '0');
                    const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
                    const s = String(Math.floor(diff % 60)).padStart(2, '0');
                    data.tiempo_servicio = `${h}:${m}:${s}`;
                }
            }
            const res = await fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const resp = await res.json();
            if (resp.success) {
                console.log(`✅ GPS time updated (${tipo}) for service ${id_servicio}`);

                // === ACTUALIZAR EL SERVICIO EN serviciosData ===
                const servicio = window.serviciosData?.find(s => s.id_servicio == id_servicio);
                if (servicio) {
                    if (tipo === 'inicio') {
                        servicio.hora_inicio_gps = hora;
                    } else if (tipo === 'fin') {
                        servicio.hora_fin_gps = hora;
                        servicio.tiempo_servicio = data.tiempo_servicio;
                    }
                }

                // === ACTUALIZAR STATUS M2 EN EL MARCADOR ===
                actualizarStatusM2(id_servicio);

            } else {
                console.warn(`⚠️ Error updating GPS time: ${resp.message}`);
            }
        } catch (err) {
            console.error('Error updating GPS time with backend:', err);
        }
    }

    window.addEventListener('servicioIniciado', function (e) {
        const { id_servicio, hora } = e.detail;

        // 1. Sincronizar con el backend
        sincronizarEstadoGPS(id_servicio, 'inicio', hora);

        // 2. Actualizar estado local
        const servicio = carrusel.datos.find(s => s.id_servicio == id_servicio);
        if (servicio) {
            servicio.hora_aviso_usuario = hora;
            servicio.estado_servicio = 'inicio_actividades';
            servicio.finalizado = false;

            actualizarTarjeta(servicio);
            actualizarCeldaActividad(servicio);
            actualizarCeldaActividadGps(servicio);

            console.log(`🟢 Servicio iniciado por geofencing: ${id_servicio}`);
        }
    });

    window.addEventListener('servicioCerrado', function (e) {
        const { id_servicio, hora } = e.detail;

        // 1. Sincronizar con el backend
        console.log("Proceso para finalizar el servicio");
        sincronizarEstadoGPS(id_servicio, 'fin', hora);

        // 2. Actualizar estado local
        const servicio = carrusel.datos.find(s => s.id_servicio == id_servicio);
        if (servicio) {
            servicio.hora_finalizado = hora;
            servicio.estado_servicio = 'finalizado';
            servicio.finalizado = true;

            actualizarTarjeta(servicio);
            actualizarCeldaActividad(servicio);
            actualizarCeldaActividadGps(servicio);

            console.log(`🔴 Servicio cerrado por geofencing: ${id_servicio}`);
        }
    });

    console.log("🟢 GreenTrack Live: Iniciando dashboard");
    let servicioTemporal = null;

    // === 1. Configuración ===
    const config = {
        separacion: 10,
        intervaloCarrusel: 6000,
        intervaloGPS: 3000
    };

    // === 2. Estructura del carrusel ===
    const carrusel = {
        contenedor: document.getElementById('servicio-carrusel'),
        datos: [],
        elementos: [],
        TOTAL_DISPLAY: 0,
        espacioUsado: 0,
        intervalo: null
    };

    if (!carrusel.contenedor) {
        console.error("❌ No se encontró #servicio-carrusel");
        return;
    }

    // === 3. Inicializar mapa ===
    let map;
    try {

        // ✅ Ahora sí, puedes inicializar el mapa
        console.log("Inicializando Mapa");
        inicializarMapa();

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
        }).addTo(window.map);

        sedeMarker.bindPopup(`
            <b>Sergio's Landscape</b><br>
            <span style="font-size: 0.9em; color: #555;">Headquarters • Starting Point</span>
        `);

    } catch (e) {
        console.error("❌ Error al cargar el mapa:", e);
        return;
    }

    // === 4. Iconos SVG para evidencias ===
    const ICONOS = {
        audio: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 06 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>',
        fotos: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>',
        videos: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>'
    };

    // === 5. Funciones auxiliares ===
    const truncar = (texto, max) => {
        return texto.length > max ? texto.substring(0, max) + '...' : texto;
    };

    function medirAltura(card) {
        const clon = card.cloneNode(true);
        clon.style.position = 'absolute';
        clon.style.visibility = 'hidden';
        clon.style.left = '-9999px';
        document.body.appendChild(clon);
        const h = clon.scrollHeight;
        document.body.removeChild(clon);
        return h;
    }

    // === NUEVA: Actualizar tarjeta existente ===
    function actualizarTarjeta(servicio) {
        const card = carrusel.contenedor.querySelector(`[data-id="${servicio.id_servicio}"]`);
        if (card) {
            const nueva = crearTarjeta(servicio);
            card.replaceWith(nueva);
            const index = carrusel.elementos.findIndex(el => el.dataset.id == servicio.id_servicio);
            if (index !== -1) {
                carrusel.elementos[index] = nueva;
            }
            reposicionarElementos();
        }
    }

    function crearTarjeta(servicio) {
        const card = document.createElement('div');
        card.className = 'item-arrendador';
        card.dataset.id = servicio.id_servicio;
        card.style.cursor = 'pointer';
        card.title = 'Click to view details';

        const nombre = truncar(servicio.cliente, 20);
        const first = nombre[0] || '?';
        const rest = nombre.slice(1);

        let badge = '';
        if (servicio.estado_visita === 'finalizado') {
            badge = '<span class="badge-sin-programar" title="Client not being processed">✅</span>';
        } else if (servicio.estado_visita === 'replanificado') {
            badge = '<span class="badge-replanificado" title="Client attended outside scheduled day">🔄</span>';
        } else if (servicio.estado_visita === 'sin_programar') {
            badge = '<span class="badge-sin-programar" title="Client without scheduled day">❓</span>';
        } else if (servicio.estado_visita === 'cancelado') {
            badge = '<span class="badge-cancelado" title="Customer Service Cancelled">❌</span>';
        }

        // --- Inicializar crew_integrantes ---
        const crewIntegrantes = Array.isArray(servicio.crew_integrantes)
            ? servicio.crew_integrantes
            : [];

        // Obtener hora de aviso
        const horaInicio = servicio.hora_aviso_usuario ? new Date(servicio.hora_aviso_usuario) : null;

        // === Calcular duración si hay inicio y fin ===
        let duracionTexto = '—';
        if (servicio.hora_aviso_usuario && servicio.hora_finalizado) {
            const inicio = new Date(servicio.hora_aviso_usuario).getTime();
            const fin = new Date(servicio.hora_finalizado).getTime();
            const diffMin = Math.round((fin - inicio) / 60000); // minutos
            duracionTexto = `${diffMin} min`;
        }

        // Formatear horas
        const inicioFormato = servicio.hora_aviso_usuario
            ? new Date(servicio.hora_aviso_usuario).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            : '—';

        const finFormato = servicio.hora_finalizado
            ? new Date(servicio.hora_finalizado).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            : '—';

        let crewGridHtml = '<div class="crew-grid">';
        const totalCeldas = 4;
        for (let i = 0; i < totalCeldas; i++) {
            if (i < crewIntegrantes.length && crewIntegrantes[i].nombre_completo) {
                const p = crewIntegrantes[i];
                crewGridHtml += `
                    <div class="member" style="background-color: ${p.color || '#666'};">
                        <span>${p.crew} - ${p.nombre}</span>
                        <span>${p.apellido}</span>
                    </div>`;
            } else {
                crewGridHtml += '<div class="member" style="background-color: #ccc;"></div>';
            }
        }
        crewGridHtml += '</div>';

        card.innerHTML = `
            <div class="informacion">
                <span class="nombre text-alto elem-carr">
                    <span class="first-letter">${first}</span>
                    <span class="rest-letter">${rest}</span>
                </span>
                <span class="local" style="background-color: ${servicio.crew_color_principal};">Crew ${servicio.truck || 'N/A'}</span>
                ${badge}
            </div>

            <!-- Cuadrícula 2x2 -->
            <div class="info-extra">
                <div class="grid-2x2">
                    
                    <!-- 1,1: Crew (subgrid 2x2) -->
                    <div class="cell crew-cell">
                        <div class="tit_d_grid">Crew</div>
                        ${crewGridHtml}
                    </div>

                    <!-- 1,2: Activity -->
                    <div class="cell aviso-cell" id="aviso-cell-${servicio.id_servicio}">
                        <div class="tit_d_grid">Activity</div>
                        <div class="hora" style="line-height: 1.2;">
                            <span id="start-${servicio.id_servicio}" class="time-start"></span><br>
                            <span id="end-${servicio.id_servicio}" class="time-end"></span><br>
                            <span id="duration-${servicio.id_servicio}" class="time-duration"><b>Duration:</b> Waiting for data to calculate</span>
                        </div>
                    </div>

                    <!-- 2,1: Tiempo Servicio -->
                    <div class="cell tiempo-cell">
                        <div class="tit_d_grid">Time</div>
                        <span class="contador">${horaInicio ? '00:00:00' : '—'}</span>
                    </div>

                    <!-- 2,2: GPS -->
                    <div class="cell gps-cell" data-id="${servicio.id_servicio}">
                        <div class="tit_d_grid">Activity GPS</div>
                        <div class="hora" style="line-height: 1.2;">
                            <span class="time-start">${servicio.hora_inicio_gps ? new Date(servicio.hora_inicio_gps).toLocaleTimeString() : '—'}</span>
                            <br>
                            <span class="diff"></span>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // === Aplicar clases para overlay en mitad derecha (CSS) ===
        if (servicio.finalizado) {
            card.classList.add('finalizado');
        } else if (servicio.estado_visita === 'replanificado') {
            card.classList.add('reprogramado');
        } else if (servicio.estado_visita === 'cancelado') {
            card.classList.add('cancelado');
        }

        // === 2. Iniciar contador si hay hora de aviso y no está finalizado ===
        const contador = card.querySelector('.tiempo-cell .contador');
        if (servicio.hora_aviso_usuario && !servicio.finalizado) {
            iniciarContador(contador, servicio.hora_aviso_usuario);
        } else if (servicio.finalizado) {
            contador.textContent = 'Completed';
        } else {
            contador.textContent = '—';
        }

        // --- Evidencias ---
        if (servicio.finalizado && servicio.evidencias?.length) {
            const ev = document.createElement('div');
            ev.className = 'evidencias';
            servicio.evidencias.forEach(tipo => {
                const span = document.createElement('span');
                span.innerHTML = ICONOS[tipo].replace('stroke="currentColor"', 'stroke="#000"');
                ev.appendChild(span);
            });
            card.appendChild(ev);
        }

        card.addEventListener('click', (e) => {
            e.stopPropagation();

            // === CLICK: Abrir modal ===
            // if (document.querySelector('.modal-overlay')) {
            abrirModalDetalles(servicio, false);
            // }

            // === CLICK: Simular clic en el marcador del mapa ===
            const markerId = `marker-${servicio.id_servicio}`;
            const elementoMarcador = document.getElementById(markerId);

            if (elementoMarcador) {
                elementoMarcador.click();
            }
        });

        return card;
    }

    // === 6. Inicializar espacio disponible ===
    function actualizarEspacio() {
        carrusel.TOTAL_DISPLAY = carrusel.contenedor.clientHeight;
        carrusel.espacioUsado = 0;
    }

    // === 7. Reposicionar todos los elementos desde el fondo ===
    function reposicionarElementos() {
        let offset = config.separacion;
        for (let i = 0; i < carrusel.elementos.length; i++) {
            const el = carrusel.elementos[i];
            if (!el || !carrusel.contenedor.contains(el)) continue;
            const altura = el.scrollHeight;
            el.style.position = 'absolute';
            el.style.left = '0';
            el.style.right = '0';
            el.style.transition = 'bottom 0.8s ease, opacity 0.8s ease';
            el.style.bottom = `${offset}px`;
            offset += altura + config.separacion;
        }
    }

    // === 8. Insertar nueva tarjeta ===
    function insertarTarjeta(servicio) {
        const card = crearTarjeta(servicio);

        // === AGREGAR EL ATRIBUTO DATA-SERVICIO-ID ===
        // Esta es la línea crucial que falta
        card.setAttribute('data-servicio-id', servicio.id_servicio);
        // === FIN DE LA MODIFICACIÓN ===

        const altura = medirAltura(card);
        const requerido = altura + config.separacion;

        if (carrusel.espacioUsado + requerido > carrusel.TOTAL_DISPLAY && carrusel.elementos.length > 0) {
            const antiguo = carrusel.elementos.pop();
            if (antiguo && antiguo.parentNode) {
                antiguo.remove();
            }
            const h = antiguo ? antiguo.scrollHeight : 0;
            carrusel.espacioUsado -= h + config.separacion;
        }

        card.style.position = 'absolute';
        card.style.left = '0';
        card.style.right = '0';
        card.style.bottom = '-200px';
        card.style.opacity = '0';
        card.style.transition = 'none';

        carrusel.contenedor.appendChild(card);
        carrusel.elementos.unshift(card);
        carrusel.espacioUsado += requerido;

        reposicionarElementos();

        setTimeout(() => {
            card.style.opacity = '1';
            reposicionarElementos();
        }, 50);

        // Agregar el efecto visual al marcador correspondiente
        if (servicio && servicio.id_servicio) {
            setTimeout(() => {
                crearEfectoRadar(servicio.id_servicio);
            }, 100); // Pequeño delay para asegurar que el DOM esté listo
        }
    }

    // === 9. CARGAR SERVICIOS Y EMPEZAR ===
    try {
        const res = await fetch('/app/ajax/serviciosAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ modulo_servicios: 'listar' })
        });

        if (!res.ok) throw new Error('HTTP ' + res.status);

        const respuesta = await res.json();
        const serviciosRaw = respuesta.servicios || [];
        const vehiculosSinServicio = respuesta.vehiculosSinServicio || [];

        if (!Array.isArray(serviciosRaw) || serviciosRaw.length === 0) {
            console.warn("⚠️ No hay servicios programados para hoy. Iniciando polling...");

            // === Mostrar estado visual y comenzar polling ===
            const contenedor = document.getElementById('carrusel') || document.body;
            let intentos = 0;
            const maxIntentos = 120; // 60 minutos (120 x 30 seg)

            // Estilo limpio y visible
            contenedor.style.display = 'flex';
            contenedor.innerHTML = `
                <div style="
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    height: 100%;
                    text-align: center;
                    color: #666;
                    font-family: Arial, sans-serif;
                    padding: 20px;
                    width: 100%;
                ">
                    <h3>⏳ Waiting for today's services...</h3>
                    <p>System will start automatically when services are loaded.</p>
                    <div id="contador-polling" style="font-size: 0.9em; margin-top: 10px;">Attempts: 0</div>
                </div>
            `;

            const vehicles1 = [];
            const seenTrucks1 = new Set();

            const intervaloPolling = setInterval(async () => {
                intentos++;
                document.getElementById('contador-polling').textContent = `Attempts: ${intentos}`;

                try {
                    const res = await fetch('/app/ajax/serviciosAjax.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ modulo_servicios: 'listar' })
                    });

                    if (!res.ok) {
                        console.error(`❌ HTTP ${res.status}: ${res.statusText}`);
                        return;
                    }

                    const respuesta = await res.json();
                    const nuevosServicios = respuesta.servicios || [];
                    const vehiculosSinServicio = respuesta.vehiculosSinServicio || [];

                    if (Array.isArray(nuevosServicios) && nuevosServicios.length > 0) {
                        console.log("✅ Servicios detectados, recargando sistema");
                        clearInterval(intervaloPolling);

                        // Limpiar contenedor
                        contenedor.innerHTML = '';
                        contenedor.style.display = '';

                        // Recargar todo el flujo con los nuevos datos
                        procesarYSistema(nuevosServicios); // ← Llama al mismo flujo principal 
                    }

                    // Vehiculos sin servicios (Deberian estar en el HQ)
                    vehiculosSinServicio.forEach(s => {
                        if (!seenTrucks1.has(s.truck)) {
                            seenTrucks1.add(s.truck);

                            vehicles1.push({
                                id: s.truck,
                                color: s.color_truck,
                            });
                        }
                    });

                    const container1 = document.getElementById('contenedor-vehiculos-sin-servicio');
                    container1.innerHTML = ''; // Limpiar antes

                    vehicles1.forEach(v => {
                        const btn = document.createElement('button');
                        btn.textContent = v.id;
                        btn.style.background = v.color;
                        btn.style.color = getColorContraste(v.color); // Blanco o negro según contraste
                        btn.style.border = 'none';
                        btn.style.borderRadius = '4px';
                        btn.style.padding = '2px 6px';
                        btn.style.fontSize = '0.8em';
                        btn.style.cursor = 'pointer';
                        btn.style.fontWeight = 'bold';
                        btn.style.minWidth = '60px';
                        btn.style.width = '31%';
                        btn.style.textAlign = 'center';

                        // Opcional: tooltip
                        btn.title = `Vehicle: ${v.id}`;

                        container1.appendChild(btn);
                    });

                } catch (err) {
                    console.error("Error en polling:", err);
                }

                if (intentos >= maxIntentos) {
                    clearInterval(intervaloPolling);
                    document.getElementById('contador-polling').textContent = '❌ Timeout: No services loaded after 60 minutes.';
                    console.error("❌ Timeout: No se cargaron servicios después de 60 minutos");
                }
            }, 30000); // Cada 30 segundos
        } else {
            // === CONVERTIR lat y lng a números ===
            const servicios = serviciosRaw.map(s => ({
                ...s,
                lat: typeof s.lat === 'string' ? parseFloat(s.lat) : s.lat,
                lng: typeof s.lng === 'string' ? parseFloat(s.lng) : s.lng
            }));

            // === Validar coordenadas numéricas ===
            const serviciosValidos = servicios.filter(s =>
                typeof s.lat === 'number' &&
                typeof s.lng === 'number' &&
                !isNaN(s.lat) &&
                !isNaN(s.lng)
            );

            if (serviciosValidos.length === 0) {
                console.error("🔴 No hay servicios con coordenadas válidas después de conversión");
            } else {
                console.log(`✅ ${serviciosValidos.length} servicios con coordenadas válidas: `, serviciosValidos);
            }

            // Asignar al contexto global
            window.serviciosData = serviciosValidos;

            // Actualizar carrusel
            carrusel.datos = serviciosValidos;
            actualizarEspacio();

            // Insertar primera tarjeta
            let indiceCarruselGlobal = 0;
            insertarTarjeta(serviciosValidos[indiceCarruselGlobal]);
            indiceCarruselGlobal = (indiceCarruselGlobal + 1) % serviciosValidos.length;

            // Iniciar carrusel
            if (carrusel.intervalo) clearInterval(carrusel.intervalo);
            carrusel.intervalo = setInterval(() => {
                insertarTarjeta(serviciosValidos[indiceCarruselGlobal]);
                indiceCarruselGlobal = (indiceCarruselGlobal + 1) % serviciosValidos.length;
            }, config.intervaloCarrusel);

            // === 10. Ajustar posiciones de marcadores cercanos ===
            ajustarMarcadoresCercanos(serviciosValidos);

            // === 11. Marcadores fijos ===
            const vehicles = [];
            const vehicles1 = [];
            const seenTrucks = new Set();
            const seenTrucks1 = new Set();

            serviciosValidos.forEach(s => {
                if (s.lat && s.lng && s.truck && s.crew_color_principal) {
                    // Solo agregar si no se ha visto este truck
                    if (!seenTrucks.has(s.truck)) {
                        seenTrucks.add(s.truck);

                        vehicles.push({
                            id: s.truck,
                            color: s.crew_color_principal,
                            crew: Array.isArray(s.crew_integrantes) ? [...s.crew_integrantes] : []
                        });
                    }

                    // === Crear marcador ===
                    const useOffset = s.lat_offset && s.lng_offset;

                    // === USAR COORDENADAS AJUSTADAS SI EXISTEN ===
                    const lat = s.lat_offset !== undefined ? s.lat_offset : s.lat;
                    const lng = s.lng_offset !== undefined ? s.lng_offset : s.lng;

                    const marker = L.marker([lat, lng], {
                        icon: L.divIcon({
                            html: `<div style="background:${s.crew_color_principal};width:16px;height:16px;border-radius:50%;border:2px solid black;box-shadow:0 0 5px rgba(0,0,0,0.5);"></div>`,
                            className: '',
                            iconSize: [16, 16],
                            iconAnchor: [8, 8]
                        })
                    });

                    // Guardar referencia
                    window.mapMarkers[s.id_servicio] = marker;

                    // Asignar ID al ícono cuando se añada al mapa
                    marker.on('add', function () {
                        const iconElement = this.getElement();
                        if (iconElement) {
                            iconElement.id = `marker-${s.id_servicio}`;
                            iconElement.style.transition = 'all 0.3s ease';

                            // Opcional: Tooltip pequeño para identificar rápido
                            iconElement.title = `Truck: ${s.truck}`;
                        }
                    });

                    // === Generar HTML del crew ===
                    const crewHtml = Array.isArray(s.crew_integrantes) && s.crew_integrantes.length > 0
                        ? s.crew_integrantes
                            .map(member => {
                                const rolIcono = member.responsabilidad === 'D'
                                    ? '<span title="Driver" style="color:#FFD700;">🚚</span>'
                                    : '<span title="Operator" style="color:#87CEEB;">🛠️</span>';
                                return `<span style="
                                    display: inline-block;
                                    background: #333;
                                    color: white;
                                    padding: 2px 6px;
                                    border-radius: 4px;
                                    font-size: 0.8em;
                                    margin: 2px 4px 2px 0;
                                    line-height: 1.2;
                                ">${rolIcono} ${member.nombre_completo}</span>`;
                            })
                            .join('')
                        : '<span style="color:#aaa; font-style:italic; font-size:0.8em;">No crew assigned</span>';

                    marker.addTo(window.map);
                    marker.bindPopup(`
                        <b>${s.cliente}</b><br>
                        ${s.direccion || 'No address'}<br>
                        <div class="tit_status"><b>Status M1:</b> ${s.s_status}</div><br>
                        <div class="tit_status2"><b>Status M2:</b> ${s.status_m2}</div><br>
                        <div class="tit_d_grid"><b>Crew:</b>
                        <div style="margin-top:4px;">${crewHtml}</div></div>
                    `);
                }

            });

            vehiculosSinServicio.forEach(s => {
                if (!seenTrucks1.has(s.truck)) {
                    seenTrucks1.add(s.truck);

                    vehicles1.push({
                        id: s.truck,
                        color: s.color_truck,
                    });
                }
            });

            // ✅ Disparar evento para GPS
            const event = new Event('serviciosCargados');
            document.dispatchEvent(event);
            console.log("✅ Evento 'serviciosCargados' lanzado");

            // === Iniciar monitoreo GPS cada 60 segundos ===
            if (window.monitoreoGPSInterval) clearInterval(window.monitoreoGPSInterval);
            window.monitoreoGPSInterval = setInterval(() => {
                iniciarMonitoreoGPSContinuo().catch(err => {
                    console.error("Error no capturado en monitoreo GPS:", err);
                });
            }, 5000); // Cada minuto

            console.log("🔁 Monitoreo GPS programado cada 60 segundos");

            // Insertar botones
            const container = document.getElementById('contenedor-vehiculos-historico');
            container.innerHTML = ''; // Limpiar antes

            vehicles.forEach(v => {
                const btn = document.createElement('button');
                btn.textContent = v.id;
                btn.style.background = v.color;
                btn.style.color = getColorContraste(v.color); // Blanco o negro según contraste
                btn.style.border = 'none';
                btn.style.borderRadius = '4px';
                btn.style.padding = '2px 6px';
                btn.style.fontSize = '0.8em';
                btn.style.cursor = 'pointer';
                btn.style.fontWeight = 'bold';
                btn.style.minWidth = '60px';
                btn.style.width = '31%';
                btn.style.textAlign = 'center';

                // Opcional: tooltip
                btn.title = `Vehicle: ${v.id}`;

                container.appendChild(btn);
            });

            const container1 = document.getElementById('contenedor-vehiculos-sin-servicio');
            container1.innerHTML = ''; // Limpiar antes

            vehicles1.forEach(v => {
                const btn = document.createElement('button');
                btn.textContent = v.id;
                btn.style.background = v.color;
                btn.style.color = getColorContraste(v.color); // Blanco o negro según contraste
                btn.style.border = 'none';
                btn.style.borderRadius = '4px';
                btn.style.padding = '2px 6px';
                btn.style.fontSize = '0.8em';
                btn.style.cursor = 'pointer';
                btn.style.fontWeight = 'bold';
                btn.style.minWidth = '60px';
                btn.style.width = '31%';
                btn.style.textAlign = 'center';

                // Opcional: tooltip
                btn.title = `Vehicle: ${v.id}`;

                container1.appendChild(btn);
            });

        }

    } catch (error) {
        console.error("❌ Error al cargar servicios:", error);
    }

    // === 12. MODAL DE DETALLES ===
    async function abrirModalDetalles(servicio, mantenerCarrusel = false) {
        const modal = document.getElementById('modal-servicio');
        if (!modal) {
            console.error("❌ No se encontró el modal con id 'modal-servicio'");
            return;
        }

        window.estadoTemporal = null;
        window.servicioTemporal = servicio;
        window.servicioTemporalId = servicio.id_servicio;

        // Pausar carrusel solo si no se indica lo contrario
        if (!mantenerCarrusel && carrusel.intervalo) {
            clearInterval(carrusel.intervalo);
            carrusel.intervalo = null;
        }

        // 1. Cargar datos actualizados del servicio
        const response = await fetch('/app/ajax/serviciosAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                modulo_servicios: 'obtener_servicio_detalle',
                id_servicio: servicio.id_servicio
            })
        });
        const servicioActualizado = await response.json();
        let servicioHistorico = null;

        let con_historia = null;
        if (!servicioActualizado) {
            await suiteAlertError("Error", "Could not load service");
            reanudarCarrusel();
            return;
        } else {
            const historico = await fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_servicios: 'historial_cliente',
                    id_cliente: servicioActualizado.id_cliente
                })
            });
            servicioHistorico = await historico.json();
            if (!servicioHistorico) {
                con_historia = false;
            } else {
                con_historia = true;
            }
        }

        // 2. Mostrar el modal
        modal.style.display = 'flex';

        // 3. Llenar información principal
        document.getElementById('tabla-detalles-servicio').innerHTML = `
            <tr><th>Client</th><td>${servicioActualizado.cliente}</td></tr>
            <tr><th>Truck</th><td>${servicioActualizado.truck}</td></tr>
            <tr><th>Scheduled Day</th><td>${servicioActualizado.dia_servicio}</td></tr>
            <tr><th>Status</th><td>${servicioActualizado.estado_servicio}</td></tr>
            <tr><th>Coordinates</th><td>${servicioActualizado.lat}, ${servicioActualizado.lng}</td></tr>
            <tr><th>Address</th><td>${servicioActualizado.direccion}</td></tr>
            <tr><th>Start</th><td>${servicioActualizado.hora_aviso_usuario || '—'}</td></tr>
            <tr><th>End</th><td>${servicioActualizado.hora_finalizado || '—'}</td></tr>
        `;
        document.getElementById('crew-detalle-lista').innerHTML = (servicioActualizado.crew_integrantes || []).map(p =>
            `<div class="crew-item" style="background:${p.color};">${p.nombre} ${p.apellido}</div>`
        ).join('') || 'Not available';

        // 4. Estado de los botones
        const tieneInicio = !!servicioActualizado.hora_aviso_usuario;
        const estaFinalizado = !!servicioActualizado.finalizado;

        // Todos los botones activos por defecto
        document.getElementById('btn-inicio-actividades').disabled = false;
        document.getElementById('btn-finalizar-servicio').disabled = false;
        document.getElementById('btn-replanificar-servicio').disabled = false;
        document.getElementById('btn-cancelar-servicio').disabled = false;

        // Si ya inició, deshabilita solo el de inicio
        if (tieneInicio && !estaFinalizado) {
            document.getElementById('btn-inicio-actividades').disabled = true;
        }
        // Si está finalizado, deshabilita todos
        if (estaFinalizado) {
            document.getElementById('btn-inicio-actividades').disabled = true;
            document.getElementById('btn-finalizar-servicio').disabled = true;
            document.getElementById('btn-replanificar-servicio').disabled = true;
            document.getElementById('btn-cancelar-servicio').disabled = true;
        }

        // 5. Ocultar todos los campos de hora y notas al abrir
        ['bloque-hora-inicio', 'bloque-nota-inicio', 'bloque-hora-fin', 'bloque-nota-final', 'acciones-notas'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });

        // 6. Mostrar última nota previa (readonly)
        const ultimaNota = servicioActualizado.nota_final || servicioActualizado.nota_inicio || '';
        const textareaUltima = document.getElementById('input-nota-previa');
        if (textareaUltima) {
            textareaUltima.value = ultimaNota;
            textareaUltima.style.display = ultimaNota ? 'block' : 'none';
        }

        // 7. Limpiar y deshabilitar campos de hora y nota
        document.getElementById('input-hora-inicio').value = '';
        document.getElementById('input-hora-inicio').disabled = true;
        document.getElementById('input-hora-fin').value = '';
        document.getElementById('input-hora-fin').disabled = true;
        document.getElementById('input-nota-final').value = '';
        document.getElementById('input-nota-final').disabled = true;

        // 8. Botón cerrar
        document.getElementById('close_modal_servicio').onclick = () => {
            modal.style.display = 'none';
            if (!mantenerCarrusel) reanudarCarrusel();
            window.servicioTemporalId = null;
            window.estadoTemporal = null;
        };

        // 9. Historial
        const historialBody = document.getElementById('historial-servicio');
        historialBody.innerHTML = '';

        if (con_historia) {
            if (Array.isArray(servicioHistorico.historial) && servicioHistorico.historial.length > 0) {
                servicioHistorico.historial.forEach(h => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${h.fecha_programada}</td>
                        <td>${h.truck}</td>
                        <td>${h.estado_visita}</td>
                        <td>${h.tiempo_duracion || '—'}</td>
                    `;
                    historialBody.appendChild(tr);
                });
            } else {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td colspan="4">No history available</td>';
                historialBody.appendChild(tr);
            }
        } else {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="4">No history available</td>';
            historialBody.appendChild(tr);
        }
    }

    // === Función auxiliar: color por estado ===
    function getEstadoColor(estado) {
        switch (estado) {
            case 'finalizado':
            case 'usuario_alerto':
            case 'inicio_actividades':
                return '#565709ff';
            case 'gps_detectado':
                return '#4CAF50';
            case 'replanificado':
                return '#FF9800';
            case 'sin_programar':
                return '#9E9E9E';
            case 'pendiente':
                return '#2196F3';
            case 'cancelado':
                return '#F44336';
            default:
                return '#666';
        }
    }

    // === Función auxiliar: color por estado ===
    function getEstadoMessage(estado) {
        switch (estado) {
            case 'finalizado':
                return 'Service completed successfully';
            case 'usuario_alerto':
                return 'User has alerted';
            case 'inicio_actividades':
                return 'Activities started';
            case 'gps_detectado':
                return 'GPS detected';
            case 'replanificado':
                return 'Service rescheduled';
            case 'sin_programar':
                return 'Not scheduled';
            case 'pendiente':
                return 'Pending';
            case 'cancelado':
                return 'Cancelled by user';
            default:
                return 'Unknown status';
        }
    }

    function formatoHora(fechaStr) {
        if (!fechaStr) return '—';
        const fecha = new Date(fechaStr);
        return fecha.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function calcularDuracion(inicio, fin) {
        if (!inicio || !fin) return '—';
        const start = new Date(inicio).getTime();
        const end = new Date(fin).getTime();
        const diff = end - start;
        const h = String(Math.floor(diff / 3600000)).padStart(2, '0');
        const m = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
        const s = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
        return `${h}:${m}:${s}`;
    }

    function iniciarContador(elemento, inicio) {
        const inicioTime = new Date(inicio).getTime();
        const timer = setInterval(() => {
            const ahora = Date.now();
            const diff = Math.max(0, ahora - inicioTime);
            const h = String(Math.floor(diff / 3600000)).padStart(2, '0');
            const m = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
            const s = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
            elemento.textContent = `${h}:${m}:${s}`;
        }, 1000);
        return timer;
    }

    // === Variables de estado ===
    let estadoTemporal = null;

    // === Habilitar edición por botón ===
    window.prepararAccion = function (id_servicio, nuevoEstado) {
        const acciones = document.getElementById('acciones-notas');
        const campoHoraInicio = document.getElementById('bloque-hora-inicio');
        const campoHoraFin = document.getElementById('bloque-hora-fin');
        const labelHoraInicio = campoHoraInicio ? campoHoraInicio.querySelector('label') : null;
        const labelHoraFin = campoHoraFin ? campoHoraFin.querySelector('label') : null;
        const textareaNotaFinal = document.getElementById('input-nota-final');
        const inputHoraInicio = document.getElementById('input-hora-inicio');
        const inputHoraFin = document.getElementById('input-hora-fin');
        const bloqueNotaFinal = document.getElementById('bloque-nota-final');

        // Ocultar todos los campos primero
        if (campoHoraInicio) campoHoraInicio.style.display = 'none';
        if (campoHoraFin) campoHoraFin.style.display = 'none';
        if (bloqueNotaFinal) bloqueNotaFinal.style.display = 'none';

        // Mostrar y configurar según acción
        if (nuevoEstado === 'inicio_actividades') {
            if (campoHoraInicio) {
                campoHoraInicio.style.display = 'block';
                if (labelHoraInicio) labelHoraInicio.textContent = 'Start Service:';
            }
            if (bloqueNotaFinal) bloqueNotaFinal.style.display = 'block';
            if (textareaNotaFinal) {
                textareaNotaFinal.placeholder = 'Add start note...';
                textareaNotaFinal.value = '';
                textareaNotaFinal.disabled = false;
                textareaNotaFinal.readOnly = false;
                textareaNotaFinal.style.backgroundColor = '#e3f2fd';
                textareaNotaFinal.style.color = '#1565c0';
            }
            if (inputHoraInicio) {
                inputHoraInicio.disabled = false;
                const now = new Date();
                inputHoraInicio.value = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
            }
        } else if (nuevoEstado === 'finalizado') {
            if (campoHoraFin) {
                campoHoraFin.style.display = 'block';
                if (labelHoraFin) labelHoraFin.textContent = 'End Service:';
            }
            if (bloqueNotaFinal) bloqueNotaFinal.style.display = 'block';
            if (textareaNotaFinal) {
                textareaNotaFinal.placeholder = 'Add final note...';
                textareaNotaFinal.value = '';
                textareaNotaFinal.disabled = false;
                textareaNotaFinal.readOnly = false;
                textareaNotaFinal.style.backgroundColor = '#e8f5e8';
                textareaNotaFinal.style.color = '#2e7d32';
            }
            if (inputHoraFin) {
                inputHoraFin.disabled = false;
                const now = new Date();
                inputHoraFin.value = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
            }
        } else if (nuevoEstado === 'replanificado' || nuevoEstado === 'cancelado') {
            if (bloqueNotaFinal) bloqueNotaFinal.style.display = 'block';
            if (textareaNotaFinal) {
                textareaNotaFinal.placeholder = nuevoEstado === 'replanificado' ? 'Add reschedule note...' : 'Add cancel note...';
                textareaNotaFinal.value = '';
                textareaNotaFinal.disabled = false;
                textareaNotaFinal.readOnly = false;
                textareaNotaFinal.style.backgroundColor = nuevoEstado === 'replanificado' ? '#fff8e1' : '#ffcdd2';
                textareaNotaFinal.style.color = nuevoEstado === 'replanificado' ? '#f57c00' : '#c62828';
            }
        }

        // Mostrar botones aceptar/cancelar
        if (acciones) acciones.style.display = 'flex';

        // Guardar estado temporal
        window.estadoTemporal = nuevoEstado;
        window.servicioTemporalId = id_servicio;
    };

    // === Guardar notas ===
    window.guardarNotas = function (id_servicio, estadoActual) {
        const textarea = document.querySelector('.input-notas');
        const notas = textarea.value.trim();
        const nuevoEstado = window.estadoTemporal;

        const inputHoraInicio = document.querySelector('.input-hora-inicio');
        const inputHoraFin = document.querySelector('.input-hora-fin');
        const hora_aviso_usuario = inputHoraInicio ? inputHoraInicio.value : '';
        const hora_finalizado_usuario = inputHoraFin ? inputHoraFin.value : '';

        if (!nuevoEstado) {
            suiteAlertWarning("Warning", "No action selected")
            return;
        }

        if (!window.servicioTemporal) {
            suiteAlertError("Error", "Service not found. Reload the page.");
            return;
        }

        const data = {
            modulo_servicios: 'actualizar_estado_con_historial',
            id_servicio: id_servicio,
            estado: nuevoEstado,
            notas: notas,
            hora_aviso_usuario: hora_aviso_usuario,
            hora_finalizado_usuario: hora_finalizado_usuario,
            estado_actual: estadoActual,
            cliente: window.servicioTemporal.cliente,
            truck: window.servicioTemporal.truck
        };

        fetch('/app/ajax/serviciosAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    suiteAlertSuccess('Updated', `Service updated to ${nuevoEstado}`);
                    const servicio = carrusel.datos.find(s => s.id_servicio == id_servicio);
                    if (servicio) {
                        servicio.estado_servicio = (nuevoEstado === 'inicio_actividades') ? 'usuario_alerto' : servicio.estado_servicio;
                        servicio.finalizado = (nuevoEstado === 'finalizado');

                        const now = new Date();
                        const localDate = now.getFullYear() + '-' +
                            String(now.getMonth() + 1).padStart(2, '0') + '-' +
                            String(now.getDate()).padStart(2, '0') + ' ' +
                            String(now.getHours()).padStart(2, '0') + ':' +
                            String(now.getMinutes()).padStart(2, '0') + ':' +
                            String(now.getSeconds()).padStart(2, '0');

                        if (nuevoEstado === 'inicio_actividades' && !servicio.hora_aviso_usuario) {
                            // ✅ Fecha en formato local "Y-m-d H:i:s"
                            servicio.hora_aviso_usuario = localDate;
                        }

                        if (nuevoEstado === 'finalizado' && !servicio.hora_finalizado) {
                            servicio.hora_finalizado = localDate;
                        }

                        actualizarTarjeta(servicio);
                    }

                    const modal = document.getElementById('modal-servicio');
                    if (modal) modal.style.display = 'none';

                    reanudarCarrusel();
                } else {
                    suiteAlertError("Error", resp.error);
                }
            })
            .catch(err => {
                console.error("Error al guardar:", err);
                suiteAlertError("Error", "Connection error");
            });
    };

    // === Cancelar proceso ===
    window.cancelarNotas = function () {
        // Ocultar y limpiar campos
        ['bloque-hora-inicio', 'bloque-hora-fin', 'bloque-nota-final', 'acciones-notas'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
        document.getElementById('input-hora-inicio').value = '';
        document.getElementById('input-hora-inicio').disabled = true;
        document.getElementById('input-hora-fin').value = '';
        document.getElementById('input-hora-fin').disabled = true;
        document.getElementById('input-nota-final').value = '';
        document.getElementById('input-nota-final').disabled = true;
        window.estadoTemporal = null;
    };

    // === Reanudar carrusel al cerrar modal ===
    window.reanudarCarrusel = function () {
        if (!carrusel.intervalo && carrusel.datos.length > 0) {
            let indiceCarruselGlobal = 0;
            carrusel.intervalo = setInterval(() => {
                insertarTarjeta(carrusel.datos[indiceCarruselGlobal]);
                indiceCarruselGlobal = (indiceCarruselGlobal + 1) % carrusel.datos.length;
            }, config.intervaloCarrusel);
        }
    };

    // === 13. POLLING INTELIGENTE: Actualización en tiempo real del carrusel ===
    const now = new Date();
    const localDate = now.getFullYear() + '-' +
        String(now.getMonth() + 1).padStart(2, '0') + '-' +
        String(now.getDate()).padStart(2, '0') + ' ' +
        String(now.getHours()).padStart(2, '0') + ':' +
        String(now.getMinutes()).padStart(2, '0') + ':' +
        String(now.getSeconds()).padStart(2, '0');

    let ultimoTiempo = localDate;

    // Verificar si el polling ya está activo para evitar duplicados
    if (!window.__pollingActivo) {
        window.__pollingActivo = true;

        setInterval(async () => {
            try {
                const res = await fetch('/app/ajax/serviciosAjax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        modulo_servicios: 'listar_actualizados',
                        ultimo_tiempo: ultimoTiempo
                    })
                });

                if (!res.ok) throw new Error('HTTP ' + res.status);
                const serviciosActualizados = await res.json();

                if (Array.isArray(serviciosActualizados) && serviciosActualizados.length > 0) {

                    //                    console.log(`✅ ${serviciosActualizados.length} servicios actualizados`);

                    serviciosActualizados.forEach(servicio => {
                        // Actualiza la tarjeta si existe
                        const card = document.querySelector(`[data-id="${servicio.id_servicio}"]`);
                        if (card) {
                            // Actualiza estado
                            const estadoBadge = card.querySelector('.estado-badge');
                            if (estadoBadge) {
                                estadoBadge.textContent = servicio.estado_servicio;
                                estadoBadge.style.backgroundColor = getEstadoColor(servicio.estado_servicio);
                            }
                            // Reinicia contador si aplica
                            const contador = card.querySelector('.contador');
                            if (servicio.hora_aviso_usuario && !servicio.finalizado && contador && !contador.dataset.timer) {
                                contador.dataset.timer = iniciarContador(contador, servicio.hora_aviso_usuario);
                            } else if (servicio.finalizado && contador) {
                                contador.textContent = 'Finalizado';
                                if (contador.dataset.timer) clearInterval(contador.dataset.timer);
                                delete contador.dataset.timer;
                            }
                            // Debe actualizar los campos de inicio y fin de actividades
                            actualizarCeldaActividad(servicio);
                            actualizarCeldaActividadGps(servicio);
                        } else {
                            // Si no existe, podrías insertarla, pero no es necesario
                        }
                    });
                    // Actualiza la marca de tiempo
                    ultimoTiempo = localDate;
                }
            } catch (err) {
                console.error("Error en polling de actualización:", err);
                // No desactivar el polling por un error temporal
            }
        }, 5000); // Cada 5 segundos
    }
    // === FIN DEL POLLING ===

    // === Control botón "Select Client" ===
    document.getElementById('btn-select-client').addEventListener('click', async () => {
        const modalSelectClient = document.getElementById('modal-select-client');
        const btnCerrar_lista = document.getElementById('close-select-client');

        btnCerrar_lista.addEventListener('click', () => {
            if (modalSelectClient) modalSelectClient.style.display = 'none';
        });

        if (!modalSelectClient) {
            console.error("❌ No se encontró el modal #modal-select-client");
            return;
        }
        modalSelectClient.style.display = 'flex';
        await cargarListaClientes();
    });

    const listaClientes = document.getElementById('lista-clientes');

    async function cargarListaClientes() {
        try {
            const res = await fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_servicios: 'listar' })
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);

            const respuesta = await res.json();
            const servicios = respuesta.servicios || [];
            const vehiculosSinServicio = respuesta.vehiculosSinServicio || [];

            if (!Array.isArray(servicios) || servicios.length === 0) {
                listaClientes.innerHTML = '<p>No services scheduled for today</p>';

                // Iniciar polling cada 5 segundos
                const intervalo = setInterval(async () => {
                    try {
                        const res = await fetch('/app/ajax/serviciosAjax.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ modulo_servicios: 'listar' })
                        });

                        if (!res.ok) return;

                        const respuesta = await res.json();
                        const nuevosServicios = respuesta.servicios || [];
                        const vehiculosSinServicio = respuesta.vehiculosSinServicio || [];

                        if (Array.isArray(nuevosServicios) && nuevosServicios.length > 0) {
                            // ✅ Servicios llegaron → detener polling y recargar
                            clearInterval(intervalo);
                            console.log("✅ Servicios recibidos, recargando lista");
                            await cargarListaClientes(); // Volver a ejecutar con datos
                        }
                    } catch (err) {
                        console.error("Error en polling de servicios:", err);
                    }
                }, 5000);

                return;
            }

            const clientesMap = new Map();
            servicios.forEach(s => {
                if (!clientesMap.has(s.cliente)) {
                    clientesMap.set(s.cliente, []);
                }
                clientesMap.get(s.cliente).push(s);
            });

            // Generar lista
            listaClientes.innerHTML = '';
            clientesMap.forEach((serviciosCliente, cliente) => {
                const divCliente = document.createElement('div');
                divCliente.style.marginBottom = '7.5px';
                divCliente.style.marginTop = '7.5px';
                divCliente.innerHTML = `<strong style="padding-left: 10px;">${cliente}</strong><div class="servicios-lista" style="margin-top: 5px; margin-left: 10px; margin-right: 10px;">`;

                serviciosCliente.forEach(s => {
                    const crewNombres = s.crew_integrantes
                        .map(p => `${p.nombre} ${p.apellido}`)
                        .join(', ');

                    divCliente.querySelector('.servicios-lista').innerHTML += `
                        <div class="servicio-item" 
                            style="padding: 12px; border: 1px solid #ddd; margin: 8px 0; border-radius: 4px; cursor: pointer;"
                            onclick="abrirModalDesdeSeleccion(${s.id_servicio})">
                            <b style="color: ${s.crew_color_principal};">${s.truck}</b> - ${crewNombres}
                        </div>
                    `;
                });

                listaClientes.appendChild(divCliente);
            });

        } catch (err) {
            console.error("Error al cargar lista de clientes:", err);
            listaClientes.innerHTML = '<p>Error loading clients</p>';
        }
    }

    // Función para formatear fecha como "YYYY-MM-DD"
    function formatearFechaInput(fecha) {
        const año = fecha.getFullYear();
        const mes = String(fecha.getMonth() + 1).padStart(2, '0'); // getMonth() es 0-11
        const dia = String(fecha.getDate()).padStart(2, '0');
        return `${año}-${mes}-${dia}`;
    }

    // Escuchar cambios en el input de búsqueda
    document.getElementById('buscar-no-asignados')?.addEventListener('input', function (e) {
        const texto = e.target.value.trim().toLowerCase();
        const contenedor = document.getElementById('lista-no-asignados');
        const items = contenedor.querySelectorAll('.item-despacho');

        // Remover resaltado previo
        items.forEach(item => {
            item.classList.remove('resaltado');
            // También podrías quitar resaltado de texto interno si usas <mark>, pero con clase es suficiente
        });

        if (texto === '') return;

        // Resaltar ítems que coincidan
        let primero = true;

        items.forEach(item => {
            // Extraer texto visible del ítem (todo el contenido de texto)
            const textoItem = item.textContent.toLowerCase();
            if (textoItem.includes(texto)) {
                item.classList.add('resaltado');
                if (primero) {
                    item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    primero = false;
                }
            }
        });
    });

    // === Control botón "Select Despacho" ===
    document.getElementById('btn-select-despacho').addEventListener('click', async () => {
        let formFlotante = document.getElementById('form-flotante-historico');
        if (formFlotante) {
            formFlotante.style.display = 'none'; // Oculta el panel
        }

        const modalSelectDespacho = document.getElementById('modal-select-despacho');
        modalSelectDespacho.style.display = 'flex';

        const btnCerrar_lista = document.getElementById('close-select-despacho');

        const fecha_next = sumarUnDiaYEvitarSabado(new Date());

        // ✅ CORRECTO: usa .value y formatea la fecha
        const inputFecha = document.getElementById('fecha-despacho');
        if (inputFecha) {
            inputFecha.value = formatearFechaInput(fecha_next);
        }

        btnCerrar_lista.addEventListener('click', () => {
            if (modalSelectDespacho) modalSelectDespacho.style.display = 'none';
            if (formFlotante) {
                formFlotante.style.display = 'flex'; // O 'block' / 'inline-flex', según diseño
            }
        });

        if (!modalSelectDespacho) {
            console.error("❌ No se encontró el modal #modal-select-despacho");
            return;
        }

        // 👇 OBSERVADOR DEL PANEL DERECHO
        const panelDerecho = document.getElementById('lista-no-asignados');
        if (panelDerecho) {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList') {
                        // Si el panel queda vacío después de un cambio
                        if (panelDerecho.children.length === 0) {
                            console.warn("🚨 El panel derecho fue vaciado en este momento.");
                            console.trace("Pila de llamadas que llevó al vaciado:");
                        }
                    }
                });
            });

            // Observar solo cambios en los hijos (no atributos ni subárboles profundos)
            observer.observe(panelDerecho, { childList: true });
        }

        await cargarListaDespacho();
    });

    document.getElementById('fecha-despacho')?.addEventListener('change', async (e) => {
        // Opcional: guardar en una variable global o recargar
        window.fechaDespachoSeleccionada = e.target.value;
        await cargarListaDespacho();
    });

    async function cargarListaDespacho() {
        try {
            const fecha_despacho = document.getElementById('fecha-despacho')?.value || formatearFechaInput(new Date());

            window.listaAsignados = document.getElementById('lista-asignados');
            window.listaNoAsignados = document.getElementById('lista-no-asignados');
            window.htmlTotalTiempo = document.getElementById('htmlTotalTiempo');

            let editable = true;

            const res = await fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(
                    {
                        modulo_servicios: 'listar_despacho',
                        fecha_despacho: fecha_despacho
                    }
                )
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);
            const respuesta = await res.json();
            if (respuesta.success === false) {
                const la = document.getElementById('lista-asignados');
                const lna = document.getElementById('lista-no-asignados');
                if (la) la.innerHTML = `<p>❌ ${respuesta.mess} </p>`;
                if (lna) lna.innerHTML = `<p>❌ ${respuesta.mess} </p>`;
            } else {

                window.listaNoAsignados.innerHTML = respuesta.html_no_asignados;
                window.listaAsignados.innerHTML = respuesta.html_asignados;
                window.htmlTotalTiempo.innerHTML = respuesta.htmlTotalTiempo;

                editable = respuesta.editable;

                const tit_tipo_servicio = document.getElementById('tit_tipo_servicio');
                if (!editable) {
                    tit_tipo_servicio.innerHTML = "Assigned Services"
                } else {
                    tit_tipo_servicio.innerHTML = "Pre-Assigned"
                }

                // ... después de asignar innerHTML 
                actualizarContadorPanel(window.listaAsignados, 'totalRegistros');
                actualizarContadorPanel(window.listaNoAsignados, 'pie_panel_derecho');

                // Inyectar lista de servicios sin ruta (si viene en la respuesta)
                try {
                    const pieDerecho = document.getElementById('pie_panel_derecho');
                    if (pieDerecho) {
                        if (respuesta && respuesta.html_no_route) {
                            let cont = pieDerecho.querySelector('.sin-ruta-lista');
                            if (!cont) {
                                cont = document.createElement('div');
                                cont.className = 'sin-ruta-lista';
                                cont.style.marginTop = '6px';
                                pieDerecho.appendChild(cont);
                            }
                            cont.innerHTML = respuesta.html_no_route;
                            // Hacer que los items dentro de la lista "sin ruta" sean seleccionables
                            try {
                                const elementos = cont.querySelectorAll('.item-despacho, li, .no-route-item');
                                elementos.forEach(el => {
                                    // Normalizar dataset y clases para integrarse con el flujo existente
                                    el.dataset.tipo = el.dataset.tipo || 'no-asignado';
                                    if (!el.classList.contains('item-despacho')) el.classList.add('item-despacho');

                                    // Click -> seleccionar (soportando Ctrl multi-select)
                                    el.addEventListener('click', (ev) => seleccionarItem(el, ev));

                                    // Habilitar drag (compatibilidad)
                                    el.setAttribute('draggable', 'true');
                                    el.addEventListener('dragstart', (e) => {
                                        if (window.dispatchConfig && window.dispatchConfig.dateIsProcessed) {
                                            e.preventDefault();
                                            return;
                                        }
                                        const id = el.dataset.id || el.getAttribute('data-id') || '';
                                        e.dataTransfer.setData('text/plain', id);
                                        e.dataTransfer.effectAllowed = 'move';
                                        el.classList.add('dragging');
                                    });
                                    el.addEventListener('dragend', () => {
                                        el.classList.remove('dragging');
                                    });
                                });
                            } catch (e2) {
                                console.warn('No se pudieron vincular eventos a items sin-ruta:', e2);
                            }
                        } else {
                            const cont = pieDerecho.querySelector('.sin-ruta-lista');
                            if (cont) cont.innerHTML = '';
                        }
                    }
                } catch (e) {
                    console.warn('No se pudo inyectar html_no_route:', e);
                }

                // Re-vincular eventos en AMBOS paneles
                [window.listaNoAsignados, window.listaAsignados].forEach(contenedor => {
                    contenedor.querySelectorAll('.item-despacho').forEach(el => {
                        el.addEventListener('click', (ev) => seleccionarItem(el, ev));

                        el.addEventListener('dragstart', (e) => {
                            // Bloquear inicio de drag si la fecha seleccionada está procesada
                            if (window.dispatchConfig && window.dispatchConfig.dateIsProcessed) {
                                e.preventDefault();
                                return;
                            }
                            e.dataTransfer.setData('text/plain', el.dataset.id);
                            e.dataTransfer.effectAllowed = 'move';
                            el.classList.add('dragging');
                        });

                        el.addEventListener('dragend', () => {
                            el.classList.remove('dragging');
                        });
                    });
                });
            }

        } catch (err) {
            console.error("Error al cargar lista de Despacho:", err);
            const la = document.getElementById('lista-asignados');
            const lna = document.getElementById('lista-no-asignados');
            if (la) la.innerHTML = '<p>❌ Error loading assigned services</p>';
            if (lna) lna.innerHTML = '<p>❌ Error loading available services</p>';
        }
    }

    let coincidencias = [];
    let indiceActual = -1;

    function resaltarCoincidencias(texto) {
        const contenedor = document.getElementById('lista-no-asignados');
        const items = contenedor.querySelectorAll('.item-despacho');

        // Reset
        coincidencias = [];
        indiceActual = -1;
        items.forEach(item => {
            item.classList.remove('resaltado', 'resaltado-activo');
        });

        if (!texto.trim()) {
            document.getElementById('btn-next-coincidencia').style.display = 'none';
            return;
        }

        const busqueda = texto.trim().toLowerCase();

        items.forEach(item => {
            const textoItem = item.textContent.toLowerCase();
            if (textoItem.includes(busqueda)) {
                item.classList.add('resaltado');
                coincidencias.push(item);
            }
        });

        if (coincidencias.length > 0) {
            document.getElementById('btn-next-coincidencia').style.display = 'inline-block';
            // Ir al primer coincidente automáticamente
            irAlSiguienteCoincidente();
        } else {
            document.getElementById('btn-next-coincidencia').style.display = 'none';
        }
    }

    function actualizarContadorPanel(contenedor, pieId) {
        const count = contenedor.querySelectorAll('.item-despacho').length;
        document.getElementById(pieId).textContent = `${count} item${count !== 1 ? 's' : ''}`;
    }

    function irAlSiguienteCoincidente() {
        if (coincidencias.length === 0) return;

        // Quitar resaltado activo anterior
        coincidencias.forEach(item => item.classList.remove('resaltado-activo'));

        // Avanzar al siguiente
        indiceActual = (indiceActual + 1) % coincidencias.length;
        const itemActual = coincidencias[indiceActual];

        // Resaltar activo
        itemActual.classList.add('resaltado-activo');

        // Hacer scroll suave al ítem
        itemActual.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
            inline: 'nearest'
        });
    }

    // Evento: escribir en el input
    document.getElementById('buscar-no-asignados')?.addEventListener('input', function (e) {
        resaltarCoincidencias(e.target.value);
    });

    // Evento: clic en "Next"
    document.getElementById('btn-next-coincidencia')?.addEventListener('click', function () {
        irAlSiguienteCoincidente();
    });

    let itemSeleccionado = null;

    function seleccionarItem(item, ev = null) {
        // Si se usa Ctrl para multi-selección, alternar el estado
        const usarMulti = ev && (ev.ctrlKey || ev.metaKey);

        if (usarMulti) {
            if (item.classList.contains('seleccionado')) {
                item.classList.remove('seleccionado');
            } else {
                item.classList.add('seleccionado');
            }
            // reconstruir itemSeleccionado como el último seleccionado
            const seleccionados = document.querySelectorAll('.item-despacho.seleccionado');
            itemSeleccionado = seleccionados.length > 0 ? seleccionados[seleccionados.length - 1] : null;
        } else {
            // Quitar selección previa (modo single)
            document.querySelectorAll('.item-despacho.seleccionado').forEach(it => it.classList.remove('seleccionado'));
            // Seleccionar nuevo
            item.classList.add('seleccionado');
            itemSeleccionado = item;
        }
        // Si la fecha está marcada como procesada, no permitir seleccionar ni habilitar acciones
        if (window.dispatchConfig && window.dispatchConfig.dateIsProcessed) {
            itemSeleccionado = null;
            document.getElementById('btn-mover-a-no-asignados').disabled = true;
            document.getElementById('btn-mover-a-asignados').disabled = true;
            return;
        }

        // Habilitar botón correspondiente según última selección
        const tipo = itemSeleccionado ? itemSeleccionado.dataset.tipo : null;
        document.getElementById('btn-mover-a-no-asignados').disabled = (tipo !== 'asignado');
        document.getElementById('btn-mover-a-asignados').disabled = (tipo !== 'no-asignado');
    }

    async function verificarRuta(elemento, tipo_dato) {
console.log("🔍 verificarRuta llamado para:", elemento.dataset.id, "tipo:", tipo_dato);
        const id_cliente = elemento.dataset.id || elemento.getAttribute('data-id');
        const modulo = 'verificar_ruta';
        const fecha = document.getElementById('fecha-despacho')?.value || null;
        if (!fecha) {
            console.warn('Fecha no seleccionada: no se persisten cambios');
            return;
        }

        try {
            const res = await fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(
                    {
                        modulo_servicios: modulo,
                        id_cliente: id_cliente,
                        fecha: fecha
                    }
                )
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (data.error) throw new Error(data.error);
            const situacion = data.status;
            const mensaje = data.message;
            const cliente = data.cliente;

            if (situacion == 'false') {
                // Mostrar modal
                const valorSeleccionado = await mostrarModalSelect(mensaje, cliente);

                if (valorSeleccionado !== null) {
                    console.log('Usuario seleccionó:', valorSeleccionado);
                    moverItem(elemento, tipo_dato, valorSeleccionado);
                    itemSeleccionado = null;
                    document.getElementById('btn-mover-a-asignados').disabled = true;
                    document.getElementById('btn-mover-a-no-asignados').disabled = true;
                } else {
                    console.log('Usuario canceló');
                }
            } else {
                moverItem(elemento, tipo_dato, null);
                document.getElementById('btn-mover-a-asignados').disabled = true;
                document.getElementById('btn-mover-a-no-asignados').disabled = true;
            }
        } catch (error) {
            console.error('Error en verificarRuta:', error);
            alert('Error al verificar la ruta: ' + error.message);
        }
    }

    /**
     * Muestra un modal con un select y devuelve la opción seleccionada
     * @param {Array} opciones - [{ value: 'id', text: 'Nombre' }, ...]
     * @param {Function} callback - función que recibe el valor seleccionado (o null si cancela)
     */
    function mostrarModalSelect(opciones, cliente) {
        return new Promise((resolve) => {
            const select = document.getElementById('modalSelect');
            const btnAceptar = document.getElementById('btnAceptar');
            const btnCancelar = document.getElementById('btnCancelar');
            const modal = document.getElementById('selectModal');
            const overlay = document.getElementById('modalOverlay');
            const dat_cliente = document.getElementById('dat_cliente');

            dat_cliente.innerHTML = cliente;

            // Limpiar y llenar el select
            select.innerHTML = opciones;

            // Mostrar modal
            modal.style.display = 'block';
            overlay.style.display = 'block';

            // Manejar botones
            const handleAceptar = () => {
                cleanup();
                const selectedOption = select.options[select.selectedIndex];
                if (selectedOption) {
                    resolve({
                        id_ruta: selectedOption.value,
                        nombre_ruta: selectedOption.textContent,
                    });
                } else {
                    resolve(null);
                }
            };

            const handleCancelar = () => {
                cleanup();
                resolve(null);
            };

            const cleanup = () => {
                btnAceptar.removeEventListener('click', handleAceptar);
                btnCancelar.removeEventListener('click', handleCancelar);
                overlay.removeEventListener('click', handleCancelar);
                modal.style.display = 'none';
                overlay.style.display = 'none';
            };

            // Asignar eventos (una sola vez)
            btnAceptar.addEventListener('click', handleAceptar);
            btnCancelar.addEventListener('click', handleCancelar);
            overlay.addEventListener('click', handleCancelar); // cerrar al hacer clic fuera
        });
    }

    async function moverItem(elemento, origen, rutaSeleccionada = null) {
console.log("🚚 moverItem llamado para:", elemento.dataset.id, "origen:", origen);
        //const listaNoAsignados = document.getElementById('lista-no-asignados');
        const destino = origen === 'asignado' ? 'no-asignado' : 'asignado';
        const contenedorDestino = destino === 'asignado' ? window.listaAsignados : window.listaNoAsignados;
        const contenedorOrigen = origen === 'asignado' ? window.listaAsignados : window.listaNoAsignados;

        // Determinar elementos a mover (soporta multi-select)
        let seleccionados = Array.from(contenedorOrigen.querySelectorAll('.item-despacho.seleccionado'));
        if (seleccionados.length === 0) {
            seleccionados = [elemento];
        }

        const clientes = [];
        for (const el of seleccionados) {
            // Quitar selección
            el.classList.remove('seleccionado');

            // Mover visualmente
            contenedorDestino.insertAdjacentElement('beforeend', el);
            el.dataset.tipo = destino;
            el.style.backgroundColor = destino === 'asignado' ? '#f0fff0' : '';

            // ✅ ACTUALIZAR LA RUTA VISUAL SI SE MOVIÓ A "ASIGNADOS"
            if (destino === 'asignado' && rutaSeleccionada) {
                const formatoRutaDiv = el.querySelector('.formatoRuta');
                if (formatoRutaDiv) {
                    formatoRutaDiv.innerHTML = `<span class="colorE">Route: </span> ${rutaSeleccionada.nombre_ruta}`;
                }
            }
            const idCliente = el.dataset.id || el.getAttribute('data-id');
            if (idCliente) clientes.push(idCliente);
        }

        // ✅ Actualizar contadores
        actualizarContadorPanel(window.listaAsignados, 'pie_panel_izquierdo');
        actualizarContadorPanel(window.listaNoAsignados, 'pie_panel_derecho');

        // Deshabilitar botones
        document.getElementById('btn-mover-a-asignados').disabled = true;
        document.getElementById('btn-mover-a-no-asignados').disabled = true;

        // Persistir cambio en servidor
        try {
            const fecha = document.getElementById('fecha-despacho')?.value || null;
            if (!fecha) {
                console.warn('Fecha no seleccionada: no se persisten cambios');
                return;
            }

            let id_ruta_new = '';

            if (destino == 'no-asignado'){
                id_ruta_new = null;
            }else{
                id_ruta_new = rutaSeleccionada.id_ruta ? rutaSeleccionada.id_ruta : null;
            }

            const modulo = destino === 'asignado' ? 'preservicio_add' : 'preservicio_remove';

            console.log('Modulo: ', modulo);
            console.log('Origen: ', clientes);
            console.log('Fecha:', fecha);

            const res = await fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(
                    {
                        modulo_servicios: modulo,
                        clientes: clientes,
                        fecha: fecha,
                        id_ruta_new: id_ruta_new
                    }
                )
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (data.error) throw new Error(data.error);

            // Recargar la lista para asegurar orden y consistencia
            await cargarListaDespacho();
        } catch (err) {
            console.error('Error persistiendo cambios de preservicios:', err);
        }
    }

    // Eventos para botones centrales (si quieres usarlos en lugar del clic directo)
    document.getElementById('btn-mover-a-asignados')?.addEventListener('click', () => {
        if (itemSeleccionado && itemSeleccionado.dataset.tipo === 'no-asignado') {
            verificarRuta(itemSeleccionado);
        }
    });

    document.getElementById('btn-mover-a-no-asignados')?.addEventListener('click', () => {
        if (itemSeleccionado && itemSeleccionado.dataset.tipo === 'asignado') {
            moverItem(itemSeleccionado, 'asignado', null);
            itemSeleccionado = null;
            document.getElementById('btn-mover-a-asignados').disabled = true;
            document.getElementById('btn-mover-a-no-asignados').disabled = true;
        }
    });

    // === Control botón "Select Despacho" ===
    // document.getElementById('btn-zona_c').addEventListener('click', async () => {
    //     try {
    //         const res = await fetch('/app/ajax/zonasAjax.php', {
    //             method: 'POST',
    //             headers: { 'Content-Type': 'application/json' },
    //             body: JSON.stringify({ modulo_zonas: 'registrar_zonas' })
    //         });

    //         if (!res.ok) throw new Error('HTTP ' + res.status);


    //     } catch (err) {
    //         console.error("Error al cargar lista de Despacho:", err);
    //         listaDespacho.innerHTML = '<p>Error loading clients</p>';
    //     }
    // });

    window.abrirModalDesdeSeleccion = function (id_servicio) {
        const servicio = carrusel.datos.find(s => s.id_servicio == id_servicio);
        if (servicio) {
            const modalSelectClient = document.getElementById('modal-daily-status');
            if (modalSelectClient) {
                modalSelectClient.style.display = 'none';
                abrirModalDetalles(servicio, true);
            }
        }
    };


    // === Daily Status Modal ===
    document.getElementById('btn-daily-status')?.addEventListener('click', async () => {
        const modal = document.getElementById('modal-daily-status');
        if (!modal) {
            console.error("❌ No se encontró el modal #modal-daily-status");
            return;
        }
        modal.style.display = 'flex';
        console.log("Llego al la captura del click");
        await cargarMatrizDiaria();
    });

    document.getElementById('close-daily-status')?.addEventListener('click', () => {
        const modal = document.getElementById('modal-daily-status');
        if (modal) modal.style.display = 'none';
    });

    document.getElementById('modal-daily-status')?.addEventListener('click', (e) => {
        const modal = document.getElementById('modal-daily-status');
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    async function cargarMatrizDiaria() {
        const tbody = document.getElementById('matrix-body');
        const thead = document.querySelector('#daily-status-matrix thead tr');
        const tfoot = document.getElementById('matrix-footer');

        thead.innerHTML = '<th style="border: 1px solid #ddd; padding: 8px; position: sticky; top: 0; left: 0; background: #e0e0e0; z-index: 3;">Client</th>';
        tbody.innerHTML = '';
        tfoot.innerHTML = '';

        try {
            const res = await fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_servicios: 'listar' })
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);

            const respuesta = await res.json();
            const servicios = respuesta.servicios || [];
            const vehiculosSinServicio = respuesta.vehiculosSinServicio || [];

            if (!Array.isArray(servicios)) {
                throw new Error('La respuesta no es un array');
            }

            const crews = [...new Set(servicios.map(s => s.truck))].sort();
            const clientes = [...new Set(servicios.map(s => s.cliente))].sort();

            crews.forEach(crew => {
                const th = document.createElement('th');
                th.textContent = crew;
                th.style.cssText = 'border: 1px solid #ddd; padding: 8px; position: sticky; top: 0; background: #f0f0f0;';
                thead.appendChild(th);
            });

            const procesados = Array(crews.length).fill(0);
            const replanificados = Array(crews.length).fill(0);
            const cancelados = Array(crews.length).fill(0);
            const totales = Array(crews.length).fill(0);
            const totalServicios = servicios.length;

            const trResume = document.createElement('tr');
            const thResume = document.createElement('th');
            thResume.style.cssText = 'background: #bbdefb; font-weight: bold; text-align: center; height: 40px; font-size: 1.1em;';
            thResume.innerHTML = `<div style="font-size: 1.1em;">RESUME</div>
                                    <div style="font-size: 0.8em; margin-top: 4px; color: #555;">P: Processed, R: Rescheduled, C: Cancelled, T: Total</div>
                                    <div>Total services for the day: ${totalServicios}<div>`;
            trResume.appendChild(thResume);

            crews.forEach((crew, idx) => {
                const td = document.createElement('td');
                td.style.cssText = 'height: 40px; font-size: 1.1em; text-align: center; padding: 6px;';
                td.innerHTML = `<div style="display: flex; justify-content: space-around; font-size: 0.9em; font-weight: bold;">
                                    <span style="background: #4CAF50; color: white; padding: 2px 6px; border-radius: 4px;">P: ${procesados[idx]}</span>
                                    <span style="background: #FF9800; color: white; padding: 2px 6px; border-radius: 4px;">R: ${replanificados[idx]}</span>
                                    <span style="background: #F44336; color: white; padding: 2px 6px; border-radius: 4px;">C: ${cancelados[idx]}</span>
                                    <span style="background: #2196F3; color: white; padding: 2px 6px; border-radius: 4px;">T: ${totales[idx]}</span>
                                </div>`;
                trResume.appendChild(td);
            });
            tbody.appendChild(trResume);

            console.log(`✅ ${servicios.length} servicios a mostrar: `, servicios);
            clientes.forEach(cliente => {
                const tr = document.createElement('tr');
                const tdCliente = document.createElement('td');
                tdCliente.textContent = cliente;
                tdCliente.style.cssText = 'font-weight: bold; background: #f9f9f9; border: 1px solid #ddd; padding: 6px; position: sticky; left: 0; z-index: 2;';
                tr.appendChild(tdCliente);

                crews.forEach((crew, idx) => {
                    const servicio = servicios.find(s => s.cliente === cliente && s.truck === crew);
                    const td = document.createElement('td');
                    td.style.cssText = 'border: 1px solid #ddd; padding: 6px; text-align: center;';

                    if (servicio) {
                        totales[idx]++;
                        if (servicio.finalizado) {
                            td.textContent = '✅';
                            td.classList.add('celda-procesado');
                            procesados[idx]++;
                        } else if (servicio.estado_visita === 'replanificado') {
                            td.textContent = '🔄';
                            td.classList.add('celda-replanificado');
                            replanificados[idx]++;
                        } else if (servicio.estado_visita === 'cancelado') {
                            td.textContent = '❌';
                            td.classList.add('celda-cancelado');
                            cancelados[idx]++;
                        } else if (servicio.estado_visita === 'programado' && servicio.hora_aviso_usuario === null) {
                            td.textContent = '🕒';
                            td.classList.add('celda-por-procesar');
                        } else {
                            td.textContent = '🟢';
                            td.classList.add('celda-por-procesar');
                        }
                    } else {
                        td.textContent = '—';
                        td.classList.add('celda-sin-asignar');
                    }
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });

            const resumeRow = tbody.querySelector('tr');
            crews.forEach((crew, idx) => {
                const td = resumeRow.cells[idx + 1];
                td.innerHTML = `<div style="display: flex; justify-content: space-around; font-size: 0.9em; font-weight: bold;">
                                    <span style="background: #4CAF50; color: white; padding: 2px 6px; border-radius: 4px;">P: ${procesados[idx]}</span>
                                    <span style="background: #FF9800; color: white; padding: 2px 6px; border-radius: 4px;">R: ${replanificados[idx]}</span>
                                    <span style="background: #F44336; color: white; padding: 2px 6px; border-radius: 4px;">C: ${cancelados[idx]}</span>
                                    <span style="background: #2196F3; color: white; padding: 2px 6px; border-radius: 4px;">T: ${totales[idx]}</span>
                                </div>`;
            });

        } catch (err) {
            console.error("Error al cargar matriz diaria:", err);
            tbody.innerHTML = `<tr><td colspan="100">Error: ${err.message}</td></tr>`;
        }
    }

    // === AQUÍ agregas esta línea ===
    //esperarYcargarCarrusel();

    function actualizarStatusM2(id_servicio) {
        // Buscar el servicio actualizado
        const servicio = window.serviciosData?.find(s => s.id_servicio == id_servicio);
        if (!servicio) return;

        // Obtener el elemento del popup si está abierto
        const marker = window.mapMarkers?.[id_servicio];
        if (!marker) return;

        const popup = marker.getPopup();
        if (!popup || !popup._content) return;

        // === Lógica equivalente al CASE SQL ===
        const { hora_inicio_gps, hora_fin_gps } = servicio;
        let statusText = 'Not yet attended';

        if (hora_inicio_gps && hora_fin_gps) {
            // Service Performed
            const inicio = new Date(hora_inicio_gps);
            const fin = new Date(hora_fin_gps);
            const diffSec = (fin - inicio) / 1000;
            const h = String(Math.floor(diffSec / 3600)).padStart(2, '0');
            const m = String(Math.floor((diffSec % 3600) / 60)).padStart(2, '0');
            const s = String(Math.floor(diffSec % 60)).padStart(2, '0');
            statusText = `Service Performed (${h}:${m}:${s})`;
        }
        else if (hora_inicio_gps && !hora_fin_gps) {
            // Started
            const inicio = new Date(hora_inicio_gps);
            const ahora = new Date();
            const diffMin = Math.floor((ahora - inicio) / 60000); // minutos
            statusText = `Started (${diffMin} min ago)`;
        }
        else if (!hora_inicio_gps && hora_fin_gps) {
            // Finished
            statusText = 'Finished';
        }

        // === Actualizar solo el div con class "tit_status2" ===
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = popup.getContent();

        const status2El = tempDiv.querySelector('.tit_status2');
        if (status2El) {
            status2El.innerHTML = `<b>Status M2:</b> ${statusText}`;
        }

        // Reemplazar contenido del popup
        popup.setContent(tempDiv.innerHTML);

        // Si el popup está abierto, se actualiza visualmente
        if (popup.isOpen()) {
            popup.update();
        }

        // ✅ Actualizar automáticamente cada minuto para "Started"
        if (hora_inicio_gps && !hora_fin_gps) {
            // Limpiar temporizador previo si existe
            if (window.statusM2Timers?.[id_servicio]) {
                clearInterval(window.statusM2Timers[id_servicio]);
            }

            const timer = setInterval(() => {
                actualizarStatusM2(id_servicio);
            }, 10000); // Cada minuto

            // Guardar referencia
            if (!window.statusM2Timers) window.statusM2Timers = {};
            window.statusM2Timers[id_servicio] = timer;
        }
        else {
            // Limpiar si ya no es "Started"
            if (window.statusM2Timers?.[id_servicio]) {
                clearInterval(window.statusM2Timers[id_servicio]);
                delete window.statusM2Timers[id_servicio];
            }
        }
    }

    // === MONITOREO CONTINUO DEL GPS PARA SINCRONIZAR ESTADO AUTOMÁTICAMENTE ===
    async function iniciarMonitoreoGPSContinuo() {
        //console.log("🚀 Iniciando monitoreo continuo del GPS...");

        // Solo proceder si existe window.serviciosData
        if (!Array.isArray(window.serviciosData)) {
            console.warn("⚠️ window.serviciosData no disponible aún. Reintentando...");
            return;
        }

        try {
            const res = await fetch('/app/ajax/motor2Ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_motor2: 'obtener_ultimo_punto_todos'
                })
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const data = await res.json();
            if (!data?.puntos || !Array.isArray(data.puntos)) return;

            const umbralMetros = window.APP_CONFIG?.umbral_metros || 150;

            // Procesar cada punto GPS recibido
            for (const punto of data.puntos) {
                const truck = punto.truck;
                const lat = parseFloat(punto.lat);
                const lng = parseFloat(punto.lng);

                if (isNaN(lat) || isNaN(lng)) continue;

                // Buscar servicios asociados a este camión
                const serviciosCamion = window.serviciosData.filter(s => s.truck === truck);

                for (const servicio of serviciosCamion) {
                    if (!servicio.lat || !servicio.lng) continue;

                    const distancia = calcularDistanciaMetros(lat, lng, servicio.lat, servicio.lng);
                    const estaCerca = distancia <= umbralMetros;

                    const inicioMarcado = !!servicio.hora_inicio_gps;
                    const finMarcado = !!servicio.hora_fin_gps;

                    // Caso 1: Detectar INICIO del servicio
                    if (estaCerca && !inicioMarcado) {
                        console.log(`📍 GPS detecta inicio para servicio ${servicio.id_servicio} (${servicio.cliente})`);
                        await sincronizarEstadoGPS(servicio.id_servicio, 'inicio', punto.timestamp);

                        // Opcional: Actualizar localmente
                        servicio.hora_inicio_gps = punto.timestamp;
                    }

                    // Caso 2: Detectar FIN del servicio
                    else if (!estaCerca && inicioMarcado && !finMarcado) {
                        console.log(`🏁 GPS detecta fin para servicio ${servicio.id_servicio} (${servicio.cliente})`);
                        await sincronizarEstadoGPS(servicio.id_servicio, 'fin', punto.timestamp);

                        // Opcional: Actualizar localmente
                        servicio.hora_fin_gps = punto.timestamp;
                    }
                }
            }
        } catch (err) {
            console.error('❌ Error en monitoreo GPS continuo:', err);
        }
    }

    // === SEGUIMIENTO MÚLTIPLE DE VEHÍCULOS – Emulado con datos reales ===
    window.gpsMarkers = {};
    window.gpsPolylines = {};
    window.gpsPositions = {};

    // O si no usas eventos, inicia después de esperarYcargarCarrusel()
    // === SISTEMA DE FLECHA HACIA MAPA ===
    // Variables globales para el sistema de flecha
    window.mousePosition = { x: 0, y: 0 };
    window.flechaActual = null;
    window.tooltipActual = null;
    window.lineaActual = null;
    window.puntaActual = null;
    window.servicioActualRastreado = null;

    // Capturar posición del mouse
    document.addEventListener('mousemove', function (event) {
        window.mousePosition = {
            x: event.clientX,
            y: event.clientY
        };
    });

    // Verificar si el mouse está sobre un elemento del carrusel
    function mouseSobreElementoCarrusel() {
        // Usar la misma referencia del carrusel que usas en insertarTarjeta
        const carrusel = window.carrusel ? window.carrusel.contenedor : document.getElementById('carrusel');

        if (!carrusel || !window.mousePosition) {
            return null;
        }

        const rect = carrusel.getBoundingClientRect();

        // Verificar si el carrusel tiene dimensiones válidas
        if (rect.width === 0 && rect.height === 0) {
            return null;
        }

        const mouseX = window.mousePosition.x;
        const mouseY = window.mousePosition.y;

        // Verificar si el mouse está dentro del carrusel
        const dentroDelCarrusel = (
            mouseX >= rect.left &&
            mouseX <= rect.right &&
            mouseY >= rect.top &&
            mouseY <= rect.bottom
        );

        if (!dentroDelCarrusel) {
            return null;
        }

        // Buscar elementos del carrusel que contengan el mouse
        const elementos = carrusel.querySelectorAll('[data-servicio-id]');
        if (elementos.length === 0) {
            return null;
        }

        // Verificar cada elemento
        for (let elemento of elementos) {
            const elemRect = elemento.getBoundingClientRect();
            const mouseEnElemento = (
                mouseX >= elemRect.left && mouseX <= elemRect.right &&
                mouseY >= elemRect.top && mouseY <= elemRect.bottom
            );

            if (mouseEnElemento) {
                const servicioId = elemento.getAttribute('data-servicio-id');
                return {
                    elemento: elemento,
                    servicioId: servicioId
                };
            }
        }

        return null;
    }


    // Encontrar contenedor del mapa
    function encontrarContenedorMapa() {
        const posiblesSelectores = ['#map', '#map-container', '.map', '.leaflet-container', '#leaflet-map'];
        for (let selector of posiblesSelectores) {
            const elemento = document.querySelector(selector);
            if (elemento) return elemento;
        }
        const divs = document.querySelectorAll('div');
        for (let div of divs) {
            if (div.className && (div.className.includes('leaflet') || div.className.includes('map'))) {
                return div;
            }
        }
        return document.body;
    }

    // Crear flecha con tooltip
    function crearFlechaHaciaMapa(servicio) {
        // Eliminar flecha anterior si existe
        eliminarFlechaActual();

        if (!servicio || !servicio.lat || !servicio.lng) return;

        // Obtener posición del marcador en el mapa
        if (window.mapMarkers && window.mapMarkers[servicio.id_servicio]) {
            const marker = window.mapMarkers[servicio.id_servicio];
            const latlng = marker.getLatLng();
            const puntoMapa = window.map.latLngToContainerPoint(latlng);

            // Obtener posición del elemento del carrusel
            const elementoCarrusel = document.querySelector(`[data-servicio-id="${servicio.id_servicio}"]`);
            if (!elementoCarrusel) return;

            const rectCarrusel = elementoCarrusel.getBoundingClientRect();
            const puntoCarrusel = {
                x: rectCarrusel.left + rectCarrusel.width / 2,
                y: rectCarrusel.top + rectCarrusel.height / 2
            };

            // Calcular ángulo y distancia
            const deltaX = puntoMapa.x - puntoCarrusel.x;
            const deltaY = puntoMapa.y - puntoCarrusel.y;
            const distancia = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
            const angulo = Math.atan2(deltaY, deltaX) * 180 / Math.PI;

            // Crear línea de la flecha
            const linea = document.createElement('div');
            linea.className = 'flecha-linea';
            linea.style.width = distancia + 'px';
            linea.style.height = '4px';
            linea.style.left = puntoCarrusel.x + 'px';
            linea.style.top = puntoCarrusel.y + 'px';
            linea.style.transform = `rotate(${angulo}deg)`;

            // Crear punta de la flecha
            const punta = document.createElement('div');
            punta.className = 'flecha-punta';
            punta.style.left = (puntoMapa.x - 10) + 'px';
            punta.style.top = (puntoMapa.y - 6) + 'px';
            punta.style.transform = `rotate(${angulo}deg)`;

            // Crear tooltip con información
            const tooltip = document.createElement('div');
            tooltip.className = 'flecha-tooltip';
            tooltip.innerHTML = `
            <div style="font-size: 11px; margin-bottom: 2px;">${servicio.cliente}</div>
            <div style="font-size: 10px; opacity: 0.9;">${servicio.direccion || 'Sin dirección'}</div>
        `;
            tooltip.style.left = (puntoMapa.x + 15) + 'px';
            tooltip.style.top = (puntoMapa.y - 30) + 'px';

            // Añadir al documento
            const contenedorMapa = encontrarContenedorMapa();
            contenedorMapa.appendChild(linea);
            contenedorMapa.appendChild(punta);
            contenedorMapa.appendChild(tooltip);

            // Guardar referencias
            window.lineaActual = linea;
            window.puntaActual = punta;
            window.tooltipActual = tooltip;
        }
    }

    // Eliminar flecha actual
    function eliminarFlechaActual() {
        if (window.lineaActual && window.lineaActual.parentNode) {
            window.lineaActual.parentNode.removeChild(window.lineaActual);
        }
        if (window.puntaActual && window.puntaActual.parentNode) {
            window.puntaActual.parentNode.removeChild(window.puntaActual);
        }
        if (window.tooltipActual && window.tooltipActual.parentNode) {
            window.tooltipActual.parentNode.removeChild(window.tooltipActual);
        }

        window.lineaActual = null;
        window.puntaActual = null;
        window.tooltipActual = null;
    }

    // Función principal de rastreo
    // Función principal de rastreo DEBUG
    function rastrearMouseYCrearFlecha() {
        const elementoBajoMouse = mouseSobreElementoCarrusel();

        // console.log('🔍 Rastreando mouse...', elementoBajoMouse);

        if (elementoBajoMouse) {
            console.log('🎯 Elemento encontrado:', elementoBajoMouse.servicioId);

            // Si es un servicio diferente al actualmente rastreado
            if (elementoBajoMouse.servicioId !== window.servicioActualRastreado) {
                console.log('🔄 Cambio de servicio detectado');

                // Eliminar flecha anterior
                eliminarFlechaActual();

                // Buscar el servicio completo
                if (window.serviciosData) {
                    const servicio = window.serviciosData.find(s => s.id_servicio == elementoBajoMouse.servicioId);
                    console.log('📋 Servicio encontrado:', servicio);

                    if (servicio) {
                        // Crear nueva flecha
                        console.log('➡️ Creando flecha para servicio:', servicio.id_servicio);
                        setTimeout(() => {
                            crearFlechaHaciaMapa(servicio);
                        }, 50);

                        window.servicioActualRastreado = elementoBajoMouse.servicioId;
                    } else {
                        console.log('❌ Servicio no encontrado en window.serviciosData');
                    }
                }
            }
        } else {
            // Si el mouse no está sobre ningún elemento, eliminar flecha
            if (window.servicioActualRastreado) {
                console.log('🧹 Limpiando flecha anterior');
                eliminarFlechaActual();
                window.servicioActualRastreado = null;
            }
        }
    }

    // Iniciar rastreo continuo
    function iniciarRastreoFlecha() {
        setInterval(rastrearMouseYCrearFlecha, 100);
    }

    // === INICIAR SISTEMA DE FLECHA ===
    // Iniciar rastreo de flecha
    iniciarRastreoFlecha();


    console.log('🟢 script.js cargado');

    // === 1. Función para obtener vehículos únicos ===
    function obtenerVehiclesUnicos() {
        if (!window.serviciosData || !Array.isArray(window.serviciosData)) return [];
        const seen = new Set();
        const vehicles = [];
        window.serviciosData.forEach(s => {
            const id = s.truck;
            const color = s.crew_color_principal || '#2196F3';
            if (id && !seen.has(id)) {
                seen.add(id);
                vehicles.push({ id, color });
            }
        });
        return vehicles;
    }

    // === 2. Función para llenar el panel con botones ===
    function actualizarPanelFlotante(vehicles) {
        const contenedor = document.getElementById('contenedor-vehiculos-historico');
        if (!contenedor) return;
        contenedor.innerHTML = '';
        vehicles.forEach(v => {
            const btn = document.createElement('button');
            btn.textContent = v.id;
            btn.style.background = v.color;
            btn.style.color = getColorContraste(v.color); // ← Esta función viene de motor2.js
            btn.style.border = 'none';
            btn.style.borderRadius = '4px';
            btn.style.padding = '2px 6px';
            btn.style.fontSize = '0.8em';
            btn.style.fontWeight = 'bold';
            btn.style.cursor = 'pointer';
            btn.style.minWidth = '60px';
            btn.style.width = '31%';
            btn.style.textAlign = 'center';
            btn.title = `Show ${v.id} on map`;
            btn.onclick = (e) => {
                e.stopPropagation();
                abrirPopupVehiculo(v.id); // ← Viene de motor2.js
            };
            contenedor.appendChild(btn);
        });
    }

    // === 3. Inicializar panel al cargar ===
    setTimeout(() => {
        const vehicles = obtenerVehiclesUnicos();
        if (vehicles.length > 0) {
            actualizarPanelFlotante(vehicles);
        } else {
            console.log('🟡 Aún no hay servicios para mostrar en el panel');
        }
    }, 1000);

    // === 4. Escuchar si los servicios se actualizan más tarde ===
    window.addEventListener('serviciosActualizados', () => {
        const vehicles = obtenerVehiclesUnicos();
        actualizarPanelFlotante(vehicles);
    });

    // Escuchar clic en botón de reconciliación
    document.getElementById('btn-reconciliar-historico')?.addEventListener('click', async () => {
        const confirmado = await suiteConfirm(
            "Reconcile Data",
            "Execute historical reconciliation for the last 7 days?\n\nServices without GPS time will be processed."
        );

        if (!confirmado) return;
        try {
            const res = await fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_servicios: 'reconciliar_datos_historicos' })
            });

            const data = await res.json();

            if (data.success) {
                await suiteAlertSuccess("Éxito", data.message);
                console.log("📊 Detalles:", data.detalles);
            } else {
                await suiteAlertError("Error", data.error || "No se pudo completar");
            }
        } catch (err) {
            await suiteAlertError("Error", "Fallo de conexión en btn-reconciliar-historico: " + err.message);
            console.error(err);
        }
    });

    document.getElementById('btn-reconciliar-historico-completo')?.addEventListener('click', async () => {
        const confirmado = await suiteConfirm(
            "Reconciliar Datos",
            "¿Ejecutar reconciliación histórica?\n\nSe procesarán servicios sin hora GPS."
        );

        if (!confirmado) return;
        try {
            const res = await fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_servicios: 'reconciliar_datos_historicos_final' })
            });

            const data = await res.json();

            if (data.success) {
                await suiteAlertSuccess("Éxito", data.message);
                console.log("📊 Detalles:", data.detalles);
            } else {
                await suiteAlertError("Error", data.error || "No se pudo completar");
            }
        } catch (err) {
            await suiteAlertError("Error", "Fallo de conexión en btn-reconciliar-historico: " + err.message);
            console.error(err);
        }
    });

    // === LISTENERS PARA BOTONES DEL MODAL DE SERVICIO ===
    document.getElementById('btn-inicio-actividades').onclick = () => {
        prepararAccion(window.servicioTemporalId, 'inicio_actividades');
    };
    document.getElementById('btn-finalizar-servicio').onclick = () => {
        prepararAccion(window.servicioTemporalId, 'finalizado');
    };
    document.getElementById('btn-replanificar-servicio').onclick = () => {
        prepararAccion(window.servicioTemporalId, 'replanificado');
    };
    document.getElementById('btn-cancelar-servicio').onclick = () => {
        prepararAccion(window.servicioTemporalId, 'cancelado');
    };
    document.getElementById('btn-guardar-notas').onclick = () => {
        guardarNotas(window.servicioTemporalId, window.estadoTemporal);
    };
    document.getElementById('btn-cancelar-notas').onclick = () => {
        cancelarNotas();
    };

    //if (window.location.href.includes('dashboard-view')) {

    // 1. Abrir modal al hacer click en "Where is it?"
    const menuDondeEsta = document.getElementById('menu-donde-esta');
    const modalDondeEsta = document.getElementById('modal-donde-esta');
    const selectVehiculo = document.getElementById('select-vehiculo-donde-esta');
    const closeModalDondeEsta = document.getElementById('close_modal_donde_esta');
    const infoVehiculo = document.getElementById('info-vehiculo-donde-esta');

    if (menuDondeEsta && modalDondeEsta && selectVehiculo && closeModalDondeEsta && infoVehiculo) {
        menuDondeEsta.onclick = async function (e) {
            e.preventDefault();
            document.getElementById('menu-lateral').style.display = 'none';
            document.getElementById('menu-overlay').style.display = 'none';
            infoVehiculo.innerHTML = '';
            modalDondeEsta.style.display = 'flex';

            // Cargar vehículos del día
            const res = await fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_servicios: 'listar_vehiculos' })
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
                const resProps = await fetch('/app/ajax/serviciosAjax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ modulo_servicios: 'listar_propiedades' })
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

    // Inicializar comportamiento de submenús (hover + focus)
    (function initSubmenuBehaviour() {
        const submenuToggles = document.querySelectorAll('.has-submenu');

        submenuToggles.forEach(container => {
            const toggleLink = container.querySelector('.submenu-toggle');

            // mouse enter / leave
            container.addEventListener('mouseenter', () => {
                container.classList.add('open');
                container.setAttribute('aria-expanded', 'true');
            });
            container.addEventListener('mouseleave', () => {
                container.classList.remove('open');
                container.setAttribute('aria-expanded', 'false');
            });

            // keyboard accessibility: focusin / focusout
            container.addEventListener('focusin', () => {
                container.classList.add('open');
                container.setAttribute('aria-expanded', 'true');
            });
            container.addEventListener('focusout', (ev) => {
                // si el foco sale completamente del contenedor, cerramos
                if (!container.contains(ev.relatedTarget)) {
                    container.classList.remove('open');
                    container.setAttribute('aria-expanded', 'false');
                }
            });

            // permitir toggle con tecla Enter / Space sobre el enlace
            if (toggleLink) {
                toggleLink.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        const isOpen = container.classList.toggle('open');
                        container.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                        // mover foco al primer elemento del submenu si se abre
                        if (isOpen) {
                            const first = container.querySelector('.submenu a');
                            if (first) first.focus();
                        }
                    }
                });
            }
        });
    })();

    // 3. Botón cerrar modal
    document.getElementById('close_modal_donde_esta').onclick = function () {
        document.getElementById('modal-donde-esta').style.display = 'none';
        if (window.markerDondeEsta && window.mapa) {
            window.mapa.removeLayer(window.markerDondeEsta);
        }
        document.getElementById('menu-lateral').style.display = '';
        document.getElementById('menu-overlay').style.display = '';
    };
    //}    

    // === 5. Si usas polling o eventos personalizados, dispara este evento cuando haya datos ===
    // Ejemplo: cuando asignes window.serviciosData, haz:
    // window.dispatchEvent(new Event('serviciosActualizados'));


    document.getElementById('lista-asignados').addEventListener('dragover', (e) => {
        // Bloquear drag si la fecha está procesada
        if (window.dispatchConfig && window.dispatchConfig.dateIsProcessed) {
            e.preventDefault();
            return;
        }
        e.preventDefault(); // permite soltar
        e.dataTransfer.dropEffect = 'move';
    });

    document.getElementById('lista-no-asignados').addEventListener('dragover', (e) => {
        if (window.dispatchConfig && window.dispatchConfig.dateIsProcessed) {
            e.preventDefault();
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    });

    document.getElementById('lista-asignados').addEventListener('drop', (e) => {
        if (window.dispatchConfig && window.dispatchConfig.dateIsProcessed) {
            e.preventDefault();
            return;
        }
        e.preventDefault();
        const id = e.dataTransfer.getData('text/plain');
        const item = document.querySelector(`.item-despacho[data-id="${id}"]`);
        if (item && item.dataset.tipo === 'no-asignado') {
            moverItem(item, 'no-asignado', null);
            document.getElementById('btn-mover-a-asignados').disabled = true;
            document.getElementById('btn-mover-a-no-asignados').disabled = true;
        }
    });

    document.getElementById('lista-no-asignados').addEventListener('drop', (e) => {
        if (window.dispatchConfig && window.dispatchConfig.dateIsProcessed) {
            e.preventDefault();
            return;
        }
        e.preventDefault();
        const id = e.dataTransfer.getData('text/plain');
        const item = document.querySelector(`.item-despacho[data-id="${id}"]`);
        if (item && item.dataset.tipo === 'asignado') {
            verificarRuta(item, 'asignado');
        }
    });

    console.log("🚀 Sistema de flecha hacia mapa iniciado");
}); // Cierre del DOMContentLoaded



// === Función para llenar el panel flotante con vehículos activos ===
function actualizarPanelFlotante(vehicles) {
    const contenedor = document.getElementById('contenedor-vehiculos-historico');
    if (!contenedor) return;

    // Limpiar contenido anterior
    contenedor.innerHTML = '';

    // Crear botón para cada vehículo
    vehicles.forEach(v => {
        const btn = document.createElement('button');
        btn.textContent = v.id;
        btn.style.background = v.color;
        btn.style.color = getColorContraste(v.color); // Usa la función que ya tienes
        btn.style.border = 'none';
        btn.style.borderRadius = '4px';
        btn.style.padding = '2px 6px';
        btn.style.fontSize = '0.8em';
        btn.style.fontWeight = 'bold';
        btn.style.cursor = 'pointer';
        btn.style.minWidth = '60px';
        btn.style.width = '31%';
        btn.style.textAlign = 'center';

        // 🔥 Asignar evento onClick
        btn.onclick = (e) => {
            e.stopPropagation(); // Evita interferencia si el contenedor es "draggable"
            abrirPopupVehiculo(v.id);
        };

        // Opcional: tooltip%
        btn.title = `Show ${v.id} on map`;

        contenedor.appendChild(btn);
    });
}

// ———————————————————————————————————————
// Función: actualizarCeldaActividad
// Actualiza solo los elementos visuales de Activity (Start, End, Duration)
// ———————————————————————————————————————
function actualizarCeldaActividad(servicio) {
    const id = servicio.id_servicio;
    const inicio = servicio.hora_aviso_usuario;
    const fin = servicio.hora_finalizado;

    const startSpan = document.getElementById(`start-${id}`);
    const endSpan = document.getElementById(`end-${id}`);
    const durationSpan = document.getElementById(`duration-${id}`);

    if (!startSpan || !endSpan || !durationSpan) return;

    // Formatear hora: HH:mm
    const formatTime = (datetime) => {
        if (!datetime) return '';
        const date = new Date(datetime);
        return isNaN(date.getTime())
            ? ''
            : date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const inicioFormato = formatTime(inicio);
    const finFormato = formatTime(fin);

    // --- Start ---
    if (inicioFormato) {
        startSpan.innerHTML = `<b>Start:</b> ${inicioFormato}`;
        startSpan.style.display = 'block';
    } else {
        startSpan.style.display = 'none';
    }

    // --- End ---
    if (finFormato) {
        endSpan.innerHTML = `<b>End:</b> ${finFormato}`;
        endSpan.style.display = 'block';
    } else {
        endSpan.style.display = 'none';
    }

    // --- Duration ---
    let durationText = 'Waiting for data to calculate';

    if (inicio && !fin) {
        durationText = 'In progress';
    } else if (inicio && fin) {
        const start = new Date(inicio).getTime();
        const end = new Date(fin).getTime();
        const diff = end - start;
        const h = String(Math.floor(diff / 3600000)).padStart(2, '0');
        const m = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
        const s = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
        durationText = `${h}:${m}:${s}`;
    }

    durationSpan.innerHTML = `<b>Duration:</b> ${durationText}`;
}

// ———————————————————————————————————————
// Función: actualizarCeldaActividadGps
// Actualiza los elementos visuales de Activity GPS (hora_inicio_gps, hora_fin_gps, tiempo_servicio)
// ———————————————————————————————————————
function actualizarCeldaActividadGps(servicio) {
    const id = servicio.id_servicio;
    const gpsCell = document.querySelector(`.gps-cell[data-id='${id}']`);
    if (!gpsCell) return;

    // Buscar o crear los elementos
    let startGps = gpsCell.querySelector('.gps-start');
    let endGps = gpsCell.querySelector('.gps-end');
    let durationGps = gpsCell.querySelector('.gps-duration');
    if (!startGps) {
        startGps = document.createElement('span');
        startGps.className = 'gps-start';
        gpsCell.appendChild(startGps);
    }
    if (!endGps) {
        endGps = document.createElement('span');
        endGps.className = 'gps-end';
        gpsCell.appendChild(endGps);
    }
    if (!durationGps) {
        durationGps = document.createElement('span');
        durationGps.className = 'gps-duration';
        gpsCell.appendChild(durationGps);
    }

    // Formatear hora
    const formatTime = (datetime) => {
        if (!datetime) return '';
        const date = new Date(datetime);
        return isNaN(date.getTime())
            ? ''
            : date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const inicioGps = formatTime(servicio.hora_inicio_gps);
    const finGps = formatTime(servicio.hora_fin_gps);

    // --- Start GPS ---
    const ahora = new Date();
    let ejecucion = new Date(
        ahora.getFullYear(),
        ahora.getMonth(),
        ahora.getDate(),
        0, 1, 0 // 00:01:00
    );

    // Si ya pasó la hora de hoy, programar para mañana
    if (ejecucion <= ahora) {
        ejecucion.setDate(ejecucion.getDate() + 1);
    }

    const esperaMs = ejecucion - ahora;

    //console.log(`⏱️ Programado para ejecutar en: ${Math.ceil(esperaMs / 1000 / 60)} minutos`);

    setTimeout(async () => {
        console.log("⏰ [00:01:00] Ejecutando reinicio del sistema...");

        try {
            location.reload();

        } catch (error) {
            console.error("❌ Error en ejecución programada:", error);
            setTimeout(() => location.reload(), 5000);
        }
    }, esperaMs);
}