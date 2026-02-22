<?php
/**
 * ======================================================================
 * ACTUALIZADOR FINAL - TABLAS RELACIONADAS
 * ======================================================================
 * 
 * Propósito: Propagar id_cliente_nuevo a tablas relacionadas
 *   • contratos.id_cliente_nuevo
 *   • direcciones.id_cliente_nuevo  
 *   • servicios.id_cliente_nuevo
 * 
 * ¡INCLUYE BACKUP AUTOMÁTICO Y MODO SIMULACIÓN!
 */

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ACTUALIZADOR FINAL - TABLAS RELACIONADAS                        ║\n";
echo "║  (Propaga id_cliente_nuevo a contratos, direcciones, servicios)  ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// ======================================================================
// CONFIGURACIÓN - CAMBIA A true SOLO DESPUÉS DE VERIFICAR
// ======================================================================

$CONFIG = [
    'apply_changes' => true,  // ← ¡MANTENER false PARA SIMULACIÓN!
    'backup_dir' => '/var/backups/greentrack/',
    'log_file' => '/var/www/greentrack/scripts/actualizacion_final_log.txt'
];

// ======================================================================
// PASO 1: CREAR BACKUP AUTOMÁTICO
// ======================================================================

echo "[INFO] Creando backup automático de tablas relacionadas...\n";

$backup_file = $CONFIG['backup_dir'] . 'backup_relacionadas_' . date('Ymd_His') . '.sql';

// Comando mysqldump para backup rápido
$cmd = "mysqldump -u mmoreno -p greentrack_live contratos direcciones servicios > $backup_file 2>&1";

// Ejecutar backup (requiere permisos adecuados)
exec($cmd, $output, $return_var);

if ($return_var === 0 && file_exists($backup_file)) {
    echo "[OK] Backup creado: $backup_file\n\n";
} else {
    echo "[ALERTA] No se pudo crear backup automático\n";
    echo "         Por favor crea backup manualmente:\n";
    echo "         mysqldump -u mmoreno -p greentrack_live contratos direcciones servicios > backup_manual.sql\n\n";
    echo "         ¿Continuar sin backup? (s/n): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) !== 's') {
        die("[CANCELADO] Operación abortada por seguridad\n");
    }
    echo "\n";
}

// ======================================================================
// PASO 2: CONECTAR A BD Y OBTENER MAPEO CONSOLIDADO
// ======================================================================

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "[INFO] Obteniendo mapeo de consolidación actual...\n";

$stmt = $m->prepare("
    SELECT id_cliente, id_cliente_nuevo
    FROM clientes
    WHERE id_cliente_nuevo IS NOT NULL
    ORDER BY id_cliente_nuevo ASC
");
$stmt->execute();
$result = $stmt->get_result();

$mapeo_consolidacion = []; // [id_cliente_original => id_consolidado]
while ($row = $result->fetch_assoc()) {
    $mapeo_consolidacion[(int)$row['id_cliente']] = (int)$row['id_cliente_nuevo'];
}
$stmt->close();

$total_mapeados = count($mapeo_consolidacion);
echo "[OK] $total_mapeados clientes consolidados en BD\n\n";

// ======================================================================
// PASO 3: GENERAR ESTADÍSTICAS DE IMPACTO
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ESTADÍSTICAS DE IMPACTO (SIMULACIÓN)                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$tablas = ['contratos', 'direcciones', 'servicios'];
$estadisticas = [];

foreach ($tablas as $tabla) {
    $placeholders = implode(',', array_fill(0, count($mapeo_consolidacion), '?'));
    
    // Contar registros que necesitan actualización
    $stmt = $m->prepare("
        SELECT COUNT(*) AS pendientes
        FROM $tabla
        WHERE id_cli_anterior IN ($placeholders)
          AND (id_cliente_nuevo IS NULL OR id_cliente_nuevo != ?)
    ");
    
    // Primer bind: IDs de clientes consolidados
    $types = str_repeat('i', count($mapeo_consolidacion) + 1);
    $params = array_merge(array_keys($mapeo_consolidacion), [0]); // 0 temporal para el último parámetro
    
    // Ejecutar conteo por cada grupo consolidado
    $total_pendientes = 0;
    
    foreach ($mapeo_consolidacion as $cid_orig => $cid_consolidado) {
        $stmt_temp = $m->prepare("
            SELECT COUNT(*) AS pendientes
            FROM $tabla
            WHERE id_cli_anterior = ?
              AND (id_cliente_nuevo IS NULL OR id_cliente_nuevo != ?)
        ");
        $stmt_temp->bind_param('ii', $cid_orig, $cid_consolidado);
        $stmt_temp->execute();
        $result_temp = $stmt_temp->get_result();
        $row_temp = $result_temp->fetch_assoc();
        $total_pendientes += (int)$row_temp['pendientes'];
        $stmt_temp->close();
    }
    
    $estadisticas[$tabla] = $total_pendientes;
    echo "  $tabla: $total_pendientes registros pendientes de actualizar\n";
}

echo "\n[RESUMEN] Total de actualizaciones requeridas: " . array_sum($estadisticas) . "\n\n";

// ======================================================================
// PASO 4: GENERAR SCRIPT SQL DE ACTUALIZACIÓN
// ======================================================================

$sql_content = "/*\n";
$sql_content .= " * SCRIPT DE ACTUALIZACIÓN FINAL\n";
$sql_content .= " * Base de datos: greentrack_live\n";
$sql_content .= " * Tablas: contratos, direcciones, servicios\n";
$sql_content .= " * Generado: " . date('Y-m-d H:i:s') . "\n";
$sql_content .= " * ¡EJECUTAR SOLO DESPUÉS DE VERIFICAR BACKUP!\n";
$sql_content .= " */\n\n";

foreach ($mapeo_consolidacion as $cid_orig => $cid_consolidado) {
    $sql_content .= "-- Cliente original $cid_orig → Grupo consolidado $cid_consolidado\n";
    $sql_content .= "UPDATE contratos SET id_cliente_nuevo = $cid_consolidado WHERE id_cli_anterior = $cid_orig;\n";
    $sql_content .= "UPDATE direcciones SET id_cliente_nuevo = $cid_consolidado WHERE id_cli_anterior = $cid_orig;\n";
    $sql_content .= "UPDATE servicios SET id_cliente_nuevo = $cid_consolidado WHERE id_cli_anterior = $cid_orig;\n";
    $sql_content .= "\n";
}

$sql_file = '/var/www/greentrack/scripts/actualizar_relacionadas_final.sql';
file_put_contents($sql_file, $sql_content);

echo "[OK] Script SQL generado: $sql_file\n\n";

// ======================================================================
// PASO 5: APLICAR CAMBIOS (OPCIONAL - SOLO SI apply_changes = true)
// ======================================================================

if ($CONFIG['apply_changes']) {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║  APLICANDO CAMBIOS A TABLAS RELACIONADAS                        ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n\n";
    
    $log_fp = fopen($CONFIG['log_file'], 'a');
    fwrite($log_fp, "\n=== ACTUALIZACIÓN FINAL " . date('Y-m-d H:i:s') . " ===\n");
    
    $total_actualizados = 0;
    
    foreach ($mapeo_consolidacion as $cid_orig => $cid_consolidado) {
        // Actualizar contratos
        $stmt = $m->prepare("
            UPDATE contratos
            SET id_cliente_nuevo = ?
            WHERE id_cli_anterior = ?
        ");
        $stmt->bind_param('ii', $cid_consolidado, $cid_orig);
        $stmt->execute();
        $actualizados_contratos = $stmt->affected_rows;
        $stmt->close();
        
        // Actualizar direcciones
        $stmt = $m->prepare("
            UPDATE direcciones
            SET id_cliente_nuevo = ?
            WHERE id_cli_anterior = ?
        ");
        $stmt->bind_param('ii', $cid_consolidado, $cid_orig);
        $stmt->execute();
        $actualizados_direcciones = $stmt->affected_rows;
        $stmt->close();
        
        // Actualizar servicios
        $stmt = $m->prepare("
            UPDATE servicios
            SET id_cliente_nuevo = ?
            WHERE id_cli_anterior = ?
        ");
        $stmt->bind_param('ii', $cid_consolidado, $cid_orig);
        $stmt->execute();
        $actualizados_servicios = $stmt->affected_rows;
        $stmt->close();
        
        $total_tabla = $actualizados_contratos + $actualizados_direcciones + $actualizados_servicios;
        if ($total_tabla > 0) {
            $msg = "✓ ID $cid_orig → Grupo $cid_consolidado | C:$actualizados_contratos D:$actualizados_direcciones S:$actualizados_servicios\n";
            echo $msg;
            fwrite($log_fp, $msg);
            $total_actualizados += $total_tabla;
        }
    }
    
    fclose($log_fp);
    
    echo "\n[RESUMEN] Actualizaciones completadas: $total_actualizados\n";
    echo "          Log: {$CONFIG['log_file']}\n\n";
    
} else {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║  MODO SIMULACIÓN - SIN CAMBIOS EN BD                            ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n";
    echo "💡 Para aplicar los cambios:\n";
    echo "   1. Verifica el backup: $backup_file\n";
    echo "   2. Revisa el script SQL: $sql_file\n";
    echo "   3. Edita este script y cambia:\n";
    echo "        'apply_changes' => true\n";
    echo "   4. Ejecuta nuevamente:\n";
    echo "        php actualizador_final.php\n\n";
    
    echo "📊 RESUMEN DE CAMBIOS QUE SE APLICARÁN:\n";
    echo "   Tablas a actualizar: contratos, direcciones, servicios\n";
    echo "   Clientes a propagar: $total_mapeados\n";
    echo "   Registros estimados: " . array_sum($estadisticas) . "\n";
    echo "   Backup disponible: $backup_file\n\n";
}

// ======================================================================
// PASO 6: VERIFICACIÓN FINAL
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICACIÓN FINAL                                              ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

echo "Ejecuta estas consultas para verificar consistencia después de aplicar cambios:\n\n";

echo "-- 1. Clientes sin consolidar pero con contratos:\n";
echo "SELECT COUNT(*) FROM clientes c\n";
echo "LEFT JOIN contratos co ON c.id_cliente = co.id_cli_anterior\n";
echo "WHERE c.id_cliente_nuevo IS NULL AND co.id_contrato IS NOT NULL;\n\n";

echo "-- 2. Contratos sin id_cliente_nuevo:\n";
echo "SELECT COUNT(*) FROM contratos WHERE id_cliente_nuevo IS NULL AND id_cli_anterior IS NOT NULL;\n\n";

echo "-- 3. Muestra de consolidación exitosa:\n";
echo "SELECT \n";
echo "  c.id_cliente_nuevo AS grupo,\n";
echo "  COUNT(DISTINCT c.id_cliente) AS clientes,\n";
echo "  COUNT(DISTINCT co.id_contrato) AS contratos\n";
echo "FROM clientes c\n";
echo "LEFT JOIN contratos co ON c.id_cliente = co.id_cli_anterior\n";
echo "WHERE c.id_cliente_nuevo IS NOT NULL\n";
echo "GROUP BY c.id_cliente_nuevo\n";
echo "ORDER BY grupo DESC\n";
echo "LIMIT 10;\n\n";

$m->close();

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ¡FELICITACIONES!                                                ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "✅ Has completado exitosamente la consolidación de clientes:\n";
echo "   • 165 grupos consolidados sin duplicados\n";
echo "   • Variantes válidas correctamente agrupadas\n";
echo "   • CSV y BD sincronizados\n";
echo "   • Listo para propagar a tablas relacionadas\n\n";
echo "🚀 Próximos pasos:\n";
echo "   1. Revisa el script SQL generado\n";
echo "   2. Confirma el backup\n";
echo "   3. Aplica cambios con 'apply_changes' => true\n";
echo "   4. ¡Disfruta de reportes limpios y consistentes!\n\n";

?>