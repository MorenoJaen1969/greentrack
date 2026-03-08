<?php
// locationTypesAJAX.PHP
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
    echo json_encode(['error' => 'Method not permitted']);
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
        echo json_encode(['error' => 'Invalid JSON']);
        exit();
    }
} else {
    $inputData = $_POST;
}

// === 7. Procesar módulo ===
$modulo = $inputData['modulo_location'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'The parameter is missing. "modulo_location"']);
    exit();
}

// === 8. Cargar el controlador ===
require_once  '../controllers/locationTypesController.php';

use app\controllers\locationTypesController;

$controller = new locationTypesController();

switch ($modulo) {
    case 'listar_select':
        echo json_encode($controller->consultar_location_types());
        break;

    case 'data_direcciones':
        $locationType = $controller->getlocationType();

        // Limpia cualquier salida previa (espacios o errores)
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        if (is_array($locationType)) {
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $locationType]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Query error: ' . $locationType]);
        }
        exit();

    case 'registrar':
        // Lógica de registro para el CRUD futuro
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid module: ' . $modulo]);
        exit();
}

exit();
