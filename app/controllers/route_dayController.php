<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
class route_dayController extends mainModel
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
		$nom_controlador = "route_dayController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/route_day/';

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

	public function rutas_disponibles($day){

		$sql = "SELECT r.id_ruta, r.nombre_ruta, r.color_ruta, 000000 AS clientes_compatibles
			FROM rutas r
			GROUP BY r.id_ruta, r.nombre_ruta, r.color_ruta
			ORDER BY r.nombre_ruta";
		$param = [];
		$result = $this->ejecutarConsulta($sql, "", $param, "fetchAll");

		$sql = "SELECT r.id_ruta, r.nombre_ruta, r.color_ruta, COUNT(ct.id_cliente) AS clientes_compatibles
			FROM rutas r
			LEFT JOIN rutas_zonas_cuadricula rz ON r.id_ruta = rz.id_ruta
			LEFT JOIN rutas_direcciones rd ON rz.id_ruta = rd.id_ruta
			LEFT JOIN direcciones d ON rd.id_direccion = d.id_direccion
			LEFT JOIN contratos ct ON d.id_direccion = ct.id_direccion
			LEFT JOIN dias_semana di ON ct.id_dia_semana = di.id_dia_semana
			WHERE ct.id_status = 18 
				AND di.id_dia_semana = :v_id_dia_semana
			GROUP BY r.id_ruta, r.nombre_ruta, r.color_ruta
			ORDER BY r.nombre_ruta";

		$param = [
			':v_id_dia_semana' => $this->valor_dia(strtoupper($day))
		];
		$totales = $this->ejecutarConsulta($sql, "", $param, "fetchAll");

		foreach($result as $row){
			$id_ruta = $row['id_ruta'];
			foreach($totales as $total){
				if($total['id_ruta'] == $id_ruta){
					$row['clientes_compatibles'] = $total['clientes_compatibles'];
					break;
				}	
			}
		}

		$tabla = '';

		if ($result) {
			$tabla .= '<div style="display:flex; flex-direction: column;">';
			foreach ($result as $ruta) {
				// Asegurar color válido
				$color = $ruta['color_ruta'] ?? '#f0f0f0';
				
				// --- SUAVIZAR EL COLOR (equivalente a softenColor en PHP) ---
				// Reutilizamos la función softenColor() que ya tienes
				$softColor = $this->softenColor($color, 0.8); // 80% blanco → muy tenue

				$tabla .= '
					<div 
						class="ruta-item" 
						data-ruta-id="' . htmlspecialchars($ruta['id_ruta']) . '" 
						style="
							padding: 8px;
							border: 1px solid #eee;
							margin-bottom: 5px;
							cursor: pointer;
							background-color: ' . htmlspecialchars($softColor) . ';
							display: flex;
							justify-content: space-between;
							align-items: center;
						"
						onclick="cargarClientesRuta(' . (int)$ruta['id_ruta'] . ')">
						
						<div>
							<strong>' . htmlspecialchars($ruta['nombre_ruta']) . '</strong><br>
							<small>Customers: <b>' . (int)$ruta['clientes_compatibles'] . '</b></small>
						</div>

						<label style="margin: 0; display: flex; align-items: center;">
							<input 
								type="checkbox" 
								name="rutas_seleccionadas[]" 
								value="' . (int)$ruta['id_ruta'] . '"
								style="margin: 0; transform: scale(1.2);"
								onclick=\'event.stopPropagation(); toggleRutaSeleccionada(
									' . (int)$ruta['id_ruta'] . ',
									' . json_encode($ruta['nombre_ruta']) . ',
									' . json_encode($ruta['color_ruta']) . ',
									this.checked
								);\'>
						</label>
					</div>';
			}
			$tabla .= '</div>';
		} else {
			$tabla .= '<p style="padding: 15px; color: #666;">No routes are configured.</p>';
		}

		return $tabla;
	}

	private function valor_dia($day) {
		$sql = "SELECT id_dia_semana 
			FROM dias_semana 
			WHERE dia_ingles = :v_dia_ingles 
			LIMIT 1";
		
		$params = [
			'v_dia_ingles' => $day
		];

		$result = $this->ejecutarConsulta($sql, "", $params);

		return $result['id_dia_semana'] ?? null;
	}

	private function softenColor($hex, $factor = 0.3) {
		// Asegurarse de que el # esté presente
		$hex = ltrim($hex, '#');
		if (strlen($hex) === 3) {
			$hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
		}
		if (strlen($hex) !== 6) {
			return '#ffffff'; // fallback
		}

		$r = hexdec(substr($hex, 0, 2));
		$g = hexdec(substr($hex, 2, 2));
		$b = hexdec(substr($hex, 4, 2));

		// Mezclar con blanco: new = color * (1 - factor) + white * factor
		// Esto aclara y reduce la intensidad
		$r = min(255, intval($r * (1 - $factor) + 255 * $factor));
		$g = min(255, intval($g * (1 - $factor) + 255 * $factor));
		$b = min(255, intval($b * (1 - $factor) + 255 * $factor));

		return sprintf('#%02X%02X%02X', $r, $g, $b);
	}	


	public function guardar_asignacion_dia($day, $route_ids){
		// Insertar nuevas asignaciones
		if (!empty($route_ids)) {
			try {
				$stmt = "DELETE FROM route_day_assignments WHERE day_of_week = :v_day_of_week";
				$params = [
					'v_day_of_week' => $day
				];
				$resultado = $this->ejecutarConsulta($stmt, "", $params);

				foreach ($route_ids as $id) {
					$datos = [
						['campo_nombre' => 'day_of_week', 'campo_marcador' => ':day_of_week', 'campo_valor' => $day],
						['campo_nombre' => 'id_ruta', 'campo_marcador' => ':id_ruta', 'campo_valor' => $id]
					];

					try {
						$salaId = $this->guardarDatos('route_day_assignments', $datos);
					} catch (Exception $e) {
						$this->logWithBacktrace("Error en Guardar Zona: " . print_r($datos, true));
					}
				}
			} catch (Exception $e) {
				error_log("Error saving route-day: " . $e->getMessage());
				return false;
			}				
		}
		return true;
	}

	public function cargar_zonas(){
		$stmt = "SELECT rda.day_of_week, rda.id_ruta, r.nombre_ruta, color_ruta
			FROM route_day_assignments AS rda
			LEFT JOIN rutas AS r ON r.id_ruta = rda.id_ruta
			ORDER BY rda.day_of_week, r.nombre_ruta";
		$params = [];

		$resultado = $this->ejecutarConsulta($stmt, "", $params, "fetchAll");
		$asignaciones = [];
		
		foreach ($resultado as $row) {
			$asignaciones[$row['day_of_week']][] = [
					'id'   => $row['id_ruta'],
					'name' => $row['nombre_ruta'],
					'color_ruta' => $row['color_ruta']
				];
		}
		return $asignaciones;
	}

	public function cliente_en_rutas($day, $id_ruta){
		$stmt = "SELECT 
				c.nombre AS cliente,
				di.dia_ingles AS dia_servicio
			FROM contratos ct
			JOIN clientes c ON ct.id_cliente = c.id_cliente
			JOIN direcciones d ON ct.id_direccion = d.id_direccion
			JOIN rutas_direcciones rd ON d.id_direccion = rd.id_direccion
			JOIN rutas_zonas_cuadricula rz ON rd.id_ruta = rz.id_ruta
			JOIN dias_semana di ON ct.id_dia_semana = di.id_dia_semana
			WHERE 
				ct.id_status = 18
				AND rz.id_ruta = :v_id_ruta
				AND di.dia_ingles = :v_dia_ingles
			ORDER BY c.nombre
		";

		$params = [
			':v_id_ruta' => $id_ruta,
			':v_dia_ingles' => $day
		];	

		$clientes = $this->ejecutarConsulta($stmt, "", $params, "fetchAll");

		$tabla = '';

		if ($clientes) {
			$tabla .= '<ul style="list-style: none; padding: 0;">';
			foreach ($clientes as $cliente) {
				$tabla .= 
				'<li style="padding: 6px 0; border-bottom: 1px solid #f0f0f0;">
					<strong>' . $cliente['cliente'] . '</strong> 
					<span style="color: #666; font-size: 0.9em;">' . $cliente['dia_servicio'] . '</span>
				</li>';
			}
			$tabla .= '</ul>';
		} else {
			$tabla .= '<p style="padding: 15px; color: #666;">This route does not have any customers with scheduled service for this day.</p>';
		}

		return $tabla;
	}
}
