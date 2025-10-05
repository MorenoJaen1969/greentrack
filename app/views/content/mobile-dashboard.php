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
        <button id="btn-messages" aria-label="Internal messages" class="msg-btn default">
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
        <div id="camionetas-list"></div>
    </div>


    <!-- COLUMNA DERECHA: Estado detallado -->
    <div id="estado-detallado">
        <p style="color:#888; text-align:center;">Select a truck</p>
    </div>
</div>

<script>
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
    document.addEventListener('DOMContentLoaded', cargarConfigYIniciar);

</script>
