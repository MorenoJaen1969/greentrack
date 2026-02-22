<?php
// SERVICIOSAJAX.PHP
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
$modulo = $inputData['modulo_DG'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro "datos_generales"']);
    exit();
}

// === 8. Cargar el controlador ===
require_once  '../controllers/datosgeneralesController.php';
use app\controllers\datosgeneralesController;

$controller = new datosgeneralesController();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'obtener_clave':
        $clave = $inputData['clave'] ?? null;
        if ($clave) {
            $controller->obtener_Clave($clave);  
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'La clave es requerida']);
        }
        break;

    case 'datos_para_gps':
        $controller->datos_para_gps();  
        break;

    case 'verificar_acceso':
        $result = $controller->verificarAccesoParametros();
        http_response_code($result['success'] ? 200 : 401);
        echo json_encode($result);
        break;
        
    case 'autenticar':
        $result = $controller->autenticarParametros($inputData);
        http_response_code($result['success'] ? 200 : 401);
        echo json_encode($result);
        break;
        
    case 'cerrar_sesion':
        $result = $controller->cerrarSesionParametros();
        echo json_encode($result);
        break;
        
    case 'set_redirect_url':
        $url = $inputData['url'] ?? '';
        $result = $controller->setRedirectUrl($url);
        echo json_encode($result);
        break;
        
    case 'clear_redirect_url':
        $result = $controller->clearRedirectUrl();
        echo json_encode($result);
        break;
        
    case 'es_url_protegida':
        $url = $inputData['url'] ?? '';
        $result = [
            'success' => true,
            'protegida' => $controller->esUrlProtegida($url),
            'nivel_seguridad' => $controller->obtenerNivelSeguridad($url)
        ];
        echo json_encode($result);
        break;
        
    case 'obtener_procesos_protegidos':
        $result = $controller->obtenerProcesosProtegidos();
        http_response_code(200);
        echo json_encode(['success' => true, 'procesos' => $result]);
        break;
        
    // Configuration methods
    case 'obtener_configuracion':
        $result = $controller->obtenerConfiguracion();
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
        break;
        
    case 'guardar_configuracion':
        $result = $controller->guardarConfiguracion($inputData);
        http_response_code($result['success'] ? 200 : 400);
        echo json_encode($result);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid module: ' . $modulo]);
        break;
}

exit();
?>
