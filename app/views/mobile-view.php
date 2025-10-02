<?php
// app/views/mobile-view.php
require_once "../../config/app.php"; // Constantes

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
</body>
</html>