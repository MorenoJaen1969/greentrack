<?php
// route_dayAJAX.PHP
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
$modulo = $inputData['modulo_route_day'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro "modulo_route_day"']);
    exit();
}

// === 8. Cargar el controlador ===
require_once  '../controllers/route_dayController.php';
use app\controllers\route_dayController;

$controller = new route_dayController();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'rutas_disponibles':
        $day = $inputData['current_day'];

        $resultado = $controller->rutas_disponibles($day);
        echo $resultado;
        break;

    case 'cargar_clientes_ruta':
        $day = $inputData['day'] ?? null;
        $id_ruta = $inputData['id_ruta'] ?? [];

        $resultado = $controller->cliente_en_rutas($day, $id_ruta);
        echo $resultado;
        break;

    case 'guardar':
        $day = $inputData['day'] ?? null;
        $route_ids = $inputData['route_ids'] ?? [];

        if (!$day || !in_array($day, ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid day']);
            exit;
        }

        $resultado = $controller->guardar_asignacion_dia($day, $route_ids);

        if (!$resultado){
            echo json_encode(['success' => false, 'message' => 'Database error']);
        } else {
            echo json_encode(['success' => true]);
        }
        break;

    case 'cargar_asignaciones':
        $resultado = $controller->cargar_zonas();

        echo json_encode($resultado);
        exit;

    default:
        http_response_code(response_code: 400);
        echo json_encode(['error' => 'Módulo no válido: ' . $modulo]);
        exit();
}

exit();
?>