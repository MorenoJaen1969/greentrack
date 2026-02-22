<?php
/**
 * SCRIPT DE NORMALIZACIÓN DEFINITIVA DE DIRECCIONES
 * GreenTrack - Final Address Normalization
 * 
 * PROPÓSITO: Unificar TODAS las direcciones a un formato estándar
 * para poder identificar duplicados de manera confiable.
 * 
 * NORMALIZA:
 * - Mayúsculas consistentes
 * - Abreviaturas estándar (DR, RD, ST, etc.)
 * - Elimina errores tipográficos (BLVDVDDD → BLVD)
 * - Formato fijo: NUMERO CALLE, CIUDAD, ESTADO CP, USA
 */

// ============================================
// CONFIGURACIÓN
// ============================================

$procesar = TRUE; // ← EJECUTAR EN MODO REAL

$db_config = [
    'host' => 'localhost',
    'user' => 'mmoreno',
    'pass' => 'Noloseno#2017',
    'name' => 'greentrack_live'
];

$log_config = [
    'dir' => __DIR__,
    'prefix' => 'normalizacion_definitiva_',
    'date_format' => 'Ymd_His'
];

// ============================================
// FUNCIÓN DE NORMALIZACIÓN DEFINITIVA
// ============================================

function normalizarDireccion($original) {
    if (empty($original)) return '';
    
    $dir = trim($original);
    $original_guardado = $dir;
    
    // 1. CONVERTIR A MAYÚSCULAS
    $dir = strtoupper($dir);
    
    // 2. CORREGIR ERRORES COMUNES
    $correcciones = [
        // Errores tipográficos
        'BLVDVDDD' => 'BLVD',
        'BLVDDDD' => 'BLVD',
        'BLVDD' => 'BLVD',
        'BLV' => 'BLVD',
        
        // Espacios faltantes
        'TEXAS773' => 'TEXAS 773',
        'TX773' => 'TX 773',
        'USA773' => 'USA 773',
        
        // Puntuación incorrecta
        ',,,' => ',',
        ',, ' => ', ',
        ' .' => ' ',
    ];
    
    $dir = str_replace(array_keys($correcciones), array_values($correcciones), $dir);
    
    // 3. ELIMINAR PUNTUACIÓN EXCESIVA
    $dir = str_replace(['.', ';', ':'], ' ', $dir);
    $dir = preg_replace('/,\s*,/', ',', $dir);
    
    // 4. NORMALIZAR ABREVIATURAS (TODAS A FORMA CORTA ESTÁNDAR)
    $abreviaturas = [
        // Road
        ' ROAD ' => ' RD ',
        ' ROAD' => ' RD',
        'ROAD ' => 'RD ',
        'ROAD' => 'RD',
        
        // Drive
        ' DRIVE ' => ' DR ',
        ' DRIVE' => ' DR',
        'DRIVE ' => 'DR ',
        'DRIVE' => 'DR',
        
        // Street
        ' STREET ' => ' ST ',
        ' STREET' => ' ST',
        'STREET ' => 'ST ',
        'STREET' => 'ST',
        
        // Avenue
        ' AVENUE ' => ' AVE ',
        ' AVENUE' => ' AVE',
        'AVENUE ' => 'AVE ',
        'AVENUE' => 'AVE',
        
        // Lane
        ' LANE ' => ' LN ',
        ' LANE' => ' LN',
        'LANE ' => 'LN ',
        'LANE' => 'LN',
        
        // Court
        ' COURT ' => ' CT ',
        ' COURT' => ' CT',
        'COURT ' => 'CT ',
        'COURT' => 'CT',
        
        // Circle
        ' CIRCLE ' => ' CIR ',
        ' CIRCLE' => ' CIR',
        'CIRCLE ' => 'CIR ',
        'CIRCLE' => 'CIR',
        
        // Boulevard
        ' BOULEVARD ' => ' BLVD ',
        ' BOULEVARD' => ' BLVD',
        'BOULEVARD ' => 'BLVD ',
        'BOULEVARD' => 'BLVD',
        
        // Parkway
        ' PARKWAY ' => ' PKWY ',
        ' PARKWAY' => ' PKWY',
        'PARKWAY ' => 'PKWY ',
        'PARKWAY' => 'PKWY',
        
        // Highway
        ' HIGHWAY ' => ' HWY ',
        ' HIGHWAY' => ' HWY',
        'HIGHWAY ' => 'HWY ',
        'HIGHWAY' => 'HWY',
        
        // Trail
        ' TRAIL ' => ' TRL ',
        ' TRAIL' => ' TRL',
        'TRAIL ' => 'TRL ',
        'TRAIL' => 'TRL',
        
        // Loop
        ' LOOP ' => ' LP ',
        ' LOOP' => ' LP',
        'LOOP ' => 'LP ',
        'LOOP' => 'LP',
        
        // Way
        ' WAY ' => ' WY ',
        ' WAY' => ' WY',
        'WAY ' => 'WY ',
        'WAY' => 'WY',
        
        // Place
        ' PLACE ' => ' PL ',
        ' PLACE' => ' PL',
        'PLACE ' => 'PL ',
        'PLACE' => 'PL',
        
        // Fort
        ' FORT ' => ' FT ',
        ' FORT' => ' FT',
        'FORT ' => 'FT ',
        'FORT' => 'FT',
        
        // Mount
        ' MOUNT ' => ' MT ',
        ' MOUNT' => ' MT',
        'MOUNT ' => 'MT ',
        'MOUNT' => 'MT',
        
        // Cardinales
        ' NORTH ' => ' N ',
        ' NORTH' => ' N',
        'NORTH ' => 'N ',
        'NORTH' => 'N',
        
        ' SOUTH ' => ' S ',
        ' SOUTH' => ' S',
        'SOUTH ' => 'S ',
        'SOUTH' => 'S',
        
        ' EAST ' => ' E ',
        ' EAST' => ' E',
        'EAST ' => 'E ',
        'EAST' => 'E',
        
        ' WEST ' => ' W ',
        ' WEST' => ' W',
        'WEST ' => 'W ',
        'WEST' => 'W',
        
        ' NORTHEAST ' => ' NE ',
        ' NORTHEAST' => ' NE',
        'NORTHEAST ' => 'NE ',
        'NORTHEAST' => 'NE',
        
        ' NORTHWEST ' => ' NW ',
        ' NORTHWEST' => ' NW',
        'NORTHWEST ' => 'NW ',
        'NORTHWEST' => 'NW',
        
        ' SOUTHEAST ' => ' SE ',
        ' SOUTHEAST' => ' SE',
        'SOUTHEAST ' => 'SE ',
        'SOUTHEAST' => 'SE',
        
        ' SOUTHWEST ' => ' SW ',
        ' SOUTHWEST' => ' SW',
        'SOUTHWEST ' => 'SW ',
        'SOUTHWEST' => 'SW',
    ];
    
    $dir = str_replace(array_keys($abreviaturas), array_values($abreviaturas), $dir);
    
    // 5. NORMALIZAR CIUDADES (nombres consistentes)
    $ciudades = [
        'PANORAMA VILLAGE' => 'PANORAMA VILLAGE',
        'PANORAMA VLG' => 'PANORAMA VILLAGE',
        'PANORAMA CITY' => 'PANORAMA VILLAGE',
        'THE WOODLANDS' => 'THE WOODLANDS',
        'WOODLANDS' => 'THE WOODLANDS',
        'SPRING' => 'SPRING',
        'CONROE' => 'CONROE',
        'MONTGOMERY' => 'MONTGOMERY',
        'WILLIS' => 'WILLIS',
        'CLEVELAND' => 'CLEVELAND',
        'HUNTSVILLE' => 'HUNTSVILLE',
        'HOUSTON' => 'HOUSTON',
    ];
    
    foreach ($ciudades as $buscar => $reemplazo) {
        $dir = str_replace($buscar, $reemplazo, $dir);
    }
    
    // 6. ELIMINAR CONDADO (redundante)
    $dir = str_replace(' MONTGOMERY COUNTY', '', $dir);
    $dir = str_replace(' MONTGOMERY CO', '', $dir);
    
    // 7. NORMALIZAR ESTADO
    $dir = str_replace(' TX', ' TEXAS', $dir);
    $dir = str_replace(' TEXAS', ' TEXAS', $dir);
    $dir = preg_replace('/(TEXAS\s*)+/', 'TEXAS', $dir);
    
    // 8. EXTRACCIÓN DE CÓDIGO POSTAL
    $cp = '';
    if (preg_match('/\b(\d{5})\b/', $dir, $matches)) {
        $cp = $matches[1];
        $dir = preg_replace('/\b\d{5}\b/', '', $dir);
    }
    
    // 9. LIMPIEZA DE ESPACIOS Y COMAS
    $dir = preg_replace('/\s+/', ' ', $dir);
    $dir = str_replace(' ,', ',', $dir);
    $dir = trim($dir);
    $dir = rtrim($dir, ',');
    
    // 10. RECONSTRUIR FORMATO ESTÁNDAR
    $partes = explode(',', $dir);
    $partes = array_map('trim', $partes);
    $partes = array_filter($partes);
    
    $nueva = implode(', ', $partes);
    
    // 11. AÑADIR CÓDIGO POSTAL
    if ($cp) {
        $nueva .= ' ' . $cp;
    }
    
    // 12. ASEGURAR ", USA" AL FINAL
    if (substr($nueva, -4) !== ' USA') {
        $nueva = rtrim($nueva, ', ') . ', USA';
    }
    
    // 13. LIMPIEZA FINAL
    $nueva = str_replace('  ', ' ', $nueva);
    $nueva = str_replace(',,', ',', $nueva);
    $nueva = trim($nueva);
    
    return [
        'original' => $original_guardado,
        'normalizada' => $nueva,
        'fue_modificada' => ($original_guardado != $nueva)
    ];
}

// ============================================
// CONEXIÓN A BASE DE DATOS
// ============================================

$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['pass'],
    $db_config['name']
);

if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error . "\n");
}

// ============================================
// INICIALIZAR LOG
// ============================================

$log_file = $log_config['dir'] . '/' . $log_config['prefix'] . date($log_config['date_format']) . '.log';

function writeLog($message, $log_file) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo $message . "\n";
}

writeLog("==================================================", $log_file);
writeLog("🚀 NORMALIZACIÓN DEFINITIVA DE DIRECCIONES", $log_file);
writeLog("📋 Modo: " . ($procesar ? "REAL (CON CAMBIOS)" : "SIMULACIÓN"), $log_file);
writeLog("==================================================", $log_file);

// ============================================
// OBTENER TODAS LAS DIRECCIONES
// ============================================

$query = "SELECT id_direccion, direccion FROM direcciones ORDER BY id_direccion";
$result = $conn->query($query);
$total = $result->num_rows;

writeLog("📊 TOTAL DIRECCIONES A PROCESAR: {$total}", $log_file);

$modificadas = 0;
$ids_modificados = [];

// ============================================
// PROCESAR CADA DIRECCIÓN
// ============================================

while ($row = $result->fetch_assoc()) {
    $id = $row['id_direccion'];
    $direccion_original = $row['direccion'];
    
    $normalizada = normalizarDireccion($direccion_original);
    
    if ($normalizada['fue_modificada']) {
        $modificadas++;
        $ids_modificados[] = $id;
        
        writeLog("────────────────────────────────────", $log_file);
        writeLog("📌 ID: {$id}", $log_file);
        writeLog("   📍 ORIGINAL:  {$normalizada['original']}", $log_file);
        writeLog("   🔄 NORMALIZADA: {$normalizada['normalizada']}", $log_file);
        
        if ($procesar) {
            $stmt = $conn->prepare("UPDATE direcciones SET direccion = ? WHERE id_direccion = ?");
            $stmt->bind_param("si", $normalizada['normalizada'], $id);
            $stmt->execute();
            $stmt->close();
            writeLog("   ✅ ACTUALIZADA", $log_file);
        } else {
            writeLog("   📝 [SIMULACIÓN] Se actualizaría", $log_file);
        }
    }
}

// ============================================
// RESUMEN FINAL
// ============================================

writeLog("", $log_file);
writeLog("==================================================", $log_file);
writeLog("📊 RESUMEN FINAL", $log_file);
writeLog("==================================================", $log_file);
writeLog("   Total direcciones procesadas: {$total}", $log_file);
writeLog("   Direcciones modificadas: {$modificadas}", $log_file);
writeLog("   Direcciones sin cambios: " . ($total - $modificadas), $log_file);

if (!$procesar) {
    writeLog("", $log_file);
    writeLog("📝 [SIMULACIÓN] No se ejecutaron cambios", $log_file);
    writeLog("📝 Para aplicar, cambia \$procesar = TRUE", $log_file);
}

writeLog("==================================================", $log_file);
writeLog("🏁 PROCESO FINALIZADO", $log_file);
writeLog("📋 Log generado: " . basename($log_file), $log_file);
writeLog("==================================================", $log_file);

$conn->close();

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Proceso completado\n";
echo "📊 Total direcciones: {$total}\n";
echo "📝 Modificadas: {$modificadas}\n";
echo "📋 Log: " . basename($log_file) . "\n";
echo str_repeat("=", 50) . "\n";
?>