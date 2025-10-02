<div class="header-container">
    <div class="header-left">
        <img src="/app/views/img/logo.jpg" alt="Sergio's Landscape" class="logo">
    </div>
    <div class="header-center">
        <h2>🟢 GreenTrack Live</h2>
        <p>Welcome, <?= htmlspecialchars(explode('@', $user_email)[0]) ?></p>
    </div>
    <!-- No hay nada en la derecha -->
</div>

<div class="container">
    <!-- COLUMNA IZQUIERDA: Lista de camionetas -->
    <div id="lista-camionetas">
        <!-- Barra de estado debajo del header -->
        <div class="fleet-status-bar">
            <span id="fleet-date-label">Active Fleet Today</span>
            <button id="btn-calendar" onclick="abrirCalendario()">
                📅
            </button>
        </div>
        <div id="camionetas-list"></div>
    </div>


    <!-- COLUMNA DERECHA: Estado detallado -->
    <div id="estado-detallado">
        <p style="color:#888; text-align:center;">Select a truck</p>
    </div>
</div>