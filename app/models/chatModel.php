<?php
namespace app\models;

// Cargar configuración antes de mainModel
require_once __DIR__ . '/../../config/app.php';
require_once APP_R_PROY . 'app/models/mainModel.php';

class chatModel extends mainModel
{
    private $log_path;
    private $logFile;
    private $errorLogFile;

    public function __construct()
    {
        parent::__construct();
        
        $this->log_path = APP_R_PROY . 'app/logs/Chat/';
        
        if (!file_exists($this->log_path)) {
            mkdir($this->log_path, 0775, true);
        }

        $this->logFile = $this->log_path . 'chat_' . date('Y-m-d') . '.log';
        $this->errorLogFile = $this->log_path . 'chat_error_' . date('Y-m-d') . '.log';
    }

    /**
     * Guardar mensaje en la base de datos
     */
    public function guardarMensaje($datosMensaje)
    {
        try {
            $sala_id = $this->obtenerIdSala($datosMensaje['room']);
            $usuario_id = $datosMensaje['user']['id'];
            $tipo = $datosMensaje['messageType'] ?? 'texto';
            $mensaje = $datosMensaje['content'];
            $archivo_nombre = $datosMensaje['file_name'] ?? null;
            $archivo_url = $datosMensaje['file_url'] ?? null;
            $archivo_tamano = $datosMensaje['file_size'] ?? null;

            $datos = [
                ['campo_nombre' => 'sala_id', 'campo_marcador' => ':sala_id', 'campo_valor' => $sala_id],
                ['campo_nombre' => 'usuario_id', 'campo_marcador' => ':usuario_id', 'campo_valor' => $usuario_id],
                ['campo_nombre' => 'tipo', 'campo_marcador' => ':tipo', 'campo_valor' => $tipo],
                ['campo_nombre' => 'mensaje', 'campo_marcador' => ':mensaje', 'campo_valor' => $mensaje],
                ['campo_nombre' => 'archivo_nombre', 'campo_marcador' => ':archivo_nombre', 'campo_valor' => $archivo_nombre],
                ['campo_nombre' => 'archivo_url', 'campo_marcador' => ':archivo_url', 'campo_valor' => $archivo_url],
                ['campo_nombre' => 'archivo_tamano', 'campo_marcador' => ':archivo_tamano', 'campo_valor' => $archivo_tamano],
            ];
            
            $resultado = $this->guardarDatos('chat_mensajes', $datos);

            if ($resultado) {
                $mensajeId = $resultado;
                $this->log("✅ Mensaje guardado - ID: $mensajeId, Sala: {$datosMensaje['room']}, Usuario: {$datosMensaje['user']['email']}");
                return $mensajeId;
            } else {
                $this->log("❌ Error al guardar mensaje");
                return false;
            }

        } catch (\Exception $e) {
            $this->log("💥 Excepción al guardar mensaje: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener ID de sala por nombre
     */
    private function obtenerIdSala($nombreSala)
    {
        $sql = "SELECT id 
                    FROM chat_salas 
                    WHERE nombre = :nombre AND activo = 1";
        $params = [':nombre' => $nombreSala];
        
        $resultado = $this->ejecutarConsulta($sql, "", $params);
        
        if ($resultado && isset($resultado['id'])) {
            return $resultado['id'];
        } else {
            $this->log("❌ Sala no encontrada: $nombreSala");
            return 1; // Default a sala 'interno'
        }
    }

    /**
     * Obtener historial de mensajes de una sala
     */
    public function obtenerHistorialSala($nombreSala, $limite = 50)
    {
        try {
            $salaId = $this->obtenerIdSala($nombreSala);
            
            $sql = "SELECT cm.*, u.nombre as usuario_nombre, u.email as usuario_email
                        FROM chat_mensajes cm
                        LEFT JOIN usuarios_ejecutivos u ON cm.usuario_id = u.id
                        WHERE cm.sala_id = :sala_id
                        ORDER BY cm.fecha_envio DESC
                        LIMIT :limite";

            $params = [
                ':sala_id' => $salaId,
                ':limite' => (int)$limite
            ];

            $mensajes = $this->ejecutarConsulta($sql, "", $params, "fetchAll");
            
            // Ordenar del más antiguo al más nuevo para el frontend
            return array_reverse($mensajes);

        } catch (\Exception $e) {
            $this->log("💥 Error obteniendo historial: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Registrar llamada en la base de datos
     */
    public function registrarLlamada($datosLlamada)
    {
        try {
            $sala_id = $this->obtenerIdSala($datosLlamada['room']);
            $llamada_id = $datosLlamada['callId'];
            $iniciado_por = $datosLlamada['from']['id'];
            $tipo = $datosLlamada['callType'];
            $participantes = json_encode([$datosLlamada['from']['email']]);
            $estado = 'activa';

            $datos = [
                ['campo_nombre' => 'sala_id', 'campo_marcador' => ':sala_id', 'campo_valor' => $sala_id],
                ['campo_nombre' => 'llamada_id', 'campo_marcador' => ':llamada_id', 'campo_valor' => $llamada_id],
                ['campo_nombre' => 'iniciado_por', 'campo_marcador' => ':iniciado_por', 'campo_valor' => $iniciado_por],
                ['campo_nombre' => 'tipo', 'campo_marcador' => ':tipo', 'campo_valor' => $tipo],
                ['campo_nombre' => 'participantes', 'campo_marcador' => ':participantes', 'campo_valor' => $participantes],
                ['campo_nombre' => 'estado', 'campo_marcador' => ':estado', 'campo_valor' => $estado]
            ];
            
            $resultado = $this->guardarDatos('chat_llamadas', $datos);
           
            if ($resultado) {
                $this->log("✅ Llamada registrada - ID: {$datosLlamada['callId']}");
                return true;
            }

        } catch (\Exception $e) {
            $this->log("💥 Error registrando llamada: " . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Finalizar llamada
     */
    public function finalizarLlamada($callId, $duracion)
    {
        try {
            $estado = 'finalizada';
            $fecha_fin = date('Y-m-d H:i:s');

            $datos = [
                ['campo_nombre' => 'estado', 'campo_marcador' => ':estado', 'campo_valor' => $estado],
                ['campo_nombre' => 'fecha_fin', 'campo_marcador' => ':fecha_fin', 'campo_valor' => $fecha_fin],
                ['campo_nombre' => 'duracion', 'campo_marcador' => ':duracion', 'campo_valor' => $duracion]
            ];
            
            $condicion = [
                ['condicion_campo' => 'llamada_id', 'condicion_operador' => '=', 'condicion_marcador' => ':llamada_id', 'condicion_valor' => $callId],
                ['condicion_campo' => 'estado', 'condicion_operador' => '=', 'condicion_marcador' => ':estado', 'condicion_valor' => 'activa']
            ];

            $cant_reg = $this->actualizarDatos('chat_llamadas', $datos, $condicion);

            return $cant_reg;

        } catch (\Exception $e) {
            $this->log("💥 Error finalizando llamada: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Logging
     */
    private function log($mensaje)
    {
        $logEntry = "[" . date('Y-m-d H:i:s') . "] " . $mensaje . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>