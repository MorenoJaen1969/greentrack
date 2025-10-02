<?php
// index.php
// Punto de entrada principal del sistema

// Incluir configuración
namespace app\controllers;

ob_start();

// === Cargar configuración y autoload ===
require_once "config/app.php"; // Constantes
require_once "autoload.php";

require_once APP_R_PROY . 'app/views/inc/session_start.php';

// === 1. Detectar si hay token en la URL ===
$token = $_GET['access_key'] ?? null;

if ($token){
    require_once APP_R_PROY . 'app/controllers/usuariosController.php';
    
    $controller = new usuariosController();

    $param = [
        'token' => $token
    ];

    $validacion = $controller->valida_usuario($param);
    // === 2. Lista de correos autorizados ===
    $usuarios_permitidos = [
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
            // Redirigir al dashboard ejecutivo
            header("Location: /app/views/mobile-view.php");
            //require_once 'app/views/mobile-view.php';
            exit();
        }
    }

    // Si llega aquí, token inválido
    die('<h3 style="text-align:center; margin-top:50px;">🔑 Token inválido o expirado</h3>');
} else {
    // Determinar qué vista mostrar
    $modulo = $_GET['modulo'] ?? 'principal';

    switch ($modulo) {
        case 'dashboard':
            // En el futuro: con login
            require_once 'app/views/content/dashboard-view.php';
            break;

        case 'principal':
        default:
            // Vista pública: dashboard en kiosk
            require_once 'app/views/principal-view.php';
            break;
    }
}





