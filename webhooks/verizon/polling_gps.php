#!/usr/bin/php
<?php
// --- Inicio: Configuración inicial ---
define('APP_R_PROY', '/var/www/greentrack/');

require_once APP_R_PROY . 'config/app.php';
require_once APP_R_PROY . 'app/controllers/motor2Controller.php';

use app\controllers\motor2Controller;

// --- Fin: Configuración ---
echo "[INFO] " . date('Y-m-d H:i:s') . " Iniciando sistema de seguimiento GPS...\n";

$controller = new motor2Controller();

// === Obtener trucks activos desde la base de datos ===
$trucks_activos = $controller->obtenerTrucksActivosHoy();

if (empty($trucks_activos)) {
    echo "[INFO] No hay trucks activos hoy. El sistema se detendrá.\n";
    exit(0);
}

echo "[INFO] Trucks activos detectados: " . implode(', ', $trucks_activos) . "\n";

// Intervalo entre lecturas (en segundos)
$intervalo = (defined('GPS_POLLING_INTERVAL')) ? GPS_POLLING_INTERVAL : 5;

echo "[INFO] Polling cada {$intervalo} segundos para " . count($trucks_activos) . " vehículos.\n";

// Bucle infinito
while (true) {
    foreach ($trucks_activos as $truck) {
        echo "[→] Consultando GPS: {$truck}\n";
        
        try {
            // Forzar que obtenerGpsVerizon reciba solo el vehicle_id
            $controller->obtenerGpsVerizon($truck);
            
        } catch (Exception $e) {
            echo "[ERROR] Excepción al consultar {$truck}: " . $e->getMessage() . "\n";
            error_log("polling_gps.php - Error con {$truck}: " . $e->getMessage());
        }
    }

    // Esperar antes de la siguiente ronda
    sleep($intervalo);
}