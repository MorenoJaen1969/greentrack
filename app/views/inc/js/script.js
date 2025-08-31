
/**
 * GREENTRACK LIVE – DASHBOARD FINAL
 * Autor: Mario Moreno
 * Fecha: Agosto 2025
 * 
 * Funcionalidades:
 * - Carrusel dinámico con reposicionamiento físico
 * - Mapa Leaflet con marcadores fijos
 * - Seguimiento en tiempo real del CREW desde GPS real
 * - Ruta continua sin triángulos
 * - Modal clickeable con pausa del carrusel
 * - Historial de cliente y acciones en tiempo real
 */

document.addEventListener('DOMContentLoaded', async () => {
    console.log("🟢 GreenTrack Live: Iniciando dashboard");
    let indiceCarruselGlobal = 0;
    let servicioTemporal = null;

    // === 11. SEGUIMIENTO GPS ===
    let crewMarker = null;
    let polilinea = null;
    const rutaCoordenadas = [];

    // === Mapeo de estados: frontend (español) → backend (código o inglés) ===
    const MAPEO_ESTADOS = {
        'inicio_actividades': 'usuario_alerto',   // o 'start', 'in_progress', etc.
        'finalizado': 'finalizado',               // si el backend lo acepta en español
        'replanificado': 'replanificado',
        'cancelado': 'cancelado'
    };

    // === 1. Configuración ===
    const config = {
        separacion: 10,           // Espacio entre tarjetas
        intervaloCarrusel: 6000,  // Avance cada 6 segundos
        intervaloGPS: 3000        // Consulta GPS cada 3 segundos
    };

    // === 2. Estructura del carrusel ===
    const carrusel = {
        contenedor: document.getElementById('servicio-carrusel'),
        datos: [],
        elementos: [],  // [más nuevo, ..., más viejo]
        TOTAL_DISPLAY: 0,
        espacioUsado: 0,
        intervalo: null           // Referencia al setInterval
    };

    if (!carrusel.contenedor) {
        console.error("❌ No se encontró #servicio-carrusel");
        return;
    }

    // === 3. Inicializar mapa ===
    let map;
    try {
        map = L.map('live-map').setView([30.3096, -95.4750], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        map.scrollWheelZoom.disable();
        window.map = map;
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
                    <div class="cell aviso-cell">
                        <div class="tit_d_grid">Activity</div>
                        <div class="hora" style="line-height: 1.2;">
                            ${servicio.hora_aviso_usuario ? `<span><b>Start:</b> ${inicioFormato}</span><br>` : ''}
                            ${servicio.hora_finalizado ? `<span><b>End:</b> ${finFormato}</span><br>` : ''}
                            <span><b>Duration:</b> ${duracionTexto}</span>
                        </div>
                    </div>

                    <!-- 2,1: Tiempo Servicio -->
                    <div class="cell tiempo-cell">
                        <div class="tit_d_grid">Time</div>
                        <span class="contador">${horaInicio ? '00:00:00' : '—'}</span>
                    </div>

                    <!-- 2,2: GPS -->
                    <div class="cell gps-cell">
                        <div class="tit_d_grid">Activity GPS</div>
                        <span class="hora">${servicio.hora_inicio_gps ? new Date(servicio.hora_inicio_gps).toLocaleTimeString() : '—'}</span>
                        <br>
                        <span class="diff"></span>
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

        if (servicio.finalizado) {
            card.classList.add('finalizado');
        }

        // === CLICK: Abrir modal ===
        card.addEventListener('click', (e) => {
            e.stopPropagation();
            abrirModalDetalles(servicio, false);
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
    }

    function iniciarSeguimientoGPS() {
        crewMarker = L.marker([30.3096, -95.4750], {
            icon: L.divIcon({
                html: '<div style="background:#FF5722;width:18px;height:18px;border-radius:50%;border:3px solid white;box-shadow:0 0 8px rgba(0,0,0,0.6);"></div>',
                className: '',
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            })
        }).addTo(window.map);
        crewMarker.bindPopup("<b>CREW moving</b>");

        polilinea = L.polyline([], { color: 'blue', weight: 5, opacity: 0.7 }).addTo(window.map);

        setInterval(async () => {
            try {
                const res = await fetch('/webhooks/verizon/ultima_coordenada.php?vehicle_id=Crew%201');
                const data = await res.json();

                if (data.lat && data.lng) {
                    const latlng = [data.lat, data.lng];
                    crewMarker.setLatLng(latlng);
                    crewMarker.setPopupContent(`<b>CREW 1</b><br>Lat: ${data.lat.toFixed(6)}<br>Lng: ${data.lng.toFixed(6)}`);
                    rutaCoordenadas.push(latlng);
                    polilinea.setLatLngs(rutaCoordenadas);
                }
            } catch (error) {
                console.error("Error al obtener coordenada:", error);
            }
        }, config.intervaloGPS);
    }

    // === 9. Cargar servicios y empezar ===
    try {
        const res = await fetch('/app/ajax/serviciosAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ modulo_servicios: 'listar' })
        });

        if (!res.ok) throw new Error('HTTP ' + res.status);
        const servicios = await res.json();

        // === SI HAY SERVICIOS: Iniciar carrusel normalmente ===
        if (Array.isArray(servicios) && servicios.length > 0) {
            window.serviciosData = servicios;
            carrusel.datos = servicios;
            actualizarEspacio();

            indiceCarruselGlobal = 0;
            insertarTarjeta(servicios[indiceCarruselGlobal]);
            indiceCarruselGlobal = (indiceCarruselGlobal + 1) % servicios.length;

            // === Iniciar carrusel con referencia guardada ===
            carrusel.intervalo = setInterval(() => {
                insertarTarjeta(carrusel.datos[indiceCarruselGlobal]);
                indiceCarruselGlobal = (indiceCarruselGlobal + 1) % carrusel.datos.length;
            }, config.intervaloCarrusel);

            // === 10. Marcadores fijos ===
            servicios.forEach(s => {
                if (s.lat && s.lng) {
                    const marker = L.marker([s.lat, s.lng], {
                        icon: L.divIcon({
                            html: `<div style="background:${s.crew_color_principal};width:16px;height:16px;border-radius:50%;border:2px solid white;box-shadow:0 0 5px rgba(0,0,0,0.5);"></div>`,
                            className: '',
                            iconSize: [16, 16],
                            iconAnchor: [8, 8]
                        })
                    }).addTo(window.map);

                    marker.bindPopup(`<b>${s.cliente}</b><br>${s.direccion || 'No address'}<br><div class="tit_d_grid"><b>Crew:</b> ${s.truck || 'N/A'}</div>`);
                }
            });

            iniciarSeguimientoGPS();
            return; // Fin normal
        }

        // === SI NO HAY SERVICIOS: Activar polling ===
        console.warn("⚠️ No hay servicios para hoy. Iniciando polling...");
        let intentos = 0;
        const maxIntentos = 120; // 60 minutos (120 * 30s)
        const contenedor = document.getElementById('carrusel') || document.body;

        contenedor.style.display = 'flex';
        contenedor.innerHTML = `
        <div style="display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100%; text-align: center; color: #666; font-family: Arial, sans-serif; padding: 20px; width: 100%;">
            <h3>Waiting for today's services...</h3>
            <p>System will start automatically when services are loaded.</p>
            <div id="contador-polling" style="font-size: 0.9em; margin-top: 10px;">Attempts: 0</div>
        </div>`;

        const intervaloPolling = setInterval(async () => {
            intentos++;
            document.getElementById('contador-polling').textContent = `Attempts: ${intentos}`;

            try {
                const res = await fetch('/app/ajax/serviciosAjax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ modulo_servicios: 'listar' })
                });

                if (!res.ok) return;
                const nuevosServicios = await res.json();

                if (Array.isArray(nuevosServicios) && nuevosServicios.length > 0) {
                    clearInterval(intervaloPolling);
                    console.log("✅ Servicios cargados tras polling", nuevosServicios);

                    // Limpiar y reiniciar
                    contenedor.innerHTML = '';
                    contenedor.style.display = '';

                    // Iniciar carrusel
                    window.serviciosData = nuevosServicios;
                    carrusel.datos = nuevosServicios;
                    actualizarEspacio();

                    let indiceCarruselGlobal = 0;
                    insertarTarjeta(nuevosServicios[indiceCarruselGlobal]);
                    indiceCarruselGlobal = (indiceCarruselGlobal + 1) % nuevosServicios.length;

                    carrusel.intervalo = setInterval(() => {
                        insertarTarjeta(carrusel.datos[indiceCarruselGlobal]);
                        indiceCarruselGlobal = (indiceCarruselGlobal + 1) % carrusel.datos.length;
                    }, config.intervaloCarrusel);

                    // Marcadores
                    nuevosServicios.forEach(s => {
                        if (s.lat && s.lng) {
                            const marker = L.marker([s.lat, s.lng], {
                                icon: L.divIcon({
                                    html: `<div style="background:${s.crew_color_principal};width:16px;height:16px;border-radius:50%;border:2px solid white;box-shadow:0 0 5px rgba(0,0,0,0.5);"></div>`,
                                    className: '',
                                    iconSize: [16, 16],
                                    iconAnchor: [8, 8]
                                })
                            }).addTo(window.map);
                            marker.bindPopup(`<b>${s.cliente}</b><br>${s.direccion || 'No address'}<br><b>Crew:</b> ${s.truck || 'N/A'}`);
                        }
                    });

                    iniciarSeguimientoGPS();
                }

                if (intentos >= maxIntentos) {
                    clearInterval(intervaloPolling);
                    document.getElementById('contador-polling').textContent = 'Error: Timeout. No services loaded.';
                    console.error("❌ Timeout: No se cargaron servicios después de 60 minutos");
                }
            } catch (err) {
                console.error("Error en polling:", err);
            }
        }, 30000); // Cada 30 segundos

    } catch (error) {
        console.error("❌ Error al cargar servicios:", error);
    }

    // === 12. MODAL DE DETALLES ===
    function abrirModalDetalles(servicio, mantenerCarrusel = false) {
        servicioTemporal = servicio;

        // === Pausar carrusel ===
        if (!mantenerCarrusel && carrusel.intervalo) {
            clearInterval(carrusel.intervalo);
            carrusel.intervalo = null;
        }

        // === Cargar datos del servicio y su historial ===
        Promise.all([
            // === Cargar datos reales del servicio desde el backend ===
            fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_servicios: 'obtener_servicio_detalle',
                    id_servicio: servicio.id_servicio
                })
            }).then(r => r.json()),

            fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_servicios: 'obtener_historial_servicio',
                    id_cliente: servicio.id_cliente
                })
            }).then(r => r.json())
        ])
            .then(([data, historialData]) => {
                if (!data) {
                    alert("Error: Could not load service");
                    reanudarCarrusel();
                    return;
                }

                const servicioActualizado = data;
                const historialServicio = historialData || [];

                // === Normalizar valores nulos a booleanos ===
                const tieneInicio = !!servicioActualizado.hora_aviso_usuario;
                const estaFinalizado = !!servicioActualizado.finalizado;
                const estadoServicio = servicioActualizado.estado_servicio;

                // === Determinar si se permiten acciones ===
                const puedeAcciones = !estaFinalizado && estadoServicio !== 'finalizado';

                // === Obtener última nota (excepto para "Inicio de actividades") ===
                const notasHistorial = servicioActualizado.historial?.filter(h => h.campo_afectado === 'notas') || [];
                const ultimaNota = notasHistorial.length > 0 ? notasHistorial[notasHistorial.length - 1] : { valor_nuevo: '' };

                const contenido = `
                <div class="modal-overlay">
                    <div class="modal-contenedor">
                        <button class="modal-cerrar">✕</button>
                        <div class="modal-grid-3x2">
                            <!-- 1,1 + 2,1: Información principal -->
                            <div class="modal-info" style="grid-column: 1; grid-row: 1 / 3;">
                                <h3>Service Details</h3>
                                <table class="tabla-detalles">
                                    <tr><th>Client</th><td>${servicioActualizado.cliente}</td></tr>
                                    <tr><th>Truck</th><td>${servicioActualizado.truck}</td></tr>
                                    <tr><th>Scheduled Day</th><td>${servicioActualizado.dia_servicio}</td></tr>
                                    <tr><th>Operational Status</th><td><span class="estado-badge" style="background:${getEstadoColor(servicioActualizado.estado_servicio)}">${servicioActualizado.estado_servicio}</span></td></tr>
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

                                <!-- Mostrar última nota -->
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
                        // Estados que permiten edición de notas
                        const estadoInicial = !servicioActualizado.estado_servicio ||
                            servicioActualizado.estado_servicio === 'pendiente' ||
                            servicioActualizado.estado_servicio === 'usuario_alerto';

                        if (estadoInicial) {
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
                            // Mostrar tiempo: total si finalizado, activo si no
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
                </div>
            `;

                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = contenido;
                document.body.appendChild(tempDiv.firstElementChild);


                // =========================================================================================== //
                // === Cerrar con botón X ===
                const modalOverlay = document.querySelector('.modal-overlay');
                const btnCerrar = modalOverlay.querySelector('.modal-cerrar');

                btnCerrar.addEventListener('click', () => {
                    modalOverlay.remove();
                    reanudarCarrusel();
                    servicioTemporal = null;
                });

                // === Cerrar al hacer clic fuera del contenedor ===
                modalOverlay.addEventListener('click', (e) => {
                    if (e.target === modalOverlay) {
                        modalOverlay.remove();
                        reanudarCarrusel();
                        servicioTemporal = null;
                    }
                });
                // =========================================================================================== //

                // === Llenar historial del servicio ===
                const historialBody = document.getElementById(`historial-${servicioActualizado.id_servicio}`);
                if (historialBody) {
                    historialBody.innerHTML = ''; // Limpiar "Loading..."

                    if (historialServicio.length > 0) {
                        historialServicio.forEach(h => {
                            const tr = document.createElement('tr');
                            const duracion = h.tiempo_duracion ? h.tiempo_duracion : '—';
                            tr.innerHTML = `
                            <td>${h.fecha_programada}</td>
                            <td>${h.truck}</td>
                            ${h.estado_visita
                                    ? `<td><span class="estado-badge" style="background:${getEstadoColor(h.estado_visita)}">${h.estado_visita}</span></td>`
                                    : '<td>—</td>'}
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

                // === Iniciar contador si hay hora de aviso y no está finalizado, y no se muestran notas ===
                if (!(!servicioActualizado.estado_servicio ||
                    servicioActualizado.estado_servicio === 'pendiente' ||
                    servicioActualizado.estado_servicio === 'usuario_alerto')) {

                    if (servicioActualizado.hora_aviso_usuario && !servicioActualizado.finalizado) {
                        const contador = document.querySelector('.contador-modal');
                        if (contador) {
                            iniciarContador(contador, servicioActualizado.hora_aviso_usuario);
                        }
                    }
                }

                // === Mostrar contador o tiempo total ===
                if (estaFinalizado && servicioActualizado.hora_aviso_usuario && servicioActualizado.hora_finalizado) {
                    // Ya se mostró el tiempo total
                } else if (tieneInicio && !estaFinalizado) {
                    const contador = document.querySelector('.contador-modal');
                    if (contador) {
                        iniciarContador(contador, servicioActualizado.hora_aviso_usuario);
                    }
                }

                // Cerrar con botón X
                document.querySelector('.modal-cerrar').addEventListener('click', () => {
                    document.querySelector('.modal-overlay').remove();
                    reanudarCarrusel();
                    servicioTemporal = null;
                });

                document.querySelector('.modal-overlay').addEventListener('click', (e) => {
                    if (e.target === e.currentTarget) {
                        e.target.remove();
                        reanudarCarrusel();
                    }
                });
            })
            .catch(err => {
                console.error("Error al cargar servicio actualizado:", err);
                alert("Could not load service. Try again.");
                reanudarCarrusel();
            });
    }

    // === MODAL para seleccionar cliente ===
    // === 1. Referencias ===
    const btnSelectClient = document.getElementById('btn-select-client');
    const modalSelectClient = document.getElementById('modal-select-client');
    const listaClientes = document.getElementById('lista-clientes');

    // === 2. Abrir modal de selección de cliente ===
    btnSelectClient.addEventListener('click', async () => {
        modalSelectClient.style.display = 'flex';
        await cargarListaClientes();
    });

    // === 3. Cerrar modal de selección ===
    modalSelectClient.addEventListener('click', (e) => {
        if (e.target === modalSelectClient) {
            modalSelectClient.style.display = 'none';
        }
    });

    modalSelectClient.querySelector('.modal-cerrar')?.addEventListener('click', () => {
        modalSelectClient.style.display = 'none';
    });

    // === 4. Cargar lista de clientes con sus servicios ===
    async function cargarListaClientes() {
        try {
            const res = await fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_servicios: 'listar_modal' })
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);
            const servicios = await res.json();

            if (!Array.isArray(servicios) || servicios.length === 0) {
                listaClientes.innerHTML = '<p>No services scheduled for today</p>';
                return;
            }

            // Agrupar servicios por cliente
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

    // === 5. Abrir modal del servicio seleccionado ===
    window.abrirModalDesdeSeleccion = function (id_servicio) {
        const servicio = carrusel.datos.find(s => s.id_servicio == id_servicio);
        if (servicio) {
            modalSelectClient.style.display = 'none';
            abrirModalDetalles(servicio, true);
        }
    };




    // === Función auxiliar: color por estado ===
    function getEstadoColor(estado) {
        switch (estado) {
            case 'finalizado':
            case 'usuario_alerto':
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
            alert("This service is already completed. No further actions allowed.");
            return;
        }

        if (nuevoEstado === 'inicio_actividades' && tieneInicio) {
            alert("Start of activities has already been registered.");
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
            alert("No action selected");
            return;
        }

        if (!servicioTemporal) {
            alert("Error: Service not found. Reload the page.");
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
                    alert(`Service updated to ${nuevoEstado}`);
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

                    document.querySelector('.modal-overlay')?.remove();
                    reanudarCarrusel();
                } else {
                    alert("Error: " + resp.error);
                }
            })
            .catch(err => {
                console.error("Error al guardar:", err);
                alert("Connection error");
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

    // === Modificar la función que inicia el carrusel ===
    function iniciarCarrusel() {
        if (!carrusel.intervalo && carrusel.datos.length > 0) {
            carrusel.intervalo = setInterval(() => {
                insertarTarjeta(carrusel.datos[indiceCarruselGlobal]);
                indiceCarruselGlobal = (indiceCarruselGlobal + 1) % carrusel.datos.length;
            }, config.intervaloCarrusel);
        }
    }

    // === Reanudar carrusel al cerrar modal ===
    window.reanudarCarrusel = function () {
        iniciarCarrusel();
    };

    // === Asegurar que el DOM esté listo para el botón Daily Status ===
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

    // === Función para cargar matriz diaria ===
    async function cargarMatrizDiaria() {
        const tbody = document.getElementById('matrix-body');
        const thead = document.querySelector('#daily-status-matrix thead tr');
        const tfoot = document.getElementById('matrix-footer');

        // Limpiar
        thead.innerHTML = '<th style="border: 1px solid #ddd; padding: 8px; position: sticky; top: 0; left: 0; background: #e0e0e0; z-index: 3;">Client</th>';
        tbody.innerHTML = '';
        tfoot.innerHTML = '';

        try {
            const res = await fetch('/app/ajax/serviciosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ modulo_servicios: 'listar' })
            });

            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }

            const servicios = await res.json();

            if (!Array.isArray(servicios)) {
                throw new Error('La respuesta no es un array');
            }

            // === 1. Obtener Crews y Clientes únicos ===
            const crews = [...new Set(servicios.map(s => s.truck))].sort();
            const clientes = [...new Set(servicios.map(s => s.cliente))].sort();

            // === 2. Totales por estado y por crew: P, R, C ===
            const procesados = Array(crews.length).fill(0);
            const replanificados = Array(crews.length).fill(0);
            const cancelados = Array(crews.length).fill(0);
            const totales = Array(crews.length).fill(0); // Total de servicios por truck

            // Llenar encabezados
            crews.forEach(crew => {
                const th = document.createElement('th');
                th.textContent = crew;
                th.style.cssText = 'border: 1px solid #ddd; padding: 8px; position: sticky; top: 0; background: #f0f0f0;';
                thead.appendChild(th);
            });


            // === 3. Fila RESUME (al inicio del tbody) ===
            const trResume = document.createElement('tr');
            const thResume = document.createElement('th');
            thResume.textContent = 'RESUME';
            thResume.style.cssText = 'background: #bbdefb; font-weight: bold; text-align: center; height: 40px; font-size: 1.1em;';

            // Usar innerHTML para incluir múltiples líneas
            thResume.innerHTML = `
                <div style="font-size: 1.1em;">RESUME</div>
                <div style="font-size: 0.8em; margin-top: 4px; color: #555;">
                    P: Processed, R: Rescheduled, C: Cancelled, T: Total
                </div>
            `;
            trResume.appendChild(thResume);

            crews.forEach((crew, idx) => {
                const td = document.createElement('td');
                td.style.cssText = 'height: 40px; font-size: 1.1em; text-align: center; padding: 6px;';

                // Celdas internas con colores
                td.innerHTML = `
                    <div style="display: flex; justify-content: space-around; font-size: 0.9em; font-weight: bold;">
                        <span style="background: #4CAF50; color: white; padding: 2px 6px; border-radius: 4px;">P: ${procesados[idx]}</span>
                        <span style="background: #FF9800; color: white; padding: 2px 6px; border-radius: 4px;">R: ${replanificados[idx]}</span>
                        <span style="background: #F44336; color: white; padding: 2px 6px; border-radius: 4px;">C: ${cancelados[idx]}</span>
                        <span style="background: #2196F3; color: white; padding: 2px 6px; border-radius: 4px;">T: ${totales[idx]}</span>
                    </div>
                `;
                trResume.appendChild(td);
            });
            tbody.appendChild(trResume);

            // === 4. Llenar filas ===
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
                        // Contar servicios por truck
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
                        } else {
                            td.textContent = '🕒';
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

            // === 5. Actualizar RESUME con valores reales ===
            const resumeRow = tbody.querySelector('tr');
            crews.forEach((crew, idx) => {
                const td = resumeRow.cells[idx + 1];
                td.innerHTML = `
                    <div style="display: flex; justify-content: space-around; font-size: 0.9em; font-weight: bold;">
                        <span style="background: #4CAF50; color: white; padding: 2px 6px; border-radius: 4px;">P: ${procesados[idx]}</span>
                        <span style="background: #FF9800; color: white; padding: 2px 6px; border-radius: 4px;">R: ${replanificados[idx]}</span>
                        <span style="background: #F44336; color: white; padding: 2px 6px; border-radius: 4px;">C: ${cancelados[idx]}</span>
                        <span style="background: #2196F3; color: white; padding: 2px 6px; border-radius: 4px;">T: ${totales[idx]}</span>
                    </div>
                `;
            });
        } catch (err) {
            console.error("Error al cargar matriz diaria:", err);
            tbody.innerHTML = `<tr><td colspan="100">Error: ${err.message}</td></tr>`;
        }
    }
});