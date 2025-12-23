<?php
// === 1. Iniciar buffer y sesión (lo primero) ===
ob_start();
require_once "../views/inc/session_start.php";

// === 2. Cargar configuración y autoload ===
require_once "../../config/app.php";
require_once "../../autoload.php";
require_once "../../config/controllers.php";

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

// === 5. Procesar módulo ===
$modulo = $inputData['modulo_buscado'] ?? '';

$accion = $inputData['parametros'];

if ($accion['accion'] == "Con Filtro") {
    $datos = $inputData['datos'];

    $origen = $datos['origen'];
    $registrosPorPagina = $datos['registrosPorPagina'];
    $url = $datos['url'];
    $buscado = $datos['buscado'];
    $ruta_retorno = $datos['ruta_retorno'];
    $orden = $datos['orden'];
    $direccion = $datos['direccion'];

    $_SESSION['filtro'] = $buscado;
    $_SESSION['origen'] = $origen;

    $param_datos = [
        1,
        $registrosPorPagina,
        $url,
        $buscado,
        $ruta_retorno,
        $orden,
        $direccion
    ];

    switch ($modulo) {
        case 'address_clas':
            echo $address_clas->listaraddress_clasControlador($param_datos);
            break;

        case 'address_type':
            echo $address_type->listaraddress_typeControlador($param_datos);
            break;

        case 'clientes':
            echo $clientes->listarclientesControlador($param_datos);
            break;

        case 'contratos':
            echo $contratos->listarcontratosControlador($param_datos);
            break;

        case 'datosgenerales':
            break;

        case 'direcciones':
            echo $direcciones->listardireccionesControlador($param_datos);
            break;
            
        case 'proveedores':
            echo $proveedores->listarproveedoresControlador($param_datos);
            break;
    }

} else {
    $datos = $inputData['datos'];

    $_SESSION['filtro'] = "";
    $_SESSION['origen'] = "";

    // === Parámetros para recargar SIN filtro ===
    $origen = $datos['origen'];
    $registrosPorPagina = $datos['registrosPorPagina'];
    $url = $datos['url'];
    $buscado = $datos['buscado'];
    $ruta_retorno = $datos['ruta_retorno'];
    $orden = $datos['orden'];
    $direccion = $datos['direccion'];

    $param_datos = [
        1, // página
        $registrosPorPagina,
        $url,
        $buscado,
        $ruta_retorno,
        $orden,
        $direccion
    ];

    switch ($origen) {
        case 'address_clas':
            echo $address_clas->listaraddress_clasControlador($param_datos);
            break;
        case 'address_type':
            echo $address_type->listaraddress_typeControlador($param_datos);
            break;
        case 'clientes':
            echo $clientes->listarclientesControlador($param_datos);
            break;
        case 'contratos':
            echo $contratos->listarcontratosControlador($param_datos);
            break;
        case 'direcciones':
            echo $direcciones->listardireccionesControlador($param_datos);
            break;
        case 'proveedores':
            echo $proveedores->listarproveedoresControlador($param_datos);
            break;
    }
}