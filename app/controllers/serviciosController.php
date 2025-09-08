<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
class serviciosController extends mainModel
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
		// Nombre del controlador actual abreviado para reconocer el archivo
		$nom_controlador = "serviciosController";
		// ____________________________________________________________________

		$this->log_path = __DIR__ . '/../logs/greentrack/';

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
						s.crew_color_principal,
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
						$query_crew = "SELECT id_crew, nombre_completo, nombre, apellido, color, crew FROM crew WHERE id_crew = :id AND id_status = 32";
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

	public function listarServiciosConEstado()
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
						s.crew_color_principal,
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
						ct.day_work AS dia_servicio_ct 
					FROM servicios AS s
					LEFT JOIN clientes AS c ON s.id_cliente = c.id_cliente
					LEFT JOIN direcciones AS d ON c.id_cliente = d.id_cliente
					LEFT JOIN contratos AS ct ON s.id_cliente = ct.id_cliente
					LEFT JOIN truck AS t ON s.id_truck = t.id_truck
					WHERE DATE(s.fecha_programada) = :v_fecha_programada AND s.id_status != $this->id_status_historico
					ORDER BY 
						FIELD(t.id_truck, 1,2,3,4,5,6,11,15), 
						c.nombre";
			$params = [
				':v_fecha_programada' => date('Y-m-d')
			];

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
						$query_crew = "
							SELECT id_crew, nombre_completo, nombre, apellido, color, crew 
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
					'dia_servicio_ct' => $s['dia_servicio_ct'],
					'crew_integrantes' => $crew_integrantes,
					'estado_visita' => $s['estado_visita'] ?? 'programado',
					'estado_servicio' => $s['estado_servicio'],
					'tipo_dia' => $s['tipo_dia'],
					'hora_aviso_usuario' => $s['hora_aviso_usuario'],
					'hora_finalizado' => $s['hora_finalizado'],
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

	public function listarServiciosParaModal(){
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
						s.crew_color_principal,
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
						ct.day_work AS dia_servicio_ct 
					FROM servicios AS s
					LEFT JOIN clientes AS c ON s.id_cliente = c.id_cliente
					LEFT JOIN direcciones AS d ON c.id_cliente = d.id_cliente
					LEFT JOIN contratos AS ct ON s.id_cliente = ct.id_cliente
					LEFT JOIN truck AS t ON s.id_truck = t.id_truck
					WHERE DATE(s.fecha_programada) = :v_fecha_programada AND s.id_status != $this->id_status_historico
					ORDER BY c.nombre ASC";

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
						$query_crew = "
							SELECT id_crew, nombre_completo, nombre, apellido, color, crew 
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
					'dia_servicio_ct' => $s['dia_servicio_ct'],
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
			}else{			
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
						s.crew_color_principal,
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

			$this->log("Resultado de la consulta de detalle: " . print_r($result, true)	);

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

			$this->log("Resultado de la consulta de detalle con Crew: " . print_r($result, true)	);

			http_response_code(200);
			echo json_encode($result);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en obtenerServicioDetalle: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'Error al cargar servicio']);
		}
	}

	private function obtenerNotasHist($id_servicio){
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
					$query_crew = "SELECT id_crew, nombre, apellido, nombre_completo, color FROM crew WHERE id_crew = :id AND id_status = 32";
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

	public function procesarClientesDesdeMotor1($id_cliente, $nombre_cliente){

        if (empty($id_cliente) || empty($nombre_cliente)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Faltan datos']);
            return;
        }

        $query = "UPDATE clientes SET nombre = :nombre WHERE id_cliente = :id";
        $params = [
            ':nombre' => $nombre_cliente,
            ':id' => $id_cliente
        ];

        try {
            $this->ejecutarConsulta($query, 'clientes', $params);
			return [
				'status' => 'ok',
				'message' => 'Cliente actualizado'
			];

		} catch (Exception $e) {
			$this->log("Error crítico en Clientes Motor1: " . $e->getMessage(), true);
			return ['error' => 'Internal server error'];
        }
	}

	public function buscar_actualizacion($ultimo_tiempo){
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
						'color'  => $servicio['crew_1_color'] ?? '#666'
					];
				}
				if (!empty($servicio['crew_2_nombre'])) {
					$integrantes[] = [
						'nombre' => $servicio['crew_2_nombre'],
						'color'  => $servicio['crew_2_color'] ?? '#666'
					];
				}
				if (!empty($servicio['crew_3_nombre'])) {
					$integrantes[] = [
						'nombre' => $servicio['crew_3_nombre'],
						'color'  => $servicio['crew_3_color'] ?? '#666'
					];
				}
				if (!empty($servicio['crew_4_nombre'])) {
					$integrantes[] = [
						'nombre' => $servicio['crew_4_nombre'],
						'color'  => $servicio['crew_4_color'] ?? '#666'
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

	public function obtener_direcciones($cantidad){
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

	public function act_lat_long_direcciones($id_servicio, $lat, $lon){
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
}
?>