<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';
require_once APP_R_PROY . 'app/controllers/usuariosController.php';

use app\models\mainModel;
use app\controllers\usuariosController;

use \Exception;
use DateTime;
use DateTimeZone;
use DateInterval;

class locationTypesController extends mainModel
{
	private $ultimoToken = null;
	private $tokenExpiraEn = null; // Timestamp de expiración
	private $log_path;
	private $logFile;
	private $errorLogFile;
	private $ultimaCoordenada = [];
	private $o_f;
	public function __construct()
	{
		// ¡ESTA LÍNEA ES CRUCIAL!
		parent::__construct();

		// Nombre del controlador actual abreviado para reconocer el archivo
		$nom_controlador = "locationTypesController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/locationTypes/';

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

    public function consultar_location_types() {
        // Obtenemos id, name y el booleano que controla el input del número
        $sql = "SELECT id, name, requires_number 
            FROM cat_location_types 
            WHERE active = 1 
            ORDER BY name ASC";

        $params = [];    
        $datos = $this->ejecutarConsulta($sql, "", $params, "fetchAll");
        
        return $datos;
    }

	public function getlocationType()
	{
        try {
            $stmt = "SELECT id_cat_location_types, name, requires_number, code, description 
				FROM cat_location_types 
				WHERE active = 1 
				ORDER BY name ASC";
            $params = [];
            $data = $this->ejecutarConsulta($stmt, "", $params, "fetchAll");
			$this->log("Datos procesados: " . json_encode($data));

            return $data;
        } catch (Exception $e) {
			$this->logWithBacktrace("❌ Error al guardar scheduled_service: " . $e->getMessage(), true);
            return $e->getMessage();
        }
	}
}