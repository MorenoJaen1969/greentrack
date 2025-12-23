<?php
// usuarios2AJAX.PHP
ob_start(); // Iniciar buffer de salida

error_log("1 - Llego a usuariosAjax.php");
// === 1. Limpiar cualquier buffer existente ===
while (ob_get_level()) {
    ob_end_clean();
}

// === 2. Iniciar sesión ===
require_once "../views/inc/session_start.php";

// === 3. Cargar configuración y autoload ===
require_once "../../config/app.php";
require_once "../../autoload.php";

// === 4. Manejo de CORS ===
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// === 5. SILENCIAR ERRORES PARA NO CONTAMINAR JSON ===
error_log("2 - Antes de silenciar errores");
ini_set('display_errors', 0);
error_reporting(0);

// === 6. Preflight ===
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// === 7. Validar método ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

error_log("3 - Sin errores. Procede a armar consulta");

// === 8. Leer y decodificar JSON si viene en POST ===
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

error_log("4 - Procesado inputData: " . print_r($inputData, true));

// === 9. Procesar módulo ===
$modulo = $inputData['modulo_usuarios'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Falta el parámetro "modulo_usuarios"']);
    exit();
}

// === 10. Cargar el controlador ===
require_once '../controllers/usuariosController.php';
use app\controllers\usuariosController;

$controller = new usuariosController();

// === 11. Enrutar según el módulo ===
try {
    switch ($modulo) {
        case 'control_acceso':
            $username = $inputData['username'] ?? '';
            $password = $inputData['password'] ?? '';


            if (empty($username) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Usuario y contraseña requeridos']);
                exit();
            }

            $resultado = $controller->getUser($username, $password);

            if ($resultado == false) {
                echo json_encode(['success' => false, 'message' => 'Credenciales inválidas']);
                exit();
            } else {
                // ✅ Credenciales válidas - Iniciar sesión
                $_SESSION['user_valid'] = true;
                $_SESSION['user_name'] = $resultado['nombre'];
                $_SESSION['user_email'] = $resultado['email'];
                $_SESSION['token'] = $resultado['token'];
                $_SESSION['user_id'] = $resultado['id_user'];

                $data = [
                    'success' => true,
                    'message' => 'Autenticación exitosa',
                    'user' => [
                        'email' => $_SESSION['user_email'],
                        'nombre' => $_SESSION['user_name']
                    ]
                ];
                echo json_encode($data);
                exit();
            }

        case 'registrar_usuarios':
            $usuarios = $inputData['usuarios'];

            $controller->crear_registro($usuarios);
            break;

        case 'cerrar_parada':
            $id_parada = $inputData['id_parada'] ?? null;
            $vehicle_id = $inputData['vehicle_id'] ?? null;

            $controller->cerrar_parada($id_parada, $vehicle_id);
            break;

        case 'crear_select':
            $id_address_clas = $inputData['id_address_clas'];
            $id_usuarios = $inputData['id_usuarios'];

            $usuarios = $controller->consultar_usuarios($id_address_clas);
            $cadena = '';

            $cadena = '<option value="">Select a Address Type</option>';

            foreach ($usuarios as $curr) {
                $cadena = $cadena . '<option value="' . $curr['id_usuarios'] . '" ';
                if ($id_usuarios == $curr['id_usuarios']) {
                    $cadena = $cadena . 'selected> ';
                } else {
                    $cadena = $cadena . '> ';
                }
                $cadena = $cadena . $curr['usuarios'] . '</option>';
            }
            echo $cadena;
            break;

        case 'cerrar_sesion':
            // Limpiar cualquier salida previa
            if (ob_get_level()) ob_end_clean();

            // Asegurar que la sesión está activa
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Invalidar solo las claves del chat
            unset($_SESSION['user_valid']);
            unset($_SESSION['user_email']);
            unset($_SESSION['user_name']);
            unset($_SESSION['token']);
            unset($_SESSION['user_id']);

            // Regenerar ID para seguridad
            session_regenerate_id(true);

            // Forzar respuesta JSON limpia
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            echo json_encode(['success' => true]);
            exit(); // Asegura que no se imprime nada más                                    

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Módulo no válido: ' . $modulo]);
            exit();
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}

ob_end_clean(); // Limpiar cualquier salida previa
exit();
?>