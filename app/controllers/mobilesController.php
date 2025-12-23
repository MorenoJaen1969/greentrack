<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';
require_once APP_R_PROY . 'app/controllers/usuariosController.php';

use app\models\mainModel;
use app\controllers\usuariosController;

use \Exception;
use DateTime;
use DateTimeZone;

class mobilesController extends mainModel
{
	private $ultimoToken = null;
	private $tokenExpiraEn = null; // Timestamp de expiración
	private $log_path;
	private $logFile;
	private $errorLogFile;
	private $ultimaCoordenada = [];
	private $o_f;
	private $id_status_cancelado;
	private $id_status_activo;
	private $id_status_historico;
	private $id_status_finalizado;
	private $id_status_replanificado;

	public function __construct()
	{
		// ¡ESTA LÍNEA ES CRUCIAL!
		parent::__construct();

		// Nombre del controlador actual abreviado para reconocer el archivo
		$nom_controlador = "mobilesController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/mobiles/';

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

	public function validarAcceso($token)
	{
		// 1. Verificar que el usuario tiene acceso
		if (!isset($_SESSION['user_valid']) || !$_SESSION['user_valid']) {
			http_response_code(403);
			echo json_encode([
				'status' => 'error',
				'message' => 'Acceso denegado: no autenticado'
			]);
			exit;
		}

		// 2. Verificar que el access_key existe en sesión
		$access_key = $_SESSION['token'] ?? null;
		if (!$access_key) {
			http_response_code(403);
			echo json_encode([
				'status' => 'error',
				'message' => 'Acceso denegado: falta token de sesión'
			]);
			exit;
		}

		$usuarioController = new usuariosController();
		$userData = $usuarioController->getUserByToken($token);

		if (!$userData) {
			session_destroy();
			http_response_code(403);
			echo json_encode([
				'status' => 'error',
				'message' => 'Token inválido o expirado'
			]);
			exit;
		}

		http_response_code(200);
		echo json_encode([
			'status' => 'ok',
		]);
		exit;
	}

	public function listarmobilesConEstado($fecha = null, $truck = null)
	{
		$this->log("Inicio: listarServiciosConEstado");

		try {
			// Obtener el día actual en formato inglés (como en la BD)
			$diasMap = [
				'sunday' => 'SUNDAY',
				'monday' => 'MONDAY',
				'tuesday' => 'TUESDAY',
				'wednesday' => 'WEDNESDAY',
				'thursday' => 'THURSDAY',
				'friday' => 'FRIDAY',
				'saturday' => 'SATURDAY'
			];

			$dia_actual = $diasMap[strtolower(date('l'))]; // Ej: 'TUESDAY'

			$this->log("Día actual: " . $dia_actual);

			if ($fecha) {
					$fecha_programada2 = $fecha;
			} else {
					$fecha_programada2 = date('Y-m-d');
			}

			$query = "SELECT
						s.id_servicio, 
						s.id_cliente,
						c.nombre as cliente,
						s.id_direccion,
						s.id_truck,
						s.id_crew_1,
						s.id_crew_2,
						s.id_crew_3,
						s.id_crew_4,
						s.id_status,
						t.color AS crew_color_principal,
						s.dia_servicio,
						s.finalizado,
						s.estado_servicio,    
						s.estado_visita,
						s.hora_aviso_usuario,
						s.hora_finalizado,
						s.tipo_dia,
						s.hora_inicio_gps,
						s.hora_fin_gps,
						d.direccion, 
						d.id_geofence, 
						d.lat, 
						d.lng, 
						t.nombre as truck,
						CASE 
							WHEN s.id_status = 37 AND s.hora_aviso_usuario IS NOT NULL THEN 'Service started'
							WHEN s.id_status = 38 
								AND s.hora_aviso_usuario IS NOT NULL 
								AND s.hora_finalizado IS NOT NULL THEN 
								CONCAT('Processed (', 
									TIME_FORMAT(TIMEDIFF(s.hora_finalizado, s.hora_aviso_usuario), '%H:%i:%s'),
									')'
								)
							WHEN s.id_status = 38 
								AND s.hora_aviso_usuario IS NULL 
								AND s.hora_finalizado IS NOT NULL THEN 'Not started, finished'
							WHEN s.id_status = 40 THEN 'Rescheduled'
							WHEN s.id_status = 47 THEN 'Cancelled'
							ELSE 'Pendiente'
						END AS s_status,
						CASE
						    WHEN s.hora_inicio_gps IS NOT NULL AND s.hora_fin_gps IS NOT NULL THEN 
						        CONCAT('Service Performed (', TIME_FORMAT(TIMEDIFF(s.hora_fin_gps, s.hora_inicio_gps), '%H:%i:%s'), ')')
						    WHEN s.hora_inicio_gps IS NOT NULL AND s.hora_fin_gps IS NULL THEN 
						        CONCAT('Started (', TIMESTAMPDIFF(MINUTE, s.hora_inicio_gps, NOW()), ' min ago)')
						    WHEN s.hora_inicio_gps IS NULL AND s.hora_fin_gps IS NOT NULL THEN 
						        'Finished'
						    WHEN s.hora_inicio_gps IS NULL AND s.hora_fin_gps IS NULL THEN 
						        'Not yet attended'
						END AS status_m2
					FROM servicios AS s
					LEFT JOIN clientes AS c ON s.id_cliente = c.id_cliente
					LEFT JOIN direcciones AS d ON s.id_direccion = d.id_direccion
					LEFT JOIN truck AS t ON s.id_truck = t.id_truck
					WHERE s.id_status != $this->id_status_historico
					AND s.id_servicio IN (
							SELECT DISTINCT id_servicio 
							FROM servicios 
							WHERE id_status != $this->id_status_historico
								AND DATE(fecha_programada) = '$fecha_programada2'
						)
					GROUP BY s.id_servicio, s.id_cliente, s.id_direccion, s.id_truck, s.id_crew_1, 
						s.id_crew_2, s.id_crew_3, s.id_crew_4, s.id_status, t.color, 
						s.dia_servicio, s.finalizado, s.estado_servicio, s.estado_visita, 
						s.hora_aviso_usuario, s.hora_finalizado, s.tipo_dia, s.hora_inicio_gps, 
						s.hora_fin_gps, c.nombre, d.direccion, d.id_geofence, d.lat, d.lng, 
						t.nombre";

			$params = [];
			if ($fecha) {
				$query .= " AND DATE(s.fecha_programada) = :v_fecha_programada";
				$params = [
					':v_fecha_programada' => $fecha
				];
			} else {
				$query .= " AND DATE(s.fecha_programada) = :v_fecha_programada";
				$params = [
					':v_fecha_programada' => date('Y-m-d')
				];
			}
			if ($truck) {
				$query .= " AND s.id_truck = :v_id_truck";
				$params[':v_id_truck'] = $truck;
			}
			$query .= " ORDER BY FIELD(t.id_truck, 1,2,3,4,5,6,7,8,9,10,11,12), c.nombre";

			$this->log("Consulta de listarServiciosConEstado(): " . $query);
			$this->log("Parametros: " . json_encode($params));

			$servicios = $this->ejecutarConsulta($query, '', $params, 'fetchAll');

			$this->log("Resultado de la consulta: " . print_r($servicios, true));

			$resultado = []; 
			foreach ($servicios as $s) {
				// === Obtener integrantes del crew ===
				$crew_integrantes = [];

				$ids_crew = [
					$s['id_crew_1'],
					$s['id_crew_2'],
					$s['id_crew_3'],
					$s['id_crew_4']
				];

				foreach ($ids_crew as $id) {
					if ($id) {
						$query_crew = "SELECT id_crew, nombre_completo, nombre, apellido, color, crew as responsabilidad
							FROM crew 
							WHERE id_crew = :id AND id_status = 32";
						$params_crew = [':id' => $id];
						$result = $this->ejecutarConsulta($query_crew, '', $params_crew);
						if ($result) {
							$crew_integrantes[] = $result;
						}
					}
				}

				$registro = [
					'id_servicio' => $s['id_servicio'],
					'id_status' => $s['id_status'],
					'id_cliente' => $s['id_cliente'],
					'cliente' => $s['cliente'],
					'direccion' => $s['direccion'],
					'id_geofence' => $s['id_geofence'],
					'truck' => $s['truck'],
					'crew_color_principal' => $s['crew_color_principal'] ?? '#666666',
					'lat' => $s['lat'],
					'lng' => $s['lng'],
					'finalizado' => (bool) $s['finalizado'],
					'dia_servicio' => $s['dia_servicio'],
					'dia_servicio_ct' => $s['dia_servicio'],
					'crew_integrantes' => $crew_integrantes,
					'estado_visita' => $s['estado_visita'] ?? 'programado',
					'estado_servicio' => $s['estado_servicio'],
					'tipo_dia' => $s['tipo_dia'],
					'hora_aviso_usuario' => $s['hora_aviso_usuario'],
					'hora_finalizado' => $s['hora_finalizado'],
					's_status' => $s['s_status'],
					'status_m2' => $s['status_m2'],
					'hora_inicio_gps' => $s['hora_inicio_gps'],
					'hora_fin_gps' => $s['hora_fin_gps'],
					'evidencias' => []
				];



				// Solo aplica la validación de día si es tipo "semanal"
//				if ($s['tipo_dia'] === 'semanal' && $s['dia_servicio']) {
//					if ($s['dia_servicio'] !== $dia_actual) {
//						$registro['estado_visita'] = 'replanificado';
//					}
//				}

				// Si no tiene día asignado
				if (!$s['dia_servicio']) {
					$registro['estado_visita'] = 'sin_programar';
				}

				$resultado[] = $registro;
			}

			http_response_code(200);
			echo json_encode($resultado, JSON_PRETTY_PRINT);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en listarServiciosConEstado: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'Error al cargar servicios']);
		}
	}

	public function listarVehiculos() 
	{
		$sql = "SELECT * 
			FROM truck 
			WHERE id_status = 26 
			ORDER BY nombre";	
		
		try {
			$vehiculos = $this->ejecutarConsulta($sql, '', [], 'fetchAll');

			echo json_encode([
				'success' => true,
				'data' => array_values($vehiculos)
			]);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en listarVehiculos: " . $e->getMessage());
			echo json_encode([
				'success' => false,
				'error' => 'Error al cargar vehiculos'
			]);
		}
		exit(); 
	}

	public function cargar_clientes_y_direccion()
	{
		$sql = "SELECT c.nombre as cliente, d.direccion, d.lat, d.lng
			FROM clientes c
			LEFT JOIN direcciones d ON c.id_cliente = d.id_cliente
			ORDER BY d.lat DESC, d.lng DESC";

		$param = [];

		$data = $this->ejecutarConsulta($sql, '', $param, 'fetchAll');

		echo json_encode([
			'success' => true,
			'data' => array_values($data)
		]);
	}

}