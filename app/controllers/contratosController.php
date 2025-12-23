<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';
require_once APP_R_PROY . 'app/controllers/usuariosController.php';

use app\models\mainModel;
use app\controllers\usuariosController;

use \Exception;
use DateTime;
use DateTimeZone;

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

		$campos = "c.*, ct.nombre AS cliente_nombre, ct.apellido AS cliente_apellido, d.dia_ingles AS day_work, 
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

				// 2. Convertir a objetos DateTime
				$inicio = new DateTime($fecha_ini);
				$fin    = new DateTime($fecha_fin);
				$hoy    = new DateTime(); // fecha de hoy

				// 3. Calcular posición de "hoy" como porcentaje entre inicio y fin
				if ($hoy <= $inicio) {
					$porcentaje = 0;
				} elseif ($hoy >= $fin) {
					$porcentaje = 100;
				} else {
					$total = $inicio->diff($fin)->days;      // días totales del contrato
					$pasados = $inicio->diff($hoy)->days;    // días transcurridos
					$porcentaje = ($pasados / $total) * 100;
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

		$sql = "SELECT c.id_contrato, c.contrato_origen, c.id_cliente,	cl.nombre, cl.apellido, cl.telefono, cl.email, c.id_direccion, d.direccion, c.costo, 
					c.fecha_ini, c.fecha_fin, c.id_status, s.status, s.color, c.id_frecuencia_servicio, fs.concepto, c.id_frecuencia_pago, 
					c.id_dia_semana, c.secondary_day, ds.dia_ingles AS day_work, c.notas, c.observaciones
				FROM contratos c
				LEFT JOIN clientes AS cl ON cl.id_cliente = c.id_cliente
				LEFT JOIN direcciones AS d ON d.id_direccion = c.id_direccion
				LEFT JOIN status_all AS s ON s.id_status = c.id_status
				LEFT JOIN frecuencia_servicio AS fs ON fs.id_frecuencia_servicio = c.id_frecuencia_servicio
				LEFT JOIN dias_semana AS ds ON ds.id_dia_semana = c.id_dia_semana
				WHERE c.id_contrato = :v_id_contrato";

		$params = [
			':v_id_contrato' => $id_contrato
		];

		$this->log("Mostrar consulta de Contrato: " . $sql);
		$registro = $this->ejecutarConsulta($sql, '', $params);

		return $registro;
	}

	public function formatearTelefono($telefono, $codigoPais = '+1')
	{
		$telefonoLimpio = preg_replace('/[^\+\d]/', '', $telefono);
		if (!str_starts_with($telefonoLimpio, '+')) {
			$telefonoLimpio = $codigoPais . ltrim($telefonoLimpio, '0');
		}
		return $telefonoLimpio;
	}

	public function cargar_distribucion($id_contrato)
	{
		// Bifurcación inteligente: generar si no existen
		$this->log("Inicio de Distribuicion ");
		$this->ensureScheduledServicesExist($id_contrato);

		$sql = "SELECT id, service_date, is_done, service_number, num_servicio
			FROM scheduled_services
			WHERE id_contrato = :v_id_contrato
			ORDER BY service_date";
		$params = [
			':v_id_contrato' => $id_contrato
		];

		$services = $this->ejecutarConsulta($sql, "", $params, "fetchAll");

		http_response_code(200);
		echo json_encode([
			'status' => 'ok',
			'services' => $services
		]);
		exit;
	}

	protected function ensureScheduledServicesExist(int $id_contrato): void
	{
		// Verificar si ya existen servicios programados
		$sql = "SELECT COUNT(*) AS total FROM scheduled_services WHERE id_contrato = :v_id_contrato";
		$params = [':v_id_contrato' => $id_contrato];
		$total = $this->ejecutarConsulta($sql, "", $params, "fetchColumn");
		$this->log("Registros existentes de scheduled_services para contrato $id_contrato: $total");

		if ($total >= 1) {
			return; // Ya están generados
		}

		// Obtener datos del contrato con fecha_ini real
		$sql = "SELECT 
					COALESCE(
						(SELECT s.fecha_programada
						FROM servicios s
						WHERE s.id_cliente = c.id_cliente
						AND s.id_status != 39
						ORDER BY s.fecha_programada DESC
						LIMIT 1),
						c.fecha_ini
					) AS fecha_ini,
					DATE_SUB(DATE_ADD(
						COALESCE(
							(SELECT s.fecha_programada
							FROM servicios s
							WHERE s.id_cliente = c.id_cliente
							AND s.id_status != 39
							ORDER BY s.fecha_programada DESC
							LIMIT 1),
							c.fecha_ini
						), INTERVAL 1 YEAR), INTERVAL 1 DAY) AS fecha_fin,
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

		$current = $contrato['fecha_ini']; // string 'Y-m-d'
		$end = $contrato['fecha_fin'];     // string 'Y-m-d'
		$frecuencia = (int) $contrato['id_frecuencia_servicio'];
		$dia_principal = (int) ($contrato['id_dia_semana'] ?? 0); // 1=domingo, 7=sabado
		$dia_secundario = (int) ($contrato['secondary_day'] ?? 0); // 1=domingo, 7=sabado
		$num_semanas = (int) ($contrato['num_semanas'] ?? 0); // Límite de servicios
		$mensual_calendario = (bool) ($contrato['mensual_calendario'] ?? false);

		$servicesToInsert = [];
		$seenDates = []; // para evitar duplicados

		$this->log("Ciclo desde $current hasta $end con frecuencia $frecuencia, dia_principal $dia_principal, dia_secundario $dia_secundario");

		// Cargar fechas no laborables una sola vez
		$dias_no_actividad = $this->obtenerDiasNoActividad();

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
						$current = $this->avanzarFecha($current, $frecuencia, $mensual_calendario);
						continue;
					}
				} else {
					// No hay día secundario y el principal es no laborable
					$this->log("Saltando fecha $adjustedDate (no laborable y sin día secundario)");
					$current = $this->avanzarFecha($current, $frecuencia, $mensual_calendario);
					continue;
				}
			}

			// 3. Evitar duplicados y verificar límite
			if (!in_array($adjustedDate, $seenDates) && $adjustedDate <= $end && count($servicesToInsert) < $num_semanas) {
				$servicesToInsert[] = $adjustedDate;
				$seenDates[] = $adjustedDate;
			}

			// 4. Avanzar según frecuencia
			$current = $this->avanzarFecha($current, $frecuencia, $mensual_calendario);
		}

		// Insertar registros
		$num_servicio = 1;
		foreach ($servicesToInsert as $date) {
			$datos = [
				['campo_nombre' => 'id_contrato',      'campo_marcador' => ':id_contrato',      'campo_valor' => $id_contrato],
				['campo_nombre' => 'num_servicio',     'campo_marcador' => ':num_servicio',     'campo_valor' => $num_servicio],
				['campo_nombre' => 'service_date',     'campo_marcador' => ':service_date',     'campo_valor' => $date],
				['campo_nombre' => 'is_done',          'campo_marcador' => ':is_done',          'campo_valor' => 0],
				['campo_nombre' => 'service_number',   'campo_marcador' => ':service_number',   'campo_valor' => null]
			];
			try {
				$this->guardarDatos('scheduled_services', $datos);
				$num_servicio++;
			} catch (Exception $e) {
				$this->logWithBacktrace("❌ Error al guardar registro de fecha de servicio: " . $e->getMessage(), true);
			}
		}
	}

	protected function obtenerDiasNoActividad(): array
	{
		$sql = "SELECT fecha FROM dias_no_actividad WHERE activo = 53";
		$resultados = $this->ejecutarConsulta($sql, "", [], "fetchAll");
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
}