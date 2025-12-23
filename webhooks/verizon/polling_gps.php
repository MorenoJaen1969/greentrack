#!/usr/bin/php
<?php
// --- Inicio: Configuración inicial ---
// define('APP_R_PROY', '/var/www/greentrack/');

// require_once APP_R_PROY . 'config/app.php';
// require_once APP_R_PROY . 'app/controllers/motor2Controller.php';

// use app\controllers\motor2Controller;

// // --- Fin: Configuración ---
// echo "[INFO] " . date('Y-m-d H:i:s') . " Iniciando sistema de seguimiento GPS...\n";

// $controller = new motor2Controller();

// // === Obtener trucks activos desde la base de datos ===
// $trucks_activos = $controller->obtenerTrucksActivosHoy();

// if (empty($trucks_activos)) {
//     echo "[INFO] No hay trucks activos hoy. El sistema se detendrá.\n";
//     exit(0);
// }

// echo "[INFO] Trucks activos detectados: " . implode(', ', $trucks_activos) . "\n";

// // Intervalo entre lecturas (en segundos)
// $intervalo = (defined('GPS_POLLING_INTERVAL')) ? GPS_POLLING_INTERVAL : 5;

// echo "[INFO] Polling cada {$intervalo} segundos para " . count($trucks_activos) . " vehículos.\n";

// // Bucle infinito
// while (true) {
//     foreach ($trucks_activos as $truck) {
//         echo "[→] Consultando GPS: {$truck}\n";
        
//         try {
//             // Capturar salida con output buffering
//             ob_start();
//             $resultado = $controller->obtenerGpsVerizon($truck);
//             $salida = ob_get_clean();

//             if ($salida) {
//                 $data = @json_decode($salida, true);
//                 if ($data && isset($data['lat'])) {
//                     echo "[✓] GPS guardado: {$truck} → {$data['lat']}, {$data['lng']}\n";
//                 } else {
//                     echo "[✗] Error: " . ($data['message'] ?? 'Unknown') . "\n";
//                 }
//             }

//         } catch (Exception $e) {
//             echo "[EXCEPTION] {$truck}: " . $e->getMessage() . "\n";
//             error_log("polling_gps.php - Error con {$truck}: " . $e->getMessage());
//         }
//     }

//     // Esperar antes de la siguiente ronda
//     sleep($intervalo);
// }

// Propuesta nueva

// --- Inicio: Configuración inicial ---

#!/usr/bin/php
define('APP_R_PROY', '/var/www/greentrack/');
require_once APP_R_PROY . 'config/app.php';
require_once APP_R_PROY . 'app/controllers/motor2Controller.php';

use app\controllers\motor2Controller;

echo "[INFO] " . date('Y-m-d H:i:s') . " Iniciando GPS Poller...\n";

$intervalo = (defined('GPS_POLLING_INTERVAL')) ? GPS_POLLING_INTERVAL : 30;
$max_reintentos_inicial = 3;

$controller = null;
$trucks_activos = [];
$intentos_conexion = 0;

// === BUCLE INFINITO CON REUSO DE CONEXIÓN ===
while (true) {
    try {
        // === 1. Crear controlador SOLO SI ES NULL o FALLÓ ===
        if (!$controller || $intentos_conexion >= $max_reintentos_inicial) {
            if ($intentos_conexion >= $max_reintentos_inicial) {
                echo "[FATAL] Demasiados fallos de conexión. Durmiendo 60s...\n";
                sleep(60);
                $intentos_conexion = 0;
            }

            echo "[INFO] Creando nuevo controlador...\n";
            $controller = new motor2Controller();
            $intentos_conexion = 0;
        }

        // === 2. Obtener trucks activos (con reintento interno) ===
        try {
            $trucks_activos = $controller->obtenerTrucksActivosHoy();
        } catch (Exception $e) {
            echo "[ERROR] Fallo al obtener trucks activos: " . $e->getMessage() . "\n";
            $intentos_conexion++;
            sleep(5);
            continue;
        }

        if (empty($trucks_activos)) {
            echo "[INFO] No hay trucks activos hoy. Reintentando en {$intervalo}s...\n";
            sleep($intervalo);
            continue;
        }

        echo "[INFO] Procesando: " . implode(', ', $trucks_activos) . "\n";

        // === 3. Consultar cada truck ===
        foreach ($trucks_activos as $truck) {
            echo "[→] GPS: {$truck}\n";
            try {
                ob_start();
                $controller->obtenerGpsVerizon($truck);
                $salida = ob_get_clean();

                if ($salida) {
                    $data = @json_decode($salida, true);
                    if ($data && isset($data['lat'])) {
                        echo "[✓] OK: {$truck} → {$data['lat']}, {$data['lng']}\n";
                    } else {
                        echo "[✗] Respuesta inválida\n";
                    }
                }
            } catch (Exception $e) {
                echo "[EXCEPTION] {$truck}: " . $e->getMessage() . "\n";
                error_log("polling_gps.php - Exception con {$truck}: " . $e->getMessage());
            }
        }

        // Reiniciar contador de fallos tras éxito
        $intentos_conexion = 0;

    } catch (Exception $e) {
        echo "[FATAL] Error global: " . $e->getMessage() . "\n";
        error_log("polling_gps.php - FATAL: " . $e->getMessage());
        $intentos_conexion++;
    }

    // === 4. Dormir al final ===
    try {
        sleep($intervalo);
    } catch (Exception $e) {
        echo "[WARNING] sleep interrumpido\n";
    }
}