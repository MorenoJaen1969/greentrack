<?php

namespace app\controllers;

require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
use DateTime;
use DateTimeZone;
use DateInterval;

class gps_archiverController extends mainModel
{
    private $ultimoToken = null;
    private $tokenExpiraEn = null; // Timestamp de expiración
    private $log_path;
    private $logFile;
    private $errorLogFile;
    private $ultimaCoordenada = [];
    private $o_f;

    private $tabla_origen = 'gps_estado_dispositivos';

    public function __construct()
    {
        // ¡ESTA LÍNEA ES CRUCIAL!
        parent::__construct();

        // Nombre del controlador actual abreviado para reconocer el archivo
        $nom_controlador = "gps_archiverController";
        // ____________________________________________________________________

        $this->log_path = APP_R_PROY . 'app/logs/gps_archiver/';

        if (!file_exists($this->log_path)) {
            mkdir($this->log_path, 0775, true);
            chgrp($this->log_path, 'www-data');
            chmod($this->log_path, 0775); // Asegurarse de que el directorio sea legible y escribible
        }

        $this->logFile = $this->log_path . $nom_controlador . '_' . date('Y-m-d') . '.log';
        $this->errorLogFile = $this->log_path . $nom_controlador . '_error_' . date('Y-m-d') . '.log';

        $this->initializeLogFile(file: $this->logFile);
        $this->initializeLogFile($this->errorLogFile);

        $this->verificarPermisos();

        if (!is_writable($this->log_path)) {
            error_log("No hay permiso de escritura en: " . $this->log_path);
        }

        // Solo intentar chown si NO estamos en CLI o si el usuario real es root
        if (php_sapi_name() !== 'cli' || \posix_getuid() === 0) {
            @chown($this->logFile, 'www-data');
            @chgrp($this->logFile, 'www-data');

            @chown($this->errorLogFile, 'www-data');
            @chgrp($this->errorLogFile, 'www-data');
        }

        // rotación automatica de log (Elimina logs > XX dias)
        $this->rotarLogs(15);

        // if (isset($_COOKIE['clang'])) {
        // 	$this->idioma_act = $_COOKIE['clang'];
        // } else {
        //	$this->idioma_act = "en";
        // }
        // $this->o_f = new otras_fun();
        // $this->idioma_ctrol = $this->o_f->cargar_idioma($this->idioma_act);

    }

    private function initializeLogFile($file)
    {
        if (!file_exists($file)) {
            $initialContent = "[" . date('Y-m-d H:i:s') . "] Archivo de log iniciado" . PHP_EOL;
            $created = file_put_contents($file, $initialContent, FILE_APPEND | LOCK_EX);
            if ($created === false) {
                error_log("No se pudo crear el archivo de log: " . $file);
            } else {
                chmod($file, 0644); // Asegurarse de que el archivo sea legible y escribible
            }
            if (!is_writable($file)) {
                throw new \Exception("El archivo de log no es escribible: " . $file);
            }
        }
    }

    private function verificarPermisos()
    {
        if (!is_writable($this->log_path)) {
            error_log("No hay permiso de escritura en: " . $this->log_path);
        }
    }

    private function rotarLogs($dias)
    {
        $archivos = glob($this->log_path . '*.log');
        $fechaLimite = time() - ($dias * 24 * 60 * 60);

        foreach ($archivos as $archivo) {
            if (filemtime($archivo) < $fechaLimite) {
                unlink($archivo);
            }
        }
    }

    /**
     * Logging interno
     */
    private function log($message, $isError = false)
    {
        $file = $isError ? $this->errorLogFile : $this->logFile;
        if (!file_exists($file)) {
            $initialContent = "[" . date('Y-m-d H:i:s') . "] Archivo de log iniciado" . PHP_EOL;
            $created = file_put_contents($file, $initialContent, FILE_APPEND | LOCK_EX);
            if ($created === false) {
                error_log("No se pudo crear el archivo de log: " . $file);
                return;
            }
            chmod($file, 0644); // Asegurarse de que el archivo sea legible y escribible
        }
        $logEntry = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
        file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function logWithBacktrace($message, $isError = true)
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1] ?? $backtrace[0];
        $logMessage = sprintf("[%s] %s - Called from %s::%s (Line %d)%s%s", date('Y-m-d H:i:s'), $message, $caller['class'] ?? '', $caller['function'], $caller['line'], PHP_EOL, "Stack trace:" . PHP_EOL . json_encode($backtrace, JSON_PRETTY_PRINT));
        file_put_contents($this->errorLogFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public function recibir_log($msg, $control = false)
    {
        if ($control) {
            $this->logWithBacktrace($msg, true);
        } else {
            $this->log($msg);
        }
    }

    /**
     * Procesar un día específico
     * @param string $fecha Fecha en formato Y-m-d
     * @return array Resultado del procesamiento
     */
    public function procesarDia($fecha)
    {
        try {
            // Obtener nombre de la tabla mensual
            $info_fecha = $this->extraerInfoFecha($fecha);
            $nombre_tabla = $this->generarNombreTabla($info_fecha['mes'], $info_fecha['anio']);

            // Verificar si existe la tabla
            $tabla_existe = $this->tablaExiste($nombre_tabla);

            if (!$tabla_existe) {
                $this->crearTablaMensual($nombre_tabla, $info_fecha['mes_texto'], $info_fecha['anio']);
            }

            // Mover datos del día a la tabla mensual
            $registros_movidos = $this->moverDatosDia($fecha, $nombre_tabla);

            return [
                'success' => true,
                'tabla' => $nombre_tabla,
                'registros_movidos' => $registros_movidos,
                'tabla_creada' => !$tabla_existe
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Extraer información de la fecha
     */
    public function extraerInfoFecha($fecha)
    {
        $date = new DateTime($fecha);
        $mes_numero = (int)$date->format('m');
        $meses_texto = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre'
        ];

        return [
            'anio' => $date->format('Y'),
            'mes' => $mes_numero,
            'mes_texto' => $meses_texto[$mes_numero]
        ];
    }

    /**
     * Generar nombre de tabla mensual
     */
    public function generarNombreTabla($mes, $anio)
    {
        $meses_abrev = [
            1 => 'ene',
            2 => 'feb',
            3 => 'mar',
            4 => 'abr',
            5 => 'may',
            6 => 'jun',
            7 => 'jul',
            8 => 'ago',
            9 => 'sep',
            10 => 'oct',
            11 => 'nov',
            12 => 'dic'
        ];
        return "gps_{$meses_abrev[$mes]}_{$anio}_e_d";
    }

    /**
     * Verificar si una tabla existe
     */
    public function tablaExiste($nombre_tabla)
    {
        $stmt = "SELECT COUNT(*) as existe 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
                AND table_name = :v_table_name";
        $param = [
            ':v_table_name' => $nombre_tabla
        ];

        $result = $this->ejecutarConsulta($stmt, "", $param);
        return $result['existe'] > 0;
    }

    /**
     * Crear tabla mensual
     */
    public function crearTablaMensual($nombre_tabla, $mes_texto, $anio)
    {
        $this->log("Creando tabla: $nombre_tabla");

        $sql = "CREATE TABLE IF NOT EXISTS `$nombre_tabla` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `id_gps_estado` INT NOT NULL,
                `truck` VARCHAR(50) NOT NULL,
                `estado` VARCHAR(50) NOT NULL,
                `lat` DECIMAL(10,8),
                `lng` DECIMAL(11,8),
                `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_device_id (id_gps_estado),
                INDEX idx_timestamp (timestamp),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='GPS $mes_texto $anio - Archivo historico'";

        $param = [];
        if ($this->ejecutarConsulta($sql, "", $param)) {
            $this->log("✓ Tabla $nombre_tabla creada exitosamente");
        } else {
            $msg = "❌ Tabla $nombre_tabla No se pudo crear";
            $this->logWithBacktrace($msg, true);
        }
    }

    /**
     * Mover datos de un día a la tabla mensual
     * @param string $fecha Fecha en formato Y-m-d
     * @param string $tabla_destino Nombre de la tabla destino
     * @return int Cantidad de registros movidos
     */
    private function moverDatosDia($fecha, $tabla_destino) {
        $this->log("Moviendo datos del DÍA ESPECÍFICO $fecha a $tabla_destino");
        
        try {
            // PASO 1: Obtener IDs de los registros a mover (para validación)
            $stmt_ids = "SELECT `id_gps_estado` 
                FROM `$this->tabla_origen` 
                WHERE DATE(`timestamp`) = :v_fecha
                ORDER BY `id_gps_estado`";
            $param = [':v_fecha' => $fecha];
            
            $ids_originales = $this->ejecutarConsulta($stmt_ids, "", $param, "fetchAll");
            $total_registros = count($ids_originales);
            
            if ($total_registros == 0) {
                $this->log("  - No hay registros para el día $fecha");
                return 0;
            }
            
            $this->log("  Registros encontrados para $fecha: $total_registros");
            
            // Extraer IDs para validación posterior
            $ids_array = array_column($ids_originales, 'id_gps_estado');
            
            // PASO 2: Insertar registros
            $stmt_insert = "INSERT INTO `$tabla_destino` 
                (`id_gps_estado`, `truck`, `estado`, `lat`, `lng`, `timestamp`)
                SELECT `id_gps_estado`, `truck`, `estado`, `lat`, `lng`, `timestamp`
                FROM `$this->tabla_origen`
                WHERE DATE(`timestamp`) = :v_fecha";
            
            $param = [':v_fecha' => $fecha];
            $this->ejecutarConsulta($stmt_insert, "", $param);
            
            // PASO 3: Verificar que TODOS los IDs se insertaron correctamente
            $placeholders = implode(',', array_fill(0, count($ids_array), '?'));
            $stmt_verif_ids = "SELECT `id_gps_estado` 
                FROM `$tabla_destino` 
                WHERE `id_gps_estado` IN ($placeholders)";
            
            $insertados_ids = $this->ejecutarConsulta($stmt_verif_ids, "", $ids_array, "fetchAll");
            $insertados_count = count($insertados_ids);
            
            // VALIDACIÓN ESTRÍCTA
            if ($insertados_count != $total_registros) {
                $ids_faltantes = array_diff($ids_array, array_column($insertados_ids, 'id_gps_estado'));
                throw new Exception("FALLO DE INSERCIÓN: Esperados $total_registros, Insertados $insertados_count. IDs faltantes: " . implode(',', $ids_faltantes));
            }
            
            // PASO 4: Eliminar registros originales
            $stmt_delete = "DELETE 
                FROM `$this->tabla_origen`
                WHERE DATE(`timestamp`) = :v_fecha";
            $param = [':v_fecha' => $fecha];
            
            $eliminados = $this->ejecutarConsulta($stmt_delete, "", $param);
            
            // VALIDACIÓN FINAL
            if ($eliminados != $total_registros) {
                throw new Exception("FALLO DE ELIMINACIÓN: Esperados $total_registros, Eliminados $eliminados");
            }
            
            $this->log("  ✓ Movidos $total_registros registros del día $fecha a $tabla_destino");
            
            return $total_registros;
            
        } catch (Exception $e) {
            $this->logWithBacktrace("  ✗ ERROR procesando día $fecha: " . $e->getMessage(), true);
            throw $e;
        }
    }

    /**
     * Obtener todas las tablas mensuales existentes
     */
    public function obtenerTablasMensuales()
    {
        $stmt = "SELECT table_name, table_comment, table_rows
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name LIKE 'gps_%_e_d'
            ORDER BY table_name DESC";
        $param = [];
        $resultado = $this->ejecutarConsulta($stmt, "", $param, "fetchAll");

        $tablas = [];

        foreach ($resultado as $row) {
            $tablas[] = [
                'nombre' => $row['TABLE_NAME'],
                'comentario' => $row['TABLE_COMMENT'],
                'registros' => $row['TABLE_ROWS']
            ];
        }

        return $tablas;
    }

    /**
     * Obtener estadísticas de archivado
     */
    public function obtenerEstadisticas()
    {
        // Contar registros en tabla original
        $sql_origen = "SELECT COUNT(*) AS total 
            FROM `$this->tabla_origen`";
        $total_origen = $this->ejecutarConsulta($sql_origen, "", [], "fetchColumn");

        // Contar tablas mensuales
        $sql_tablas = "SELECT COUNT(*) AS total 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
                AND table_name LIKE 'gps_%_e_d'";
        $total_tablas = $this->ejecutarConsulta($sql_tablas, "", [], "fetchColumn");

        return [
            'total_registros_origen' => $total_origen,
            'total_tablas_archivo' => $total_tablas
        ];
    }

    public function buscar_extremos()
    {
        // Obtener rango de fechas en la tabla original
        $stmt_range = "SELECT 
            MIN(DATE(timestamp)) as fecha_minima,
            MAX(DATE(timestamp)) as fecha_maxima,
            COUNT(*) as total_registros
        FROM `$this->tabla_origen`";

        $range = $this->ejecutarConsulta($stmt_range, "", []);
        return $range;
    }

    /**
     * Migrar un lote de datos entre dos fechas específicas
     * @param string $nombre_tabla Nombre de la tabla destino
     * @param string $fecha_inicio Fecha inicial (Y-m-d)
     * @param string $fecha_fin Fecha final (Y-m-d)
     * @param string $mes_texto Nombre del mes en texto
     * @param string $anio Año
     * @return int Registros migrados
     */
    public function migrar_lote($nombre_tabla, $fecha_inicio, $fecha_fin, $mes_texto, $anio)
    {
        // Verificar que la tabla exista
        if (!$this->tablaExiste($nombre_tabla)) {
            $this->crearTablaMensual($nombre_tabla, $mes_texto, $anio);
        }

        try {
            // Contar registros en el rango de fechas
            $stmt_count = "SELECT COUNT(*) as total 
            FROM `$this->tabla_origen` 
            WHERE DATE(`timestamp`) BETWEEN :v_fecha_inicio AND :v_fecha_fin";
            $param = [
                ':v_fecha_inicio' => $fecha_inicio,
                ':v_fecha_fin' => $fecha_fin
            ];

            $registros_rango = $this->ejecutarConsulta($stmt_count, "", $param, "fetchColumn");

            if ($registros_rango == 0) {
                $this->log("  - No hay registros en el rango $fecha_inicio a $fecha_fin");
                return 0;
            }

            $this->log("  Registros a migrar en rango $fecha_inicio a $fecha_fin: $registros_rango");

            // Iniciar transacción manualmente si tu modelo lo soporta
            // $this->db->beginTransaction();

            try {
                // Insertar registros en tabla mensual
                $stmt_insert = "INSERT INTO `$nombre_tabla` 
                    (`id_gps_estado`, `truck`, `estado`, `lat`, `lng`, `timestamp`)
                    SELECT `id_gps_estado`, `truck`, `estado`, `lat`, `lng`, `timestamp`
                    FROM `$this->tabla_origen`
                    WHERE DATE(`timestamp`) BETWEEN :v_fecha_inicio AND :v_fecha_fin";

                $param = [
                    ':v_fecha_inicio' => $fecha_inicio,
                    ':v_fecha_fin' => $fecha_fin
                ];

                $this->ejecutarConsulta($stmt_insert, "", $param);

                // Verificar cuántos se insertaron
                $stmt_verif = "SELECT COUNT(*) as total 
                FROM `$nombre_tabla` 
                WHERE DATE(`timestamp`) BETWEEN :v_fecha_inicio AND :v_fecha_fin";
                $param = [
                    ':v_fecha_inicio' => $fecha_inicio,
                    ':v_fecha_fin' => $fecha_fin
                ];

                $insertados = $this->ejecutarConsulta($stmt_verif, "", $param, "fetchColumn");

                // Eliminar registros de tabla original
                $stmt_delete = "DELETE 
                FROM `$this->tabla_origen`
                WHERE DATE(`timestamp`) BETWEEN :v_fecha_inicio AND :v_fecha_fin";
                $param = [
                    ':v_fecha_inicio' => $fecha_inicio,
                    ':v_fecha_fin' => $fecha_fin
                ];

                $eliminados = $this->ejecutarConsulta($stmt_delete, "", $param);

                // Verificar consistencia
                if ($insertados != $eliminados) {
                    throw new Exception("Inconsistencia: Insertados $insertados, Eliminados $eliminados");
                }

                $this->log("  ✓ Migrados $insertados registros del rango $fecha_inicio a $fecha_fin");

                // $this->db->commit();
                return $insertados;
            } catch (Exception $e) {
                // $this->db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            $this->logWithBacktrace("  ✗ ERROR: " . $e->getMessage(), true);
            return 0;
        }
    }
}
