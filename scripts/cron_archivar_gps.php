<?php
/**
 * CRON para archivar datos GPS por mes - PROCESAMIENTO DE UN SOLO DÍA
 * Ejecutar diariamente: 0 2 * * * (2:00 AM)
 * 
 * Procesa EXACTAMENTE UN DÍA: el día que cumple 30 días de antigüedad
 * Ejemplo: Si hoy es 2026-02-05, procesa SOLO el día 2026-01-06
 */

ob_start();
chdir(__DIR__ . '/..');
require_once 'config/app.php';
require_once 'autoload.php';

use app\controllers\gps_archiverController;

// Inicializar controlador
$archiver = new gps_archiverController();

// Iniciar logging
$archiver->recibir_log("=== INICIO CRON ARCHIVAR GPS (UN SOLO DÍA) ===");
$archiver->recibir_log("Ejecutado el: " . date('Y-m-d H:i:s'));

try {
    // Calcular fecha a procesar: hoy - 30 días
    $fecha_actual = new DateTime();
    $fecha_procesar = clone $fecha_actual;
    $fecha_procesar->modify('-30 days');
    $fecha_str = $fecha_procesar->format('Y-m-d');
    
    $archiver->recibir_log("Fecha actual: " . $fecha_actual->format('Y-m-d'));
    $archiver->recibir_log("Fecha a procesar (hoy - 30 días): $fecha_str");
    $archiver->recibir_log("Procesando SOLO el día: $fecha_str");
    
    // Procesar SOLO ese día específico
    $resultado = $archiver->procesarDia($fecha_str);
    
    $total_registros_movidos = 0;
    $errores = [];
    
    if ($resultado['success']) {
        $total_registros_movidos = $resultado['registros_movidos'];
        $archiver->recibir_log("✓ Día $fecha_str: {$resultado['registros_movidos']} registros movidos a {$resultado['tabla']}");
    } else {
        $error_msg = "Día $fecha_str: {$resultado['message']}";
        $errores[] = $error_msg;
        $archiver->recibir_log("✗ Día $fecha_str: {$resultado['message']}", true);
    }
    
    // Resumen final
    $archiver->recibir_log("=== RESUMEN DE EJECUCIÓN ===");
    $archiver->recibir_log("Día procesado: $fecha_str");
    $archiver->recibir_log("Total registros movidos: $total_registros_movidos");
    $archiver->recibir_log("Errores encontrados: " . count($errores));
    
    if (!empty($errores)) {
        $archiver->recibir_log("=== ERRORES DETALLADOS ===");
        foreach ($errores as $idx => $error) {
            $archiver->recibir_log("Error " . ($idx + 1) . ": $error");
        }
    }
    
    // Estadísticas finales
    $estadisticas = $archiver->obtenerEstadisticas();
    $archiver->recibir_log("=== ESTADÍSTICAS FINALES ===");
    $archiver->recibir_log("Registros restantes en tabla original: {$estadisticas['total_registros_origen']}");
    $archiver->recibir_log("Total tablas de archivo: {$estadisticas['total_tablas_archivo']}");
    
    $archiver->recibir_log("=== FIN CRON ARCHIVAR GPS ===");
    
} catch (Exception $e) {
    $archiver->recibir_log("✗ ERROR CRÍTICO: " . $e->getMessage(), true);
    $archiver->recibir_log("Stack trace: " . $e->getTraceAsString(), true);
    exit(1);
}
?>