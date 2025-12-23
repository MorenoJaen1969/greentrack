<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
class direccionesController extends mainModel
{
	private $log_path;
	private $logFile;
	private $errorLogFile;
	private $id_status_cancelado;
	private $id_status_activo;
	private $id_status_inactivo;
	private $id_status_pendiente;
	private $id_status_eliminado;
	private $id_status_revisar;
	private $id_status_en_espera;
	private $id_status_verificado;
	private $id_status_no_verificado;
	private $o_f;

	public function __construct()
	{
		// ¡ESTA LÍNEA ES CRUCIAL!
		parent::__construct();

		// Nombre del controlador actual abreviado para reconocer el archivo
		$nom_controlador = "direccionesController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/direcciones/';

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

		$this->id_status_activo = 10;
		$this->id_status_inactivo = 11;
		$this->id_status_pendiente = 12;
		$this->id_status_eliminado = 13;
		$this->id_status_revisar = 14;
		$this->id_status_en_espera = 15;
		$this->id_status_verificado = 16;
		$this->id_status_no_verificado = 17;
		
		
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

    public function listardireccionesControlador($dato_ori)
	{
		$pagina_direcciones = $dato_ori[0];
		$registrosPorPagina = $dato_ori[1];
		$url1 = $dato_ori[2];
		$busca_frase = $dato_ori[3];
		$ruta_retorno = $dato_ori[4];
		$orden = $dato_ori[5];
		$direccion  = $dato_ori[6];

		$pagina = (isset($pagina_direcciones) && $pagina_direcciones > 0) ? (int) $pagina_direcciones : 1;
        $pagina = $this->limpiarCadena($pagina);
        $registros = $this->limpiarCadena($registrosPorPagina);

		$url = $this->limpiarCadena($url1);
        $url_origen = $url;
        $url = RUTA_APP . "/" . $url . "/";
        $tabla = "";

        $busqueda = $this->limpiarCadena($busca_frase);

        $inicio = ($pagina > 0) ? (($pagina * $registrosPorPagina) - $registrosPorPagina) : 0;
	
		$campos = "
			d.*,
			ac.address_clas,
			at.address_type,
			s.status,
			COALESCE(c.nombre, p.nombre, 'Sin nombre') AS nombre_entidad,
			pa.nombre AS pais,
			es.nombre AS estado,
			co.nombre AS condado,
			ci.nombre AS ciudad,
			cp.codigo AS zip
		";

		// === Generar la cláusula ORDER BY ===
		$orden_r = "nombre_entidad";

		if (isset($busqueda) && $busqueda != "") { 
			$campos_filtro = "
				(c.nombre LIKE '%$busqueda%' OR p.nombre LIKE '%$busqueda%' OR d.direccion LIKE '%$busqueda%')
			";

			$consulta_datos = "SELECT " . $campos . " 
				FROM direcciones d
				LEFT JOIN address_clas AS ac ON d.id_address_clas = ac.id_address_clas
				LEFT JOIN address_type AS at ON d.id_address_type = at.id_address_type
				LEFT JOIN status_all AS s ON d.id_status = s.id_status
				LEFT JOIN clientes c ON d.id_cliente = c.id_cliente AND d.id_address_clas = 1
				LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor AND d.id_address_clas = 3
				LEFT JOIN paises pa ON d.id_pais = pa.id_pais
				LEFT JOIN estados es ON d.id_estado = es.id_estado
				LEFT JOIN condados co ON d.id_condado = co.id_condado
				LEFT JOIN ciudades ci ON d.id_ciudad = ci.id_ciudad
				LEFT JOIN codigos_postales cp ON d.id_zip = cp.id_zip
				WHERE (" . $campos_filtro . ") 
				AND d.id_status != " . $this->id_status_eliminado . "
				ORDER BY " . $orden_r . "
				LIMIT " . $inicio . ", " . $registros;

			$consulta_total = "SELECT COUNT(d.id_direccion) AS total 
				FROM direcciones d
				LEFT JOIN clientes c ON d.id_cliente = c.id_cliente AND d.id_address_clas = 1
				LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor AND d.id_address_clas = 3
				LEFT JOIN address_clas AS ac ON d.id_address_clas = ac.id_address_clas
				LEFT JOIN address_type AS at ON d.id_address_type = at.id_address_type
				LEFT JOIN status_all AS s ON d.id_status = s.id_status
				WHERE (" . $campos_filtro . ") 
				AND d.id_status != " . $this->id_status_eliminado;
		} else {
			$consulta_datos = "SELECT " . $campos . " 
				FROM direcciones d
				LEFT JOIN address_clas AS ac ON d.id_address_clas = ac.id_address_clas
				LEFT JOIN address_type AS at ON d.id_address_type = at.id_address_type
				LEFT JOIN status_all AS s ON d.id_status = s.id_status
				LEFT JOIN clientes c ON d.id_cliente = c.id_cliente AND d.id_address_clas = 1
				LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor AND d.id_address_clas = 3
				LEFT JOIN paises pa ON d.id_pais = pa.id_pais
				LEFT JOIN estados es ON d.id_estado = es.id_estado
				LEFT JOIN condados co ON d.id_condado = co.id_condado
				LEFT JOIN ciudades ci ON d.id_ciudad = ci.id_ciudad
				LEFT JOIN codigos_postales cp ON d.id_zip = cp.id_zip
				WHERE d.id_status != " . $this->id_status_eliminado . "
				ORDER BY " . $orden_r . "
				LIMIT " . $inicio . ", " . $registros;

			$consulta_total = "SELECT COUNT(d.id_direccion) AS total 
				FROM direcciones d
				LEFT JOIN clientes c ON d.id_cliente = c.id_cliente AND d.id_address_clas = 1
				LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor AND d.id_address_clas = 3
				LEFT JOIN address_clas AS ac ON d.id_address_clas = ac.id_address_clas
				LEFT JOIN address_type AS at ON d.id_address_type = at.id_address_type
				LEFT JOIN status_all AS s ON d.id_status = s.id_status
				WHERE d.id_status != " . $this->id_status_eliminado;
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
			<div id="tabla-direcciones-wrapper">
				<div class="table-container">
					<table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
						<thead class="cabecera">
							<tr>
								<th class="has-text-centered">#</th>
								<th class="has-text-centered">Address</th>
								<th class="has-text-centered">Register</th>
								<th class="has-text-centered">Clasification</th>
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

                $ruta_direcciones = '/direcciones/' . $pagina . '/';
                $ruta_destino = RUTA_APP . "/direccionesVista/" . $rows['id_direccion'] . "/" . $rows['id_address_clas'] . $ruta_direcciones;

				$id_direccion = $rows['id_direccion'];
				$sql = "SELECT COUNT(s.id_direccion) AS total_servicio 
                    FROM servicios AS s
					WHERE s.id_status != 39 AND s.id_direccion = " . $id_direccion;
		
				$total_s = $this->ejecutarConsulta($sql, "", [], "fetchColumn");

                $ruta_det_servicio = RUTA_APP . "/serviciosLista" . $ruta_direcciones;
				$href = ($total_s > 0) 
					? $ruta_det_servicio . $rows['id_direccion'] 
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

                $tabla .= '
                        <tr class="has-text-centered table-row">
                            <td>' . $contador . '</td>
                            <td>' . $rows['direccion'] . '
								<div class="grid_cuatro">
									<div class="grid_cuatro_1">
										Country: ' . $rows['pais'] . ' 
									</div>
									<div class="grid_cuatro_2">
										State: ' . $rows['estado'] . ' 
									</div>
									<div class="grid_cuatro_3">
										County: ' . $rows['condado'] . ' 
									</div>
									<div class="grid_cuatro_4">
										City: ' . $rows['ciudad'] . ' 
									</div>
									<div class="grid_cuatro_5">
										ZIP: ' . $rows['zip'] . ' 
									</div>
								</div>
							</td>
                            <td>' . $rows['nombre_entidad'] . '</td>
                            <td>' . $rows['address_clas'] . '</td>
                            <td>' . $rows['address_type'] . '</td>
                            <td>' . $rows['status'] . '</td>
                            <td>
								<a href="' . $href . '" 
									class="' . $claseBoton . '" 
									id="c_id_direccion" 
									name="c_id_direccion"
									style="' . $estilo . '"
									title="' . ($total_s > 0 ? 'View services for this address' : 'No services assigned') . '">
										<span class="fas fa-person-digging"> '. $total_s .'
								</a>
							</td>
							<td>
                                <a href="' . $ruta_destino . '" 
									class="button is-link is-rounded is-small" 
									id="a_id_direccion" 
									name="a_id_direccion"
									title="View address details">
										<span class="fas fa-eye">
								</a>
                            </td>
							<td>
								<button type="button" 
										class="button is-danger is-rounded is-small btn-eliminar-direccion"
										data-id="' . $rows['id_direccion'] . '"
										data-pagina="' . $pagina . '"
										data-registros="' . $registrosPorPagina . '"
										data-url="' . $url_origen . '"
										title="Delete Address"
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
                        <p class="has-text-right">Displaying Address <strong>' . $pag_inicio . '</strong> to <strong>' . $pag_final . '</strong> of a total of <strong>' . $total . '</strong></p>
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

	public function eliminar_direccion($id_direccion)
	{
		$sql = "SELECT id_servicio
			FROM servicios
			WHERE id_direccion = :v_id_direccion";
		$param = [
			"v_id_direccion" => $id_direccion
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
					'condicion_campo' => 'id_direccion',
					'condicion_operador' => '=', 
					'condicion_marcador' => ':id_direccion',
					'condicion_valor' => $id_direccion
				];

				$this->actualizarDatos('direcciones', $datos, $condicion);

				http_response_code(200);
				echo json_encode(['success' => 'ok', 'message' => 'Update completed']);

			} catch (Exception $e) {
				$this->logWithBacktrace("Error en finalizarServicio: " . $e->getMessage(), true);
				http_response_code(500);
				echo json_encode(['error' => 'Could not update']);
			}
		}
	}

    public function crear_registro($direcciones)
    {
        try {
            $direcciones = $this->limpiarCadena($direcciones);

            $datos = [
                ['campo_nombre' => 'direcciones', 'campo_marcador' => ':direcciones', 'campo_valor' => $direcciones],
            ];

            $id_direccion = $this->guardarDatos('direcciones', $datos);
            $this->log("Clasificacion insertado: Clasificacion = {$id_direccion}");

            if ($id_direccion) {
                $this->log("Nuevo registro de direcciones creado: " . $direcciones);
                echo json_encode([
                    'tipo' => 'success',
                    'titulo' => 'Success',
                    'texto' => 'The new address classification has been successfully registered.'
                ]);
            } else {
                $this->log("Error al crear el nuevo registro de direcciones: " . $direcciones, true);
                echo json_encode([
                    'tipo' => 'error',
                    'titulo' => 'Error',
                    'texto' => 'There was an error registering the new address classification. Please try again.'
                ]);
            }
        } catch (Exception $e) {
            $this->log("Excepción al crear el nuevo registro de direcciones: " . $e->getMessage(), true);
            echo json_encode([
                'tipo' => 'error',
                'titulo' => 'Exception',
                'texto' => 'An exception occurred while registering the new address classification: ' . $e->getMessage()
            ]);
        }
    }

    public function actualizar_direccion($id_direccion, $direccion, $lat, $lng, $cambio)
    {
		try {
			// ... después de obtener $lat y $lng del formulario ...
			$id_zona = null;
			if (!empty($lat) && !empty($lng)) {
				$id_zona = $this->encontrarZonaPorCoordenadas($lat, $lng);
			}

			$datos = [
				['campo_nombre' => 'direccion', 'campo_marcador' => ':direccion', 'campo_valor' => $direccion],
				['campo_nombre' => 'lat', 'campo_marcador' => ':lat', 'campo_valor' => $lat],
				['campo_nombre' => 'lng', 'campo_marcador' => ':lng', 'campo_valor' => $lng],
				['campo_nombre' => 'cambio', 'campo_marcador' => ':cambio', 'campo_valor' => $cambio],
				['campo_nombre' => 'id_zona', 'campo_marcador' => ':id_zona', 'campo_valor' => $id_zona]
			];
			$condicion = [
				'condicion_campo' => 'id_direccion',
				'condicion_operador' => '=',  
				'condicion_marcador' => ':id_direccion',
				'condicion_valor' => $id_direccion
			];

            $this->log("Direccion a ser modificada: " . json_encode($datos) . " condicion: " . json_encode($condicion));
			$respuesta = $this->actualizarDatos('direcciones', $datos, $condicion);
            $this->log("Resultado: " . $respuesta);

			if (!headers_sent()) {
				http_response_code(200);
				header('Content-Type: application/json');
			} else {
				error_log("Advertencia: Algunos encabezados ya fueron enviados antes de la respuesta JSON.");
				// O manejar el error
			}

			if ($respuesta > 0) {
				echo json_encode(['status' => 'ok', 'message' => 'Direccion Actualizada'], JSON_PRETTY_PRINT);
				exit; // Salir inmediatamente después de enviar el JSON
			} else {
				echo json_encode(['error' => 'fail', 'message' => 'Direccion Actualizada Fallo'], JSON_PRETTY_PRINT);
				exit; // Salir inmediatamente después de enviar el JSON
			}

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en actualizar_direccion: " . $e->getMessage(), true);
			http_response_code(500);
			echo json_encode(['error' => 'No se pudo finalizar']);
		}
    }

	public function direccionesCompleto($id_direccion, $id_address_clas) 
	{
		if ($id_address_clas==1) {
			$tabla = "clientes AS o ON d.id_cliente = o.id_cliente";
		} elseif ($id_address_clas==3) {
			$tabla = "proveedores  AS o ON d.id_proveedor = o.id_proveedor";
		}

		$sql = "SELECT d.*, pa.nombre AS pais, es.nombre AS estado, co.nombre AS condado, ci.nombre AS ciudad, cp.codigo AS zip, 
				geo.id_geofence, geo.tipo, geo.geofence_data, zc.nombre_zona
			FROM direcciones AS d
			LEFT JOIN " . $tabla . " 
			LEFT JOIN paises pa ON d.id_pais = pa.id_pais
			LEFT JOIN estados es ON d.id_estado = es.id_estado
			LEFT JOIN condados co ON d.id_condado = co.id_condado
			LEFT JOIN ciudades ci ON d.id_ciudad = ci.id_ciudad
			LEFT JOIN codigos_postales cp ON d.id_zip = cp.id_zip
			LEFT JOIN geofence geo ON d.id_direccion = geo.id_direccion
			LEFT JOIN zonas_cuadricula AS zc ON zc.id_zona = zc.id_zona
			WHERE d.id_direccion = :v_id_direccion";		
		$param = [
			"v_id_direccion" => $id_direccion
		];	
        
		$datos = $this->ejecutarConsulta($sql, "", $param);

		return $datos;
	}

	public function guardarCambios($tabla, $datos, $condicion)
	{
        try {
			$resulta = $this->actualizarDatos($tabla, $datos, $condicion);
            if ($resulta) {
                echo json_encode(['tipo' => 'success', 'texto' => 'Dirección actualizada correctamente']);
            } else {
                throw new Exception("No se pudo actualizar la dirección");
            }
		} catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['tipo' => 'error', 'texto' => $e->getMessage()]);
        }

	}

	public function guardarGeofence($tabla, $datos, $condicion, $id_direccion, $id_geofence, $validaEmpty)
	{
		try {
			// 🔑 Sanitizar id_geofence: convertir a entero o null
			$id_geofence = ($id_geofence === '' || $id_geofence === null) ? null : (int)$id_geofence;
			$id_direccion = (int)$id_direccion;

			$cant_reg = count((array)$condicion);

			if ($cant_reg > 0) {
	            if ($id_geofence !== null && $id_geofence > 0) {
					$query2 = "
						UPDATE direcciones 
							SET id_geofence = NULL 
							WHERE id_geofence = :v_id_geofence";

					$params2 = [
						':v_id_geofence' => $id_geofence
					];

					$filas = $this->ejecutarConsulta($query2, '', $params2);
					
					if ($filas > 0) {
						$query2 = "DELETE FROM geofence WHERE id_direccion = :v_id_direccion";
						$params2 = [
							':v_id_direccion' => $id_direccion
						];

						if ($this->ejecutarConsulta($query2, '', $params2)){
							echo json_encode(['tipo' => 'success', 'texto' => 'Geofence updated']);
						} else {
							throw new Exception("The address could not be updated");
						}	
					} else {
						// Puede que ya esté en NULL, así que igual intentamos borrar en geofence
						$query2 = "DELETE FROM geofence WHERE id_direccion = :v_id_direccion";
						$params2 = [':v_id_direccion' => $id_direccion];
						$this->ejecutarConsulta($query2, '', $params2);
						echo json_encode(['tipo' => 'success', 'texto' => 'Geofence cleared']);
                	    return;
					}
				}
			} else {
				$id_geofence = $this->guardarDatos($tabla, $datos);

				$datos01 = [
					['campo_nombre' => 'id_geofence', 'campo_marcador' => ':id_geofence', 'campo_valor' => $id_geofence]
				];

				$condicion01 = [
					'condicion_campo' => 'id_direccion',
					'condicion_operador' => '=',
					'condicion_marcador' => ':id_direccion',
					'condicion_valor' => $id_direccion
				];

				$this->actualizarDatos('direcciones', $datos01, $condicion01);

				echo json_encode(['tipo' => 'success', 'texto' => 'Geofence created']);
			}
        } catch (Exception $e) {
			$this->logWithBacktrace("Error en guardarGeofence: " . $e->getMessage(), true);
            http_response_code(400);
            echo json_encode(['tipo' => 'error', 'texto' => $e->getMessage()]);
        }
	}

	public function obtenerGpsLocationIQSoloConsulta($apikey, $direccion)
	{
		$this->log("=== INICIO: Direccion Actual a partir de coordenadas ===");
		try {
			$direccionCodificada = urlencode($direccion);
			$url = "https://us1.locationiq.com/v1/search?key={$apikey}&q={$direccionCodificada}&format=json&addressdetails=1";

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

			echo json_encode([
				'success' => true,
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

	// Verifica o crea un país y devuelve su ID
	public function verifica_pais($nombre, $codigo_iso2_dato) {
		if (empty($nombre)) return null;

		// Primero intentar por código ISO (más confiable)
		if (!empty($codigo_iso2_dato)) {
			$sql = "SELECT id_pais FROM paises WHERE codigo_iso2 = :iso2";
			$param = [':iso2' => $codigo_iso2_dato];
			$row = $this->ejecutarConsulta($sql, '', $param);
			if ($row) return $row['id_pais'];
		}

		$sql = "SELECT id_pais FROM paises 
            WHERE UPPER(nombre) LIKE UPPER(CONCAT('%', :v_nombre, '%')) 
            LIMIT 1";

		$param = [
			':v_nombre' => $nombre
		];
		
		$row = $this->ejecutarConsulta($sql, '', $param);

		if ($row) {
			return $row['id_pais'];
		}

		$datos = [
			['campo_nombre' => 'nombre', 'campo_marcador' => ':nombre', 'campo_valor' => $nombre],
			['campo_nombre' => 'codigo_iso2', 'campo_marcador' => ':codigo_iso2', 'campo_valor' => $codigo_iso2_dato],
			['campo_nombre' => 'codigo_iso3', 'campo_marcador' => ':codigo_iso3', 'campo_valor' => $codigo_iso2_dato],
		];

		$id_pais = $this->guardarDatos('paises', $datos);

		return $id_pais;
	}

	// Verifica o crea un estado (asociado a un país)
	public function verifica_estado($nombre, $id_pais) {
		if (empty($nombre) || empty($id_pais)) return null;

		$nombre = trim($nombre);
   		$abreviatura = strtoupper(substr($nombre, 0, 2)); 

		$sql = "SELECT id_estado FROM estados 
				WHERE UPPER(nombre) LIKE UPPER(CONCAT('%', :v_nombre, '%')) 
				AND id_pais = :v_id_pais 
				LIMIT 1";

		$param = [
			':v_nombre' => $nombre,
			':v_id_pais' => $id_pais
		];

		$row = $this->ejecutarConsulta($sql, '', $param);

		if ($row) {
			return $row['id_estado'];
		}

		$datos = [
			['campo_nombre' => 'nombre', 'campo_marcador' => ':nombre', 'campo_valor' => $nombre],
			['campo_nombre' => 'id_pais', 'campo_marcador' => ':id_pais', 'campo_valor' => $id_pais],
			['campo_nombre' => 'abreviatura', 'campo_marcador' => ':abreviatura', 'campo_valor' => $abreviatura]
		];

		return $this->guardarDatos('estados', $datos);
	}

	// Verifica o crea un condado (asociado a un estado)
	public function verifica_condado($nombre, $id_estado) {
		if (empty($nombre) || empty($id_estado)) return null;

		$nombre = trim($nombre);

		$sql = "SELECT id_condado FROM condados 
				WHERE UPPER(nombre) LIKE UPPER(CONCAT('%', :v_nombre, '%')) 
				AND id_estado = :v_id_estado 
				LIMIT 1";

		$param = [
			':v_nombre' => $nombre,
			':v_id_estado' => $id_estado
		];

		$row = $this->ejecutarConsulta($sql, '', $param);

		if ($row) {
			return $row['id_condado'];
		}

		$datos = [
			['campo_nombre' => 'nombre', 'campo_marcador' => ':nombre', 'campo_valor' => $nombre],
			['campo_nombre' => 'id_estado', 'campo_marcador' => ':id_estado', 'campo_valor' => $id_estado],
		];

		return $this->guardarDatos('condados', $datos);
	}

	// Verifica o crea una ciudad (asociada a un condado o estado)
	public function verifica_ciudad($nombre, $id_condado = null, $id_estado = null) {
		if (empty($nombre)) return null;

		$nombre = trim($nombre);

		// Prioridad 1: buscar por condado
		if (!empty($id_condado)) {
			$sql = "SELECT id_ciudad FROM ciudades 
					WHERE UPPER(nombre) LIKE UPPER(CONCAT('%', :v_nombre, '%')) 
					AND id_condado = :v_id_condado 
					LIMIT 1";

			$param = [
				':v_nombre' => $nombre,
				':v_id_condado' => $id_condado
			];

			$row = $this->ejecutarConsulta($sql, '', $param);
			if ($row) return $row['id_ciudad'];

			// Crear asociado al condado
			$datos = [
				['campo_nombre' => 'nombre', 'campo_marcador' => ':nombre', 'campo_valor' => $nombre],
				['campo_nombre' => 'id_condado', 'campo_marcador' => ':id_condado', 'campo_valor' => $id_condado],
			];
			return $this->guardarDatos('ciudades', $datos);
		}

		// Prioridad 2: buscar/crear por estado
		if (!empty($id_estado)) {
			$sql = "SELECT id_ciudad FROM ciudades 
					WHERE UPPER(nombre) LIKE UPPER(CONCAT('%', :v_nombre, '%')) 
					AND id_estado = :v_id_estado 
					LIMIT 1";

			$param = [
				':v_nombre' => $nombre,
				':v_id_estado' => $id_estado
			];

			$row = $this->ejecutarConsulta($sql, '', $param);
			if ($row) return $row['id_ciudad'];

			$datos = [
				['campo_nombre' => 'nombre', 'campo_marcador' => ':nombre', 'campo_valor' => $nombre],
				['campo_nombre' => 'id_estado', 'campo_marcador' => ':id_estado', 'campo_valor' => $id_estado],
			];
			return $this->guardarDatos('ciudades', $datos);
		}

		// Último recurso: sin asociación
		$sql = "SELECT id_ciudad FROM ciudades 
				WHERE UPPER(nombre) LIKE UPPER(CONCAT('%', :v_nombre, '%')) 
				LIMIT 1";

		$param = [':v_nombre' => $nombre];
		$row = $this->ejecutarConsulta($sql, '', $param);
		if ($row) return $row['id_ciudad'];

		$datos = [
			['campo_nombre' => 'nombre', 'campo_marcador' => ':nombre', 'campo_valor' => $nombre],
		];
		return $this->guardarDatos('ciudades', $datos);
	}

	// Busca un código postal en tu base cercano a las coordenadas dadas
	public function buscarZipPorCoordenadas($lat, $lng, $id_pais, $id_estado = null, $zip_sugerido) {
		if (empty($lat) || empty($lng) || empty($id_pais)) return null;

		// Radio aumentado a ±0.05 grados (~5.5 km)
		$lat_min = $lat - 0.05;
		$lat_max = $lat + 0.05;
		$lng_min = $lng - 0.05;
		$lng_max = $lng + 0.05;

		$where = "id_pais = :id_pais 
				AND lat BETWEEN :lat_min AND :lat_max 
				AND lng BETWEEN :lng_min AND :lng_max
				AND lat IS NOT NULL 
				AND lng IS NOT NULL";
		$params = [
			':id_pais' => $id_pais,
			':lat_min' => $lat_min,
			':lat_max' => $lat_max,
			':lng_min' => $lng_min,
			':lng_max' => $lng_max
		];

		if (!empty($id_estado)) {
			$where .= " AND id_estado = :id_estado";
			$params[':id_estado'] = $id_estado;
		}

		$sql = "SELECT id_zip, codigo, id_ciudad, 
			POW(lat - :lat_ref, 2) + POW(lng - :lng_ref, 2) AS distancia
			FROM codigos_postales 
			WHERE {$where} 
			ORDER BY distancia
			LIMIT 5";

		$params[':lat_ref'] = $lat;
		$params[':lng_ref'] = $lng;

		$resultado = $this->ejecutarConsulta($sql, 'fetch', $params, "fetchAll");

		foreach ($resultado as $r) {
			if ($r['codigo'] == $zip_sugerido) {
				return $r;
			}
		}

		return $resultado[0] ?? null;

	}

	// Verifica o crea un código postal con contexto geográfico
	public function verifica_codigo_postal($codigo, $id_ciudad = null, $id_condado = null, $id_estado = null, $id_pais = null) {
		if (empty($codigo) || empty($id_pais)) return null;

		$codigo = trim($codigo);

		$where = "codigo = :codigo AND id_pais = :id_pais";
		$params = [':codigo' => $codigo, ':id_pais' => $id_pais];

		if (!empty($id_ciudad)) {
			$where .= " AND id_ciudad = :id_ciudad";
			$params[':id_ciudad'] = $id_ciudad;
		} elseif (!empty($id_condado)) {
			$where .= " AND id_condado = :id_condado";
			$params[':id_condado'] = $id_condado;
		} elseif (!empty($id_estado)) {
			$where .= " AND id_estado = :id_estado";
			$params[':id_estado'] = $id_estado;
		}

		$sql = "SELECT id_zip FROM codigos_postales WHERE {$where} LIMIT 1";
		$row = $this->ejecutarConsulta($sql, '', $params);

		if ($row) {
			return $row['id_zip'];
		}

		$datos = [
			['campo_nombre' => 'codigo', 'campo_marcador' => ':codigo', 'campo_valor' => $codigo],
			['campo_nombre' => 'id_pais', 'campo_marcador' => ':id_pais', 'campo_valor' => $id_pais],
		];
		if (!empty($id_estado)) $datos[] = ['campo_nombre' => 'id_estado', 'campo_marcador' => ':id_estado', 'campo_valor' => $id_estado];
		if (!empty($id_condado)) $datos[] = ['campo_nombre' => 'id_condado', 'campo_marcador' => ':id_condado', 'campo_valor' => $id_condado];
		if (!empty($id_ciudad)) $datos[] = ['campo_nombre' => 'id_ciudad', 'campo_marcador' => ':id_ciudad', 'campo_valor' => $id_ciudad];

		return $this->guardarDatos('codigos_postales', $datos);
	}

	// Método principal: obtiene IDs geográficos usando coordenadas como fuente de verdad
	public function obtenerIdsGeograficos($datos_geo, $lat_api = null, $lng_api = null) {
		try {
			$pais = $datos_geo['pais_dato'] ?? null;
			$codigo_iso2 = $datos_geo['codigo_iso2_dato'] ?? null;
			$estado = $datos_geo['estado_dato'] ?? null;
			$condado = $datos_geo['condado_dato'] ?? null;
			$ciudad = $datos_geo['ciudad_dato'] ?? null;
			$zip_sugerido = $datos_geo['zip_sugerido'] ?? null;

			$id_pais = $this->verifica_pais($pais, $codigo_iso2);
			if (!$id_pais) throw new Exception('País no identificado');

			$id_estado = $estado ? $this->verifica_estado($estado, $id_pais) : null;
			$id_condado = $condado ? $this->verifica_condado($condado, $id_estado) : null;
			$id_ciudad = $ciudad ? $this->verifica_ciudad($ciudad, $id_condado, $id_estado) : null;

			// === Paso 1: Intentar encontrar ZIP por coordenadas ===
			$id_zip = null;
			$zip_usar = null;

error_log("Datos actuales; ". json_encode($datos_geo)." id_pais: ". $id_pais." id_estado: ". $id_estado." id_condado: ". $id_condado. $id_estado." id_ciudad: ". $id_ciudad);
			if (!empty($lat_api) && !empty($lng_api)) {
				$zip_encontrado = $this->buscarZipPorCoordenadas($lat_api, $lng_api, $id_pais, $id_estado, $zip_sugerido);
				if ($zip_encontrado) {
					$id_zip = $zip_encontrado['id_zip'];
					$zip_usar = $zip_encontrado['codigo'];
				}
			}
error_log("Valor de zip: ". $id_zip . " y " . $zip_usar);
			// === Paso 2: Si no se encontró (o quieres forzar el ZIP del usuario), usar zip_sugerido ===
			if (empty($id_zip) && !empty($zip_sugerido)) {
				// Verificar/crear el ZIP sugerido con el contexto geográfico
				$id_zip = $this->verifica_codigo_postal(
					$zip_sugerido, 
					$id_ciudad, 
					$id_condado, 
					$id_estado, 
					$id_pais
				);
				if ($id_zip) {
					$zip_usar = $zip_sugerido;
				}
			}

			echo json_encode([
				'success' => true,
				'ids' => [
					'id_pais' => $id_pais,
					'id_estado' => $id_estado,
					'id_condado' => $id_condado,
					'id_ciudad' => $id_ciudad,
					'id_zip' => $id_zip,
					'zip_usado' => $zip_usar
				]
			]);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error en obtenerIdsGeograficos: " . $e->getMessage(), true);
			echo json_encode(['success' => false, 'error' => 'Error al procesar ubicación']);
		}
	}

	/**
	* Extrae un código postal de EE.UU. (5 dígitos o 5-4 dígitos) de una cadena de dirección.
	*
	* @param string $direccion Cadena de texto con la dirección completa.
	* @return string|null Código postal encontrado (solo los 5 primeros dígitos), o null si no se encuentra.
	*/
	private function extraerZipDeDireccion(string $direccion): ?string
	{
		// Buscar todos los grupos de 5 dígitos (con o sin ZIP+4)
		preg_match_all('/\b(\d{5})(?:-\d{4})?\b/', $direccion, $coincidencias, PREG_SET_ORDER);

		if (empty($coincidencias)) {
			return null;
		}

		// Tomar el ÚLTIMO grupo encontrado (más cercano al final)
		$ultima = end($coincidencias);
		return $ultima[1];
	}

	public function cant_servicios($id_direccion)
	{
		$sql = "SELECT COUNT(s.id_direccion) AS total_servicio 
			FROM servicios AS s
			WHERE s.id_status != 39 AND s.id_direccion = " . $id_direccion;
		
		$total_s = $this->ejecutarConsulta($sql, "", [], "fetchColumn");
		return $total_s;
	}

	/**
	 * Encuentra la zona de cuadrícula que contiene las coordenadas dadas.
	 *
	 * @param float $lat
	 * @param float $lng
	 * @return int|null ID de la zona o null si no se encuentra
	 */
	public function encontrarZonaPorCoordenadas($lat, $lng) {
		if (empty($lat) || empty($lng)) return null;

		$sql = "
			SELECT id_zona
				FROM zonas_cuadricula
				WHERE lat_sw <= :lat AND lat_ne >= :lat
				AND lng_sw <= :lng AND lng_ne >= :lng
				LIMIT 1
		";

		$params = [
			':lat' => $lat,
			':lng' => $lng
		];

		$row = $this->ejecutarConsulta($sql, '', $params);
		return $row ? (int)$row['id_zona'] : null;
	}	

	public function consultar_direcciones($id_cliente)
	{
        $sql = 'SELECT id_direccion, direccion
			FROM direcciones
			WHERE id_cliente = :v_id_cliente
			ORDER BY direccion';
        
        $param = [
			':v_id_cliente' => $id_cliente
		];

        $data = $this->ejecutarConsulta($sql, "", $param, "fetchAll");

        return $data;
	}

	public function consultar_direcciones_con_coordenadas()
	{
		$sql = 'SELECT d.id_direccion, d.direccion, d.lat, d.lng, c.nombre AS cliente_nombre
			FROM direcciones AS d
			LEFT JOIN zonas_direcciones zd ON d.id_direccion = zd.id_direccion
			LEFT JOIN clientes AS c ON d.id_cliente = c.id_cliente
			WHERE lat IS NOT NULL 
				AND lng IS NOT NULL
	          	AND zd.id_direccion IS NULL
			ORDER BY c.nombre';
		
		$data = $this->ejecutarConsulta($sql, "", [], "fetchAll");

		echo json_encode([
			'success' => true,
			'data' => $data
		]);
	}

}
