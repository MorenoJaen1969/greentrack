<?php
// MOTOR2AJAX.PHP
// === 1. Iniciar buffer y sesión (lo primero) ===
ob_start();
require_once "../views/inc/session_start.php";

// === 2. Cargar configuración y autoload ===
require_once "../../config/app.php";
require_once "../../autoload.php";

// === 3. Manejo de CORS ===
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// === 4. Preflight ===
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// === 5. Validar método ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// === 6. Leer y decodificar JSON si viene en POST ===
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$inputData = [];

if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents("php://input");
    $jsonInput = json_decode($rawInput, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonInput)) {
        $inputData = $jsonInput;
    } else {
        error_log("JSON malformado o no decodificado: " . $rawInput);
        http_response_code(400);
        echo json_encode(['error' => 'JSON inválido']);
        exit();
    }
} else {
    $inputData = $_POST;
}

// === 7. Procesar módulo ===
$modulo = $inputData['modulo_motor2'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro "modulo_motor2"']);
    exit();
}

// === 8. Cargar el controlador ===
require_once  '../controllers/motor2Controller.php';
use app\controllers\motor2Controller;

$controller = new motor2Controller();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'obtener_gps_verizon':
        $vehiculo_id = $inputData['vehicle_id'] ?? '';
        
        if ($vehiculo_id === '') {
            http_response_code(400);
            echo json_encode(['error' => 'id_vehiculo es requeridos']);
            exit();
        }
        
        $controller->obtenerGpsVerizon($vehiculo_id);
        break;

    case 'obtener_historico_verizon':
        $vehicle_id = $inputData['vehicle_id'] ?? '';
        $from_time = $inputData['from_time'] ?? null;
        $to_time = $inputData['to_time'] ?? null;

        if (!$vehicle_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta vehicle_id']);
            break;
        }

        $controller->obtenerHistoricoVerizon($vehicle_id, $from_time, $to_time);
        echo json_encode(['success' => true, 'message' => 'Histórico procesado']);
        break;        

    case 'obtener_historico_bd':
        $vehicle_id = $inputData['vehicle_id'] ?? '';
        $from_time = $inputData['from_time'] ?? null;
        $to_time = $inputData['to_time'] ?? null;

        if (!$vehicle_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta vehicle_id']);
            break;
        }

        $controller->obtenerHistorialGPS_bd($vehicle_id, $from_time, $to_time);
        break;        

    case 'obtener_trucks_activos_hoy':
        $trucks = $controller->obtenerTrucksActivosHoy();
        
        echo json_encode([
            'success' => true,
            'trucks' => $trucks,
            'count' => count($trucks)
        ]);
        break;                
     
    case 'obtener_trucks_activos_hoy_color':
        $fecha_proc = $inputData['fecha_proc'];
        $trucks = $controller->obtenerTrucksActivosHoy_Color($fecha_proc);

        echo json_encode([
            'success' => true,
            'trucks' => $trucks,
            'count' => count($trucks)
        ]);
        break;                

    case 'obtener_historial_gps':
        $vehicle_id = $inputData['vehicle_id'] ?? '';
        
        if (!$vehicle_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta vehicle_id']);
            break;
        }

        $controller->obtenerHistorialGPS($vehicle_id);
        break;

    case 'obtener_ultima_posicion':
        $vehicle_id = $_POST['vehicle_id'] ?? null;
        if (!$vehicle_id) {
            echo json_encode(['error' => 'ID requerido']);
            break;
        }
        $controller->obtenerUP($vehicle_id);
        break; 

    case 'obtener_ultimo_historial':
        $vehicle_id = $_POST['vehicle_id'] ?? '';
        $limit = filter_var($_POST['limit'] ?? 1, FILTER_VALIDATE_INT);

        if (!$vehicle_id) {
            echo json_encode(['historial' => []]);
            exit();
        }

        $controller->obtener_UH($vehicle_id, $limit);
        break;

    case 'obtener_ultimo_punto_truck':
        $truck = trim($inputData['truck'] ?? '');
        
        if (empty($truck)) {
            echo json_encode(['error' => 'Truck ID required']);
            exit;
        }

        $controller->ultima_pos_truck($truck);
        break;
            
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Módulo no válido: ' . $modulo]);
        exit();
}

exit();
?>
