<?php
// webhooks/verizon/gps.php

ob_start();

// 1. Cargar configuración principal
require_once '../../config/app.php';

// 2. Verificar que APP_R_PROY esté definido
if (!defined('APP_R_PROY')) {
    die('ERROR: APP_R_PROY no está definido');
}

// 3. Incluir el controlador específico para webhooks
require_once APP_R_PROY . 'app/controllers/webhookController.php';

use app\controllers\webhookController;

// 4. Configuración de CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// 5. Manejo de preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 6. Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// 7. Leer y decodificar JSON
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON malformado en gps.php: " . file_get_contents('php://input'));
    http_response_code(400);
    echo json_encode(['error' => 'JSON malformado']);
    exit();
}

// 8. Validar datos requeridos
$vehicle_id = $input['vehicleId'] ?? $input['vehicle_id'] ?? null;
$lat        = $input['latitude'] ?? null;
$lng        = $input['longitude'] ?? null;

if (!$vehicle_id || $lat === null || $lng === null) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Faltan datos críticos',
        'requeridos' => ['vehicleId', 'latitude', 'longitude'],
        'recibidos' => array_keys($input)
    ]);
    exit();
}

// 9. Guardar en gps_realtime usando el controlador
try {
    $controller = new webhookController();

    $datos = [
        ['campo_nombre' => 'vehicle_id',    'campo_marcador' => ':vehicle_id',    'campo_valor' => $vehicle_id],
        ['campo_nombre' => 'lat',           'campo_marcador' => ':lat',           'campo_valor' => $lat],
        ['campo_nombre' => 'lng',           'campo_marcador' => ':lng',           'campo_valor' => $lng],
        ['campo_nombre' => 'timestamp',     'campo_marcador' => ':timestamp',     'campo_valor' => date('Y-m-d H:i:s')],
        ['campo_nombre' => 'geofence_id',   'campo_marcador' => ':geofence_id',   'campo_valor' => $input['geofenceId'] ?? '']
    ];

    $controller->guardarGPS($datos);

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Coordenada guardada']);

} catch (Exception $e) {
    error_log("Error en gps.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}