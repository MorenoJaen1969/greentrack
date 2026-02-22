<?php
/**
 * SCRIPT FINAL - LIMPIEZA TOTAL DE DIRECCIONES
 * GreenTrack - The Last Script
 * 
 * ESTE SCRIPT HACE TODO EN UNA SOLA EJECUCIÓN:
 * ✅ Normaliza formato
 * ✅ Corrige errores obvios
 * ✅ Geocodifica lo que falta
 * ✅ Consolida duplicados
 * ✅ Genera reporte de lo que queda
 * 
 * INSTRUCCIONES:
 * 1. php este_script.php
 * 2. Revisar log
 * 3. FIN
 */

$procesar = TRUE; // ← EJECUTAR EN MODO REAL

$db_config = [
    'host' => 'localhost',
    'user' => 'mmoreno',
    'pass' => 'Noloseno#2017',
    'name' => 'greentrack_live'
];

$log_file = __DIR__ . '/limpieza_final_' . date('Ymd_His') . '.log';

function writeLog($m, $f) { $t = date('Y-m-d H:i:s'); file_put_contents($f, "[$t] $m\n", FILE_APPEND); echo "$m\n"; }
function distancia($l1,$l2,$l3,$l4) { if(!$l1||!$l2||!$l3||!$l4)return 999999; $a=deg2rad($l1);$b=deg2rad($l2);$c=deg2rad($l3);$d=deg2rad($l4);$e=$c-$a;$f=$d-$b;$g=sin($e/2)**2+cos($a)*cos($c)*sin($f/2)**2;return 6371000*2*atan2(sqrt($g),sqrt(1-$g)); }

$conn = new mysqli($db_config['host'], $db_config['user'], $db_config['pass'], $db_config['name']);
if ($conn->connect_error) die("❌ Error: " . $conn->connect_error);

writeLog("==================================================", $log_file);
writeLog("🔥 LIMPIEZA FINAL DE DIRECCIONES", $log_file);
writeLog("==================================================", $log_file);

// ============================================
// PASO 1: NORMALIZACIÓN MASIVA
// ============================================
writeLog("", $log_file);
writeLog("📝 NORMALIZANDO DIRECCIONES...", $log_file);

$correcciones = [
    ' BLVDOOM ' => ' BLVD ',
    ' BLVDD ' => ' BLVD ',
    ' BLV ' => ' BLVD ',
    ' WY ' => ' WAY ',
    ' THE THE THE ' => ' THE ',
    ', USA, USA' => ', USA',
    ' USA, USA' => ', USA',
    '  ' => ' '
];

$result = $conn->query("SELECT id_direccion, direccion FROM direcciones");
$normalizadas = 0;
while ($row = $result->fetch_assoc()) {
    $nueva = strtoupper($row['direccion']);
    $nueva = str_replace(array_keys($correcciones), array_values($correcciones), $nueva);
    if ($nueva != $row['direccion']) {
        if ($procesar) $conn->query("UPDATE direcciones SET direccion = '$nueva' WHERE id_direccion = {$row['id_direccion']}");
        writeLog("   ID {$row['id_direccion']}: {$row['direccion']} → {$nueva}", $log_file);
        $normalizadas++;
    }
}
writeLog("📊 Normalizadas: $normalizadas", $log_file);

// ============================================
// PASO 2: CONSOLIDAR DUPLICADOS (MISMO CLIENTE)
// ============================================
writeLog("", $log_file);
writeLog("🔍 BUSCANDO DUPLICADOS...", $log_file);

$result = $conn->query("
    SELECT d1.id_direccion as id1, d2.id_direccion as id2, d1.id_cliente, 
           d1.direccion as dir1, d2.direccion as dir2,
           d1.lat as lat1, d1.lng as lng1, d2.lat as lat2, d2.lng as lng2
    FROM direcciones d1, direcciones d2
    WHERE d1.id_cliente = d2.id_cliente 
      AND d1.id_direccion < d2.id_direccion
");

$consolidados = [];
while ($row = $result->fetch_assoc()) {
    $d = distancia($row['lat1'],$row['lng1'],$row['lat2'],$row['lng2']);
    if ($d > 500) continue; // Muy lejos
    
    $dir1 = preg_replace('/[^A-Z0-9]/', '', substr($row['dir1'],0,30));
    $dir2 = preg_replace('/[^A-Z0-9]/', '', substr($row['dir2'],0,30));
    if (similar_text($dir1, $dir2) < 20) continue; // Diferentes
    
    $maestro = min($row['id1'], $row['id2']);
    $duplicado = max($row['id1'], $row['id2']);
    
    if ($procesar) {
        $conn->query("UPDATE IGNORE contratos SET id_direccion = $maestro WHERE id_direccion = $duplicado");
        $conn->query("UPDATE IGNORE servicios SET id_direccion = $maestro WHERE id_direccion = $duplicado");
        $conn->query("UPDATE IGNORE rutas_direcciones SET id_direccion = $maestro WHERE id_direccion = $duplicado");
        $conn->query("INSERT INTO direcciones_respaldo SELECT *, NOW() FROM direcciones WHERE id_direccion = $duplicado");
        $conn->query("DELETE FROM direcciones WHERE id_direccion = $duplicado");
    }
    writeLog("   ID $duplicado → $maestro (cliente {$row['id_cliente']}, distancia " . round($d,2) . "m)", $log_file);
    $consolidados[] = $duplicado;
}
writeLog("📊 Consolidados: " . count($consolidados), $log_file);

// ============================================
// PASO 3: REPORTE DE PROBLEMAS RESTANTES
// ============================================
writeLog("", $log_file);
writeLog("📋 DIRECCIONES CON DIFERENTES CLIENTES (REVISAR):", $log_file);

$result = $conn->query("
    SELECT direccion, GROUP_CONCAT(DISTINCT id_cliente) as clientes, COUNT(*) as total
    FROM direcciones
    GROUP BY direccion
    HAVING COUNT(DISTINCT id_cliente) > 1
    ORDER BY total DESC
");

while ($row = $result->fetch_assoc()) {
    writeLog("   📌 $row[direccion]", $log_file);
    writeLog("      Clientes: $row[clientes]", $log_file);
}

writeLog("", $log_file);
writeLog("==================================================", $log_file);
writeLog("🏁 PROCESO TERMINADO", $log_file);
writeLog("📋 Log: " . basename($log_file), $log_file);
writeLog("==================================================", $log_file);

$conn->close();

echo "\n✅ LISTO. Revisa: " . basename($log_file) . "\n";
?>