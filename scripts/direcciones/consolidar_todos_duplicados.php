<?php
/**
 * SCRIPT MAESTRO DE CONSOLIDACIÓN DE DIRECCIONES - VERSIÓN FINAL
 * GreenTrack - Ultimate Address Consolidation
 * 
 * INSTRUCCIONES:
 * 1. Guarda este archivo
 * 2. Ejecuta: php este_archivo.php
 * 3. Listo.
 */

// ============================================
// CONFIGURACIÓN - SOLO CAMBIAR ESTO
// ============================================

$procesar = TRUE; // ← CAMBIAR A TRUE CUANDO ESTÉS LISTO

$db_config = [
    'host' => 'localhost',
    'user' => 'mmoreno',
    'pass' => 'Noloseno#2017',
    'name' => 'greentrack_live'
];

// ============================================
// NO TOCAR NADA DE AQUÍ EN ADELANTE
// ============================================

$log_file = __DIR__ . '/consolidacion_final_' . date('Ymd_His') . '.log';

function writeLog($message, $log_file) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo $message . "\n";
}

$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['pass'],
    $db_config['name']
);

if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error . "\n");
}

writeLog("==================================================", $log_file);
writeLog("🚀 CONSOLIDACIÓN FINAL DE DIRECCIONES", $log_file);
writeLog("📋 Modo: " . ($procesar ? "REAL" : "SIMULACIÓN"), $log_file);
writeLog("==================================================", $log_file);

// ============================================
// CREAR TABLA DE RESPALDO (SIN ERRORES)
// ============================================

writeLog("", $log_file);
writeLog("📁 Preparando tabla de respaldo...", $log_file);

$conn->query("CREATE TABLE IF NOT EXISTS direcciones_respaldo LIKE direcciones");

// Verificar si la columna ya existe ANTES de crearla
$check = $conn->query("SHOW COLUMNS FROM direcciones_respaldo LIKE 'fecha_respaldo'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE direcciones_respaldo ADD COLUMN fecha_respaldo TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    writeLog("   ✅ Columna fecha_respaldo creada", $log_file);
} else {
    writeLog("   ℹ️ Columna fecha_respaldo ya existe", $log_file);
}

// ============================================
// PASO 1: NORMALIZACIÓN BÁSICA
// ============================================

writeLog("", $log_file);
writeLog("📝 NORMALIZANDO DIRECCIONES...", $log_file);

$result = $conn->query("SELECT id_direccion, direccion FROM direcciones");
$total = $result->num_rows;
$normalizadas = 0;

while ($row = $result->fetch_assoc()) {
    $id = $row['id_direccion'];
    $original = $row['direccion'];
    
    // Normalización simple pero efectiva
    $nueva = strtoupper($original);
    $nueva = str_replace(['BLVDVDDD', 'BLVDDDD', 'BLVDD'], 'BLVD', $nueva);
    $nueva = str_replace('TEXAS773', 'TEXAS 773', $nueva);
    $nueva = str_replace('TX773', 'TX 773', $nueva);
    $nueva = str_replace('USA773', 'USA 773', $nueva);
    $nueva = preg_replace('/\s+/', ' ', $nueva);
    
    if ($nueva != $original) {
        if ($procesar) {
            $stmt = $conn->prepare("UPDATE direcciones SET direccion = ? WHERE id_direccion = ?");
            $stmt->bind_param("si", $nueva, $id);
            $stmt->execute();
            $stmt->close();
        }
        $normalizadas++;
        writeLog("   ID {$id}: {$original} → {$nueva}", $log_file);
    }
}

writeLog("📊 Normalizadas: {$normalizadas}/{$total}", $log_file);

// ============================================
// PASO 2: CORREGIR CÓDIGOS POSTALES
// ============================================

writeLog("", $log_file);
writeLog("📝 CORRIGIENDO CÓDIGOS POSTALES...", $log_file);

$cp_correctos = [
    'PANORAMA VILLAGE' => '77304',
    'CONROE' => '77301',
    'WILLIS' => '77378',
    'MONTGOMERY' => '77356',
    'SPRING' => '77373',
    'THE WOODLANDS' => '77380',
    'CLEVELAND' => '77327',
    'HUNTSVILLE' => '77340'
];

$result = $conn->query("SELECT id_direccion, direccion FROM direcciones");
$corregidos = 0;

while ($row = $result->fetch_assoc()) {
    $id = $row['id_direccion'];
    $direccion = $row['direccion'];
    $nueva = $direccion;
    
    foreach ($cp_correctos as $ciudad => $cp) {
        if (strpos($direccion, $ciudad) !== false) {
            if (preg_match('/\b(\d{5})\b/', $direccion, $matches)) {
                if ($matches[1] != $cp) {
                    $nueva = str_replace($matches[1], $cp, $direccion);
                    if ($procesar) {
                        $stmt = $conn->prepare("UPDATE direcciones SET direccion = ? WHERE id_direccion = ?");
                        $stmt->bind_param("si", $nueva, $id);
                        $stmt->execute();
                        $stmt->close();
                    }
                    $corregidos++;
                    writeLog("   ID {$id}: CP {$matches[1]} → {$cp}", $log_file);
                }
            }
            break;
        }
    }
}

writeLog("📊 Códigos postales corregidos: {$corregidos}", $log_file);

// ============================================
// PASO 3: IDENTIFICAR DUPLICADOS
// ============================================

writeLog("", $log_file);
writeLog("📝 BUSCANDO DIRECCIONES DUPLICADAS...", $log_file);

$result = $conn->query("
    SELECT 
        d1.id_direccion as id1,
        d2.id_direccion as id2,
        d1.id_cliente as cliente,
        d1.direccion as dir1,
        d2.direccion as dir2,
        d1.lat as lat1,
        d1.lng as lng1,
        d2.lat as lat2,
        d2.lng as lng2
    FROM direcciones d1
    JOIN direcciones d2 ON d1.id_cliente = d2.id_cliente AND d1.id_direccion < d2.id_direccion
    WHERE d1.id_cliente IS NOT NULL
");

$duplicados = [];
while ($row = $result->fetch_assoc()) {
    // Calcular distancia
    if ($row['lat1'] && $row['lat2']) {
        $dist = calcularDistancia($row['lat1'], $row['lng1'], $row['lat2'], $row['lng2']);
        if ($dist > 500) continue; // Más de 500m = diferente ubicación
    }
    
    // Normalizar direcciones para comparar
    $dir1 = preg_replace('/\b\d{5}\b/', '', $row['dir1']);
    $dir2 = preg_replace('/\b\d{5}\b/', '', $row['dir2']);
    $dir1 = preg_replace('/[^A-Z0-9]/', '', $dir1);
    $dir2 = preg_replace('/[^A-Z0-9]/', '', $dir2);
    
    if (similar_text($dir1, $dir2) / max(strlen($dir1), strlen($dir2)) > 0.8) {
        $duplicados[] = $row;
    }
}

writeLog("📊 Posibles duplicados encontrados: " . count($duplicados), $log_file);

// ============================================
// PASO 4: MOSTRAR Y CONSOLIDAR
// ============================================

$consolidaciones = [];

foreach ($duplicados as $dup) {
    $maestro = min($dup['id1'], $dup['id2']);
    $duplicado = max($dup['id1'], $dup['id2']);
    
    writeLog("────────────────────────────────────", $log_file);
    writeLog("📌 Cliente: {$dup['cliente']}", $log_file);
    writeLog("   👑 MAESTRO ID {$maestro}: {$dup['dir1']}", $log_file);
    writeLog("   📌 DUPLICADO ID {$duplicado}: {$dup['dir2']}", $log_file);
    
    if ($procesar) {
        $consolidaciones[] = ['maestro' => $maestro, 'duplicado' => $duplicado];
    }
}

// ============================================
// PASO 5: EJECUTAR CONSOLIDACIONES
// ============================================

if ($procesar && !empty($consolidaciones)) {
    writeLog("", $log_file);
    writeLog("🔄 CONSOLIDANDO...", $log_file);
    
    foreach ($consolidaciones as $cons) {
        // Actualizar tablas
        $conn->query("UPDATE IGNORE contratos SET id_direccion = {$cons['maestro']} WHERE id_direccion = {$cons['duplicado']}");
        $conn->query("UPDATE IGNORE servicios SET id_direccion = {$cons['maestro']} WHERE id_direccion = {$cons['duplicado']}");
        $conn->query("UPDATE IGNORE rutas_direcciones SET id_direccion = {$cons['maestro']} WHERE id_direccion = {$cons['duplicado']}");
        
        // Backup y eliminar
        $conn->query("INSERT INTO direcciones_respaldo SELECT *, NOW() FROM direcciones WHERE id_direccion = {$cons['duplicado']}");
        $conn->query("DELETE FROM direcciones WHERE id_direccion = {$cons['duplicado']}");
        
        writeLog("   ✅ ID {$cons['duplicado']} → {$cons['maestro']}", $log_file);
    }
    
    writeLog("📊 Total consolidaciones: " . count($consolidaciones), $log_file);
}

// ============================================
// FUNCIÓN AUXILIAR
// ============================================

function calcularDistancia($lat1, $lng1, $lat2, $lng2) {
    $radio = 6371000;
    $lat1 = deg2rad($lat1);
    $lng1 = deg2rad($lng1);
    $lat2 = deg2rad($lat2);
    $lng2 = deg2rad($lng2);
    
    $dlat = $lat2 - $lat1;
    $dlng = $lng2 - $lng1;
    
    $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlng/2) * sin($dlng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $radio * $c;
}

// ============================================
// REPORTE FINAL
// ============================================

writeLog("", $log_file);
writeLog("==================================================", $log_file);
writeLog("🏁 PROCESO FINALIZADO", $log_file);
writeLog("📋 Log: " . basename($log_file), $log_file);
writeLog("==================================================", $log_file);

$conn->close();

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Listo. Revisa el log: " . basename($log_file) . "\n";
echo str_repeat("=", 50) . "\n";
?>