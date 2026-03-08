<?php
/**
 * Script de actualización de ciudades en zonas_cuadricula
 * Ejecutar desde consola: php actualizar_zonas_geografia.php
 */

// ======================================================================
// CONFIGURACIÓN
// ======================================================================

$CONFIG = [
    'apply_changes' => TRUE,  // ← ¡MANTENER false PARA SIMULACIÓN!
    'delay_microseconds' => 1000000, // 1 segundo entre peticiones a Nominatim
    'backup_sql' => '/var/www/greentrack/scripts/actualizar_geografia/backup_zona_cuadricula_' . date('Ymd_His') . '.sql',
    'log_file' => '/var/www/greentrack/scripts/actualizar_geografia/actualizacion_nombres_log.txt'
];

// ======================================================================
// CONEXIÓN BASE DE DATOS
// ======================================================================

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ACTUALIZADOR DE CIUDADES EN ZONAS_CUADRICULA                      ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

echo "Modo: " . ($CONFIG['apply_changes'] ? "⚠️  APLICAR CAMBIOS" : "🔍 SIMULACIÓN (solo lectura)") . "\n\n";

// ======================================================================
// CREAR DIRECTORIO DE LOGS SI NO EXISTE
// ======================================================================

$logDir = dirname($CONFIG['log_file']);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// ======================================================================
// FUNCIONES AUXILIARES
// ======================================================================

function logMessage($msg, $type = 'INFO') {
    global $CONFIG;
    $line = "[" . date('Y-m-d H:i:s') . "] [$type] $msg\n";
    echo $line;
    file_put_contents($CONFIG['log_file'], $line, FILE_APPEND | LOCK_EX);
}

function geocodificarInversa($lat, $lng) {
    global $CONFIG;
    
    $url = "https://nominatim.openstreetmap.org/reverse?lat={$lat}&lon={$lng}&format=json&addressdetails=1";
    
    $opts = [
        'http' => [
            'header' => 'User-Agent: GreenTrackApp/1.0 (mmoreno@dominio.com)',
            'timeout' => 30,
            'follow_location' => 1
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    
    if (!$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['address'])) {
        return null;
    }
    
    $addr = $data['address'];
    
    // Mapeo de campos con múltiples alternativas
    $ciudad = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['hamlet'] ?? $addr['county'] ?? null;
    $condado = $addr['county'] ?? null;
    $estado = $addr['state'] ?? null;
    $pais = $addr['country'] ?? 'United States';
    $codigoPais = $addr['country_code'] ?? 'us';
    $zip = $addr['postcode'] ?? null;
    
    // Si no hay ciudad pero hay condado, usar condado como ciudad
    if (!$ciudad && $condado) {
        $ciudad = $condado;
    }
    
    return [
        'ciudad' => $ciudad,
        'condado' => $condado,
        'estado' => $estado,
        'pais' => $pais,
        'codigo_pais' => strtoupper($codigoPais),
        'zip' => $zip,
        'direccion_completa' => $data['display_name'] ?? null
    ];
}

function obtenerOCrearPais($mysqli, $nombre, $codigoIso2) {
    // Buscar por código ISO2
    $stmt = $mysqli->prepare("SELECT id_pais FROM paises WHERE codigo_iso2 = ? LIMIT 1");
    $stmt->bind_param('s', $codigoIso2);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id_pais'];
    }
    
    // Crear país
    $codigoIso3 = $codigoIso2 . 'A'; // Simplificación
    
    $stmt = $mysqli->prepare("INSERT INTO paises (nombre, codigo_iso2, codigo_iso3) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $nombre, $codigoIso2, $codigoIso3);
    
    if ($stmt->execute()) {
        return $mysqli->insert_id;
    }
    
    return null;
}

function obtenerOCrearEstado($mysqli, $nombre, $idPais) {
    if (!$nombre) return null;
    
    // Generar abreviatura
    $abrev = abreviarEstado($nombre);
    
    // Buscar por nombre o abreviatura
    $stmt = $mysqli->prepare("SELECT id_estado FROM estados WHERE (nombre = ? OR abreviatura = ?) AND id_pais = ? LIMIT 1");
    $stmt->bind_param('ssi', $nombre, $abrev, $idPais);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id_estado'];
    }
    
    // Crear estado
    $stmt = $mysqli->prepare("INSERT INTO estados (nombre, abreviatura, id_pais) VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $nombre, $abrev, $idPais);
    
    if ($stmt->execute()) {
        return $mysqli->insert_id;
    }
    
    return null;
}

function obtenerOCrearCondado($mysqli, $nombre, $idEstado) {
    if (!$nombre) {
        $nombre = 'Unknown County';
    }
    
    $stmt = $mysqli->prepare("SELECT id_condado FROM condados WHERE nombre = ? AND id_estado = ? LIMIT 1");
    $stmt->bind_param('si', $nombre, $idEstado);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id_condado'];
    }
    
    // Crear condado
    $stmt = $mysqli->prepare("INSERT INTO condados (nombre, id_estado) VALUES (?, ?)");
    $stmt->bind_param('si', $nombre, $idEstado);
    
    if ($stmt->execute()) {
        return $mysqli->insert_id;
    }
    
    return null;
}

function obtenerOCrearCiudad($mysqli, $nombre, $idCondado, $idEstado, $idPais) {
    if (!$nombre) {
        $nombre = 'Unknown City';
    }
    
    $stmt = $mysqli->prepare("SELECT id_ciudad FROM ciudades WHERE nombre = ? AND id_estado = ? LIMIT 1");
    $stmt->bind_param('si', $nombre, $idEstado);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id_ciudad'];
    }
    
    // Crear ciudad
    $esOperativa = 1;
    $stmt = $mysqli->prepare("INSERT INTO ciudades (nombre, id_condado, id_estado, id_pais, es_zona_operativa) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('siiii', $nombre, $idCondado, $idEstado, $idPais, $esOperativa);
    
    if ($stmt->execute()) {
        return $mysqli->insert_id;
    }
    
    return null;
}

function abreviarEstado($nombre) {
    $abrevs = [
        'Alabama' => 'AL', 'Alaska' => 'AK', 'Arizona' => 'AZ', 'Arkansas' => 'AR',
        'California' => 'CA', 'Colorado' => 'CO', 'Connecticut' => 'CT', 'Delaware' => 'DE',
        'Florida' => 'FL', 'Georgia' => 'GA', 'Hawaii' => 'HI', 'Idaho' => 'ID',
        'Illinois' => 'IL', 'Indiana' => 'IN', 'Iowa' => 'IA', 'Kansas' => 'KS',
        'Kentucky' => 'KY', 'Louisiana' => 'LA', 'Maine' => 'ME', 'Maryland' => 'MD',
        'Massachusetts' => 'MA', 'Michigan' => 'MI', 'Minnesota' => 'MN', 'Mississippi' => 'MS',
        'Missouri' => 'MO', 'Montana' => 'MT', 'Nebraska' => 'NE', 'Nevada' => 'NV',
        'New Hampshire' => 'NH', 'New Jersey' => 'NJ', 'New Mexico' => 'NM', 'New York' => 'NY',
        'North Carolina' => 'NC', 'North Dakota' => 'ND', 'Ohio' => 'OH', 'Oklahoma' => 'OK',
        'Oregon' => 'OR', 'Pennsylvania' => 'PA', 'Rhode Island' => 'RI', 'South Carolina' => 'SC',
        'South Dakota' => 'SD', 'Tennessee' => 'TN', 'Texas' => 'TX', 'Utah' => 'UT',
        'Vermont' => 'VT', 'Virginia' => 'VA', 'Washington' => 'WA', 'West Virginia' => 'WV',
        'Wisconsin' => 'WI', 'Wyoming' => 'WY', 'District of Columbia' => 'DC'
    ];
    
    return $abrevs[$nombre] ?? substr(strtoupper(str_replace(' ', '', $nombre)), 0, 2);
}

// ======================================================================
// PROCESO PRINCIPAL
// ======================================================================

logMessage("Iniciando proceso de actualización");

// Obtener zonas sin ciudad asignada
$sql = "SELECT id_zona, nombre_zona, centro_lat, centro_lng, lat_sw, lat_ne, lng_sw, lng_ne 
        FROM zonas_cuadricula 
        WHERE id_ciudad_origen IS NULL 
        AND activo = 1
        ORDER BY id_zona";

$result = $m->query($sql);

if (!$result) {
    die("[ERROR] No se pudieron obtener zonas: " . $m->error . "\n");
}

$zonas = [];
while ($row = $result->fetch_assoc()) {
    $zonas[] = $row;
}

logMessage("Encontradas " . count($zonas) . " zonas sin ciudad asignada");

if (count($zonas) === 0) {
    logMessage("No hay zonas para procesar. Saliendo.");
    exit(0);
}

// Estadísticas
$stats = [
    'procesadas' => 0,
    'actualizadas' => 0,
    'errores' => 0,
    'saltadas' => 0
];

echo "\n";

foreach ($zonas as $zona) {
    $stats['procesadas']++;
    
    echo str_repeat("=", 70) . "\n";
    logMessage("Procesando zona {$stats['procesadas']}/" . count($zonas) . ": {$zona['nombre_zona']} (ID: {$zona['id_zona']})");
    
    // Calcular centro si no existe
    $lat = $zona['centro_lat'] ?? (($zona['lat_sw'] + $zona['lat_ne']) / 2);
    $lng = $zona['centro_lng'] ?? (($zona['lng_sw'] + $zona['lng_ne']) / 2);
    
    logMessage("  Coordenadas: {$lat}, {$lng}");
    
    // Geocodificación inversa
    $geoData = geocodificarInversa($lat, $lng);
    
    if (!$geoData) {
        logMessage("  ❌ Error en geocodificación", "ERROR");
        $stats['errores']++;
        continue;
    }
    
    logMessage("  📍 Encontrado: {$geoData['ciudad']}, {$geoData['estado']}, {$geoData['pais']}");
    
    if ($geoData['direccion_completa']) {
        logMessage("  📝 " . substr($geoData['direccion_completa'], 0, 80) . "...");
    }
    
    // Si estamos en modo simulación, no hacer cambios en BD
    if (!$CONFIG['apply_changes']) {
        logMessage("  ⏭️  MODO SIMULACIÓN: No se aplican cambios");
        $stats['saltadas']++;
        
        // Delay igual para no saturar la API en pruebas
        usleep($CONFIG['delay_microseconds']);
        continue;
    }
    
    // Crear/obtener jerarquía geográfica
    $idPais = obtenerOCrearPais($m, $geoData['pais'], $geoData['codigo_pais']);
    if (!$idPais) {
        logMessage("  ❌ Error obteniendo/creando país", "ERROR");
        $stats['errores']++;
        continue;
    }
    
    $idEstado = obtenerOCrearEstado($m, $geoData['estado'], $idPais);
    if (!$idEstado) {
        logMessage("  ❌ Error obteniendo/creando estado", "ERROR");
        $stats['errores']++;
        continue;
    }
    
    $idCondado = obtenerOCrearCondado($m, $geoData['condado'], $idEstado);
    if (!$idCondado) {
        logMessage("  ❌ Error obteniendo/creando condado", "ERROR");
        $stats['errores']++;
        continue;
    }
    
    $idCiudad = obtenerOCrearCiudad($m, $geoData['ciudad'], $idCondado, $idEstado, $idPais);
    if (!$idCiudad) {
        logMessage("  ❌ Error obteniendo/creando ciudad", "ERROR");
        $stats['errores']++;
        continue;
    }
    
    // Actualizar zona
    $stmt = $m->prepare("UPDATE zonas_cuadricula SET id_ciudad_origen = ? WHERE id_zona = ?");
    $stmt->bind_param('ii', $idCiudad, $zona['id_zona']);
    
    if ($stmt->execute()) {
        logMessage("  ✅ Actualizado: id_ciudad_origen = {$idCiudad}");
        $stats['actualizadas']++;
    } else {
        logMessage("  ❌ Error actualizando zona: " . $stmt->error, "ERROR");
        $stats['errores']++;
    }
    
    // Respetar límites de API
    usleep($CONFIG['delay_microseconds']);
}

// ======================================================================
// RESUMEN
// ======================================================================

echo "\n" . str_repeat("=", 70) . "\n";
echo "RESUMEN\n";
echo str_repeat("=", 70) . "\n";
logMessage("Proceso completado");
logMessage("Zonas procesadas: {$stats['procesadas']}");
logMessage("Actualizadas: {$stats['actualizadas']}");
logMessage("Errores: {$stats['errores']}");
logMessage("Saltadas (simulación): {$stats['saltadas']}");

if (!$CONFIG['apply_changes']) {
    echo "\n⚠️  ESTE FUE UN SIMULACRO. No se aplicaron cambios.\n";
    echo "Para aplicar cambios, edita \$CONFIG['apply_changes'] = true;\n";
}

echo "\nLog guardado en: {$CONFIG['log_file']}\n";

$m->close();