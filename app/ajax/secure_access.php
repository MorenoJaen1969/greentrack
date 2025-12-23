<?php
// /app/ajax/secure_access.php

// 1. Iniciar o reanudar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Verificar que el usuario tiene acceso
if (!isset($_SESSION['user_valid']) || !$_SESSION['user_valid']) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Acceso denegado: no autenticado'
    ]);
    exit;
}

// 3. Verificar que el access_key existe en sesión
$access_key = $_SESSION['token'] ?? null;
if (!$access_key) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Acceso denegado: falta token de sesión'
    ]);
    exit;
}

// 4. Opcional: validar que el access_key sigue siendo válido en BD
// Solo si quieres máxima seguridad (recomendado para ambientes sensibles)
require_once '../controllers/usuariosController.php';

use app\controllers\usuariosController;

$usuarioController = new usuariosController();
$userData = $usuarioController->getUserByToken($access_key);

if (!$userData) {
    session_destroy();
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Token inválido o expirado'
    ]);
    exit;
}

// 5. Si llega aquí, el acceso es válido
define('AUTHORIZED_ACCESS', true);