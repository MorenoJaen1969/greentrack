<?php
// ciudades2AJAX.PHP
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
$modulo = $inputData['modulo_ciudades'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro "modulo_ciudades"']);
    exit();
}

// === 8. Cargar el controlador ===
require_once  '../controllers/ciudadesController.php';
use app\controllers\ciudadesController;

$controller = new ciudadesController();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'crear_select':
        $id_condado = $inputData['id_condado'];
        $id_ciudad = $inputData['id_ciudad'];

        $ciudades = $controller->consultar_ciudades($id_condado);
        $cadena='';

        $cadena = '<option value="">Select a City</option>';

        foreach ($ciudades as $curr){
            $cadena = $cadena. '<option value="' . $curr['id_ciudad'] . '" ';
            if($id_ciudad == $curr['id_ciudad']){ 
                $cadena = $cadena. 'selected> ';
            }else{
                $cadena = $cadena. '> ';
            }
            $cadena = $cadena. $curr['nombre'] . '</option>';
        }
        echo $cadena;
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Módulo no válido: ' . $modulo]);
        exit();
}

exit();
?>
