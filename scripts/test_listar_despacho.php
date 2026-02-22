<?php
// Script de prueba: invoca listarServicios_despachos para una fecha específica
chdir(__DIR__ . '/..');
require_once 'config/app.php';
require_once 'autoload.php';
// Incluir inicio de sesión mínimo
if (file_exists('app/views/inc/session_start.php')) {
    require_once 'app/views/inc/session_start.php';
}

use app\controllers\serviciosController;

$controller = new serviciosController();
ob_start();
$controller->listarServicios_despachos('2025-12-31');
$output = ob_get_clean();
// Mostrar salida en CLI
echo $output;
