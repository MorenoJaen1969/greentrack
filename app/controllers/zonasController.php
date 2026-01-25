<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
class zonasController extends mainModel
{
	private $log_path;
	private $logFile;
	private $errorLogFile;

	private $o_f;

	public function __construct()
	{
		// ¡ESTA LÍNEA ES CRUCIAL!
		parent::__construct();

		// Nombre del controlador actual abreviado para reconocer el archivo
		$nom_controlador = "zonasController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/zonas/';

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

	public function generarCuadriculaPorCiudad($id_ciudad, $radio_km = 10) {
		// Obtener centro de la ciudad
		$sql = "SELECT nombre, lat, lng FROM ciudades WHERE id_ciudad = :id";
		$ciudad = $this->ejecutarConsulta($sql, '', [':id' => $id_ciudad]);
		if (!$ciudad || !$ciudad['lat'] || !$ciudad['lng']) {
			throw new Exception("Ciudad no válida o sin coordenadas");
		}

		$lat_centro = (float)$ciudad['lat'];
		$lng_centro = (float)$ciudad['lng'];

		// 🔑 Tamaño de celda FIJO (~1 km)
		$delta_lat_celda = 0.009; // ~1 km en latitud
		$delta_lng_celda = 0.012; // ~1 km en longitud

		// 🔑 Radio en grados (solo para definir límites del área)
		$delta_lat_radio = $radio_km * 0.009;      // radio en grados de lat
		$delta_lng_radio = $radio_km * 0.012 * 1.3; // compensar convergencia

		$lat_min = $lat_centro - $delta_lat_radio;
		$lat_max = $lat_centro + $delta_lat_radio;
		$lng_min = $lng_centro - $delta_lng_radio;
		$lng_max = $lng_centro + $delta_lng_radio;

		// Generar celdas
		$contador = 0;
		for ($lat = $lat_min; $lat < $lat_max; $lat += $delta_lat_celda) {
			for ($lng = $lng_min; $lng < $lng_max; $lng += $delta_lng_celda) {
				$lat_sw = round($lat, 8);
				$lng_sw = round($lng, 8);
				$lat_ne = round($lat + $delta_lat_celda, 8);
				$lng_ne = round($lng + $delta_lng_celda, 8);

				// Evitar celdas inválidas (opcional pero recomendado)
				if ($lat_sw >= $lat_ne || $lng_sw >= $lng_ne) continue;

				$nombre = 'ZONA_' . str_pad(++$contador, 5, '0', STR_PAD_LEFT);

				$logZonas = [
					['campo_nombre' => 'nombre_zona', 'campo_marcador' => ':nombre_zona', 'campo_valor' => $nombre],
					['campo_nombre' => 'lat_sw', 'campo_marcador' => ':lat_sw', 'campo_valor' => $lat_sw],
					['campo_nombre' => 'lng_sw', 'campo_marcador' => ':lng_sw', 'campo_valor' => $lng_sw],
					['campo_nombre' => 'lat_ne', 'campo_marcador' => ':lat_ne', 'campo_valor' => $lat_ne],
					['campo_nombre' => 'lng_ne', 'campo_marcador' => ':lng_ne', 'campo_valor' => $lng_ne],
					['campo_nombre' => 'id_ciudad_origen', 'campo_marcador' => ':id_ciudad_origen', 'campo_valor' => $id_ciudad]
				];

				$this->guardarDatos('zonas_cuadricula', $logZonas);
			}
		}
		return $contador;
	}
    
	public function generarCuadriculaConroe() {
        // Área de interés (Montgomery County + zonas cercanas)
        $lat_min = 30.10;
        $lat_max = 30.60;
        $lng_min = -95.80;
        $lng_max = -95.00;

        // Tamaño de celda en grados (~1 km)
        $delta_lat = 0.009; // ~1 km en latitud
        $delta_lng = 0.012; // ~1 km en longitud (en Texas)

        try {
            $contador = 1;
            for ($lat = $lat_min; $lat < $lat_max; $lat += $delta_lat) {
                for ($lng = $lng_min; $lng < $lng_max; $lng += $delta_lng) {
                    $lat_sw = round($lat, 8);
                    $lng_sw = round($lng, 8);
                    $lat_ne = round($lat + $delta_lat, 8);
                    $lng_ne = round($lng + $delta_lng, 8);

                    // Saltar celdas fuera del condado (opcional: usar polígono de límite real)
                    // Por ahora, todas las celdas dentro del rectángulo

                    $nombre = 'ZONA_' . str_pad($contador, 5, '0', STR_PAD_LEFT);

                    $logZonas = [
                        ['campo_nombre' => 'nombre_zona', 'campo_marcador' => ':nombre_zona', 'campo_valor' => $nombre],
                        ['campo_nombre' => 'lat_sw', 'campo_marcador' => ':lat_sw', 'campo_valor' => $lat_sw],
                        ['campo_nombre' => 'lng_sw', 'campo_marcador' => ':lng_sw', 'campo_valor' => $lng_sw],
                        ['campo_nombre' => 'lat_ne', 'campo_marcador' => ':lat_ne', 'campo_valor' => $lat_ne],
                        ['campo_nombre' => 'lng_ne', 'campo_marcador' => ':lng_ne', 'campo_valor' => $lng_ne]
                    ];

                    $this->log("Arreglo para crear Zonas Cuadriculas " . print_r($logZonas, true));

                    $this->guardarDatos('zonas_cuadricula', $logZonas);

                    $contador++;
                }
            }
            echo "Generadas $contador celdas.\n";
        } catch (Exception $e) {
            throw $e;
        }
    }

	public function crearZona($lat_sw, $lng_sw, $lat_ne, $lng_ne, $ids_direcciones, $nombre_zona = null, $id_ruta) {
		if (!$nombre_zona) {
			$nombre_zona = 'Zona ' . date('Y-m-d H:i');
		}

		return $this->crearZonaConDirecciones(
			$lat_sw, $lng_sw, $lat_ne, $lng_ne,
			$nombre_zona,
			$ids_direcciones, $id_ruta
		);
	}	

	private function crearZonaConDirecciones($lat_sw, $lng_sw, $lat_ne, $lng_ne, $nombre_zona, $ids_direcciones, $id_ruta) {
		// Normalizar límites (opcional, por seguridad)
		$min_lat = min($lat_sw, $lat_ne);
		$max_lat = max($lat_sw, $lat_ne);
		$min_lng = min($lng_sw, $lng_ne);
		$max_lng = max($lng_sw, $lng_ne);

		// Buscar zona EXISTENTE que coincida EXACTAMENTE con la celda
		$sql_buscar = " SELECT id_zona 
			FROM zonas_cuadricula 
			WHERE lat_sw = :lat_sw 
			AND lng_sw = :lng_sw 
			AND lat_ne = :lat_ne 
			AND lng_ne = :lng_ne
		";

		$param_buscar = [
			':lat_sw' => $min_lat,
			':lng_sw' => $min_lng,
			':lat_ne' => $max_lat,
			':lng_ne' => $max_lng
		];

		$resultado = $this->ejecutarConsulta($sql_buscar, '', $param_buscar);
		
		if (!$resultado) {
			// Insertar zona
			$logZonas = [
				['campo_nombre' => 'nombre_zona', 'campo_marcador' => ':nombre_zona', 'campo_valor' => $nombre_zona],
				['campo_nombre' => 'lat_sw', 'campo_marcador' => ':lat_sw', 'campo_valor' => $lat_sw],
				['campo_nombre' => 'lng_sw', 'campo_marcador' => ':lng_sw', 'campo_valor' => $lng_sw],
				['campo_nombre' => 'lat_ne', 'campo_marcador' => ':lat_ne', 'campo_valor' => $lat_ne],
				['campo_nombre' => 'lng_ne', 'campo_marcador' => ':lng_ne', 'campo_valor' => $lng_ne]
			];

			$this->log("Arreglo para crear Zonas Cuadriculas " . print_r($logZonas, true));

			$id_zona = $this->guardarDatos('zonas_cuadricula', $logZonas);
		} else {
			$id_zona = $resultado['id_zona'];
		}

        if ($id_ruta !== null) {
			try {
				foreach ($ids_direcciones as $id_dir) {
					// Evitar duplicados
					$existe = $this->ejecutarConsulta(
						"SELECT 1 FROM rutas_direcciones WHERE id_ruta = :id_ruta AND id_direccion = :id_dir",
						'', 
						[':id_ruta' => $id_ruta, ':id_dir' => $id_dir]
					);
					if (!$existe) {
						$this->guardarDatos('rutas_direcciones', [
							['campo_nombre' => 'id_ruta', 'campo_marcador' => ':id_ruta', 'campo_valor' => $id_ruta],
							['campo_nombre' => 'id_direccion', 'campo_marcador' => ':id_direccion', 'campo_valor' => $id_dir],
							['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1]
						]);
					}
				}				

				return $id_zona;

			} catch (Exception $e) {
				throw new \Exception("El registro no pudo ser creado: " . $e);
			}
		}
	}	

	public function listarDireccionesDeZona($id_zona) {
		$sql = "SELECT d.id_direccion, c.nombre AS cliente_nombre, d.direccion, d.lat, d.lng
			FROM rutas_direcciones rd
			JOIN rutas_zonas_cuadricula rzc ON rd.id_ruta = rzc.id_ruta
			JOIN direcciones d ON rd.id_direccion = d.id_direccion
			JOIN clientes c ON c.id_cliente = d.id_cliente
			WHERE rzc.id_zona = :id_zona
		";
		$params = [
			':id_zona' => $id_zona
		];
		return $this->ejecutarConsulta($sql, '', $params, "fetchAll");
	}

	public function eliminarDireccionDeZona($id_zona, $id_direccion) {
		$sql = "DELETE FROM rutas_direcciones 
			WHERE id_direccion = :id_direccion";
		$params = [
			':id_direccion' => $id_direccion
		];
		$this->ejecutarConsulta($sql, '', $params);
	}
}
