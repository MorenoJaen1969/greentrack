<!-- /app/views/motor4/supervisor-mobile.php -->
<div id="supervisor-app" class="mobile-only">
    <header class="mobile-header">
        <h2>🟢 GreenTrack Supervisor</h2>
        <p>Bienvenido, <?= $_SESSION['user_name'] ?? 'Supervisor' ?></p>
    </header>
    <main id="rutas-container">
        <!-- Aquí se cargarán las rutas del día -->
        <p>Cargando servicios...</p>
    </main>
</div>

<script>
    // Inicialización del Motor 4
    document.addEventListener('DOMContentLoaded', () => {
        console.log('🟢 Motor 4 iniciado');
        // Cargar rutas del día (Proceso 2)
    });
</script>