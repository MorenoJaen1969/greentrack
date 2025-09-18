<?php
namespace app\controllers;
use app\models\mainModel;

class VerizonImportController extends mainModel
{
    private $log_path;
    private $logFile;
    private $errorLogFile;
    
    private $tokenUrl;
    private $geofencesUrl;

    public function __construct()
    {
        // ¡ESTA LÍNEA ES CRUCIAL!
        parent::__construct();

        // Nombre del controlador actual abreviado para reconocer el archivo
        $nom_controlador = "VerizonImportController";
        // ____________________________________________________________________

        // Inicializar sistema de logs independiente
		$this->log_path = __DIR__ . '/../logs/controlador/';
        
        if (!file_exists($this->log_path)) {
            mkdir($this->log_path, 0775, true);
            chgrp($this->log_path, 'www-data');
            chmod($this->log_path, 0775);
        }

        $this->logFile = $this->log_path . $nom_controlador . '_' . date('Y-m-d') . '.log';
        $this->errorLogFile = $this->log_path . $nom_controlador . '_error_' . date('Y-m-d') . '.log';

        $this->initializeLogFile($this->logFile);
        $this->initializeLogFile($this->errorLogFile);
        $this->rotarLogs(15);

        // URLs de API
        $this->tokenUrl = 'https://fim.api.us.fleetmatics.com/token';
        $this->geofencesUrl = 'https://fim.api.us.fleetmatics.com/geofence/v1/geofences';

        $this->log("VerizonImportController inicializado");
    }

    private function initializeLogFile($file)
    {
        if (!file_exists($file)) {
            $initialContent = "[" . date('Y-m-d H:i:s') . "] Archivo de log iniciado\n";
            $created = file_put_contents($file, $initialContent, FILE_APPEND | LOCK_EX);
            if ($created === false) {
                error_log("No se pudo crear el archivo de log: " . $file);
            } else {
                chmod($file, 0644);
            }
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
            $this->initializeLogFile($file);
        }
        $logEntry = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
        file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function logWithBacktrace($message)
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1] ?? $backtrace[0];
        $logMessage = sprintf(
            "[%s] %s - Called from %s::%s (Line %d)\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            $message,
            $caller['class'] ?? '',
            $caller['function'],
            $caller['line'],
            json_encode($backtrace, JSON_PRETTY_PRINT)
        );
        file_put_contents($this->errorLogFile, $logMessage . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Importa geocercas desde Verizon Connect
     */
    public function importarGeocercas($usuario = 'mmoreno', $password = 'alele4')
    {
        $this->log("Iniciando importación de geocercas");

        // === 1. Obtener token ===
        $token = $this->obtenerToken($usuario, $password);
        if (!$token) {
            $this->log("Fallo en autenticación", true);
            return ['insertados' => 0, 'errores' => ['No se pudo obtener el token']];
        }

        // === 2. Obtener geocercas ===
        $geocercas = $this->obtenerGeocercas($token);
        if (!$geocercas) {
            return ['insertados' => 0, 'errores' => ['No se pudieron obtener geocercas']];
        }

        $colores = [
            '1' => '#4FC3F7', '2' => '#8BC34A', '3' => '#FF9800',
            '4' => '#E91E63', '5' => '#9C27B0', '6' => '#FF5722',
            '7' => '#795548', '8' => '#3F51B5', '9' => '#009688',
            '10' => '#CDDC39', '11' => '#607D8B', '12' => '#FFC107',
            '' => '#9E9E9E'
        ];

        $insertados = 0;
        $errores = [];

        foreach ($geocercas as $g) {
            try {
                $nombre = $g['name'] ?? '';
                if (strpos($nombre, 'Cliente:') === false) continue;

                $cliente = trim(str_replace('Cliente: ', '', $nombre));
                $direccion = $g['address'] ?? 'Dirección no disponible';
                $lat = (float)($g['center']['latitude'] ?? 0);
                $lng = (float)($g['center']['longitude'] ?? 0);
                $geofence_id = $g['id'] ?? uniqid('gf_');
                $truck = $this->extraerTruck($nombre);

                // Verificar si ya existe
                $existe = $this->ejecutarConsulta(
                    "SELECT id FROM servicios WHERE geofence_id = :geofence_id",
                    'servicios',
                    [':geofence_id' => $geofence_id],
                    'fetchColumn'
                );

                if ($existe) {
                    $this->log("Geocerca duplicada, omitiendo: {$cliente}");
                    continue;
                }

                $datos_servicio = [
                    ['campo_nombre' => 'cliente', 'campo_marcador' => ':cliente', 'campo_valor' => $cliente],
                    ['campo_nombre' => 'direccion', 'campo_marcador' => ':direccion', 'campo_valor' => $direccion],
                    ['campo_nombre' => 'truck', 'campo_marcador' => ':truck', 'campo_valor' => $truck],
                    ['campo_nombre' => 'crew_color', 'campo_marcador' => ':crew_color', 'campo_valor' => $colores[$truck] ?? '#9e9e9e'],
                    ['campo_nombre' => 'lat', 'campo_marcador' => ':lat', 'campo_valor' => $lat],
                    ['campo_nombre' => 'lng', 'campo_marcador' => ':lng', 'campo_valor' => $lng],
                    ['campo_nombre' => 'geofence_id', 'campo_marcador' => ':geofence_id', 'campo_valor' => $geofence_id],
                    ['campo_nombre' => 'finalizado', 'campo_marcador' => ':finalizado', 'campo_valor' => 0]
                ];

                $id = $this->guardarDatos('servicios', $datos_servicio);
                if ($id) {
                    $insertados++;
                    $this->log("Insertado: {$cliente} (ID: {$id})");
                }
            } catch (Exception $e) {
                $errores[] = "Error con geocerca {$g['id']}: " . $e->getMessage();
                $this->logWithBacktrace("Error en geocerca {$g['id']}: " . $e->getMessage());
            }
        }

        return ['insertados' => $insertados, 'errores' => $errores];
    }

    private function obtenerGeocercas($token)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->geofencesUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->log("Geocercas error $httpCode: $response", true);
            return false;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $this->log("Formato de respuesta inválido", true);
            return false;
        }

        return $data;
    }

    private function extraerTruck($nombre)
    {
        if (preg_match('/Crew (\d+)/i', $nombre, $matches)) return $matches[1];
        if (preg_match('/Truck (\d+)/i', $nombre, $matches)) return $matches[1];
        return '';
    }

    private function obtenerToken($usuario, $password)
    {
        $credentials = base64_encode($usuario . ':' . $password);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->tokenUrl,
            CURLOPT_HTTPGET => true, // ← Usa GET
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credentials,
                'Accept: text/plain' // ← Importante: no application/json
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->log("Token error $httpCode: $response", true);
            return false;
        }

        // El token viene en texto plano, no en JSON
        $token = trim($response);
        if (empty($token)) {
            $this->log("Token vacío en respuesta: [$response]", true);
            return false;
        }

        $this->log("Token obtenido exitosamente (longitud: " . strlen($token) . ")");
        return $token;
    }    
}
?>