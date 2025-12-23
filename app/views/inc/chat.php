<?php
// Evitar carga en móviles o cuando ya estamos en el chat
if (isset($_GET['access_key']) || (isset($url[0]) && $url[0] === 'chat')) {
    return;
}
?>
<!-- Botón flotante de chat -->
<div id="btn-chat-toggle" class="chat_flot" title="Open Chat" style="display: none;">💬</div>
<div id="chatModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.6); z-index: 20000;"></div>

<script>
window.loadChatModal = function() {
    const modal = document.getElementById('chatModal');
    const btn = document.getElementById('btn-chat-toggle');

    // ✅ Limpiar modal siempre
    modal.innerHTML = '';
    modal.style.display = 'flex';

    fetch('<?= RUTA_REAL ?>/?chat=1&check=1&_nocache=' + Date.now())
        .then(res => res.text())
        .then(text => {
            if (!text.trim()) throw new Error('Empty response from session check');
            if (text.trim().startsWith('<')) throw new Error('HTML received instead of JSON');
            return JSON.parse(text);
        })
        .then(checkData => {
            if (checkData.valid) {
                // === Renderizar el chat ===
                modal.innerHTML = `
                <div class="chat-app-container" style="width:100%;height:100%;margin:0;padding:0;background:white;">
                    <div class="chat-header">
                        <div class="user-info">
                            <img src="<?= RUTA_REAL ?>/app/views/img/avatars/${btoa(checkData.userEmail) || 'default'}.jpg" 
                                class="user-avatar" onerror="this.src='<?= RUTA_REAL ?>/app/views/img/avatars/default.png'">
                            <div class="user-details">
                                <h1>${checkData.userName}</h1>
                            </div>
                        </div>
                        <div class="header-actions">
                            <button class="btn-action" id="logoutChatBtn" title="Log out">
                                <i class="fas fa-sign-out-alt"></i>
                            </button>
                            <button class="btn-close" id="closeChatBtn" title="Close chat">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <main class="chat-main-grid">
                        <aside class="contacts-sidebar">
                            <div class="sidebar-header">
                                <h2>Conversations</h2>
                                <button class="btn-new-chat" id="newChatBtn" title="New chat">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="contacts-list" id="contactsList">
                                <div class="loading-contacts">
                                    <i class="fas fa-spinner fa-spin"></i> Loading contacts...
                                </div>
                            </div>
                        </aside>
                        <section class="messages-area">
                            <div class="messages-header">
                                <h3 id="currentChatName">Select a conversation</h3>
                            </div>
                            <div class="messages-container" id="messagesContainer">
                                <div class="welcome-message">
                                    <i class="fas fa-comments"></i>
                                    <h3>Welcome to the Chat</h3>
                                    <p>Select a contact to start chatting</p>
                                </div>
                            </div>
                            <div class="message-input-area" id="messageInputArea" style="display:none;">
                                <div class="format-toolbar">
                                    <button type="button" class="format-btn" data-command="bold" title="Bold">B</button>
                                    <button type="button" class="format-btn" data-command="italic" title="Italic">I</button>
                                    <button type="button" class="format-btn" data-command="underline" title="Underline">U</button>
                                    <button type="button" class="format-btn" data-command="strikeThrough" title="Strikethrough">S</button>
                                    <button type="button" class="format-btn2" data-command="bulletList" title="Bullet List">•</button>
                                    <button type="button" class="format-btn2" data-command="orderedList" title="Numbered List">1.</button>
                                    <button type="button" class="format-btn2" id="insertImageFile" title="Insert image from device">🖼️</button>
                                    <button type="button" class="format-btn2" id="attachFile" title="Attach File">📎</button>
                                </div>
                                <div class="input-with-send">
                                    <div 
                                        id="messageInput" 
                                        contenteditable="true"
                                        class="message-textarea"
                                        placeholder="Write a message..."
                                    ></div>
                                    <button class="btn-send" id="sendBtn" title="Send message">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                        </section>
                    </main>
                </div>
                `;

                window.chatConfig = {
                    userEmail: checkData.userEmail,
                    userName: checkData.userName,
                    userToken: checkData.userToken,
                    baseUrl: '<?= RUTA_REAL ?>',
                    authType: 'session'
                };

                const chatScript = document.createElement('script');
                // Usar variables globales definidas en PHP
                chatScript.src = window.CHAT_CONFIG.baseUrl + '/app/views/inc/js/chat.js?v=' + window.CHAT_CONFIG.timestamp;

                chatScript.onload = () => {
                    if (typeof GreenTrackChat !== 'undefined') {
                        window.chatApp = new GreenTrackChat();
                    }
                };
                document.head.appendChild(chatScript);

                const loadTiptapScripts = () => {
                    return new Promise((resolve, reject) => {
                        let loaded = 0;
                        const total = 2;
                        const onLoad = () => {
                            loaded++;
                            if (loaded === total) resolve();
                        };

                        const coreScript = document.createElement('script');
                        coreScript.src = '<?= RUTA_REAL ?>/app/views/inc/js/tiptap/core.js';
                        coreScript.onload = onLoad;
                        coreScript.onerror = reject;
                        document.head.appendChild(coreScript);

                        const starterScript = document.createElement('script');
                        starterScript.src = '<?= RUTA_REAL ?>/app/views/inc/js/tiptap/starter-kit.js';
                        starterScript.onload = onLoad;
                        starterScript.onerror = reject;
                        document.head.appendChild(starterScript);
                    });
                };

            } else {
                // === Renderizar login ===
                modal.innerHTML = `
                    <div class="login">
                        <h1>Member Login</h1>
                        <form id="loginForm" method="post" class="form login-form" action="<?= RUTA_APP ?>/app/ajax/usuariosAjax.php">
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
                `;

                const loginScript = document.createElement('script');
                loginScript.textContent = `
                    document.getElementById('loginForm').addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const btn = e.target.querySelector('.btn-login');
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validating...';
                        btn.disabled = true;
                        try {
                            const res = await fetch(e.target.action, {
                                method: 'POST',
                                body: new FormData(e.target),
                                credentials: 'same-origin',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });
                            const text = await res.text();
                            if (!text.trim()) throw new Error('Empty response');
                            if (text.trim().startsWith('<')) throw new Error('Server error');
                            const json = JSON.parse(text);
                            if (json.success) {
                                // Desbloquear audio
                                const audio = new Audio('/app/views/sounds/new-notification-010-352755.mp3');
                                audio.play().catch(() => {}).then(() => {
                                    audio.pause();
                                    audio.currentTime = 0;
                                });

                                // ✅ Recargar el mismo modal con el chat
                                window.loadChatModal();
                            } else {
                                alert(json.message || 'Authentication failed');
                            }
                        } catch (err) {
                            console.error('Login error:', err);
                            alert('Login failed. Check console.');
                        } finally {
                            btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
                            btn.disabled = false;
                        }
                    });
                `;
                modal.appendChild(loginScript);
            }
        })
        .catch(e => {
            console.error('Error al cargar el chat:', e);
            modal.innerHTML = '<div style="padding:2rem;text-align:center;color:red;">Error loading chat.</div>';
            if (btn) btn.style.display = 'flex';
        });
};

// Inicializar botón flotante
if (window.innerWidth > 768) {
    document.getElementById('btn-chat-toggle').style.display = 'flex';
}
document.getElementById('btn-chat-toggle').addEventListener('click', window.loadChatModal);

// Variables globales seguras
window.CHAT_CONFIG = window.CHAT_CONFIG || {};
Object.assign(window.CHAT_CONFIG, {
    baseUrl: '<?= RUTA_REAL ?>',
    timestamp: <?= time() ?>
});

// Verificación global de mensajes no leídos (incluso si el chat no se abre)
if (window.chatConfig?.userToken) {
    const checkUnread = async () => {
        try {
            const res = await fetch('./app/ajax/contactsAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_contacts: 'unread_count',
                    token: window.chatConfig.userToken
                })
            });
            const data = await res.json();
            const count = data.success ? data.count : 0;

            // Actualizar badge
            const btn = document.getElementById('btn-chat-toggle');
            if (btn) {
                const badge = btn.querySelector('.chat-unread-badge');
                if (badge) badge.remove();
                if (count > 0) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'chat-unread-badge';
                    newBadge.textContent = count > 99 ? '99+' : count;
                    btn.appendChild(newBadge);
                }
            }

            // Actualizar título
            let cleanTitle = document.title.replace(/^\(\d+\+?\)\s*/, '');
            if (count > 0) {
                document.title = `(${count > 99 ? '99+' : count}) ${cleanTitle}`;
            } else {
                document.title = cleanTitle;
            }

        } catch (e) {
            console.error('Global unread check failed:', e);
        }
    };

    setInterval(checkUnread, 10000);
    checkUnread();
}
</script>