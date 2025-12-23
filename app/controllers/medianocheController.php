<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
class medianocheController extends mainModel
{
	private $log_path;
	private $logFile;
	private $errorLogFile;

	private $id_status_cancelado;
	private $id_status_activo;
	private $id_status_historico;

	private $o_f;

	public function __construct()
	{
       // ¡ESTA LÍNEA ES CRUCIAL!
        parent::__construct();

		// Nombre del controlador actual abreviado para reconocer el archivo
		$nom_controlador = "medianocheController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/cron/';

		if (!file_exists($this->log_path)) {
			mkdir($this->log_path, 0775, true);
			chgrp($this->log_path, 'www-data');
			chmod($this->log_path, 0775); // Asegurarse de que el directorio sea legible y escribible
		}

		$this->logFile = $this->log_path . $nom_controlador . '_' . date('Y-m-d') . '.log';
		$this->errorLogFile = $this->log_path . $nom_controlador . '_error_' . date('Y-m-d') . '.log';

		$this->initializeLogFile($this->logFile);
		$this->initializeLogFile($this->errorLogFile);

		$this->verificarPermisos();

		// rotación automatica de log (Elimina logs > XX dias)
		$this->rotarLogs(15);

		$this->id_status_cancelado = 47;
		$this->id_status_historico = 39;
		$this->id_status_activo = 37;

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

	public function cerrarServiciosNoAtendidos() {
		try {
			$this->log("=== INICIANDO: Cierre de servicios no atendidos ==="); 

			$hoy = date('Y-m-d');

			// === 1. Cerrar servicios no atendidos (id_status = 37 → 48) ===

			try {
				$datos = [
					['campo_nombre' => 'id_status', 'campo_marcador' => ':id_status', 'campo_valor' => 48],
					['campo_nombre' => 'estado_servicio', 'campo_marcador' => ':estado_servicio', 'campo_valor' => 'no_servido'],
					['campo_nombre' => 'fecha_actualizacion', 'campo_marcador' => ':fecha_actualizacion', 'campo_valor' => $hoy]
				];
				$condicion = [
					['condicion_campo' => 'fecha_programada', 'condicion_operador' => '<', 'condicion_marcador' => ':fecha_programada', 'condicion_valor' => $hoy],
					['condicion_campo' => 'id_status', 'condicion_operador' => '=', 'condicion_marcador' => ':id_status', 'condicion_valor' => 37]
				];

				$cant_reg = $this->actualizarDatos('servicios', $datos, $condicion);
				$this->log("✅ Cierre completado. $cant_reg servicios marcados como 'No Servido'");

				http_response_code(200);
				echo json_encode(['success' => 'ok', 'message' => $cant_reg . ' Record Update completed']);

			} catch (Exception $e) {
				$this->logWithBacktrace("Error en finalizarServicio: " . $e->getMessage(), true);
				http_response_code(500);
				echo json_encode(['error' => 'Could not update']);
			}

			// === 2. Actualizar campo `historial = 1` en clientes que tengan servicios anteriores válidos ===
			$query2 = "
				UPDATE clientes 
				SET historial = 1 
				WHERE EXISTS (
					SELECT 1 
					FROM servicios s 
					WHERE s.id_cliente = clientes.id_cliente 
					AND s.id_status != 39 
					AND s.fecha_programada < :hoy
				)";

			$params2 = [':hoy' => $hoy];
			$filas = $this->ejecutarConsulta($query2, '', $params2);

			if ($filas > 0) {
				$this->registrarAuditoriaOperacionCompleja(
					tabla: 'clientes',
					accion: 'UPDATE',
					query: $query2,
					params: [':hoy' => $hoy]
				);
			}

			// Limpiar estado GPS al cerrar el día
			if (isset($_SESSION['gps_tracker_last'])) {
				unset($_SESSION['gps_tracker_last']);
				$this->log("🧹 SESIÓN gps_tracker_last limpiada a medianoche");
			}

			$this->log("✅ Campo 'historial' actualizado en clientes con servicios anteriores válidos");

			http_response_code(200);
			echo json_encode([
				'success' => true,
				'message' => "Closure of unattended services and history update completed",
				'servicios_cerrados' => $filas
			]);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en cierre nocturno: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode([
				'success' => false,
				'error' => $e->getMessage()
			]);
		}
	}
}