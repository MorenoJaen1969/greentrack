<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);

$raw = file_get_contents('php://input');
$inputData = json_decode($raw, true);

if (!isset($inputData['vehicle_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta vehicle_id']);
    exit;
}

// Reenvía al motor2Ajax.php real
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost/app/ajax/motor2Ajax.php',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $raw,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($http_code);
echo $response;