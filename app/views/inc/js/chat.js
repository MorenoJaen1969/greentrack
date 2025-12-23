// /* *
// * Sistema de Mensajería GreenTrack
// * Versión 100% AJAX/Polling (sin WebSocket)
// * Integrado con la arquitectura PHP existente
// */

// Bandera global para evitar listeners duplicados
if (typeof window.chatListenersInitialized === 'undefined') {
    window.chatListenersInitialized = false;
}

class GreenTrackChat {
    constructor() {
        console.log('🔧 Inicializando GreenTrackChat (modo AJAX)');
        this.config = window.chatConfig;
        this.contacts = [];
        this.currentChat = null;
        this.polling = null;
        this.lastMessageId = 0;
        this.unreadCount = 0; // ← añade esta línea
        // Sonido de notificación
        this.notificationSound = null;
        this.initializeChat();
    }

    initializeChat() {
        const messagesContainer = document.getElementById('messagesContainer');
        if (messagesContainer) {
            messagesContainer.innerHTML = `
                <div class="welcome-message">
                    <div class="welcome-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3>Welcome to the Chat</h3>
                    <p>Select a contact to start chatting</p>
                </div>
            `;
        }

        // Intercepta imágenes pegadas y las sube automáticamente
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('paste', async (e) => {
                const items = e.clipboardData?.items;
                if (!items) return;

                for (let i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') === 0) {
                        e.preventDefault();
                        const blob = items[i].getAsFile();
                        if (blob) {
                            const file = new File([blob], 'pasted-image.png', { type: 'image/png' });
                            await this.uploadFileAndInsert(file);
                        }
                        break;
                    }
                }
            });
        }

        // Inicializar sonido
        // Permitir reproducción de audio incluso sin interacción
        if (this.notificationSound) {
            const unlockAudio = () => {
                this.notificationSound.play().catch(() => {}).then(() => {
                    this.notificationSound.pause();
                    this.notificationSound.currentTime = 0;
                });
                document.removeEventListener('click', unlockAudio);
                document.removeEventListener('touchstart', unlockAudio);
            };
            document.addEventListener('click', unlockAudio, { once: true });
            document.addEventListener('touchstart', unlockAudio, { once: true });
        }

        // Iniciar verificación de mensajes no leídos cada 10s
        setInterval(() => this.checkUnreadMessages(), 10000);
        this.checkUnreadMessages(); // ejecutar inmediatamente

        this.loadContacts();
        this.setupEventListeners();
        console.log('✅ Chat inicializado para:', this.config.userName);
    }

    startPolling() {
        // Refrescar contactos cada 10 segundos (para notificaciones)
        setInterval(() => this.loadContacts(), 10000);
        // Refrescar mensajes del chat actual cada 2 segundos
        this.polling = setInterval(() => this.loadNewMessages(), 2000);
    }

    stopPolling() {
        if (this.polling) {
            clearInterval(this.polling);
            this.polling = null;
        }
    }

    updateUnreadBadge(count) {
        const btn = document.getElementById('btn-chat-toggle');
        if (!btn) return;

        const existingBadge = btn.querySelector('.chat-unread-badge');
        if (existingBadge) existingBadge.remove();

        if (count > 0) {
            const badge = document.createElement('span');
            badge.className = 'chat-unread-badge';
            badge.textContent = count > 99 ? '99+' : count;
            btn.appendChild(badge);
        }
    }    

    playNotificationSound() {
        if (document.hasFocus() && this.currentChat) return; // si el chat está abierto, no sonar
        if (this.notificationSound) {
            this.notificationSound.play().catch(e => console.warn('Audio play blocked:', e));
        }
    }    

    updateTabTitle(count) {
        // Eliminar cualquier prefijo de conteo existente
        let cleanTitle = document.title.replace(/^\(\d+\+?\)\s*/, '');
        
        if (count > 0) {
            const displayCount = count > 99 ? '99+' : count;
            document.title = `(${displayCount}) ${cleanTitle}`;
        } else {
            document.title = cleanTitle;
        }
    }

    initNotificationSound() {
        if (!this.notificationSound) {
            this.notificationSound = new Audio('/app/views/sounds/new-notification-010-352755.mp3');
        }
    }

    async checkUnreadMessages() {
        if (!this.config?.userToken) return;

        try {
            const res = await fetch('../app/ajax/contactsAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_contacts: 'unread_count',
                    token: this.config.userToken
                })
            });
            const data = await res.json();
            const count = data.success ? data.count : 0;

            // Solo notificar si hay nuevos mensajes
            if (count > this.unreadCount && count > 0 && !this.currentChat) {
                this.playNotificationSound();
                this.updateTabTitle(count);
            }

            this.unreadCount = count;
            this.updateUnreadBadge(count);
        } catch (e) {
            console.error('Error checking unread messages:', e);
        }
    }
    
    async loadContacts() {
        if (!this.config || !this.config.userToken) {
            console.error('❌ Token no disponible para cargar contactos');
            return;
        }
        try {
            const res = await fetch('../app/ajax/contactsAjax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.config.userToken}`
                },
                body: JSON.stringify({ modulo_contacts: 'contactos' })
            });
            if (res.ok) {
                const data = await res.json();
                if (data.success) {
                    this.contacts = data.data.contacts || [];
                    this.renderContacts();
                }
            }
        } catch (e) {
            console.error('Error al cargar contactos:', e);
        }
    }

    renderContacts() {
        const container = document.getElementById('contactsList');
        if (!container) return;

        container.innerHTML = this.contacts.map(contact => { 
            const unreadBadge = contact.unread > 0 ? 
                `<span class="contact-unread-badge">${contact.unread > 99 ? '99+' : contact.unread}</span>` : '';
                
            return `
                <div class="contact-item" data-id="${contact.id}" data-email="${contact.email}">
                    <img src="../app/views/img/avatars/${contact.email}.jpg" class="contact-avatar" onerror="this.src='../app/views/img/avatars/default.png'">
                    <div class="contact-details">
                        <div class="contact-name">${this.escapeHTML(contact.nombre)}</div>
                        <div class="contact-status">${contact.status === 'online' ? '🟢 Online' : '⚫ Offline'}</div>
                    </div>
                    ${unreadBadge}
                </div>
            `;
        }).join('');
        
        container.querySelectorAll('.contact-item').forEach(item => {
            item.addEventListener('click', () => this.selectChat(item));
        });
    }

    setupEventListeners() {
        if (window.chatListenersInitialized) return;
        window.chatListenersInitialized = true;

        // Botón de envío
        document.addEventListener('click', (e) => {
            const sendBtn = e.target.closest('#sendBtn');
            if (sendBtn && this.currentChat) {
                console.log('Enviando mensaje...'); // ← Añade esto para depurar
                this.sendMessage();
            }
        });

        // Ctrl+B, Ctrl+I, Ctrl+U para formato
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                const input = document.getElementById('messageInput');
                if (!input || !input.contains(document.activeElement)) return;

                if (e.key === 'b' || e.key === 'B') {
                    e.preventDefault();
                    document.execCommand('bold', false, null);
                } else if (e.key === 'i' || e.key === 'I') {
                    e.preventDefault();
                    document.execCommand('italic', false, null);
                } else if (e.key === 'u' || e.key === 'U') {
                    e.preventDefault();
                    document.execCommand('underline', false, null);
                }
            }
        });

        // Enter para enviar
        document.addEventListener('keydown', (e) => {
            const input = document.getElementById('messageInput');
            if (input && e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.currentChat) {
                    this.sendMessage();
                }
            }
        });

        // Cerrar chat
        document.addEventListener('click', (e) => {
            const closeBtn = e.target.closest('#closeChatBtn');
            if (closeBtn) {
                const modal = document.getElementById('chatModal');
                const toggleBtn = document.getElementById('btn-chat-toggle');
                if (modal) modal.style.display = 'none';
                if (toggleBtn) toggleBtn.style.display = 'flex';
                this.stopPolling();
            }
        });

        // Logout
        const logoutBtn = document.getElementById('logoutChatBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', async () => {
                const confirmado = await suiteConfirm(
                    'Confirm Logout',
                    'Do you want to close the chat session?',
                    { aceptar: 'Yes, logout', cancelar: 'Cancel' }
                );
                if (confirmado) {
                    this.logoutFromChat();
                }
            });
        }

        document.addEventListener('click', (e) => {
            const formatBtn = e.target.closest('.format-btn');
            if (formatBtn && this.currentChat) {
                const command = formatBtn.dataset.command;
                this.applyFormat(command);
            }
        });

        // Insertar imagen desde URL
        document.getElementById('insertImageFile')?.addEventListener('click', () => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*'; // ← Solo imágenes
            input.onchange = (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.uploadFileAndInsert(file);
                }
            };
            input.click();
        });
        
        // Adjuntar archivo (imagen, audio, video)
        document.getElementById('attachFile')?.addEventListener('click', () => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*,audio/*,video/*';
            input.onchange = (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.uploadFileAndInsert(file);
                }
            };
            input.click();
        });        
    }

    autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    applyFormat(command) {
        const input = document.getElementById('messageInput');
        if (!input) return;
        input.focus();

        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) return;

        const range = selection.getRangeAt(0);
        if (range.toString().trim() === '') return; // No aplicar si no hay texto seleccionado

        // Clonar el contenido seleccionado
        const clonedContents = range.cloneContents();
        const wrapper = document.createElement(this.getTagName(command));

        // Mover el contenido al wrapper
        wrapper.appendChild(clonedContents);

        // Reemplazar la selección con el contenido formateado
        range.deleteContents();
        range.insertNode(wrapper);

        // Restaurar la selección alrededor del nuevo nodo
        const newRange = document.createRange();
        newRange.selectNodeContents(wrapper);
        selection.removeAllRanges();
        selection.addRange(newRange);
    }

    getTagName(command) {
        switch (command) {
            case 'bold': return 'strong';
            case 'italic': return 'em';
            case 'underline': return 'u';
            case 'strikeThrough': return 's';
            default: return 'span';
        }
    }

    async sendMessage() {
        console.log('sendMessage() llamado');
        const input = document.getElementById('messageInput');
        if (!input || !this.currentChat) {
            console.warn('No hay input o no hay chat seleccionado');
            return;
        }

        // Obtener HTML del contenteditable
        let content = input.innerHTML.trim();
        // Sanitización básica en el frontend (el backend ya hace la fuerte)
        content = content.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
        // Si está vacío o solo tiene <br>
        if (!content || content === '<br>') {
            console.warn('Mensaje vacío');
            return;
        }

        console.log('Contenido a enviar:', content);
        try {
            const res = await fetch('../app/ajax/contactsAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_contacts: 'send_message',
                    to: this.currentChat.email,
                    content: content,
                    token: this.config.userToken
                })
            });
            const data = await res.json();
            if (data.success) {
                input.innerHTML = ''; // Limpiar
                this.scrollToBottom();
            }
        } catch (e) {
            console.error('Error al enviar mensaje:', e);
        }
    }

    selectChat(el) {
        if (this.polling) this.stopPolling();

        document.querySelectorAll('.contact-item').forEach(i => i.classList.remove('active'));
        el.classList.add('active');

        const userId = el.dataset.id;
        const userEmail = el.dataset.email;
        const userName = el.querySelector('.contact-name').textContent;

        this.currentChat = { id: userId, email: userEmail, name: userName };
        document.getElementById('currentChatName').textContent = this.currentChat.name;

        // ✅ Asegurar que el área de mensaje es visible
        const messageArea = document.getElementById('messageInputArea');
        if (messageArea) messageArea.style.display = 'flex';

        // ✅ Limpiar notificaciones al abrir el chat
        this.unreadCount = 0;
        this.updateUnreadBadge(0);
        this.updateTabTitle(0);

        this.loadChatHistory(this.currentChat.id);
        this.startPolling();

        // ✅ Marcar como leídos
        this.markMessagesAsRead(this.currentChat.id);
    }

    async markMessagesAsRead(contactId) {
        try {
            await fetch('../app/ajax/contactsAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_contacts: 'mark_messages_as_read',
                    contact_id: contactId,
                    token: this.config.userToken
                })
            });
            // Actualizar lista de contactos para reflejar 0 mensajes
            this.loadContacts();
        } catch (e) {
            console.error('Error marking as read:', e);
        }
    }

    async loadChatHistory(contactId) {
        try {
            const res = await fetch('../app/ajax/contactsAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_contacts: 'get_history',
                    contact_id: contactId,
                    token: this.config.userToken
                })
            });
            const data = await res.json();
            if (data.success && data.data?.length) {
                const messages = data.data.map(msg => ({
                    id: msg.id,
                    user: {
                        id: msg.remitente_id,
                        nombre: msg.remitente_nombre,
                        email: msg.remitente_email
                    },
                    content: msg.content,
                    timestamp: msg.timestamp,
                    leido: msg.leido
                }));
                this.renderMessagesWithGroups(messages);
                this.lastMessageId = data.data[data.data.length - 1].id;

                // ✅ Scroll forzado al inicio de la conversación
                setTimeout(() => {
                    this.setMessagesContainerHeight();
                    this.scrollToBottom();
                }, 100);
            } else {
                this.showNoMessages();
            }
        } catch (e) {
            console.error('Error al cargar historial:', e);
        }
    }

    async loadNewMessages() {
        if (!this.currentChat) return;
        try {
            const res = await fetch('../app/ajax/contactsAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_contacts: 'get_history_since',
                    contact_id: this.currentChat.id,
                    since_id: this.lastMessageId,
                    token: this.config.userToken
                })
            });
            const data = await res.json();
            if (data.success && data.data?.length) {
                const messages = data.data.map(msg => ({
                    id: msg.id,
                    user: {
                        id: msg.remitente_id,
                        nombre: msg.remitente_nombre,
                        email: msg.remitente_email
                    },
                    content: msg.content,
                    timestamp: msg.timestamp
                }));
                messages.forEach(msg => {
                    if (!document.querySelector(`[data-id="${msg.id}"]`)) {
                        this.addMessageToGroup(msg);
                    }
                });
                this.lastMessageId = data.data[data.data.length - 1].id;
            }
        } catch (e) {
            console.error('Error al cargar nuevos mensajes:', e);
        }
    }

    getGroupHeaderText(groupKey) {
        if (groupKey === 'today') return 'Today';
        if (groupKey === 'yesterday') return 'Yesterday';
        // Para días de la semana
        const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        if (days.includes(groupKey.toLowerCase())) {
            return groupKey.charAt(0).toUpperCase() + groupKey.slice(1);
        }
        // Para "15 de diciembre" o "diciembre 2024", el key ya está en formato legible
        return groupKey;
    }

    renderMessagesWithGroups(messages) {
        const container = document.getElementById('messagesContainer');
        if (!container) return;

        const groups = {};
        const unreadByGroup = {};

        // === Paso 1: Agrupar mensajes y contar no leídos ===
        messages.forEach(msg => {
            const dateKey = this.getMessageGroupKey(msg.timestamp);
            if (!groups[dateKey]) {
                groups[dateKey] = [];
                unreadByGroup[dateKey] = 0;
            }
            groups[dateKey].push(msg);

            // ✅ Verificar si el mensaje está no leído
            if (msg.leido === 0 || msg.leido === '0') {
                unreadByGroup[dateKey]++;
            }
        });

        // === LOG: Mostrar conteo por grupo ===
        const sortedDates = Object.keys(groups).sort((a, b) => new Date(a) - new Date(b));
        const lastDate = sortedDates.length > 0 ? sortedDates[sortedDates.length - 1] : null;

        let html = '';

        sortedDates.forEach(dateKey => {
            // ✅ Aquí aplicas la corrección de zona horaria para la fecha del grupo
            const formattedDate = this.getGroupHeaderText(dateKey);
            const unreadCount = unreadByGroup[dateKey] || 0;

            // === LOG: Detalle por grupo ===

            const isOpen = dateKey === lastDate;
            const displayStyle = isOpen ? 'block' : 'none';
            const iconClass = isOpen ? 'fas fa-chevron-down' : 'fas fa-chevron-right';

            // ✅ Solo mostrar badge si hay no leídos            
            const unreadBadge = unreadCount > 0 ? 
                `<span class="group-unread-badge">${unreadCount}</span>` : '';

            html += `
                <div class="message-date-group">
                    <div class="date-header" data-date="${dateKey}">
                        <div class="date-text-with-badge">
                            <span class="date-text">${formattedDate}</span>
                            ${unreadBadge}
                        </div>
                        <button class="toggle-date-group" data-date="${dateKey}">
                            <i class="${iconClass}"></i>
                        </button>
                    </div>
                    <div class="date-messages" id="messages-${dateKey}" style="display: ${displayStyle};">
                        ${groups[dateKey].map(msg => this.createMessageElement(msg)).join('')}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html || `
            <div class="welcome-message">
                <i class="fas fa-comments"></i>
                <h3>No messages</h3>
                <p>Start a conversation</p>
            </div>
        `;

        // Vincular listeners de toggle
        container.querySelectorAll('.toggle-date-group').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const dateKey = e.currentTarget.dataset.date;
                const messagesDiv = document.getElementById(`messages-${dateKey}`);
                const isOpen = messagesDiv.style.display !== 'none';
                
                if (!isOpen) {
                    messagesDiv.style.display = 'block';
                    e.currentTarget.querySelector('i').className = 'fas fa-chevron-down';
                    
                    // ✅ Marcar mensajes de este grupo como leídos
                    const messageElements = messagesDiv.querySelectorAll('.message[data-id]');
                    const messageIds = Array.from(messageElements).map(el => el.dataset.id);
                    if (messageIds.length > 0) {
                        this.markMessagesByIdAsRead(messageIds);
                    }
                } else {
                    messagesDiv.style.display = 'none';
                    e.currentTarget.querySelector('i').className = 'fas fa-chevron-right';
                }
            });
        });

        // Scroll al último mensaje
        if (lastDate) {
            const lastGroup = document.getElementById(`messages-${lastDate}`);
            if (lastGroup && lastGroup.lastElementChild) {
                lastGroup.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
        }
    }

    addMessageToGroup(message) {
        const container = document.getElementById('messagesContainer');
        if (!container) return;

        const dateKey = this.getMessageGroupKey(message.timestamp);        
        let group = document.getElementById(`messages-${dateKey}`);

        if (!group) {
            // Crear nuevo grupo de fecha
            const formattedDate = this.getGroupHeaderText(dateKey);
            const newGroup = `
                <div class="message-date-group">
                    <div class="date-header" data-date="${dateKey}">
                        <span class="date-text">${formattedDate}</span>
                        <button class="toggle-date-group" data-date="${dateKey}">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="date-messages" id="messages-${dateKey}" style="display: block;">
                        ${this.createMessageElement(message)}
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', newGroup);

            // Vincular listener al nuevo botón
            const newBtn = container.querySelector(`[data-date="${dateKey}"]`);
            if (newBtn) {
                newBtn.addEventListener('click', (e) => {
                    const messagesDiv = document.getElementById(`messages-${dateKey}`);
                    const isOpen = messagesDiv.style.display !== 'none';
                    
                    if (!isOpen) {
                        messagesDiv.style.display = 'block';
                        e.currentTarget.querySelector('i').className = 'fas fa-chevron-down';
                        
                        // ✅ Marcar mensajes de este grupo como leídos
                        const messageElements = messagesDiv.querySelectorAll('.message[data-id]');
                        const messageIds = Array.from(messageElements).map(el => el.dataset.id);
                        if (messageIds.length > 0) {
                            this.markMessagesByIdAsRead(messageIds);
                        }
                    } else {
                        messagesDiv.style.display = 'none';
                        e.currentTarget.querySelector('i').className = 'fas fa-chevron-right';
                    }
                });
            }
        } else {
            // Añadir mensaje al grupo existente
            group.insertAdjacentHTML('beforeend', this.createMessageElement(message));
            const lastMsg = group.lastElementChild;
            const isScrolledToBottom = container.scrollHeight - container.clientHeight <= container.scrollTop + 1;
            if (isScrolledToBottom) {
                lastMsg.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
        }

        // ✅ Scroll al final si el usuario está abajo
        this.setMessagesContainerHeight();
        this.scrollToBottom();
    }

    createMessageElement(message) {
        const isOwn = message.user.email === this.config.userEmail;
        // ✅ Insertar HTML directamente (el backend ya lo sanitizó)
        return `
            <div class="message ${isOwn ? 'own-message' : ''}" data-id="${message.id}">
                <img src="../app/views/img/avatars/${message.user.email}.jpg" class="message-avatar" onerror="this.src='../app/views/img/avatars/default.png'">
                <div class="message-content">
                    <div class="message-text">${message.content}</div>
                    <div class="message-time">${this.formatTime(message.timestamp)}</div>
                </div>
            </div>
        `;
    }

    getMessageGroupKey(timestamp) {
        // Parsear timestamp del servidor (formato: "2025-12-10 14:30:00")
        const [datePart] = timestamp.split(' ');
        const [Y, M, D] = datePart.split('-').map(Number);

        // Asumir que el servidor envía hora en Chicago (UTC-6 en invierno)
        // Convertir a UTC para comparar con la fecha local del cliente
        const utcTime = Date.UTC(Y, M - 1, D, 12, 0, 0); // 12:00 UTC para evitar ambigüedad
        const msgDate = new Date(utcTime);

        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);

        const msgDay = new Date(msgDate.getFullYear(), msgDate.getMonth(), msgDate.getDate());

        // 1. Hoy
        if (msgDay.getTime() === today.getTime()) {
            return 'today';
        }

        // 2. Ayer
        if (msgDay.getTime() === yesterday.getTime()) {
            return 'yesterday';
        }

        // 3. Esta semana (últimos 7 días)
        const sevenDaysAgo = new Date(today);
        sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
        if (msgDay >= sevenDaysAgo) {
            return msgDay.toLocaleDateString('en-US', { weekday: 'long' });
        }

        // 4. Este año (pero fuera de esta semana)
        if (msgDate.getFullYear() === now.getFullYear()) {
            return msgDate.toLocaleDateString('en-US', { day: 'numeric', month: 'long' });
        }

        // 5. Años anteriores
        return msgDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    }

    showNoMessages() {
        const container = document.getElementById('messagesContainer');
        if (container) {
            container.innerHTML = `
                <div class="welcome-message">
                    <i class="fas fa-comments"></i>
                    <h3>No messages</h3>
                    <p>Start a conversation</p>
                </div>
            `;
        }
    }

    formatTime(dateTimeString) {
        // Si no es un string válido o no contiene espacio, retornar guión
        if (typeof dateTimeString !== 'string' || !dateTimeString.includes(' ')) {
            return '—';
        }

        const [datePart, timePart] = dateTimeString.split(' ');
        const [Y, M, D] = datePart.split('-').map(Number);
        const [h, m, s] = (timePart || '00:00:00').split(':').map(Number);

        // Validar que los componentes sean números válidos y dentro de rangos razonables
        if (
            isNaN(Y) || isNaN(M) || isNaN(D) || isNaN(h) || isNaN(m) || isNaN(s) ||
            Y < 1970 || Y > 2100 || M < 1 || M > 12 || D < 1 || D > 31 ||
            h < 0 || h > 23 || m < 0 || m > 59 || s < 0 || s > 59
        ) {
            return '—';
        }

        // Asumir que la hora del servidor es Chicago (UTC-6 en invierno)
        // Convertir a UTC sumando 6 horas
        const utcTimestamp = Date.UTC(Y, M - 1, D, h + 6, m, s);

        // Verificar que la fecha sea válida
        const date = new Date(utcTimestamp);
        if (isNaN(date.getTime())) {
            return '—';
        }

        // Formatear en la zona horaria del cliente
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    escapeHTML(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    displayIncomingMessage(message) {
        const container = document.getElementById('messagesContainer');
        if (!container) return;

        const html = this.createMessageElement(message);
        container.insertAdjacentHTML('beforeend', html);

        // Scroll inteligente
        this.scrollToBottom();
    }

    scrollToBottom() {
        const container = document.getElementById('messagesContainer');
        if (!container) return;

        const lastMessage = container.lastElementChild?.lastElementChild;
        if (lastMessage) {
            lastMessage.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
                inline: 'nearest'
            });
        }
    }

    setMessagesContainerHeight() {
        const messagesArea = document.querySelector('.messages-area');
        const header = document.querySelector('.messages-header');
        const inputArea = document.getElementById('messageInputArea');
        const container = document.getElementById('messagesContainer');

        if (messagesArea && header && inputArea && container) {
            const areaHeight = messagesArea.clientHeight;
            const headerHeight = header.offsetHeight;
            const inputHeight = inputArea.offsetHeight;
            const padding = 20; // Ajusta según tu diseño

            const containerHeight = areaHeight - headerHeight - inputHeight - padding;
            container.style.height = containerHeight + 'px';
            container.style.overflowY = 'auto';
        }
    }

    async uploadFileAndInsert(file) { 
        const formData = new FormData(); 
        formData.append('file', file); 
        formData.append('modulo_contacts', 'upload_file'); 
        
        try { 
            const res = await fetch('../app/ajax/contactsAjax.php', { 
                method: 'POST', 
                body: formData 
            }); 
            const data = await res.json(); 
            
            if (data.success && data.url) { 
                let html = ''; 
                if (file.type.startsWith('image/')) { 
                    html = `<img src="${data.url}" alt="Imagen" style="max-width:100%; height:auto;">`; 
                } else if (file.type.startsWith('audio/')) { 
                    html = `<audio controls src="${data.url}"></audio>`; 
                } else if (file.type.startsWith('video/')) { 
                    html = `<video controls src="${data.url}" style="max-width:100%; height:auto;"></video>`; 
                } else { 
                    html = `<a href="${data.url}" target="_blank">📎 ${this.escapeHTML(file.name)}</a>`; 
                } 
                this.insertHtmlAtCursor(html); 
            } else { 
                alert('Error al subir el archivo.'); 
            } 
        } catch (e) { 
            console.error('Upload error:', e); 
            suiteAlertInfo('Error', 'The file could not be uploaded.');
        } 
    }

    insertHtmlAtCursor(html) {
        const input = document.getElementById('messageInput');
        if (!input) return;
        input.focus();

        if (window.getSelection) {
            const sel = window.getSelection();
            if (sel.getRangeAt && sel.rangeCount) {
                const range = sel.getRangeAt(0);
                range.deleteContents();
                const el = document.createElement('div');
                el.innerHTML = html;
                const frag = document.createDocumentFragment();
                while (el.firstChild) {
                    frag.appendChild(el.firstChild);
                }
                range.insertNode(frag);
                // Mover el cursor al final del contenido insertado
                range.collapse(false);
                sel.removeAllRanges();
                sel.addRange(range);
            }
        } else if (document.selection && document.selection.createRange) {
            // Soporte para IE (opcional, pero incluido por compatibilidad)
            document.selection.createRange().pasteHTML(html);
        }
    }

    async markMessagesByIdAsRead(messageIds) {
        try {
            await fetch('../app/ajax/contactsAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_contacts: 'mark_messages_as_read',
                    message_ids: messageIds,
                    token: this.config.userToken
                })
            });
            // Opcional: actualizar badges de contactos
            this.loadContacts();
        } catch (e) {
            console.error('Error marking messages as read:', e);
        }
    }

    async logoutFromChat() {
        const baseUrl = window.chatConfig?.baseUrl || '/';
        const url = baseUrl + '/app/ajax/usuariosAjax.php';

        const formData = new FormData();
        formData.append('modulo_usuarios', 'cerrar_sesion');

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const text = await response.text();
            if (!text.trim()) {
                throw new Error('Empty response');
            }

            let json;
            try {
                json = JSON.parse(text);
            } catch (parseErr) {
                console.error('Server returned non-JSON response:', text);
                throw new Error('Server error (check console)');
            }

            if (!json.success) {
                throw new Error(json.message || 'Logout failed');
            }

            // ✅ Limpiar estado global
            delete window.chatConfig;
            window.chatApp = null;
            window.chatListenersInitialized = false;

            // ✅ Limpiar y cerrar UI
            const modal = document.getElementById('chatModal');
            const btn = document.getElementById('btn-chat-toggle');
            if (modal) {
                modal.innerHTML = '';        // ← ESTO ES CLAVE
                modal.style.display = 'none';
            }
            if (btn) btn.style.display = 'flex';

            suiteAlertInfo('Action Close', 'Chat session closed.');

        } catch (e) {
            console.error('Logout failed:', e);
            suiteAlertInfo('Error', 'Could not close chat session.');
        }
    }

    initRichEditor() {
        const editorElement = document.getElementById('editor');
        if (!editorElement) return;

        // Barra de herramientas (opcional)
        const toolbarHtml = `
            <div class="tiptap-toolbar">
                <button type="button" data-command="bold" title="Bold"><strong>B</strong></button>
                <button type="button" data-command="italic" title="Italic"><em>I</em></button>
                <button type="button" data-command="strike" title="Strikethrough"><s>S</s></button>
                <button type="button" data-command="underline" title="Underline"><u>U</u></button>
                <button type="button" data-command="emoji" title="Emoji">😊</button>
            </div>
        `;
        editorElement.insertAdjacentHTML('beforebegin', toolbarHtml);

        this.richEditor = new Tiptap.Editor({
            element: editorElement,
            extensions: [
                Tiptap.StarterKit,
                Tiptap.Emoji.configure({
                    enableShortcodes: true,
                })
            ],
            content: '',
            autofocus: false
        });

        // Configurar botones de toolbar
        document.querySelectorAll('.tiptap-toolbar button').forEach(btn => {
            const command = btn.dataset.command;
            btn.addEventListener('click', () => {
                if (command === 'emoji') {
                    // Emoji picker básico (puedes expandirlo)
                    const emoji = prompt('Insert emoji or shortcode (e.g. :smile:)');
                    if (emoji) this.richEditor.chain().focus().insertContent(emoji).run();
                } else if (this.richEditor.can().toggleMark(command)) {
                    this.richEditor.commands.toggleMark(command);
                }
            });
        });
    }

    updateStatus(status, type = 'info') {
        const statusElement = document.getElementById('userStatus');
        if (statusElement) {
            statusElement.textContent = status;
            statusElement.className = `user-status status-${type}`;
        }
    }
   
}

// Cargar y ejecutar chat.js
// let chatScript = document.createElement('script');
// chatScript.src = window.CHAT_CONFIG.baseUrl + '/app/views/inc/js/chat.js?v=' + window.CHAT_CONFIG.timestamp;
// chatScript.onload = () => {
//     // ✅ Inicializar explícitamente
//     if (typeof GreenTrackChat !== 'undefined') {
//         window.chatApp = new GreenTrackChat();
//     }
// };
// document.head.appendChild(chatScript);


// document.addEventListener('DOMContentLoaded', () => {
//     if (window.chatConfig) {
//         window.chatApp = new GreenTrackChat();
//     }
// });