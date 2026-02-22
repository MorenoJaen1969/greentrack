// /app/views/js/motor4.js
(function() {
    'use strict';

    function isSmartphoneOnly() {
        const ua = navigator.userAgent || navigator.vendor || window.opera;
        
        // Excluir tablets explícitamente
        if (/ipad|tablet|playbook|silk|android.*mobile.*tablet/i.test(ua)) {
            return false;
        }
        
        // Permitir smartphones
        if (/iphone|ipod|android.*mobile|windows phone/i.test(ua)) {
            return true;
        }
        
        // Fallback seguro
        const hasTouch = 'ontouchstart' in window || (navigator.maxTouchPoints > 1 && navigator.maxTouchPoints < 5);
        const smallScreen = screen.width <= 768;
        return hasTouch && smallScreen;
    }

    if (!isSmartphoneOnly()) {
        // Mostrar mensaje amable y detener interacción
        document.body.innerHTML = `
            <div style="padding:30px; text-align:center; font-family:sans-serif;">
                <h2 style="color:#d32f2f;">⚠️ Acceso Restringido</h2>
                <p>Esta aplicación solo está disponible en smartphones.<br>No es compatible con tablets ni computadoras.</p>
            </div>
        `;
        // Deshabilitar cualquier interacción futura
        document.body.style.pointerEvents = 'none';
        throw new Error('Dispositivo no autorizado');
    }
})();