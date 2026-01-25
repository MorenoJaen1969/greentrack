// * Sistema de Mensajería GreenTrack
// * Versión 100% AJAX/Polling (sin WebSocket)
// * Integrado con la arquitectura PHP existente
// */

// Bandera global para evitar listeners duplicados
if (typeof window.chatListenersInitialized === "undefined") {
    window.chatListenersInitialized = false;
}

class GreenTrackChat {
    constructor() {
        console.log("🔧 Inicializando GreenTrackChat (modo AJAX)");
        this.config = window.chatConfig;
        this.contacts = [];
        this.currentChat = null;
        this.polling = null;
        this.lastMessageId = 0;
        this.unreadCount = 0; // ← añade esta línea
        // Sonido de notificación
        this.notificationSound = null;
        this.initializeChat();

        this.lastChatInteraction = Date.now(); // ← nueva
        this.INTERACTION_TIMEOUT = 5 * 60 * 1000; // 5 minutos
    }

    registerChatInteraction() {
        this.lastChatInteraction = Date.now();
    }

    async initializeChat() {
        const messagesContainer = document.getElementById("messagesContainer");
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
        const messageInput = document.getElementById("messageInput");
        if (messageInput) {
            messageInput.addEventListener("paste", async (e) => {
                const items = e.clipboardData?.items;
                if (!items) return;

                for (let i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf("image") === 0) {
                        e.preventDefault();
                        const blob = items[i].getAsFile();
                        if (blob) {
                            const file = new File([blob], "pasted-image.png", {
                                type: "image/png",
                            });
                            await this.uploadFileAndInsert(file);
                        }
                        break;
                    }
                }
            });
        }

        // Inicializar sonido
        if (this.notificationSound) {
            const unlockAudio = () => {
                this.notificationSound
                    .play()
                    .catch(() => { })
                    .then(() => {
                        this.notificationSound.pause();
                        this.notificationSound.currentTime = 0;
                    });
                document.removeEventListener("click", unlockAudio);
                document.removeEventListener("touchstart", unlockAudio);
            };
            document.addEventListener("click", unlockAudio, { once: true });
            document.addEventListener("touchstart", unlockAudio, {
                once: true,
            });
        }

        this.sendHeartbeat();
        setInterval(() => this.checkUnreadMessages(), 10000);
        this.checkUnreadMessages();

        // ✅ 1. Cargar salas del usuario
        let salas = [];
        try {
            const res = await fetch("../app/ajax/contactsAjax.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    modulo_contacts: "listar_salas_usuario",
                    user_id: this.config.userId
                }),
            });
            const data = await res.json();
            if (data.success) {
                salas = data.salas;
                // Renderizar salas en el sidebar
                this.renderUserRooms(salas);
            }
        } catch (e) {
            console.error("Error al cargar salas:", e);
            salas = [];
        }

        // ✅ 2. Determinar qué sala abrir
        let salaActivaId = null;

        // Intentar obtener última sala
        try {
            const res = await fetch("../app/ajax/contactsAjax.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    modulo_contacts: "obtener_ultima_sala",
                    user_id: this.config.userId
                }),
            });
            const data = await res.json();
            if (data.success && data.ultima_sala_id) {
                salaActivaId = data.ultima_sala_id;
            }
        } catch (e) {
            console.warn("No se pudo cargar la última sala:", e);
        }

        // Si no hay última sala, usar la primera disponible
        if (!salaActivaId && salas.length > 0) {
            salaActivaId = salas[0].id;
        }

        // ✅ 3. Seleccionar la sala si existe
        if (salaActivaId) {
            setTimeout(() => {
                this.selectRoom(salaActivaId);
            }, 300);
        }

        this.setupEventListeners();
        console.log("✅ Chat inicializado para:", this.config.userName);
    }

    renderUserRooms(salas) {
        const container = document.getElementById('roomsList');
        if (!container) return;

        container.innerHTML = salas.map(sala => `
            <div class="room-item" data-sala-id="${sala.id}">
                <div class="room-name">${this.escapeHTML(sala.nombre)}</div>
                ${sala.unread > 0 ? `<span class="room-unread-badge">${sala.unread > 99 ? '99+' : sala.unread}</span>` : ''}
            </div>
        `).join('');

        // Añadir listeners
        container.querySelectorAll('.room-item').forEach(item => {
            item.addEventListener('click', (e) => {
                this.selectRoom(e.currentTarget.dataset.salaId);
            });
        });
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
        const btn = document.getElementById("btn-chat-toggle");
        if (!btn) return;

        const existingBadge = btn.querySelector(".chat-unread-badge");
        if (existingBadge) existingBadge.remove();

        if (count > 0) {
            const badge = document.createElement("span");
            badge.className = "chat-unread-badge";
            badge.textContent = count > 99 ? "99+" : count;
            btn.appendChild(badge);
        }
    }

    playNotificationSound() {
        if (document.hasFocus() && this.currentChat) return; // si el chat está abierto, no sonar
        if (this.notificationSound) {
            this.notificationSound
                .play()
                .catch((e) => console.warn("Audio play blocked:", e));
        }
    }

    updateTabTitle(count) {
        // Eliminar cualquier prefijo de conteo existente
        let cleanTitle = document.title.replace(/^\(\d+\+?\)\s*/, "");

        if (count > 0) {
            const displayCount = count > 99 ? "99+" : count;
            document.title = `(${displayCount}) ${cleanTitle}`;
        } else {
            document.title = cleanTitle;
        }
    }

    initNotificationSound() {
        if (!this.notificationSound) {
            this.notificationSound = new Audio(
                "/app/views/sounds/new-notification-010-352755.mp3"
            );
        }
    }

    async checkUnreadMessages() {
        try {
            // 1) Si no hay chat seleccionado -> conteo global (para el icono)
            if (!this.currentChat) {
                const resG = await fetch("../app/ajax/contactsAjax.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ modulo_contacts: 'unread_count', token: this.config.userToken })
                });
                const dataG = await resG.json();
                const globalCount = dataG && dataG.success ? dataG.count : 0;
                this.updateUnreadBadge(globalCount);
                this.updateTabTitle(globalCount);
                return;
            }

            // 2) Si hay chat seleccionado -> manejar según tipo
            const payload = { modulo_contacts: "get_unread_count", token: this.config.userToken };
            if (this.currentChat.isRoom) {
                payload.sala_id = this.currentChat.id;
                payload.is_room = true;
            } else {
                // Si estamos en 1:1 pero dentro de una sala, incluir sala context
                if (this.currentSalaId || this.lastRoomId) payload.sala_id = this.currentSalaId || this.lastRoomId;
                payload.contact_id = this.currentChat.id;
                payload.is_room = false;
            }

            const res = await fetch("../app/ajax/contactsAjax.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload),
            });
            const data = await res.json();

            if (data && data.success) {
                if (this.currentChat.isRoom) {
                    this.updateRoomUnreadBadge(this.currentChat.id, data.count);
                } else {
                    // actualizar badge global si backend lo devuelve
                    if (typeof data.global_count !== 'undefined') {
                        this.updateUnreadBadge(data.global_count);
                        this.updateTabTitle(data.global_count);
                    }
                    // actualizar badge por contacto si devuelve `count`
                    if (typeof data.count !== 'undefined') {
                        const badgeEl = document.getElementById(`badge-${this.currentChat.id}`);
                        if (data.count > 0) {
                            if (!badgeEl) {
                                const contactEl = document.getElementById(`contact-${this.currentChat.id}`);
                                if (contactEl) {
                                    const newBadge = document.createElement('span');
                                    newBadge.id = `badge-${this.currentChat.id}`;
                                    newBadge.className = 'contact-unread-badge';
                                    newBadge.textContent = data.count > 99 ? '99+' : data.count;
                                    contactEl.appendChild(newBadge);
                                }
                            } else {
                                badgeEl.textContent = data.count > 99 ? '99+' : data.count;
                            }
                        } else if (badgeEl) badgeEl.remove();
                    }
                }
            }
        } catch (e) {
            console.error("Error checking unread count:", e);
        }
    }

    async loadContacts() {
        const container = document.getElementById("contactsList");
        if (container && container.children.length === 0) {
            container.innerHTML =
                '<div class="loading-contacts">Loading contacts...</div>';
        }

        if (!this.config || !this.config.userToken) {
            console.error("❌ Token no disponible");
            if (container)
                container.innerHTML = '<div class="error">No session</div>';
            return;
        }

        try {
            // 👉 Verificar si hay una sala activa guardada
            const shouldLoadRoomMembers = this.currentSalaId || this.lastRoomId;

            if (shouldLoadRoomMembers) {
                // Cargar miembros de la sala (no contactos globales)
                const salaId = this.currentSalaId || this.lastRoomId;
                await this.loadRoomMembers(salaId);
            } else {
                const res = await fetch("../app/ajax/contactsAjax.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Authorization: `Bearer ${this.config.userToken}`,
                    },
                    body: JSON.stringify({ modulo_contacts: "contactos" }),
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data.success) {
                        this.contacts = data.data.contacts || [];
                        this.renderContacts(); // ← ahora renderiza SIN duplicar
                    }
                }
            }
        } catch (e) {
            console.error("Error al cargar contactos:", e);
            if (container) container.innerHTML = '<div class="error">Failed to load contacts</div>';
        }
    }

    // En GreenTrackChat.js
    async loadUserRooms() {
        const res = await fetch('../app/ajax/contactsAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                modulo_contacts: 'listar_salas_usuario',
                user_id: this.config.userId
            })
        });
        const data = await res.json();
        if (data.success) {
            const container = document.getElementById('roomsList');
            if (!container) return;

            container.innerHTML = data.salas.map(sala => `
                <div class="room-item" data-sala-id="${sala.id}">
                    <div class="room-name">${this.escapeHTML(sala.nombre)}</div>
                    ${sala.unread > 0 ? `<span class="room-unread-badge">${sala.unread > 99 ? '99+' : sala.unread}</span>` : ''}
                </div>
                `).join('');

            // Añadir listeners
            container.querySelectorAll('.room-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    const salaId = e.currentTarget.dataset.salaId;
                    this.selectRoom(salaId);
                });
            });
        }
    }

    async selectRoom(salaId) {
        console.log('Codigo de la sala: ', salaId);
        this.currentSalaId = salaId;
        this.lastRoomId = salaId;

        // Guardar como última sala
        await fetch('../app/ajax/contactsAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                modulo_contacts: 'guardar_ultima_sala',
                user_id: this.config.userId,
                sala_id: salaId
            })
        });

        // ✅ Cargar miembros de la sala y actualizar lista de contactos
        await this.loadRoomMembers(salaId);
        // Actualizar UI: resaltar sala seleccionada
        document.querySelectorAll('.room-item').forEach(el => {
            el.classList.toggle('active', el.dataset.salaId == salaId);
        });

        // Deseleccionar cualquier contacto activo al cambiar de sala
        document.querySelectorAll('.contact-item.active').forEach(c => c.classList.remove('active'));

        // Ocultar área de mensajes hasta que el usuario seleccione un contacto
        const messageArea = document.getElementById('messageInputArea');
        if (messageArea) messageArea.style.display = 'none';

        const salaEl = document.querySelector(`.room-item[data-sala-id="${salaId}"]`);
        const salaName = salaEl?.querySelector(".room-name")?.textContent || "Room";
        // currentChat ahora representa la sala, sin contacto seleccionado
        this.currentChat = { id: salaId, name: salaName, isRoom: true };

        // Update header: room name and reset selected user
        const roomNameEl = document.getElementById("currentRoomName");
        if (roomNameEl) roomNameEl.textContent = salaName;
        const userNameEl = document.getElementById("currentUserName");
        if (userNameEl) userNameEl.textContent = 'None';

        // Broadcast checkbox: show and enable in room context, load stored state
        const bcContainer = document.getElementById('broadcastCheckboxContainer');
        if (bcContainer) bcContainer.style.display = 'flex';
        const bc = document.getElementById('broadcastCheckbox');
        if (bc) {
            try {
                const val = localStorage.getItem('greentrack_broadcast_' + salaId);
                bc.checked = val === '1';
            } catch (e) { /* ignore */ }

            bc.disabled = false;
            bc.onchange = (e) => {
                try { localStorage.setItem('greentrack_broadcast_' + salaId, e.target.checked ? '1' : '0'); } catch (err) { }
            };
        }

        // ✅ Mostrar mensaje de bienvenida (sin historial)
        this.showNoMessages();

        // Cargar historial de la sala
        //await this.loadRoomHistory(salaId);
    }

    async loadRoomMembers(salaId) {
        const res = await fetch('../app/ajax/contactsAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                modulo_contacts: 'get_room_members',
                sala_id: salaId,
                token: this.config.userToken
            })
        });
        const data = await res.json();

        if (data.success) {
            // Guardar contexto: estamos en una sala
            this.currentSalaId = salaId;
            this.currentContactsMode = 'room'; // ← modo sala

            // Renderizar SOLO estos contactos
            this.contacts = data.contacts.map(c => ({
                id: c.id,
                nombre: c.nombre,
                email: c.email,
                avatar: c.avatar,
                status: 'online', // o lo que venga de sesiones_activas
                unread: 0, // opcional: conteo por contacto en esta sala
                dispositivos: { pc: 'active', movil: null } // opcional
            }));
            this.renderContacts();
        }
    }

    renderContacts() {
        const container = document.getElementById("contactsList");
        if (!container || !this.contacts) return;

        // Eliminar loading si existe
        const loading = container.querySelector(".loading-contacts");
        if (loading) loading.remove();

        // Procesar cada contacto recibido
        this.contacts.forEach((contact) => {
            const contactId = `contact-${contact.id}`;
            let contactEl = document.getElementById(contactId);

            // ✅ Si no existe, crearlo
            if (!contactEl) {
                contactEl = document.createElement("div");
                contactEl.id = contactId;
                contactEl.className = "contact-item";
                contactEl.dataset.id = contact.id;
                contactEl.dataset.email = contact.email;

                // Crear estructura base con IDs
                contactEl.innerHTML = `
                    <img id="avatar-${contact.id}" 
                        src="../app/views/img/avatars/${btoa(contact.email)}.jpg" 
                        width="40" height="40"
                        class="contact-avatar"
                        onerror="this.src='../app/views/img/avatars/default.png'">
                    <div class="contact-details">
                        <div id="name-${contact.id}" class="contact-name">${this.escapeHTML(contact.nombre)}</div>
                        <div id="status-${contact.id}" class="contact-status"></div>
                    </div>
                    <!-- El badge se crea/elimina dinámicamente -->
                `;

                // ✅ Siempre añadir listener a los contactos
                contactEl.addEventListener("click", () => this.selectChat(contactEl));

                container.appendChild(contactEl);
            }

            // ✅ Actualizar nombre (por si cambió)
            const nameEl = document.getElementById(`name-${contact.id}`);
            if (nameEl && nameEl.textContent !== contact.nombre) {
                nameEl.textContent = contact.nombre;
            }

            // ✅ Actualizar estado por dispositivos (CON SOPORTE PARA "EN PAUSA")
            const statusEl = document.getElementById(`status-${contact.id}`);
            if (statusEl) {
                const deviceLines = [];

                // --- PC ---
                const pc = contact.dispositivos?.pc;
                if (pc === 'active') {
                    deviceLines.push('🟢 PC: Online');
                } else if (pc === 'pause') {
                    deviceLines.push('⏸️ PC: On pause');
                }

                // --- Móvil ---
                const movil = contact.dispositivos?.movil;
                if (movil === 'active' || movil === 'pause') {
                    deviceLines.push('📱 Mobile: Online');
                }

                const statusHTML = deviceLines.length > 0
                    ? deviceLines.join('<br>')
                    : '⚫ Offline';

                if (statusEl.innerHTML !== statusHTML) {
                    statusEl.innerHTML = statusHTML;
                }
            }

            // ✅ Actualizar badge de no leídos
            const badgeId = `badge-${contact.id}`;
            let badgeEl = document.getElementById(badgeId);
            if (contact.unread > 0) {
                if (!badgeEl) {
                    badgeEl = document.createElement("span");
                    badgeEl.id = badgeId;
                    badgeEl.className = "contact-unread-badge";
                    contactEl.appendChild(badgeEl);
                }
                badgeEl.textContent = contact.unread > 99 ? "99+" : contact.unread;
            } else if (badgeEl) {
                badgeEl.remove();
            }
        });

        // ✅ Eliminar contactos que ya no están en la lista
        const currentIds = new Set(this.contacts.map((c) => `contact-${c.id}`));
        document.querySelectorAll(".contact-item").forEach((el) => {
            if (!currentIds.has(el.id)) {
                el.remove();
            }
        });
    }

    setupEventListeners() {
        if (window.chatListenersInitialized) return;
        window.chatListenersInitialized = true;

        // Registrar interacción al usar el chat
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            ['focus', 'input', 'keydown'].forEach(eventType => {
                messageInput.addEventListener(eventType, () => this.registerChatInteraction());
            });
        }

        // Al seleccionar un contacto
        document.querySelectorAll('.contact-item').forEach(item => {
            item.addEventListener('click', () => {
                this.selectChat(item);
                this.registerChatInteraction();
            });
        });

        // Botón de envío
        document.addEventListener("click", (e) => {
            const sendBtn = e.target.closest("#sendBtn");
            if (sendBtn && this.currentChat) {
                console.log("Enviando mensaje..."); // ← Añade esto para depurar
                this.sendMessage();
            }
        });

        // Ctrl+B, Ctrl+I, Ctrl+U para formato (versión moderna, sin execCommand)
        document.addEventListener("keydown", (e) => {
            if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey) {
                const input = document.getElementById("messageInput");
                if (!input || !input.contains(document.activeElement)) return;

                let command = null;
                if (e.key === "b" || e.key === "B") {
                    command = "bold";
                } else if (e.key === "i" || e.key === "I") {
                    command = "italic";
                } else if (e.key === "u" || e.key === "U") {
                    command = "underline";
                }

                if (command) {
                    e.preventDefault();
                    // Llamar al método existente de la instancia
                    if (
                        typeof window.chatApp !== "undefined" &&
                        window.chatApp instanceof GreenTrackChat
                    ) {
                        window.chatApp.applyFormat(command);
                    }
                }
            }
        });

        // Enter para enviar
        document.addEventListener("keydown", (e) => {
            const input = document.getElementById("messageInput");
            if (input && e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                if (this.currentChat) {
                    this.sendMessage();
                }
            }
        });

        // Cerrar chat
        document.addEventListener("click", (e) => {
            const closeBtn = e.target.closest("#closeChatBtn");
            if (closeBtn) {
                const modal = document.getElementById("chatModal");
                const toggleBtn = document.getElementById("btn-chat-toggle");
                if (modal) modal.style.display = "none";
                if (toggleBtn) toggleBtn.style.display = "flex";
                this.stopPolling();
            }
        });

        // Logout
        const logoutBtn = document.getElementById("logoutChatBtn");
        if (logoutBtn) {
            logoutBtn.addEventListener("click", async () => {
                const confirmado = await suiteConfirm(
                    "Confirm Logout",
                    "Do you want to close the chat session?",
                    { aceptar: "Yes, logout", cancelar: "Cancel" }
                );
                if (confirmado) {
                    this.logoutFromChat();
                }
            });
        }

        document.addEventListener("click", (e) => {
            const formatBtn = e.target.closest(".format-btn");
            if (formatBtn && this.currentChat) {
                const command = formatBtn.dataset.command;
                this.applyFormat(command);
            }
        });

        // Insertar imagen desde URL
        document
            .getElementById("insertImageFile")
            ?.addEventListener("click", () => {
                const input = document.createElement("input");
                input.type = "file";
                input.accept = "image/*"; // ← Solo imágenes
                input.onchange = (e) => {
                    const file = e.target.files[0];
                    if (file) {
                        this.uploadFileAndInsert(file);
                    }
                };
                input.click();
            });

        // Adjuntar archivo (imagen, audio, video)
        document.getElementById("attachFile")?.addEventListener("click", () => {
            const input = document.createElement("input");
            input.type = "file";
            input.accept = "image/*,audio/*,video/*";
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
        textarea.style.height = "auto";
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + "px";
    }

    applyFormat(command) {
        const input = document.getElementById("messageInput");
        if (!input) return;
        input.focus();

        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) return;

        const range = selection.getRangeAt(0);
        if (range.toString().trim() === "") return; // No aplicar si no hay texto seleccionado

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
            case "bold":
                return "strong";
            case "italic":
                return "em";
            case "underline":
                return "u";
            case "strikeThrough":
                return "s";
            default:
                return "span";
        }
    }

    async sendMessage() {
        console.log("sendMessage() llamado");
        if (!this.currentChat || !this.currentChat.email) {
            // Opcional: mostrar tooltip "Selecciona un contacto"
            return;
        }

        const input = document.getElementById("messageInput");
        if (!input || !this.currentChat) {
            console.warn("No hay input o no hay chat seleccionado");
            return;
        }

        let content = input.innerHTML.trim();
        content = content.replace(
            /<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi,
            ""
        );
        if (!content || content === "<br>") {
            console.warn("Mensaje vacío");
            return;
        }

        console.log("Contenido a enviar:", content);
        try {
            // ✅ Detectar si es sala o contacto
            const isRoom = this.currentChat.isRoom;

            // Construir payload considerando contexto de sala o 1:1
            const salaId = this.currentSalaId || this.lastRoomId || null;
            const payload = {
                modulo_contacts: "send_message",
                content: content,
                token: this.config.userToken
            };

            // Broadcast checkbox (solo relevante en salas)
            const broadcastEl = document.getElementById('broadcastCheckbox');
            const broadcast = !!(broadcastEl && broadcastEl.checked);

            if (salaId) {
                // Mensaje dentro de una sala (grupo). Backend debe asociarlo a la sala.
                payload.sala_id = salaId;
                payload.is_room = true;
                if (!broadcast) {
                    // Si NO es broadcast y hay contacto seleccionado, enviar como privado dentro de la sala
                    if (this.currentChat && this.currentChat.email) payload.to = this.currentChat.email;
                } else {
                    payload.broadcast = true;
                }
            } else {
                payload.is_room = false;
                if (this.currentChat && this.currentChat.email) {
                    payload.to = this.currentChat.email;
                } else if (this.currentChat && this.currentChat.id) {
                    payload.contact_id = this.currentChat.id;
                }
            }
            console.log("Paquete de envio ", payload);
            // ✅ Enviar parámetros según el contexto
            // if (isRoom) {
            //     payload.sala_id = this.currentChat.id; // ID de la sala
            // } else {
            //     payload.to = this.currentChat.email;   // Email del contacto
            // }

            const res = await fetch("../app/ajax/contactsAjax.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.success) {
                input.innerHTML = "";
                this.scrollToBottom();
            }
        } catch (e) {
            console.error("Error al enviar mensaje:", e);
        }
    }

    // Nueva función: seleccionar cualquier elemento (contacto o sala)
    selectChat(el) {
        if (el.classList.contains('room-item')) {
            this.selectRoom(el.dataset.salaId);
        } else {
            this.selectContact(el);
        }
    }

    // Nueva función: manejo de contactos 1:1
    selectContact(el) {
        if (this.polling) this.stopPolling();

        document.querySelectorAll(".contact-item").forEach(i =>
            i.classList.remove("active")
        );
        el.classList.add("active");

        const userId = el.dataset.id;
        const userEmail = el.dataset.email;
        const userName = el.querySelector(".contact-name").textContent;

        this.currentChat = {
            id: userId,
            email: userEmail,
            name: userName,
            isRoom: false
        };

        console.log("current chat set to:", this.currentChat);
        const userNameEl = document.getElementById("currentUserName");
        if (userNameEl) userNameEl.textContent = userName;

        const messageArea = document.getElementById("messageInputArea");
        if (messageArea) messageArea.style.display = "flex";

        this.unreadCount = 0;
        this.updateUnreadBadge(0);
        this.updateTabTitle(0);

        this.loadChatHistory(userId);
        this.startPolling();
        this.markMessagesAsRead(userId);

        // Keep broadcast checkbox visible but disable it if there's no active room
        const bc = document.getElementById('broadcastCheckbox');
        if (bc) {
            const hasRoom = !!(this.currentSalaId || this.lastRoomId);
            bc.disabled = !hasRoom;
        }
    }


    async markMessagesAsRead(contactId) {
        try {
            await fetch("../app/ajax/contactsAjax.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    modulo_contacts: "mark_messages_as_read",
                    contact_id: contactId,
                    token: this.config.userToken,
                }),
            });
            // Actualizar lista de contactos para reflejar 0 mensajes
            this.loadContacts();
        } catch (e) {
            console.error("Error marking as read:", e);
        }
    }

    async loadChatHistory(contactId) {
        try {
            const salaId = this.currentSalaId || this.lastRoomId;
            console.log("🔍 loadChatHistory - currentSalaId:", this.currentSalaId, "lastRoomId:", this.lastRoomId, "salaId enviado:", salaId);

            const res = await fetch("../app/ajax/contactsAjax.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    modulo_contacts: "get_history",
                    contact_id: contactId,
                    token: this.config.userToken,
                    salaId: salaId,
                }),
            });
            const data = await res.json();
            if (data.success && data.data?.length) {
                const messages = data.data.map((msg) => ({
                    id: msg.id,
                    user: {
                        id: msg.remitente_id,
                        nombre: msg.remitente_nombre,
                        email: msg.remitente_email,
                    },
                    content: msg.content,
                    timestamp: msg.timestamp,
                    leido: msg.leido,
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
            console.error("Error al cargar historial:", e);
        }
    }

    // Para salas grupales
    async loadRoomHistory(salaId) {
        try {
            const res = await fetch("../app/ajax/contactsAjax.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    modulo_contacts: "get_room_history", // ← nuevo módulo
                    sala_id: salaId,
                    token: this.config.userToken,
                }),
            });
            const data = await res.json();
            if (data.success && data.data?.length) {
                const messages = data.data.map((msg) => ({
                    id: msg.id,
                    user: {
                        id: msg.remitente_id,
                        nombre: msg.remitente_nombre,
                        email: msg.remitente_email,
                    },
                    content: msg.content,
                    timestamp: msg.timestamp,
                    leido: msg.leido,
                    sala_id: salaId, // ← útil para marcar como leídos
                }));
                this.renderMessagesWithGroups(messages);
                this.currentSalaId = salaId; // ← guardar contexto
                this.lastMessageId = data.data[data.data.length - 1].id;

                setTimeout(() => {
                    this.setMessagesContainerHeight();
                    this.scrollToBottom();
                }, 100);

                // ✅ Marcar mensajes de ESTA SALA como leídos
                this.markMessagesAsReadInRoom(salaId);
            } else {
                this.showNoMessages();
            }
        } catch (e) {
            console.error("Error al cargar historial de sala:", e);
        }
    }

    async markMessagesAsReadInRoom(salaId) {
        const messageIds = Array.from(
            document.querySelectorAll(`[data-sala-id="${salaId}"] .message[data-id]`)
        ).map(el => el.dataset.id);

        if (messageIds.length > 0) {
            await fetch('../app/ajax/contactsAjax.php', {
                method: 'POST',
                body: JSON.stringify({
                    modulo_contacts: 'marcar_leidos_por_sala',
                    sala_id: salaId,
                    user_id: this.config.userId
                })
            });
            // Actualizar badge de la sala
            this.updateRoomUnreadBadge(salaId, 0);
        }
    }

    updateRoomUnreadBadge(salaId, count) {
        const roomEl = document.querySelector(`.room-item[data-sala-id="${salaId}"]`);
        if (!roomEl) return;

        const existingBadge = roomEl.querySelector(".room-unread-badge");
        if (existingBadge) existingBadge.remove();

        if (count > 0) {
            const badge = document.createElement("span");
            badge.className = "room-unread-badge";
            badge.textContent = count > 99 ? "99+" : count;
            roomEl.appendChild(badge);
        }
    }

    async loadNewMessages() {
        if (!this.currentChat) return;
        try {
            const salaId = this.currentSalaId || this.lastRoomId;
            const res = await fetch("../app/ajax/contactsAjax.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    modulo_contacts: "get_history_since",
                    contact_id: this.currentChat.id,
                    since_id: this.lastMessageId,
                    token: this.config.userToken,
                    salaId: salaId,
                }),
            });
            const data = await res.json();
            if (data.success && data.data?.length) {
                const messages = data.data.map((msg) => ({
                    id: msg.id,
                    user: {
                        id: msg.remitente_id,
                        nombre: msg.remitente_nombre,
                        email: msg.remitente_email,
                    },
                    content: msg.content,
                    timestamp: msg.timestamp,
                }));
                messages.forEach((msg) => {
                    if (!document.querySelector(`[data-id="${msg.id}"]`)) {
                        this.addMessageToGroup(msg);
                    }
                });
                this.lastMessageId = data.data[data.data.length - 1].id;
            }
        } catch (e) {
            console.error("Error al cargar nuevos mensajes:", e);
        }
    }

    getGroupHeaderText(groupKey) {
        if (groupKey === "today") return "Today";
        if (groupKey === "yesterday") return "Yesterday";
        // Para días de la semana
        const days = [
            "monday",
            "tuesday",
            "wednesday",
            "thursday",
            "friday",
            "saturday",
            "sunday",
        ];

        if (days.includes(groupKey.toLowerCase())) {
            return groupKey.charAt(0).toUpperCase() + groupKey.slice(1);
        }
        // Para "15 de diciembre" o "diciembre 2024", el key ya está en formato legible
        return groupKey;
    }

    renderMessagesWithGroups(messages) {
        const container = document.getElementById("messagesContainer");
        if (!container) return;

        // Si no hay mensajes, mostrar "No messages"
        if (!messages || messages.length === 0) {
            this.showNoMessages();
            return;
        }

        const groups = {};
        const unreadByGroup = {};

        messages.forEach((msg) => {
            const dateKey = this.getMessageGroupKey(msg.timestamp);
            if (!groups[dateKey]) {
                groups[dateKey] = [];
                unreadByGroup[dateKey] = 0;
            }
            groups[dateKey].push(msg);

            const isOwnMessage = msg.user.id == this.config.userId;
            if (!isOwnMessage && (msg.leido === 0 || msg.leido === "0")) {
                unreadByGroup[dateKey]++;
            }
        });

        const sortedDates = Object.keys(groups).sort(
            (a, b) => new Date(a) - new Date(b)
        );
        const lastDate = sortedDates.length > 0 ? sortedDates[sortedDates.length - 1] : null;

        let html = "";
        sortedDates.forEach((dateKey) => {
            const formattedDate = this.getGroupHeaderText(dateKey);
            const unreadCount = unreadByGroup[dateKey] || 0;
            const isOpen = dateKey === lastDate;
            const displayStyle = isOpen ? "block" : "none";
            const iconClass = isOpen ? "fas fa-chevron-down" : "fas fa-chevron-right";
            const unreadBadge = unreadCount > 0
                ? `<span class="group-unread-badge">${unreadCount}</span>`
                : "";

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
                        ${groups[dateKey].map((msg) => this.createMessageElement(msg)).join("")}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;

        // Añadir listeners a los toggles
        container.querySelectorAll(".toggle-date-group").forEach((btn) => {
            btn.addEventListener("click", (e) => {
                const dateKey = e.currentTarget.dataset.date;
                const messagesDiv = document.getElementById(`messages-${dateKey}`);
                const isOpen = messagesDiv.style.display !== "none";
                const dateHeader = e.currentTarget.closest(".date-header");
                const unreadBadge = dateHeader?.querySelector(".group-unread-badge");

                if (!isOpen) {
                    messagesDiv.style.display = "block";
                    e.currentTarget.querySelector("i").className = "fas fa-chevron-down";

                    const messageIds = Array.from(
                        messagesDiv.querySelectorAll(".message[data-id]")
                    ).map(el => el.dataset.id);

                    if (messageIds.length > 0) {
                        this.markMessagesByIdAsRead(messageIds).then(() => {
                            if (unreadBadge) unreadBadge.remove();
                        });
                    }
                } else {
                    messagesDiv.style.display = "none";
                    e.currentTarget.querySelector("i").className = "fas fa-chevron-right";
                }
            });
        });

        // Scroll al final
        if (lastDate) {
            const lastGroup = document.getElementById(`messages-${lastDate}`);
            if (lastGroup && lastGroup.lastElementChild) {
                lastGroup.lastElementChild.scrollIntoView({ behavior: "smooth", block: "end" });
            }
        }
    }

    addMessageToGroup(message) {
        const container = document.getElementById("messagesContainer");
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
            container.insertAdjacentHTML("beforeend", newGroup);

            // Vincular listener al nuevo botón
            const newBtn = container.querySelector(`[data-date="${dateKey}"]`);
            if (newBtn) {
                newBtn.addEventListener("click", (e) => {
                    const messagesDiv = document.getElementById(
                        `messages-${dateKey}`
                    );
                    const isOpen = messagesDiv.style.display !== "none";

                    if (!isOpen) {
                        messagesDiv.style.display = "block";
                        e.currentTarget.querySelector("i").className =
                            "fas fa-chevron-down";

                        // ✅ Marcar mensajes de este grupo como leídos
                        const messageElements =
                            messagesDiv.querySelectorAll(".message[data-id]");
                        const messageIds = Array.from(messageElements).map(
                            (el) => el.dataset.id
                        );
                        if (messageIds.length > 0) {
                            this.markMessagesByIdAsRead(messageIds);
                        }
                    } else {
                        messagesDiv.style.display = "none";
                        e.currentTarget.querySelector("i").className =
                            "fas fa-chevron-right";
                    }
                });
            }
        } else {
            // Añadir mensaje al grupo existente
            group.insertAdjacentHTML(
                "beforeend",
                this.createMessageElement(message)
            );
            const lastMsg = group.lastElementChild;
            const isScrolledToBottom =
                container.scrollHeight - container.clientHeight <=
                container.scrollTop + 1;
            if (isScrolledToBottom) {
                lastMsg.scrollIntoView({ behavior: "smooth", block: "end" });
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
            <div class="message ${isOwn ? "own-message" : ""}" data-id="${message.id
            }">
                <img src="../app/views/img/avatars/${btoa(message.user.email)}.jpg" 
                    class="message-avatar" onerror="this.src='../app/views/img/avatars/default.png'">
                <div class="message-content">
                    <div class="message-text">${message.content}</div>
                    <div class="message-time">${this.formatTime(
                message.timestamp
            )}</div>
                </div>
            </div>
        `;
    }

    getMessageGroupKey(timestamp) {
        // Parsear timestamp del servidor (formato: "2025-12-10 14:30:00")
        const [datePart] = timestamp.split(" ");
        const [Y, M, D] = datePart.split("-").map(Number);

        // Asumir que el servidor envía hora en Chicago (UTC-6 en invierno)
        // Convertir a UTC para comparar con la fecha local del cliente
        const utcTime = Date.UTC(Y, M - 1, D, 12, 0, 0); // 12:00 UTC para evitar ambigüedad
        const msgDate = new Date(utcTime);

        const now = new Date();
        const today = new Date(
            now.getFullYear(),
            now.getMonth(),
            now.getDate()
        );
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);

        const msgDay = new Date(
            msgDate.getFullYear(),
            msgDate.getMonth(),
            msgDate.getDate()
        );

        // 1. Hoy
        if (msgDay.getTime() === today.getTime()) {
            return "today";
        }

        // 2. Ayer
        if (msgDay.getTime() === yesterday.getTime()) {
            return "yesterday";
        }

        // 3. Esta semana (últimos 7 días)
        const sevenDaysAgo = new Date(today);
        sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
        if (msgDay >= sevenDaysAgo) {
            return msgDay.toLocaleDateString("en-US", { weekday: "long" });
        }

        // 4. Este año (pero fuera de esta semana)
        if (msgDate.getFullYear() === now.getFullYear()) {
            return msgDate.toLocaleDateString("en-US", {
                day: "numeric",
                month: "long",
            });
        }

        // 5. Años anteriores
        return msgDate.toLocaleDateString("en-US", {
            month: "long",
            year: "numeric",
        });
    }

    showNoMessages() {
        const container = document.getElementById("messagesContainer");
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
        if (
            typeof dateTimeString !== "string" ||
            !dateTimeString.includes(" ")
        ) {
            return "—";
        }

        const [datePart, timePart] = dateTimeString.split(" ");
        const [Y, M, D] = datePart.split("-").map(Number);
        const [h, m, s] = (timePart || "00:00:00").split(":").map(Number);

        // Validar que los componentes sean números válidos y dentro de rangos razonables
        if (
            isNaN(Y) ||
            isNaN(M) ||
            isNaN(D) ||
            isNaN(h) ||
            isNaN(m) ||
            isNaN(s) ||
            Y < 1970 ||
            Y > 2100 ||
            M < 1 ||
            M > 12 ||
            D < 1 ||
            D > 31 ||
            h < 0 ||
            h > 23 ||
            m < 0 ||
            m > 59 ||
            s < 0 ||
            s > 59
        ) {
            return "—";
        }

        // Asumir que la hora del servidor es Chicago (UTC-6 en invierno)
        // Convertir a UTC sumando 6 horas
        const utcTimestamp = Date.UTC(Y, M - 1, D, h + 6, m, s);

        // Verificar que la fecha sea válida
        const date = new Date(utcTimestamp);
        if (isNaN(date.getTime())) {
            return "—";
        }

        // Formatear en la zona horaria del cliente
        return date.toLocaleTimeString("en-US", {
            hour: "2-digit",
            minute: "2-digit",
        });
    }

    escapeHTML(text) {
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    displayIncomingMessage(message) {
        const container = document.getElementById("messagesContainer");
        if (!container) return;

        const html = this.createMessageElement(message);
        container.insertAdjacentHTML("beforeend", html);

        // Scroll inteligente
        this.scrollToBottom();
    }

    scrollToBottom() {
        const container = document.getElementById("messagesContainer");
        if (!container) return;

        const lastMessage = container.lastElementChild?.lastElementChild;
        if (lastMessage) {
            lastMessage.scrollIntoView({
                behavior: "smooth",
                block: "nearest",
                inline: "nearest",
            });
        }
    }

    setMessagesContainerHeight() {
        const messagesArea = document.querySelector(".messages-area");
        const header = document.querySelector(".messages-header");
        const inputArea = document.getElementById("messageInputArea");
        const container = document.getElementById("messagesContainer");

        if (messagesArea && header && inputArea && container) {
            const areaHeight = messagesArea.clientHeight;
            const headerHeight = header.offsetHeight;
            const inputHeight = inputArea.offsetHeight;
            const padding = 20; // Ajusta según tu diseño

            const containerHeight =
                areaHeight - headerHeight - inputHeight - padding;
            container.style.height = containerHeight + "px";
            container.style.overflowY = "auto";
        }
    }

    async uploadFileAndInsert(file) {
        const formData = new FormData();
        formData.append("file", file);
        formData.append("modulo_contacts", "upload_file");

        try {
            const res = await fetch("../app/ajax/contactsAjax.php", {
                method: "POST",
                body: formData,
            });
            const data = await res.json();

            if (data.success && data.url) {
                let html = "";
                if (file.type.startsWith("image/")) {
                    html = `<img src="${data.url}" alt="Imagen" style="max-width:100%; height:auto;">`;
                } else if (file.type.startsWith("audio/")) {
                    html = `<audio controls src="${data.url}"></audio>`;
                } else if (file.type.startsWith("video/")) {
                    html = `<video controls src="${data.url}" style="max-width:100%; height:auto;"></video>`;
                } else {
                    html = `<a href="${data.url
                        }" target="_blank">📎 ${this.escapeHTML(file.name)}</a>`;
                }
                this.insertHtmlAtCursor(html);
            } else {
                alert("Error al subir el archivo.");
            }
        } catch (e) {
            console.error("Upload error:", e);
            suiteAlertInfo("Error", "The file could not be uploaded.");
        }
    }

    insertHtmlAtCursor(html) {
        const input = document.getElementById("messageInput");
        if (!input) return;
        input.focus();

        if (window.getSelection) {
            const sel = window.getSelection();
            if (sel.getRangeAt && sel.rangeCount) {
                const range = sel.getRangeAt(0);
                range.deleteContents();
                const el = document.createElement("div");
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
        if (!Array.isArray(messageIds) || messageIds.length === 0) return;

        try {
            const res = await fetch("../app/ajax/contactsAjax.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    modulo_contacts: "mark_messages_as_read",
                    message_ids: messageIds,
                    token: this.config.userToken,
                }),
            });
            const data = await res.json();
            if (data.success) {
                // ✅ 1. Eliminar el badge del grupo (ya lo haces implícitamente al recargar, pero si no...)
                const unreadBadge = document.querySelector(`.group-unread-badge`);
                if (unreadBadge) unreadBadge.remove();

                // ✅ 2. ACTUALIZAR EL BADGE DE LA SALA ACTUAL
                if (this.currentSalaId) {
                    // Recargar solo el conteo de la sala actual
                    const salaRes = await fetch("../app/ajax/contactsAjax.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({
                            modulo_contacts: "listar_salas_usuario",
                            user_id: this.config.userId
                        }),
                    });
                    const salaData = await salaRes.json();
                    if (salaData.success) {
                        const sala = salaData.salas.find(s => s.id == this.currentSalaId);
                        if (sala) {
                            this.updateRoomUnreadBadge(this.currentSalaId, sala.unread);
                        }
                    }
                }
            }
        } catch (e) {
            console.error("Error marking as read:", e);
        }
    }

    async logoutFromChat() {
        const baseUrl = window.chatConfig?.baseUrl || "/";
        const url = baseUrl + "/app/ajax/usuariosAjax.php";

        const formData = new FormData();
        formData.append("modulo_usuarios", "cerrar_sesion");

        try {
            const response = await fetch(url, {
                method: "POST",
                body: formData,
                credentials: "same-origin",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            const text = await response.text();
            if (!text.trim()) {
                throw new Error("Empty response");
            }

            let json;
            try {
                json = JSON.parse(text);
            } catch (parseErr) {
                console.error("Server returned non-JSON response:", text);
                throw new Error("Server error (check console)");
            }

            if (!json.success) {
                throw new Error(json.message || "Logout failed");
            }

            // ✅ Limpiar estado global
            delete window.chatConfig;
            window.chatApp = null;
            window.chatListenersInitialized = false;

            // ✅ Limpiar y cerrar UI
            const modal = document.getElementById("chatModal");
            const btn = document.getElementById("btn-chat-toggle");
            if (modal) {
                modal.innerHTML = ""; // ← ESTO ES CLAVE
                modal.style.display = "none";
            }
            if (btn) btn.style.display = "flex";

            suiteAlertInfo("Action Close", "Chat session closed.");
        } catch (e) {
            console.error("Logout failed:", e);
            suiteAlertInfo("Error", "Could not close chat session.");
        }
    }

    initRichEditor() {
        const editorElement = document.getElementById("editor");
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
        editorElement.insertAdjacentHTML("beforebegin", toolbarHtml);

        this.richEditor = new Tiptap.Editor({
            element: editorElement,
            extensions: [
                Tiptap.StarterKit,
                Tiptap.Emoji.configure({
                    enableShortcodes: true,
                }),
            ],
            content: "",
            autofocus: false,
        });

        // Configurar botones de toolbar
        document.querySelectorAll(".tiptap-toolbar button").forEach((btn) => {
            const command = btn.dataset.command;
            btn.addEventListener("click", () => {
                if (command === "emoji") {
                    // Emoji picker básico (puedes expandirlo)
                    const emoji = prompt(
                        "Insert emoji or shortcode (e.g. :smile:)"
                    );
                    if (emoji)
                        this.richEditor
                            .chain()
                            .focus()
                            .insertContent(emoji)
                            .run();
                } else if (this.richEditor.can().toggleMark(command)) {
                    this.richEditor.commands.toggleMark(command);
                }
            });
        });
    }

    updateStatus(status, type = "info") {
        const statusElement = document.getElementById("userStatus");
        if (statusElement) {
            statusElement.textContent = status;
            statusElement.className = `user-status status-${type}`;
        }
    }

    displayIncomingMessage(message) {
        const container = document.getElementById("messagesContainer");
        if (!container) return;
        const html = this.createMessageElement(message);

        // ✅ CORRECTO: solo agrega el nuevo HTML, sin tocar el existente
        container.insertAdjacentHTML("beforeend", html);

        // Ajuste de scroll (mejorado)
        if (
            container.scrollHeight - container.clientHeight <=
            container.scrollTop + 5
        ) {
            container.scrollTop = container.scrollHeight;
        }
    }

    // Método para enviar heartbeat
    sendHeartbeat() {
        if (!this.config?.userToken) return;

        // Detectar si es móvil o PC
        const isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        const dispositivo = isMobile ? "movil" : "pc";

        // Para móvil, siempre "activo"
        // Para PC, depende de la interacción
        let modo = 'active';
        if (!isMobile) {
            const inactividad = Date.now() - this.lastChatInteraction;
            modo = (inactividad < this.INTERACTION_TIMEOUT) ? 'active' : 'pause';
        }

        const data = {
            modulo_usuarios: "heartbeat",
            token: this.config.userToken,
            dispositivo: dispositivo,
            modo: modo,
        };

        const baseUrl = window.chatConfig?.baseUrl || "/";
        const url = baseUrl + "/app/ajax/usuariosAjax.php";

        fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data),
        }).catch((err) => {
            console.warn("Heartbeat fallido:", err);
        });
    }

    // Llamar cada 45 segundos (menos que el umbral de "online", ej: 60s)
    startHeartbeat() {
        this.sendHeartbeat(); // Inmediato al iniciar
        this.heartbeatInterval = setInterval(() => {
            this.sendHeartbeat();
        }, 45000);
    }

    // Detener al salir
    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
    }
}
