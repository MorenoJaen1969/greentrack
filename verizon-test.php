<?php
// verizon-test.php
// Prueba técnica única: obtener token y datos de vehículos

echo "<h2>📡 Prueba de Conexión a Verizon Connect</h2>";
echo "<p><strong>Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// === CREDENCIALES (las que te envió Verizon) ===
$usuario = 'REST_PositronTX_6200@1233691.com';
$contrasena = 'pICBrcJh';

// === PASO 1: Obtener TOKEN ===
echo "<h3>1. Obteniendo token...</h3>";
$base64 = base64_encode("$usuario:$contrasena");
$url_token = 'https://fim.api.us.fleetmatics.com/token';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_token);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . $base64,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$respuesta_token = curl_exec($ch);
$http_code_token = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code_token === 200) {
    $datos_token = json_decode($respuesta_token, true);
    $token = $datos_token['token'];
    echo "<p>✅ Token obtenido con éxito.</p>";
    echo "<p><strong>Token:</strong> <code style='font-size:12px; background:#eee; padding:5px; word-break:break-all; display:block; margin:10px 0;'>$token</code></p>";
} else {
    echo "<p>❌ No se pudo obtener el token. HTTP $http_code_token</p>";
    echo "<pre>" . htmlspecialchars($respuesta_token) . "</pre>";
    exit;
}

// === PASO 2: Obtener DATOS DE VEHÍCULOS ===
echo "<h3>2. Obteniendo datos de vehículos...</h3>";
$app_id = 'fleetmatics-p-us-h2Nmd50ScVMOqkGDeEQ7ipHnGoiaRz6wfGttfjWQ'; // ← Esto lo necesitas del Developer Portal
$url_api = 'https://fim.api.us.fleetmatics.com/assets'; // Endpoint común (puede variar)

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $url_api);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
    'Authorization: Atmosphere atmosphere_app_id=' . $app_id . ', Bearer ' . $token
]);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, true);

$respuesta_api = curl_exec($ch2);
$http_code_api = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

if ($http_code_api === 200) {
    $datos = json_decode($respuesta_api, true);
    echo "<p>✅ Datos de vehículos obtenidos.</p>";
    echo "<pre>" . print_r($datos, true) . "</pre>";
} else {
    echo "<p>❌ Error al obtener datos de vehículos. HTTP $http_code_api</p>";
    echo "<pre>" . htmlspecialchars($respuesta_api) . "</pre>";
}


