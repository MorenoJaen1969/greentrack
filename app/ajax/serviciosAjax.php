<?php
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
$modulo = $inputData['modulo_servicios'] ?? '';

error_log("Situacion de modulo: " . json_encode($inputData));

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro "modulo_servicios"']);
    exit();
}

// === 8. Cargar el controlador ===
require_once  '../controllers/serviciosController.php';
use app\controllers\serviciosController;

$controller = new serviciosController();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'listar':
        $controller->listarServiciosConEstado();
        break;

    case 'listar_modal':
        $controller->listarServiciosParaModal();
        break;

    case 'finalizar_manual':
        $id = $inputData['id'] ?? null;
        if ($id) {
            $controller->finalizarServicio($id);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ID requerido']);
        }
        break;

    case 'historial_cliente':
        $id_cliente = $inputData['id_cliente'] ?? null;
        if ($id_cliente) {
            $controller->obtenerHistorialCliente($id_cliente);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'id_cliente es requerido']);
        }
        break;

    case 'actualizar_estado_con_historial':
        $id_servicio = $inputData['id_servicio'] ?? null;
        $estado = $inputData['estado'] ?? null;
        $notas = $inputData['notas'] ?? '';
        $estado_actual = $inputData['estado_actual'] ?? '';
        $cliente = $inputData['cliente'] ?? null;
        $truck = $inputData['truck'] ?? null;

        if (!$id_servicio || !$estado || !$cliente || !$truck) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan datos requeridos']);
            exit();
        }

        $controller->actualizarEstadoConHistorial($inputData);
        break;

    case 'obtener_servicio_detalle':
        $id_servicio = $inputData['id_servicio'] ?? null;
        if ($id_servicio) {
            $controller->obtenerServicioDetalle($id_servicio);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ID de servicio requerido']);
        }
        break;
                
    case 'obtener_historial_servicio':
        $id_cliente = $inputData['id_cliente'] ?? null;
        if ($id_cliente) {
            $controller->obtenerHistorialServicio($id_cliente);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ID de Cliente requerido para historial']);
        }
        break;


    case 'actualizar_estado':
        $id_servicio = $inputData['id_servicio'] ?? null;
        $nuevo_estado = $inputData['estado'] ?? null;
        $notas = $inputData['notas'] ?? '';

        if (!$id_servicio || !$nuevo_estado) {
            http_response_code(400);
            echo json_encode(['error' => 'id_servicio y estado son requeridos']);
            exit();
        }

        $controller->actualizarEstadoServicio($id_servicio, $nuevo_estado, $notas);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Módulo no válido: ' . $modulo]);
        exit();
}

exit();
?>