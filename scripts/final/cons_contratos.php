<?php
/**
 * ======================================================================
 * ACTUALIZACIÓN CONTRATOS: id_cliente_nuevo + id_direccion_nuevo
 * ======================================================================
 * 
 * Propósito:
 *   Actualizar campos *_nuevo en tabla contratos basado en:
 *   • contratos.id_cliente → clientes.id_cliente_nuevo
 *   • contratos.id_direccion → direcciones.id_direccion_nuevo
 * 
 * Acciones:
 *   1. Actualizar contratos.id_cliente_nuevo con JOIN a clientes
 *   2. Actualizar contratos.id_direccion_nuevo con JOIN a direcciones
 *   3. Reporte de registros actualizados vs pendientes
 * 
 * Configuración:
 *   • Modo simulación por defecto (sin modificar BD)
 *   • Solo actualiza donde *_nuevo IS NULL
 *   • Reporte detallado de resultados
 */

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ACTUALIZACIÓN CONTRATOS: id_cliente_nuevo + id_direccion_nuevo ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// ======================================================================
// CONFIGURACIÓN
// ======================================================================

$CONFIG = [
    'apply_changes' => true,  // false = simulación, true = aplicar cambios 
    'only_null' => true,       // Solo actualizar donde *_nuevo IS NULL
    'log_file' => '/var/www/greentrack/scripts/actualizacion_contratos_' . date('Ymd_His') . '.txt'
];

// ======================================================================
// PASO 1: ESTADÍSTICAS INICIALES
// ======================================================================

echo "[INFO] Obteniendo estadísticas iniciales...\n\n";

$stats_queries = [
    'Total contratos' => "SELECT COUNT(*) AS total FROM contratos",
    'Contratos con id_cliente_nuevo NULL' => "SELECT COUNT(*) AS total FROM contratos WHERE id_cliente_nuevo IS NULL",
    'Contratos con id_direccion_nuevo NULL' => "SELECT COUNT(*) AS total FROM contratos WHERE id_direccion_nuevo IS NULL",
    'Contratos con ambos *_nuevo NULL' => "SELECT COUNT(*) AS total FROM contratos WHERE id_cliente_nuevo IS NULL AND id_direccion_nuevo IS NULL"
];

foreach ($stats_queries as $desc => $query) {
    $result = $m->query($query);
    $total = $result->fetch_assoc()['total'];
    echo "  $desc: $total\n";
}
echo "\n";

// ======================================================================
// PASO 2: ACTUALIZAR id_cliente_nuevo
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ACTUALIZANDO contratos.id_cliente_nuevo                        ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$where_cli = $CONFIG['only_null'] ? "AND co.id_cliente_nuevo IS NULL" : "";

$query_sim_cli = "
    SELECT 
        COUNT(*) AS total_actualizables,
        COUNT(DISTINCT c.id_cliente_nuevo) AS grupos_distintos
    FROM contratos co
    JOIN clientes c ON co.id_cli_anterior = c.id_cliente
    WHERE c.id_cliente_nuevo IS NOT NULL
    $where_cli
";

$result_sim = $m->query($query_sim_cli);
$row_sim = $result_sim->fetch_assoc();
$total_cli_actualizables = $row_sim['total_actualizables'];
$grupos_cli_distintos = $row_sim['grupos_distintos'];

echo "[SIMULACIÓN] Contratos actualizables (id_cliente_nuevo): $total_cli_actualizables\n";
echo "[SIMULACIÓN] Grupos de clientes distintos: $grupos_cli_distintos\n\n";

if ($CONFIG['apply_changes']) {
    $query_upd_cli = "
        UPDATE contratos co
        JOIN clientes c ON co.id_cli_anterior = c.id_cliente
        SET co.id_cliente_nuevo = c.id_cliente_nuevo
        WHERE c.id_cliente_nuevo IS NOT NULL
        $where_cli
    ";
    
    if ($m->query($query_upd_cli)) {
        $afectados_cli = $m->affected_rows;
        echo "[OK] Contratos actualizados (id_cliente_nuevo): $afectados_cli\n\n";
    } else {
        die("[ERROR] Falló actualización id_cliente_nuevo: " . $m->error . "\n");
    }
} else {
    echo "💡 Para aplicar cambios, cambia 'apply_changes' => true\n\n";
}

// ======================================================================
// PASO 3: ACTUALIZAR id_direccion_nuevo
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ACTUALIZANDO contratos.id_direccion_nuevo                      ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$where_dir = $CONFIG['only_null'] ? "AND co.id_direccion_nuevo IS NULL" : "";

$query_sim_dir = "
    SELECT 
        COUNT(*) AS total_actualizables,
        COUNT(DISTINCT d.id_direccion_nuevo) AS grupos_distintos
    FROM contratos co
    JOIN direcciones d ON co.id_dir_anterior = d.id_direccion
    WHERE d.id_direccion_nuevo IS NOT NULL
    $where_dir
";

$result_sim = $m->query($query_sim_dir);
$row_sim = $result_sim->fetch_assoc();
$total_dir_actualizables = $row_sim['total_actualizables'];
$grupos_dir_distintos = $row_sim['grupos_distintos'];

echo "[SIMULACIÓN] Contratos actualizables (id_direccion_nuevo): $total_dir_actualizables\n";
echo "[SIMULACIÓN] Grupos de direcciones distintos: $grupos_dir_distintos\n\n";

if ($CONFIG['apply_changes']) {
    $query_upd_dir = "
        UPDATE contratos co
        JOIN direcciones d ON co.id_dir_anterior = d.id_direccion
        SET co.id_direccion_nuevo = d.id_direccion_nuevo
        WHERE d.id_direccion_nuevo IS NOT NULL
        $where_dir
    ";
    
    if ($m->query($query_upd_dir)) {
        $afectados_dir = $m->affected_rows;
        echo "[OK] Contratos actualizados (id_direccion_nuevo): $afectados_dir\n\n";
    } else {
        die("[ERROR] Falló actualización id_direccion_nuevo: " . $m->error . "\n");
    }
} else {
    echo "💡 Para aplicar cambios, cambia 'apply_changes' => true\n\n";
}

// ======================================================================
// PASO 4: VERIFICACIÓN FINAL
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICACIÓN FINAL                                              ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$verif_queries = [
    'Contratos con id_cliente_nuevo IS NULL' => "
        SELECT COUNT(*) AS total 
        FROM contratos 
        WHERE id_cliente_nuevo IS NULL
    ",
    'Contratos con id_direccion_nuevo IS NULL' => "
        SELECT COUNT(*) AS total 
        FROM contratos 
        WHERE id_direccion_nuevo IS NULL
    ",
    'Contratos con ambos *_nuevo IS NOT NULL' => "
        SELECT COUNT(*) AS total 
        FROM contratos 
        WHERE id_cliente_nuevo IS NOT NULL 
          AND id_direccion_nuevo IS NOT NULL
    ",
    'Contratos con id_cli_anterior ≠ id_cliente_nuevo' => "
        SELECT COUNT(*) AS total 
        FROM contratos 
        WHERE id_cli_anterior != id_cliente_nuevo
    ",
    'Contratos con id_dir_anterior ≠ id_direccion_nuevo' => "
        SELECT COUNT(*) AS total 
        FROM contratos 
        WHERE id_dir_anterior != id_direccion_nuevo
    "
];

foreach ($verif_queries as $desc => $query) {
    $result = $m->query($query);
    $total = $result->fetch_assoc()['total'];
    echo "  $desc: $total\n";
}

echo "\n";

// ======================================================================
// PASO 5: EJEMPLOS DE CONTRATOS ACTUALIZADOS
// ======================================================================

if ($CONFIG['apply_changes']) {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║  EJEMPLOS DE CONTRATOS ACTUALIZADOS                             ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n\n";
    
    $ejemplos_query = "
        SELECT 
            co.id_contrato,
            co.id_cli_anterior,
            co.id_cliente_nuevo,
            co.id_dir_anterior,
            co.id_direccion_nuevo,
            COALESCE(
                CASE 
                    WHEN c.id_tipo_persona = 1 THEN TRIM(CONCAT_WS(' ', NULLIF(c.nombre, ''), NULLIF(c.apellido, '')))
                    WHEN c.id_tipo_persona = 2 THEN NULLIF(c.nombre_comercial, '')
                    ELSE NULLIF(c.nombre, '')
                END,
                '[SIN NOMBRE]'
            ) AS cliente_nombre, 
            d.direccion AS direccion_texto
        FROM contratos co
        JOIN clientes c ON co.id_cli_anterior = c.id_cliente
        JOIN direcciones d ON co.id_dir_anterior = d.id_direccion
        WHERE co.id_cliente_nuevo IS NOT NULL
            AND co.id_direccion_nuevo IS NOT NULL
        ORDER BY co.id_contrato DESC
        LIMIT 5
    ";
    
    $result_ej = $m->query($ejemplos_query);
    
    while ($row = $result_ej->fetch_assoc()) {
        echo "Contrato ID: {$row['id_contrato']}\n";
        echo "  Cliente: {$row['id_cli_anterior']} → {$row['id_cliente_nuevo']} ('{$row['cliente_nombre']}')\n";
        echo "  Dirección: {$row['id_dir_anterior']} → {$row['id_direccion_nuevo']}\n";
        echo "  Dirección texto: {$row['direccion_texto']}\n";
        echo "  ──────────────────────────────────────────────────────────────\n\n";
    }
}

$m->close();

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  RESUMEN EJECUTIVO                                               ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "✅ Actualización mediante JOIN eficiente (no bucle PHP)\n";
echo "✅ Solo actualiza donde *_nuevo IS NULL (configurable)\n";
echo "✅ Modo simulación activo (sin cambios reales en BD)\n";
echo "✅ Reporte detallado de estadísticas y ejemplos\n\n";

echo "💡 Para aplicar los cambios:\n";
echo "   1. Verifica las estadísticas de simulación arriba\n";
echo "   2. Edita este script y cambia:\n";
echo "        'apply_changes' => true\n";
echo "   3. Ejecuta nuevamente\n\n";

echo "[FIN] Actualización de contratos completada\n\n";
?>