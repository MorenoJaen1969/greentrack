<?php
// clientes2AJAX.PHP
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
$modulo = $inputData['modulo_clientes'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'The parameter is missing. "modulo_clientes"']);
    exit();
}

// === 8. Cargar el controlador ===
require_once  '../controllers/clientesController.php';

use app\controllers\clientesController;

$controller = new clientesController();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'actualizar_direccion':
        $id_direccion = $inputData['id_direccion'];
        $direccion = $inputData['direccion'];
        $lat = $inputData['lat'];
        $lng = $inputData['lng'];
        $cambio = $inputData['cambio'];

        //        $controller->actualizar_direccion($id_direccion, $direccion, $lat, $lng, $cambio); 
        break;

    case 'eliminar':
        $id_direccion = $inputData['id_direccion'];

        //        $controller->eliminar_direccion($id_direccion);
        break;

    case 'listar_tabla':
        $pagina = $inputData['pagina'] ?? 1;
        $registros_por_pagina = $inputData['registros_por_pagina'] ?? 10;
        $url_origen = $inputData['url_origen'] ?? 'Customers';
        $busca_frase = $inputData['busca_frase'] ?? '';
        $filtro = $inputData['filtro_estado'] ?? 'todos';

        if ($filtro == 'activos') {
            $filtro_estado = 1;
        } else if ($filtro == 'inactivos') {
            $filtro_estado = 2;
        } else {
            $filtro_estado = null;
        }

        // Llamar a tu método existente
        $dato_ori = [
            $pagina,
            $registros_por_pagina,
            $url_origen,
            $busca_frase,
            '', // ruta_retorno (no usado aquí)
            '', // orden
            '', // direccion
            $filtro_estado
        ];

        $tabla_html = $controller->listarclientesControlador($dato_ori);
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

        $_SESSION['nav_clientes'] = [
            'pagina_clientes' => 1,
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

        $tabla_html = $controller->listarclientesControlador($dato_ori);
        echo $tabla_html; // Solo el HTML de la tabla + paginación
        break;

    case 'crear_select':
        $id_cliente = $inputData['id_cliente'];

        $clientes = $controller->consultar_clientes();
        $cadena = '';

        $cadena = '<option value="">Select a Customer</option>';

        foreach ($clientes as $curr) {
            $cadena = $cadena . '<option value="' . $curr['id_cliente'] . '" ';
            if ($id_cliente == $curr['id_cliente']) {
                $cadena = $cadena . 'selected> ';
            } else {
                $cadena = $cadena . '> ';
            }
            $cadena = $cadena . $curr['cliente'] . '</option>';
        }
        echo $cadena;
        break;

    case 'solo_datos':
        $filtro = $inputData['filtro'];

        $clientes = $controller->consultar_clientes_with_without($filtro);
        
        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $clientes]);
        exit();

    case 'obtener_contratos_cliente':
        $id_cliente = (int)$inputData['id_cliente'];

        $controller->consultar_clientes_contratos($id_cliente);
        break;

    case 'registrar_cliente':
        $data = $inputData;

        $foto = $_FILES['foto'] ?? null; // El archivo en sí

        $datos_guardar = [];

        $tipo_persona_act = $data['tipo_persona'];

        if ($tipo_persona_act == 'N') {
            $id_status = $data['id_status'];
            $cliente_foto = $data['cliente_foto'];
            $identification = $data['identification'];
            $nombre = $data['nombre'];
            $apellido = $data['apellido'];
            $email = $data['email'];
            $telefono = $data['telefono'];
            $telefono2 = $data['telefono2'];
            $id_tratamiento = $data['id_tratamiento'];
            $id_sexo = $data['id_sexo'];
            $website = '';
            $nombre_comercial = '';
            $sector_industrial = '';
            $id_tipo_persona = 1;
        } else {
            $id_status = $data['id_status'];
            $telefono = $data['juridica_telefono1'];
            $telefono2 = $data['juridica_telefono2'];
            $cliente_foto = $data['cliente_foto'];
            $identification = $data['juridica_identificacion'];
            $id_tratamiento = $data['id_tratamiento_jur'];
            $nombre = $data['nombre_jur'];
            $apellido = $data['apellido_jur'];
            $id_sexo = $data['id_sexo_jur'];
            $email = $data['juridica_email'];
            $website = $data['juridica_website'];
            $nombre_comercial = $data['juridica_nombre_empresa'];
            $sector_industrial = $data['juridica_sector'];
            $id_tipo_persona = 2;
        }

        $datos_guardar = [
            'id_status' => $id_status,
            'foto' => $foto,
            'cliente_foto' => $cliente_foto,
            'identification' => $identification,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email,
            'telefono' => $telefono,
            'telefono2' => $telefono2,
            'id_tratamiento' => $id_tratamiento,
            'id_sexo' => $id_sexo,
            'id_tipo_persona' => $id_tipo_persona,
            'website' => $website,
            'nombre_comercial' => $nombre_comercial,
            'sector_industrial' => $sector_industrial
        ];

        $controller->ingresarClientes($datos_guardar);
        break;

    case 'update_cliente':
        $data = $inputData;

        $foto = $_FILES['fileInput'] ?? null; // El archivo en sí




        // "modulo_clientes":"update_cliente",
        // "id_cliente":"",
        // "id_tratamiento":"1",
        // "nombre":"Mario",
        // "apellido":"Moreno",
        // "fecha_creacion":"2026-02-27",
        // "id_status":"1",
        // "telefono":"+58 (424) 736-1340",
        // "email":"mmoreno.internet@gmail.com",
        // "notas":"",
        // "observaciones":""}



        //             $id_status = $data['id_status'];
        //             $nombre = $data['nombre'];
        //             $apellido = $data['apellido'];
        //             $email = $data['email'];
        //             $telefono = $data['telefono'];
        //             $id_tratamiento = $data['id_tratamiento'];
        //             $id_sexo = $data['id_sexo'];

        //         $cliente_foto = $data['cliente_foto'];
        //         $identification = $data['identification'];
        //         $telefono2 = $data['telefono2'];
        //         $website = '';
        //         $nombre_comercial = '';
        //         $sector_industrial = '';
        //         $id_tipo_persona = 1;

        // "id_cliente":"",
        // "id_tratamiento":"1",
        // "nombre":"Mario",
        // "apellido":"Moreno",
        // "fecha_creacion":"2026-02-27",
        // "id_status":"1",
        // "telefono":"+58 (424) 736-1340",
        // "email":"mmoreno.internet@gmail.com",
        // "notas":"",
        // "observaciones":""



        //$ruta_img
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Todo bien']);
        exit();

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid module: ' . $modulo]);
        exit();
}
exit();
