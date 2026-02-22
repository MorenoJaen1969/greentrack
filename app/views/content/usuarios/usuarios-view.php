<?php
if (isset($url[1])) {
    $pagina = $url[1] ? $url[1] : 1;   ///Número de Página
} else {
    $pagina = 1;
}

$pagina_usuarios = $pagina;

$proceso_actual = "usuarios";
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
$ruta_usuariosnew = "usuariosNew";
$orden = isset($_GET['orden']) ? $_GET['orden'] : '#';
$direccion = isset($_GET['dir']) ? $_GET['dir'] : 'ASC';

$registrosPorPagina = 10;

// === Recuperar el orden desde sesión y parsearlo === 
$ordenActivo = [];

if (!isset($usuarios)) {
    echo "Problema con el Controlador de Usuarios";
}

if (!isset($_SESSION['nav_usuarios'])) {
    // Almacenar el nivel y página actual en la sesión
    $_SESSION['nav_usuarios'] = [
        'pagina_usuarios' => $pagina_usuarios,
        'registrosPorPagina' => $registrosPorPagina 
    ];
} else {
    if (isset($_SESSION['nav_usuarios']['registrosPorPagina'])) {
        if (!is_numeric($_SESSION['nav_usuarios']['registrosPorPagina'])) {
            $_SESSION['nav_usuarios']['registrosPorPagina'] = $registrosPorPagina;
        }
        $registrosPorPagina = $_SESSION['nav_usuarios']['registrosPorPagina'];
    }

    if (isset($_SESSION['nav_usuarios']['orden'])) {
        $f_orden = $_SESSION['nav_usuarios']['orden'];

        $ordenStr = $_SESSION['nav_usuarios']['orden'];
        $partes = explode(',', $ordenStr);
        foreach ($partes as $parte) {
            if (strpos($parte, ':') !== false) {
                [$campo, $dir] = explode(':', $parte);
                $ordenActivo[trim($campo)] = trim($dir);
            }
        }
    }

    if (isset($_SESSION['nav_usuarios']['pagina_usuarios'])) {
        if (!is_numeric($_SESSION['nav_usuarios']['pagina_usuarios'])) {
            $_SESSION['nav_usuarios']['pagina_usuarios'] = $pagina_usuarios;
        }
    } else {
        $_SESSION['nav_usuarios'] = [
            'pagina_usuarios' => $pagina_usuarios
        ];
    }
}

$ruta_retorno = RUTA_APP . "/dashboard";

$no_hacer = true;
$ruta_usuarios_ajax = APP_URL . "/app/ajax/usuariosAjax.php";
$encabezado = PROJECT_ROOT . "/app/views/inc/encabezado.php";
$opcion = "usuarios";
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
                    $pagina_usuarios,
                    $registrosPorPagina,
                    $url[0],
                    $busca_frase,
                    $ruta_retorno,
                    $orden,
                    $direccion
                ];
                echo $usuarios->listarUsuarioControlador($param_datos);
            ?>
        </div>
    </div>
</main>

<!-- Inyectar variables para búsqueda global -->
<script>
window.CONFIG_BUSQUEDA = <?php echo json_encode([
    'modulo' => $proceso_actual ?? 'usuarios',
    'pagina' => $pagina_usuarios ?? 1,
    'registrosPorPagina' => $registrosPorPagina ?? 10,
    'url' => $url[0] ?? 'usuarios',
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

    // === Eliminación segura de usuarios con suiteConfirm ===
    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.btn-eliminar-direccion');
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
            'Are you sure you want to delete this address? This action cannot be undone.',
            { aceptar: 'Yes, delete', cancelar: 'Cancel' }
        );

        if (!confirmado) return;

        try {
            const res = await fetch('/app/ajax/usuariosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_usuarios: 'eliminar',
                    id_direccion: idDireccion
                })
            });

            const data = await res.json();

            if (data.success) {
                // ✅ Recargar la tabla en la misma página

                const filtro_act = document.getElementById("txt_buscador");
                if (filtro_act) filtro_act.value = '';
                const valor = filtro_act?.value.trim();

                recargarTablausuarios(pagina, registrosPorPagina, urlOrigen, valor);
                await suiteAlertSuccess('Deleted', 'The address has been successfully removed.');
            } else {
                await suiteAlertError('Error', data.message || 'Could not delete the address.');
            }
        } catch (err) {
            console.error('Error al eliminar dirección:', err);
            await suiteAlertError('Connection Error', 'Could not connect to the server.');
        }
    });

    async function recargarTablausuarios(pagina, registrosPorPagina, urlOrigen, busqueda = '') {
        try {
            const res = await fetch('/app/ajax/usuariosAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_usuarios: 'listar_tabla',
                    pagina: pagina,
                    registros_por_pagina: registrosPorPagina,
                    url_origen: urlOrigen,
                    busca_frase: busqueda
                })
            });
            const html = await res.text();
            // ✅ Reemplazar SOLO el contenedor con ID fijo

            const wrapper = document.getElementById('tabla-usuarios-wrapper');
            if (wrapper) {
                wrapper.outerHTML = html;
            } else {
                console.error('❌ No se encontró #tabla-usuarios-wrapper en el DOM');
            }
        } catch (err) {
            console.error('Error al recargar tabla de usuarios:', err);
            await suiteAlertError('Error', 'Could not refresh the address list.');
        }
    }

    
</script>