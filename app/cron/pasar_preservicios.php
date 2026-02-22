<?php
// Este script se ejecuta a las 00:01 desde CRON para transferir preservicios a servicios

ob_start();

require_once '/var/www/greentrack/config/app.php';
if (!defined('APP_R_PROY')) {
    die('ERROR: APP_R_PROY no está definido');
}

// Cambiar al directorio del proyecto
chdir('/var/www/greentrack'); // ← Ajusta a tu ruta si es necesario

require_once APP_R_PROY . 'app/controllers/medianocheController.php';

use app\controllers\medianocheController;

$ctrl = new medianocheController();
$ctrl->transferPreservicios();

$file = 'app/logs/cron/pasar_preservicios.log';
if (!file_exists($file)) {
    $initialContent = "[" . date('Y-m-d H:i:s') . "] Archivo de log iniciado" . PHP_EOL;
    file_put_contents($file, $initialContent, FILE_APPEND | LOCK_EX);
    chmod($file, 0644);
}
$logEntry = "[" .  date('Y-m-d H:i:s') . "] - Ejecución pasar_preservicios\n" . PHP_EOL;
file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);

echo "OK";
