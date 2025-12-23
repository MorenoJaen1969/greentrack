<?php
// vehiculos2AJAX.PHP
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
$modulo = $inputData['modulo_vehiculos'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro "modulo_vehiculos"']);
    exit();
}

// === 8. Cargar el controlador ===
require_once  '../controllers/vehiculosController.php';
use app\controllers\vehiculosController;

$controller = new vehiculosController();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'eliminar':
        $id_vehiculo = $inputData['id_vehiculo'];

        $controller->eliminar_vehiculo($id_vehiculo);
        break;
    
    case 'listar_tabla':
        $pagina = $inputData['pagina'] ?? 1;
        $registros_por_pagina = $inputData['registros_por_pagina'] ?? 10;
        $url_origen = $inputData['url_origen'] ?? 'vehiculos';
        $busca_frase = $inputData['busca_frase'] ?? '';

        // Llamar a tu método existente
        $dato_ori = [
            $pagina,
            $registros_por_pagina,
            $url_origen,
            $busca_frase,
            '', // ruta_retorno (no usado aquí)
            '', // orden
            ''  // direccion
        ];

        $tabla_html = $controller->listarvehiculosControlador($dato_ori);
        echo $tabla_html; // Solo el HTML de la tabla + paginación
        break;
    
    case 'cambio_cant_reg':
        $datos = $inputData['datos'];

        $origen = $datos['origen'];
        $registrosPorPagina = $datos['registrosPorPagina'];
        $url_origen = $datos['url'];
        $busca_frase = $datos['buscado'];
        $ruta_retorno = $datos['ruta_retorno'];
        $orden = $datos['orden'];
        $direccion = $datos['direccion'];

        $_SESSION['nav_vehiculos'] = [
            'pagina_vehiculos' => 1,
            'registrosPorPagina' => $registrosPorPagina 
        ];

        $dato_ori = [
            1,
            $registrosPorPagina,
            $url_origen,
            $busca_frase,
            $ruta_retorno,
            $orden,
            $direccion
        ];

        $tabla_html = $controller->listarvehiculosControlador($dato_ori);
        echo $tabla_html; // Solo el HTML de la tabla + paginación
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Módulo no válido: ' . $modulo]);
        exit();
}

exit();
?>
