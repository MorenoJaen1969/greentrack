<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

class webhookController extends mainModel
{
	private $log_path;
	private $logFile;
	private $errorLogFile;

	private $o_f;

	public function __construct()
	{
        // ¡ESTA LÍNEA ES CRUCIAL!
        parent::__construct();

		// Nombre del controlador actual abreviado para reconocer el archivo
		$nom_controlador = "webhookController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/webhooks/';

		// Verificar y crear directorio con mejor manejo de errores
		if (!file_exists($this->log_path)) {
			if (!mkdir($this->log_path, 0755, true)) {
				error_log("No se pudo crear el directorio de logs: " . $this->log_path);
				// Fallback a un directorio temporal
				$this->log_path = '/tmp/greentrack_logs/';
				if (!file_exists($this->log_path)) {
					mkdir($this->log_path, 0755, true);
				}
			}
		}

		// Establecer permisos y propietario
		chown($this->log_path, 'www-data');
		chgrp($this->log_path, 'www-data');
		chmod($this->log_path, 0755);

		$this->logFile = $this->log_path . $nom_controlador . '_' . date('Y-m-d') . '.log';
		$this->errorLogFile = $this->log_path . $nom_controlador . '_error_' . date('Y-m-d') . '.log';

		$this->initializeLogFile($this->logFile);
		$this->initializeLogFile($this->errorLogFile);

		$this->verificarPermisos();

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
			
			// Intentar crear el archivo
			$created = file_put_contents($file, $initialContent, FILE_APPEND | LOCK_EX);
			
			if ($created === false) {
				// Si falla, intentar crear directorio padre
				$dir = dirname($file);
				if (!file_exists($dir)) {
					mkdir($dir, 0755, true);
					chown($dir, 'www-data');
					chgrp($dir, 'www-data');
				}
				// Intentar nuevamente
				$created = file_put_contents($file, $initialContent, FILE_APPEND | LOCK_EX);
				
				if ($created === false) {
					error_log("No se pudo crear el archivo de log: " . $file);
					return false;
				}
			}
			
			// Establecer permisos correctos
			chown($file, 'www-data');
			chgrp($file, 'www-data');
			chmod($file, 0644);
		}
		
		return true;
	}
	
	private function verificarPermisos()
	{
		// Verificar permisos del directorio
		if (!is_writable($this->log_path)) {
			error_log("No hay permiso de escritura en: " . $this->log_path);
			
			// Intentar corregir permisos al crear
			if (is_dir($this->log_path)) {
				chmod($this->log_path, 0755);
				chown($this->log_path, 'www-data');
				chgrp($this->log_path, 'www-data');
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
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		$caller = $backtrace[1] ?? $backtrace[0];
		$logMessage = sprintf("[%s] %s - Called from %s::%s (Line %d)%s%s", date('Y-m-d H:i:s'), $message, $caller['class'] ?? '', $caller['function'], $caller['line'], PHP_EOL, "Stack trace:" . PHP_EOL . json_encode($backtrace, JSON_PRETTY_PRINT));
		file_put_contents($this->errorLogFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
	}

    /**
     * Obtener la última coordenada de un vehículo
     */
    public function obtenerUltimaCoordenada($id_truck)
    {
        $query = "SELECT lat, lng, timestamp, id_truck 
                  FROM gps_realtime 
                  WHERE id_truck = :id_truck 
                  ORDER BY id_gps DESC 
                  LIMIT 1";

        $params = [':id_truck' => $id_truck];


        //return $this->ejecutarConsulta($query, 'gps_realtime', $params, 'fetch');
        $this->log("Consulta para obtener Ultima Coordenada: " . $query);
        $resultado = $this->ejecutarConsulta($query, '', $params);
        $this->log("Resultado de la consulta: " . print_r($resultado, true));
        return $resultado;
    }

    /**
     * Guardar una coordenada GPS
     */
    public function guardarGPS($datos)
    {
        return $this->guardarDatos('gps_realtime', $datos);
    }
}