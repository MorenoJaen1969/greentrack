<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
class datosgeneralesController extends mainModel
{
	private $log_path;
	private $logFile;
	private $errorLogFile;

	private $id_status_cancelado;
	private $id_status_activo;
	private $id_status_historico;
	private $id_status_finalizado;
	private $id_status_replanificado;

	private $o_f;

	public function __construct()
	{
		// ¡ESTA LÍNEA ES CRUCIAL!
		parent::__construct();

		// Nombre del controlador actual abreviado para reconocer el archivo
		$nom_controlador = "datosgeneralesController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/datosgenerales/';

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

		// rotación automatica de log (Elimina logs > XX dias)
		$this->rotarLogs(15);

		$this->id_status_cancelado = 47;
		$this->id_status_finalizado = 38;
		$this->id_status_historico = 39;
		$this->id_status_activo = 37;
		$this->id_status_replanificado = 40;

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

    public function obtener_Clave($clave) 
    {
        $query = "SELECT valor 
			FROM configuracion_sistema 
			WHERE clave = :v_clave";
        $parametros = ['v_clave' => $clave];

		try {
			$resultado = $this->ejecutarConsulta($query, '', $parametros, 'fetchAll');
			$clave = array_map(function($row) {
				return [
					'valor' => $row['valor']
				];
			}, $resultado);

        	echo json_encode(['valor' => $clave]);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error crítico en obtenerClave: " . $e->getMessage(), true);
	        echo json_encode(['valor' => []]);
		}

    }

    public function tiempos_de_actividad() 
    {
        $query = "SELECT clave, valor, TIME_FORMAT(valor, '%H:%i') AS valor_hora
			FROM configuracion_sistema
			WHERE clave IN ('hora_cierre_sesion', 'hora_fin_jornada', 'hora_inicio_jornada')";

        $parametros = [];

		try {
			$resultado = $this->ejecutarConsulta($query, '', $parametros, 'fetchAll');

			// Valores por defecto
			$parametros = [
				'hora_cierre_sesion' => '18:30',
				'hora_fin_jornada'     => '18:00',
				'hora_inicio_jornada'  => '08:00'
			];

			if ($resultado && is_array($resultado)) {
				foreach ($resultado as $fila) {
					if ($fila['valor'] !== null) {
						$parametros[$fila['clave']] = $fila['valor'];
					}
				}
			}
		    return $parametros; // ✅ SOLO RETURN, NUNCA ECHO

		} catch (Exception $e) {
			$this->logWithBacktrace("Error crítico en obtenerClave: " . $e->getMessage(), true);
	        echo json_encode(['valor' => []]);
		}
    }

	public function datos_para_gps()
	{
        $query = "SELECT valor 
			FROM configuracion_sistema 
			WHERE clave = :v_clave1 or clave = :v_clave2 OR clave = :v_clave3 OR clave = :v_clave4";
        $parametros = [
			'v_clave1' => "mapa_base",
			'v_clave2' => "umbral_metros",
			'v_clave3' => "umbral_minutos",
			'v_clave4' => "umbral_course"
		];

		try {
			$resultado = $this->ejecutarConsulta($query, '', $parametros, 'fetchAll');

			// Convertir resultado en un array asociativo: ['mapa_base' => 'ESRI', ...]
			$config = [];
			foreach ($resultado as $row) {
				$config[$row['valor']] = $row['valor'];
			}

			// Ahora puedes acceder fácilmente:
			// $config['mapa_base'], $config['umbral_metros'], etc.

			echo json_encode([
				'success' => true,
				'config' => $config
			]);

		} catch (Exception $e) {
			echo json_encode([
				'success' => false,
				'error' => 'Error al obtener configuraciones',
				'detalle' => $e->getMessage()
			]);
		}
	}
}
?>    