const registrosPorPagina = document.getElementById('registrosPorPagina')

// Verificar si existe un elemento con id="retorno"
if (document.getElementById("retorno")) {
    const btn_retorno = document.getElementById("retorno");

    btn_retorno.addEventListener("click", async function (e) {
        e.preventDefault(); // ← Prevenir redirección inmediata

        // Si hay filtro activo, limpiarlo primero
        if (document.getElementById("eli_fil")) {
            try {
                await limpiarBusqueda(); // ← Esperar a que termine
            } catch (err) {
                console.warn('No se pudo limpiar el filtro:', err);
                // Continuar igual
            }
        }

        // Ahora sí, redirigir
        window.location.href = ruta_retorno;
    });
}

if (registrosPorPagina) {
    registrosPorPagina.addEventListener('change', actualizarVista);
}

async function actualizarVista() {
    // === 3. Capturar datos ===
    const rpp = registrosPorPagina.value
    if (!window.CONFIG_BUSQUEDA) {
        console.error('❌ CONFIG_BUSQUEDA no definido');
        suiteAlertError('Error', 'Search settings unavailable');
        return;
    }
    const filtro_act = document.getElementById("txt_buscador");
    if (filtro_act) filtro_act.value = '';
    const valor = filtro_act?.value.trim();

    const config = window.CONFIG_BUSQUEDA;
    const clave = `modulo_${config.modulo}`;

    const payload = {
        [clave]: 'cambio_cant_reg', // ← Notación de corchetes
  
        datos: {
            origen: config.modulo,
            registrosPorPagina: rpp,
            url: config.url,
            buscado: valor,
            ruta_retorno: config.ruta_retorno,
            orden: config.orden,
            direccion: config.direccion
        }
    };

    try {
        const ruta = `/app/ajax/${config.modulo}Ajax.php`;

        const res = await fetch(ruta, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const html = await res.text();

        const contenedor = document.getElementById('datos_act');
        if (contenedor) {
            contenedor.innerHTML = html;
        } else {
            throw new Error('Contenedor #datos_act no encontrado');
        }
    } catch (err) {
        console.error('Error al limpiar filtro:', err);
        suiteAlertError('Error', 'Could not clear filter');
    }
}
