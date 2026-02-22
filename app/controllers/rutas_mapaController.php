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

        $this->id_status_cancelado = 22;
        $this->id_status_finalizado = 38;
        $this->id_status_historico = 39;
        $this->id_status_activo = 18;
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
            $sql = "SELECT r.id_ruta, r.nombre_ruta, r.color_ruta, r.activo,
                COUNT(rd.id_direccion) AS total_direcciones
                FROM rutas r
                LEFT JOIN rutas_direcciones rd ON r.id_ruta = rd.id_ruta AND rd.activo = 1
                WHERE r.activo = :v_activo
                GROUP BY r.id_ruta, r.nombre_ruta, r.color_ruta, r.activo
                ORDER BY r.nombre_ruta";

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
            $sql = "SELECT d.id_direccion, 
						COALESCE(
							CASE 
								WHEN c.id_tipo_persona = 1 THEN TRIM(CONCAT_WS(' ', NULLIF(c.nombre, ''), NULLIF(c.apellido, '')))
								WHEN c.id_tipo_persona = 2 THEN NULLIF(c.nombre_comercial, '')
								ELSE NULLIF(c.nombre, '')
							END,
							'[SIN NOMBRE]'
						) AS cliente_nombre, 
                        d.direccion, d.lat, d.lng, rd.tiempo_servicio, ct.id_ruta
                    FROM rutas_direcciones rd
                    LEFT JOIN direcciones d ON rd.id_direccion = d.id_direccion
                    LEFT JOIN contratos ct ON rd.id_ruta = ct.id_ruta AND rd.id_direccion = ct.id_direccion AND ct.id_status = 18
                    LEFT JOIN clientes c ON d.id_cliente = c.id_cliente
                    WHERE rd.id_ruta = :id_ruta 
                        AND rd.activo = 1
                        AND ct.id_status = 18
                        AND d.id_direccion IS NOT NULL
                        AND (ct.fecha_fin IS NULL OR ct.fecha_fin >= CURDATE())
                    ORDER BY rd.orden_en_ruta";

            $param = [
                ':id_ruta' => $id_ruta
            ];

            $zonasConDirecciones = $this->ejecutarConsulta($sql, '', $param, "fetchAll");
            $this->log("Consulta para obtener la ruta con direcciones: " . json_encode($zonasConDirecciones));

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

            // Obtener zonas de la ruta
            $sql_zonas = "SELECT zc.id_zona, zc.nombre_zona, zc.lat_sw,
                    zc.lng_sw, zc.lat_ne, zc.lng_ne
                FROM rutas_zonas_cuadricula rz
                INNER JOIN zonas_cuadricula zc ON rz.id_zona = zc.id_zona
                WHERE rz.id_ruta = :id_ruta 
                    AND rz.activo = 1
                ORDER BY zc.id_zona
            ";            

            $param = [':id_ruta' => $id_ruta];
            $zonasRaw = $this->ejecutarConsulta($sql_zonas, '', $param, "fetchAll");

            // Inicializar estructura de zonas (vacía, sin direcciones aún)
            $zonasAgrupadas = [];
            foreach ($zonasRaw as $fila) {
                $id_zona = $fila['id_zona'];
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

            // Obtener direcciones ORDENADAS por orden_en_ruta
            $sql_direcciones = "SELECT rd.id_ruta, COUNT(*) OVER() AS cant_reg, d.id_direccion, 
                    COALESCE(
                        CASE 
                            WHEN c.id_tipo_persona = 1 THEN TRIM(CONCAT_WS(' ', NULLIF(c.nombre, ''), NULLIF(c.apellido, '')))
                            WHEN c.id_tipo_persona = 2 THEN NULLIF(c.nombre_comercial, '')
                            ELSE NULLIF(c.nombre, '')
                        END,
                        '[SIN NOMBRE]'
                    ) AS cliente_nombre,
                    d.direccion, d.lat AS dir_lat, d.lng AS dir_lng, 
                    COALESCE(FLOOR(TIME_TO_SEC(ct.tiempo_servicio) / 60), 0) AS tiempo_servicio,
                    rd.orden_en_ruta
                FROM rutas_direcciones rd
                LEFT JOIN direcciones d ON rd.id_direccion = d.id_direccion
                left JOIN contratos ct ON rd.id_ruta = ct.id_ruta AND rd.id_direccion = ct.id_direccion AND ct.id_status = 18
                LEFT JOIN clientes c ON d.id_cliente = c.id_cliente
                WHERE rd.id_ruta = :v_id_ruta
                    AND rd.activo = 1
                    AND d.id_direccion IS NOT NULL
                ORDER BY rd.orden_en_ruta ASC
            ";            

            $param = [':v_id_ruta' => $id_ruta];
            $direcciones = $this->ejecutarConsulta($sql_direcciones, '', $param, "fetchAll");
            $this->log("Consulta obtenerRutaConZonas: " . json_encode($direcciones));

            // Crear mapa de zonas para búsqueda rápida de límites
            $mapaZonas = [];
            foreach ($zonasRaw as $z) {
                $mapaZonas[$z['id_zona']] = $z;
            }

            // Determinar a qué zona pertenece cada dirección (manteniendo el orden de la SQL)
            $direccionesConZona = [];
            foreach ($direcciones as $dir) {
                if ($dir['id_direccion'] === null || $dir['dir_lat'] === null || $dir['dir_lng'] === null) {
                    continue;
                }

                // Buscar en qué zona está esta dirección
                $idZonaEncontrada = null;
                foreach ($mapaZonas as $id_zona => $z) {
                    if (
                        $dir['dir_lat'] >= $z['lat_sw'] &&
                        $dir['dir_lat'] <= $z['lat_ne'] &&
                        $dir['dir_lng'] >= $z['lng_sw'] &&
                        $dir['dir_lng'] <= $z['lng_ne']
                    ) {
                        $idZonaEncontrada = $id_zona;
                        break;
                    }
                }

                if ($idZonaEncontrada) {
                    $direccionesConZona[] = [
                        'id_direccion' => $dir['id_direccion'],
                        'cliente_nombre' => $dir['cliente_nombre'],
                        'direccion' => $dir['direccion'],
                        'lat' => $dir['dir_lat'],
                        'lng' => $dir['dir_lng'],
                        'tiempo_servicio' => (int)$dir['tiempo_servicio'],
                        'orden_en_ruta' => (int)$dir['orden_en_ruta'],
                        'id_zona' => $idZonaEncontrada
                    ];
                } else {
                    error_log("Dirección {$dir['id_direccion']} no está en ninguna zona de la ruta $id_ruta");
                }
            }

            // Reconstruir: insertar direcciones en sus zonas respetando el orden global
            foreach ($direccionesConZona as $dir) {
                $idZona = $dir['id_zona'];
                unset($dir['id_zona']); // Quitar campo auxiliar
                
                $zonasAgrupadas[$idZona]['direcciones'][] = $dir;
            }

            // Ordenar zonas según aparición de su primera dirección en la ruta
            $ordenZonas = [];
            $zonasVistas = [];
            foreach ($direccionesConZona as $dir) {
                $zonaId = $dir['id_zona'];
                if (!isset($zonasVistas[$zonaId])) {
                    $zonasVistas[$zonaId] = true;
                    $ordenZonas[] = $zonaId;
                }
            }
            
            // Reconstruir array de zonas en orden de aparición
            $zonasOrdenadas = [];
            foreach ($ordenZonas as $zonaId) {
                if (isset($zonasAgrupadas[$zonaId])) {
                    $zonasOrdenadas[] = $zonasAgrupadas[$zonaId];
                }
            }
            
            // Agregar zonas sin direcciones al final
            foreach ($zonasAgrupadas as $zonaId => $zonaData) {
                if (!in_array($zonaId, $ordenZonas)) {
                    $zonasOrdenadas[] = $zonaData;
                }
            }

            $ruta['zonas'] = $zonasOrdenadas;
            $this->log("Ruta de Consulta: $id_ruta");
            $this->log("Datos de ruta con zonas y direcciones obtenidos para ID: ". print_r($ruta,true));
            
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
     * @param array $direcciones_ids (antes era zonas_ids, ahora son IDs de direcciones)
     * @return int id_ruta creada
     */
    public function crearRuta($nombre_ruta, $color_ruta, $direcciones_ids)
    {
        try {
            // 1. Validar que las zonas existan y estén conectadas al HQ
            if (empty($direcciones_ids)) {
                throw new Exception("No se puede crear una ruta sin zonas asociadas.");
            }

            // === 1. Validar que las direcciones existan y tengan coordenadas ===
            // Obtener coordenadas de las zonas para validación
            $placeholders = str_repeat('?,', count($direcciones_ids) - 1) . '?';
            $sql_check = "SELECT id_direccion, lat, lng 
                        FROM direcciones 
                        WHERE id_direccion IN ($placeholders) 
                            AND lat IS NOT NULL 
                            AND lng IS NOT NULL";
            $params = $direcciones_ids;
            $direcciones_datos = $this->ejecutarConsulta($sql_check, '', $params, "fetchAll");

            if (count($direcciones_datos) !== count($direcciones_ids)) {
                throw new Exception("Algunas direcciones no existen o no tienen coordenadas válidas.");
            }

            // === 2. Validar conexión con HQ (30.3204272, -95.4217815) ===
            // Validar conexión con HQ (lat_hq = 30.3204272, lng_hq = -95.4217815)
            $hq_lat = 30.3204272;
            $hq_lng = -95.4217815;
            $distancia_minima = PHP_FLOAT_MAX;

            foreach ($direcciones_datos as $dir) {
                // Calcular distancia en grados
                $dist_grados = sqrt(
                    pow($dir['lat'] - $hq_lat, 2) + 
                    pow($dir['lng'] - $hq_lng, 2)
                );
                // Convertir a km (~111 km por grado)
                $dist_km = $dist_grados * 111;

                if ($dist_km < $distancia_minima_km) {
                    $distancia_minima_km = $dist_km;
                }
            }

            // Convertir distancia en grados a km (aproximado: 1 grado ~ 111 km)
            $distancia_km = $distancia_minima * 111;
            $umbral_km = 10; // Definir umbral, por ejemplo 10 km

            if ($distancia_km > $umbral_km) {
                throw new Exception("La ruta no está conectada al headquarter. La zona más cercana está a " . sprintf('%.2f', $distancia_km) . " km.");
            }

            // === 3. Crear la ruta ===
            $logRuta = [
                ['campo_nombre' => 'nombre_ruta', 'campo_marcador' => ':nombre_ruta', 'campo_valor' => $nombre_ruta],
                ['campo_nombre' => 'color_ruta', 'campo_marcador' => ':color_ruta', 'campo_valor' => $color_ruta],
                ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1],
                // Lat y Lng del HQ están por defecto en la tabla
            ];
            $id_ruta = $this->guardarDatos('rutas', $logRuta);

            // === 4. Asociar direcciones a la ruta ===
            foreach ($direcciones_ids as $id_direccion) {
                $logRutaZona = [
                    ['campo_nombre' => 'id_ruta', 'campo_marcador' => ':id_ruta', 'campo_valor' => $id_ruta],
                    ['campo_nombre' => 'id_direccion', 'campo_marcador' => ':id_direccion', 'campo_valor' => $id_direccion],
                    ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1],
                ];
                $this->guardarDatos('rutas_direcciones', $logRutaZona);
            }

            $this->log("Ruta creada exitosamente: ID $id_ruta, Nombre '$nombre_ruta', Zonas: " . count($direcciones_ids));
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
    public function actualizarRuta($id_ruta, $nombre_ruta, $color_ruta, $direcciones_ids)
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
            if (empty($direcciones_ids)) {
                throw new Exception("A route cannot be updated without associated zones.");
            }

            $placeholders = str_repeat('?,', count($direcciones_ids) - 1) . '?';
            $sql_check = "SELECT id_zona, lat_sw, lng_sw, lat_ne, lng_ne 
                            FROM zonas_cuadricula 
                            WHERE id_zona IN ($placeholders) AND activo = 1";
            $params = $direcciones_ids;

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

                // 5. Asociar nuevas zonas
                foreach ($direcciones_ids as $id_zona) {
                    // Verificar si ya existe el vínculo (y está inactivo), si es así, reactivarlo
                    $sql_check_link = "SELECT id_ruta_zona 
                        FROM rutas_zonas_cuadricula 
                        WHERE id_ruta = :id_ruta 
                            AND id_zona = :id_zona";
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

                $this->log("Ruta actualizada exitosamente: ID $id_ruta, Nombre '$nombre_ruta', Zonas: " . count($direcciones_ids));
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
            $fecha_actual = new \DateTime();
            $actualizado_en = $fecha_actual->format('Y-m-d H:i:s');

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
            
            $sql = "DELETE rda
                FROM route_day_assignments rda
                WHERE rda.id_ruta > :v_id_ruta";
            $params = [
                ":v_id_ruta" => $id_ruta,
            ];
            $borrados = $this->ejecutarConsulta($sql, "", $params);

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
            $sql = "SELECT d.id_direccion, d.direccion, d.lat, d.lng, 
						COALESCE(
							CASE 
								WHEN c.id_tipo_persona = 1 THEN TRIM(CONCAT_WS(' ', NULLIF(c.nombre, ''), NULLIF(c.apellido, '')))
								WHEN c.id_tipo_persona = 2 THEN NULLIF(c.nombre_comercial, '')
								ELSE NULLIF(c.nombre, '')
							END,
							'[SIN NOMBRE]'
						) AS cliente_nombre
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

    public function consultar_rutas()
    {
        $sql = 'SELECT id_ruta, nombre_ruta
            FROM rutas
            WHERE activo = 1
			ORDER BY nombre_ruta';
        
        $param = [];

        $data = $this->ejecutarConsulta($sql, "", $param, "fetchAll");

        return $data;
    }

    public function actualizarOrdenDirecciones($id_ruta, $orden_direcciones) {
        if (!is_array($orden_direcciones)) {
            throw new Exception("El orden de direcciones debe ser un array");
        }

        // ✅ Crear mapa: id_direccion => orden (1, 2, 3, ...)
        $mapa_orden = [];
        foreach ($orden_direcciones as $indice => $id_direccion) {
            // Asegurar que sea entero
            $id_direccion = (int)$id_direccion;
            if ($id_direccion > 0) {
                $mapa_orden[$id_direccion] = $indice + 1; // orden empieza en 1
            }
        }

        $this->log("Orden Nuevo - Proceso antes de aplicar Update: " . print_r($mapa_orden, true));

        if (empty($mapa_orden)) {
            return true; // nada que actualizar
        }

        // Obtener registros actuales
        $sql = "SELECT id_ruta_direccion, id_direccion, orden_en_ruta
                FROM rutas_direcciones
                WHERE id_ruta = :v_id_ruta";

        $params = [':v_id_ruta' => $id_ruta];
        $data = $this->ejecutarConsulta($sql, "", $params, "fetchAll");

        $this->log("Orden Nuevo: " . print_r($mapa_orden, true));

        // ✅ Crear mapa inverso: id_direccion => id_ruta_direccion (para buscar rápido)
        $mapa_bd = [];
        foreach ($data as $row) {
            $mapa_bd[(int)$row['id_direccion']] = [
                'id_ruta_direccion' => $row['id_ruta_direccion'],
                'orden_actual' => $row['orden_en_ruta']
            ];
        }

        $actualizados = 0;
        $errores = [];

        // ✅ Iterar sobre el MAPA DE ORDEN (no sobre la BD)
        foreach ($mapa_orden as $id_direccion => $nuevo_orden) {
            
            if (!isset($mapa_bd[$id_direccion])) {
                $this->log("⚠️ Advertencia: id_direccion $id_direccion no existe en la ruta $id_ruta");
                continue;
            }

            $id_ruta_direccion = $mapa_bd[$id_direccion]['id_ruta_direccion'];
            $orden_actual = $mapa_bd[$id_direccion]['orden_actual'];

            // Solo actualizar si cambió el orden
            if ($orden_actual != $nuevo_orden) {
                
                $datos = [
                    ['campo_nombre' => 'orden_en_ruta', 'campo_marcador' => ':orden_en_ruta', 'campo_valor' => $nuevo_orden]
                ];
                $condicion = [
                    'condicion_campo' => 'id_ruta_direccion',
                    'condicion_operador' => '=',
                    'condicion_marcador' => ':id_ruta_direccion',
                    'condicion_valor' => $id_ruta_direccion
                ];

                try {
                    $resultado = $this->actualizarDatos('rutas_direcciones', $datos, $condicion);
                    
                    if ($resultado) {
                        $actualizados++;
                        $this->log("✅ Actualizado id_ruta_direccion=$id_ruta_direccion (id_direccion=$id_direccion): orden $orden_actual → $nuevo_orden");
                    } else {
                        $errores[] = "Falló actualización para id_ruta_direccion=$id_ruta_direccion";
                        $this->log("❌ Falló actualizarDatos para id_ruta_direccion=$id_ruta_direccion");
                    }
                    
                } catch (Exception $e) {
                    $errores[] = "Excepción: " . $e->getMessage();
                    $this->log("❌ Excepción: " . $e->getMessage());
                }
            } else {
                $this->log("⏭️ Sin cambios para id_direccion=$id_direccion (orden ya es $nuevo_orden)");
            }
        }

        $this->log("Resumen: $actualizados registros actualizados. Errores: " . count($errores));

        if (!empty($errores)) {
            throw new Exception("Errores al actualizar orden: " . implode(", ", $errores));
        }

        return true;
    }

    public function consultar_rutas_dia($fecha_proceso)    
    {
		// 1 - Determinar el d{ia de la semana
		$timestamp = strtotime($fecha_proceso); // Convierte la cadena a un timestamp
		$dia_semana_numero = date('w', $timestamp) + 1; // 'w' devuelve el día numérico (0 = domingo, 6 = sábado)

		$sql = "SELECT dia_ingles 
					FROM dias_semana
					WHERE id_dia_semana = :v_id_dia_semana";
		$params = [
			":v_id_dia_semana" => $dia_semana_numero
		];

		$resultado = $this->ejecutarConsulta($sql, "", $params);
		$variable_php_upper = strtoupper($resultado['dia_ingles']);

		// 2 - Determinar las Rutas asignadas al dia seleccionado
		$sql = "SELECT rda.day_of_week, rda.id_ruta, r.nombre_ruta
					FROM route_day_assignments AS rda
					LEFT JOIN rutas AS r ON rda.id_ruta = r.id_ruta
					WHERE UPPER(day_of_week) = :v_day_of_week";
		$params = [
			":v_day_of_week" => $variable_php_upper
		];
		$rutas = $this->ejecutarConsulta($sql, "", $params, "fetchAll");

        $resultado = [
            'dia' => $variable_php_upper,
            'rutas' => $rutas
        ];
        return $resultado;
    }

    public function direcciones_libres($id_ruta_actual)
    {
        $sql = "SELECT d.*, 
                COALESCE(
                    CASE 
                        WHEN c.id_tipo_persona = 1 THEN TRIM(CONCAT_WS(' ', NULLIF(c.nombre, ''), NULLIF(c.apellido, '')))
                        WHEN c.id_tipo_persona = 2 THEN NULLIF(c.nombre_comercial, '')
                        ELSE NULLIF(c.nombre, '')
                    END,
                    '[SIN NOMBRE]'
                ) AS cliente_nombre
            FROM direcciones d
            JOIN contratos co ON d.id_direccion = co.id_direccion
            JOIN clientes c ON c.id_cliente = d.id_cliente
            WHERE co.id_status = 18
                AND (co.fecha_fin IS NULL OR co.fecha_fin >= CURDATE())
                AND d.id_direccion NOT IN (
                    SELECT DISTINCT id_direccion 
                    FROM rutas_direcciones 
                    WHERE activo = 1
                )
                AND d.lat IS NOT NULL 
                AND d.lng IS NOT NULL
                AND d.id_direccion NOT IN (
                SELECT id_direccion 
                    FROM rutas_direcciones 
                    WHERE id_ruta = :v_id_ruta_actual AND activo = 1)";

        $params = [
			":v_id_ruta_actual" => $id_ruta_actual
		];
		$addresfree = $this->ejecutarConsulta($sql, "", $params, "fetchAll");
        return $addresfree;
    }

    public function asignarDireccionARuta($id_ruta, $id_direccion, $id_zona)
    {
        // 1. Verificar que dirección no esté en otra ruta activa
        $sql = "SELECT id_ruta 
                FROM rutas_direcciones 
                WHERE id_direccion = :v_id_direccion 
                    AND activo = 1 
                    AND id_ruta != :v_id_ruta";
        $params = [
            ':v_id_direccion' => $id_direccion,
            ':v_id_ruta' => $id_ruta
        ];

        $rutaExistente = $this->ejecutarConsulta($sql, '', $params);
        if ($rutaExistente) {
            throw new Exception("The address is already assigned to another active route (ID Route: " . $rutaExistente['id_ruta'] . ")");
        }

        // 2. Calcular orden_en_ruta (MAX + 1 de la ruta actual)
        $sql = "SELECT MAX(orden_en_ruta) AS max_orden 
                FROM rutas_direcciones 
                WHERE id_ruta = :v_id_ruta";
        $params = [
            ':v_id_ruta' => $id_ruta
        ];
        $resultado = $this->ejecutarConsulta($sql, '', $params);

        // 3. INSERT INTO rutas_direcciones
        $nuevo_orden = ($resultado['max_orden'] !== null) ? $resultado['max_orden'] + 1 : 1;
        $logRutaZona = [
            ['campo_nombre' => 'id_ruta', 'campo_marcador' => ':id_ruta', 'campo_valor' => $id_ruta],
            ['campo_nombre' => 'id_direccion', 'campo_marcador' => ':id_direccion', 'campo_valor' => $id_direccion],
            ['campo_nombre' => 'orden_en_ruta', 'campo_marcador' => ':orden_en_ruta', 'campo_valor' => $nuevo_orden],
            ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1],
        ];
        $this->guardarDatos('rutas_direcciones', $logRutaZona);

        // 4. UPDATE CONTRATOS SET id_ruta = :id_ruta WHERE id_direccion = :id_direccion
        $actualizado_en = (new \DateTime())->format('Y-m-d H:i:s');
        $observaciones = "Dirección asignada a ruta ID: $id_ruta en fecha $actualizado_en";
        
        $datos = [
            ['campo_nombre' => 'id_ruta', 'campo_marcador' => ':id_ruta', 'campo_valor' => $id_ruta],
            ['campo_nombre' => 'observaciones', 'campo_marcador' => ':observaciones', 'campo_valor' => $observaciones]
            
        ];
        $condicion = [
            'condicion_campo' => 'id_direccion',
            'condicion_operador' => '=',
            'condicion_marcador' => ':id_direccion',
            'condicion_valor' => $id_direccion
        ];
        $this->actualizarDatos('contratos', $datos, $condicion);

        // 5. Si id_zona proporcionada: verificar/crear en rutas_zonas_cuadricula
        $sql_check_link = "SELECT id_ruta_zona 
            FROM rutas_zonas_cuadricula 
            WHERE id_ruta = :id_ruta 
                AND id_zona = :id_zona";
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

        return true;
    }

    public function guardarCambiosBatch($id_ruta, $nombre_ruta, $color_ruta, $cambios) {
        $resultado = [
            'zonas_agregadas' => 0,
            'zonas_removidas' => 0,
            'direcciones_agregadas' => 0,
            'direcciones_removidas' => 0,
            'errores' => []
        ];
        
        try {
            // 1. Actualizar nombre y color de ruta
            $this->actualizarDatos('rutas', [
                ['campo_nombre' => 'nombre_ruta', 'campo_valor' => $nombre_ruta],
                ['campo_nombre' => 'color_ruta', 'campo_valor' => $color_ruta],
                ['campo_nombre' => 'actualizado_en', 'campo_valor' => date('Y-m-d H:i:s')]
            ], [
                'condicion_campo' => 'id_ruta',
                'condicion_operador' => '=',
                'condicion_valor' => $id_ruta
            ]);
            
            // 2. Procesar zonas a quitar (solo si quedan sin direcciones)
            foreach (($cambios['zonas_vacias_agregar'] ?? []) as $id_zona) {
                try {
                    $this->agregarZonaVaciaARuta($id_ruta, $id_zona);  // Solo vincula zona, sin direcciones
                    $resultado['zonas_removidas']++;
                } catch (Exception $e) {
                    $resultado['errores'][] = "Zone $id_zona: " . $e->getMessage();
                }
            }
            
            // 4. Procesar direcciones a agregar (usar tu función existente)
            foreach ($cambios['direcciones_agregar'] as $dir) {
                try {
                    $this->asignarDireccionARuta($id_ruta, $dir['id_direccion'], $dir['id_zona']);
                    $resultado['direcciones_agregadas']++;
                } catch (Exception $e) {
                    $resultado['errores'][] = "Address {$dir['id_direccion']}: " . $e->getMessage();
                }
            }
            
            // 5. Procesar direcciones a quitar
            foreach ($cambios['direcciones_quitar'] as $id_direccion) {
                try {
                    $this->quitarDireccionDeRuta($id_ruta, $id_direccion);
                    $resultado['direcciones_removidas']++;
                } catch (Exception $e) {
                    $resultado['errores'][] = "Address $id_direccion: " . $e->getMessage();
                }
            }
            
            return [
                'success' => true,
                'data' => $resultado
            ];
            
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function agregarZonaVaciaARuta($id_ruta, $id_zona) {
        // Similar a tu lógica de zona en asignarDireccionARuta
        // Pero SIN dirección asociada
        
        $sql_check = "SELECT id_ruta_zona FROM rutas_zonas_cuadricula 
            WHERE id_ruta = :id_ruta AND id_zona = :id_zona";
        $existing = $this->ejecutarConsulta($sql_check, '', [
            ':id_ruta' => $id_ruta,
            ':id_zona' => $id_zona
        ]);
        
        if ($existing) {
            // Reactivar
            $this->actualizarDatos('rutas_zonas_cuadricula', 
                [['campo_nombre' => 'activo', 'campo_valor' => 1]],
                ['condicion_campo' => 'id_ruta_zona', 'condicion_valor' => $existing['id_ruta_zona']]
            );
        } else {
            // Crear nuevo
            $this->guardarDatos('rutas_zonas_cuadricula', [
                ['campo_nombre' => 'id_ruta', 'campo_valor' => $id_ruta],
                ['campo_nombre' => 'id_zona', 'campo_valor' => $id_zona],
                ['campo_nombre' => 'activo', 'campo_valor' => 1]
            ]);
        }
    }

    public function quitarDireccionDeRuta($id_ruta, $id_direccion)
    {
        // 1. Verificar que la dirección exista en la ruta
        $sql = "SELECT id_ruta_direccion, orden_en_ruta 
                FROM rutas_direcciones 
                WHERE id_ruta = :v_id_ruta 
                    AND id_direccion = :v_id_direccion 
                    AND activo = 1";
        $params = [
            ':v_id_ruta' => $id_ruta,
            ':v_id_direccion' => $id_direccion
        ];

        $direccionEnRuta = $this->ejecutarConsulta($sql, '', $params);
        
        if (!$direccionEnRuta) {
            throw new Exception("Address not found in this route or already inactive");
        }

        $id_ruta_direccion = $direccionEnRuta['id_ruta_direccion'];
        $orden_removido = $direccionEnRuta['orden_en_ruta'];

        // 2. Soft delete de la dirección en la ruta
        $actualizado_en = (new \DateTime())->format('Y-m-d H:i:s');
        $datos = [
            ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 0],
            ['campo_nombre' => 'actualizado_en', 'campo_marcador' => ':actualizado_en', 'campo_valor' => $actualizado_en]
        ];
        $condicion = [
            'condicion_campo' => 'id_ruta_direccion',
            'condicion_operador' => '=',
            'condicion_marcador' => ':id_ruta_direccion',
            'condicion_valor' => $id_ruta_direccion
        ];
        $this->actualizarDatos('rutas_direcciones', $datos, $condicion);

        // 3. Reordenar las direcciones restantes (compactar orden)
        $sql_reordenar = "UPDATE rutas_direcciones 
                        SET orden_en_ruta = orden_en_ruta - 1 
                        WHERE id_ruta = :v_id_ruta 
                            AND activo = 1 
                            AND orden_en_ruta > :v_orden_removido";
        $params_reordenar = [
            ':v_id_ruta' => $id_ruta,
            ':v_orden_removido' => $orden_removido
        ];
        $this->ejecutarConsulta($sql_reordenar, '', $params_reordenar);

        // 4. UPDATE CONTRATOS - quitar id_ruta
        $observaciones = "Dirección removida de ruta ID: $id_ruta en fecha $actualizado_en";
        
        $datos = [
            ['campo_nombre' => 'id_ruta', 'campo_marcador' => ':id_ruta', 'campo_valor' => null],
            ['campo_nombre' => 'observaciones', 'campo_marcador' => ':observaciones', 'campo_valor' => $observaciones]
        ];
        $condicion = [
            'condicion_campo' => 'id_direccion',
            'condicion_operador' => '=',
            'condicion_marcador' => ':id_direccion',
            'condicion_valor' => $id_direccion
        ];
        $this->actualizarDatos('contratos', $datos, $condicion);

        // 5. Verificar si la zona quedó vacía (opcional - para notificación)
        // Esta consulta es informativa, no afecta la operación
        $sql_zona = "SELECT zc.id_zona, zc.nombre_zona, COUNT(rd.id_ruta_direccion) as total_direcciones
                    FROM rutas_zonas_cuadricula rzc
                    JOIN zonas_cuadricula zc ON rzc.id_zona = zc.id_zona
                    LEFT JOIN rutas_direcciones rd ON rzc.id_ruta = rd.id_ruta 
                        AND rd.activo = 1
                    WHERE rzc.id_ruta = :v_id_ruta 
                        AND rzc.activo = 1
                    GROUP BY zc.id_zona, zc.nombre_zona
                    HAVING total_direcciones = 0";
        $zonasVacias = $this->ejecutarConsulta($sql_zona, 'all', [':v_id_ruta' => $id_ruta]);

        return [
            'success' => true,
            'id_direccion' => $id_direccion,
            'id_ruta' => $id_ruta,
            'orden_anterior' => $orden_removido,
            'zonas_vacias_detectadas' => $zonasVacias ?: []
        ];
    }    

    // rutas_mapaController.php
    public function listarDireccionesLibresConContrato() {
        $sql = "SELECT 
                    d.id_direccion,
                    d.lat,
                    d.lng,
                    d.direccion,
                    COALESCE(
                        CASE 
                            WHEN c.id_tipo_persona = 1 THEN TRIM(CONCAT_WS(' ', NULLIF(c.nombre, ''), NULLIF(c.apellido, '')))
                            WHEN c.id_tipo_persona = 2 THEN NULLIF(c.nombre_comercial, '')
                            ELSE NULLIF(c.nombre, '')
                        END,
                        '[SIN NOMBRE]'
                    ) AS cliente_nombre,
                    co.tiempo_servicio
                FROM direcciones d
                INNER JOIN contratos co ON d.id_direccion = co.id_direccion
                INNER JOIN clientes c ON co.id_cliente = c.id_cliente
                WHERE co.activo = 1
                    AND (co.fecha_fin IS NULL OR co.fecha_fin >= CURDATE())
                    AND co.id_ruta IS NULL
                    AND d.lat IS NOT NULL 
                    AND d.lng IS NOT NULL";
        $params = [];
        $datos = $this->ejecutarConsulta($sql, '', $params, 'fetchAll');
        return $datos;
    }

    /**
     * Crea una ruta completa con zonas y direcciones seleccionadas
     * @param string $nombre_ruta
     * @param string $color_ruta
     * @param array $zonas [{id_zona, direcciones_ids: []}]
     * @return int id_ruta creada
     */
    public function crearRutaCompleta($nombre_ruta, $color_ruta, $zonas)
    {
        try {
            // 1. Crear la ruta base
            $logRuta = [
                ['campo_nombre' => 'nombre_ruta', 'campo_marcador' => ':nombre_ruta', 'campo_valor' => $nombre_ruta],
                ['campo_nombre' => 'color_ruta', 'campo_marcador' => ':color_ruta', 'campo_valor' => $color_ruta],
                ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1],
            ];
            $id_ruta = $this->guardarDatos('rutas', $logRuta);
            
            $totalDirecciones = 0;
            $ordenGlobal = 1;
            
            // 2. Procesar cada zona
            foreach ($zonas as $zonaData) {
                $id_zona = $zonaData['id_zona'];
                $direcciones_ids = $zonaData['direcciones_ids'] ?? [];
                
                // 2.1 Vincular zona a ruta (siempre, aunque esté vacía)
                $this->vincularZonaARuta($id_ruta, $id_zona);
                
                // 2.2 Procesar direcciones seleccionadas
                foreach ($direcciones_ids as $id_direccion) {
                    // Verificar que dirección no esté en otra ruta
                    if ($this->direccionEnOtraRuta($id_direccion, $id_ruta)) {
                        $this->log("⚠️ Dirección $id_direccion ya está en otra ruta, se omite");
                        continue;
                    }
                    
                    // Insertar en rutas_direcciones con orden global
                    $this->asignarDireccionARutaConOrden($id_ruta, $id_direccion, $id_zona, $ordenGlobal);
                    $ordenGlobal++;
                    $totalDirecciones++;
                }
            }
            
            $this->log("✅ Ruta creada: ID $id_ruta, Zonas: " . count($zonas) . ", Direcciones: $totalDirecciones");
            return $id_ruta;
            
        } catch (Exception $e) {
            $this->logWithBacktrace("Error al crear ruta completa: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualiza ruta existente con cambios batch
     * @param int $id_ruta
     * @param string $nombre_ruta
     * @param string $color_ruta
     * @param array $cambios [
     *   zonas_agregar: [{id_zona, direcciones_ids: []}],
     *   zonas_quitar: [id_zona],
     *   direcciones_agregar: [{id_direccion, id_zona}],
     *   direcciones_quitar: [id_direccion]
     * ]
     */
    public function actualizarRutaCompleta($id_ruta, $nombre_ruta, $color_ruta, $cambios)
    {
        try {
            // 1. Actualizar datos básicos
            $datos = [
                ['campo_nombre' => 'nombre_ruta', 'campo_marcador' => ':nombre_ruta', 'campo_valor' => $nombre_ruta],
                ['campo_nombre' => 'color_ruta', 'campo_marcador' => ':color_ruta', 'campo_valor' => $color_ruta],
                ['campo_nombre' => 'actualizado_en', 'campo_marcador' => ':actualizado_en', 'campo_valor' => date('Y-m-d H:i:s')]
            ];
            $condicion = [
                'condicion_campo' => 'id_ruta',
                'condicion_operador' => '=',
                'condicion_marcador' => ':id_ruta',
                'condicion_valor' => $id_ruta
            ];
            $this->actualizarDatos('rutas', $datos, $condicion);
            
            $resultado = [
                'zonas_agregadas' => 0,
                'zonas_removidas' => 0,
                'direcciones_agregadas' => 0,
                'direcciones_removidas' => 0
            ];
            
            // 2. Quitar zonas (y sus direcciones asociadas)
            foreach (($cambios['zonas_quitar'] ?? []) as $id_zona) {
                $this->quitarZonaDeRuta($id_ruta, $id_zona);
                $resultado['zonas_removidas']++;
            }
            
            // 3. Agregar nuevas zonas
            foreach (($cambios['zonas_agregar'] ?? []) as $zonaData) {
                $id_zona = $zonaData['id_zona'];
                $direcciones_ids = $zonaData['direcciones_ids'] ?? [];
                
                $this->vincularZonaARuta($id_ruta, $id_zona);
                $resultado['zonas_agregadas']++;
                
                foreach ($direcciones_ids as $id_direccion) {
                    if (!$this->direccionEnOtraRuta($id_direccion, $id_ruta)) {
                        $this->asignarDireccionARutaConOrden($id_ruta, $id_direccion, $id_zona, null); // orden automático
                        $resultado['direcciones_agregadas']++;
                    }
                }
            }
            
            // 4. Agregar direcciones sueltas a zonas existentes
            foreach (($cambios['direcciones_agregar'] ?? []) as $dirData) {
                $id_direccion = $dirData['id_direccion'];
                $id_zona = $dirData['id_zona'];
                
                if (!$this->direccionEnOtraRuta($id_direccion, $id_ruta)) {
                    $this->asignarDireccionARutaConOrden($id_ruta, $id_direccion, $id_zona, null);
                    $resultado['direcciones_agregadas']++;
                }
            }
            
            // 5. Quitar direcciones específicas
            foreach (($cambios['direcciones_quitar'] ?? []) as $id_direccion) {
                $this->quitarDireccionDeRuta($id_ruta, $id_direccion);
                $resultado['direcciones_removidas']++;
            }
            
            // 6. Reordenar si es necesario (compactar orden)
            $this->compactarOrdenDirecciones($id_ruta);
            
            $this->log("✅ Ruta actualizada: ID $id_ruta. " . json_encode($resultado));
            return $resultado;
            
        } catch (Exception $e) {
            $this->logWithBacktrace("Error al actualizar ruta completa: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene direcciones de una zona para modal de selección
     * Marca cuáles ya están en la ruta actual
     */
    public function obtenerDireccionesZonaParaSeleccion($id_zona, $id_ruta_actual = null)
    {
        try {
            // 1. Obtener límites de la zona
            $sqlZona = "SELECT lat_sw, lng_sw, lat_ne, lng_ne 
                        FROM zonas_cuadricula 
                        WHERE id_zona = :id_zona AND activo = 1";
            $zona = $this->ejecutarConsulta($sqlZona, '', [':id_zona' => $id_zona]);
            
            if (!$zona) {
                throw new Exception("Zona no encontrada");
            }
            
            // 2. Buscar direcciones dentro de los límites de la zona
            $sql = "SELECT 
                        d.id_direccion,
                        d.direccion,
                        d.lat,
                        d.lng,
                        COALESCE(
                            CASE 
                                WHEN c.id_tipo_persona = 1 THEN TRIM(CONCAT_WS(' ', NULLIF(c.nombre, ''), NULLIF(c.apellido, '')))
                                WHEN c.id_tipo_persona = 2 THEN NULLIF(c.nombre_comercial, '')
                                ELSE NULLIF(c.nombre, '')
                            END,
                            '[SIN NOMBRE]'
                        ) AS cliente_nombre,
                        co.tiempo_servicio,
                        rd.id_ruta as id_ruta_asignada,
                        r.nombre_ruta as nombre_ruta_asignada
                    FROM direcciones d
                    INNER JOIN contratos co ON d.id_direccion = co.id_direccion 
                        AND co.id_status = :status_activo
                        AND (co.fecha_fin IS NULL OR co.fecha_fin >= CURDATE())
                    LEFT JOIN clientes c ON d.id_cliente = c.id_cliente
                    LEFT JOIN rutas_direcciones rd ON d.id_direccion = rd.id_direccion 
                        AND rd.activo = 1
                    LEFT JOIN rutas r ON rd.id_ruta = r.id_ruta
                    WHERE d.lat BETWEEN :lat_sw AND :lat_ne
                    AND d.lng BETWEEN :lng_sw AND :lng_ne
                    AND d.lat IS NOT NULL 
                    AND d.lng IS NOT NULL
                    ORDER BY cliente_nombre";
            
            $params = [
                ':status_activo' => $this->id_status_activo,
                ':lat_sw' => $zona['lat_sw'],
                ':lat_ne' => $zona['lat_ne'],
                ':lng_sw' => $zona['lng_sw'],
                ':lng_ne' => $zona['lng_ne']
            ];
            
            $direcciones = $this->ejecutarConsulta($sql, '', $params, "fetchAll");
            
            // 3. Clasificar direcciones
            $resultado = [
                'en_ruta_actual' => [],      // Ya en esta ruta (disabled en modal)
                'libres' => [],              // Sin ruta (seleccionables)
                'en_otra_ruta' => []         // En otra ruta (info only, no seleccionables por ahora)
            ];
            
            foreach ($direcciones as $dir) {
                $dir['tiempo_servicio'] = $dir['tiempo_servicio'] ? 
                    floor(strtotime($dir['tiempo_servicio']) / 60) : 0;
                
                if ($id_ruta_actual && $dir['id_ruta_asignada'] == $id_ruta_actual) {
                    $resultado['en_ruta_actual'][] = $dir;
                } elseif (!$dir['id_ruta_asignada']) {
                    $resultado['libres'][] = $dir;
                } else {
                    $resultado['en_otra_ruta'][] = $dir;
                }
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            $this->logWithBacktrace("Error al obtener direcciones de zona: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lista TODAS las direcciones con contrato activo para modo ADDRESSES
     * Incluye info de ruta asignada y su color
     */
    public function listarTodasDireccionesConContrato()
    {
        try {
            $sql = "SELECT 
                        d.id_direccion,
                        d.direccion,
                        d.lat,
                        d.lng,
                        COALESCE(
                            CASE 
                                WHEN c.id_tipo_persona = 1 THEN TRIM(CONCAT_WS(' ', NULLIF(c.nombre, ''), NULLIF(c.apellido, '')))
                                WHEN c.id_tipo_persona = 2 THEN NULLIF(c.nombre_comercial, '')
                                ELSE NULLIF(c.nombre, '')
                            END,
                            '[SIN NOMBRE]'
                        ) AS cliente_nombre,
                        co.tiempo_servicio,
                        rd.id_ruta,
                        r.nombre_ruta,
                        r.color_ruta
                    FROM direcciones d
                    INNER JOIN contratos co ON d.id_direccion = co.id_direccion 
                        AND co.id_status = :status_activo
                        AND (co.fecha_fin IS NULL OR co.fecha_fin >= CURDATE())
                    LEFT JOIN clientes c ON d.id_cliente = c.id_cliente
                    LEFT JOIN rutas_direcciones rd ON d.id_direccion = rd.id_direccion 
                        AND rd.activo = 1
                    LEFT JOIN rutas r ON rd.id_ruta = r.id_ruta AND r.activo = 1
                    WHERE d.lat IS NOT NULL 
                    AND d.lng IS NOT NULL
                    ORDER BY r.nombre_ruta, cliente_nombre";
            
            $params = [':status_activo' => $this->id_status_activo];
            $direcciones = $this->ejecutarConsulta($sql, '', $params, "fetchAll");
            
            // Formatear tiempo_servicio
            foreach ($direcciones as &$dir) {
                $dir['es_libre'] = ($dir['id_ruta'] === null);
                $dir['tiempo_servicio'] = $dir['tiempo_servicio'] ? 
                    floor(strtotime($dir['tiempo_servicio']) / 60) : 0;
            }
            
            return $direcciones;
            
        } catch (Exception $e) {
            $this->logWithBacktrace("Error al listar direcciones: " . $e->getMessage());
            throw $e;
        }
    }

    // ============================================
    // FUNCIONES AUXILIARES PRIVADAS
    // ============================================

    private function vincularZonaARuta($id_ruta, $id_zona)
    {
        // Verificar si ya existe
        $sql = "SELECT id_ruta_zona FROM rutas_zonas_cuadricula 
                WHERE id_ruta = :id_ruta AND id_zona = :id_zona";
        $existing = $this->ejecutarConsulta($sql, '', [
            ':id_ruta' => $id_ruta,
            ':id_zona' => $id_zona
        ]);
        
        if ($existing) {
            // Reactivar si está inactivo
            $datos = [
                ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1],
                ['campo_nombre' => 'actualizado_en', 'campo_marcador' => ':actualizado_en', 'campo_valor' => date('Y-m-d H:i:s')]
            ];
            $condicion = [
                'condicion_campo' => 'id_ruta_zona',
                'condicion_operador' => '=',
                'condicion_marcador' => ':id_ruta_zona',
                'condicion_valor' => $existing['id_ruta_zona']
            ];
            $this->actualizarDatos('rutas_zonas_cuadricula', $datos, $condicion);
        } else {
            // Crear nuevo vínculo
            $log = [
                ['campo_nombre' => 'id_ruta', 'campo_marcador' => ':id_ruta', 'campo_valor' => $id_ruta],
                ['campo_nombre' => 'id_zona', 'campo_marcador' => ':id_zona', 'campo_valor' => $id_zona],
                ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1]
            ];
            $this->guardarDatos('rutas_zonas_cuadricula', $log);
        }
    }

    private function quitarZonaDeRuta($id_ruta, $id_zona)
    {
        // Soft delete del vínculo
        $sql = "UPDATE rutas_zonas_cuadricula 
                SET activo = 0, actualizado_en = :actualizado_en
                WHERE id_ruta = :id_ruta AND id_zona = :id_zona";
        $this->ejecutarConsulta($sql, '', [
            ':id_ruta' => $id_ruta,
            ':id_zona' => $id_zona,
            ':actualizado_en' => date('Y-m-d H:i:s')
        ]);
        
        // También desactivar direcciones de esta zona en esta ruta
        $sql = "UPDATE rutas_direcciones rd
                JOIN direcciones d ON rd.id_direccion = d.id_direccion
                SET rd.activo = 0, rd.actualizado_en = :actualizado_en
                WHERE rd.id_ruta = :id_ruta 
                AND d.id_zona = :id_zona
                AND rd.activo = 1";
        $this->ejecutarConsulta($sql, '', [
            ':id_ruta' => $id_ruta,
            ':id_zona' => $id_zona,
            ':actualizado_en' => date('Y-m-d H:i:s')
        ]);
    }

    private function asignarDireccionARutaConOrden($id_ruta, $id_direccion, $id_zona, $orden = null)
    {
        // Si no se especifica orden, calcular el siguiente
        if ($orden === null) {
            $sql = "SELECT MAX(orden_en_ruta) as max_orden 
                    FROM rutas_direcciones 
                    WHERE id_ruta = :id_ruta AND activo = 1";
            $result = $this->ejecutarConsulta($sql, '', [':id_ruta' => $id_ruta]);
            $orden = ($result['max_orden'] ?? 0) + 1;
        }
        
        $log = [
            ['campo_nombre' => 'id_ruta', 'campo_marcador' => ':id_ruta', 'campo_valor' => $id_ruta],
            ['campo_nombre' => 'id_direccion', 'campo_marcador' => ':id_direccion', 'campo_valor' => $id_direccion],
            ['campo_nombre' => 'orden_en_ruta', 'campo_marcador' => ':orden_en_ruta', 'campo_valor' => $orden],
            ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1]
        ];
        $this->guardarDatos('rutas_direcciones', $log);
        
        // Actualizar contrato
        $datos = [
            ['campo_nombre' => 'id_ruta', 'campo_marcador' => ':id_ruta', 'campo_valor' => $id_ruta]
        ];
        $condicion = [
            'condicion_campo' => 'id_direccion',
            'condicion_operador' => '=',
            'condicion_marcador' => ':id_direccion',
            'condicion_valor' => $id_direccion
        ];
        $this->actualizarDatos('contratos', $datos, $condicion);
    }

    private function direccionEnOtraRuta($id_direccion, $id_ruta_actual)
    {
        $sql = "SELECT id_ruta FROM rutas_direcciones 
                WHERE id_direccion = :id_direccion 
                AND activo = 1 
                AND id_ruta != :id_ruta_actual";
        $result = $this->ejecutarConsulta($sql, '', [
            ':id_direccion' => $id_direccion,
            ':id_ruta_actual' => $id_ruta_actual
        ]);
        return ($result !== false);
    }

    private function compactarOrdenDirecciones($id_ruta)
    {
        $sql = "SELECT id_ruta_direccion 
                FROM rutas_direcciones 
                WHERE id_ruta = :id_ruta AND activo = 1 
                ORDER BY orden_en_ruta, id_ruta_direccion";
        $direcciones = $this->ejecutarConsulta($sql, '', [':id_ruta' => $id_ruta], "fetchAll");
        
        $orden = 1;
        foreach ($direcciones as $dir) {
            $datos = [
                ['campo_nombre' => 'orden_en_ruta', 'campo_marcador' => ':orden', 'campo_valor' => $orden]
            ];
            $condicion = [
                'condicion_campo' => 'id_ruta_direccion',
                'condicion_operador' => '=',
                'condicion_marcador' => ':id',
                'condicion_valor' => $dir['id_ruta_direccion']
            ];
            $this->actualizarDatos('rutas_direcciones', $datos, $condicion);
            $orden++;
        }
    }    
}
