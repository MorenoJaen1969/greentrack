<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
class datosgeneralesController extends mainModel
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

	private $session_timeout = 300; // 5 minutes
    
    private function initParametrosSession()
    {
        if (!isset($_SESSION['parametros_auth'])) {
            $_SESSION['parametros_auth'] = [
                'authenticated' => false,
                'username' => null,
                'login_time' => null,
                'attempts' => 0,
                'locked_until' => null
            ];
        }
    }

	public function __construct()
	{
		// ¡ESTA LÍNEA ES CRUCIAL!
		parent::__construct();

        $this->initParametrosSession();

		// Nombre del controlador actual abreviado para reconocer el archivo
		$nom_controlador = "datosgeneralesController";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/datosgenerales/';

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

    public function obtener_Clave($clave) 
    {
        $query = "SELECT valor 
			FROM configuracion_sistema 
			WHERE clave = :v_clave";
        $parametros = ['v_clave' => $clave];

		try {
			$resultado = $this->ejecutarConsulta($query, '', $parametros, 'fetchAll');
			$clave = array_map(function($row) {
				return [
					'valor' => $row['valor']
				];
			}, $resultado);

            echo json_encode(['valor' => $clave]);

		} catch (Exception $e) {
			$this->logWithBacktrace("Error crítico en obtenerClave: " . $e->getMessage(), true);
            echo json_encode(['valor' => []]);
		}

    }

	public function datos_para_gps()
	{
        $query = "SELECT valor 
			FROM configuracion_sistema 
			WHERE clave = :v_clave1 or clave = :v_clave2 OR clave = :v_clave3 OR clave = :v_clave4";
        $parametros = [
			'v_clave1' => "mapa_base",
			'v_clave2' => "umbral_metros",
			'v_clave3' => "umbral_minutos",
			'v_clave4' => "umbral_course"
		];

		try {
			$resultado = $this->ejecutarConsulta($query, '', $parametros, 'fetchAll');

			// Convertir resultado en un array asociativo: ['mapa_base' => 'ESRI', ...]
			$config = [];
			foreach ($resultado as $row) {
				$config[$row['valor']] = $row['valor'];
			}

			// Ahora puedes acceder fácilmente:
			// $config['mapa_base'], $config['umbral_metros'], etc.

			echo json_encode([
				'success' => true,
				'config' => $config
			]);

		} catch (Exception $e) {
			echo json_encode([
				'success' => false,
				'error' => 'Error al obtener configuraciones',
				'detalle' => $e->getMessage()
			]);
		}
	}

    public function tiempos_de_actividad() 
    {
        $query = "SELECT clave, valor, TIME_FORMAT(valor, '%H:%i') AS valor_hora
			FROM configuracion_sistema
			WHERE clave IN ('hora_cierre_sesion', 'hora_fin_jornada', 'hora_inicio_jornada')";

        $parametros = [];

		try {
			$resultado = $this->ejecutarConsulta($query, '', $parametros, 'fetchAll');

			// Valores por defecto
			$parametros = [
				'hora_cierre_sesion' => '18:30',
				'hora_fin_jornada'     => '18:00',
				'hora_inicio_jornada'  => '08:00'
			];

			if ($resultado && is_array($resultado)) {
				foreach ($resultado as $fila) {
					if ($fila['valor'] !== null) {
						$parametros[$fila['clave']] = $fila['valor'];
					}
				}
			}
		    return $parametros; // ✅ SOLO RETURN, NUNCA ECHO

		} catch (Exception $e) {
			$this->logWithBacktrace("Error crítico en obtenerClave: " . $e->getMessage(), true);
	        echo json_encode(['valor' => []]);
		}
    }

   	// ========== EXISTING METHOD - DO NOT MODIFY ==========
    // public function tiempos_de_actividad()
    // {
    //     $sql = "SELECT hora_inicio_jornada, hora_fin_jornada, hora_cierre_sesion 
    //             FROM configuracion_sistema 
    //             WHERE clave IN ('hora_inicio_jornada', 'hora_fin_jornada', 'hora_cierre_sesion')";
        
    //     $resultados = $this->ejecutarConsulta($sql, "", [], "fetchAll");
        
    //     $horarios = [
    //         'hora_inicio_jornada' => '08:00',
    //         'hora_fin_jornada' => '18:00',
    //         'hora_cierre_sesion' => '18:30'
    //     ];
        
    //     foreach ($resultados as $row) {
    //         $horarios[$row['clave']] = $row['valor'];
    //     }
        
    //     return $horarios;
    // }
    
    // ========== NEW METHODS FOR PARAMETROS ==========
    
    // ========== NEW METHODS FOR PARAMETROS ==========
    
    /**
     * Get all protected processes from database
     */
    public function obtenerProcesosProtegidos()
    {
        $sql = "SELECT url_proceso, nombre_proceso, nivel_seguridad 
                FROM procesos_criticos 
                WHERE activo = 1 AND requiere_autenticacion = 1 
                ORDER BY nivel_seguridad DESC, nombre_proceso";
        
        $resultados = $this->ejecutarConsulta($sql, "", [], "fetchAll");
        $this->log("Resultado de la consulta: " . json_encode($resultados));
        
        $procesos = [];
        foreach ($resultados as $row) {
            $procesos[$row['url_proceso']] = [
                'nombre' => $row['nombre_proceso'],
                'nivel' => $row['nivel_seguridad']
            ];
        }
        
        return $procesos;
    }
    
    /**
     * Check if URL is protected
     */
    public function esUrlProtegida($url)
    {
        $sql = "SELECT COUNT(*) 
                FROM procesos_criticos 
                WHERE url_proceso = :url 
                AND activo = 1 
                AND requiere_autenticacion = 1";
        
        $params = [':url' => $url];
        $count = $this->ejecutarConsulta($sql, "", $params, "fetchColumn");
        
        return $count > 0;
    }
    
    /**
     * Get security level of a process
     */
    public function obtenerNivelSeguridad($url)
    {
        $sql = "SELECT nivel_seguridad 
                FROM procesos_criticos 
                WHERE url_proceso = :url 
                AND activo = 1";
        
        $params = [':url' => $url];
        $nivel = $this->ejecutarConsulta($sql, "", $params, "fetchColumn");
        
        return $nivel ?? 1; // Default level 1 if not found
    }

    /**
     * Verify if user is authenticated for parametros access
     */
    public function verificarAccesoParametros()
    {
        if ($this->estaBloqueado()) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please try again later.'];
        }
        
        if ($this->sessionExpirada()) {
            $this->cerrarSesionParametros();
            return ['success' => false, 'message' => 'Session expired. Please authenticate again.'];
        }
        
        return [
            'success' => $_SESSION['parametros_auth']['authenticated'],
            'username' => $_SESSION['parametros_auth']['username'],
            'nombre' => $_SESSION['parametros_auth']['nombre'] ?? null,
            'redirect_url' => $_SESSION['parametros_auth']['redirect_url'] ?? null
        ];
    }
    
    /**
     * Set redirect URL before authentication
     */
    public function setRedirectUrl($url)
    {
        $_SESSION['parametros_auth']['redirect_url'] = $url;
        return ['success' => true, 'message' => 'Redirect URL set.'];
    }
    
    /**
     * Clear redirect URL after successful authentication
     */
    public function clearRedirectUrl()
    {
        $_SESSION['parametros_auth']['redirect_url'] = null;
        return ['success' => true, 'message' => 'Redirect URL cleared.'];
    }

    /**
     * Authenticate user for parametros access
     * Uses existing usuarios table
     */
    public function autenticarParametros($inputData)
    {
        if ($this->estaBloqueado()) {
            return ['success' => false, 'message' => 'Account temporarily locked.'];
        }
        
        $username = $inputData['username'] ?? '';
        $password = $inputData['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required.'];
        }
        
        // Check if user exists and has access to parametros
        $usuario = $this->obtenerUsuario($username);
        
        if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
            $this->registrarIntentoFallido();
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }
        
        // Check if user has permission to access parametros
        if (!$this->tienePermisoParametros($usuario['id'])) {
            return ['success' => false, 'message' => 'You do not have permission to access General Parameters.'];
        }
        
        $_SESSION['parametros_auth'] = [
            'authenticated' => true,
            'username' => $username,
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email'],
            'login_time' => time(),
            'attempts' => 0,
            'locked_until' => null,
            'redirect_url' => $_SESSION['parametros_auth']['redirect_url'] ?? null
        ];
        
        return [
            'success' => true, 
            'message' => 'Authentication successful.',
            'user' => [
                'username' => $username,
                'nombre' => $usuario['nombre'],
                'email' => $usuario['email']
            ],
            'redirect_url' => $_SESSION['parametros_auth']['redirect_url']
        ];
    }
    
    /**
     * Close parametros session
     */
    public function cerrarSesionParametros()
    {
        $_SESSION['parametros_auth'] = [
            'authenticated' => false,
            'username' => null,
            'nombre' => null,
            'login_time' => null,
            'attempts' => 0,
            'locked_until' => null,
            'redirect_url' => null
        ];
        
        return ['success' => true, 'message' => 'Session closed successfully.'];
    }
    
    /**
     * Get all configuration parameters
     */
    public function obtenerConfiguracion()
    {
        $sql = "SELECT clave, valor, descripcion FROM configuracion_sistema ORDER BY clave";
        $resultados = $this->ejecutarConsulta($sql, "", [], "fetchAll");
        
        $config = [];
        foreach ($resultados as $row) {
            $config[$row['clave']] = [
                'valor' => $row['valor'],
                'descripcion' => $row['descripcion']
            ];
        }
        
        return ['success' => true, 'config' => $config];
    }
    
    /**
     * Save configuration parameter
     */
    public function guardarConfiguracion($inputData)
    {
        $clave = $this->limpiarCadena($inputData['clave'] ?? '');
        $valor = $this->limpiarCadena($inputData['valor'] ?? '');
        
        if (empty($clave)) {
            return ['success' => false, 'message' => 'Parameter key is required.'];
        }
        
        // Validate specific parameters
        $validacion = $this->validarParametro($clave, $valor);
        if (!$validacion['success']) {
            return $validacion;
        }
        
        // Check if parameter exists
        $sql_check = "SELECT COUNT(*) FROM configuracion_sistema WHERE clave = :clave";
        $exists = $this->ejecutarConsulta($sql_check, "", [':clave' => $clave], "fetchColumn");
        
        if ($exists) {
            // Update existing parameter
            $datos = [
                ['campo_nombre' => 'valor', 'campo_marcador' => ':valor', 'campo_valor' => $valor]
            ];
            $condicion = [
                'condicion_campo' => 'clave',
                'condicion_operador' => '=',
                'condicion_marcador' => ':clave',
                'condicion_valor' => $clave
            ];
            
            $result = $this->actualizarDatos('configuracion_sistema', $datos, $condicion);
            
            if ($result) {
                return ['success' => true, 'message' => "Parameter '{$clave}' updated successfully."];
            } else {
                return ['success' => false, 'message' => 'Error updating parameter.'];
            }
        } else {
            // Insert new parameter
            $datos = [
                ['campo_nombre' => 'clave', 'campo_marcador' => ':clave', 'campo_valor' => $clave],
                ['campo_nombre' => 'valor', 'campo_marcador' => ':valor', 'campo_valor' => $valor],
                ['campo_nombre' => 'descripcion', 'campo_marcador' => ':descripcion', 'campo_valor' => $inputData['descripcion'] ?? '']
            ];
            
            $result = $this->guardarDatos('configuracion_sistema', $datos);
            
            if ($result) {
                return ['success' => true, 'message' => "Parameter '{$clave}' created successfully."];
            } else {
                return ['success' => false, 'message' => 'Error creating parameter.'];
            }
        }
    }
    
    /**
     * Validate parameter value based on key
     */
    private function validarParametro($clave, $valor)
    {
        switch ($clave) {
            case 'hora_inicio_jornada':
            case 'hora_fin_jornada':
            case 'hora_cierre_sesion':
                // Validate time format HH:mm:ss
                if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $valor)) {
                    return ['success' => false, 'message' => 'Invalid time format. Use HH:mm:ss'];
                }
                break;
                
            case 'inicio_invierno':
            case 'fin_invierno':
                // Validate date format MM-DD
                if (!preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', $valor)) {
                    return ['success' => false, 'message' => 'Invalid date format. Use MM-DD'];
                }
                break;
                
            case 'mapa_base':
                // Validate allowed values
                if (!in_array($valor, ['OSM', 'ESRI', 'GMAP'])) {
                    return ['success' => false, 'message' => 'Invalid map base. Allowed values: OSM, ESRI, GMAP'];
                }
                break;
                
            case 'reorganizar_invierno':
                // Validate boolean
                if (!in_array(strtolower($valor), ['true', 'false', '1', '0'])) {
                    return ['success' => false, 'message' => 'Invalid boolean value. Use true/false or 1/0'];
                }
                break;
                
            case 'radio_geocerca':
            case 'tiempo_minimo_parada':
            case 'umbral_course':
            case 'umbral_metros':
            case 'umbral_minutos':
                // Validate positive integer
                if (!preg_match('/^\d+$/', $valor) || intval($valor) < 0) {
                    return ['success' => false, 'message' => 'Invalid number. Must be a positive integer.'];
                }
                break;
                
            default:
                // No validation for other parameters
                break;
        }
        
        return ['success' => true, 'message' => 'Validation passed.'];
    }
    
    /**
     * Get user from database
     */
    private function obtenerUsuario($username)
    {
        $sql = "SELECT id, username, password_hash, nombre, email 
                FROM usuarios_ejecutivos 
                WHERE username = :username AND activo = 1";
        $params = [':username' => $username];
        
        return $this->ejecutarConsulta($sql, "", $params);
    }
    
    /**
     * Check if user has permission to access parametros
     * You can customize this logic based on your permission system
     */
    private function tienePermisoParametros($id_usuario)
    {
        // Option 1: Check if user is in specific role/area
        $sql = "SELECT area FROM usuarios_ejecutivos WHERE id = :id_usuario";
        $params = [
            ':id_usuario' => $id_usuario
        ];
        $area = $this->ejecutarConsulta($sql, "", $params, "fetchColumn");
        
        // Allow access if user is in 'sistema' area or similar
        $areas_autorizadas = ['sistema', 'administracion'];
        return in_array($area, $areas_autorizadas);
        
        // Option 2: Check specific permission table
        // $sql = "SELECT COUNT(*) FROM permisos_usuarios 
        //         WHERE id_usuario = :id_usuario AND permiso = 'parametros'";
        // $tiene_permiso = $this->ejecutarConsulta($sql, "", [':id_usuario' => $id_usuario], "fetchColumn");
        // return $tiene_permiso > 0;
    }
    
    // ========== PRIVATE METHODS ==========
    
    private function registrarIntentoFallido()
    {
        $_SESSION['parametros_auth']['attempts']++;
        
        if ($_SESSION['parametros_auth']['attempts'] >= 5) {
            $_SESSION['parametros_auth']['locked_until'] = time() + 300; // 5 minutes
        }
    }
    
    private function estaBloqueado()
    {
        if (!isset($_SESSION['parametros_auth']['locked_until'])) {
            return false;
        }
        
        if ($_SESSION['parametros_auth']['locked_until'] !== null && 
            time() < $_SESSION['parametros_auth']['locked_until']) {
            return true;
        }
        
        // Reset lock
        $_SESSION['parametros_auth']['locked_until'] = null;
        $_SESSION['parametros_auth']['attempts'] = 0;
        return false;
    }
    
    private function sessionExpirada()
    {
        if (!$_SESSION['parametros_auth']['authenticated']) {
            return true;
        }
        
        $login_time = $_SESSION['parametros_auth']['login_time'];
        return (time() - $login_time) > $this->session_timeout;
    }	
}
?>    