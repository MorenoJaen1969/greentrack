<?php

namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
class dias_no_actividadController extends mainModel
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
	private $ruta_reporte;

	public function __construct()
	{
		// ¡ESTA LÍNEA ES CRUCIAL!
		parent::__construct();

		// Nombre del controlador actual abreviado para reconocer el archivo
		$nom_controlador = "dias_no_actividadController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/dias_no_actividad/';

		if (!file_exists($this->log_path)) {
			mkdir($this->log_path, 0775, true);
			chgrp($this->log_path, 'www-data');
			chmod($this->log_path, 0775); // Asegurarse de que el directorio sea legible y escribible
		}

		$this->logFile = $this->log_path . $nom_controlador . '_' . date('Y-m-d') . '.log';
		$this->errorLogFile = $this->log_path . $nom_controlador . '_error_' . date('Y-m-d') . '.log';

        $this->verificarPermisos();
		// rotación automatica de log (Elimina logs > XX dias)
		$this->rotarLogs(15);

		$this->id_status_cancelado = 47;
		$this->id_status_finalizado = 38;
		$this->id_status_historico = 39;
		$this->id_status_activo = 37;
		$this->id_status_replanificado = 40;

        $this->ruta_reporte = APP_R_PROY . '/public/reports/';
		if (!file_exists($this->ruta_reporte)) {
			mkdir($this->ruta_reporte, 0755, true);
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
		if (empty($file)) {
			// Si no hay archivo de log configurado, evitar fallos en ambiente de pruebas 
			return;
		}
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
		if (empty($this->errorLogFile)) {
			return;
		}
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		$caller = $backtrace[1] ?? $backtrace[0];
		$logMessage = sprintf("[%s] %s - Called from %s::%s (Line %d)%s%s", date('Y-m-d H:i:s'), $message, $caller['class'] ?? '', $caller['function'], $caller['line'], PHP_EOL, "Stack trace:" . PHP_EOL . json_encode($backtrace, JSON_PRETTY_PRINT));
		file_put_contents($this->errorLogFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
	}

    public function listardias_no_actividadControlador($dato_ori)
	{
		$pagina_dias_no_actividad = $dato_ori[0];
		$registrosPorPagina = $dato_ori[1];
		$url1 = $dato_ori[2];
		$busca_frase = $dato_ori[3];
		$ruta_retorno = $dato_ori[4];
		$orden = $dato_ori[5];
		$direccion  = $dato_ori[6];

		$pagina = (isset($pagina_dias_no_actividad) && $pagina_dias_no_actividad > 0) ? (int) $pagina_dias_no_actividad : 1;
        $pagina = $this->limpiarCadena($pagina);
        $registros = $this->limpiarCadena($registrosPorPagina);

		$url = $this->limpiarCadena($url1);
        $url_origen = $url;
        $url = RUTA_APP . "/" . $url . "/";
        $tabla = "";

        $busqueda = $this->limpiarCadena($busca_frase);

        $inicio = ($pagina > 0) ? (($pagina * $registrosPorPagina) - $registrosPorPagina) : 0;
	
		$campos = "*, s.status ";
        // === Generar la cláusula ORDER BY ===
        $orden_r = "fecha DESC";

        if (isset($busqueda) && $busqueda != "") {
            $campos_filtro = "motivo LIKE '%$busqueda%'";

            $consulta_datos = "SELECT " . $campos . "
                    FROM dias_no_actividad 
                    LEFT JOIN status_all AS s ON s.id_status = dias_no_actividad.activo
                    WHERE " . $campos_filtro . "
                    ORDER BY " . $orden_r . "
                    LIMIT " . $inicio . ", " . $registros;

            $consulta_total = "SELECT COUNT(id) AS total 
                    FROM dias_no_actividad 
                    LEFT JOIN status_all AS s ON s.id_status = dias_no_actividad.activo
                    WHERE " . $campos_filtro; 

        } else {
            // Consulta principal: lista todos los dias_no_actividad con tiempo restante
            $consulta_datos = "SELECT " . $campos . " 
                    FROM dias_no_actividad 
                    LEFT JOIN status_all AS s ON s.id_status = dias_no_actividad.activo
                    ORDER BY " . $orden_r . "
                    LIMIT " . $inicio . ", " . $registros;

            // Conteo total de registros
            $consulta_total = "SELECT COUNT(id) AS total 
                    FROM dias_no_actividad
                    LEFT JOIN status_all AS s ON s.id_status = dias_no_actividad.activo
                    "; 
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
							<th class="has-text-centered">Date</th>
							<th class="has-text-centered">Motive</th>
                            <th class="has-text-centered">Type</th>
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

                $ruta_dias_no_actividad = '/dias_no_actividad/' . $pagina . '/';
                $ruta_destino = RUTA_APP . "/dias_no_actividadVista/dias_no_actividad/" . $rows['id_dias_no_actividad'] . $ruta_dias_no_actividad;

                $tabla .= '
                        <tr class="has-text-centered table-row">
                            <td>' . $contador . '</td>
                            <td>' . $rows['fecha'] . '</td>
                            <td>' . $rows['motivo'] . '</td>
                            <td>' . $rows['tipo'] . '</td>
                            <td>' . $rows['status'] . '</td>
                            <td>
                                <a href="' . $ruta_destino. '/' . $rows['id'] . '" class="button is-link is-rounded is-small" id="a_id" name="a_id"><span class="fas fa-eye"></a>
                            </td>
                            <td>
                                <form class="FormularioAjax" action="' . APP_URL . 'app/ajax/dias_no_actividadAjax.php" method="POST" autocomplete="off" >
                                    <input type="hidden" name="modulo_dias_no_actividad" value="eliminar">
                                    <input type="hidden" name="id_dias_no_actividad" id="id_dias_no_actividad" value="' . $rows['id'] . '">
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
                'n0' => 0,
                'n1' => 0
            ];
            $tabla .= $this->paginadorTablas($pagina, $numeroPaginas, $url, 7, $id_codigos);
        }
        return $tabla;
    }

    public function crear_registro($dias_no_actividad)
    {
        try {
            $dias_no_actividad = $this->limpiarCadena($dias_no_actividad);

            $datos = [
                ['campo_nombre' => 'dias_no_actividad', 'campo_marcador' => ':dias_no_actividad', 'campo_valor' => $dias_no_actividad],
            ];

            $id_dias_no_actividad = $this->guardarDatos('dias_no_actividad', $datos);
            $this->log("Clasificacion insertado: Clasificacion = {$id_dias_no_actividad}");

            if ($id_dias_no_actividad) {
                $this->log("Nuevo registro de dias_no_actividad creado: " . $dias_no_actividad);
                echo json_encode([
                    'tipo' => 'success',
                    'titulo' => 'Success',
                    'texto' => 'The new address classification has been successfully registered.'
                ]);
            } else {
                $this->log("Error al crear el nuevo registro de dias_no_actividad: " . $dias_no_actividad, true);
                echo json_encode([
                    'tipo' => 'error',
                    'titulo' => 'Error',
                    'texto' => 'There was an error registering the new address classification. Please try again.'
                ]);
            }
        } catch (Exception $e) {
            $this->log("Excepción al crear el nuevo registro de dias_no_actividad: " . $e->getMessage(), true);
            echo json_encode([
                'tipo' => 'error',
                'titulo' => 'Exception',
                'texto' => 'An exception occurred while registering the new address classification: ' . $e->getMessage()
            ]);
        }
    }

    public function consultar_dias_no_actividad()
    {
        $sql = 'SELECT id_dias_no_actividad, dias_no_actividad
            FROM dias_no_actividad';
        
        $param = [];

        $data = $this->ejecutarConsulta($sql, "", $param, "fetchAll");

        return $data;
    }

    /* ==========================================
    API: Obtener Resumen de Fecha (MDS)
    ========================================== */
    public function cargar_fecha($fecha_consulta)
    {
        $this->log("Inicio de Consulta para la fecha: " . $fecha_consulta);
        try {
            // 1. Validar si es día laborable
            $sql_validacion = "
                SELECT 
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 FROM dias_no_actividad 
                            WHERE DATE(FECHA) = DATE(:fecha1)
                        ) THEN 0
                        ELSE 1
                    END AS es_laborable,
                    (
                        SELECT FECHA 
                        FROM dias_no_actividad 
                        WHERE DATE(FECHA) > DATE(:fecha2)
                        ORDER BY DATE(FECHA) ASC
                        LIMIT 1
                    ) AS proximo_no_laborable
                FROM DUAL";
            
            $params = [
                ':fecha1' => $fecha_consulta,
                ':fecha2' => $fecha_consulta
            ];
            
            $validacion = $this->ejecutarConsulta(
                $sql_validacion, 
                "", 
                $params
            );
            $this->log("Determinar si es laborable: " . json_encode($validacion));

            $es_laborable = $validacion['es_laborable'] == 1;
            
            // 2. Obtener resumen de rutas y servicios
            $sql_resumen = "SELECT 
                COUNT(DISTINCT id_ruta) AS total_rutas,
                COUNT(*) AS total_servicios,
                SUM(
                    CASE 
                        WHEN hora_aviso_usuario IS NOT NULL 
                        AND hora_inicio_gps IS NOT NULL 
                        THEN 1 ELSE 0 
                    END
                ) AS fully_completed,
                SUM(
                    CASE 
                        WHEN (hora_aviso_usuario IS NOT NULL AND hora_inicio_gps IS NULL)
                        OR (hora_aviso_usuario IS NULL AND hora_inicio_gps IS NOT NULL)
                        THEN 1 ELSE 0 
                    END
                ) AS partially_confirmed,
                SUM(
                    CASE 
                        WHEN hora_aviso_usuario IS NULL 
                        AND hora_inicio_gps IS NULL
                        AND estado_servicio NOT IN ('cancelado', 'replanificado', 'no_servido')
                        THEN 1 ELSE 0 
                    END
                ) AS not_confirmed,
                SUM(
                    CASE 
                        WHEN estado_servicio = 'pendiente' 
                        AND hora_aviso_usuario IS NULL 
                        AND hora_inicio_gps IS NULL
                        THEN 1 ELSE 0 
                    END
                ) AS pendientes,
                SUM(
                    CASE 
                        WHEN estado_servicio IN ('cancelado', 'replanificado') 
                        THEN 1 ELSE 0 
                    END
                ) AS no_completados
                FROM servicios 
                WHERE fecha_programada = :fecha
                AND id_status != 39";

            $params =[
                ':fecha' => $fecha_consulta
            ];

            $resumen = $this->ejecutarConsulta(
                $sql_resumen, 
                "", 
                $params
            );
            $this->log("Resumen del servicio: " . json_encode($resumen));
            
            // 3. Mensaje de alerta
            $mensaje_alerta = '';
            if (!$es_laborable) {
                $mensaje_alerta = 'This is a non-working day. Next working day: ' . 
                                date('M d, Y', strtotime($validacion['proximo_no_laborable']));
            }

			http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => [
                    'fecha_consulta' => $fecha_consulta,
                    'es_laborable' => $es_laborable,
                    'mensaje_alerta' => $mensaje_alerta,
                    'resumen' => [
                        'total_rutas' => (int)($resumen['total_rutas'] ?? 0),
                        'total_servicios' => (int)($resumen['total_servicios'] ?? 0),
                        'finalizados' => (int)($resumen['finalizados'] ?? 0),
                        'pendientes' => (int)($resumen['pendientes'] ?? 0),
                        'no_completados' => (int)($resumen['no_completados'] ?? 0)
                    ]
                ]
            ], JSON_PRETTY_PRINT);
            
        } catch (Exception $e) {
			$this->logWithBacktrace("Error en cargar_fecha: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode([
                'success' => false,
                'message' => 'Error loading summary'
            ]);
        }
    }

    /* ==========================================
    API: Validar Fecha Laborable
    ========================================== */
    public function validar_fecha_laborable($fecha) {
        try {
            $sql = "
                SELECT 
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 FROM dias_no_actividad 
                            WHERE DATE(FECHA) = DATE(:fecha)
                        ) THEN 0
                        ELSE 1
                    END AS es_laborable
                FROM DUAL
            ";
            
            $result = $this->ejecutarConsulta(
                $sql, 
                "", 
                [':fecha' => $fecha], 
                "fetch"
            );
            
			http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => [
                    'es_laborable' => $result['es_laborable'] == 1,
                    'fecha' => $fecha
                ]
            ], JSON_PRETTY_PRINT);
            
        } catch (Exception $e) {
			$this->logWithBacktrace("Error en cargar_fecha: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode([
                'success' => false,
                'data' => ['es_laborable' => true]
            ]);
        }
    }
}
