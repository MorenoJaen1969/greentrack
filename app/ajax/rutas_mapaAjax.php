<?php
// rutasAjax.php
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
$modulo = $inputData['modulo_rutas'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro "modulo_rutas"']);
    exit();
}

// === 8. Cargar el controlador ===
require_once  '../controllers/rutas_mapaController.php';
use app\controllers\rutas_mapaController;

$controller = new rutas_mapaController();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'listar_rutas':
        try {
            $rutas = $controller->listarRutas();
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $rutas]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'obtener_ruta':
        $id_ruta = $inputData['id_ruta'] ?? null;
        if (!$id_ruta) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta el parámetro "id_ruta"']);
            exit();
        }
        try {
            $ruta = $controller->obtenerRutaConZonas($id_ruta);
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $ruta]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'obtener_clientes':
        $id_ruta = $inputData['id_ruta'] ?? null;
        if (!$id_ruta) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta el parámetro "id_ruta"']);
            exit();
        }
        try {
            $ruta = $controller->obtenerRutaConZonas_d($id_ruta);
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $ruta]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'crear_ruta':
        $nombre_ruta = $inputData['nombre_ruta'] ?? null;
        $color_ruta = $inputData['color_ruta'] ?? '#000000';
        $zonas_ids = $inputData['zonas_ids'] ?? [];

        if (!$nombre_ruta) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta el parámetro "nombre_ruta"']);
            exit();
        }
        if (!is_array($zonas_ids) || empty($zonas_ids)) {
            http_response_code(400);
            echo json_encode(['error' => 'Parámetro "zonas_ids" es requerido y debe ser un array no vacío']);
            exit();
        }

        try {
            $id_ruta = $controller->crearRuta($nombre_ruta, $color_ruta, $zonas_ids);
            echo json_encode(['success' => true, 'message' => 'Ruta creada exitosamente', 'id_ruta' => $id_ruta]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'actualizar_ruta':
        $id_ruta = $inputData['id_ruta'] ?? null;
        $nombre_ruta = $inputData['nombre_ruta'] ?? null;
        $color_ruta = $inputData['color_ruta'] ?? '#000000';
        $zonas_ids = $inputData['zonas_ids'] ?? [];

        if (!$id_ruta || !$nombre_ruta) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan los parámetros "id_ruta" o "nombre_ruta"']);
            exit();
        }
        if (!is_array($zonas_ids) || empty($zonas_ids)) {
            http_response_code(400);
            echo json_encode(['error' => 'Parámetro "zonas_ids" es requerido y debe ser un array no vacío']);
            exit();
        }

        try {
            $id_ruta = $controller->actualizarRuta($id_ruta, $nombre_ruta, $color_ruta, $zonas_ids);
            echo json_encode(['success' => true, 'message' => 'Ruta actualizada exitosamente', 'id_ruta' => $id_ruta]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'eliminar_ruta':
        $id_ruta = $inputData['id_ruta'] ?? null;
        if (!$id_ruta) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta el parámetro "id_ruta"']);
            exit();
        }
        try {
            $controller->eliminarRuta($id_ruta);
            echo json_encode(['success' => true, 'message' => 'Ruta eliminada (inactivada) exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'listar_zonas_por_ciudad':
        $id_ciudad = $inputData['id_ciudad'] ?? null;
        if (!$id_ciudad) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta el parámetro "id_ciudad"']);
            exit();
        }
        try {
            $zonas = $controller->listarZonasPorCiudad($id_ciudad);
            echo json_encode(['success' => true, 'data' => $zonas]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'listar_todas_zonas':
        try {
            $zonas = $controller->listarTodasZonas();
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $zonas]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'listar_direcciones_por_zonas':
        $zonas_ids = $inputData['zonas_ids'] ?? [];
        if (!is_array($zonas_ids) || empty($zonas_ids)) {
            http_response_code(400);
            echo json_encode(['error' => 'Parámetro "zonas_ids" es requerido y debe ser un array no vacío']);
            exit();
        }
        try {
            $direcciones = $controller->listarDireccionesPorZonas($zonas_ids);
            echo json_encode(['success' => true, 'data' => $direcciones]);
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