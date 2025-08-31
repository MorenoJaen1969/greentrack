<!-- app/views/content/dashboard-view.php -->
<div class="dashboard-container">
    <!-- 30% - Carrusel de servicios -->
    <div class="carousel-wrapper">
        <div class="elem_row">
            <h3 class="carousel-title">
                Day's Itinirary 
                <span id="fecha-display" style="color: #fffb23ff; margin: 0 8px;">-- --</span>
                <span id="hora-display" style="color: #4aff1cff;">--:--:--</span>
                <button id="btn-daily-status" class="button" style="margin-left: 10px;">
                    Daily Status
                </button>
                <button id="btn-select-client" class="button" style="margin-left: 10px;">
                    Select Client
                </button>
            </h3>
        </div>

        <!-- Contenedor del carrusel (igual a tu estructura) -->
        <div class="carrusel-container">
        <div class="contenedor-piso-detalles">
            <div class="carrusel" id="servicio-carrusel">
                <div id="carrusel" class="dis_caja forma_caja">
                    <!-- Aquí se cargan las tarjetas o el mensaje de estado -->
                </div>
            <!-- Tarjetas generadas por JS -->
            </div>
        </div>
        </div>
    </div>

    <!-- 70% - Mapa -->
    <div class="map-wrapper">
        <div id="live-map"></div>
    </div>
</div>

<!-- Modal: Daily Status Matrix -->
<div id="modal-daily-status" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 10000; justify-content: center; align-items: center;">
    <div style="background: white; width: 90%; max-width: 1400px; height: 85vh; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        <!-- Encabezado -->
        <div style="padding: 15px; background: #2196F3; color: white; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.2em;">Daily Status – Crew vs Clients</h3>
            <button id="close-daily-status" style="background: #f44336; color: white; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 18px; cursor: pointer;">✕</button>
        </div>
        <!-- Contenido -->
        <div id="matrix-container" style="flex: 1; overflow: auto; padding: 10px;">
            <table id="daily-status-matrix" class="tabla-matriz" style="width: 100%; border-collapse: collapse; font-size: 0.8em;">
                <thead>
                    <tr style="background: #f0f0f0;">
                        <th style="border: 1px solid #ddd; padding: 8px; position: sticky; top: 0; left: 0; background: #e0e0e0; z-index: 3;">Client</th>
                        <!-- Crews se insertarán aquí -->
                    </tr>
                </thead>
                <tbody id="matrix-body">
                    <!-- Filas de clientes -->
                </tbody>
                <tfoot id="matrix-footer">
                    <!-- Fila de totales -->
                </tfoot>
            </table>
        </div>
    </div>
</div>    

<div id="modal-select-client" class="modal-overlay" style="display: none;">
    <div class="modal-contenedor" style="width: 50%;">
        <div class="modal-header">
            <button id="close-select-client" class="modal-cerrar">&times;</button>
            <h3 class="titulo_modal">Select Client</h3>
        </div>
        <div id="lista-clientes" style="max-height: 50vh; overflow-y: auto;">
            <p>Loading...</p>
        </div>
    </div>
</div>


<script>
// Actualizar fecha y hora en tiempo real
function actualizarReloj() {
    const ahora = new Date();
    
    // Formato: 15 Abr
    const opcionesFecha = { day: '2-digit', month: 'short' };
    const fecha = ahora.toLocaleDateString('es-ES', opcionesFecha)
                    .replace('.', ''); // Quitar el punto de "abr."

    // Formato: hh:mm:ss
    const hora = ahora.toLocaleTimeString('es-ES', { 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: false 
    });

    // Actualizar el DOM
    document.getElementById('fecha-display').textContent = fecha;
    document.getElementById('hora-display').textContent = hora;
}

// Actualizar cada segundo
actualizarReloj();
setInterval(actualizarReloj, 1000);
</script>