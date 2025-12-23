<?php
// internalAjax.php
ob_start();
require_once "../views/inc/session_start.php";
require_once "../../config/app.php";
require_once "../../autoload.php";

// Solo permitir solicitudes desde localhost (seguridad)
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
    http_response_code(403);
    exit('Acceso denegado');
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

// Leer JSON
$input = json_decode(file_get_contents('php://input'), true);
$modulo = $input['modulo_interno'] ?? '';

if ($modulo !== 'actualizar_estado_online') {
    http_response_code(400);
    exit('Módulo no válido');
}

// Validar token
$token = $input['token'] ?? '';
if (empty($token)) {
    http_response_code(401);
    exit('Token no proporcionado');
}

// Cargar controlador y ejecutar
require_once '../controllers/contactsController.php';
use app\controllers\contactsController;
$controller = new contactsController();
$result = $controller->updateUserStatusFromToken($token);

echo json_encode($result ?: ['success' => true]);
exit();