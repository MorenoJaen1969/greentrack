<?php
// paradas2AJAX.PHP
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
$inputDataData = [];

if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents("php://input");
    $jsonInput = json_decode($rawInput, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonInput)) {
        $inputDataData = $jsonInput;
    } else {
        error_log("JSON malformado o no decodificado: " . $rawInput);
        http_response_code(400);
        echo json_encode(['error' => 'JSON inválido']);
        exit();
    }
} else {
    $inputDataData = $_POST;
}

// === 7. Procesar módulo ===
$modulo = $inputDataData['modulo_paradas'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro "modulo_paradas"']);
    exit();
}

// === 8. Cargar el controlador ===
require_once  '../controllers/paradasController.php';
use app\controllers\paradasController;

$controller = new paradasController();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'iniciar_parada':
        $vehicle_id = $inputData['vehicle_id'] ?? null;
        $lat = $inputData['lat'] ?? null;
        $lng = $inputData['lng'] ?? null;
        $hora_act = $inputData['hora_inicio'] ?? null;

        $controller->iniciar_parada($vehicle_id, $lat, $lng, $hora_act);
        break;

    case 'cerrar_parada':
        $id_parada = $inputData['id_parada'] ?? null;
        $vehicle_id = $inputData['vehicle_id'] ?? null;

        $controller->cerrar_parada($id_parada, $vehicle_id);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Módulo no válido: ' . $modulo]);
        exit();
}

exit();
?>
