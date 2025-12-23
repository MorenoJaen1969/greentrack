<?php
// chat-view.php
// Soporta modo completo (móvil) y modo parcial (PC modal)

// === 1. Variables iniciales ===
$isPartial = false;
$userEmail = '';
$userName = '';
$userToken = '';
$userValid = false;
$user_id = 0;

// === 2. Determinar la raíz absoluta del proyecto ===
if (!defined('APP_R_PROY')) {
    $current = __DIR__;
    $maxLevels = 5;
    $found = false;

    for ($i = 0; $i < $maxLevels; $i++) {
        $testPath = $current . '/config/app.php';
        if (file_exists($testPath)) {
            define('APP_R_PROY', $current . '/');
            $found = true;
            break;
        }
        $current = dirname($current);
    }

    if (!$found) {
        http_response_code(500);
        exit('❌ No se pudo determinar la raíz del proyecto.');
    }
}

// === 3. Cargar configuración y sesión ===
ob_start();
require_once APP_R_PROY . 'config/app.php';
require_once APP_R_PROY . 'autoload.php';
require_once APP_R_PROY . 'app/views/inc/session_start.php';

use app\controllers\usuariosController;
$usuarios = new usuariosController();

$wsHost = 'positron4tx.ddns.net';
$wsPort = '7070';
$ruta_usuariosAjax = RUTA_APP . "/app/ajax/usuariosAjax.php";

// === 4. Detectar modo parcial ===
$isPartial = isset($_GET['chat']) && $_GET['chat'] === '1';

// === 5. Obtener datos de sesión ===
$userEmail = $_SESSION['user_email'] ?? '';
$userName = $_SESSION['user_name'] ?? '';
$userToken = $_SESSION['token'] ?? '';
$userValid = $_SESSION['user_valid'] ?? false;
$userid = $_SESSION['user_id']  ?? 0;

// === 6. Autenticación con token (para móvil) ===
if (empty($userEmail) && isset($_GET['access_key'])) {
    $token = $_GET['access_key'];
    $param = ['token' => $token];
    $validacion = $usuarios->valida_usuario($param);

    if (!empty($validacion)) {
        $email = $validacion['email'];
        $usuarios_permitidos = [
            'adriana@sergioslandscape.com',
            'sergio@sergioslandscape.com',
            'oparra@mcka915.com',
            'morenojaen@gmail.com'
        ];

        if (in_array($email, $usuarios_permitidos)) {
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $validacion['nombre'];
            $_SESSION['token'] = $validacion['token'];
            $_SESSION['user_valid'] = true;
            $_SESSION['user_id'] = $validacion['id'];
            $userEmail = $email;
            $userName = $validacion['nombre'];
            $userToken = $validacion['token'];
            $userValid = true;
            $user_id = $validacion['id'];
        }
    }
}

// === 7. Si no hay autenticación, mostrar login (solo en modo completo) ===
if (empty($userEmail)) {
    if ($isPartial) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    ?>
    <div class="login">
        <h1>Member Login</h1>
        <form id="loginForm" method="post" class="form login-form" action="<?= $ruta_usuariosAjax ?>">
            <input type="hidden" name="modulo_usuarios" value="control_acceso">
            <label class="form-label" for="username">Username</label>
            <div class="form-group">
                <svg class="form-icon-left" width="14" height="14" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                    <path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H418.3c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304H178.3z"/>
                </svg>
                <input class="form-input" type="text" name="username" placeholder="Username" id="username" required>
            </div>
            <label class="form-label" for="password">Password</label>
            <div class="form-group mar-bot-5">
                <svg class="form-icon-left" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 448 512">
                    <path d="M144 144v48H304V144c0-44.2-35.8-80-80-80s-80 35.8-80 80zM80 192V144C80 64.5 144.5 0 224 0s144 64.5 144 144v48h16c35.3 0 64 28.7 64 64V448c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V256c0-35.3 28.7-64 64-64H80z"/>
                </svg>
                <input class="form-input" type="password" name="password" placeholder="Password" id="password" required>
            </div>
            <button type="submit" class="btn blue btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
    </div>
    <script>
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('.btn-login');
        const originalText = submitBtn.innerHTML;
        try {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validando...';
            submitBtn.disabled = true;
            let data = new FormData(this);
            const res = await fetch(this.action, { method: this.method, body: data });
            const json = await res.json();
            if (json.success) {
                window.location.reload();
            } else {
                alert(json.message || 'Authentication error');
            }
        } catch (e) {
            alert('Invalid server response');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
    </script>
    <?php
    exit();
}

// === 8. Modo parcial: solo el contenedor del chat (para PC) ===
// if ($isPartial): ?>
// <div class="chat-app-container" style="width:100%;height:100%;margin:0;padding:0;">
//     <div class="chat-header">
//         <div class="user-info">
//             <img src="<?= RUTA_REAL ?>/app/views/img/avatars/<?= md5($userEmail) ?>.jpg" alt="Avatar"
//                  class="user-avatar" onerror="this.src='<?= RUTA_REAL ?>/app/views/img/avatars/default.png'">
//             <div class="user-details">
//                 <h1><?= htmlspecialchars($userName) ?></h1>
//                 <span class="user-status" id="userStatus">Online</span>
//             </div>
//         </div>
//         <div class="header-actions">
//             <button class="btn-action" id="logoutChatBtn" title="Log out">
//                 <i class="fas fa-sign-out-alt"></i>
//             </button>            

//             <button class="btn-close" id="closeChatBtn" title="Close chat">
//                 <i class="fas fa-times"></i>
//             </button>
//         </div>
//     </div>
//     <main class="chat-main-grid">
//         <aside class="contacts-sidebar">
//             <div class="sidebar-header">
//                 <h2>Conversations</h2>
//                 <button class="btn-new-chat" id="newChatBtn" title="New chat">
//                     <i class="fas fa-plus"></i>
//                 </button>
//             </div>
//             <div class="contacts-list" id="contactsList">
//                 <div class="loading-contacts">
//                     <i class="fas fa-spinner fa-spin"></i> Loading contacts...
//                 </div>
//             </div>
//         </aside>
//         <section class="messages-area">
//             <div class="messages-header">
//                 <h3 id="currentChatName">Select a conversation</h3>
//             </div>
//             <div class="messages-container" id="messagesContainer">
//                 <div class="welcome-message">
//                     <i class="fas fa-comments"></i>
//                     <h3>Welcome to the Chat</h3>
//                     <p>Select a contact to start chatting</p>
//                 </div>
//             </div>
//             <div class="message-input-area" id="messageInputArea" style="display:none;">
//                 <input type="text" class="message-input" id="messageInput" placeholder="Write a message..." maxlength="1000">
//                 <button class="btn-send" id="sendBtn"><i class="fas fa-paper-plane"></i></button>
//             </div>
//         </section>
//         <aside class="chat-info-sidebar">
//             <h4>Information</h4>
//         </aside>
//     </main>
// </div>
// <script>
// window.chatConfig = {
//     userEmail: <?= json_encode($userEmail) ?>,
//     userName: <?= json_encode($userName) ?>,
//     userToken: <?= json_encode($userToken) ?>,
//     baseUrl: <?= json_encode(defined('APP_URL') ? APP_URL : '') ?>,
//     wsUrl: <?= json_encode('https://' . $_SERVER['HTTP_HOST'] . '/websocket') ?>,
//     authType: 'session'
// };
// </script>
// <?php
// exit();
// endif;

// === 9. Modo completo: página standalone (para móvil) ===
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Chat</title>
    <link rel="stylesheet" href="<?= RUTA_REAL ?>/app/views/inc/css/chat.css?v=<?= time() ?>">
</head>
<body>
    <div class="chat-app-container">
        <div class="chat-header">
            <div class="user-info">
                <img src="<?= RUTA_REAL ?>/app/views/img/avatars/<?= md5($userEmail) ?>.jpg" alt="Avatar"
                     class="user-avatar" onerror="this.src='<?= RUTA_REAL ?>/app/views/img/avatars/default.png'">
                <div class="user-details">
                    <h1><?= htmlspecialchars($userName) ?></h1>
                    <span class="user-status" id="userStatus">Online</span>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn-action" id="audioCallBtn" title="Audio call"><i class="fas fa-phone"></i></button>
                <button class="btn-action" id="videoCallBtn" title="Video call"><i class="fas fa-video"></i></button>
                <button class="btn-action" id="menuBtn" title="Menu"><i class="fas fa-ellipsis-v"></i></button>
                <button class="btn-close" id="closeChatBtn" title="Close chat"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <main class="chat-main-grid">
            <aside class="contacts-sidebar">
                <div class="sidebar-header">
                    <h2>Conversations</h2>
                    <button class="btn-new-chat" id="newChatBtn" title="New chat"><i class="fas fa-plus"></i></button>
                </div>
                <div class="contacts-list" id="contactsList">
                    <div class="loading-contacts"><i class="fas fa-spinner fa-spin"></i> Loading contacts...</div>
                </div>
            </aside>
            <section class="messages-area">
                <div class="messages-header">
                    <div class="current-chat-info">
                        <h3 id="currentChatName">Select a conversation</h3>
                        <span class="chat-status" id="chatStatus"></span>
                    </div>
                </div>
                <div class="messages-container" id="messagesContainer">
                    <div class="welcome-message">
                        <div class="welcome-icon"><i class="fas fa-comments"></i></div>
                        <h3>Welcome to the Chat</h3>
                        <p>Select a contact to start chatting</p>
                    </div>
                </div>
                <div class="message-input-area" id="messageInputArea" style="display:none;">
                    <div class="input-actions">
                        <button class="btn-attach" id="attachBtn" title="Attach file"><i class="fas fa-paperclip"></i></button>
                        <button class="btn-audio" id="audioMsgBtn" title="Audio message"><i class="fas fa-microphone"></i></button>
                    </div>
                    <input type="text" class="message-input" id="messageInput" placeholder="Write a message..." maxlength="1000">
                    <button class="btn-send" id="sendBtn" title="Send message"><i class="fas fa-paper-plane"></i></button>
                </div>
            </section>
            <aside class="chat-info-sidebar">
                <div class="info-content" id="chatInfo">
                    <h4>Information</h4>
                    <p>Select a conversation to see the details</p>
                </div>
            </aside>
        </main>
    </div>
    <script>
    window.chatConfig = {
        userEmail: <?= json_encode($userEmail) ?>,
        userName: <?= json_encode($userName) ?>,
        userToken: <?= json_encode($userToken) ?>,
        userId: <?= json_encode($user_id) ?>,
        baseUrl: <?= json_encode(defined('APP_URL') ? APP_URL : '') ?>,
        wsUrl: <?= json_encode('https://' . $_SERVER['HTTP_HOST'] . '/websocket') ?>,
        authType: <?= json_encode(isset($_GET['access_key']) ? 'token' : 'session') ?>
    };
    </script>
    <script src="<?= RUTA_REAL ?>/app/views/inc/js/chat.js?v=<?= time() ?>"></script>
</body>
</html>