<?php
if (isset($url[1])) {
    $pagina = $url[1] ? $url[1] : 1;   ///Número de Página
} else {
    $pagina = 1;
}

$pagina_salas = $pagina;

$proceso_actual = "salas";
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
$ruta_salasnew = "salasNew";
$orden = isset($_GET['orden']) ? $_GET['orden'] : '#';
$direccion = isset($_GET['dir']) ? $_GET['dir'] : 'ASC';

$registrosPorPagina = 10;

// === Recuperar el orden desde sesión y parsearlo ===
$ordenActivo = [];

if (!isset($salas)) {
    echo "Problema con el Controlador de Salas Class";
}

if (!isset($_SESSION['nav_salas'])) {
    // Almacenar el nivel y página actual en la sesión
    $_SESSION['nav_salas'] = [
        'pagina_salas' => $pagina_salas,
        'registrosPorPagina' => $registrosPorPagina 
    ];
} else {
    if (isset($_SESSION['nav_salas']['registrosPorPagina'])) {
        if (!is_numeric($_SESSION['nav_salas']['registrosPorPagina'])) {
            $_SESSION['nav_salas']['registrosPorPagina'] = $registrosPorPagina;
        }
        $registrosPorPagina = $_SESSION['nav_salas']['registrosPorPagina'];
    }

    if (isset($_SESSION['nav_salas']['orden'])) {
        $f_orden = $_SESSION['nav_salas']['orden'];

        $ordenStr = $_SESSION['nav_salas']['orden'];
        $partes = explode(',', $ordenStr);
        foreach ($partes as $parte) {
            if (strpos($parte, ':') !== false) {
                [$campo, $dir] = explode(':', $parte);
                $ordenActivo[trim($campo)] = trim($dir);
            }
        }
    }

    if (isset($_SESSION['nav_salas']['pagina_salas'])) {
        if (!is_numeric($_SESSION['nav_salas']['pagina_salas'])) {
            $_SESSION['nav_salas']['pagina_salas'] = $pagina_salas;
        }
    } else {
        $_SESSION['nav_salas'] = [
            'pagina_salas' => $pagina_salas
        ];
    }
}

$ruta_retorno = RUTA_APP . "/dashboard";

$no_hacer = true;
$ruta_salas_ajax = APP_URL . "/app/ajax/salasAjax.php";
$encabezado = PROJECT_ROOT . "/app/views/inc/encabezado.php";
$opcion = "salas";
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
                    $pagina_salas,
                    $registrosPorPagina,
                    $url[0],
                    $busca_frase,
                    $ruta_retorno,
                    $orden,
                    $direccion
                ];
                echo $salas->listarsalasControlador($param_datos);
            ?>
        </div>
    </div>
</main>

<!-- Inyectar variables para búsqueda global -->
<script>
window.CONFIG_BUSQUEDA = <?php echo json_encode([
    'modulo' => $proceso_actual ?? 'salas',
    'pagina' => $pagina_salas ?? 1,
    'registrosPorPagina' => $registrosPorPagina ?? 10,
    'url' => $url[0] ?? 'salas',
    'orden' => $orden ?? '#',
    'direccion' => $direccion ?? 'ASC',
    'ruta_retorno' => $ruta_retorno ?? '/dashboard',
    'origen' => $proceso_actual
]); ?>;
</script>

<script src="<?= RUTA_REAL ?>/app/views/inc/js/ajax-busqueda.js?v=<?= time() ?>"></script>
<script src="<?= RUTA_REAL ?>/app/views/inc/js/func_comm.js?v=<?= time() ?>"></script>
<script type="text/javascript">
const ruta_retorno = "<?php echo $ruta_retorno; ?>";
const origen = "<?php echo $ruta_origen; ?>";

// === Eliminación segura de salas con suiteConfirm ===
document.addEventListener('click', async function(e) {
    const btn = e.target.closest('.btn-eliminar-salas');
    if (!btn) return;

    const idDireccion = btn.dataset.id;
    const pagina = parseInt(btn.dataset.pagina);
    const registrosPorPagina = parseInt(btn.dataset.registros);
    const urlOrigen = btn.dataset.url;

    if (!idDireccion || isNaN(pagina) || isNaN(registrosPorPagina)) {
        await suiteAlertError('Error', 'Missing data to delete address.');
        return;
    }

    const confirmado = await suiteConfirm(
        'Confirm Delete',
        'Are you sure you want to delete this Chat Rooms? This action cannot be undone.', {
            aceptar: 'Yes, delete',
            cancelar: 'Cancel'
        }
    );

    if (!confirmado) return;

    try {
        const res = await fetch('/app/ajax/salasAjax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                modulo_salas: 'eliminar',
                id_direccion: idDireccion
            })
        });

        const data = await res.json();

        if (data.success) {
            // ✅ Recargar la tabla en la misma página

            const filtro_act = document.getElementById("txt_buscador");
            if (filtro_act) filtro_act.value = '';
            const valor = filtro_act?.value.trim();

            recargarTablasalas(pagina, registrosPorPagina, urlOrigen, valor);
            await suiteAlertSuccess('Deleted', 'The address has been successfully removed.');
        } else {
            await suiteAlertError('Error', data.message || 'Could not delete the address.');
        }
    } catch (err) {
        console.error('Error al eliminar dirección:', err);
        await suiteAlertError('Connection Error', 'Could not connect to the server.');
    }
});

async function recargarTablasalas(pagina, registrosPorPagina, urlOrigen, busqueda = '') {
    try {
        const res = await fetch('/app/ajax/salasAjax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                modulo_salas: 'listar_tabla',
                pagina: pagina,
                registros_por_pagina: registrosPorPagina,
                url_origen: urlOrigen,
                busca_frase: busqueda
            })
        });
        const html = await res.text();
        // ✅ Reemplazar SOLO el contenedor con ID fijo

        const wrapper = document.getElementById('tabla-salas-wrapper');
        if (wrapper) {
            wrapper.outerHTML = html;
        } else {
            console.error('❌ No se encontró #tabla-salas-wrapper en el DOM');
        }
    } catch (err) {
        console.error('Error al recargar tabla de salas:', err);
        await suiteAlertError('Error', 'Could not refresh the address list.');
    }
}
</script>