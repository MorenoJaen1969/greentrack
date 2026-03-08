<?php
// contratos2AJAX.PHP
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
$modulo = $inputData['modulo_contratos'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'The parameter is missing. "modulo_contratos"']);
    exit();
}

// === 8. Cargar el controlador === 
require_once  '../controllers/contratosController.php';

use app\controllers\contratosController;

$controller = new contratosController();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'distribucion_servicios':
        $id_contrato = $inputData['id_contrato'];

        $controller->cargar_distribucion($id_contrato);
        break;

    case 'update_contrato':
        $controller->guardarCambios($inputData);
        break;

    case 'ceros':
        $valor = $inputData['valor'];
        $controller->completa0($valor);
        break;

    case 'registrar_contrato':
        $data = $inputData;

        $foto = $_FILES['foto'] ?? null; // El archivo en sí

        $datos_guardar = [];

        $id_status = $data['id_status'];
        $contrato_foto = $data['contrato_foto'];
        $id_cliente = $data['id_cliente'];
        $id_direccion = $data['id_direccion'];
        $nom_contrato = $data['nom_contrato'];
        $tiempo_servicio = $data['tiempo_servicio'];
        $retraso_invierno = $data['retraso_invierno'];
        $id_area = $data['id_area'];
        $num_semanas = $data['num_semanas'];
        $costo = $data['costo'];
        $id_dia_semana = $data['id_dia_semana'];
        $secondary_day = $data['secondary_day'];
        $id_ruta = $data['id_ruta'];
        $fecha_ini = $data['fecha_ini'];
        $fecha_fin = $data['fecha_fin'];
        $id_frecuencia_servicio = $data['id_frecuencia_servicio'];
        $id_frecuencia_pago = $data['id_frecuencia_pago'];

        $datos_guardar = [
            'id_status' => $id_status,
            'contrato_foto' => $contrato_foto,
            'id_cliente' => $id_cliente,
            'id_direccion' => $id_direccion,
            'nombre' => $nom_contrato,
            'tiempo_servicio' => $tiempo_servicio,
            'retraso_invierno' => $retraso_invierno,
            'id_area' => $id_area,
            'num_semanas' => $num_semanas,
            'costo' => $costo,
            'id_dia_semana' => $id_dia_semana,
            'secondary_day' => $secondary_day,
            'id_ruta' => $id_ruta,
            'fecha_ini' => $fecha_ini,
            'fecha_fin' => $fecha_fin,
            'id_frecuencia_servicio' => $id_frecuencia_servicio,
            'id_frecuencia_pago' => $id_frecuencia_pago,
            'foto' => $foto
        ];

        $controller->ingresarContrato($datos_guardar);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid module: ' . $modulo]);
        exit();
}

exit();
