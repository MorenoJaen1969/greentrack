<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
class usuariosController extends mainModel
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
		$nom_controlador = "usuariosController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/usuarios/';

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

	/*----------  Controlador listar usuario  ----------*/
	public function listarUsuarioControlador($dato_ori)
	{
		$pagina_usuarios = $dato_ori[0];
		$registrosPorPagina = $dato_ori[1];
		$url1 = $dato_ori[2];
		$busca_frase = $dato_ori[3];
		$ruta_retorno = $dato_ori[4];
		$orden = $dato_ori[5];
		$direccion  = $dato_ori[6];


		$pagina = $this->limpiarCadena($pagina_usuarios);
		$registros = $this->limpiarCadena($registrosPorPagina);

		$url = $this->limpiarCadena($url1);
		$url = APP_URL . $url . "/";

		$busqueda = $this->limpiarCadena($busca_frase);
		$tabla = "";

		$pagina = (isset($pagina) && $pagina > 0) ? (int) $pagina : 1;
		$inicio = ($pagina > 0) ? (($pagina * $registros) - $registros) : 0;

		if (isset($busqueda) && $busqueda != "") {
			$consulta_datos = "SELECT * 
				FROM usuarios_ejecutivos 
				WHERE ((id != 1 AND id != 7)
					AND (nombre LIKE '%$busqueda%' OR email LIKE '%$busqueda%' OR username LIKE '%$busqueda%')) 
				ORDER BY nombre ASC LIMIT $inicio,$registros";

			$consulta_total = "SELECT COUNT(id) 
				FROM usuarios_ejecutivos 
				WHERE ((id != 1 AND id != 7)
					AND (nombre LIKE '%$busqueda%' OR email LIKE '%$busqueda%' OR username LIKE '%$busqueda%'))";
		} else {
			$consulta_datos = "SELECT * 
				FROM usuarios_ejecutivos 
				WHERE (id != 1 AND id != 7)
					ORDER BY nombre ASC LIMIT $inicio,$registros";

			$consulta_total = "SELECT COUNT(id) 
				FROM usuarios_ejecutivos
				WHERE (id != 1 AND id != 7)";
		}

		$datos = $this->ejecutarConsulta($consulta_datos, "", [], "fetchAll");

		$total = $this->ejecutarConsulta($consulta_total, "", [], "fetchColumn");

		$numeroPaginas = ceil($total / $registros);

		$tabla .= '
				<div class="table-container">
				<table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
					<thead class="cabecera">
						<tr>
							<th class="has-text-centered">#</th>
							<th class="has-text-centered w_max"><i class="fa-solid fa-camera"></i></th>
							<th class="has-text-centered">Name</th>
							<th class="has-text-centered">User</th>
							<th class="has-text-centered">Email</th>
							<th class="has-text-centered" colspan="2">Options</th>
						</tr>
					</thead>
					<tbody>
			';

		if ($total >= 1 && $pagina <= $numeroPaginas) {
			$contador = $inicio + 1;
			$pag_inicio = $inicio + 1;

			$ruta_usuarios = '/usuarios/' . $pagina . '/';

			foreach ($datos as $rows) {
				$ruta_destino = RUTA_APP . "/usuariosVista/usuarios/" . $rows['id'] . $ruta_usuarios;
				$ruta_Photo = RUTA_APP . "/usuariosPhoto/usuariosVista/" . $rows['id'] . $ruta_usuarios;

				$tabla .= '
						<tr class="has-text-centered" >
							<td>' . $contador . '</td>
                            <td class="person-info">';

                if (empty($rows['usuario_foto'])) {
                    if ($rows['id_sexo'] == 1) {
                        $tabla .= '<img class="is-rounded side_little" src="/app/views/fotos/responsable.png">';
                    } else {
                        $tabla .= '<img class="is-rounded side_little" src="/app/views/fotos/responsable-mujer.png">';
                    }
                } else {
                    //$ruta_img = RUTA_APP . '/app/views/img/uploads/fotos/'. $rows['usuario_foto'];
                    $ruta_img = '/app/views/img/uploads/fotos/' . $rows['usuario_foto'];
                    if ($this->isFile($ruta_img)) {
                        $foto_act = '/app/views/img/uploads/fotos/'. $rows['usuario_foto'];
                        $tabla .= '<img class="is-rounded  side_little" src="' . $foto_act . '">';
                    } else {
                        if ($rows['id_sexo'] == 1) {
                            $tabla .= '<img class="is-rounded side_little" src="/app/views/fotos/responsable.png">';
                        } else {
                            $tabla .= '<img class="is-rounded side_little" src="/app/views/fotos/responsable-mujer.png">';
                        }
                    }
                }
                $tabla .= '
                            </td>
							<td>' . $rows['nombre'] . '</td>
							<td>' . $rows['username'] . '</td>
							<td>' . $rows['email'] . '</td>
							<td>
                                <a href="' . $ruta_destino . $rows['id'] . '" 
									class="button is-link is-rounded is-small" 
									id="a_id" 
									name="a_id"
									title="View User details">
										<span class="fas fa-eye">
								</a>
                            </td>

							<td>
								<form class="FormularioAjax" action="' . APP_URL . 'app/ajax/usuariosAjax.php" method="POST" autocomplete="off" >

									<input type="hidden" name="modulo_usuarios" value="eliminar">
									<input type="hidden" name="id" value="' . $rows['id'] . '">

									<button type="submit" class="button is-danger is-rounded is-small"><span class="fa fa-trash"></span></button>
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
									Click here to refresh the list.
								</a>
							</td>
						</tr>
					';
			} else {
				$tabla .= '
						<tr class="has-text-centered" >
							<td colspan="7">
								There are no records in the system.
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
						<p class="has-text-right">Showing users <strong>' . $pag_inicio . '</strong> to <strong>' . $pag_final . '</strong> of a <strong>total of ' . $total . '</strong></p>
					</div>';
			$id_codigos = [
				'id_obra' => 0,
				'id_partida' => 0
			];
			$tabla .= $this->paginadorTablas($pagina, $numeroPaginas, $url, 7, $id_codigos);
		}
		return $tabla;
	}

	/*---------- Funcion seleccionar datos ----------*/
	public function seleccionarDatos($id)
	{
		$sql = "SELECT * 
			FROM usuarios_ejecutivos 
			WHERE id = :v_id_user";
		$paramValue = [
			'v_id_user' => $id
		];
		$res_imagenes = $this->ejecutarConsulta($sql, "", $paramValue);

		return $res_imagenes;
	}

	public function isFile($file) {
        $f = pathinfo($file, PATHINFO_EXTENSION);
        return (strlen($f) > 0) ? true : false;
    }	

    public function nuevo_usuario($paquete){

        if (!empty($paquete)) {
            $nombre = $paquete['nombre'];
            $email = $paquete['email'];
            $token = bin2hex(random_bytes(32)); // Ej: a1b2c3d4...

            $logNota = [
                ['campo_nombre' => 'nombre', 'campo_marcador' => ':nombre', 'campo_valor' => $nombre],
                ['campo_nombre' => 'email', 'campo_marcador' => ':email', 'campo_valor' => $email],
                ['campo_nombre' => 'token', 'campo_marcador' => ':token', 'campo_valor' => $token]
            ];
            $this->guardarDatos('usuarios_ejecutivos', $logNota);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Estado y historial actualizados']);

    }

    public function valida_usuario($paquete){
        $token = $paquete['token'];

        $sql = "SELECT id, nombre, email, token, area
            FROM usuarios_ejecutivos 
            WHERE token = :v_token AND activo = 1";

        $parametro = [
            ':v_token' => $token
        ];

        $result = $this->ejecutarConsulta($sql, "", $parametro);
		$this->log("Consulta de usuario Ejecutada". print_r($result, true));
        if (empty($result)) {
			
		}

        if (empty($result)) {
			$result = [];
			$this->logWithBacktrace("Hubo un error al validar el token",true);
            die('<h3>Token inválido o expirado.</h3>');
        }

        // ✅ Usuario autorizado 
        return $result;
    }

	public function getUserByToken($token)
	{
        $sql = "SELECT id, nombre, email, token
            FROM usuarios_ejecutivos 
            WHERE token = :v_token AND activo = 1";

		$params = [
			':v_token' => $token
		];
		
		$result = $this->ejecutarConsulta($sql, '', $params);
		
		if ($result && count((array)$result) > 0) {
			$respuesta = $result;
		} else {
			$respuesta = false;
		}	
		return $respuesta;
	}

	public function getUser($username, $password)
	{
        $sql = "SELECT id AS id_user, nombre, email, token
            FROM usuarios_ejecutivos 
            WHERE email = :email AND activo = 1";

		$params = [
			':email' => $username
		];

		$this->log("Consulta de validacion de usuario " . $sql);
		
		$result = $this->ejecutarConsulta($sql, '', $params);

		if ($result){
			$user_id = $result['id_user'];
			$dispositivo = $_SERVER['HTTP_USER_AGENT'];
			$token_sesion =  $result['token'];
			
			$this->log("Resultado de validacion de usuario " . json_encode($result));

			if (count((array)$result) === 0) {
				return false; // Usuario no encontrado
			} else {
				// ✅ NUEVO: Actualizar estado a 'online' en la base de datos
				$chat_estado = 'online';
				$chat_ultima_conexion = date('Y-m-d H:i:s');
				$datos = [
					['campo_nombre' => 'chat_estado', 'campo_marcador' => ':chat_estado', 'campo_valor' => $chat_estado],
					['campo_nombre' => 'chat_ultima_conexion', 'campo_marcador' => ':chat_ultima_conexion', 'campo_valor' => $chat_ultima_conexion]
				];
				$condicion = [
					'condicion_campo' => 'id',
					'condicion_operador' => '=', 
					'condicion_marcador' => ':id',
					'condicion_valor' => $result['id_user']
				];
				$this->actualizarDatos('usuarios_ejecutivos', $datos, $condicion);

				$sql = "SELECT id AS id_user, nombre, email, token
					FROM usuarios_ejecutivos 
					WHERE email = :email AND activo = 1";

				$params = [
					':email' => $username
				];
				
				$result = $this->ejecutarConsulta($sql, '', $params);
				if ($result){
					return $result;
				} else {
					return false; // Usuario no encontrado
				}
			}
		} else {
			return false; // Usuario no encontrado
		}
	}
	
	public function heartbeat($token, $dispositivo, $modo){
		// ✅ Paso 1: Verificar si ya existe una sesión para este usuario + dispositivo

		$usuario_actual = $this->getUserByToken($token); 
		$user_id = $usuario_actual['id'];

		$sql = "SELECT id 
			FROM sesiones_activas
			WHERE user_id = :v_user_id AND dispositivo = :v_dispositivo 
			LIMIT 1";

		$params = [
			':v_user_id' => $user_id,
			':v_dispositivo' => $dispositivo
		];

		$this->log("Consulta de Dispositivo del Usuario " . $sql);
		
		$result = $this->ejecutarConsulta($sql, '', $params);

		if ($result) {
			// ✅ Paso 2a: Actualizar si ya existe
			$ultima_actividad = date('Y-m-d H:i:s');

			$datos = [
				['campo_nombre' => 'ultima_actividad', 'campo_marcador' => ':ultima_actividad', 'campo_valor' => $ultima_actividad]
			];
			$condicion = [
				[
					'condicion_campo' => 'user_id',
					'condicion_operador' => '=', 
					'condicion_marcador' => ':user_id',
					'condicion_valor' => $user_id
				],
				[
					'condicion_campo' => 'dispositivo',
					'condicion_operador' => '=', 
					'condicion_marcador' => ':dispositivo',
					'condicion_valor' => $dispositivo
				]
			];
			$this->actualizarDatos('sesiones_activas', $datos, $condicion);

		} else {
			// ✅ Paso 2b: Insertar si no existe
			$ultima_actividad = date('Y-m-d H:i:s');
			$logNota = [
				['campo_nombre' => 'user_id', 'campo_marcador' => ':user_id', 'campo_valor' => $user_id],
				['campo_nombre' => 'dispositivo', 'campo_marcador' => ':dispositivo', 'campo_valor' => $dispositivo],
				['campo_nombre' => 'token_sesion', 'campo_marcador' => ':token_sesion', 'campo_valor' => $token],
				['campo_nombre' => 'ultima_actividad', 'campo_marcador' => ':ultima_actividad', 'campo_valor' => $ultima_actividad],
				['campo_nombre' => 'modo', 'campo_marcador' => ':modo', 'campo_valor' => $modo]
			];
			$this->guardarDatos('sesiones_activas', $logNota);
		}			
		return true;
	}
	
	public function changeAvatar($email, $token, $file){
		$usuario_actual = $this->getUserByToken($token); 
		if (!$usuario_actual || $usuario_actual['email'] !== $email) {
			$this->logWithBacktrace("Intento de cambio de avatar fallido: token inválido o email no coincide", true);
			return false; // Usuario no autorizado
		}
		
		$uploadDir = APP_R_PROY . 'app/views/img/avatars/';
		
		if (!file_exists($uploadDir)) {
			mkdir($uploadDir, 0775, true);
			chgrp($uploadDir, 'www-data');
			chmod($uploadDir, 0775);
		}

		$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
		$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

		if (!in_array(strtolower($extension), $allowedExtensions)) {
			$this->logWithBacktrace("Intento de cambio de avatar fallido: extensión de archivo no permitida", true);
			return false; // Extensión no permitida
		}

		$targetFile = $uploadDir . base64_encode($email) . '.' . $extension;

		if (move_uploaded_file($file['tmp_name'], $targetFile)) {
			chmod($targetFile, 0644); // Asegurarse de que el archivo sea legible
			return true;
		} else {
			$this->logWithBacktrace("Intento de cambio de avatar fallido: error al mover el archivo", true);
			return false; // Error al mover el archivo
		}
	}

	public function consulta_registro($param_dat)
	{
		$id_usuario = $param_dat['id_usuario'];
		$sql = "SELECT * 
			FROM usuarios_ejecutivos
			WHERE id = :v_user_id";

		$params = [
			':v_user_id' => $id_usuario
		];

		$this->log("Consulta de Usuario unico" . $sql);
		
		$result = $this->ejecutarConsulta($sql, '', $params);
		return $result;
	}

	public function guardarCambios($datos, $archivo){
		$id = (int) $datos['id_usuario'];
		$nombre = $datos['nombre'];
		$username = $datos['username'];
		$activo = (int) $datos['activo'];
		$phone = $datos['phone'];
		$chat_activo = (int) $datos['chat_activo'];

		// Llamar al controlador
		$datos_actualizar = [
			['campo_nombre' => 'nombre', 'campo_marcador' => ':nombre', 'campo_valor' => $nombre],
			['campo_nombre' => 'username', 'campo_marcador' => ':username', 'campo_valor' => $username],
			['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => $activo],
			['campo_nombre' => 'phone', 'campo_marcador' => ':phone', 'campo_valor' => $phone],
			['campo_nombre' => 'chat_activo', 'campo_marcador' => ':chat_activo', 'campo_valor' => $chat_activo]
		];

		$condicion = [
			'condicion_campo' => 'id',
			'condicion_operador' => '=', 
			'condicion_marcador' => ':id',
			'condicion_valor' => $id
		];

		$cod_respuesta = 200;
		// Manejar subida de imagen si existe
		$file = $archivo['fileInput'];
		if(!empty($file['name']) && !empty($file['tmp_name'])) {
			$target_dir = "../views/img/uploads/fotos/";
			$imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
			$new_filename = $username . "_" . uniqid() . "." . $imageFileType;
			$target_file = $target_dir . $new_filename;

			// Validar que sea una imagen
			$check = getimagesize($file["tmp_name"]);
			if($check === false) {
				$cod_respuesta = 400;
				$mensaje = [
					'tipo' => 'error',
					'texto' => 'The file is not a valid image.'
				];
			}

			// Verificar tamaño (4MB límite)
			if ($file["size"] > 4000000) {
				$cod_respuesta = 400;
				$mensaje = [
					'tipo' => 'error',
					'texto' => 'The file is too large. Maximum size is 4MB.'
				];
			}

			// Verificar formato de archivo
			if(!in_array($imageFileType, ["jpg", "jpeg", "png", "gif"])) {
				$cod_respuesta = 400;
				$mensaje = [
					'tipo' => 'error',
					'texto' => 'Only JPG, JPEG, PNG and GIF files are allowed.'
				];
			}

			// Subir archivo
			if(move_uploaded_file($file["tmp_name"], $target_file)) {
				// Agregar campo de imagen a los datos a actualizar
				$datos_actualizar[] = [
					'campo_nombre' => 'usuario_foto',
					'campo_marcador' => ':usuario_foto',
					'campo_valor' => $new_filename
				];
			} else {
				$cod_respuesta = 500;
				$mensaje = [
					'tipo' => 'error',
					'texto' => 'Error uploading the image. Please try again.'
				];
			}
		}

		// Actualizar datos en la base de datos
		if ($cod_respuesta == 200){
			try {
				$resulta = $this->actualizarDatos("usuarios_ejecutivos", $datos_actualizar, $condicion);
				
				if ($resulta === "No hay cambios") {
					$mensaje = [
						'tipo' => 'info', 
						'texto' => 'There were no changes to be made.'
					];
				} elseif ($resulta === 1) {
					$mensaje = [
						'tipo' => 'success', 
						'texto' => 'User updated successfully.'
					];
				} else {
					throw new Exception("The user could not be updated.");
				}
				
			} catch (Exception $e) {
				$cod_respuesta = 500;
				$mensaje = [
					'tipo' => 'error', 
					'texto' => $e->getMessage()
				];
			}
		} 

		$resulta = [
			$cod_respuesta,
			$mensaje
		];
		return $resulta;
	}
}