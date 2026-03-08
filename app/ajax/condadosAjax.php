<?php
// condados2AJAX.PHP
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
$modulo = $inputData['modulo_condados'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'The parameter is missing. "modulo_condados"']);
    exit();
}

// === 8. Cargar el controlador === 
require_once  '../controllers/condadosController.php';

use app\controllers\condadosController;

$controller = new condadosController();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'crear_select':
        $id_estado = $inputData['id_estado'];
        $id_condado = $inputData['id_condado'];

        $condados = $controller->consultar_condados($id_estado);
        $cadena = '';

        $cadena = '<option value="">Select a State</option>';

        foreach ($condados as $curr) {
            $cadena = $cadena . '<option value="' . $curr['id_condado'] . '" ';
            if ($id_condado == $curr['id_condado']) {
                $cadena = $cadena . 'selected> ';
            } else {
                $cadena = $cadena . '> ';
            }
            $cadena = $cadena . $curr['nombre'] . '</option>';
        }
        echo $cadena;
        break;

    case 'data_direcciones':
        $id_estado = $inputData['id_estado'];
        $condados = $controller->getCondados($id_estado);

        if (is_array($condados)) {
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $condados]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Query error: ' . $condados]);
        }
        exit();

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid module: ' . $modulo]);
        exit();
}

exit();
