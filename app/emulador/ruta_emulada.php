<?php
// ruta_emulada.php
// No uses mainModel.php, no uses namespaces, no uses traits

// Define APP_R_PROY si no existe
if (!defined('APP_R_PROY')) {
    define('APP_R_PROY', '/var/www/greentrack/');
}

// Conexión a la base de datos
$pdo = new PDO('mysql:host=localhost;dbname=greentrack_live;charset=utf8', 'mmoreno', 'Noloseno#2017');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Función de log simple
function log_emulador($mensaje) {
    $log_file = '/var/www/greentrack/app/logs/emulador.log';
    $fecha = date('Y-m-d H:i:s');
    $linea = "[$fecha] $mensaje\n";
    file_put_contents($log_file, $linea, FILE_APPEND | LOCK_EX);
}

// Función para generar trayecto
function generarTrayecto($inicio, $fin, $duracion_minutos = 5, $intervalo_segundos = 10) {
    $puntos = [];
    $total_segundos = $duracion_minutos * 60;
    $pasos = $total_segundos / $intervalo_segundos;

    $delta_lat = ($fin['lat'] - $inicio['lat']) / $pasos;
    $delta_lng = ($fin['lng'] - $inicio['lng']) / $pasos;

    $lat = $inicio['lat'];
    $lng = $inicio['lng'];

    for ($i = 0; $i < $pasos; $i++) {
        $lat += $delta_lat;
        $lng += $delta_lng;
        $puntos[] = [
            'lat' => $lat,
            'lng' => $lng,
            'timestamp' => date('Y-m-d H:i:s', time() + ($i * $intervalo_segundos))
        ];
    }
    return $puntos;
}

// Iniciar emulación
log_emulador("Iniciando emulación de ruta GPS");

// Obtener servicios
$servicios = $pdo->query("SELECT id, cliente, direccion, lat, lng, truck, crew_color, geofence_id FROM servicios ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

if (!$servicios) {
    log_emulador("No hay servicios para emular");
    echo "❌ No hay servicios en la base de datos.\n";
    exit;
}

log_emulador("Emulando ruta para " . count($servicios) . " clientes");

$todos_los_puntos = [];

foreach ($servicios as $index => $s) {
    $punto_actual = ['lat' => (float)$s['lat'], 'lng' => (float)$s['lng']];

    // Parada de 3 minutos
    for ($i = 0; $i < 18; $i++) {
        $todos_los_puntos[] = [
            'vehicle_id' => 'Crew ' . $s['truck'],
            'lat' => $punto_actual['lat'],
            'lng' => $punto_actual['lng'],
            'timestamp' => date('Y-m-d H:i:s', time() + ($i * 10)),
            'geofence_id' => $s['geofence_id']
        ];
    }

    // Trayecto al siguiente
    if (isset($servicios[$index + 1])) {
        $siguiente = $servicios[$index + 1];
        $destino = ['lat' => (float)$siguiente['lat'], 'lng' => (float)$siguiente['lng']];
        $trayecto = generarTrayecto($punto_actual, $destino, 5, 10);

        foreach ($trayecto as $pos) {
            $todos_los_puntos[] = [
                'vehicle_id' => 'Crew ' . $s['truck'],
                'lat' => $pos['lat'],
                'lng' => $pos['lng'],
                'timestamp' => $pos['timestamp'],
                'geofence_id' => $s['geofence_id']
            ];
        }
    }
}

// Insertar en gps_realtime
foreach ($todos_los_puntos as $pos) {
    $stmt = $pdo->prepare("INSERT INTO gps_realtime (vehicle_id, lat, lng, timestamp, geofence_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$pos['vehicle_id'], $pos['lat'], $pos['lng'], $pos['timestamp'], $pos['geofence_id']]);
    usleep(100000); // 0.1 segundos
}

log_emulador("Emulación completada: " . count($todos_los_puntos) . " puntos insertados");
echo "✅ Ruta emulada: " . count($todos_los_puntos) . " puntos insertados en gps_realtime\n";
?>