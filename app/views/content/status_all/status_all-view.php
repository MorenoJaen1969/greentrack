<?php
if (isset($url[1])) {
    $pagina = $url[1] ? $url[1] : 1;   ///Número de Página
} else {
    $pagina = 1;
}

$pagina_status_all = $pagina;

$proceso_actual = "status_all";
$busqueda = "";

if(isset($_SESSION['filtro'])){
    if($_SESSION['filtro']==""){
        $no_hacer = false;
    }else{ 
        if(isset($_SESSION['origen'])){
            if($_SESSION['origen']==$proceso_actual){
                $busqueda = $_SESSION['filtro'];
                $no_hacer = true;
            }else{
                $no_hacer = false;
                $_SESSION['origen']="";
                $_SESSION['filtro']="";
            }
        }else{
            $no_hacer = false;
            $_SESSION['origen']="";
            $_SESSION['filtro']="";
        }
    }
}else{
    $no_hacer = false;
}

$ruta_origen = "dashboard/";
$ruta_status_allnew = "status_allNew";
$orden = isset($_GET['orden']) ? $_GET['orden'] : '#';
$direccion = isset($_GET['dir']) ? $_GET['dir'] : 'ASC';

$registrosPorPagina = 10;

// === Recuperar el orden desde sesión y parsearlo ===
$ordenActivo = [];

if (!isset($status)) {
    echo "Problema con el Controlador de Todos los Status";
}

if (!isset($_SESSION['nav_status_all'])) {
    // Almacenar el nivel y página actual en la sesión
    $_SESSION['nav_status_all'] = [
        'pagina_status_all' => $pagina_status_all,
        'registrosPorPagina' => $registrosPorPagina
    ];
} else {
    if (isset($_SESSION['nav_status_all']['registrosPorPagina'])) {
        if (!is_numeric($_SESSION['nav_status_all']['registrosPorPagina'])) {
            $_SESSION['nav_status_all']['registrosPorPagina'] = $registrosPorPagina;
        }
        $registrosPorPagina = $_SESSION['nav_status_all']['registrosPorPagina'];
    }

    if (isset($_SESSION['nav_status_all']['orden'])) {
        $f_orden = $_SESSION['nav_status_all']['orden'];

        $ordenStr = $_SESSION['nav_status_all']['orden'];
        $partes = explode(',', $ordenStr);
        foreach ($partes as $parte) {
            if (strpos($parte, ':') !== false) {
                [$campo, $dir] = explode(':', $parte);
                $ordenActivo[trim($campo)] = trim($dir);
            }
        }
    }

    if (isset($_SESSION['nav_status_all']['pagina_status_all'])) {
        if (!is_numeric($_SESSION['nav_status_all']['pagina_status_all'])) {
            $_SESSION['nav_status_all']['pagina_status_all'] = $pagina_status_all;
        }
    } else {
        $_SESSION['nav_status_all'] = [
            'pagina_status_all' => $pagina_status_all
        ];
    }
}

$ruta_retorno = RUTA_APP . "/dashboard";

$no_hacer = true;
$ruta_status_all_ajax = APP_URL . "/app/ajax/statusAjax.php";
$encabezado = PROJECT_ROOT . "/app/views/inc/encabezado.php";
$opcion = "status_all";
?>

<main>
    <?php
        require_once $encabezado;
    ?>

    <div class="container pb-1 pt-1">
        <div id="datos_act" name="datos_act">
            <?php
                if ($no_hacer == false) {
                    $busca_frase = "";
                } else {
                    $busca_frase = $busqueda;
                }
                $param_datos = [
                    $pagina_status_all,
                    $registrosPorPagina,
                    $url[0],
                    $busca_frase,
                    $ruta_retorno,
                    $orden,
                    $direccion
                ];
                echo $status->listarstatus_allControlador($param_datos);
            ?>
        </div>
    </div>
</main>

<!-- Inyectar variables para búsqueda global -->
<script>
window.CONFIG_BUSQUEDA = <?php echo json_encode([
    'modulo' => $proceso_actual ?? 'direcciones',
    'pagina' => $pagina_direcciones ?? 1,
    'registrosPorPagina' => $registrosPorPagina ?? 10,
    'url' => $url[0] ?? 'direcciones',
    'orden' => $orden ?? '#',
    'direccion' => $direccion ?? 'ASC',
    'ruta_retorno' => $ruta_retorno ?? '/dashboard',
    'origen' => $proceso_actual
]); ?>;
</script>

<script src="<?= RUTA_REAL ?>/app/views/inc/js/ajax-busqueda.js?v=<?= time() ?>"></script>
<script src="<?= RUTA_REAL ?>/app/views/inc/js/func_comm.js?v=<?= time() ?>"></script>
<script type="text/javascript">
    // Pasar el orden de la sesión a JavaScript
    window.ordenActivoPHP = <?php echo json_encode(array_keys($ordenActivo)); ?>;
    const ruta_retorno = "<?php echo $ruta_retorno; ?>";
    const origen = "<?php echo $ruta_origen; ?>";

    // === Actualizar números de secuencia ===
    function actualizarNumerosOrden() {
        const items = document.querySelectorAll('.orden-container .orden-item');
        items.forEach((item, index) => {
            let numero = item.querySelector('.orden-numero');
            if (!numero) {
                numero = document.createElement('span');
                numero.className = 'orden-numero';
                item.insertBefore(numero, item.firstChild);
            }
            numero.textContent = index + 1;
        });
    }

    function actualizarEstadoRadios(campo) {
        const chk = document.getElementById('chk_' + campo);
        const grupo = document.getElementById('grupo-' + campo);
        const radios = grupo.querySelectorAll('input[type="radio"]');

        if (chk && grupo) {
            if (chk.checked) {
                radios.forEach(radio => { radio.disabled = false; });
                grupo.style.opacity = "1";
                if (!document.querySelector(`input[name="ord_${campo}"]:checked`)) {
                    document.getElementById(`ord_${campo}_asc`).checked = true;
                }
            } else {
                radios.forEach(radio => { radio.disabled = true; });
                grupo.style.opacity = "0.5";
            }
        }
    }

    function actualizarVista() {
        // === 1. Mostrar spinner inmediatamente ===
        const spinner = document.getElementById('loading-spinner');
        if (spinner) {
            spinner.style.display = 'flex';
            // Opcional: fuerza reflow para asegurar que el navegador lo pinte
            void spinner.offsetWidth;
        }

        // === 2. Capturar datos en orden visual (DOM order) ===
        const ordenCampos = [];

        // Recorrer los .orden-item en el orden en que están en el DOM
        document.querySelectorAll('.orden-container .orden-item')?.forEach(item => {
            const campo = item.dataset.campo;
            const chk = document.getElementById(`chk_${campo}`);
            if (chk && chk.checked) {
                const radio = document.querySelector(`input[name="ord_${campo}"]:checked`);
                const dir = radio ? radio.value : 'asc';
                ordenCampos.push(`${campo}:${dir}`);
            }
        });

        const ordenStr = ordenCampos.join(',') || 'piso:asc';

        // === 3. Capturar datos ===
        const registrosPorPagina = document.getElementById('registrosPorPagina')?.value || 10;
        const cmb_edificios = document.getElementById('cmb_edificios')?.value || 0;
        const cmb_pisos = document.getElementById('cmb_pisos')?.value || 'all';
        const estado_status_all = document.querySelector('input[name="estado_status_all"]:checked')?.value || 'all';

        const formData = new FormData();
        formData.append('modulo_status_all', 'actualizar_navegacion');
        formData.append('registrosPorPagina', registrosPorPagina);
        formData.append('f_edificio', cmb_edificios);
        formData.append('f_piso', cmb_pisos);
        formData.append('f_estado', estado_status_all);
        formData.append('orden', ordenStr);

        const ruta_status_all_ajax = "<?php echo $ruta_status_all_ajax; ?>";

        // === 4. Enviar vía fetch ===
        fetch(ruta_status_all_ajax, {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) throw new Error('Error ' + response.status);
                return response.text();
            })
            .then(data => {
                console.log('Configuración guardada:', data);
                // === 4. Recargar después de un breve retraso ===
                setTimeout(() => {
                    const url = new URL(window.location.href);
                    url.searchParams.set('pagina', '1');
                    url.searchParams.set('t', Date.now());
                    window.location.href = url.toString();
                }, 50); // 50ms es suficiente para que el spinner se pinte
            })
            .catch(error => {
                console.error('Error:', error);
                setTimeout(() => {
                    window.location.href = window.location.href.split('?')[0] + '?pagina=1&t=' + Date.now();
                }, 50);
            });
    }

    // Función auxiliar: ¿dónde soltar?
    function getDragAfterElement(container, y) {
        const draggableElements = [...container.parentNode.querySelectorAll('.orden-item:not(.dragging)')];
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const registrosPorPagina = document.getElementById('registrosPorPagina');
        const cmb_edificios = document.getElementById('cmb_edificios');
        const cmb_pisos = document.getElementById('cmb_pisos');
        const radiosEstado = document.querySelectorAll('input[name="estado_status_all"]');
        const ordenInputs = document.querySelectorAll('input[name^="ord_"], input[name="ord_campos[]"]');
        const spinner = document.getElementById('loading-spinner');
        const contenido = document.getElementById('contenido-principal');
        const btnAplicarOrden = document.getElementById('btn-aplicar-orden');
        const ordenContainer = document.querySelector('.orden-container');
        const itemPiso = ordenContainer?.querySelector('[data-campo="piso"]');
        const chkPiso = document.getElementById('chk_piso');

        if (ordenContainer && window.ordenActivoPHP) {
            const ordenCampos = window.ordenActivoPHP; // ['piso', 'status_all', ...]

            ordenCampos.forEach(campo => {
                const item = ordenContainer.querySelector(`[data-campo="${campo}"]`);
                if (item) {
                    ordenContainer.prepend(item.cloneNode(true));
                    item.remove();
                }
            });

            // Actualizar números
            actualizarNumerosOrden();
        }

        if (contenido) {
            contenido.classList.add('contenido-listo');
        }

        // === Detectar cambios en filtros y orden ===
        // Cambio: registros por página
        if (registrosPorPagina) {
            registrosPorPagina.addEventListener('change', actualizarVista);
        }

        // === Drag & Drop para ordenamiento ===
        let draggedItem = null;

        // === Inicializar estado de radios ===
        document.querySelectorAll('.orden-item').forEach(item => {
            const campo = item.dataset.campo;
            actualizarEstadoRadios(campo);
        });

        // === Actualizar números al cargar ===
        actualizarNumerosOrden();

        document.querySelectorAll('.orden-item').forEach(item => {
            // Iniciar arrastre
            item.addEventListener('dragstart', function () {
                draggedItem = this;
                this.classList.add('dragging');
            });

            item.addEventListener('dragend', function () {
                this.classList.remove('dragging');
                draggedItem = null;
                // === Actualizar números después de soltar ===
                actualizarNumerosOrden();
            });

            // Permitir soltar
            item.addEventListener('dragover', function (e) {
                e.preventDefault();
                const afterElement = getDragAfterElement(this, e.clientY);
                const current = draggedItem;
                if (afterElement == null) {
                    this.parentNode.appendChild(current);
                } else {
                    this.parentNode.insertBefore(current, afterElement);
                }
            });
        });
        // *******************************************

        // Cambio: estado
        radiosEstado.forEach(radio => {
            radio.addEventListener('change', actualizarVista);
        });

    });

</script>