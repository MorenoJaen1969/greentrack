<?php
if (isset($url[0])) {
    $proceso_actual = $url[0];
} else {
    $proceso_actual = "usuariosVista";
}

if (isset($url[1])) {
    $ruta_retorno = RUTA_APP . "/" . $url[1];
} else {
    $ruta_retorno = RUTA_APP . "/dashboard";
}

if (isset($url[2])) {
    $id_usuario = $url[2];
} else {
    $id_usuario = 0;
}

if (isset($url[3])) {
    $pagina_retorno = $url[3];
} else {
    $pagina_retorno = 1;
}

$param_dat = [
    'id_usuario' => $id_usuario
];

$row1 = $usuarios->consulta_registro($param_dat);

error_log("Resultado de consulta: " . json_encode($row1));

$id_usuario = $row1['id'];
$nombre = $row1['nombre'];
$username = $row1['username'];
if (is_null($row1['email'])) {
    $email = "---";
} else {
    $email = $row1['email'];
}
$phone = $row1['phone']; 
$id_sexo = $row1['id_sexo'];
$usuario_foto = $row1['usuario_foto'];
$id_status = $row1['activo'];
$chat_activo = $row1['chat_activo'];

// fecha_registro
// chat_avatar
// chat_estado
// chat_ultima_conexion
// es_sistema
// ultima_sala_id
// area
// password_hash
// user_agent_fijo

// ----------------------------------------------------------------
// Valores para el SELECT
$estatus_options = [
    ['value' => '1', 'label' => 'Active'],
    ['value' => '2', 'label' => 'Inactive']
];

// Valor seleccionado (ejemplo)
$selected_value = $id_status;
// ----------------------------------------------------------------

// ----------------------------------------------------------------
// Valores para el SELECT
$chat_options = [
    ['value' => '1', 'label' => 'Active'],
    ['value' => '2', 'label' => 'Inactive']
];

// Valor seleccionado (ejemplo)
$selected_value_1 = $chat_activo;
// ----------------------------------------------------------------

if ($id_status == 2) {
    $puede_editar = 'disabled';
} else {
    $puede_editar = '';
}

$modo_edicion = true;

$ruta_status_ajax = RUTA_APP . "/app/ajax/statusAjax.php";
$ruta_direcciones_ajax = RUTA_APP . "/app/ajax/direccionesAjax.php";
$ruta_usuario_ajax = RUTA_APP . "/app/ajax/usuariosAjax.php";
$ruta_usuario = RUTA_APP . "/usuario/";
$encabezadoVista = PROJECT_ROOT . "/app/views/inc/encabezadoVista.php";
$opcion = "usuariosVista";

?>

<main>
    <p hidden id="ruta_usuario"><?php echo $ruta_usuario; ?></p>
    <p hidden id="ruta_usuario_ajax"><?php echo $ruta_usuario_ajax; ?></p>

    <?php
    require_once $encabezadoVista;
    ?>

    <div class="form-container">
        <form class="FormularioAjax form-horizontal validate-form" action="<?php echo $ruta_usuario_ajax; ?>"
            method="POST" id="update_usuario" name="update_usuario" enctype="multipart/form-data" autocomplete="off">

            <input class="form-font" type="hidden" name="modulo_usuarios" value="update_usuario">
            <input class="form-font" type="hidden" id="id_usuario" name="id_usuario"
                value="<?php echo $row1['id']; ?>">

            <div class="tab-container">
                <div class="tabs-gen">
                    <button type="button" class="tab-button active tablink" data-tab="tab1" onclick="openTab(event, 'tab1')">
                        Personal Data
                    </button>
                </div>

                <div id="tab1" class="tabcontent tab-link">
                    <div class="forma01">
                        <h3>Personal Data</h3>
                        <div class="grupo_user10">
                            <div class="grupo_foto01">
                                <div class="form-group-ct-incolumn">
                                    <div class="form-group-ct">
                                        <label for="nombre">Name</label>
                                        <input class="input" type="text" id="nombre" name="nombre" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}"
                                            maxlength="40" value="<?php echo $nombre; ?>" required>
                                    </div>
                                    <div class="form-group-ct-inline">
                                        <div class="form-group-ct">
                                            <label for="username">User</label>
                                            <input class="input" type="text" id="username" name="username" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{3,40}"
                                                maxlength="40" value="<?php echo $username; ?>" required>
                                        </div>
                                        <div class="form-group-ct">
                                            <label for="activo">Status</label>
                                            <select class="form-control form-font" id="activo" name="activo" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                                <?php foreach ($estatus_options as $option): ?>
                                                    <option value="<?php echo $option['value']; ?>" 
                                                            <?php echo ($option['value'] == $selected_value) ? 'selected' : ''; ?>>
                                                        <?php echo $option['label']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="grupo_foto02">
                                <div class="photo-container-2">
                                    <div class="photo-container_01 upload-btn">
                                        <label for="fileInput" class="custom-file-label">Select Image</label>
                                        <input type="file" multiple="" id="fileInput" name="fileInput" accept="image/*" onchange="previewImage(event)">
                                    </div>
                                    <div class="photo-container_02 image-preview">
                                        <?php
                                        $ruta_img = '/app/views/img/uploads/fotos/' . $usuario_foto;
                                        if ($id_sexo == 1) {
                                            $r_sin_img = '/app/views/fotos/default.png';
                                        } else {
                                            $r_sin_img = '/app/views/fotos/responsable-mujer.png';
                                        }

                                        if ($usuarios->isFile($ruta_img)) {
                                            echo '<img class="is-rounded" src="' . $ruta_img . '" alt="Imagen previa" id="imagePreview" name="imagePreview">';
                                        } else {
                                            echo '<img class="is-rounded" src="' . $r_sin_img . '" alt="Imagen previa" id="imagePreview">';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="forma02">
                        <h3>Other data</h3>
                        <div class="form-group-ct-inline">
                            <div class="form-group-ct">
                                <label for="phone">Enter your phone number
                                    <br />
                                    <small>Format: +XX (123) 456-7890</small>
                                </label>
                                <input class="input" type="tel" id="phone" name="phone"
                                    pattern="\+\d{1,4}(\s|\-)?(\(\d+\))?(\s|\-)?\d{3,4}(\s|\-)?\d{3,4}(\s|\-)?\d{0,4}"
                                    placeholder="+XX (XXX) XXX-XXXX"
                                    value="<?php echo $phone; ?>" required>
                            </div>

                            <div class="form-group-ct">
                                <label for = "email">Email</label>
                                <input class="input" type="email" id="email" name="email" maxlength="70"
                                    value="<?php echo $email; ?>">
                            </div>
                        </div>
                        <div class="form-group-ct">
                            <label for="chat_activo">Chat Member</label>
                            <select class="form-control form-font" id="chat_activo" name="chat_activo" <?php echo !$modo_edicion ? 'disabled' : ''; ?> required>
                                <?php foreach ($chat_options as $option): ?>
                                    <option value="<?php echo $option['value']; ?>" 
                                            <?php echo ($option['value'] == $selected_value_1) ? 'selected' : ''; ?>>
                                        <?php echo $option['label']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn-submit" id="submitBtn" <?php echo $puede_editar; ?>>
                Save changes
            </button>
        </form>
    </div>
</main>

<script src="<?= RUTA_REAL ?>/app/views/inc/js/func_comm.js?v=<?= time() ?>"></script>
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

    const ruta_usuario_ajax = "<?php echo $ruta_usuario_ajax; ?>";

    let r_retorno = "<?php echo $ruta_retorno . "/" . $pagina_retorno; ?>";

    const ruta_retorno = r_retorno;

    // === ABRIR PESTAÑAS ===
    function openTab(evt, tabName) {
        document.querySelectorAll(".tabcontent").forEach(el => el.style.display = "none");
        document.querySelectorAll(".tablink").forEach(btn => btn.classList.remove("active"));
        const tab = document.getElementById(tabName);
        const submitBtn = document.getElementById('submitBtn');
        tab.style.display = "block";
        if (evt) evt.currentTarget.classList.add("active");
    }

        // === VALIDACIÓN DE FORMULARIO ===
    function validateTabs() {
        if (!validateTab(1)) return showTab(1) && false;
        return true;
    }

    function showTab(index) {
        ["tab1"].forEach((id, i) => {
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

    function previewImage(event) {
        const reader = new FileReader();
        const imageField = document.getElementById("imagePreview");
        reader.onload = function() {
            if (reader.readyState === 2) {
                imageField.src = reader.result;
            }
        }
        reader.readAsDataURL(event.target.files[0]);
    }

    // === ENVÍO DEL FORMULARIO PRINCIPAL ===
    document.addEventListener('DOMContentLoaded', async () => {
        suiteLoading('show');

        openTab(null, 'tab1');

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

            const form = document.getElementById("update_usuario");
            const data = new FormData(form);

            try {
                const res = await fetch(form.action, 
                    { 
                        method: form.method, 
                        body: data
                    }
                );

                const json = await res.json();

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

        suiteLoading('hide');
    });
</script>