<?php

namespace app\controllers;

require_once APP_R_PROY . 'app/models/mainModel.php';
require_once APP_R_PROY . 'app/models/datosGenerales.php';
require_once APP_R_PROY . 'app/controllers/usuariosController.php';

use app\models\mainModel;
use app\models\datosGenerales;
use app\controllers\usuariosController;

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

	public function __construct()
	{
		parent::__construct();

		$nom_controlador = 'serviciosController';
		$this->log_path = APP_R_PROY . 'app/logs/servicios/';

		if (!file_exists($this->log_path)) {
			mkdir($this->log_path, 0775, true);
			@chgrp($this->log_path, 'www-data');
			@chmod($this->log_path, 0775);
		}

		$this->logFile = $this->log_path . $nom_controlador . '_' . date('Y-m-d') . '.log';
		$this->errorLogFile = $this->log_path . $nom_controlador . '_error_' . date('Y-m-d') . '.log';

		$this->verificarPermisos();
		$this->rotarLogs(15);

		$this->id_status_cancelado = 47;
		$this->id_status_finalizado = 38;
		$this->id_status_historico = 39;
		$this->id_status_activo = 37;
		$this->id_status_replanificado = 40;
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
		if (empty($file)) {
			// Si no hay archivo de log configurado, evitar fallos en ambiente de pruebas
			return;
		}
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
		if (empty($this->errorLogFile)) {
			return;
		}
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
				d.id_geofence, 
				d.lat, 
				d.lng, 
				t.nombre as truck
			FROM servicios AS s
			LEFT JOIN clientes AS c ON s.id_cliente = c.id_cliente
			LEFT JOIN direcciones AS d ON s.id_direccion = d.id_direccion
			LEFT JOIN truck AS t ON s.id_truck = t.id_truck
			WHERE s.id_status = 37
			AND s.id_servicio IN (
				SELECT DISTINCT id_servicio 
				FROM servicios 
				WHERE id_status = 37
			)
			-- Agrupar por servicio para evitar duplicados
			GROUP BY s.id_servicio
			ORDER BY 
				FIELD(t.id_truck, 1,2,3,4,5,6,11,15), 
				c.nombre";

			// queda pendiente colocar contrato: ct.day_work AS dia_servicio y LEFT JOIN contratos AS ct ON s.id_cliente = ct.id_cliente

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
					'id_geofence' => $s['id_geofence'],
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
				'condicion_operador' => '=',
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

			if ($fecha) {
				$fecha_programada2 = $fecha;
			} else {
				$fecha_programada2 = date('Y-m-d');
			}

			$query = "SELECT 
						s.id_servicio, s.id_cliente, c.nombre as cliente, s.id_direccion, s.id_truck, s.id_crew_1, s.id_crew_2,
						s.id_crew_3, s.id_crew_4, s.id_status, t.color AS crew_color_principal, s.dia_servicio, s.finalizado,
						s.estado_servicio, s.estado_visita, s.hora_aviso_usuario, s.hora_finalizado, s.tipo_dia, s.hora_inicio_gps,
						s.hora_fin_gps, d.direccion, d.id_geofence, d.lat, d.lng, t.nombre as truck,
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
					'id_truck' => $s['id_truck'],
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

			usort($resultado, function ($a, $b) {
				$numA = (int) preg_replace('/[^0-9]/', '', $a['truck']);
				$numB = (int) preg_replace('/[^0-9]/', '', $b['truck']);
				return $numA <=> $numB;
			});

			// === PASO 1: Obtener IDs de trucks que SÍ tienen servicio HOY ===
			$truckIdsConServicio = [];
			foreach ($resultado as $s) {
				if (!empty($s['id_truck'])) {
					$truckIdsConServicio[] = (int) $s['id_truck'];
				}
			}

			$truckIdsConServicio = array_unique($truckIdsConServicio);

			// === PASO 2: Obtener TODOS los trucks activos (ajusta id_status según tu lógica) ===
			$queryTrucks = "SELECT id_truck, nombre, color 
				FROM truck 
				WHERE id_status = 26";
			$camionesActivos = $this->ejecutarConsulta($queryTrucks, '', [], 'fetchAll');

			// === PASO 3: Filtrar los que NO tienen servicio hoy ===
			$vehiculosSinServicio = [];
			if (is_array($camionesActivos)) {
				foreach ($camionesActivos as $camion) {
					if (!in_array((int) $camion['id_truck'], $truckIdsConServicio)) {
						$vehiculosSinServicio[] = [
							'id_truck' => $camion['id_truck'],
							'truck' => $camion['nombre'],
							'color_truck' => $camion['color']
						];
					}
				}
				usort($vehiculosSinServicio, function ($a, $b) {
					$numA = (int) preg_replace('/[^0-9]/', '', $a['truck']);
					$numB = (int) preg_replace('/[^0-9]/', '', $b['truck']);
					return $numA <=> $numB;
				});
			} else {
				foreach ($camionesActivos as $camion) {
					$vehiculosSinServicio[] = [
						'id_truck' => $camion['id_truck'],
						'truck' => $camion['nombre'],
						'color_truck' => $camion['color']
					];
				}
				usort($vehiculosSinServicio, function ($a, $b) {
					$numA = (int) preg_replace('/[^0-9]/', '', $a['truck']);
					$numB = (int) preg_replace('/[^0-9]/', '', $b['truck']);
					return $numA <=> $numB;
				});
			}

			// === PASO 4: Enviar AMBOS conjuntos en un solo JSON (solo echo, sin return) ===
			http_response_code(200);
			echo json_encode([
				'servicios' => $resultado,
				'vehiculosSinServicio' => $vehiculosSinServicio
			], JSON_PRETTY_PRINT);
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
						d.id_geofence, 
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
						d.id_geofence, 
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
						AND s.id_status != $this->id_status_historico
						AND s.fecha_programada < :v_fecha_programada
					ORDER BY s.fecha_programada DESC
					LIMIT 10";

			$params = [
				':id_cliente' => $id_cliente,
				':v_fecha_programada' => date('Y-m-d')
			];

			$result = $this->ejecutarConsulta($query, '', $params, 'fetchAll');
			$this->log("Resultado de la consulta de historico: " . print_r($result, true));

			if (count((array)$result) > 0) {
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
				$this->log("Resultado final de historico: " . print_r($historial, true));

				http_response_code(200);
				echo json_encode(['historial' => $historial]);
			} else {
				$historial = [];
				http_response_code(200);
				echo json_encode(['historial' => $historial]);
			}
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
		$hora_aviso_usuario = $data['hora_aviso_usuario'] ?? '';
		$estado_actual = $data['estado_actual'] ?? '';
		$cliente = $data['cliente'] ?? null;
		$truck = $data['truck'] ?? null;

		try {
			$this->log("=== ACTUALIZANDO CON HISTORIAL: Servicio $id_servicio a $estado ===");

			$datetime_inicio = null;

			if ($origen_act == "motor1") {
				if ($estado === 'INICIO DE SERVICIO') {
					$estado = 'inicio_actividades';
					// 2. Fecha del servicio (asegurarse de tenerla)

					$fecha_servicio = date('Y-m-d'); // O recuperarla de BD
					$datetime_inicio = $fecha_servicio;

					if (!$hora_aviso_usuario || !preg_match('/^([0-1][0-9]|2[0-3]):([0-5][0-9])$/', $hora_aviso_usuario)) {
						// Hora inválida
						$response = [
							'status' => 'error',
							'message' => 'Formato de hora inválido'
						];
					} else {
						// 3. Combinar fecha + hora → formato DATETIME
						$datetime_inicio = $fecha_servicio . ' ' . $hora_aviso_usuario . ':00'; // '2025-04-05 14:30:00'

						// Opcional: validar que sea una fecha/hora válida
						$validDate = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime_inicio);
						if (!$validDate) {
							$response = [
								'status' => 'error',
								'message' => 'Fecha/hora no válida'
							];
						} else {
						}
					}
				} elseif ($estado === 'FINALIZO SERVICIO') {
					$estado = 'finalizado';
				} elseif ($estado === 'CANCELAR SERVICIO') {
					$estado = 'cancelado';
				} elseif ($estado === 'REPLANIFICAR SERVICIO') {
					$estado = 'replanificado';
				}
			} else {
				if ($estado === 'FINALIZO SERVICIO' || $estado === 'finalizado') {
					$estado = 'finalizado';
				} elseif ($estado === 'REPLANIFICAR SERVICIO' || $estado === 'replanificado') {
					$estado = 'replanificado';
				} elseif ($estado === 'CANCELAR SERVICIO' || $estado === 'cancelado') {
					$estado = 'cancelado';
				} elseif ($estado === 'INICIO DE SERVICIO' || $estado === 'inicio_actividades') {
					$estado = 'inicio_actividades';
					// 2. Fecha del servicio (asegurarse de tenerla)

					$fecha_servicio = date('Y-m-d'); // O recuperarla de BD
					$datetime_inicio = $fecha_servicio;

					if (!$hora_aviso_usuario || !preg_match('/^([0-1][0-9]|2[0-3]):([0-5][0-9])$/', $hora_aviso_usuario)) {
						// Hora inválida
						$response = [
							'status' => 'error',
							'message' => 'Formato de hora inválido'
						];
					} else {
						// 3. Combinar fecha + hora → formato DATETIME
						$datetime_inicio = $fecha_servicio . ' ' . $hora_aviso_usuario . ':00'; // '2025-04-05 14:30:00'

						// Opcional: validar que sea una fecha/hora válida
						$validDate = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime_inicio);
						if (!$validDate) {
							$response = [
								'status' => 'error',
								'message' => 'Fecha/hora no válida'
							];
						} else {
						}
					}
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
				$hora_aviso_usuario = $datetime_inicio;
			}

			// Si es "Inicio de actividades", registrar la hora
			$hora_aviso = null;
			if ($estado === 'inicio_actividades') {
				$hora_aviso = date('Y-m-d H:i:s');
			}

			// Si el estado es 'finalizado', registrar la hora actual
			$hora_finalizado = null;
			if ($estado === 'finalizado') {
				$hora_aviso = date('Y-m-d H:i:s');
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
			if ($estado === 'inicio_actividades') {
				$condicion = "hora_aviso_usuario = COALESCE(hora_aviso_usuario, :v_hora_aviso_usuario),
					audit_f_hau = COALESCE(audit_f_hau, :v_audit_f_hau)";
			} else {
				$condicion = "finalizado = :v_finalizado,
					hora_finalizado = COALESCE(hora_finalizado, :v_hora_finalizado),
					audit_f_hf = COALESCE(audit_f_hf,  :v_audit_f_hf)";
			}

			$sql = "UPDATE servicios 
					SET estado_servicio = :v_estado_servicio, 
						estado_visita = :v_estado_visita,
						id_status = :v_id_status, 
						$condicion
					WHERE id_servicio = :v_id_servicio";

			if ($estado === 'inicio_actividades') {
				$param = [
					':v_estado_servicio' => $estado_servicio,
					':v_estado_visita' => $estado_visita,
					':v_id_status' => $id_status,
					':v_hora_aviso_usuario' => $datetime_inicio,
					':v_audit_f_hau' => $hora_aviso,
					':v_id_servicio' => $id_servicio
				];
			} else {
				$param = [
					':v_estado_servicio' => $estado_servicio,
					':v_estado_visita' => $estado_visita,
					':v_id_status' => $id_status,
					':v_finalizado' => $finalizado,
					':v_audit_f_hf' => $hora_aviso,
					':v_hora_finalizado' => $hora_finalizado,
					':v_id_servicio' => $id_servicio
				];
			}
			$this->log("Actualización SQL: $sql con params " . json_encode($param));

			$resultados = $this->ejecutarConsulta($sql, "", $param);

			if ($resultados === false) {
				http_response_code(500);
				echo json_encode(['error' => 'No se pudo actualizar el servicio']);
				return;
			} else {
				// Registrar auditoría si hubo cambios
				if ($resultados > 0) {
					$this->registrarAuditoriaOperacionCompleja(
						tabla: 'servicios',
						accion: 'UPDATE',
						query: $sql,
						params: $param
					);
				}
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

			try {
				if ($estado == 'finalizado') {
					$finalizado = 1;
				} else {
					$finalizado = 0;
				}
				$datos = [
					['campo_nombre' => 'estado_visita', 'campo_marcador' => ':estado_visita', 'campo_valor' => $estado],
					['campo_nombre' => 'id_status', 'campo_marcador' => ':id_status', 'campo_valor' => $id_status],
					['campo_nombre' => 'finalizado', 'campo_marcador' => ':finalizado', 'campo_valor' => $finalizado]
				];
				$condicion = [
					'condicion_campo' => 'id_servicio',
					'condicion_operador' => '=',
					'condicion_marcador' => ':id_servicio',
					'condicion_valor' => $id_servicio
				];

				// Actualizar servicio
				$this->log("Actualización SQL");

				$resultados = $this->actualizarDatos('servicios', $datos, $condicion);

				// Preparar respuesta base
				$response = ['success' => 'ok', 'message' => $resultados . ' Record Update completed'];

				// Si el servicio fue marcado como finalizado, revisar si hay clientes que "deberían" estar en rutas
				// pero no están asignados (misma lógica que en listarServicios_despachos)
				try {
					// Obtener fecha_programada del servicio
					$srv = $this->ejecutarConsulta("SELECT fecha_programada FROM servicios WHERE id_servicio = :id", '', [':id' => $id_servicio]);
					$fecha_programada_srv = $srv['fecha_programada'] ?? date('Y-m-d');

					// Determinar día en inglés con helper existente
					$dayWord = $this->obtenerDiaSemana($fecha_programada_srv);

					$sinRutaSQL = "SELECT DISTINCT ct.id_contrato, ct.id_cliente, c.nombre AS cliente, ct.id_direccion, d.direccion, di.dia_ingles AS day_work,
						se.fecha_programada AS ultimo_servicio, COALESCE(DATEDIFF(:v_fecha01, se.fecha_programada), -1) AS dias, fs.concepto
						FROM contratos ct
						LEFT JOIN clientes c ON ct.id_cliente = c.id_cliente
						LEFT JOIN direcciones d ON ct.id_direccion = d.id_direccion
						LEFT JOIN dias_semana di ON ct.id_dia_semana = di.id_dia_semana
						LEFT JOIN frecuencia_servicio fs ON fs.id_frecuencia_servicio = ct.id_frecuencia_servicio
						LEFT JOIN (
							SELECT id_servicio, id_cliente, fecha_programada,
								ROW_NUMBER() OVER (PARTITION BY id_cliente ORDER BY fecha_programada DESC, id_servicio DESC) AS rn
							FROM servicios
						) AS se ON se.id_cliente = ct.id_cliente AND se.rn = 1
						LEFT JOIN rutas_direcciones rd ON d.id_direccion = rd.id_direccion
						LEFT JOIN rutas_zonas_cuadricula rz ON rd.id_ruta = rz.id_ruta
						LEFT JOIN route_day_assignments rda ON rz.id_ruta = rda.id_ruta AND rda.day_of_week = :v_day
						WHERE ct.id_status = 18
							AND di.dia_ingles = :v_day_work
							AND (
								CASE ct.id_frecuencia_servicio
									WHEN 1 THEN DATEDIFF(:v_fecha02, se.fecha_programada) >= 7
									WHEN 2 THEN DATEDIFF(:v_fecha03, se.fecha_programada) >= 14
									WHEN 3 THEN DATEDIFF(:v_fecha04, se.fecha_programada) >= 21
									WHEN 4 THEN
										CASE ct.mensual_calendario
											WHEN 1 THEN :v_fecha05 >= DATE_ADD(se.fecha_programada, INTERVAL 1 MONTH)
											ELSE DATEDIFF(:v_fecha06, se.fecha_programada) >= 28
										END
									ELSE DATEDIFF(:v_fecha07, se.fecha_programada) >= 7
								END
							)
							AND rda.id_ruta IS NULL
						ORDER BY c.nombre";

					$paramsSinRuta = [
						':v_fecha01' => $fecha_programada_srv,
						':v_day_work' => strtoupper($dayWord),
						':v_fecha02' => $fecha_programada_srv,
						':v_fecha03' => $fecha_programada_srv,
						':v_fecha04' => $fecha_programada_srv,
						':v_fecha05' => $fecha_programada_srv,
						':v_fecha06' => $fecha_programada_srv,
						':v_fecha07' => $fecha_programada_srv,
						':v_day' => strtolower($dayWord)
					];

					$sinRuta = $this->ejecutarConsulta($sinRutaSQL, '', $paramsSinRuta, 'fetchAll');

					$htmlNoRoute = '';
					if ($sinRuta) {
						$htmlNoRoute .= '<div class="sinruta-header"><strong>Possible services for this date with no route assigned. (' . count($sinRuta) . ')</strong></div>';
						$htmlNoRoute .= '<ul style="list-style: none; padding-left:0;">';
						$textParts = [];
						foreach ($sinRuta as $sr) {
							$nombre = htmlspecialchars($sr['cliente'] ?? '—');
							$dir = htmlspecialchars($sr['direccion'] ?? '—');
							$ultimo = !empty($sr['ultimo_servicio']) && $sr['ultimo_servicio'] !== '0000-00-00' ? (new \DateTime($sr['ultimo_servicio']))->format('m/d/Y') : '—';
							$htmlNoRoute .= '<li style="padding:6px 0; border-bottom:1px solid #eee;">' . $nombre . ' — ' . $dir . ' <small style="color:#666;">(Last: ' . $ultimo . ')</small></li>';
							$textParts[] = $nombre . ' — ' . $dir;
						}
						$htmlNoRoute .= '</ul>';

						// Generar mensaje en chat del sistema
						try {
							$currentUserId = $_SESSION['user_id'] ?? null;
							// Buscar o crear sala sistema_despacho
							$room = $this->ejecutarConsulta("SELECT id FROM chat_salas WHERE nombre = :n LIMIT 1", '', [':n' => 'sistema_despacho']);
							if (!$room) {
								$datosSala = [
									['campo_nombre' => 'nombre', 'campo_marcador' => ':nombre', 'campo_valor' => 'sistema_despacho'],
									['campo_nombre' => 'descripcion', 'campo_marcador' => ':descripcion', 'campo_valor' => 'Sala del sistema para notificaciones de despacho'],
									['campo_nombre' => 'creado_por', 'campo_marcador' => ':creado_por', 'campo_valor' => $currentUserId ?? 0],
									['campo_nombre' => 'fecha_creacion', 'campo_marcador' => ':fecha_creacion', 'campo_valor' => date('Y-m-d H:i:s')],
									['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1]
								];
								$newSalaId = $this->guardarDatos('chat_salas', $datosSala);
								$roomId = $newSalaId;
							} else {
								$roomId = $room['id'];
							}

							// Añadir usuarios activos a la sala si no están
							$users = $this->ejecutarConsulta("SELECT id FROM usuarios_ejecutivos WHERE activo = 1", '', [], 'fetchAll');
							if (!empty($users)) {
								foreach ($users as $u) {
									$exists = $this->ejecutarConsulta("SELECT 1 FROM chat_usuarios_salas WHERE sala_id = :s AND usuario_id = :u LIMIT 1", '', [':s' => $roomId, ':u' => $u['id']]);
									if (!$exists) {
										$datosUsu = [
											['campo_nombre' => 'sala_id', 'campo_marcador' => ':sala_id', 'campo_valor' => $roomId],
											['campo_nombre' => 'usuario_id', 'campo_marcador' => ':usuario_id', 'campo_valor' => $u['id']],
											['campo_nombre' => 'rol', 'campo_marcador' => ':rol', 'campo_valor' => 'member'],
											['campo_nombre' => 'fecha_union', 'campo_marcador' => ':fecha_union', 'campo_valor' => date('Y-m-d H:i:s')]
										];
										$this->guardarDatos('chat_usuarios_salas', $datosUsu);
									}
								}
							}

							// Insertar mensaje con resumen
							$maxShow = 15;
							$textPreview = implode('; ', array_slice($textParts, 0, $maxShow));
							if (count($textParts) > $maxShow) $textPreview .= '... (+' . (count($textParts) - $maxShow) . ' más)';
							$msg = "[SISTEMA] Durante la marca de servicio para " . $fecha_programada_srv . " se detectaron " . count($sinRuta) . " clientes que deberían estar en rutas pero no lo están: " . $textPreview;

							$datosMsg = [
								['campo_nombre' => 'sala_id', 'campo_marcador' => ':sala_id', 'campo_valor' => $roomId],
								['campo_nombre' => 'usuario_id', 'campo_marcador' => ':usuario_id', 'campo_valor' => $currentUserId ?? 0],
								['campo_nombre' => 'mensaje', 'campo_marcador' => ':mensaje', 'campo_valor' => $msg],
								['campo_nombre' => 'fecha_envio', 'campo_marcador' => ':fecha_envio', 'campo_valor' => date('Y-m-d H:i:s')]
							];
							$this->guardarDatos('chat_mensajes', $datosMsg);
						} catch (Exception $e) {
							$this->logWithBacktrace('Error creando mensaje sistema: ' . $e->getMessage(), true);
						}
					} else {
						$htmlNoRoute = '<p style="color: #888;">No missing services detected.</p>';
					}

					$response['html_no_route'] = $htmlNoRoute;
				} catch (Exception $e) {
					$this->logWithBacktrace('Error calculando sin ruta tras actualizar estado: ' . $e->getMessage(), true);
				}

				http_response_code(200);
				echo json_encode($response);
			} catch (Exception $e) {
				$this->logWithBacktrace("Error en finalizarServicio: " . $e->getMessage(), true);
				http_response_code(500);
				echo json_encode(['error' => 'Could not update']);
			}

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
				'condicion_operador' => '=',
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
				'condicion_operador' => '=',
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
				'condicion_operador' => '=',
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
		$this->log("Servicio Procesado: " . $id_servicio . " " . print_r($resultado, true));

		$message = '';
		$sql = '';
		$param = [];

		if ($hora_inicio_gps && !$hora_fin_gps && !$tiempo_servicio) {
			// Verificar si ya fue iniciado
			if (!empty($resultado['hora_inicio_gps'])) {
				$message = 'El servicio ya fue iniciado anteriormente. ' . ' hora_inicio_existente:' . $resultado['hora_inicio_gps'];
				return [
					'error' => true,
					'message' => $message
				];
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
			$message = 'Servicio iniciado';
		} elseif (!$hora_inicio_gps && $hora_fin_gps && $tiempo_servicio) {

			// Verificar si ya fue cerrado
			if (!empty($resultado['hora_fin_gps'])) {
				$message = 'El servicio ya fue cerrado anteriormente. ' . ' hora_fin_existente:' . $resultado['hora_fin_gps'];
				return [
					'error' => true,
					'message' => $message
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
			$message = 'Servicio Finalizado';
		}

		if (empty($datos)) {
			$message = 'No hay campos para actualizar';
			return [
				'error' => true,
				'message' => $message
			];
		}

		// Condición para el WHERE
		$condicion = [
			'condicion_campo' => 'id_servicio',
			'condicion_operador' => '=',
			'condicion_marcador' => ':id_servicio',
			'condicion_valor' => $id_servicio
		];

		$resultado = $this->actualizarDatos('servicios', $datos, $condicion);

		if ($resultado === true) {
			$this->log("Success en ActualizarServicio ");
			return [
				'success' => true,
				'message' => $message
			];
		} else {
			$this->logWithBacktrace("Error en ActualizarServicio ", true);
			return [
				'error' => true,
				'message' => 'No se pudo actualizar el servicio'
			];
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

	private function calcularDistanciaMetros_2($lat1, $lon1, $lat2, $lon2)
	{
		$rad = M_PI / 180;
		$lat1 *= $rad;
		$lon1 *= $rad;
		$lat2 *= $rad;
		$lon2 *= $rad;
		$theta = $lon1 - $lon2;
		$dist = acos(sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($theta));
		return 6371000 * $dist; // Metros
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

	public function dato_actual_de_truck($id_truck)
	{
		$sql = "SELECT t.*, gps.id, gps.lat, gps.lng, gps.timestamp as tiempo, 1 AS en_servicio 
			FROM truck t 
			LEFT JOIN gps_tracker gps ON t.nombre COLLATE utf8mb4_unicode_ci = gps.vehicle_id 
			WHERE gps.timestamp > :v_f_calculo AND gps.vehicle_id = :v_vehicle_id 
			ORDER BY timestamp DESC LIMIT 1";

		$param = [
			':v_vehicle_id' => $id_truck,
			':v_f_calculo' => date('Y-m-d 00:00:00')
		];
		error_log($sql);
		error_log(json_encode($param));

		try {
			$truck_act = $this->ejecutarConsulta($sql, '', $param);

			error_log(print_r($truck_act, true));

			if (!$truck_act) {
				echo json_encode([
					'success' => false,
					'error' => 'No data found'
				]);
				return;
			}

			echo json_encode([
				'success' => true,
				'data' => $truck_act // No uses array_values aquí
			]);
		} catch (Exception $e) {
			$this->logWithBacktrace("Error en dato_actual_de_truck: " . $e->getMessage());
			echo json_encode([
				'success' => false,
				'error' => 'Error al buscar el Truck'
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

	public function actualizarServicios($id_servicio, $id_cliente, $id_direccion)
	{
		try {
			$datos = [
				['campo_nombre' => 'id_cliente', 'campo_marcador' => ':id_cliente', 'campo_valor' => $id_cliente],
				['campo_nombre' => 'id_direccion', 'campo_marcador' => ':id_direccion', 'campo_valor' => $id_direccion]
			];
			$condicion = [
				'condicion_campo' => 'id_servicio',
				'condicion_operador' => '=',
				'condicion_marcador' => ':id_servicio',
				'condicion_valor' => $id_servicio
			];

			$respuesta = $this->actualizarDatos('servicios', $datos, $condicion);

			if (!headers_sent()) {
				http_response_code(200);
				header('Content-Type: application/json');
			} else {
				error_log("Advertencia: Algunos encabezados ya fueron enviados antes de la respuesta JSON.");
				// O manejar el error
			}

			if ($respuesta > 0) {
				echo json_encode(['status' => 'ok', 'message' => 'Servicio Actualizado'], JSON_PRETTY_PRINT);
				exit; // Salir inmediatamente después de enviar el JSON
			} else {
				echo json_encode(['error' => 'fail', 'message' => 'Servicio Actualizado Fallo'], JSON_PRETTY_PRINT);
				exit; // Salir inmediatamente después de enviar el JSON
			}
		} catch (Exception $e) {
			$this->logWithBacktrace("Error en finalizarServicio: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'No se pudo finalizar']);
		}
	}

	public function reconciliar_datos_historicos_final()
	{
		// Solo accesible para admins
		// if (!esAdmin($_SESSION)) {
		// 	echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
		// 	exit;
		// }

		$detalles = [
			'total_procesados' => 0,
			'actualizados' => 0,
			'sin_coordenadas' => 0,
			'sin_puntos_gps' => 0,
			'errores' => []
		];

		try {
			// Fecha límite: ayer
			$fechaLimite = date('Y-m-d', strtotime('today'));

			// Obtener servicios finalizados antes de ayer
			$sql = "
				SELECT s.id_servicio, s.id_truck, s.id_direccion, s.fecha_programada, d.lat, d.lng, s.hora_aviso_usuario, s.hora_finalizado
				FROM servicios s
				LEFT JOIN direcciones d ON s.id_direccion = d.id_direccion
				WHERE DATE(s.fecha_programada) <= :v_fechaLimite 
				AND s.id_status != 39
				ORDER BY s.fecha_programada DESC
			";

			$param = [
				':v_fechaLimite' => $fechaLimite
			];

			error_log("Consulta SQL de servicios históricos: $sql con params " . json_encode($param));
			$servicios = $this->ejecutarConsulta($sql, '', $param, 'fetchAll');
			error_log("Resultado de servicios históricos: " . print_r($servicios, true));
			foreach ($servicios as $servicio) {
				$detalles['total_procesados']++;

				// Validar coordenadas
				if (!is_numeric($servicio['lat']) || !is_numeric($servicio['lng'])) {
					$detalles['sin_coordenadas']++;
					continue;
				}

				$id_servicio = $servicio['id_servicio'];
				$coordServicio = ['lat' => (float) $servicio['lat'], 'lng' => (float) $servicio['lng']];
				$umbralMetros = 150;

				// Buscar puntos GPS del camión ese día
				$fechaServicio = date('Y-m-d', strtotime($servicio['fecha_programada']));
				$inicioDia = $fechaServicio . ' 00:00:00';
				$finDia = $fechaServicio . ' 23:59:59';

				$sqlgps = "
					SELECT lat, lng, timestamp 
					FROM gps_tracker 
					WHERE vehicle_id = :v_vehicle_id 
					AND timestamp BETWEEN :v_inicioDia AND :v_finDia
					ORDER BY timestamp ASC
				";

				$paramgps = [
					':v_vehicle_id' => 'TRUCK ' . $servicio['id_truck'],
					':v_inicioDia' => $inicioDia,
					':v_finDia' => $finDia
				];

				error_log("Consulta SQL de gps históricos: $sql con params " . json_encode($paramgps));
				$puntos = $this->ejecutarConsulta($sqlgps, '', $paramgps, 'fetchAll');
				error_log("Resultado de servicios gps: " . print_r($puntos, true));

				if (empty($puntos)) {
					$detalles['sin_puntos_gps']++;
					continue;
				}

				$puntosCercanos = [];
				foreach ($puntos as $punto) {
					$distancia = $this->calcularDistanciaMetros(
						$punto['lat'],
						$punto['lng'],
						$coordServicio['lat'],
						$coordServicio['lng']
					);
					if ($distancia <= $umbralMetros) {
						$puntosCercanos[] = $punto;
					}
				}

				if (empty($puntosCercanos)) {
					$detalles['sin_puntos_gps']++;
					continue;
				}

				// Primer y último punto cercano
				$inicioGps = $puntosCercanos[0]['timestamp'];
				$finGps = $puntosCercanos[count($puntosCercanos) - 1]['timestamp'];

				// Calcular duración
				$t1 = new \DateTime($inicioGps);
				$t2 = new \DateTime($finGps);
				$diff = $t1->diff($t2);
				$duracion = $diff->format('%H:%I:%S');

				// Verificar si ya está actualizado
				$sqlV = "SELECT hora_inicio_gps, hora_fin_gps FROM servicios WHERE id_servicio = :v_id_servicio";
				$paramV = [
					':v_id_servicio' => $id_servicio
				];

				$row = $this->ejecutarConsulta($sqlV, '', $paramV);

				if ($row['hora_inicio_gps'] === $inicioGps && $row['hora_fin_gps'] === $finGps) {
					continue; // Ya sincronizado
				}

				$datos = [
					['campo_nombre' => 'hora_inicio_gps', 'campo_marcador' => ':hora_inicio_gps', 'campo_valor' => $inicioGps],
					['campo_nombre' => 'hora_fin_gps', 'campo_marcador' => ':hora_fin_gps', 'campo_valor' => $finGps],
					['campo_nombre' => 'tiempo_servicio', 'campo_marcador' => ':tiempo_servicio', 'campo_valor' => $duracion]
				];
				$condicion = [
					'condicion_campo' => 'id_servicio',
					'condicion_operador' => '=',
					'condicion_marcador' => ':id_servicio',
					'condicion_valor' => $id_servicio
				];

				$update = $this->actualizarDatos('servicios', $datos, $condicion);

				if ($update > 0) {
					$detalles['actualizados']++;
				}
			}

			return [
				'success' => true,
				'message' => 'Reconciliación histórica completada.',
				'detalles' => $detalles
			];
		} catch (Exception $e) {
			return [
				'success' => false,
				'error' => 'Error en reconciliación: ' . $e->getMessage(),
				'detalles' => $detalles
			];
		}
	}

	public function buscarLista($datos)
	{
		$origen = $datos['origen'];
		$id_codigo = $datos['id_codigo'];

		switch ($origen) {
			case "clientes":
				$tabla = "clientes";
				$where = "s.id_cliente = " . $id_codigo;
				break;
			case "direcciones":
				$tabla = "direcciones";
				$where = "s.id_direccion = " . $id_codigo;
				break;
			case "vehiculos":
				break;
		}

		$query = "
				SELECT
					s.id_servicio, s.id_cliente, c.nombre as cliente, s.id_direccion, s.fecha_programada, s.id_truck, s.id_crew_1, 
					s.id_crew_2, s.id_crew_3, s.id_crew_4, s.id_status, t.color AS crew_color_principal, s.dia_servicio, 
					s.finalizado, s.estado_servicio, s.estado_visita, s.hora_aviso_usuario, s.hora_finalizado, s.tipo_dia, 
					s.hora_inicio_gps, s.hora_fin_gps, d.direccion, d.id_geofence, d.lat, d.lng, t.nombre as truck, ac.id_address_clas, 
					CASE 
						WHEN s.id_status = 37 AND s.hora_aviso_usuario IS NOT NULL THEN 'Service Started'
						WHEN s.id_status = 38 AND s.hora_aviso_usuario IS NOT NULL AND s.hora_finalizado IS NOT NULL THEN 'Processed'
						WHEN s.id_status = 38 AND s.hora_aviso_usuario IS NULL AND s.hora_finalizado IS NOT NULL THEN 'Not Started, Finished'
						WHEN s.id_status = 40 THEN 'Rescheduled'
						WHEN s.id_status = 47 THEN 'Cancelled'
						WHEN s.id_status = 48 THEN 'Not Served'
						ELSE 'Pending'
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
				LEFT JOIN address_clas AS ac ON d.id_address_clas = ac.id_address_clas
				LEFT JOIN truck AS t ON s.id_truck = t.id_truck
				WHERE s.id_status != $this->id_status_historico
				AND s.id_servicio IN (
						SELECT DISTINCT id_servicio 
						FROM servicios 
						WHERE id_status != $this->id_status_historico
							AND $where
					)
				GROUP BY s.id_servicio, s.id_cliente, s.id_direccion, s.id_truck, s.id_crew_1, 
					s.id_crew_2, s.id_crew_3, s.id_crew_4, s.id_status, t.color, 
					s.dia_servicio, s.finalizado, s.estado_servicio, s.estado_visita, 
					s.hora_aviso_usuario, s.hora_finalizado, s.tipo_dia, s.hora_inicio_gps, 
					s.hora_fin_gps, c.nombre, d.direccion, d.id_geofence, d.lat, d.lng, 
					t.nombre";

		$query .= " ORDER BY id_servicio DESC, FIELD(t.id_truck, 1,2,3,4,5,6,7,8,9,10,11,12), c.nombre";

		$servicios = $this->ejecutarConsulta($query, '', [], 'fetchAll');

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
				'id_direccion' => $s['id_direccion'],
				'id_address_clas' => $s['id_address_clas'],
				'direccion' => $s['direccion'],
				'id_geofence' => $s['id_geofence'],
				'id_truck' => $s['id_truck'],
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
				'fecha_programada' => $s['fecha_programada'],
				'tipo_dia' => $s['tipo_dia'],
				'hora_aviso_usuario' => $s['hora_aviso_usuario'],
				'hora_finalizado' => $s['hora_finalizado'],
				's_status' => $s['s_status'],
				'status_m2' => $s['status_m2'],
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

		return $resultado;
	}

	public function status_servicios()
	{
		$sql = "SELECT id_status, status AS nom_status, color
			FROM status_all
			WHERE tabla = 'servicios' AND id_status != 39
			ORDER BY id_status";
		$param = [];

		$result = $this->ejecutarConsulta($sql, '', $param, 'fetchAll');
		return $result;
	}

	public function listarServicios_despachos($fecha)
	{
		$this->log("Inicio: listarServicios_despachos");

		// 1) Revisar dias_no_actividad
		$checkDiaSQL = "SELECT COUNT(*) AS cnt FROM dias_no_actividad WHERE fecha = :v_fecha";
		$params = [
			':v_fecha' => $fecha
		];
		$cntRes = $this->ejecutarConsulta($checkDiaSQL, '', $params);
		if (!empty($cntRes) && isset($cntRes['cnt']) && $cntRes['cnt'] > 0) {
			// Día no laborable: no editable
			http_response_code(response_code: 200);
			echo json_encode(
				[
					'success' => false,
					'mess' => 'The calculation day has been scheduled as a non-working day.'
				]
			);
			return;
		}

		$si_existe = $this->verificar_servicio($fecha);

		if ($si_existe) {
			try {
				// Obtener el día actual en formato inglés (como en la BD)
				$dia_actual = $this->obtenerDiaSemana($fecha);

				$this->log("Día actual: " . $dia_actual);

				if ($fecha) {
					$fecha_programada2 = $fecha;
				} else {
					$fecha_programada2 = date('Y-m-d');
				}

				$query = "SELECT s.id_servicio, s.id_cliente, c.nombre AS cliente, s.id_direccion, d.direccion, ct.id_dia_semana, di.dia_ingles AS day_work,
							s.fecha_programada AS ultimo_servicio, fs.concepto, s.hora_aviso_usuario, s.hora_finalizado, s.hora_inicio_gps, 
							s.hora_fin_gps, ct.mensual_calendario, ct.id_ruta, ru.nombre_ruta
						FROM servicios s
						LEFT JOIN contratos ct ON ct.id_contrato = s.id_contrato
						LEFT JOIN clientes AS c ON s.id_cliente = c.id_cliente
						LEFT JOIN direcciones AS d ON s.id_direccion = d.id_direccion
						LEFT JOIN dias_semana AS di ON di.id_dia_semana = ct.id_dia_semana
						LEFT JOIN frecuencia_servicio AS fs ON fs.id_frecuencia_servicio = ct.id_frecuencia_servicio 
						LEFT JOIN rutas AS ru ON ct.id_ruta = ru.id_ruta
						WHERE s.fecha_programada = :v_fecha01 AND
							s.id_status != $this->id_status_historico
						ORDER BY s.id_servicio
					";

				$params = [
					':v_fecha01' => $fecha_programada2
				];

				$this->log("Consulta de listarServicios_despachos() Viejos: " . $query);
				$this->log("Parametros: " . json_encode($params));

				$asignados = $this->ejecutarConsulta($query, '', $params, 'fetchAll');

				$this->log("Resultado de la consulta: " . print_r($asignados, true));

				$query = "SELECT ct.id_contrato, ct.id_cliente, c.nombre AS cliente, ct.id_direccion, d.direccion, ct.id_dia_semana,
						di.dia_ingles AS day_work, :v_fecha01 AS fecha_consultada, fs.concepto, ct.mensual_calendario, s.fecha_programada AS ultimo_servicio
					FROM contratos ct
					INNER JOIN clientes c ON c.id_cliente = ct.id_cliente
					INNER JOIN direcciones d ON d.id_direccion = ct.id_direccion
					INNER JOIN dias_semana di ON di.id_dia_semana = ct.id_dia_semana
					INNER JOIN frecuencia_servicio fs ON fs.id_frecuencia_servicio = ct.id_frecuencia_servicio
					LEFT JOIN (
							SELECT 
								id_servicio,
								id_cliente,
								fecha_programada,
								ROW_NUMBER() OVER (PARTITION BY id_cliente ORDER BY fecha_programada DESC, id_servicio DESC) AS rn
							FROM servicios
							) AS s 
								ON s.id_cliente = ct.id_cliente AND s.rn = 1
					WHERE ct.id_status = 18
						AND ct.id_contrato NOT IN (
							SELECT s.id_contrato
							FROM servicios s
							WHERE s.fecha_programada = :v_fecha02
						)
					ORDER BY di.dia_ingles, c.nombre";

				$params = [
					':v_fecha01' => $fecha_programada2,
					':v_fecha02' => $fecha_programada2
				];

				$this->log("Consulta de listarServicios_despachos Descartados() Viejos: " . $query);

				$disponibles = $this->ejecutarConsulta($query, '', $params, 'fetchAll');

				// Supongamos que ya tienes $asignados y $noAsignados
				$htmlAsignados = '';
				foreach ($asignados as $item) {
					$htmlAsignados .= $this->renderItemDespacho($item, 'asignado', $si_existe, true);
				}

				$this->log("HTML registros asignados: " . $htmlAsignados);

				$htmlNoAsignados = '';
				foreach ($disponibles as $item) {
					$htmlNoAsignados .= $this->renderItemDespacho($item, 'no-asignado', $si_existe, false);
				}

				$this->log("HTML registros No asignados: " . $htmlNoAsignados);

				// Responder con HTML
				// === PASO 4: Enviar AMBOS conjuntos en un solo JSON (solo echo, sin return) ===
				http_response_code(response_code: 200);
				echo json_encode([
					'html_asignados' => $htmlAsignados ?: '<p style="color: #888;">No items</p>',
					'html_no_asignados' => $htmlNoAsignados ?: '<p style="color: #888;">No items</p>',
					'editable' => false
				]);
			} catch (Exception $e) {
				$this->logWithBacktrace("Error en listarServiciosConEstado: " . $e->getMessage(), true);
				http_response_code(500);
				echo json_encode(['error' => 'Error al cargar servicios']);
			}
		} else {
			try {
				// Obtener el día actual en formato inglés (como en la BD)
				$dia_actual = $this->obtenerDiaSemana($fecha);

				$this->log("Día actual para calculo de Predespacho: " . $dia_actual);

				if ($fecha) {
					$fecha_programada2 = $fecha;
				} else {
					$fecha_programada2 = date('Y-m-d');
				}
				$dayWord = $this->obtenerDiaSemana($fecha_programada2);

				if (!$this->busca_preservicio($fecha_programada2)) {
					// === NUEVA LÓGICA: Generar preservicios basados en route_day_assignments (si no existen servicios)
					// 2) Limpiar preservicios pendientes para esta fecha (evitar duplicados)
					try {
						$delSQL = "DELETE FROM preservicios WHERE fecha_programada = :v_fecha AND transferred = 0";
						$this->ejecutarConsulta($delSQL, '', [':v_fecha' => $fecha_programada2]);
					} catch (Exception $e) {
						$this->logWithBacktrace("No se pudo limpiar preservicios antiguos: " . $e->getMessage(), true);
					}

					// 3) Obtener rutas asignadas para el día
					$rutasSQL = "SELECT rda.id_ruta, r.nombre_ruta 
									FROM route_day_assignments AS rda 
									LEFT JOIN rutas r ON r.id_ruta = rda.id_ruta 
									WHERE rda.day_of_week = :v_day 
									ORDER BY r.nombre_ruta";

					$rutas = $this->ejecutarConsulta($rutasSQL, '', [':v_day' => $dayWord], 'fetchAll');

					// 4) Por cada ruta, obtener clientes compatibles y crear preservicios
					if ($rutas) {
						foreach ($rutas as $ruta) {
							$id_ruta = $ruta['id_ruta'];
							$clientesSQL = "SELECT ct.id_contrato, ct.id_cliente, ct.id_direccion, c.nombre AS cliente, d.direccion, ct.retraso_invierno,
									d.lat, d.lng, di.dia_ingles AS dia_servicio, fs.concepto, ct.id_ruta, rd.orden_en_ruta,	rd.tiempo_servicio
								FROM rutas_direcciones rd
								JOIN direcciones d ON rd.id_direccion = d.id_direccion
								JOIN contratos ct ON ct.id_direccion = d.id_direccion
								JOIN clientes c ON c.id_cliente = ct.id_cliente 
								JOIN dias_semana di ON ct.id_dia_semana = di.id_dia_semana
								JOIN frecuencia_servicio fs ON fs.id_frecuencia_servicio = ct.id_frecuencia_servicio
								WHERE ct.id_status = 18 AND rd.id_ruta = :v_id_ruta AND di.dia_ingles = :v_dia_ingles
								ORDER BY rd.orden_en_ruta";

							$paramsClientes = [':v_id_ruta' => $id_ruta, ':v_dia_ingles' => $dayWord];
							$clientes = $this->ejecutarConsulta($clientesSQL, '', $paramsClientes, 'fetchAll');

							if ($clientes) {
								foreach ($clientes as $cl) {
									$meta = [
										'origin' => 'route_day_assignment',
										'route_id' => $id_ruta,
										'route_name' => $ruta['nombre_ruta'] ?? null,
										'contract_id' => $cl['id_contrato'] ?? null
									];

									$datos = [
										['campo_nombre' => 'id_cliente', 'campo_marcador' => ':id_cliente', 'campo_valor' => $cl['id_cliente']],
										['campo_nombre' => 'id_direccion', 'campo_marcador' => ':id_direccion', 'campo_valor' => $cl['id_direccion']],
										['campo_nombre' => 'fecha_programada', 'campo_marcador' => ':fecha_programada', 'campo_valor' => $fecha_programada2],
										['campo_nombre' => 'dia_servicio', 'campo_marcador' => ':dia_servicio', 'campo_valor' => $cl['dia_servicio'] ?? $dayWord],
										['campo_nombre' => 'estado_servicio', 'campo_marcador' => ':estado_servicio', 'campo_valor' => 'pendiente'],
										['campo_nombre' => 'id_status', 'campo_marcador' => ':id_status', 'campo_valor' => 37],
										['campo_nombre' => 'id_ruta', 'campo_marcador' => ':id_ruta', 'campo_valor' => $cl['id_ruta']],
										['campo_nombre' => 'created_meta', 'campo_marcador' => ':created_meta', 'campo_valor' => json_encode($meta)],
										['campo_nombre' => 'retraso_invierno', 'campo_marcador' => ':retraso_invierno', 'campo_valor' => $cl['retraso_invierno']],
										['campo_nombre' => 'orden_en_ruta', 'campo_marcador' => ':orden_en_ruta', 'campo_valor' => $cl['orden_en_ruta']],
										['campo_nombre' => 'tiempo_servicio', 'campo_marcador' => ':tiempo_servicio', 'campo_valor' => $cl['tiempo_servicio']]
									];

									try {
										$this->guardarDatos('preservicios', $datos);
									} catch (Exception $e) {
										$this->logWithBacktrace("Error guardando preservicio: " . $e->getMessage(), true);
									}
								}
								// fin foreach clientes
							}
						}
						// fin foreach rutas
					}

					// 5) Incluir servicios reprogramados (id_status = replanificado) que ya apunten a la fecha
					$reprogSQL = "SELECT s.id_servicio, s.id_cliente, s.id_direccion, s.fecha_programada, s.id_status, s.id_truck, 
							s.id_crew_1, s.id_crew_2, s.id_crew_3, s.id_crew_4, c.tiempo_servicio, 99 as orden_en_ruta, c.retraso_invierno
						FROM servicios s
						LEFT JOIN contratos c ON c.id_cliente = s.id_cliente AND c.id_direccion = s.id_direccion
						WHERE s.id_status = :v_replanificado 
							AND DATE(s.fecha_programada) = :v_fecha";
					$params = [
						':v_replanificado' => $this->id_status_replanificado,
						':v_fecha' => $fecha_programada2
					];
					$reprog = $this->ejecutarConsulta($reprogSQL, '', $params, 'fetchAll');
					if ($reprog) {
						foreach ($reprog as $r) {
							$meta = ['origin' => 'reprogrammed', 'source_servicio' => $r['id_servicio']];
							$datos = [
								['campo_nombre' => 'id_cliente', 'campo_marcador' => ':id_cliente', 'campo_valor' => $r['id_cliente']],
								['campo_nombre' => 'id_direccion', 'campo_marcador' => ':id_direccion', 'campo_valor' => $r['id_direccion']],
								['campo_nombre' => 'fecha_programada', 'campo_marcador' => ':fecha_programada', 'campo_valor' => $fecha_programada2],
								['campo_nombre' => 'dia_servicio', 'campo_marcador' => ':dia_servicio', 'campo_valor' => $dayWord],
								['campo_nombre' => 'estado_servicio', 'campo_marcador' => ':estado_servicio', 'campo_valor' => 'reprogramado'],
								['campo_nombre' => 'id_status', 'campo_marcador' => ':id_status', 'campo_valor' => $r['id_status']],
								['campo_nombre' => 'created_meta', 'campo_marcador' => ':created_meta', 'campo_valor' => json_encode($meta)],
								['campo_nombre' => 'retraso_invierno', 'campo_marcador' => ':retraso_invierno', 'campo_valor' => $r['retraso_invierno']],
								['campo_nombre' => 'orden_en_ruta', 'campo_marcador' => ':orden_en_ruta', 'campo_valor' => $r['orden_en_ruta']],
								['campo_nombre' => 'tiempo_servicio', 'campo_marcador' => ':tiempo_servicio', 'campo_valor' => $r['tiempo_servicio']]
							];
							try {
								$this->guardarDatos('preservicios', $datos);
							} catch (Exception $e) {
								$this->logWithBacktrace("Error guardando preservicio reprogramado: " . $e->getMessage(), true);
							}
						}
					}
				}

				// 6) Ahora leer todos los preservicios creados para la fecha y renderizarlos como no-asignados (editable)
				$fetchPresSQL = "SELECT p.*, c.nombre AS cliente, d.direccion, d.id_geofence, d.lat, d.lng, 
						p.id_ruta, p.id_ruta_new, ru.nombre_ruta, ru_n.nombre_ruta AS nombre_ruta_new
					FROM preservicios p
					LEFT JOIN clientes c ON p.id_cliente = c.id_cliente
					LEFT JOIN direcciones d ON p.id_direccion = d.id_direccion
					LEFT JOIN rutas ru ON p.id_ruta = ru.id_ruta
					LEFT JOIN rutas ru_n ON p.id_ruta_new = ru_n.id_ruta
					WHERE DATE(p.fecha_programada) = :v_fecha_programada 
						AND p.transferred = 0
					ORDER BY p.id_ruta, p.orden_en_ruta";

				$params = [
					':v_fecha_programada' => $fecha_programada2
				];

				$preservs = $this->ejecutarConsulta($fetchPresSQL, '', $params, 'fetchAll');

				$htmlAsignados = '';
				$htmlNoAsignados = '';
				$htmlTotalTiempo = '';

				// Los preservicios generados deben mostrarse en el panel izquierdo (asignados)
				if ($preservs) {
					foreach ($preservs as $item) {
						// Enriquecer con último servicio y datos de frecuencia/concepto
						try {
							if ($item['id_ruta_new'] == null) {
								$campo = "'' AS id_ruta_new, '' AS nombre_ruta_new ";
							} else {
								$campo = $item['id_ruta_new'] . " AS id_ruta_new";
								if ($item['nombre_ruta_new'] == null) {
									$campo .= ", '' AS nombre_ruta_new";
								} else {
									$campo .= ", '" . $item['nombre_ruta_new'] . "' AS nombre_ruta_new";
								}
							}
							$lastSQL = "SELECT se.id_servicio, se.fecha_programada AS ultimo_servicio, 
									COALESCE(DATEDIFF(:v_fecha_cons, se.fecha_programada), -1) AS dias,
									se.hora_aviso_usuario, se.hora_finalizado, se.hora_inicio_gps, se.hora_fin_gps,
									se.id_status, fs.id_frecuencia_servicio, fs.concepto, 
									ct.id_ruta, ru.nombre_ruta, ru.color_ruta, ct.retraso_invierno
									" . $campo . "
								FROM servicios se
								LEFT JOIN contratos ct ON se.id_cliente = ct.id_cliente AND se.id_direccion = ct.id_direccion
								LEFT JOIN frecuencia_servicio fs ON ct.id_frecuencia_servicio = fs.id_frecuencia_servicio
								LEFT JOIN rutas ru ON ct.id_ruta = ru.id_ruta
								WHERE se.id_cliente = :v_id_cliente
								ORDER BY se.fecha_programada DESC, se.id_servicio DESC
								LIMIT 1";

							$paramsLast = [
								':v_id_cliente' => $item['id_cliente'],
								':v_fecha_cons' => $fecha_programada2
							];
							$se = $this->ejecutarConsulta($lastSQL, '', $paramsLast);

							if ($se && is_array($se)) {
								$item['ultimo_servicio'] = $se['ultimo_servicio'] ?? null;
								$item['dias'] = isset($se['dias']) ? $se['dias'] : null;
								$item['hora_aviso_usuario'] = $se['hora_aviso_usuario'] ?? null;
								$item['hora_finalizado'] = $se['hora_finalizado'] ?? null;
								$item['hora_inicio_gps'] = $se['hora_inicio_gps'] ?? null;
								$item['hora_fin_gps'] = $se['hora_fin_gps'] ?? null;
								$item['id_frecuencia_servicio'] = $se['id_frecuencia_servicio'] ?? null;
								$item['concepto'] = $se['concepto'] ?? ($item['concepto'] ?? null);
								$item['id_status'] = $se['id_status'] ?? null;
								if ($item['id_ruta'] == null && $item['id_ruta_new'] != null) {
									$item['nombre_ruta'] = $item['nombre_ruta_new'] ?? null;
								} else {
									if ($item['id_ruta'] != null && $item['id_ruta_new'] != null) {
										$item['nombre_ruta'] = $item['nombre_ruta_new'] ?? null;
									} else {
										$item['nombre_ruta'] = $se['nombre_ruta'] ?? null;
									}
								}
								$item['color_ruta'] = $se['color_ruta'] ?? null;
							} else {
								// No hay servicio previo: marcar como nuevo
								$item['ultimo_servicio'] = null;
								$item['dias'] = null;
								$item['hora_aviso_usuario'] = null;
								$item['hora_finalizado'] = null;
								$item['hora_inicio_gps'] = null;
								$item['hora_fin_gps'] = null;
								$item['id_frecuencia_servicio'] = $item['id_frecuencia_servicio'] ?? null;
								$item['concepto'] = $item['concepto'] ?? null;
								$item['id_status'] = null;
								$item['nombre_ruta'] = null;
								$item['color_ruta'] = null;
							}
						} catch (Exception $e) {
							$this->logWithBacktrace("Error obteniendo ultimo servicio para cliente {$item['id_cliente']}: " . $e->getMessage(), true);
						}

						// Asegurar campo day_work usado por renderItemDespacho
						$item['day_work'] = $item['day_work'] ?? $item['dia_servicio'] ?? $dayWord;

						$htmlAsignados .= $this->renderItemDespacho($item, 'asignado', false, true);
					}

					// === Calcular tiempo total por ruta ===
					$rutasTiempos = [];

					foreach ($preservs as $item) {
						// Solo considerar preservicios asignados a una ruta
						if ($item['id_ruta'] === null || $item['id_ruta'] == 0) {
							continue;
						}

						$idRuta = $item['id_ruta'];
						$nombreRuta = $item['nombre_ruta'] ?? "Ruta {$idRuta}";
						$tiempoMinutos = (int)($item['tiempo_servicio'] ?? 0);

						if (!isset($rutasTiempos[$idRuta])) {
							$rutasTiempos[$idRuta] = [
								'nombre' => $nombreRuta,
								'total_minutos' => 0,
								'servicios' => 0
							];
						}

						$rutasTiempos[$idRuta]['total_minutos'] += $tiempoMinutos;
						$rutasTiempos[$idRuta]['servicios']++;
					}

					// === Generar HTML ===
					if (!empty($rutasTiempos)) {
						$htmlTotalTiempo .= '<ul class="lista-tiempo-rutas" style="list-style: none; padding: 0; margin: 10px 0;">';
						foreach ($rutasTiempos as $datos) {
							$horaFormateada = $this->minutosAHora($datos['total_minutos']);
							$htmlTotalTiempo .= "<li style=\"padding: 4px 0; border-bottom: 1px solid #eee;\">
								<strong>{$datos['nombre']}</strong> ({$datos['servicios']} servicios) 
								&nbsp; <em style=\"color: #27ae60;\">⏱️ {$horaFormateada}</em>
							</li>";
						}
						$htmlTotalTiempo .= '</ul>';
					} else {
						$htmlTotalTiempo = '<p style="color: #888; font-style: italic;">No hay rutas asignadas</p>';
					}					
				} else {
					$htmlAsignados = '<p style="color: #888;">No items</p>';
				}

				// Los contratos no asignados (disponibles)
				// Nuevo comportamiento solicitado: tomar TODOS los contratos activos (ct.id_status = 18)
				// que NO estén en preservicios para la fecha, sin aplicar análisis de frecuencia/días/servicios.
				try {
					$noAsignadosSQL = "SELECT DISTINCT ct.id_contrato, ct.id_cliente, c.nombre AS cliente, ct.id_direccion, d.direccion, 
							di.dia_ingles AS day_work, se.id_servicio, se.fecha_programada AS ultimo_servicio, se.hora_aviso_usuario, 
							se.hora_finalizado, se.hora_inicio_gps, se.hora_fin_gps, fs.id_frecuencia_servicio, fs.concepto, ct.id_ruta, ru.nombre_ruta, ru.color_ruta,
							COALESCE(FLOOR(TIME_TO_SEC(ct.tiempo_servicio) / 60), 0) AS tiempo_servicio
						FROM contratos ct
						LEFT JOIN clientes c ON ct.id_cliente = c.id_cliente
						LEFT JOIN direcciones d ON ct.id_direccion = d.id_direccion
						LEFT JOIN frecuencia_servicio fs ON fs.id_frecuencia_servicio = ct.id_frecuencia_servicio
						LEFT JOIN dias_semana di ON di.id_dia_semana = ct.id_dia_semana
						LEFT JOIN rutas ru ON ct.id_ruta = ru.id_ruta
						LEFT JOIN (
							SELECT id_servicio, id_cliente, fecha_programada, hora_aviso_usuario, hora_finalizado, hora_inicio_gps, hora_fin_gps,
								ROW_NUMBER() OVER (PARTITION BY id_cliente ORDER BY fecha_programada DESC, id_servicio DESC) AS rn
							FROM servicios
						) AS se ON se.id_cliente = ct.id_cliente AND se.rn = 1
						WHERE ct.id_status = 18
						AND ct.id_cliente NOT IN (
							SELECT DISTINCT p.id_cliente 
							FROM preservicios p 
							WHERE DATE(p.fecha_programada) = :v_fecha_pres 
								AND p.transferred = 0
						)
						ORDER BY di.dia_ingles, c.nombre";

					$paramsNoAsig = [
						':v_fecha_pres' => $fecha_programada2
					];

					$noAsignados = $this->ejecutarConsulta($noAsignadosSQL, '', $paramsNoAsig, 'fetchAll');

					if ($noAsignados) {
						foreach ($noAsignados as $item) {
							$htmlNoAsignados .= $this->renderItemDespacho($item, 'no-asignado', false, false);
						}
					} else {
						$htmlNoAsignados = '<p style="color: #888;">No items</p>';
					}
				} catch (Exception $e) {
					$this->logWithBacktrace("Error recalculando no-asignados (simplificado): " . $e->getMessage(), true);
					$htmlNoAsignados = '<p style="color: #888;">Error calculating available contracts</p>';
				}

				// 7) Construir lista de contratos que deberían ser hoy pero NO pertenecen a ninguna ruta asignada
				try {
					$sinRutaSQL = "SELECT DISTINCT ct.id_contrato, ct.id_cliente, c.nombre AS cliente, ct.id_direccion, d.direccion, di.dia_ingles AS day_work,
						se.fecha_programada AS ultimo_servicio, COALESCE(DATEDIFF(:v_fecha01, se.fecha_programada), -1) AS dias, fs.concepto
						FROM contratos ct
						LEFT JOIN clientes c ON ct.id_cliente = c.id_cliente
						LEFT JOIN direcciones d ON ct.id_direccion = d.id_direccion
						LEFT JOIN dias_semana di ON ct.id_dia_semana = di.id_dia_semana
						LEFT JOIN frecuencia_servicio fs ON fs.id_frecuencia_servicio = ct.id_frecuencia_servicio
						LEFT JOIN (
							SELECT id_servicio, id_cliente, fecha_programada,
								ROW_NUMBER() OVER (PARTITION BY id_cliente ORDER BY fecha_programada DESC, id_servicio DESC) AS rn
							FROM servicios
						) AS se ON se.id_cliente = ct.id_cliente AND se.rn = 1
						LEFT JOIN rutas_direcciones rd ON d.id_direccion = rd.id_direccion
						LEFT JOIN rutas_zonas_cuadricula rz ON rd.id_ruta = rz.id_ruta
						LEFT JOIN route_day_assignments rda ON rz.id_ruta = rda.id_ruta AND rda.day_of_week = :v_day
						WHERE ct.id_status = 18
							AND di.dia_ingles = :v_day_work
							AND (
								CASE ct.id_frecuencia_servicio
									WHEN 1 THEN DATEDIFF(:v_fecha02, se.fecha_programada) >= 7
									WHEN 2 THEN DATEDIFF(:v_fecha03, se.fecha_programada) >= 14
									WHEN 3 THEN DATEDIFF(:v_fecha04, se.fecha_programada) >= 21
									WHEN 4 THEN
										CASE ct.mensual_calendario
											WHEN 1 THEN :v_fecha05 >= DATE_ADD(se.fecha_programada, INTERVAL 1 MONTH)
											ELSE DATEDIFF(:v_fecha06, se.fecha_programada) >= 28
										END
									ELSE DATEDIFF(:v_fecha07, se.fecha_programada) >= 7
								END
							)
							AND ct.id_cliente NOT IN (
								SELECT DISTINCT p.id_cliente 
								FROM preservicios p 
								WHERE p.fecha_programada = :v_fecha_programada 
								AND p.transferred = 0
							)
							AND rda.id_ruta IS NULL
						ORDER BY di.dia_ingles, c.nombre";

					$paramsSinRuta = [
						':v_fecha01' => $fecha_programada2,
						':v_day_work' => strtoupper($dayWord),
						':v_fecha02' => $fecha_programada2,
						':v_fecha03' => $fecha_programada2,
						':v_fecha04' => $fecha_programada2,
						':v_fecha05' => $fecha_programada2,
						':v_fecha06' => $fecha_programada2,
						':v_fecha07' => $fecha_programada2,
						':v_day' => strtolower($dayWord),
						':v_fecha_programada' => date($fecha_programada2)
					];
					$sinRuta = $this->ejecutarConsulta($sinRutaSQL, '', $paramsSinRuta, 'fetchAll');

					$htmlNoRoute = '';
					if ($sinRuta) {
						$htmlNoRoute .= '<div class="sinruta-header"><strong>Possible services for this date with no route assigned. (' . count($sinRuta) . ')</strong></div>';
						$htmlNoRoute .= '<ul style="list-style: none; padding-left:0;">';
						foreach ($sinRuta as $sr) {
							$nombre = htmlspecialchars($sr['cliente'] ?? '—');
							$dir = htmlspecialchars($sr['direccion'] ?? '—');
							$ultimo = !empty($sr['ultimo_servicio']) && $sr['ultimo_servicio'] !== '0000-00-00' ? (new \DateTime($sr['ultimo_servicio']))->format('m/d/Y') : '—';
							$htmlNoRoute .= '<li style="padding:6px 0; border-bottom:1px solid #eee;">' . $nombre . ' — ' . $dir . ' <small style="color:#666;">(Last: ' . $ultimo . ')</small></li>';
						}
						$htmlNoRoute .= '</ul>';
					} else {
						$htmlNoRoute = '<p style="color: #888;">No missing services detected.</p>';
					}
				} catch (Exception $e) {
					$this->logWithBacktrace("Error generando lista sin ruta: " . $e->getMessage(), true);
					$htmlNoRoute = '<p style="color: #888;">Error generating missing schedule.</p>';
				}

				http_response_code(response_code: 200);
				echo json_encode([
					'html_asignados' => $htmlAsignados,
					'html_no_asignados' => $htmlNoAsignados,
					'html_no_route' => $htmlNoRoute,
					'htmlTotalTiempo' => $htmlTotalTiempo,
					'editable' => true
				]);

				return;
			} catch (Exception $e) {
				$this->logWithBacktrace("Error en listarServiciosConEstado: " . $e->getMessage(), true);
				http_response_code(500);
				echo json_encode(['error' => 'Error al cargar Preservicios']);
			}
		}
	}

	// === Convertir minutos a HH:mm:ss ===
	public function minutosAHora($minutos) {
		$horas = floor($minutos / 60);
		$min = $minutos % 60;
		return sprintf('%d:%02d:00', $horas, $min);
	}

	private function busca_preservicio($fecha_programada2)
	{
		$sql_pre = "SELECT COUNT(*) AS conteo
						FROM preservicios
						WHERE fecha_programada = :v_fecha_programa";
		$param = [
			":v_fecha_programa" => $fecha_programada2
		];

		$result = $this->ejecutarConsulta($sql_pre, '', $param);

		return $result['conteo'] > 0;
	}

	public function renderItemDespacho($item, $tipo, $si_existe, $p_assing)
	{
		// Sanitizar
		if ($si_existe) {
			$cod_servicio = htmlspecialchars($item['id_servicio'] ?? '—');
		};
		$nombreCliente = htmlspecialchars($item['cliente'] ?? $item['nombre'] ?? '—');
		if ($item['tiempo_servicio'] == 0 || empty($item['tiempo_servicio'])) {
			$tiempo_servicio = 'Unassigned';
		} else {
			$tiempo_servicio = htmlspecialchars($item['tiempo_servicio']);
		}
		$direccion = htmlspecialchars($item['direccion'] ?? '—');
		$zona = !empty($item['zona']) ? '<div class="formatoDireccion colorA">Zona: ' . htmlspecialchars($item['zona']) . '</div>' : '';

		$colorRuta = !empty(trim($item['color_ruta'] ?? '')) ? htmlspecialchars($item['color_ruta']) : '#ffffffff';
		$nombre_ruta = !empty(trim($item['nombre_ruta'] ?? '')) ? htmlspecialchars($item['nombre_ruta']) : '—';

		$dayWork = !empty(trim($item['day_work'] ?? '')) ? htmlspecialchars($item['day_work']) : '—';
		$concepto = htmlspecialchars($item['concepto'] ?? '—');

		$ultimoServicio = '—';
		if (!empty($item['ultimo_servicio']) && $item['ultimo_servicio'] !== '0000-00-00') {
			$fecha = new \DateTime($item['ultimo_servicio']);
			$ultimoServicio = $fecha->format('m/d/Y');
		} else {
			$ultimoServicio = '—';
		}

		$colorDias = '#777';
		$diasTexto = '—';

		if (isset($item['dias']) && is_numeric($item['dias'])) {
			$diasNum = (int) $item['dias'];

			if ($diasNum === 0) {
				$dias = " days";
			} elseif ($diasNum === 1 || $diasNum === -1) {
				$dias = " day";
			} else {
				$dias = " days";
			}
			$diasTexto = $diasNum . $dias;

			// Obtener la frecuencia (por defecto 1 si no está definida)
			$frecuencia = isset($item['id_frecuencia_servicio'])
				? (int) $item['id_frecuencia_servicio']
				: 1;

			// Calcular umbral dinámico
			$umbral = 7 * $frecuencia;

			// Aplicar color según el umbral
			$colorDias = $diasNum >= $umbral ? '#2c7' : '#a33';
		} elseif (empty($item['ultimo_servicio']) || $item['ultimo_servicio'] === '0000-00-00') {
			$diasTexto = 'New customer';
			$colorDias = '#555';
		}

		$idCliente = (int) ($item['id_cliente'] ?? $item['id'] ?? 0);

		$m1_1 = $item['hora_aviso_usuario'];
		$m1_2 = $item['hora_finalizado'];

		$m2_1 = $item['hora_inicio_gps'];
		$m2_2 = $item['hora_fin_gps'];

		if (empty($m1_1) && empty($m1_1)) {
			$m1_0 = "/";
		} else {
			$m1_0 = "X";
		}

		if (empty($m2_1) && empty($m2_1)) {
			$m2_0 = "/";
		} else {
			$m2_0 = "X";
		}
		$m3_0 = " ";

		$claseColor1 = ($m1_0 === 'X') ? 'con_color' : 'sin_color';
		$claseColor2 = ($m2_0 === 'X') ? 'con_color' : 'sin_color';
		$claseColor3 = ($m3_0 === 'X') ? 'con_color' : 'sin_color';

		$html = "
			<div class=\"item-despacho dis_elem\" draggable=\"true\" data-id=\"{$idCliente}\" data-tipo=\"{$tipo}\">";
			if ($p_assing) {
				if ($si_existe) {
					$html .= "
					<div>
						<div class=\"formatoNombre dis_orienta\"># Service: {$cod_servicio} - {$nombreCliente} &nbsp;" . 
						($tiempo_servicio !== 'Unassigned' ? "<div class=\"formatoTiempo\">Service time: {$tiempo_servicio} min</div>" : "") . 
						"</div>
						<div class=\"formatoDireccion colorA\">{$direccion}</div>
						{$zona}
					</div>";
				} else {
					$html .= "
					<div>
						<div class=\"formatoNombre dis_orienta\">{$nombreCliente} &nbsp;" . 
						($tiempo_servicio !== 'Unassigned' ? "<div class=\"formatoTiempo\">Service time: {$tiempo_servicio} min</div>" : "") . 
						"</div>
						<div class=\"formatoDireccion colorA\">{$direccion}</div>
						{$zona}
					</div>";
				}
			} else {
				$html .= "
				<div>
					<div class=\"formatoNombre dis_orienta\">{$nombreCliente} &nbsp;" . 
					($tiempo_servicio !== 'Unassigned' ? "<div class=\"formatoTiempo\">Service time: {$tiempo_servicio} min</div>" : "") . 
					"</div>
					<div class=\"formatoDireccion colorA\">{$direccion}</div>
					{$zona}
				</div>";
			}
		$html .= "
				<div style=\"text-align: center;\">
					<div class=\"formatoRuta\" style=\"background: {$colorRuta};\"><span class=\"colorE\">Route:</span> $nombre_ruta</div>
					<div class=\"formatoNombre colorC\">{$dayWork}</div>
					<div class=\"formatoDireccion colorB\">{$concepto}</div>
				</div>
				<div class=\"formatoUltSer colorA;\">
					<div><span class=\"colorD\">Last:</span> {$ultimoServicio}</div>
					<div>
						<span class=\"colorD\">Days:</span>
						<span style=\"color: {$colorDias}; font-weight: bold; margin-left: 0.3rem;\">{$diasTexto}</span>
					</div>
				</div>
				<div>
					<div class=\"grid-motor\"> 
						<div class=\"grid-motor_01 m1_base\"> 
							M1
						</div>
						<div class=\"grid-motor_02 m2_base\"> 
							M2
						</div>
						<div class=\"grid-motor_03 m3_base\"> 
							M3
						</div>
						<div class=\"grid-motor_04 {$claseColor1} \"> 
							{$m1_0}
						</div>
						<div class=\"grid-motor_05 {$claseColor2} \"> 
							{$m2_0}
						</div>
						<div class=\"grid-motor_06 {$claseColor3} \"> 
							{$m3_0}
						</div>
					</div>
				</div>
			</div>
		";

		return $html;
	}

	function obtenerDiaSemana($fecha)
	{
		$diasMap = [
			'sunday' => 'SUNDAY',
			'monday' => 'MONDAY',
			'tuesday' => 'TUESDAY',
			'wednesday' => 'WEDNESDAY',
			'thursday' => 'THURSDAY',
			'friday' => 'FRIDAY',
			'saturday' => 'SATURDAY'
		];

		$dateTime = new \DateTime($fecha);
		$nombreDia = strtolower($dateTime->format('l')); // 'monday', 'tuesday', etc.

		return $diasMap[$nombreDia] ?? null; // null si no coincide (poco probable)
	}

	public function consultaServicios($id_servicio)
	{
		$where = "s.id_servicio = " . $id_servicio;
		$query = "
				SELECT
					s.id_servicio, s.id_cliente, c.nombre as cliente, s.id_direccion, s.fecha_programada, s.id_truck, s.id_crew_1, 
					s.id_crew_2, s.id_crew_3, s.id_crew_4, s.id_status, t.color AS crew_color_principal, s.dia_servicio, 
					s.finalizado, s.estado_servicio, s.estado_visita, s.hora_aviso_usuario, s.hora_finalizado, s.tipo_dia, 
					s.hora_inicio_gps, s.hora_fin_gps, d.direccion, d.id_geofence, d.lat, d.lng, t.nombre as truck, ac.id_address_clas, 
					CASE 
						WHEN s.id_status = 37 AND s.hora_aviso_usuario IS NOT NULL THEN 'Service Started'
						WHEN s.id_status = 38 AND s.hora_aviso_usuario IS NOT NULL AND s.hora_finalizado IS NOT NULL THEN 'Processed'
						WHEN s.id_status = 38 AND s.hora_aviso_usuario IS NULL AND s.hora_finalizado IS NOT NULL THEN 'Not Started, Finished'
						WHEN s.id_status = 40 THEN 'Rescheduled'
						WHEN s.id_status = 47 THEN 'Cancelled'
						WHEN s.id_status = 48 THEN 'Not Served'
						ELSE 'Pending'
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
				LEFT JOIN address_clas AS ac ON d.id_address_clas = ac.id_address_clas
				LEFT JOIN truck AS t ON s.id_truck = t.id_truck
				WHERE s.id_status != $this->id_status_historico
				AND s.id_servicio IN (
						SELECT DISTINCT id_servicio 
						FROM servicios 
						WHERE $where
					)
				GROUP BY s.id_servicio, s.id_cliente, s.id_direccion, s.id_truck, s.id_crew_1, 
					s.id_crew_2, s.id_crew_3, s.id_crew_4, s.id_status, t.color, 
					s.dia_servicio, s.finalizado, s.estado_servicio, s.estado_visita, 
					s.hora_aviso_usuario, s.hora_finalizado, s.tipo_dia, s.hora_inicio_gps, 
					s.hora_fin_gps, c.nombre, d.direccion, d.id_geofence, d.lat, d.lng, 
					t.nombre";

		$query .= " ORDER BY id_servicio DESC, FIELD(t.id_truck, 1,2,3,4,5,6,7,8,9,10,11,12), c.nombre";

		$servicios = $this->ejecutarConsulta($query, '', []);


		// === Obtener integrantes del crew ===
		$crew_integrantes = [];

		$ids_crew = [
			$servicios['id_crew_1'],
			$servicios['id_crew_2'],
			$servicios['id_crew_3'],
			$servicios['id_crew_4']
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
			'id_servicio' => $servicios['id_servicio'],
			'id_status' => $servicios['id_status'],
			'id_cliente' => $servicios['id_cliente'],
			'cliente' => $servicios['cliente'],
			'id_direccion' => $servicios['id_direccion'],
			'id_address_clas' => $servicios['id_address_clas'],
			'direccion' => $servicios['direccion'],
			'id_geofence' => $servicios['id_geofence'],
			'id_truck' => $servicios['id_truck'],
			'truck' => $servicios['truck'],
			'crew_color_principal' => $servicios['crew_color_principal'] ?? '#666666',
			'lat' => $servicios['lat'],
			'lng' => $servicios['lng'],
			'finalizado' => (bool) $servicios['finalizado'],
			'dia_servicio' => $servicios['dia_servicio'],
			'dia_servicio_ct' => $servicios['dia_servicio'],
			'crew_integrantes' => $crew_integrantes,
			'estado_visita' => $servicios['estado_visita'] ?? 'programado',
			'estado_servicio' => $servicios['estado_servicio'],
			'fecha_programada' => $servicios['fecha_programada'],
			'tipo_dia' => $servicios['tipo_dia'],
			'hora_aviso_usuario' => $servicios['hora_aviso_usuario'],
			'hora_finalizado' => $servicios['hora_finalizado'],
			's_status' => $servicios['s_status'],
			'status_m2' => $servicios['status_m2'],
			'hora_inicio_gps' => $servicios['hora_inicio_gps'],
			'hora_fin_gps' => $servicios['hora_fin_gps'],
			'evidencias' => []
		];


		// Si no tiene día asignado
		if (!$servicios['dia_servicio']) {
			$registro['estado_visita'] = 'sin_programar';
		}

		$htmlAsignados = $this->renderServicio($registro);

		http_response_code(response_code: 200);
		echo $htmlAsignados;
	}

	/**
	 * Añadir preservicios para un listado de clientes en una fecha
	 * Recibe array de ids de cliente y la fecha (YYYY-MM-DD)
	 */
	public function addPreservicios($clientes, $fecha, $id_ruta_new)
	{
		try {
			$created = [];
			foreach ($clientes as $id_cliente) {
				$id_cliente = (int)$id_cliente;
				if ($id_cliente <= 0) continue;

				// Intentar obtener una dirección asociada al contrato del cliente
				$q = "SELECT ct.id_contrato, ct.id_direccion, di.dia_ingles AS dia_servicio, fs.concepto, ct.id_ruta, 
							ru.nombre_ruta, ru.color_ruta						
						FROM contratos ct
						LEFT JOIN dias_semana di ON ct.id_dia_semana = di.id_dia_semana
						LEFT JOIN frecuencia_servicio fs ON ct.id_frecuencia_servicio = fs.id_frecuencia_servicio
						LEFT JOIN rutas ru ON ct.id_ruta = ru.id_ruta
						WHERE ct.id_cliente = :id_cliente LIMIT 1";

				try {
					$res = $this->ejecutarConsulta($q, '', [':id_cliente' => $id_cliente]);

					$id_direccion = $res['id_direccion'] ?? null;
					$dia_servicio = $res['dia_servicio'] ?? $this->obtenerDiaSemana($fecha);
					$meta = ['origin' => 'manual_from_ui', 'created_by' => $this->usuario_id ?? null];
					$id_ruta = !empty(trim($res['id_ruta'] ?? '')) ? $res['id_ruta'] : "";

					$datos = [
						['campo_nombre' => 'id_cliente', 'campo_marcador' => ':id_cliente', 'campo_valor' => $id_cliente],
						['campo_nombre' => 'id_direccion', 'campo_marcador' => ':id_direccion', 'campo_valor' => $id_direccion],
						['campo_nombre' => 'fecha_programada', 'campo_marcador' => ':fecha_programada', 'campo_valor' => $fecha],
						['campo_nombre' => 'dia_servicio', 'campo_marcador' => ':dia_servicio', 'campo_valor' => $dia_servicio],
						['campo_nombre' => 'estado_servicio', 'campo_marcador' => ':estado_servicio', 'campo_valor' => 'pendiente'],
						['campo_nombre' => 'id_status', 'campo_marcador' => ':id_status', 'campo_valor' => 37],
						['campo_nombre' => 'created_meta', 'campo_marcador' => ':created_meta', 'campo_valor' => json_encode($meta)],
					];

					// ✅ Agregar id_ruta SOLO si no está vacío
					if (!empty($id_ruta)) {
						$datos[] = [
							'campo_nombre' => 'id_ruta',
							'campo_marcador' => ':id_ruta',
							'campo_valor' => $id_ruta
						];
					}

					if (!empty($id_ruta_new)) {
						$datos[] = [
							'campo_nombre' => 'id_ruta_new',
							'campo_marcador' => ':id_ruta_new',
							'campo_valor' => $id_ruta_new
						];
					}

					try {
						$id_preservicio = $this->guardarDatos('preservicios', $datos);
						$created[] = $id_preservicio;
					} catch (Exception $e) {
						$this->logWithBacktrace("Error guardando preservicio para cliente {$id_cliente}: " . $e->getMessage(), true);
					}
				} catch (Exception $e) {
					$this->logWithBacktrace("Error ubicando direccion y demas datos del cliente {$id_cliente}: " . $e->getMessage(), true);
				}
			}

			echo json_encode(['success' => true, 'created' => $created]);
			return;
		} catch (Exception $e) {
			$this->logWithBacktrace("addPreservicios error: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'Error adding preservicios']);
			return;
		}
	}

	/**
	 * Eliminar preservicios (pendientes) para una lista de clientes y fecha
	 */
	public function removePreservicios(array $clientes, string $fecha)
	{
		try {
			// Sanear lista
			$clientesInt = array_filter(array_map('intval', $clientes));
			if (empty($clientesInt)) {
				echo json_encode(['success' => true, 'deleted' => []]);
				return;
			}

			$placeholders = implode(',', array_fill(0, count($clientesInt), '?'));
			$sql = "DELETE FROM preservicios WHERE DATE(fecha_programada) = ? AND transferred = 0 AND id_cliente IN ($placeholders)";
			$params = array_merge([$fecha], $clientesInt);

			$this->ejecutarConsulta($sql, '', $params);

			echo json_encode(['success' => true, 'deleted' => $clientesInt]);
			return;
		} catch (Exception $e) {
			$this->logWithBacktrace("removePreservicios error: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'Error removing preservicios']);
			return;
		}
	}
	private function renderServicio($registro)
	{
		// Reutilizar tus funciones (asegúrate de que estén accesibles)
		$fechaData = $this->formatearFechaParaDisplay($registro['fecha_programada']);
		$statusBg = $this->getBackgoundStatus($registro['id_status']);
		$colorContraste = $this->getContrastColor($statusBg);

		ob_start(); // Iniciar buffer de salida
?>
		<h3 style="margin-top:0; color: #333;">Service Detail #<?php echo htmlspecialchars($registro['id_servicio']); ?></h3>

		<div class="fila-dato" style="background: <?php echo $statusBg; ?>; padding: 12px; border-radius: 6px;">
			<div
				style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 14px;">

				<!-- Fecha -->
				<div>
					<strong>Date:</strong><br>
					<div style="font-size: 16px; margin-top: 4px;">
						<div><?php echo $fechaData['ano']; ?></div>
						<div><?php echo $fechaData['mes']; ?></div>
						<div><?php echo $fechaData['dia']; ?></div>
					</div>
				</div>

				<!-- Cliente y Dirección -->
				<div>
					<strong>Customer:</strong><br>
					<span><?php echo htmlspecialchars($registro['cliente']); ?></span><br><br>
					<strong>Address:</strong><br>
					<span><?php echo htmlspecialchars($registro['direccion']); ?></span>
				</div>

				<!-- Vehículo y Estado -->
				<div>
					<strong>Truck:</strong><br>
					<div class="div_truck" style="background: <?php echo $registro['crew_color_principal']; ?>; 
								color: <?php echo $this->getContrastColor($registro['crew_color_principal']); ?>; 
								display: inline-block; padding: 4px 8px; border-radius: 4px; margin-top: 4px;">
						<?php echo htmlspecialchars($registro['truck']); ?>
					</div><br><br>
					<strong>Status:</strong><br>
					<span class="div_status" style="color: <?php echo $colorContraste; ?>; font-weight: bold;">
						<?php echo htmlspecialchars($registro['s_status']); ?>
					</span>
				</div>

				<!-- Crew -->
				<div>
					<strong>Crew:</strong>
					<div class="grid_crew" style="margin-top: 6px; display: flex; flex-wrap: wrap; gap: 4px;">
						<?php foreach ($registro['crew_integrantes'] as $crew): ?>
							<?php if (!empty(trim($crew['nombre_completo']))): ?>
								<div style="background: <?php echo $crew['color']; ?>; 
											color: <?php echo $this->getContrastColor($crew['color']); ?>; 
											padding: 4px 8px; border-radius: 4px; font-size: 13px;">
									<?php echo htmlspecialchars($crew['nombre_completo']); ?>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Tiempos -->
				<div>
					<strong>Service Times:</strong>
					<div class="grid_motor" style="font-size: 13px; margin-top: 6px; line-height: 1.5;">
						<div class="grid_motor01"><strong>Start (User):</strong><?php echo $this->extraerHoraDesdeDatetime($registro['hora_aviso_usuario']); ?></div>
						<div class="grid_motor02"><strong>Start (GPS):</strong> <?php echo $this->extraerHoraDesdeDatetime($registro['hora_inicio_gps']); ?></div>
						<div class="grid_motor03"><strong>End (User):</strong> <?php echo $this->extraerHoraDesdeDatetime($registro['hora_finalizado']); ?></div>
						<div class="grid_motor04"><strong>End (GPS):</strong> <?php echo $this->extraerHoraDesdeDatetime($registro['hora_fin_gps']); ?></div>
						<div class="grid_motor05"><strong>User Duration:</strong><?php echo $this->calcularTiempoTranscurrido($registro['hora_aviso_usuario'], $registro['hora_finalizado']); ?></div>
						<div class="grid_motor06"><strong>GPS Duration:</strong><?php echo $this->calcularTiempoTranscurrido($registro['hora_inicio_gps'], $registro['hora_fin_gps']); ?></div>
					</div>
				</div>

			</div>
		</div>
<?php
		return ob_get_clean(); // Devolver el HTML generado
	}

	private function formatearFechaParaDisplay($fecha_mysql)
	{
		if (!$fecha_mysql)
			return '';

		$fecha = new \DateTime($fecha_mysql);
		$ano = $fecha->format('Y');

		// Meses en inglés abreviados
		$mesesIngles = [
			'Jan',
			'Feb',
			'Mar',
			'Apr',
			'May',
			'Jun',
			'Jul',
			'Aug',
			'Sep',
			'Oct',
			'Nov',
			'Dec'
		];
		$mesAbrev = $mesesIngles[(int) $fecha->format('n') - 1]; // n = 1-12

		// Días en español
		$diasSemana = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
		$diaSemana = $diasSemana[(int) $fecha->format('w')]; // w = 0 (domingo) a 6

		$dia = $fecha->format('d');

		return [
			'ano' => $ano,
			'mes' => $mesAbrev,
			'dia' => "(" . $dia . ") " . $diaSemana
		];
	}

	private function getBackgoundStatus($id_status)
	{
		$lista_status = $this->status_servicios();

		foreach ($lista_status as $status) {
			if (isset($status['id_status']) && $status['id_status'] == $id_status) {
				return $status['color'];
			}
		}
		return '#cccccc'; // color por defecto si no se encuentra
	}

	private function getContrastColor($hexColor)
	{
		// Eliminar el símbolo # si existe
		$hex = ltrim($hexColor, '#');
		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if (strlen($hex) !== 6) {
			return '#000'; // fallback
		}

		// Convertir a RGB
		$r = hexdec(substr($hex, 0, 2));
		$g = hexdec(substr($hex, 2, 2));
		$b = hexdec(substr($hex, 4, 2));

		// Fórmula de luminancia relativa (WCAG)
		$luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

		// Si la luminancia es < 0.5, es oscuro → usar blanco; si no, negro
		return $luminance < 0.5 ? '#ffffff' : '#000000';
	}

	private function extraerHoraDesdeDatetime($fecha_hora)
	{
		// Definir valores considerados "vacíos"
		$valores_vacios = ['', null, '0000-00-00', '0000-00-00 00:00:00'];

		if (in_array($fecha_hora, $valores_vacios, true)) {
			return '---';
		}

		// Intentar crear un objeto DateTime
		try {
			$dt = new \DateTime($fecha_hora);
			return $dt->format('H:i'); // Ej: "14:30"
		} catch (Exception $e) {
			return '---'; // Si el formato es inválido
		}
	}

	private function calcularTiempoTranscurrido($fecha_inicio, $fecha_fin)
	{
		// Valores considerados "vacíos"
		$valores_vacios = ['', null, '0000-00-00 00:00:00', '0000-00-00'];

		if (in_array($fecha_inicio, $valores_vacios, true) || in_array($fecha_fin, $valores_vacios, true)) {
			return '---';
		}

		try {
			$inicio = new \DateTime($fecha_inicio);
			$fin = new \DateTime($fecha_fin);

			if ($fin < $inicio) {
				return '---'; // Fecha de fin no puede ser antes que la de inicio
			}

			$intervalo = $inicio->diff($fin);

			$partes = [];

			if ($intervalo->d > 0) {
				$partes[] = $intervalo->d . ' d' . ($intervalo->d > 1 ? 's' : '');
			}
			if ($intervalo->h > 0) {
				$partes[] = $intervalo->h . ' h' . ($intervalo->h > 1 ? 's' : '');
			}
			if ($intervalo->i > 0 || (empty($partes) && $intervalo->i === 0)) {
				// Si no hay días ni horas, al menos mostrar "0 minutes"
				$partes[] = $intervalo->i . ' m' . ($intervalo->i !== 1 ? 's' : '');
			}

			if (empty($partes)) {
				return '0 minutes';
			}

			return implode(' ', $partes);
		} catch (Exception $e) {
			return '---';
		}
	}

	private function verificar_servicio($fecha)
	{
		$sql = "SELECT COUNT(*) AS conteo
			FROM servicios
			WHERE fecha_programada = :v_fecha";
		$param = [':v_fecha' => $fecha];

		$result = $this->ejecutarConsulta($sql, '', $param);

		return $result['conteo'] > 0;
	}

	public function verificar_ruta($id_cliente, $fecha)
	{
		// 1 - Determinar el d{ia de la semana
		$timestamp = strtotime($fecha); // Convierte la cadena a un timestamp
		$dia_semana_numero = date('w', $timestamp) + 1; // 'w' devuelve el día numérico (0 = domingo, 6 = sábado)

		$sql = "SELECT dia_ingles 
					FROM dias_semana
					WHERE id_dia_semana = :v_id_dia_semana";
		$params = [
			":v_id_dia_semana" => $dia_semana_numero
		];

		$resultado = $this->ejecutarConsulta($sql, "", $params);
		$variable_php_upper = strtoupper($resultado['dia_ingles']);

		// 2 - Determinar las Rutas asignadas al dia seleccionado
		$sql = "SELECT rda.day_of_week, rda.id_ruta, r.nombre_ruta
					FROM route_day_assignments AS rda
					LEFT JOIN rutas AS r ON rda.id_ruta = r.id_ruta
					WHERE UPPER(day_of_week) = :v_day_of_week";
		$params = [
			":v_day_of_week" => $variable_php_upper
		];
		$rutas = $this->ejecutarConsulta($sql, "", $params, "fetchAll");

		// 3 - Determinar la ruta asignada al cliente en el contrato
		$sql = "SELECT c.id_ruta, r.nombre_ruta
					FROM contratos AS c
					LEFT JOIN rutas AS r ON c.id_ruta = r.id_ruta
					WHERE id_cliente = :v_id_cliente";
		$params = [
			":v_id_cliente" => $id_cliente
		];
		$cliente = $this->ejecutarConsulta($sql, "", $params);

		// 3 - Verifica si la ruta del Contrato del cliente está asignada en las rutas del dia seleccionado
		$asignado = false;
		foreach ($rutas as $s) {
			if ($s["id_ruta"] == $cliente["id_ruta"]) {
				$asignado = true;
			}
		}

		$cliente_format = '';
		if ($cliente['id_ruta'] !== null) {
			$cliente_format = '<p>The contract is associated with the route: ' . $cliente['nombre_ruta'] . '</p>';
		} else {
			$cliente_format = '<p>The contract does not have an associated route.</p>';
		}

		// 4 - Responder según corresponda "!$asignado" mostrara las rutas del dia seleccionado
		if (!$asignado) {
			$cadena = '';

			$cadena = '<option value="">Select a Route</option>';

			foreach ($rutas as $curr) {
				$cadena = $cadena . '<option value="' . $curr['id_ruta'] . '"> ';
				$cadena = $cadena . $curr['nombre_ruta'] . '</option>';
			}
			http_response_code(200);
			echo json_encode([
				'status' => 'false',
				'message' => $cadena,
				'cliente' => $cliente_format
			]);
			exit;
		} else {
			http_response_code(200);
			echo json_encode([
				'status' => 'ok',
				'message' => 'Assigned Route',
				'cliente' => $cliente_format
			]);
			exit;
		}
	}
}
?>