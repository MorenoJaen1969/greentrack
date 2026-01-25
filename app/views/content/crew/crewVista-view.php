<?php
if (isset($url[0])) {
    $proceso_actual = $url[0];
} else {
    $proceso_actual = "crewVista";
}
if (isset($url[1])) {
    $id_crew = $url[1];
} else {
    $id_crew = 0;
}
if (isset($url[2])) {
    $ruta_retorno = RUTA_APP . "/" . $url[2];
} else {
    $ruta_retorno = RUTA_APP . "/dashboard";
}
if (isset($url[3])) {
    $pagina_retorno = $url[3];
} else {
    $pagina_retorno = 1;
}

$param_dat = [
    'id_crew' => $id_crew
];

$row1 = $crew->consulta_registro($param_dat);

$id_crew = $row1['id_crew'];
$id_cargo = $row1['id_cargo'];
$nombre = $row1['nombre'];
$apellido = $row1['apellido'];
$telefono = $row1['telefono'];
$id_status = $row1['id_status'];
$foto = $row1['foto'];
$id_sexo = $row1['id_sexo'];
$color = $row1['color'];
if (is_null($row1['email'])) {
    $email = "---";
} else {
    $email = $row1['email'];
}
$acceso = $row1['acceso'];
$crew_n = $row1['crew'];

if (is_null($row1['notas'])) {
    $notas = "";
} else {
    $notas = $row1['notas'];
}

if (is_null($row1['observaciones'])) {
    $observaciones = "";
} else {
    $observaciones = $row1['observaciones'];
}

$modo_edicion = true;

$result_status = $status->consultar_status('crew');

$ruta_status_ajax = RUTA_APP . "/app/ajax/statusAjax.php";
$ruta_direcciones_ajax = RUTA_APP . "/app/ajax/direccionesAjax.php";
$ruta_crew_ajax = RUTA_APP . "/app/ajax/crewAjax.php";
$ruta_crew = RUTA_APP . "/crew/";
$encabezadoVista = PROJECT_ROOT . "/app/views/inc/encabezadoVista.php";
$opcion = "crewVista";

?>

<main>
    <p hidden id="ruta_contrato"><?php echo $ruta_crew; ?></p>
    <p hidden id="ruta_contrato_ajax"><?php echo $ruta_crew_ajax; ?></p>

    <?php
    require_once $encabezadoVista;
    ?>

    <div class="form-container">
        <form class="FormularioAjax form-horizontal validate-form" action="<?php echo $ruta_crew_ajax; ?>"
            method="POST" id="update_contrato" name="update_contrato" enctype="multipart/form-data" autocomplete="off">

            <input class="form-font" type="hidden" name="modulo_crew" value="update_contrato">
            <input class="form-font" type="hidden" id="id_crew" name="id_crew"
                value="<?php echo $row1['id_crew']; ?>">

            <div class="tab-container">
                <div class="tabs-gen">
                    <button type="button" class="tab-button active tablink" data-tab="tab1"
                        onclick="openTab(event, 'tab1')">
                        Contract Details
                    </button>
                    <button type="button" class="tab-button tablink" data-tab="tab2" onclick="openTab(event, 'tab2')">
                        Customer Identification
                    </button>
                    <button type="button" class="tab-button tablink" data-tab="tab3" onclick="openTab(event, 'tab3')">
                        Notes and Observations
                    </button>
                    <button type="button" class="tab-button tablink" data-tab="tab4" onclick="openTab(event, 'tab4')">
                        Distribution of services
                    </button>
                </div>

                <div id="tab1" class="tabcontent tab-link">
                    <h3>Personnel Information</h3>

                    <div class="forma01">
                        <div class="forma01">
                            <div class="grupo_foto">
                                <div class="grupo_foto01">
                                    <h3>Personal Data</h3>
                                    <div class="form-group-ct-inline">
                                        <div class="form-group-ct">
                                            <label class="ancho_label1"
                                                for="nombre">Name:</label>
                                            <input type="text" id="nombre" name="nombre" value="<?php echo $nombre; ?>"
                                                required>
                                        </div>
                                        <div class="form-group-ct">
                                            <label class="ancho_label1"
                                                for="apellido">Last Name:</label>
                                            <input type="text" id="apellido" name="apellido"
                                                value="<?php echo $apellido; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="grupo_foto02">
                                    <div class="photo-container">
                                        <div class="image-preview">
                                            <?php
                                            if (empty($foto)) {
                                                if ($id_sexo == "1") {
                                                    echo '<img class="is-rounded" src="/app/views/fotos/contratista.png" alt="Imagen previa" id="imagePreview">';
                                                } else {
                                                    echo '<img class="is-rounded" src="/app/views/fotos/contratista_mujer.png" alt="Imagen previa" id="imagePreview">';
                                                }
                                            } else {
                                                $foto_ruta = $ruta_imagenes01 . $foto;
                                                if (is_file($foto_ruta)) {
                                                    $imagen = $ruta_imagenes . $foto;
                                                    echo '<img class="is-rounded" src="' . $imagen . '" alt="Imagen previa" id="imagePreview">';
                                                } else {
                                                    if ($id_sexo == "1") {
                                                        echo '<img class="is-rounded" src="/app/views/fotos/contratista.png" alt="Imagen previa" id="imagePreview">';
                                                    } else {
                                                        echo '<img class="is-rounded" src="/app/views/fotos/contratista_mujer.png" alt="Imagen previa" id="imagePreview">';
                                                    }
                                                }
                                            }
                                            ?>
                                        </div>
                                        <div class="upload-btn">
                                            <label for="fileInput"
                                                class="custom-file-label">Select Image</label>
                                            <input type="file" multiple="" id="fileInput" name="fileInput"
                                                accept="image/*" onchange="previewImage(event)">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h3>Control dates</h3>

                    <div class="forma01">
                        <div class="form-group-ct-inline">
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="fecha_ini">Start Date:</label>
                                <input type="date" id="fecha_ini" name="fecha_ini" value="<?php echo $fecha_ini; ?>">
                            </div>
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="fecha_fin">End Date:</label>
                                <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
                            </div>

                            <div class="form-group-ct">
                                <label class="ancho_label1 widthField" for="num_semanas">Number of services in the period:</label>
                                <input type="number" id="num_semanas" name="num_semanas" value="<?php echo $num_semanas; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="forma02">
                        <div class="form-group-ct-inline">
                            <div class="form-group-ct">
                                <div class="form-group-ct">
                                    <label for="id_frecuencia_servicio" class="ancho_label1 widthField">Service Frequency:</label>
                                    <select class="form-control-co form-font" id="id_frecuencia_servicio" name="id_frecuencia_servicio" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                        <!-- Se llenará con JS -->
                                    </select>
                                </div>
                            </div>

                            <div class="form-group-ct">
                                <div class="form-group-ct">
                                    <label for="id_frecuencia_pago" class="ancho_label1 widthField">Payment Frequency:</label>
                                    <select class="form-control-co form-font" id="id_frecuencia_pago"
                                        name="id_frecuencia_pago" <?php echo !$modo_edicion ? 'disabled' : ''; ?>
                                        required>
                                        <!-- Se llenará con JS -->
                                    </select>
                                </div>
                            </div>

                            <div class="form-group-ct">
                                <label class="ancho_label1 widthField" for="costo">Payment Amount:</label>
                                <input class="alinea_der" type='currency' id="costo" name="costo"
                                    value="<?php echo $costo; ?>" placeholder="Payment for the period" />
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tab2" class="tabcontent tab-link" style="display:none">
                    <!-- Formulario del Arrendatario -->
                    <h3>Customer Details</h3>

                    <div class="forma01">
                        <div class="form-group-ct-inline">
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="id_cliente1">Customer code:</label>
                                <input type="text" id="id_cliente1"
                                    value="<?php echo $crew->ceros($id_cliente); ?>" placeholder="Customer Coding"
                                    readonly>
                            </div>

                            <div class="form-group-ct">
                                <div class="form-group-ct">
                                    <label for="id_cliente" class="ancho_label1">Customers:</label>
                                    <select class="form-control-co form-font" id="id_cliente" name="id_cliente" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                        <!-- Se llenará con JS -->
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="forma02">
                        <div class="form-group-ct-inline">
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="telefono">Phone:</label>
                                <div class="telefono-call">
                                    <div class="telefono-display">
                                        <div class="telefono-info">
                                            <span class="bandera"></span>
                                            <input class="numero-formateado" type="text" id="telefono" name="telefono"
                                                value="<?php echo $telefono; ?>" readonly>
                                        </div>
                                        <div class="accion-telefono">
                                            <button class="btn-llamar is-small">
                                                <span class="fa-solid fa-phone-volume"></span>
                                            </button>
                                        </div>
                                        <div class="accion-whatsapp">
                                            <button class="btn-whatsapp is-small">
                                                <span class="fa-brands fa-whatsapp"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="email">Email:</label>
                                <input type="email" id="email" name="email" value="<?php echo $email; ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <h3>Related Address</h3>
                    <div class="forma01">
                        <div class="form-group-ct-inline">
                            <div class="form-group-ct">
                                <label class="ancho_label1" for="id_direccion1">Address code:</label>
                                <input type="text" id="id_direccion1"
                                    value="<?php echo $crew->ceros($id_direccion); ?>" placeholder="Address Coding"
                                    readonly>
                            </div>

                            <div class="form-group-ct">
                                <div class="form-group-ct">
                                    <label for="id_direccion" class="ancho_label1">Address:</label>
                                    <select class="form-control-co form-font" id="id_direccion" name="id_direccion"
                                        <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                        <!-- Se llenará con JS -->
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div id="tab3" class="tabcontent tab-link" style="display:none">
                    <!-- Formulario del detalle del contrato -->
                    <h3>Notes and Comments</h3>
                    <div class="forma01">
                        <div class="form-group-ct">
                            <label class="ancho_label1" for="notas">Notes:</label>
                            <textarea id="notas" name="notas" placeholder="Add notes"></textarea>
                        </div>
                        <div class="form-group-ct">
                            <label class="ancho_label1" for="observaciones">Comments:</label>
                            <textarea id="observaciones" name="observaciones" placeholder="Add comments"></textarea>
                        </div>
                    </div>
                </div>

                <div id="tab4" class="tabcontent tab-link" style="display:none">
                    <!-- Formulario del detalle del contrato -->
                    <div class="form-group-ct-inline">
                        <h3>Distribution of Services</h3>
                        <h3 style="margin-left: auto; color: #6d1a72ff; font-weight: bold;"><label
                                class="ancho_label1"><?php echo $cliente; ?></label></h3>
                    </div>

                    <!-- Tab Distribution of Services -->
                    <div id="distribution-tab" class="tab-content">
                        <div id="loading" class="loading">Cargando servicios...</div>
                        <table id="servicesGrid" class="excel-style">
                            <thead>
                                <tr>
                                    <th class="f_th">Month</th>
                                    <th class="f_th">Week 1</th>
                                    <th class="f_th">Week 2</th>
                                    <th class="f_th">Week 3</th>
                                    <th class="f_th">Week 4</th>
                                    <th class="f_th">Week 5</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Se llena con JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn" <?php echo $puede_editar; ?>>
                    Save changes
                </button>
            </div>
        </form>
    </div>
</main>

<!-- El Modal Cambio de situación de crew-->
<section class="modal_fade" id="modal_cont">
    <div class="modal-dialog-div">
        <div class="modal-header">
            <div class="modal-recuadro_titulo">
                <h1 class="modal-title" id="modalTitulo">
                    Change of contract status
                </h1>
            </div>
        </div>
        <div class="modal-content">
            <form class="formato_modal" id="formProducto">
                <div class="modal-body">
                    <div class="tipo_accion" id="sub_tit_modal"></div>
                    <div class="form-formato-01">
                        <div class="form-container-01">
                            <div class="elem_modal" id="iden_cont"></div>
                        </div>
                    </div>
                </div>
                <div class="modal_fade_footer">
                    <button class="modal_fade__close btn btn-secundary formato_de_fuente">Cancel</button>
                    <button type="submit" class="btn btn-primary formato_de_fuente">Save Change</button>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Modal contenedor -->
<div id="serviceModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <span class="modal-close" onclick="closeServiceModal()">&times;</span>
        <div id="modalBody">
            <!-- Aquí se cargará el contenido del servicio -->
            <p>Cargando...</p>
        </div>
    </div>
</div>


<script src="<?= RUTA_REAL ?>/app/views/inc/js/func_comm.js?v=<?= time() ?>"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js?v=<?= time() ?>"></script>
<script type="text/javascript">
    let arreglo_frases_g = [];
    var p01 = "Are you sure";
    var p02 = "Do you confirm that you want to save the changes you have made?";
    var p03 = "Yes, Save";
    var p04 = "Do not save";
    var p05 = "Response error";

    arreglo_frases_g = [
        p01,
        p02,
        p03,
        p04,
        p05
    ];

    let r_retorno = "<?php echo $ruta_retorno . "/" . $pagina_retorno; ?>";

    const ruta_frecuencia_servicio_ajax = "<?php echo $ruta_frecuencia_servicio_ajax; ?>";
    const ruta_crew_ajax = "<?php echo $ruta_crew_ajax; ?>";
    const ruta_status_ajax = "<?php echo $ruta_status_ajax; ?>";
    const ruta_ruta_ajax = "<?php echo $ruta_ruta_ajax; ?>";
    const ruta_frec_pago_ajax = "<?php echo $ruta_frec_pago_ajax; ?>";
    const ruta_clientes_ajax = "<?php echo $ruta_clientes_ajax; ?>";
    const ruta_direcciones_ajax = "<?php echo $ruta_direcciones_ajax; ?>";
    const ruta_dia_semana_ajax = "<?php echo $ruta_dia_semana_ajax; ?>";
    const ruta_servicios_ajax = "<?php echo $ruta_servicios_ajax; ?>";

    const ruta_retorno = r_retorno;

    // === ABRIR PESTAÑAS ===
    function openTab(evt, tabName) {
        document.querySelectorAll(".tabcontent").forEach(el => el.style.display = "none");
        document.querySelectorAll(".tablink").forEach(btn => btn.classList.remove("active"));
        const tab = document.getElementById(tabName);
        const submitBtn = document.getElementById('submitBtn');
        tab.style.display = "block";
        if (evt) evt.currentTarget.classList.add("active");

        if (tabName === 'tab4') {
            submitBtn.style.display = 'none';
        } else {
            submitBtn.style.display = 'block';
        }
    }

    // === VALIDACIÓN DE FORMULARIO ===
    function validateTabs() {
        if (!validateTab(1)) return showTab(1) && false;
        if (!validateTab(2)) return showTab(2) && false;
        if (!validateTab(3)) return showTab(3) && false;
        if (!validateTab(4)) return showTab(4) && false;
        return true;
    }

    function showTab(index) {
        ["tab1", "tab2", "tab3", "tab4"].forEach((id, i) => {
            document.getElementById(id).style.display = (i + 1 === index) ? "block" : "none";
        });
        const errorDiv = document.querySelector('#form-errors');
        errorDiv.style.display = 'block';
        errorDiv.textContent = 'Please correct the errors in the form.';
    }

    function validateTab(tabNumber) {
        const fields = document.querySelectorAll(`#tab${tabNumber} input[required], #tab${tabNumber} select[required]`);
        let isValid = true;
        for (let field of fields) {
            const label = document.querySelector(`label[for="${field.id}"]`);
            if (!field.value.trim()) {
                field.classList.add('error');
                if (label) label.classList.add('error');
                if (isValid) field.focus();
                isValid = false;
            } else {
                field.classList.remove('error');
                if (label) label.classList.remove('error');
            }
        }
        return isValid;
    }

    // --- CONFIGURACIÓN ---
    const id_crew = "<?php echo $id_crew; ?>"; // Inyectado desde PHP Blade o similar

    // Fecha actual en formato YYYY-MM-DD
    const TODAY = new Date().toISOString().split('T')[0];

    async function loadAndRenderServices() {
        try {
            const res = await fetch(ruta_crew_ajax,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_crew: 'distribucion_servicios',
                        id_crew: id_crew
                    })
                }
            );
            if (res.ok) {
                const respuesta = await res.json();
                const services = respuesta.services || [];

                renderGrid(services);
                document.getElementById('loading').style.display = 'none';
            }
        } catch (err) {
            console.error('Error al cargar servicios:', err);
            document.getElementById('loading').textContent = 'Error al cargar';
        }
    }

    function renderGrid(services) {
        // Agrupar por mes (clave: YYYY-MM)
        const grouped = {};

        services.forEach(s => {
            const d = new Date(s.service_date);
            const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            if (!grouped[key]) grouped[key] = [];
            grouped[key].push(s);
        });

        const tbody = document.querySelector('#servicesGrid tbody');
        tbody.innerHTML = '';

        Object.keys(grouped)
            .sort()
            .forEach(monthKey => {
                const [year, month] = monthKey.split('-').map(Number);
                const firstDay = new Date(year, month - 1, 1);
                const monthName = firstDay.toLocaleString('default', { month: 'long', year: 'numeric' });

                const row = document.createElement('tr');
                const th = document.createElement('td');
                th.textContent = monthName;
                th.style.backgroundColor = '#f9f9f9';
                th.style.fontWeight = 'bold';
                th.style.textAlign = 'center';
                th.style.fontSize = '1.5em';
                row.appendChild(th);

                // Crear 5 celdas vacías
                const cells = Array(5).fill().map(() => {
                    const td = document.createElement('td');
                    td.classList.add('service-cell', 'empty');
                    return td;
                });

                // Asignar servicios a semanas
                grouped[monthKey].forEach(s => {
                    const weekIndex = getWeekIndexInMonth(new Date(s.service_date), new Date(year, month - 1, 1));
                    if (weekIndex < 5) {
                        cells[weekIndex].classList.remove('empty');
                        cells[weekIndex].innerHTML = buildServiceCell(s);
                    }
                });

                cells.forEach(cell => row.appendChild(cell));
                tbody.appendChild(row);
            });
    }

    function getWeekIndexInMonth(date, firstDayOfMonth) {
        const startOfWeek1 = new Date(firstDayOfMonth);
        startOfWeek1.setDate(firstDayOfMonth.getDate() - firstDayOfMonth.getDay()); // domingo inicio
        const diffDays = Math.floor((date - startOfWeek1) / (24 * 60 * 60 * 1000));
        return Math.floor(diffDays / 7);
    }

    function buildServiceCell(service) {
        const isPast = service.service_date < TODAY;
        const isEditable = !isPast && !service.is_done;
        const dateClass = isPast ? 'past' : 'editable';
        const badgeClass = isPast ? 'service-number-badge past' : 'service-number-badge';

        // --- Corrección: Parsear la fecha como local, evitando desfase ---
        const parts = service.service_date.split('-'); // ['2025', '11', '17']
        const dateObj = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2])); // Año, Mes (0-indexado), Día
        const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' }); // Monday, Tuesday...

        let html = `
            <div class="elemnto_hor">
                <span class="${badgeClass}">${service.num_servicio}</span>
                <span class="service-date ${dateClass}" data-id="${service.id}">${service.service_date} (${dayName})</span>
            </div>
        `;

        if (service.is_done) {
            html += `<div class="elemnto_hor_for">
                        <span class="service-done">✔ Done</span>`;
            if (service.service_number) {
                html += `<span class="service-number" onclick="viewServiceDetail('${service.service_number}')">${service.service_number}</span>`;
            }
            html += `<div>`;
        } else {
            if (isEditable) {
                html += `<div class="elemnto_hor_for">
                            <button class="mark-done" onclick="markAsDone(${service.id})">Reschedule</button>`;
            } else {
                if (service.service_number) {
                    html += `<div class="elemnto_hor_for">
                                <span class="service-unrealized">✘ Unrealized</span>`;
                    html += `<span class="service-number" onclick="viewServiceDetail('${service.service_number}')">${service.service_number}</span>`;
                }
            }
            html += `<div>`;
        }

        return `<div>${html}</div>`;
    }

    async function viewServiceDetail(serviceNumber) {
        suiteLoading('show');
        const modal = document.getElementById('serviceModal');
        const modalBody = document.getElementById('modalBody');
        const ruta = ruta_servicios_ajax;
        modalBody.innerHTML = '<p>Loading service details...</p>';
        modal.style.display = 'flex';

        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_servicios: 'datos_servicio',
                        id_servicio: serviceNumber
                    })
                }
            );
            if (res.ok) modalBody.innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Servicio:", err); }
        suiteLoading('hide');
    }

    function closeServiceModal() {
        document.getElementById('serviceModal').style.display = 'none';
    }

    // Cerrar modal al hacer clic fuera del contenido
    document.getElementById('serviceModal').addEventListener('click', (e) => {
        if (e.target.id === 'serviceModal') {
            closeServiceModal();
        }
    });

    function markAsDone(serviceId) {
        if (!confirm('¿Marcar este servicio como completado?')) return;

        fetch(`${ruta_crew_ajax}/${serviceId}/complete`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content') })
        })
            .then(res => res.json())
            .then(() => loadAndRenderServices())
            .catch(err => alert('Error al marcar como completado'));
    }

    async function cargar_id_frecuencia_servicio(id_frecuencia_servicio, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_frecuencia_servicio: 'crear_select',
                        id_frecuencia_servicio: id_frecuencia_servicio
                    })
                }
            );
            if (res.ok) document.getElementById('id_frecuencia_servicio').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Frecuencia de Servicio:", err); }
    }

    async function cargar_id_status(id_status, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_status: 'crear_select',
                        tabla: 'crew',
                        id_status: id_status
                    })
                }
            );
            if (res.ok) document.getElementById('id_status').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Status:", err); }
    }

    async function cargar_id_ruta(id_ruta, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_rutas: 'crear_select',
                        id_ruta: id_ruta
                    })
                }
            );
            if (res.ok) document.getElementById('id_ruta').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Ruta:", err); }
    }

    async function cargar_id_frecuencia_pago(id_frecuencia_pago, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_frecuencia_pago: 'crear_select',
                        id_frecuencia_pago: id_frecuencia_pago
                    })
                }
            );
            if (res.ok) document.getElementById('id_frecuencia_pago').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Fecuencia de Pago:", err); }
    }

    async function cargar_id_cliente(id_cliente, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_clientes: 'crear_select',
                        id_cliente: id_cliente
                    })
                }
            );
            if (res.ok) document.getElementById('id_cliente').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Clientes:", err); }
    }

    async function cargar_id_direccion(id_direccion, id_cliente, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_direcciones: 'crear_select',
                        id_cliente: id_cliente,
                        id_direccion: id_direccion
                    })
                }
            );
            if (res.ok) document.getElementById('id_direccion').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Clientes:", err); }
    }

    async function cargar_id_dia_semana(id_dia_semana, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_dia_semana: 'crear_select',
                        id_dia_semana: id_dia_semana
                    })
                }
            );
            if (res.ok) document.getElementById('id_dia_semana').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Clientes:", err); }
    }

    async function cargar_secondary_day(secondary_day, ruta) {
        try {
            const res = await fetch(ruta,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_dia_semana: 'crear_select_two_d',
                        secondary_day: secondary_day
                    })
                }
            );
            if (res.ok) document.getElementById('secondary_day').innerHTML = await res.text();
        } catch (err) { console.error("Error al cargar Work day:", err); }
    }

    // === ENVÍO DEL FORMULARIO PRINCIPAL ===
    document.addEventListener('DOMContentLoaded', async () => {
        suiteLoading('show');

        openTab(null, 'tab1');

        let id_frecuencia_servicio = "<?php echo $id_frecuencia_servicio; ?>"
        await cargar_id_frecuencia_servicio(id_frecuencia_servicio, ruta_frecuencia_servicio_ajax);

        let id_status = "<?php echo $id_status; ?>"
        await cargar_id_status(id_status, ruta_status_ajax);

        let id_ruta = "<?php echo $id_ruta; ?>"
        await cargar_id_ruta(id_ruta, ruta_ruta_ajax);

        let id_frecuencia_pago = "<?php echo $id_frecuencia_pago; ?>"
        await cargar_id_frecuencia_pago(id_frecuencia_pago, ruta_frec_pago_ajax);

        let id_cliente = "<?php echo $id_cliente; ?>"
        await cargar_id_cliente(id_cliente, ruta_clientes_ajax);

        let id_direccion = "<?php echo $id_direccion; ?>"
        await cargar_id_direccion(id_direccion, id_cliente, ruta_direcciones_ajax);

        let id_dia_semana = "<?php echo $id_dia_semana; ?>"
        await cargar_id_dia_semana(id_dia_semana, ruta_dia_semana_ajax);

        let secondary_day = "<?php echo $secondary_day; ?>"
        await cargar_secondary_day(secondary_day, ruta_dia_semana_ajax);

        loadAndRenderServices();

        // === LIMPIAR ERRORES AL ESCRIBIR ===
        document.querySelectorAll('input[required], select[required]').forEach(field => {
            field.addEventListener('input', () => {
                field.classList.remove('error');
                const label = document.querySelector(`label[for="${field.id}"]`);
                if (label) label.classList.remove('error');
            });
        });

        // === MANEJO DEL BOTÓN PRINCIPAL ===
        document.getElementById("submitBtn")?.addEventListener("click", async function (event) {
            event.preventDefault();
            if (!validateTabs()) return;

            const confirmado = await suiteConfirm(
                arreglo_frases_g[0],
                arreglo_frases_g[1],
                { 
                    aceptar: arreglo_frases_g[2], 
                    cancelar: arreglo_frases_g[3] 
                }
            );
            if (!confirmado) return;

            const form = document.getElementById("update_contrato");
            const data = new FormData(form);

            try {
                const res = await fetch(form.action, 
                    { 
                        method: form.method, 
                        body: data
                    }
                );
                const text = await res.text();
                const json = JSON.parse(text);
                if (json.tipo === 'success') {
                    // ✅ Reiniciar el estado de cambios tras guardar con éxito

                    await suiteAlertSuccess("Success", json.texto);
                    window.location.href = ruta_retorno;
                } else {
                    await suiteAlertError("Error", json.texto);
                }
            } catch (err) {
                console.error("Error en submit:", err);
                await suiteAlertError("Error", "Submission failed: " + err.message);
            }
        });

        loadAndRenderServices();

        suiteLoading('hide');
    });
</script>