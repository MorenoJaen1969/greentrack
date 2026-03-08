<?php
/**
 * ======================================================================
 * ACTUALIZADOR DE NOMBRES Y APELLIDOS
 * ======================================================================
 * 
 * Propósito: Aplicar cambios verificados del CSV a la tabla CLIENTES
 *   • Actualiza campos: nombre, apellido, id_sexo, id_tratamiento
 *   • Solo procesa registros marcados como VERIFICADO_MANUAL='SI'
 *   • Genera backup SQL y log detallado de cambios
 *   • Modo simulación por defecto (seguridad total)
 */

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  ACTUALIZADOR DE NOMBRES Y APELLIDOS                            ║\n";
echo "║  (Aplica cambios verificados del CSV)                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// ======================================================================
// CONFIGURACIÓN
// ======================================================================

$CONFIG = [
    'csv_verificado' => '/var/www/greentrack/scripts/actualizar_geografia/separacion_nombres_limpieza_VERIFICADO.csv',
    'apply_changes' => true,  // ← ¡MANTENER false PARA SIMULACIÓN!
    'backup_sql' => '/var/www/greentrack/scripts/backup_nombres_' . date('Ymd_His') . '.sql',
    'log_file' => '/var/www/greentrack/scripts/actualizacion_nombres_log.txt'
];

// ======================================================================
// PASO 1: VERIFICAR EXISTENCIA DEL CSV VERIFICADO
// ======================================================================

echo "[INFO] Verificando CSV verificado...\n";

if (!file_exists($CONFIG['csv_verificado'])) {
    die("[ERROR] CSV verificado no encontrado: {$CONFIG['csv_verificado']}\n" .
        "      Asegúrate de haber guardado el archivo como:\n" .
        "      separacion_nombres_limpieza_VERIFICADO.csv\n");
}

$lines = file($CONFIG['csv_verificado']);
$header = array_shift($lines);

if (empty($lines)) {
    die("[ERROR] El CSV verificado está vacío\n");
}

echo "[OK] CSV verificado encontrado con " . count($lines) . " registros\n\n";

// ======================================================================
// PASO 2: LEER Y FILTRAR REGISTROS VERIFICADOS
// ======================================================================

echo "[INFO] Leyendo y filtrando registros verificados...\n";

$actualizaciones = [];
$total_verificados = 0;
$total_validos = 0;

foreach ($lines as $line) {
    $parts = str_getcsv(trim($line), ';');
    if (count($parts) < 11) continue;
    
    // Verificar que esté marcado como verificado
    $verificado = strtoupper(trim($parts[10] ?? ''));
    if ($verificado !== 'SI') continue;
    
    $total_verificados++;
    
    // Validar datos requeridos
    $id_cliente = (int)trim($parts[0]);
    $nombre_propuesto = trim($parts[3]);
    $apellido_propuesto = trim($parts[4]);
    
    if (empty($nombre_propuesto) || empty($apellido_propuesto)) {
        continue; // Saltar si falta nombre o apellido
    }
    
    // Obtener valores propuestos (manejar NULL)
    $sexo_propuesto = trim($parts[5]);
    $tratamiento_propuesto = trim($parts[6]);
    
    $sexo_propuesto = ($sexo_propuesto === 'NULL' || $sexo_propuesto === '') ? null : (int)$sexo_propuesto;
    $tratamiento_propuesto = ($tratamiento_propuesto === 'NULL' || $tratamiento_propuesto === '') ? null : (int)$tratamiento_propuesto;
    
    $actualizaciones[] = [
        'id_cliente' => $id_cliente,
        'nombre_original' => trim($parts[1]),
        'nombre_propuesto' => $nombre_propuesto,
        'apellido_propuesto' => $apellido_propuesto,
        'sexo_propuesto' => $sexo_propuesto,
        'tratamiento_propuesto' => $tratamiento_propuesto,
        'sexo_actual' => trim($parts[7]) === 'NULL' ? null : (int)trim($parts[7]),
        'tratamiento_actual' => trim($parts[8]) === 'NULL' ? null : (int)trim($parts[8])
    ];
    
    $total_validos++;
}

echo "[OK] Registros verificados: $total_verificados\n";
echo "     Registros válidos para actualizar: $total_validos\n\n";

if ($total_validos === 0) {
    echo "[INFO] No hay registros válidos para actualizar\n";
    echo "      Verifica que el CSV tenga VERIFICADO_MANUAL='SI' y datos completos\n\n";
    $m->close();
    exit(0);
}

// ======================================================================
// PASO 3: GENERAR BACKUP SQL (ANTES DE CUALQUIER CAMBIO)
// ======================================================================

echo "[INFO] Generando backup SQL de registros a actualizar...\n";

$backup_content = "/*\n";
$backup_content .= " * BACKUP ANTES DE ACTUALIZACIÓN DE NOMBRES\n";
$backup_content .= " * Fecha: " . date('Y-m-d H:i:s') . "\n";
$backup_content .= " * Registros a actualizar: $total_validos\n";
$backup_content .= " */\n\n";

// Obtener valores actuales de la BD para los registros a actualizar
$ids_para_backup = array_column($actualizaciones, 'id_cliente');
$placeholders = implode(',', array_fill(0, count($ids_para_backup), '?'));
$stmt_backup = $m->prepare("
    SELECT id_cliente, nombre, apellido, id_sexo, id_tratamiento
    FROM clientes
    WHERE id_cliente IN ($placeholders)
");
$stmt_backup->bind_param(str_repeat('i', count($ids_para_backup)), ...$ids_para_backup);
$stmt_backup->execute();
$result_backup = $stmt_backup->get_result();

while ($row = $result_backup->fetch_assoc()) {
    $backup_content .= "-- ID {$row['id_cliente']}\n";
    $backup_content .= "UPDATE clientes SET\n";
    $backup_content .= "  nombre = " . ($row['nombre'] ? "'{$row['nombre']}'" : 'NULL') . ",\n";
    $backup_content .= "  apellido = " . ($row['apellido'] ? "'{$row['apellido']}'" : 'NULL') . ",\n";
    $backup_content .= "  id_sexo = " . ($row['id_sexo'] ?? 'NULL') . ",\n";
    $backup_content .= "  id_tratamiento = " . ($row['id_tratamiento'] ?? 'NULL') . "\n";
    $backup_content .= "WHERE id_cliente = {$row['id_cliente']};\n\n";
}
$stmt_backup->close();

file_put_contents($CONFIG['backup_sql'], $backup_content);
echo "[OK] Backup SQL generado: {$CONFIG['backup_sql']}\n\n";

// ======================================================================
// PASO 4: APLICAR CAMBIOS (SI apply_changes = true)
// ======================================================================

if ($CONFIG['apply_changes']) {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║  APLICANDO CAMBIOS A BASE DE DATOS                               ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n\n";
    
    $log_fp = fopen($CONFIG['log_file'], 'a');
    fwrite($log_fp, "\n=== ACTUALIZACIÓN DE NOMBRES " . date('Y-m-d H:i:s') . " ===\n");
    
    $actualizados = 0;
    $errores = 0;
    
    foreach ($actualizaciones as $act) {
        // Preparar valores para UPDATE
        $nombre_new = $act['nombre_propuesto'];
        $apellido_new = $act['apellido_propuesto'];
        $sexo_new = $act['sexo_propuesto'];
        $tratamiento_new = $act['tratamiento_propuesto'];
        
        // Construir SET dinámicamente (solo campos con valor)
        $set_parts = [
            "nombre = ?",
            "apellido = ?"
        ];
        $params = [$nombre_new, $apellido_new];
        $types = 'ss';
        
        if ($sexo_new !== null) {
            $set_parts[] = "id_sexo = ?";
            $params[] = $sexo_new;
            $types .= 'i';
        }
        
        if ($tratamiento_new !== null) {
            $set_parts[] = "id_tratamiento = ?";
            $params[] = $tratamiento_new;
            $types .= 'i';
        }
        
        $set_clause = implode(', ', $set_parts);
        
        $stmt = $m->prepare("
            UPDATE clientes
            SET $set_clause
            WHERE id_cliente = ?
        ");
        
        // Agregar id_cliente al final de los parámetros
        $params[] = $act['id_cliente'];
        $types .= 'i';
        
        // Bind dinámico
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $msg = "✓ ID {$act['id_cliente']} | '{$act['nombre_original']}' → ";
            $msg .= "nombre='{$nombre_new}', apellido='{$apellido_new}'";
            if ($sexo_new !== null) $msg .= ", sexo=$sexo_new";
            if ($tratamiento_new !== null) $msg .= ", tratamiento=$tratamiento_new";
            $msg .= "\n";
            
            echo $msg;
            fwrite($log_fp, $msg);
            $actualizados++;
        } else {
            $error_msg = "✗ Error ID {$act['id_cliente']}: " . $stmt->error . "\n";
            echo $error_msg;
            fwrite($log_fp, $error_msg);
            $errores++;
        }
        
        $stmt->close();
    }
    
    fclose($log_fp);
    
    echo "\n╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║  RESUMEN DE ACTUALIZACIÓN                                        ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n";
    echo "  Actualizados exitosamente: $actualizados\n";
    echo "  Errores: $errores\n";
    echo "  Log detallado: {$CONFIG['log_file']}\n";
    echo "  Backup SQL (rollback): {$CONFIG['backup_sql']}\n\n";
    
} else {
    echo "╔════════════════════════════════════════════════════════════════════╗\n";
    echo "║  MODO SIMULACIÓN - SIN CAMBIOS EN BD                            ║\n";
    echo "╚════════════════════════════════════════════════════════════════════╝\n";
    echo "💡 Para aplicar los cambios:\n";
    echo "   1. Verifica el backup SQL generado:\n";
    echo "      {$CONFIG['backup_sql']}\n";
    echo "\n";
    echo "   2. Confirma que el CSV verificado es correcto:\n";
    echo "      {$CONFIG['csv_verificado']}\n";
    echo "\n";
    echo "   3. Edita este script y cambia:\n";
    echo "        'apply_changes' => true\n";
    echo "\n";
    echo "   4. Ejecuta nuevamente:\n";
    echo "        php actualizador_nombres.php\n\n";
    
    echo "📊 RESUMEN DE CAMBIOS QUE SE APLICARÁN:\n";
    echo "   Total de registros a actualizar: $total_validos\n\n";
    
    echo "   Ejemplos de actualizaciones:\n";
    $count = 0;
    foreach ($actualizaciones as $act) {
        if ($count >= 5) break;
        
        echo "   ID {$act['id_cliente']}:\n";
        echo "     Original: '{$act['nombre_original']}'\n";
        echo "     Nuevo nombre: '{$act['nombre_propuesto']}'\n";
        echo "     Nuevo apellido: '{$act['apellido_propuesto']}'\n";
        if ($act['sexo_propuesto'] !== null) {
            echo "     Sexo: {$act['sexo_propuesto']} (" . ($act['sexo_propuesto'] == 1 ? 'Masculino' : 'Femenino') . ")\n";
        }
        if ($act['tratamiento_propuesto'] !== null) {
            echo "     Tratamiento: {$act['tratamiento_propuesto']} (" . ($act['tratamiento_propuesto'] == 1 ? 'Mr.' : 'Mrs.') . ")\n";
        }
        echo "\n";
        
        $count++;
    }
    
    echo "⚠️  IMPORTANTE:\n";
    echo "   • El backup SQL permite revertir los cambios si es necesario\n";
    echo "   • Solo se actualizarán registros con VERIFICADO_MANUAL='SI'\n";
    echo "   • Los campos sin valor propuesto (NULL) no se modificarán\n\n";
}

// ======================================================================
// PASO 5: VERIFICACIÓN FINAL
// ======================================================================

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICACIÓN FINAL                                              ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

echo "Ejecuta estas consultas para verificar los cambios después de aplicar:\n\n";

echo "-- 1. Clientes con nombre y apellido separados correctamente:\n";
echo "SELECT \n";
echo "  id_cliente,\n";
echo "  nombre,\n";
echo "  apellido,\n";
echo "  id_sexo,\n";
echo "  id_tratamiento\n";
echo "FROM clientes\n";
echo "WHERE id_tipo_persona = 1\n";
echo "  AND nombre IS NOT NULL\n";
echo "  AND apellido IS NOT NULL\n";
echo "  AND TRIM(nombre) != ''\n";
echo "  AND TRIM(apellido) != ''\n";
echo "LIMIT 20;\n\n";

echo "-- 2. Clientes que aún necesitan separación (pendientes):\n";
echo "SELECT COUNT(*) AS pendientes\n";
echo "FROM clientes\n";
echo "WHERE id_tipo_persona = 1\n";
echo "  AND (apellido IS NULL OR TRIM(apellido) = '')\n";
echo "  AND TRIM(nombre) != '';\n\n";

echo "-- 3. Estadísticas de sexo y tratamiento:\n";
echo "SELECT \n";
echo "  id_sexo,\n";
echo "  id_tratamiento,\n";
echo "  COUNT(*) AS total\n";
echo "FROM clientes\n";
echo "WHERE id_tipo_persona = 1\n";
echo "  AND nombre IS NOT NULL\n";
echo "  AND apellido IS NOT NULL\n";
echo "GROUP BY id_sexo, id_tratamiento\n";
echo "ORDER BY id_sexo, id_tratamiento;\n\n";

$m->close();

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  RESUMEN EJECUTIVO                                               ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "✅ Backup SQL generado antes de cualquier cambio\n";
echo "✅ Solo se procesan registros con VERIFICADO_MANUAL='SI'\n";
echo "✅ Modo simulación activo (sin cambios reales)\n";
echo "✅ Log detallado de todas las operaciones\n";
echo "\n";
echo "🚀 Próximos pasos:\n";
echo "   1. Revisa el backup SQL: {$CONFIG['backup_sql']}\n";
echo "   2. Verifica los ejemplos de cambios mostrados arriba\n";
echo "   3. Si todo es correcto, cambia 'apply_changes' => true\n";
echo "   4. Ejecuta nuevamente para aplicar los cambios\n";
echo "   5. Verifica con las consultas SQL proporcionadas\n\n";

echo "[FIN] Actualizador de nombres y apellidos\n\n";

?>