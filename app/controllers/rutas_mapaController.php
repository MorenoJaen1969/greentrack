<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
class rutas_mapaController extends mainModel
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
        $nom_controlador = "rutas_mapaController";
        // ____________________________________________________________________

        $this->log_path = APP_R_PROY . 'app/logs/rutas_mapa/';

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

    /**
     * Obtiene todas las rutas activas
     */
    public function listarRutas()
    {
        $this->log("Inicio de lectura de Zonas ");
        try {
            $sql = "SELECT id_ruta, nombre_ruta, color_ruta, activo 
                        FROM rutas 
                        WHERE activo = :v_activo 
                        ORDER BY nombre_ruta";

            $params = [
                ':v_activo' => 1
            ];
            $rutas = $this->ejecutarConsulta($sql, '', $params, "fetchAll");
            if (count((array) $rutas) > 0) {
                $this->log("Listado de rutas obtenido exitosamente. Total: " . count((array) $rutas));
            } else {
                $rutas = [];
            }
            return $rutas;
        } catch (Exception $e) {
            $this->logWithBacktrace("Error al listar rutas: " . $e->getMessage());
            throw $e;
        }
    }

    public function obtenerRutaConZonas_d($id_ruta)
    {
        try {
            $sql = "SELECT zc.id_zona, zc.nombre_zona, d.id_direccion, c.nombre AS cliente_nombre, d.direccion, d.lat, d.lng
                FROM rutas_zonas_cuadricula rz
                JOIN zonas_cuadricula zc ON rz.id_zona = zc.id_zona
                LEFT JOIN zonas_direcciones zd ON zc.id_zona = zd.id_zona
                LEFT JOIN direcciones d ON zd.id_direccion = d.id_direccion
                LEFT JOIN clientes c ON d.id_cliente = c.id_cliente
                WHERE rz.id_ruta = :id_ruta 
                    AND rz.activo = 1 
                    AND d.id_direccion IS NOT NULL
                ORDER BY zc.id_zona, c.nombre
		    ";

            $param = [
                ':id_ruta' => $id_ruta
            ];

            $zonasConDirecciones = $this->ejecutarConsulta($sql, '', $param, "fetchAll");

            // // Agrupar direcciones por zona
            // $zonasAgrupadas = [];
            // foreach ($zonasConDirecciones as $fila) {
            //     $id_zona = $fila['id_zona'];

            //     if (!isset($zonasAgrupadas[$id_zona])) {
            //         $zonasAgrupadas[$id_zona] = [
            //             'id_zona' => $fila['id_zona'],
            //             'nombre_zona' => $fila['nombre_zona'],
            //             'lat_sw' => $fila['lat_sw'],
            //             'lng_sw' => $fila['lng_sw'],
            //             'lat_ne' => $fila['lat_ne'],
            //             'lng_ne' => $fila['lng_ne'],
            //             'direcciones' => []
            //         ];
            //     }

            //     // Añadir dirección si existe (evita nulls de LEFT JOIN)
            //     if ($fila['id_direccion'] !== null) {
            //         $zonasAgrupadas[$id_zona]['direcciones'][] = [
            //             'id_direccion' => $fila['id_direccion'],
            //             'cliente_nombre' => $fila['cliente_nombre'],
            //             'direccion' => $fila['direccion'],
            //             'lat' => $fila['dir_lat'],
            //             'lng' => $fila['dir_lng']
            //         ];
            //     }
            // }

            // $ruta['zonas'] = array_values($zonasAgrupadas);
            // $this->log("Datos de ruta con zonas y direcciones obtenidos para ID: $id_ruta");
            return $zonasConDirecciones;
        } catch (Exception $e) {
            $this->logWithBacktrace("Error al obtener ruta con zonas y direcciones: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene una ruta específica y sus zonas asociadas
     */
    public function obtenerRutaConZonas($id_ruta)
    {
        try {
            // Obtener datos de la ruta
            $sql_ruta = "SELECT id_ruta, nombre_ruta, color_ruta, activo, lat_hq, lng_hq 
                        FROM rutas 
                        WHERE id_ruta = :id_ruta";
            $params = [':id_ruta' => $id_ruta];
            $ruta = $this->ejecutarConsulta($sql_ruta, '', $params);

            if (!$ruta) {
                throw new Exception("Ruta no encontrada con ID: $id_ruta");
            }

            // Obtener zonas + direcciones asignadas
            $sql_zonas_direcciones = "SELECT 
                    zc.id_zona, zc.nombre_zona, zc.lat_sw, zc.lng_sw, zc.lat_ne, zc.lng_ne,
                    d.id_direccion, c.nombre AS cliente_nombre, d.direccion, d.lat AS dir_lat, d.lng AS dir_lng
                FROM rutas_zonas_cuadricula rz
                JOIN zonas_cuadricula zc ON rz.id_zona = zc.id_zona
                LEFT JOIN zonas_direcciones zd ON zc.id_zona = zd.id_zona
                LEFT JOIN direcciones d ON zd.id_direccion = d.id_direccion
                LEFT JOIN clientes c ON d.id_cliente = c.id_cliente
                WHERE rz.id_ruta = :id_ruta AND rz.activo = 1
                ORDER BY zc.id_zona, d.id_direccion
            ";

            $param = [':id_ruta' => $id_ruta];

            $zonasConDirecciones = $this->ejecutarConsulta($sql_zonas_direcciones, '', $param, "fetchAll");

            // Agrupar direcciones por zona
            $zonasAgrupadas = [];
            foreach ($zonasConDirecciones as $fila) {
                $id_zona = $fila['id_zona'];

                if (!isset($zonasAgrupadas[$id_zona])) {
                    $zonasAgrupadas[$id_zona] = [
                        'id_zona' => $fila['id_zona'],
                        'nombre_zona' => $fila['nombre_zona'],
                        'lat_sw' => $fila['lat_sw'],
                        'lng_sw' => $fila['lng_sw'],
                        'lat_ne' => $fila['lat_ne'],
                        'lng_ne' => $fila['lng_ne'],
                        'direcciones' => []
                    ];
                }

                // Añadir dirección si existe (evita nulls de LEFT JOIN)
                if ($fila['id_direccion'] !== null) {
                    $zonasAgrupadas[$id_zona]['direcciones'][] = [
                        'id_direccion' => $fila['id_direccion'],
                        'cliente_nombre' => $fila['cliente_nombre'],
                        'direccion' => $fila['direccion'],
                        'lat' => $fila['dir_lat'],
                        'lng' => $fila['dir_lng']
                    ];
                }
            }

            $ruta['zonas'] = array_values($zonasAgrupadas);
            $this->log("Datos de ruta con zonas y direcciones obtenidos para ID: $id_ruta");
            return $ruta;
        } catch (Exception $e) {
            $this->logWithBacktrace("Error al obtener ruta con zonas y direcciones: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crea una nueva ruta y asocia zonas
     * @param string $nombre_ruta
     * @param string $color_ruta (e.g., #FF0000)
     * @param array $zonas_ids (array de id_zona)
     * @return int id_ruta creada
     */
    public function crearRuta($nombre_ruta, $color_ruta, $zonas_ids)
    {
        try {
            // 1. Validar que las zonas existan y estén conectadas al HQ
            if (empty($zonas_ids)) {
                throw new Exception("No se puede crear una ruta sin zonas asociadas.");
            }

            // Obtener coordenadas de las zonas para validación
            $placeholders = str_repeat('?,', count($zonas_ids) - 1) . '?';
            $sql_check = "SELECT id_zona, lat_sw, lng_sw, lat_ne, lng_ne 
                FROM zonas_cuadricula 
                WHERE id_zona IN ($placeholders) AND activo = 1";
            $params = $zonas_ids;

            $zonas_datos = $this->ejecutarConsulta($sql_check, '', $params, "fetchAll");

            if (count($zonas_datos) !== count($zonas_ids)) {
                throw new Exception("Algunas zonas no existen o no están activas.");
            }

            // Validar conexión con HQ (lat_hq = 30.3204272, lng_hq = -95.4217815)
            $hq_lat = 30.3204272;
            $hq_lng = -95.4217815;
            $distancia_minima = PHP_FLOAT_MAX;

            foreach ($zonas_datos as $zona) {
                // Calcular distancia del centro de la zona al HQ
                $centro_lat = ($zona['lat_sw'] + $zona['lat_ne']) / 2;
                $centro_lng = ($zona['lng_sw'] + $zona['lng_ne']) / 2;

                // Distancia aproximada en grados (simplificación)
                $distancia = sqrt(pow($centro_lat - $hq_lat, 2) + pow($centro_lng - $hq_lng, 2));
                if ($distancia < $distancia_minima) {
                    $distancia_minima = $distancia;
                }
            }

            // Convertir distancia en grados a km (aproximado: 1 grado ~ 111 km)
            $distancia_km = $distancia_minima * 111;
            $umbral_km = 10; // Definir umbral, por ejemplo 10 km

            if ($distancia_km > $umbral_km) {
                throw new Exception("La ruta no está conectada al headquarter. La zona más cercana está a " . sprintf('%.2f', $distancia_km) . " km.");
            }


            // 2. Crear la ruta
            $logRuta = [
                ['campo_nombre' => 'nombre_ruta', 'campo_marcador' => ':nombre_ruta', 'campo_valor' => $nombre_ruta],
                ['campo_nombre' => 'color_ruta', 'campo_marcador' => ':color_ruta', 'campo_valor' => $color_ruta],
                ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1],
                // Lat y Lng del HQ están por defecto en la tabla
            ];
            $id_ruta = $this->guardarDatos('rutas', $logRuta);

            // 3. Asociar zonas a la ruta
            foreach ($zonas_ids as $id_zona) {
                $logRutaZona = [
                    ['campo_nombre' => 'id_ruta', 'campo_marcador' => ':id_ruta', 'campo_valor' => $id_ruta],
                    ['campo_nombre' => 'id_zona', 'campo_marcador' => ':id_zona', 'campo_valor' => $id_zona],
                    ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1],
                ];
                $this->guardarDatos('rutas_zonas_cuadricula', $logRutaZona);
            }

            $this->log("Ruta creada exitosamente: ID $id_ruta, Nombre '$nombre_ruta', Zonas: " . count($zonas_ids));
            return $id_ruta;

        } catch (Exception $e) {
            $this->logWithBacktrace("Error al crear ruta: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualiza una ruta existente y sus zonas asociadas
     * @param int $id_ruta
     * @param string $nombre_ruta
     * @param string $color_ruta
     * @param array $zonas_ids
     */
    public function actualizarRuta($id_ruta, $nombre_ruta, $color_ruta, $zonas_ids)
    {
        try {
            // 1. Validar existencia de la ruta
            $sql_check = "SELECT id_ruta FROM rutas WHERE id_ruta = :id_ruta";
            $params = [
                ':id_ruta' => $id_ruta
            ];
            $ruta_existente = $this->ejecutarConsulta($sql_check, '', $params);

            if (!$ruta_existente) {
                throw new Exception("Ruta no encontrada con ID: $id_ruta");
            } 

            // 2. Actualizar datos de la ruta
            $actualizado_en = (new \DateTime())->format('Y-m-d H:i:s');

            $datos = [
                ['campo_nombre' => 'nombre_ruta', 'campo_marcador' => ':nombre_ruta', 'campo_valor' => $nombre_ruta],
                ['campo_nombre' => 'color_ruta', 'campo_marcador' => ':color_ruta', 'campo_valor' => $color_ruta],
                ['campo_nombre' => 'actualizado_en', 'campo_marcador' => ':actualizado_en', 'campo_valor' => $actualizado_en]
            ];
            $condicion = [
                'condicion_campo' => 'id_ruta',
                'condicion_operador' => '=',
                'condicion_marcador' => ':id_ruta',
                'condicion_valor' => $id_ruta
            ];
            $this->actualizarDatos('rutas', $datos, $condicion);
            $this->log("Registro de Ruta actualizado exitosamente: ID $id_ruta, Nombre '$nombre_ruta', Color: " . $color_ruta);

            // 3. Validar zonas y conexión con HQ (similar a crearRuta)
            if (empty($zonas_ids)) {
                throw new Exception("A route cannot be updated without associated zones.");
            }

            $placeholders = str_repeat('?,', count($zonas_ids) - 1) . '?';
            $sql_check = "SELECT id_zona, lat_sw, lng_sw, lat_ne, lng_ne 
                            FROM zonas_cuadricula 
                            WHERE id_zona IN ($placeholders) AND activo = 1";
            $params = $zonas_ids;

            $zonas_datos = $this->ejecutarConsulta($sql_check, '', $params, "fetchAll");
            if (count((array) $zonas_datos) > 0) {
                $hq_lat = 30.3204272;
                $hq_lng = -95.4217815;
                $distancia_minima = PHP_FLOAT_MAX;

                foreach ($zonas_datos as $zona) {
                    $centro_lat = ($zona['lat_sw'] + $zona['lat_ne']) / 2;
                    $centro_lng = ($zona['lng_sw'] + $zona['lng_ne']) / 2;
                    $distancia = sqrt(pow($centro_lat - $hq_lat, 2) + pow($centro_lng - $hq_lng, 2));
                    if ($distancia < $distancia_minima) {
                        $distancia_minima = $distancia;
                    }
                }

                $distancia_km = $distancia_minima * 111;
                $umbral_km = 10;

                if ($distancia_km > $umbral_km) {
                    throw new Exception("La ruta no está conectada al headquarter. La zona más cercana está a " . sprintf('%.2f', $distancia_km) . " km.");
                }

                // 4. Eliminar vínculos anteriores (marcar como inactivos o borrar, aquí marco como inactivos)
                // $activo = 0;
                // $datos = [
                //     ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => $activo]
                // ];
                // $condicion = [
                //     'condicion_campo' => 'id_ruta',
                //     'condicion_operador' => '=',
                //     'condicion_marcador' => ':id_ruta',
                //     'condicion_valor' => $id_ruta
                // ];

                // $this->actualizarDatos('rutas_zonas_cuadricula', $datos, $condicion);

                // 5. Asociar nuevas zonas
                foreach ($zonas_ids as $id_zona) {
                    // Verificar si ya existe el vínculo (y está inactivo), si es así, reactivarlo
                    $sql_check_link = "SELECT id_ruta_zona FROM rutas_zonas_cuadricula WHERE id_ruta = :id_ruta AND id_zona = :id_zona";
                    $params = [
                        ':id_ruta' => $id_ruta,
                        ':id_zona' => $id_zona
                    ];

                    $existing_link = $this->ejecutarConsulta($sql_check_link, '', $params);

                    if ($existing_link) {
                        // Reactivar
                        $activo = 1;
                        $actualizado_en = (new \DateTime())->format('Y-m-d H:i:s');
                        $id_ruta_zona = $existing_link['id_ruta_zona'];
                        $datos = [
                            ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => $activo],
                            ['campo_nombre' => 'actualizado_en', 'campo_marcador' => ':actualizado_en', 'campo_valor' => $actualizado_en]
                        ];
                        $condicion = [
                            'condicion_campo' => 'id_ruta_zona',
                            'condicion_operador' => '=',
                            'condicion_marcador' => ':id_ruta_zona',
                            'condicion_valor' => $id_ruta_zona
                        ];

                        $this->actualizarDatos('rutas_zonas_cuadricula', $datos, $condicion);

                    } else {
                        // Crear nuevo vínculo
                        $logRutaZona = [
                            ['campo_nombre' => 'id_ruta', 'campo_marcador' => ':id_ruta', 'campo_valor' => $id_ruta],
                            ['campo_nombre' => 'id_zona', 'campo_marcador' => ':id_zona', 'campo_valor' => $id_zona],
                            ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1],
                        ];
                        $this->guardarDatos('rutas_zonas_cuadricula', $logRutaZona);
                    }
                }

                $this->log("Ruta actualizada exitosamente: ID $id_ruta, Nombre '$nombre_ruta', Zonas: " . count($zonas_ids));
                return $id_ruta;
            }

        } catch (Exception $e) {
            $this->logWithBacktrace("Error al actualizar ruta: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Marca una ruta como inactiva
     * @param int $id_ruta
     */
    public function eliminarRuta($id_ruta)
    {
        try {
            // Marcar como inactiva en lugar de borrar físicamente
            $activo = 0;
            $actualizado_en = new \DateTime();

            $datos = [
                ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => $activo],
                ['campo_nombre' => 'actualizado_en', 'campo_marcador' => ':actualizado_en', 'campo_valor' => $actualizado_en]
            ];
            $condicion = [
                'condicion_campo' => 'id_ruta',
                'condicion_operador' => '=',
                'condicion_marcador' => ':id_ruta',
                'condicion_valor' => $id_ruta
            ];

            $this->actualizarDatos('rutas', $datos, $condicion);

            $this->log("Ruta marcada como inactiva: ID $id_ruta");
            return true;
        } catch (Exception $e) {
            $this->logWithBacktrace("Error al eliminar ruta: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene las zonas cuadriculadas activas de una ciudad específica
     */
    public function listarZonasPorCiudad($id_ciudad)
    {
        try {
            $sql = "SELECT id_zona, nombre_zona, lat_sw, lng_sw, lat_ne, lng_ne 
                        FROM zonas_cuadricula 
                        WHERE id_ciudad_origen = :id_ciudad 
                        AND activo = 1 
                        ORDER BY nombre_zona";

            $params = [
                ':id_ciudad' => $id_ciudad
            ];
            $zonas = $this->ejecutarConsulta($sql, '', $params, "fetchAll");

            $this->log("Listado de zonas para ciudad $id_ciudad obtenido. Total: " . count((array) $zonas));
            return $zonas;
        } catch (Exception $e) {
            $this->logWithBacktrace("Error al listar zonas por ciudad: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene todas las zonas cuadriculadas activas (sin filtro de ciudad)
     */
    public function listarTodasZonas()
    {
        try {
            $sql = "SELECT id_zona, nombre_zona, lat_sw, lng_sw, lat_ne, lng_ne 
                        FROM zonas_cuadricula 
                        WHERE activo = 1 
                        ORDER BY id_zona";

            $zonas = $this->ejecutarConsulta($sql, '', [], "fetchAll");

            $this->log("Listado de todas las zonas obtenido. Total: " . count((array) $zonas));
            return $zonas;
        } catch (Exception $e) {
            $this->logWithBacktrace("Error al listar todas las zonas: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene direcciones dentro de un conjunto de zonas (para mostrar en el mapa)
     */
    public function listarDireccionesPorZonas($zonas_ids)
    {
        if (empty($zonas_ids)) {
            return [];
        }

        try {
            $placeholders = str_repeat('?,', count($zonas_ids) - 1) . '?';
            $sql = "SELECT d.id_direccion, d.direccion, d.lat, d.lng, c.nombre_comercial as cliente_nombre
                        FROM direcciones d
                        LEFT JOIN clientes c ON d.id_cliente = c.id_cliente
                        WHERE d.id_zona IN ($placeholders) AND d.lat IS NOT NULL AND d.lng IS NOT NULL
            ";
            $params = $zonas_ids;

            $direcciones = $this->ejecutarConsulta($sql, '', $params, "fetchAll");

            $this->log("Listado de direcciones para zonas obtenido. Total: " . count($direcciones));
            return $direcciones;
        } catch (Exception $e) {
            $this->logWithBacktrace("Error al listar direcciones por zonas: " . $e->getMessage());
            throw $e;
        }
    }

}
