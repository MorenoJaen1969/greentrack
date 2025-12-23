<?php
ob_start();
require_once "app/views/inc/session_start.php";

// === 2. Cargar configuración y autoload ===
require_once "config/app.php";
require_once "autoload.php";

// === 3. Cargar el controlador ===
require_once  'app/controllers/usuariosController.php';
use app\controllers\usuariosController;

$controller = new usuariosController();

$param = [
    'nombre' => 'Adriana Cordoba',
    'email' => 'adriana@sergioslandscape.com'
];

$controller->nuevo_usuario($param);
