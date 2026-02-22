<?php

namespace app\controllers;

require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
use DateTime;
use DateTimeZone;
use DateInterval;

class actualizarClientesDireccionesController extends mainModel
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
        $nom_controlador = "actualizarClientesDireccionesController";
        // ____________________________________________________________________

        $this->log_path = APP_R_PROY . 'app/logs/actualizarClientesDirecciones/';

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
        return true;
    }

    public function executeQueryAll($consulta, $param = [], $tipof){
        $consulta = ltrim($consulta);
        preg_match('/^(select|insert|update|delete|create|show)/i', $consulta, $matches);
        $statement = isset($matches[0]) ? strtolower($matches[0]) : 'unknown';
        
        $rawStatement = explode(" ", preg_replace("/\s+|\t+|\n+/", " ", $consulta));
        $statement = strtolower($rawStatement[0]);

        if ($statement === 'update' || $statement === 'delete') {
            $this->log("Proceso: :" . $statement . " Consulta: " . $consulta);
            // Desactivar foreign keys temporalmente
            $accion = $this->ejecutarComando("SET FOREIGN_KEY_CHECKS = 0;");
            $this->log("Accion ocurrida antes: " . json_encode($accion));
            $total_prueba = $this->ejecutarConsulta($consulta, "", $param, $tipof);
            $this->log("Consulta: " . $total_prueba);
            $accion = $this->ejecutarComando("SET FOREIGN_KEY_CHECKS = 1;");
            $this->log("Accion ocurrida despues: " . json_encode($accion));
        }else{
            $total_prueba = $this->ejecutarConsulta($consulta, "", $param, $tipof);
        }

        return $total_prueba;
    }

}
