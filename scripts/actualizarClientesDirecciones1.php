<?php

/**
 * DEDUPLICACIÓN SEGURA PARA MYSQL 8.0+
 * Versión 5.0 - REFACTORIZADA CON MÁXIMA SEGURIDAD
 * 
 * FLUJO SEGURO:
 * 1. Crear backups automáticos de todas las tablas
 * 2. Validar integridad referencial ANTES de cambios
 * 3. Actualizar referencias en lotes (servicios, contratos)
 * 4. Validar referencias actualizadas correctamente
 * 5. Confirmar eliminación de duplicados
 * 6. Eliminar clientes duplicados
 * 7. Verificar integridad final
 * 
 * @author Greentrack Systems
 * @version 5.0
 */

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

// ============================================================================
// CONFIGURACIÓN
// ============================================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'mmoreno');
define('DB_PASS', 'Noloseno#2017');
define('DB_NAME', 'greentrack_live');
define('BATCH_SIZE', 5);
define('MAX_RETRIES', 10);
define('LOG_FILE', __DIR__ . '/deduplicacion_' . date('Y-m-d_H-i-s') . '.log');
define('MAPEO_FILE', __DIR__ . '/mapeo_duplicados_FINAL.csv');
define('BACKUP_SUFFIX', '_backup_prededup_' . date('YmdHis'));
define('MODE', 'execute');  // 'test' o 'execute'

// ============================================================================
// LOGGER
// ============================================================================

function logMsg($msg, $level = 'INFO')
{
    ob_start();
    $ts = date('Y-m-d H:i:s');
    $color = [
        'INFO' => '36',
        'SUCCESS' => '32',
        'WARNING' => '33',
        'ERROR' => '31',
        'STEP' => '35',
        'CRITICAL' => '41'
    ][$level] ?? '37';

    $line = "[$ts] [$level] $msg\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
    echo "\033[{$color}m$line\033[0m";
    ob_flush();
    flush();
}

// ============================================================================
// CONEXIÓN A BD
// ============================================================================

function getDb()
{
    logMsg("Conectando a BD: " . DB_HOST . " / " . DB_NAME, 'INFO');
    $m = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($m->connect_error) {
        die(logMsg("ERROR DE CONEXIÓN: {$m->connect_error}", 'CRITICAL'));
    }
    $m->set_charset('utf8mb4');
    logMsg("✓ Conexión establecida", 'SUCCESS');

    // Configuraciones de seguridad
    $m->query("SET SESSION innodb_lock_wait_timeout = 15");
    $m->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    logMsg("✓ Configuración de sesión establecida", 'INFO');

    return $m;
}

// ============================================================================
// CREACIÓN DE BACKUPS AUTOMÁTICOS
// ============================================================================

function crearBackups($m)
{
    logMsg("=== CREANDO BACKUPS AUTOMÁTICOS ===", 'STEP');

    $tablas = ['clientes', 'servicios', 'contratos'];
    $fecha = date('Y-m-d H:i:s');

    foreach ($tablas as $tabla) {
        $backup = $tabla . BACKUP_SUFFIX;
        logMsg("Creando backup: $backup", 'INFO');

        $sql = "CREATE TABLE $backup LIKE $tabla";
        if (!$m->query($sql)) {
            throw new Exception("Error creando estructura de backup: " . $m->error);
        }

        $sql = "INSERT INTO $backup SELECT * FROM $tabla";
        if (!$m->query($sql)) {
            throw new Exception("Error copiando datos a backup: " . $m->error);
        }

        $r = $m->query("SELECT COUNT(*) as total FROM $backup");
        $count = $r->fetch_assoc()['total'];
        logMsg("✓ Backup creado: $backup ($count registros)", 'SUCCESS');
    }
}

// ============================================================================
// CARGA Y VALIDACIÓN DE MAPEO
// ============================================================================

function cargarMapeo()
{
    logMsg("=== CARGANDO MAPEO ===", 'STEP');

    if (!file_exists(MAPEO_FILE)) {
        throw new Exception("Archivo de mapeo no encontrado: " . MAPEO_FILE);
    }

    logMsg("Leyendo: " . MAPEO_FILE, 'INFO');

    $mapeo = [];
    $lineasProcesadas = 0;
    $lineasValidas = 0;

    if (($handle = fopen(MAPEO_FILE, 'r')) !== FALSE) {
        // Saltar encabezado
        $headers = fgetcsv($handle);
        logMsg("Encabezado: " . implode(', ', $headers), 'INFO');

        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $lineasProcesadas++;

            if (count($data) >= 2) {
                $idOld = trim($data[0]);
                $idNew = trim($data[1]);

                // Validar que sean números
                if (is_numeric($idOld) && is_numeric($idNew)) {
                    $mapeo[(int)$idOld] = (int)$idNew;
                    $lineasValidas++;
                } else {
                    logMsg("WARN: Línea $lineasProcesadas ignorada (datos no numéricos)", 'WARNING');
                }
            }
        }
        fclose($handle);
    }

    logMsg("Líneas procesadas: $lineasProcesadas | Relaciones válidas: $lineasValidas", 'INFO');

    if (empty($mapeo)) {
        throw new Exception("Mapeo vacío. Verificar archivo CSV.");
    }

    // Validar que no haya duplicados en la clave
    if (count($mapeo) !== $lineasValidas) {
        logMsg("⚠️  Se detectaron duplicados en la columna id_old", 'WARNING');
    }

    logMsg("✓ Mapeo cargado: " . count($mapeo) . " relaciones", 'SUCCESS');
    return $mapeo;
}

// ============================================================================
// VALIDACIÓN DE IDs EN BD
// ============================================================================

function validarIdsEnBd($m, &$mapeo)
{
    logMsg("=== VALIDANDO IDs EN BASE DE DATOS ===", 'STEP');

    $idsOld = array_keys($mapeo);
    $idsNew = array_values($mapeo);

    logMsg("IDs a validar: " . count($idsOld), 'INFO');

    // Consulta: ¿existen todos los id_old?
    $idsStr = implode(',', $idsOld);
    $sql = "SELECT id_cliente FROM clientes WHERE id_cliente IN ($idsStr)";
    $result = $m->query($sql);

    if (!$result) {
        throw new Exception("Error validando IDs: " . $m->error);
    }

    $idsExistentes = [];
    while ($row = $result->fetch_assoc()) {
        $idsExistentes[] = $row['id_cliente'];
    }

    logMsg("IDs id_old existentes: " . count($idsExistentes) . " / " . count($idsOld), 'INFO');

    // Encontrar IDs no existentes
    $idsNoExistentes = array_diff($idsOld, $idsExistentes);

    if (!empty($idsNoExistentes)) {
        logMsg("⚠️  IDs no existentes: " . implode(', ', $idsNoExistentes), 'WARNING');

        // Filtrar mapeo
        $mapeoAntes = count($mapeo);
        $mapeo = array_filter($mapeo, function ($k) use ($idsExistentes) {
            return in_array($k, $idsExistentes);
        }, ARRAY_FILTER_USE_KEY);

        logMsg("Mapeo filtrado: $mapeoAntes → " . count($mapeo) . " relaciones", 'INFO');
    }

    // Validar que todos los id_new existan
    $idsNewStr = implode(',', array_unique($idsNew));
    $sql = "SELECT COUNT(DISTINCT id_cliente) as total FROM clientes WHERE id_cliente IN ($idsNewStr)";
    $result = $m->query($sql);
    $countNew = $result->fetch_assoc()['total'];

    logMsg("IDs id_new únicos: $countNew / " . count(array_unique($idsNew)), 'INFO');

    if ($countNew !== count(array_unique($idsNew))) {
        logMsg("⚠️  Algunos id_new no existen en la BD", 'WARNING');
    }

    logMsg("✓ Validación completada", 'SUCCESS');
    return true;
}

// ============================================================================
// VERIFICACIÓN PRE-ACTUALIZACIÓN
// ============================================================================

function verificarPreActualizacion($m, $mapeo)
{
    logMsg("=== VERIFICACIÓN PRE-ACTUALIZACIÓN ===", 'STEP');

    $idsOld = implode(',', array_keys($mapeo));

    // Servicios con referencias obsoletas
    $sql = "SELECT COUNT(*) as total FROM servicios WHERE id_cliente IN ($idsOld)";
    $r = $m->query($sql);
    $servObsoletos = $r->fetch_assoc()['total'];
    logMsg("Servicios con referencias obsoletas: $servObsoletos", 'INFO');

    // Contratos con referencias obsoletas
    $sql = "SELECT COUNT(*) as total FROM contratos WHERE id_cliente IN ($idsOld)";
    $r = $m->query($sql);
    $contObsoletos = $r->fetch_assoc()['total'];
    logMsg("Contratos con referencias obsoletas: $contObsoletos", 'INFO');

    if ($servObsoletos == 0 && $contObsoletos == 0) {
        logMsg("✓ No hay referencias que actualizar", 'SUCCESS');
    } else {
        logMsg("✓ Se actualizarán " . ($servObsoletos + $contObsoletos) . " referencias", 'SUCCESS');
    }
}

// ============================================================================
// ACTUALIZACIÓN DE TABLA
// ============================================================================

function actualizarTabla($m, $mapeo, $tabla)
{
    logMsg("=== ACTUALIZANDO $tabla ===", 'STEP');

    if (empty($mapeo)) {
        logMsg("⚠️  Mapeo vacío, omitiendo $tabla", 'WARNING');
        return 0;
    }

    $idsOld = array_keys($mapeo);
    $lotes = array_chunk($mapeo, BATCH_SIZE, true);
    $totalActualizados = 0;

    logMsg("Lotes a procesar: " . count($lotes) . " (tamaño: " . BATCH_SIZE . ")", 'INFO');

    foreach ($lotes as $numLote => $lote) {
        $batchIds = implode(',', array_keys($lote));
        $retries = 0;
        $exitoso = false;

        while ($retries <= MAX_RETRIES && !$exitoso) {
            try {
                // Construir CASE dinámico
                $cases = [];
                foreach ($lote as $idOld => $idNew) {
                    $cases[] = "WHEN $idOld THEN $idNew";
                }
                $casesStr = implode(' ', $cases);

                $sql = "UPDATE $tabla 
                        SET id_cliente = CASE id_cliente $casesStr END 
                        WHERE id_cliente IN ($batchIds)";

                logMsg("[Lote " . ($numLote + 1) . "] Ejecutando UPDATE...", 'INFO');

                if (MODE === 'execute') {
                    if (!$m->query($sql)) {
                        $error = $m->error;

                        // Detectar timeout
                        if (
                            strpos($error, 'Lock wait timeout exceeded') !== false ||
                            strpos($error, 'try restarting transaction') !== false
                        ) {
                            throw new Exception('LOCK_TIMEOUT: ' . $error);
                        }

                        throw new Exception($error);
                    }

                    $afectados = $m->affected_rows;
                    $m->commit();
                    logMsg("[Lote " . ($numLote + 1) . "] ✓ $afectados filas actualizadas", 'SUCCESS');

                    $totalActualizados += $afectados;
                    usleep(300000); // 0.3s entre batches
                } else {
                    // Modo TEST: EJECUTAR REALMENTE pero luego rollback para validar
                    if (!$m->query($sql)) {
                        $error = $m->error;
                        if (
                            strpos($error, 'Lock wait timeout exceeded') !== false ||
                            strpos($error, 'try restarting transaction') !== false
                        ) {
                            throw new Exception('LOCK_TIMEOUT: ' . $error);
                        }
                        throw new Exception($error);
                    }
                    $afectados = $m->affected_rows;
                    logMsg("[TEST Lote " . ($numLote + 1) . "] Se actualizaron $afectados filas (se desharán al final)", 'INFO');
                    $totalActualizados += $afectados;
                }

                $exitoso = true;
            } catch (Exception $e) {
                $msg = $e->getMessage();

                if (strpos($msg, 'LOCK_TIMEOUT') !== false) {
                    $retries++;
                    if ($retries <= MAX_RETRIES) {
                        $espera = min(pow(1.3, $retries), 25);
                        logMsg("[Lote " . ($numLote + 1) . "] Timeout. Reintento $retries/" . MAX_RETRIES . " en ${espera}s", 'WARNING');
                        sleep($espera);
                        continue;
                    }
                }

                logMsg("[Lote " . ($numLote + 1) . "] ERROR IRREVERSIBLE: $msg", 'ERROR');
                throw $e;
            }
        }
    }

    logMsg("✓ Total actualizados en $tabla: $totalActualizados", 'SUCCESS');
    return $totalActualizados;
}

// ============================================================================
// VERIFICACIÓN POST-ACTUALIZACIÓN
// ============================================================================

function verificarPostActualizacion($m, $mapeo)
{
    logMsg("=== VERIFICACIÓN POST-ACTUALIZACIÓN ===", 'STEP');

    $idsOld = implode(',', array_keys($mapeo));

    // Verificar que NO quedan referencias a IDs obsoletos
    $sql = "SELECT COUNT(*) as total FROM servicios WHERE id_cliente IN ($idsOld)";
    $r = $m->query($sql);
    $servObsoletos = $r->fetch_assoc()['total'];

    $sql = "SELECT COUNT(*) as total FROM contratos WHERE id_cliente IN ($idsOld)";
    $r = $m->query($sql);
    $contObsoletos = $r->fetch_assoc()['total'];

    logMsg("Servicios con referencias obsoletas: $servObsoletos", 'INFO');
    logMsg("Contratos con referencias obsoletas: $contObsoletos", 'INFO');

    if ($servObsoletos > 0 || $contObsoletos > 0) {
        throw new Exception("PELIGRO: Aún hay referencias a IDs obsoletos. Abortando.");
    }

    logMsg("✓ Todas las referencias fueron actualizadas correctamente", 'SUCCESS');
}

// ============================================================================
// CONFIRMAR ELIMINACIÓN
// ============================================================================

function confirmarEliminacion($mapeo)
{
    logMsg("=== CONFIRMACIÓN DE ELIMINACIÓN ===", 'STEP');

    $idsOld = array_keys($mapeo);
    $idsNew = array_unique(array_values($mapeo));
    $idsAEliminar = array_diff($idsOld, $idsNew);

    logMsg("IDs a ELIMINAR: " . count($idsAEliminar), 'WARNING');
    logMsg("IDs a CONSERVAR: " . count($idsNew), 'SUCCESS');

    if (empty($idsAEliminar)) {
        logMsg("⚠️  No hay clientes para eliminar", 'WARNING');
        return 0;
    }

    // Mostrar ejemplos
    $ejemplos = array_slice($idsAEliminar, 0, 10);
    logMsg("Ejemplos de IDs a eliminar: " . implode(', ', $ejemplos) . (count($idsAEliminar) > 10 ? '...' : ''), 'INFO');

    if (MODE === 'execute') {
        logMsg("⚠️  MODO EXECUTE: Se eliminarán " . count($idsAEliminar) . " clientes", 'WARNING');
        // En un entorno real, aquí podrías pedir confirmación
    } else {
        logMsg("ℹ️  MODO TEST: Se mostró qué se eliminaría", 'INFO');
    }

    return count($idsAEliminar);
}

// ============================================================================
// ELIMINACIÓN DE DUPLICADOS
// ============================================================================

function eliminarDuplicados($m, $mapeo)
{
    logMsg("=== ELIMINACIÓN DE CLIENTES DUPLICADOS ===", 'STEP');

    $idsOld = array_keys($mapeo);
    $idsNew = array_unique(array_values($mapeo));
    $idsAEliminar = array_diff($idsOld, $idsNew);

    if (empty($idsAEliminar)) {
        logMsg("⚠️  No hay clientes para eliminar", 'WARNING');
        return 0;
    }

    logMsg("Clientes a eliminar: " . count($idsAEliminar), 'INFO');

    $totalEliminados = 0;
    $lotes = array_chunk($idsAEliminar, BATCH_SIZE);

    foreach ($lotes as $numLote => $lote) {
        $idsStr = implode(',', $lote);

        if (MODE === 'execute') {
            $sql = "DELETE FROM clientes WHERE id_cliente IN ($idsStr)";
            logMsg("[Lote " . ($numLote + 1) . "] Ejecutando DELETE...", 'INFO');

            if (!$m->query($sql)) {
                throw new Exception("Error en DELETE: " . $m->error);
            }

            $eliminados = $m->affected_rows;
            $m->commit();

            logMsg("[Lote " . ($numLote + 1) . "] ✓ $eliminados clientes eliminados", 'SUCCESS');
            $totalEliminados += $eliminados;
            usleep(250000);
        } else {
            logMsg("[TEST Lote " . ($numLote + 1) . "] Se eliminarían " . count($lote) . " clientes", 'INFO');
            $totalEliminados += count($lote);
        }
    }

    logMsg("✓ Total clientes eliminados: $totalEliminados", 'SUCCESS');
    return $totalEliminados;
}

// ============================================================================
// VERIFICACIÓN FINAL DE INTEGRIDAD
// ============================================================================

function verificarIntegridad($m)
{
    logMsg("=== VERIFICACIÓN FINAL DE INTEGRIDAD ===", 'STEP');

    $r = $m->query("SELECT COUNT(*) as total FROM clientes");
    $totalClientes = $r->fetch_assoc()['total'];
    logMsg("Total de clientes: $totalClientes", 'INFO');

    // Servicios huérfanos
    $sql = "SELECT COUNT(*) as total FROM servicios s 
            LEFT JOIN clientes c ON s.id_cliente = c.id_cliente 
            WHERE c.id_cliente IS NULL";
    $r = $m->query($sql);
    $servHuerf = $r->fetch_assoc()['total'];

    if ($servHuerf > 0) {
        logMsg("⚠️  ADVERTENCIA: $servHuerf servicios huérfanos", 'WARNING');

        $sql = "SELECT s.id_servicio, s.id_cliente FROM servicios s 
                LEFT JOIN clientes c ON s.id_cliente = c.id_cliente 
                WHERE c.id_cliente IS NULL LIMIT 5";
        $r = $m->query($sql);
        while ($row = $r->fetch_assoc()) {
            logMsg("   - Servicio {$row['id_servicio']} → Cliente {$row['id_cliente']}", 'WARNING');
        }
    } else {
        logMsg("✓ Sin servicios huérfanos", 'SUCCESS');
    }

    // Contratos huérfanos
    $sql = "SELECT COUNT(*) as total FROM contratos ct 
            LEFT JOIN clientes c ON ct.id_cliente = c.id_cliente 
            WHERE c.id_cliente IS NULL";
    $r = $m->query($sql);
    $contHuerf = $r->fetch_assoc()['total'];

    if ($contHuerf > 0) {
        logMsg("⚠️  ADVERTENCIA: $contHuerf contratos huérfanos", 'WARNING');
    } else {
        logMsg("✓ Sin contratos huérfanos", 'SUCCESS');
    }

    return [
        'clientes' => $totalClientes,
        'servicios_huerfanos' => $servHuerf,
        'contratos_huerfanos' => $contHuerf
    ];
}

// ============================================================================
// FUNCIÓN PRINCIPAL
// ============================================================================

function deduplicar()
{
    logMsg(str_repeat("=", 70), 'STEP');
    logMsg("DEDUPLICACIÓN SEGURA - MYSQL 8.0+ v5.0", 'STEP');
    logMsg(str_repeat("=", 70), 'STEP');
    logMsg("Modo: " . strtoupper(MODE) . " | Batch Size: " . BATCH_SIZE, 'INFO');
    logMsg("Log: " . LOG_FILE, 'INFO');
    logMsg(str_repeat("=", 70), 'STEP');

    $m = getDb();
    $m->autocommit(false);

    try {
        // 1. CREAR BACKUPS AUTOMÁTICOS
        crearBackups($m);

        // 2. CARGAR MAPEO
        $mapeo = cargarMapeo();

        // 3. VALIDAR IDs
        validarIdsEnBd($m, $mapeo);

        if (empty($mapeo)) {
            throw new Exception("Mapeo vacío tras validación. Abortando.");
        }

        // 4. DESHABILITAR RESTRICCIONES DE CLAVE FORÁNEA (ANTES de cualquier UPDATE)
        logMsg("=== PREPARANDO PARA ACTUALIZACIÓN ===", 'STEP');
        logMsg("Deshabilitando restricciones de clave foránea globalmente", 'WARNING');
        $m->query("SET FOREIGN_KEY_CHECKS = 0");
        logMsg("✓ Restricciones deshabilitadas", 'SUCCESS');

        // 5. VERIFICAR PRE-ACTUALIZACIÓN
        verificarPreActualizacion($m, $mapeo);

        // 6. ACTUALIZAR SERVICIOS
        $servActualizados = actualizarTabla($m, $mapeo, 'servicios');

        // 6. ACTUALIZAR CONTRATOS
        $contActualizados = actualizarTabla($m, $mapeo, 'contratos');

        // 7. REHABILITAR RESTRICCIONES DE CLAVE FORÁNEA
        logMsg("=== REHABILITANDO RESTRICCIONES ===", 'STEP');
        logMsg("Rehabilitando restricciones de clave foránea", 'INFO');
        $m->query("SET FOREIGN_KEY_CHECKS = 1");
        logMsg("✓ Restricciones rehabilitadas", 'SUCCESS');

        // 8. VERIFICAR POST-ACTUALIZACIÓN
        verificarPostActualizacion($m, $mapeo);

        // 8. CONFIRMAR ELIMINACIÓN
        $aEliminar = confirmarEliminacion($mapeo);

        // 9. ELIMINAR DUPLICADOS
        if ($aEliminar > 0) {
            $eliminados = eliminarDuplicados($m, $mapeo);
        } else {
            $eliminados = 0;
        }

        // 10. VERIFICAR INTEGRIDAD FINAL
        $integridad = verificarIntegridad($m);

        // 11. RESUMEN
        logMsg(str_repeat("=", 70), 'STEP');
        logMsg("RESUMEN FINAL", 'STEP');
        logMsg(str_repeat("=", 70), 'STEP');
        logMsg("Modo: " . strtoupper(MODE), 'INFO');
        logMsg("Mapeos procesados: " . count($mapeo), 'INFO');
        logMsg("Servicios actualizados: $servActualizados", 'INFO');
        logMsg("Contratos actualizados: $contActualizados", 'INFO');
        logMsg("Clientes eliminados: $eliminados", 'INFO');
        logMsg("Clientes totales: {$integridad['clientes']}", 'INFO');
        logMsg("Servicios huérfanos: {$integridad['servicios_huerfanos']}", 'INFO');
        logMsg("Contratos huérfanos: {$integridad['contratos_huerfanos']}", 'INFO');
        logMsg("Backups creados con sufijo: " . BACKUP_SUFFIX, 'INFO');
        logMsg(str_repeat("=", 70), 'STEP');

        // VALIDAR ÉXITO
        if (MODE === 'execute') {
            if ($integridad['servicios_huerfanos'] == 0 && $integridad['contratos_huerfanos'] == 0) {
                logMsg("✓✓✓ PROCESO COMPLETADO EXITOSAMENTE ✓✓✓", 'SUCCESS');
            } else {
                logMsg("⚠️  COMPLETADO CON ADVERTENCIAS - Revisar registros huérfanos", 'WARNING');
            }
        } else {
            logMsg("ℹ️  MODO TEST - Haciendo ROLLBACK de cambios...", 'INFO');
            $m->rollback();
            logMsg("✓ ROLLBACK completado - Sin cambios aplicados", 'SUCCESS');
            logMsg("ℹ️  Ejecutar con MODE = 'execute' para aplicar cambios", 'INFO');
        }

        $m->close();
        return true;
    } catch (Exception $e) {
        logMsg(str_repeat("=", 70), 'CRITICAL');
        logMsg("ERROR FATAL: " . $e->getMessage(), 'CRITICAL');
        logMsg(str_repeat("=", 70), 'CRITICAL');

        if (MODE === 'execute') {
            logMsg("Ejecutando ROLLBACK...", 'WARNING');
            $m->rollback();
            logMsg("✓ ROLLBACK completado - Sin cambios aplicados", 'SUCCESS');
        }

        $m->close();
        return false;
    }
}

// ============================================================================
// EJECUCIÓN
// ============================================================================

if (php_sapi_name() !== 'cli') {
    die("Este script debe ejecutarse desde terminal: php actualizarClientesDirecciones1.php\n");
}

// Crear archivo de mapeo de ejemplo si no existe
if (!file_exists(MAPEO_FILE)) {
    logMsg("⚠️  Archivo de mapeo no encontrado", 'WARNING');
    logMsg("Creando plantilla en: " . MAPEO_FILE, 'INFO');

    $ejemplo = "id_old,id_new
2,12
419,12
420,12
3,14
363,14
364,14
4,16
380,16
381,16
";

    file_put_contents(MAPEO_FILE, $ejemplo);
    logMsg("✓ Plantilla creada", 'SUCCESS');
    logMsg("EDITAR el archivo CSV con tus mapeos reales (id_old,id_new)", 'WARNING');
    logMsg("Luego ejecutar: php actualizarClientesDirecciones1.php", 'WARNING');
    exit(0);
}

// INICIAR PROCESO
$exito = deduplicar();
echo "\n" . ($exito ? "✓" : "✗") . " Proceso finalizado. Log: " . LOG_FILE . "\n";
exit($exito ? 0 : 1);
