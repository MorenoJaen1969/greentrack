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
$modulo = $inputData['modulo_rutas'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'The parameter is missing. "modulo_rutas"']);
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
            echo json_encode(['error' => 'The parameter is missing. "id_ruta"']);
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

    case 'crear_ruta':
        $nombre_ruta = $inputData['nombre_ruta'] ?? null;
        $color_ruta = $inputData['color_ruta'] ?? '#000000';
        $zonas = $inputData['zonas'] ?? []; // NUEVO: array de zonas con direcciones

        if (!$nombre_ruta) {
            http_response_code(400);
            echo json_encode(['error' => 'The parameter is missing. "nombre_ruta"']);
            exit();
        }
        if (empty($zonas)) {
            http_response_code(400);
            echo json_encode(['error' => 'Debe seleccionar al menos una zona']);
            exit();
        }

        try {
            $id_ruta = $controller->crearRutaCompleta($nombre_ruta, $color_ruta, $zonas);
            echo json_encode([
                'success' => true,
                'message' => 'Ruta creada exitosamente',
                'id_ruta' => $id_ruta
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'actualizar_ruta_completa':
        $id_ruta = $inputData['id_ruta'] ?? null;
        $nombre_ruta = $inputData['nombre_ruta'] ?? null;
        $color_ruta = $inputData['color_ruta'] ?? null;
        $cambios = $inputData['cambios'] ?? null;

        if (!$id_ruta || !$nombre_ruta) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan parámetros requeridos']);
            exit();
        }

        try {
            $resultado = $controller->actualizarRutaCompleta($id_ruta, $nombre_ruta, $color_ruta, $cambios);
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Ruta actualizada exitosamente',
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'eliminar_ruta':
        $id_ruta = $inputData['id_ruta'] ?? null;
        if (!$id_ruta) {
            http_response_code(400);
            echo json_encode(['error' => 'The parameter is missing. "id_ruta"']);
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

    case 'obtener_direcciones_zona':
        // Para modal de selección: direcciones disponibles en una zona
        $id_zona = $inputData['id_zona'] ?? null;
        $id_ruta_actual = $inputData['id_ruta_actual'] ?? null; // Para excluir o marcar las ya en ruta

        if (!$id_zona) {
            http_response_code(400);
            echo json_encode(['error' => 'The parameter is missing. "id_zona"']);
            exit();
        }

        try {
            $direcciones = $controller->obtenerDireccionesZonaParaSeleccion($id_zona, $id_ruta_actual);
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $direcciones]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'listar_todas_direcciones_contrato':
        // Todas las direcciones con contrato activo, indicando ruta asignada
        try {
            $direcciones = $controller->listarTodasDireccionesConContrato();
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $direcciones]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'listar_direcciones_libres':
        // CORREGIDO: quitado el $ del nombre del campo
        $id_ruta_actual = $inputData['id_ruta_actual'] ?? null;
        try {
            // Usar el nombre real de la función en el controller
            $direcciones = $controller->direcciones_libres($id_ruta_actual);
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $direcciones]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'actualizar_orden_direcciones':
        $id_ruta = $inputData['id_ruta'] ?? null;
        $orden_direcciones = $inputData['orden_direcciones'] ?? [];

        if (!$id_ruta || !is_array($orden_direcciones)) {
            http_response_code(400);
            echo json_encode(['error' => 'Parámetros inválidos']);
            exit();
        }

        try {
            $controller->actualizarOrdenDirecciones($id_ruta, $orden_direcciones);
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Orden actualizado']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'listar_zonas_por_ciudad':
        $id_ciudad = $inputData['id_ciudad'] ?? null;
        if (!$id_ciudad) {
            http_response_code(400);
            echo json_encode(['error' => 'The parameter is missing. "id_ciudad"']);
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

    case 'crear_select':
        $id_ruta = $inputData['id_ruta'];

        $routes = $controller->consultar_rutas();
        $cadena = '';
        $cadena = '<option value="">Select a Route</option>';

        foreach ($routes as $curr) {
            $cadena = $cadena . '<option value="' . $curr['id_ruta'] . '" ';
            if ($id_ruta == $curr['id_ruta']) {
                $cadena = $cadena . 'selected> ';
            } else {
                $cadena = $cadena . '> ';
            }
            $cadena = $cadena . $curr['nombre_ruta'] . '</option>';
        }
        echo $cadena;
        break;

    case 'crear_select_dia':
        $id_ruta = $inputData['id_ruta'];
        $fecha_proceso = $inputData['fecha_proceso'];

        $resulta = $controller->consultar_rutas_dia($fecha_proceso);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'resulta' => $resulta
        ]);
        exit();

    case 'asignar_direccion_a_ruta':
        $id_ruta = $inputData['id_ruta'];
        $id_direccion = $inputData['id_direccion'];
        $id_zona = $inputData['id_zona'];

        try {
            $controller->asignarDireccionARuta($id_ruta, $id_direccion, $id_zona);
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();

    case 'guardar_cambios_batch':
        $id_ruta = $inputData['id_ruta'] ?? null;
        $nombre_ruta = $inputData['nombre_ruta'] ?? null;
        $color_ruta = $inputData['color_ruta'] ?? '#000000';
        $cambios = $inputData['cambios'] ?? null;

        if (!$id_ruta) {
            http_response_code(400);
            echo json_encode(['error' => 'The parameter is missing. "id_ruta"']);
            exit();
        }

        if (!is_array($cambios)) {
            http_response_code(400);
            echo json_encode(['error' => 'Parámetro "cambios" debe ser un array']);
            exit();
        }

        try {
            $resultado = $controller->guardarCambiosBatch($id_ruta, $nombre_ruta, $color_ruta, $cambios);
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Changes saved successfully',
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // rutas_mapaAjax.php
    case 'listar_direcciones_libres_con_contrato':
        try {
            $direcciones = $controller->listarDireccionesLibresConContrato();
            echo json_encode(['success' => true, 'data' => $direcciones]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();

    case 'obtener_clientes':
        $id_ruta = $inputData['id_ruta'] ?? null;
        if (!$id_ruta) {
            http_response_code(400);
            echo json_encode(['error' => 'The parameter is missing. "id_ruta"']);
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

    case 'actualizar_ruta':
        $id_ruta = $inputData['id_ruta'] ?? null;
        $nombre_ruta = $inputData['nombre_ruta'] ?? null;
        $color_ruta = $inputData['color_ruta'] ?? '#000000';
        $direcciones_ids = $inputData['direcciones_ids'] ?? [];

        if (!$id_ruta || !$nombre_ruta) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan los parámetros "id_ruta" o "nombre_ruta"']);
            exit();
        }
        if (!is_array($direcciones_ids) || empty($direcciones_ids)) {
            http_response_code(400);
            echo json_encode(['error' => 'Parámetro "direcciones_ids" es requerido y debe ser un array no vacío']);
            exit();
        }

        try {
            $id_ruta = $controller->actualizarRuta($id_ruta, $nombre_ruta, $color_ruta, $direcciones_ids);
            echo json_encode(['success' => true, 'message' => 'Ruta actualizada exitosamente', 'id_ruta' => $id_ruta]);
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
