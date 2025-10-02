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

// Funciones específicas para cada tipo de alerta
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
            { texto: opcionesConfirm.cancelar || 'Cancelar', tipo: 'secondary', valor: false },
            { texto: opcionesConfirm.aceptar || 'Aceptar', tipo: 'primary', valor: true }
        ]
    };
    return mostrarSuiteAlert('warning', titulo, mensaje, opciones);
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
        const botones = opciones.botones || [{ texto: 'OK', tipo: 'primary' }];

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
            } else {
                console.warn(`⚠️ Error updating GPS time: ${resp.error}`);
            }
        } catch (err) {
            console.error('Error updating GPS time with backend:', err);
        }
    }

    window.addEventListener('servicioIniciado', function(e) {
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

    window.addEventListener('servicioCerrado', function(e) {
        const { id_servicio, hora } = e.detail;

        // 1. Sincronizar con el backend
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

        const serviciosRaw = await res.json();

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

            const intervaloPolling = setInterval(async () => {
                intentos++;
                document.getElementById('contador-polling').textContent = `Attempts: ${intentos}`;
                console.log(`🔍 Polling intento ${intentos}`);

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

                    const nuevosServicios = await res.json();

                    if (Array.isArray(nuevosServicios) && nuevosServicios.length > 0) {
                        console.log("✅ Servicios detectados, recargando sistema");
                        clearInterval(intervaloPolling);
                        
                        // Limpiar contenedor
                        contenedor.innerHTML = '';
                        contenedor.style.display = '';

                        // Recargar todo el flujo con los nuevos datos
                        procesarYSistema(nuevosServicios); // ← Llama al mismo flujo principal
                    }
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
                console.log(`✅ ${serviciosValidos.length} servicios con coordenadas válidas`);
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

            // === 10. Marcadores fijos ===
            const vehicles = [];
            const seenTrucks = new Set();

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
                    const marker = L.marker([s.lat, s.lng], {
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
            
            // ✅ Disparar evento para GPS
            const event = new Event('serviciosCargados');
            document.dispatchEvent(event);
            console.log("✅ Evento 'serviciosCargados' lanzado");

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
        }

    } catch (error) {
        console.error("❌ Error al cargar servicios:", error);
    }

    // === 12. MODAL DE DETALLES ===
    async function abrirModalDetalles(servicio, mantenerCarrusel = false) {
        servicioTemporal = servicio;

        // === Pausar carrusel solo si no se indica lo contrario ===
        if (!mantenerCarrusel && carrusel.intervalo) {
            clearInterval(carrusel.intervalo);
            carrusel.intervalo = null;
        }

        try {
            // === 1. Cargar datos del servicio (obligatorio) ===
            const response = await fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_servicios: 'obtener_servicio_detalle',
                    id_servicio: servicio.id_servicio
                })
            });

            const data = await response.json();

            if (!data) {
                await suiteAlertError("Error", "Could not load service");
                reanudarCarrusel();
                return;
            }

            const servicioActualizado = data;

            // === 2. Decidir si se carga el historial según el campo 'historial' del cliente ===
            let historialServicio = [];

            // ✅ Verificamos si el cliente tiene historial habilitado
            if (servicioActualizado.historial === 1) {
                try {
                    const historialResponse = await fetch('/app/ajax/serviciosAjax.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            modulo_servicios: 'obtener_historial_servicio',
                            id_cliente: servicioActualizado.id_cliente
                        })
                    });

                    if (historialResponse.ok) {
                        const historialData = await historialResponse.json();
                        if (Array.isArray(historialData)) {
                            historialServicio = historialData; // ← Asume que devuelve el array directo
                        } else if (historialData?.historial && Array.isArray(historialData.historial)) {
                            historialServicio = historialData.historial;
                        }
                    }
                } catch (err) {
                    console.warn("Historial no disponible (error de red o timeout)", err);
                    // No rompe → historial queda como []
                }
            } else {
                console.log("Historial deshabilitado para este cliente");
            }

            // ✅ === CORRECCIÓN CLAVE: Asignar el historial al objeto para que esté disponible después ===
            servicioActualizado.historial = historialServicio;

            // === Continuamos con la lógica de normalización y renderizado ===
            const tieneInicio = !!servicioActualizado.hora_aviso_usuario;
            const estaFinalizado = !!servicioActualizado.finalizado;
            const estadoServicio = servicioActualizado.estado_servicio;

            const puedeAcciones = !estaFinalizado && estadoServicio !== 'finalizado';

            const notasHistorial = servicioActualizado.historial?.filter(h => h.campo_afectado === 'notas') || [];
            const ultimaNota = notasHistorial.length > 0 ? notasHistorial[notasHistorial.length - 1] : { valor_nuevo: '' };

            const carac_serv = !servicioActualizado.estado_servicio ||
                servicioActualizado.estado_servicio === 'pendiente' ||
                servicioActualizado.estado_servicio === 'inicio_actividades';

            const sin_nota = servicioActualizado.estado_servicio === 'finalizado' ||
                servicioActualizado.estado_servicio === 'cancelado' ||
                servicioActualizado.estado_servicio === 'replanificado';

            const contenido = `
            <div class="modal-overlay_gps">
                <div class="modal-contenedor">
                    <button id="close_modal" class="modal-cerrar1">✕</button>
                    <div class="modal-grid-3x2">
                        <!-- 1,1 + 2,1: Información principal -->
                        <div class="modal-info" style="grid-column: 1; grid-row: 1 / 3;">
                            <h3>Service Details</h3>
                            <table class="tabla-detalles">
                                <tr><th>Client</th><td>${servicioActualizado.cliente}</td></tr>
                                <tr><th>Truck</th><td>${servicioActualizado.truck}</td></tr>
                                <tr><th>Scheduled Day</th><td>${servicioActualizado.dia_servicio}</td></tr>
                                <tr><th>Operational Status</th><td><span class="estado-badge" style="background:${getEstadoColor(servicioActualizado.estado_servicio)}">${getEstadoMessage(servicioActualizado.estado_servicio)}</span></td></tr>
                                <tr><th>Visit Status</th><td><span class="estado-badge" style="background:${getEstadoColor(servicioActualizado.estado_visita)}">${servicioActualizado.estado_visita || '—'}</span></td></tr>
                                <tr><th>Coordinates</th><td>${servicioActualizado.lat}, ${servicioActualizado.lng}</td></tr>
                                <tr><th>Address</th><td>${servicioActualizado.direccion}</td></tr>
                                <tr><th>Start</th><td>${formatoHora(servicioActualizado.hora_aviso_usuario)}</td></tr>
                                <tr><th>End</th><td>${formatoHora(servicioActualizado.hora_finalizado)}</td></tr>
                            </table>

                            <h4>Crew Members</h4>
                            <div class="crew-detalle-lista">
                                ${servicioActualizado.crew_integrantes?.map(p => `
                                    <div class="crew-item" style="background:${p.color};">
                                        ${p.nombre} ${p.apellido}
                                    </div>
                                `).join('') || 'Not available'}
                            </div>

                            ${ultimaNota.valor_nuevo && servicioActualizado.estado_servicio !== 'usuario_alerto' ? `
                            <div class="ultima-nota" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 6px; font-size: 0.9em;">
                                <strong>Last Note:</strong> "${ultimaNota.valor_nuevo}"
                            </div>` : ''}
                        </div>

                        <!-- 1,2: Acciones -->
                        <div class="modal-acciones" style="grid-column: 2; grid-row: 1;">
                            <h4>Actions</h4>
                            <button class="btn-accion inicio" 
                                    onclick="prepararAccion(${servicioActualizado.id_servicio}, 'inicio_actividades')"
                                    ${tieneInicio || estaFinalizado ? 'disabled' : ''}>
                                Start of activities
                            </button>
                            <button class="btn-accion procesado" 
                                    onclick="prepararAccion(${servicioActualizado.id_servicio}, 'finalizado')"
                                    ${puedeAcciones ? '' : 'disabled'}>
                                Processed
                            </button>
                            <button class="btn-accion replanificado" 
                                    onclick="prepararAccion(${servicioActualizado.id_servicio}, 'replanificado')"
                                    ${puedeAcciones ? '' : 'disabled'}>
                                Rescheduled
                            </button>
                            <button class="btn-accion cancelado" 
                                    onclick="prepararAccion(${servicioActualizado.id_servicio}, 'cancelado')"
                                    ${puedeAcciones ? '' : 'disabled'}>
                                Cancelled
                            </button>
                        </div>

                        <!-- 2,2: Notas o Tiempo Activo -->
                        <div class="modal-notas" style="grid-column: 2; grid-row: 2;">

                        ${(() => {
                    if (!sin_nota) {
                        return `
                                    <h4>Notes</h4>
                                    <textarea placeholder="Add notes..." class="input-notas" 
                                            ${estaFinalizado ? 'disabled' : ''}></textarea>
                                    <div class="acciones-notas" style="display: none;">
                                        <button class="btn-guardar-notas" onclick="guardarNotas(${servicioActualizado.id_servicio}, '${servicioActualizado.estado_servicio}')">Save</button>
                                        <button class="btn-cancelar-notas" onclick="cancelarNotas()">Cancel</button>
                                    </div>
                                            `;
                    } else {
                        return `
                                    <h4>Preview Notes</h4>
                                    <textarea placeholder="Add notes..." class="input-notas" disabled>${servicioActualizado.notas_anteriores}</textarea>`;
                    }
                })()}


                        ${(() => {
                    const estadoInicial = !servicioActualizado.estado_servicio ||
                        servicioActualizado.estado_servicio === 'pendiente' ||
                        servicioActualizado.estado_servicio === 'usuario_alerto';

                    if (estadoInicial) {
                        return `
                                            `;
                    } else {
                        const mostrarTiempoTotal = estaFinalizado && servicioActualizado.hora_aviso_usuario && servicioActualizado.hora_finalizado;
                        const duracion = mostrarTiempoTotal
                            ? calcularDuracion(servicioActualizado.hora_aviso_usuario, servicioActualizado.hora_finalizado)
                            : '00:00:00';

                        return `
                                        <h4>${mostrarTiempoTotal ? 'Total Time' : 'Active Time'}</h4>
                                        <span class="contador-modal" style="font-size: 1.2em; font-weight: bold; color: ${mostrarTiempoTotal ? '#4CAF50' : '#2196F3'};">
                                            ${mostrarTiempoTotal ? duracion : '00:00:00'}
                                        </span>
                                        <p style="font-size: 0.8em; color: #666; margin-top: 5px;">
                                            ${mostrarTiempoTotal ? 'Duration of service' : 'Time since start'}
                                        </p>
                                    `;
                    }
                })()}


                        </div>

                        <!-- 3,1 + 3,2: Historial -->
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
                                <tbody id="historial-${servicioActualizado.id_servicio}">
                                    <tr><td colspan="4">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>`;

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = contenido;
            document.body.appendChild(tempDiv.firstElementChild);

            // === Iniciar contador si corresponde ===
            if (tieneInicio && !estaFinalizado && !puedeAcciones) {
                const contador = document.querySelector('.contador-modal');
                if (contador) {
                    iniciarContador(contador, servicioActualizado.hora_aviso_usuario);
                }
            }

            // === Llenar historial del servicio ===
            const historialBody = document.getElementById(`historial-${servicioActualizado.id_servicio}`);
            if (historialBody) {
                historialBody.innerHTML = '';
                if (historialServicio.length > 0) {
                    historialServicio.forEach(h => {
                        const tr = document.createElement('tr');
                        const duracion = h.tiempo_duracion ? h.tiempo_duracion : '—';
                        tr.innerHTML = `
                        <td>${h.fecha_programada}</td>
                        <td>${h.truck}</td>
                        <td>${h.estado_visita ? `<span class="estado-badge" style="background:${getEstadoColor(h.estado_visita)}">${h.estado_visita}</span>` : '—'}</td>
                        <td><span style="font-size: 0.9em; color: #555;">${duracion}</span></td>
                    `;
                        historialBody.appendChild(tr);
                    });
                } else {
                    const tr = document.createElement('tr');
                    tr.innerHTML = '<td colspan="4">No history available</td>';
                    historialBody.appendChild(tr);
                }
            }

            // === Cerrar modal ===
            const modalOverlay = document.querySelector('.modal-overlay_gps');

            const btnCerrar = document.getElementById('close_modal');
            btnCerrar.addEventListener('click', () => {
console.log("Cerrando modal");                
                modalOverlay.remove();
                if (!mantenerCarrusel) {
                    reanudarCarrusel();
                }
                servicioTemporal = null;
            });

            modalOverlay.addEventListener('click', (e) => {
                if (e.target === modalOverlay) {
                    modalOverlay.remove();
                    if (!mantenerCarrusel) {
                        reanudarCarrusel();
                    }
                    servicioTemporal = null;
                }
            });

        } catch (err) {
            console.error("Error crítico en modal:", err);
            await suiteAlertError("Error", "No se pudo cargar el servicio");

            reanudarCarrusel();
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
        const textarea = document.querySelector('.input-notas');
        const acciones = document.querySelector('.acciones-notas');

        const servicio = carrusel.datos.find(s => s.id_servicio == id_servicio);
        if (!servicio) return;

        const tieneInicio = !!servicio.hora_aviso_usuario;
        const esFinalizado = !!servicio.finalizado;

        if (esFinalizado) {
            suiteAlertInfo("Info", "This service is already completed. No further actions allowed.")
            return;
        }

        if (nuevoEstado === 'inicio_actividades' && tieneInicio) {
            suiteAlertInfo("Info", "Start of activities has already been registered.")
            return;
        }

        window.servicioTemporalId = id_servicio;

        textarea.style.backgroundColor = '';
        textarea.style.color = '';

        if (nuevoEstado === 'finalizado') {
            textarea.style.backgroundColor = '#e8f5e8';
            textarea.style.color = '#2e7d32';
        } else if (nuevoEstado === 'replanificado') {
            textarea.style.backgroundColor = '#fff8e1';
            textarea.style.color = '#f57c00';
        } else if (nuevoEstado === 'cancelado') {
            textarea.style.backgroundColor = '#ffcdd2';
            textarea.style.color = '#c62828';
        } else if (nuevoEstado === 'inicio_actividades') {
            textarea.style.backgroundColor = '#e3f2fd';
            textarea.style.color = '#1565c0';
        }

        textarea.disabled = false;
        textarea.focus();
        acciones.style.display = 'flex';
        estadoTemporal = nuevoEstado;
    };

    // === Guardar notas ===
    window.guardarNotas = function (id_servicio, estadoActual) {
        const textarea = document.querySelector('.input-notas');
        const notas = textarea.value.trim();
        const nuevoEstado = estadoTemporal;

        if (!nuevoEstado) {
            suiteAlertWarning("Warning", "No action selected")
            return;
        }

        if (!servicioTemporal) {
            suiteAlertError("Error", "Service not found. Reload the page.");
            return;
        }

        const data = {
            modulo_servicios: 'actualizar_estado_con_historial',
            id_servicio: id_servicio,
            estado: nuevoEstado,
            notas: notas,
            estado_actual: estadoActual,
            cliente: servicioTemporal.cliente,
            truck: servicioTemporal.truck
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

                        if (nuevoEstado === 'inicio_actividades' && !servicio.hora_aviso_usuario) {
                            servicio.hora_aviso_usuario = new Date().toISOString();
                        }

                        if (nuevoEstado === 'finalizado' && !servicio.hora_finalizado) {
                            servicio.hora_finalizado = new Date().toISOString();
                        }

                        actualizarTarjeta(servicio);
                    }

                    document.querySelector('.modal-overlay_gps')?.remove();
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
        const textarea = document.querySelector('.input-notas');
        const acciones = document.querySelector('.acciones-notas');

        textarea.disabled = true;
        textarea.style.backgroundColor = '#f9f9f9';
        textarea.style.opacity = '0.7';
        textarea.style.color = '';
        textarea.value = '';
        acciones.style.display = 'none';

        estadoTemporal = null;
        window.servicioTemporalId = null;
        servicioTemporal = null;
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
    let ultimoTiempo = new Date().toISOString();

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
                    ultimoTiempo = new Date().toISOString();
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
            const servicios = await res.json();

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
                        const nuevosServicios = await res.json();

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
            const servicios = await res.json();

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
    esperarYcargarCarrusel();

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
            "Reconciliar Datos",
            "¿Ejecutar reconciliación histórica para los últimos 7 días?\n\nSe procesarán servicios sin hora GPS."
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

    // === 5. Si usas polling o eventos personalizados, dispara este evento cuando haya datos ===
    // Ejemplo: cuando asignes window.serviciosData, haz:
    // window.dispatchEvent(new Event('serviciosActualizados'));


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
    if (inicioGps) {
        startGps.innerHTML = `<b>Start GPS:</b> ${inicioGps}`;
        startGps.style.display = 'block';
    } else {
        startGps.style.display = 'none';
    }

    // --- End GPS ---
    if (finGps) {
        endGps.innerHTML = `<b>End GPS:</b> ${finGps}`;
        endGps.style.display = 'block';
    } else {
        endGps.style.display = 'none';
    }

    // --- Duration GPS ---
    let durationText = 'Waiting for data to calculate';
    if (servicio.hora_inicio_gps && !servicio.hora_fin_gps) {
        durationText = 'In progress';
    } else if (servicio.hora_inicio_gps && servicio.hora_fin_gps) {
        const start = new Date(servicio.hora_inicio_gps).getTime();
        const end = new Date(servicio.hora_fin_gps).getTime();
        const diff = end - start;
        const h = String(Math.floor(diff / 3600000)).padStart(2, '0');
        const m = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
        const s = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
        durationText = `${h}:${m}:${s}`;
    }
    durationGps.innerHTML = `<b>Duration GPS:</b> ${durationText}`;
}

// ———————————————————————————————————————
// CARGA OBLIGATORIA A LAS 00:01:00
// ———————————————————————————————————————
function esperarYcargarCarrusel() {
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

    console.log(`⏱️ Programado para ejecutar en: ${Math.ceil(esperaMs / 1000 / 60)} minutos`);

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

// === ESTILOS CSS PARA LA FLECHA ===
// (Esto puede ir también en tu archivo CSS principal)
const estiloFlecha = `
.flecha-tooltip {
    position: absolute;
    background: linear-gradient(135deg, #4CAF50, #2E7D32);
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: bold;
    white-space: nowrap;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    pointer-events: none;
    animation: fadeIn 0.3s ease;
}

.flecha-tooltip::before {
    content: '';
    position: absolute;
    width: 0;
    height: 0;
    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-top: 8px solid #4CAF50;
    bottom: -8px;
    left: 50%;
    transform: translateX(-50%);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.flecha-linea {
    position: absolute;
    background: #4CAF50;
    transform-origin: 0 0;
    z-index: 999;
    pointer-events: none;
}

.flecha-punta {
    position: absolute;
    width: 0;
    height: 0;
    border-top: 6px solid transparent;
    border-bottom: 6px solid transparent;
    border-left: 10px solid #4CAF50;
    z-index: 999;
    pointer-events: none;
}
`;

// Insertar estilos en el documento
const styleSheet = document.createElement('style');
styleSheet.textContent = estiloFlecha;
document.head.appendChild(styleSheet);

