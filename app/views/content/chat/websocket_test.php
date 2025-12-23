<?php
// websocket_test.php
// Ejecutar desde consola: php websocket_test.php

date_default_timezone_set('America/Bogota');

$logfile = __DIR__ . '/websocket_test.log';
$token = 'cfadd3fa951916768e9524d9e0091756f9aae75d35429c3bd4d14a06593a2d16'; // <- Usa un token válido
$host = 'positron4tx.ddns.net';
$port = 7070;

$log = function($msg) use ($logfile) {
    $line = "[" . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL;
    echo $line;
    file_put_contents($logfile, $line, FILE_APPEND);
};

$log("🧪 Iniciando prueba de WebSocket en ws://{$host}:{$port}");
$log("🔑 Token usado: " . substr($token, 0, 20) . "...");

// 1. Intentar conectar al socket
$socket = @fsockopen($host, $port, $errno, $errstr, 10);

if (!$socket) {
    $log("❌ ERROR: No se pudo conectar al WebSocket en {$host}:{$port}");
    $log("   Código: {$errno}, Mensaje: {$errstr}");
    exit(1);
}

$log("✅ Conexión TCP establecida al puerto {$port}");

// 2. Enviar el handshake HTTP/1.1 para WebSocket
$wsKey = base64_encode(random_bytes(16));
$handshake = "GET /?token=" . urlencode($token) . " HTTP/1.1\r\n";
$handshake .= "Host: {$host}:{$port}\r\n";
$handshake .= "Upgrade: websocket\r\n";
$handshake .= "Connection: Upgrade\r\n";
$handshake .= "Sec-WebSocket-Key: {$wsKey}\r\n";
$handshake .= "Sec-WebSocket-Version: 13\r\n";
$handshake .= "\r\n";

fwrite($socket, $handshake);
$log("📤 Handshake WebSocket enviado");

// 3. Leer la respuesta del servidor
$response = fread($socket, 2048);
$log("📥 Respuesta del servidor:");
$log("--------------------------------------------------");
foreach (explode("\n", $response) as $line) {
    $log(trim($line));
}
$log("--------------------------------------------------");

// 4. Verificar si el handshake fue exitoso
if (strpos($response, '101 Switching Protocols') !== false) {
    $log("✅ Handshake exitoso: conexión WebSocket establecida");
    $log("🟢 El servidor WebSocket está operativo y acepta conexiones.");
} else {
    $log("❌ Handshake fallido: el servidor no respondió como WebSocket");
    $log("⚠️ Posible causa: el servidor no está escuchando en el puerto, o hay un firewall interno.");
}

// 5. Cerrar conexión
fclose($socket);
$log("🔌 Conexión cerrada");
$log("📝 Prueba finalizada. Revisa el archivo websocket_test.log para más detalles.");