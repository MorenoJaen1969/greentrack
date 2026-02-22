<?php
/**
 * Script para crear tablas mensuales de meses anteriores
 * Ejecutar una sola vez para inicializar el sistema
 */
ob_start();
chdir(__DIR__ . '/..');
require_once 'config/app.php';
require_once 'autoload.php';

use app\controllers\gps_archiverController;

$archiver = new gps_archiverController();

try {
    // Definir rango de fechas (desde septiembre 2025 hasta mes actual)
    $fecha_inicio = new DateTime('2025-06-01');
    $fecha_fin = new DateTime('first day of this month');
    
    $archiver->recibir_log("Rango de fechas: " . $fecha_inicio->format('Y-m-d') . " a " . $fecha_fin->format('Y-m-d'));
    
    $meses_procesados = [];
    $fecha_actual = clone $fecha_inicio;
    
    while ($fecha_actual <= $fecha_fin) {
        $info_fecha = $archiver->extraerInfoFecha($fecha_actual->format('Y-m-d'));
        $nombre_tabla = $archiver->generarNombreTabla($info_fecha['mes'], $info_fecha['anio']);
        
        if (!in_array($nombre_tabla, $meses_procesados)) {
            $tabla_existe = $archiver->tablaExiste($nombre_tabla);
            
            if (!$tabla_existe) {
                $archiver->recibir_log("Creando tabla: $nombre_tabla");
                $archiver->crearTablaMensual($nombre_tabla, $info_fecha['mes_texto'], $info_fecha['anio']);
                $archiver->recibir_log("✓ Tabla $nombre_tabla creada");
            } else {
                $archiver->recibir_log("✓ Tabla $nombre_tabla ya existe");
            }
            
            $meses_procesados[] = $nombre_tabla;
        }
        
        $fecha_actual->modify('+1 month');
    }
    
    $archiver->recibir_log("=== RESUMEN ===");
    $archiver->recibir_log("Total tablas procesadas: " . count($meses_procesados));
    
    // Mostrar tablas existentes
    $tablas = $archiver->obtenerTablasMensuales();
    $archiver->recibir_log("Tablas mensuales existentes:");
    foreach ($tablas as $tabla) {
        $archiver->recibir_log("  - {$tabla['nombre']} ({$tabla['registros']} registros)");
    }
    
    $archiver->recibir_log("=== FIN CREACIÓN TABLAS HISTÓRICAS ===");
    
} catch (Exception $e) {
    $archiver->recibir_log("✗ ERROR CRÍTICO: " . $e->getMessage(), true);
    $archiver->recibir_log("Stack trace: " . $e->getTraceAsString(), true);
    exit(1);
}
?>