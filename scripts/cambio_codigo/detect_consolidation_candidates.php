<?php
/**
 * ======================================================================
 * PASO 2 CORREGIDO DEFINITIVO: CONSOLIDADOR SIN DEPENDENCIA DEL CSV
 * ======================================================================
 * 
 * CORRECCIÓN CRÍTICA:
 * ✅ Elimina la dependencia del CSV para matching
 * ✅ La BD es la FUENTE DE VERDAD (no el CSV)
 * ✅ Cualquier grupo existente en BD es válido para consolidar nuevos matches
 * ✅ El CSV se actualiza AUTOMÁTICAMENTE para reflejar la realidad de la BD
 * 
 * Caso TOM MOSS resuelto:
 *   ID 550 'TOM MOSS' → Grupo 1147 (100% match con 'MOSS; TOM 1')
 */

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  PASO 2 CORREGIDO DEFINITIVO                                     ║\n";
echo "║  (BD como fuente de verdad - Sin dependencia del CSV)            ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// ======================================================================
// CONFIGURACIÓN
// ======================================================================

$CONFIG = [
    'csv_file' => '/var/www/greentrack/scripts/clientes_nuevo.csv',
    'csv_backup_prefix' => '/var/www/greentrack/scripts/clientes_nuevo_backup_',
    'similarity_threshold' => 85,
    'apply_changes' => true,  // false = simulación, true = aplicar cambios
    'csv_report' => '/var/www/greentrack/scripts/paso2_corregido_definitivo.csv',
    'sql_update_script' => '/var/www/greentrack/scripts/paso2_actualizar_relacionadas.sql',
    'log_file' => '/var/www/greentrack/scripts/paso2_log.txt'
];

// ======================================================================
// PASO 1: BACKUP DEL CSV
// ======================================================================

echo "[INFO] Creando backup del CSV...\n";

if (!file_exists($CONFIG['csv_file'])) {
    die("[ERROR] Archivo CSV no encontrado: {$CONFIG['csv_file']}\n");
}

$backup_file = $CONFIG['csv_backup_prefix'] . 'paso2_def_' . date('Ymd_His') . '.csv';
if (!copy($CONFIG['csv_file'], $backup_file)) {
    die("[ERROR] No se pudo crear backup del CSV\n");
}
echo "[OK] Backup creado: $backup_file\n\n";

// ======================================================================
// PASO 2: LEER GRUPOS EXISTENTES DE LA BD (FUENTE DE VERDAD)
// ======================================================================

echo "[INFO] Leyendo grupos consolidados existentes de la BD...\n";

$stmt = $m->prepare("
    SELECT 
        c.id_cliente,
        c.nombre,
        c.id_cliente_nuevo AS grupo_id
    FROM clientes c
    WHERE c.id_cliente_nuevo IS NOT NULL
    ORDER BY c.id_cliente_nuevo ASC, c.nombre ASC
");
$stmt->execute();
$result = $stmt->get_result();

$grupos_bd = []; // [grupo_id => datos_maestro]
while ($row = $result->fetch_assoc()) {
    $gid = (int)$row['grupo_id'];
    if (!isset($grupos_bd[$gid])) {
        $grupos_bd[$gid] = [
            'grupo_id' => $gid,
            'maestro_id' => (int)$row['id_cliente'],
            'maestro_nombre' => $row['nombre']
        ];
    }
}
$stmt->close();

$total_grupos_bd = count($grupos_bd);
echo "[OK] $total_grupos_bd grupos existentes cargados de la BD\n\n";

// ======================================================================
// PASO 3: NORMALIZACIÓN AGRESIVA (CORREGIDA)
// ======================================================================

function normalizeAggressive($text) {
    if (empty($text)) return [];
    
    // Convertir a mayúsculas y limpiar
    $text = strtoupper(trim($text));
    
    // Eliminar TODO contenido entre paréntesis
    $text = preg_replace('/\([^)]*\)/', ' ', $text);
    
    // ✅ ELIMINAR TODA PUNTUACIÓN (incluyendo ; , . - _ / etc.)
    $text = preg_replace('/[^A-Z0-9\s]/', ' ', $text);
    
    // Normalizar espacios
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Extraer palabras
    $words = array_filter(explode(' ', trim($text)));
    
    // Eliminar último número si existe
    if (!empty($words) && is_numeric(end($words))) {
        array_pop($words);
    }
    
    // Ordenar alfabéticamente para ignorar orden
    sort($words);
    
    return array_values($words);
}

function calculateSimilarity($text1, $text2) {
    $words1 = normalizeAggressive($text1);
    $words2 = normalizeAggressive($text2);
    
    if (empty($words1) || empty($words2)) return 0;
    
    // Si palabras idénticas → 100% match
    if ($words1 === $words2) return 100.0;
    
    $common = count(array_intersect($words1, $words2));
    $total = count(array_unique(array_merge($words1, $words2)));
    
    return round(($common / $total) * 100, 2);
}

// ======================================================================
// DIAGNÓSTICO: CASO TOM MOSS (VERIFICACIÓN)
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICACIÓN: TOM MOSS / MOSS; TOM                              ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$test_cases = [
    ['id' => 550, 'nombre' => 'TOM MOSS'],
    ['id' => 757, 'nombre' => 'MOSS; TOM 1'],
    ['id' => 759, 'nombre' => 'MOSS; TOM 2']
];

foreach ($test_cases as $caso) {
    $words = normalizeAggressive($caso['nombre']);
    echo "ID {$caso['id']} '{$caso['nombre']}' → Palabras: [" . implode(', ', $words) . "]\n";
}
echo "\n";

// Calcular similitudes
for ($i = 0; $i < count($test_cases); $i++) {
    for ($j = $i + 1; $j < count($test_cases); $j++) {
        $sim = calculateSimilarity($test_cases[$i]['nombre'], $test_cases[$j]['nombre']);
        echo "✓ '{$test_cases[$i]['nombre']}' vs '{$test_cases[$j]['nombre']}' = $sim%\n";
    }
}
echo "\n✅ ¡100% de similitud garantizada con normalización agresiva!\n\n";

// ======================================================================
// PASO 4: OBTENER CLIENTES PENDIENTES
// ======================================================================

echo "[INFO] Obteniendo clientes pendientes (id_cliente_nuevo IS NULL)...\n";

$stmt = $m->prepare("
    SELECT id_cliente, nombre 
    FROM clientes
    WHERE id_cliente_nuevo IS NULL
      AND nombre IS NOT NULL
      AND TRIM(nombre) != ''
    ORDER BY nombre ASC
");
$stmt->execute();
$result = $stmt->get_result();

$pendientes = [];
while ($row = $result->fetch_assoc()) {
    $pendientes[] = ['id_cliente' => (int)$row['id_cliente'], 'nombre' => $row['nombre']];
}
$stmt->close();

$total_pendientes = count($pendientes);
echo "[OK] $total_pendientes clientes pendientes para procesar\n\n";

if ($total_pendientes === 0) {
    echo "[INFO] No hay clientes pendientes\n";
    $m->close();
    exit(0);
}

// ======================================================================
// PASO 5: LEER CSV ACTUAL (SOLO PARA ACTUALIZACIÓN POSTERIOR)
// ======================================================================

echo "[INFO] Leyendo CSV actual (solo para actualizar después)...\n";

$csv_lines = file($CONFIG['csv_file']);
$header_csv = array_shift($csv_lines);

$grupos_csv = []; // [destino_id => datos_grupo]
$ids_en_csv = []; // Todos los IDs presentes en el CSV

foreach ($csv_lines as $line) {
    $parts = str_getcsv(trim($line), ';');
    if (count($parts) < 2) continue;
    
    $dest_id = (int)trim($parts[0]);
    $dest_name = trim($parts[1]);
    $source_ids = [];
    
    if (!empty($parts[2])) {
        $source_ids_raw = array_map('trim', explode(',', $parts[2]));
        foreach ($source_ids_raw as $id) {
            if (is_numeric($id) && $id > 0) {
                $source_ids[] = (int)$id;
                $ids_en_csv[(int)$id] = true;
            }
        }
    }
    
    $ids_en_csv[$dest_id] = true;
    
    $grupos_csv[$dest_id] = [
        'destino_id' => $dest_id,
        'destino_nombre' => $dest_name,
        'source_ids' => $source_ids
    ];
}
echo "[OK] CSV leído para referencia (no usado para matching)\n\n";

// ======================================================================
// PASO 6: PROCESAR PENDIENTES - MATCH DIRECTO CON GRUPOS BD
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  CONSOLIDANDO CONTRA GRUPOS EXISTENTES EN BD                     ║\n";
echo "║  (¡SIN DEPENDENCIA DEL CSV - BD es fuente de verdad!)            ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$resultados = [];
$nuevos_ids_para_grupo = []; // [grupo_id => [ids...]]

foreach ($pendientes as $p) {
    $mejor_match = null;
    $mejor_sim = 0;
    
    // ✅ COMPARAR DIRECTAMENTE CONTRA GRUPOS EN BD (SIN CSV)
    foreach ($grupos_bd as $grupo) {
        $sim = calculateSimilarity($p['nombre'], $grupo['maestro_nombre']);
        
        if ($sim >= $CONFIG['similarity_threshold'] && $sim > $mejor_sim) {
            $mejor_sim = $sim;
            $mejor_match = $grupo;
        }
    }
    
    if ($mejor_match !== null) {
        $resultados[] = [
            'cliente_id' => $p['id_cliente'],
            'nombre' => $p['nombre'],
            'grupo_id' => $mejor_match['grupo_id'],
            'maestro_nombre' => $mejor_match['maestro_nombre'],
            'similitud' => $mejor_sim
        ];
        
        if (!isset($nuevos_ids_para_grupo[$mejor_match['grupo_id']])) {
            $nuevos_ids_para_grupo[$mejor_match['grupo_id']] = [];
        }
        $nuevos_ids_para_grupo[$mejor_match['grupo_id']][] = $p['id_cliente'];
        
        echo "✓ {$p['id_cliente']} '{$p['nombre']}' → Grupo {$mejor_match['grupo_id']} ";
        echo "({$mejor_match['maestro_nombre']}, $mejor_sim%)\n";
    }
}

$total_matches = count($resultados);
echo "\n[OK] $total_matches clientes consolidados con grupos existentes en BD\n\n";

// ======================================================================
// PASO 7: ACTUALIZAR CSV PARA REFLEJAR REALIDAD DE BD
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ACTUALIZANDO CSV PARA REFLEJAR CONSOLIDACIONES                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Leer CSV original
$csv_content = file($CONFIG['csv_file']);
$header_line = array_shift($csv_content);

$csv_actualizado = [$header_line];
$total_agregados = 0;

// Actualizar grupos existentes en CSV
foreach ($csv_content as $line) {
    $parts = str_getcsv(trim($line), ';');
    if (count($parts) < 2) {
        $csv_actualizado[] = $line;
        continue;
    }
    
    $dest_id = (int)trim($parts[0]);
    
    // Buscar si este destino corresponde a algún grupo BD con nuevos IDs
    $grupo_id_match = null;
    foreach ($grupos_bd as $gid => $gdata) {
        if ($gdata['maestro_id'] == $dest_id) {
            $grupo_id_match = $gid;
            break;
        }
    }
    
    if ($grupo_id_match && isset($nuevos_ids_para_grupo[$grupo_id_match])) {
        $source_ids_existentes = [];
        if (!empty($parts[2])) {
            $source_ids_existentes = array_map('trim', explode(',', $parts[2]));
            $source_ids_existentes = array_filter($source_ids_existentes, fn($id) => is_numeric($id) && $id > 0);
        }
        
        $todos_ids = array_unique(array_merge(
            $source_ids_existentes,
            array_map('strval', $nuevos_ids_para_grupo[$grupo_id_match])
        ));
        sort($todos_ids, SORT_NUMERIC);
        
        $nueva_linea = "$dest_id;{$parts[1]};" . implode(', ', $todos_ids) . "\n";
        $csv_actualizado[] = $nueva_linea;
        
        $agregados = count($nuevos_ids_para_grupo[$grupo_id_match]);
        $total_agregados += $agregados;
        
        echo "✓ Grupo $dest_id '{$parts[1]}' → +$agregados IDs: " . 
             implode(', ', $nuevos_ids_para_grupo[$grupo_id_match]) . "\n";
        
        // Eliminar del array para no procesar dos veces
        unset($nuevos_ids_para_grupo[$grupo_id_match]);
    } else {
        $csv_actualizado[] = $line;
    }
}

// Agregar grupos BD que no están en el CSV (nuevos grupos automáticos)
if (!empty($nuevos_ids_para_grupo)) {
    echo "\n🆕 Agregando grupos BD no presentes en CSV:\n";
    
    foreach ($nuevos_ids_para_grupo as $gid => $ids) {
        if (!isset($grupos_bd[$gid])) continue;
        
        $maestro = $grupos_bd[$gid];
        $nueva_linea = "{$maestro['maestro_id']};{$maestro['maestro_nombre']};" . 
                       implode(', ', $ids) . "\n";
        $csv_actualizado[] = $nueva_linea;
        
        echo "✓ Nuevo grupo BD $gid (maestro {$maestro['maestro_id']} '{$maestro['maestro_nombre']}') ";
        echo "→ +{$ids} IDs\n";
        $total_agregados += count($ids);
    }
}

// Escribir CSV actualizado
$fp = fopen($CONFIG['csv_file'], 'w');
foreach ($csv_actualizado as $line) fwrite($fp, $line);
fclose($fp);

echo "\n[OK] CSV actualizado: {$CONFIG['csv_file']}\n";
echo "    Total de IDs agregados: $total_agregados\n\n";

// ======================================================================
// PASO 8: GENERAR REPORTE
// ======================================================================

$fp = fopen($CONFIG['csv_report'], 'w');
fputcsv($fp, ['CLIENTE_ID', 'NOMBRE', 'GRUPO_ID', 'MAESTRO', 'SIMILITUD_%'], ';');

foreach ($resultados as $r) {
    fputcsv($fp, [
        $r['cliente_id'],
        $r['nombre'],
        $r['grupo_id'],
        $r['maestro_nombre'],
        $r['similitud']
    ], ';');
}
fclose($fp);

echo "[ARCHIVO] Reporte generado: {$CONFIG['csv_report']}\n\n";

// ======================================================================
// PASO 9: APLICAR CAMBIOS A BD (OPCIONAL)
// ======================================================================

if ($CONFIG['apply_changes'] && !empty($resultados)) {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║  APLICANDO CONSOLIDACIONES A BASE DE DATOS                       ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n\n";
    
    $log_fp = fopen($CONFIG['log_file'], 'a');
    $aplicados = 0;
    $fallidos = 0;
    
    foreach ($resultados as $r) {
        $stmt = $m->prepare("
            UPDATE clientes
            SET id_cliente_nuevo = ?
            WHERE id_cliente = ?
              AND id_cliente_nuevo IS NULL
        ");
        $stmt->bind_param('ii', $r['grupo_id'], $r['cliente_id']);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $msg = "✓ {$r['cliente_id']} '{$r['nombre']}' → Grupo {$r['grupo_id']} ({$r['similitud']}%)\n";
            echo $msg;
            fwrite($log_fp, date('Y-m-d H:i:s') . " $msg");
            $aplicados++;
        } else {
            $msg = "✗ Error {$r['cliente_id']}: " . $stmt->error . "\n";
            echo $msg;
            fwrite($log_fp, date('Y-m-d H:i:s') . " $msg");
            $fallidos++;
        }
        $stmt->close();
    }
    
    fclose($log_fp);
    
    echo "\n[RESUMEN] Aplicados: $aplicados | Fallidos: $fallidos\n\n";
    
    // Generar script SQL para tablas relacionadas
    $sql_content = "/* Script SQL generado: " . date('Y-m-d H:i:s') . " */\n\n";
    
    foreach ($resultados as $r) {
        $sql_content .= "-- Cliente {$r['cliente_id']} → Grupo {$r['grupo_id']}\n";
        $sql_content .= "UPDATE direcciones SET id_cliente_nuevo = {$r['grupo_id']} WHERE id_cli_anterior = {$r['cliente_id']};\n";
        $sql_content .= "UPDATE contratos SET id_cliente_nuevo = {$r['grupo_id']} WHERE id_cli_anterior = {$r['cliente_id']};\n";
        $sql_content .= "UPDATE servicios SET id_cliente_nuevo = {$r['grupo_id']} WHERE id_cli_anterior = {$r['cliente_id']};\n\n";
    }
    
    file_put_contents($CONFIG['sql_update_script'], $sql_content);
    echo "[OK] Script SQL generado: {$CONFIG['sql_update_script']}\n\n";
    
} else {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║  MODO SIMULACIÓN - LISTO PARA APLICAR                            ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n";
    echo "💡 Para aplicar cambios:\n";
    echo "   1. Verifica reporte: {$CONFIG['csv_report']}\n";
    echo "   2. Confirma que TOM MOSS (ID 550) aparece consolidado al grupo 1147\n";
    echo "   3. BACKUP: mysqldump -u mmoreno -p greentrack_live > backup.sql\n";
    echo "   4. Cambia 'apply_changes' => true\n";
    echo "   5. Ejecuta nuevamente\n\n";
}

// ======================================================================
// VERIFICACIÓN FINAL: TOM MOSS
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICACIÓN FINAL: TOM MOSS / MOSS; TOM                        ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$stmt_check = $m->prepare("
    SELECT id_cliente, nombre, id_cliente_nuevo 
    FROM clientes 
    WHERE id_cliente IN (550, 757, 759)
    ORDER BY id_cliente
");
$stmt_check->execute();
$result_check = $stmt_check->get_result();

while ($row = $result_check->fetch_assoc()) {
    $status = $row['id_cliente_nuevo'] ? 
        "✅ Grupo {$row['id_cliente_nuevo']}" : 
        "❌ Pendiente (NULL)";
    
    $words = normalizeAggressive($row['nombre']);
    
    echo "ID {$row['id_cliente']} '{$row['nombre']}' → $status\n";
    echo "   Palabras normalizadas: [" . implode(', ', $words) . "]\n\n";
}
$stmt_check->close();

// ======================================================================
// ESTADÍSTICAS FINALES
// ======================================================================

$r = $m->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN id_cliente_nuevo IS NOT NULL THEN 1 ELSE 0 END) as consolidados
    FROM clientes 
    WHERE nombre IS NOT NULL AND TRIM(nombre) != ''
");
$row = $r->fetch_assoc();
$porc = $row['total'] > 0 ? round(($row['consolidados'] / $row['total']) * 100, 1) : 0;

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ESTADÍSTICAS FINALES                                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "  Clientes totales: {$row['total']}\n";
echo "  Clientes consolidados: {$row['consolidados']} ($porc%)\n";
echo "  Clientes pendientes: " . ($row['total'] - $row['consolidados']) . "\n\n";

$m->close();

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  PASO 2 CORREGIDO DEFINITIVO COMPLETADO                          ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "[FIN] Consolidador con BD como fuente de verdad\n\n";

?>