<?php
/**
 * ======================================================================
 * RECONSTRUCCIÓN DEFINITIVA DE TABLA DIRECCIONES
 * ======================================================================
 * 
 * Pasos EXACTOS según tus instrucciones:
 * 1. Crear backup específico de tabla direcciones
 * 2. Vaciar COMPLETAMENTE la tabla direcciones
 * 3. Procesar backup ordenado por id_direccion:
 *    a) Verificar si dirección existe en tabla direcciones (vacía inicialmente)
 *    b) Si NO existe:
 *       - Si id_direccion_nuevo es NULL → asignar próximo número (5170+)
 *       - Si id_direccion_nuevo tiene valor → usar ese valor
 *    c) Si existe Y id_cliente_nuevo coincide → NO cargar (evitar duplicados)
 * 4. Último id_direccion_nuevo usado: 5169 → próximo = 5170
 */

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  RECONSTRUCCIÓN DEFINITIVA TABLA DIRECCIONES                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// ======================================================================
// PASO 1: CREAR BACKUP ESPECÍFICO
// ======================================================================

$backup_table = 'direcciones_backup_' . date('Ymd_His');
echo "[INFO] Creando backup específico: $backup_table\n";

// Verificar si backup ya existe (por seguridad)
if ($m->query("SHOW TABLES LIKE '$backup_table'")->num_rows > 0) {
    die("[ERROR] Tabla de backup $backup_table ya existe. Elimínala manualmente primero.\n");
}

// Crear backup
if (!$m->query("CREATE TABLE $backup_table AS SELECT * FROM direcciones")) {
    die("[ERROR] Falló creación de backup: " . $m->error . "\n");
}

// Verificar cantidad respaldada
$result_count = $m->query("SELECT COUNT(*) AS total FROM $backup_table");
$total_backup = $result_count->fetch_assoc()['total'];
echo "[OK] Backup creado exitosamente: $total_backup registros\n\n";

// ======================================================================
// PASO 2: VACIAR COMPLETAMENTE TABLA DIRECCIONES
// ======================================================================

echo "[INFO] Vaciamos COMPLETAMENTE la tabla direcciones...\n";

if (!$m->query("TRUNCATE TABLE direcciones")) {
    die("[ERROR] Falló TRUNCATE de direcciones: " . $m->error . "\n");
}

echo "[OK] Tabla direcciones VACÍA (0 registros)\n\n";

// ======================================================================
// PASO 3: PROCESAR BACKUP ORDENADO POR id_direccion
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  PROCESANDO BACKUP ORDENADO POR id_direccion                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$next_id_dir_nuevo = 5170; // Último usado: 5169
$insertados = 0;
$skipped_duplicados = 0;
$skipped_sin_cliente_nuevo = 0;

// Obtener todos los registros del backup ordenados por id_direccion
$stmt_backup = $m->prepare("
    SELECT 
        id_direccion, id_cliente, direccion, lat, lng, id_status, fecha_creacion,
        cambio, id_geofence, id_zona, id_address_clas, id_address_type, id_contrato,
        id_proveedor, id_pais, id_estado, id_condado, id_ciudad, id_zip, notas,
        observaciones, id_cli_anterior, id_cliente_nuevo, id_dir_anterior, id_direccion_nuevo
    FROM $backup_table
    ORDER BY id_direccion ASC
");
$stmt_backup->execute();
$result_backup = $stmt_backup->get_result();

while ($row = $result_backup->fetch_assoc()) {
    $id_direccion = $row['id_direccion'];
    $id_cliente = $row['id_cliente'];
    $direccion_str = $row['direccion'];
    $id_cliente_nuevo = $row['id_cliente_nuevo'];
    $id_dir_nuevo_original = $row['id_direccion_nuevo'];
    
    // ==================================================================
    // VERIFICAR SI YA EXISTE EN TABLA ACTUAL (POR DIRECCIÓN + CLIENTE_NUEVO)
    // ==================================================================
    if ($id_cliente_nuevo === null) {
        // Si id_cliente_nuevo es NULL, no podemos consolidar → saltar
        $skipped_sin_cliente_nuevo++;
        continue;
    }
    
    // Buscar si ya existe registro con MISMA DIRECCIÓN y MISMO id_cliente_nuevo
    $stmt_check = $m->prepare("
        SELECT COUNT(*) AS existe 
        FROM direcciones 
        WHERE direccion = ? AND id_cliente_nuevo = ?
    ");
    $stmt_check->bind_param('si', $direccion_str, $id_cliente_nuevo);
    $stmt_check->execute();
    $existe = $stmt_check->get_result()->fetch_assoc()['existe'] > 0;
    $stmt_check->close();
    
    if ($existe) {
        $skipped_duplicados++;
        continue; // Ya existe → NO cargar (evitar duplicados)
    }
    
    // ==================================================================
    // DETERMINAR id_direccion_nuevo A USAR
    // ==================================================================
    if ($id_dir_nuevo_original === null || $id_dir_nuevo_original == 0) {
        $id_dir_nuevo_usar = $next_id_dir_nuevo;
        $next_id_dir_nuevo++;
    } else {
        $id_dir_nuevo_usar = $id_dir_nuevo_original;
    }
    
    // ==================================================================
    // INSERTAR REGISTRO COMPLETO EN TABLA VACÍA
    // ==================================================================
    $stmt_ins = $m->prepare("
        INSERT INTO direcciones (
            id_direccion, id_cliente, direccion, lat, lng, id_status, fecha_creacion,
            cambio, id_geofence, id_zona, id_address_clas, id_address_type, id_contrato,
            id_proveedor, id_pais, id_estado, id_condado, id_ciudad, id_zip, notas,
            observaciones, id_cli_anterior, id_cliente_nuevo, id_dir_anterior, id_direccion_nuevo
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");
    
    // Bind de todos los valores (manteniendo originales excepto id_direccion_nuevo)
    $stmt_ins->bind_param(
        'iisddisiiiiiiiiiiisssiiii',
        $row['id_direccion'],
        $row['id_cliente'],
        $row['direccion'],
        $row['lat'],
        $row['lng'],
        $row['id_status'],
        $row['fecha_creacion'],
        $row['cambio'],
        $row['id_geofence'],
        $row['id_zona'],
        $row['id_address_clas'],
        $row['id_address_type'],
        $row['id_contrato'],
        $row['id_proveedor'],
        $row['id_pais'],
        $row['id_estado'],
        $row['id_condado'],
        $row['id_ciudad'],
        $row['id_zip'],
        $row['notas'],
        $row['observaciones'],
        $row['id_cli_anterior'],
        $row['id_cliente_nuevo'],
        $row['id_dir_anterior'],
        $id_dir_nuevo_usar // Valor calculado
    );
    
    if ($stmt_ins->execute()) {
        $insertados++;
        if ($insertados % 100 == 0) {
            echo "✓ Procesados: $insertados registros...\n";
        }
    }
    $stmt_ins->close();
}
$stmt_backup->close();

// ======================================================================
// RESUMEN FINAL
// ======================================================================

echo "\n╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  RESUMEN DE RECONSTRUCCIÓN                                      ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "  Backup creado: $backup_table ($total_backup registros)\n";
echo "  Tabla direcciones: VACÍA → RECONSTRUIDA\n";
echo "  Registros insertados: $insertados\n";
echo "  Registros saltados (duplicados por dirección + cliente_nuevo): $skipped_duplicados\n";
echo "  Registros saltados (sin id_cliente_nuevo): $skipped_sin_cliente_nuevo\n";
echo "  Próximo id_direccion_nuevo disponible: $next_id_dir_nuevo\n\n";

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICACIÓN FINAL                                              ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";

// Verificar estado actual
$verif_queries = [
    'Total registros en direcciones' => "SELECT COUNT(*) AS total FROM direcciones",
    'Registros con id_direccion_nuevo IS NULL' => "SELECT COUNT(*) AS total FROM direcciones WHERE id_direccion_nuevo IS NULL",
    'Registros con id_cliente_nuevo IS NULL' => "SELECT COUNT(*) AS total FROM direcciones WHERE id_cliente_nuevo IS NULL",
    'Máximo id_direccion_nuevo asignado' => "SELECT MAX(id_direccion_nuevo) AS max_id FROM direcciones WHERE id_direccion_nuevo >= 5170"
];

foreach ($verif_queries as $desc => $query) {
    $result = $m->query($query);
    $value = $result->fetch_assoc();
    $total = $value['total'] ?? $value['max_id'] ?? 0;
    echo "  $desc: $total\n";
}

$m->close();

echo "\n[OK] RECONSTRUCCIÓN COMPLETADA EXITOSAMENTE\n";
echo "💡 Backup de seguridad: $backup_table\n";
echo "💡 Para restaurar en caso de error:\n";
echo "   TRUNCATE TABLE direcciones;\n";
echo "   INSERT INTO direcciones SELECT * FROM $backup_table;\n\n";

?>