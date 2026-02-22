<?php
// contacts2AJAX.PHP
// === 1. Iniciar buffer y sesión (lo primero) ===
header('Content-Type: application/json; charset=utf-8');
ob_start(); // Iniciar buffer de salida

require_once "../views/inc/session_start.php";

// === 2. Cargar configuración y autoload ===
require_once "../../config/app.php";
require_once "../../autoload.php";

// === 3. Manejo de CORS ===
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// === 4. Preflight ===
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// === 5. Validar método ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// === 6. Leer y decodificar JSON si viene en POST ===
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$inputData = [];

if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents("php://input");
    $jsonInput = json_decode($rawInput, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonInput)) {
        $inputData = $jsonInput;
    } else {
        error_log("JSON malformado o no decodificado: " . $rawInput);
        http_response_code(400);
        echo json_encode(['error' => 'JSON inválido']);
        exit();
    }
} else {
    $inputData = $_POST;
}

// === 7. Procesar módulo ===
$modulo = $inputData['modulo_contacts'] ?? '';

if (!$modulo) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro "modulo_contacts"']);
    exit();
}

// === 8. Cargar el controlador ===
require_once  '../controllers/contactsController.php';

use app\controllers\contactsController;

$controller = new contactsController();

// === 9. Enrutar según el módulo ===
switch ($modulo) {
    case 'get_history':
        $contact_id = $inputData['contact_id'];
        $token = $inputData['token'];
        $salaId = $inputData['salaId'];

        $controller->getChatHistoryPublic($token, $contact_id, $salaId);
        break;

    case 'contactos':
        $controller->getContacts();
        break;

    case 'update_status':
        $controller->updateUserStatus();
        break;

    case 'self_status':
        $selfData = $controller->self_status();
        $self = $selfData[0] ?? null;
        echo json_encode([
            'success' => true,
            'data' => ['self' => $self]
        ]);
        break;

    case 'get_history_from':
        $controller->getChatHistoryFrom(
            $user['id'],
            (int)($input['contact_id'] ?? 0),
            $input['since'] ?? ''
        );
        break;

    case 'send_message':
        $salaId = $inputData['sala_id'] ?? null;
        $toEmail = $inputData['to'] ?? null;
        $content = $inputData['content'] ?? '';
        $broadcast = !empty($inputData['broadcast']);

        $controller->sendMessage($salaId, $toEmail, $content, $broadcast);
        break;

    case 'get_history_since':
        $salaId = $inputData['sala_id'] ?? null;
        $controller->getHistorySince($salaId);
        break;

    case 'upload_file':
        if (ob_get_level()) ob_clean();

        if (!isset($_SESSION['user_valid']) || !$_SESSION['user_valid']) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit();
        }

        if (!isset($_FILES['file'])) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded']);
            exit();
        }

        $file = $_FILES['file'];
        $allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'audio/mpeg',
            'audio/ogg',
            'audio/wav',
            'video/mp4',
            'video/webm',
            'video/ogg'
        ];
        $maxSize = 10 * 1024 * 1024; // 10 MB

        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Upload error']);
            exit();
        }

        if ($file['size'] > $maxSize) {
            echo json_encode(['success' => false, 'error' => 'File too large (max 10 MB)']);
            exit();
        }

        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => 'Unallowed file type']);
            exit();
        }

        // Ruta de subida
        $uploadDir = APP_R_PROY . 'uploads/chat/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                echo json_encode(['success' => false, 'error' => 'The upload folder could not be created']);
                exit();
            }
        }

        // Nombre único
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'chat_' . uniqid() . '.' . strtolower($ext);
        $path = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $path)) {
            $url = RUTA_APP . '/uploads/chat/' . $filename;
            if (ob_get_level()) ob_clean(); // ← Limpieza inmediata antes de JSON
            echo json_encode(['success' => true, 'url' => $url]);
        } else {
            echo json_encode(['success' => false, 'error' => 'The file could not be saved.']);
        }
        exit();

    case 'unread_count':
        $params = [
            'success' => false, 
            'count' => 0
        ];
        if (!isset($_SESSION['user_valid']) || !$_SESSION['user_valid']) {
            echo json_encode($params);
            exit();
        }
        $userId = $_SESSION['user_id'] ?? 0;
        if (!$userId) {
            echo json_encode($params);
            exit();
        }

        $count = $controller->getNumberChatNew($userId);

        $params = [
            'success' => true, 
            'count' => $count
        ];
        echo json_encode($params);
        exit();

    case 'mark_messages_as_read':
        $user_id = $_SESSION['user_id'] ?? 0;
        if (!$user_id) {
            echo json_encode(['success' => false]);
            exit();
        }

        $input = json_decode(file_get_contents('php://input'), true);

        $messageIds = $input['message_ids'] ?? [];

        if (empty($messageIds)) {
            echo json_encode(['success' => true]);
            exit();
        }

        $result = $controller->markMessagesAsRead($user_id, $messageIds);

        echo json_encode($result);
        exit();

    case 'listar_salas_usuario':
        $userId = $inputData['user_id'] ?? null;
        if (!$userId) break;

        $result = $controller->traer_salas($userId);

        echo json_encode(['success' => true, 'salas' => $result]);
        break;

    case 'guardar_ultima_sala':
        $userId = $inputData['user_id'] ?? null;
        $salaId = $inputData['sala_id'] ?? null;

        if ($userId && $salaId) {
            $result = $controller->ultima_sala($userId, $salaId);
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false]);
            }
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    // En chatAjax.php
    case 'marcar_leidos_por_sala':
        $salaId = $inputData['sala_id'] ?? null;
        $userId = $inputData['user_id'] ?? null;

        if ($salaId && $userId) {
            $result = $controller->marca_leidos_por_sala($userId, $salaId);
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false]);
            }
        }
        break;

    // En contactsAjax.php
    case 'get_room_history':
        $salaId = (int)($inputData['sala_id'] ?? 0);
        $token = $inputData['token'];
        $mensajes = $controller->room_history($salaId, $token);

        echo json_encode(['success' => true, 'data' => $mensajes]);
        break;

    case 'get_room_members':
        $salaId = (int)($inputData['sala_id'] ?? 0);
        $token = $inputData['token'];

        $miembros = $controller->get_room_members($salaId, $token);

        echo json_encode(['success' => true, 'contacts' => $miembros]);
        break;

    case 'obtener_ultima_sala':
        $userId = (int)($inputData['user_id'] ?? 0);
        if (!$userId) {
            echo json_encode(['success' => false]);
            return;
        }

        $salaId = $controller->obtener_ultima_sala($userId);

        echo json_encode([
            'success' => true,
            'ultima_sala_id' => $salaId ? (int)$salaId : null
        ]);
        break;

    case 'get_unread_count':
        $token = $inputData['token'] ?? null;
        $isroom = !empty($inputData['is_room']);

        if ($isroom) {
            $sala_id = $inputData['sala_id'] ?? null;
            $cantidad = $controller->messUnreadRoom($token, $sala_id);
            $cantidad = (int)($cantidad ?? 0);
            echo json_encode(['success' => true, 'count' => $cantidad]);
        } else {
            $sala_id = $inputData['sala_id'] ?? null;
            $contact_id = $inputData['contact_id'] ?? null;
            $cantidad = $controller->messUnreadContact($contact_id, $sala_id);
            if ($cantidad && is_array($cantidad) && isset($cantidad[0]['unread'])) {
                $noleidos = (int)$cantidad[0]['unread'];
                echo json_encode(['success' => true, 'count' => $noleidos]);
            } else {
                echo json_encode(['success' => true, 'count' => 0]);
            }
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid module: ' . $modulo]);
        exit();
}

exit();
