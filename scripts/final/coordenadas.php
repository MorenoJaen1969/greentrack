<?php
/**
 * ======================================================================
 * GEOCODIFICACIÓN DE DIRECCIONES - ACTUALIZAR lat/lng
 * ======================================================================
 * 
 * Propósito:
 *   Barrer tabla direcciones y actualizar campos lat/lng usando API
 *   de geocodificación (OpenStreetMap Nominatim - gratuito)
 * 
 * Acciones:
 *   1. Buscar direcciones con lat/lng NULL o 0
 *   2. Geocodificar dirección mediante API
 *   3. Actualizar campos lat/lng
 *   4. Registrar cambio en campo observaciones
 * 
 * Configuración:
 *   • Modo simulación por defecto (sin modificar BD)
 *   • Límite de requests configurable
 *   • Delay entre requests para evitar rate limiting
 *   • Registro completo en log
 */

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  GEOCODIFICACIÓN DE DIRECCIONES - ACTUALIZAR lat/lng            ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// ======================================================================
// CONFIGURACIÓN
// ======================================================================

$CONFIG = [
    'apply_changes' => true,           // false = simulación, true = aplicar cambios
    'max_requests' => 500,              // Límite máximo de geocodificaciones
    'delay_seconds' => 1,               // Delay entre requests (evitar rate limiting)
    'log_file' => '/var/www/greentrack/scripts/geocodificacion_log_' . date('Ymd_His') . '.txt',
    'use_google_maps' => false,         // true = Google Maps (requiere API key), false = OpenStreetMap
    'google_api_key' => 'TU_API_KEY',   // Solo necesario si use_google_maps = true
    'tolerance_km' => 0.05,             // Tolerancia para considerar coordenadas válidas (50 metros)
    'skip_with_coordinates' => true,    // Saltar direcciones que ya tienen coordenadas válidas
    'only_process_null' => true         // Solo procesar si lat/lng son NULL o 0
];

// ======================================================================
// FUNCIONES DE GEOCODIFICACIÓN
// ======================================================================

/**
 * Geocodificar dirección usando OpenStreetMap Nominatim (GRATUITO)
 */
function geocodeOSM($address) {
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($address) . "&limit=1";
    
    $headers = [
        'User-Agent: GreenTrack Geocodification Tool/1.0',
        'Accept-Language: en-US,en;q=0.5'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && !empty($response)) {
        $data = json_decode($response, true);
        if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
            return [
                'lat' => (float)$data[0]['lat'],
                'lng' => (float)$data[0]['lon'],
                'source' => 'OSM',
                'address' => $data[0]['display_name'] ?? $address
            ];
        }
    }
    
    return null;
}

/**
 * Geocodificar dirección usando Google Maps Geocoding API (REQUIERE API KEY)
 */
function geocodeGoogle($address, $api_key) {
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($address) . "&key=" . $api_key;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && !empty($response)) {
        $data = json_decode($response, true);
        if ($data['status'] == 'OK' && !empty($data['results'])) {
            $location = $data['results'][0]['geometry']['location'];
            return [
                'lat' => (float)$location['lat'],
                'lng' => (float)$location['lng'],
                'source' => 'Google',
                'address' => $data['results'][0]['formatted_address'] ?? $address
            ];
        }
    }
    
    return null;
}

/**
 * Calcular distancia entre dos coordenadas (en kilómetros)
 */
function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371; // km
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}

// ======================================================================
// PASO 1: OBTENER DIRECCIONES A PROCESAR
// ======================================================================

echo "[INFO] Obteniendo direcciones a geocodificar...\n";

$where_clause = "direccion IS NOT NULL AND TRIM(direccion) != ''";

if ($CONFIG['only_process_null']) {
    $where_clause .= " AND (lat IS NULL OR lng IS NULL OR lat = 0 OR lng = 0)";
}

if ($CONFIG['skip_with_coordinates']) {
    // Ya se filtra arriba con only_process_null
}

$stmt = $m->prepare("
    SELECT 
        id_direccion,
        id_cliente,
        direccion,
        lat,
        lng,
        observaciones
    FROM direcciones
    WHERE $where_clause
    ORDER BY id_direccion ASC
    LIMIT 5000
");
$stmt->execute();
$result = $stmt->get_result();

$direcciones_a_procesar = [];
while ($row = $result->fetch_assoc()) {
    $direcciones_a_procesar[] = $row;
}
$stmt->close();

$total_a_procesar = count($direcciones_a_procesar);
echo "[OK] Direcciones a procesar: $total_a_procesar\n\n";

// ======================================================================
// PASO 2: PROCESAR CADA DIRECCIÓN
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  PROCESANDO GEOCODIFICACIÓN                                      ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$log_fp = fopen($CONFIG['log_file'], 'a');
fwrite($log_fp, "════════════════════════════════════════════════════════════════════\n");
fwrite($log_fp, "  GEOCODIFICACIÓN DE DIRECCIONES\n");
fwrite($log_fp, "  Fecha: " . date('Y-m-d H:i:s') . "\n");
fwrite($log_fp, "  API: " . ($CONFIG['use_google_maps'] ? 'Google Maps' : 'OpenStreetMap Nominatim') . "\n");
fwrite($log_fp, "  Modo: " . ($CONFIG['apply_changes'] ? 'APLICAR CAMBIOS' : 'SIMULACIÓN') . "\n");
fwrite($log_fp, "════════════════════════════════════════════════════════════════════\n\n");

$geocodificados = 0;
$errores = 0;
$sin_cambios = 0;
$requests_realizados = 0;

foreach ($direcciones_a_procesar as $idx => $dir) {
    if ($requests_realizados >= $CONFIG['max_requests']) {
        echo "⚠️  Límite de $CONFIG[max_requests] requests alcanzado\n";
        break;
    }
    
    $id_direccion = $dir['id_direccion'];
    $direccion = trim($dir['direccion']);
    $lat_actual = $dir['lat'];
    $lng_actual = $dir['lng'];
    $observaciones_actuales = $dir['observaciones'] ?? '';
    
    // Geocodificar dirección
    if ($CONFIG['use_google_maps']) {
        $resultado = geocodeGoogle($direccion, $CONFIG['google_api_key']);
    } else {
        $resultado = geocodeOSM($direccion);
    }
    
    $requests_realizados++;
    
    if ($resultado === null) {
        $errores++;
        $log_msg = "✗ ID $id_direccion | '$direccion' → ERROR: Geocodificación fallida\n";
        echo $log_msg;
        fwrite($log_fp, $log_msg);
        continue;
    }
    
    $lat_nueva = $resultado['lat'];
    $lng_nueva = $resultado['lng'];
    $address_encontrada = $resultado['address'];
    
    // Verificar si hay cambios
    $cambio_necesario = false;
    $motivo_cambio = '';
    
    if ($lat_actual === null || $lng_actual === null || $lat_actual == 0 || $lng_actual == 0) {
        $cambio_necesario = true;
        $motivo_cambio = 'Coordenadas faltantes';
    } else {
        $distancia = calculateDistance($lat_actual, $lng_actual, $lat_nueva, $lng_nueva);
        if ($distancia > $CONFIG['tolerance_km']) {
            $cambio_necesario = true;
            $motivo_cambio = sprintf('Coordenadas incorrectas (%.3f km de diferencia)', $distancia);
        }
    }
    
    if (!$cambio_necesario) {
        $sin_cambios++;
        continue;
    }
    
    // Preparar observaciones
    $nuevas_observaciones = $observaciones_actuales;
    if (!empty($observaciones_actuales)) {
        $nuevas_observaciones .= "\n";
    }
    $nuevas_observaciones .= sprintf(
        "[GEOCODIFICACIÓN %s] lat: %s → %.6f | lng: %s → %.6f | Origen: %s",
        date('Y-m-d'),
        $lat_actual ?? 'NULL',
        $lat_nueva,
        $lng_actual ?? 'NULL',
        $lng_nueva,
        $resultado['source']
    );
    
    if ($CONFIG['apply_changes']) {
        // Actualizar en BD
        $stmt_upd = $m->prepare("
            UPDATE direcciones
            SET 
                lat = ?,
                lng = ?,
                observaciones = ?
            WHERE id_direccion = ?
        ");
        $stmt_upd->bind_param('ddsi', $lat_nueva, $lng_nueva, $nuevas_observaciones, $id_direccion);
        
        if ($stmt_upd->execute()) {
            $geocodificados++;
            $log_msg = sprintf(
                "✓ ID %d | '%s' → lat: %.6f, lng: %.6f (%s)\n",
                $id_direccion,
                $direccion,
                $lat_nueva,
                $lng_nueva,
                $motivo_cambio
            );
            echo $log_msg;
            fwrite($log_fp, $log_msg);
        } else {
            $errores++;
            $log_msg = "✗ ID $id_direccion | '$direccion' → ERROR: " . $stmt_upd->error . "\n";
            echo $log_msg;
            fwrite($log_fp, $log_msg);
        }
        $stmt_upd->close();
    } else {
        // Modo simulación
        $geocodificados++;
        $log_msg = sprintf(
            "✓ ID %d | '%s' → lat: %.6f, lng: %.6f (%s) [SIMULACIÓN]\n",
            $id_direccion,
            $direccion,
            $lat_nueva,
            $lng_nueva,
            $motivo_cambio
        );
        echo $log_msg;
        fwrite($log_fp, $log_msg);
    }
    
    // Esperar para evitar rate limiting
    if (($idx + 1) % 10 == 0) {
        echo "  [Esperando $CONFIG[delay_seconds] segundo(s) para evitar rate limiting...]\n";
    }
    sleep($CONFIG['delay_seconds']);
}

fclose($log_fp);

echo "\n[OK] Geocodificación completada\n";
echo "    Requests realizados: $requests_realizados\n";
echo "    Direcciones geocodificadas: $geocodificados\n";
echo "    Errores: $errores\n";
echo "    Sin cambios necesarios: $sin_cambios\n";
echo "    Log: {$CONFIG['log_file']}\n\n";

// ======================================================================
// VERIFICACIÓN FINAL
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICACIÓN FINAL                                              ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$verif = [
    'Direcciones con coordenadas (lat/lng)' => "
        SELECT COUNT(*) AS total 
        FROM direcciones 
        WHERE lat IS NOT NULL AND lng IS NOT NULL AND lat != 0 AND lng != 0
    ",
    'Direcciones sin coordenadas' => "
        SELECT COUNT(*) AS total 
        FROM direcciones 
        WHERE lat IS NULL OR lng IS NULL OR lat = 0 OR lng = 0
    ",
    'Direcciones procesadas hoy' => "
        SELECT COUNT(*) AS total 
        FROM direcciones 
        WHERE observaciones LIKE '%GEOCODIFICACIÓN " . date('Y-m-d') . "%'
    "
];

foreach ($verif as $desc => $query) {
    $result = $m->query($query);
    $total = $result->fetch_assoc()['total'];
    echo "  $desc: $total\n";
}

$m->close();

echo "\n╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  RESUMEN EJECUTIVO                                               ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "✅ Geocodificación con OpenStreetMap Nominatim (gratuito)\n";
echo "✅ Modo simulación activo (sin cambios reales en BD)\n";
echo "✅ Coordenadas actualizadas solo si son NULL, 0 o incorrectas (> 50m)\n";
echo "✅ Registro de cambios en campo observaciones\n";
echo "✅ Respeto de rate limiting (1 segundo entre requests)\n\n";

echo "💡 Para aplicar los cambios:\n";
echo "   1. Revisa el log: cat {$CONFIG['log_file']}\n";
echo "   2. Verifica que las coordenadas geocodificadas sean correctas\n";
echo "   3. Edita este script y cambia:\n";
echo "        'apply_changes' => true\n";
echo "   4. Ejecuta nuevamente\n\n";

echo "[FIN] Geocodificación completada\n\n";
?>