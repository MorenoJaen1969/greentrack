#!/usr/bin/php
<?php
/**
 * Script temporal: migrar_trucks.php
 * Llama al método seguro dentro de motor2Controller
 * Ejecución: php migrar_trucks.php
 */

// === 1. Definir constante APP_R_PROY si no existe ===
if (!defined('APP_R_PROY')) {
    define('APP_R_PROY', '/var/www/greentrack/');
}

// === 2. Cargar configuración y controlador ===
require_once APP_R_PROY . 'config/app.php';
require_once APP_R_PROY . 'app/controllers/motor2Controller.php';

use app\controllers\motor2Controller;

// === 3. Crear instancia y ejecutar migración ===
try {
    $controller = new motor2Controller();
    $controller->migrarTrucksAVerizon(); // Llama al método seguro
} catch (Exception $e) {
    error_log("[ERROR] No se pudo iniciar motor2Controller: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}