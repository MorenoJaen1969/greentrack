<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';
require_once APP_R_PROY . 'app/controllers/usuariosController.php';

use app\models\mainModel;
use app\controllers\usuariosController;

use \Exception;
use DateTime;
use DateTimeZone;

class clientesController extends mainModel
{
	private $ultimoToken = null;
	private $tokenExpiraEn = null; // Timestamp de expiración
	private $log_path;
	private $logFile;
	private $errorLogFile;
	private $ultimaCoordenada = [];
	private $o_f;
	private $id_status_activo;
	private $id_status_inactivo;
	private $id_status_pendiente;
	private $id_status_eliminado;
	private $id_status_suspendido;
	private $id_status_renovado;
	private $id_status_en_espera;
	private $id_status_verificado;
	private $id_status_no_verificado;

	public function __construct()
	{
		// ¡ESTA LÍNEA ES CRUCIAL!
		parent::__construct();

		// Nombre del controlador actual abreviado para reconocer el archivo
		$nom_controlador = "clientesController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/clientes/';

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

		$this->id_status_activo = 1;
		$this->id_status_inactivo = 2;
		$this->id_status_pendiente = 3;
		$this->id_status_eliminado = 4;
		$this->id_status_suspendido = 5;
		$this->id_status_renovado = 6;
		$this->id_status_en_espera = 7;
		$this->id_status_verificado = 8;
		$this->id_status_no_verificado = 9;

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

	public function listarclientesControlador($dato_ori)
	{
		$pagina_clientes = $dato_ori[0];
		$registrosPorPagina = $dato_ori[1];
		$url1 = $dato_ori[2];
		$busca_frase = $dato_ori[3];
		$ruta_retorno = $dato_ori[4];
		$orden = $dato_ori[5];
		$direccion  = $dato_ori[6];

		$pagina = (isset($pagina_clientes) && $pagina_clientes > 0) ? (int) $pagina_clientes : 1;
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

		$campos = "c.*, s.status";

        $where = " c.id_status != " . $this->id_status_eliminado;
		// === Generar la cláusula ORDER BY ===
		$orden_r = "c.nombre";

        if (isset($busqueda) && $busqueda != "") {
            $campos_filtro = "c.nombre LIKE '%$busqueda%' OR c.apellido LIKE '%$busqueda%'";
            $consulta_datos = "SELECT " . $campos . " 
                    FROM clientes c
					LEFT JOIN status_all AS s ON s.id_status = c.id_status
					WHERE (" . $campos_filtro . ") AND " . $where . 
                    " ORDER BY " . $orden_r . "
                    LIMIT " . $inicio . ", " . $registros;

            // Conteo total de registros
            $consulta_total = "SELECT COUNT(c.id_cliente) AS total 
                    FROM clientes AS c
					LEFT JOIN status_all AS s ON s.id_status = c.id_status
					WHERE (" . $campos_filtro . ") AND " . $where;

		} else {
            // Consulta principal: lista todos los clientes con tiempo restante
            $consulta_datos = "SELECT " . $campos . " 
                    FROM clientes c
					LEFT JOIN status_all AS s ON s.id_status = c.id_status
					WHERE " . $where . 
                    " ORDER BY " . $orden_r . "
                    LIMIT " . $inicio . ", " . $registros;

            // Conteo total de registros
            $consulta_total = "SELECT COUNT(c.id_cliente) AS total 
                    FROM clientes AS c
					LEFT JOIN status_all AS s ON s.id_status = c.id_status
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
						<button type="button" id="eli_fil" class="button is-warning is-small">
							' . htmlspecialchars($text_filtro) . '
						</button>
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
							<th class="has-text-centered">Name</th>
							<th class="has-text-centered">Last name</th>
							<th class="has-text-centered">Type of Person</th>
							<th class="has-text-centered">Status</th>
							<th class="has-text-centered" colspan="4">Options</th>
						</tr>
					</thead>
					<tbody>
			';

		if ($total >= 1 && $pagina <= $numeroPaginas) {
            $contador = $inicio + 1;
            $pag_inicio = $inicio + 1;
			$ruta_clientes = '/clientes/' . $pagina . '/';

			foreach ($datos as $rows) {

				$id_cliente = $rows['id_cliente'];
				$sql = "SELECT COUNT(s.id_cliente) AS total_servicio 
                    FROM servicios AS s
					WHERE s.id_status != 39 AND s.id_cliente = " . $id_cliente;
		
				$total_s = $this->ejecutarConsulta($sql, "", [], "fetchColumn");

                $ruta_det_servicio = RUTA_APP . "/serviciosLista" . $ruta_clientes;
				$href = ($total_s > 0) 
					? $ruta_det_servicio . $rows['id_cliente'] 
					: 'javascript:void(0);';
				$claseBoton = 'button is-primary is-rounded is-small';
				$estilo = '';

				if ($total_s == 0) {
					// Deshabilitar visualmente y funcionalmente
					$claseBoton .= ' is-disabled';
					$estilo = 'pointer-events: none; opacity: 0.5; color: white !important;';
				} elseif ($total_s > 0) {
					// Color de texto negro
					$estilo = 'color: black !important;';
				}

				$sql = "SELECT COUNT(c.id_cliente) AS total_contratos 
                    FROM contratos AS c
					WHERE c.id_status != 49 AND c.id_cliente = " . $id_cliente;
		
				$total_c = $this->ejecutarConsulta($sql, "", [], "fetchColumn");

				if ($total_c == 0) {
					// Deshabilitar visualmente y funcionalmente
					$estilo2 = 'opacity: 0.5; color: red !important;';
				} elseif ($total_c > 0) {
					// Color de texto negro
					$estilo2 = 'color: black !important;';
				}

                $ruta_destino = RUTA_APP . "/clientesVista/clientes/" . $rows['id_cliente'] . $ruta_clientes;
                $ruta_contratos = '/contratos/';
                $ruta_destino_c = RUTA_APP . "/contratosVista/contrato/" . $rows['id_cliente'] . $ruta_contratos;

				$tabla .= '
                        <tr class="has-text-centered table-row">
                            <td>' . $contador . '</td>
                            <td>' . $rows['nombre'] . '</td>
							<td>' . $rows['apellido'] . '</td>
                            <td>' . $rows['id_tipo_persona'] . '</td>
                            <td>' . $rows['status'] . '</td>
                            <td>
								<a href="' . $ruta_destino_c . $rows['id_cliente'] . '" 
									class="button is-warning is-rounded is-small" 
									id="b_id_cliente" 
									name="b_id_cliente"
									style="' . $estilo2 . '"
									title="' . ($total_c > 0 ? 'View Contract for this client' : 'No Contract assigned') . '">
										<span class="fas fa-file-signature"> '. $total_c .'
								</a>
							</td>
                            <td>
								<a href="' . $href . '" 
									class="' . $claseBoton . '" 
									id="c_id_cliente" 
									name="c_id_cliente"
									style="' . $estilo . '"
									title="' . ($total_s > 0 ? 'View services for this client' : 'No services assigned') . '">
										<span class="fas fa-person-digging"> '. $total_s .'
								</a>
							</td>
                            <td>
                                <a href="' . $ruta_destino . $rows['id_cliente'] . '" 
									class="button is-link is-rounded is-small" 
									id="a_id_cliente" 
									name="a_id_cliente"
									title="View client details">
										<span class="fas fa-eye">
								</a>
                            </td>
							<td>
								<button type="button" 
										class="button is-danger is-rounded is-small btn-eliminar-clientes"
										data-id="' . $rows['id_cliente'] . '"
										data-pagina="' . $pagina . '"
										data-registros="' . $registrosPorPagina . '"
										data-url="' . $url_origen . '"
										title="Delete customer"
										' . ($total_s > 0 ? 'disabled' : '') . '
										style="' . ($total_s > 0 ? 'opacity: 0.5; pointer-events: none;' : '') . '">
									<span class="fas fa-trash"></span>
								</button>
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
                        <p class="has-text-right">Displaying Customers <strong>' . $pag_inicio . '</strong> to <strong>' . $pag_final . '</strong> of a total of <strong>' . $total . '</strong></p>
                    </div>';
            $id_codigos = [
                'n0' => 0,
                'n1' => 0
            ];
            $tabla .= $this->paginadorTablas($pagina, $numeroPaginas, $url, 7, $id_codigos);
        }
		$tabla .= '</div>';
        return $tabla;
    }

	public function eliminar_cliente($id_cliente)
	{
		$sql = "SELECT id_servicio
			FROM servicios
			WHERE id_cliente = :v_id_cliente";
		$param = [
			"v_id_cliente" => $id_cliente
		];	
        
		$datos = $this->ejecutarConsulta($sql, "", $param, "fetchAll");

		if (count((array)$datos) > 0) {
			http_response_code(200);
			echo json_encode(['error' => 'false', 'message' => 'Cannot be deleted. There are related records.']);
		} else {
			try {
				$datos = [
					['campo_nombre' => 'id_status', 'campo_marcador' => ':id_status', 'campo_valor' => 13]
				];
				$condicion = [
					'condicion_campo' => 'id_cliente',
					'condicion_operador' => '=', 					
					'condicion_marcador' => ':id_cliente',
					'condicion_valor' => $id_cliente
				];

				$this->actualizarDatos('clientes', $datos, $condicion);

				http_response_code(200);
				echo json_encode(['success' => 'ok', 'message' => 'Update completed']);

			} catch (Exception $e) {
				$this->logWithBacktrace("Error en finalizarServicio: " . $e->getMessage(), true);
				http_response_code(500);
				echo json_encode(['error' => 'Could not update']);
			}
		}
	}

	public function clienteCompleto_cliente($id_cliente){
		$consulta = "SELECT c.*, s.status 
			FROM clientes c
			LEFT JOIN status_all AS s ON s.id_status = c.id_status
			WHERE id_cliente = :v_id_cliente";

		$parametros = [
			':v_id_cliente' => $id_cliente
		];

		$resultado = $this->ejecutarConsulta($consulta, "",$parametros);

		return $resultado;
	}

    public function formatearTelefono($telefono, $codigoPais = '+1')
    {
        $telefonoLimpio = preg_replace('/[^\+\d]/', '', $telefono);
        if (!str_starts_with($telefonoLimpio, '+')) {
            $telefonoLimpio = $codigoPais . ltrim($telefonoLimpio, '0');
        }
        return $telefonoLimpio;
    }

	public function frecuencia_pago($id_frecuencia_pago)
	{
		$consulta = "SELECT fp.*
			FROM frecuencia_pago fp";

		$resultado = $this->ejecutarConsulta($consulta, "",[], "fetchAll");

		return $resultado;
	}

	public function consultar_clientes()
	{
        $sql = 'SELECT id_cliente, nombre AS cliente
			FROM clientes
			ORDER BY nombre';
        
        $param = [];

        $data = $this->ejecutarConsulta($sql, "", $param, "fetchAll");

        return $data;

	}
}