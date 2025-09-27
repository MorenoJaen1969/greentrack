<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
use DateTime;
use DateTimeZone;

class motor2Controller extends mainModel
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
		$nom_controlador = "motor2Controller";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/Motor2/';

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

	public function obtenerTokenVerizon()
	{
		$this->log("Solicitando nuevo token a Verizon FIM API (GET)");

		// Si ya tenemos un token válido, devolverlo
		if ($this->ultimoToken && $this->tokenExpiraEn > time()) {
			$this->log("Reutilizando token existente");
			return $this->ultimoToken;
		}

		$credentials = base64_encode(VERIZON_USERNAME . ':' . VERIZON_PASSWORD);

		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL => VERIZON_TOKEN_URL . '?grant_type=client_credentials',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPGET => true,
			CURLOPT_HTTPHEADER => [
				'Authorization: Basic ' . $credentials,
				'Accept: application/json'
			],
			CURLOPT_TIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => true
		]);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);
		curl_close($ch);

		// === CASO 1: Error de conexión cURL ===
		if ($curl_error) {
			$mensaje = "cURL Error al obtener token: {$curl_error}";
			$this->logWithBacktrace($mensaje, true);
			return false;
		}

		// === CASO 2: Código HTTP diferente de 200 ===
		if ($http_code !== 200) {
			$mensaje = "Error HTTP {$http_code} al obtener token. Respuesta: " . substr($response ?? '', 0, 200);
			$this->logWithBacktrace($mensaje, true);
			return false;
		}

		// === CASO 3: Respuesta vacía ===
		if (empty($response)) {
			$mensaje = "Respuesta vacía del endpoint de token";
			$this->logWithBacktrace($mensaje, true);
			return false;
		}

		// === CASO 4: Validar que la respuesta sea un JWT válido (no JSON) ===
		$token = trim($response);

		if (empty($token)) {
			$this->logWithBacktrace("Respuesta vacía del endpoint de token", true);
			return false;
		}

		if (!str_starts_with($token, 'ey')) {
			$mensaje = "El token recibido no es un JWT válido. Respuesta: " . substr($token, 0, 200);
			$this->logWithBacktrace($mensaje, true);
			return false;
		}

		$this->ultimoToken = $token;
		$this->tokenExpiraEn = time() + (VERIZON_TOKEN_TTL_MINUTES * 60);
		$this->log("Nuevo token JWT obtenido y almacenado (expira en " . VERIZON_TOKEN_TTL_MINUTES . " min)");
		return $this->ultimoToken;
	}

	public function getTrucksEnServicioConVerizonMap()
	{
		$this->log("Solicitando lista de trucks en servicio con mapeo Verizon");

		try {
			$sql = "
				SELECT DISTINCT 
					t.id_truck,
					t.nombre,
					COALESCE(tm.id_verizon, CONCAT('VCN-', UPPER(t.nombre))) AS id_verizon
				FROM greentrack_live.truck t
				INNER JOIN greentrack_live.servicios s ON t.id_truck = s.id_truck
				LEFT JOIN greentrack_live.truck_mapping tm ON t.id_truck = tm.id_truck
				WHERE 
					DATE(s.fecha_programada) = CURDATE()
					AND s.id_status != 39
					AND t.id_status = 1
				ORDER BY t.nombre
			";

			$result = $this->ejecutarConsulta($sql);

			if ($result && is_array($result)) {
				$this->log("Trucks encontrados: " . count($result));
				return $result;
			} else {
				$this->log("No se encontraron trucks en servicio hoy");
				return [];
			}

		} catch (Exception $e) {
			$this->logWithBacktrace("Error al obtener trucks en servicio: " . $e->getMessage(), true);
			return [];
		}
	}

	public function obtenerGpsVerizon($vehicle_id)
	{
		$this->log("=== INICIO: obtenerGpsVerizon === Solicitud GPS para: {$vehicle_id}");

		try {
			// === 1. Validar que el truck exista y esté en servicio hoy ===
			$sql_status = "SELECT * FROM status_all 
				WHERE tabla LIKE '%truck%' 
				AND status LIKE '%Activo%'";

			$result_status = $this->ejecutarConsulta($sql_status, '', [], 'fetchAll');

			if (!$result_status || !isset($result_status[0]['id_status'])) {
				$this->logWithBacktrace("No se encontró status Activo para truck ", true);
				throw new Exception("No se encontró status Activo para truck");
			}

			$id_status_truck_activo = $result_status[0]['id_status'];		// Esto deveria devolver 26. Lo voy a dejar fijo  a ver que pasa

			// === 2. Consultar mapeo dinámico: Truck → ID Verizon ===
			$sql = "
				SELECT DISTINCT 
					t.nombre, UPPER(t.nombre) AS id_verizon
				FROM truck t
				INNER JOIN servicios s ON t.id_truck = s.id_truck
				WHERE 
					DATE(s.fecha_programada) = CURDATE()
					AND s.id_status != 39
					AND t.id_status = 26
					AND t.nombre = :nombre_truck
			";

			$params = [
				':nombre_truck' => $vehicle_id
			];

			$result = $this->ejecutarConsulta($sql, "", $params, 'fetchAll');

			if (empty($result)) {
				$this->log("INFO: El vehículo {$vehicle_id} no está programado hoy");
				echo json_encode([
					'lat' => 30.3204272,
					'lng' => -95.4217815,
					'from_backup' => true,
					'message' => 'Truck not in service today'
				]);
				return;
			}

			// Obtener el nombre original del vehículo
			$vehicle_nombre_bruto = $result[0]['nombre']; // Ej: TRUCK 2

			// Formatearlo para que coincida con Verizon
			$device_id = $this->formatearVehicleNumber($vehicle_nombre_bruto);

			$this->log("Mapeo: {$vehicle_id} → DeviceID: {$device_id}");

			// === 3. Obtener token de acceso ===
			$token = $this->obtenerTokenVerizon();
			if (!$token) {
				$this->logWithBacktrace("No se pudo obtener token de Verizon", true);
				throw new Exception("No se pudo obtener token de Verizon");
			}

			// === 4. Consultar ubicación real del vehículo ===
			// === Usar vehicle_number (no deviceId) y codificar correctamente ===
			$vehicle_number = $device_id; // Ej: "TRUCK 10"
			$encoded_vehicle = rawurlencode($vehicle_number); // → "TRUCK%2010"

			$url = "https://fim.api.us.fleetmatics.com/rad/v1/vehicles/{$encoded_vehicle}/location";

			$this->log("Consultando GPS vía: {$url}"); 

			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => [
					'Authorization: Atmosphere atmosphere_app_id=' . VERIZON_APP_ID . ', Bearer ' . $token,
					'Accept: application/json'
				],
				CURLOPT_TIMEOUT => 10,
				CURLOPT_SSL_VERIFYPEER => true
			]);

			$response = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$curl_error = curl_error($ch);
			curl_close($ch);

$this->log("Respuesta: " . $http_code);
$this->log("Paquete: " . print_r($response,true));


			// === CASO 1: Error de conexión cURL ===
			if ($curl_error) {
				$mensaje = "cURL Error al obtener GPS de {$device_id}: {$curl_error}";
				$this->logWithBacktrace($mensaje, true);
				echo json_encode([
					'lat' => 30.3204272,
					'lng' => -95.4217815,
					'from_backup' => true,
					'error' => 'Connection failed'
				]);
				return;
			}

			// === CASO 2: Código HTTP diferente de 200 ===
			if ($http_code !== 200) {
				// Caso especial: vehículo sin actividad reciente
				if ($http_code >= 201 || $http_code <= 299 ){
					if ($http_code === 204) {
						$this->log("INFO: Vehículo {$vehicle_id} está detenido sin actividad reciente");
						
						// Aún así, si tiene servicio hoy, devolvemos sede como punto de partida
						echo json_encode([
							'lat' => 30.3204272,
							'lng' => -95.4217815,
							'from_backup' => true,
							'message' => 'Vehicle inactive but in service',
							'source' => 'backup_hub'
						]);
						return;
					} else {
						$this->log("INFO: Vehículo {$vehicle_id} posee una condicion. HTTP: {$http_code}");
						// Aún así, si tiene servicio hoy, devolvemos sede como punto de partida
						echo json_encode([
							'lat' => 30.3204272,
							'lng' => -95.4217815,
							'from_backup' => true,
							'message' => 'Vehicle inactive but in service',
							'source' => 'backup_hub'
						]);
						return;
					}
				}

				// Otros errores sí son críticos
				$mensaje = "HTTP {$http_code} al obtener GPS de {$device_id}. Respuesta: " . substr($response ?? '', 0, 200);
				$this->logWithBacktrace($mensaje, true);
				echo json_encode([
					'lat' => 30.3204272,
					'lng' => -95.4217815,
					'from_backup' => true,
					'error' => 'Failed to fetch from Verizon'
				]);
				return;
			}

			// === CASO 3: Respuesta vacía ===
			if (empty($response)) {
				$mensaje = "Respuesta vacía del GPS para {$device_id}";
				$this->logWithBacktrace($mensaje, true);
				echo json_encode([
					'lat' => 30.3204272,
					'lng' => -95.4217815,
					'from_backup' => true,
					'error' => 'Empty response'
				]);
				return;
			}

			// === CASO 4: Decodificación JSON fallida ===
			$data = json_decode($response, true);
			if (!is_array($data)) {
				$mensaje = "Respuesta no JSON válida de Verizon para {$device_id}: " . substr($response, 0, 200);
				$this->logWithBacktrace($mensaje, true);
				echo json_encode([
					'lat' => 30.3204272,
					'lng' => -95.4217815,
					'from_backup' => true,
					'error' => 'Invalid JSON'
				]);
				return;
			}

			// === CASO 5: Extraer coordenadas con tolerancia a mayúsculas ===
			$lat = $data['Latitude'] ?? $data['latitude'] ?? null;
			$lng = $data['Longitude'] ?? $data['longitude'] ?? null;

			if (!is_numeric($lat) || !is_numeric($lng)) {
				$mensaje = "Coordenadas no válidas para {$device_id}: lat={$lat}, lng={$lng}";
				$this->logWithBacktrace($mensaje, true);
				echo json_encode([
					'lat' => 30.3204272,
					'lng' => -95.4217815,
					'from_backup' => true,
					'error' => 'Missing or invalid coordinates'
				]);
				return;
			}

			// Mapear otros campos con tolerancia
			$speed = $data['Speed'] ?? $data['speedKmph'] ?? $data['speed'] ?? null;
			$course = $data['Direction'] ?? $data['directionDegrees'] ?? $data['course'] ?? null;
			$timestamp = $data['UpdateUTC'] ?? $data['timestamp'] ?? null;

			$output = [
				'lat' => (float)$lat,
				'lng' => (float)$lng,
				'timestamp' => $timestamp,
				'speed' => is_numeric($speed) ? (float)$speed : null,
				'course' => is_numeric($course) ? (float)$course : null,
				'source' => 'verizon_api'
			];

			$this->guardarCoordenada(
				$vehicle_id,
				(float)$lat,
				(float)$lng,
				is_numeric($speed) ? (int)$speed : null,
				is_numeric($course) ? (int)$course : null
			);

			$this->log("GPS OK: {$vehicle_id} → {$output['lat']}, {$output['lng']}");
			echo json_encode($output);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error crítico en obtenerGpsVerizon: " . $e->getMessage(), true);
			echo json_encode([
				'lat' => 30.3204272,
				'lng' => -95.4217815,
				'from_backup' => true,
				'error' => 'Internal server error'
			]);
		}
	}

	public function guardarCoordenada($vehicle_id, $lat, $lng, $speed = null, $course = null) {
		// === 1. Clave única por vehículo y día ===
		$clave = $vehicle_id . '_' . date('Y-m-d');

		// === 1. Obtener última coordenada: SESSION → luego $this->ultimaCoordenada ===
		$anterior = null;

		if (isset($_SESSION['gps_tracker_last'][$clave])) {
			// Prioridad 1: Sesión (persiste entre requests)
			$anterior = $_SESSION['gps_tracker_last'][$clave];
			$this->log("📍 Usando coordenada de SESSION para {$vehicle_id}");
			
		} elseif (isset($this->ultimaCoordenada[$clave])) {
			// Prioridad 2: Memoria del objeto (fallback)
			$anterior = $this->ultimaCoordenada[$clave];
			$this->log("📍 Usando coordenada de memoria local para {$vehicle_id}");
			
			// Opcional: Recuperar en SESSION para próxima petición
			$_SESSION['gps_tracker_last'][$clave] = $this->ultimaCoordenada[$clave];
			$this->log("🔁 Sincronizando memoria → SESSION");
		}
	

		// === 2. Formatear nueva coordenada (redondear a 6 decimales) ===
		$nueva_lat = round($lat, 6);
		$nueva_lng = round($lng, 6);

		// === 3. Si ya existe y es igual, NO GUARDAR ===
		if ($anterior) {
			$anterior_lat = round($anterior[0], 6);
			$anterior_lng = round($anterior[1], 6);

			if ($nueva_lat == $anterior_lat && $nueva_lng == $anterior_lng) {
				$this->log("🔁 Coordenada duplicada para {$vehicle_id}. No se guarda.");
				return; // ← Salida temprana: no se inserta en BD
			}
		}

		// === 4. Si es diferente, entonces SÍ guardar ===
		$datos = [
			['campo_nombre' => 'vehicle_id',   'campo_marcador' => ':vehicle_id',   'campo_valor' => $vehicle_id],
			['campo_nombre' => 'lat',          'campo_marcador' => ':lat',          'campo_valor' => $nueva_lat],
			['campo_nombre' => 'lng',          'campo_marcador' => ':lng',          'campo_valor' => $nueva_lng],
			['campo_nombre' => 'speed',    	   'campo_marcador' => ':speed',    	'campo_valor' => $speed],
			['campo_nombre' => 'course',       'campo_marcador' => ':course',       'campo_valor' => $course],
			['campo_nombre' => 'origen',       'campo_marcador' => ':origen',       'campo_valor' => 'realtime']
		];

		try {
			$this->guardarDatos('gps_tracker', $datos);
			// === 5. Actualizar MEMORIA con la nueva coordenada ===
			$this->ultimaCoordenada[$clave] = [$nueva_lat, $nueva_lng];
	        $_SESSION['gps_tracker_last'][$clave] = [$nueva_lat, $nueva_lng];
			$this->log("✅ Coordenada guardada: {$vehicle_id} → {$nueva_lat}, {$nueva_lng}");
		} catch (Exception $e) {
			$this->logWithBacktrace("❌ Error al guardar coordenada: " . $e->getMessage(), true);
		}
	}

	/**
	 * Obtiene posiciones históricas de un vehículo en un rango de tiempo
	 */
	public function obtenerHistoricoVerizon($vehicle_id, $fromTimeUtc = null, $toTimeUtc = null) {
		$this->log("=== INICIO: obtenerHistoricoVerizon ===");
		$this->log("Solicitando histórico para: {$vehicle_id}");

		try {
			// === 1. Validar entrada y establecer tiempos por defecto (últimos 30 minutos) ===
			$now = new DateTime('now', new DateTimeZone('UTC'));
			$toTime = $toTimeUtc ? new DateTime($toTimeUtc, new DateTimeZone('UTC')) : $now;
			$fromTime = $fromTimeUtc ? new DateTime($fromTimeUtc, new DateTimeZone('UTC')) : clone $toTime;

			if (!$fromTimeUtc && !$toTimeUtc) {
				$fromTime->modify('-30 minutes'); // Últimos 30 minutos por defecto
			}

			$fromStr = $fromTime->format('c'); // ISO8601
			$toStr = $toTime->format('c');

			$this->log("Rango solicitado 1: " . $fromTimeUtc . " → " . $toTimeUtc);
			$this->log("Rango solicitado 2: {$fromStr} → {$toStr}");

			// === 2. Obtener deviceId desde la base de datos ===
			$sql = "
				SELECT verizon_device_id 
					FROM truck 
					WHERE nombre = :nombre_truck";

			$result = $this->ejecutarConsulta($sql, '', [':nombre_truck' => $vehicle_id]);

			if (!$result || empty($result['verizon_device_id'])) {
				throw new Exception("No se encontró verizon_device_id para {$vehicle_id}");
			}

			$deviceId = $result['verizon_device_id'];
			$this->log("Usando device_id: {$deviceId}");

			// === 3. Obtener token válido ===
			$token = $this->obtenerTokenVerizon();
			if (!$token) {
				throw new Exception("No se pudo obtener token de Verizon");
			}

			// === 4. Llamar al endpoint de histórico ===
			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_URL => VERIZON_PLOTS_URL,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => json_encode([
					'deviceIds' => [$deviceId],
					'fromTimeUtc' => $fromStr,
					'toTimeUtc' => $toStr
				]),
				CURLOPT_HTTPHEADER => [
					'Authorization: Atmosphere atmosphere_app_id=' . VERIZON_APP_ID . ', Bearer ' . $token,
					'Accept: application/json',
					'Content-Type: application/json'
				],
				CURLOPT_TIMEOUT => 30,
				CURLOPT_SSL_VERIFYPEER => true
			]);

			$response = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$curl_error = curl_error($ch);
			curl_close($ch);

			// === 5. Manejar respuesta ===
			if ($curl_error) {
				$mensaje = "cURL Error al obtener histórico de {$deviceId}: {$curl_error}";
				$this->logWithBacktrace($mensaje, true);
				return false;
			}

			if ($http_code !== 200) {
				$mensaje = "HTTP {$http_code} al obtener histórico de {$deviceId}. Respuesta: " . substr($response ?? '', 0, 200);
				$this->logWithBacktrace($mensaje, true);
				return false;
			}

			$data = json_decode($response, true);
			if (!is_array($data) || !isset($data['plots']) || !is_array($data['plots'])) {
				$this->logWithBacktrace("Estructura de respuesta inválida para histórico de {$deviceId}", true);
				return false;
			}

			$plots = $data['plots'];
			$this->log("Histórico recibido: " . count($plots) . " puntos para {$vehicle_id}");

			// === 6. Guardar cada punto en gps_tracker ===
			$guardados = 0;
			foreach ($plots as $plot) {
				$lat = $plot['latitude'] ?? null;
				$lng = $plot['longitude'] ?? null;
				$speed = $plot['speedKmph'] ?? null;
				$course = $plot['directionDegrees'] ?? null;

				if (!is_numeric($lat) || !is_numeric($lng)) continue;

				try {
					$this->guardarCoordenada($vehicle_id, $lat, $lng, $speed, $course);
					$guardados++;
				} catch (Exception $e) {
					$this->logWithBacktrace("Error al guardar plot individual: " . $e->getMessage(), true);
				}
			}

			$this->log("Histórico procesado: {$guardados} de " . count($plots) . " puntos guardados");

		} catch (Exception $e) {
			$this->logWithBacktrace("Error crítico en obtenerHistoricoVerizon: " . $e->getMessage(), true);
		}
	}

	public function obtenerHistorialGPS_bd($vehicle_id, $fromTimeUtc = null, $toTimeUtc = null) {
		$this->log("Solicitando historial GPS para: $vehicle_id desde $fromTimeUtc hasta $toTimeUtc");

		$sql = "
			SELECT lat, lng, speed, course, timestamp
				FROM gps_tracker 
				WHERE vehicle_id = :v_vehicle_id 
				AND timestamp BETWEEN :v_timestamp1 AND :v_timestamp2
				ORDER BY timestamp ASC";

		$param = [
			':v_vehicle_id' => $vehicle_id,
			':v_timestamp1' => $fromTimeUtc ?? date('Y-m-d') . ' 00:00:00',
			':v_timestamp2' => $toTimeUtc ?? date('Y-m-d') . ' 23:59:59'
		];

		try {
			$result = $this->ejecutarConsulta($sql, '', $param, 'fetchAll');
			
			$historial = [];
			if (!$result || !is_array($result)) {
				$this->log("INFO: No hay historial GPS para $vehicle_id hoy");
				$historial[] = [
					'lat' => 30.3204272,
					'lng' => -95.4217815,
					'from_backup' => true,
					'message' => 'Truck not in service'
				];
				$this->log("✅ Sin Historial GPS BD devuelto para $vehicle_id: " . count($historial) . " puntos");
			} else {
				// === CASO 2: Hay resultados → construir $historial ===
				foreach ($result as $row) {
					$historial[] = [
						'lat' => (float)$row['lat'],
						'lng' => (float)$row['lng'],
						'speed' => $row['speed'] !== null ? (float)$row['speed'] : null,
						'course' => $row['course'] !== null ? (float)$row['course'] : null,
						'timestamp' => $row['timestamp']
					];
				}
				$this->log("✅ Historial GPS BD devuelto para $vehicle_id: " . count($historial) . " puntos");
			}

			echo json_encode([
				'success' => true,
				'truck' => $vehicle_id,
				'historial' => $historial
			]);
		} catch (Exception $e) {
			$this->logWithBacktrace("Error al obtener historial GPS BD: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode([
				'success' => false,
				'truck' => $vehicle_id,
				'historial' => [],
				'error' => 'Internal server error'
			]);
		}
	}	


	/**
	 * Método público para migrar los trucks: copiar 'nombre' a nuevos campos Verizon
	 * Puede ser llamado desde scripts temporales
	 */
	public function migrarTrucksAVerizon() {
		$this->log("=== INICIO DE MIGRACIÓN: truck → verizon_vehicle_number / verizon_device_id ===");

		try {
			// Obtener todos los trucks
			$sql = "SELECT id_truck, nombre FROM truck";
			$trucks = $this->ejecutarConsulta($sql, '', [], 'fetchAll');

			if (!$trucks || !is_array($trucks)) {
				$this->log("No se encontraron registros en 'truck' o formato inválido");
				return;
			}

			$actualizados = 0;
			foreach ($trucks as $t) {
				$id = $t['id_truck'];
				$nombre = $t['nombre']; // Ej: TRUCK 1

				// Validar que tenga nombre
				if (empty($nombre)) {
					$this->log("Advertencia: id_truck={$id} tiene nombre vacío, omitido");
					continue;
				}

				// Preparar datos para actualizar
				$sql = "UPDATE truck
						SET verizon_vehicle_number = :v_verizon_vehicle_number, 
							verizon_device_id = :v_verizon_device_id
						WHERE id_truck = :v_id";

				$param = [
					':v_verizon_vehicle_number' => $nombre,
					':v_verizon_device_id' => $nombre,
					':v_id' => $id
				];

				$filas = $this->ejecutarConsulta($sql, "", $param);

				if ($filas !== null && $filas > 0) {
					$this->log("✅ Migrado: {$nombre} (id_truck={$id})");
					$actualizados++;
				}
			}

			$this->log("=== MIGRACIÓN COMPLETADA === {$actualizados} de " . count($trucks) . " registros actualizados.");

		} catch (Exception $e) {
			$this->logWithBacktrace("❌ Error crítico durante la migración: " . $e->getMessage(), true);
		}
	}	

	/**
	 * Formatea el nombre del truck para coincidir con el formato de Verizon
	 * Ej: TRUCK 2 → TRUCK 02, TRUCK 10 → TRUCK 10
	 */
	private function formatearVehicleNumber($nombre) {
		// Extraer solo el número después de "TRUCK"
		if (preg_match('/TRUCK\s+(\d+)/i', $nombre, $matches)) {
			$numero = (int)$matches[1];
			// Si es menor o igual a 9, añadir cero adelante
			$numeroFormateado = $numero <= 9 ? '0' . $numero : $numero;
			return 'TRUCK ' . $numeroFormateado;
		}
		// Si no coincide, devuelve el original (para evitar errores)
		return $nombre;
	}	

	/**
	 * Obtiene la lista de vehículos activos hoy (con servicio Activo)
	 */
	public function obtenerTrucksActivosHoy() {
		$this->log("Solicitando lista de trucks activos hoy");

		$sql = "
			SELECT DISTINCT 
				t.id_truck, t.nombre AS vehicle_id
			FROM truck t
			INNER JOIN servicios s ON t.id_truck = s.id_truck
			WHERE 
				DATE(s.fecha_programada) = CURDATE()
				AND s.id_status != 39
				AND t.id_status = 26
			ORDER BY t.nombre
		";

		try {
			$result = $this->ejecutarConsulta($sql, '', [], 'fetchAll');
			
			if (!$result || !is_array($result)) {
				$this->log("INFO: No se encontraron trucks activos hoy");
				return [];
			}

			// Extraer solo los nombres
			$activos = array_column($result, 'vehicle_id');
			$this->log("Trucks activos hoy: " . implode(', ', $activos));
			return $activos;

		} catch (Exception $e) {
			$this->logWithBacktrace("Error al obtener trucks activos: " . $e->getMessage(), true);
			return [];
		}
	}	

	public function obtenerHistorialGPS($vehicle_id) {
		//$this->log("Solicitando historial GPS para: $vehicle_id");

		$sql = "
			SELECT lat, lng, speed, course, timestamp 
				FROM gps_tracker 
				WHERE vehicle_id = ? 
				AND DATE(timestamp) = CURDATE()
				ORDER BY timestamp ASC
		";

		try {
			$result = $this->ejecutarConsulta($sql, '', [$vehicle_id], 'fetchAll');
			
			$historial = [];
			if (!$result || !is_array($result)) {
				//$this->log("INFO: No hay historial GPS para $vehicle_id hoy");
				$historial[] = [
					'lat' => 30.3204272,
					'lng' => -95.4217815,
					'from_backup' => true,
					'message' => 'Truck not in service'
				];
				//$this->log("✅ Sin Historial GPS devuelto para $vehicle_id: " . count($historial) . " puntos");
			} else {
				// === CASO 2: Hay resultados → construir $historial ===
				foreach ($result as $row) {
					$historial[] = [
						'lat' => (float)$row['lat'],
						'lng' => (float)$row['lng'],
						'speed' => $row['speed'] !== null ? (float)$row['speed'] : null,
						'course' => $row['course'] !== null ? (float)$row['course'] : null,
						'timestamp' => $row['timestamp']
					];
				}
				//$this->log("✅ Historial GPS devuelto para $vehicle_id: " . count($historial) . " puntos");
			}

			echo json_encode([
				'success' => true,
				'truck' => $vehicle_id,
				'historial' => $historial
			]);
		} catch (Exception $e) {
			$this->logWithBacktrace("Error al obtener historial GPS: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode([
				'success' => false,
				'truck' => $vehicle_id,
				'historial' => [],
				'error' => 'Internal server error'
			]);
		}
	}	

	public function obtenerUP($vehicle_id){
        $query = "
            SELECT lat, lng, timestamp 
				FROM gps_tracker 
				WHERE vehicle_id = :v_vehicle_id 
				ORDER BY timestamp DESC 
				LIMIT 1
        ";
		$params = [':v_vehicle_id' => $vehicle_id];

		$fila = $this->ejecutarConsulta($query, '', $params, 'fetchAll');
		$this->log("movimientos del vehiculo: " . $vehicle_id . " " . print_r($fila,true));

		if (is_array($fila) && count($fila) > 0 && isset($fila[0]['lat'], $fila[0]['lng'])) {
			http_response_code(200);
			echo json_encode([
				'lat' => floatval($fila[0]['lat']),
				'lng' => floatval($fila[0]['lng']),
				'hora_ser' => $fila[0]['timestamp']
			]);
		} else {
			// No hay datos, pero no es un error — es estado válido
			http_response_code(204); // No Content
			echo json_encode([
				'status' => 'no_data',
				'message' => 'No GPS signal for this vehicle'
			]);
			exit();
		}	
	}
}
