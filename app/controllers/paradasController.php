<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
class paradasController extends mainModel
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
		$nom_controlador = "paradasController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/paradasTruck/';

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

    public function iniciar_parada($vehicle_id, $lat, $lng, $hora_act)
    {
        try {
            if (!$vehicle_id || !is_numeric($lat) || !is_numeric($lng)) {
                throw new Exception("Datos incompletos");
            }

			// Obtener id_truck desde tabla truck
			$sql = "SELECT id_truck FROM truck WHERE nombre = :v_nombre";
			$param = [':v_nombre' => $vehicle_id];

			$result = $this->ejecutarConsulta($sql, '', $param);
			if ($result) {
				$id_truck = $result[0]['id_truck'];

				$datos = [
					['campo_nombre' => 'id_truck',          'campo_marcador' => ':id_truck',		'campo_valor' => $id_truck],
					['campo_nombre' => 'vehicle_id',        'campo_marcador' => ':vehicle_id',      'campo_valor' => $vehicle_id],
					['campo_nombre' => 'fecha_operacion',   'campo_marcador' => ':fecha_operacion',	'campo_valor' => date('Y-m-d', strtotime($hora_act))],
					['campo_nombre' => 'hora_inicio',      	'campo_marcador' => ':hora_inicio',		'campo_valor' => $hora_act],
					['campo_nombre' => 'lat_inicial',       'campo_marcador' => ':lat_inicial',   	'campo_valor' => $lat],
					['campo_nombre' => 'lng_inicial',       'campo_marcador' => ':lng_inicial',   	'campo_valor' => $lng]
				];

				try {
					$id_parada = $this->guardarDatos('paradas_operativas', $datos);
					http_response_code(200);
					echo json_encode([
						'success' => true,
						'id_parada' => $id_parada
					]);

				} catch (Exception $e) {
					error_log("Error al guardar GPS: " . $e->getMessage());
					http_response_code(500);
					echo json_encode(['error' => 'Error interno']);
				}

			}
        } catch (Exception $e) {
            $this->logWithBacktrace("Error en iniciar_parada: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error del servidor']);
        }
    }

	public function cerrar_parada($id_parada, $vehicle_id){
		if (!$id_parada || !$vehicle_id) {
            throw new Exception("ID requerido");
        }

		$datos = [
			["campo_nombre" => "hora_fin",	"campo_marcador" => ":hora_fin",	"campo_valor" => date('H:i:s')],
			["campo_nombre" => "estado",	"campo_marcador" => ":estado",		"campo_valor" => 'cerrada'],
		];

		$condicion = [
			["condicion_campo" => "id_parada", 	"condicion_operador" => "=", "condicion_marcador" => ":id_parada", 	"condicion_valor" => $id_parada],
			["condicion_campo" => "vehicle_id", "condicion_operador" => "=", "condicion_marcador" => ":vehicle_id", 	"condicion_valor" => $vehicle_id],
			["condicion_campo" => "estado", 	"condicion_operador" => "=", "condicion_marcador" => ":estado", 		"condicion_valor" => 'abierta']
		];

		$cant_reg = $this->actualizarDatos("paradas_operativas", $datos, $condicion);
		
		if ($cant_reg > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se encontró parada abierta']);
        }
	}
}
