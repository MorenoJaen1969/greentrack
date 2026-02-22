<?php
/**
 * Script para migrar datos históricos existentes a tablas mensuales
 * Ejecutar una sola vez después de crear las tablas
 */

ob_start();
chdir(__DIR__ . '/..');
require_once 'config/app.php';
require_once 'autoload.php';

use app\controllers\gps_archiverController;

$archiver = new gps_archiverController();

$archiver->recibir_log("=== INICIO MIGRACIÓN DATOS HISTÓRICOS ===");

try {
    // Obtener rango de fechas en la tabla original
    $range = $archiver->buscar_extremos();
    
    $archiver->recibir_log("Rango de datos: {$range['fecha_minima']} a {$range['fecha_maxima']}");
    $archiver->recibir_log("Total registros a migrar: {$range['total_registros']}");
    
    // Calcular fecha límite: hoy - 30 días
    $fecha_limite = new DateTime();
    $fecha_limite->modify('-30 days');
    $fecha_limite_str = $fecha_limite->format('Y-m-d');
    
    $archiver->recibir_log("Fecha límite para migración: $fecha_limite_str (hoy - 30 días)");
    
    // Procesar por meses completos hasta la fecha límite
    $fecha_inicio = new DateTime($range['fecha_minima']);
    $mes_actual = clone $fecha_inicio;
    $total_migrados = 0;
    
    while ($mes_actual <= $fecha_limite) {
        $info_fecha = $archiver->extraerInfoFecha($mes_actual->format('Y-m-d'));
        $nombre_tabla = $archiver->generarNombreTabla($info_fecha['mes'], $info_fecha['anio']);
        
        $archiver->recibir_log("Procesando mes: {$info_fecha['mes_texto']} {$info_fecha['anio']} ($nombre_tabla)");
        
        // Calcular rango del mes
        $primer_dia = $mes_actual->format('Y-m-01');
        
        // Si es el último mes, usar fecha límite en lugar del último día del mes
        if ($mes_actual->format('Y-m') === $fecha_limite->format('Y-m')) {
            $ultimo_dia = $fecha_limite_str;
            $archiver->recibir_log("  Último mes parcial: $primer_dia a $ultimo_dia");
        } else {
            $ultimo_dia = $mes_actual->format('Y-m-t');
            $archiver->recibir_log("  Mes completo: $primer_dia a $ultimo_dia");
        }
        
        $migrados = $archiver->migrar_lote($nombre_tabla, $primer_dia, $ultimo_dia, $info_fecha['mes_texto'], $info_fecha['anio']);
        $total_migrados += $migrados;
        
        $mes_actual->modify('+1 month');
    }
    
    $archiver->recibir_log("=== RESUMEN ===");
    $archiver->recibir_log("Total registros migrados: $total_migrados");
    
    // Estadísticas finales
    $estadisticas = $archiver->obtenerEstadisticas();
    $archiver->recibir_log("Registros restantes en tabla original: {$estadisticas['total_registros_origen']}");
    $archiver->recibir_log("Total tablas de archivo: {$estadisticas['total_tablas_archivo']}");
    
    $archiver->recibir_log("=== FIN MIGRACIÓN DATOS HISTÓRICOS ===");
    
} catch (Exception $e) {
    $archiver->recibir_log("✗ ERROR CRÍTICO: " . $e->getMessage(), true);
    $archiver->recibir_log("Stack trace: " . $e->getTraceAsString(), true);
    exit(1);
}
?>