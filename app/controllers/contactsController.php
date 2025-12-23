<?php
namespace app\controllers;
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;

use \Exception;
class contactsController extends mainModel
{
    private $log_path;
    private $logFile;
    private $errorLogFile;

    private $o_f;

    public function __construct()
    {
        // ¡ESTA LÍNEA ES CRUCIAL!
        parent::__construct();

        // Nombre del controlador actual abreviado para reconocer el archivo
        $nom_controlador = "contactsController";
        // ____________________________________________________________________

        $this->log_path = APP_R_PROY . 'app/logs/contacts/';

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

    public function getContacts()
    {
        try {
            // Obtener el token del header Authorization o query string
            $token = $this->getAuthToken();

            if (!$token) {
                http_response_code(401);
                echo json_encode(['error' => 'Token no proporcionado']);
                return;
            }

            // Validar token y obtener usuario
            $user = $this->validateToken($token);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Token inválido o expirado']);
                return;
            }

            $userId = $user['id'];

            // Obtener datos combinados
            $contactsData = [
                'id_user' => $userId,
                'user' => $this->getUserData($userId),
                'contacts' => $this->getAvailableContacts($userId),
                'rooms' => $this->getUserRooms($userId),
                'direct_messages' => $this->getDirectMessageContacts($userId)
            ];

            echo json_encode([
                'success' => true,
                'data' => $contactsData
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
        }
    }

    public function getAuthToken()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['token']) && !empty($input['token'])) {
            return $input['token'];
        }
        
        // Método 1: Buscar en $_SERVER (más confiable)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        
        // Método 2: Fallback a getallheaders() (por compatibilidad)
        if (!$authHeader && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? null;
        }

        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Buscar en query string como último recurso
        return $_GET['token'] ?? null;
    }

    public function validateToken($token)
    {
        $stmt = "
            SELECT id, nombre, email, chat_activo, chat_avatar, chat_estado 
                FROM usuarios_ejecutivos 
                WHERE token = :v_token AND activo = 1 AND chat_activo = 1
        ";
        
        $param = [
            ':v_token' => $token
        ];

        $this->log("Consulta actual: " . $stmt);
        $datos = $this->ejecutarConsulta($stmt, "", $param);
        $this->log("Resultado de la consulta: " . json_encode($datos));
        if (is_array($datos) && count($datos) > 0) {
            return $datos;
        }
        return false; // o null, dependiendo de lo que esperes
    }

    public function getUserData($userId)
    {
        $stmt = "SELECT id, nombre, email, chat_avatar as avatar, chat_estado as status, 'user' as type
                FROM usuarios_ejecutivos WHERE id = :v_userId AND activo = 1";
        $param = [':v_userId' => $userId];
        $datos = $this->ejecutarConsulta($stmt, "", $param, "fetchAll");
        return $datos;
    }

    public function self_status()
    {
        $token = $this->getAuthToken();
        if (!$token) {
            echo json_encode(['success' => false, 'error' => 'Token no proporcionado']);
                exit();
        }
        $user = $this->validateToken($token);
        if (!$user || !isset($user['id'])) {
            echo json_encode(['success' => false, 'error' => 'Token inválido']);
            exit();
        }
        $userId = $user['id'];
        $selfData = $this->getUserData($userId);
        return $selfData;
    }

    private function getAvailableContacts($userId)
    {
        $this->log("Proceso getAvailableContacts ");
        // Paso 1: Obtener todos los usuarios ejecutivos activos (excepto el usuario actual)
        $stmt = "
            SELECT id, nombre, email, chat_avatar as avatar, chat_estado as status, 'contact' as type,
                CASE 
                    WHEN chat_estado = 'online' THEN 1
                    WHEN chat_estado = 'offline' THEN 2
                    ELSE 3 
                END as status_order
            FROM usuarios_ejecutivos
            WHERE id != :v_userId AND activo = 1 AND chat_activo = 1
            ORDER BY status_order, nombre";
        $param = [
            ':v_userId' => $userId
        ];
        $contacts = $this->ejecutarConsulta($stmt, "", $param, "fetchAll");
        $this->log("Resultado en la variable contacts: " . json_encode($contacts));

        // Paso 2: obtener conteo de no leídos por contacto
        $stmt2 = "
            SELECT 
                (CASE 
                    WHEN m.usuario_id = :user_id1 THEN s_other.usuario_id
                    ELSE m.usuario_id
                END) as contact_id,
                COUNT(*) as unread
            FROM chat_mensajes m
            INNER JOIN chat_usuarios_salas s_me ON m.sala_id = s_me.sala_id AND s_me.usuario_id = :user_id2
            INNER JOIN chat_usuarios_salas s_other ON m.sala_id = s_other.sala_id AND s_other.usuario_id != :user_id3
            WHERE 
                m.leido = 0 
                AND m.usuario_id != :user_id4
            GROUP BY contact_id";
        $param2 = [
            ':user_id1' => $userId,
            ':user_id2' => $userId,
            ':user_id3' => $userId,
            ':user_id4' => $userId
        ];
        $unreadList = $this->ejecutarConsulta($stmt2, "", $param2, "fetchAll");
        $this->log("Resultado en la variable unreadList: " . json_encode($unreadList));

        // Paso 3: mapear conteo a contactos
        $unreadMap = [];
        foreach ($unreadList as $row) {
            $unreadMap[$row['contact_id']] = (int)$row['unread'];
        }

        foreach ($contacts as &$contact) {
            $contact['unread'] = $unreadMap[$contact['id']] ?? 0;
        }

        $this->log("Resultado del arreglo final: " . json_encode($contacts));
        return $contacts;
    }

    private function getUserRooms($userId)
    {
        // Obtener salas a las que pertenece el usuario
        $stmt = "
            SELECT 
                cs.id, cs.nombre, cs.descripcion, cs.creado_por, ue.nombre as creador_nombre, 
                ue.email as creador_email, cs.fecha_creacion, cs.max_usuarios, cs.activo as sala_activa, 
                cus.rol, cus.fecha_union, cus.ultima_conexion, 'room' as type,
                COUNT(cus2.usuario_id) as total_miembros
            FROM chat_usuarios_salas cus
            INNER JOIN chat_salas cs ON cus.sala_id = cs.id
            LEFT JOIN usuarios_ejecutivos ue ON cs.creado_por = ue.id
            LEFT JOIN chat_usuarios_salas cus2 ON cs.id = cus2.sala_id
            WHERE cus.usuario_id = :v_userId AND cs.activo = 1
            GROUP BY cs.id, cus.rol, cus.fecha_union, cus.ultima_conexion, ue.nombre, ue.email
            ORDER BY cs.nombre
        ";
        $param = [
            ':v_userId' => $userId
        ];


        $this->log("Consulta actual: " . $stmt);
        $datos = $this->ejecutarConsulta($stmt, "", $param, "fetchAll");
        $this->log("Resultado de la consulta: " . json_encode($datos));
        return $datos;
    }

    private function getDirectMessageContacts($userId)
    {
        // Obtener contactos con los que ha tenido conversaciones directas
        $stmt = "
            SELECT DISTINCT
                ue.id, ue.nombre, ue.email, ue.chat_avatar as avatar, ue.chat_estado as status,
                'direct' as type, MAX(cm.fecha_envio) as ultimo_mensaje_fecha
            FROM chat_usuarios_salas cus_usuario_actual
            INNER JOIN chat_usuarios_salas cus_contacto ON cus_usuario_actual.sala_id = cus_contacto.sala_id
            INNER JOIN usuarios_ejecutivos ue ON cus_contacto.usuario_id = ue.id
            LEFT JOIN chat_mensajes cm ON cm.sala_id = cus_usuario_actual.sala_id
            WHERE cus_usuario_actual.usuario_id = :v_userId1
                AND cus_contacto.usuario_id != :v_userId2
                AND ue.activo = 1
            GROUP BY ue.id, ue.nombre, ue.email, ue.chat_avatar, ue.chat_estado
            ORDER BY ultimo_mensaje_fecha DESC
            LIMIT 20
        ";

        $param = [
            ':v_userId1' => $userId,
            ':v_userId2' => $userId
        ];

        $this->log("Consulta actual: " . $stmt);
        $datos = $this->ejecutarConsulta($stmt, "", $param, "fetchAll");
        $this->log("Resultado de la consulta: " . json_encode($datos));
        return $datos;
    }

    private function getChatHistory($userIdActual, $contactId) {
        $stmt = "
            SELECT 
                cm.id, cm.usuario_id as remitente_id, ue.nombre as remitente_nombre, ue.email as remitente_email,
                ue.chat_avatar as remitente_avatar, cm.mensaje as content, cm.fecha_envio as timestamp,
                cs.id as sala_id, cm.leido
            FROM chat_mensajes cm
            INNER JOIN chat_usuarios_salas cus1 ON cm.sala_id = cus1.sala_id
            INNER JOIN chat_usuarios_salas cus2 ON cm.sala_id = cus2.sala_id
            INNER JOIN usuarios_ejecutivos ue ON cm.usuario_id = ue.id
            INNER JOIN chat_salas cs ON cm.sala_id = cs.id
            WHERE 
                cus1.usuario_id = :v_userIdActual1 AND
                cus2.usuario_id = :v_contactId1 AND
                (cm.usuario_id = :v_userIdActual2 OR cm.usuario_id = :v_contactId2) AND
                cs.activo = 1 AND
                ue.activo = 1
            ORDER BY cm.fecha_envio ASC
            LIMIT 100
        ";

        $param = [
            ':v_userIdActual1' => $userIdActual,
            ':v_userIdActual2' => $userIdActual,
            ':v_contactId1' => $contactId,
            ':v_contactId2' => $contactId
        ];

        $this->log("Consulta historial: " . $stmt);
        $datos = $this->ejecutarConsulta($stmt, "", $param, "fetchAll");
        $this->log("Resultado historial: " . json_encode($datos));
        if (is_array($datos) && count($datos) > 0) {
            return $datos;
        }
        return false; // o null, dependiendo de lo que esperes
    }

    // Método público para ser llamado desde contactsAjax.php
    public function getChatHistoryPublic($token, $contactId) {
        try {
            if (!$token) {
                http_response_code(401);
                echo json_encode(['error' => 'Token no proporcionado']);
                return;
            }

            $user = $this->validateToken($token);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Token inválido o expirado']);
                return;
            }

            $userIdActual = $user['id'];

            if ($contactId === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Falta el parámetro contact_id']);
                return;
            }

            // Validar que contactId sea numérico si es necesario
            if (!is_numeric($contactId)) {
                 http_response_code(400);
                 echo json_encode(['error' => 'contact_id debe ser un número']);
                 return;
            }

            $messages = $this->getChatHistory($userIdActual, (int)$contactId);

            echo json_encode([
                'success' => true,
                'data' => $messages
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
        }
    }    

    public function updateUserStatus() {
        try {
            $token = $this->getAuthToken();
            if (!$token) {
                http_response_code(401);
                echo json_encode(['error' => 'Token no proporcionado']);
                return;
            }

            $user = $this->validateToken($token);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Token inválido']);
                return;
            }

            // Obtener el nuevo estado del cuerpo de la solicitud
            $input = json_decode(file_get_contents('php://input'), true);
            $newStatus = $input['status'] ?? 'online';

            if (!in_array($newStatus, ['online', 'offline', 'ausente'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Estado no válido']);
                return;
            }

            // Actualizar en la base de datos

            $chat_estado = $newStatus;
            $chat_ultima_conexion = date('Y-m-d H:i:s');

            $datos = [
                ['campo_nombre' => 'chat_estado', 'campo_marcador' => ':chat_estado', 'campo_valor' => $chat_estado],
                ['campo_nombre' => 'chat_ultima_conexion', 'campo_marcador' => ':chat_ultima_conexion', 'campo_valor' => $chat_ultima_conexion]
            ];
            $condicion = [
                'condicion_campo' => 'id',
                'condicion_operador' => '=', 
                'condicion_marcador' => ':id',
                'condicion_valor' => $user['id']
            ];

            $this->actualizarDatos('usuarios_ejecutivos', $datos, $condicion);

            echo json_encode(['success' => true, 'message' => 'Estado actualizado']);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function updateUserStatusFromToken($token)
    {
        $user = $this->validateToken($token);
        if (!$user || !isset($user['id'])) {
            return ['success' => false, 'error' => 'Token inválido'];
        }

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
            'condicion_valor' => $user['id']
        ];

        try {
            $this->actualizarDatos('usuarios_ejecutivos', $datos, $condicion);
            return ['success' => true, 'user_id' => $user['id']];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error al actualizar estado'];
        }
    }

    public function getChatHistoryFrom($userIdActual, $contactId, $sinceTimestamp) {
        $stmt = "
            SELECT 
                cm.id, 
                cm.usuario_id as remitente_id, 
                ue.nombre as remitente_nombre, 
                ue.email as remitente_email,
                ue.chat_avatar as remitente_avatar, 
                cm.mensaje as content, 
                cm.fecha_envio as timestamp,
                cs.id as sala_id,
                cm.leido
            FROM chat_mensajes cm
            INNER JOIN chat_usuarios_salas cus1 ON cm.sala_id = cus1.sala_id
            INNER JOIN chat_usuarios_salas cus2 ON cm.sala_id = cus2.sala_id
            INNER JOIN usuarios_ejecutivos ue ON cm.usuario_id = ue.id
            INNER JOIN chat_salas cs ON cm.sala_id = cs.id
            WHERE 
                cus1.usuario_id = :v_userIdActual1 AND
                cus2.usuario_id = :v_contactId1 AND
                (cm.usuario_id = :v_userIdActual2 OR cm.usuario_id = :v_contactId3) AND
                cm.fecha_envio > :v_since AND
                cs.activo = 1 AND
                ue.activo = 1
            ORDER BY cm.fecha_envio ASC
            LIMIT 100";

        $param = [
            ':v_userIdActual1' => $userIdActual,
            ':v_userIdActual2' => $userIdActual,
            ':v_contactId1' => $contactId,
            ':v_contactId2' => $contactId,
            ':v_contactId3' => $contactId,
            ':v_since' => $sinceTimestamp
        ];

        try {
            $this->log("Consulta historial desde: " . $stmt);
            $datos = $this->ejecutarConsulta($stmt, "", $param, "fetchAll");
            $this->log("Resultado historial desde: " . json_encode($datos));
            if (is_array($datos) && count((array)$datos) > 0) {
                return $datos;
            } else {
                return [];
            }    
        } catch (Exception $e) {
            $this->logWithBacktrace("Error en Consulta de historico: " . print_r(e, true));
            return $datos ?: [];
        }
    }    

    // Función de sanitización recursiva
    private function sanitizeHtml($html, $allowed) {
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'auto');
        }

        $dom = new \DOMDocument();
        $dom->strictErrorChecking = false;
        $dom->recover = true;
        libxml_use_internal_errors(true);

        $wrapper = '<!DOCTYPE html><html><body>' . $html . '</body></html>';
        $dom->loadHTML($wrapper, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_PARSEHUGE);

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*');

        $removeList = [];
        foreach ($nodes as $node) {
            $tag = $node->nodeName;

            // ✅ Ignorar html y body (son del wrapper, no del contenido)
            if ($tag === 'html' || $tag === 'body') {
                continue;
            }

            if (!isset($allowed[$tag])) {
                $removeList[] = $node;
                continue;
            }

            $allowedAttrs = $allowed[$tag] ?? [];
            $attrsToRemove = [];
            foreach ($node->attributes as $attr) {
                if (!in_array($attr->name, $allowedAttrs)) {
                    $attrsToRemove[] = $attr->name;
                }
            }
            foreach ($attrsToRemove as $attrName) {
                $node->removeAttribute($attrName);
            }

            if (in_array($tag, ['img', 'audio', 'video']) && $node->hasAttribute('src')) {
                $src = trim($node->getAttribute('src'));
                if (!preg_match('#^https?://positron4tx\.ddns\.net:9990/#', $src)) {
                    $node->removeAttribute('src');
                } else {
                    $node->setAttribute('src', $src);
                }
            }
        }

        foreach ($removeList as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }

        // ✅ Ahora sí: el <body> original del wrapper sigue intacto
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }

        $result = '';
        foreach ($body->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }

    public function sendMessage(): void {
        try {
            $token = $this->getAuthToken();
            if (!$token) {
                echo json_encode(['success' => false, 'error' => 'Token no proporcionado']);
                return;
            }

            $user = $this->validateToken($token);
            if (!$user) {
                echo json_encode(['success' => false, 'error' => 'Token inválido']);
                return;
            }
            $emisorId = $user['id'];

            $input = json_decode(file_get_contents('php://input'), true);
            $toEmail = $input['to'] ?? '';
            $content = $input['content'] ?? '';

            if (empty($toEmail) || empty($content)) {
                echo json_encode(['success' => false, 'error' => 'Faltan datos']);
                return;
            }

            // Lista de etiquetas y atributos permitidos
            $allowedTags = [
                'b' => [],
                'strong' => [],
                'i' => [],
                'em' => [],
                'u' => [],
                's' => [],
                'strike' => [],
                'p' => [],
                'br' => [],
                'ul' => [],
                'ol' => [],
                'li' => [],
                'img' => ['src', 'alt', 'width', 'height'],
                'audio' => ['src', 'controls'],
                'video' => ['src', 'controls', 'width', 'height'],
                'a' => ['href', 'target']
            ];

            // Sanitizar el contenido: eliminar tags no permitidos y doble codificación
            $sanitizedContent = $this->sanitizeHtml($content, $allowedTags);

            // Opcional: prevenir inyección de eventos (onload, onclick, etc.)
            //$sanitizedContent = preg_replace('/<[^>]*\s(on\w+\s*=|javascript:)[^>]*>/i', '', $sanitizedContent);

            // Opcional: decodificar entidades HTML si vienen codificadas
            //$sanitizedContent = html_entity_decode($sanitizedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (empty($sanitizedContent)) {
                echo json_encode(['success' => false, 'error' => 'Mensaje vacío después de sanitización']);
                return;
            }

            // 1. Buscar destinatario
            $stmt = "SELECT id FROM usuarios_ejecutivos WHERE email = :v_email AND activo = :v_activo";

            $param = [
                ':v_email' =>  $toEmail,
                ':v_activo' => 1
            ];
            $dest = $this->ejecutarConsulta($stmt, "", $param);

            if (!$dest) {
                echo json_encode(['success' => false, 'error' => 'Destinatario no encontrado']);
                return;
            }
            $destinatarioId = $dest['id'];

            // Obtener salas del emisor
            $stmtEmisor = " SELECT sala_id 
                FROM chat_usuarios_salas 
                WHERE usuario_id = :v_usuario_id";

            $param = [
                ':v_usuario_id' => $emisorId
            ];    
            $salasEmisor = $this->ejecutarConsulta($stmtEmisor, "", $param,  "fetchAll");

            // Obtener salas del destinatario
            $stmtDestino = "SELECT sala_id 
                FROM chat_usuarios_salas 
                WHERE usuario_id = :v_usuario_id";
            $param = [
                ':v_usuario_id' => $destinatarioId
            ];    
            
            $salasDestino = $this->ejecutarConsulta($stmtDestino, "", $param, "fetchAll");

            // Encontrar la intersección
            $salasEmisorIds = array_column($salasEmisor, 'sala_id');
            $salasDestinoIds = array_column($salasDestino, 'sala_id');
            $salaComun = array_values(array_intersect($salasEmisorIds, $salasDestinoIds));

            if (empty($salaComun)) {
                // Si no hay sala común, crear una privada
                $salaComunId = $this->crearSalaDirecta($emisorId, $destinatarioId);
            } else {
                $salaComunId = $salaComun[0];
            }

            $fecha_envio = date('Y-m-d H:i:s');
            $datos = [
                ['campo_nombre' => 'sala_id', 'campo_marcador' => ':sala_id', 'campo_valor' => $salaComunId],
                ['campo_nombre' => 'usuario_id', 'campo_marcador' => ':usuario_id', 'campo_valor' => $emisorId],
                ['campo_nombre' => 'mensaje', 'campo_marcador' => ':mensaje', 'campo_valor' => $sanitizedContent],
                ['campo_nombre' => 'fecha_envio', 'campo_marcador' => ':fecha_envio', 'campo_valor' => $fecha_envio]
            ];

            try {
                $salaId = $this->guardarDatos('chat_mensajes', $datos);
                $this->log("Mensaje insertado");

            } catch (Exception $e) {
                $this->logWithBacktrace("Error en Crear Mensaje: " . print_r($datos, true));
                echo json_encode(['success' => false, 'error' => 'No se pudo crear el mensaje']);
            }

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al enviar mensaje']);
        }
    }

    public function getHistorySince() {
        try {
            $token = $this->getAuthToken();
            if (!$token) {
                echo json_encode(['success' => false, 'error' => 'Token no proporcionado']);
                return;
            }
            $user = $this->validateToken($token);
            if (!$user) {
                echo json_encode(['success' => false, 'error' => 'Token inválido']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $contactId = (int)($input['contact_id'] ?? 0);
            $sinceId = (int)($input['since_id'] ?? 0);

            if ($contactId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Contacto inválido']);
                return;
            }

            if ($sinceId <= 0) {
                echo json_encode(['success' => false, 'error' => 'since_id inválido']);
                return;
            }
                        
            // Obtener mensajes nuevos
            $anterior = "
                SELECT cm.id, cm.usuario_id as remitente_id, ue.nombre as remitente_nombre, ue.email as remitente_email,
                    cm.mensaje as content, cm.fecha_envio as timestamp, cs.id as sala_id
                FROM chat_mensajes cm
                JOIN usuarios_ejecutivos ue ON cm.usuario_id = ue.id
                JOIN chat_usuarios_salas cus1 ON cm.sala_id = cus1.sala_id
                JOIN chat_usuarios_salas cus2 ON cm.sala_id = cus2.sala_id
                WHERE (cus1.usuario_id = :user AND cus2.usuario_id = :contact)
                OR (cus1.usuario_id = :contact AND cus2.usuario_id = :user)
                AND cm.id > :since
                ORDER BY cm.id ASC
            ";
            $params01 = [
                ':user' => $user['id'], 
                ':contact' => $contactId, 
                ':since' => $sinceId
            ];

            $stmt = " SELECT  cm.id, cm.usuario_id as remitente_id, ue.nombre as remitente_nombre, ue.email as remitente_email,
                    cm.mensaje as content, cm.fecha_envio as timestamp, cs.id as sala_id
                FROM chat_mensajes cm
                INNER JOIN chat_usuarios_salas cus ON cm.sala_id = cus.sala_id
                INNER JOIN usuarios_ejecutivos ue ON cm.usuario_id = ue.id
                INNER JOIN chat_salas cs ON cm.sala_id = cs.id
                WHERE 
                    cus.usuario_id = :usuario_id AND
                    cm.id > :since_id AND
                    cs.activo = 1 AND
                    ue.activo = 1
                ORDER BY cm.id ASC";

            $params = [
                ':usuario_id' => $user['id'],
                ':since_id' => $sinceId
            ];

            $mensajes = $this->ejecutarConsulta($stmt, "", $params, "fetchAll");
            echo json_encode(['success' => true, 'data' => $mensajes ?: []]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al cargar historial']);
        }
    }

    public function getNumberChatNew($userId) {
        $stmt = "SELECT COUNT(*) as count 
                FROM chat_mensajes m
                INNER JOIN chat_usuarios_salas s ON m.sala_id = s.sala_id
                WHERE s.usuario_id = :user_id1 AND m.leido = 0 AND m.usuario_id != :user_id2";
        $param = [
            ':user_id1' => $userId,
            ':user_id2' => $userId
        ];
        $result = $this->ejecutarConsulta($stmt, "", $param);

        $count = (int)($result['count'] ?? 0);
        return $count;
    }

    public function getNumberChatNew_for_user($userId){
        // Obtener contactos con conteo de mensajes no leídos
        $stmt = "
            SELECT 
                u.id,
                u.nombre,
                u.email,
                u.chat_estado as status,
                COALESCE(unread_counts.unread, 0) as unread
            FROM usuarios_ejecutivos u
            LEFT JOIN (
                SELECT 
                    IF(m.usuario_id = :user_id1, s2.usuario_id, s1.usuario_id) as other_user_id,
                    COUNT(*) as unread
                FROM chat_mensajes m
                INNER JOIN chat_usuarios_salas s1 ON m.sala_id = s1.sala_id
                INNER JOIN chat_usuarios_salas s2 ON m.sala_id = s2.sala_id
                WHERE 
                    s1.usuario_id = :user_id2 
                    AND m.leido = 0 
                    AND m.usuario_id != :user_id3
                GROUP BY other_user_id
            ) unread_counts ON u.id = unread_counts.other_user_id
            WHERE u.id != :user_id4 AND u.activo = 1
            ORDER BY u.nombre";

        $param = [
            ':user_id1' => $userId,
            ':user_id2' => $userId,
            ':user_id3' => $userId,
            ':user_id4' => $userId
        ];
        $result = $this->ejecutarConsulta($stmt, "", $param, "fetchAll");
        return $result;
    }

 
    public function markMessagesAsRead($user_id, $messageIds){
        // Asegurar que los mensajes pertenecen al usuario
        $placeholders = str_repeat('?,', count($messageIds) - 1) . '?';

        if (empty($messageIds)) {
            $this->log("marcarMensajesComoLeidos: lista de IDs vacía");
            return 0;
        }

        // Validar que todos los IDs sean enteros positivos
        $messageIds = array_map('intval', $messageIds);
        $messageIds = array_filter($messageIds, fn($id) => $id > 0);

        if (empty($messageIds)) {
            $this->log("marcarMensajesComoLeidos: IDs inválidos");
            return 0;
        }        

        $filas = $this->marcarMensajesComoLeidos($user_id, $messageIds);
        
        if ($filas >= 0) {
            echo json_encode(['success' => true, 'affected' => $filas]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al marcar mensajes']);
        }
        exit();
    }
}
