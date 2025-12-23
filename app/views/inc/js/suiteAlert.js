// Funciones específicas para cada tipo de alerta

function suiteAlertSuccess(titulo, mensaje) {
    return mostrarSuiteAlert('success', titulo, mensaje);
}

function suiteAlertError(titulo, mensaje) {
    return mostrarSuiteAlert('error', titulo, mensaje);
}

function suiteAlertWarning(titulo, mensaje) {
    return mostrarSuiteAlert('warning', titulo, mensaje);
}

function suiteAlertInfo(titulo, mensaje) {
    return mostrarSuiteAlert('info', titulo, mensaje);
}

// Modal de confirmación
function suiteConfirm(titulo, mensaje, opcionesConfirm = {}) {
    const opciones = {
        botones: [
            { texto: opcionesConfirm.cancelar || 'Cancelar', tipo: 'secondary', valor: false },
            { texto: opcionesConfirm.aceptar || 'Aceptar', tipo: 'primary', valor: true }
        ]
    };
    return mostrarSuiteAlert('warning', titulo, mensaje, opciones);
}

function mostrarSuiteAlert(tipo, titulo, mensaje, opciones = {}) {
    return new Promise((resolve) => {
        // Eliminar alerta existente
        const alertaExistente = document.querySelector('.alerta-overlay');
        if (alertaExistente) {
            alertaExistente.remove();
        }

        // Crear elementos de la alerta
        const overlay = document.createElement('div');
        overlay.className = 'alerta-overlay';

        const alerta = document.createElement('div');
        alerta.className = 'suite-alerta-box';

        const header = document.createElement('div');
        header.className = `alerta-header ${tipo}`;

        const icon = document.createElement('div');
        icon.className = 'alerta-icon';

        // Iconos según el tipo
        const iconos = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        icon.textContent = iconos[tipo] || 'ℹ';

        const title = document.createElement('h2');
        title.className = 'alerta-title';
        title.textContent = titulo;

        const body = document.createElement('div');
        body.className = 'alerta-body';

        const message = document.createElement('p');
        message.className = 'alerta-message';
        message.textContent = mensaje;

        const actions = document.createElement('div');
        actions.className = 'alerta-actions';

        // Botones según opciones
        const botones = opciones.botones || [{ texto: 'OK', tipo: 'primary' }];

        botones.forEach((btn, index) => {
            const button = document.createElement('button');
            button.className = `btn-alerta btn-alerta-${btn.tipo}`;
            button.textContent = btn.texto;
            button.onclick = () => {
                overlay.classList.remove('activo');
                setTimeout(() => {
                    overlay.remove();
                    resolve(btn.valor || index);
                }, 300);
            };
            actions.appendChild(button);
        });

        // Construir la alerta
        header.appendChild(icon);
        header.appendChild(title);
        body.appendChild(message);
        body.appendChild(actions);
        alerta.appendChild(header);
        alerta.appendChild(body);
        overlay.appendChild(alerta);

        // Añadir al documento
        document.body.appendChild(overlay);

        // Mostrar con animación
        setTimeout(() => {
            overlay.classList.add('activo');
            alerta.classList.add('alerta-pulse');
        }, 10);

        // Cerrar con Escape
        const teclaEscape = (e) => {
            if (e.key === 'Escape') {
                overlay.classList.remove('activo');
                setTimeout(() => {
                    overlay.remove();
                    document.removeEventListener('keydown', teclaEscape);
                    resolve(null);
                }, 300);
            }
        };
        document.addEventListener('keydown', teclaEscape);

        // Cerrar haciendo clic fuera de la alerta
        if (opciones.cerrarClickFuera !== false) {
            overlay.onclick = (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('activo');
                    setTimeout(() => {
                        overlay.remove();
                        resolve(null);
                    }, 300);
                }
            };
        }
    });
}

function suiteLoading(action = 'show') {
    const existingLoading = document.getElementById('suite-loading');
    
    if (action === 'show') {
        if (existingLoading) {
            existingLoading.style.display = 'flex';
            return;
        }
        
        const loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'suite-loading';
        loadingOverlay.className = 'suite-loading-overlay';
        
        // SVG del INFINITO HORIZONTAL correcto
        const svgHTML = `
            <svg class="infinity-svg" viewBox="-40 -20 480 160" preserveAspectRatio="xMidYMid meet">
                <defs>
                    <linearGradient id="glassGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#00d4aa" />
                        <stop offset="50%" stop-color="#0099ff" />
                        <stop offset="100%" stop-color="#00d4aa" />
                    </linearGradient>
                </defs>
                <path class="infinity-path" 
                      d="M 200,60 
                         C 100,0 100,120 200,60 
                         C 300,120 300,0 200,60 
                         Z" 
                      fill="none" 
                      stroke="url(#glassGradient)" 
                      stroke-width="20" 
                      stroke-linecap="round"/>
            </svg>
        `;
        
        loadingOverlay.innerHTML = svgHTML;
        document.body.appendChild(loadingOverlay);
        document.body.classList.add('suite-loading-active');
        
    } else if (action === 'hide') {
        if (existingLoading) {
            existingLoading.remove();
            document.body.classList.remove('suite-loading-active');
        }
    }
}

suiteLoading.while = async function(promise) {
    this('show');
    try {
        const result = await promise;
        return result;
    } finally {
        this('hide');
    }
};