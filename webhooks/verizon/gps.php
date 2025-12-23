<?php
// webhooks/verizon/gps.php

ob_start();

// === 1. Cargar configuración y modelo ===
require_once '../../config/app.php';

// Verificar que APP_R_PROY esté definido
if (!defined('APP_R_PROY')) {
    die('ERROR: APP_R_PROY no está definido');
}

// Incluir el modelo principal
require_once APP_R_PROY . 'app/models/mainModel.php';

use app\models\mainModel;
$modelo = new mainModel();

// === 2. Detectar mensaje de Amazon SNS (Confirmación) ===
if (isset($_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE'])) {
    $messageType = $_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE'];
    $input = file_get_contents('php://input');

    if ($messageType === 'SubscriptionConfirmation') {
        // Log para depuración
        error_log("SNS: SubscriptionConfirmation recibido");
        error_log("SNS: Contenido -> " . $input);

        // Responder 200 OK para confirmar
        http_response_code(200);
        echo 'Confirmado';
        exit();
    }

    if ($messageType === 'Notification') {
        // Continuar con el procesamiento del GPS
        // El mensaje viene en $input como JSON dentro de texto plano
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("SNS: JSON malformado en Notification -> " . $input);
            http_response_code(400);
            exit();
        }

        // El GPS real está en $data['Message'] (cadena JSON)
        $gpsJson = json_decode($data['Message'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("SNS: JSON interno malformado -> " . $data['Message']);
            http_response_code(400);
            exit();
        }

        // Procesar el GPS
        procesarGPS($modelo, $gpsJson);
        exit();
    }
}

// === 3. Si no es SNS, verificar método POST (para pruebas) ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Leer entrada
$input = file_get_contents('php://input');
$gpsData = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON malformado en gps.php: " . $input);
    http_response_code(400);
    echo json_encode(['error' => 'JSON malformado']);
    exit();
}

// Procesar el GPS
procesarGPS($modelo, $gpsData);
exit();

// === 4. Función para procesar los datos GPS ===
function procesarGPS($modelo, $data) {
    // Validar estructura mínima
    if (!isset($data['data']['latitude']) || !isset($data['data']['longitude'])) {
        error_log("GPS: Datos incompletos -> " . json_encode($data));
        http_response_code(400);
        echo json_encode(['error' => 'Faltan coordenadas']);
        return;
    }

    // Extraer datos
    $vehicleData = $data['data']['vehicle'] ?? [];
    $driverData = $data['data']['driver'] ?? [];

    $datos = [
        ['campo_nombre' => 'vehicle_id',    'campo_marcador' => ':vehicle_id',    'campo_valor' => $vehicleData['vehicleNumber'] ?? $vehicleData['id'] ?? 'Unknown'],
        ['campo_nombre' => 'lat',           'campo_marcador' => ':lat',           'campo_valor' => $data['data']['latitude']],
        ['campo_nombre' => 'lng',           'campo_marcador' => ':lng',           'campo_valor' => $data['data']['longitude']],
        ['campo_nombre' => 'timestamp',     'campo_marcador' => ':timestamp',     'campo_valor' => date('Y-m-d H:i:s')],
        ['campo_nombre' => 'geofence_id',   'campo_marcador' => ':geofence_id',   'campo_valor' => $data['data']['geofenceId'] ?? ''],
        ['campo_nombre' => 'speed',         'campo_marcador' => ':speed',         'campo_valor' => $data['data']['speedKmph'] ?? null],
        ['campo_nombre' => 'heading',       'campo_marcador' => ':heading',       'campo_valor' => $data['data']['heading'] ?? ''],
        ['campo_nombre' => 'driver_id',     'campo_marcador' => ':driver_id',     'campo_valor' => $driverData['driverNumber'] ?? $driverData['id'] ?? '']
    ];

    try {
        $modelo->guardarDatos('gps_realtime', $datos);

        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Coordenada guardada']);
    } catch (Exception $e) {
        error_log("Error al guardar GPS: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Error interno']);
    }
}

?>