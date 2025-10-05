<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';
require_once APP_R_PROY . 'app/models/datosGenerales.php';

use app\models\mainModel;
use app\models\datosGenerales;

use \Exception;
class serviciosController extends mainModel
{
	private $log_path;
	private $logFile;
	private $errorLogFile;

	private $mapa_base;
	private $umbral_metros;
	private $umbral_minutos;

	private $id_status_cancelado;
	private $id_status_activo;
	private $id_status_historico;
	private $id_status_finalizado;
	private $id_status_replanificado;

	private $o_f;

	private $DGcontroller;

	public function __construct()
	{
		// ¡ESTA LÍNEA ES CRUCIAL!
		parent::__construct();

		// Nombre del controlador actual abreviado para reconocer el archivo
		$nom_controlador = "serviciosController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/greentrack/';

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

		$this->DGcontroller = new datosGenerales();
		$arre_DG = $this->DGcontroller->datos_para_gps();

		$this->mapa_base = $arre_DG['config']['mapa_base'] ?? 'OSM';
		$this->umbral_metros = (int) ($arre_DG['config']['umbral_metros'] ?? 150);
		$this->umbral_minutos = (int) ($arre_DG['config']['umbral_minutos'] ?? 5);

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

	/**
	 * Listar todos los servicios desde la base de datos
	 */
	public function listarServicios()
	{
		$this->log("Inicio de actividad: listarServicios");
		try {
			$query = "SELECT
						s.id_servicio, 
						s.id_cliente,
						s.id_direccion,
						s.id_truck,
						s.id_crew_1,
						s.id_crew_2,
						s.id_crew_3,
						s.id_crew_4,
						t.color AS crew_color_principal,
						s.dia_servicio,
						s.finalizado,
						s.estado_servicio,			
						s.estado_visita,
						c.nombre as cliente, 
						d.direccion, 
						d.geofence_id, 
						d.lat, 
						d.lng, 
						t.nombre as truck, 
						ct.day_work AS dia_servicio 
					FROM servicios AS s
					LEFT JOIN clientes AS c ON s.id_cliente = c.id_cliente
					LEFT JOIN direcciones AS d ON s.id_direccion = d.id_direccion
					LEFT JOIN contratos AS ct ON s.id_cliente = ct.id_cliente
					LEFT JOIN truck AS t ON s.id_truck = t.id_truck
					WHERE s.id_status = 37  -- 'Activo'
					ORDER BY 
						FIELD(t.id_truck, 1,2,3,4,5,6,11,15), 
						c.nombre";

			$servicios = $this->ejecutarConsulta($query, '', [], 'fetchAll');

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
					'cliente' => $s['cliente'],
					'direccion' => $s['direccion'],
					'geofence_id' => $s['geofence_id'],
					'truck' => $s['truck'],
					'crew_color_principal' => $s['crew_color_principal'] ?? '#666666',
					'lat' => $s['lat'],
					'lng' => $s['lng'],
					'finalizado' => (bool) $s['finalizado'],
					'dia_servicio' => $s['dia_servicio'],
					'estado_visita' => $s['estado_visita'] ?? 'programado',
					'crew_integrantes' => $crew_integrantes,
					'evidencias' => []
				];

				// Si está finalizado, obtener evidencias (por ahora, por lógica de tiempo)
				if ($s['finalizado']) {
					$registro['evidencias'] = ['gps', 'tiempo']; // Ej: evidencias por parada larga
				}

				$resultado[] = $registro;
			}

			http_response_code(200);
			echo json_encode($resultado, JSON_PRETTY_PRINT);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en listarServicios: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'Error al cargar servicios']);
		}
	}

	/**
	 * Finalizar un servicio manualmente (opcional)
	 */
	public function finalizarServicio($id)
	{
		try {
			$datos = [
				['campo_nombre' => 'finalizado', 'campo_marcador' => ':finalizado', 'campo_valor' => 1]
			];
			$condicion = [
				'condicion_campo' => 'id',
				'condicion_marcador' => ':id',
				'condicion_valor' => $id
			];

			$this->actualizarDatos('servicios', $datos, $condicion);

			http_response_code(200);
			echo json_encode(['status' => 'ok', 'message' => 'Servicio finalizado']);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en finalizarServicio: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'No se pudo finalizar']);
		}
	}

	public function listarServiciosConEstado($fecha = null, $truck = null)
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

			$query = "SELECT
						s.id_servicio, 
						s.id_cliente,
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
						c.nombre as cliente, 
						d.direccion, 
						d.geofence_id, 
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
					LEFT JOIN direcciones AS d ON c.id_cliente = d.id_cliente
					LEFT JOIN truck AS t ON s.id_truck = t.id_truck
					WHERE s.id_status != $this->id_status_historico";

			// quite esta linea por que contratos esta vacio:
// ct.day_work AS dia_servicio_ct 
// 					LEFT JOIN contratos AS ct ON s.id_cliente = ct.id_cliente

			$params = [];
			if ($fecha) {
				$query .= " AND DATE(s.fecha_programada) = :v_fecha_programada";
				$params[':v_fecha_programada'] = $fecha;
			} else {
				$query .= " AND DATE(s.fecha_programada) = :v_fecha_programada";
				$params[':v_fecha_programada'] = date('Y-m-d');
			}
			if ($truck) {
				$query .= " AND s.id_truck = :v_id_truck";
				$params[':v_id_truck'] = $truck;
			}
			$query .= " ORDER BY FIELD(t.id_truck, 1,2,3,4,5,6,7,8,9,10,11,12), c.nombre";

			$this->log("Consulta de listarServiciosConEstado(): " . $query);

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
					'geofence_id' => $s['geofence_id'],
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

	public function listarServicioshistorico($fecha = null, $truck = null)
	{
		$this->log("Inicio: listarServiciosConEstado - Historico Individual");

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

			$dia_actual = $diasMap[strtolower(date('l', strtotime($fecha)))];

			$this->log("Día actual: " . $dia_actual . " Fecha de calculo: " . $fecha);

			$query = "SELECT
						s.id_servicio, 
						s.id_cliente,
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
						c.nombre as cliente, 
						d.direccion, 
						d.geofence_id, 
						d.lat, 
						d.lng, 
						t.nombre as truck
					FROM servicios AS s
					LEFT JOIN clientes AS c ON s.id_cliente = c.id_cliente
					LEFT JOIN direcciones AS d ON c.id_cliente = d.id_cliente
					LEFT JOIN truck AS t ON s.id_truck = t.id_truck
					WHERE s.id_status != $this->id_status_historico AND DATE(s.fecha_programada) = :v_fecha_programada
						AND t.verizon_device_id = :v_id_truck
					ORDER BY CASE WHEN s.hora_finalizado IS NULL THEN 1 ELSE 0 END, s.hora_finalizado ASC";

			$params = [
				':v_fecha_programada' => $fecha,
				':v_id_truck' => $truck
			];

			$servicios = $this->ejecutarConsulta($query, '', $params, 'fetchAll');
			$this->log("Resultado de la consulta Historicos Indivudual: " . print_r($servicios, true));

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
					'geofence_id' => $s['geofence_id'],
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
					'hora_inicio_gps' => $s['hora_inicio_gps'],
					'hora_fin_gps' => $s['hora_fin_gps'],
					'evidencias' => []
				];

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

	public function listarServiciosParaModal()
	{
		$this->log("Inicio: listarServiciosParaModal");

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

			$query = "SELECT
						s.id_servicio, 
						s.id_cliente,
						s.id_direccion,
						s.id_truck,
						s.id_crew_1,
						s.id_crew_2,
						s.id_crew_3,
						s.id_crew_4,
						t.color AS crew_color_principal,
						s.dia_servicio,
						s.finalizado,
						s.estado_servicio,	
						s.estado_visita,
			            s.hora_aviso_usuario,
						s.hora_finalizado,
						s.tipo_dia,
						c.nombre as cliente, 
						d.direccion, 
						d.geofence_id, 
						d.lat, 
						d.lng, 
						t.nombre as truck, 
					FROM servicios AS s
					LEFT JOIN clientes AS c ON s.id_cliente = c.id_cliente
					LEFT JOIN direcciones AS d ON c.id_cliente = d.id_cliente
					LEFT JOIN truck AS t ON s.id_truck = t.id_truck
					WHERE DATE(s.fecha_programada) = :v_fecha_programada AND s.id_status != $this->id_status_historico
					ORDER BY c.nombre ASC";

			// quite esta linea por que contratos esta vacio:
// ct.day_work AS dia_servicio_ct 
// 					LEFT JOIN contratos AS ct ON s.id_cliente = ct.id_cliente

			$params = [
				':v_fecha_programada' => date('Y-m-d')
			];

			$this->log("Consulta: " . $query);

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
					'id_cliente' => $s['id_cliente'],
					'cliente' => $s['cliente'],
					'direccion' => $s['direccion'],
					'geofence_id' => $s['geofence_id'],
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
					'hora_aviso_usuario' => $s['hora_aviso_usuario'],
					'hora_finalizado' => $s['hora_finalizado'],
					'tipo_dia' => $s['tipo_dia'],
					'evidencias' => []
				];

				// === Determinar estado_visita ===
				$registro['estado_visita'] = 'programado';

				// Solo aplica la validación de día si es tipo "semanal"
				if ($s['tipo_dia'] === 'semanal' && $s['dia_servicio']) {
					if ($s['dia_servicio'] !== $dia_actual) {
						$registro['estado_visita'] = 'replanificado';
					}
				}
				// Si es tipo "fijo", no se marca como replanificado por día
				// (puede tener otro estado, pero no por desfase de día)

				// Si no tiene día asignado
				if (!$s['dia_servicio']) {
					$registro['estado_visita'] = 'sin_programar';
				}

				$resultado[] = $registro;
			}

			http_response_code(200);
			echo json_encode($resultado, JSON_PRETTY_PRINT);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en listarServiciosParaModal: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'Error al cargar servicios para Modal']);
		}

	}

	private function clienteTrabajaEsteDia($id_cliente, $dia_servicio)
	{
		// Asumimos que la tabla 'contratos' tiene el campo 'day_work'
		$query = "SELECT id_contrato FROM contratos WHERE id_cliente = :id_cliente AND day_work = :day_work AND id_status = 18";
		$params = [
			':id_cliente' => $id_cliente,
			':day_work' => $dia_servicio
		];
		$result = $this->ejecutarConsulta($query, '', $params);
		return $result !== false;
	}

	/**
	 * Obtiene el historial de servicios de un cliente
	 * @param int $id_cliente
	 * @return void (echo JSON)
	 */
	public function obtenerHistorialCliente($id_cliente)
	{
		try {
			$this->log("=== OBTENIENDO HISTORIAL PARA CLIENTE: $id_cliente ===");

			$query = "SELECT 
						s.fecha_programada,
						t.nombre as truck,
						s.estado_servicio,
						s.finalizado
					FROM servicios AS s
					LEFT JOIN truck AS t ON s.id_truck = t.id_truck
					WHERE s.id_cliente = :id_cliente
						AND s.id_status = 37
					ORDER BY s.fecha_programada DESC
					LIMIT 10";

			$params = [':id_cliente' => $id_cliente];
			$result = $this->ejecutarConsulta($query, '', $params, 'fetchAll');

			$historial = [];
			foreach ($result as $row) {
				$estado = 'programado';
				if ($row['finalizado']) {
					$estado = 'finalizado';
				} else {
					switch ($row['estado_servicio']) {
						case 'usuario_alerto':
						case 'gps_detectado':
							$estado = 'en_proceso';
							break;
						case 'replanificado':
							$estado = 'replanificado';
							break;
						case 'cancelado':
							$estado = 'cancelado';
							break;
						default:
							$estado = 'pendiente';
							break;
					}
				}

				$historial[] = [
					'fecha_programada' => $row['fecha_programada'],
					'truck' => $row['truck'],
					'estado_visita' => $estado
				];
			}

			http_response_code(200);
			echo json_encode(['historial' => $historial]);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en obtenerHistorialCliente: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'Error al cargar historial']);
		}
	}

	public function actualizarEstadoConHistorial($data, $origen_act)
	{
		$id_servicio = $data['id_servicio'] ?? null;
		$estado = $data['estado'] ?? null;
		$notas = $data['notas'] ?? '';
		$estado_actual = $data['estado_actual'] ?? '';
		$cliente = $data['cliente'] ?? null;
		$truck = $data['truck'] ?? null;

		try {
			$this->log("=== ACTUALIZANDO CON HISTORIAL: Servicio $id_servicio a $estado ===");

			if ($origen_act = "motor1") {
				if ($estado === 'INICIO DE SERVICIO') {
					$estado = 'inicio_actividades';
				} elseif ($estado === 'FINALIZO SERVICIO') {
					$estado = 'finalizado';
				} elseif ($estado === 'CANCELAR SERVICIO') {
					$estado = 'cancelado';
				} elseif ($estado === 'REPLANIFICAR SERVICIO') {
					$estado = 'replanificado';
				}
			} else {
				if ($estado === 'FINALIZO SERVICIO') {
					$estado = 'finalizado';
				} elseif ($estado === 'REPLANIFICAR SERVICIO') {
					$estado = 'replanificado';
				} elseif ($estado === 'CANCELAR SERVICIO') {
					$estado = 'cancelado';
				} elseif ($estado === 'INICIO DE SERVICIO') {
					$estado = 'inicio_actividades';
				}
			}

			// Validar estado operativo

			$estados_validos = ['finalizado', 'replanificado', 'cancelado', 'inicio_actividades'];

			if (!in_array($estado, $estados_validos)) {
				http_response_code(400);
				echo json_encode(['error' => 'Estado no válido']);
				return;
			}

			// === 1. Determinar estado interno y id_status ===
			$estado_servicio = $estado;
			$estado_visita = NULL;
			$id_status = 37; // Activo
			$finalizado = 0;

			if ($estado === 'finalizado') {
				$estado_servicio = 'finalizado';
				$estado_visita = 'finalizado';
				$id_status = $this->id_status_finalizado;
				$finalizado = 1;
			} elseif ($estado === 'replanificado') {
				$estado_servicio = 'usuario_alerto';
				$estado_visita = 'replanificado';
				$id_status = $this->id_status_replanificado;
			} elseif ($estado === 'cancelado') {
				$estado_servicio = 'cancelado';
				$estado_visita = 'cancelado';
				$id_status = $this->id_status_cancelado;
			} elseif ($estado === 'inicio_actividades') {
				$estado_servicio = 'inicio_actividades';
				$id_status = $this->id_status_activo;
			}

			// Si es "Inicio de actividades", registrar la hora
			$hora_aviso = null;
			if ($estado === 'inicio_actividades' || $estado === 'cancelado' || $estado === 'replanificado') {
				$hora_aviso = date('Y-m-d H:i:s');
			}

			// Si el estado es 'finalizado', registrar la hora actual
			$hora_finalizado = null;
			if ($estado === 'finalizado') {
				$hora_finalizado = date('Y-m-d H:i:s');
			}

			// === 2. Validar cliente y truck ===
			$id_cliente = $this->validarCliente($cliente);
			$id_truck = $this->validarTruck($truck);

			if (!$id_cliente || !$id_truck) {
				http_response_code(400);
				echo json_encode(['error' => 'Cliente o Truck no válido']);
				return;
			}

			// === 3. Actualizar servicio con estado_servicio ===
			$sql = "UPDATE servicios 
					SET estado_servicio = :v_estado_servicio, 
						estado_visita = :v_estado_visita,
						id_status = :v_id_status, 
						finalizado = :v_finalizado,
						hora_aviso_usuario = COALESCE(hora_aviso_usuario, :v_hora_aviso_usuario),
			            hora_finalizado = COALESCE(hora_finalizado, :v_hora_finalizado)
					WHERE id_servicio = :v_id_servicio";

			$param = [
				':v_estado_servicio' => $estado_servicio,
				':v_estado_visita' => $estado_visita,
				':v_id_status' => $id_status,
				':v_finalizado' => $finalizado,
				':v_hora_aviso_usuario' => $hora_aviso,
				':v_hora_finalizado' => $hora_finalizado,
				':v_id_servicio' => $id_servicio
			];

			$this->log("Actualización SQL: $sql con params " . json_encode($param));

			$resultados = $this->ejecutarConsulta($sql, "", $param);

			if ($resultados === false) {
				http_response_code(500);
				echo json_encode(['error' => 'No se pudo actualizar el servicio']);
				return;
			}

			// === 4. Registrar en historial_servicios ===
			$logEstado = [
				['campo_nombre' => 'id_servicio', 'campo_marcador' => ':id_servicio', 'campo_valor' => $id_servicio],
				['campo_nombre' => 'id_cliente', 'campo_marcador' => ':id_cliente', 'campo_valor' => $id_cliente],
				['campo_nombre' => 'id_truck', 'campo_marcador' => ':id_truck', 'campo_valor' => $id_truck],
				['campo_nombre' => 'campo_afectado', 'campo_marcador' => ':campo_afectado', 'campo_valor' => 'estado_servicio'],
				['campo_nombre' => 'valor_anterior', 'campo_marcador' => ':valor_anterior', 'campo_valor' => $estado_actual],
				['campo_nombre' => 'valor_nuevo', 'campo_marcador' => ':valor_nuevo', 'campo_valor' => $estado],
				['campo_nombre' => 'origen', 'campo_marcador' => ':origen', 'campo_valor' => 'manual'],
				['campo_nombre' => 'usuario_nombre', 'campo_marcador' => ':usuario_nombre', 'campo_valor' => $_SESSION['nombre'] ?? 'Usuario Web'],
				['campo_nombre' => 'ip_origen', 'campo_marcador' => ':ip_origen', 'campo_valor' => $_SERVER['REMOTE_ADDR']],
				['campo_nombre' => 'fecha_servicio', 'campo_marcador' => ':fecha_servicio', 'campo_valor' => date('Y-m-d')]
			];

			$this->log("Arreglo para crear Historico " . print_r($logEstado, true));

			$this->guardarDatos('historial_servicios', $logEstado);

			// === 5. Registrar nota si existe ===
			if (!empty($notas)) {
				$logNota = [
					['campo_nombre' => 'id_servicio', 'campo_marcador' => ':id_servicio_n', 'campo_valor' => $id_servicio],
					['campo_nombre' => 'id_cliente', 'campo_marcador' => ':id_cliente_n', 'campo_valor' => $id_cliente],
					['campo_nombre' => 'id_truck', 'campo_marcador' => ':id_truck_n', 'campo_valor' => $id_truck],
					['campo_nombre' => 'campo_afectado', 'campo_marcador' => ':campo_afectado_n', 'campo_valor' => 'notas'],
					['campo_nombre' => 'valor_anterior', 'campo_marcador' => ':valor_anterior_n', 'campo_valor' => ''],
					['campo_nombre' => 'valor_nuevo', 'campo_marcador' => ':valor_nuevo_n', 'campo_valor' => $notas],
					['campo_nombre' => 'origen', 'campo_marcador' => ':origen_n', 'campo_valor' => 'manual'],
					['campo_nombre' => 'usuario_nombre', 'campo_marcador' => ':usuario_nombre_n', 'campo_valor' => $_SESSION['nombre'] ?? 'Usuario Web'],
					['campo_nombre' => 'ip_origen', 'campo_marcador' => ':ip_origen_n', 'campo_valor' => $_SERVER['REMOTE_ADDR']],
					['campo_nombre' => 'fecha_servicio', 'campo_marcador' => ':fecha_servicio_n', 'campo_valor' => date('Y-m-d')]
				];
				$this->guardarDatos('historial_servicios', $logNota);
			}

			http_response_code(200);
			echo json_encode(['success' => true, 'message' => 'Estado y historial actualizados']);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en actualizarEstadoConHistorial: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'No se pudo actualizar']);
		}
	}

	private function validarCliente($nombre_cliente)
	{
		$query = "SELECT id_cliente FROM clientes WHERE nombre = :nombre AND id_status = 1";
		$params = [':nombre' => trim($nombre_cliente)];
		$result = $this->ejecutarConsulta($query, '', $params);
		return $result ? $result['id_cliente'] : false;
	}

	private function validarTruck($truck): mixed
	{
		$query = "SELECT id_truck FROM truck WHERE nombre = :nombre AND id_status = 26";
		$params = [':nombre' => $truck];
		$result = $this->ejecutarConsulta($query, '', $params);
		return $result ? $result['id_truck'] : false;
	}

	/**
	 * Actualiza el estado de un servicio desde el modal
	 * @param int $id_servicio
	 * @param string $estado (finalizado, replanificado, cancelado)
	 * @param int $id_cliente (para actualizar el servicio)
	 * @return void (echo JSON)
	 */

	public function actualizarEstadoServicio($id_servicio, $estado, $id_cliente)
	{
		try {
			$this->log("=== ACTUALIZANDO ESTADO: Servicio $id_servicio a $estado ===");

			// Validar estado permitido
			$estados_validos = ['finalizado', 'replanificado', 'cancelado'];
			if (!in_array($estado, $estados_validos)) {
				http_response_code(400);
				echo json_encode(['error' => 'Estado no válido']);
				return;
			}

			// Determinar id_status
			$id_status = 37; // Activo por defecto
			if ($estado === 'finalizado') {
				$id_status = 37;
			} elseif ($estado === 'replanificado') {
				$id_status = 38; // Asumiendo que existe
			} elseif ($estado === 'cancelado') {
				$id_status = 47;
			}

			// Actualizar servicio
			$sql = "UPDATE servicios 
				SET estado_visita = :v_estado_visita, id_status = :v_id_status, finalizado = CASE WHEN :v_estado_visita = 'finalizado' THEN 1 ELSE 0 END 
				WHERE id_servicio = :v_id_servicio";

			$param = [
				':v_estado_visita' => $estado,
				':v_id_status' => $id_status,
				':v_id_servicio' => $id_servicio
			];

			$this->log("Actualización SQL: $sql con params " . json_encode($param));

			// Ejecución y post-procesamiento
			$resultados = $this->ejecutarConsulta($sql, "", $param);

			$this->log("Estado actualizado: Servicio $id_servicio -> $estado");

			http_response_code(200);
			echo json_encode(['success' => true, 'message' => 'Estado actualizado']);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en actualizarEstadoServicio: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'No se pudo actualizar el estado']);
		}
	}

	public function obtenerServicioDetalle($id_servicio)
	{
		try {
			$this->log("Inicio de Consulta para obtener el detalle del Servicio");
			$query = "SELECT 
						s.id_servicio,
						s.id_cliente,
						c.nombre as cliente,
						s.id_truck,
						t.nombre as truck,
						s.estado_servicio,
						s.estado_visita,
						s.finalizado,
						s.dia_servicio,
						d.lat,
						d.lng,
						d.direccion,
						t.color AS crew_color_principal,
						s.fecha_programada,
						s.hora_aviso_usuario,
						s.hora_finalizado,
						c.historial
					FROM servicios AS s
					LEFT JOIN clientes AS c ON s.id_cliente = c.id_cliente
					LEFT JOIN truck AS t ON s.id_truck = t.id_truck
					LEFT JOIN direcciones AS d ON s.id_direccion = d.id_direccion
					WHERE s.id_servicio = :id_servicio";

			$params = [':id_servicio' => $id_servicio];
			$result = $this->ejecutarConsulta($query, '', $params);

			$this->log("Resultado de la consulta de detalle: " . print_r($result, true));

			if (!$result) {
				http_response_code(404);
				echo json_encode(['error' => 'Servicio no encontrado']);
				return;
			}

			// Añadir crew_integrantes
			$result['crew_integrantes'] = $this->obtenerCrewIntegrantes($id_servicio);

			// Añadir notas previas del servicio
			$result['notas_anteriores'] = trim($this->obtenerNotasHist($id_servicio));

			// === Asegurar que los campos temporales estén presentes ===
			// $result['hora_aviso_usuario'] = $result['hora_aviso_usuario'] ?? null;
			// $result['hora_finalizado'] = $result['hora_finalizado'] ?? null;

			$this->log("Resultado de la consulta de detalle con Crew: " . print_r($result, true));

			http_response_code(200);
			echo json_encode($result);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en obtenerServicioDetalle: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'Error al cargar servicio']);
		}
	}

	private function obtenerNotasHist($id_servicio)
	{
		try {
			$query = "SELECT 
						h.valor_nuevo
					FROM historial_servicios AS h
					WHERE h.id_servicio = :id_servicio AND
						h.campo_afectado = 'notas'";

			$params = [':id_servicio' => $id_servicio];
			$result = $this->ejecutarConsulta($query, '', $params);

			if (!$result) {
				return '';
			}

			return $result['valor_nuevo'];

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en obtenerCrewIntegrantes: " . $e->getMessage(), true);
			return '';
		}

	}

	private function obtenerCrewIntegrantes($id_servicio)
	{
		try {
			$query = "SELECT 
						s.id_crew_1, s.id_crew_2, s.id_crew_3, s.id_crew_4
					FROM servicios AS s
					WHERE s.id_servicio = :id_servicio";

			$params = [':id_servicio' => $id_servicio];
			$result = $this->ejecutarConsulta($query, '', $params);

			if (!$result) {
				return [];
			}

			$ids = [
				$result['id_crew_1'],
				$result['id_crew_2'],
				$result['id_crew_3'],
				$result['id_crew_4']
			];

			$integrantes = [];
			foreach ($ids as $id) {
				if ($id) {
					$query_crew = "SELECT id_crew, nombre, apellido, nombre_completo, color, crew as responsabilidad
						FROM crew 
						WHERE id_crew = :id AND id_status = 32";
					$params_crew = [':id' => $id];
					$crew_data = $this->ejecutarConsulta($query_crew, '', $params_crew);
					if ($crew_data) {
						$integrantes[] = $crew_data;
					}
				}
			}

			return $integrantes;

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en obtenerCrewIntegrantes: " . $e->getMessage(), true);
			return [];
		}
	}

	public function obtenerHistorialServicio($id_cliente)
	{
		try {
			$query = '
				SELECT 
					s.id_servicio,
					s.id_cliente,
					s.id_truck,
					s.fecha_programada,
					t.nombre AS truck,
					s.estado_visita,
					s.estado_servicio,
					s.finalizado,
					h.campo_afectado,
					CASE 
						WHEN inicio.fecha_registro IS NOT NULL 
							AND fin.fecha_registro IS NOT NULL 
						THEN TIMEDIFF(fin.fecha_registro, inicio.fecha_registro)
						ELSE NULL 
					END AS tiempo_duracion
				FROM servicios AS s
				LEFT JOIN truck AS t ON s.id_truck = t.id_truck
				LEFT JOIN historial_servicios AS h ON s.id_servicio = h.id_servicio
				LEFT JOIN (
					SELECT id_servicio, MIN(fecha_registro) AS fecha_registro 
					FROM historial_servicios 
					WHERE campo_afectado = "estado_servicio" 
					AND valor_nuevo = "usuario_alerto"
					AND origen = "manual"
					GROUP BY id_servicio
				) AS inicio ON s.id_servicio = inicio.id_servicio
				LEFT JOIN (
					SELECT id_servicio, MIN(fecha_registro) AS fecha_registro 
					FROM historial_servicios 
					WHERE campo_afectado = "estado_servicio" 
					AND valor_nuevo = "finalizado"
					AND origen = "manual"
					GROUP BY id_servicio
				) AS fin ON s.id_servicio = fin.id_servicio
				WHERE s.id_status != 47
					AND s.id_cliente = :v_id_cliente
					AND DATE(s.fecha_programada) < :v_fecha_programada
					ORDER BY s.fecha_programada DESC';

			$params = [
				':v_fecha_programada' => date('Y-m-d'),
				':v_id_cliente' => $id_cliente
			];
			$result = $this->ejecutarConsulta($query, '', $params, 'fetchAll');

			$this->log(message: "Resultado de la consulta de historial: " . print_r($result, true));

			if (!$result) {
				http_response_code(404);
				echo json_encode(['error' => 'Historial no encontrado']);
				return;
			}

			http_response_code(200);
			echo json_encode($result);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en obtenerHistorialServicio: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'Error al cargar historial']);
		}
	}

	public function procesarClientesDesdeMotor1($datos)
	{
		$id_cliente = $datos['id_cliente'];
		$nombre_cliente = $datos['nombre_cliente'];
		$telefono_cliente = $datos['telefono_cliente'];
		$email_cliente = $datos['email_cliente'];
		$direccion_cliente = $datos['direccion_cliente'];
		$latitud_cliente = $this->normalizarCoordenada($datos['latitud'], "Latitud");
		$longitud_cliente = $this->normalizarCoordenada($datos['longitud'], "Longitud");

		$this->log("Latitud normalizada: " . $latitud_cliente);
		$this->log("Longitud normalizada: " . $longitud_cliente);

		$this->log("Procesando cliente desde Motor1: " . print_r($datos, true));

		if (empty($id_cliente) || empty($nombre_cliente)) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Faltan datos']);
			return;
		}
		$query = "SELECT * FROM clientes WHERE nombre = :v_nombre";
		$params = [
			':v_nombre' => $nombre_cliente
		];
		try {
			$resulta = $this->ejecutarConsulta($query, "", $params);
			if ($resulta) {
				$query1 = "SELECT * FROM direcciones WHERE id_cliente = :v_id_cliente";
				$params = [
					':v_id_cliente' => $resulta['id_cliente']
				];
				$respuesta = $this->ejecutarConsulta($query1, "", $params);
				if ($respuesta) {
					if ($respuesta['direccion'] == "" || is_null($respuesta['direccion'])) {
						if ($direccion_cliente != "" && !is_null($direccion_cliente)) {
							$query3 = "UPDATE direcciones SET direccion = :v_direccion WHERE id_cliente = :v_id_cliente";
							$params = [
								':v_direccion' => $direccion_cliente,
								':v_id_cliente' => $resulta['id_cliente']
							];
							$respuesta3 = $this->ejecutarConsulta($query3, "", $params);
							return [
								'status' => 'ok',
								'id_cliente' => $resulta['id_cliente'],
								'message' => 'Direccion de Cliente actualizada'
							];
						} else {
							return [
								'status' => 'ok',
								'id_cliente' => $resulta['id_cliente'],
								'message' => 'No hubo actualizacion'
							];
						}
					} else {
						return [
							'status' => 'ok',
							'id_cliente' => $resulta['id_cliente'],
							'message' => 'No hubo actualizacion'
						];
					}
				}
			} else {
				$datos = [
					['campo_nombre' => 'nombre', 'campo_marcador' => ':id_cliente', 'campo_valor' => $nombre_cliente],
					['campo_nombre' => 'telefono', 'campo_marcador' => ':direccion', 'campo_valor' => $telefono_cliente],
					['campo_nombre' => 'email', 'campo_marcador' => ':lat', 'campo_valor' => $email_cliente],
					['campo_nombre' => 'id_status', 'campo_marcador' => ':lng', 'campo_valor' => 1],
					['campo_nombre' => 'historial', 'campo_marcador' => ':historial', 'campo_valor' => 0],
					['campo_nombre' => 'tiene_historial', 'campo_marcador' => ':tiene_historial', 'campo_valor' => 0]
				];

				$id_cliente_new = $this->guardarDatos('clientes', $datos);

				if ($id_cliente_new > 0) {
					error_log("Nuevo Codigo: $id_cliente_new");

					$datos = [
						['campo_nombre' => 'id_cliente', 'campo_marcador' => ':id_cliente', 'campo_valor' => $id_cliente_new],
						['campo_nombre' => 'direccion', 'campo_marcador' => ':direccion', 'campo_valor' => $direccion_cliente],
						['campo_nombre' => 'lat', 'campo_marcador' => ':lat', 'campo_valor' => $latitud_cliente],
						['campo_nombre' => 'lng', 'campo_marcador' => ':lng', 'campo_valor' => $longitud_cliente]
					];

					$id_direccion = $this->guardarDatos('direcciones', $datos);

					return [
						'status' => 'ok',
						'id_cliente' => $id_cliente_new,
						'message' => 'Cliente y Direccion creados'
					];
				} else {
					$this->logWithBacktrace("Error crítico en Nuevo Clientes: " . print_r($id_cliente_new, true), true);
					return ['error' => 'No se pudo crear al Cliente'];
				}
			}
		} catch (Exception $e) {
			$this->logWithBacktrace("Error crítico en Clientes Motor1: " . $e->getMessage(), true);
			return ['error' => 'Error en la consulta (Nombre)'];
		}
	}

	function normalizarCoordenada($coord_str, $nombre_campo = "Coordenada")
	{
		// 1. Trim: Eliminar espacios iniciales/finales
		$coord_str = trim($coord_str);

		// 2. Verificar si está vacío
		if ($coord_str === '') {
			error_log("Advertencia: $nombre_campo está vacía.");
			return null; // o lanzar una excepción si es requerida
		}

		// 3. Reemplazar coma por punto
		// Esta es la parte crucial que resuelve tu problema de localización.
		$coord_normalizada_str = str_replace(',', '.', $coord_str);

		// 4. Verificar si es numérica después del reemplazo
		if (!is_numeric($coord_normalizada_str)) {
			error_log("Error: $nombre_campo '$coord_str' no es un número válido después de normalizar.");
			// O lanzar una excepción
			// throw new InvalidArgumentException("$nombre_campo '$coord_str' no es un número válido.");
			return null;
		}

		// 5. Convertir a float
		$coord_float = (float) $coord_normalizada_str;

		// 6. (Opcional) Validaciones de rango básicas
		// Latitud válida: -90 a 90
		// Longitud válida: -180 a 180
		// Puedes agregar estas validaciones aquí si es necesario.

		return $coord_float;
	}

	public function buscar_actualizacion($ultimo_tiempo)
	{
		try {
			$query = "
				SELECT 
					s.*,
					c.nombre AS cliente,
					cr1.nombre AS crew_1_nombre, cr1.color AS crew_1_color,
					cr2.nombre AS crew_2_nombre, cr2.color AS crew_2_color,
					cr3.nombre AS crew_3_nombre, cr3.color AS crew_3_color,
					cr4.nombre AS crew_4_nombre, cr4.color AS crew_4_color

				FROM servicios s
				JOIN clientes c ON s.id_cliente = c.id_cliente
				LEFT JOIN crew cr1 ON s.id_crew_1 = cr1.id_crew
				LEFT JOIN crew cr2 ON s.id_crew_2 = cr2.id_crew
				LEFT JOIN crew cr3 ON s.id_crew_3 = cr3.id_crew
				LEFT JOIN crew cr4 ON s.id_crew_4 = cr4.id_crew

				WHERE
				    DATE(s.fecha_programada) = CURDATE() 				 
				 	AND s.id_status != $this->id_status_historico
					AND (
						s.fecha_actualizacion > :v_ultimo_tiempo1
						OR s.hora_aviso_usuario > :v_ultimo_tiempo2
						OR s.hora_finalizado > :v_ultimo_tiempo3
					)
				ORDER BY s.id_servicio, s.fecha_actualizacion DESC;";

			$params = [
				':v_ultimo_tiempo1' => $ultimo_tiempo,
				':v_ultimo_tiempo2' => $ultimo_tiempo,
				':v_ultimo_tiempo3' => $ultimo_tiempo,
			];

			$servicios = $this->ejecutarConsulta($query, '', $params, 'fetchAll');

			// === Asegurar que $servicios sea un array ===
			if (!$servicios) {
				$servicios = [];
			}

			// === Procesar crew_integrantes (solo si hay datos) ===
			foreach ($servicios as &$servicio) {
				$integrantes = [];
				if (!empty($servicio['crew_1_nombre'])) {
					$integrantes[] = [
						'nombre' => $servicio['crew_1_nombre'],
						'color' => $servicio['crew_1_color'] ?? '#666'
					];
				}
				if (!empty($servicio['crew_2_nombre'])) {
					$integrantes[] = [
						'nombre' => $servicio['crew_2_nombre'],
						'color' => $servicio['crew_2_color'] ?? '#666'
					];
				}
				if (!empty($servicio['crew_3_nombre'])) {
					$integrantes[] = [
						'nombre' => $servicio['crew_3_nombre'],
						'color' => $servicio['crew_3_color'] ?? '#666'
					];
				}
				if (!empty($servicio['crew_4_nombre'])) {
					$integrantes[] = [
						'nombre' => $servicio['crew_4_nombre'],
						'color' => $servicio['crew_4_color'] ?? '#666'
					];
				}
				$servicio['crew_integrantes'] = $integrantes;
			}

			// === Siempre devolver un array ===
			http_response_code(200);
			echo json_encode(array_values($servicios)); // array_values por seguridad

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en obtenerHistorialServicio: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'Error al cargar historial']);
		}

	}

	public function obtener_direcciones($cantidad)
	{
		$params = [];
		$inicio = 1;
		$registros = $cantidad;

		$query = "
			SELECT 
				id_direccion, 
				direccion
			FROM direcciones 
			WHERE 
				AND direccion IS NOT NULL 
				AND direccion != ''
			ORDER BY id_direccion LIMIT " . $inicio . ", " . $registros;

		try {
			$resultado = $this->ejecutarConsulta($query, '', $params, 'fetchAll');
			return $resultado;

		} catch (Exception $e) {
			$this->logWithBacktrace("Error crítico en Direcciones Motor1: " . $e->getMessage(), true);
			return [];
		}

	}

	public function act_lat_long_direcciones($id_servicio, $lat, $lon)
	{
		$update = "UPDATE direcciones SET lat = :v_lat, lng = :v_lng 
			WHERE id_servicio = :v_id_servicio";

		$params = [
			':v_id_servicio' => $id_servicio,
			':v_lat' => $lat,
			':v_lng' => $lon
		];

		try {
			$resultado = $this->ejecutarConsulta($update, '', $params);
			return $resultado;

		} catch (Exception $e) {
			$this->logWithBacktrace("Error crítico al actualizar Direcciones Motor1: " . $e->getMessage(), true);
			return [];
		}

	}
	/**
	 * Actualiza hora_inicio_gps, hora_fin_gps y tiempo_servicio para un servicio
	 * Espera: id_servicio, hora_inicio_gps, hora_fin_gps, tiempo_servicio
	 * Llamado desde AJAX (modulo_servicios: 'actualizar_hora_gps')
	 */
	public function actualizarHoraGps($inputData)
	{
		try {
			$id_servicio = $inputData['id_servicio'] ?? null;
			$hora_inicio_gps = $inputData['hora_inicio_gps'] ?? null;
			$hora_fin_gps = $inputData['hora_fin_gps'] ?? null;
			$tiempo_servicio = $inputData['tiempo_servicio'] ?? null;

			if (!$id_servicio) {
				http_response_code(400);
				echo json_encode(['error' => 'id_servicio es requerido']);
				return;
			}

			// Construir array de campos para actualizar
			$datos = [];
			if ($hora_inicio_gps !== null) {
				$datos[] = [
					'campo_nombre' => 'hora_inicio_gps',
					'campo_marcador' => ':hora_inicio_gps',
					'campo_valor' => $hora_inicio_gps
				];
			}
			if ($hora_fin_gps !== null) {
				$datos[] = [
					'campo_nombre' => 'hora_fin_gps',
					'campo_marcador' => ':hora_fin_gps',
					'campo_valor' => $hora_fin_gps
				];
			}
			if ($tiempo_servicio !== null) {
				$datos[] = [
					'campo_nombre' => 'tiempo_servicio',
					'campo_marcador' => ':tiempo_servicio',
					'campo_valor' => $tiempo_servicio
				];
			}

			if (empty($datos)) {
				http_response_code(400);
				echo json_encode(['error' => 'No hay campos para actualizar']);
				return;
			}

			// Condición para el WHERE
			$condicion = [
				'condicion_campo' => 'id_servicio',
				'condicion_marcador' => ':id_servicio',
				'condicion_valor' => $id_servicio
			];

			$resultado = $this->actualizarDatos('servicios', $datos, $condicion);

			if ($resultado === true) {
				http_response_code(200);
				echo json_encode(['success' => true, 'message' => 'Datos GPS actualizados']);
			} else {
				http_response_code(500);
				echo json_encode(['error' => 'No se pudo actualizar', 'detalle' => $resultado]);
			}
		} catch (Exception $e) {
			$this->logWithBacktrace('Error en actualizarHoraGps: ' . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'Error crítico al actualizar GPS']);
		}
	}

	/**
	 * Actualiza solo la hora de inicio GPS para un servicio
	 * Espera: id_servicio, hora_inicio_gps
	 */
	public function actualizarHoraInicioGps($inputData)
	{
		try {
			$id_servicio = $inputData['id_servicio'] ?? null;
			$hora_inicio_gps = $inputData['hora_inicio_gps'] ?? null;

			if (!$id_servicio || $hora_inicio_gps === null) {
				http_response_code(400);
				echo json_encode(['error' => 'id_servicio y hora_inicio_gps son requeridos']);
				return;
			}

			$datos = [
				[
					'campo_nombre' => 'hora_inicio_gps',
					'campo_marcador' => ':hora_inicio_gps',
					'campo_valor' => $hora_inicio_gps
				]
			];
			$condicion = [
				'condicion_campo' => 'id_servicio',
				'condicion_marcador' => ':id_servicio',
				'condicion_valor' => $id_servicio
			];
			$resultado = $this->actualizarDatos('servicios', $datos, $condicion);
			if ($resultado === true) {
				http_response_code(200);
				echo json_encode(['success' => true, 'message' => 'Hora de inicio GPS actualizada']);
			} else {
				http_response_code(500);
				echo json_encode(['error' => 'No se pudo actualizar', 'detalle' => $resultado]);
			}
		} catch (Exception $e) {
			$this->logWithBacktrace('Error en actualizarHoraInicioGps: ' . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'Error crítico al actualizar hora de inicio GPS']);
		}
	}

	public function obtenerVehiclesPorFecha($fecha_ctrl)
	{
		$query = "SELECT DISTINCT
					t.id_truck,  t.nombre as vehicle_id, COUNT(s.id_servicio) AS total_servicios, t.color
				FROM truck AS t
				INNER JOIN servicios AS s ON t.id_truck = s.id_truck 
					AND DATE(s.fecha_programada) = :v_fecha_ctrl 
					AND s.id_status != 39
				WHERE t.id_status = 26
				GROUP BY t.id_truck, t.nombre
				ORDER BY t.nombre";

		$parems = [
			':v_fecha_ctrl' => $fecha_ctrl
		];

		try {
			$resultado = $this->ejecutarConsulta($query, '', $parems, 'fetchAll');
			$trucks = array_map(function ($row) {
				return [
					'vehicle_id' => $row['vehicle_id'],
					'color' => $row['color'] // Asegúrate de que este campo no sea null
				];
			}, $resultado);

			echo json_encode(['trucks' => $trucks]);

			//return $trucks;

		} catch (Exception $e) {
			$this->logWithBacktrace("Error crítico en obtenerVehiclesPorFecha: " . $e->getMessage(), true);
			echo json_encode(['trucks' => []]);
		}
	}

	/**
	 * Actualiza la hora de fin GPS y el tiempo de servicio para un servicio
	 * Espera: id_servicio, hora_fin_gps, tiempo_servicio
	 */
	public function actualizarHoraFinGps($inputData)
	{
		try {
			$id_servicio = $inputData['id_servicio'] ?? null;
			$hora_fin_gps = $inputData['hora_fin_gps'] ?? null;
			$tiempo_servicio = $inputData['tiempo_servicio'] ?? null;

			if (!$id_servicio || $hora_fin_gps === null || $tiempo_servicio === null) {
				http_response_code(400);
				echo json_encode(['error' => 'id_servicio, hora_fin_gps y tiempo_servicio son requeridos']);
				return;
			}

			$datos = [
				[
					'campo_nombre' => 'hora_fin_gps',
					'campo_marcador' => ':hora_fin_gps',
					'campo_valor' => $hora_fin_gps
				],
				[
					'campo_nombre' => 'tiempo_servicio',
					'campo_marcador' => ':tiempo_servicio',
					'campo_valor' => $tiempo_servicio
				]
			];
			$condicion = [
				'condicion_campo' => 'id_servicio',
				'condicion_marcador' => ':id_servicio',
				'condicion_valor' => $id_servicio
			];
			$resultado = $this->actualizarDatos('servicios', $datos, $condicion);
			if ($resultado === true) {
				http_response_code(200);
				echo json_encode(['success' => true, 'message' => 'Hora de fin GPS y tiempo de servicio actualizados']);
			} else {
				http_response_code(500);
				echo json_encode(['error' => 'No se pudo actualizar', 'detalle' => $resultado]);
			}
		} catch (Exception $e) {
			$this->logWithBacktrace('Error en actualizarHoraFinGps: ' . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'Error crítico al actualizar hora de fin GPS']);
		}
	}

	public function actualizarServicio($id_servicio, $hora_inicio_gps, $hora_fin_gps, $tiempo_servicio)
	{
		$id_servicio = filter_var($id_servicio, FILTER_VALIDATE_INT);
		if (!$id_servicio || $id_servicio <= 0) {
			http_response_code(400);
			echo json_encode(['error' => 'ID de servicio inválido']);
			return;
		}

		$sql = "SELECT hora_inicio_gps, hora_fin_gps, tiempo_servicio 
			FROM servicios 
			WHERE id_servicio = :v_id_servicio";
		$param = [
			':v_id_servicio' => $id_servicio
		];

		$resultado = $this->ejecutarConsulta($sql, '', $param);
		$this->log("Procesando el Servicio: " . print_r($resultado, true));

		$message = '';
		$sql = '';
		$param = [];

		if ($hora_inicio_gps && !$hora_fin_gps && !$tiempo_servicio) {
			// Verificar si ya fue iniciado
			if (!empty($resultado['hora_inicio_gps'])) {
				http_response_code(204); // Conflict
				echo json_encode([
					'success' => false,
					'error' => 'El servicio ya fue iniciado anteriormente',
					'hora_inicio_existente' => $resultado['hora_inicio_gps']
				]);
				return;
			}

			$sql = "UPDATE servicios 
				SET hora_inicio_gps = :v_hora_inicio_gps
				WHERE id_servicio = :v_id_servicio";

			$param = [
				':v_hora_inicio_gps' => $hora_inicio_gps,
				':v_id_servicio' => $id_servicio
			];
			$message = 'Servicio iniciado';
		} elseif (!$hora_inicio_gps && $hora_fin_gps && $tiempo_servicio) {

			// Verificar si ya fue cerrado
			if (!empty($resultado['hora_fin_gps'])) {
				http_response_code(204); // Conflict
				echo json_encode([
					'success' => false,
					'error' => 'El servicio ya fue cerrado anteriormente',
					'hora_fin_existente' => $resultado['hora_fin_gps']
				]);
				return;
			}

			$sql = "UPDATE servicios 
				SET hora_fin_gps = :v_hora_fin_gps, tiempo_servicio = :v_tiempo_servicio
				WHERE id_servicio = :v_id_servicio";

			$param = [
				':v_hora_fin_gps' => $hora_fin_gps,
				':v_tiempo_servicio' => $tiempo_servicio,
				':v_id_servicio' => $id_servicio
			];
			$message = 'Servicio Finalizado';
		}

		try {
			$resultados = $this->ejecutarConsulta($sql, "", $param);
			http_response_code(200);
			echo json_encode(['success' => true, 'message' => $message]);
		} catch (Exception $e) {
			$this->logWithBacktrace("Error en ActualizarServicio: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'No se pudo actualizar el servicio']);
		}
	}

	public function reconciliarDatosHistoricos()
	{
		try {
			// === 1. Obtener servicios sin hora_inicio_gps (últimos 7 días) ===
			$sql_servicios = "
				SELECT 
					s.id_servicio,
					s.id_cliente,
					s.id_truck,
					s.fecha_programada,
					d.lat AS lat_cliente,
					d.lng AS lng_cliente,
					t.nombre AS vehicle_id
				FROM servicios s
				JOIN direcciones d ON s.id_cliente = d.id_cliente
				JOIN truck t ON s.id_truck = t.id_truck
				WHERE s.id_status != 39
					AND s.fecha_programada >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
					AND s.hora_inicio_gps IS NULL
					AND d.lat != 0
					AND d.lng != 0
				ORDER BY s.fecha_programada, t.nombre
			";

			$servicios = $this->ejecutarConsulta($sql_servicios, '', [], 'fetchAll');
			$this->log("Servicios a procesar para reconciliación: " . print_r($servicios, true));

			$procesados = 0;
			$actualizados = 0;

			foreach ($servicios as $servicio) {
				$procesados++;
				$vehicle_id = $servicio['vehicle_id'];
				$fecha = $servicio['fecha_programada'];

				// === 2. Obtener puntos GPS NO procesados ===
				$sql_puntos = "
					SELECT lat, lng, timestamp 
						FROM gps_tracker 
						WHERE vehicle_id = :v_vehicle_id 
							AND DATE(timestamp) = :v_fecha
							AND (procesado IS NULL OR procesado = 0)
						ORDER BY timestamp ASC
				";
				$param = [
					':v_vehicle_id' => $vehicle_id,
					':v_fecha' => $fecha
				];
				$puntos = $this->ejecutarConsulta($sql_puntos, '', $param, 'fetchAll');

				if (empty($puntos))
					continue;

				// === 3. Detectar inicio por geofencing ===
				$inicio = $this->detectarInicioConGeofencing(
					$puntos,
					$servicio['lat_cliente'],
					$servicio['lng_cliente']
				);

				if ($inicio) {
					// Actualizar servicio
					$exito = $this->actualizarInicioServicioBD(
						$servicio['id_servicio'],
						$inicio['timestamp'],
						$inicio['lat'],
						$inicio['lng']
					);

					if ($exito) {
						// Marcar puntos usados como procesados
						$this->marcarPuntosComoProcesados($inicio['puntos_usados']);
						$actualizados++;
					}
				}
			}
			// === 4. Ahora detectar paradas operativas con puntos RESTANTES ===
			$this->detectarParadasOperativasPostReconciliation();

			return [
				'success' => true,
				'message' => "✅ Reconciliación completa.\n$actualizados servicios actualizados de $procesados procesados.",
				'detalles' => compact('procesados', 'actualizados')
			];

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en reconciliación: " . $e->getMessage());
			return [
				'success' => false,
				'error' => 'Error interno al procesar la reconciliación.'
			];
		}
	}

	private function detectarParadasOperativasPostReconciliation()
	{
		$sql_vehiculos = "SELECT DISTINCT nombre 
								FROM truck
								ORDER BY nombre";
		$vehiculos = $this->ejecutarConsulta($sql_vehiculos, '', [], 'fetchAll');

		foreach ($vehiculos as $v) {
			$vehicle_id = $v['nombre'];
			$sql = "
				SELECT lat, lng, timestamp 
					FROM gps_tracker 
					WHERE vehicle_id = :v_vehicle_id 
						AND (procesado IS NULL OR procesado = 0)
					ORDER BY timestamp ASC
			";

			$param = [
				':v_vehicle_id' => $vehicle_id
			];
			$puntos = $this->ejecutarConsulta($sql, '', $param, 'fetchAll');
			error_log("Pocesando Vehiculos #: " . $vehicle_id);
			$this->registrarParadasDeVehiculo($vehicle_id, $puntos);
		}
	}

	private function calcularDistanciaMetros($lat1, $lng1, $lat2, $lng2)
	{
		$R = 6371e3;
		$φ1 = deg2rad($lat1);
		$φ2 = deg2rad($lat2);
		$Δφ = deg2rad($lat2 - $lat1);
		$Δλ = deg2rad($lng2 - $lng1);
		$a = sin($Δφ / 2) * sin($Δφ / 2) + cos($φ1) * cos($φ2) * sin($Δλ / 2) * sin($Δλ / 2);
		$c = 2 * atan2(sqrt($a), sqrt(1 - $a));
		return $R * $c;
	}

	private function obtenerPuntosGPS($vehicle_id, $fecha)
	{
		$sql = "
			SELECT lat, lng, timestamp 
				FROM gps_tracker 
				WHERE vehicle_id = :v_vehicle_id 
					AND (procesado IS NULL OR procesado = 0)
					AND DATE(timestamp) = :v_fecha 
				ORDER BY timestamp ASC";
		$param = [
			':v_vehicle_id' => $vehicle_id,
			'v_fecha' => $fecha
		];
		return $this->ejecutarConsulta($sql, '', $param, 'fetchAll');
	}

	private function marcarPuntosComoProcesados($puntos)
	{
		if (empty($puntos))
			return;

		$vehicle_id = $puntos[0]['vehicle_id'] ?? null;
		if (!$vehicle_id)
			return;

		// === Generar placeholders con nombre para cada timestamp ===
		$timestamp_placeholders = [];
		$params = [':v_vehicle_id' => $vehicle_id];

		foreach ($puntos as $i => $punto) {
			$placeholder = ":ts_$i";
			$timestamp_placeholders[] = $placeholder;
			$params[$placeholder] = $punto['timestamp']; // Asociar valor
		}

		// Unir los placeholders: :ts_0, :ts_1, :ts_2, ...
		$in_clause = implode(',', $timestamp_placeholders);

		// === Consulta SQL con marcadores nombrados ===
		$sql = "UPDATE gps_tracker 
				SET procesado = 1 
				WHERE vehicle_id = :v_vehicle_id 
				AND timestamp IN ($in_clause)";

		// === Ejecutar con mainModel ===
		try {
			$this->ejecutarConsulta($sql, "", $params);
			$this->log("✅ Marcados " . count($puntos) . " puntos como procesados para $vehicle_id");
		} catch (Exception $e) {
			$this->logWithBacktrace("❌ Error al marcar puntos como procesados: " . $e->getMessage(), true);
		}
	}

	private function detectarInicioConGeofencing($puntos, $lat_cliente, $lng_cliente)
	{

		$min_puntos_detencion = $this->umbral_minutos; // ~5 minutos si hay punto cada 30 seg
		$umbral_metros_loc = $this->umbral_metros; // 100 metros del cliente
		$min_segundos_detencion = $min_puntos_detencion * 60;

		$ventana_actual = [];
		$inicio_ventana = null;

		$total_puntos = count((array) $puntos);

		if ($total_puntos > 0) {
			foreach ($puntos as $i => $punto) {
				$lat = (float) $punto['lat'];
				$lng = (float) $punto['lng'];

				// Calcular distancia al cliente
				$distancia = $this->calcularDistanciaMetros($lat, $lng, $lat_cliente, $lng_cliente);

				if ($distancia <= $umbral_metros_loc) {
					// Agregar a ventana si está cerca
					$ventana_actual[] = $punto;
					if (!$inicio_ventana)
						$inicio_ventana = $punto['timestamp'];

					// Verificar si hay suficiente tiempo acumulado
					$primer_punto = $ventana_actual[0];
					$t_inicio = new \DateTime($primer_punto['timestamp']);
					$t_fin = new \DateTime($punto['timestamp']);
					$segundos_transcurridos = $t_inicio->diff($t_fin, true)->s;

					if ($segundos_transcurridos >= $min_segundos_detencion) {
						// Calcular velocidad media en este bloque
						$velocidad_media = $this->calcularVelocidadMedia($ventana_actual);

						// Si fue efectivamente detenido
						if ($velocidad_media < 0.5) { // < 0.5 m/s → considerado detenido
							return [
								'timestamp' => $inicio_ventana,
								'lat' => $lat,
								'lng' => $lng,
								'puntos_usados' => $ventana_actual
							];
						}
					}

				} else {
					// Fuera del radio → reiniciar ventana
					$ventana_actual = [];
					$inicio_ventana = null;
				}
			}
		}
		return null;
	}

	private function calcularVelocidadMedia($bloquePuntos)
	{
		if (count($bloquePuntos) < 2)
			return INF; // No hay movimiento

		$distanciaTotal = 0;
		$tiempoTotalSeg = 0;

		for ($i = 1; $i < count($bloquePuntos); $i++) {
			$p1 = $bloquePuntos[$i - 1];
			$p2 = $bloquePuntos[$i];

			$dist = $this->calcularDistanciaMetros(
				$p1['lat'],
				$p1['lng'],
				$p2['lat'],
				$p2['lng']
			);

			$t1 = new \DateTime($p1['timestamp']);
			$t2 = new \DateTime($p2['timestamp']);
			$segundos = $t1->diff($t2, true)->s;

			$distanciaTotal += $dist;
			$tiempoTotalSeg += $segundos;
		}

		return $tiempoTotalSeg > 0 ? $distanciaTotal / $tiempoTotalSeg : 0; // m/s
	}

	private function estaDetenido($punto1, $punto2)
	{
		$distancia = $this->calcularDistanciaMetros(
			$punto1['lat'],
			$punto1['lng'],
			$punto2['lat'],
			$punto2['lng']
		);
		return $distancia <= 10; // ≤ 10 metros = considerado detenido
	}

	private function actualizarInicioServicioBD($id_servicio, $hora_inicio, $lat, $lng)
	{
		$sql = "UPDATE servicios SET 
                hora_inicio_gps = :hora_inicio
            WHERE id_servicio = :id_servicio";

		$param = [
			':hora_inicio' => $hora_inicio,
			':id_servicio' => $id_servicio
		];

		return $this->ejecutarConsulta($sql, '', $param);
	}

	private function detectarParadasOperativas()
	{
		$sql_vehiculos = "SELECT DISTINCT nombre FROM truck";
		$vehiculos = $this->ejecutarConsulta($sql_vehiculos);

		foreach ($vehiculos as $v) {
			$vehicle_id = $v['nombre'];
			$puntos = $this->obtenerPuntosGPS($vehicle_id, date('Y-m-d', strtotime('-1 day')));
			$this->registrarParadasDeVehiculo($vehicle_id, $puntos);
		}
	}

	private function registrarParadasDeVehiculo($vehicle_id, $puntos)
	{
		$detencion_continua = 0;
		$inicio_parada = null;
		$lat_inicial = null;
		$lng_inicial = null;
		$ultimo_punto_detenido = null;

		$total_puntos = 0;
		$total_puntos = count((array) $puntos);

		$van = 0;
		if ($total_puntos > 0) {
			for ($i = 0; $i < $total_puntos; $i++) {
				$punto_actual = $puntos[$i];
				$lat = (float) $punto_actual['lat'];
				$lng = (float) $punto_actual['lng'];

				$detenido = false;
				if ($i > 0) {
					$dist = $this->calcularDistanciaMetros(
						$lat,
						$lng,
						(float) $puntos[$i - 1]['lat'],
						(float) $puntos[$i - 1]['lng']
					);
					$detenido = $dist <= 5;
				}

				if ($detenido) {
					$detencion_continua++;
					if (!$inicio_parada) {
						$inicio_parada = $punto_actual['timestamp'];
						$lat_inicial = $lat;
						$lng_inicial = $lng;
					}
					$ultimo_punto_detenido = $punto_actual;
				} else {
					// Fin de detención: verificar si fue una parada válida
					if ($inicio_parada && $detencion_continua >= 10) { // ~5 min
						// Buscar el primer punto NO detenido después (para lat/lon final)
						$lat_final = $lat;
						$lng_final = $lng;
						$hora_fin_real = $punto_actual['timestamp'];

						// Verificar que no esté cerca de cliente
						if (!$this->estuvoCercaDeCliente($vehicle_id, $inicio_parada, $lat_inicial, $lng_inicial)) {
							$id_parada = $this->guardarParadaOperativa(
								$vehicle_id,
								$inicio_parada,
								$hora_fin_real,
								$lat_inicial,
								$lng_inicial
							);

							if ($id_parada && $lat_final !== $lat_inicial && $lng_final !== $lng_inicial) {
								$this->actualizarParadaConMovimiento($id_parada, $lat_final, $lng_final, $hora_fin_real);
								$this->log("✅ Parada ID=$id_parada actualizada con coordenadas finales desde movimiento");
							}
						}
					}

					// Resetear
					$detencion_continua = 0;
					$inicio_parada = null;
					$lat_inicial = null;
					$lng_inicial = null;
					$ultimo_punto_detenido = null;
				}

				$van++;
			}
		}

		// Caso: termina en detención
		if ($inicio_parada && $detencion_continua >= 10) {
			if (!$this->estuvoCercaDeCliente($vehicle_id, $inicio_parada, $lat_inicial, $lng_inicial)) {
				// Usar el último punto detenido como final también
				$this->guardarParadaOperativa(
					$vehicle_id,
					$inicio_parada,
					$ultimo_punto_detenido['timestamp'], // Último punto estático
					$lat_inicial,
					$lng_inicial
				);
				$this->log("⚠️ Parada final sin movimiento posterior registrada sin lat_final exacta");
			}
		}
	}

	private function estuvoCercaDeCliente($vehicle_id, $timestamp, $lat, $lng)
	{
		$fecha = substr($timestamp, 0, 10);
		$sql = "
				SELECT d.lat, d.lng 
					FROM servicios s
					JOIN direcciones d ON s.id_cliente = d.id_cliente
					JOIN truck t ON s.id_truck = t.id_truck
					WHERE t.nombre = :v_nombre AND s.fecha_programada = :v_fecha
			";
		$param = [
			':v_nombre' => $vehicle_id,
			':v_fecha' => $fecha
		];

		$clientes = $this->ejecutarConsulta($sql, '', $param, 'fetchAll');

		foreach ($clientes as $cliente) {
			$lat2 = (float) $cliente['lat'];
			$lng2 = (float) $cliente['lng'];
			$dist = $this->calcularDistanciaMetros($lat, $lng, $lat2, $lng2);

			if ($dist <= 150)
				return true;
		}
		return false;
	}

	private function guardarParadaOperativa($vehicle_id, $inicio, $fin, $lat, $lng)
	{
		$sql_check = "
			SELECT COUNT(*) 
				FROM paradas_operativas 
				WHERE vehicle_id = :v_vehicle_id AND hora_inicio = :v_hora_inicio";

		$param = [
			':v_vehicle_id' => $vehicle_id,
			':v_hora_inicio' => $inicio
		];

		$existe = $this->ejecutarConsulta($sql_check, '', $param);

		if ($existe['COUNT(*)'] > 0)
			return;

		$id_truck = $this->id_Truck($vehicle_id);

		$datos = [
			['campo_nombre' => 'id_truck', 'campo_marcador' => ':id_truck', 'campo_valor' => $id_truck],
			['campo_nombre' => 'fecha_operacion', 'campo_marcador' => ':fecha_operacion', 'campo_valor' => substr($inicio, 0, 10)],
			['campo_nombre' => 'vehicle_id', 'campo_marcador' => ':vehicle_id', 'campo_valor' => $vehicle_id],
			['campo_nombre' => 'hora_inicio', 'campo_marcador' => ':hora_inicio', 'campo_valor' => $inicio],
			['campo_nombre' => 'hora_fin', 'campo_marcador' => ':hora_fin', 'campo_valor' => $fin],
			['campo_nombre' => 'lat_inicial', 'campo_marcador' => ':lat_inicial', 'campo_valor' => (float) $lat],
			['campo_nombre' => 'lng_inicial', 'campo_marcador' => ':lng_inicial', 'campo_valor' => (float) $lng]
		];
		$id_registro = $this->guardarDatos('paradas_operativas', $datos);
		return $id_registro; // Devuelve el ID para futuras actualizaciones
	}

	private function id_Truck($nombre): mixed
	{
		$query = "SELECT id_truck 
			FROM truck 
			WHERE nombre = :nombre";
		$params = [
			':nombre' => $nombre
		];

		$result = $this->ejecutarConsulta($query, '', $params);
		return $result ? $result['id_truck'] : false;
	}


	private function actualizarParadaConMovimiento($id_parada, $lat_final, $lng_final, $hora_fin_real = null)
	{
		$datos = [
			['campo_nombre' => 'lat_final', 'campo_marcador' => ':lat_final', 'campo_valor' => (float) $lat_final],
			['campo_nombre' => 'lng_final', 'campo_marcador' => ':lng_final', 'campo_valor' => (float) $lng_final],
			['campo_nombre' => 'estado', 'campo_marcador' => ':estado', 'campo_valor' => 'cerrda']
		];

		// Opcional: actualizar hora_fin si fue más larga de lo detectado
		if ($hora_fin_real) {
			$datos[] = ['campo_nombre' => 'hora_fin', 'campo_marcador' => ':hora_fin', 'campo_valor' => $hora_fin_real];
		}

		$condicion = [
			'campo_nombre' => 'id_parada',
			'campo_marcador' => ':id_parada',
			'campo_valor' => (int) $id_parada
		];

		return $this->actualizarDatos('paradas_operativas', $datos, $condicion);
	}

	public function listar_activos($fecha)
	{
		$sql = "
			SELECT 
				s.id_servicio,
				s.id_cliente,
				s.id_truck,
				s.id_status,
				s.hora_inicio_gps,
				s.hora_fin_gps,
				s.estado_servicio,
				s.estado_visita,
				s.dia_servicio,
				s.tipo_dia,
				s.finalizado,
				
				s.id_crew_1,
				s.id_crew_2,
				s.id_crew_3,
				s.id_crew_4,
				 
				c.nombre AS cliente,
				
				d.lat,
				d.lng,
				d.direccion,
				
				t.nombre AS truck,
				t.color AS crew_color_principal
				
			FROM servicios s
			LEFT JOIN clientes c ON s.id_cliente = c.id_cliente
			LEFT JOIN direcciones d ON c.id_cliente = d.id_cliente
			LEFT JOIN truck t ON s.id_truck = t.id_truck
			
			WHERE s.fecha_programada = :v_fecha
			AND s.id_status != :v_id_status_historico
			AND s.id_truck IS NOT NULL
			AND d.lat IS NOT NULL
			AND d.lng IS NOT NULL
			
			ORDER BY t.nombre, s.dia_servicio";

		$param = [
			':v_fecha' => $fecha,
			':v_id_status_historico' => $this->id_status_historico
		];

		try {
			$servicios = $this->ejecutarConsulta($sql, '', $param, 'fetchAll');
			// === Ahora obtener crew_integrantes para cada servicio ===

			foreach ($servicios as $servicio) {
				$servicio['crew_integrantes'] = [];
				$ids_crew = [
					$servicio['id_crew_1'],
					$servicio['id_crew_2'],
					$servicio['id_crew_3'],
					$servicio['id_crew_4']
				];

				foreach ($ids_crew as $id) {
					if ($id) {
						$query_crew = "SELECT id_crew, nombre_completo, nombre, apellido, color, crew as responsabilidad
								FROM crew 
								WHERE id_crew = :id AND id_status = 32";
						$params_crew = [':id' => $id];
						$result = $this->ejecutarConsulta($query_crew, '', $params_crew);
						if ($result) {
							$servicio['crew_integrantes'][] = $result[0];
						}
					}
				}
			}
			unset($servicio);


			echo json_encode([
				'success' => true,
				'data' => array_values($servicios)
			]);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en listar_para_geoferencia: " . $e->getMessage());
			echo json_encode([
				'success' => false,
				'error' => 'Error al cargar servicios'
			]);
		}
		exit();

	}
}

?>