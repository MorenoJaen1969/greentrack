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

// Verificar BOM o caracteres extraños
$hex_input = bin2hex(substr($input, 0, 10));
$has_bom = (substr($hex_input, 0, 6) === 'efbbbf');

$raw_log_entry = 
    "[" . date('Y-m-d H:i:s') . "] IP: " . $_SERVER['REMOTE_ADDR'] . "\n" .
    "BOM: " . ($has_bom ? 'SÍ (EF BB BF)' : 'NO') . "\n" .
    "Longitud: " . strlen($input) . " bytes\n" .
    "Hex iniciales: $hex_input\n" .
    "Contenido:\n" . 
    $input . "\n" . 
    str_repeat("-", 80) . "\n";

file_put_contents($log_raw_file, $raw_log_entry, FILE_APPEND | LOCK_EX);
// === FIN LOG CRUDO ===

// === Eliminar BOM UTF-8 si existe ===
$input = preg_replace('/^\xEF\xBB\xBF/', '', $input);
// ===

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

// Validar campos obligatorios
if (!isset($data['fecha_servicio']) || !isset($data['servicios'])) {
    http_response_code(400);
    echo json_encode(['error' => 'fecha_servicio y servicios son obligatorios']);
    exit;
}

$fecha_servicio = $data['fecha_servicio'];
$servicios = $data['servicios'];

// Validar formato de fecha
if (!DateTime::createFromFormat('Y-m-d', $fecha_servicio)) {
    http_response_code(400);
    echo json_encode(['error' => 'fecha_servicio debe estar en formato YYYY-MM-DD']);
    exit;
}

// === DELEGAR AL CONTROLADOR OFICIAL ===
try {
    $controller = new serviciosController();
    $resultado = $controller->procesarServiciosDesdeMotor1($fecha_servicio, $servicios);

    http_response_code(200);
    echo json_encode($resultado, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("Error en servicios_dia.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}






