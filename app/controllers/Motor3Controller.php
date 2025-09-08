<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';
require_once APP_R_PROY . 'app/lib/Motor3PDFGenerator.php';

use app\lib\Motor3PDFGenerator;

use app\models\mainModel;

use \Exception;

class Motor3Controller extends mainModel{
	private $log_path;
	private $logFile;
	private $errorLogFile;

    	private $o_f;

	public function __construct()
	{ 
		// Nombre del controlador actual abreviado para reconocer el archivo
		$nom_controlador = "Motor3Controller";
		// ____________________________________________________________________

		$this->log_path = __DIR__ . '/../logs/Motor3/';

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
 
   
    private function validarDatosExcel($datos) {
        // Validar estructura básica
        if (!isset($datos['servicios']) || !is_array($datos['servicios'])) {
            return ['valido' => false, 'mensaje' => 'Datos de servicios inválidos'];
        }
        
        // Validar campos requeridos en cada servicio
        foreach ($datos['servicios'] as $servicio) {
            $camposRequeridos = ['id_servicio', 'cliente', 'direccion', 'crew'];
            foreach ($camposRequeridos as $campo) {
                if (!isset($servicio[$campo])) {
                    return ['valido' => false, 'mensaje' => "Campo {$campo} requerido en servicio"];
                }
            }
        }
        
        return ['valido' => true, 'mensaje' => 'Datos válidos'];
    }
    
    private function agruparPorViajes($servicios) {
        $viajes = [];

        // Agrupar servicios por Crew
        foreach ($servicios as $servicio) {
            $crew = $servicio['crew'];
            
            if (!isset($viajes[$crew])) {
                $viajes[$crew] = [
                    'crew' => $crew,
                    'vehiculo' => $servicio['vehiculo'] ?? 'No especificado',
                    'fecha' => date('Y-m-d'),
                    'servicios' => []
                ];
            }
            
            // Agregar servicio al viaje
            $viajes[$crew]['servicios'][] = $servicio;
        }
        
        // Ordenar servicios por orden (si existe) o mantener orden original
        foreach ($viajes as &$viaje) {
            usort($viaje['servicios'], function($a, $b) {
                return ($a['orden'] ?? 0) <=> ($b['orden'] ?? 0);
            });
        }
        
        return array_values($viajes);
    }
    
    private function generarDocumentosPDF($viajes) {
        $pdfGenerator = new Motor3PDFGenerator();
        
        $documentos = [];
        
        foreach ($viajes as $viaje) {
            // Generar contenido PDF
            $pdfContent = $pdfGenerator->generarPDFViaje($viaje);
            
            // Generar datos para QR
            $qrData = $this->generarDatosQR($viaje);
            
            $documentos[] = [
                'crew' => $viaje['crew'],
                'nombre_archivo' => 'viaje_' . strtolower(str_replace(' ', '_', $viaje['crew'])) . '_' . date('Ymd') . '.pdf',
                'contenido' => $pdfContent,
                'qr_data' => $qrData,
                'servicios_count' => count($viaje['servicios'])
            ];
        }
        
        return $documentos;
    }

    private function generarDatosQR($viaje) {
        $data = [
            'viaje_id' => $viaje['crew'] . '_' . date('Ymd'),
            'crew' => $viaje['crew'],
            'vehiculo' => $viaje['vehiculo'],
            'fecha' => $viaje['fecha'],
            'servicios' => array_map(function($s) {
                return [
                    'servicio_id' => $s['id_servicio'],
                    'cliente' => $s['cliente'],
                    'direccion' => $s['direccion']
                ];
            }, $viaje['servicios']),
            'timestamp' => date('Y-m-d H:i:s'),
            'tipo' => 'motor3_viaje_crew',
            'hash' => $this->generarHash($viaje)
        ];
        
        return base64_encode(json_encode($data));
    }
    
    private function prepararRespuestaPrincipal($datosExcel, $viajes) {
        return [
            'success' => true,
            'message' => 'Datos procesados correctamente',
            'data' => [
                'servicios_procesados' => count($datosExcel['servicios']),
                'viajes_generados' => count($viajes),
                'crews' => array_column($viajes, 'crew'),
                'fecha_procesamiento' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    private function prepararPaqueteDocumentos($documentos) {
        return [
            'tipo' => 'paquete_documentos_motor3',
            'timestamp' => date('Y-m-d H:i:s'),
            'documentos' => array_map(function($doc) {
                return [
                    'nombre' => $doc['nombre_archivo'],
                    'crew' => $doc['crew'],
                    'servicios_count' => $doc['servicios_count'],
                    'contenido_base64' => base64_encode($doc['contenido']),
                    'qr_data' => $doc['qr_data']
                ];
            }, $documentos)
        ];
    }
    
    public function generarPaqueteDocumentos($servicios) {
        try {
            // 1. Agrupar servicios por viajes de Crew
            $viajes = $this->agruparPorViajes($servicios['servicios_para_motor3']);
            
            // 2. Generar documentos PDF para cada viaje
            $documentos = $this->generarDocumentosPDF($viajes);
            
            // 3. Preparar paquete de documentos
            return $this->prepararPaqueteDocumentos($documentos);
            
        } catch (Exception $e) {
            error_log("Error en Motor3Generator: " . $e->getMessage());
            return null;
        }
    }
  
    private function generarHash($viaje) {
        $dataString = $viaje['crew'] . $viaje['fecha'] . count($viaje['servicios']) . 'greentrack_motor3_secret';
        return hash('sha256', $dataString);
    }

    private function generarHTMLViaje($viaje) {
        $serviciosHTML = '';
        foreach ($viaje['servicios'] as $index => $servicio) {
            $serviciosHTML .= "
            <tr>
                <td>" . ($index + 1) . "</td>
                <td>{$servicio['cliente']}</td>
                <td>{$servicio['direccion']}</td>
                <td>" . ($servicio['telefono'] ?? 'N/A') . "</td>
            </tr>";
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Viaje {$viaje['crew']} - " . date('Y-m-d') . "</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white; 
                    padding: 20px; 
                    text-align: center; 
                    border-radius: 10px;
                    margin-bottom: 20px;
                }
                .info-section { 
                    background: #f8f9fa; 
                    padding: 15px; 
                    border-radius: 5px; 
                    margin-bottom: 20px;
                    border-left: 4px solid #667eea;
                }
                .servicios-table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 20px 0;
                }
                .servicios-table th, .servicios-table td { 
                    border: 1px solid #ddd; 
                    padding: 12px; 
                    text-align: left; 
                }
                .servicios-table th { 
                    background-color: #e9ecef; 
                    font-weight: bold;
                }
                .qr-section { 
                    text-align: center; 
                    margin: 30px 0; 
                    padding: 20px;
                    background: #fff;
                    border: 2px dashed #667eea;
                    border-radius: 10px;
                }
                .instructions { 
                    background-color: #d4edda; 
                    padding: 15px; 
                    border-left: 4px solid #28a745;
                    border-radius: 5px;
                }
                .footer { 
                    text-align: center; 
                    margin-top: 30px; 
                    color: #666; 
                    font-size: 12px;
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>GREENTRACK - MOTOR 3</h1>
                <h2>VIAJE DE CREW</h2>
                <p><strong>{$viaje['crew']}</strong> - " . date('Y-m-d') . "</p>
            </div>
            
            <div class='info-section'>
                <h3>INFORMACIÓN DEL VIAJE</h3>
                <p><strong>Vehículo:</strong> {$viaje['vehiculo']}</p>
                <p><strong>Fecha:</strong> {$viaje['fecha']}</p>
                <p><strong>Total Servicios:</strong> " . count($viaje['servicios']) . "</p>
            </div>
            
            <div class='info-section'>
                <h3>SERVICIOS PROGRAMADOS</h3>
                <table class='servicios-table'>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Dirección</th>
                            <th>Teléfono</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$serviciosHTML}
                    </tbody>
                </table>
            </div>
            
            <div class='qr-section'>
                <h3>ESCANEAR PARA REGISTRO EN TIEMPO REAL</h3>
                <div style='margin: 20px 0; font-size: 12px; color: #666;'>
                    [QR_DATA: {$this->generarDatosQR($viaje)}]
                </div>
                <p style='font-size: 14px; color: #666;'>
                    Use la aplicación móvil de GreenTrack para escanear este código<br>
                    y registrar actividades durante el viaje
                </p>
            </div>
            
            <div class='instructions'>
                <h4>Instrucciones para el Crew:</h4>
                <ul>
                    <li>Escanea este QR al inicio de tu jornada</li>
                    <li>Registra cada llegada y salida de cliente</li>
                    <li>Reporta cualquier incidencia durante el viaje</li>
                    <li>El sistema sincronizará automáticamente con Motor 2</li>
                </ul>
            </div>
            
            <div class='footer'>
                <p>Documento generado automáticamente - GreenTrack System</p>
                <p>" . date('Y-m-d H:i:s') . "</p>
            </div>
        </body>
        </html>";
    }
}
?>