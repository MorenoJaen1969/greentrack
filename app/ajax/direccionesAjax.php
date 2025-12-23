<?php
// direcciones2AJAX.PHP
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
$modulo = $inputData['modulo_direcciones'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro "modulo_direcciones"']);
    exit();
}

// === 8. Cargar el controlador ===
require_once  '../controllers/direccionesController.php';
use app\controllers\direccionesController;

$controller = new direccionesController();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'actualizar_direccion':
        $id_direccion = $inputData['id_direccion'];
        $direccion = $inputData['direccion'];
        $lat = $inputData['lat'];
        $lng = $inputData['lng'];
        $cambio = $inputData['cambio'];

        $controller->actualizar_direccion($id_direccion, $direccion, $lat, $lng, $cambio);
        break;
    
    case 'eliminar':
        $id_direccion = $inputData['id_direccion'];

        $controller->eliminar_direccion($id_direccion);
        break;
    
    case 'listar_tabla':
        $pagina = $inputData['pagina'] ?? 1;
        $registros_por_pagina = $inputData['registros_por_pagina'] ?? 10;
        $url_origen = $inputData['url_origen'] ?? 'direcciones';
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

        $tabla_html = $controller->listardireccionesControlador($dato_ori);
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

        $_SESSION['nav_direcciones'] = [
            'pagina_direcciones' => 1,
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

        $tabla_html = $controller->listardireccionesControlador($dato_ori);
        echo $tabla_html; // Solo el HTML de la tabla + paginación
        break;
    
    case 'update_direccion':
        // Extraer datos del formulario (FormData envía como $_POST)
        $id_direccion = $inputData['id_direccion'] ?? null;
        $direccion = $inputData['direccion'] ?? '';
        $lat = $inputData['lat'] ?? '';
        $lng = $inputData['lng'] ?? '';
        $id_pais = $inputData['id_pais'] ?? '';
        $id_estado = $inputData['id_estado'] ?? '';
        $id_condado = $inputData['id_condado'] ?? '';
        $id_ciudad = $inputData['id_ciudad'] ?? '';
        $id_zip = $inputData['id_zip'] ?? '';
        $id_address_clas = $inputData['id_address_clas'] ?? '';
        $id_cliente = $inputData['id_cliente'] ?? '';
        $id_address_type = $inputData['id_address_type'] ?? '';
        $id_status = $inputData['id_status'] ?? '';
        $notas = $inputData['notas'] ?? '';
        $observaciones = $inputData['observaciones'] ?? '';
        $id_geofence = $inputData['id_geofence'] ?? '';

        // Validar datos mínimos
        if (!$id_direccion) {
            throw new Exception("ID de dirección no proporcionado");
        }

        // Llamar al controlador
        $datos = [
            ['campo_nombre' => 'direccion', 'campo_marcador' => ':direccion', 'campo_valor' => $direccion],
            ['campo_nombre' => 'lat', 'campo_marcador' => ':lat', 'campo_valor' => $lat],
            ['campo_nombre' => 'lng', 'campo_marcador' => ':lng', 'campo_valor' => $lng],
            ['campo_nombre' => 'id_pais', 'campo_marcador' => ':id_pais', 'campo_valor' => $id_pais],
            ['campo_nombre' => 'id_estado', 'campo_marcador' => ':id_estado', 'campo_valor' => $id_estado],
            ['campo_nombre' => 'id_condado', 'campo_marcador' => ':id_condado', 'campo_valor' => $id_condado],
            ['campo_nombre' => 'id_ciudad', 'campo_marcador' => ':id_ciudad', 'campo_valor' => $id_ciudad],
            ['campo_nombre' => 'id_zip', 'campo_marcador' => ':id_zip', 'campo_valor' => $id_zip],
            ['campo_nombre' => 'id_address_clas', 'campo_marcador' => ':id_address_clas', 'campo_valor' => $id_address_clas],
            ['campo_nombre' => 'id_cliente', 'campo_marcador' => ':id_cliente', 'campo_valor' => $id_cliente],
            ['campo_nombre' => 'id_address_type', 'campo_marcador' => ':id_address_type', 'campo_valor' => $id_address_type],
            ['campo_nombre' => 'id_status', 'campo_marcador' => ':id_status', 'campo_valor' => $id_status],
            ['campo_nombre' => 'notas', 'campo_marcador' => ':notas', 'campo_valor' => $notas],
            ['campo_nombre' => 'observaciones', 'campo_marcador' => ':observaciones', 'campo_valor' => $observaciones],
            ['campo_nombre' => 'id_geofence', 'campo_marcador' => ':id_geofence', 'campo_valor' => $id_geofence]
        ];
        $condicion = [
            'condicion_campo' => 'id_direccion',
            'condicion_operador' => '=', 
            'condicion_marcador' => ':id_direccion',
            'condicion_valor' => $id_direccion
        ];

        $controller->guardarCambios('direcciones', $datos, $condicion);
        break;

    case 'guardar_geofence':
        $accion  = $inputData['accion'];
        if ($accion=='guardar'){
            $id_direccion = $inputData['id_direccion'] ?? null;
            $geofence_data = $inputData['geofence_data'] ?? '';

            if (!$id_direccion) {
                throw new Exception("ID de dirección no proporcionado");
            }

            // Si está vacío, usar null
            if ($geofence_data === '') {
                $geofence_value = null;
            } else {
                // Validar que sea JSON válido
                $decoded = json_decode($geofence_data, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("geofence_data no es JSON válido");
                }
                $geofence_value = $geofence_data;
            }

            // Validar que sea JSON válido
            $decoded = json_decode($geofence_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("geofence_data no es un JSON válido");
            }

            // Determinar tipo
            $tipo = isset($decoded['properties']['type']) && $decoded['properties']['type'] === 'circle' 
                ? 'circle' 
                : 'polygon';        

            $datos = [
                ['campo_nombre' => 'id_direccion', 'campo_marcador' => ':id_direccion', 'campo_valor' => $id_direccion],
                ['campo_nombre' => 'geofence_data', 'campo_marcador' => ':geofence_data', 'campo_valor' => $geofence_value],
                ['campo_nombre' => 'tipo', 'campo_marcador' => ':tipo', 'campo_valor' => $tipo]
            ];
            $condicion = [];
            $controller->guardarGeofence('geofence', $datos, $condicion, $id_direccion, 0, false);

        }else{
            if ($accion=='modificar'){
                $id_direccion = $inputData['id_direccion'];
                $id_geofence = $inputData['id_geofence'];
                $datos = [
                    ['campo_nombre' => 'id_geofence', 'campo_marcador' => ':id_geofence', 'campo_valor' => NULL]
                ];
                $condicion = [
                    'condicion_campo' => 'id_direccion',
                    'condicion_operador' => '=',
                    'condicion_marcador' => ':id_direccion',
                    'condicion_valor' => $id_direccion
                ];
                $controller->guardarGeofence('direcciones', $datos, $condicion, $id_direccion, $id_geofence, false);
            }
        }
        break;

    case 'obtener_direccion':
        $apikey = trim($inputData['apikey']);
        $direccion = trim($inputData['direccion']);

        $controller->obtenerGpsLocationIQSoloConsulta($apikey, $direccion);
        break;

    case 'obtener_codigos':
        $datos_geo = $inputData['datos_geo'];
        $lat_api = $inputData['lat'] ?? null;
        $lng_api = $inputData['lng'] ?? null;
        $controller->obtenerIdsGeograficos($datos_geo, $lat_api, $lng_api);
        break;

    case 'crear_select':
        $id_cliente = $inputData['id_cliente'];
        $id_direccion = $inputData['id_direccion'];
            
        $direcciones = $controller->consultar_direcciones($id_cliente);
        $cadena='';

        $cadena = '<option value="">Select a Address</option>';

        foreach ($direcciones as $curr){
            $cadena = $cadena. '<option value="' . $curr['id_direccion'] . '" ';
            if($id_direccion == $curr['id_direccion']){ 
                $cadena = $cadena. 'selected> ';
            }else{
                $cadena = $cadena. '> ';
            }
            $cadena = $cadena. $curr['direccion'] . '</option>';
        }
        echo $cadena;
        break;

    case 'listar_direcciones_con_coordenadas':
        $controller->consultar_direcciones_con_coordenadas();
        break;
                
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Módulo no válido: ' . $modulo]);
        exit();
}

exit();
?>
