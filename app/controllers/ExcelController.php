<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;

class ExcelController extends mainModel{

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
		$nom_controlador = "ExcelController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/Excel/';

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

	public function procesarServiciosDesdeMotor1($fecha_servicio, $servicios_array)
	{
		try {
			$this->log("=== INICIO MOTOR 1 - Fecha: $fecha_servicio ==="); 

			// === Verificar si hay actualizaciones recientes ===
			$query = "SELECT COUNT(*) as total 
						FROM historial_servicios 
						WHERE fecha_registro > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)";
			$result = $this->ejecutarConsulta($query, '', []);
			if ($result && $result['total'] > 0) {
				return [
					'error' => 'Negado el proceso. Existen actualizaciones',
					'actualizaciones_recientes' => $result['total']
				];
			}

			// === 1. Cancelar servicios existentes para esta fecha ===
			$this->marcarServiciosComoHistorias($fecha_servicio);

			$insertados = 0;
			$registros = [];
			$errores = [];
			$crew = [];
			$vehiculo = [];
			foreach ($servicios_array as $index => $s) {
				try {
					$resultado = $this->insertarServicioDesdeMotor1($s, $index, $fecha_servicio);
					if ($resultado['success']) {
						$registros[] = "Registro $index: Id_Servicio->" . $resultado['id_servicio'];
						$insertados++;

						// Preparar datos completos para Motor 3
						$servicios_para_motor3[] = [
							'id_servicio' => $resultado['id_servicio'],
							'cliente' => $s['Nombre_del_cliente'] ?? 'Sin cliente',
							'crew' => $s['Crew'] ?? 'Sin crew',
							'vehiculo' => $s['truck'] ?? 'Sin vehículo',
							'direccion' => $resultado['direccion'] ?? 'Sin dirección',
							'color_crew' => $resultado['color_crew'] ?? 'Sin color',
							'telefono' => $s['Phone'] ?? '',
							'orden' => $s['orden'] ?? $index, // Si existe orden, usarlo, sino usar índice
							'fecha_servicio' => $fecha_servicio,
							// Agrega aquí cualquier otro campo que necesites para el PDF
							'notas' => $s['Notes'] ?? '',
							'tipo_servicio' => $s['Service_Type'] ?? '',
						];						
					} else {
						$errores[] = $resultado['error'];
						$this->logWithBacktrace("Error fila $index: " . print_r($errores,true), true);
						$this->logWithBacktrace("Registro con problema:" . print_r($s,true), true);
					}
				} catch (Exception $e) {
					$errores[] = "Fila $index: " . $e->getMessage();
					$this->logWithBacktrace("Error fila $index: " . $e->getMessage(), true);
				}
			}

			$this->log("Motor1 - Procesados: $insertados, Errores: " . count($errores));
			return [
				'status' => 'ok',
				'insertados' => $insertados,
				'validos' => $registros,
				'servicios_para_motor3' => $servicios_para_motor3,
				'errores' => $errores,
				'detalles' => $errores,
				'fecha_servicio' => $fecha_servicio
			];

		} catch (Exception $e) {
			$this->log("Error crítico en Motor1: " . $e->getMessage(), true);
			return ['error' => 'Internal server error'];
		}
	}

	private function insertarServicioDesdeMotor1($s, $index, $fecha_servicio)
	{
		// Validar campos obligatorios
		$campos_requeridos = ['Nombre_del_cliente', 'truck', 'dia_servicio', 'Crew'];
		foreach ($campos_requeridos as $campo) {
			if (empty($s[$campo])) {
				return ['success' => false, 'error' => "Fila $index: $campo es obligatorio"];
			}
		}

		// === 1. Validar CLIENTE ===
		$id_cliente = $this->validarCliente($s['Nombre_del_cliente']);
		if (!$id_cliente) {
			return ['success' => false, 'error' => "Fila $index: Cliente '{$s['Nombre_del_cliente']}' no encontrado"];
		}

		$direccion = $this->obtenerDireccionPrincipal($id_cliente);
		if (!$direccion) {
			return ['success' => false, 'error' => "Fila $index: Cliente sin dirección activa"];
		}

		// === 2. Validar TRUCK ===
		$truck = 'TRUCK ' . trim($s['truck']);

		$id_truck = $this->validarTruck($truck);
		if (!$id_truck) {
			return ['success' => false, 'error' => "Fila $index: Truck '{$s['truck']}' no válido"];
		}

		// === 3. Procesar CREW: Lista de nombres completos ===
		$nombres_crew = array_map('trim', explode(',', $s['Crew']));
		if (count($nombres_crew) < 1 || count($nombres_crew) > 4) {
			return ['success' => false, 'error' => "Fila $index: Crew debe tener entre 1 y 4 integrantes"];
		}
		if ($s['truck'] == 10){
			$this->log("Arreglo de Crew actual: " . json_encode($nombres_crew));
		}

		// Buscar cada integrante en la tabla `crew` por `nombre_completo`
		$ids_crew = [];
		for ($i = 1; $i <= 4; $i++) {
			$campo = "id_crew_$i";
			$ids_crew[$campo] = null;
		}

		for ($i = 0; $i < count($nombres_crew); $i++) {
			$nombre = $nombres_crew[$i];
			$query = "SELECT id_crew, color 
				FROM crew 
				WHERE nombre_completo = :nombre 
					AND id_status = 32";
			
			$params = [':nombre' => $nombre];
			$result = $this->ejecutarConsulta($query, '', $params);
			if (!$result) {
				http_response_code(404);
				echo json_encode(['error' => 'Servicio no encontrado']);
				return;
			}

			$ids_crew["id_crew_" . ($i + 1)] = $result['id_crew'];
		}

		// === 4. Obtener crew_color (del primer integrante o del truck) ===
		$crew_color_principal = $result['color'] ?? $this->obtenerColorPorTruck($id_truck);

		// === 5. Preparar datos para INSERT ===
		$dia_servicio = strtoupper(trim($s['dia_servicio']));
		$diasSemanales = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];
		$tipo_dia = in_array($dia_servicio, $diasSemanales) ? 'semanal' : 'fijo';

		$datos = [
			['campo_nombre' => 'id_cliente', 'campo_marcador' => ':id_cliente', 'campo_valor' => $id_cliente],
			['campo_nombre' => 'id_direccion', 'campo_marcador' => ':id_direccion', 'campo_valor' => $direccion['id_direccion']],
			['campo_nombre' => 'id_truck', 'campo_marcador' => ':id_truck', 'campo_valor' => $id_truck],
			['campo_nombre' => 'id_crew_grupo', 'campo_marcador' => ':id_crew_grupo', 'campo_valor' => 1], // Por ahora fijo
			['campo_nombre' => 'id_crew_1', 'campo_marcador' => ':id_crew_1', 'campo_valor' => $ids_crew['id_crew_1']],
			['campo_nombre' => 'id_crew_2', 'campo_marcador' => ':id_crew_2', 'campo_valor' => $ids_crew['id_crew_2']],
			['campo_nombre' => 'id_crew_3', 'campo_marcador' => ':id_crew_3', 'campo_valor' => $ids_crew['id_crew_3']],
			['campo_nombre' => 'id_crew_4', 'campo_marcador' => ':id_crew_4', 'campo_valor' => $ids_crew['id_crew_4']],
			['campo_nombre' => 'ruta_mapa', 'campo_marcador' => ':ruta_mapa', 'campo_valor' => (int) $s['ruta']],
			['campo_nombre' => 'dia_servicio', 'campo_marcador' => ':dia_servicio', 'campo_valor' => $dia_servicio],
			['campo_nombre' => 'crew_color_principal', 'campo_marcador' => ':crew_color_principal', 'campo_valor' => $crew_color_principal],
			['campo_nombre' => 'fecha_programada', 'campo_marcador' => ':fecha_programada', 'campo_valor' => $fecha_servicio],
			['campo_nombre' => 'estado_servicio', 'campo_marcador' => ':estado_servicio', 'campo_valor' => 'pendiente'],
			['campo_nombre' => 'id_status', 'campo_marcador' => ':id_status', 'campo_valor' => 37],
			['campo_nombre' => 'tipo_dia', 'campo_marcador' => ':tipo_dia', 'campo_valor' => $tipo_dia],
			['campo_nombre' => 'fecha_creacion', 'campo_marcador' => ':fecha_creacion', 'campo_valor' => date('Y-m-d H:i:s')]
		];

		try {
			$id_servicio = $this->guardarDatos('servicios', $datos);
			$this->log("Servicio insertado: Cliente={$s['Nombre_del_cliente']}, Crew=" . $s['Crew']);
			return ['success' => true, 'id_servicio'  => $id_servicio, 'direccion' => $direccion['direccion'], 'color_crew' => $crew_color_principal];
		} catch (Exception $e) {
			$this->logWithBacktrace("Error en Servicio: " . print_r($datos, true));
			return ['success' => false, 'error' => "Fila $index: Error al insertar: " . $e->getMessage()];
		}
	}

	private function validarCliente($nombre_cliente)
	{
		$query = "SELECT id_cliente FROM clientes WHERE nombre = :nombre AND id_status = 1";
		$params = [':nombre' => trim($nombre_cliente)];
		$result = $this->ejecutarConsulta($query, '', $params);
		return $result ? $result['id_cliente'] : false;
	}

    private function obtenerDireccionPrincipal($id_cliente)
	{
		$query = "SELECT id_direccion, direccion, lat, lng FROM direcciones WHERE id_cliente = :id_cliente AND id_status = 10 ORDER BY id_direccion LIMIT 1";
		$params = [':id_cliente' => $id_cliente];
		return $this->ejecutarConsulta($query, '', $params);
	}

	private function validarTruck($truck)
	{
		$query = "SELECT id_truck FROM truck WHERE nombre = :nombre AND id_status = 26";
		$params = [':nombre' => $truck];
		$result = $this->ejecutarConsulta($query, '', $params);
		return $result ? $result['id_truck'] : false;
	}

    private function marcarServiciosComoHistorias($fecha)
	{
		$query = "UPDATE servicios SET id_status = :id_status WHERE DATE(fecha_programada) = :fecha AND id_status = :status_activo";
		$params = [
			':id_status' => $this->id_status_historico,
			':fecha' => $fecha,
			':status_activo' => $this->id_status_activo
		];
		$cant_reg = $this->ejecutarConsulta($query, 'servicios', $params);
		$this->log("$cant_reg Servicios del $fecha marcados como Cancelados");
	}

	private function obtenerColorPorTruck($id_truck)
	{
		$colores = [
			1 => '#2196F3',
			2 => '#FF9800',
			3 => '#F44336',
			4 => '#00BCD4',
			5 => '#9C27B0',
			6 => '#4CAF50',
			7 => '#CDDC39',
			8 => '#FF5722',
			9 => '#abf52c',
			10 => '#8BC34A',
			11 => '#FFC107',
			12 => '#6b2e02',
			13 => '#9730db',
			15 => '#3F51B5'
		];
		return $colores[$id_truck] ?? '#666666';
	}

    public function procesarArchivoExcel($archivoExcel) {
        try {
            // 1. Procesar Excel
            $datosExcel = $this->leerYValidarExcel($archivoExcel);
            
            // 2. Procesar Motor 1 (datos principales)
            $resultadoMotor1 = $this->procesarMotor1($datosExcel);
            
            // 3. Generar documentos Motor 3
            $motor3Controller = new Motor3Controller();
            $paquetes = $motor3Controller->procesarPaqueteExcel($datosExcel);
            
            // 4. Retornar ambos paquetes
            return [
                'principal' => $paquetes['principal'],
                'documentos_motor3' => $paquetes['documentos']
            ];
            
        } catch (Exception $e) {
            return [
                'principal' => [
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ],
                'documentos_motor3' => null
            ];
        }
    }
}    
?>