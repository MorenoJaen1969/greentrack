// app/views/inc/js/menu-control.js

class MenuControl {
    constructor() {
        this.apiUrl = '/app/ajax/datosgeneralesAjax.php';
        this.menuProtegido = ['parametros', 'usuarios', 'salas'];
        this.init();
    }
    
    async init() {
        await this.cargarProcesosProtegidos();
        this.interceptarLinksProtegidos();
        this.agregarEventListeners();
    }
    
    /**
     * Intercept clicks on protected menu links
     */
    async cargarProcesosProtegidos() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    modulo_DG: 'obtener_procesos_protegidos'
                })
            });
            
            const result = await response.json();

            if (result.success) {
                this.procesosProtegidos = Object.keys(result.procesos);
                console.log('✅ Procesos protegidos cargados:', this.procesosProtegidos);
            }
        } catch (error) {
            console.error('Error cargando procesos protegidos:', error);
            // Fallback a valores hardcoded
            this.procesosProtegidos = ['parametros', 'usuarios', 'salas'];
        }
    }
    
    /**
     * Intercept clicks on protected menu links
     */
    interceptarLinksProtegidos() {
        const menuLinks = document.querySelectorAll('#menu-lateral a.menu-item');
        
        menuLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                if (!href || href === '#') return;
                
                const urlPath = href.split('/').filter(Boolean).pop();
                
                // Verificar si está en la lista de procesos protegidos
                if (this.procesosProtegidos.includes(urlPath)) {
                    e.preventDefault();
                    this.verificarAccesoAntesDeNavegar(urlPath, href);
                }
            });
        });
    }

    /**
     * Verify access before navigating to protected URL
     */
    async verificarAccesoAntesDeNavegar(urlPath, fullUrl) {
        try {
            // Check if URL is protected
            const checkResponse = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    modulo_DG: 'es_url_protegida',
                    url: urlPath
                })
            });
            
            const checkResult = await checkResponse.json();
            
            if (!checkResult.protegida) {
                // Not protected, allow navigation
                window.location.href = fullUrl;
                return;
            }
            
            // URL is protected, verify authentication
            const authResponse = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    modulo_DG: 'verificar_acceso'
                })
            });
            
            const authResult = await authResponse.json();
            
            if (authResult.success) {
                // Already authenticated, allow navigation
                window.location.href = fullUrl;
            } else {
                // Not authenticated, show login modal
                this.mostrarModalLogin(fullUrl);
            }
        } catch (error) {
            console.error('Error verifying access:', error);
            suiteAlert('Connection error. Please try again.', 'error');
        }
    }
    
    /**
     * Show login modal and set redirect URL
     */
    async mostrarModalLogin(redirectUrl) {
        try {
            // Set redirect URL in session
            await fetch(this.apiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    modulo_DG: 'set_redirect_url',
                    url: redirectUrl
                })
            });
            
            // Create and show login modal
            this.crearModalLogin();
        } catch (error) {
            console.error('Error setting redirect URL:', error);
            suiteAlert('Error preparing authentication. Please try again.', 'error');
        }
    }
    
    /**
     * Create login modal HTML
     */
    crearModalLogin() {
        // Remove existing modal if any
        const existingModal = document.getElementById('modal-parametros-login');
        if (existingModal) existingModal.remove();
        
        const modalHTML = `
            <div id="modal-parametros-login" class="modal-overlay" style="display: flex; align-items: center; justify-content: center; z-index: 10000;">
                <div class="modal-contenedor" style="width: 400px; padding: 20px;">
                    <button id="close-login-modal" class="modal-cerrar1" style="position: absolute; top: 10px; right: 10px;">✕</button>
                    
                    <div style="text-align: center; margin-bottom: 20px;">
                        <div style="font-size: 3em; margin-bottom: 10px;">🔐</div>
                        <h3 style="margin: 0; font-size: 1.3em;">General Maintenance Access</h3>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 0.9em;">Please authenticate to continue</p>
                    </div>
                    
                    <form id="parametros-login-form">
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                <i class="fas fa-user"></i> Username
                            </label>
                            <input type="text" id="login-username" class="input-text" 
                                    style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px;"
                                    required autofocus>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                <i class="fas fa-key"></i> Password
                            </label>
                            <input type="password" id="login-password" class="input-text"
                                    style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px;"
                                    required>
                        </div>
                        
                        <div id="login-message" style="margin-bottom: 15px; display: none;"></div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="button" id="btn-cancel-login" class="btn-secondary"
                                    style="flex: 1; padding: 10px; border: none; border-radius: 5px; cursor: pointer;">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" id="btn-submit-login" class="btn-primary"
                                    style="flex: 1; padding: 10px; border: none; border-radius: 5px; 
                                            background: #007bff; color: white; cursor: pointer;">
                                <i class="fas fa-sign-in-alt"></i> Access
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.initLoginModalEvents();
    }
    
    /**
     * Initialize login modal events
     */
    initLoginModalEvents() {
        const modal = document.getElementById('modal-parametros-login');
        
        // Close button
        document.getElementById('close-login-modal').addEventListener('click', () => {
            modal.remove();
        });
        
        // Cancel button
        document.getElementById('btn-cancel-login').addEventListener('click', () => {
            modal.remove();
        });
        
        // Form submit
        document.getElementById('parametros-login-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.procesarLogin();
        });
        
        // Close on overlay click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                modal.remove();
            }
        });
    }
    
    /**
     * Process login form submission
     */
    async procesarLogin() {
        const username = document.getElementById('login-username').value;
        const password = document.getElementById('login-password').value;
        const submitBtn = document.getElementById('btn-submit-login');
        const messageDiv = document.getElementById('login-message');
        
        // Disable button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';
        
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    modulo_DG: 'autenticar',
                    username: username,
                    password: password
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message
                messageDiv.style.display = 'block';
                messageDiv.style.backgroundColor = '#d4edda';
                messageDiv.style.color = '#155724';
                messageDiv.style.padding = '10px';
                messageDiv.style.borderRadius = '5px';
                messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> Access granted. Redirecting...';
                
                // Clear redirect URL from session
                await fetch(this.apiUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        modulo_DG: 'clear_redirect_url'
                    })
                });
                
                // Redirect after delay
                setTimeout(() => {
                    const modal = document.getElementById('modal-parametros-login');
                    if (modal) modal.remove();
                    
                    if (result.redirect_url) {
                        window.location.href = result.redirect_url;
                    } else {
                        // Default redirect if no URL stored
                        window.location.href = '/parametros';
                    }
                }, 1500);
            } else {
                // Show error message
                messageDiv.style.display = 'block';
                messageDiv.style.backgroundColor = '#f8d7da';
                messageDiv.style.color = '#721c24';
                messageDiv.style.padding = '10px';
                messageDiv.style.borderRadius = '5px';
                messageDiv.textContent = result.message || 'Authentication failed';
                
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Access';
                
                // Clear password field
                document.getElementById('login-password').value = '';
                document.getElementById('login-password').focus();
            }
        } catch (error) {
            console.error('Login error:', error);
            messageDiv.style.display = 'block';
            messageDiv.style.backgroundColor = '#f8d7da';
            messageDiv.style.color = '#721c24';
            messageDiv.style.padding = '10px';
            messageDiv.style.borderRadius = '5px';
            messageDiv.textContent = 'Connection error. Please try again.';
            
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Access';
        }
    }
    
    /**
     * Add event listeners for additional controls
     */
    agregarEventListeners() {
        // Add any additional event listeners here
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.menuControl = new MenuControl();
});