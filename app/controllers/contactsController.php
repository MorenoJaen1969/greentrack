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

        $datos = $this->ejecutarConsulta($stmt, "", $param);
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
            WHERE id != :v_userId AND activo = 1 
                AND chat_activo = 1
                AND es_sistema = 0   -- ← EXCLUIR USUARIO SISTEMA DE LA LISTA DE CONTACTOS
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

        // === Paso 3: Obtener sesiones móviles activas (últimos 5 minutos) ===
        $contactIds = array_column($contacts, 'id');
        $movilActivo = [];
        if (!empty($contactIds)) {
            $placeholders = str_repeat('?,', count($contactIds) - 1) . '?';
            $stmtMovil = "SELECT user_id
                FROM sesiones_activas
                WHERE user_id IN ($placeholders)
                    AND dispositivo = 'movil'
                    AND ultima_actividad >= NOW() - INTERVAL 3600 SECOND";
            $resultado = $this->ejecutarConsulta($stmtMovil, "", $contactIds, "fetchAll");
            foreach ($resultado as $row) {
                $movilActivo[$row['user_id']] = true;
            }
        }

        // === Paso 4: Obtener modo de PC (para "En Pausa") ===
        $pcModo = [];
        if (!empty($contactIds)) {
            $stmtPC = "
                SELECT user_id, modo
                FROM sesiones_activas
                WHERE user_id IN ($placeholders)
                    AND dispositivo = 'pc'
                    AND ultima_actividad >= NOW() - INTERVAL 3600 SECOND";
            $resultadoPC = $this->ejecutarConsulta($stmtPC, "", $contactIds, "fetchAll");
            foreach ($resultadoPC as $row) {
                $pcModo[$row['user_id']] = $row['modo'];
            }
        }

        // Paso 5: mapear conteo a contactos
        $unreadMap = [];
        foreach ($unreadList as $row) {
            $unreadMap[$row['contact_id']] = (int)$row['unread'];
        }

        foreach ($contacts as &$contact) {
            $contact['unread'] = $unreadMap[$contact['id']] ?? 0;

            $dispositivos = ['pc' => null, 'movil' => null];

            // 🖥️ PC: basado en chat_estado
            if ($contact['status'] === 'online') {
                // Si hay modo reciente, usarlo; si no, asumir 'activo'
                $dispositivos['pc'] = $pcModo[$contact['id']] ?? 'activo';
            }

            // 📱 Móvil: basado SOLO en sesiones_activas
            if (!empty($movilActivo[$contact['id']])) {
                $dispositivos['movil'] = 'active';
            }

            $contact['dispositivos'] = $dispositivos;
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

    private function getChatHistory($userIdActual, $contactId, $salaId)
    {
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
                ue.activo = 1 AND
                cm.sala_id = :v_salaId
            ORDER BY cm.fecha_envio ASC
            LIMIT 100
        ";

        $param = [
            ':v_userIdActual1' => $userIdActual,
            ':v_userIdActual2' => $userIdActual,
            ':v_contactId1' => $contactId,
            ':v_contactId2' => $contactId,
            ':v_salaId' => $salaId
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
    public function getChatHistoryPublic($token, $contactId, $salaId)
    {
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
            $messages = $this->getChatHistory($userIdActual, (int)$contactId, $salaId);

            echo json_encode([
                'success' => true,
                'data' => $messages
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
        }
    }

    public function updateUserStatus()
    {
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

            // Asegurarse de tener una sala válida antes de insertar
            if (empty($salaId)) {
                echo json_encode(['success' => false, 'error' => 'No se pudo determinar la sala para el mensaje']);
                return;
            }

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

    public function getChatHistoryFrom($userIdActual, $contactId, $sinceTimestamp)
    {
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
            $this->logWithBacktrace("Error en Consulta de historico: " . print_r($e, true));
            return [];
        }
    }

    // Función de sanitización recursiva
    private function sanitizeHtml($html, $allowed)
    {
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

    public function sendMessage($salaId, $toEmail, $content, $broadcast = false): void
    {
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

            if (empty($content)) {
                echo json_encode(['success' => false, 'error' => 'Faltan datos (contenido)']);
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

            $destId = null;
            // Si es broadcast, ignorar destinatario privado
            if (!empty($broadcast)) {
                $toEmail = null;
            }
            if (!empty($toEmail)) {
                // 1. Buscar destinatario por email (solo si se especificó)
                $stmt = "SELECT id 
                    FROM usuarios_ejecutivos 
                    WHERE email = :v_email 
                        AND activo = :v_activo";
                $param = [
                    ':v_email' =>  $toEmail,
                    ':v_activo' => 1
                ];
                $dest = $this->ejecutarConsulta($stmt, "", $param);
                if (!$dest) {
                    echo json_encode(['success' => false, 'error' => 'Destinatario no encontrado']);
                    return;
                }
                $destId = $dest['id'] ?? null;
            }

            // Si no se proporcionó sala_id pero se indicó destinatario y NO es broadcast, intentar resolver sala 1:1
            if (empty($salaId) && $destId) {
                if ($broadcast) {
                    echo json_encode(['success' => false, 'error' => 'Broadcast requires a sala_id']);
                    return;
                }
                $stmtSala = "SELECT s.sala_id as sala_id
                    FROM chat_usuarios_salas s
                    INNER JOIN chat_usuarios_salas s2 ON s.sala_id = s2.sala_id
                    WHERE s.usuario_id = :emisor_id AND s2.usuario_id = :dest_id
                    LIMIT 1";
                $paramsSala = [':emisor_id' => $emisorId, ':dest_id' => $destId];
                $found = $this->ejecutarConsulta($stmtSala, "", $paramsSala);
                if ($found && isset($found['sala_id'])) {
                    $salaId = $found['sala_id'];
                } else {
                    // Crear sala privada 1:1 si no existe
                    $nombreSala = 'dm_' . min($emisorId, $destId) . '_' . max($emisorId, $destId);
                    $datosSala = [
                        ['campo_nombre' => 'nombre', 'campo_marcador' => ':nombre', 'campo_valor' => $nombreSala],
                        ['campo_nombre' => 'descripcion', 'campo_marcador' => ':descripcion', 'campo_valor' => 'Sala privada entre usuarios'],
                        ['campo_nombre' => 'creado_por', 'campo_marcador' => ':creado_por', 'campo_valor' => $emisorId],
                        ['campo_nombre' => 'fecha_creacion', 'campo_marcador' => ':fecha_creacion', 'campo_valor' => date('Y-m-d H:i:s')],
                        ['campo_nombre' => 'activo', 'campo_marcador' => ':activo', 'campo_valor' => 1]
                    ];
                    try {
                        $newSalaId = $this->guardarDatos('chat_salas', $datosSala);
                        if ($newSalaId) {
                            // Añadir usuarios a la sala
                            $datosUsuario1 = [
                                ['campo_nombre' => 'sala_id', 'campo_marcador' => ':sala_id', 'campo_valor' => $newSalaId],
                                ['campo_nombre' => 'usuario_id', 'campo_marcador' => ':usuario_id', 'campo_valor' => $emisorId],
                                ['campo_nombre' => 'rol', 'campo_marcador' => ':rol', 'campo_valor' => 'owner'],
                                ['campo_nombre' => 'fecha_union', 'campo_marcador' => ':fecha_union', 'campo_valor' => date('Y-m-d H:i:s')]
                            ];
                            $datosUsuario2 = [
                                ['campo_nombre' => 'sala_id', 'campo_marcador' => ':sala_id', 'campo_valor' => $newSalaId],
                                ['campo_nombre' => 'usuario_id', 'campo_marcador' => ':usuario_id', 'campo_valor' => $destId],
                                ['campo_nombre' => 'rol', 'campo_marcador' => ':rol', 'campo_valor' => 'member'],
                                ['campo_nombre' => 'fecha_union', 'campo_marcador' => ':fecha_union', 'campo_valor' => date('Y-m-d H:i:s')]
                            ];
                            $this->guardarDatos('chat_usuarios_salas', $datosUsuario1);
                            $this->guardarDatos('chat_usuarios_salas', $datosUsuario2);
                            $salaId = $newSalaId;
                        }
                    } catch (Exception $e) {
                        $this->logWithBacktrace("Error creando sala 1:1: " . $e->getMessage());
                    }
                }
            }

            $fecha_envio = date('Y-m-d H:i:s');
            $datos = [
                ['campo_nombre' => 'sala_id', 'campo_marcador' => ':sala_id', 'campo_valor' => $salaId],
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

    public function getHistorySince($salaId)
    {
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
            $anterior = "SELECT cm.id, cm.usuario_id as remitente_id, ue.nombre as remitente_nombre, ue.email as remitente_email,
                    cm.mensaje as content, cm.fecha_envio as timestamp, cs.id as sala_id
                FROM chat_mensajes cm
                JOIN usuarios_ejecutivos ue ON cm.usuario_id = ue.id
                JOIN chat_usuarios_salas cus1 ON cm.sala_id = cus1.sala_id
                JOIN chat_usuarios_salas cus2 ON cm.sala_id = cus2.sala_id
                WHERE (cus1.usuario_id = :user AND cus2.usuario_id = :contact)
                    OR (cus1.usuario_id = :contact AND cus2.usuario_id = :user)
                    AND cm.id > :since
                    AND cm.sala_id = :sala_id
                ORDER BY cm.id ASC
            ";
            $params01 = [
                ':user' => $user['id'],
                ':contact' => $contactId,
                ':since' => $sinceId,
                ':sala_id' => $salaId
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
                    ue.activo = 1 AND
                    cm.sala_id = :sala_id
                ORDER BY cm.id ASC";

            $params = [
                ':usuario_id' => $user['id'],
                ':since_id' => $sinceId,
                ':sala_id' => $salaId
            ];

            $mensajes = $this->ejecutarConsulta($stmt, "", $params, "fetchAll");
            echo json_encode(['success' => true, 'data' => $mensajes ?: []]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al cargar historial']);
        }
    }

    public function getNumberChatNew($userId)
    {
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

    public function getNumberChatNew_for_user($userId)
    {
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

    public function traer_salas($userId)
    {
        // Obtener salas + conteo de no leídos por sala
        $sql = "SELECT s.id, s.nombre, s.descripcion,
                COUNT(CASE WHEN m.leido = 0 AND m.usuario_id != :v_usuario_id1 THEN 1 END) as unread
            FROM chat_usuarios_salas us
            INNER JOIN chat_salas s ON us.sala_id = s.id
            LEFT JOIN chat_mensajes m ON s.id = m.sala_id
            WHERE us.usuario_id = :v_usuario_id2
                AND s.activo = 1
            GROUP BY s.id, s.nombre, s.descripcion
            ORDER BY s.nombre";

        $param = [
            ':v_usuario_id1' => $userId,
            ':v_usuario_id2' => $userId
        ];

        $salas = $this->ejecutarConsulta($sql, "", $param, "fetchAll");
        return $salas;
    }

    public function ultima_sala($userId, $salaId)
    {
        $datos = [
            ['campo_nombre' => 'ultima_sala_id', 'campo_marcador' => ':ultima_sala_id', 'campo_valor' => $salaId]
        ];
        $condicion = [
            'condicion_campo' => 'id',
            'condicion_operador' => '=',
            'condicion_marcador' => ':id',
            'condicion_valor' => $userId
        ];

        try {
            $this->actualizarDatos('usuarios_ejecutivos', $datos, $condicion);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function marca_leidos_por_sala($userId, $salaId)
    {
        $datos = [
            ['campo_nombre' => 'leido', 'campo_marcador' => ':leido', 'campo_valor' => 1]
        ];
        $condicion = [
            [
                'condicion_campo' => 'sala_id',
                'condicion_operador' => '=',
                'condicion_marcador' => ':sala_id',
                'condicion_valor' => $salaId
            ],
            [
                'condicion_campo' => 'usuario_id',
                'condicion_operador' => '=',
                'condicion_marcador' => ':usuario_id',
                'condicion_valor' => $userId
            ],
            [
                'condicion_campo' => 'leido',
                'condicion_operador' => '=',
                'condicion_marcador' => ':leido',
                'condicion_valor' => 0
            ]
        ];


        try {
            $this->actualizarDatos('chat_mensajes', $datos, $condicion);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function room_history($salaId, $token)
    {
        $usuarioToken = $this->validateToken($token);
        $userId = $usuarioToken['id'];

        if (!$salaId || !$userId) {
            echo json_encode(['success' => false]);
            return;
        }

        // ✅ Verificar que el usuario pertenece a la sala
        $stmt = "SELECT 1 
            FROM chat_usuarios_salas 
            WHERE sala_id = :v_sala_id 
                AND usuario_id = :v_usuario_id";
        $param = [
            ':v_sala_id' => $salaId,
            ':v_usuario_id' => $userId
        ];

        $result = $this->ejecutarConsulta($stmt, "", $param);
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            return;
        }

        // Cargar mensajes de la sala
        $sql = "SELECT 
                m.id,
                m.mensaje as content,
                m.fecha_envio as timestamp,
                m.leido,
                u.id as remitente_id,
                u.nombre as remitente_nombre,
                u.email as remitente_email
            FROM chat_mensajes m
            INNER JOIN usuarios_ejecutivos u ON m.usuario_id = u.id
            WHERE m.sala_id = :v_sala_id
            ORDER BY m.fecha_envio ASC";

        $param = [
            ':v_sala_id' => $salaId
        ];

        $mensajes = $this->ejecutarConsulta($sql, "", $param, "fetchAll");
        return $mensajes;
    }

    public function markMessagesAsRead($user_id, $messageIds)
    {
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

    public function marcarMensajesComoLeidos($user_id, $messageIds): int
    {
        $datos = [
            ['campo_nombre' => 'leido', 'campo_marcador' => ':leido', 'campo_valor' => 1]
        ];

        $cantidad_registros = 0;

        foreach ($messageIds as $id) {
            $condicion = [
                'condicion_campo' => 'id',
                'condicion_operador' => '=',
                'condicion_marcador' => ':id',
                'condicion_valor' => $id
            ];
            $marcado = $this->actualizarDatos('chat_mensajes', $datos, $condicion);
            if ($marcado) {
                $cantidad_registros++;
            }
        }

        return $cantidad_registros;
    }

    public function messUnreadRoom($token, $sala_id)
    {
        $usuarioToken = $this->validateToken($token);
        $userId = $usuarioToken['id'];

        if (!$sala_id || !$userId) {
            echo json_encode(['success' => false]);
            return;
        }

        $stmt = "SELECT COUNT(*) as total_unread
            FROM chat_mensajes m
            WHERE 
                m.sala_id = :sala_id
                AND m.leido = 0
                AND m.usuario_id != :user_id";

        $params = [
            ':sala_id' => $sala_id,
            ':user_id' => $userId
        ];

        $result = $this->ejecutarConsulta($stmt, "", $params);
        return (int)($result['total_unread'] ?? 0);
    }

    public function messUnreadContact($contact_id, $sala_id)
    {
        if (!$sala_id || !$contact_id) {
            echo json_encode(['success' => false]);
            return;
        }

        $stmt2 = "SELECT 
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
                AND m.sala_id = :sala_id
            GROUP BY contact_id";
        $param2 = [
            ':user_id1' => $contact_id,
            ':user_id2' => $contact_id,
            ':user_id3' => $contact_id,
            ':user_id4' => $contact_id,
            ':sala_id' => $sala_id
        ];
        $unreadList = $this->ejecutarConsulta($stmt2, "", $param2, "fetchAll");
        return $unreadList;
    }

    public function get_room_members($salaId, $token)
    {
        $usuarioToken = $this->validateToken($token);
        $userId = $usuarioToken['id'];

        if (!$salaId || !$userId) {
            echo json_encode(['success' => false]);
            return;
        }

        // Verificar que el usuario pertenece a la sala
        $stmt = "SELECT 1 
            FROM chat_usuarios_salas 
            WHERE sala_id = :v_sala_id 
                AND usuario_id = :v_usuario_id";
        $param = [
            ':v_sala_id' => $salaId,
            ':v_usuario_id' => $userId
        ];

        $result = $this->ejecutarConsulta($stmt, "", $param);
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            return;
        }

        // Obtener miembros de la sala (excluyendo al usuario actual)
        $stmt = "SELECT u.id, u.nombre, u.email, u.chat_avatar as avatar
            FROM chat_usuarios_salas us
            INNER JOIN usuarios_ejecutivos u ON us.usuario_id = u.id
            WHERE us.sala_id = :v_sala_id AND u.id != :v_usuario_id
            ORDER BY u.nombre";

        $param = [
            ':v_sala_id' => $salaId,
            ':v_usuario_id' => $userId
        ];

        $miembros = $this->ejecutarConsulta($stmt, "", $param, "fetchAll");
        return $miembros;
    }

    public function obtener_ultima_sala($userId)
    {

        $stmt = "SELECT ultima_sala_id 
            FROM usuarios_ejecutivos 
            WHERE id = :v_id";
        $param = [
            ':v_id' => $userId
        ];

        $miembros = $this->ejecutarConsulta($stmt, "", $param);

        $salaId = $miembros['ultima_sala_id'] ?? null;

        return $salaId;
    }
}
