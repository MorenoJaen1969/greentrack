<?php
// app/views/mobile-view.php
require_once "../../config/app.php"; // Constantes
require_once APP_R_PROY . 'app/views/inc/session_start.php';

// No hay sesión ni validación por ahora
// En el futuro: session_start.php + validación de hardware
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Cargar head.php (contiene enlaces a CSS y JS) -->
    <?php require_once 'inc/mobile_head.php'; ?>
</head>

<body>
    <!-- El contenido principal lo define dashboard-view.php -->
    <?php
    require_once 'content/mobile-dashboard.php';
    require_once 'inc/mobile.php';
    ?>

    <!-- Modal del Mapa -->
    <div id="modal-mapa" class="modal-overlay" style="
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.9);
        display: none;
        z-index: 9998;
        justify-content: center;
        align-items: center;
    ">
        <div style="
            width: 100%;
            max-width: 100%;
            height: 100%;
            max-height: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        ">
            <!-- Encabezado -->
            <div style="
                background: #001F3F;
                color: white;
                padding: 12px 16px;
                font-size: 1.1em;
                font-weight: bold;
                display: flex;
                justify-content: space-between;
                align-items: center;
            ">
                <span id="titulo-mapa">📍 TRUCK 10 - Map</span>

                <!-- Dentro del encabezado del modal-mapa -->
                <div style="
                    display: flex;
                    gap: 8px;
                    font-size: 0.9em;
                ">
                    <button id="btn-play-pause" onclick="togglePlayPause()" style="
                        background: #4CAF50;
                        color: white;
                        border: none;
                        padding: 6px 12px;
                        border-radius: 4px;
                        font-size: 0.9em;
                        cursor: pointer;
                    ">▶️ Play</button>
                    <button onclick="resetRuta()" style="
                        background: #FF9800;
                        color: white;
                        border: none;
                        padding: 6px 12px;
                        border-radius: 4px;
                        font-size: 0.9em;
                        cursor: pointer;
                    ">⏮️ Reset</button>
                    <span id="tiempo-actual" style="line-height: 2; color: white;">--:--</span>
                </div>

                <button onclick="cerrarMapa()" style="
                    background: #FF4136;
                    color: white;
                    border: none;
                    width: 30px;
                    height: 30px;
                    border-radius: 50%;
                    font-size: 1.2em;
                    cursor: pointer;
                ">&times;</button>
            </div>

            <!-- Contenedor del mapa -->
            <div id="mapa-container" style="
                flex: 1;
                width: 100%;
                min-height: 0;
                background: #f0f0f0;
            ">
            </div>
        </div>
    </div>
</body>

</html>