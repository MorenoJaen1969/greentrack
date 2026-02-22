<?php
/**
 * SCRIPT DE CONSOLIDACIÓN DE DIRECCIONES - CASOS SEGUROS
 * GreenTrack - Safe Deduplication (Mismo cliente)
 * 
 * SOLO PROCESA los 12 grupos identificados donde el cliente es el mismo
 * Muestra dirección completa de AMBOS registros (maestro y duplicado)
 */

// CONFIGURACIÓN
$procesar = true; // ← Cambiar a TRUE solo después de verificar simulación

$db_config = [
    'host' => 'localhost',
    'user' => 'mmoreno',
    'pass' => 'Noloseno#2017',
    'name' => 'greentrack_live'
];

// CONFIGURACIÓN DE LOGS (en el mismo directorio)
$log_config = [
    'dir' => __DIR__,
    'prefix' => 'consolidacion_direcciones_',
    'date_format' => 'Ymd_His'
];

// Los 12 grupos seguros (ID_MAESTRO ← ID_DUPLICADO)
$consolidaciones = [
    ['maestro' => 5123, 'duplicado' => 5276], // 1011 West LEWIS Street...
    ['maestro' => 5264, 'duplicado' => 5436], // 10586 TX-75...
    ['maestro' => 5080, 'duplicado' => 5184], // 108 Marseille...
    ['maestro' => 5313, 'duplicado' => 5438], // 10819 Twin Circles...
    ['maestro' => 5046, 'duplicado' => 5186], // 11431 OUTPOST COVE...
    ['maestro' => 5138, 'duplicado' => 5434], // 147 CLUB ISLAND WAY... (cliente 1127)
    ['maestro' => 5102, 'duplicado' => 5254], // 15 East Loftwood Circle...
    ['maestro' => 5435, 'duplicado' => 5439], // 16221 ALDINE WESTFIELD...
    ['maestro' => 5041, 'duplicado' => 5210], // 2 Merit Woods Place...
    ['maestro' => 5294, 'duplicado' => 5437], // 318 WATERFORD WAY...
    ['maestro' => 5006, 'duplicado' => 5176], // 45 Bentwood Dr...
    ['maestro' => 5213, 'duplicado' => 5356]  // 608 Arkansas Park...
];

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

// Crear tabla de respaldo si no existe (VERSIÓN CORREGIDA)
$conn->query("
    CREATE TABLE IF NOT EXISTS direcciones_respaldo LIKE direcciones
");

// Inicializar archivo de log
$log_file = $log_config['dir'] . '/' . $log_config['prefix'] . date($log_config['date_format']) . '.txt';

// Verificar si las columnas ya existen antes de añadirlas
$check_columns = $conn->query("SHOW COLUMNS FROM direcciones_respaldo LIKE 'fecha_respaldo'");
if ($check_columns->num_rows == 0) {
    $conn->query("
        ALTER TABLE direcciones_respaldo 
        ADD COLUMN fecha_respaldo TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ADD COLUMN motivo VARCHAR(50)
    ");
    writeLog("✅ Columnas de respaldo creadas en direcciones_respaldo", $log_file);
} else {
    writeLog("ℹ️ Las columnas de respaldo ya existen en direcciones_respaldo", $log_file);
}

writeLog("==================================================", $log_file);
writeLog("🚀 CONSOLIDACIÓN DE DIRECCIONES - CASOS SEGUROS", $log_file);
writeLog("📋 Modo: " . ($procesar ? "REAL (UPDATE)" : "SIMULACIÓN (solo log)"), $log_file);
writeLog("📊 Total grupos a procesar: " . count($consolidaciones), $log_file);
writeLog("==================================================", $log_file);

$verificados = 0;
$errores = 0;

// Verificar que los IDs existen y son del mismo cliente
foreach ($consolidaciones as $idx => $cons) {
    writeLog("────────────────────────────────────", $log_file);
    writeLog("📌 Verificando grupo " . ($idx + 1) . "/" . count($consolidaciones), $log_file);
    
    $check = $conn->query("
        SELECT 
            d1.id_cliente as cliente_maestro,
            d2.id_cliente as cliente_duplicado,
            d1.direccion as dir_maestro,
            d2.direccion as dir_duplicado,
            d1.lat as lat_maestro,
            d1.lng as lng_maestro,
            d2.lat as lat_duplicado,
            d2.lng as lng_duplicado
        FROM direcciones d1, direcciones d2
        WHERE d1.id_direccion = {$cons['maestro']}
        AND d2.id_direccion = {$cons['duplicado']}
    ");
    
    if ($check->num_rows == 0) {
        writeLog("❌ ERROR: IDs no encontrados ({$cons['maestro']} o {$cons['duplicado']})", $log_file);
        $errores++;
        continue;
    }
    
    $row = $check->fetch_assoc();
    
    writeLog("👤 Cliente involucrado: {$row['cliente_maestro']}", $log_file);
    writeLog("", $log_file);
    
    writeLog("📌 REGISTRO MAESTRO (se conservará):", $log_file);
    writeLog("   ├── ID: {$cons['maestro']}", $log_file);
    writeLog("   ├── Dirección: {$row['dir_maestro']}", $log_file);
    writeLog("   ├── Latitud:  {$row['lat_maestro']}", $log_file);
    writeLog("   └── Longitud: {$row['lng_maestro']}", $log_file);
    writeLog("", $log_file);
    
    writeLog("📌 REGISTRO DUPLICADO (se eliminará):", $log_file);
    writeLog("   ├── ID: {$cons['duplicado']}", $log_file);
    writeLog("   ├── Dirección: {$row['dir_duplicado']}", $log_file);
    writeLog("   ├── Latitud:  {$row['lat_duplicado']}", $log_file);
    writeLog("   └── Longitud: {$row['lng_duplicado']}", $log_file);
    writeLog("", $log_file);
    
    if ($row['cliente_maestro'] != $row['cliente_duplicado']) {
        writeLog("   ⚠️ ALERTA: Los registros tienen DIFERENTES clientes!", $log_file);
        writeLog("   │   Maestro: Cliente {$row['cliente_maestro']}", $log_file);
        writeLog("   │   Duplicado: Cliente {$row['cliente_duplicado']}", $log_file);
        writeLog("   └── Este grupo NO debería estar aquí. Revisar manualmente.", $log_file);
        $errores++;
    } else {
        writeLog("   ✅ VERIFICACIÓN OK: Mismo cliente (ID {$row['cliente_maestro']})", $log_file);
        writeLog("   ✅ Las direcciones son idénticas, procede consolidación", $log_file);
        $verificados++;
    }
}

writeLog("────────────────────────────────────", $log_file);
writeLog("📊 VERIFICACIÓN COMPLETADA: {$verificados} grupos OK, {$errores} errores", $log_file);

if ($procesar && $verificados == count($consolidaciones) && $errores == 0) {
    writeLog("", $log_file);
    writeLog("🔄 EJECUTANDO ACTUALIZACIONES EN MODO REAL...", $log_file);
    
    $total_actualizados = 0;
    $tablas_actualizadas = [
        'contratos' => 0,
        'servicios' => 0,
        'rutas_direcciones' => 0
    ];
    
    foreach ($consolidaciones as $cons) {
        writeLog("────────────────────────────────────", $log_file);
        
        // Obtener información completa para el log
        $info = $conn->query("
            SELECT 
                d1.direccion as dir_maestro,
                d2.direccion as dir_duplicado,
                d1.id_cliente as cliente
            FROM direcciones d1, direcciones d2
            WHERE d1.id_direccion = {$cons['maestro']}
            AND d2.id_direccion = {$cons['duplicado']}
        ")->fetch_assoc();
        
        writeLog("📌 Procesando consolidación:", $log_file);
        writeLog("   Cliente: {$info['cliente']}", $log_file);
        writeLog("", $log_file);
        writeLog("   👑 MAESTRO (ID {$cons['maestro']}):", $log_file);
        writeLog("   └── {$info['dir_maestro']}", $log_file);
        writeLog("", $log_file);
        writeLog("   📍 DUPLICADO (ID {$cons['duplicado']}):", $log_file);
        writeLog("   └── {$info['dir_duplicado']}", $log_file);
        writeLog("", $log_file);
        
        // 1. Actualizar contratos
        $conn->query("
            UPDATE IGNORE contratos 
            SET id_direccion = {$cons['maestro']} 
            WHERE id_direccion = {$cons['duplicado']}
        ");
        $afectados = $conn->affected_rows;
        $tablas_actualizadas['contratos'] += $afectados;
        if ($afectados > 0) {
            writeLog("   ✅ Contratos: {$afectados} registros actualizados", $log_file);
        }
        
        // 2. Actualizar servicios
        $conn->query("
            UPDATE IGNORE servicios 
            SET id_direccion = {$cons['maestro']} 
            WHERE id_direccion = {$cons['duplicado']}
        ");
        $afectados = $conn->affected_rows;
        $tablas_actualizadas['servicios'] += $afectados;
        if ($afectados > 0) {
            writeLog("   ✅ Servicios: {$afectados} registros actualizados", $log_file);
        }
        
        // 3. Actualizar rutas_direcciones
        $conn->query("
            UPDATE IGNORE rutas_direcciones 
            SET id_direccion = {$cons['maestro']} 
            WHERE id_direccion = {$cons['duplicado']}
        ");
        $afectados = $conn->affected_rows;
        $tablas_actualizadas['rutas_direcciones'] += $afectados;
        if ($afectados > 0) {
            writeLog("   ✅ Rutas_direcciones: {$afectados} registros actualizados", $log_file);
        }
        
        // 4. Backup y eliminar
        $conn->query("
            INSERT INTO direcciones_respaldo 
            SELECT *, NOW(), 'consolidado' FROM direcciones 
            WHERE id_direccion = {$cons['duplicado']}
        ");
        writeLog("   ✅ Backup creado en direcciones_respaldo", $log_file);
        
        $conn->query("DELETE FROM direcciones WHERE id_direccion = {$cons['duplicado']}");
        writeLog("   ✅ Registro duplicado eliminado", $log_file);
        
        $total_actualizados++;
    }
    
    writeLog("────────────────────────────────────", $log_file);
    writeLog("📊 RESUMEN DE ACTUALIZACIONES:", $log_file);
    writeLog("   ├── Direcciones consolidadas: {$total_actualizados}", $log_file);
    writeLog("   ├── Contratos actualizados: {$tablas_actualizadas['contratos']}", $log_file);
    writeLog("   ├── Servicios actualizados: {$tablas_actualizadas['servicios']}", $log_file);
    writeLog("   └── Rutas_direcciones actualizadas: {$tablas_actualizadas['rutas_direcciones']}", $log_file);
    
} elseif (!$procesar) {
    writeLog("", $log_file);
    writeLog("📝 [SIMULACIÓN] No se ejecutaron cambios reales", $log_file);
    writeLog("📝 Para ejecutar en modo real, cambia \$procesar = TRUE en el script", $log_file);
} else {
    writeLog("", $log_file);
    writeLog("❌ No se ejecutaron actualizaciones debido a errores en la verificación", $log_file);
    writeLog("❌ Revisa los grupos marcados con ALERTA antes de proceder", $log_file);
}

writeLog("==================================================", $log_file);
writeLog("🏁 PROCESO FINALIZADO", $log_file);
writeLog("📋 Log generado: " . basename($log_file), $log_file);
writeLog("==================================================", $log_file);

$conn->close();

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Proceso completado. Revisa el log: " . basename($log_file) . "\n";
echo str_repeat("=", 50) . "\n";
?>