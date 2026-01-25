<?php
// zonas2AJAX.PHP
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
$modulo = $inputData['modulo_zonas'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro "modulo_zonas"']);
    exit();
}

// === 8. Cargar el controlador ===
require_once  '../controllers/zonasController.php';
use app\controllers\zonasController;

$controller = new zonasController();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'registrar_zonas':
        $controller->generarCuadriculaConroe();
        break;

    // 🔹 NUEVO: crear una zona a partir de límites y lista de direcciones
    case 'crear_zona':
        $lat_sw = $inputData['lat_sw'] ?? null;
        $lng_sw = $inputData['lng_sw'] ?? null;
        $lat_ne = $inputData['lat_ne'] ?? null;
        $lng_ne = $inputData['lng_ne'] ?? null;
        $ids_direcciones = $inputData['ids_direcciones'] ?? [];
        $nombre_zona = $inputData['nombre_zona'] ?? null;
        $id_ruta = $inputData['id_ruta'] ?? null;

        if ($lat_sw === null || $lng_sw === null || $lat_ne === null || $lng_ne === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Límites geográficos requeridos']);
            exit();
        }
        if (!is_array($ids_direcciones) || empty($ids_direcciones)) {
            http_response_code(400);
            echo json_encode(['error' => 'Debe seleccionar al menos una dirección']);
            exit();
        }

        try {
            $id_zona = $controller->crearZona(
                $lat_sw, $lng_sw, $lat_ne, $lng_ne,
                $ids_direcciones,
                $nombre_zona, $id_ruta
            );
            echo json_encode(['success' => true, 'id_zona' => $id_zona, 'message' => 'Zona creada exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'listar_direcciones_de_zona':
        $id_zona = $inputData['id_zona'] ?? null;
        if (!$id_zona) {
            http_response_code(400);
            echo json_encode(['error' => 'Zone ID required']);
            exit();
        }
        try {
            $direcciones = $controller->listarDireccionesDeZona($id_zona);
            echo json_encode(['success' => true, 'data' => $direcciones]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
                
    case 'eliminar_direccion_de_zona':
        $id_zona = $inputData['id_zona'] ?? null;
        $id_direccion = $inputData['id_direccion'] ?? null;
        if (!$id_zona || !$id_direccion) {
            http_response_code(400);
            echo json_encode(['error' => 'Zone and address ID required']);
            exit();
        }
        try {
            $controller->eliminarDireccionDeZona($id_zona, $id_direccion);
            echo json_encode(['success' => true, 'message' => 'Address removed']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Módulo no válido: ' . $modulo]);
        exit();
}

exit();
?>
