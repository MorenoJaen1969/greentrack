<?php
// index.php
// Punto de entrada principal del sistema

namespace app\controllers;

ob_start();

// === ENDPOINT DE VERIFICACIÓN DE SESIÓN PARA CHAT (PRIMERA COSA QUE SE EJECUTA) ===
if (isset($_GET['chat']) && $_GET['chat'] === '1' && isset($_GET['check'])) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');

    // Iniciar sesión si no está activa
    if (session_status() === PHP_SESSION_NONE) {
        require_once "config/app.php";
        require_once APP_R_PROY . 'app/views/inc/session_start.php';
    }

    // Asegurar que $_SESSION existe
    if (!isset($_SESSION)) {
        session_start();
    }

    // Validar sesión del chat de forma segura
    $valid = false;
    $userEmail = '';
    $userName = '';
    $userToken = '';
    $userId = 0;

    if (isset($_SESSION['user_valid']) && $_SESSION['user_valid'] === true) {
        $valid = true;
        $userEmail = $_SESSION['user_email'] ?? '';
        $userName = $_SESSION['user_name'] ?? '';
        $userToken = $_SESSION['token'] ?? '';
        $userId = $_SESSION['user_id'] ?? 0;
    }

    echo json_encode([
        'valid' => $valid,
        'userEmail' => $userEmail,
        'userName' => $userName,
        'userToken' => $userToken,
        'userId' => $userId
    ]);
    exit();
}

// === ENDPOINT DE ACCESO DIRECTO A CHAT (OPCIONAL: SOLO PARA DEBUG) ===
if (isset($_GET['chat']) && $_GET['chat'] === '1' && !isset($_GET['check'])) {
    // En producción, no permitir acceso directo al chat
    // Redirigir al inicio o mostrar error
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/'));
    exit();
}

// === Cargar configuración y autoload ===
date_default_timezone_set('America/Chicago');
require_once "config/app.php"; // Constantes
require_once "autoload.php";
require_once APP_R_PROY . 'app/views/inc/session_start.php';

// === Cargar parámetros de horario dinámicos ===

if (!isset($_SESSION['parametros_horario'])) {
    try {
        require_once APP_R_PROY . 'app/controllers/datosgeneralesController.php';
        $controller = new \app\controllers\datosgeneralesController();
        $_SESSION['parametros_horario'] = $controller->tiempos_de_actividad();
    } catch (\Exception $e) {
        // En caso de error, usar valores por defecto
        $_SESSION['parametros_horario'] = [
            'hora_cierre_sesion' => '18:30',
            'hora_fin_jornada'     => '18:00',
            'hora_inicio_jornada'  => '08:00'
        ];
    }
} else {
    if (is_null($_SESSION['parametros_horario']['hora_cierre_sesion']) && 
            is_null($_SESSION['parametros_horario']['hora_fin_jornada']) && 
            is_null($_SESSION['parametros_horario']['hora_inicio_jornada'])) {
        try {
            require_once APP_R_PROY . 'app/controllers/datosgeneralesController.php';
            $controller = new \app\controllers\datosgeneralesController();
            $_SESSION['parametros_horario'] = $controller->tiempos_de_actividad();
        } catch (\Exception $e) {
            // En caso de error, usar valores por defecto
            $_SESSION['parametros_horario'] = [
                'hora_cierre_sesion' => '18:30',
                'hora_fin_jornada'     => '18:00',
                'hora_inicio_jornada'  => '08:00'
            ];
        }
    }
}

// Definir constantes dinámicas (opcional, pero útil)
if (!defined('HORA_CIERRE_SESION')) {
    define('HORA_CIERRE_SESION', $_SESSION['parametros_horario']['hora_cierre_sesion']);
    define('HORA_FIN_JORNADA',     $_SESSION['parametros_horario']['hora_fin_jornada']);
    define('HORA_INICIO_JORNADA',  $_SESSION['parametros_horario']['hora_inicio_jornada']);
}

// === 1. Detectar si hay token en la URL ===
$token = $_GET['access_key'] ?? null;
$user_email = "";

if (isset($_GET['views'])) {
    $url = explode("/", $_GET['views']);
} else {
    $url = ["dashboard"];
}

$ruta_control = APP_R_PROY . "/app/views/inc/controles.php";
if (file_exists($ruta_control)) {
    include $ruta_control;
} else {
    include "./app/views/inc/controles.php";
}

?>
<!DOCTYPE html>
<html lang="EN">

<head>
    <?php
    require_once "./app/views/inc/head.php";
    ?>
</head>

<body>
    <?php
    require_once "./config/controllers.php";
    $vista = $viewsController->obtenerVistasControlador($url[0]);

    if ($token) {
        require_once APP_R_PROY . 'app/controllers/usuariosController.php';

        $controller = new usuariosController();

        $param = [
            'token' => $token
        ];

        $validacion = $controller->valida_usuario($param);
        // === 2. Lista de correos autorizados ===
        $usuarios_permitidos = [
            'adriana@sergioslandscape.com',
            'sergio@sergioslandscape.com',
            'oparra@mcka915.com',
            'morenojaen@gmail.com'
        ];

        //https://positron4tx.ddns.net:9990/index.php?access_key=bd3933852ea0edd0321937344f2c5a2dcc446213857230954ce3dd58ac4810be
        //https://positron4tx.ddns.net:9990/index.php?access_key=cfadd3fa951916768e9524d9e0091756f9aae75d35429c3bd4d14a06593a2d16
    
        //use app\controllers\usuariosController;
    
        if (!empty($validacion)) {
            $email = $validacion['email'];
            if (in_array($email, $usuarios_permitidos)) {
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $validacion['nombre'];
                $_SESSION['token'] = $validacion['token'];
                $_SESSION['user_valid'] = true;
                $_SESSION['user_id'] = $validacion['id'];

                $user_email = $email;
                // Redirigir al dashboard ejecutivo
                header("Location: /app/views/mobile-view.php");
                //require_once 'app/views/mobile-view.php';
                exit();
            }
        }

        // Si llega aquí, token inválido
        die('<h3 style="text-align:center; margin-top:50px;">🔑 Token inválido o expirado</h3>');
    } else {
        if ($vista !== "./app/views/content/dashboard-view.php") {
            ?>
            <header>
                <?php
                require_once "./app/views/inc/navbar.php";
                if ($vista !== "./app/views/content/chat/chat-view.php") {
                    require_once "./app/views/inc/chat.php";
                } else {
                    ?>
                    <div class='titulo-del-chat'>
                        Chat Adela
                    </div>
                    <?php
                }
                ?>
            </header>
            <?php
            if (is_array($vista)) {
                //error_log("Es un arreglo ".json_encode($vista));
            } else {
                require_once $vista;
            }
        } else {
            require_once "./app/views/inc/navbar.php";
            require_once "./app/views/inc/chat.php";
            require_once $vista;
        }
        require_once "./app/views/inc/script.php";
    }
    ?>
</body>

</html>