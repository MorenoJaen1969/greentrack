<?php
// app/views/content/ruoute_day-view.php
// Asignación de Rutas - Inglés de Texas (Conroe Style)

// Verificar si el usuario tiene permisos para acceder a esta vista
// if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
//     exit("Access denied, partner.");
// }

$ruta_retorno = RUTA_APP . "/dashboard";

$ruta_route_day_ajax = RUTA_APP . "/app/ajax/route_dayAjax.php";
$encabezado = PROJECT_ROOT . "/app/views/inc/encabezado.php";
$opcion = "route_day";
?>

<main>
    <?php
        require_once $encabezado;
    ?>

    <div id="main-container">
        <div class="titulo_form">
            <h5>Route Assignment by Day</h5>
        </div>

        <!-- Day containers -->
        <div class="day-titulo">
            <div class="zone_grid zone_titulo">
                <div class="zone-grid_01">Route by day</div>
                <div class="zone-grid_02" style="text-align:center;">Related</div>
                <div class="zone-grid_03">Action</div>
            </div>
        </div>

        <div class="day-container" data-day="monday">
            <div class="zone_grid">
                <div class="zone-grid_01">
                    <div class="day-label">Monday</div>
                </div>
                <div class="zone-grid_02">
                    <div class="selected-zones-container" id="zones-monday">No routes selected</div>            
                </div>
                <div class="zone-grid_03">
                    <button class="select-zones-btn" onclick="openZoneModal('monday')">Select Routes</button>
                </div>
            </div>
        </div>

        <div class="day-container" data-day="tuesday">
            <div class="zone_grid">
                <div class="zone-grid_01">
                    <div class="day-label">Tuesday</div>
                </div>
                <div class="zone-grid_02">
                    <div class="selected-zones-container" id="zones-tuesday">No routes selected</div>
                </div>
                <div class="zone-grid_03">
                    <button class="select-zones-btn" onclick="openZoneModal('tuesday')">Select Routes</button>
                </div>
            </div>
        </div>

        <div class="day-container" data-day="wednesday">
            <div class="zone_grid">
                <div class="zone-grid_01">
                    <div class="day-label">Wednesday</div>
                </div>
                <div class="zone-grid_02">
                    <div class="selected-zones-container" id="zones-wednesday">No routes selected</div>
                </div>
                <div class="zone-grid_03">
                    <button class="select-zones-btn" onclick="openZoneModal('wednesday')">Select Routes</button>
                </div>
            </div>
        </div>

        <div class="day-container" data-day="thursday">
            <div class="zone_grid">
                <div class="zone-grid_01">
                    <div class="day-label">Thursday</div>
                </div>
                <div class="zone-grid_02">
                    <div class="selected-zones-container" id="zones-thursday">No routes selected</div>
                </div>
                <div class="zone-grid_03">
                    <button class="select-zones-btn" onclick="openZoneModal('thursday')">Select Routes</button>
                </div>
            </div>
        </div>

        <div class="day-container" data-day="friday">
            <div class="zone_grid">
                <div class="zone-grid_01">
                    <div class="day-label">Friday</div>
                </div>
                <div class="zone-grid_02">
                    <div class="selected-zones-container" id="zones-friday">No routes selected</div>
                </div>
                <div class="zone-grid_03">
                    <button class="select-zones-btn" onclick="openZoneModal('friday')">Select Routes</button>
                </div>
            </div>
        </div>

        <div class="day-container" data-day="saturday">
            <div class="zone_grid">
                <div class="zone-grid_01">
                    <div class="day-label">Saturday</div>
                </div>
                <div class="zone-grid_02">
                    <div class="selected-zones-container" id="zones-saturday">No routes selected</div>
                </div>
                <div class="zone-grid_03">
                    <button class="select-zones-btn" onclick="openZoneModal('saturday')">Select Routes</button>
                </div>
            </div>
        </div>

        <div class="day-container" data-day="sunday">
            <div class="zone_grid">
                <div class="zone-grid_01">
                    <div class="day-label">Sunday</div>
                </div>
                <div class="zone-grid_02">
                    <div class="selected-zones-container" id="zones-sunday">No routes selected</div>
                </div>
                <div class="zone-grid_03">
                    <button class="select-zones-btn" onclick="openZoneModal('sunday')">Select Routes</button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal -->
<div id="zoneModal" class="modal01">
    <div class="modal-content01">
        <span class="close-modal" onclick="closeZoneModal()">✖️</span>
        <div style="display: flex;">
            <h3>Select Routes</h3>
            <div class="formato_dia">
                <h3 id="day_select"></h3>
            </div>
        </div>

        <div id="zone-options" class="modal-panels">
            <!-- Panel izquierdo: Rutas -->
            <div class="panel_izq">
                <h4>Available routes</h4>
                <div id="rutas-list">
                    <!-- Aquí se cargan las rutas -->
                </div>
            </div>

            <!-- Panel derecho: Clientes -->
            <div class="panel_der">
                <h4>Customers on the selected route</h4>
                <div id="clientes-list">
                    <p>Select a route to view its customers.</p>
                </div>
            </div>
        </div>
        <div class="modal-buttons">
            <button class="cancel" onclick="closeZoneModal()">Cancel</button>
            <button class="save" onclick="saveSelectedZones()">Save</button>
        </div>
    </div>
</div>

<script src="<?= RUTA_REAL ?>/app/views/inc/js/func_comm.js?v=<?= time() ?>"></script>
<script>
    const ruta_retorno = "<?php echo $ruta_retorno; ?>";
    const ruta_ajax = "<?php echo $ruta_route_day_ajax; ?>";

    // Store selections per day 
    const daySelections = {
        monday: [],
        tuesday: [],
        wednesday: [],
        thursday: [],
        friday: [],
        saturday: [],
        sunday: []
    };

    async function loadCurrentAssignments() {
        try {
            const res = await fetch(ruta_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(
                    { 
                        modulo_route_day: 'cargar_asignaciones' 
                    }
                )
            });
            const data = await res.json();

            // Restaurar selecciones
            for (const [day, routeIds] of Object.entries(data)) {
                if (daySelections.hasOwnProperty(day)) {
                    daySelections[day] = Array.isArray(routeIds) ? routeIds : [];
                }
            }

            // Renderizar en UI
            Object.keys(daySelections).forEach(day => {
                renderSelectedZones(day);
            });

        } catch (err) {
            console.error('Error loading current assignments:', err);
        }
    }

    let currentDay = null;

    async function openZoneModal(day) {
        currentDay = day;
        const day_select = document.getElementById('day_select');
        day_select.innerHTML = day;

        const modal = document.getElementById('zoneModal');
        const content = document.getElementById('rutas-list');
        content.innerHTML = 'Loading...';
        modal.style.display = 'block';

        try {
            const res = await fetch(ruta_ajax, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(
                    {
                        modulo_route_day: "rutas_disponibles",
                        current_day: day
                    }
                ),
            });

            const data = await res.text();

            // Guardar día actual para cargar clientes
            window.diaActualModal = day;

            content.innerHTML = data;

        } catch (e) {
            console.error("Error al cargar Zonas:", e);
            salas = [];
        }
    }

    async function cargarClientesRuta(idRuta) {
        // Quitar resaltado
        document.querySelectorAll('.ruta-item').forEach(el => {
            el.style.border = '1px solid #eee';
        });
        // Resaltar seleccionado
        const selected = document.querySelector(`.ruta-item[data-ruta-id="${idRuta}"]`);
        if (selected) selected.style.border = '1px solid #007bff';

        try {
            const res = await fetch(ruta_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_route_day: 'cargar_clientes_ruta',
                    id_ruta: idRuta,
                    day: window.diaActualModal
                })
            });
            const data = await res.text();
            document.getElementById('clientes-list').innerHTML = data;
        } catch (err) {
            console.error('Error al cargar clientes:', err);
            document.getElementById('clientes-list').innerHTML = '<p style="color:red;">Error loading customers.</p>';
        }
    }

    const rutasSeleccionadas = new Map(); // clave: id_ruta, valor: { id, name, color_ruta }    

    function toggleRutaSeleccionada(rutaId, nombreRuta, colorRuta, isChecked) {
        if (isChecked) {
            rutasSeleccionadas.set(rutaId, {
                id: rutaId,
                name: nombreRuta,
                color_ruta: colorRuta
            });
        } else {
            rutasSeleccionadas.delete(rutaId);
        }
        console.log('Rutas seleccionadas:', Array.from(rutasSeleccionadas.values()));
    }

    function closeZoneModal() {
        document.getElementById('zoneModal').style.display = 'none';
        currentDay = null;
    }

    async function saveSelectedZones() {
        if (!currentDay) return;

        // Convertir Map a array de objetos
        const selected = Array.from(rutasSeleccionadas.values());
        daySelections[currentDay] = selected; // para renderSelectedZones()

        // ✅ Enviar SOLO LOS IDs al backend
        try {
            // ✅ Extraer solo los IDs para el backend
            const routeIdsForBackend = selected.map(item => item.id);            
            const response = await fetch(ruta_ajax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_route_day: 'guardar',
                    day: currentDay,
                    route_ids: routeIdsForBackend // ← array de números o strings
                })
            });

            const result = await response.json();

            if (!result.success) {
                await suiteAlertError('Error', 'Error saving assignment: ' + (result.message || 'Unknown error'));
                console.error('Save response:', result);
            } else {
                suiteAlertSuccess("Success", "The information was updated successfully.");
            }
        } catch (error) {
            console.error('Network error while saving:', error);
            await suiteAlertError('Error', 'Failed to save. Please check your connection.');
        }

        // ✅ Renderizar con nombre y color
        renderSelectedZones(currentDay);
        closeZoneModal();
    }

    async function saveDayAssignments(day) {
        const routeIds = (daySelections[day] || []).map(z => z.id);
        const res = await fetch(ruta_ajax, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                modulo_route_day: 'guardar',
                day: day,
                route_ids: routeIds
            })
        });
        const result = await res.json();
        if (!result.success) {
            throw new Error(result.message || 'Save failed');
        } else {
            suiteAlertSuccess("Success", "The information was updated successfully.");
        }
    }

    function renderSelectedZones(day) {
        const container = document.getElementById(`zones-${day}`);
        if (!container) return;

        container.innerHTML = '';
        const selections = daySelections[day] || [];

        if (selections.length === 0) {
            container.innerHTML = '<em>No routes selected</em>';
            return;
        }

        selections.forEach(zone => {
            const tag = document.createElement('span');
            tag.className = 'zone-tag';
            tag.style.display = 'inline-flex';
            tag.style.alignItems = 'center';
            tag.style.background = '#e9ecef';
            tag.style.padding = '4px 8px';
            tag.style.borderRadius = '4px';
            tag.style.marginRight = '6px';
            tag.style.marginBottom = '4px';
            tag.style.fontSize = '13px';
            tag.title = zone.name;

            // ✅ Botón de eliminación con color de la ruta
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-zone';
            removeBtn.dataset.zoneId = zone.id;
            removeBtn.innerHTML = '✕';
            removeBtn.style.marginLeft = '6px';
            removeBtn.style.border = 'none';
            removeBtn.style.borderRadius = '50%';
            removeBtn.style.width = '20px';
            removeBtn.style.height = '20px';
            removeBtn.style.display = 'flex';
            removeBtn.style.alignItems = 'center';
            removeBtn.style.justifyContent = 'center';
            removeBtn.style.fontSize = '12px';
            removeBtn.style.cursor = 'pointer';
            removeBtn.style.color = 'white';
            removeBtn.style.fontWeight = 'bold';
            removeBtn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.2)';

            // Aplicar el color de la ruta al fondo del botón
            let bgColor = zone.color_ruta || '#6c757d';
            // Opcional: si el color es muy claro, forzar texto oscuro
            removeBtn.style.backgroundColor = bgColor;

            // Añadir evento
            removeBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                
                const confirmado = await suiteConfirm(
                    'Confirm Delete',
                    'Are you sure you want to delete this Route?', {
                        aceptar: 'Yes, delete',
                        cancelar: 'Cancel'
                    }
                );

                if (!confirmado) return;

                const prevState = [...daySelections[day]];

                // Eliminar visualmente (optimista)
                daySelections[day] = daySelections[day].filter(z => z.id !== zone.id);
                renderSelectedZones(day);

                try {
                    await saveDayAssignments(day); // si falla, se lanza error
                } catch (err) {
                    // Revertir en caso de error
                    daySelections[day] = prevState;
                    renderSelectedZones(day);
                    await suiteAlertError('Error', 'Failed to remove zone. Changes reverted.');
                }
            });

            tag.innerHTML = `<span>${zone.name}</span>`;
            tag.appendChild(removeBtn);
            container.appendChild(tag);
        });
    }

    // Close modal if user clicks outside
    window.onclick = function(event) {
        const modal = document.getElementById('zoneModal');
        if (event.target === modal) {
            closeZoneModal();
        }
    };

    // Cargar asignaciones actuales al iniciar
    document.addEventListener('DOMContentLoaded', loadCurrentAssignments);

</script>