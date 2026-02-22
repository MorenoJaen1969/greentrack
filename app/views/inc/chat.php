<?php
// Evitar carga en móviles o cuando ya estamos en el chat
if (isset($_GET['access_key']) || (isset($url[0]) && $url[0] === 'chat')) {
    return;
}
?>
<!-- Botón flotante de chat -->
<!-- Botón flotante de chat -->
<style>
    /* Posiciones permitidas para el botón de chat */
    .chat-float-btn {
        position: fixed;
        z-index: 20010;
        display: none;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #0b93f6;
        color: white;
        cursor: pointer;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
        font-size: 20px;
    }

    .chat-pos-bottom-right {
        bottom: 24px;
        right: 24px;
    }

    .chat-pos-bottom-left {
        bottom: 24px;
        left: 24px;
    }

    .chat-pos-top-right {
        top: 24px;
        right: 24px;
    }

    .chat-pos-top-left {
        top: 24px;
        left: 24px;
    }

    .chat-unread-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        background: #ff3b30;
        color: #fff;
        border-radius: 12px;
        padding: 2px 6px;
        font-size: 11px;
    }

    .chat-tooltip {
        position: fixed;
        z-index: 20020;
        background: rgba(0, 0, 0, 0.85);
        color: #fff;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 13px;
        opacity: 0;
        transition: opacity .18s ease, transform .18s ease;
        transform: translateY(4px);
        pointer-events: none;
    }

    .chat-tooltip.visible {
        opacity: 1;
        transform: translateY(0);
    }
</style>
<div id="btn-chat-toggle" class="chat-float-btn chat-pos-bottom-right" title="Open Chat">💬</div>
<div id="chatModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.6); z-index: 20000;">
</div>

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
                            <div class="avatar-container" style="position:relative;display:inline-block;">
                                <img id="userAvatarHeader" src="<?= RUTA_REAL ?>/app/views/img/avatars/${btoa(checkData.userEmail) || 'default'}.jpg" 
                                    class="user-avatar" onerror="this.src='<?= RUTA_REAL ?>/app/views/img/avatars/default.png'">
                                <button id="editUserAvatarBtn" 
                                    title="Change your profile picture"
                                    style="
                                        position: absolute;
                                        bottom: 4px;
                                        right: 4px;
                                        width: 24px;
                                        height: 24px;
                                        border-radius: 50%;
                                        background: rgba(0,0,0,0.7);
                                        border: 2px solid white;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        cursor: pointer;
                                        padding: 0;
                                        z-index: 10;
                                    ">
                                    <i class="fas fa-camera" style="color:white;font-size:12px;"></i>
                                </button>
                            </div>
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
                                <!-- <button class="btn-new-chat" id="newChatBtn" title="New chat">
                                    <i class="fas fa-plus"></i>
                                </button> -->
                            </div>

                            <!-- Sidebar de salas -->
                            <div id="roomsSidebar" class="rooms-sidebar">
                                <div class="solo_titulo">
                                    <h5>Rooms</h5>
                                </div>
                                <div id="roomsList" class="rooms-list">
                                    <!-- Aquí se cargarán las salas -->
                                </div>
                            </div>
                            <!-- Área de mensajes -->
                            <div id="chatArea" class="chat-area">
                                <!-- Mensajes de la sala seleccionada -->
                            </div>
                            
                            <div class="contacts-list" id="contactsList">
                                <div class="loading-contacts">
                                    <i class="fas fa-spinner fa-spin"></i> Loading contacts...
                                </div>
                            </div>
                        </aside>
                        <section class="messages-area">
                            <div class="messages-header">
                                <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
                                    <div style="display:flex;flex-direction:column;">
                                        <div style="font-size:14px;color:#666;">Room: <strong id="currentRoomName">None</strong></div>
                                        <div style="font-size:14px;color:#666;">User: <strong id="currentUserName">None</strong></div>
                                    </div>
                                    <div id="broadcastCheckboxContainer" style="display:flex;align-items:center;gap:8px;">
                                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;color:#333;font-size:14px;">
                                            <input type="checkbox" id="broadcastCheckbox" style="width:16px;height:16px;" />
                                            <span>Group message</span>
                                        </label>
                                    </div>
                                </div>
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
                        userId: checkData.userId,
                        baseUrl: '<?= RUTA_REAL ?>',
                        authType: 'session'
                    };

                    // ✅ Añadir listener al botón de avatar
                    document.getElementById('editUserAvatarBtn').addEventListener('click', (e) => {
                        e.stopPropagation();
                        const input = document.createElement('input');
                        input.type = 'file';
                        input.accept = 'image/jpeg,image/png,image/jpg';
                        input.onchange = async (ev) => {
                            const file = ev.target.files[0];
                            if (!file) return;
                            if (file.size > 2 * 1024 * 1024) {
                                alert('Image must be under 2MB');
                                return;
                            }

                            const formData = new FormData();
                            formData.append('avatar', file);
                            formData.append('email', checkData.userEmail); // para btoa
                            formData.append('token', checkData.userToken);
                            formData.append('modulo_usuarios', 'changeAvatar');

                            try {
                                const res = await fetch('<?= RUTA_REAL ?>/app/ajax/usuariosAjax.php', {
                                    method: 'POST',
                                    body: formData
                                });
                                const data = await res.json();
                                if (data.success) {
                                    const avatar = document.getElementById('userAvatarHeader');
                                    if (avatar) {
                                        // Usar btoa del email + timestamp
                                        const encodedEmail = btoa(checkData.userEmail);
                                        avatar.src = `<?= RUTA_REAL ?>/app/views/img/avatars/${btoa(encodedEmail)}.jpg?t=${Date.now()}`;
                                    }
                                } else {
                                    alert('Error uploading avatar');
                                }
                            } catch (err) {
                                alert('Upload failed');
                            }
                        };
                        input.click();
                    });

                    const chatScript = document.createElement('script');
                    // Usar variables globales definidas en PHP
                    chatScript.src = window.CHAT_CONFIG.baseUrl + '/app/views/inc/js/chat.js?v=' + window
                        .CHAT_CONFIG.timestamp;

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
                            starterScript.src =
                                '<?= RUTA_REAL ?>/app/views/inc/js/tiptap/starter-kit.js';
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
                modal.innerHTML =
                    '<div style="padding:2rem;text-align:center;color:red;">Error loading chat.</div>';
                if (btn) btn.style.display = 'flex';
            });
    };

    // Inicializar botón flotante y aplicar posición configurable
    const _chatBtn = () => document.getElementById('btn-chat-toggle');

    function applyChatButtonPosition() {
        const btn = _chatBtn();
        if (!btn) return;

        const configured = (document.body && document.body.dataset && document.body.dataset.chatButtonPosition) ||
            (window.CHAT_CONFIG && window.CHAT_CONFIG.buttonPosition) ||
            'bottom-right';

        const map = {
            'bottom-right': 'chat-pos-bottom-right',
            'bottom-left': 'chat-pos-bottom-left',
            'top-right': 'chat-pos-top-right',
            'top-left': 'chat-pos-top-left'
        };

        Object.values(map).forEach(c => btn.classList.remove(c));
        const cls = map[configured] || map['bottom-right'];
        btn.classList.add(cls);
    }

    window.addEventListener('DOMContentLoaded', applyChatButtonPosition);
    // permitir cambios dinámicos desde otras vistas mediante asignación a window.CHAT_CONFIG.buttonPosition
    window.CHAT_CONFIG = window.CHAT_CONFIG || {};
    const _initialChatBtnPos = (window.CHAT_CONFIG && window.CHAT_CONFIG.buttonPosition) || (document.body && document.body.dataset && document.body.dataset.chatButtonPosition) || null;
    Object.defineProperty(window.CHAT_CONFIG, 'buttonPosition', {
        configurable: true,
        set: function(val) {
            this._buttonPosition = val;
            applyChatButtonPosition();
        },
        get: function() {
            return this._buttonPosition;
        }
    });

    if (_initialChatBtnPos) window.CHAT_CONFIG.buttonPosition = _initialChatBtnPos;

    const btn = _chatBtn();

    function setChatButtonVisible(visible) {
        const b = _chatBtn();
        if (!b) return;
        if (visible) {
            if (window.innerWidth > 768) b.style.display = 'flex';
            else b.style.display = 'none';
        } else {
            b.style.display = 'none';
        }
    }

    function closeChatModal() {
        const modal = document.getElementById('chatModal');
        if (!modal) return;
        modal.style.display = 'none';
        modal.innerHTML = '';
        setChatButtonVisible(true);
        if (window.chatApp && typeof window.chatApp.destroy === 'function') {
            try {
                window.chatApp.destroy();
            } catch (e) {
                /* ignore */
            }
        }
    }

    // Drag & drop support + persistence
    const STORAGE_KEY = 'greentrack_chat_btn_pos';

    function applyStoredPosition(btn) {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return false;
            const pos = JSON.parse(raw);
            if (pos && typeof pos.left === 'number' && typeof pos.top === 'number') {
                btn.style.left = pos.left + 'px';
                btn.style.top = pos.top + 'px';
                btn.style.right = 'auto';
                btn.style.bottom = 'auto';
                // remove positional classes
                btn.classList.remove('chat-pos-bottom-right', 'chat-pos-bottom-left', 'chat-pos-top-right', 'chat-pos-top-left');
                return true;
            }
        } catch (e) {
            /* ignore */
        }
        return false;
    }

    if (btn) {
        // Apply stored position if available
        applyStoredPosition(btn);

        // Show/hide based on viewport
        setChatButtonVisible(true);

        let dragging = false;
        let startX = 0,
            startY = 0,
            offsetX = 0,
            offsetY = 0;
        let moved = false;

        const threshold = 6; // px to consider as drag

        function onPointerDown(clientX, clientY) {
            const rect = btn.getBoundingClientRect();
            dragging = true;
            startX = clientX;
            startY = clientY;
            offsetX = clientX - rect.left;
            offsetY = clientY - rect.top;
            moved = false;
            document.body.style.userSelect = 'none';
        }

        function onPointerMove(clientX, clientY) {
            if (!dragging) return;
            const dx = clientX - startX;
            const dy = clientY - startY;
            if (!moved && Math.hypot(dx, dy) > threshold) moved = true;

            const left = clientX - offsetX;
            const top = clientY - offsetY;
            btn.style.left = Math.max(8, Math.min(window.innerWidth - btn.offsetWidth - 8, left)) + 'px';
            btn.style.top = Math.max(8, Math.min(window.innerHeight - btn.offsetHeight - 8, top)) + 'px';
            btn.style.right = 'auto';
            btn.style.bottom = 'auto';
            // remove positional classes while free-moving
            btn.classList.remove('chat-pos-bottom-right', 'chat-pos-bottom-left', 'chat-pos-top-right', 'chat-pos-top-left');
        }

        function onPointerUp() {
            if (!dragging) return;
            dragging = false;
            document.body.style.userSelect = '';
            // persist position if moved
            if (moved) {
                try {
                    const rect = btn.getBoundingClientRect();
                    localStorage.setItem(STORAGE_KEY, JSON.stringify({
                        left: rect.left,
                        top: rect.top
                    }));
                } catch (e) {
                    /* ignore */
                }
            }
        }

        // Mouse events
        btn.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return;
            e.preventDefault();
            onPointerDown(e.clientX, e.clientY);
        });
        document.addEventListener('mousemove', (e) => {
            onPointerMove(e.clientX, e.clientY);
        });
        document.addEventListener('mouseup', (e) => {
            onPointerUp();
        });

        // Touch events
        btn.addEventListener('touchstart', (e) => {
            const t = e.touches[0];
            if (!t) return;
            onPointerDown(t.clientX, t.clientY);
        }, {
            passive: true
        });
        document.addEventListener('touchmove', (e) => {
            const t = e.touches[0];
            if (!t) return;
            onPointerMove(t.clientX, t.clientY);
        }, {
            passive: true
        });
        document.addEventListener('touchend', (e) => {
            onPointerUp();
        });

        // Click: only open modal if not dragged
        btn.addEventListener('click', (e) => {
            if (moved) {
                // reset moved after click to avoid immediate re-open
                moved = false;
                return;
            }
            setChatButtonVisible(false);
            window.loadChatModal();
        });

        // Close modal on overlay click
        const modal = document.getElementById('chatModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeChatModal();
            });
        }

        // Resize: if modal not open, restore button visibility
        window.addEventListener('resize', () => {
            const modalEl = document.getElementById('chatModal');
            const open = modalEl && modalEl.style && modalEl.style.display && modalEl.style.display !== 'none';
            if (!open) setChatButtonVisible(true);
        });

        // Tooltip hint (one-time): indicate that the button can be dragged
        const TOOLTIP_KEY = 'greentrack_chat_btn_tooltip_shown';
        if (!localStorage.getItem(TOOLTIP_KEY)) {
            const showTooltip = () => {
                try {
                    const rect = btn.getBoundingClientRect();
                    const tip = document.createElement('div');
                    tip.id = 'chatTooltip';
                    tip.className = 'chat-tooltip';
                    tip.textContent = 'Drag to move';
                    document.body.appendChild(tip);
                    // Position tooltip centered above the button (or below if not enough space)
                    const tipRect = tip.getBoundingClientRect();
                    let left = rect.left + (rect.width - tipRect.width) / 2;
                    left = Math.max(8, Math.min(window.innerWidth - tipRect.width - 8, left));
                    let top = rect.top - tipRect.height - 8;
                    if (top < 8) top = rect.bottom + 8;
                    tip.style.left = left + 'px';
                    tip.style.top = top + 'px';
                    // Animate in
                    setTimeout(() => tip.classList.add('visible'), 50);

                    const removeTip = () => {
                        if (tip.parentNode) tip.parentNode.removeChild(tip);
                    };

                    btn.addEventListener('mousedown', removeTip, {
                        once: true
                    });
                    btn.addEventListener('touchstart', removeTip, {
                        once: true
                    });
                    // Auto remove after 5s
                    setTimeout(removeTip, 5000);
                    localStorage.setItem(TOOLTIP_KEY, '1');
                } catch (e) {
                    /* ignore */
                }
            };
            setTimeout(showTooltip, 600);
        }
    }

    // Variables globales seguras
    window.CHAT_CONFIG = window.CHAT_CONFIG || {};
    Object.assign(window.CHAT_CONFIG, {
        baseUrl: '<?= RUTA_REAL ?>',
        timestamp: <?= time() ?>
    });

    // Verificación global de mensajes no leídos (incluso si el chat no se abre)
    (function startGlobalUnreadPolling() {
        const checkUnread = async () => {
            try {
                const res = await fetch('<?= RUTA_REAL ?>/app/ajax/contactsAjax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        modulo_contacts: 'unread_count'
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
    })();
</script>