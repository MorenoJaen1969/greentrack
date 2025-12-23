// busqueda-global.js

// === Función reutilizable para realizar búsquedas ===
async function realizarBusqueda(termino = '') {
    if (!window.CONFIG_BUSQUEDA) {
        console.error('❌ CONFIG_BUSQUEDA no definido');
        suiteAlertError('Error', 'Search settings unavailable');
        return;
    }

    const config = window.CONFIG_BUSQUEDA;
    const payload = {
        modulo_buscado: config.modulo,
        parametros: { accion: termino ? "Con Filtro" : "Sin Filtro" },
        datos: {
            origen: config.modulo,
            registrosPorPagina: config.registrosPorPagina,
            url: config.url,
            buscado: termino, // ← cadena vacía si no hay filtro
            ruta_retorno: config.ruta_retorno,
            orden: config.orden,
            direccion: config.direccion
        }
    };

    try {
        const res = await fetch('/app/ajax/busquedaAjax.php', {
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
        console.error('Error en búsqueda:', err);
        suiteAlertError('Error', 'Could not perform search');
    }
}

// === Función para limpiar filtro ===
window.limpiarBusqueda = async function() {
    const input = document.getElementById('txt_buscador');
    if (input) input.value = '';

    if (!window.CONFIG_BUSQUEDA) {
        console.error('❌ CONFIG_BUSQUEDA no definido');
        suiteAlertError('Error', 'Search settings unavailable');
        return;
    }

    const config = window.CONFIG_BUSQUEDA;
    const payload = {
        modulo_buscado: config.modulo,
        parametros: { accion: "Sin Filtro" },
        datos: {
            origen: config.modulo,
            registrosPorPagina: config.registrosPorPagina,
            url: config.url,
            buscado: "",
            ruta_retorno: config.ruta_retorno,
            orden: config.orden,
            direccion: config.direccion
        }
    };

    try {
        const res = await fetch('/app/ajax/busquedaAjax.php', {
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
};

// === Inicializar búsqueda y delegar eventos ===
document.addEventListener('DOMContentLoaded', () => {
    // Búsqueda
    document.addEventListener('click', async (e) => {
        if (e.target.id === 'btn-busca') {
            const input = document.getElementById('txt_buscador');
            const valor = input?.value.trim();
            if (valor) {
                // Reutiliza la lógica de búsqueda
                if (!window.CONFIG_BUSQUEDA) {
                    suiteAlertWarning('Search', 'Configuración no disponible');
                    return;
                }
                const config = window.CONFIG_BUSQUEDA;
                const payload = {
                    modulo_buscado: config.modulo,
                    parametros: { accion: "Con Filtro" },
                    datos: {
                        origen: config.modulo,
                        registrosPorPagina: config.registrosPorPagina,
                        url: config.url,
                        buscado: valor,
                        ruta_retorno: config.ruta_retorno,
                        orden: config.orden,
                        direccion: config.direccion
                    }
                };

                try {
                    const res = await fetch('/app/ajax/busquedaAjax.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const html = await res.text();
                    document.getElementById('datos_act').innerHTML = html;
                } catch (err) {
                    suiteAlertError('Error', 'Could not perform search');
                }
            } else {
                window.limpiarBusqueda();
            }
        }

        // Eliminar filtro (delegación)
        if (e.target.id === 'eli_fil') {
            e.preventDefault();
            window.limpiarBusqueda();
        }
    });

    // Permitir búsqueda con Enter
    document.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && e.target.id === 'txt_buscador') {
            e.preventDefault();
            document.getElementById('btn-busca')?.click();
        }
    });
});