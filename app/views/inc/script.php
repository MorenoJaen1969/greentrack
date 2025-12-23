<script>
    // Toggle del menú lateral
    document.getElementById('btn-menu-toggle').addEventListener('click', function () {
        document.getElementById('menu-lateral').style.left = '0';
        document.getElementById('menu-overlay').style.display = 'block';
    });

    // ✅ El botón de chat ahora se gestiona desde /app/views/inc/chat.php (modal)
    // Este bloque está deshabilitado para evitar conflictos
    /*
    const btnChatToggle = document.getElementById('btn-chat-toggle');
    if (btnChatToggle) {
        btnChatToggle.addEventListener('click', function () {
            window.location.href = "<?php echo RUTA_APP; ?>/chat/";
        });
    }
    */

    // Cerrar menú desde la X
    document.getElementById('btn-menu-close').addEventListener('click', function () {
        document.getElementById('menu-lateral').style.left = '-300px';
        document.getElementById('menu-overlay').style.display = 'none';
    });

    // Cerrar menú al hacer clic en overlay
    document.getElementById('menu-overlay').addEventListener('click', function () {
        document.getElementById('menu-lateral').style.left = '-300px';
        document.getElementById('menu-overlay').style.display = 'none';
    });

    // Opcional: cerrar con tecla ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.getElementById('menu-lateral').style.left = '-300px';
            document.getElementById('menu-overlay').style.display = 'none';
        }
    });
    
    document.addEventListener('DOMContentLoaded', () => {
        // 1. Activar loading al hacer clic en enlaces del menú
        document.querySelectorAll('.menu-item').forEach(link => {
            link.addEventListener('click', (e) => {
                // Solo si es navegación interna (no descarga, no ancla, no externo)
                if (
                    e.button === 0 && // clic izquierdo
                    !e.ctrlKey && !e.metaKey && // no abrir en nueva pestaña
                    !link.target && // no tiene target="_blank"
                    link.href.startsWith(window.location.origin) // es enlace interno
                ) {
                    suiteLoading('show');
                }
            });
        });

        // 2. Ocultar loading cuando la página esté lista
        // Usa 'load' para esperar imágenes, o 'DOMContentLoaded' si solo necesitas el HTML
        window.addEventListener('load', () => {
            suiteLoading('hide');
        });
    });
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js?v=<?= time() ?>"></script>
<!-- Carrusel: JS (solo animación) -->

<?php
if ($url[0] == "dashboard") {
    ?>
    <script src="<?= RUTA_REAL ?>/app/views/inc/js/script.js?v=<?= time() ?>"></script>
    <script src="<?= RUTA_REAL ?>/app/views/inc/js/motor2.js?v=<?= time() ?>" defer></script>
    <script src="<?= RUTA_REAL ?>/app/views/inc/js/suiteAlert.js?v=<?= time() ?>"></script>

    <?php
} elseif ($url[0] == "address_clasNew" || $url[0] == "address_typeNew") {
    ?>
    <script src="<?= RUTA_REAL ?>/app/views/inc/js/modalMess.js?v=<?= time() ?>"></script>
    <?php
}
?>