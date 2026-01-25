<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';
require_once APP_R_PROY . 'app/controllers/usuariosController.php';

use app\models\mainModel;
use app\controllers\usuariosController;

use \Exception;
use DateTime;
use DateTimeZone;
use DateInterval;

class contratosController extends mainModel
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
		$nom_controlador = "contratosController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/Contratos/';

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

	public function listarcontratosControlador($dato_ori)
	{
		$pagina_contratos = $dato_ori[0];
		$registrosPorPagina = $dato_ori[1];
		$url1 = $dato_ori[2];
		$busca_frase = $dato_ori[3];
		$ruta_retorno = $dato_ori[4];
		$orden = $dato_ori[5];
		$direccion = $dato_ori[6];

		$pagina = (isset($pagina_contratos) && $pagina_contratos > 0) ? (int) $pagina_contratos : 1;
		$pagina = $this->limpiarCadena($pagina);
		$registros = $this->limpiarCadena($registrosPorPagina);

		$url = $this->limpiarCadena($url1);
		$url_origen = $url;
		$url = RUTA_APP . "/" . $url . "/";
		$tabla = "";

		$busqueda = $this->limpiarCadena($busca_frase);

		$inicio = ($pagina > 0) ? (($pagina * $registrosPorPagina) - $registrosPorPagina) : 0;

		$vencido_var = "Defeated";
		$meses_var = "Months";
		$semanas_var = "Weeks";
		$dias_var = "Days";

		$campos = "c.*, ct.nombre AS cliente_nombre, ct.apellido AS cliente_apellido, d.dia_ingles AS day_work, ar.descripcion as area_desc,
			ct.telefono AS cliente_telefono, ct.email AS cliente_email, s.status, fs.concepto, se.id_servicio, se.fecha_programada,
			CASE
				WHEN c.fecha_fin <= CURDATE() THEN '$vencido_var'
				ELSE
					CONCAT(
						FLOOR(TIMESTAMPDIFF(MONTH, CURDATE(), c.fecha_fin)), ' $meses_var ',
						FLOOR( (
							GREATEST(0,
								TIMESTAMPDIFF(DAY,
									DATE_ADD(CURDATE(), INTERVAL TIMESTAMPDIFF(MONTH, CURDATE(), c.fecha_fin) MONTH),
									c.fecha_fin
								)
							) - 1
						) / 7 ), ' $semanas_var ',
						( (
							GREATEST(0,
								TIMESTAMPDIFF(DAY,
									DATE_ADD(CURDATE(), INTERVAL TIMESTAMPDIFF(MONTH, CURDATE(), c.fecha_fin) MONTH),
									c.fecha_fin
								)
							) - 1
						) % 7 ) + 1, ' $dias_var'
					)
			END AS tiempo_restante			
		";

		$where = " c.id_status != 49 ";

		if (isset($busqueda) && $busqueda != "") {
			// === Generar la cláusula ORDER BY ===
			$orden_r = "ct.nombre";

			$campos_filtro = "ct.nombre LIKE '%$busqueda%' OR ct.apellido LIKE '%$busqueda%' OR d.dia_ingles LIKE '%$busqueda%'";

			$consulta_datos = "SELECT " . $campos . " 
                    FROM contratos c
					LEFT JOIN clientes AS ct ON ct.id_cliente = c.id_cliente 
					LEFT JOIN status_all AS s ON s.id_status = c.id_status 
					LEFT JOIN frecuencia_servicio AS fs ON fs.id_frecuencia_servicio = c.id_frecuencia_servicio
					LEFT JOIN dias_semana AS d ON d.id_dia_semana = c.id_dia_semana
					LEFT JOIN areas AS ar ON ar.id_area = c.id_area
					LEFT JOIN (
						SELECT 
							id_servicio,
							id_cliente,
							fecha_programada,
							ROW_NUMBER() OVER (PARTITION BY id_cliente ORDER BY fecha_programada DESC, id_servicio DESC) AS rn
						FROM servicios
					) AS se ON se.id_cliente = c.id_cliente AND se.rn = 1
					WHERE " . $where . " AND " . $campos_filtro .
				" ORDER BY " . $orden_r . "
                    LIMIT " . $inicio . ", " . $registros;

			$consulta_total = "SELECT COUNT(c.id_contrato) AS total 
                    FROM contratos c
					LEFT JOIN clientes AS ct ON ct.id_cliente = c.id_cliente 
					LEFT JOIN status_all AS s ON s.id_status = c.id_status 
					LEFT JOIN frecuencia_servicio AS fs ON fs.id_frecuencia_servicio = c.id_frecuencia_servicio
					LEFT JOIN dias_semana AS d ON d.id_dia_semana = c.id_dia_semana
					LEFT JOIN (
						SELECT 
							id_servicio,
							id_cliente,
							fecha_programada,
							ROW_NUMBER() OVER (PARTITION BY id_cliente ORDER BY fecha_programada DESC, id_servicio DESC) AS rn
						FROM servicios
					) AS se ON se.id_cliente = c.id_cliente AND se.rn = 1
					WHERE " . $where . " AND " . $campos_filtro;

		} else {
			// === Generar la cláusula ORDER BY ===
			$orden_r = "ct.nombre";

			// Consulta principal: lista todos los contratos con tiempo restante
			$consulta_datos = "SELECT " . $campos . " 
                    FROM contratos c
					LEFT JOIN clientes AS ct ON ct.id_cliente = c.id_cliente 
					LEFT JOIN status_all AS s ON s.id_status = c.id_status 
					LEFT JOIN frecuencia_servicio AS fs ON fs.id_frecuencia_servicio = c.id_frecuencia_servicio
					LEFT JOIN dias_semana AS d ON d.id_dia_semana = c.id_dia_semana
					LEFT JOIN areas AS ar ON ar.id_area = c.id_area
					LEFT JOIN (
						SELECT 
							id_servicio,
							id_cliente,
							fecha_programada,
							ROW_NUMBER() OVER (PARTITION BY id_cliente ORDER BY fecha_programada DESC, id_servicio DESC) AS rn
						FROM servicios
					) AS se ON se.id_cliente = c.id_cliente AND se.rn = 1
					WHERE " . $where .
				" ORDER BY " . $orden_r . "
                    LIMIT " . $inicio . ", " . $registros;

			// Conteo total de registros
			$consulta_total = "SELECT COUNT(c.id_contrato) AS total 
                    FROM contratos c
					LEFT JOIN clientes AS ct ON ct.id_cliente = c.id_cliente 
					LEFT JOIN status_all AS s ON s.id_status = c.id_status 
					LEFT JOIN frecuencia_servicio AS fs ON fs.id_frecuencia_servicio = c.id_frecuencia_servicio
					LEFT JOIN dias_semana AS d ON d.id_dia_semana = c.id_dia_semana
					LEFT JOIN (
						SELECT 
							id_servicio,
							id_cliente,
							fecha_programada,
							ROW_NUMBER() OVER (PARTITION BY id_cliente ORDER BY fecha_programada DESC, id_servicio DESC) AS rn
						FROM servicios
					) AS se ON se.id_cliente = c.id_cliente AND se.rn = 1
					WHERE " . $where;
		}

		$this->log("Consulta actual: " . $consulta_datos);
		$datos = $this->ejecutarConsulta($consulta_datos, "", [], "fetchAll");
		$this->log("Resultado de la consulta: " . json_encode($datos));

		$total = $this->ejecutarConsulta($consulta_total, "", [], "fetchColumn");
		$this->log("Conteo de Registros: " . json_encode($total));

		$numeroPaginas = ceil($total / $registros);

		if ($busqueda <> "") {
			$text_filtro = "Remove Filter: " . $busqueda;
			$tabla .= '
                    <div class="row">
                        <form class="Form_elimina" method="POST" autocomplete="off" >
                            <input type="submit" name="eli_fil" id="eli_fil" value="' . $text_filtro . '">
                        </form>
                    </div>
                    <br>
                ';
		}

		$tabla .= '
			<div class="table-container">
				<table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
					<thead class="cabecera">
						<tr>
							<th class="has-text-centered">#</th>
							<th class="has-text-centered">Contract</th>
							<th class="has-text-centered">Responsible</th>
							<th class="has-text-centered">Time Remaining</th>
							<th class="has-text-centered">Area</th>
							<th class="has-text-centered">Service Frequency</th>
							<th class="has-text-centered">Service Day</th>
							<th class="has-text-centered">Last Service</th>
							<th class="has-text-centered">Status</th>
							<th class="has-text-centered" colspan="3">Options</th>
						</tr>
					</thead>
					<tbody>
			';

		if ($total >= 1 && $pagina <= $numeroPaginas) {
			$contador = $inicio + 1;
			$pag_inicio = $inicio + 1;

			foreach ($datos as $rows) {

				$ruta_contratos = '/contratos/' . $pagina . '/';
				$ruta_destino = RUTA_APP . "/contratosVista/" . $rows['id_contrato'] . $ruta_contratos;

				$fecha_ini = $rows['fecha_ini'];
				$fecha_fin = $rows['fecha_fin'];

				// Inicializar fechas como null
				$inicio = null;
				$fin = null;

				// Convertir solo si no son NULL
				if ($fecha_ini !== null) {
					$inicio = new DateTime($fecha_ini);
				}
				if ($fecha_fin !== null) {
					$fin = new DateTime($fecha_fin);
				}

				$hoy = new DateTime();

				// Inicializar porcentaje por defecto
				$porcentaje = 0;

				// Solo calcular si AMBAS fechas existen
				if ($inicio !== null && $fin !== null) {
					if ($hoy <= $inicio) {
						$porcentaje = 0;
					} elseif ($hoy >= $fin) {
						$porcentaje = 100;
					} else {
						$total = $inicio->diff($fin)->days;
						// Evitar división por cero (aunque en teoría no debería ocurrir)
						if ($total > 0) {
							$pasados = $inicio->diff($hoy)->days;
							$porcentaje = ($pasados / $total) * 100;
						} else {
							$porcentaje = 0;
						}
					}
				} 

				// 4. Redondear para evitar decimales largos
				$pos = round($porcentaje, 1);	
				$leftPos = round($pos, 2); // Ej: 42.35
			

				$tabla .= '
                        <tr class="has-text-centered table-row">
                            <td>' . $contador . '</td>
                            <td>' . $this->ceros($rows['id_contrato']) . '</td>
                            <td>' . $rows['cliente_nombre'] . " " . $rows['cliente_apellido'] . '</td>
                            <td>
								<div style="width: 10vw; height: 25px; background: linear-gradient(to right, #4CAF50, #2196F3, #F44336);
									position: relative; border-radius: 8px; overflow: hidden; margin-right: auto; margin-left: auto;">
									<div style="position: absolute; top: 0; left: <?php echo $pos; ?>%; width: 2px; height: 25px; background: white;
										box-shadow: 0 0 0 1px #0005; "></div>
									<div style="position: absolute; top: 6px; left: calc(' . $leftPos . '% - 6px);
										width: 12px; height: 12px; background: white; border: 2px solid #333; border-radius: 50%; z-index: 3;
									"></div>										
								</div>
							</td>
                            <td>' . $rows['area_desc'] . '</td>
                            <td>' . $rows['concepto'] . '</td>
							<td>' . $rows['day_work'] . '</td>
							<td>' . "(# " . $rows['id_servicio'] . ") - (🗓️ " . $rows['fecha_programada'] . ')</td>
                            <td>' . $rows['status'] . '</td>
                            <td>
                                <a href="' . $ruta_destino . '" class="button is-link is-rounded is-small" id="a_id_contrato" name="a_id_contrato"><span class="fas fa-eye"></a>
                            </td>
                            <td>
                                <form class="FormularioAjax" action="' . APP_URL . 'app/ajax/contratosAjax.php" method="POST" autocomplete="off" >
                                    <input type="hidden" name="modulo_contratos" value="eliminar">
                                    <input type="hidden" name="id_contrato" id="id_contrato" value="' . $rows['id_contrato'] . '">
                                    <button type="submit" class="button is-danger is-rounded is-small">
                                        <span class="fas fa-trash"></span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    ';
				$contador++;
			}
			$pag_final = $contador - 1;
		} else {
			if ($total >= 1) {
				$tabla .= '
                        <tr class="has-text-centered" >
                            <td colspan="7">
                                <a href="' . $url . '1/" class="button is-link is-rounded is-small mt-4 mb-4">
                                    Click here to reload the list
                                </a>
                            </td>
                        </tr>
                    ';
			} else {
				$tabla .= '
                        <tr class="has-text-centered" >
                            <td colspan="7">
                                there are no records in the system
                            </td>
                        </tr>
                    ';
			}
		}

		$tabla .= '</tbody></table></div>';

		### Paginacion ###
		if ($total > 0 && $pagina <= $numeroPaginas) {
			$tabla .= '
                    <div class="vis_min">    
                        <p class="has-text-right">Displaying Contracts <strong>' . $pag_inicio . '</strong> to <strong>' . $pag_final . '</strong> of a total of <strong>' . $total . '</strong></p>
                    </div>';
			$id_codigos = [
				'id_obra' => 0,
				'id_partida' => 0
			];
			$tabla .= $this->paginadorTablas($pagina, $numeroPaginas, $url, 7, $id_codigos);
		}
		return $tabla;
	}

	public function crearContratos()
	{
		$query = "SELECT s.id_servicio, s.id_cliente, c.nombre AS cliente, s.id_direccion, d.direccion
				FROM servicios AS s
				LEFT JOIN clientes AS c ON s.id_cliente = c.id_cliente
				LEFT JOIN direcciones AS d ON s.id_direccion = d.id_direccion
				WHERE s.id_status != 39
				AND s.id_servicio IN (
						SELECT DISTINCT id_servicio 
						FROM servicios 
						WHERE id_status != 39
					)
				GROUP BY s.id_servicio, s.id_cliente, c.nombre, s.id_direccion, d.direccion
				ORDER BY s.id_servicio";

		$params = [];
		// if ($fecha) {
		// 	$query .= " AND DATE(s.fecha_programada) = :v_fecha_programada";
		// 	$params = [
		// 		':v_fecha_programada' => $fecha
		// 	];
		// } else {
		$query .= " AND DATE(s.fecha_programada) = :v_fecha_programada";
		$params = [
			':v_fecha_programada' => date('Y-m-d')
		];
		// }
		// if ($truck) {
		// 	$query .= " AND s.id_truck = :v_id_truck";
		// 	$params[':v_id_truck'] = $truck;
		// }
		$query .= " ORDER BY c.nombre";

		$this->log("Consulta de listarServicios_despachos(): " . $query);
		$this->log("Parametros: " . json_encode($params));

		$asignados = $this->ejecutarConsulta($query, '', $params, 'fetchAll');

	}

	public function contratoCompleto_contrato($parametros)
	{
		$id_contrato = $parametros['id_contrato'];

		$sql = "SELECT c.id_contrato, c.contrato_origen, c.id_cliente, c.nombre AS nom_contrato, cl.nombre, cl.apellido, cl.telefono, cl.email, c.id_direccion,
					d.direccion, c.costo, c.fecha_ini, c.fecha_fin, c.id_status, s.status, s.color, c.id_ruta, c.id_frecuencia_servicio, fs.concepto, 
					c.id_frecuencia_pago, c.id_dia_semana, c.secondary_day, ds.dia_ingles AS day_work, c.notas, c.observaciones, c.num_semanas, c.tiempo_servicio,
					c.retraso_invierno, ar.descripcion as nom_area, c.id_area, c.fecha_cancelacion
				FROM contratos c
				LEFT JOIN clientes AS cl ON cl.id_cliente = c.id_cliente
				LEFT JOIN direcciones AS d ON d.id_direccion = c.id_direccion
				LEFT JOIN status_all AS s ON s.id_status = c.id_status
				LEFT JOIN frecuencia_servicio AS fs ON fs.id_frecuencia_servicio = c.id_frecuencia_servicio
				LEFT JOIN dias_semana AS ds ON ds.id_dia_semana = c.id_dia_semana
				LEFT JOIN areas AS ar ON ar.id_area = c.id_area
				WHERE c.id_contrato = :v_id_contrato";

		$params = [
			':v_id_contrato' => $id_contrato
		];

		$this->log("Mostrar consulta de Contrato: " . $sql);
		$registro = $this->ejecutarConsulta($sql, '', $params);

		return $registro;
	}

	public function cargar_distribucion($id_contrato)
	{
		$sql = "SELECT c.id_contrato, c.fecha_ini, c.fecha_fin, c.fecha_cancelacion
				FROM contratos c
				WHERE c.id_contrato = :v_id_contrato";
		$params = [
			':v_id_contrato' => $id_contrato
		];

		$this->log("Mostrar consulta de Contrato: " . $sql);
		$registro = $this->ejecutarConsulta($sql, '', $params);
		$fecha_cancelacion = $registro["fecha_cancelacion"];

		// Bifurcación inteligente: generar si no existen
		$this->log("Inicio de Distribuicion ");
		$this->ensureScheduledServicesExist($id_contrato);

		$sql = "SELECT 
					ss.id, 
					ss.service_date, 
					ss.is_done, 
					ss.service_number, 
					ss.num_servicio,
					ss.id_status,
					c.fecha_cancelacion
				FROM scheduled_services ss
				JOIN contratos c ON ss.id_contrato = c.id_contrato
				WHERE ss.id_contrato = :v_id_contrato
				ORDER BY ss.service_date";

		$params = [
			':v_id_contrato' => $id_contrato
		];

		$services = $this->ejecutarConsulta($sql, "", $params, "fetchAll");

		$sql_status = "SELECT id_status, status, color
			FROM status_all 
			WHERE id_tabla = :v_id_tabla
			ORDER BY id_status";
		$params = [
			":v_id_tabla"=> 25
		];

		$all_status = $this->ejecutarConsulta($sql_status, '', $params, "fetchAll");

		http_response_code(200);
		echo json_encode([
			'status' => 'ok',
			'services' => $services,
			'all_status' => $all_status
		]);
		exit;
	}

	protected function ensureScheduledServicesExist(int $id_contrato): void
	{
		// Verificar si ya existen servicios programados
		$sql = "SELECT COUNT(*) AS total FROM scheduled_services WHERE id_contrato = :v_id_contrato";
		$params = [
			':v_id_contrato' => $id_contrato
		];
		$total = $this->ejecutarConsulta($sql, "", $params, "fetchColumn");
		$this->log("Registros existentes de scheduled_services para contrato $id_contrato: $total");

		if ($total >= 1) {
			return; // Ya están generados
		}

		$sql = "SELECT c.fecha_ini, c.fecha_fin, c.retraso_invierno,
					c.id_frecuencia_servicio, c.id_dia_semana, c.secondary_day, c.num_semanas, c.mensual_calendario
				FROM contratos c
				WHERE c.id_contrato = :v_id_contrato";

		$params = [
			':v_id_contrato' => $id_contrato
		];
		
		$this->log("Mostrar consulta de Contrato para render Antes: " . print_r($sql, true));

		$contrato = $this->ejecutarConsulta($sql, "", $params);

		$this->log("Mostrar consulta de Contrato para render: " . print_r($contrato, true));

		if (!$contrato || !$contrato['fecha_ini'] || !$contrato['fecha_fin']) {
			return; // No se puede generar sin fechas
		}

		$this->log("Inicio de creacion de registros");

		$servicios_reales = $this->obtenerServiciosReales($id_contrato);
		$fechas_reales = array_column($servicios_reales, 'fecha_real');
		$numeros_reales = array_column($servicios_reales, 'id_servicio');
		$id_status_all = array_column($servicios_reales, 'id_status');

		$fechas_programadas = [];
		$current = $contrato['fecha_ini']; // string 'Y-m-d'
	
		if (empty($contrato['fecha_fin']) || $contrato['fecha_fin'] === '0000-00-00') {
			if (!empty($current) && $current !== '0000-00-00') {
				$dt = new DateTime($current);
				$dt->add(new DateInterval('P1Y')); // +1 año
				$dt->sub(new DateInterval('P1D')); // -1 día
				$end = $dt->format('Y-m-d');
			} else {
				$end = null; // o manejar error
			}
		}else{
			$end = $contrato['fecha_fin']; // string 'Y-m-d'
		}
		$frecuencia = (int) $contrato['id_frecuencia_servicio'];
		$dia_principal = (int) ($contrato['id_dia_semana'] ?? 0); // 1=domingo, 7=sabado
		$dia_secundario = (int) ($contrato['secondary_day'] ?? 0); // 1=domingo, 7=sabado
		$num_semanas = (int) ($contrato['num_semanas'] ?? 0); // Límite de servicios
		$mensual_calendario = (bool) ($contrato['mensual_calendario'] ?? false);
		$retraso_invierno = (bool)($contrato['retraso_invierno'] ?? false);

		$servicesToInsert = [];
		$seenDates = []; // para evitar duplicados

		$this->log("Ciclo desde $current hasta $end con frecuencia $frecuencia, dia_principal $dia_principal, dia_secundario $dia_secundario");

		// Cargar fechas no laborables una sola vez
		$dias_no_actividad = $this->obtenerDiasNoActividad($current, $end);

		while ($current <= $end) {
			// 1. Ajustar al día principal permitido
			$adjustedDate = $this->adjustToAllowedDay($current, $dia_principal);

			// 2. Si el día ajustado es no laborable, intentar con el día secundario
			if (in_array($adjustedDate, $dias_no_actividad)) {
				if ($dia_secundario > 0 && $dia_secundario <= 7) {
					$adjustedDate = $this->adjustToAllowedDay($current, $dia_secundario);
					// Si el secundario también es no laborable, saltar esta iteración
					if (in_array($adjustedDate, $dias_no_actividad)) {
						$this->log("Saltando fecha $adjustedDate (no laborable y secundario también no laborable)");
						$intervalo = $this->getIntervaloFrecuencia($current, $frecuencia, $retraso_invierno, $current, $end);
						//$current = $this->avanzarFecha($current, $frecuencia, $mensual_calendario);
						$current = (new DateTime($current))->add(new DateInterval($intervalo))->format('Y-m-d');
						continue;
					}
				} else {
					// No hay día secundario y el principal es no laborable
					$this->log("Saltando fecha $adjustedDate (no laborable y sin día secundario)");
					$intervalo = $this->getIntervaloFrecuencia($current, $frecuencia, $retraso_invierno, $current, $end);
					//$current = $this->avanzarFecha($current, $frecuencia, $mensual_calendario);
					$current = (new DateTime($current))->add(new DateInterval($intervalo))->format('Y-m-d');
					continue;
				}
			}

			// 3. Evitar duplicados y verificar límite
			// if (!in_array($adjustedDate, $seenDates) && $adjustedDate <= $end && count($servicesToInsert) < $num_semanas) {

			// 	$servicesToInsert[] = $adjustedDate;
			// 	$seenDates[] = $adjustedDate;
			// }

			if ($adjustedDate <= $end) {
				$fechas_programadas[] = $adjustedDate;
			}

			// 4. Avanzar según frecuencia
			$current = $this->avanzarFecha($current, $frecuencia, $mensual_calendario);
		}

		// Crear mapa de fechas reales → id_servicio
		$mapa_fechas_reales = [];
		$mapa_status_reales = [];
		foreach ($servicios_reales as $serv) {
			$mapa_fechas_reales[$serv['fecha_real']] = $serv['id_servicio'];
			$mapa_status_reales[$serv['fecha_real']] = $serv['id_status'];
		}

		// Asignar service_number e is_done con ventana de tolerancia
		$servicesToInsert = [];
		$num_servicio = 1;

		foreach ($fechas_programadas as $fecha_prog) {
			if ($num_servicio > $num_semanas) break; // Respetar límite contractual

			$service_number = null;
			$is_done = 0;

			// Buscar en ventana de ±3 días
			for ($d = -3; $d <= 3; $d++) {
				$fecha_busqueda = date('Y-m-d', strtotime("$fecha_prog $d days"));
				if (isset($mapa_fechas_reales[$fecha_busqueda])) {
					$service_number = $mapa_fechas_reales[$fecha_busqueda];
					$id_status = $mapa_status_reales[$fecha_busqueda];
					$is_done = 1;
					break;
				}
			}

			$servicesToInsert[] = [
				'date' => $fecha_prog,
				'service_number' => $service_number,
				'is_done' => $is_done,
				'id_status' => $id_status,
				'num_servicio' => $num_servicio
			];
			$num_servicio++;
		}

		// Insertar registros
		foreach ($servicesToInsert as $record) {
			$datos = [
				['campo_nombre' => 'id_contrato',      'campo_marcador' => ':id_contrato',      'campo_valor' => $id_contrato],
				['campo_nombre' => 'num_servicio',     'campo_marcador' => ':num_servicio',     'campo_valor' => $record['num_servicio']],
				['campo_nombre' => 'service_date',     'campo_marcador' => ':service_date',     'campo_valor' => $record['date']],
				['campo_nombre' => 'id_status',        'campo_marcador' => ':id_status',     	'campo_valor' => $record['id_status']],
				['campo_nombre' => 'is_done',          'campo_marcador' => ':is_done',          'campo_valor' => $record['is_done']],
				['campo_nombre' => 'service_number',   'campo_marcador' => ':service_number',   'campo_valor' => $record['service_number']]
			];
			try {
				$this->guardarDatos('scheduled_services', $datos);
			} catch (Exception $e) {
				$this->logWithBacktrace("❌ Error al guardar scheduled_service: " . $e->getMessage(), true);
			}
		}
	}

	private function obtenerServiciosReales(int $id_contrato): array {
		$sql = "SELECT id_servicio, DATE(fecha_programada) AS fecha_real, id_status
				FROM servicios 
				WHERE id_contrato = :id_contrato
					AND fecha_programada IS NOT NULL
				ORDER BY fecha_programada ASC";
		$params = [':id_contrato' => $id_contrato];
		return $this->ejecutarConsulta($sql, "", $params, "fetchAll");
	}

	private function getIntervaloFrecuencia(string $fecha, int $frecuencia_base, bool $retraso_invierno, $fecha_ini, $fecha_fin): string {
		$ano_inicio = (new DateTime($fecha_ini))->format('Y');
		$ano_fin = (new DateTime($fecha_fin))->format('Y');

		// Consultar configuración
		$query = "SELECT clave, valor 
			FROM configuracion_sistema 
			WHERE clave IN ('inicio_invierno', 'fin_invierno')";
		$resultado = $this->ejecutarConsulta($query, '', [], 'fetchAll');

		// Inicializar con valores por defecto
		$inicio_invierno = "{$ano_inicio}-11-15";
		$fin_invierno = "{$ano_fin}-02-15";

		// Procesar resultados
		if ($resultado) {
			foreach ($resultado as $row) {
				if ($row['clave'] === 'inicio_invierno') {
					// Asumir formato MM-DD
					$inicio_invierno = "{$ano_inicio}-{$row['valor']}";
				} elseif ($row['clave'] === 'fin_invierno') {
					$fin_invierno = "{$ano_fin}-{$row['valor']}";
				}
			}
		}

		// Validar y ajustar año del fin de invierno si es necesario
		if (strtotime($fin_invierno) < strtotime($inicio_invierno)) {
			$fin_invierno = ($ano_inicio + 1) . '-' . substr($fin_invierno, 5);
		}


		if ($frecuencia_base !== 1) { // 1 = semanal
			return 'P1W'; // Por defecto, semanal
		}

		if (!$retraso_invierno) {
			return 'P1W';
		}

		// Verificar si la fecha está en invierno
		if ($fecha >= $inicio_invierno && $fecha <= $fin_invierno) {
			return 'P2W'; // Quincenal en invierno
		}

		return 'P1W';
	}

	protected function obtenerDiasNoActividad($current, $end): array
	{
		$sql = "SELECT fecha 
			FROM dias_no_actividad 
			WHERE activo = 53
				AND fecha >= :v_fecha01
				AND fecha <= :v_fecha02
			ORDER BY fecha";
		$params = [
			':v_fecha01' => $current,
			':v_fecha02' => $end
		];

		$resultados = $this->ejecutarConsulta($sql, "", $params, "fetchAll");
		$fechas = [];
		foreach ($resultados as $row) {
			$fechas[] = $row['fecha'];
		}
		return $fechas;
	}

	protected function avanzarFecha(string $fecha, int $frecuencia, bool $mensual_calendario): string
	{
		if ($frecuencia === 4) { // Mensual
			if ($mensual_calendario) {
				$ts = strtotime("$fecha +1 month");
				$adjustedTs = strtotime(date('Y-m-01', $ts) . ' +' . (date('d', strtotime($fecha)) - 1) . ' days');
				$next = date('Y-m-d', $adjustedTs);
				if (date('d', strtotime($fecha)) != date('d', strtotime($next))) {
					$next = date('Y-m-t', $ts);
				}
				return $next;
			} else {
				return date('Y-m-d', strtotime("$fecha +28 days"));
			}
		} else { // Semanal/bi/tri
			$days = match($frecuencia) {
				1 => 7,
				2 => 14,
				3 => 21,
				default => 7
			};
			return date('Y-m-d', strtotime("$fecha +{$days} days"));
		}
	}

	protected function adjustToAllowedDay(string $dateStr, ?int $allowedDay): string
	{
		if ($allowedDay === null || $allowedDay < 1 || $allowedDay > 7) {
			return $dateStr; // sin cambio
		}

		// En PHP: date('w') → 0=domingo, 1=lunes, ..., 6=sábado
		// Pero tú usas: 1=domingo, 2=lunes, ..., 7=sábado
		// Entonces mapeamos:
		// allowedDay (tú) → phpDay: allowedDay - 1
		$targetPhpDay = $allowedDay - 1; // 0=dom, 1=lun, ..., 6=sáb

		$ts = strtotime($dateStr);
		$currentPhpDay = (int) date('w', $ts); // 0=domingo

		if ($currentPhpDay == $targetPhpDay) {
			return $dateStr;
		}

		// Calcular días a sumar
		if ($currentPhpDay <= $targetPhpDay) {
			$diff = $targetPhpDay - $currentPhpDay;
		} else {
			$diff = (7 - $currentPhpDay) + $targetPhpDay;
		}

		return date('Y-m-d', strtotime("$dateStr +{$diff} days"));
	}

	public function guardarCambios($datos){
		$id_contrato = (int) $datos['id_contrato'];

		$id_cliente  = (int) $datos['id_cliente'];
		$id_direccion = (int) $datos['id_direccion'];
		$nombre = $datos['nom_contrato'];
		$costo = $this->verifica_num($datos['$costo']);
		$fecha_ini = $datos['fecha_ini'];
		$fecha_fin = $datos['fecha_fin'];
		$id_status = (int) $datos['id_status'];
		$id_frecuencia_pago = (int) $datos['id_frecuencia_pago'];
		$id_frecuencia_servicio = (int) $datos['id_frecuencia_servicio'];
		$id_dia_semana = (int) $datos['id_dia_semana'];
		$secondary_day = (int) $datos['secondary_day'];
		$contrato_origen = $datos['contrato_origen'];
		$notas = $datos['notas'];
		$observaciones = $datos['observaciones'];
		$num_semanas = (int) $datos['num_semanas'];
		$id_ruta = (int) $datos['id_ruta'];
		$retraso_invierno = isset($datos['retraso_invierno']) ? 1 : 0;
		$tiempo_servicio = $datos['tiempo_servicio'];

		if ($tiempo_servicio) {
			$parts = explode(':', $tiempo_servicio);
			if (count($parts) === 3) {
				[$h, $m, $s] = $parts;
				if (checkdate(1, 1, 2000) && 
					intval($h) >= 0 && intval($h) <= 23 &&
					intval($m) >= 0 && intval($m) <= 59 &&
					intval($s) >= 0 && intval($s) <= 59) {
					// Válido: $tiempo_servicio está en formato HH:mm:ss
				} else {
					throw new Exception("Invalid time format");
				}
			} else {
				throw new Exception("Time must be in HH:mm:ss format");
			}
		}

		// mensual_calendario TINYINT,

		if($id_ruta>0){
			$respuesta = $this->cargar_dir_zona($id_ruta, $id_direccion, $tiempo_servicio);
			if($respuesta['success'] == true){
				$resp_dir_zon = $respuesta['mess'];
			}else{
				$id_ruta = 0;
				$resp_dir_zon = $respuesta['mess'];
			}
		}

		// Llamar al controlador
        $datos = [
            ['campo_nombre' => 'id_cliente', 'campo_marcador' => ':id_cliente', 'campo_valor' => $id_cliente],
            ['campo_nombre' => 'id_direccion', 'campo_marcador' => ':id_direccion', 'campo_valor' => $id_direccion],
            ['campo_nombre' => 'nombre', 'campo_marcador' => ':nombre', 'campo_valor' => $nombre],
            ['campo_nombre' => 'costo', 'campo_marcador' => ':costo', 'campo_valor' => $costo],
            ['campo_nombre' => 'fecha_ini', 'campo_marcador' => ':fecha_ini', 'campo_valor' => $fecha_ini],
            ['campo_nombre' => 'fecha_fin', 'campo_marcador' => ':fecha_fin', 'campo_valor' => $fecha_fin],
            ['campo_nombre' => 'id_status', 'campo_marcador' => ':id_status', 'campo_valor' => $id_status],
            ['campo_nombre' => 'id_frecuencia_pago', 'campo_marcador' => ':id_frecuencia_pago', 'campo_valor' => $id_frecuencia_pago],
            ['campo_nombre' => 'id_frecuencia_servicio', 'campo_marcador' => ':id_frecuencia_servicio', 'campo_valor' => $id_frecuencia_servicio],
            ['campo_nombre' => 'id_dia_semana', 'campo_marcador' => ':id_dia_semana', 'campo_valor' => $id_dia_semana],
            ['campo_nombre' => 'secondary_day', 'campo_marcador' => ':secondary_day', 'campo_valor' => $secondary_day],
            ['campo_nombre' => 'contrato_origen', 'campo_marcador' => ':contrato_origen', 'campo_valor' => $contrato_origen],
            ['campo_nombre' => 'notas', 'campo_marcador' => ':notas', 'campo_valor' => $notas],
            ['campo_nombre' => 'observaciones', 'campo_marcador' => ':observaciones', 'campo_valor' => $observaciones],
            ['campo_nombre' => 'id_ruta', 'campo_marcador' => ':id_ruta', 'campo_valor' => $id_ruta],
            ['campo_nombre' => 'num_semanas', 'campo_marcador' => ':num_semanas', 'campo_valor' => $num_semanas],
			['campo_nombre' => 'retraso_invierno', 'campo_marcador' => ':retraso_invierno', 'campo_valor' => $retraso_invierno],
            ['campo_nombre' => 'tiempo_servicio', 'campo_marcador' => ':tiempo_servicio', 'campo_valor' => $tiempo_servicio]
        ];

		if ($id_status == 22) {
			$datos[] = [
				'campo_nombre' => 'fecha_cancelacion',
				'campo_marcador' => ':fecha_cancelacion',
				'campo_valor' => date('Y-m-d H:i:s')
			];
		}

        $condicion = [
            'condicion_campo' => 'id_contrato',
            'condicion_operador' => '=', 
            'condicion_marcador' => ':id_contrato',
            'condicion_valor' => $id_contrato
        ];

        try {
			$resulta = $this->actualizarDatos("contratos", $datos, $condicion);
            if ($resulta) {
				if ($id_status == 22) {
					// Paso 2: Eliminar servicios programados posteriores a la cancelación
					$sql = "DELETE ss
						FROM scheduled_services ss
						JOIN contratos ct ON ss.id_contrato = ct.id_contrato
						WHERE ct.id_contrato = :v_id_contrato 
							AND ct.id_status = :v_id_status
							AND ct.fecha_cancelacion IS NOT NULL
							AND ss.fecha_programada > ct.fecha_cancelacion";
					$params = [
						":v_id_contrato" => $id_contrato,
						"v_id_status" => $id_status
					];
					$borrados = $this->ejecutarConsulta($sql, "", $params, "fetchAll");
				}			

				http_response_code(200);
                echo json_encode(['tipo' => 'success', 'texto' => 'Contract updated successfully. ' . $resp_dir_zon]);
            } else {
                throw new Exception("The contract could not be updated. ". $resp_dir_zon);
            }
		} catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['tipo' => 'error', 'texto' => $e->getMessage()]);
        }
	}

	/**
	 * Convierte una cadena en formato HH:mm:ss a minutos enteros.
	 * Si el formato es inválido, devuelve 0.
	 *
	 * @param string $timeStr Ej: "01:30:45"
	 * @return int Minutos (ej: 90)
	 */
	public function timeToMinutes($timeStr) {
		if (empty($timeStr) || $timeStr === '00:00:00' || $timeStr === '00:00') {
			return 0;
		}

		// Asegurar que tenga al menos 5 caracteres (HH:mm)
		if (strlen($timeStr) < 5) {
			return 0;
		}

		// Normalizar a HH:mm:ss si viene como HH:mm
		if (substr_count($timeStr, ':') === 1) {
			$timeStr .= ':00';
		}

		$parts = explode(':', $timeStr);
		if (count($parts) !== 3) {
			return 0;
		}

		$hours = (int)$parts[0];
		$minutes = (int)$parts[1];
		$seconds = (int)$parts[2];

		// Validar rangos básicos
		if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59 || $seconds < 0 || $seconds > 59) {
			return 0;
		}

		// Convertir a minutos (truncando segundos)
		return $hours * 60 + $minutes;
	}

	private function verifica_rutas_direcciones($id_ruta, $id_direccion){
		$sql = "SELECT id_ruta_direccion
			FROM rutas_direcciones
			WHERE id_direccion = :v_id_direccion 
				AND id_ruta = :v_id_ruta";
		$params = [
			":v_id_ruta" => $id_ruta,
			":v_id_direccion" => $id_direccion
		];
		$id_ruta_direccion = $this->ejecutarConsulta($sql, "", $params, "fetchColumn");
		return $id_ruta_direccion;
	}

	private function cargar_dir_zona($id_ruta, $id_direccion, $tiempo_servicio){
		$sql = "SELECT lat, lng
			FROM direcciones
			WHERE id_direccion = :v_id_direccion";
		$params = [
			":v_id_direccion" => $id_direccion
		];
		$resultados = $this->ejecutarConsulta($sql, "", $params);

		if ($resultados){
			$coor_lat = $resultados['lat'];
			$coor_lng = $resultados['lng'];

			$sql = "SELECT id_zona, nombre_zona, centro_lat, centro_lng
				FROM zonas_cuadricula	
				WHERE 
					activo = 1
					AND :v_coor_lat BETWEEN lat_sw AND lat_ne
					AND :v_coor_lng BETWEEN lng_sw AND lng_ne
				LIMIT 1";

			$params = [
				":v_coor_lat" => $coor_lat,
				":v_coor_lng" => $coor_lng
			];
			$resultados = $this->ejecutarConsulta($sql, "", $params);

			if ($resultados){
				$id_ruta_direccion = $this->verifica_rutas_direcciones($id_ruta, $id_direccion);

				if ($id_ruta_direccion > 0){
					// Existe el elemento en la tabla rutas_direcciones. Solo deben actualizarse los campos
					$tiempo_servicio_hhmmss = $tiempo_servicio ?? '00:00:00';
					$tiempo_en_minutos = $this->timeToMinutes($tiempo_servicio_hhmmss);

					$datos = [
						['campo_nombre' => 'tiempo_servicio',	'campo_marcador' => ':tiempo_servicio',	'campo_valor' => $tiempo_en_minutos]
					];
					$condicion = [
						'condicion_campo' => 'id_ruta_direccion',
						'condicion_operador' => '=', 
						'condicion_marcador' => ':id_ruta_direccion',
						'condicion_valor' => $id_ruta_direccion
					];

					try {
						$resulta = $this->actualizarDatos("rutas_direcciones", $datos, $condicion);
						if ($resulta) {
							$respuesta = [
								"success" => true,
								"mess" => "Se Actualizo el Contrato en la Zona de Direcciones"
							];
						} else {
							$respuesta = [
								"success" => false,
								"mess" => "No se Actualizo el Contrato en la Zona de Direcciones"
							];
						}
					} catch (Exception $e) {
						$respuesta = [
							"success" => false,
							"mess" => "ERROR al actualizar el Contrato en la Zona de Direcciones" . $e->getMessage()
						];
					}

				}else{
					$sql = "SELECT COUNT(*) AS total 
						FROM rutas_direcciones 
						WHERE id_ruta = :v_id_ruta";
					$params = [
						':v_id_ruta' => $id_ruta
					];
					$total = $this->ejecutarConsulta($sql, "", $params, "fetchColumn");

					$tiempo_servicio_hhmmss = $tiempo_servicio ?? '00:00:00';
					$tiempo_en_minutos = $this->timeToMinutes($tiempo_servicio_hhmmss);

					$datos = [
						['campo_nombre' => 'id_ruta',			'campo_marcador' => ':id_ruta',			'campo_valor' => $id_ruta],
						['campo_nombre' => 'id_direccion',		'campo_marcador' => ':id_direccion',	'campo_valor' => $id_direccion],
						['campo_nombre' => 'orden_en_ruta',		'campo_marcador' => ':orden_en_ruta',	'campo_valor' => $total + 1],
						['campo_nombre' => 'tiempo_servicio',	'campo_marcador' => ':tiempo_servicio',	'campo_valor' => $tiempo_en_minutos]
					];
					try {
						$reg_act = $this->guardarDatos('rutas_direcciones', $datos);
						if($reg_act){

							$id_zona = $resultados['id_zona'];
							$sql = "SELECT id_ruta_zona 
								FROM rutas_zonas_cuadricula
								WHERE id_ruta = :_v_id_ruta
									AND id_zona	= :_v_id_zona";
							$params = [
								":_v_id_ruta" => $id_ruta,
								":_v_id_zona" => $id_zona
							];
							$resultados = $this->ejecutarConsulta($sql,	"", $params);
							if ($resultados){
								$respuesta = [
									"success" => true,
									"mess" => "Ya esta registrada en rutas_zonas_cuadricula la Zona"
								];
							}else{
								$datos = [
									['campo_nombre' => 'id_ruta', 'campo_marcador' => ':id_ruta', 'campo_valor' => $id_ruta],
									['campo_nombre' => 'id_zona', 'campo_marcador' => ':id_zona', 'campo_valor' => $id_zona],
									['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1],
								];
								try {
									$reg_act = $this->guardarDatos('rutas_zonas_cuadricula', $datos);
								} catch (Exception $e) {
									$this->logWithBacktrace("❌ Error al guardar rutas_zonas_cuadricula: " . $e->getMessage(), true);
									$respuesta = [
										"success" => false,
										"mess" => "No se pudo guardar el Contrato en la Zona de Direcciones"
									];
								}
							}
						}

						$respuesta = [
							"success" => true,
							"mess" => "Se guardar el Contrato en la Zona de Direcciones"
						];
					} catch (Exception $e) {
						$this->logWithBacktrace("❌ Error al guardar registro de fecha de servicio: " . $e->getMessage(), true);
						$respuesta = [
							"success" => false,
							"mess" => "ERROR. Al guardar el registro en rutas_direcciones: " . $e->getMessage()
						];
					}
				}
			} else {
				$respuesta = [
					"success" => false,
					"mess" => "No hubo logro localizando la Zona" 
				];
			}
		}else{
			$respuesta = [
				"success" => false,
				"mess" => "No hubo Dirección en el Cliente" 
			];
		}

		return $respuesta;
	}
}