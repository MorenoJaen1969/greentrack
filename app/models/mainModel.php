<?php
namespace app\models;
require_once APP_R_PROY . 'app/models/mainModel.php';
require_once APP_R_PROY . 'app/models/otras_fun.php';

use app\models\otras_fun;
# use app\models\IdiomaTrait;

use \PDO;
use \Exception;
use \RuntimeException;

$ruta_arch = APP_R_PROY . 'config/server.php';
if (file_exists($ruta_arch)) {
    require_once $ruta_arch;
}

class mainModel
{
#    use IdiomaTrait;
    private $pdo;
    private $app_r_proy = APP_R_PROY;
    private $server = DB_SERVER;
    private $db = DB_NAME;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $sSQL;
    private $isConectared = false;
    private $parametros;
    private $conn;

    private $log_path;
    private $logFile;
    private $errorLogFile;
    private $logsInitialized = false;


    private static $instanceTracker = []; 
    private $o_f;

    public function __construct()
    {
        // Configurar manejo de errores
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'shutdownCheck']);

        $this->initializeLoggingSystem();

		$this->verificarPermisos();

		// rotación automatica de log (Elimina logs > XX dias)
		$this->rotarLogs(15);

        // === Establecer zona horaria al inicio ===
        //date_default_timezone_set('America/Chicago'); // Conroe, TX (CDT/UTC-5)
    
        $this->parametros = array();

        //Registro de instancia para diagnostico
        self::$instanceTracker[] = [
            'time' => microtime(true),
            'hash' => spl_object_hash($this),
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),
        ];
    }

    public function handleError($error, $errstr, $errfile, $errline)
    {
        error_log("ERROR PHP: $errstr EN $errfile LÍNEA $errline");
    }

    public function shutdownCheck()
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            error_log("SHUTDOWN ERROR: " . print_r($error, true));
        }

        // Verificar si la instancia está en el rastreador
        if (!file_exists($this->logFile) || !is_writable($this->logFile)) {
            error_log("ESTADO FINAL: Log principal no accesible");
            if (!empty($this->logFile)) {
                $this->repairLogFile($this->logFile);
            }
        }
    }

    private function repairLogFile($file)
    {
        try {
            if (file_put_contents($file, "") === false) {
                throw new RuntimeException("No se pudo crear el archivo de log: " . $file);
            }

            if (!chmod($file, 0775)) {
                throw new RuntimeException("No se pudo cambiar los permisos del archivo de log: " . $file);
            }

            if (!is_writable($file)) {
                throw new RuntimeException("El archivo de log no es escribible: " . $file);
            }
            return true;
        } catch (Exception $e) {
            error_log("Error al reparar el archivo de log: " . $e->getMessage());
            return false;
        }
    }

    private function initializeLoggingSystem() {
        if ($this->logsInitialized) {
            return;
        }

        // === Modo CLI: Permitir continuar aunque falle el log ===
        $es_cli = php_sapi_name() === 'cli';

        try {
            $nom_modelo = "mainModel";

            $intended_path = $this->app_r_proy . 'app/logs/modelo/';

            // Si realpath falla, pero el directorio existe, úsalo directamente
            if (!is_dir($intended_path)) {
                if (!mkdir($intended_path, 0755, true)) {
                    throw new RuntimeException("No se pudo crear directorio de logs");
                }
            }
            $this->log_path = $intended_path;            

            if (!$this->log_path || !is_dir($this->log_path)) {
                if ($es_cli) {
                    $this->log_path = sys_get_temp_dir() . '/greentrack_logs/';
                    if (!file_exists($this->log_path)) {
                        mkdir($this->log_path, 0755, true);
                    }
                } else {
                    if (!mkdir($this->log_path, 0755, true)) {
                        throw new RuntimeException("No se pudo crear directorio de logs");
                    }
                }
            }

            // Solo cambiar permisos si NO estamos en CLI o si el usuario es root
            if (php_sapi_name() !== 'cli') {
                @chown($this->log_path, 'www-data');
                @chgrp($this->log_path, 'www-data');
                @chmod($this->log_path, 0755);
            }

            $this->logFile = $this->log_path . $nom_modelo . '_' . date('Y-m-d') . ".log";
            $this->errorLogFile = $this->log_path . $nom_modelo . '_error_' . date('Y-m-d') . ".log";

            $this->initializeLogFile($this->logFile);
            $this->initializeLogFile($this->errorLogFile);

            $this->logsInitialized = true;

        } catch (Exception $e) {
            if (!$es_cli) {
                // En modo web: error crítico
                throw new RuntimeException("Fallo en inicialización de archivo de log principal", 0, $e);
            } else {
                // En modo CLI: advertencia, pero continúa
                error_log("[WARN] Logging desactivado: " . $e->getMessage());
                $this->logsInitialized = false;
            }
        }
    }

    private function initializeLogFile($file) {
        $es_cli = php_sapi_name() === 'cli';

        if (!file_exists($file)) {
            $initialContent = "[" . date('Y-m-d H:i:s') . "] Archivo de log iniciado" . PHP_EOL;
            $result = @file_put_contents($file, $initialContent, FILE_APPEND | LOCK_EX);

            if ($result === false) {
                if (!$es_cli) {
                    throw new Exception("No se pudo crear el archivo de log: " . $file);
                } else {
                    error_log("Advertencia: No se pudo crear log: " . $file);
                    return false;
                }
            }

            if (php_sapi_name() !== 'cli') {
                @chown($file, 'www-data');
                @chgrp($file, 'www-data');
                @chmod($file, 0644);
            }            
        }

        if (!is_writable($file)) {
            if (!$es_cli) {
                throw new Exception("El archivo de log no es escribible: " . $file);
            } else {
                error_log("Advertencia: Log no escribible: " . $file);
                return false;
            }
        }

        return true;
    }

	private function verificarPermisos()
	{
		// Verificar permisos del directorio
		if (!is_writable($this->log_path)) {
			error_log("No hay permiso de escritura en: " . $this->log_path);
			
			// Intentar corregir permisos al crear
            // Solo cambiar permisos si NO estamos en CLI
            if (php_sapi_name() !== 'cli') {
                if (is_dir($this->log_path)) {
                    @chown($this->log_path, 'www-data');
                    @chgrp($this->log_path, 'www-data');
                    @chmod($this->log_path, 0755);
                }
            } else {
                // En CLI, asume que los permisos ya están bien
                $this->log("Ejecución CLI: usando permisos existentes para {$this->log_path}");
            }                
		}
		
		// Verificar permisos de los archivos
		if (file_exists($this->logFile) && !is_writable($this->logFile)) {
			chmod($this->logFile, 0644);
			chown($this->logFile, 'www-data');
			chgrp($this->logFile, 'www-data');
		}
		
		if (file_exists($this->errorLogFile) && !is_writable($this->errorLogFile)) {
			chmod($this->errorLogFile, 0644);
			chown($this->errorLogFile, 'www-data');
			chgrp($this->errorLogFile, 'www-data');
		}
	}

	private function log($message, $isError = false): void
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
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $backtrace[1] ?? $backtrace[0];
        $logMessage = sprintf("[%s] %s - Called from %s::%s (Line %d)%s%s", date('Y-m-d H:i:s'), $message, $caller['class'] ?? '', $caller['function'], $caller['line'], PHP_EOL, "Stack trace:" . PHP_EOL . json_encode($backtrace, JSON_PRETTY_PRINT));
        $this->log($logMessage, $isError);
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

    public static function sanitize($query)
    {
        // Permitir saltos de línea y espacios
        $query = str_replace("\t", '', $query); // Eliminar retornos de carro y tabulaciones
        $query = str_replace("\n", ' ', $query); // Eliminar retornos de carro y tabulaciones
        $query = str_replace("\r", ' ', $query); // Eliminar retornos de carro y tabulaciones
        $query = str_replace("  ", ' ', $query); // Eliminar retornos de carro y tabulaciones

        // Eliminar caracteres peligrosos
        $query = preg_replace('/[^\x20-\x7E]/', '', $query); // Eliminar caracteres no imprimibles

        return trim($query);
    }

    /*----------  Función para conectar a la BD  ----------*/
    protected function conectar($bas_dat = "") { 
        if ($bas_dat == "") {
            $bas_dat = $this->db;				
        }
        $this->conn = null;

        $config = [ 
            'host' => 'localhost', 
            'dbname' => $bas_dat, 
            'user' => $this->user, 
            'pass' => $this->pass 
        ]; 
        try { 
            $dsn = "mysql:host={$config['host']}; dbname={$config['dbname']};charset=utf8"; 
            $opciones = [ 
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, 
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false 
            ]; 
            $this->conn = new PDO($dsn, $config['user'], $config['pass'], $opciones); 

            // Verificación adicional ejecutando una consulta simple 
            $stmt = $this->conn->query("SELECT 1"); 
            if ($stmt->fetchColumn() != 1) { 
                throw new Exception("*********** Verificación de conexión fallida ***********"); 
            }
            $this->log("Conexión exitosa a {$bas_dat}");
            return $this->conn; 
        } catch (Exception $e) { 
            
            $error_act = "Error de conexión a BD [{$bas_dat}]: ". $e->getMessage();
            $this->logWithBacktrace($error_act, true);
            throw new Exception("No se pudo conectar a la base de datos. Por favor, intente más tarde."); 
        } 
    } 
    
    protected function ejecutarConsulta($consulta, $tabla = "", $params = [], $fetchmode = PDO::FETCH_ASSOC, $base_datos = "")
    {
        $this->log("=== INICIO DE CONSULTA ===");
        $consulta = $this->sanitize($consulta);
        $this->log("Ejecutar Consulta: " . $consulta);

        // Validar parámetros
        if (!is_array($params)) {
            throw new \InvalidArgumentException("Los parámetros deben ser un array.");
        }

        try { 
            # Determina el tipo de SQL 
            $consulta = ltrim($consulta);
            preg_match('/^(select|insert|update|delete|create|show)/i', $consulta, $matches);
            $statement = isset($matches[0]) ? strtolower($matches[0]) : 'unknown';
            
            $rawStatement = explode(" ", preg_replace("/\s+|\t+|\n+/", " ", $consulta));
            $statement = strtolower($rawStatement[0]);

            // Determinar conexión según la tabla 
            if (!empty($base_datos)) {
                $conn = $this->conectar($base_datos); 
                $this->log("Consulta en: " . $base_datos); 
            } else if ($tabla !== "" && in_array($tabla, ["administrators", "clients", "dbs"])) {
                $conn = $this->conectar("principal"); 
                $this->log("Consulta en: principal");
            } else {
                $conn = $this->conectar(); 
            }

            $parametrosLog = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($parametrosLog === false) {
                $parametrosLog = 'json_encode failed - Non-serializable data';
            }
            $this->log("Parámetros: " . json_encode($params));            

            $stmt = $conn->prepare($consulta); 

            // Ejecutar con parámetros 
            $startTime = microtime(true); 
            $stmt->execute($params); 
            $duration = round((microtime(true) - $startTime) * 1000, 2); 

            $this->log("Consulta ejecutada en {$duration}ms"); 

            if ($statement === 'select' || $statement === 'show') {
                // Manejar diferentes tipos de fetch 
                switch ($fetchmode) 
                { 
                    case 'fetchAll': 
                        return $stmt->fetchAll(PDO::FETCH_ASSOC); 
                    case 'fetchColumn': 
                        return $stmt->fetchColumn(); 
                    case 'fetchBoth': 
                        return $stmt->fetch(PDO::FETCH_BOTH);
                    case 'fetchRow':
                        return $stmt->fetch(PDO::FETCH_NUM);
                    default: 
                        return $stmt->fetch($fetchmode);
                } 
            } elseif ($statement === 'insert' || $statement === 'update' || $statement === 'delete') {
                return $stmt->rowCount();
            } elseif ($statement === 'create') {
                return $stmt->fetch($fetchmode);
            } else {
                return NULL;
            }

        } catch (\PDOException $e) { 
            // if (isset($conn)) { 
            //     $conn->rollBack(); 
            // } 
        
            $errorDetails = [ 
                'query' => substr($consulta, 0, 200), 
                'params' => $params, 
                'error' => $e->getMessage(), 
                'trace' => $e->getTraceAsString() 
            ]; 
            
            $this->logWithBacktrace("🚨 Error en consulta: " . json_encode($errorDetails), true); 
            throw new Exception("Fallo en consulta: " . $e->getMessage()); 

        } catch (Exception $e) {
            $this->logWithBacktrace("⚠️ Excepción general: " . $e->getMessage(), true);
            throw $e;
        }
    }
    
    public function query($sql, $params = null, $opt = 0, $fetchmode = PDO::FETCH_ASSOC)
    {
        $cleanQuery = $this->sanitize($sql);
        $sql = $cleanQuery;
        try {
            $sql = trim(str_replace("\r", " ", $sql));

            $this->Init($sql, $params);

            $rawStatement = explode(" ", preg_replace("/\s+|\t+|\n+/", " ", $sql));

            # Determina el tipo de SQL 
            $statement = strtolower($rawStatement[0]);

            if ($statement === 'select' || $statement === 'show') {
                if ($opt == 0) {
                    return $this->sSQL->fetchAll($fetchmode);
                } else {
                    return $this->sSQL->fetch($fetchmode);
                }
            } elseif ($statement === 'insert' || $statement === 'update' || $statement === 'delete') {
                return count((array)$this->sSQL);
            } elseif ($statement === 'create') {
                return $this->sSQL->fetch($fetchmode);
            } else {
                return NULL;
            }
        } catch (Exception $e) {
            error_log("Algo salió mal con la consulta.\n<br>" .                "Error: \n<br>" . $e->getMessage() . "\n<br>" .
                "Error: \n<br>" . $e->getMessage() . "\n<br>" .
                "StackTrace: \n<br>"  . $e->getTraceAsString());
            return null;
        }
    }

    protected function ejecutarComando($comando)
    {
        $sql = $this->conectar();
        $sql->exec($comando);
        return $sql;
    }

    /*----------  Funcion limpiar cadenas  ----------*/
    public function limpiarCadena($cadena)
    {
        $palabras = ["<script>", "</script>", "<script src", "<script type=", "SELECT * FROM", "SELECT ", " SELECT ", "DELETE FROM", "INSERT INTO", "DROP TABLE", "DROP DATABASE", "TRUNCATE TABLE", "SHOW TABLES", "SHOW DATABASES", "<?php", "?>", "--", "^", "<", ">", "==", "=", ";", "::"];
        $cadena = trim($cadena);
        $cadena = stripslashes($cadena);

        foreach ($palabras as $palabra) {
            $cadena = str_ireplace($palabra, "", $cadena);
        }

        $cadena = trim($cadena);
        $cadena = stripslashes($cadena);

        return $cadena;
    }

    /*---------- Funcion verificar datos (expresion regular) ----------*/
    protected function verificarDatos($filtro, $cadena)
    {
        if (preg_match("/^" . $filtro . "$/", $cadena)) {
            return false;
        } else {
            return true;
        }
    }

    /*----------  Funcion para ejecutar una consulta INSERT preparada  ----------*/
    protected function guardarDatos($tabla, $datos)
    {
        $this->log("=== INICIO DE INGRESO DE DATOS ===");

        $query = "INSERT INTO $tabla (";

        $C = 0;
        foreach ($datos as $clave) {
            if ($C >= 1) {
                $query .= ",";
            }
            $query .= $clave["campo_nombre"];
            $C++;
        }

        $query .= ") VALUES (";

        $C = 0;
        foreach ($datos as $clave) {
            if ($C >= 1) {
                $query .= ",";
            }
            $query .= $clave["campo_marcador"];
            $C++;
        }

        $query .= ")";

        $this->log("Proceso de armado de consulta: " . $query);

        if ($tabla == "administrators" || $tabla == "clients" || $tabla == "dbs" || $tabla == "recetas" || $tabla == "recetas_partidas") {
            $sql = $this->conectar("principal");
        } else {
            $sql = $this->conectar();
        }

        $consulta = $sql->prepare($query);

        foreach ($datos as $clave) {
            $consulta->bindParam($clave["campo_marcador"], $clave["campo_valor"]);
        }

        $consulta->execute();
        $id_resulta = $sql->lastInsertId();     /* ULTIMO ID GENERADO */

        $consulta->closeCursor();
        $consulta = null;

        $this->log("Registro incorporado a la tabla " . $tabla . " Ultimo ID: " . $id_resulta);

        return $id_resulta;
    }

    /*----------  Funcion para ejecutar una consulta UPDATE preparada  ----------*/
    protected function actualizarDatos($tabla, $datos, $condicion)
    {
        $this->log("=== INICIO ACTUALIZACIÓN ===");
        try {
            $conn = $this->conectar();
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->beginTransaction();

            // 1. Verificar existencia del registro ANTES de actualizar 
            $registro = $this->verificarRegistroExistente($conn, $tabla, $condicion);
            if (!$registro) {
                throw new Exception("El registro no existe");
            }

            // 2. Construir consulta con verificación EXPLÍCITA de cambios 
            $query = "UPDATE $tabla SET "; 
            $sets = [];
            $datos_final = [];
            $changes = false;

            // Obtener metadatos de los campos
            $estructuraCampos = $this->obtenerEstructuraCampos($conn, $tabla);

            $this->log("Origen de datos: " . print_r($datos, true));
            foreach ($datos as $clave) {
                $campo = $clave['campo_nombre'];
                $marcador = $clave['campo_marcador'];
                $tipoCampo = strtolower($estructuraCampos[$campo]['Type'] ?? 'varchar');
                if ($this->compararValores($registro[$campo], $clave['campo_valor'], $tipoCampo)){
                    $changes = true;
                    $sets[] = "$campo = $marcador";
                    $datos_final[] = $clave;
                    $this->log("Campo '$campo' tiene cambios (Tipo: $tipoCampo)");
                } else {
                    $this->log("Campo '$campo' sin cambios (Tipo: $tipoCampo)");
                }
            }

            if (!$changes) {
                $this->log("ADVERTENCIA: No hay cambios reales en los datos");
                $conn->rollBack();
                $mensaje = $this->generarHtmlError(2, []);
                return $mensaje;
            }

            $query .= implode(", ", $sets);
            $query .= " WHERE ";

            // === CONSTRUCCIÓN DE MÚLTIPLES CONDICIONES ===
error_log("Arreglo de condicion " . json_encode($condicion));            
//            $where_parts = [];
//            foreach ($condicion as $cond) {
//                $where_parts[] = $cond['campo_nombre'] . " = " . $cond['condicion_marcador'];
//            }
//            $query .= implode(" AND ", $where_parts);

            $query .= $condicion['campo_nombre'] . " = " . $condicion['campo_marcador'];

            // 3. Ejecutar con DEBUG profundo
            $stmt = $conn->prepare($query);
            
            // Vincular parámetros 
            foreach ($datos_final as $clave) {
                $stmt->bindValue($clave['campo_nombre'], $clave['campo_valor']);
                $this->log("Vinculado: " . $clave['campo_nombre'] . " = " . $clave['campo_valor']);
            }

            // Vincular cada condición
//            foreach ($condicion as $cond) {
//                $stmt->bindValue($cond['campo_nombre'], $cond['campo_valor']);
//                $this->log("Vinculado WHERE: " . $cond['campo_nombre'] . " = " . $cond['campo_valor']);
//            }

            $stmt->bindValue($condicion['campo_nombre'], $condicion['campo_valor']);
            $this->log("Vinculado WHERE: " . $condicion['campo_nombre'] . " = " . $condicion['campo_valor']);


            $this->log("Consulta final: $query");
            
            // 4. Ejecutar y verificar 
            $stmt->execute();
            $filas = $stmt->rowCount();

            if ($filas === 0) {
                // Debug avanzado 
                $this->log("=== DEBUG AVANZADO ==="); 
                $this->log("Registro actual: " . print_r($registro, true));
                $this->log("Valores nuevos: " . print_r(array_column($datos, 'campo_valor', 'campo_nombre'), true));

                // Verificar constraints 
                $this->verificarConstraints($conn, $tabla); 
                throw new Exception("Actualización ejecutada pero 0 filas afectadas");
            }

            $conn->commit();
            $this->log("Actualización exitosa. Filas afectadas: $filas");
            return $filas;
            
        } catch (Exception $e) {
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $this->logWithBacktrace("ERROR: " . $e->getMessage(), true);
//$mensaje = $this->generarHtmlError(1, $e, $query, $datos, $condicion);
            return 0;
        }
    }

    private function generarHtmlError( $caso, $e, $query = "", $datos = [], $condicion = [])
    {
        $palabras = $this->initializeIdioma();
$this->log("Palabras: ", json_encode($palabras));
        // Mostrar información detallada del error (en desarrollo) 
        if ($caso == 1){
            $mensaje = "
                <div style='color: red; border: 1px solid #f00; padding: 10px; margin: 10px;'> 
                    <h3>" . $palabras['errores']['error_en_la_actualizacion'] . ":</h3> 
                    <p><strong>" . $palabras['errores']['mensaje'] . ":</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                    <p><strong>" . $palabras['errores']['consulta'] . ":</strong> " . htmlspecialchars($query) . "</p>
                    <p><strong>Datos:</strong><pre> " . htmlspecialchars(json_encode($datos)) . "</pre></p>
                    <p><strong>Condición:</strong><pre> " . htmlspecialchars(print_r($condicion, true)) . "</pre></p>
                    <p><strong>Código de error:</strong> " . htmlspecialchars($e->getCode()) . "</p> 
                    <p><strong>Archivo:</strong> " . $e->getFile() . " (Línea " . $e->getLine() . ")</p> 
                </div> 
            ";
        } elseif ($caso == 2) {
            $mensaje = "
                <div style='color: red; border: 1px solid #f00; padding: 10px; margin: 10px;'> 
                    <h3>" . $palabras['errores']['error_en_la_actualizacion'] . ":</h3> 
                    <p><strong>" . $palabras['errores']['mensaje'] . ":</strong> " . $palabras['errores']['no_se_detectaron_cambios'] . "</p>
                    <p><strong>" . $palabras['errores']['consulta'] . ":</strong> " . $palabras['errores']['al_evaluar_los_campos'] . "</p>
                </div> 
            ";
        } elseif ($caso == 3) {
            $mensaje = "
                <div style='color: red; border: 1px solid #f00; padding: 10px; margin: 10px;'> 
                    <h3>" . $palabras['errores']['error_en_conexion'] . ":</h3> 
                    <p><strong>" . $palabras['errores']['mensaje'] . ":</strong> " . $palabras['errores']['no_se_pudo_conectar'] . "</p>
                    <p><strong>" . $palabras['errores']['consulta'] . ":</strong> " . $palabras['errores']['error_al_validar_la_bd'] . "</p>
                </div> 
            ";
        }
        return $mensaje;
    }

    private function obtenerEstructuraCampos($conn, $tabla){
        $stmt = $conn->query("DESCRIBE $tabla");
        $campos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $estructura = [];
        foreach ($campos as $campo) {
            $estructura[$campo['Field']] = $campo;
        }
        return $estructura;
    }
    
    private function compararValores($valorActual, $valorNuevo, $tipoCampo)
    {
        //Manejo especial para campos de texto largo
        if (in_array($tipoCampo, ['mediumtext', 'longtext', 'text', 'blob'])) {
            return strcmp($valorActual ?? '', $valorNuevo ?? '') !== 0;
        }

        //Comparación normal para otros tipos
        return $valorActual != $valorNuevo;
    }

    private function verificarRegistroExistente($conn, $tabla, $condicion)
    {
        error_log("Consulta de la condicion: " . json_encode($condicion));
        $cond = $condicion['campo_nombre'] . " = " . $condicion['campo_marcador'];
        $query = "SELECT * FROM $tabla WHERE " . $cond . " LIMIT 1";

        error_log("Consulta armada: " . $query);

        $params = [
            $condicion['campo_marcador'] => $condicion['campo_valor']
        ];
		$result = $this->ejecutarConsulta($query, '', $params);

        return $result;
    }

    private function verificarConstraints($conn, $tabla)
    {
        // Verificar triggers 
        $stmt = $conn->query("SHOW TRIGGERS LIKE '$tabla'");
        $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->log("Triggers existentes: " . print_r($triggers, true));

        // Verificar foreign keys 
        $stmt = $conn->query(" SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tabla' AND REFERENCED_TABLE_NAME IS NOT NULL ");
        $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->log("Foreign Keys: " . print_r($fks, true));
    }

    private function determinarTipoPdo($valor)
    {
        if (is_int($valor)) return PDO::PARAM_INT;
        if (is_bool($valor)) return PDO::PARAM_BOOL;
        if (is_null($valor)) return PDO::PARAM_NULL;
        return PDO::PARAM_STR;
    }

    protected function actualizarDatos_principal($tabla, $datos, $condicion)
    {
        $query = "UPDATE $tabla SET ";

        $C = 0;
        foreach ($datos as $clave) {
            if ($C >= 1) {
                $query .= ",";
            }
            $query .= $clave["campo_nombre"] . "=" . $clave["campo_marcador"];
            $C++;
        }

        $query .= " WHERE " . $condicion["condicion_campo"] . "=" . $condicion["condicion_marcador"];

        $this->sSQL = $this->conectar("principal")->prepare($query);
        foreach ($datos as $clave) {
            $this->sSQL->bindParam($clave["campo_marcador"], $clave["campo_valor"]);
        }
        $this->sSQL->bindParam($condicion["condicion_marcador"], $condicion["condicion_valor"]);
        $this->sSQL->execute();
        return $this->sSQL;
    }


    /*---------- Funcion eliminar registro ----------*/
    protected function eliminarRegistro($tabla, $campo, $id)
    {
        $sql_eliminar = "DELETE FROM $tabla WHERE $campo=:id";
        $sql = $this->conectar()->prepare($sql_eliminar);
        $sql->bindParam(":id", $id);
        $sql->execute();
        $total_delete = count((array)$sql);
        return $total_delete;
    }

    /*---------- Paginador de tablas ----------*/
    protected function paginadorTablas($pagina, $numeroPaginas, $url, $botones, $id_codigos)
    {
        $palabras = $this->initializeIdioma();

        if (isset($id_codigos['id_obra'])) {
            $n0 = $id_codigos['id_obra'];
            $n1 = $id_codigos['id_partida'];
        } else {
            $n0 = $id_codigos['n0'];
            $n1 = $id_codigos['n1'];
        }

        $tabla = '<nav class="pagination is-centered is-rounded" role="navigation" aria-label="pagination">';

        if ($n0 > 0 && $n1 > 0) {
            $origen = 2;    // Controles para dom padres
        } elseif ($n0 == 0 && $n1 == 0) {
            $origen = 0;
        } elseif ($n0 > 0 && $n1 == 0) {
            $origen = 1;
        }

        #if($pagina <= 1){

        if ($numeroPaginas <= 1) {
            $tabla .= '
                <a class="pagination-previous is-disabled" disabled >' . $palabras["paginador"]["anterior"] . '</a>
                <ul class="pagination-list">
                ';
        } else {
            // Paginas Anteriores
            if ($origen == 0) {
                $tabla .= '<a class="pagination-previous" href="' . $url . ($pagina - 1) . '/">' . $palabras["paginador"]["anterior"] . '</a>';
            } elseif ($origen == 1) {
                $tabla .= '<a class="pagination-previous" href="' . $url . ($pagina - 1) . '/' . $n0 . '/">' . $palabras["paginador"]["anterior"] . '</a>';
            } elseif ($origen == 2) {
                $tabla .= '<a class="pagination-previous" href="' . $url . ($pagina - 1) . '/">' . $palabras["paginador"]["anterior"] . '</a>';
            }
            $tabla .= '<ul class="pagination-list">';

            // Paginas Siguientes
            if ($origen == 0) {
                $tabla .= '<li><a class="pagination-link" href="' . $url . '1/">1</a></li>';
            } elseif ($origen == 1) {
                $tabla .= '<li><a class="pagination-link" href="' . $url . '1/' . $n0 . '/">1</a></li>';
            } elseif ($origen == 2) {
                $tabla .= '<li><a class="pagination-link" href="' . $url . '1/">1</a></li>';
            }
            $tabla .= '<li><span class="pagination-ellipsis">&hellip;</span></li>
                ';
        }

        $ci = 0;

        for ($i = $pagina; $i <= $numeroPaginas; $i++) {

            if ($ci >= $botones) {
                break;
            }

            if ($pagina == $i) {
                if ($origen == 0) {
                    $tabla .= '<li><a class="pagination-link is-current" href="' . $url . $i . '/">' . $i . '</a></li>';
                } elseif ($origen == 1) {
                    $tabla .= '<li><a class="pagination-link is-current" href="' . $url . $i . '/' . $n0 . '/">' . $i . '</a></li>';
                } elseif ($origen == 2) {
                    $tabla .= '<li><a class="pagination-link is-current" href="' . $url . $i . '/">' . $i . '</a></li>';
                }
            } else {
                if ($origen == 0) {
                    $tabla .= '<li><a class="pagination-link" href="' . $url . $i . '/">' . $i . '</a></li>';
                } elseif ($origen == 1) {
                    $tabla .= '<li><a class="pagination-link" href="' . $url . $i . '/' . $n0 . '/">' . $i . '</a></li>';
                } elseif ($origen == 2) {
                    $tabla .= '<li><a class="pagination-link" href="' . $url . $i . '/">' . $i . '</a></li>';
                }
            }
            $ci++;
        }

        if ($pagina == $numeroPaginas) {
            $tabla .= '
                </ul>
                <a class="pagination-next is-disabled" disabled >' . $palabras["paginador"]["siguiente"] . '</a>
                ';
        } else {
            $tabla .= '<li><span class="pagination-ellipsis">&hellip;</span></li>';
            if ($origen == 0) {
                $tabla .= '<li><a class="pagination-link" href="' . $url . $numeroPaginas . '/">' . $numeroPaginas . '</a></li>';
            } elseif ($origen == 1) {
                $tabla .= '<li><a class="pagination-link" href="' . $url . $numeroPaginas . '/' . $n0 . '/">' . $numeroPaginas . '</a></li>';
            } elseif ($origen == 2) {
                $tabla .= '<li><a class="pagination-link" href="' . $url . $numeroPaginas . '/">' . $numeroPaginas . '</a></li>';
            }
            $tabla .= '</ul>';
            if ($origen == 0) {
                $tabla .= '<a class="pagination-next" href="' . $url . ($pagina + 1) . '/">' . $palabras["paginador"]["siguiente"] . '</a>';
            } elseif ($origen == 1) {
                $tabla .= '<a class="pagination-next" href="' . $url . ($pagina + 1) . '/' . $n0 . '/">' . $palabras["paginador"]["siguiente"] . '</a>';
            } elseif ($origen == 2) {
                $tabla .= '<a class="pagination-next" href="' . $url . ($pagina + 1) . '/">' . $palabras["paginador"]["siguiente"] . '</a>';
            }
        }
        $tabla .= '</nav>';
        return $tabla;
    }

    private function Init($sql, $parametros = "")
    {
        try {
            $this->sSQL = $this->conectar()->prepare($sql);
            if (isset($this->sSQL)) {
                # Agregar parámetros
                $this->bindMas($parametros);

                # Debug para verificar la consulta y los parámetros

                if (!empty($this->parametros)) {
                    foreach ($this->parametros as $param => $value) {
                        if (is_int($value[1])) {
                            $type = PDO::PARAM_INT;
                        } elseif (is_bool($value[1])) {
                            $type = PDO::PARAM_BOOL;
                        } elseif (is_null($value[1])) {
                            $type = PDO::PARAM_NULL;
                        } else {
                            $type = PDO::PARAM_STR;
                        }
                        # Vincular los valores
                        $this->sSQL->bindValue($value[0], $value[1], $type);
                    }
                }

                $this->sSQL->execute();
            } else {
                error_log("Hubo error en la carga de POD");
            }
        } catch (Exception $e) {
            error_log("Error de Init.\n<br>" .
                "Error: \n<br>" . $e->getMessage() . "\n<br>" .
                "StackTrace: \n<br>"  . $e->getTraceAsString());
        }

        $this->parametros = array();  # Resetear parámetros después de cada consulta
    }

    public function bind($parametro, $valor)
    {
        $this->parametros[count((array)$this->parametros)] = [":" . $parametro, $valor];
    }

    public function bindMas($parray)
    {
        if (empty($this->parametros) && is_array($parray)) {
            $columns = array_keys($parray);
            foreach ($columns as $i => &$column) {
                $this->bind($column, $parray[$column]);
            }
        }
    }

    public function situacion($datos, $origen)
    {
        $cod_status = $datos[0];
        $idioma_act = $datos[1];
        $fechaInicio = $datos[2];
        $fechaFin = $datos[3];
        $fec_termino = $datos[4];
        $fechaHoy = $datos[5];
        $diferencia = $datos[6];
        $XBStatusTerminada = $datos[7];
        $XBStatusCancelada = $datos[8];

        $pos_class = 0;
        $situacion = "";
        if ($cod_status == $XBStatusTerminada || $cod_status == $XBStatusCancelada) {
            if ($cod_status == $XBStatusTerminada) {
                if ($idioma_act == "es") {
                    $texto2 = "Proceso terminado ";
                } else {
                    $texto2 = "Process completed ";
                }
                if ($fec_termino > $fechaFin) {
                    $pos_class = 5;
                    $diferencia2 = $fec_termino->diff($fechaFin);
                    $diferencia2 = $diferencia2->days;
                    if ($idioma_act == "es") {
                        $texto2 = $texto2 . "con un retraso de " . $diferencia2 . " días";
                    } else {
                        $texto2 = $texto2 . "with a delay of " . $diferencia2 . " days";
                    }
                } else {
                    if ($fec_termino < $fechaFin) {
                        $pos_class = 7;
                        $diferencia2 = $fechaFin->diff($fec_termino);
                        $diferencia2 = $diferencia2->days;
                        if ($idioma_act == "es") {
                            $texto2 = $texto2 . "con un adelanto de " . $diferencia2 . " días";
                        } else {
                            $texto2 = $texto2 . "with an advance of " . $diferencia2 . " days";
                        }
                    } else {
                        $pos_class = 6;
                        if ($idioma_act == "es") {
                            $texto2 = $texto2 . "según tiempo programado";
                        } else {
                            $texto2 = $texto2 . "according to scheduled time";
                        }
                    }
                }
            } else {
                $pos_class = 4;
                if ($idioma_act == "es") {
                    $texto2 = "Proceso cancelado";
                } else {
                    $texto2 = "Canceled Process";
                }
            }
            if ($idioma_act == "es") {
                if ($origen == "obras") {
                    $origen2 = " la Obra. - " . $texto2;
                } elseif ($origen == "partidas") {
                    $origen2 = " la Partida. - " . $texto2;
                } elseif ($origen == "detalles") {
                    $origen2 = " el Detalle de Partida. - " . $texto2;
                }
            } else {
                if ($origen == "obras") {
                    $origen2 = " Work. - " . $texto2;
                } elseif ($origen == "partidas") {
                    $origen2 = " Work Item. - " . $texto2;
                } elseif ($origen == "detalles") {
                    $origen2 = " Detail Work Item. - " . $texto2;
                }
            }

            if ($idioma_act == "es") {
                if ($diferencia == 0) {
                    $situacion = "Se estipularon horas del día para culminar" . $origen2;
                } elseif ($diferencia == 1) {
                    $situacion = "Se estipulo " . $diferencia . " día para culminar" . $origen2;
                } else {
                    $situacion = "Se estipularon " . $diferencia . " días para culminar" . $origen2;
                }
            } else {
                $situacion = $diferencia . " days were stipulated to completemthe" . $origen2;
            }
        } else {
            if ($idioma_act == "es") {
                if ($origen == "obras") {
                    $origen2 = "La Obra";
                } elseif ($origen == "partidas") {
                    $origen2 = "La Partida";
                } elseif ($origen == "detalles") {
                    $origen2 = "El Detalle de Partida";
                }
            } else {
                if ($origen == "obras") {
                    $origen2 = "The Work";
                } elseif ($origen == "partidas") {
                    $origen2 = "Work Item";
                } elseif ($origen == "detalles") {
                    $origen2 = "Detail Work Item";
                }
            }

            if ($fechaFin < $fechaHoy) {
                $pos_class = 3;
                $diferencia2 = $fechaHoy->diff($fechaFin);
                $diferencia2 = $diferencia2->days;

                if ($idioma_act == "es") {
                    $texto2 = $origen2 . " se encuentra Retrasada en " . $diferencia2 . " días.";
                } else {
                    $texto2 = $origen2 . " Item is " . $diferencia2 . " days late.";
                }
            } else {
                if ($fechaFin == $fechaHoy) {
                    $pos_class = 2;
                    if ($idioma_act == "es") {
                        $texto2 = "El tiempo para el termino de " . $origen2 . " vence el día de hoy.";
                    } else {
                        $texto2 = "The time for the end of .$origen2. Item expires today.";
                    }
                } else {
                    if ($fechaHoy < $fechaInicio) {
                        $pos_class = 1;
                        $diferencia2 = $fechaHoy->diff($fechaInicio);
                        $diferencia2 = $diferencia2->days;

                        if ($diferencia2 == 0) {
                            if ($idioma_act == "es") {
                                $texto2 = "La ejecución de " . $origen2 . " comienza el día hoy.";
                            } else {
                                $texto2 = "The execution of de " . $origen2 . " begins today";
                            }
                        } elseif ($diferencia2 == 1) {
                            if ($idioma_act == "es") {
                                $texto2 = "La ejecución de " . $origen2 . " comienza el día de mañana.";
                            } else {
                                $texto2 = "The execution of de " . $origen2 . " begins tomorrow";
                            }
                        } else {
                            if ($idioma_act == "es") {
                                $texto2 = "La ejecución de " . $origen2 . " comienza en " . $diferencia2 . " días.";
                            } else {
                                $texto2 = "The execution of " . $origen2 . " begins in " . $diferencia2 . " days.";
                            }
                        }
                    } else {
                        $pos_class = 0;
                        $diferencia2 = $fechaHoy->diff($fechaInicio);
                        $diferencia2 = $diferencia2->days;
                        if ($idioma_act == "es") {
                            $texto2 = "Han trancurrido " . $diferencia2 . " días desde el inicio de " . $origen2 . ".";
                        } else {
                            $texto2 = $diferencia2 . " days have passed since the start of " . $origen2 . ".";
                        }
                    }
                }
            }
            if ($idioma_act == "es") {
                if ($origen == "obras") {
                    $origen2 = " la Obra. - " . $texto2;
                } elseif ($origen == "partidas") {
                    $origen2 = " la Partida. - " . $texto2;
                } elseif ($origen == "detalles") {
                    $origen2 = " el Detalle de Partida. - " . $texto2;
                }

                if ($diferencia == 0) {
                    $situacion = "Se estipularon horas del día para culminar" . $origen2;
                } elseif ($diferencia == 1) {
                    $situacion = "Se estipulo " . $diferencia . " día para culminar" . $origen2;
                } else {
                    $situacion = "Se estipularon " . $diferencia . " días para culminar" . $origen2;
                }
            } else {
                $situacion = $diferencia . " days were stipulated to completemthe Work Item. - " . $texto2;
            }
        }
        $resp_sit = [
            'situacion' => $situacion,
            'pos_class' => $pos_class
        ];
        return $resp_sit;
    }

    public function ceros($val_ini)
    {
        $val_fin = str_pad($val_ini,  4, "0", STR_PAD_LEFT);
        return $val_fin;
    }

    public function getToken($length)
    {
        $token = "";
        $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $codeAlphabet .= "abcdefghijklmnopqrstuvwxyz";
        $codeAlphabet .= "0123456789";
        $max = strlen($codeAlphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $token .= $codeAlphabet[$this->cryptoRandSecure(0, $max)];
        }
        return $token;
    }

    public function cryptoRandSecure($min, $max)
    {
        $range = $max - $min;
        if ($range < 1) {
            return $min; // not so random...
        }
        $log = ceil(log($range, 2));
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);
        return $min + $rnd;
    }

    public function arreglaMonto($monto)
    {
        $largo = strlen($monto) - 2;
        $decimales = substr($monto, $largo, 2);
        $monto = substr($monto, 0, -3);
        $monto = preg_replace('/[^0-9]/', '', $monto);
        $total = $monto . "." . $decimales;
        return $total;
    }

    function formatDecimal($valor)
    {
        // Eliminar caracteres no numéricos excepto el punto decimal y la coma
        $valor = preg_replace('/[^\d,\.]/', '', $valor);

        // Reemplazar la coma por un punto en caso de formato europeo
        $valor = str_replace(',', '.', $valor);

        // Convertir a flotante
        $numero = floatval($valor);

        // Formatear con dos decimales
        return number_format($numero, 2, '.', '');
    }

    public function publica($tabla, $tabla_datos_up)
    {

        return $this->guardarDatos($tabla, $tabla_datos_up);
    }

    public function reemplazarPlaceholders($textoBase, $valores)
    {
        foreach ($valores as $clave => $valor) {
            $textoBase = str_replace("{" . $clave . "}", $valor, $textoBase);
        }
        return $textoBase;
    }

    protected function crear_BaseDeDatos($nombreBD)
    {
        try {
            // Conectar sin especificar base de datos para poder crearla
            $conn = $this->conectar();
            if (!$conn) {
                throw new Exception("Error de conexión al servidor de bases de datos");
            }

            // Crear la base de datos si no existe
            $sql = "CREATE DATABASE IF NOT EXISTS `$nombreBD` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $conn->exec($sql);

            return ["success" => true, "message" => "Base de datos '$nombreBD' creada exitosamente."];
        } catch (Exception $e) {
            error_log("Error al crear la base de datos: " . $e->getMessage());
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    protected function crearTablasDesdeEstructura($nombreBD)
    {
        try {
            // Conectar a la base de datos recién creada
            $conn = $this->conectar($nombreBD);
            if (!$conn) {
                throw new Exception("Error de conexión a la base de datos '$nombreBD'");
            }

            // Obtener la estructura de las tablas desde las tablas del sistema
            $sql = "SELECT id_tabla, nombre FROM principal.tablas_sistema";
            $tablas = $this->ejecutarConsulta($sql, "", [], 'fetchAll');
            if (!$tablas) {
                throw new Exception("No se encontraron definiciones de tablas en 'tablas_sistemas'");
            }

            foreach ($tablas as $tabla) {
                $id_tabla = $tabla["id_tabla"];
                $nombreTabla = $tabla["nombre"];

                // Obtener los campos de la tabla
                $sql1 = "SELECT campo, tipo, pe_charset, pe_collate, casonull, autoincrement, valor 
                        FROM principal.tablas_campos 
                        WHERE id_tabla = :id_tabla";
                $param01 = [
                    ":id_tabla" => $id_tabla
                ];

                $campos = $this->ejecutarConsulta($sql1, "", $param01, 'fetchAll');
                if (!$campos) {
                    throw new Exception("No se encontraron campos para la tabla '$nombreTabla'");
                }

                // Construir la consulta CREATE TABLE
                $sql = "CREATE TABLE IF NOT EXISTS `$nombreTabla` (";
                $columnas = [];
                $campo_ind = "";
                foreach ($campos as $campo) {
                    $columna = "`{$campo['campo']}` {$campo['tipo']}";
                    if (!empty($campo['pe_charset'])) {
                        $columna .= " CHARACTER SET {$campo['pe_charset']} COLLATE {$campo['pe_collate']}";
                    }
                    if ($campo['casonull'] == "NOT NULL") {
                        $columna .= " NOT NULL";
                    }
                    if ($campo['autoincrement'] == 1) {
                        $columna .= " AUTO_INCREMENT";
                        $campo_ind = ", PRIMARY KEY  (`{$campo['campo']}`)";
                    }
                    if (!empty($campo['valor']) || $campo['valor'] === "0") {
                        $columna .= " DEFAULT {$campo['valor']}";
                    }
                    $columnas[] = $columna;
                }

                $sql .= implode(", ", $columnas);

                if (!empty($campo_ind)) {
                    $sql .= $campo_ind;
                }

                $sql .= ") ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_spanish_ci;";

                // Ejecutar la consulta
                $conn->exec($sql);
            }
            return ["success" => true, "message" => "Estructura de tablas creada exitosamente en '$nombreBD'."];
        } catch (Exception $e) {
            error_log("Error al crear las tablas: " . $e->getMessage());
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public function __sleep()
    {
        throw new RuntimeException("Serialización de mainModel no permitida.");
    }

    public function __wakeup()
    {
        throw new RuntimeException("Deserialización de mainModel no permitida.");
    }
}
