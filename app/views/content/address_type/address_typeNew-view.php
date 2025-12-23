<?php
    $ruta_address_type_ajax = RUTA_APP."/app/ajax/address_typeAjax.php";
    $ruta_address_type = RUTA_APP."/address_type/";

    $encabezadoNew = PROJECT_ROOT . "/app/views/inc/encabezadoNew.php";

    $ruta_retorno = "/address_type";

    $opcion = "address_typeNew";
?>

<main>
    <p hidden id="$ruta_address_type"><?php echo $ruta_address_type;?></p>    
    <p hidden id="$ruta_address_type_ajax"><?php echo $ruta_address_type_ajax;?></p>    

    <?php
        require_once $encabezadoNew;
    ?>

    <div class="form-container">
        <form class="FormularioAjax form-horizontal" id="ingreso_address_type" name="ingreso_address_type" action="<?php echo $ruta_address_type_ajax; ?>" method="POST" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="modulo_address_type" value="registrar_address_type">

            <div class="tab-container">
                <div class="tabs-gen">
                    <button type="button" class="tab-button active tablink" data-tab="tab1" onclick="openTab(event, 'tab1')">
                        Address Sort Configuration
                    </button>
                </div>
            </div>

            <div id="tab1" class="tabcontent">
                <div class="forma01">
                    <h3>Address Type</h3>
                    <div class="form-group-ct-inline">
                        <div class="form-group-ct">
                            <label class="ancho_label1" for="nombre">Address Type Identification:</label>
                            <input type="text" id="address_type" name="address_type" required>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                Register Address Type
            </button>                
        </form>
    </div>

</main>

<script type="text/javascript">
    const formularios_ajax=document.querySelectorAll(".FormularioAjax");
    const ruta_retorno = "<?php echo $ruta_retorno; ?>";

    let arreglo_frases_g = [];
    var p01 = "Are you sure";
    var p02 = "Do you want to perform the requested action";
    var p03 = "Yes, perform";
    var p04 = "Do not cancel";
    var p05 = "Response error";

    arreglo_frases_g = [
        p01,
        p02,
        p03,
        p04,
        p05
    ];

    function openTab(evt, tabName) {
        // Ocultar todas las pestañas
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tabcontent");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }

        // Eliminar la clase "active" de todos los botones
        tablinks = document.getElementsByClassName("tablink");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }

        // Mostrar la pestaña seleccionada
        document.getElementById(tabName).style.display = "block";
        if (typeof evt !== 'undefined') {
            evt.currentTarget.classList.add("active");
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const address_type = document.getElementById('address_type');
        openTab(null, 'tab1'); // Abrir la primera pestaña por defecto
    });

    document.getElementById("submitBtn").addEventListener("click", function(event) {
        // Valida campos de la primera pestaña (Identificación de la Obra)
        let valid = validateTab1();
        let pagina = 1;

        // Si hay algún campo inválido, redirigir a la pestaña correspondiente
        if (!valid) {
            // Mostrar la pestaña correspondiente según la página con error
            if (pagina === 1) {
                document.getElementById("tab1").style.display = "block";
            }            
        }
    });
    
    function validateTab1() {
        let fields = document.querySelectorAll("#tab1 input, #tab1 select");
        let valid = true;
        fields.forEach(field => {
            // Espera un breve momento y luego enfoca en el primer campo inválido
            if (!field.checkValidity()) {
                // Redirige a la pestaña correcta y actualiza la clase activa
                document.querySelector(".tab-button[data-tab='tab1']").click(); // Simula el clic para cambiar la pestaña
                field.focus(); // Enfoca el primer campo inválido
                field.reportValidity(); // Muestra el mensaje de error HTML5
                valid = false;
            }
        });
        return valid;
    }

    function openTab(evt, tabName) {
        // Oculta todo el contenido de las pestañas
        let tabcontent = document.querySelectorAll(".tabcontent");
        tabcontent.forEach(content => content.style.display = "none");

        // Remueve la clase "active" de todos los botones de las pestañas
        let tablinks = document.querySelectorAll(".tab-button");
        tablinks.forEach(link => link.classList.remove("active"));

        // Muestra el contenido de la pestaña actual
        document.getElementById(tabName).style.display = "block";

        // Agrega la clase "active" al botón que abrió la pestaña
        if (evt && evt.currentTarget) {
            evt.currentTarget.classList.add("active");
        } else {
            // Si no hay evento, activar el botón correspondiente a la pestaña
            const targetButton = document.querySelector(`.tab-button[data-tab="${tabName}"]`);
            if (targetButton) {
                targetButton.classList.add("active");
            }
        }        
    }

    formularios_ajax.forEach(formularios => {
        var title = arreglo_frases_g[0];
        var text = arreglo_frases_g[1];
        var confirma = arreglo_frases_g[2];
        var cancelar = arreglo_frases_g[3];
        var error_res = arreglo_frases_g[4];
        
        formularios.addEventListener("submit", async function(e){
            
            e.preventDefault();

            const confirmado = await suiteConfirm(
                title,
                text,
                {
                    aceptar: confirma,
                    cancelar: cancelar
                }                
            );
            
            if (!confirmado) return;
            
            try {
                // Preparar datos del formulario
                let data = new FormData(this);
                let method = this.getAttribute("method");
                let action = this.getAttribute("action");

                let encabezados= new Headers();

                let config = {
                    method: method,
                    headers: encabezados,
                    mode: 'cors',
                    cache: 'no-cache',
                    body: data
                };

                // Enviar petición
                const respuesta = await fetch(action, config);
                const respuestaText = await respuesta.text();
                const respuestaJSON = JSON.parse(respuestaText);

                if (respuestaJSON.tipo == 'success') {
                    window.location.href = "<?php echo RUTA_APP; ?>" + ruta_retorno;
                    return suiteAlertSuccess("Success", respuestaJSON.texto);
                } else {
                    console.error('Error parsing JSON:', respuestaJSON.texto);
                    return suiteAlertError("Error", respuestaJSON.texto);
                }

            } catch (err) {
                await suiteAlertError("Error", "The incorporation of the new record failed: " + err.message);
                console.error(err);
            }
        });
    });

</script>