<?php
// dia_semana2AJAX.PHP
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
$modulo = $inputData['modulo_dia_semana'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro "modulo_dia_semana"']);
    exit();
}

// === 8. Cargar el controlador ===
require_once  '../controllers/dia_semanaController.php';
use app\controllers\dia_semanaController;

$controller = new dia_semanaController();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'registrar_dia_semana':
        $dia_semana = $inputData['dia_semana'];

        $controller->crear_registro($dia_semana);
        break;

    case 'crear_select':
        $id_dia_semana = $inputData['id_dia_semana'];

        $dia_semana = $controller->consultar_dia_semana();
        $cadena='';

        $cadena = '<option value="">Select a Day Work</option>';

        foreach ($dia_semana as $curr){
            $cadena = $cadena. '<option value="' . $curr['id_dia_semana'] . '" ';
            if($id_dia_semana == $curr['id_dia_semana']){ 
                $cadena = $cadena. 'selected> ';
            }else{
                $cadena = $cadena. '> ';
            }
            $cadena = $cadena. $curr['dia_ingles'] . '</option>';
        }
        echo $cadena;
        break;
    
    case 'crear_select_two_d':
        $secondary_day = $inputData['secondary_day'];

        $dia_semana = $controller->consultar_dia_semana();
        $cadena='';

        $cadena = '<option value="">Select a Day Work</option>';

        foreach ($dia_semana as $curr){
            $cadena = $cadena. '<option value="' . $curr['id_dia_semana'] . '" ';
            if($secondary_day == $curr['id_dia_semana']){ 
                $cadena = $cadena. 'selected> ';
            }else{
                $cadena = $cadena. '> ';
            }
            $cadena = $cadena. $curr['dia_ingles'] . '</option>';
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
