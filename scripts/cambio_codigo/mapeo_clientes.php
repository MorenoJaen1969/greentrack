<?php
/**
 * MAPEO DE CLIENTES - CONSOLIDACIÓN CORREGIDA
 * 
 * Correcciones críticas:
 * 1. ✅ Actualiza clientes por id_cliente (NO id_cli_anterior)
 * 2. ✅ Actualiza tablas relacionadas por id_cli_anterior (CORRECTO para esas tablas)
 * 3. ✅ Verifica existencia de cada ID antes de actualizar
 * 4. ✅ Reporta IDs no encontrados para auditoría
 * 5. ✅ Usa prepared statements para seguridad
 */

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
$m->set_charset('utf8mb4');

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  MAPEO DE CLIENTES - VERSIÓN CORREGIDA                           ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Leer CSV
$csv_file = '/var/www/greentrack/scripts/clientes_nuevo.csv';
if (!file_exists($csv_file)) {
    die("[ERROR] Archivo no encontrado: $csv_file\n");
}

$lines = file($csv_file);
$mapeo = [];
$contador = 1000;

foreach ($lines as $i => $line) {
    if ($i === 0) continue; // Header
    $parts = str_getcsv(trim($line), ';');
    if (count($parts) < 2) continue;

    $id_nuevo = $contador++;
    $nombre = trim($parts[1]);
    $ids_anteriores = [];

    if (!empty($parts[2])) {
        $ids_raw = explode(',', $parts[2]);
        foreach ($ids_raw as $id) {
            $id = trim($id);
            if (is_numeric($id) && $id > 0) {
                $ids_anteriores[] = (int)$id;
            }
        }
    }

    $mapeo[] = [
        'id_nuevo' => $id_nuevo,
        'nombre' => $nombre,
        'ids_anteriores' => $ids_anteriores,
        'linea_csv' => $i + 1
    ];
}

echo "[OK] " . count($mapeo) . " grupos definidos en CSV\n\n";

// ======================================================================
// VERIFICAR EXISTENCIA DE IDs ANTES DE ACTUALIZAR
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICANDO EXISTENCIA DE IDs                                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$ids_no_encontrados = [];
$total_verificados = 0;

foreach ($mapeo as $item) {
    foreach ($item['ids_anteriores'] as $id_ant) {
        $total_verificados++;
        
        // Verificar en tabla clientes (por id_cliente)
        $stmt = $m->prepare("SELECT id_cliente, nombre FROM clientes WHERE id_cliente = ?");
        $stmt->bind_param('i', $id_ant);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $ids_no_encontrados[] = [
                'id' => $id_ant,
                'grupo' => $item['nombre'],
                'grupo_id' => $item['id_nuevo'],
                'linea_csv' => $item['linea_csv']
            ];
            echo "⚠️  ID $id_ant NO ENCONTRADO en clientes (Grupo: {$item['nombre']}, línea CSV: {$item['linea_csv']})\n";
        }
        $stmt->close();
    }
}

if (!empty($ids_no_encontrados)) {
    echo "\n[ALERTA] " . count($ids_no_encontrados) . " IDs no encontrados de $total_verificados verificados\n";
    echo "💡 Posibles causas:\n";
    echo "   • El ID fue eliminado de la tabla clientes\n";
    echo "   • El ID pertenece a otro sistema (legacy)\n";
    echo "   • Error tipográfico en el CSV\n\n";
    
    // Pausa para revisión
    echo "❓ ¿Continuar de todos modos? (s/n): ";
    $handle = fopen("php://stdin", "r");
    $respuesta = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($respuesta) !== 's') {
        echo "[CANCELADO] Operación abortada por usuario\n";
        $m->close();
        exit(0);
    }
    echo "\n";
}

// ======================================================================
// ACTUALIZAR BASE DE DATOS (CORRECTAMENTE)
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ACTUALIZANDO BASE DE DATOS                                      ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$stats = [
    'clientes' => 0,
    'servicios' => 0,
    'contratos' => 0,
    'direcciones' => 0
];

foreach ($mapeo as $item) {
    $id_nuevo = $item['id_nuevo'];
    
    echo "Procesando grupo {$item['id_nuevo']} ('{$item['nombre']}')...\n";
    
    foreach ($item['ids_anteriores'] as $id_ant) {
        // 1. ACTUALIZAR CLIENTES (POR id_cliente - ¡CORRECTO!)
        $stmt = $m->prepare("UPDATE clientes SET id_cliente_nuevo = ? WHERE id_cliente = ?");
        $stmt->bind_param('ii', $id_nuevo, $id_ant);
        $stmt->execute();
        $stats['clientes'] += $stmt->affected_rows;
        $stmt->close();
        
        // 2. ACTUALIZAR SERVICIOS (POR id_cli_anterior - correcto para esta tabla)
        $stmt = $m->prepare("UPDATE servicios SET id_cliente_nuevo = ? WHERE id_cli_anterior = ?");
        $stmt->bind_param('ii', $id_nuevo, $id_ant);
        $stmt->execute();
        $stats['servicios'] += $stmt->affected_rows;
        $stmt->close();
        
        // 3. ACTUALIZAR CONTRATOS (POR id_cli_anterior)
        $stmt = $m->prepare("UPDATE contratos SET id_cliente_nuevo = ? WHERE id_cli_anterior = ?");
        $stmt->bind_param('ii', $id_nuevo, $id_ant);
        $stmt->execute();
        $stats['contratos'] += $stmt->affected_rows;
        $stmt->close();
        
        // 4. ACTUALIZAR DIRECCIONES (POR id_cli_anterior)
        $stmt = $m->prepare("UPDATE direcciones SET id_cliente_nuevo = ? WHERE id_cli_anterior = ?");
        $stmt->bind_param('ii', $id_nuevo, $id_ant);
        $stmt->execute();
        $stats['direcciones'] += $stmt->affected_rows;
        $stmt->close();
    }
    
    echo "  → IDs procesados: " . count($item['ids_anteriores']) . "\n";
}

// ======================================================================
// ESTADÍSTICAS FINALES
// ======================================================================

echo "\n╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ESTADÍSTICAS FINALES                                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";

// Clientes
$r = $m->query("SELECT COUNT(*) as total, SUM(CASE WHEN id_cliente_nuevo IS NOT NULL THEN 1 ELSE 0 END) as con_nuevo FROM clientes");
$row = $r->fetch_assoc();
echo "  Clientes con id_cliente_nuevo: {$row['con_nuevo']}/{$row['total']} (" . round(($row['con_nuevo']/$row['total'])*100, 1) . "%)\n";

// Servicios
$r = $m->query("SELECT COUNT(*) as total, SUM(CASE WHEN id_cliente_nuevo IS NOT NULL THEN 1 ELSE 0 END) as con_nuevo FROM servicios");
$row = $r->fetch_assoc();
echo "  Servicios con id_cliente_nuevo: {$row['con_nuevo']}/{$row['total']} (" . round(($row['con_nuevo']/$row['total'])*100, 1) . "%)\n";

// IDs no encontrados
if (!empty($ids_no_encontrados)) {
    echo "\n⚠️  IDs NO ENCONTRADOS (requieren revisión manual):\n";
    foreach ($ids_no_encontrados as $no_encontrado) {
        echo "   • ID {$no_encontrado['id']} (Grupo: {$no_encontrado['grupo']}, línea CSV: {$no_encontrado['linea_csv']})\n";
    }
}

echo "\n[FIN] Mapeo de clientes completado correctamente\n";
$m->close();

?>