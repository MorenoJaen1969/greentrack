<?php

// --- CONFIGURACIÓN (Reemplaza con tus datos) ---
$username = 'REST_IntengracionconGreenTrack_9217@1233691.com';
$password = 'X1wmy87J';
$app_id = 'fleetmatics-p-us-7rnUGbM64kQdj72Q79DRor6hkF04E5gyRqOZ3fSo';
$token_endpoint = 'https://fim.api.us.fleetmatics.com/token';
$base_geofences_endpoint = 'https://fim.api.us.fleetmatics.com/geo/v1/geofences';

// Lista de categorías obtenida de la interfaz web del cliente
$categorias = [
    'Barred location',
    'Client',
    'Company',
    'Depot',
    'Employee Home',
    'Fuel station',
    'Location',
    'Residential',
    'Restaurant',
    'UnCategorized'
];
// --- FIN CONFIGURACIÓN ---

/**
 * Función para obtener un nuevo token de autorización.
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
 * Función para obtener la lista de geocercas por categoría.
 */
function obtenerGeocercasPorCategoria($token, $app_id, $base_endpoint, $categoria) {
    // Codificar la categoría para URL por si tiene espacios
    $categoria_codificada = rawurlencode($categoria);
    $endpoint = $base_endpoint . '?categoryName=' . $categoria_codificada;
    
    echo "Obteniendo geocercas para la categoría: '{$categoria}'...\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
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
        echo "  Error cURL al obtener geocercas para '{$categoria}': " . $error . "\n";
        return false;
    }

    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "  Geocercas para '{$categoria}' obtenidas exitosamente.\n";
            return $data;
        } else {
            echo "  Error al decodificar la respuesta JSON para '{$categoria}': " . json_last_error_msg() . "\n";
            return false;
        }
    } else {
        echo "  Error HTTP al obtener geocercas para '{$categoria}'. Código: " . $http_code . ". Respuesta: " . $response . "\n";
        return false;
    }
}

/**
 * Función para extraer los places de la respuesta de la API.
 */
function extraerPlaces($respuesta_api) {
    $places = [];
    if (isset($respuesta_api['_embedded']['places']) && is_array($respuesta_api['_embedded']['places'])) {
        foreach ($respuesta_api['_embedded']['places'] as $item) {
            // Asegurarse de que obtenemos el objeto 'place' correctamente
            $place = $item['place'] ?? $item;
            if (is_array($place)) {
                $places[] = $place;
            }
        }
    }
    return $places;
}

/**
 * Función para guardar datos en un archivo JSON.
 */
function guardarEnJson($datos, $nombre_archivo) {
    $json_output = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($nombre_archivo, $json_output) !== false) {
        echo "Datos guardados en '{$nombre_archivo}'\n";
    } else {
        echo "Error al guardar datos en '{$nombre_archivo}'\n";
    }
}

/**
 * Función para guardar datos en un archivo CSV.
 */
function guardarEnCsv($places, $nombre_archivo) {
    $archivo = fopen($nombre_archivo, 'w');
    if ($archivo === false) {
        echo "Error al crear el archivo CSV '{$nombre_archivo}'\n";
        return;
    }

    // Escribir encabezados
    fputcsv($archivo, [
        'GeoFenceName', 'PlaceId', 'CategoryName', 'Latitude', 'Longitude',
        'AddressLine1', 'AddressLine2', 'Locality', 'AdministrativeArea', 'PostalCode', 'Country',
        'Note', 'PhoneNumber', 'IsShownOnMap', 'IsShownOnReport'
    ]);

    // Escribir datos
    foreach ($places as $place) {
        $address = $place['Address'] ?? [];
        fputcsv($archivo, [
            $place['GeoFenceName'] ?? '',
            $place['PlaceId'] ?? '',
            $place['CategoryName'] ?? '',
            $place['Latitude'] ?? '',
            $place['Longitude'] ?? '',
            $address['AddressLine1'] ?? '',
            $address['AddressLine2'] ?? '',
            $address['Locality'] ?? '',
            $address['AdministrativeArea'] ?? '',
            $address['PostalCode'] ?? '',
            $address['Country'] ?? '',
            $place['Note'] ?? '',
            $place['PhoneNumber'] ?? '',
            ($place['IsShownOnMap'] ?? false) ? 'true' : 'false',
            ($place['IsShownOnReport'] ?? false) ? 'true' : 'false'
        ]);
    }

    fclose($archivo);
    echo "Datos guardados en '{$nombre_archivo}'\n";
}


// --- EJECUCIÓN ---

echo "Iniciando proceso para obtener todas las geocercas de todas las categorías...\n";

// 1. Obtener un nuevo token
$token = obtenerToken($username, $password, $token_endpoint);

if ($token === false) {
    echo "No se pudo obtener el token. Abortando.\n";
    exit(1);
}

echo "Token actual: ...\n"; // No mostramos el token completo por seguridad

// 2. Iterar por cada categoría y obtener geocercas
$todas_las_geocercas = [];
$places_totales = [];

foreach ($categorias as $categoria) {
    $respuesta_categoria = obtenerGeocercasPorCategoria($token, $app_id, $base_geofences_endpoint, $categoria);
    
    if ($respuesta_categoria !== false) {
        $todas_las_geocercas[$categoria] = $respuesta_categoria;
        
        // Extraer places individuales
        $places_de_categoria = extraerPlaces($respuesta_categoria);
        $places_totales = array_merge($places_totales, $places_de_categoria);
        
        echo "  Cantidad de places encontrados en '{$categoria}': " . count($places_de_categoria) . "\n";
    } else {
        echo "  No se pudieron obtener geocercas para la categoría '{$categoria}'.\n";
    }
    echo "\n";
}

// 3. Mostrar resumen final
$total_places = count($places_totales);
echo "--- RESUMEN FINAL ---\n";
echo "Se procesaron " . count($categorias) . " categorías.\n";
echo "Se encontraron un total de {$total_places} geocercas/places.\n";

if ($total_places > 0) {
    // 4. Guardar todos los datos en archivos
    echo "\n--- GUARDANDO DATOS ---\n";
    
    // Guardar datos completos por categoría (JSON)
    guardarEnJson($todas_las_geocercas, 'geocercas_por_categoria.json');
    
    // Guardar lista plana de todos los places (JSON)
    guardarEnJson($places_totales, 'todos_los_places.json');
    
    // Guardar lista plana de todos los places (CSV)
    guardarEnCsv($places_totales, 'todos_los_places.csv');
    
    echo "\n--- MUESTRA DE LOS PRIMEROS 3 PLACES ---\n";
    $contador = 0;
    foreach ($places_totales as $place) {
        if ($contador >= 3) break;
        
        echo "Nombre: " . ($place['GeoFenceName'] ?? 'N/A') . "\n";
        echo "Categoría: " . ($place['CategoryName'] ?? 'N/A') . "\n";
        echo "ID: " . ($place['PlaceId'] ?? 'N/A') . "\n";
        echo "Latitud: " . ($place['Latitude'] ?? 'N/A') . "\n";
        echo "Longitud: " . ($place['Longitude'] ?? 'N/A') . "\n";
        
        $address = $place['Address'] ?? [];
        if (!empty($address)) {
            echo "Dirección: " . implode(', ', array_filter([
                $address['AddressLine1'] ?? '',
                $address['AddressLine2'] ?? '',
                $address['Locality'] ?? '',
                $address['AdministrativeArea'] ?? '',
                $address['PostalCode'] ?? '',
                $address['Country'] ?? ''
            ])) . "\n";
        } else {
            echo "Dirección: No disponible\n";
        }
        echo "------------------------\n";
        $contador++;
    }
} else {
    echo "No se encontraron geocercas en ninguna de las categorías.\n";
}

echo "\n--- Fin del proceso ---\n";
?>