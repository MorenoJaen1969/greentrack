<?php
// app/views/content/clientesNew-view.php

$proceso_actual = "clientesNew";
$ruta_retorno = RUTA_APP . "/clientes";
$pagina_retorno = 1;

$modo_edicion = true;
$id_status = 1;
$id_tratamiento = 1;
$id_sexo = 1;
$id_tratamiento_jur = 1;
$id_sexo_jur = 1;

$ruta_status_ajax = RUTA_APP . "/app/ajax/statusAjax.php";
$ruta_sexo_ajax = RUTA_APP . "/app/ajax/sexoAjax.php";
$ruta_tratamiento_ajax = RUTA_APP . "/app/ajax/tratamientoAjax.php";
$ruta_direcciones_ajax = RUTA_APP . "/app/ajax/direccionesAjax.php";
$ruta_cliente_ajax = RUTA_APP . "/app/ajax/clientesAjax.php";
$ruta_cliente = RUTA_APP . "/clientes/";
$encabezadoNew = PROJECT_ROOT . "/app/views/inc/encabezadoNew.php";
$opcion = "clientesNew";

$ruta_retorno = "/clientes";
?>

<main>
    <?php require_once $encabezadoNew; ?>

    <div class="form-container">
        <!-- FORMULARIO PRINCIPAL -->
        <form class="FormularioAjax form-horizontal" id="ingreso_cliente" name="ingreso_cliente" 
                action="<?php echo $ruta_cliente_ajax; ?>" method="POST" 
                enctype="multipart/form-data" autocomplete="off">
            
            <input type="hidden" name="modulo_clientes" value="registrar_cliente">
            <input type="hidden" id="tipo_persona" name="tipo_persona" value="">

            <!-- PANEL SUPERIOR: SELECCIÓN DE TIPO -->
            <div class="forma01">
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="grid_header">
                        <!-- PANEL IZQUIERDO: FOTO + BOTONES DE TIPO -->
                        <div class="grid_header01">
                            <div class="header-new">
                                <h3 class="titulo_modal">New Customer</h3>
                            </div>

                            <div style="padding: 20px;">
                                <!-- BOTONES DE SELECCIÓN DE TIPO -->
                                <p style="margin-bottom: 10px; color: #555; font-size: 1.1em; text-align: center;">
                                    Select customer type:
                                </p>

                                <div style="display: flex; gap: 15px; justify-content: center; margin-bottom: 15px;">
                                    <button type="button" id="btn-persona-natural" class="btn-tipo-persona"
                                        style="
                                            padding: 20px 25px;
                                            border: 2px solid #3498db;
                                            background: #fff;
                                            color: #3498db;
                                            border-radius: 8px;
                                            cursor: pointer;
                                            font-size: 0.95em;
                                            transition: all 0.3s;
                                            flex: 1;
                                            max-width: 140px;
                                        ">
                                        <div style="font-size: 2em; margin-bottom: 8px;">👤</div>
                                        <strong>Natural</strong>
                                        <div style="font-size: 0.8em; margin-top: 3px; color: #666;">
                                            Individual
                                        </div>
                                    </button>

                                    <button type="button" id="btn-persona-juridica" class="btn-tipo-persona"
                                        style="
                                            padding: 20px 25px;
                                            border: 2px solid #27ae60;
                                            background: #fff;
                                            color: #27ae60;
                                            border-radius: 8px;
                                            cursor: pointer;
                                            font-size: 0.95em;
                                            transition: all 0.3s;
                                            flex: 1;
                                            max-width: 140px;
                                        ">
                                        <div style="font-size: 2em; margin-bottom: 8px;">🏢</div>
                                        <strong>Legal</strong>
                                        <div style="font-size: 0.8em; margin-top: 3px; color: #666;">
                                            Company
                                        </div>
                                    </button>

                                    <!-- BOTÓN CAMBIAR TIPO (inicialmente oculto) -->
                                    <div id="cambiar-tipo-container" style="text-align: center; display: none;">
                                        <button type="button" id="btn-cambiar-tipo" style="
                                            padding: 10px 20px;
                                            background: #e74c3c;
                                            color: white;
                                            border: none;
                                            border-radius: 4px;
                                            cursor: pointer;
                                            font-size: 0.9em;
                                        ">
                                            ← Change Type
                                        </button>
                                        <p style="margin-top: 8px; font-size: 0.8em; color: #e74c3c;">
                                            Warning: This will clear all entered data
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PANEL DERECHO: INFO GENERAL -->
                        <div class="grid_header02">
                            <div class="grid_header_int">
                                <div class="grid_header_int01">
                                    <div class="form-group-ct-inline" style="margin-top: 15px;">
                                        <label for="id_status" class="ancho_label1">Current Status:</label>
                                        <select class="form-control-co form-font" id="id_status" name="id_status" 
                                                <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                            <!-- Se llenará con JS -->
                                        </select>
                                    </div>

                                    <div class="form-column" id="phone1" style="display: none; grid-template-columns: 1fr 1fr; gap: 15px; padding-left: 15px;">
                                        <div class="form-group-ct">
                                            <label for="natural-tel1-display"><strong>Phone Primary</strong><br/><small>Format: +XX (123) 456-7890</small></label>
                                            <input class="campo-natural input telefono-mask" 
                                                type="tel" 
                                                id="natural-tel1-display"
                                                placeholder="+1 (234) 567-8900" 
                                                autocomplete="off">
                                            <!-- Input hidden real que se envía al backend (solo números) -->
                                            <input type="hidden" name="natural_telefono1" id="natural-tel1" class="campo-natural">
                                            <small style="color: #666; font-size: 0.85em;">Format: +Country (Area) Number</small>
                                        </div>
                                        <div class="form-group-ct">
                                            <label for="natural-tel2-display"><strong>Phone Secondary</strong><br/><small>Format: +XX (123) 456-7890</small></label>
                                            <input class="campo-natural input telefono-mask" 
                                                type="tel" 
                                                id="natural-tel2-display"
                                                placeholder="+1 (234) 567-8900" 
                                                autocomplete="off">
                                            <input type="hidden" name="natural_telefono2" id="natural-tel2" class="campo-natural">
                                        </div>
                                    </div>
                                    <div class="form-column" id="phone2" style="display: none; grid-template-columns: 1fr 1fr; gap: 15px; padding-left: 15px;">
                                        <div class="form-group-ct">
                                            <label for="juridica-tel1-display"><strong>Office Phone</strong><br/><small>Format: +XX (123) 456-7890</small></label>
                                            <input class="campo-juridica input telefono-mask" 
                                                type="tel" 
                                                id="juridica-tel1-display"
                                                placeholder="+1 (234) 567-8900" 
                                                autocomplete="off">
                                            <input type="hidden" name="juridica_telefono1" id="juridica-tel1" class="campo-juridica">
                                            <small style="color: #666; font-size: 0.85em;">Format: +Country (Area) Number</small>
                                        </div>
                                        <div class="form-group-ct">
                                            <label for="juridica-tel2-display"><strong>Mobile / Direct</strong><br/><small>Format: +XX (123) 456-7890</small></label>
                                            <input class="campo-juridica input telefono-mask" 
                                                type="tel" 
                                                id="juridica-tel2-display"
                                                placeholder="+1 (234) 567-8900" 
                                                autocomplete="off">
                                            <input type="hidden" name="juridica_telefono2" id="juridica-tel2" class="campo-juridica">
                                        </div>
                                    </div>
                                </div>
                                <div class="grid_header_int02">
                                    <div style="padding: 10px;">
                                        <!-- ÁREA DE FOTO PREVIEW -->
                                        <div id="foto-container" style="text-align: center; margin-bottom: 5px;">
                                            <div id="foto-preview" class="foto-cliente">
                                                <span id="foto-placeholder" style="color: #999; font-size: 3em;">📷</span>
                                                <img id="foto-img" class = "foto-imagen">
                                            </div>
                                            
                                            <button type="button" id="btn-cambiar-foto" class="foto-boton">
                                                <span id="texto-btn-foto">Add Photo</span>
                                            </button>
                                            
                                            <!-- Input file OCULTO fuera del form pero vinculado -->
                                            <input type="file" id="input-foto-file" name="foto" accept="image/*" 
                                                style="display: none;" form="ingreso_cliente">
                                            
                                            <input type="hidden" id="foto_nombre" name="cliente_foto" value="">
                                            
                                            <p style="margin-top: 10px; font-size: 0.85em; color: #666;">
                                                Max 2MB (JPG, PNG)
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONTENEDOR DE TABS (Controlado exclusivamente por botones) -->
            <div class="tab-container" id="tabs-container" style="display: none;">
                <div class="tabs-gen" style="pointer-events: none; opacity: 0.6;">
                    <button type="button" class="tab-button tablink" data-tab="tab1" id="tab-btn-natural">
                        Natural Person
                    </button>
                    <button type="button" class="tab-button tablink" data-tab="tab2" id="tab-btn-juridica">
                        Legal Entity
                    </button>
                </div>
            </div>

            <!-- TAB 1: PERSONA NATURAL -->
            <div id="tab1" class="tabcontent-especial" style="display: none;">
                <div class="forma01">
                    <h3>Personal Information</h3>
                    <div class="form-group-ct-inline">
                        <div class="form-group-ct">
                            <div id="campos-natural" class="form-section">
                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group-ct-inline" style="width: 95.5vw !important;">
                                        <div class="form-group-ct">
                                            <label for="id_tratamiento">Treatment</label>
                                            <select class="form-control form-font" id="id_tratamiento" name="id_tratamiento" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                            </select>
                                        </div>
                                        <div class="form-group-ct">
                                            <label for="nombre">First Name</label>
                                            <input class="input" type="text" name="natural_nombre" id="natural-nombre"  pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}"
                                                maxlength="40" required>
                                        </div>
                                        <div class="form-group-ct">
                                            <label for="apellido">Last Name</label>
                                            <input class="input" type="text" name="natural_apellido" id="natural-apellido" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}"
                                                maxlength="40" required>
                                        </div>
                                        <div class="form-group-ct">
                                            <label for="id_sexo">Sex</label>
                                            <select class="form-control form-font" id="id_sexo" name="id_sexo" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                    <div class="form-group-ct-inline" style="width: 95.5vw !important;">
                                        <div class="form-group-ct">
                                            <label>ID / SSN *</label>
                                            <input type="text" name="natural_identificacion" id="natural-identificacion"
                                                placeholder="123-45-6789" maxlength="20" class="input">
                                        </div>
                                        <div class="form-group-ct">
                                            <label>Date of Birth</label>
                                            <input type="date" name="natural_fecha_nacimiento" id="natural-fecha-nac" 
                                                class="input">
                                        </div>
                                        <div class="form-group-ct">
                                            <labelfor="natural-email">Email</label>
                                            <input class="input" type="email" name="natural_email" id="natural-email" 
                                                placeholder="cliente@sergioslandscaping.com" style="width: 100%;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: PERSONA JURÍDICA -->
            <div id="tab2" class="tabcontent-especial" style="display: none;">
                <div class="forma01" style="height: 15vh;">
                    <h3>Company Information</h3>
                    <div class="form-group-ct-inline">
                        <div class="form-group-ct" style="margin-bottom: 1px !important;">
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div class="form-group-ct-inline" style="width: 95.5vw !important;">
                                    <div class="form-group-ct" style="margin-bottom: 1px !important;">
                                        <div class="form-row">
                                            <label>Company Name *</label>
                                            <input type="text" name="juridica_nombre_empresa" id="juridica-nombre"
                                                maxlength="200" style="width: 100%;" class="input">
                                        </div>
                                    </div>
                                    <div class="form-group-ct" style="margin-bottom: 1px !important;">
                                        <div class="form-row">
                                            <label>Tax ID / EIN *</label>
                                            <input type="text" name="juridica_identificacion" id="juridica-identificacion"
                                                placeholder="XX-XXXXXXX" maxlength="20" class="input">
                                        </div>
                                    </div>
                                    <div class="form-group-ct" style="margin-bottom: 1px !important;">
                                        <div class="form-row">
                                            <label>Industry / Sector</label>
                                            <input type="text" name="juridica_sector" id="juridica-sector" 
                                                maxlength="100" class="input">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="forma02" style="height: 15vh;">
                    <h3>Legal Representative *</h3>
                    <div class="form-group-ct-inline" style="margin-bottom: 1px !important;">
                        <div class="form-group-ct">
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div class="form-group-ct-inline" style="width: 95.5vw !important;">
                                    <div class="form-group-ct">
                                        <label for="id_tratamiento_jur">Treatment</label>
                                        <select class="form-control form-font" id="id_tratamiento_jur" name="id_tratamiento_jur" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                        </select>
                                    </div>
                                    <div class="form-group-ct">
                                        <label for="nombre_jur">First Name</label>
                                        <input class="input" type="text" name="nombre_jur" id="nombre_jur" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}"
                                            maxlength="40" placeholder="Name of legal representative" required>
                                    </div>
                                    <div class="form-group-ct">
                                        <label for="apellido_jur">Last Name</label>
                                        <input class="input" type="text" name="apellido_jur" id="apellido_jur" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}"
                                            maxlength="40" placeholder="Last Name of legal representative" required>
                                    </div>
                                    <div class="form-group-ct">
                                        <label for="id_sexo_jur">Sex</label>
                                        <select class="form-control form-font" id="id_sexo_jur" name="id_sexo_jur" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="forma03" style="height: 15vh;">
                    <h3>Other Information</h3>
                    <div class="form-group-ct-inline" style="margin-bottom: 1px !important;">
                        <div class="form-group-ct">
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div class="form-group-ct-inline" style="width: 95.5vw !important;">
                                    <div class="form-group-ct">
                                        <label>Company Email *</label>
                                        <input type="email" name="juridica_email" id="juridica-email" 
                                            style="width: 100%;" class="input">
                                    </div>
                                    <div class="form-group-ct">
                                        <label>Website</label>
                                        <input type="url" name="juridica_website" id="juridica-website"
                                            style="width: 100%;" placeholder="https://www.company.com" 
                                            class="input">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn" style="display: none;">
                Register Customer
            </button>                
        </form>
    </div>
</main>

<script>
    // ============================================
    // FLUJO CREACIÓN DE CLIENTE - OPCIÓN B
    // ============================================

    let arreglo_frases_g = [];
    var p01 = "Are you sure";
    var p02 = "Are you confirming that you want to save the current record?";
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

    const platRutaRetorno = "<?php echo $ruta_retorno . "/" . $pagina_retorno; ?>";
    const ruta_status_ajax = "<?php echo $ruta_status_ajax; ?>";

    let tipoPersonaSeleccionado = null;

    // Referencias DOM
    const btnNatural = document.getElementById('btn-persona-natural');
    const btnJuridica = document.getElementById('btn-persona-juridica');
    const btnCambiarTipo = document.getElementById('btn-cambiar-tipo');
    const containerCambiarTipo = document.getElementById('cambiar-tipo-container');
    const tabsContainer = document.getElementById('tabs-container');
    const telefono1 = document.getElementById('phone1');
    const telefono2 = document.getElementById('phone2');
    const tipoInput = document.getElementById('tipo_persona');
    const submitBtn = document.getElementById('submitBtn');
    const retorno = document.getElementById('retorno');
    const platRutaTratamientoAjax = "<?php echo $ruta_tratamiento_ajax; ?>";
    const sexoAjax = "<?php echo $ruta_sexo_ajax; ?>";

    // Foto elementos
    const btnCambiarFoto = document.getElementById('btn-cambiar-foto');
    const inputFotoFile = document.getElementById('input-foto-file');
    const fotoPreview = document.getElementById('foto-preview');
    const fotoImg = document.getElementById('foto-img');
    const fotoPlaceholder = document.getElementById('foto-placeholder');
    const textoBtnFoto = document.getElementById('texto-btn-foto');
    const fotoNombre = document.getElementById('foto_nombre');

    const ruta_tratamiento_ajax = platRutaTratamientoAjax;
    const ruta_sexo_ajax = sexoAjax;

    // Otras Variables
    var TIPO_PERSONA_GLOBAL = null;    

    const CAMPOS_CRITICOS = {
        natural: ['natural_nombre', 'natural_apellido', 'natural_identificacion'],
        juridica: ['juridica_nombre_empresa', 'juridica_identificacion', 'nombre_jur', 'apellido_jur','juridica_email']
    };
    
    // Mapeo de nombres de frontend a backend
    const MAPEO_CAMPOS = {
        // Comunes
        tipo_persona: 'id_tipo_persona',
        id_status: 'id_status',
        foto: 'foto',
        cliente_foto: 'cliente_foto',
        
        // Natural (frontend → backend)
        natural_telefono1: 'telefono',
        natural_telefono2: 'telefono2',
        id_tratamiento: 'id_tratamiento',
        natural_nombre: 'nombre',
        natural_apellido: 'apellido',
        id_sexo: 'id_sexo',
        natural_identificacion: 'identification',
        natural_fecha_nacimiento: 'fecha_nacimiento',
        natural_email: 'email',
        
        // Jurídica (frontend → backend)
        juridica_telefono1: 'telefono',
        juridica_telefono2: 'telefono2',
        juridica_nombre_empresa: 'nombre_comercial',
        juridica_identificacion: 'identification',
        juridica_sector: 'sector',
        id_tratamiento_jur: 'id_tratamiento',
        nombre_jur: 'nombre',
        apellido_jur: 'apellido',
        id_sexo_jur: 'id_sexo',
        juridica_email: 'email',
        juridica_website: 'website'
    };

    // ========== GESTIÓN DE FOTO ==========
    btnCambiarFoto.addEventListener('click', () => {
        inputFotoFile.click();
    });

    inputFotoFile.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;

        // Validar tamaño (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('File too large. Maximum 2MB allowed.');
            inputFotoFile.value = '';
            return;
        }

        // Validar tipo
        if (!file.type.startsWith('image/')) {
            alert('Only image files allowed.');
            inputFotoFile.value = '';
            return;
        }

        // Mostrar preview
        const reader = new FileReader();
        reader.onload = (e) => {
            fotoImg.src = e.target.result;
            fotoImg.style.display = 'block';
            fotoPlaceholder.style.display = 'none';
            textoBtnFoto.textContent = 'Change Photo';
            
            // Generar nombre único para el campo hidden (tu backend usará $_FILES['foto'])
            const extension = file.name.split('.').pop();
            const nombreUnico = `cliente_${Date.now()}.${extension}`;
            fotoNombre.value = nombreUnico;
        };
        reader.readAsDataURL(file);
    });

    // ========== SELECCIÓN DE TIPO ==========
    btnNatural.addEventListener('click', () => seleccionarTipo('natural'));
    btnJuridica.addEventListener('click', () => seleccionarTipo('juridica'));

    function seleccionarTipo(tipo) {
        // Asignar a variable global
        TIPO_PERSONA_GLOBAL = tipo;
        tipoPersonaSeleccionado = tipo;
        
        // Deshabilitar botones de selección
        btnNatural.disabled = true;
        btnJuridica.disabled = true;
        
        // Estilos de deshabilitado
        btnNatural.style.opacity = '0.5';
        btnNatural.style.cursor = 'not-allowed';
        btnJuridica.style.opacity = '0.5';
        btnJuridica.style.cursor = 'not-allowed';
        
        // Resaltar seleccionado
        if (tipo === 'natural') {
            btnNatural.style.opacity = '1';
            btnNatural.style.background = '#3498db';
            btnNatural.style.color = '#fff';
        } else {
            btnJuridica.style.opacity = '1';
            btnJuridica.style.background = '#27ae60';
            btnJuridica.style.color = '#fff';
        }

        // Mostrar controles de cambio
        containerCambiarTipo.style.display = 'block';

        // Actualizar displays
        tipoInput.value = tipo === 'natural' ? 'N' : 'J';

        // Mostrar tabs y formulario correspondiente
        tabsContainer.style.display = 'block';
        submitBtn.style.display = 'inline-block';

        // Activar tab visual correspondiente
        document.querySelectorAll('.tablink').forEach(btn => btn.classList.remove('active'));
        if (tipo === 'natural') {
            document.getElementById('tab-btn-natural').classList.add('active');
            openTab('tab1');
            configurarRequired('natural');
            telefono1.style.display = 'flex';
        } else {
            document.getElementById('tab-btn-juridica').classList.add('active');
            openTab('tab2');
            configurarRequired('juridica');
            telefono2.style.display = 'flex';
        }
    }

    // ========== BOTON DE RETORNO ==========
    retorno.addEventListener('click', () => {
        window.location.href = platRutaRetorno;
    });

    // ========== SELECT DEL TRATAMIENTO PERSONAL ==========
    async function cargar_id_tratamiento(id_tratamiento, ruta, elemento) {
        try {
            const res = await fetch(ruta, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_tratamiento: 'crear_select',
                    id_tratamiento: id_tratamiento
                })
            });
            if (res.ok) document.getElementById(elemento).innerHTML = await res.text();
        } catch (err) {
            console.error("Error al cargar Tratamiento:", err);
        }
    }

    // ========== SELECT DEL SEXO PERSONAL ==========
    async function cargar_id_sexo(id_sexo, ruta, elemento) {
        try {
            const res = await fetch(ruta, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_sexo: 'crear_select',
                    id_sexo: id_sexo
                })
            });
            if (res.ok) document.getElementById(elemento).innerHTML = await res.text();
        } catch (err) {
            console.error("Error al cargar Sexo:", err);
        }
    }

    // ========== CAMBIAR TIPO (RESET) ==========

    btnCambiarTipo.addEventListener('click', async () => {
        const confirmado = await suiteConfirm(
            "Confirm Cancellation",
            'Are you sure? All entered data will be lost.',
            {
                aceptar: 'Continue',
                cancelar: 'Cancel'
            }                
        );

        if (!confirmado) return;

        await resetearFormulario(true, false); // Con confirmación, no es éxito
    });

    // ========== CONFIGURACIÓN DE REQUIRED ==========

    function configurarRequired(tipo) {
        // Limpiar todos los required primero
        document.querySelectorAll('.campo-natural, .campo-juridica, #nombre_jur, #apellido_jur, #juridica-email').forEach(input => {
            input.required = false;
        });

        // Establecer required según tipo
        if (tipo === 'natural') {
            document.getElementById('natural-nombre').required = true;
            document.getElementById('natural-apellido').required = true;
            document.getElementById('natural-identificacion').required = true;

            // Asegurar que campos de jurídica NO tengan required
            document.getElementById('nombre_jur').required = false;
            document.getElementById('apellido_jur').required = false;
            document.getElementById('juridica-email').required = false;

        } else {
            document.getElementById('juridica-nombre').required = true;
            document.getElementById('juridica-identificacion').required = true;
            document.getElementById('nombre_jur').required = true;
            document.getElementById('apellido_jur').required = true;
            document.getElementById('juridica-email').required = true;
            
            // Asegurar que campos de natural NO tengan required
            document.getElementById('natural-nombre').required = false;
            document.getElementById('natural-apellido').required = false;
            document.getElementById('natural-identificacion').required = false;
        }
    }

    // ========== CONTROL DE TABS ==========

    function openTab(tabName) {
        // Ocultar todos los contenidos
        document.querySelectorAll(".tabcontent").forEach(el => el.style.display = "none");
        
        // Mostrar el seleccionado
        document.getElementById(tabName).style.display = "block";
    }

    // ========== CARGA INICIAL ==========

    async function cargar_id_status(id_status, ruta) {
        try {
            const res = await fetch(ruta, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    modulo_status: 'crear_select',
                    tabla: 'clientes',
                    id_status: id_status
                })
            });
            if (res.ok) {
                document.getElementById('id_status').innerHTML = await res.text();
            }
        } catch (err) { 
            console.error("Error al cargar Status:", err); 
        }
    }

    // ========== VALIDACION DE DATOS ==========
    async function validarDatos() {
        // Limpiar errores previos
        limpiarErroresVisuales();
        
        const data = {};
        const camposAdvertencia = [];
        
        // ========== RECOLECTAR DATOS COMUNES ==========
        
        data.tipo_persona = TIPO_PERSONA_GLOBAL === 'natural' ? 'N' : 'J';
        data.id_status = document.getElementById('id_status').value || '1';
        
        // Foto
        const inputFoto = document.getElementById('input-foto-file');
        data.foto = inputFoto.files[0] || null;
        data.cliente_foto = document.getElementById('foto_nombre').value || '';
        
        // ========== VALIDAR SEGÚN TIPO ==========
        
        if (TIPO_PERSONA_GLOBAL === 'natural') {
            const resultado = await validarNatural(data, camposAdvertencia);
            if (resultado.error) return { error: true, campo: resultado.campo };
            
        } else if (TIPO_PERSONA_GLOBAL === 'juridica') {
            const resultado = await validarJuridica(data, camposAdvertencia);
            if (resultado.error) return { error: true, campo: resultado.campo };
            
        } else {
            await suiteAlertError('Error', 'Type of person not selected');
            return { error: true };
        }
        
        // ========== MANEJAR ADVERTENCIAS ==========
        
        if (camposAdvertencia.length > 0) {
            const listaCampos = camposAdvertencia.map(c => `• ${c.label}`).join('\n');
            const confirmado = await suiteConfirm(
                'warning',
                `The following fields are empty, but you can continue.:\n\n${listaCampos}\n\nDo you wish to proceed?`,
                {
                    aceptar: 'Continue',
                    cancelar: 'revise'
                }                
            );
            
            if (!confirmado) {
                // Enfocar el primer campo de advertencia
                enfocarCampo(camposAdvertencia[0].id);
                return { error: true, canceladoPorUsuario: true };
            }
        }
        
        // ========== MAPEAR A NOMBRES DE BACKEND ==========
        
        const dataFinal = {};
        for (const [key, value] of Object.entries(data)) {
            const nombreBackend = MAPEO_CAMPOS[key] || key;
            dataFinal[nombreBackend] = value;
        }
        
        return { error: false, data: dataFinal };
    }

    // ============================================
    // VALIDACIÓN ESPECÍFICA - PERSONA NATURAL
    // ============================================

    async function validarNatural(data, camposAdvertencia) {
        const campos = {
            natural_nombre: { 
                el: document.getElementById('natural-nombre'), 
                label: 'Name',
                critico: true 
            },
            natural_apellido: { 
                el: document.getElementById('natural-apellido'), 
                label: 'Last Name',
                critico: true 
            },
            natural_identificacion: { 
                el: document.getElementById('natural-identificacion'), 
                label: 'Identification',
                critico: true 
            },
            natural_fecha_nacimiento: { 
                el: document.getElementById('natural-fecha-nac'), 
                label: 'Date of Birth',
                critico: false 
            },
            natural_email: { 
                el: document.getElementById('natural-email'), 
                label: 'Email',
                critico: false 
            },
            natural_telefono1: { 
                el: document.getElementById('natural-tel1'), 
                label: 'Phone Primary',
                critico: false,
                validar: (val) => val.length >= 10 || val.length === 0 // Opcional, mínimo 10 dígitos
            },
            natural_telefono2: { 
                el: document.getElementById('natural-tel2'), 
                label: 'Phone Secondary',
                critico: false,
                validar: (val) => val.length >= 10 || val.length === 0
            },
            id_tratamiento: { 
                el: document.getElementById('id_tratamiento') || { value: '1' }, 
                label: 'Treatment',
                critico: false,
                default: '1'
            },
            id_sexo: { 
                el: document.getElementById('id_sexo') || { value: '1' }, 
                label: 'Sex',
                critico: false,
                default: '1'
            }
        };
        
        for (const [key, config] of Object.entries(campos)) {
            const valor = config.el ? config.el.value.trim() : (config.default || '');
            data[key] = valor;
            
            // Validar críticos
            if (config.critico && !valor) {
                marcarError(config.el, config.label);
                await suiteAlertError('Error de Validación', `El campo ${config.label} es requerido`);
                enfocarCampo(config.el);
                return { error: true, campo: key };
            }
            
            // Validación personalizada para teléfonos
            if (config.validar && valor && !config.validar(valor)) {
                marcarError(config.el, config.label);
                await suiteAlertError('Validation Error', `The ${config.label} is invalid (minimum 10 digits)`);
                // Enfocar el input visible correspondiente
                const displayInput = document.getElementById(config.el.id + '-display');
                enfocarCampo(displayInput);
                return { error: true, campo: key };
            }

            // Acumular advertencias
            if (!config.critico && !valor && config.el && config.el.id) {
                camposAdvertencia.push({ 
                    id: config.el.id + '-display', 
                    label: config.label,
                    key: key 
                });
            }
        }        
        return { error: false };
    }

    // ============================================
    // VALIDACIÓN ESPECÍFICA - PERSONA JURÍDICA
    // ============================================

    async function validarJuridica(data, camposAdvertencia) {
        const campos = {
            juridica_nombre_empresa: { 
                el: document.getElementById('juridica-nombre'), 
                label: 'Company Name',
                critico: true 
            },
            juridica_identificacion: { 
                el: document.getElementById('juridica-identificacion'), 
                label: 'Tax ID / EIN',
                critico: true 
            },
            nombre_jur: { 
                el: document.getElementById('nombre_jur'), 
                label: 'First Name',
                critico: true 
            },
            apellido_jur: { 
                el: document.getElementById('apellido_jur'), 
                label: 'Last Name',
                critico: true 
            },
            juridica_email: { 
                el: document.getElementById('juridica-email'), 
                label: 'Company Email',
                critico: true 
            },
            juridica_sector: { 
                el: document.getElementById('juridica-sector'), 
                label: 'Industry / Sector',
                critico: false 
            },
            juridica_telefono1: { 
                el: document.getElementById('juridica-tel1'), 
                label: 'Office Phone',
                critico: false,
                validar: (val) => val.length >= 10 || val.length === 0
            },
            juridica_telefono2: { 
                el: document.getElementById('juridica-tel2'), 
                label: 'Mobile / Direct',
                critico: false, 
                validar: (val) => val.length >= 10 || val.length === 0
            },
            juridica_website: { 
                el: document.getElementById('juridica-website'), 
                label: 'Website',
                critico: false 
            },
            id_tratamiento_jur: { 
                el: document.getElementById('id_tratamiento_jur'), 
                label: 'Treatment',
                critico: false,
                default: '1'
            },
            id_sexo_jur: { 
                el: document.getElementById('id_sexo_jur'), 
                label: 'Sex',
                critico: false,
                default: '1'
            }
        };
        
        for (const [key, config] of Object.entries(campos)) {
            // Verificar que el elemento existe antes de acceder a value
            if (!config.el) {
                console.warn(`Elemento no encontrado: ${key}`);
                data[key] = config.default || '';
                continue;
            }
            
            const valor = config.el ? config.el.value.trim() : (config.default || '');
            data[key] = valor;
            
            // Validar críticos
            if (config.critico && !valor) {
                marcarError(config.el, config.label);
                await suiteAlertError('Validation Error', `The ${config.label} field is required`);
                enfocarCampo(config.el);
                return { error: true, campo: key };
            }
            
            // Validación personalizada para teléfonos
            if (config.validar && valor && !config.validar(valor)) {
                marcarError(config.el, config.label);
                await suiteAlertError('Validation Error', `The ${config.label} is invalid (minimum 10 digits)`);
                // Enfocar el input visible correspondiente
                const displayInput = document.getElementById(config.el.id + '-display');
                enfocarCampo(displayInput);
                return { error: true, campo: key };
            }

            // Acumular advertencias (solo si el elemento existe y está visible)
            if (!config.critico && !valor && config.el.id && config.el.offsetParent !== null) {
                camposAdvertencia.push({ 
                    id: config.el.id + '-display', 
                    label: config.label,
                    key: key 
                });
            }
        }
        
        return { error: false };
    }

    // ============================================
    // UTILIDADES VISUALES
    // ============================================

    function marcarError(elemento, nombreCampo) {
        if (!elemento) return;
        
        // Agregar clase de error
        elemento.classList.add('input-error');
        elemento.style.border = '2px solid #e74c3c';
        elemento.style.backgroundColor = '#fdf2f2';
        
        // Agregar atributo para identificación
        elemento.dataset.errorValidacion = 'true';
    }

    function limpiarErroresVisuales() {
        document.querySelectorAll('.input-error, [data-error-validacion]').forEach(el => {
            el.classList.remove('input-error');
            el.style.border = '';
            el.style.backgroundColor = '';
            delete el.dataset.errorValidacion;
        });
    }

    function enfocarCampo(elemento) {
        if (typeof elemento === 'string') {
            elemento = document.getElementById(elemento);
        }
        if (elemento && elemento.focus) {
            elemento.focus();
            elemento.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // === MANEJO DEL BOTÓN PRINCIPAL ===
    document.getElementById('ingreso_cliente').addEventListener('submit', async function(e) {
        // PREVENIR SIEMPRE el submit nativo primero
        e.preventDefault();
        e.stopImmediatePropagation(); // ← AGREGADO: Detener otros listeners
        
        // Ejecutar validación
        const resultado = await validarDatos();
        
        // Si hay error crítico o usuario canceló, detener
        if (resultado.error) {
            console.log('Validación fallida:', resultado.campo || 'Cancelado por usuario');
            return false;
        }
        
        // Éxito: resultado.data contiene el arreglo final mapeado
        console.log('Datos validados listos para envío:', resultado.data);
        
        // Aquí continúas con el envío al backend
        await enviarDatosBackend(resultado.data);
    });

    /**
     * Resetea el formulario al estado inicial de selección de tipo
     * @param {boolean} mostrarConfirm - Si debe mostrar confirmación antes de resetear
     * @param {boolean} esExito - Si se llama desde un registro exitoso (no muestra advertencia de "datos perdidos")
     */
    async function resetearFormulario(mostrarConfirm = true, esExito = false) {
        // Resetear estado global
        TIPO_PERSONA_GLOBAL = null;
        tipoPersonaSeleccionado = null;
        
        // Habilitar botones de selección
        btnNatural.disabled = false;
        btnJuridica.disabled = false;
        
        // Restaurar estilos originales
        btnNatural.style.opacity = '1';
        btnNatural.style.background = '#fff';
        btnNatural.style.color = '#3498db';
        btnNatural.style.cursor = 'pointer';
        btnNatural.style.border = '2px solid #3498db';
        
        btnJuridica.style.opacity = '1';
        btnJuridica.style.background = '#fff';
        btnJuridica.style.color = '#27ae60';
        btnJuridica.style.cursor = 'pointer';
        btnJuridica.style.border = '2px solid #27ae60';

        // Ocultar controles de cambio y tabs
        containerCambiarTipo.style.display = 'none';
        tabsContainer.style.display = 'none';
        telefono1.style.display = 'none';
        telefono2.style.display = 'none';
        submitBtn.style.display = 'none';

        // Limpiar displays
        document.getElementById('tipo_persona').value = '';

        // Ocultar tabs
        document.getElementById('tab1').style.display = 'none';
        document.getElementById('tab2').style.display = 'none';
        
        // Quitar clase active de tabs
        document.querySelectorAll('.tablink').forEach(btn => btn.classList.remove('active'));

        // Limpiar TODOS los campos del formulario
        const form = document.getElementById('ingreso_cliente');
        form.reset();
        
        // Limpiar específicamente campos que form.reset() puede no capturar
        document.querySelectorAll('.campo-natural, .campo-juridica, .telefono-mask, .input').forEach(input => {
            input.value = '';
            input.required = false;
            input.style.border = '';
            input.style.backgroundColor = '';
            input.classList.remove('input-error');
        });

        // Limpiar foto
        fotoImg.src = '';
        fotoImg.style.display = 'none';
        fotoPlaceholder.style.display = 'block';
        inputFotoFile.value = '';
        fotoNombre.value = '';
        textoBtnFoto.textContent = 'Add Photo';

        // Limpiar errores visuales
        limpiarErroresVisuales();
        
        await cargarDatosIniciales();

        return true;
    }

    // ============================================
    // FUNCIÓN AUXILIAR PARA RECARGAR DATOS
    // ============================================

    async function cargarDatosIniciales() {
        let id_status = "<?php echo $id_status; ?>";
        await cargar_id_status(id_status, ruta_status_ajax);

        let id_tratamiento = "<?php echo $id_tratamiento; ?>";
        await cargar_id_tratamiento(id_tratamiento, platRutaTratamientoAjax, 'id_tratamiento');

        let id_tratamiento_jur = "<?php echo $id_tratamiento_jur; ?>";
        await cargar_id_tratamiento(id_tratamiento_jur, platRutaTratamientoAjax, 'id_tratamiento_jur');

        let id_sexo = "<?php echo $id_sexo; ?>";
        await cargar_id_sexo(id_sexo, sexoAjax, "id_sexo");

        let id_sexo_jur = "<?php echo $id_sexo_jur; ?>";
        await cargar_id_sexo(id_sexo_jur, sexoAjax, "id_sexo_jur");
    }

    // ============================================
    // FUNCIÓN DE ENVÍO (Ejemplo)
    // ============================================

    async function enviarDatosBackend(data) {
        suiteLoading('show');

        try {
            const form = document.getElementById("ingreso_cliente");
            const formData = new FormData(form);
        
            // ✅ CORRECCIÓN: Actualizar/Agregar los campos procesados/mapeados
            for (const [key, value] of Object.entries(data)) {
                if (value instanceof File) {
                    formData.set(key, value); // set reemplaza si existe
                } else {
                    formData.set(key, value);
                }
            }

            const response = await fetch(form.action, {
                method: form.method,
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText || 'Bad Request'}`);
            }
            
            const result = await response.json();
            
            suiteLoading('hide');
            if (result.success) {
                await suiteAlertSuccess('Success', 'Customer successfully registered');
                await resetearFormulario(false, true); // Sin confirmación, es éxito
            } else {
                throw new Error(result.message || 'Server error');
            }
            
        } catch (error) {
            suiteLoading('hide');
            console.error('Error en envío:', error);
            await suiteAlertError('Error', "Submission failed: " + error.message);
        }
    }

    /**
     * Formatea el número mientras el usuario escribe
     * Formato: +X (XXX) XXX-XXXX o +XX (XXX) XXX-XXXX
     */
    function formatearTelefono(e) {
        const input = e.target;
        const hiddenInputId = input.id.replace('-display', '');
        const hiddenInput = document.getElementById(hiddenInputId);
        
        // Obtener solo los números del valor actual
        let numeros = input.value.replace(/\D/g, '');
        
        // Limitar a 15 dígitos (estándar E.164)
        numeros = numeros.substring(0, 15);
        
        // Guardar solo números en el campo hidden (lo que se envía al backend)
        if (hiddenInput) {
            hiddenInput.value = numeros;
        }
        
        // Formatear para visualización
        let formateado = '';
        
        if (numeros.length === 0) {
            formateado = '';
        } else if (numeros.length <= 2) {
            // Código de país: +X o +XX
            formateado = '+' + numeros;
        } else if (numeros.length <= 5) {
            // +XX (X
            formateado = '+' + numeros.substring(0, 2) + ' (' + numeros.substring(2);
        } else if (numeros.length <= 8) {
            // +XX (XXX) XX
            formateado = '+' + numeros.substring(0, 2) + ' (' + numeros.substring(2, 5) + ') ' + numeros.substring(5);
        } else if (numeros.length <= 12) {
            // +XX (XXX) XXX-XXXX
            formateado = '+' + numeros.substring(0, 2) + ' (' + numeros.substring(2, 5) + ') ' + 
                        numeros.substring(5, 8) + '-' + numeros.substring(8);
        } else {
            // +XX (XXX) XXX-XXXX-XXXX (extensiones largas)
            formateado = '+' + numeros.substring(0, 2) + ' (' + numeros.substring(2, 5) + ') ' + 
                        numeros.substring(5, 8) + '-' + numeros.substring(8, 12) + '-' + numeros.substring(12);
        }
        
        // Actualizar el input visible
        input.value = formateado;
    }

    /**
     * Maneja el backspace para borrar correctamente
     */
    function manejarBackspace(e) {
        if (e.key === 'Backspace') {
            const input = e.target;
            const cursorPos = input.selectionStart;
            
            // Si el cursor está justo después de un carácter de formato, moverlo antes
            const charAntes = input.value.substring(cursorPos - 1, cursorPos);
            if ([' ', '(', ')', '-', '+'].includes(charAntes)) {
                e.preventDefault();
                input.setSelectionRange(cursorPos - 1, cursorPos - 1);
            }
        }
    }

    /**
     * Validación al salir del campo
     */
    function validarTelefonoCompleto(e) {
        const input = e.target;
        const hiddenInputId = input.id.replace('-display', '');
        const hiddenInput = document.getElementById(hiddenInputId);
        const numeros = hiddenInput.value;
        
        // Validar longitud mínima (código país + área + número = mínimo 10 dígitos)
        if (numeros.length > 0 && numeros.length < 10) {
            input.style.border = '2px solid #e74c3c';
            input.title = 'Phone number too short. Minimum 10 digits.';
        } else {
            input.style.border = '';
            input.title = '';
        }
    }

    document.addEventListener('DOMContentLoaded', async () => {
        // Cargar status
        let id_status = "<?php echo $id_status; ?>";
        await cargar_id_status(id_status, ruta_status_ajax);

        let id_tratamiento = "<?php echo $id_tratamiento; ?>";
        await cargar_id_tratamiento(id_tratamiento, platRutaTratamientoAjax, 'id_tratamiento');

        let id_tratamiento_jur = "<?php echo $id_tratamiento_jur; ?>";
        await cargar_id_tratamiento(id_tratamiento_jur, platRutaTratamientoAjax, 'id_tratamiento_jur');

        let id_sexo = "<?php echo $id_sexo; ?>";
        await cargar_id_sexo(id_sexo, sexoAjax, "id_sexo");

        let id_sexo_jur = "<?php echo $id_sexo_jur; ?>";
        await cargar_id_sexo(id_sexo_jur, sexoAjax, "id_sexo_jur");

        // Asegurar que todo esté oculto inicialmente
        tabsContainer.style.display = 'none';
        submitBtn.style.display = 'none';
        document.getElementById('tab1').style.display = 'none';
        document.getElementById('tab2').style.display = 'none';
    });
</script>