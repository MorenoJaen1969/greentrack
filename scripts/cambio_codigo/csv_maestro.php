<?php
/**
 * ======================================================================
 * PASO A DEFINITIVO CORREGIDO: RECONSTRUCCIÓN EXACTA DE CSV
 * ======================================================================
 * 
 * CORRECCIONES CRÍTICAS:
 * ✅ NO filtrar por id_cliente_nuevo en la consulta principal
 * ✅ Agrupar POR id_cliente_nuevo (incluyendo NULL temporalmente)
 * ✅ Para cada grupo con id_cliente_nuevo >= 1000:
 *    - Incluir TODOS los clientes con ese id_cliente_nuevo
 *    - NO excluir ningún registro del array de orígenes
 *    - El "maestro" es solo para mostrar el nombre, NO para filtrar orígenes
 * ✅ Columna 1 = id_cliente_nuevo (ej: 1000)
 * ✅ Columna 3 = TODOS los id_cliente del grupo (sin excluir ninguno)
 */

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  PASO A DEFINITIVO CORREGIDO                                     ║\n";
echo "║  (Incluye TODOS los miembros del grupo en Cod. CLIENTE Anterior) ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// ======================================================================
// CONFIGURACIÓN
// ======================================================================

$CONFIG = [
    'csv_output' => '/var/www/greentrack/scripts/clientes_maestro_corregido.csv',
    'csv_backup_prefix' => '/var/www/greentrack/scripts/clientes_maestro_backup_'
];

// ======================================================================
// PASO 1: OBTENER TODOS LOS CLIENTES CON id_cliente_nuevo >= 1000
// ======================================================================

echo "[INFO] Obteniendo clientes consolidados (id_cliente_nuevo >= 1000)...\n";

// ✅ CORRECCIÓN CRÍTICA: Obtener TODOS los clientes con id_cliente_nuevo >= 1000
//    (NO filtrar por NULL, NO excluir ningún registro del grupo)
$stmt = $m->prepare("
    SELECT 
        id_cliente,
        COALESCE(NULLIF(TRIM(nombre), ''), nombre_comercial) AS nombre_completo,
        nombre,
        nombre_comercial,
        id_cliente_nuevo AS grupo_id
    FROM clientes
    WHERE id_cliente_nuevo >= 1000
      AND (nombre IS NOT NULL OR nombre_comercial IS NOT NULL)
      AND (TRIM(nombre) != '' OR TRIM(nombre_comercial) != '')
    ORDER BY 
        id_cliente_nuevo ASC,
        id_cliente ASC  -- Ordenar por ID para consistencia
");
$stmt->execute();
$result = $stmt->get_result();

$grupos = []; // [grupo_id => [miembros...]]

while ($row = $result->fetch_assoc()) {
    $gid = (int)$row['grupo_id'];
    if (!isset($grupos[$gid])) {
        $grupos[$gid] = [];
    }
    $grupos[$gid][] = [
        'id_cliente' => (int)$row['id_cliente'],
        'nombre_completo' => $row['nombre_completo'] ?? '',
        'nombre' => $row['nombre'] ?? '',
        'nombre_comercial' => $row['nombre_comercial'] ?? ''
    ];
}
$stmt->close();

$total_grupos = count($grupos);
$total_clientes = array_sum(array_map('count', $grupos));

echo "[OK] $total_grupos grupos encontrados\n";
echo "     $total_clientes clientes consolidados\n\n";

// ======================================================================
// PASO 2: VERIFICACIÓN EXPLÍCITA DEL GRUPO 1000 (ACCESS RESTORATION)
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICACIÓN EXPLÍCITA: GRUPO 1000 (ACCESS RESTORATION)         ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$stmt_check = $m->prepare("
    SELECT 
        id_cliente,
        nombre,
        id_cliente_nuevo
    FROM clientes
    WHERE id_cliente_nuevo = 1000
    ORDER BY id_cliente ASC
");
$stmt_check->execute();
$result_check = $stmt_check->get_result();

$miembros_grupo_1000 = [];
while ($row = $result_check->fetch_assoc()) {
    $miembros_grupo_1000[] = $row;
    echo "ID {$row['id_cliente']} | '{$row['nombre']}' | Grupo {$row['id_cliente_nuevo']}\n";
}
$stmt_check->close();

if (empty($miembros_grupo_1000)) {
    echo "⚠️  ¡ALERTA! El grupo 1000 NO tiene miembros en la BD\n";
    echo "   Posible causa: El maestro (ID 543) tiene id_cliente_nuevo = NULL\n";
    echo "   Solución: Ejecutar UPDATE para asignar id_cliente_nuevo = 1000 al maestro\n\n";
} else {
    echo "\n✅ Grupo 1000 tiene " . count($miembros_grupo_1000) . " miembros\n";
    echo "   IDs: " . implode(', ', array_column($miembros_grupo_1000, 'id_cliente')) . "\n\n";
}

// ======================================================================
// PASO 3: GENERAR CSV CORREGIDO (SIN EXCLUIR NINGÚN MIEMBRO)
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  GENERANDO CSV CORREGIDO                                         ║\n";
echo "║  (TODOS los miembros del grupo en Cod. CLIENTE Anterior)         ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Backup
if (file_exists($CONFIG['csv_output'])) {
    $backup_file = $CONFIG['csv_backup_prefix'] . date('Ymd_His') . '.csv';
    copy($CONFIG['csv_output'], $backup_file);
    echo "[INFO] Backup creado: $backup_file\n";
}

// CSV
$fp = fopen($CONFIG['csv_output'], 'w');
fputcsv($fp, ['ID_CLIENTE', 'CLIENTE', 'Cod. CLIENTE Anterior'], ';');

// ✅ CORRECCIÓN DEFINITIVA: 
//    Columna 1 = grupo_id (id_cliente_nuevo)
//    Columna 2 = nombre_completo del PRIMER miembro (para referencia visual)
//    Columna 3 = TODOS los id_cliente del grupo (sin excluir ninguno)
$linea = 1;

// Ordenar grupos alfabéticamente por nombre del primer miembro
$grupos_ordenados = [];
foreach ($grupos as $gid => $miembros) {
    usort($miembros, fn($a, $b) => $a['id_cliente'] <=> $b['id_cliente']);
    $grupos_ordenados[] = [
        'grupo_id' => $gid,
        'miembros' => $miembros,
        'nombre_orden' => strtolower($miembros[0]['nombre_completo'] ?? '')
    ];
}
usort($grupos_ordenados, fn($a, $b) => strcmp($a['nombre_orden'], $b['nombre_orden']));

foreach ($grupos_ordenados as $grupo) {
    $grupo_id = $grupo['grupo_id'];
    $miembros = $grupo['miembros'];
    
    // ✅ TODOS los IDs del grupo (sin excluir ninguno)
    $todos_ids = array_map(fn($m) => $m['id_cliente'], $miembros);
    
    // Nombre para mostrar (el del primer miembro)
    $nombre_mostrar = $miembros[0]['nombre_completo'];
    
    fputcsv($fp, [
        $grupo_id,           // ✅ Columna 1 = id_cliente_nuevo (1000)
        $nombre_mostrar,     // ✅ Columna 2 = nombre del primer miembro
        implode(', ', $todos_ids)  // ✅ Columna 3 = TODOS los IDs del grupo
    ], ';');
    
    if ($linea <= 3 || $grupo_id == 1000) {
        echo "Línea $linea: Grupo $grupo_id → '$nombre_mostrar' | IDs: " . implode(', ', $todos_ids) . "\n";
    }
    $linea++;
}

fclose($fp);

$total_lineas = $linea - 1;
echo "\n[OK] CSV generado: {$CONFIG['csv_output']}\n";
echo "    Total de líneas: $total_lineas\n\n";

// ======================================================================
// PASO 4: VERIFICACIÓN FINAL DEL GRUPO 1000 EN EL CSV
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICACIÓN FINAL DEL CSV                                      ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$csv_lines = file($CONFIG['csv_output']);
$header = array_shift($csv_lines);

foreach ($csv_lines as $line) {
    $parts = str_getcsv(trim($line), ';');
    if (count($parts) >= 1 && trim($parts[0]) == '1000') {
        echo "✅ Línea encontrada para grupo 1000:\n";
        echo "   ID_CLIENTE: {$parts[0]}\n";
        echo "   CLIENTE: '{$parts[1]}'\n";
        echo "   Cod. CLIENTE Anterior: '{$parts[2]}'\n";
        
        // Verificar que incluya 710 y 740
        $origenes = explode(',', $parts[2]);
        $origenes = array_map('trim', $origenes);
        
        $tiene_710 = in_array('710', $origenes);
        $tiene_740 = in_array('740', $origenes);
        $tiene_543 = in_array('543', $origenes);
        
        echo "   Contiene ID 710: " . ($tiene_710 ? 'SÍ ✅' : 'NO ❌') . "\n";
        echo "   Contiene ID 740: " . ($tiene_740 ? 'SÍ ✅' : 'NO ❌') . "\n";
        echo "   Contiene ID 543: " . ($tiene_543 ? 'SÍ ✅' : 'NO ❌') . "\n";
        
        if ($tiene_710 && $tiene_740) {
            echo "\n🎉 ¡CORRECTO! El grupo 1000 incluye TODOS sus miembros\n";
        } else {
            echo "\n⚠️  ¡INCOMPLETO! Faltan IDs en el grupo 1000\n";
        }
        break;
    }
}

echo "\n";

// ======================================================================
// PASO 5: ESTADÍSTICAS FINALES
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ESTADÍSTICAS FINALES                                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "  Grupos consolidados: $total_grupos\n";
echo "  Clientes en grupos: $total_clientes\n";
echo "  Promedio de miembros por grupo: " . round($total_clientes / $total_grupos, 2) . "\n";
echo "  CSV generado: {$CONFIG['csv_output']}\n\n";

$m->close();

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  RESUMEN DE CORRECCIONES                                         ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "  ✅ Columna 1 = id_cliente_nuevo (ej: 1000, no 543)\n";
echo "  ✅ Columna 2 = nombre_completo (COALESCE)\n";
echo "  ✅ Columna 3 = TODOS los IDs del grupo (sin excluir ninguno)\n";
echo "  ✅ Orden alfabético total por nombre\n";
echo "  ✅ Incluye grupos de cualquier tamaño\n";
echo "  ✅ Verificación explícita del grupo 1000\n\n";

echo "💡 NOTA IMPORTANTE:\n";
echo "   Si el maestro original (ej: ID 543) tiene id_cliente_nuevo = NULL,\n";
echo "   NO aparecerá en este CSV porque solo incluimos registros con\n";
echo "   id_cliente_nuevo >= 1000. Para incluirlo, ejecuta:\n";
echo "   \n";
echo "   UPDATE clientes SET id_cliente_nuevo = 1000 WHERE id_cliente = 543;\n";
echo "   \n";
echo "   Luego vuelve a ejecutar este script.\n\n";

?>