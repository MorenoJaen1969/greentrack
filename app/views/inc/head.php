<link rel="shortcut icon" href="<?= RUTA_REAL ?>/app/views/img/track.png">

<!-- Js principal -->

<!-- Estilos del dashboard -->
<link rel="stylesheet" href="<?= RUTA_REAL ?>/app/views/inc/css/style.css?v=<?= time() ?>">

<?php
if (isset($url)) {
    $raices = [
        "address_clas",
        "address_type",
        "chat",
        "clientes",
        "contratos",
        "crew",
        "dashboard",
        "dias_no_actividad",        
        "direcciones",
        "proveedores",
        "rutas_mapa",        
        "servicios",
        "status_all",
        "vehiculos"
    ];

    $opcion = $url[0];

    $encontrado = false;
    $pos_ini = 0;
    $raiz = "";
    foreach ($raices as $valor) {
        $posicionCoincidencia = strpos($opcion, $valor);
        if ($posicionCoincidencia !== false) {
            $encontrado = true;
            $raiz = $raices[$pos_ini];
            break;
        }
        $pos_ini = $pos_ini + 1;
    }

    if ($url[0] == "dashboard") {
        ?>
        <!-- Carrusel: Estilos -->
        <link rel="stylesheet" href="<?= RUTA_REAL ?>/app/views/inc/css/carrusel.css">
        <!-- Leaflet CSS -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link rel="stylesheet" href="<?= RUTA_REAL ?>/app/views/inc/css/chat.css?v=<?= time() ?>">
        <?php
    } elseif ($url[0] == "rutas_mapa") {
        ?>
        <link rel="stylesheet" href="<?= RUTA_REAL ?>/app/views/inc/css/rutas_mapa.css?v=<?= time() ?>">
        <link rel="stylesheet" href="<?= RUTA_REAL ?>/app/views/inc/css/suiteAlert.css?v=<?= time() ?>">
        <!-- Leaflet CSS -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

        <script src="<?= RUTA_REAL ?>/app/views/inc/js/suiteAlert.js?v=<?= time() ?>"></script>
        <?php
    } else {
		// Desactivar el caché del navegador
		header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
		header("Pragma: no-cache"); // HTTP/1.0
		header("Expires: Sat, 1 Jul 2000 05:00:00 GMT"); // Fecha pasada para forzar la expiración
		header("Content-Type: text/html; charset=utf-8");
        ?>
		<!-- Font-Awesome CSS-->
		<link rel="stylesheet" href="<?php echo RUTA_FONTAWESOME; ?>css/all.min.css">
        
        <link rel="stylesheet" href="<?= RUTA_REAL ?>/app/views/inc/css/bulma.min.css">
        <link rel="stylesheet" href="<?= RUTA_REAL ?>/app/views/inc/css/fuentes.css?v=<?= time() ?>">
        <link rel="stylesheet" href="<?= RUTA_REAL ?>/app/views/inc/css/formularios.css?v=<?= time() ?>">
        <link rel="stylesheet" href="<?= RUTA_REAL ?>/app/views/inc/css/suiteAlert.css?v=<?= time() ?>">

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link rel="stylesheet" href="<?= RUTA_REAL ?>/app/views/inc/css/chat.css?v=<?= time() ?>">
        <script src="<?= RUTA_REAL ?>/app/views/inc/js/suiteAlert.js?v=<?= time() ?>"></script>

        <?php
        if ($url[0] == "direcciones") {
            ?>
                <link rel="stylesheet" href="<?= RUTA_REAL ?>/app/views/inc/css/crud.css">
            <?php
        } elseif ($url[0] == "direccionesVista") {
            ?>
                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
            <?php
        }
    }
}
?>