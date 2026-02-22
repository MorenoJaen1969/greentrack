<?php
/**
 * ======================================================================
 * LIMPIADOR DE DUPLICADOS EN CSV - SIN MODIFICAR BD
 * ======================================================================
 * 
 * Propósito: Detectar y fusionar grupos duplicados en el CSV definitivo
 *   • Duplicados por nombre idéntico
 *   • Duplicados por IDs reales solapados
 *   • Variantes fonéticas (opcional)
 * 
 * ¡ESTE SCRIPT NO MODIFICA LA BASE DE DATOS!
 * Solo lee el CSV actual y genera uno limpio para tu revisión.
 */

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  LIMPIADOR DE DUPLICADOS EN CSV - SIN MODIFICAR BD              ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// ======================================================================
// CONFIGURACIÓN
// ======================================================================

$CONFIG = [
    'csv_entrada' => '/var/www/greentrack/scripts/clientes_nuevo_definitivo.csv',
    'csv_salida' => '/var/www/greentrack/scripts/clientes_nuevo_limpio.csv'
];

// ======================================================================
// PASO 1: LEER CSV ACTUAL
// ======================================================================

echo "[INFO] Leyendo CSV actual...\n";

if (!file_exists($CONFIG['csv_entrada'])) {
    die("[ERROR] Archivo CSV no encontrado: {$CONFIG['csv_entrada']}\n");
}

$lines = file($CONFIG['csv_entrada']);
$header = array_shift($lines);

if (empty($lines)) {
    die("[ERROR] El archivo CSV está vacío\n");
}

$grupos = [];

foreach ($lines as $idx => $line) {
    $parts = str_getcsv(trim($line), ';');
    if (count($parts) < 2) continue;
    
    $grupo_id = (int)trim($parts[0]);
    $nombre = trim($parts[1]);
    $ids_reales = [];
    
    if (!empty($parts[2])) {
        $raw = array_map('trim', explode(',', $parts[2]));
        foreach ($raw as $id) {
            if (is_numeric($id) && $id > 0) {
                $ids_reales[] = (int)$id;
            }
        }
    }
    
    $grupos[] = [
        'linea_original' => $idx + 2,  // +2 por header y base 1
        'grupo_id' => $grupo_id,
        'nombre' => $nombre,
        'ids_reales' => $ids_reales,
        'ids_set' => array_flip($ids_reales)  // Para comparación rápida
    ];
}

$total_original = count($grupos);
echo "[OK] $total_original grupos leídos\n\n";

// ======================================================================
// PASO 2: DETECTAR Y FUSIONAR DUPLICADOS
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  DETECTANDO Y FUSIONANDO DUPLICADOS                             ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$grupos_fusionados = [];
$grupos_procesados = [];
$duplicados_detectados = 0;

for ($i = 0; $i < count($grupos); $i++) {
    if (isset($grupos_procesados[$i])) continue;
    
    $grupo_base = $grupos[$i];
    $grupo_base['grupos_fusionados'] = [$i];
    
    // Buscar duplicados por:
    // 1. Nombre idéntico (ignorando mayúsculas/espacios)
    // 2. IDs reales solapados (mismo ID en múltiples grupos)
    for ($j = $i + 1; $j < count($grupos); $j++) {
        if (isset($grupos_procesados[$j])) continue;
        
        $grupo_comp = $grupos[$j];
        
        // Normalizar nombres para comparación
        $nombre_base_norm = preg_replace('/\s+/', ' ', strtoupper(trim($grupo_base['nombre'])));
        $nombre_comp_norm = preg_replace('/\s+/', ' ', strtoupper(trim($grupo_comp['nombre'])));
        
        // Verificar duplicado por nombre idéntico
        $mismo_nombre = ($nombre_base_norm === $nombre_comp_norm);
        
        // Verificar duplicado por IDs solapados
        $solapamiento = array_intersect_key($grupo_base['ids_set'], $grupo_comp['ids_set']);
        $mismo_ids = !empty($solapamiento);
        
        if ($mismo_nombre || $mismo_ids) {
            // Fusionar grupos
            $grupo_base['ids_reales'] = array_unique(array_merge(
                $grupo_base['ids_reales'],
                $grupo_comp['ids_reales']
            ));
            $grupo_base['ids_set'] = array_flip($grupo_base['ids_reales']);
            $grupo_base['grupos_fusionados'][] = $j;
            $grupos_procesados[$j] = true;
            $duplicados_detectados++;
            
            echo "⚠️  DUPLICADO DETECTADO en línea {$grupo_comp['linea_original']}:\n";
            echo "   Grupo base: {$grupo_base['grupo_id']} '{$grupo_base['nombre']}'\n";
            echo "   Grupo dup:  {$grupo_comp['grupo_id']} '{$grupo_comp['nombre']}'\n";
            echo "   IDs solapados: " . implode(', ', array_keys($solapamiento)) . "\n";
            echo "   → Fusionados en grupo {$grupo_base['grupo_id']}\n\n";
        }
    }
    
    $grupos_fusionados[] = $grupo_base;
    $grupos_procesados[$i] = true;
}

$total_limpio = count($grupos_fusionados);
echo "[OK] Duplicados detectados y fusionados: $duplicados_detectados\n";
echo "    Grupos originales: $total_original\n";
echo "    Grupos limpios: $total_limpio\n\n";

// ======================================================================
// PASO 3: GENERAR CSV LIMPIO
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  GENERANDO CSV LIMPIO                                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$fp = fopen($CONFIG['csv_salida'], 'w');
fputcsv($fp, ['ID_CLIENTE', 'CLIENTE', 'Cod. CLIENTE Anterior'], ';');

foreach ($grupos_fusionados as $grupo) {
    sort($grupo['ids_reales'], SORT_NUMERIC);
    fputcsv($fp, [
        $grupo['grupo_id'],
        $grupo['nombre'],
        implode(', ', $grupo['ids_reales'])
    ], ';');
}

fclose($fp);

echo "[OK] CSV limpio generado: {$CONFIG['csv_salida']}\n";
echo "    Total de grupos: $total_limpio\n\n";

// ======================================================================
// PASO 4: VERIFICACIÓN ESPECÍFICA - JUAN NUNEZ
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICACIÓN: JUAN NUNEZ                                        ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$csv_limpio = file($CONFIG['csv_salida']);
array_shift($csv_limpio); // Saltar header

$juan_nunez_encontrados = 0;

foreach ($csv_limpio as $line) {
    $parts = str_getcsv(trim($line), ';');
    if (count($parts) >= 2 && stripos($parts[1], 'JUAN NUNEZ') !== false) {
        $juan_nunez_encontrados++;
        echo "Encontrado: {$parts[0]};'{$parts[1]}';{$parts[2]}\n";
        
        // Verificar que incluya TODOS los IDs: 353, 354, 355, 356
        $ids = array_map('trim', explode(',', $parts[2]));
        $tiene_353 = in_array('353', $ids);
        $tiene_354 = in_array('354', $ids);
        $tiene_355 = in_array('355', $ids);
        $tiene_356 = in_array('356', $ids);
        
        echo "  Contiene ID 353 (maestro): " . ($tiene_353 ? 'SÍ ✅' : 'NO ❌') . "\n";
        echo "  Contiene ID 354: " . ($tiene_354 ? 'SÍ ✅' : 'NO ❌') . "\n";
        echo "  Contiene ID 355: " . ($tiene_355 ? 'SÍ ✅' : 'NO ❌') . "\n";
        echo "  Contiene ID 356: " . ($tiene_356 ? 'SÍ ✅' : 'NO ❌') . "\n";
        
        if ($tiene_353 && $tiene_354 && $tiene_355 && $tiene_356) {
            echo "  ✅ ¡CORRECTO! Todos los IDs de JUAN NUNEZ incluidos en UN SOLO grupo\n";
        } else {
            echo "  ⚠️  ¡INCOMPLETO! Faltan IDs en el grupo\n";
        }
        echo "\n";
    }
}

if ($juan_nunez_encontrados === 1) {
    echo "✅ ¡CORRECTO! Solo UN grupo para JUAN NUNEZ (duplicados fusionados)\n\n";
} elseif ($juan_nunez_encontrados > 1) {
    echo "❌ ¡ERROR! Aún hay $juan_nunez_encontrados grupos para JUAN NUNEZ (no se fusionaron correctamente)\n\n";
} else {
    echo "⚠️  No se encontró JUAN NUNEZ en el CSV limpio\n\n";
}

// ======================================================================
// PASO 5: ESTADÍSTICAS FINALES
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ESTADÍSTICAS FINALES                                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "  Grupos originales: $total_original\n";
echo "  Duplicados fusionados: $duplicados_detectados\n";
echo "  Grupos limpios: $total_limpio\n";
echo "  Archivo de entrada: {$CONFIG['csv_entrada']}\n";
echo "  Archivo de salida: {$CONFIG['csv_salida']}\n\n";

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  INSTRUCCIONES PARA REVISIÓN                                     ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "  1. Revisa el CSV limpio:\n";
echo "     cat {$CONFIG['csv_salida']} | grep -i 'juan nunez'\n";
echo "\n";
echo "  2. Verifica que solo exista UN grupo para JUAN NUNEZ\n";
echo "\n";
echo "  3. Si todo es correcto, reemplaza el CSV definitivo:\n";
echo "     cp {$CONFIG['csv_salida']} {$CONFIG['csv_entrada']}\n";
echo "\n";
echo "  4. ¡IMPORTANTE! Este script NO modificó la BD.\n";
echo "     Los cambios en BD se aplicarán SOLO después de tu confirmación.\n\n";

echo "[FIN] Limpieza de duplicados completada - CSV listo para revisión\n\n";

?>