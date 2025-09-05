<?php
// servicios_dia.php - Motor 1 (versión completa)
ob_start();

require_once '../../config/app.php';
if (!defined('APP_R_PROY')) {
    die('ERROR: APP_R_PROY no está definido');
}

require_once APP_R_PROY . 'app/models/mainModel.php';
require_once APP_R_PROY . 'app/controllers/serviciosController.php';

use app\models\mainModel;
use app\controllers\serviciosController;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

header('Content-Type: application/json');

$input = file_get_contents('php://input');

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'No data received']);
    exit;
}

// === LOG CRUDO DE ENTRADA ===
$log_raw_path = APP_R_PROY . 'app/logs/webhooks/motor1/';
$log_raw_file = $log_raw_path . 'raw_request_' . date('Y-m-d') . '.log';

// Crear directorio si no existe
if (!file_exists($log_raw_path)) {
    mkdir($log_raw_path, 0775, true);
    chgrp($log_raw_path, 'www-data');
    chmod($log_raw_path, 0775);
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

$id_cliente = $data['id_cliente'];
$nombre_cliente = $data['nombre_cliente'];

// === DELEGAR AL CONTROLADOR OFICIAL ===
try {
    $controller = new serviciosController();
    $resultado = $controller->procesarClientesDesdeMotor1($id_cliente, $nombre_cliente);

    http_response_code(200);
    echo json_encode($resultado, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("Error en servicios_dia.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

