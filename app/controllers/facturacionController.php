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

class facturacionController extends mainModel
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
        $nom_controlador = "facturacionController";
        // ____________________________________________________________________

        $this->log_path = APP_R_PROY . 'app/logs/facturacion/';

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

    public function generarFacturasPendientes($id_contrato): bool
    {
        // Obtener contratos activos con periodos de facturación
        $sql = "SELECT 
            c.id_contrato,
            c.costo,
            c.fecha_ini,
            c.fecha_fin,
            c.id_frecuencia_pago,
            c.fecha_cancelacion,
            COALESCE(
                (SELECT MAX(f2.periodo_fin) FROM facturas f2 WHERE f2.id_contrato = c.id_contrato),
                c.fecha_ini
            ) AS ultima_factura_hasta
        FROM contratos c
        WHERE c.id_contrato = :v_id_contrato";
        // WHERE c.id_contrato = :v_id_contrato AND c.id_status = 18";

        $params = [
            ":v_id_contrato" => $id_contrato
        ];
        $contratos = $this->ejecutarConsulta($sql, "", $params);

        $this->procesarFacturacionContrato($contratos);
        return true;
    }

    private function procesarFacturacionContrato(array $contrato): bool
    {
        $hoy = date('Y-m-d');
        $ultimaFecha = $contrato['ultima_factura_hasta']; // Última fecha de corte ya facturada
        $tipo = $contrato['id_frecuencia_pago'];
        $precio = $contrato['costo'] ?? 0;
        $cal_fact = 2; // Cálculo al final del mes

        // Fecha base para el próximo periodo: día siguiente de la última factura
        $fechaBase = date('Y-m-d', strtotime($ultimaFecha . ' +1 day'));

        // Ciclo para generar todas las facturas pendientes
        while (true) {
            // Calcular la fecha de corte para el periodo actual
            $fechaCorte = $this->calcularProximaFechaCorte($fechaBase, $tipo, $cal_fact);

            // Si la fecha de corte es mayor a hoy, detenerse
            if ($fechaCorte > $hoy) {
                break;
            }

            // Validar que el periodo esté dentro de la vigencia del contrato
            if ($fechaCorte > $contrato['fecha_fin']) {
                $fechaCorte = $contrato['fecha_fin'];
            }

            // Verificar que el periodo sea válido
            if ($fechaBase <= $fechaCorte) {
                $this->crearFactura($contrato, $fechaBase, $fechaCorte, $precio);
                
                // Actualizar la fecha base para el próximo ciclo
                $fechaBase = date('Y-m-d', strtotime($fechaCorte . ' +1 day'));
                
                // Si ya alcanzamos la fecha fin del contrato, detenerse
                if ($fechaCorte >= $contrato['fecha_fin']) {
                    break;
                }
            } else {
                // Periodo inválido, salir
                break;
            }
        }

        return true;
    }

    private function calcularProximaFechaCorte(string $fechaBase, int $tipo, $cal_fact = 2): string
    {
        if ($cal_fact == 1) {
            switch ($tipo) {
                case 1:
                    // Mensual
                    return date('Y-m-d', strtotime("$fechaBase +1 month"));
                case 2:
                    // Semestral
                    return date('Y-m-d', strtotime("$fechaBase +6 months"));
                case 3:
                    // trimestral
                    return date('Y-m-d', strtotime("$fechaBase +3 months"));
                case 4:
                    // Anual
                    return date('Y-m-d', strtotime("$fechaBase +1 year"));
                case 5:
                    // Para facturación por servicio, se genera al completar cada servicio
                    return $fechaBase; // Se maneja en otro proceso
                default:
                    return date('Y-m-d', strtotime("$fechaBase +1 month"));
            }
        } else {
            switch ($tipo) {
                case 1: // Mensual
                    // Último día del mes de la fecha base
                    return date('Y-m-t', strtotime($fechaBase));

                case 2: // Semestral
                    $mes = (int)date('m', strtotime($fechaBase));
                    $anio = (int)date('Y', strtotime($fechaBase));
                    return ($mes <= 6) ? "$anio-06-30" : "$anio-12-31";

                case 3: // Trimestral
                    $mes = (int)date('m', strtotime($fechaBase));
                    $anio = (int)date('Y', strtotime($fechaBase));
                    
                    if ($mes <= 3) {
                        return "$anio-03-31";
                    } elseif ($mes <= 6) {
                        return "$anio-06-30";
                    } elseif ($mes <= 9) {
                        return "$anio-09-30";
                    } else {
                        return "$anio-12-31";
                    }

                case 4: // Anual
                    $anio = (int)date('Y', strtotime($fechaBase));
                    return "$anio-12-31";

                case 5: // Por servicio
                    return $fechaBase;

                default: // Mensual por defecto
                    return date('Y-m-t', strtotime($fechaBase));
            }
        }
    }

    private function crearFactura(array $contrato, string $inicio, string $fin, float $precio)
    {
        // Para facturación por periodo (no por servicio)
        if ($contrato['id_frecuencia_pago'] !== 5) {
            $monto = $precio ?? 0;

            $mes_inicio = (new DateTime($inicio))->format('m');

            $concepto = "Invoicing for the month of " . $this->obtenerMes($mes_inicio) .". ". $contrato['id_contrato'];
            $concepto = $concepto . " - {$inicio} to {$fin}";

            $sql = "SELECT COUNT(*) AS total FROM facturas WHERE concepto = :v_concepto";
            $params = [
                ':v_concepto' => $concepto
            ];
            $total = $this->ejecutarConsulta($sql, "", $params, "fetchColumn");
            if ($total == 0) {
                try {
                    // Evaluar si existen servicios en el Periodo para poder generar Factura
                    $status_validos = [
                        [
                            "id_status" => 37,
                            "status" => "Activo"
                        ],
                        [
                            "id_status" => 38,
                            "status" => "Procesado"
                        ]
                    ];      
                    // Extraer solo los ID de status válidos
                    $status_ids = array_column($status_validos, 'id_status'); // [37, 38, 39]

                    // Generar placeholders con nombre único: :status_0, :status_1, etc.
                    $placeholders = [];
                    $params_status = [];
                    foreach ($status_ids as $index => $id) {
                        $key = ":status_{$index}";
                        $placeholders[] = $key;
                        $params_status[$key] = $id;
                    }

                    // Unir los placeholders en una lista para el IN
                    $in_clause = implode(',', $placeholders);

                    // Consulta SQL
                    $sql = "SELECT COUNT(*) AS total01
                            FROM servicios s
                            JOIN contratos c ON s.id_contrato = c.id_contrato
                            WHERE c.id_contrato = :v_id_contrato
                                AND s.fecha_programada BETWEEN :v_fecha1 AND :v_fecha2
                                AND s.id_status IN ($in_clause)";

                    // Combinar todos los parámetros
                    $params = array_merge(
                        [
                            ':v_id_contrato' => $contrato['id_contrato'],
                            ':v_fecha1' => $inicio,
                            ':v_fecha2' => $fin
                        ],
                        $params_status
                    );

                    $total01 = $this->ejecutarConsulta($sql, "", $params, "fetchColumn");
                } catch (Exception $e) {
                    $this->logWithBacktrace("Error consultando servicios : " . $e->getMessage());
                    $total01 = 0;
                }

                if ($total01 > 0) {
                    $idFactura = $this->guardarFactura([
                        'id_contrato' => $contrato['id_contrato'],
                        'concepto' => $concepto,
                        'periodo_inicio' => $inicio,
                        'periodo_fin' => $fin,
                        'id_frecuencia_pago' => $contrato['id_frecuencia_pago'],
                        'monto_total' => $monto,
                        'fecha_vencimiento' => date('Y-m-d', strtotime('+15 days'))
                    ]);

                    $monto_detalle = $this->guardarDetalleFactura([
                        'id_factura' => $idFactura,
                        'id_contrato' => $contrato['id_contrato'],
                        'periodo_inicio' => $inicio,
                        'periodo_fin' => $fin,
                        'monto_total' => $monto
                    ]);

                    if ($monto_detalle > 0) {
                        $datos = [
                            ['campo_nombre' => 'monto_total', 'campo_marcador' => ':monto_total', 'campo_valor' => $monto_detalle]
                        ];
                        $condicion = [
                            'condicion_campo' => 'id_factura',
                            'condicion_operador' => '=', 
                            'condicion_marcador' => ':id_factura',
                            'condicion_valor' => $idFactura
                        ];

                        $resulta = $this->actualizarDatos("facturas", $datos, $condicion);
                    }
                }
            }
            return true;
        }
    }

    // En tu función que marca un servicio como completado
    public function marcarServicioCompletado(int $id_servicio): void
    {
        // Verificar si el contrato es de facturación por servicio
        $sql = "SELECT c.id_contrato, c.precio_servicio 
                    FROM servicios s
                    JOIN contratos c ON s.id_contrato = c.id_contrato
                    WHERE s.id_servicio = :id_servicio 
                        AND c.id_status != 39
                        AND c.tipo_facturacion = 'por_servicio'";

        $contrato = $this->ejecutarConsulta($sql, "", [':id_servicio' => $id_servicio]);

        if ($contrato) {
            $this->crearFacturaPorServicio($contrato['id_contrato'], $id_servicio, $contrato['precio_servicio']);
        }
    }

    private function crearFacturaPorServicio(int $id_contrato, int $id_servicio, float $precio): void
    {
        $idFactura = $this->guardarFactura([
            'id_contrato' => $id_contrato,
            'periodo_inicio' => date('Y-m-d'),
            'periodo_fin' => date('Y-m-d'),
            'id_frecuencia_pago' => 'por_servicio',
            'monto_total' => $precio,
            'fecha_vencimiento' => date('Y-m-d', strtotime('+15 days'))
        ]);

        $this->guardarDetalleFactura([
            'id_factura' => $idFactura,
            'id_servicio' => $id_servicio,
            'concepto' => 'Servicio individual',
            'cantidad' => 1,
            'precio_unitario' => $precio,
            'subtotal' => $precio
        ]);
    }

    private function guardarFactura(array $datos): int
    {
        $id_factura = 0;
        $datos = [
            ['campo_nombre' => 'id_contrato', 'campo_marcador' => ':id_contrato', 'campo_valor' => $datos['id_contrato']],
            ['campo_nombre' => 'concepto', 'campo_marcador' => ':concepto', 'campo_valor' => $datos['concepto']],
            ['campo_nombre' => 'periodo_inicio', 'campo_marcador' => ':periodo_inicio', 'campo_valor' => $datos['periodo_inicio']],
            ['campo_nombre' => 'periodo_fin', 'campo_marcador' => ':periodo_fin', 'campo_valor' => $datos['periodo_fin']],
            ['campo_nombre' => 'id_frecuencia_pago', 'campo_marcador' => ':id_frecuencia_pago', 'campo_valor' => $datos['id_frecuencia_pago']],
            ['campo_nombre' => 'monto_total', 'campo_marcador' => ':monto_total', 'campo_valor' => $datos['monto_total']],
            ['campo_nombre' => 'fecha_vencimiento', 'campo_marcador' => ':fecha_vencimiento', 'campo_valor' => $datos['fecha_vencimiento']]
        ];
        try {
            $id_factura = $this->guardarDatos('facturas', $datos);
        } catch (Exception $e) {
            $this->logWithBacktrace("Error creando factura: " . $e->getMessage());
        }

        return $id_factura;
    }

    private function guardarDetalleFactura(array $datos)
    {
        $id_factura = $datos['id_factura'];
        $id_contrato = $datos['id_contrato'];
        $periodo_inicio = $datos['periodo_inicio'];
        $periodo_fin = $datos['periodo_fin'];
        $monto_total = $datos['monto_total'];


        // Evaluar si existen servicios en el Periodo para poder generar Factura
        $status_validos = [
            [
                "id_status" => 37,
                "status" => "Activo"
            ],
            [
                "id_status" => 38,
                "status" => "Procesado"
            ]
        ];      
        // Extraer solo los ID de status válidos
        $status_ids = array_column($status_validos, 'id_status'); // [37, 38, 39]

        // Generar placeholders con nombre único: :status_0, :status_1, etc.
        $placeholders = [];
        $params_status = [];
        foreach ($status_ids as $index => $id) {
            $key = ":status_{$index}";
            $placeholders[] = $key;
            $params_status[$key] = $id;
        }

        // Unir los placeholders en una lista para el IN
        $in_clause = implode(',', $placeholders);

        $sql = "SELECT s.id_servicio, s.fecha_programada, c.costo 
                    FROM servicios s
                    JOIN contratos c ON s.id_contrato = c.id_contrato
                    WHERE c.id_contrato = :v_id_contrato
                        AND s.fecha_programada >= :v_fecha1
                        AND s.fecha_programada <= :v_fecha2
                        AND s.id_status IN ($in_clause)
                    ORDER BY s.id_servicio";
        $params = array_merge(
            [
                ':v_id_contrato' => $id_contrato,
                ':v_fecha1' => $periodo_inicio,
                ':v_fecha2' => $periodo_fin
            ],
            $params_status
        );

        $servicios = $this->ejecutarConsulta($sql, "", $params, "fetchAll");
        $total_factura = 0;

        foreach ($servicios as $servicio) {
            $concepto = "Service " . $servicio['id_servicio'] . " processed on " . $servicio['fecha_programada'];
            $datos = [
                ['campo_nombre' => 'id_factura', 'campo_marcador' => ':id_factura', 'campo_valor' => $id_factura],
                ['campo_nombre' => 'id_servicio', 'campo_marcador' => ':id_servicio', 'campo_valor' => $servicio["id_servicio"]],
                ['campo_nombre' => 'concepto', 'campo_marcador' => ':concepto', 'campo_valor' => $concepto],
                ['campo_nombre' => 'cantidad', 'campo_marcador' => ':cantidad', 'campo_valor' => 1],
                ['campo_nombre' => 'precio_unitario', 'campo_marcador' => ':precio_unitario', 'campo_valor' => $monto_total],
                ['campo_nombre' => 'subtotal', 'campo_marcador' => ':subtotal', 'campo_valor' => $monto_total]
            ];
            $this->guardarDatos('detalles_factura', $datos);
            $total_factura = $total_factura + $monto_total;
        }
        return $total_factura;
    }

    public function cargar_distribucion($id_contrato)
	{
		$sql = "SELECT id_factura, id_contrato, periodo_inicio, periodo_fin, id_frecuencia_pago, monto_total,
                estado, fecha_generacion, fecha_vencimiento, concepto
            FROM facturas
            WHERE id_contrato = :v_id_contrato";
		$params = [
			':v_id_contrato' => $id_contrato
		];

        $registro = $this->ejecutarConsulta($sql, '', $params, "fetchAll");

        $facturas = [];

        foreach ($registro as $factura) {
            $id_factura = $factura["id_factura"];

            // Crear la factura
            $facturaCompleta = [
                'id_factura' => $factura["id_factura"],
                'id_contrato' => $factura["id_contrato"],
                'periodo_inicio' => $factura["periodo_inicio"],
                'periodo_fin' => $factura["periodo_fin"],
                'id_frecuencia_pago' => $factura["id_frecuencia_pago"],
                'monto_total' => $factura["monto_total"],
                'estado' => $factura["estado"],
                'fecha_vencimiento' => $factura["fecha_vencimiento"],
                'concepto' => $factura["concepto"],
                'detalle_f' => []
            ];

            // Consultar y asignar detalles
            $sql = "SELECT id_detalle, id_factura, id_servicio, concepto, cantidad,
                            precio_unitario, subtotal
                    FROM detalles_factura
                    WHERE id_factura = :v_id_factura
                    ORDER BY id_servicio";
            $params = [':v_id_factura' => $id_factura];
            $detalles = $this->ejecutarConsulta($sql, '', $params, "fetchAll");
            
            $facturaCompleta['detalle_f'] = $detalles;

            // Añadir al array principal
            $facturas[] = $facturaCompleta;
        }

		http_response_code(200);
		echo json_encode([
			'status' => 'ok',
			'facturas' => $facturas
		]);
		exit;
    }
}