<?php
// index.php
// Punto de entrada principal del sistema

// Incluir configuración
require_once 'config/app.php';       // Constantes
//require_once 'config/autoload.php';  // Autocarga (si usas clases)

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





