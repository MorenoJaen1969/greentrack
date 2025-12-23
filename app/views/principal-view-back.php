<?php
// app/views/principal-view.php

// No hay sesión ni validación por ahora
// En el futuro: session_start.php + validación de hardware
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenTrack Live</title>

    <!-- Cargar head.php (contiene enlaces a CSS y JS) -->
    <?php require_once 'inc/head.php'; ?>
</head>
<body>
    <!-- El contenido principal lo define dashboard-view.php -->
    <?php 
        //require_once  APP_R_PROY . "config/controllers.php"; 
        require_once 'content/dashboard-view.php';
        require_once 'inc/script.php'; 
    ?>
</body>
</html>