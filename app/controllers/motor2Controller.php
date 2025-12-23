<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';
require_once APP_R_PROY . 'app/controllers/usuariosController.php';

use app\models\mainModel;
use app\controllers\usuariosController;

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

	private $id_status_activo;
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

		$this->id_status_activo = 26;

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
			// === 1. Consultar mapeo: Truck → ID Verizon (sin filtro de servicio) ===
			$sql = "
				SELECT DISTINCT 
					t.nombre, UPPER(t.nombre) AS id_verizon
				FROM truck t
				WHERE 
					t.id_status = :id_status_activo
					AND t.nombre = :nombre_truck
			";

			$params = [
				':nombre_truck' => $vehicle_id,
				':id_status_activo' => $this->id_status_activo
			];

			$result = $this->ejecutarConsulta($sql, "", $params, 'fetchAll');

			if (empty($result)) {
				$this->log("ERROR: Vehículo {$vehicle_id} no encontrado o inactivo");
				return [ // Retornar, no imprimir
					'error' => 'truck_not_found',
					'message' => 'Truck not found or inactive',
					'source' => 'system'
				];
			}

			$vehicle_nombre_bruto = $result[0]['nombre'];
			$device_id = $this->formatearVehicleNumber($vehicle_nombre_bruto);
			$this->log("Mapeo: {$vehicle_id} → DeviceID: {$device_id}");

			// === 2. Obtener token de acceso ===
			$token = $this->obtenerTokenVerizon();
			if (!$token) {
				$this->logWithBacktrace("No se pudo obtener token de Verizon", true);
				return [
					'error' => 'auth_failed',
					'message' => 'Could not get Verizon token',
					'source' => 'verizon_auth'
				];
			}

			// === 4. Consultar ubicación real del vehículo ===
			$encoded_vehicle = rawurlencode($device_id);
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

			$this->log("Respuesta HTTP: " . $http_code);
			$this->log("Paquete: " . print_r($response, true));

			// === CASO 1: Error de conexión cURL ===
			if ($curl_error) {
				$this->logWithBacktrace("cURL Error: {$curl_error}", true);
				return [
					'error' => 'connection_failed',
					'message' => 'Connection to Verizon failed',
					'source' => 'curl'
				];
			}

			// === CASO 2: Código HTTP diferente de 200 ===
			if ($http_code !== 200) {
				switch ($http_code) {
					case 204:
						// 📵 GPS apagado o sin señal
						$this->logWithBacktrace("📡 GPS 204: Vehículo {$vehicle_id} sin señal", true);
						// Registrar estado
						$this->registrarEstadoGPS($vehicle_id, 'offline', null, null);
						
						// ✅ Opcional: usar última posición conocida o sede
						$lat_backup = 30.3204272;
						$lng_backup = -95.4217815;

						$this->guardarCoordenada($vehicle_id, $lat_backup, $lng_backup, null, null);
						echo json_encode([
							'lat' => $lat_backup,
							'lng' => $lng_backup,
							'from_backup' => true,
							'message' => 'Vehicle offline, using hub location',
							'source' => 'backup_hub'
						]);
						return; // Salir después de guardar
												
					case 404:
						$this->logWithBacktrace("⚠️ 404: Dispositivo no encontrado para {$vehicle_id}", true);
						$this->registrarEstadoGPS($vehicle_id, 'not_registered', null, null);

						// ✅ Opcional: usar última posición conocida o sede
						$lat_backup = 30.3204272;
						$lng_backup = -95.4217815;

						$this->guardarCoordenada($vehicle_id, $lat_backup, $lng_backup, null, null);
						echo json_encode([
							'lat' => $lat_backup,
							'lng' => $lng_backup,
							'from_backup' => true,
							'message' => 'Device not found in Verizon system',
							'source' => 'verizon_api'
						]);
						return; // Salir después de guardar


					case 401:
					case 403:
						$this->logWithBacktrace("🔐 Error de autenticación con Verizon para {$vehicle_id}", true);
						return [
							'error' => 'authentication_error',
							'message' => "authentication_error",
							'source' => 'verizon_api',
							'status_code' => $http_code
						];

					default:
						$this->logWithBacktrace("❌ HTTP {$http_code} inesperado para {$vehicle_id}", true);
						return [
							'error' => 'http_error',
							'message' => "HTTP {$http_code} error",
							'source' => 'verizon_api',
							'status_code' => $http_code
						];
				}

/* 				// Para otros errores, usar sede solo si tiene servicio hoy
				$tieneServicioHoy = $this->tieneServicioHoy($vehicle_id);

				if ($tieneServicioHoy) {
					$this->logWithBacktrace("📍 GPS no disponible, pero tiene servicio hoy → usando sede como referencia temporal", true);
					echo json_encode([
						'lat' => 30.3204272,
						'lng' => -95.4217815,
						'from_backup' => true,
						'message' => 'Truck has service today but GPS is unreachable',
						'source' => 'backup_hub'
					]);
				} else {
					$this->logWithBacktrace("🚫 Sin GPS ni servicio hoy para {$vehicle_id}", true);
					echo json_encode([
						'error' => 'no_data',
						'message' => 'No GPS data and no service today',
						'source' => 'system'
					]);
				}
				return;
 */			}

			// === CASO 3: Respuesta vacía ===
			if (empty($response)) {
				$this->logWithBacktrace("Respuesta vacía del GPS para {$device_id}", true);
				return [
					'error' => 'empty_response',
					'message' => 'Empty response from Verizon',
					'source' => 'verizon_api'
				];
			}

			// === CASO 4: Decodificación JSON fallida ===
			$data = json_decode($response, true);
			if (!is_array($data)) {
				$this->logWithBacktrace("Respuesta no JSON válida: " . substr($response, 0, 200), true);
				return [
					'error' => 'invalid_json',
					'message' => 'Invalid JSON response',
					'source' => 'verizon_api'
				];
			}

			// === CASO 5: Extraer coordenadas ===
			$lat = $data['Latitude'] ?? $data['latitude'] ?? null;
			$lng = $data['Longitude'] ?? $data['longitude'] ?? null;

			if (!is_numeric($lat) || !is_numeric($lng)) {
				$this->logWithBacktrace("Coordenadas no válidas para {$device_id}: lat={$lat}, lng={$lng}", true);
				return [
					'error' => 'invalid_coordinates',
					'message' => 'Missing or invalid coordinates',
					'source' => 'verizon_api'
				];
			}

			// Mapear otros campos
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

			// === 🔥 GUARDAR CON FILTRO DE MOVIMIENTO ===
			$this->guardarCoordenada(
				$vehicle_id,
				(float)$lat,
				(float)$lng,
				is_numeric($speed) ? (int)$speed : null,
				is_numeric($course) ? (int)$course : null
			);

			// También registrar como "online"
			$this->registrarEstadoGPS($vehicle_id, 'online', $lat, $lng);

			$this->log("✅ GPS procesado y guardado: {$vehicle_id} → {$lat}, {$lng}");
			echo json_encode($output);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error crítico en obtenerGpsVerizon: " . $e->getMessage(), true);
			return [
				'error' => 'internal_error',
				'message' => 'Internal server error',
				'source' => 'system'
			];
		}
	}

	private function tieneServicioHoy($truck) {
		$sql = "SELECT COUNT(*) 
				FROM servicios s
				LEFT JOIN truck t on s.id_truck = t.id_truck
				WHERE DATE(s.fecha_programada) = CURDATE() 
				AND t.nombre = :v_truck 
				AND s.id_status != 39";

		$params = [
			':v_truck' => $truck
		];
		$result = $this->ejecutarConsulta($sql, "", $params);

		if ($result->rowCount() > 0) {
			$row = $result->fetch();
			$count = $row[0];
			if ($count > 0) {
				return true;
			}else{
				return false;
			}
		} else {
			return false;
		}
	}

	private function registrarEstadoGPS($truck, $estado, $lat = null, $lng = null) {
		$datos = [
			['campo_nombre' => 'truck',		'campo_marcador' => ':truck',	'campo_valor' => $truck],
			['campo_nombre' => 'estado',	'campo_marcador' => ':estado',	'campo_valor' => $estado],
			['campo_nombre' => 'lat',    	'campo_marcador' => ':lat',		'campo_valor' => $lat],
			['campo_nombre' => 'lng',    	'campo_marcador' => ':lng',		'campo_valor' => $lng]
		];

		try {
			$this->guardarDatos('gps_estado_dispositivos', $datos); 
			$this->log("✅ Estado del vehiculo: {$truck} → {$estado} → {$lat}, {$lng}");
		} catch (Exception $e) {
			$this->logWithBacktrace("❌ Error al guardar estado del vehiculo: " . $e->getMessage(), true);
		}
	}

	public function obtenerGpsLocationIQSoloConsulta($apikey, $lat, $lng){
		$this->log("=== INICIO: Direccion Actual a partir de coordenadas ===");
		try {
			$url = "https://us1.locationiq.com/v1/reverse.php?key={$apikey}&lat={$lat}&lon={$lng}&format=json&";

			$this->log("Consultando GPS vía: {$url}.");

			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => [
					'Accept: application/json'
				],
				CURLOPT_TIMEOUT => 10,
				CURLOPT_SSL_VERIFYPEER => true
			]);

			$response = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$curl_error = curl_error($ch);
			curl_close($ch);

			if ($curl_error) {
				$this->logWithBacktrace("cURL Error: {$curl_error}", true);
				echo json_encode([
					'success' => false,
					'error' => 'Connection failed'
				]);
				return;
			}

			if ($http_code !== 200) {
				$this->logWithBacktrace("HTTP {$http_code} al obtener dirección", true);
				echo json_encode([
					'success' => false,
					'error' => 'Failed to fetch from LocationIQ'
				]);
				return;
			}

			if (empty($response)) {
				$this->logWithBacktrace("Respuesta vacía de LocationIQ", true);
				echo json_encode([
					'success' => false,
					'error' => 'Empty response'
				]);
				return;
			}

			$data = json_decode($response, true);
			if (!is_array($data)) {
				$this->logWithBacktrace("Respuesta no JSON válida de LocationIQ", true);
				echo json_encode([
					'success' => false,
					'error' => 'Invalid JSON'
				]);
				return;
			}

			// Puedes devolver el campo 'display_name' como dirección
			$direccion = $data['display_name'] ?? null;

			echo json_encode([
				'success' => true,
				'direccion' => $direccion,
				'data' => $data
			]);
			return;
						
		} catch (Exception $e) {
			$this->logWithBacktrace("Error crítico en obtenerGpsLocationIQSoloConsulta: " . $e->getMessage(), true);
			echo json_encode([
				'lat' => null,
				'lng' => null,
				'error' => 'Internal server error'
			]);
		}
	}

	public function obtenerGpsVerizonSoloConsulta($vehicle_id)
	{
		$this->log("=== INICIO: Posicion actual del Truck === Solicitud GPS para: {$vehicle_id}");

		try {
			// 1. Obtener el nombre formateado del vehículo
			$device_id = $this->formatearVehicleNumber($vehicle_id);

			// 2. Obtener token de acceso
			$token = $this->obtenerTokenVerizon();
			if (!$token) {
				$this->logWithBacktrace("No se pudo obtener token de Verizon", true);
				throw new Exception("No se pudo obtener token de Verizon");
			}

			// 3. Consultar ubicación real del vehículo
			$vehicle_number = $device_id; // Ej: "TRUCK 10"
			$encoded_vehicle = rawurlencode($vehicle_number); // → "TRUCK%2010"

			$url = "https://fim.api.us.fleetmatics.com/rad/v1/vehicles/{$encoded_vehicle}/location";

			$this->log("Consultando GPS vía: {$url}. Codigo de vehiculo: " . $encoded_vehicle);

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

			if ($curl_error) {
				$this->logWithBacktrace("cURL Error: {$curl_error}", true);
				echo json_encode([
					'lat' => null,
					'lng' => null,
					'error' => 'Connection failed'
				]);
				return;
			}

			if ($http_code !== 200) {
				$this->logWithBacktrace("HTTP {$http_code} al obtener GPS", true);
				echo json_encode([
					'lat' => null,
					'lng' => null,
					'error' => 'Failed to fetch from Verizon'
				]);
				return;
			}

			if (empty($response)) {
				$this->logWithBacktrace("Respuesta vacía del GPS", true);
				echo json_encode([
					'lat' => null,
					'lng' => null,
					'error' => 'Empty response'
				]);
				return;
			}

			$data = json_decode($response, true);
			if (!is_array($data)) {
				$this->logWithBacktrace("Respuesta no JSON válida de Verizon", true);
				echo json_encode([
					'lat' => null,
					'lng' => null,
					'error' => 'Invalid JSON'
				]);
				return;
			}

			$lat = $data['Latitude'] ?? $data['latitude'] ?? null;
			$lng = $data['Longitude'] ?? $data['longitude'] ?? null;

			if (!is_numeric($lat) || !is_numeric($lng)) {
				$this->logWithBacktrace("Coordenadas no válidas", true);
				echo json_encode([
					'lat' => null,
					'lng' => null,
					'error' => 'Missing or invalid coordinates'
				]);
				return;
			}

			$output = [
				'lat' => (float)$lat,
				'lng' => (float)$lng,
				'timestamp' => $data['UpdateUTC'] ?? $data['timestamp'] ?? null,
				'speed' => $data['Speed'] ?? $data['speedKmph'] ?? $data['speed'] ?? null,
				'course' => $data['Direction'] ?? $data['directionDegrees'] ?? $data['course'] ?? null,
				'source' => 'verizon_api'
			];

			echo json_encode($output);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error crítico en obtenerGpsVerizonSoloConsulta: " . $e->getMessage(), true);
			echo json_encode([
				'lat' => null,
				'lng' => null,
				'error' => 'Internal server error'
			]);
		}
	}

	public function guardarCoordenada($vehicle_id, $lat, $lng, $speed = null, $course = null) {
		// Al inicio de la función
		$fechaActual = date('Y-m-d');
		foreach ($_SESSION['gps_tracker_last'] as $clave => $valor) {
			$parts = explode('_', $clave);
			$fechaClave = end($parts);
			if ($fechaClave < $fechaActual) {
				unset($_SESSION['gps_tracker_last'][$clave]);
			}
		}

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

			// ✅ Opción 1: Redondeo (ya lo tienes)
			if ($nueva_lat == $anterior_lat && $nueva_lng == $anterior_lng) {
				$this->log("🔁 Coordenada duplicada para {$vehicle_id} (redondeo). No se guarda.");
				return;
			}

			// ✅ Opción 2: Distancia mínima (fallback adicional)
			$distancia = $this->calcularDistanciaMetros($lat, $lng, $anterior[0], $anterior[1]);
			$umbralMetros = 5;

			if ($distancia < $umbralMetros) {
				$this->log("📍 Movimiento menor a {$umbralMetros}m ({$distancia}m). No se guarda.");
				return;
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

		$maxIntentos = 3;
		for ($i = 1; $i <= $maxIntentos; $i++) {
			try {
				$this->guardarDatos('gps_tracker', $datos);
				// === 5. Actualizar MEMORIA con la nueva coordenada ===
				$this->ultimaCoordenada[$clave] = [$nueva_lat, $nueva_lng];
				$_SESSION['gps_tracker_last'][$clave] = [$nueva_lat, $nueva_lng];
				$this->log("✅ Coordenada guardada: {$vehicle_id} → {$nueva_lat}, {$nueva_lng}");
				break; // Éxito → salir del bucle
			} catch (Exception $e) {
				if ($i === $maxIntentos) {
					$this->logWithBacktrace("❌ Error al guardar coordenada: " . $e->getMessage(), true);
					throw $e;
				}
				sleep(1); // Esperar antes de reintento
			}
		}
	}

	// === FUNCIÓN AUXILIAR: calcularDistanciaMetros ===
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

		$timestamp1 = $fromTimeUtc ?? date('Y-m-d');
		$timestamp1 .= ' 00:00:00';
		$timestamp2 = $toTimeUtc ?? date('Y-m-d');
		$timestamp2 .= ' 23:59:59';

		$this->log("Parametros de Fechas del vehiculo $vehicle_id entre $timestamp1 y $timestamp2");

		$sql = "
			SELECT lat, lng, timestamp, speed, course
				FROM gps_tracker 
				WHERE vehicle_id = :v_vehicle_id 
				AND timestamp BETWEEN :v_timestamp1 AND :v_timestamp2
				ORDER BY timestamp ASC";

		$param = [
			':v_vehicle_id' => $vehicle_id,
			':v_timestamp1' => $timestamp1,
			':v_timestamp2' => $timestamp2
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

				try {
					$datos = [
						['campo_nombre' => 'verizon_vehicle_number', 'campo_marcador' => ':verizon_vehicle_number', 'campo_valor' => $nombre],
						['campo_nombre' => 'verizon_device_id', 'campo_marcador' => ':verizon_device_id', 'campo_valor' => $nombre]
					];
					$condicion = [
						'condicion_campo' => 'id_truck',
						'condicion_operador' => '=', 					
						'condicion_marcador' => ':id_truck',
						'condicion_valor' => $id
					];

					$filas = $this->actualizarDatos('truck', $datos, $condicion);

					http_response_code(200);
					echo json_encode(['success' => 'ok', 'message' => 'Update completed']);

				} catch (Exception $e) {
					$this->logWithBacktrace("Error en finalizarServicio: " . $e->getMessage(), true);
					http_response_code(500);
					echo json_encode(['error' => 'Could not update']);
				}

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

		// $sql = "
		// 	SELECT DISTINCT 
		// 		t.id_truck, t.nombre AS vehicle_id
		// 	FROM truck t
		// 	INNER JOIN servicios s ON t.id_truck = s.id_truck
		// 	WHERE 
		// 		DATE(s.fecha_programada) = CURDATE()
		// 		AND s.id_status != 39
		// 		AND t.id_status = 26
		// 	ORDER BY t.nombre
		// ";


		// Consulta de todos los vehiculos

		$sql = "
			SELECT DISTINCT 
				t.id_truck, t.nombre AS vehicle_id
			FROM truck t
			WHERE t.id_status = 26
			ORDER BY t.nombre
		";

		try {
			$result = $this->ejecutarConsulta($sql, '', [], 'fetchAll');
			
			if (!$result || !is_array($result)) {
				$this->log("INFO: No se encontraron trucks activos hoy");
				return [];
			}

			// Extraer solo los nombres
			$activos = array_column($result, 'vehicle_id', );
			$this->log("Trucks activos hoy: " . implode(', ', $activos));
			return $activos;

		} catch (Exception $e) {
			$this->logWithBacktrace("Error al obtener trucks activos: " . $e->getMessage(), true);
			return [];
		}
	}	

	public function obtenerTrucksActivosHoy_excl() {
		// $this->log("Solicitando lista de trucks activos hoy");

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

			$activos = array_column($result, 'vehicle_id', );
			// Extraer solo los nombres
			usort($activos, function($a, $b) {
				$numA = (int) preg_replace('/[^0-9]/', '', $a);
				$numB = (int) preg_replace('/[^0-9]/', '', $b);
				return $numA <=> $numB;
			});

			$this->log("Trucks activos hoy: " . implode(', ', $activos));
			return $activos;

		} catch (Exception $e) {
			$this->logWithBacktrace("Error al obtener trucks activos: " . $e->getMessage(), true);
			return [];
		}
	}	


	public function obtenerTrucksActivosHoy_Color($fecha_cal = null) {
		$this->log("Solicitando lista de trucks activos hoy con su color");

		if ($fecha_cal){
			$fecha_programada = $fecha_cal;
		} else {
			$fecha_programada = date('Y-m-d');
		}

		$sql = "
			SELECT DISTINCT 
				t.id_truck, t.nombre AS vehicle_id, t.color
			FROM truck t
			INNER JOIN servicios s ON t.id_truck = s.id_truck
			WHERE 
				DATE(s.fecha_programada) = :v_fecha_programada
				AND s.id_status != 39
				AND t.id_status = 26
			ORDER BY t.nombre
		";
		$param = [
			'v_fecha_programada' => $fecha_programada
		];

		try {
			$result = $this->ejecutarConsulta($sql, '', $param, 'fetchAll');
			
			if (!$result || !is_array($result)) { 
				$this->log("INFO: No se encontraron trucks activos hoy");
				return []; 
			}

			// Extraer solo los nombres
			$activos = array_map(function($fila){
				return [
					'truck' => $fila['vehicle_id'],
					'color' => $fila['color']
				];
			}, $result);
			$this->log("Trucks activos hoy: " . json_encode($activos));
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

    public function obtener_UH($vehicle_id, $limit){
        $sql = "SELECT lat, lng, timestamp 
					FROM gps_tracker 
					WHERE vehicle_id = :v_vehicle_id 
					ORDER BY timestamp DESC 
					LIMIT :v_limit";
        $param = [
			':v_vehicle_id' => $vehicle_id,
			':v_limit' => $limit
		];

        $historial = $this->ejecutarConsulta($sql, "", [], 'fetchAll');

        // Revertir orden para tener [antiguo, ..., reciente]
        $historial = array_reverse($historial);

        echo json_encode(['historial' => $historial]);
        exit();        
	}

    public function ultima_pos_truck($truck){
        $sql = "
            SELECT 
                lat, 
                lng, 
                timestamp,
                speed,
                course
            FROM gps_tracker 
            WHERE vehicle_id = :v_vehicle_id 
            AND origen = 'realtime'
            ORDER BY timestamp DESC 
            LIMIT 2
        ";
		$param = [
			':v_vehicle_id' => $truck
		];

        $puntos = $this->ejecutarConsulta($sql, "", $param, 'fetchAll');
		$this->log("Ultimos movimientos del vehiculo: " . $truck . " " . print_r($puntos,true));


		// ✅ Añadir hora del servidor
		$server_time = date('Y-m-d H:i:s');		
		
		if (count($puntos) === 0) {
            // No hay registros
            $param = [
				'message' => 'No GPS data',
				'cantidad' => count((array) $puntos),
				'success' => false,
				'puntos' => [],
				'server_time' => $server_time  
			];
		} else {
            $param = [
				'message' => 'Good Data',
				'cantidad' => count((array) $puntos),
				'success' => true,
				'puntos' => $puntos,
				'server_time' => $server_time  
			];
		}

		// Devolver ambos puntos (último y penúltimo)
        echo json_encode($param);
        exit;  
	}

	public function obtenerUltimoPuntoTodos()
	{
		$this->log("Solicitando movimientos de vehiculos en servicio");

		$timestamp1 = $fromTimeUtc ?? date('Y-m-d');
		$timestamp1 .= ' 00:00:00';
		$timestamp2 = $toTimeUtc ?? date('Y-m-d');
		$timestamp2 .= ' 23:59:59';

		$this->log("Parametros de Fechas de los vehiculos entre  $timestamp1 y $timestamp2");

		$sql = "
			SELECT g.vehicle_id as truck, g.lat, g.lng, g.timestamp, g.speed, g.course
				FROM greentrack_live.gps_tracker g
				INNER JOIN (
					SELECT 
						vehicle_id, 
						MAX(timestamp) AS max_timestamp
					FROM greentrack_live.gps_tracker
					WHERE timestamp BETWEEN :v_timestamp1 AND :v_timestamp2
					GROUP BY vehicle_id
				) ultimos ON g.vehicle_id = ultimos.vehicle_id AND g.timestamp = ultimos.max_timestamp
				ORDER BY g.vehicle_id;";

		$param = [
			':v_timestamp1' => $timestamp1,
			':v_timestamp2' => $timestamp2
		];

		try {
			$result = $this->ejecutarConsulta($sql, '', $param, 'fetchAll');
			
			$historial = [];
			if (!$result || !is_array($result)) {
				$this->log("INFO: No hay historial GPS para hoy");
				$historial[] = [
					'lat' => 30.3204272,
					'lng' => -95.4217815,
					'from_backup' => true,
					'message' => 'Truck not in service'
				];
				$this->log("✅ Sin Historial GPS BD devuelto " . count($historial) . " puntos");
			} else {
				// === CASO 2: Hay resultados → construir $historial  truck, lat, lng, timestamp ===
				foreach ($result as $row) {
					$historial[] = [
						'truck' => $row['truck'],
						'lat' => (float)$row['lat'],
						'lng' => (float)$row['lng'],
						'timestamp' => $row['timestamp']
					];
				}
				$this->log("✅ Historial GPS BD devuelto para todos los vehiculos: " . count($historial) . " puntos");
			}

			return $historial;

		} catch (Exception $e) {
			$this->logWithBacktrace("Error al obtener historial GPS BD: " . $e->getMessage(), true);
			return	[];
		}
	}

	public function obtenerRutaHoy($vehicle_id)
	{
		$timestamp1 = $fromTimeUtc ?? date('Y-m-d');
		$timestamp1 .= ' 00:00:00';
		$timestamp2 = $toTimeUtc ?? date('Y-m-d');
		$timestamp2 .= ' 23:59:59';

		$sql = "
			SELECT g.*, t.color
				FROM greentrack_live.gps_tracker g 
				LEFT JOIN greentrack_live.truck AS t ON g.vehicle_id = t.nombre COLLATE utf8mb4_unicode_ci
				WHERE g.vehicle_id = :v_vehicle_id 
				AND g.timestamp BETWEEN :v_timestamp1 AND :v_timestamp2
				ORDER BY g.timestamp ASC";

		$param = [
			':v_vehicle_id' => $vehicle_id,
			':v_timestamp1' => $timestamp1,
			':v_timestamp2' => $timestamp2
		];

		try {
			$result = $this->ejecutarConsulta($sql, '', $param, 'fetchAll');
			
			$historial = [];
			if (!$result || !is_array($result)) {
				$sql = "
					SELECT truck.color
						FROM truck 
						WHERE nombre = :v_vehicle_id";

				$param = [
					':v_vehicle_id' => $vehicle_id
				];
				$res_act = $this->ejecutarConsulta($sql, '', $param);
				$colorVehiculo = $res_act['color'] ?? '#333333';
				$nombreVehiculo = $vehicle_id;
				$sedeLat = 30.3204272;
				$sedeLng = -95.4217815;
				$latVehiculo = floatval($sedeLat);
				$lngVehiculo = floatval($sedeLng);

				$this->log("INFO: No hay historial GPS para $vehicle_id hoy");
				$dt = new DateTime($timestamp1);
				$hora = $dt->format('g:i A');


				$html1 = "
					<div style=\"
						background: {$colorVehiculo};
						color: #fff;
						font-size: 1.2em;
						font-weight: bold;
						padding: 12px 18px;
						border-radius: 8px;
						margin-bottom: 10px;
						letter-spacing: 1px;
						box-shadow: 0 2px 8px rgba(0,0,0,0.08);
					\">
						Vehicle: {$nombreVehiculo}
					</div>

					<div class=\"ele_coordenada\">
						<div style=\"margin-bottom: 4px;\">
							<b>Location:</b> 
							<span style=\"color:#0066cc\">{$latVehiculo}, {$lngVehiculo}</span>
							<b> H: {$hora}</b> 
						</div>
						<div style=\"margin-bottom: 4px;\">
							<b>Location:</b> <span style=\"color:#388e3c;\">At headquarters</span>
						</div>
						<div style=\"margin-bottom: 4px;\">
							<b>Distance:</b>0 meters
						</div>
					</div>";

				$this->log("✅ Sin Historial GPS BD devuelto para $vehicle_id: " . count($historial) . " puntos");
			} else {
				// === CASO 2: Hay resultados → construir $historial ===
				$html1 = ''; // ← Inicializar antes del bucle
				$mostrarTitulo = true; // opcional: usar una variable local

				foreach ($result as $row) {
					$lat = (float)$row['lat'];
					$lng = (float)$row['lng'];
					if (is_numeric($lat) || is_numeric($lng)) {
						// Verificar si las coordenadas son válidas
						$coordenadasValidas = ($lat !== 0.0 || $lng !== 0.0); // ajusta según tu lógica

						// Solo procesar filas con coordenadas válidas
						if (!$coordenadasValidas) {
							continue;
						}

						// Agregar el título solo la primera vez que haya datos válidos
						if ($mostrarTitulo) {
							$html1 .= "
								<div style=\"
									background: {$row['color']};
									color: #fff;
									font-size: 1.2em;
									font-weight: bold;
									padding: 12px 18px;
									border-radius: 8px;
									margin-bottom: 10px;
									letter-spacing: 1px;
									box-shadow: 0 2px 8px rgba(0,0,0,0.08);
								\">
									Vehicle: {$vehicle_id}
								</div>
							";
							$mostrarTitulo = false;
						}

						$vdata = ['lat' => $lat, 'lng' => $lng];
						$html1 .= $this->generarHtmlUbicacionVehiculo($vdata, $row['color'], $vehicle_id, $row['timestamp']);
					}
				}
				$this->log("✅ Historial GPS BD devuelto para $vehicle_id: " . count($historial) . " puntos");
			}

			echo json_encode([
				'success' => true,
				'rutas' => $html1
			]);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error al obtener historial GPS BD: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode([
				'success' => false,
				'truck' => $vehicle_id,
				'rutas' => [],
				'error' => 'Internal server error'
			]);
		}

	}

	// === FUNCIÓN: Generar HTML del modal de ubicación del vehículo ===
	private function generarHtmlUbicacionVehiculo($vdata, $colorVehiculo, $nombreVehiculo, $timestamp)
	{
		// Validar datos de entrada
		if (!isset($vdata['lat']) || !isset($vdata['lng'])) {
			return '<p>Error: Coordenadas no disponibles.</p>';
		}

		$dt = new DateTime($timestamp);
		$hora = $dt->format('g:i A');

		$latVehiculo = floatval($vdata['lat']);
		$lngVehiculo = floatval($vdata['lng']);

		// Coordenadas de la sede
		$sedeLat = 30.3204272;
		$sedeLng = -95.4217815;

		// Calcular distancia a la sede
		$distanciaSede = $this->calcularDistanciaMetros($latVehiculo, $lngVehiculo, $sedeLat, $sedeLng);
		$umbralMetros = defined('APP_UMBRAL_METROS') ? APP_UMBRAL_METROS : 150; // Ajusta según tu config
		$html = "";

		// 1. ¿Está en la sede?
		if ($distanciaSede <= $umbralMetros) {
		// Iniciar HTML
			$html .= "
				<div class=\"ele_coordenada\">
					<div style=\"margin-bottom: 4px;\">
						<b>Location:</b> 
						<span style=\"color:#0066cc\">{$latVehiculo}, {$lngVehiculo}</span>
						<b> H: {$hora}</b> 
					</div>
			";
			$html .= "
					<div style=\"margin-bottom: 4px;\">
						<b>Location:</b> <span style=\"color:#388e3c;\">At headquarters</span>
					</div>
					<div style=\"margin-bottom: 4px;\">
						<b>Distance:</b> " . round($distanciaSede) . " meters
					</div>
			";
		} else {
			// 2. Consultar propiedades desde la base de datos
			$propiedades = $this->listarPropiedades(); // Tu función que consulta DB
			$propiedadEncontrada = null;
			$dist = null;

			foreach ($propiedades as $prop) {
				if (floatval($prop['lat']) > 0 || floatval($prop['lng']) < 0) {
					$dist = $this->calcularDistanciaMetros(
						$latVehiculo,
						$lngVehiculo,
						floatval($prop['lat']),
						floatval($prop['lng'])
					);

					if ($dist <= $umbralMetros) {
						$propiedadEncontrada = $prop;
						break;
					}
				}
			}

			if ($propiedadEncontrada) {
				$html = "
					<div class=\"ele_coordenada\">
						<div style=\"margin-bottom: 4px;\">
							<b>Location:</b> <span style=\"color:#0066cc\">{$latVehiculo}, {$lngVehiculo}</span><b> H: {$hora}</b> 
						</div>
				";
				$html .= "
						<div style=\"margin-bottom: 4px;\">
							<b>Property:</b> <span style=\"color:#1565c0;\">{$propiedadEncontrada['nombre']}</span>
						</div>
						<div style=\"margin-bottom: 4px;\">
							<b>Address:</b> {$propiedadEncontrada['direccion']}
						</div>
						<div style=\"margin-bottom: 4px;\">
							<b>Distance:</b> " . round($dist) . " meters
						</div>
				";
			} else {
				// 3. Usar LocationIQ para geocodificación inversa
				$apiKey = "pk.1472af9e389d1d577738a28c25b3e620";
				$url = "https://us1.locationiq.com/v1/reverse.php?key={$apiKey}&lat={$latVehiculo}&lon={$lngVehiculo}&format=json";

				$ch = curl_init();
				curl_setopt_array($ch, [
					CURLOPT_URL => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT => 10,
					CURLOPT_USERAGENT => 'GreenTrack System'
				]);

				$response = curl_exec($ch);
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close($ch);

				if ($httpCode === 200) {
					$locationIQ = json_decode($response, true);
					$address = $locationIQ['display_name'] ?? null;

					$html = "
						<div class=\"ele_coordenada\">
							<div style=\"margin-bottom: 4px;\">
								<b>Location:</b> <span style=\"color:#0066cc\">{$latVehiculo}, {$lngVehiculo}</span><b> H: {$hora}</b> 
							</div>
					";

					if ($address) {
						$html .= "
							<div style=\"margin-bottom: 4px;\">
								<b>Address:</b> <span style=\"color:#388e3c;\">{$address}</span>
							</div>
						";
					} else {
						$html .= "
							<div style=\"margin-bottom: 4px;\">
								<b>Location:</b> <span style=\"color:#c62828;\">Not at any registered Location</span>
							</div>
						";
					}
				}
			}
		}

		$html .= "</div>";
		return $html;
	}

	private function listarPropiedades()
	{
		try {
			$sql = "SELECT c.nombre, d.direccion, d.lat, d.lng 
				FROM clientes c
				LEFT JOIN direcciones AS d ON c.id_cliente = d.id_cliente
				WHERE d.direccion IS NOT NULL AND d.lat IS NOT NULL AND d.lng IS NOT NULL AND d.cambio = 1";
			
			$param = [];
			
			$result = $this->ejecutarConsulta($sql, '', $param, "fetchAll");
			return $result;

		} catch (Exception $e) {
			error_log("Error al listar propiedades: " . $e->getMessage());
			return [];
		}
	}
}
