<?php
/**
 * Script para verificar el estado del CRON GPS
 * Ejecutar manualmente para verificar funcionamiento
 */

ob_start();
chdir(__DIR__ . '/..');
require_once 'config/app.php';
require_once 'autoload.php';

use app\controllers\gps_archiverController;

$archiver = new gps_archiverController();

echo "=== VERIFICACIÓN CRON GPS ===\n\n";

// Verificar conexión
echo "1. Verificando conexión a base de datos...\n";
try {
    $estadisticas = $archiver->obtenerEstadisticas();
    echo "   ✓ Conexión exitosa\n\n";
} catch (Exception $e) {
    echo "   ✗ Error de conexión: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Mostrar estadísticas
echo "2. Estadísticas actuales:\n";
echo "   - Registros en tabla original: " . $estadisticas['total_registros_origen'] . "\n";
echo "   - Total tablas de archivo: " . $estadisticas['total_tablas_archivo'] . "\n\n";

// Verificar tablas mensuales existentes
echo "3. Tablas mensuales existentes:\n";


$sql_tablas = "SELECT 
    table_name AS nombre,
    table_comment AS comentario,
    table_rows AS registros_aprox
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
    AND table_name LIKE 'gps_%_e_d'
ORDER BY table_name DESC";

$tablas = $archiver->ejecutarConsulta($sql_tablas, "", [], "fetchAll");

if (empty($tablas)) {
    echo "   (No hay tablas mensuales creadas aún)\n\n";
} else {
    foreach ($tablas as $tabla) {
        echo "   - {$tabla['nombre']}: {$tabla['registros_aprox']} registros ({$tabla['comentario']})\n";
    }
    echo "\n";
}

// Prueba de procesamiento de un día (simulación)
echo "4. Prueba de procesamiento (simulación):\n";
$fecha_prueba = new DateTime('yesterday');
$fecha_prueba_str = $fecha_prueba->format('Y-m-d');

echo "   Fecha de prueba: $fecha_prueba_str\n";

// Contar registros del día de prueba
$stmt_count = "SELECT COUNT(*) as total 
    FROM gps_estado_dispositivo 
    WHERE DATE(`timestamp`) = :v_fecha";
$param = [':v_fecha' => $fecha_prueba_str];

$total_prueba = $archiver->ejecutarConsulta($stmt_count, "", $param, "fetchColumn");

echo "   Registros encontrados: $total_prueba\n";

if ($total_prueba > 0) {
    echo "   ✓ El día tiene datos para procesar\n";
} else {
    echo "   - El día no tiene datos (puede ser normal)\n";
}

echo "\n=== FIN VERIFICACIÓN ===\n";
?>