<?php
// ================================================= //
//          PUNTO DE ENTRADA - MÓDULO QR ADMIN
//          - Acceso exclusivo por QR físico
//          - Usa el sistema de login principal
//          - Redirige al dashboard personalizado tras login
// ================================================= //

// Definir ruta base del proyecto
$RUTA_PROYECTO = '/var/www/greentrack/';

// Incluir configuración principal
require_once $RUTA_PROYECTO . 'config/app.php';
require_once $RUTA_PROYECTO . 'config/server.php';
require_once $RUTA_PROYECTO . 'autoload.php';

const RUTA_FONTAWESOME=$RUTA_PROYECTO."node_modules/@fortawesome/fontawesome-free/";
const RUTA_SWEETALERT=$RUTA_PROYECTO."node_modules/sweetalert2/dist/";

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determinar idioma (por cookie o sesión)
$idioma = 'en'; // por defecto
if (isset($_COOKIE['clang']) && in_array($_COOKIE['clang'], ['es', 'en'])) {
    $idioma = $_COOKIE['clang'];
} elseif (isset($_SESSION['lang'])) {
    $idioma = $_SESSION['lang'];
}

// Cargar controlador de login
use app\controllers\userController;
$insLogin = new userController();

// ================================
// FLUJO: ¿Está logueado?
// ================================

// Si hay sesión activa y el usuario está validado
if (isset($_SESSION['id']) && $_SESSION['id'] > 0) {
    // Cargar el dashboard personalizado del módulo QR
    require_once 'content/dashboard-view.php';
    exit;
}

// Si NO está logueado, cargar el login oficial del sistema
// Reutilizamos exactamente el mismo login que el sistema principal
$cookie = APP_URL . "app/views/img/cookie.svg";
$GLOBALS['id_idioma'] = $idioma;

// Cargar traducciones
$carga_idioma = new \app\models\otras_fun();
$palabras = $carga_idioma->cargar_idioma($idioma);
$errores = $carga_idioma->cargar_errores($idioma);

// Si el usuario envió el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['userName_i'], $_POST['userPassword_i'])) {
    $insLogin->iniciarSesionControlador();
    // Tras login, redirigir a este mismo index.php (para que cargue el dashboard)
    header('Location: ./');
    exit;
}

// ================================
// Mostrar login-view.php (sin duplicar código)
// ================================

// Incluir el login directamente
include $RUTA_PROYECTO . 'app/views/content/login-view.php';