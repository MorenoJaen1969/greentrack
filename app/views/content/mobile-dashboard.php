<?php
    $usuario = $_SESSION['user_name'];
?>
<header class="mobile-header">
    <div class="header-icon">
        <img src="/app/views/img/logo.jpg" alt="Sergio's Landscape" class="logo">
    </div>
    <div class="header-title">
        <h2>🟢 GreenTrack Live</h2>
        <p>Welcome, <?= $usuario ?></p> 
    </div>
    <div class="header-message">
        <button id="btn-messages" type="button" aria-label="Internal messages" class="msg-btn default">
            <svg class="svg-msg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 30" fill="currentColor">
                <path
                    d="M4.913 2.658c2.075-.27 4.19-.408 6.337-.408 2.147 0 4.262.139 6.337.408 1.922.25 3.291 1.861 3.405 3.727a4.403 4.403 0 0 0-1.032-.211 50.89 50.89 0 0 0-8.42 0c-2.358.196-4.04 2.19-4.04 4.434v4.286a4.47 4.47 0 0 0 2.433 3.984L7.28 21.53A.75.75 0 0 1 6 21v-4.03a48.527 48.527 0 0 1-1.087-.128C2.905 16.58 1.5 14.833 1.5 12.862V6.638c0-1.97 1.405-3.718 3.413-3.979Z" />
                <path
                    d="M15.75 7.5c-1.376 0-2.739.057-4.086.169C10.124 7.797 9 9.103 9 10.609v4.285c0 1.507 1.128 2.814 2.67 2.94 1.243.102 2.5.157 3.768.165l2.782 2.781a.75.75 0 0 0 1.28-.53v-2.39l.33-.026c1.542-.125 2.67-1.433 2.67-2.94v-4.286c0-1.505-1.125-2.811-2.664-2.94A49.392 49.392 0 0 0 15.75 7.5Z" />
            </svg>
        </button>
    </div>
</header>

<div class="container">
    <!-- COLUMNA IZQUIERDA: Lista de camionetas -->
    <div id="lista-camionetas">
        <!-- Barra de estado debajo del header -->
        <div class="fleet-status-bar">
            <div class='fleet-btn_mobile'>
                <button type="button" id="menu-donde-esta" class="btn_mobile">
                    📍 Where are?
                </button>

                <div>
                    <span id="fleet-date-label">Active Fleet Today</span>

                    <!-- Contenedor para superponer input sobre botón -->
                    <div style="
                            position: relative;
                            display: inline-block; 
                            width: 40px;
                            height: 40px;
                        ">
                        <!-- Botón visible (decorativo) -->
                        <button type="button" id="btn-calendar-fake" style="
                                background: none;
                                border: none;
                                font-size: 1.4em;
                                cursor: pointer;
                                width: 100%;
                                height: 100%;
                                margin: 0;
                                padding: 0;
                                color: #0066FF;
                            " aria-label="Select date">
                                    🗓️
                        </button>

                        <!-- Input real, encima (pero invisible) -->
                        <input type="date" id="calendar-input" style="
                                position: absolute;
                                top: 0;
                                left: 0;
                                width: 100%;
                                height: 100%;
                                opacity: 0;
                                cursor: pointer;
                                z-index: 2;
                            " value="">
                    </div>
                </div>
            </div>
        </div>
        <div id="camionetas-list"></div>
    </div>


    <!-- COLUMNA DERECHA: Estado detallado -->
    <div id="estado-detallado">
        <p style="color:#888; text-align:center;">Select a truck</p>
    </div>
</div>

<!-- Modal: Selección y visualización de vehículo -->
<div id="modal-donde-esta" class="modal-overlay_gps modal_mobile" style="display:none; align-items:flex-start; justify-content:center;">
    <div class="modal-contenedor">
        <button id="close_modal_donde_esta" class="modal-cerrar1">✕</button>
        <div id="contenido-modal-donde-esta">
            <h3>Select a vehicle</h3>
            <select id="select-vehiculo-donde-esta" style="width:100%;margin-bottom:16px;"></select>
            <div id="info-vehiculo-donde-esta"></div>
        </div>
    </div>
</div>

<!-- Modal del Chat Integrado -->
<div id="chat-modal" class="chat-modal-overlay" style="display: none;">
    <div class="chat-modal-container">
        <!-- Header del Chat Modal -->
        <div class="chat-modal-header">
            <button id="close-chat-modal" class="chat-close-btn">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div class="chat-modal-title">
                <h3>GreenTrack Chat</h3>
                <span class="chat-user-status" id="chat-user-status">Connecting...</span>
            </div>
            <div class="chat-header-actions">
                <button class="chat-action-btn" id="chat-audio-call">
                    <i class="fas fa-phone"></i>
                </button>
                <button class="chat-action-btn" id="chat-video-call">
                    <i class="fas fa-video"></i>
                </button>
                <button class="chat-action-btn" id="close_chat">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <!-- Contenido del Chat -->
        <div class="chat-modal-content" id="chat-modal-content">
            <!-- Aquí se cargará dinámicamente el contenido del chat -->
            <div class="chat-loading">
                <i class="fas fa-spinner fa-spin"></i> Loading chat...
            </div>
        </div>
    </div>
</div>

<script>
    // Configuración del chat para mobile
    window.chatMobileConfig = {
        userEmail: '<?php echo $_SESSION['user_email'] ?? ''; ?>',
        userName: '<?php echo $_SESSION['user_name'] ?? ''; ?>',
        userToken: '<?php echo $_SESSION['token'] ?? ''; ?>',
        userId: '<?php echo $_SESSION['user_id'] ?? ''; ?>',
        baseUrl: '<?php echo APP_URL; ?>',
        // ✅ CORRECTO: Usar la ruta proxy de Apache
        wsUrl: 'wss://<?php echo $_SERVER['HTTP_HOST']; ?>/websocket'
    };

    // Control del modal del chat
    class ChatModal {
        constructor() {
            this.modal = document.getElementById('chat-modal');
            this.content = document.getElementById('chat-modal-content');
            this.isOpen = false;
            this.chatInstance = null;
            
            this.initializeEvents();
        }

        initializeEvents() {
            // Botón de mensajes en el header
            const btnMessages = document.getElementById('btn-messages');
            if (btnMessages) {
                btnMessages.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.open();
                });
            }

            // Botón cerrar
            const closeBtn = document.getElementById('close-chat-modal');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    this.close();
                });
            }

            // Cerrar con ESC
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });
        }

        open() {
            if (this.isOpen) return;
            
            this.modal.style.display = 'flex';
            this.isOpen = true;
            
            // Cargar contenido del chat
            this.loadChatContent();
            
            // Prevenir scroll del body
            document.body.style.overflow = 'hidden';
        }

        close() {
            this.modal.style.display = 'none';
            this.isOpen = false;
            
            // Restaurar scroll del body
            document.body.style.overflow = '';
            
            // Limpiar instancia del chat si existe
            if (this.chatInstance) {
                this.chatInstance.disconnect();
                this.chatInstance = null;
            }
        }

        loadChatContent() {
            // Verificar autenticación
            if (!window.chatMobileConfig.userEmail || !window.chatMobileConfig.userToken) {
                this.content.innerHTML = `
                    <div style="padding: 20px; text-align: center; color: #666;">
                        <p>🔐 Authentication error</p>
                        <p>Your identity could not be verified.</p>
                    </div>
                `;
                return;
            }

            // Cargar interfaz del chat
            this.content.innerHTML = this.getChatHTML();
            
            // Inicializar chat
            setTimeout(() => {
                this.initializeChat();
            }, 100);
        }

        getChatHTML() {
            return `
                <div class="mobile-chat-interface">
                    <!-- Sidebar de contactos (oculto inicialmente en mobile) -->
                    <div class="mobile-contacts-sidebar" id="mobile-contacts-sidebar" style="display: none;">
                        <div class="mobile-contacts-header">
                            <h4>Conversations</h4>
                            <button class="mobile-new-chat-btn">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="mobile-contacts-list" id="mobile-contacts-list">
                            <div class="loading-contacts">
                                <i class="fas fa-spinner fa-spin"></i> Loading contacts...
                            </div>
                        </div>
                    </div>

                    <!-- Área principal de mensajes -->
                    <div class="mobile-messages-area">
                        <div class="mobile-messages-container" id="mobile-messages-container">
                            <div class="welcome-message">
                                <div class="welcome-icon">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <h4>Welcome to the Chat</h4>
                                <p>Select a contact to begin.</p>
                            </div>
                        </div>
                        
                        <!-- Input de mensajes -->
                        <div class="mobile-message-input" id="mobile-message-input" style="display: none;">
                            <div class="mobile-input-actions">
                                <button class="mobile-attach-btn">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                                <button class="mobile-audio-btn">
                                    <i class="fas fa-microphone"></i>
                                </button>
                            </div>
                            <input type="text" 
                                class="mobile-message-input-field" 
                                id="mobile-message-input-field" 
                                placeholder="Write a message..."
                                maxlength="1000">
                            <button class="mobile-send-btn" id="mobile-send-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        initializeChat() {
            // Aquí inicializarías la lógica del chat para mobile
            console.log('🚀 Inicializando chat móvil para:', window.chatMobileConfig.userName);
            
            // Conectar WebSocket
            this.connectWebSocket();
            
            // Cargar contactos
            this.loadContacts();
            
            // Configurar event listeners
            this.setupChatEvents();
        }

        connectWebSocket() {
            try {
                const ws = new WebSocket(`${window.chatMobileConfig.wsUrl}?token=${window.chatMobileConfig.userToken}`);
                
                ws.onopen = () => {
                    this.updateStatus('Connected', 'success');
                    console.log('✅ WebSocket conectado en móvil');
                };

                ws.onmessage = (event) => {
                    this.handleMessage(JSON.parse(event.data));
                };

                ws.onclose = () => {
                    this.updateStatus('Offline', 'error');
                    console.log('🔌 WebSocket desconectado');
                };

                this.ws = ws;
                
            } catch (error) {
                console.error('Error conectando WebSocket:', error);
                this.updateStatus('Connection error', 'error');
            }
        }

        updateStatus(status, type) {
            const statusEl = document.getElementById('chat-user-status');
            if (statusEl) {
                statusEl.textContent = status;
                statusEl.className = `chat-user-status status-${type}`;
            }
        }

        async loadContacts() {
            try {
                const res = await fetch('/app/ajax/contactsAjax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${window.chatMobileConfig.userToken}`
                    },
                    body: JSON.stringify({
                        modulo_contacts: 'contactos'
                    })
                });

                if (res.ok) {
                    const respuesta = await res.json();
                    if (respuesta.success && respuesta.data?.contacts) {
                        const contactsList = document.getElementById('mobile-contacts-list');
                        if (contactsList) {
                            contactsList.innerHTML = respuesta.data.contacts.map(contact => `
                                <div class="contact-item-mobile" data-user-id="${contact.id}">
                                    <div class="contact-avatar-mobile">${contact.nombre.charAt(0)}</div>
                                    <div class="contact-info-mobile">
                                        <div class="contact-name-mobile">${this.escapeHTML(contact.nombre)}</div>
                                        <div class="contact-status-mobile">
                                            ${contact.status === 'online' ? '🟢 Online' : '⚫ Offline'}
                                        </div>
                                    </div>
                                </div>
                            `).join('');
                        }
                    }
                } else {
                    console.error('Error al cargar contactos:', res.status);
                }
            } catch (error) {
                console.error('Error en loadContacts:', error);
            }
        }
        
        setupChatEvents() {
            // Configurar eventos del chat móvil
            const sendBtn = document.getElementById('mobile-send-btn');
            const inputField = document.getElementById('mobile-message-input-field');
            
            if (sendBtn && inputField) {
                sendBtn.addEventListener('click', () => this.sendMessage());
                inputField.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        this.sendMessage();
                    }
                });
            }
        }

        sendMessage() {
            const input = document.getElementById('mobile-message-input-field');
            const message = input.value.trim();
            
            if (!message || !this.ws || this.ws.readyState !== WebSocket.OPEN) {
                return;
            }

            const messageData = {
                type: 'message',
                content: message,
                to: 'all', // Enviar a todos por ahora
                room: 'general',
                timestamp: Math.floor(Date.now() / 1000)
            };

            this.ws.send(JSON.stringify(messageData));
            input.value = '';
            
            // Mostrar mensaje localmente
            this.displayMessage({
                ...messageData,
                user: {
                    email: window.chatMobileConfig.userEmail,
                    nombre: window.chatMobileConfig.userName
                }
            });
        }

        displayMessage(message) {
            const container = document.getElementById('mobile-messages-container');
            if (!container) return;

            const messageElement = document.createElement('div');
            messageElement.className = `mobile-message ${message.user.email === window.chatMobileConfig.userEmail ? 'own-message' : 'other-message'}`;
            
            messageElement.innerHTML = `
                <div class="mobile-message-content">
                    <div class="mobile-message-sender">${message.user.email === window.chatMobileConfig.userEmail ? 'Tú' : message.user.nombre}</div>
                    <div class="mobile-message-text">${this.escapeHTML(message.content)}</div>
                    <div class="mobile-message-time">${this.formatTime(message.timestamp)}</div>
                </div>
            `;

            container.appendChild(messageElement);
            container.scrollTop = container.scrollHeight;
        }

        handleMessage(data) {
            switch (data.type) {
                case 'message':
                    this.displayMessage(data);
                    break;
                case 'user_joined':
                    this.showSystemMessage(`${data.user.nombre} joined the chat`);
                    break;
                default:
                    console.log('Mensaje no manejado:', data);
            }
        }

        showSystemMessage(text) {
            const container = document.getElementById('mobile-messages-container');
            if (!container) return;

            const systemMsg = document.createElement('div');
            systemMsg.className = 'mobile-system-message';
            systemMsg.textContent = text;
            container.appendChild(systemMsg);
            container.scrollTop = container.scrollHeight;
        }

        escapeHTML(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        formatTime(timestamp) {
            return new Date(timestamp * 1000).toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        disconnect() {
            if (this.ws) {
                this.ws.close();
            }
        }
    }

    async function cargarConfigYIniciar() {
        try {
            const response = await fetch('/app/ajax/datosgeneralesAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_DG: 'datos_para_gps'
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            // Asignar valor por defecto
            const DEFAULT_CONFIG = {
                mapa_base: 'ESRI',
                umbral_metros: 150,
                umbral_minutos: 5,
                umbral_course: 10
            };

            if (data.error) {
                console.warn('⚠️ Error en datosgeneralesAjax:', data.error);
            } else {
                // === Variable global para almacenar configuración crítica ===
                window.APP_CONFIG = window.APP_CONFIG || {};
                // Guardar en configuración global
                // Dentro del fetch, tras obtener y hacer .json() a la respuesta

                if (data.success && data.config) {
                    window.APP_CONFIG = {
                        mapa_base: data.config.mapa_base || DEFAULT_CONFIG.mapa_base,
                        umbral_metros: parseInt(data.config.umbral_metros, 10) || DEFAULT_CONFIG.umbral_metros,
                        umbral_minutos: parseInt(data.config.umbral_minutos, 10) || DEFAULT_CONFIG.umbral_minutos,
                        umbral_course: parseInt(data.config.umbral_course, 10) || DEFAULT_CONFIG.umbral_course
                    };

                    // Validar NaN
                    if (isNaN(window.APP_CONFIG.umbral_metros)) {
                        window.APP_CONFIG.umbral_metros = DEFAULT_CONFIG.umbral_metros;
                    }
                    if (isNaN(window.APP_CONFIG.umbral_minutos)) {
                        window.APP_CONFIG.umbral_minutos = DEFAULT_CONFIG.umbral_minutos;
                    }
                    if (isNaN(window.APP_CONFIG.umbral_course)) {
                        window.APP_CONFIG.umbral_course = DEFAULT_CONFIG.umbral_course;
                    }
                } else {
                    console.warn('⚠️ Config no recibida o error:', data.error);
                    window.APP_CONFIG = { ...DEFAULT_CONFIG };
                }

                console.log('✅ APP_CONFIG final:', window.APP_CONFIG);
                // ✅ Solo ahora dispara el evento
                window.dispatchEvent(new Event('configListo'));

            }

            console.log('✅ mapa_base cargado:', window.APP_CONFIG.mapa_base);
        } catch (err) {
            console.error('❌ Error al cargar datos generales:', err.message);
            // Fallback
            window.APP_CONFIG.mapa_base = 'ESRI'; 
            window.APP_CONFIG.umbral_metros = 150;
            window.APP_CONFIG.umbral_minutos = 5;
            window.APP_CONFIG.umbral_course = 10;
            window.dispatchEvent(new Event('configListo'));
        }
    }

    // Inicializar cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', () => {
        cargarConfigYIniciar;
        window.chatModal = new ChatModal();
    });

</script>