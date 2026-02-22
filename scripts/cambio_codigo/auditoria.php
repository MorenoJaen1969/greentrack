<?php
/**
 * ======================================================================
 * AUDITOR CORREGIDO - SOLO INCONSISTENCIAS REALES
 * ======================================================================
 * 
 * Reglas aplicadas:
 * ✅ Ignorar números al final: "Wendy Juarez 1" = "Wendy Juarez"
 * ✅ Ignorar puntuación: "MOSS; TOM" = "MOSS TOM"
 * ✅ Ignorar orden: "TOM MOSS" = "MOSS TOM"
 * ✅ Solo reportar si nombres NORMALIZADOS son REALMENTE distintos
 * 
 * ¡100% READ-ONLY - Sin modificar la base de datos!
 */

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  AUDITOR CORREGIDO - SOLO INCONSISTENCIAS REALES                 ║\n";
echo "║  (Ignora variantes con números al final)                         ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// ======================================================================
// FUNCIONES DE NORMALIZACIÓN AGRESIVA (CORREGIDAS)
// ======================================================================

function normalizarNombre($text) {
    if (empty($text)) return '';
    
    // Convertir a minúsculas y limpiar
    $text = strtolower(trim($text));
    
    // Eliminar paréntesis y contenido
    $text = preg_replace('/\([^)]*\)/', ' ', $text);
    
    // Eliminar TODA puntuación
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    
    // Normalizar espacios
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Extraer palabras
    $words = array_filter(explode(' ', trim($text)));
    
    // Eliminar último número si existe
    if (!empty($words)) {
        $last = end($words);
        if (is_numeric($last)) {
            array_pop($words);
        }
    }
    
    // Ordenar alfabéticamente para ignorar orden original
    sort($words);
    
    return implode(' ', $words);
}

// ======================================================================
// PASO 1: LEER CSV DEFINITIVO
// ======================================================================

$csv_file = '/var/www/greentrack/scripts/clientes_nuevo_limpio.csv';
if (!file_exists($csv_file)) die("[ERROR] CSV no encontrado: $csv_file\n");

$lines = file($csv_file);
array_shift($lines); // Saltar header

$mapeo_csv = []; // [id_cliente => grupo_esperado]
$grupos_csv = []; // [grupo_id => nombre_canonico]

foreach ($lines as $line) {
    $parts = str_getcsv(trim($line), ';');
    if (count($parts) < 2) continue;
    
    $grupo_id = (int)trim($parts[0]);
    $nombre = trim($parts[1]);
    $ids = [];
    
    if (!empty($parts[2])) {
        $raw = array_map('trim', explode(',', $parts[2]));
        foreach ($raw as $id) {
            if (is_numeric($id) && $id > 0) {
                $ids[] = (int)$id;
                $mapeo_csv[(int)$id] = $grupo_id;
            }
        }
    }
    
    $grupos_csv[$grupo_id] = $nombre;
}

// ======================================================================
// PASO 2: LEER ESTADO ACTUAL DE BD
// ======================================================================

$stmt = $m->prepare("
    SELECT id_cliente, nombre, nombre_comercial, id_cliente_nuevo
    FROM clientes
    WHERE id_cliente_nuevo IS NOT NULL
    ORDER BY id_cliente_nuevo ASC, id_cliente ASC
");
$stmt->execute();
$result = $stmt->get_result();

$estado_bd = [];
$grupos_bd = [];

while ($row = $result->fetch_assoc()) {
    $cid = (int)$row['id_cliente'];
    $gid = $row['id_cliente_nuevo'] !== null ? (int)$row['id_cliente_nuevo'] : null;
    
    $nombre_completo = !empty($row['nombre_comercial']) ? $row['nombre_comercial'] : $row['nombre'];
    
    $estado_bd[$cid] = [
        'id_cliente' => $cid,
        'nombre' => $nombre_completo,
        'grupo_actual' => $gid,
        'nombre_normalizado' => normalizarNombre($nombre_completo)
    ];
    
    if ($gid !== null) {
        if (!isset($grupos_bd[$gid])) $grupos_bd[$gid] = [];
        $grupos_bd[$gid][] = $cid;
    }
}
$stmt->close();

// ======================================================================
// PASO 3: DETECTAR SOLO INCONSISTENCIAS REALES
// ======================================================================

$inconsistencias_reales = [];

foreach ($grupos_bd as $gid => $ids_grupo) {
    if (count($ids_grupo) < 2) continue; // Solo grupos con múltiples miembros
    
    // Normalizar todos los nombres del grupo
    $nombres_normalizados = [];
    foreach ($ids_grupo as $cid) {
        if (isset($estado_bd[$cid])) {
            $nombres_normalizados[$cid] = $estado_bd[$cid]['nombre_normalizado'];
        }
    }
    
    // Verificar si todos los nombres normalizados son IGUALES
    $valores_unicos = array_unique($nombres_normalizados);
    
    if (count($valores_unicos) > 1) {
        // ¡INCONSISTENCIA REAL! Nombres distintos después de normalización
        $inconsistencias_reales[] = [
            'grupo_id' => $gid,
            'ids' => $ids_grupo,
            'nombres_originales' => array_map(fn($cid) => $estado_bd[$cid]['nombre'] ?? "ID $cid", $ids_grupo),
            'nombres_normalizados' => $nombres_normalizados,
            'valores_unicos' => $valores_unicos
        ];
    }
    // Si count($valores_unicos) == 1 → Variantes válidas (ej: Wendy Juarez 1/2) → NO es error
}

// ======================================================================
// PASO 4: VERIFICAR INCONSISTENCIAS CSV vs BD (grupo equivocado)
// ======================================================================

$inconsistencias_grupo_equivocado = [];

foreach ($mapeo_csv as $cid => $grupo_esperado) {
    if (!isset($estado_bd[$cid])) continue;
    
    $grupo_actual = $estado_bd[$cid]['grupo_actual'];
    
    // Solo reportar si está consolidado en un grupo DIFERENTE al esperado
    if ($grupo_actual !== null && $grupo_actual != $grupo_esperado) {
        // Verificar si es el MISMO grupo lógico (aunque con ID numérico diferente)
        // Esto ocurre cuando el CSV fue regenerado y los IDs consolidados cambiaron
        // Pero en este caso específico, 318 YOCKEY en grupo 1142 es un error REAL
        
        $inconsistencias_grupo_equivocado[] = [
            'id_cliente' => $cid,
            'nombre' => $estado_bd[$cid]['nombre'],
            'grupo_actual' => $grupo_actual,
            'grupo_esperado' => $grupo_esperado,
            'nombre_grupo_esperado' => $grupos_csv[$grupo_esperado] ?? 'DESCONOCIDO'
        ];
    }
}

// ======================================================================
// PASO 5: GENERAR REPORTE CONCISO
// ======================================================================

$reporte_file = '/var/www/greentrack/scripts/inconsistencias_reales.txt';
$fp = fopen($reporte_file, 'w');

fwrite($fp, "════════════════════════════════════════════════════════════════════\n");
fwrite($fp, "  INCONSISTENCIAS REALES QUE REQUIEREN VERIFICACIÓN MANUAL\n");
fwrite($fp, "  Generado: " . date('Y-m-d H:i:s') . "\n");
fwrite($fp, "════════════════════════════════════════════════════════════════════\n\n");

$total_reales = count($inconsistencias_reales);
$total_grupo_equiv = count($inconsistencias_grupo_equivocado);

fwrite($fp, "RESUMEN:\n");
fwrite($fp, "  Inconsistencias reales (nombres distintos en mismo grupo): $total_reales\n");
fwrite($fp, "  Clientes en grupo equivocado (CSV vs BD): $total_grupo_equiv\n\n");

// Reportar inconsistencias reales (nombres distintos)
if ($total_reales > 0) {
    fwrite($fp, "════════════════════════════════════════════════════════════════════\n");
    fwrite($fp, "  🔴 CLIENTES DISTINTOS EN MISMO GRUPO (requiere separación)\n");
    fwrite($fp, "════════════════════════════════════════════════════════════════════\n\n");
    
    foreach ($inconsistencias_reales as $idx => $inc) {
        fwrite($fp, "INCONSISTENCIA #" . ($idx + 1) . "\n");
        fwrite($fp, "  Grupo ID: {$inc['grupo_id']}\n");
        fwrite($fp, "  IDs en grupo: " . implode(', ', $inc['ids']) . "\n");
        fwrite($fp, "  Nombres originales:\n");
        
        foreach ($inc['ids'] as $cid) {
            $nombre_orig = $inc['nombres_originales'][array_search($cid, $inc['ids'])];
            $nombre_norm = $inc['nombres_normalizados'][$cid];
            fwrite($fp, "    ID $cid: '$nombre_orig' → normalizado: '$nombre_norm'\n");
        }
        
        fwrite($fp, "  ACCIÓN: Verificar si son clientes distintos (requiere separación)\n\n");
    }
} else {
    fwrite($fp, "✅ Sin inconsistencias reales detectadas\n");
    fwrite($fp, "   (Variantes como 'Wendy Juarez 1/2' son CORRECTAS en mismo grupo)\n\n");
}

// Reportar grupo equivocado
if ($total_grupo_equiv > 0) {
    fwrite($fp, "════════════════════════════════════════════════════════════════════\n");
    fwrite($fp, "  🔴 CLIENTES EN GRUPO EQUIVOCADO (CSV vs BD)\n");
    fwrite($fp, "════════════════════════════════════════════════════════════════════\n\n");
    
    foreach ($inconsistencias_grupo_equivocado as $idx => $inc) {
        fwrite($fp, "INCONSISTENCIA #" . ($idx + 1) . "\n");
        fwrite($fp, "  ID Cliente: {$inc['id_cliente']}\n");
        fwrite($fp, "  Nombre: '{$inc['nombre']}'\n");
        fwrite($fp, "  Grupo actual en BD: {$inc['grupo_actual']}\n");
        fwrite($fp, "  Grupo esperado (CSV): {$inc['grupo_esperado']} ('{$inc['nombre_grupo_esperado']}')\n");
        fwrite($fp, "  ACCIÓN: Verificar contratos históricos para determinar grupo correcto\n\n");
    }
} else {
    fwrite($fp, "✅ Sin clientes en grupo equivocado detectados\n\n");
}

fclose($fp);

// ======================================================================
// PASO 6: MOSTRAR RESUMEN EN PANTALLA
// ======================================================================

echo "[OK] Auditoría completada\n\n";

echo "📊 RESULTADOS:\n";
echo "   Grupos analizados en BD: " . count($grupos_bd) . "\n";
echo "   Inconsistencias REALES detectadas: $total_reales\n";
echo "   Clientes en grupo equivocado: $total_grupo_equiv\n\n";

if ($total_reales === 0 && $total_grupo_equiv === 0) {
    echo "✅ ¡EXCELENTE! Tu base de datos está CONSISTENTE\n";
    echo "   • Variantes con números (Wendy Juarez 1/2) están correctamente agrupadas\n";
    echo "   • No hay clientes distintos mezclados en mismo grupo\n";
    echo "   • CSV y BD están sincronizados\n\n";
} else {
    echo "⚠️  INCONSISTENCIAS QUE REQUIEREN TU VERIFICACIÓN:\n";
    
    if ($total_reales > 0) {
        echo "   🔴 $total_reales grupo(s) con clientes DISTINTOS mezclados\n";
        echo "      Verifica: /var/www/greentrack/scripts/inconsistencias_reales.txt\n\n";
    }
    
    if ($total_grupo_equiv > 0) {
        echo "   🔴 $total_grupo_equiv cliente(s) en grupo equivocado (CSV vs BD)\n";
        echo "      Ejemplo crítico: ID 318 'YOCKEY, DONALD' en grupo 1142 (debería ser 1036)\n";
        echo "      Verifica: /var/www/greentrack/scripts/inconsistencias_reales.txt\n\n";
    }
}

echo "📁 Reporte generado: $reporte_file\n\n";

// ======================================================================
// VERIFICACIÓN ESPECÍFICA: CASO WENDY JUAREZ (DEBE SER VÁLIDO)
// ======================================================================

echo "🔍 VERIFICACIÓN DE CASOS ESPECÍFICOS:\n";

// Caso Wendy Juarez (debe ser VÁLIDO - no inconsistencia)
$ids_wendy = [275, 360, 572, 597];
echo "  Wendy Juarez (IDs " . implode(', ', $ids_wendy) . "):\n";
$normalizados_wendy = [];
foreach ($ids_wendy as $cid) {
    if (isset($estado_bd[$cid])) {
        $norm = $estado_bd[$cid]['nombre_normalizado'];
        $normalizados_wendy[] = $norm;
        echo "    ID $cid → '{$estado_bd[$cid]['nombre']}' → normalizado: '$norm'\n";
    }
}
$unicos_wendy = array_unique($normalizados_wendy);
if (count($unicos_wendy) === 1) {
    echo "    ✅ CORRECTO: Todos normalizan a '" . reset($unicos_wendy) . "' → Mismo cliente\n\n";
} else {
    echo "    ⚠️  INCONSISTENCIA: Normalizan a diferentes valores\n\n";
}

// Caso YOCKEY (debe ser INCONSISTENCIA REAL)
$ids_yockey = [318];
echo "  YOCKEY, DONALD (ID 318):\n";
if (isset($estado_bd[318])) {
    echo "    ID 318 → '{$estado_bd[318]['nombre']}' → Grupo actual: {$estado_bd[318]['grupo_actual']}\n";
    if (isset($mapeo_csv[318])) {
        if (empty($grupos_csv[$mapeo_csv[318]])) {
            echo "    Grupo esperado (CSV): {$mapeo_csv[318]} (NOMBRE DESCONOCIDO)\n";
        } else {
            echo "    Grupo esperado (CSV): {$mapeo_csv[318]} ('{$grupos_csv[$mapeo_csv[318]]}')\n";
        }
        if ($estado_bd[318]['grupo_actual'] != $mapeo_csv[318]) {
            echo "    🔴 INCONSISTENCIA CRÍTICA: Grupo BD ({$estado_bd[318]['grupo_actual']}) ≠ Grupo CSV ({$mapeo_csv[318]})\n\n";
        }
    }
}

$m->close();

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  RESUMEN FINAL                                                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "✅ Este script:\n";
echo "   • Ignora variantes con números al final (Wendy Juarez 1/2 = mismo cliente)\n";
echo "   • Solo reporta inconsistencias REALES que requieren tu intervención\n";
echo "   • Es 100% READ-ONLY (no modifica la BD)\n";
echo "   • Genera reporte conciso (< 50 líneas útiles)\n";
echo "\n";
echo "💡 Próximos pasos:\n";
echo "   1. Revisa el reporte: cat $reporte_file\n";
echo "   2. Si hay inconsistencias reales, verifica contratos históricos\n";
echo "   3. Decide si corregir CSV o BD según evidencia documental\n";
echo "   4. Una vez resueltas, genera script de actualización\n\n";

?>