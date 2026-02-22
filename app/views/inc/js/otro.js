/**
 * Sistema de Mensajería GreenTrack
 * Versión 100% AJAX/Polling (sin WebSocket)
 * Integrado con la arquitectura PHP existente
 */


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
        this.openGroups = new Set(); // ← Para recordar qué grupos están abiertos
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
        this.loadContacts();
        this.setupEventListeners();
        console.log('✅ Chat inicializado para:', this.config.userName);
    }

    startPolling() {
        this.polling = setInterval(() => this.loadNewMessages(), 2000);
    }

    stopPolling() {
        if (this.polling) {
            clearInterval(this.polling);
            this.polling = null;
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
        container.innerHTML = this.contacts.map(contact => `
            <div class="contact-item" data-id="${contact.id}" data-email="${contact.email}">
                <img src="../app/views/img/avatars/${contact.email}.jpg" class="contact-avatar" onerror="this.src='../app/views/img/avatars/default.png'">
                <div class="contact-details">
                    <div class="contact-name">${this.escapeHTML(contact.nombre)}</div>
                    <div class="contact-status">${contact.status === 'online' ? '🟢 Online' : '⚫ Offline'}</div>
                </div>
            </div>
        `).join('');
        container.querySelectorAll('.contact-item').forEach(item => {
            item.addEventListener('click', () => this.selectChat(item));
        });
    }

    updateStatus(status, type = 'info') {
        const statusElement = document.getElementById('userStatus');
        if (statusElement) {
            statusElement.textContent = status;
            statusElement.className = `user-status status-${type}`;
        }
    }

    setupEventListeners() {
        // Evitar acumulación de listeners
        if (window.chatListenersInitialized) {
            return;
        }
        window.chatListenersInitialized = true;

        // Listener único para el botón de envío
        document.addEventListener('click', (e) => {
            const sendBtn = e.target.closest('#sendBtn');
            if (sendBtn) {
                console.log("🚀 Enviando mensaje...");
                this.sendMessage();
            }
        });

        // Listener único para Enter en el input
        document.addEventListener('keypress', (e) => {
            const input = e.target.closest('#messageInput');
            if (input && e.key === 'Enter') {
                console.log("⌨️ Enter detectado, enviando mensaje...");
                this.sendMessage();
            }
        });

        // Listener único para el botón de cerrar
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
    }

    async sendMessage() {
        const input = document.getElementById('messageInput');
        const msg = input.value.trim();
        if (!msg || !this.currentChat) { // ✅ Solo verificar mensaje y contacto
            return;
        }        

        try {
            const res = await fetch('../app/ajax/contactsAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_contacts: 'send_message',
                    to: this.currentChat.email,
                    content: msg,
                    token: this.config.userToken // ← ✅ Añadir esta línea
                })
            });
            const data = await res.json();

            if (data.success) {
                input.value = '';
                // Mostrar mensaje localmente
                this.displayIncomingMessage({
                    user: { email: this.config.userEmail, nombre: this.config.userName },
                    content: msg,
                    timestamp: new Date().toISOString().replace('T', ' ').substring(0, 19)
                });
            }
        } catch (e) {
            console.error('Error al enviar mensaje:', e);
        }
    }

    formatTime(dateTimeString) {
        if (typeof dateTimeString === 'string' && dateTimeString.includes(' ')) {
            const iso = dateTimeString.replace(' ', 'T');
            const date = new Date(iso);
            if (isNaN(date.getTime())) return '—';
            return date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        }
        return '—';
    }

    escapeHTML(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
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
        document.getElementById('messageInputArea').style.display = 'grid';

        // Cargar historial
        this.loadChatHistory(this.currentChat.id);
        this.startPolling();
    }

    renderMessagesWithGroups(messages) {
        const container = document.getElementById('messagesContainer');
        if (!container) return;

        // 1. Agrupar mensajes por fecha (YYYY-MM-DD)
        const groups = {};
        messages.forEach(msg => {
            const dateKey = msg.timestamp.split(' ')[0];
            if (!groups[dateKey]) {
                groups[dateKey] = [];
            }
            groups[dateKey].push(msg);
        });

        // 2. Ordenar fechas (más antiguas arriba)
        const sortedDates = Object.keys(groups).sort((a, b) => new Date(a) - new Date(b));
        const lastDate = sortedDates.length > 0 ? sortedDates[sortedDates.length - 1] : null;

        // 3. Generar HTML de grupos
        let html = '';
        sortedDates.forEach(dateKey => {
            const dateObj = new Date(dateKey);
            const formattedDate = dateObj.toLocaleDateString('es-ES', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            // El último grupo (más reciente) se abre por defecto
            const isOpen = dateKey === lastDate;
            const displayStyle = isOpen ? 'block' : 'none';
            const iconClass = isOpen ? 'fas fa-chevron-down' : 'fas fa-chevron-right';

            html += `
                <div class="message-date-group">
                    <div class="date-header" data-date="${dateKey}">
                        <span class="date-text">${formattedDate}</span>
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

        // 4. Renderizar en el contenedor
        container.innerHTML = html || `
            <div class="welcome-message">
                <i class="fas fa-comments"></i>
                <h3>No messages</h3>
                <p>Start a conversation</p>
            </div>
        `;

        // 5. Vincular listeners a los botones de toggle
        container.querySelectorAll('.toggle-date-group').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const dateKey = e.currentTarget.dataset.date;
                const messagesDiv = document.getElementById(`messages-${dateKey}`);
                const icon = e.currentTarget.querySelector('i');

                if (messagesDiv.style.display === 'none') {
                    messagesDiv.style.display = 'block';
                    icon.className = 'fas fa-chevron-down';
                } else {
                    messagesDiv.style.display = 'none';
                    icon.className = 'fas fa-chevron-right';
                }
            });
        });

        // 6. Scroll al último mensaje del último grupo
        if (lastDate) {
            const lastGroup = document.getElementById(`messages-${lastDate}`);
            if (lastGroup && lastGroup.lastElementChild) {
                lastGroup.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
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
                // ✅ Transformar el formato de la BD al formato esperado
                const formattedMessages = data.data.map(msg => ({
                    id: msg.id,
                    user: {
                        id: msg.remitente_id,
                        nombre: msg.remitente_nombre,
                        email: msg.remitente_email
                    },
                    content: msg.content,
                    timestamp: msg.timestamp
                }));
                this.renderMessagesIncremental(formattedMessages);
                this.renderMessagesWithGroups(data.data);
                this.lastMessageId = data.data[data.data.length - 1].id;
                // ✅ Scroll al último mensaje
                setTimeout(() => {
                    const container = document.getElementById('messagesContainer');
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
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
                // ✅ Transformar también los mensajes nuevos
                const formattedMessages = data.data.map(msg => ({
                    id: msg.id,
                    user: {
                        id: msg.remitente_id,
                        nombre: msg.remitente_nombre,
                        email: msg.remitente_email
                    },
                    content: msg.content,
                    timestamp: msg.timestamp
                }));
                this.addNewMessages(formattedMessages);
                this.lastMessageId = data.data[data.data.length - 1].id;
            }
        } catch (e) {
            console.error('Error al cargar nuevos mensajes:', e);
        }
    }

    renderMessagesIncremental(messages) {
        const container = document.getElementById('messagesContainer');
        if (!container) return;

        // Agrupar mensajes por fecha
        const groups = {};
        messages.forEach(msg => {
            const dateKey = msg.timestamp.split(' ')[0];
            if (!groups[dateKey]) groups[dateKey] = [];
            groups[dateKey].push(msg);
        });

        // Determinar qué grupos deben estar abiertos (solo el último por defecto)
        const sortedDates = Object.keys(groups).sort();
        const lastDate = sortedDates[sortedDates.length - 1];
        this.openGroups = new Set([lastDate]);

        // Construir HTML
        let html = '';
        sortedDates.forEach(dateKey => {
            const isOpen = this.openGroups.has(dateKey);
            html += this.buildGroupHTML(dateKey, groups[dateKey], isOpen);
        });

        container.innerHTML = html || '<div class="welcome-message"><i class="fas fa-comments"></i><h3>No messages</h3><p>Start a conversation</p></div>';
        this.attachGroupListeners();
    }

    addNewMessages(messages) {
        const container = document.getElementById('messagesContainer');
        if (!container) return;

        let hasNew = false;
        let lastElement = null;

        messages.forEach(msg => {
            if (!document.querySelector(`[data-id="${msg.id}"]`)) {
                // Encontrar el grupo de fecha
                const dateKey = msg.timestamp.split(' ')[0];
                const group = document.getElementById(`messages-${dateKey}`);
                if (group) {
                    group.insertAdjacentHTML('beforeend', this.createMessageElement(msg));
                } else {
                    // Si no existe el grupo, crearlo
                    const newGroup = this.buildGroupHTML(dateKey, [msg], true);
                    container.insertAdjacentHTML('beforeend', newGroup);
                    this.openGroups.add(dateKey);
                }
                hasNew = true;
                lastElement = container.lastElementChild?.lastElementChild;
            }
        });

        if (hasNew && lastElement) {
            const isBottom = container.scrollHeight - container.clientHeight <= container.scrollTop + 1;
            if (isBottom) {
                lastElement.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
        }
    }

    buildGroupHTML(dateKey, messages, isOpen) {
        const dateObj = new Date(dateKey);
        const formattedDate = dateObj.toLocaleDateString('es-ES', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
        const displayStyle = isOpen ? 'block' : 'none';
        const iconClass = isOpen ? 'fas fa-chevron-down' : 'fas fa-chevron-right';

        let messagesHTML = '';
        messages.forEach(msg => {
            messagesHTML += this.createMessageElement(msg);
        });

        return `
            <div class="message-date-group">
                <div class="date-header" data-date="${dateKey}">
                    <span class="date-text">${formattedDate}</span>
                    <button class="toggle-date-group" data-date="${dateKey}">
                        <i class="${iconClass}"></i>
                    </button>
                </div>
                <div class="date-messages" id="messages-${dateKey}" style="display: ${displayStyle};">
                    ${messagesHTML}
                </div>
            </div>
        `;
    }

    attachGroupListeners() {
        document.querySelectorAll('.toggle-date-group').forEach(btn => {
            btn.onclick = (e) => {
                const dateKey = e.currentTarget.dataset.date;
                const group = document.getElementById(`messages-${dateKey}`);
                const icon = e.currentTarget.querySelector('i');
                if (group.style.display === 'none') {
                    group.style.display = 'block';
                    icon.className = 'fas fa-chevron-down';
                    this.openGroups.add(dateKey);
                } else {
                    group.style.display = 'none';
                    icon.className = 'fas fa-chevron-right';
                    this.openGroups.delete(dateKey);
                }
            };
        });
    }

    createMessageElement(message) {
        const isOwn = message.remitente_email === this.config.userEmail;
        return `
            <div class="message ${isOwn ? 'own-message' : ''}" data-id="${message.id}">
                <img src="../app/views/img/avatars/${message.remitente_email}.jpg" class="message-avatar" onerror="this.src='../app/views/img/avatars/default.png'">
                <div class="message-content">
                    <div class="message-text">${this.escapeHTML(message.content)}</div>
                    <div class="message-time">${this.formatTime(message.timestamp)}</div>
                </div>
            </div>
        `;
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

    displayIncomingMessage(message) {
        const container = document.getElementById('messagesContainer');
        if (!container) return;
        const html = this.createMessageElement(message);
        container.innerHTML += html;
        if (container.scrollHeight - container.clientHeight <= container.scrollTop + 1) {
            container.scrollTop = container.scrollHeight;
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.chatConfig) {
        window.chatApp = new GreenTrackChat();
    }
});