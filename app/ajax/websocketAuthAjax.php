<?php
// websocketAuthAjax.php
// Endpoint específico para autenticación WebSocket

ob_start();
require_once "../views/inc/session_start.php";

// === Cargar configuración y autoload ===
require_once "../../config/app.php";
require_once "../../autoload.php";

// === Manejo de CORS ===
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// === Preflight ===
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// === Validar método ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// === Leer JSON del input ===
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$inputData = [];

if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents("php://input");
    $inputData = json_decode($rawInput, true);
} else {
    $inputData = $_POST;
}

// === Obtener token ===
$token = $inputData['token'] ?? '';

if (!$token) {
    http_response_code(400);
    echo json_encode(['valido' => false, 'error' => 'Token no proporcionado']);
    exit();
}

// === Cargar controlador y validar ===
require_once '../controllers/usuariosController.php';
use app\controllers\usuariosController;

$controller = new usuariosController();

$param = ['token' => $token];
$validacion = $controller->valida_usuario($param);

// === Formatear respuesta para WebSocket ===
if (!empty($validacion)) {
    // Usuario válido - estructura esperada por WebSocket
    $response = [
        'valido' => true,
        'usuario' => [
            'id' => $validacion['id'] ?? 1, // Necesitaríamos agregar ID a tu tabla
            'nombre' => $validacion['nombre'],
            'email' => $validacion['email'],
            'token' => $validacion['token']
        ],
        'permisos_chat' => ['chat_interno'] // Por defecto
    ];
    
    // Aquí podrías agregar lógica para determinar permisos específicos
    // basado en el email o otros criterios
    
    http_response_code(200);
    echo json_encode($response);
} else {
    // Usuario inválido
    http_response_code(401);
    echo json_encode([
        'valido' => false,
        'error' => 'Token inválido o expirado'
    ]);
}

exit();
?>