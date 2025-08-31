try {
    const res = await fetch('/app/ajax/serviciosAjax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ modulo_servicios: 'listar' })
    });

    if (!res.ok) throw new Error('HTTP ' + res.status);

    const data = await res.json();

    // === 1. Validar si hay servicios ===
    if (!data || !Array.isArray(data) || data.length === 0) {
        console.warn("⚠️ No hay servicios programados para hoy.");

        // === 2. Inicializar el carrusel con un mensaje único ===
        const contenedorCarrusel = document.getElementById('carrusel');
        if (!contenedorCarrusel) {
            console.error("❌ No se encontró el contenedor #carrusel");
        } else {
            // === Resetear estilos del contenedor para garantizar visibilidad ===
            Object.assign(contenedorCarrusel.style, {
                'background-color': 'white',
                'color': 'black',
                'height': '30%',
                'min-height': '200px',
                'display': 'flex',
                'flex-direction': 'column',
                'justify-content': 'center',
                'align-items': 'center',
                'text-align': 'center',
                'padding': '20px',
                'box-sizing': 'border-box',
                'font-family': 'Arial, sans-serif',
                'border-radius': '8px',
                'margin': '10px',
                'box-shadow': '0 2px 5px rgba(0,0,0,0.1)'
            });

            // === Insertar mensaje con estilos en línea ===
            contenedorCarrusel.innerHTML = '';
            const mensajeDiv = document.createElement('div');
            mensajeDiv.innerHTML = `
                    <h3 style="
                        color: #333;
                        margin: 0 0 10px 0;
                        font-size: 1.5em;
                    ">No hay servicios programados para hoy</h3>
                    <p style="
                        color: #555;
                        margin: 0;
                        font-size: 1em;
                    ">Por favor, verifica el estado del sistema o carga nuevos servicios.</p>
                `;
            contenedorCarrusel.appendChild(mensajeDiv);
        }
        // === 3. No detener el flujo: continuar con el mapa y el GPS ===
    } else {
        // === Si hay servicios, inicializar el carrusel con los datos ===
        window.serviciosData = data;
        carrusel.datos = data;

        actualizarEspacio(); // Ajusta el tamaño del carrusel

        let indiceCarrusel = 0;
        insertarTarjeta(data[indiceCarrusel]);
        indiceCarrusel = (indiceCarrusel + 1) % data.length;

        // Avanzar carrusel automáticamente
        setInterval(() => {
            insertarTarjeta(data[indiceCarrusel]);
            indiceCarrusel = (indiceCarrusel + 1) % data.length;
        }, config.intervaloCarrusel);
    }

    // === 4. SEGUIMIENTO EN TIEMPO REAL DEL CREW (independiente de servicios) ===
    let crewMarker = null;
    let polilinea = null;
    const rutaCoordenadas = [];


    // === Iniciar seguimiento GPS solo si hay servicios ===
    if (data && Array.isArray(data) && data.length > 0) {
        iniciarSeguimientoGPS();
    } else {
        console.log("ℹ️ No hay servicios programados. El seguimiento GPS no se inicia.");
    }

    // === 5. Marcadores fijos SOLO si hay servicios ===
    if (data && Array.isArray(data) && data.length > 0) {
        data.forEach(s => {
            if (s.lat && s.lng) {
                const marker = L.marker([s.lat, s.lng], {
                    icon: L.divIcon({
                        html: `<div style="background:${s.crew_color_principal};width:16px;height:16px;border-radius:50%;border:2px solid white;box-shadow:0 0 5px rgba(0,0,0,0.5);"></div>`,
                        className: '',
                        iconSize: [16, 16],
                        iconAnchor: [8, 8]
                    })
                }).addTo(window.map);

                marker.bindPopup(`
                        <b>${s.cliente}</b><br>
                        ${s.direccion || 'Sin dirección'}<br>
                        <small><b>Crew:</b> ${s.truck || 'N/A'}</small>
                    `);
            }
        });
    }

} catch (error) {
    console.error("❌ Error crítico al cargar datos:", error);

    // Mensaje de error en el carrusel
    const contenedorCarrusel = document.getElementById('carrusel');
    if (contenedorCarrusel) {
        contenedorCarrusel.innerHTML = `
                <div style="
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    height: 100%;
                    text-align: center;
                    color: #c00;
                    font-family: Arial, sans-serif;
                    padding: 20px;
                    background: #fee;
                    border: 1px solid #fcc;
                    border-radius: 8px;
                ">
                    <h3>❌ Error de sistema</h3>
                    <p>No se pudieron cargar los servicios. Contacta al administrador.</p>
                </div>
            `;
    }
}
