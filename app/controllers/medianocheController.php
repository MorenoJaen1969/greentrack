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

	public function cerrarServiciosNoAtendidos()
	{
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

	/**
	 * Transferir registros desde `preservicios` a `servicios`.
	 * Este método inserta cada preservicio cuya `fecha_programada` sea el día actual
	 * en la tabla `servicios` y marca el registro como `transferred`.
	 */
	public function transferPreservicios()
	{
		try {
			$this->log("=== INICIANDO: Transferencia de preservicios a servicios ===");
			$hoy = date('Y-m-d');

			$query = "SELECT * FROM preservicios WHERE fecha_programada = :hoy AND transferred = 0";
			$params = [':hoy' => $hoy];
			$rows = $this->ejecutarConsulta($query, '', $params, 'fetchAll');

			if (empty($rows)) {
				$this->log("No hay preservicios a transferir para: $hoy");
				echo json_encode(['success' => true, 'message' => 'No preservicios to transfer']);
				return;
			}

			$transferredCount = 0;
			$failed = [];
			foreach ($rows as $r) {
				try {
					// Insertar sólo los campos mínimos; crew/truck serán asignados por MOTOR4 posteriormente
					$meta = json_encode(['from_preservicio' => $r['id_preservicio']]);
					// Determinar id_status por defecto: preferir 'Awaiting Assignment' si existe en status_all
					try {
						$sqlStatus = "SELECT id_status FROM status_all WHERE tabla = 'servicios' AND (LOWER(status) = 'awaiting assignment' OR LOWER(status) = 'por asignar') LIMIT 1";
						$resStatus = $this->ejecutarConsulta($sqlStatus, '', [], 'fetchAll');
						$defaultStatusId = $this->id_status_activo;
						if (!empty($resStatus) && isset($resStatus[0]['id_status'])) {
							$defaultStatusId = (int) $resStatus[0]['id_status'];
						}
					} catch (Exception $es) {
						$defaultStatusId = $this->id_status_activo;
					}

					$datos = [
						// Marcar needs_assignment = 1 para que MOTOR4 identifique servicios pendientes de asignación
						['campo_nombre' => 'needs_assignment', 'campo_marcador' => ':needs_assignment', 'campo_valor' => 1],
						['campo_nombre' => 'id_cliente', 'campo_marcador' => ':id_cliente', 'campo_valor' => $r['id_cliente']],
						['campo_nombre' => 'id_direccion', 'campo_marcador' => ':id_direccion', 'campo_valor' => $r['id_direccion']],
						['campo_nombre' => 'fecha_programada', 'campo_marcador' => ':fecha_programada', 'campo_valor' => $r['fecha_programada']],
						['campo_nombre' => 'dia_servicio', 'campo_marcador' => ':dia_servicio', 'campo_valor' => $r['dia_servicio'] ?? ''],
						['campo_nombre' => 'estado_servicio', 'campo_marcador' => ':estado_servicio', 'campo_valor' => $r['estado_servicio'] ?? 'pendiente'],
						['campo_nombre' => 'id_status', 'campo_marcador' => ':id_status', 'campo_valor' => $r['id_status'] ?? $defaultStatusId],
						['campo_nombre' => 'fecha_creacion', 'campo_marcador' => ':fecha_creacion', 'campo_valor' => $r['fecha_creacion'] ?? date('Y-m-d H:i:s')],
						['campo_nombre' => 'crew_color_principal', 'campo_marcador' => ':crew_color_principal', 'campo_valor' => $r['crew_color_principal'] ?? '#666666'],
						['campo_nombre' => 'ruta_mapa', 'campo_marcador' => ':ruta_mapa', 'campo_valor' => $r['ruta_mapa'] ?? 0],
						['campo_nombre' => 'tiempo_servicio', 'campo_marcador' => ':tiempo_servicio', 'campo_valor' => null],
						['campo_nombre' => 'id_truck', 'campo_marcador' => ':id_truck', 'campo_valor' => null],
						['campo_nombre' => 'id_crew_grupo', 'campo_marcador' => ':id_crew_grupo', 'campo_valor' => null],

					];

					$newId = $this->guardarDatos('servicios', $datos);

					// Marcar preservicio como transferido y guardar id del servicio creado
					$upd = [
						['campo_nombre' => 'transferred', 'campo_marcador' => ':transferred', 'campo_valor' => 1],
						['campo_nombre' => 'transferred_at', 'campo_marcador' => ':transferred_at', 'campo_valor' => date('Y-m-d H:i:s')],
						['campo_nombre' => 'servicio_id', 'campo_marcador' => ':servicio_id', 'campo_valor' => $newId]
					];
					$cond = [
						'condicion_campo' => 'id_preservicio',
						'condicion_operador' => '=',
						'condicion_marcador' => ':id_preservicio',
						'condicion_valor' => $r['id_preservicio']
					];
					$this->actualizarDatos('preservicios', $upd, $cond);
					$transferredCount++;
					$this->log("Preservicio {$r['id_preservicio']} transferido como servicio id={$newId}");
				} catch (Exception $inner) {
					$this->logWithBacktrace("Error al transferir preservicio {$r['id_preservicio']}: " . $inner->getMessage(), true);
					// Registrar fallo para notificación de sistema
					$failed[] = [
						'id_preservicio' => $r['id_preservicio'] ?? null,
						'id_cliente' => $r['id_cliente'] ?? null,
						'cliente' => $r['cliente'] ?? null,
						'direccion' => $r['direccion'] ?? null,
						'error' => $inner->getMessage()
					];
				}
			}

			// Si hubo fallos, publicar un mensaje en la sala de sistema
			if (!empty($failed)) {
				try {
					$currentUserId = $_SESSION['user_id'] ?? 0;
					$room = $this->ejecutarConsulta("SELECT id FROM chat_salas WHERE nombre = :n LIMIT 1", '', [':n' => 'sistema_despacho']);
					if (!$room) {
						$datosSala = [
							['campo_nombre' => 'nombre', 'campo_marcador' => ':nombre', 'campo_valor' => 'sistema_despacho'],
							['campo_nombre' => 'descripcion', 'campo_marcador' => ':descripcion', 'campo_valor' => 'Sala del sistema para notificaciones de despacho'],
							['campo_nombre' => 'creado_por', 'campo_marcador' => ':creado_por', 'campo_valor' => $currentUserId],
							['campo_nombre' => 'fecha_creacion', 'campo_marcador' => ':fecha_creacion', 'campo_valor' => date('Y-m-d H:i:s')],
							['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1]
						];
						$roomId = $this->guardarDatos('chat_salas', $datosSala);
					} else {
						$roomId = $room['id'];
					}

					$parts = [];
					foreach ($failed as $f) {
						$parts[] = ($f['cliente'] ?? ('Cliente ' . ($f['id_cliente'] ?? '?'))) . ' - ' . ($f['direccion'] ?? '');
					}
					$preview = implode('; ', array_slice($parts, 0, 15));
					if (count($parts) > 15) $preview .= '... (+' . (count($parts) - 15) . ' más)';

					$msg = "[SISTEMA] Durante la transferencia de preservicios el " . date('Y-m-d') . " hubo " . count($failed) . " registros que no pudieron convertirse en servicio: " . $preview;

					$datosMsg = [
						['campo_nombre' => 'sala_id', 'campo_marcador' => ':sala_id', 'campo_valor' => $roomId],
						['campo_nombre' => 'usuario_id', 'campo_marcador' => ':usuario_id', 'campo_valor' => $currentUserId],
						['campo_nombre' => 'mensaje', 'campo_marcador' => ':mensaje', 'campo_valor' => $msg],
						['campo_nombre' => 'fecha_envio', 'campo_marcador' => ':fecha_envio', 'campo_valor' => date('Y-m-d H:i:s')]
					];
					$this->guardarDatos('chat_mensajes', $datosMsg);
				} catch (Exception $e) {
					$this->logWithBacktrace('Error creando mensaje sistema en transferPreservicios: ' . $e->getMessage(), true);
				}
			}

			$this->log("✅ Transferencia completada. Total transferidos: $transferredCount; fallidos: " . count($failed));
			echo json_encode(['success' => true, 'transferred' => $transferredCount, 'failed' => count($failed)]);
		} catch (Exception $e) {
			$this->logWithBacktrace("Error en transferPreservicios: " . $e->getMessage(), true);
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
	}
}
