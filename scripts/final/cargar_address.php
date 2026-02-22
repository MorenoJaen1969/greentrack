<?php
/**
 * ACTUALIZACIÓN DIRECCIONES: id_cliente_nuevo desde CLIENTES (IDs 1-167)
 * 
 * Acción exacta:
 *   Para cada cliente con id_cliente BETWEEN 1 AND 167 
 *   Y id_cliente_nuevo IS NOT NULL:
 *      UPDATE direcciones 
 *      SET id_cliente_nuevo = [valor del cliente]
 *      WHERE id_cliente = [id_cliente del cliente]
 * 
 * Reporte:
 *   Clientes 1-167 con id_cliente_nuevo IS NULL (no actualizados)
 */

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ACTUALIZACIÓN DIRECCIONES: id_cliente_nuevo (IDs 1-167)        ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Paso 1: Obtener clientes 1-167 con id_cliente_nuevo asignado
$stmt = $m->prepare("
    SELECT id_cliente, id_cliente_nuevo, nombre, nombre_comercial
    FROM clientes
    WHERE id_cliente BETWEEN 1 AND 167
      AND id_cliente_nuevo IS NOT NULL
    ORDER BY id_cliente ASC
");
$stmt->execute();
$result = $stmt->get_result();

$actualizados = 0;
$clientes_consolidados = [];

while ($row = $result->fetch_assoc()) {
    $id_cliente = (int)$row['id_cliente'];
    $id_nuevo = (int)$row['id_cliente_nuevo'];
    $nombre = !empty(trim($row['nombre_comercial'])) ? $row['nombre_comercial'] : $row['nombre'];
    
    // Actualizar direcciones de este cliente
    $stmt_upd = $m->prepare("
        UPDATE direcciones
        SET id_cliente_nuevo = ?
        WHERE id_cliente = ?
    ");
    $stmt_upd->bind_param('ii', $id_nuevo, $id_cliente);
    $stmt_upd->execute();
    $afectadas = $stmt_upd->affected_rows;
    $stmt_upd->close();
    
    if ($afectadas > 0) {
        echo "✓ Cliente $id_cliente | '$nombre' → Direcciones actualizadas: $afectadas (id_cliente_nuevo = $id_nuevo)\n";
        $actualizados += $afectadas;
        $clientes_consolidados[] = $id_cliente;
    }
}
$stmt->close();

echo "\n[OK] Direcciones actualizadas: $actualizados\n\n";

// Paso 2: Reportar clientes 1-167 SIN id_cliente_nuevo
$stmt_pend = $m->prepare("
    SELECT id_cliente, nombre, nombre_comercial
    FROM clientes
    WHERE id_cliente BETWEEN 1 AND 167
      AND id_cliente_nuevo IS NULL
    ORDER BY id_cliente ASC
");
$stmt_pend->execute();
$result_pend = $stmt_pend->get_result();

$pendientes = [];
while ($row = $result_pend->fetch_assoc()) {
    $id_cliente = (int)$row['id_cliente'];
    $nombre = !empty(trim($row['nombre_comercial'])) ? $row['nombre_comercial'] : $row['nombre'];
    $pendientes[] = [$id_cliente, $nombre];
}
$stmt_pend->close();

if (!empty($pendientes)) {
    echo "⚠️  CLIENTES 1-167 SIN id_cliente_nuevo (direcciones NO actualizadas):\n";
    foreach ($pendientes as $p) {
        echo "   ID {$p[0]} | '{$p[1]}'\n";
    }
    echo "\n";
} else {
    echo "✅ Todos los clientes 1-167 tienen id_cliente_nuevo asignado\n\n";
}

// Paso 3: Verificación final
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICACIÓN FINAL                                              ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$verif = [
    'Direcciones con id_cliente 1-167 y id_cliente_nuevo asignado' => "
        SELECT COUNT(*) AS total 
        FROM direcciones 
        WHERE id_cliente BETWEEN 1 AND 167 
          AND id_cliente_nuevo IS NOT NULL
    ",
    'Direcciones con id_cliente 1-167 y id_cliente_nuevo NULL' => "
        SELECT COUNT(*) AS total 
        FROM direcciones 
        WHERE id_cliente BETWEEN 1 AND 167 
          AND id_cliente_nuevo IS NULL
    "
];

foreach ($verif as $desc => $query) {
    $r = $m->query($query);
    $total = $r->fetch_assoc()['total'];
    echo "  $desc: $total\n";
}

$m->close();

echo "\n[FIN] Actualización de direcciones completada\n";
?>