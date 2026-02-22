<?php
/**
 * ======================================================================
 * PASO 3 CORREGIDO DEFINITIVO: DESCUBRIDOR DE CLIENTES ACTIVOS
 * ======================================================================
 * 
 * CORRECCIONES CRÍTICAS:
 * ✅ NO asigna ID 1000 fijo - usa PRÓXIMO ID DISPONIBLE desde la BD
 * ✅ NO reinicia secuencia - respeta IDs ya existentes (1147, 1148...)
 * ✅ Actualiza DIRECTAMENTE la BD (clientes + tablas relacionadas)
 * ✅ Agrega al CSV SOLO para auditoría (sin afectar IDs consolidados)
 * ✅ Elimina dependencia del orden del CSV para asignación de IDs
 * 
 * Flujo correcto:
 * 1. Descubrir clientes activos no consolidados
 * 2. Obtener PRÓXIMO ID CONSOLIDADO disponible desde la BD
 * 3. Asignar ese ID directamente en BD (clientes.id_cliente_nuevo)
 * 4. Propagar a tablas relacionadas (direcciones, contratos, servicios)
 * 5. Agregar al CSV solo como registro de auditoría
 */

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  PASO 3 CORREGIDO DEFINITIVO                                     ║\n";
echo "║  (Sin reinicio de secuencia - Respeta IDs existentes en BD)      ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// ======================================================================
// CONFIGURACIÓN
// ======================================================================

$CONFIG = [
    'csv_file' => '/var/www/greentrack/scripts/clientes_nuevo.csv',
    'csv_backup_prefix' => '/var/www/greentrack/scripts/clientes_nuevo_backup_',
    'apply_changes' => true,  // false = simulación, true = aplicar cambios
    'csv_report' => '/var/www/greentrack/scripts/paso3_corregido.csv',
    'min_contratos' => 1
];

// ======================================================================
// PASO 1: OBTENER PRÓXIMO ID CONSOLIDADO DISPONIBLE (DESDE LA BD)
// ======================================================================

echo "[INFO] Obteniendo próximo ID consolidado disponible desde la BD...\n";

$stmt = $m->prepare("
    SELECT COALESCE(MAX(id_cliente_nuevo), 999) AS max_id 
    FROM clientes 
    WHERE id_cliente_nuevo >= 1000
");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$proximo_id = (int)$row['max_id'] + 1;
$stmt->close();

echo "[OK] Próximo ID consolidado disponible: $proximo_id\n";
echo "    (Basado en MAX(id_cliente_nuevo) en la BD, NO en el CSV)\n\n";

// ======================================================================
// PASO 2: BACKUP DEL CSV
// ======================================================================

echo "[INFO] Creando backup del CSV...\n";

if (!file_exists($CONFIG['csv_file'])) {
    die("[ERROR] Archivo CSV no encontrado: {$CONFIG['csv_file']}\n");
}

$backup_file = $CONFIG['csv_backup_prefix'] . 'paso3_def_' . date('Ymd_His') . '.csv';
if (!copy($CONFIG['csv_file'], $backup_file)) {
    die("[ERROR] No se pudo crear backup del CSV\n");
}
echo "[OK] Backup creado: $backup_file\n\n";

// ======================================================================
// PASO 3: LEER CSV PARA EVITAR DUPLICADOS
// ======================================================================

echo "[INFO] Leyendo CSV para evitar duplicados...\n";

$csv_lines = file($CONFIG['csv_file']);
$header_csv = array_shift($csv_lines);

$ids_en_csv = [];
foreach ($csv_lines as $line) {
    $parts = str_getcsv(trim($line), ';');
    if (count($parts) < 2) continue;
    
    $dest_id = (int)trim($parts[0]);
    $ids_en_csv[$dest_id] = true;
    
    if (!empty($parts[2])) {
        $source_ids = array_map('trim', explode(',', $parts[2]));
        foreach ($source_ids as $id) {
            if (is_numeric($id) && $id > 0) {
                $ids_en_csv[(int)$id] = true;
            }
        }
    }
}
echo "[OK] " . count($ids_en_csv) . " IDs ya presentes en CSV\n\n";

// ======================================================================
// PASO 4: BUSCAR CLIENTES ACTIVOS NO CONSOLIDADOS
// ======================================================================

echo "[INFO] Buscando clientes con contratos y sin consolidar...\n";

$stmt = $m->prepare("
    SELECT 
        c.id_cliente,
        c.nombre,
        COUNT(co.id_contrato) AS total_contratos
    FROM clientes c
    LEFT JOIN contratos co ON c.id_cliente = co.id_cli_anterior
    WHERE c.id_cliente_nuevo IS NULL
      AND c.nombre IS NOT NULL
      AND TRIM(c.nombre) != ''
    GROUP BY c.id_cliente, c.nombre
    HAVING COUNT(co.id_contrato) >= ?
    ORDER BY c.nombre ASC
");
$stmt->bind_param('i', $CONFIG['min_contratos']);
$stmt->execute();
$result = $stmt->get_result();

$clientes_activos = [];
while ($row = $result->fetch_assoc()) {
    if (isset($ids_en_csv[(int)$row['id_cliente']])) continue;
    
    $clientes_activos[] = [
        'id_cliente' => (int)$row['id_cliente'],
        'nombre' => $row['nombre'],
        'total_contratos' => (int)$row['total_contratos'],
        'grupo_id' => $proximo_id++  // ✅ Asignar ID único desde secuencia BD
    ];
}
$stmt->close();

$total_activos = count($clientes_activos);
echo "[OK] $total_activos clientes activos no consolidados encontrados\n\n";

if ($total_activos === 0) {
    echo "[INFO] No hay clientes activos pendientes\n";
    $m->close();
    exit(0);
}

// ======================================================================
// PASO 5: MOSTRAR EJEMPLOS
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  CLIENTES ACTIVOS A CONSOLIDAR                                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

foreach ($clientes_activos as $cliente) {
    echo "ID {$cliente['id_cliente']} | Grupo {$cliente['grupo_id']} | ";
    echo "'{$cliente['nombre']}' | Contratos: {$cliente['total_contratos']}\n";
}
echo "\n";

// ======================================================================
// PASO 6: GENERAR REPORTE
// ======================================================================

$fp = fopen($CONFIG['csv_report'], 'w');
fputcsv($fp, ['ID_CLIENTE', 'NOMBRE', 'CONTRATOS', 'GRUPO_ID_ASIGNADO'], ';');

foreach ($clientes_activos as $cliente) {
    fputcsv($fp, [
        $cliente['id_cliente'],
        $cliente['nombre'],
        $cliente['total_contratos'],
        $cliente['grupo_id']
    ], ';');
}
fclose($fp);

echo "[OK] Reporte generado: {$CONFIG['csv_report']}\n\n";

// ======================================================================
// PASO 7: APLICAR CAMBIOS COMPLETOS (OPCIONAL)
// ======================================================================

if ($CONFIG['apply_changes']) {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║  APLICANDO CAMBIOS COMPLETOS                                     ║\n";
    echo "║  (BD + CSV + Tablas relacionadas)                                ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n\n";
    
    // --------------------------------------------------------------
    // 7A: ACTUALIZAR TABLA CLIENTES
    // --------------------------------------------------------------
    echo "[BD] Actualizando tabla clientes...\n";
    
    $clientes_actualizados = 0;
    foreach ($clientes_activos as $cliente) {
        $stmt = $m->prepare("
            UPDATE clientes
            SET id_cliente_nuevo = ?
            WHERE id_cliente = ?
              AND id_cliente_nuevo IS NULL
        ");
        $stmt->bind_param('ii', $cliente['grupo_id'], $cliente['id_cliente']);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo "  ✓ Cliente {$cliente['id_cliente']} → Grupo {$cliente['grupo_id']}\n";
            $clientes_actualizados++;
        }
        $stmt->close();
    }
    echo "  Total actualizados: $clientes_actualizados\n\n";
    
    // --------------------------------------------------------------
    // 7B: ACTUALIZAR TABLA CONTRATOS
    // --------------------------------------------------------------
    echo "[BD] Actualizando tabla contratos...\n";
    
    $contratos_actualizados = 0;
    foreach ($clientes_activos as $cliente) {
        $stmt = $m->prepare("
            UPDATE contratos
            SET id_cliente_nuevo = ?
            WHERE id_cli_anterior = ?
        ");
        $stmt->bind_param('ii', $cliente['grupo_id'], $cliente['id_cliente']);
        $stmt->execute();
        $contratos_actualizados += $stmt->affected_rows;
        $stmt->close();
    }
    echo "  Total actualizados: $contratos_actualizados\n\n";
    
    // --------------------------------------------------------------
    // 7C: ACTUALIZAR TABLA DIRECCIONES
    // --------------------------------------------------------------
    echo "[BD] Actualizando tabla direcciones...\n";
    
    $direcciones_actualizadas = 0;
    foreach ($clientes_activos as $cliente) {
        $stmt = $m->prepare("
            UPDATE direcciones
            SET id_cliente_nuevo = ?
            WHERE id_cli_anterior = ?
        ");
        $stmt->bind_param('ii', $cliente['grupo_id'], $cliente['id_cliente']);
        $stmt->execute();
        $direcciones_actualizadas += $stmt->affected_rows;
        $stmt->close();
    }
    echo "  Total actualizados: $direcciones_actualizadas\n\n";
    
    // --------------------------------------------------------------
    // 7D: ACTUALIZAR TABLA SERVICIOS
    // --------------------------------------------------------------
    echo "[BD] Actualizando tabla servicios...\n";
    
    $servicios_actualizados = 0;
    foreach ($clientes_activos as $cliente) {
        $stmt = $m->prepare("
            UPDATE servicios
            SET id_cliente_nuevo = ?
            WHERE id_cli_anterior = ?
        ");
        $stmt->bind_param('ii', $cliente['grupo_id'], $cliente['id_cliente']);
        $stmt->execute();
        $servicios_actualizados += $stmt->affected_rows;
        $stmt->close();
    }
    echo "  Total actualizados: $servicios_actualizados\n\n";
    
    // --------------------------------------------------------------
    // 7E: ACTUALIZAR CSV (SOLO PARA AUDITORÍA)
    // --------------------------------------------------------------
    echo "[CSV] Actualizando CSV para auditoría...\n";
    
    $csv_content = file($CONFIG['csv_file']);
    $header_line = array_shift($csv_content);
    
    $csv_actualizado = [$header_line];
    foreach ($csv_content as $line) {
        $csv_actualizado[] = $line;
    }
    
    foreach ($clientes_activos as $cliente) {
        // ✅ Formato correcto: ID_CLIENTE_REAL;NOMBRE; (sin IDs consolidados en CSV)
        $linea_csv = "{$cliente['id_cliente']};{$cliente['nombre']};\n";
        $csv_actualizado[] = $linea_csv;
        echo "  ✓ Agregado al CSV: {$cliente['id_cliente']} | '{$cliente['nombre']}'\n";
    }
    
    $fp = fopen($CONFIG['csv_file'], 'w');
    foreach ($csv_actualizado as $line) fwrite($fp, $line);
    fclose($fp);
    
    echo "\n[OK] CSV actualizado para auditoría\n\n";
    
    // --------------------------------------------------------------
    // 7F: RESUMEN
    // --------------------------------------------------------------
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║  RESUMEN DE APLICACIÓN                                           ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n";
    echo "  Nuevos grupos creados: $total_activos\n";
    echo "  Rango de IDs asignados: " . ($proximo_id - $total_activos) . " - " . ($proximo_id - 1) . "\n";
    echo "  Clientes actualizados: $clientes_actualizados\n";
    echo "  Contratos actualizados: $contratos_actualizados\n";
    echo "  Direcciones actualizadas: $direcciones_actualizadas\n";
    echo "  Servicios actualizados: $servicios_actualizados\n";
    echo "  CSV actualizado: SÍ (solo para auditoría)\n\n";
    
} else {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║  MODO SIMULACIÓN - SIN CAMBIOS                                   ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n";
    echo "💡 Para aplicar cambios:\n";
    echo "   1. Verifica el reporte: {$CONFIG['csv_report']}\n";
    echo "   2. Confirma que los IDs asignados son secuenciales (ej: 1168, 1169...)\n";
    echo "   3. BACKUP completo de BD:\n";
    echo "      mysqldump -u mmoreno -p greentrack_live > backup_paso3.sql\n";
    echo "   4. Cambia 'apply_changes' => true\n";
    echo "   5. Ejecuta nuevamente\n\n";
    
    echo "⚠️  IMPORTANTE: Este script:\n";
    echo "   • Usa PRÓXIMO ID DISPONIBLE desde la BD (no reinicia en 1000)\n";
    echo "   • Actualiza DIRECTAMENTE la BD (clientes + tablas relacionadas)\n";
    echo "   • Agrega al CSV SOLO para auditoría (sin afectar IDs consolidados)\n\n";
}

// ======================================================================
// VERIFICACIÓN: CONTRATOS PENDIENTES
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICACIÓN: CONTRATOS SIN id_cliente_nuevo                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$stmt_check = $m->prepare("
    SELECT COUNT(*) AS pendientes
    FROM contratos
    WHERE id_cliente_nuevo IS NULL
      AND id_cli_anterior IS NOT NULL
");
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$row_check = $result_check->fetch_assoc();
$stmt_check->close();

echo "Contratos pendientes de consolidar: {$row_check['pendientes']}\n";
echo "💡 Nota: Algunos pueden corresponder a clientes sin consolidar aún\n\n";

// ======================================================================
// ESTADÍSTICAS FINALES
// ======================================================================

$r = $m->query("
    SELECT 
        COUNT(*) AS total_clientes,
        SUM(CASE WHEN id_cliente_nuevo IS NOT NULL THEN 1 ELSE 0 END) AS consolidados
    FROM clientes
    WHERE nombre IS NOT NULL AND TRIM(nombre) != ''
");
$row = $r->fetch_assoc();
$porc = $row['total_clientes'] > 0 ? round(($row['consolidados'] / $row['total_clientes']) * 100, 1) : 0;

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ESTADÍSTICAS FINALES                                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "  Clientes totales: {$row['total_clientes']}\n";
echo "  Clientes consolidados: {$row['consolidados']} ($porc%)\n";
echo "  Próximo ID disponible: $proximo_id\n";
echo "  Backup CSV: $backup_file\n\n";

$m->close();

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  PASO 3 CORREGIDO DEFINITIVO COMPLETADO                          ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "[FIN] Descubridor de clientes activos - Sin reinicio de secuencia\n\n";

?>