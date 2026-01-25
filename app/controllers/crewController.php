<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
class crewController extends mainModel
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
		$nom_controlador = "crewController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/crew/';

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

    public function listarcrewControlador($dato_ori)
	{
		$pagina_crew = $dato_ori[0];
		$registrosPorPagina = $dato_ori[1];
		$url1 = $dato_ori[2];
		$busca_frase = $dato_ori[3];
		$ruta_retorno = $dato_ori[4];
		$orden = $dato_ori[5];
		$direccion  = $dato_ori[6];

		$pagina = (isset($pagina_crew) && $pagina_crew > 0) ? (int) $pagina_crew : 1;
        $pagina = $this->limpiarCadena($pagina);
        $registros = $this->limpiarCadena($registrosPorPagina);

		$url = $this->limpiarCadena($url1);
        $url_origen = $url;
        $url = RUTA_APP . "/" . $url . "/";
        $tabla = "";

        $busqueda = $this->limpiarCadena($busca_frase);

        $inicio = ($pagina > 0) ? (($pagina * $registrosPorPagina) - $registrosPorPagina) : 0;
	
		$campos = "cr.*, c.cargo, s.status";
        // === Generar la cláusula ORDER BY ===
        $orden_r = "cr.crew, cr.nombre_completo";

        if (isset($busqueda) && $busqueda != "") {
            $campos_filtro = "cr.nombre_completo LIKE '%$busqueda%' || c.cargo LIKE '%$busqueda%' ";

            $consulta_datos = "SELECT " . $campos . " 
                    FROM crew cr
                    LEFT JOIN cargos c ON cr.id_cargo = c.id_cargo
					LEFT JOIN status_all AS s ON s.id_status = cr.id_status 
                    WHERE " . $campos_filtro . "
                    ORDER BY " . $orden_r . "
                    LIMIT " . $inicio . ", " . $registros;

            $consulta_total = "SELECT COUNT(id_crew) AS total 
                    FROM crew 
                    WHERE " . $campos_filtro; 

        } else {
            // Consulta principal: lista todos los crew con tiempo restante
            $consulta_datos = "SELECT " . $campos . " 
                    FROM crew  cr
                    LEFT JOIN cargos c ON cr.id_cargo = c.id_cargo
					LEFT JOIN status_all AS s ON s.id_status = cr.id_status 
                    ORDER BY " . $orden_r . "
                    LIMIT " . $inicio . ", " . $registros;

            // Conteo total de registros
            $consulta_total = "SELECT COUNT(id_crew) AS total 
                    FROM crew"; 
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
							<th class="has-text-centered">Code</th>
							<th class="has-text-centered">Full Name</th>
							<th class="has-text-centered">Position</th>
							<th class="has-text-centered">Phone</th>
							<th class="has-text-centered">Status</th>
							<th class="has-text-centered" colspan="2">Options</th>
						</tr>
					</thead>
					<tbody>
			';

		if ($total >= 1 && $pagina <= $numeroPaginas) {
            $contador = $inicio + 1;
            $pag_inicio = $inicio + 1;

            foreach ($datos as $rows) {

                $ruta_crew = '/crew/' . $pagina . '/';
                $ruta_destino = RUTA_APP . "/crewVista/" . $rows['id_crew'] . $ruta_crew;

                $tabla .= '
                        <tr class="has-text-centered table-row">
                            <td>' . $contador . '</td>
                            <td>' . $this->ceros($rows['id_crew']) . '</td>
                            <td style="background:' . $this->lightenHexColor($rows['color'], 0.8) . ';">' . $rows['nombre_completo'] . '</td>
                            <td>' . $rows['cargo'] . '</td>
                            <td>' . $rows['telefono'] . '</td>
                            <td>' . $rows['status'] . '</td>
                            <td>
                                <a href="' . $ruta_destino. '" class="button is-link is-rounded is-small" id="a_id_crew" name="a_id_crew"><span class="fas fa-eye"></a>
                            </td>
                            <td>
                                <form class="FormularioAjax" action="' . APP_URL . 'app/ajax/crewAjax.php" method="POST" autocomplete="off" >
                                    <input type="hidden" name="modulo_crew" value="eliminar">
                                    <input type="hidden" name="id_crew" id="id_crew" value="' . $rows['id_crew'] . '">
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
                        <p class="has-text-right">Displaying Crews <strong>' . $pag_inicio . '</strong> to <strong>' . $pag_final . '</strong> of a total of <strong>' . $total . '</strong></p>
                    </div>';
            $id_codigos = [
                'n0' => 0,
                'n1' => 0
            ];
            $tabla .= $this->paginadorTablas($pagina, $numeroPaginas, $url, 7, $id_codigos);
        }
        return $tabla;
    }

    public function crear_registro($crew)
    {
        try {
            $crew = $this->limpiarCadena($crew);

            $datos = [
                ['campo_nombre' => 'crew', 'campo_marcador' => ':crew', 'campo_valor' => $crew],
            ];

            $id_crew = $this->guardarDatos('crew', $datos);
            $this->log("Clasificacion insertado: Clasificacion = {$id_crew}");

            if ($id_crew) {
                $this->log("Nuevo registro de crew creado: " . $crew);
                echo json_encode([
                    'tipo' => 'success',
                    'titulo' => 'Success',
                    'texto' => 'The new address classification has been successfully registered.'
                ]);
            } else {
                $this->log("Error al crear el nuevo registro de crew: " . $crew, true);
                echo json_encode([
                    'tipo' => 'error',
                    'titulo' => 'Error',
                    'texto' => 'There was an error registering the new address classification. Please try again.'
                ]);
            }
        } catch (Exception $e) {
            $this->log("Excepción al crear el nuevo registro de crew: " . $e->getMessage(), true);
            echo json_encode([
                'tipo' => 'error',
                'titulo' => 'Exception',
                'texto' => 'An exception occurred while registering the new address classification: ' . $e->getMessage()
            ]);
        }
    }

    public function consultar_crew()
    {
        $sql = 'SELECT id_crew, crew
            FROM crew';
        
        $param = [];

        $data = $this->ejecutarConsulta($sql, "", $param, "fetchAll");

        return $data;
    }

    public function consulta_registro($param_dat)
    {
        $id_crew = $param_dat['id_crew'];
		$campos = "cr.*, c.cargo, s.status";
        $consulta_datos = "SELECT " . $campos . " 
                FROM crew  cr
                LEFT JOIN cargos c ON cr.id_cargo = c.id_cargo
                LEFT JOIN status_all AS s ON s.id_status = cr.id_status 
                WHERE cr.id_crew = :v_id_crew";
        $params = [
            ':v_id_crew' => $id_crew
        ];
        $resulta = $this->ejecutarConsulta($consulta_datos, "", $params);

        if (is_null($resulta['telefono'])) {
            $telefono = "---";
        } else {
            $telefono = $this->formatearTelefono($resulta['telefono'], '+1');
        }
        $resulta['telefono'] = $telefono;
        
        return $resulta;
    }

    public function ceros($val_ini)
    {
        $val_fin = str_pad($val_ini,  4, "0", STR_PAD_LEFT);
        return $val_fin;
    }

}
