<?php
// Este script se ejecuta a las 00:00:00 desde CRON

ob_start();

require_once '/var/www/greentrack/config/app.php';
if (!defined('APP_R_PROY')) {
    die('ERROR: APP_R_PROY no está definido');
}

// Cambia al directorio del proyecto
chdir('/var/www/greentrack'); // ← Ajusta a tu ruta

// Incluir el controlador
require_once APP_R_PROY . 'app/controllers/medianocheController.php';

use app\controllers\medianocheController;

// Ejecutar proceso
$ctrl = new medianocheController();
$ctrl->cerrarServiciosNoAtendidos();

// Registro de ejecución

$file = 'app/logs/cron/cron.log';
if (!file_exists($file)) {
    $initialContent = "[" . date('Y-m-d H:i:s') . "] Archivo de log iniciado" . PHP_EOL;
    $created = file_put_contents($file, $initialContent, FILE_APPEND | LOCK_EX);
    if ($created === false) {
        error_log("No se pudo crear el archivo de log: " . $file);
        return;
    }
    chmod($file, 0644); // Asegurarse de que el archivo sea legible y escribible
}
$logEntry = "[" .  date('Y-m-d H:i:s') . "] - Ejecución de medianoche\n" . PHP_EOL;

file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);