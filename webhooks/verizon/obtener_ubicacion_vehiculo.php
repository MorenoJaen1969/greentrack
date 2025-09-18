<?php

// --- CONFIGURACIÓN (Reemplaza con tus datos) ---
$username = 'REST_IntengracionconGreenTrack_9217@1233691.com'; // Tu Reveal Integration User Username
$password = 'X1wmy87J'; // Tu Reveal Integration User Password
$app_id = 'fleetmatics-p-us-7rnUGbM64kQdj72Q79DRor6hkF04E5gyRqOZ3fSo'; // TU APP ID
$token_endpoint = 'https://fim.api.us.fleetmatics.com/token'; // Endpoint para obtener token

// --- CONFIGURACIÓN ESPECÍFICA PARA VEHICLE LOCATION ---
// *** IMPORTANTE: Reemplaza 'TU_NUMERO_DE_VEHICULO_AQUI' con un Vehicle Number real ***
// Por ejemplo: $vehicle_number = 'OSCAR'; o $vehicle_number = 'JAVIER';
$vehicle_number = rawurlencode('TRUCK 11'); // Se convertirá en 'CRUZE%208518'
$vehicle_location_endpoint = "https://fim.api.us.fleetmatics.com/rad/v1/vehicles/{$vehicle_number}/location";
// --- FIN CONFIGURACIÓN ---

/**
 * Función para obtener un nuevo token de autorización.
 * (Esta función es la misma que antes y ya sabemos que funciona)
 */
function obtenerToken($username, $password, $token_endpoint) {
    $credentials = $username . ':' . $password;
    $encoded_credentials = base64_encode($credentials);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $encoded_credentials,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "Error cURL al obtener el token: " . $error . "\n";
        return false;
    }

    if ($http_code === 200) {
        $token = trim($response);
        if (!empty($token)) {
             echo "Token obtenido exitosamente.\n";
             return $token;
        }

        $json_response = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($json_response['access_token'])) {
             echo "Token (formato JSON) obtenido exitosamente.\n";
             return $json_response['access_token'];
        }
        echo "Respuesta inesperada al obtener el token: " . $response . "\n";
        return false;

    } else {
        echo "Error HTTP al obtener el token. Código: " . $http_code . ". Respuesta: " . $response . "\n";
        return false;
    }
}

/**
 * Función para obtener la ubicación de un vehículo usando su número y un token.
 *
 * @param string $token El token de autorización.
 * @param string $app_id El ID de la aplicación.
 * @param string $vehicle_location_endpoint El endpoint de la API para obtener la ubicación del vehículo.
 * @return array|bool Los datos de ubicación si tiene éxito, false en caso de error.
 */
function obtenerUbicacionVehiculo($token, $app_id, $vehicle_location_endpoint) {
    echo "Intentando obtener ubicación desde: " . $vehicle_location_endpoint . "\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $vehicle_location_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Atmosphere atmosphere_app_id=' . $app_id . ', Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "Error cURL al obtener ubicación del vehículo: " . $error . "\n";
        return false;
    }

    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "Ubicación del vehículo obtenida exitosamente.\n";
            return $data;
        } else {
            echo "Error al decodificar la respuesta JSON de ubicación: " . json_last_error_msg() . "\n";
            echo "Respuesta recibida (raw): " . $response . "\n";
            return false;
        }
    } else {
        echo "Error HTTP al obtener ubicación del vehículo. Código: " . $http_code . ". Respuesta: " . $response . "\n";
        return false;
    }
}

// --- EJECUCIÓN ---

echo "Iniciando proceso para obtener la ubicación de un vehículo...\n";

// 1. Obtener un nuevo token
$token = obtenerToken($username, $password, $token_endpoint);

if ($token === false) {
    echo "No se pudo obtener el token. Abortando.\n";
    exit(1);
}

echo "Token actual: " . $token . "\n";

// 2. Verificar que se haya proporcionado un número de vehículo
if (empty($vehicle_number) || $vehicle_number === 'TU_NUMERO_DE_VEHICULO_AQUI') {
    echo "ERROR: Debes reemplazar 'TU_NUMERO_DE_VEHICULO_AQUI' en el código con un Vehicle Number real.\n";
    exit(1);
}

// 3. Usar el token para obtener la ubicación del vehículo
$vehicle_location_data = obtenerUbicacionVehiculo($token, $app_id, $vehicle_location_endpoint);

if ($vehicle_location_data === false) {
    echo "No se pudo obtener la ubicación del vehículo.\n";
    exit(1);
}

// 4. Procesar y mostrar los datos de ubicación
echo "\n--- Datos de Ubicación del Vehículo ---\n";
if (is_array($vehicle_location_data)) {
    // Mostrar la estructura completa de la respuesta para ver qué datos contiene
    echo "Respuesta JSON recibida:\n";
    print_r($vehicle_location_data);

    // Intentar extraer información básica si existe
    echo "\n--- Información Extraída ---\n";
    echo "Vehicle Number: " . ($vehicle_location_data['VehicleNumber'] ?? 'N/A') . "\n";
    echo "Timestamp (UTC): " . ($vehicle_location_data['TimestampUtc'] ?? 'N/A') . "\n";
    echo "Latitude: " . ($vehicle_location_data['Latitude'] ?? 'N/A') . "\n";
    echo "Longitude: " . ($vehicle_location_data['Longitude'] ?? 'N/A') . "\n";
    echo "Speed (Km/h): " . ($vehicle_location_data['SpeedKmH'] ?? 'N/A') . "\n";
    echo "Heading: " . ($vehicle_location_data['Heading'] ?? 'N/A') . "\n";
    echo "GPS Status: " . ($vehicle_location_data['GpsStatus'] ?? 'N/A') . "\n";
    // Puedes agregar más campos según la respuesta que recibas

} else {
    echo "Error: La variable \$vehicle_location_data no es un array. Valor recibido: ";
    var_dump($vehicle_location_data);
}

echo "\n--- Fin del proceso ---\n";
?>