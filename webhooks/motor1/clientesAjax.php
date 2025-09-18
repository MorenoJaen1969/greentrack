
<?php
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
$telefono_cliente = $data['telefono'] ?? '';
$email_cliente = $data['email'] ?? '';
$direccion_cliente = $data['direccion'] ?? '';
$latitud = $data['latitud'] ?? '';
$longitud = $data['longitud'] ?? '';    

$params = [
    'id_cliente' => $id_cliente,
    'nombre_cliente' => $nombre_cliente,
    'telefono_cliente' => $telefono_cliente,
    'email_cliente' => $email_cliente,
    'direccion_cliente' => $direccion_cliente,
    'latitud' => $latitud,
    'longitud' => $longitud
];

// === DELEGAR AL CONTROLADOR OFICIAL ===
try {
    if (ob_get_level()) {
        ob_clean(); // Limpia el búfer actual sin enviarlo
    }    
    $controller = new serviciosController();
    error_log("Registrando cliente: " . print_r($params, true));
    
    $resultado = $controller->procesarClientesDesdeMotor1($params);
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json');
    } else {
        error_log("Advertencia: Algunos encabezados ya fueron enviados antes de la respuesta JSON.");
        // O manejar el error
    }

    // Enviar la respuesta JSON limpia
    echo json_encode($resultado, JSON_PRETTY_PRINT);
    // Asegurarse de que no haya más salida después
    exit; // Salir inmediatamente después de enviar el JSON

} catch (Exception $e) {
    // Limpiar búfer también en caso de error
    if (ob_get_level()) {
        ob_clean();
    }
    error_log("Error en clientesAjax.php: " . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
    exit;
}

