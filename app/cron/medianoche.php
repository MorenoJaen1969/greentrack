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
file_put_contents('app/logs/cron/cron.log', date('Y-m-d H:i:s') . " - Ejecución de medianoche\n", FILE_APPEND);