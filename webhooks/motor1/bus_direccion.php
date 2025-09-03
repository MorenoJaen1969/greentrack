<?php
// servicios_dia.php - Motor 1 (versión completa)
ob_start();

require_once '../../config/app.php';
if (!defined('APP_R_PROY')) {
    die('ERROR: APP_R_PROY no está definido');
}

require_once APP_R_PROY . 'app/models/mainModel.php';
require_once APP_R_PROY . 'app/controllers/serviciosController.php';

use app\models\mainModel;
use app\controllers\serviciosController;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}


// Habilitar errores para depuración (solo en terminal)
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0); // Sin límite de tiempo


// ================================
// 3. CONSULTAR SERVICIOS CON COORDENADAS INVÁLIDAS
// ================================

$controller = new serviciosController();
$resultado = $controller->obtener_direcciones(1);

$total = count($resultado);
echo "🔍 Total de servicios por corregir: $total\n\n";

if ($total === 0) {
    echo "✅ No hay servicios con coordenadas inválidas.\n";
    exit;
}

// ================================
// 4. FUNCIÓN: OBTENER COORDENADAS DE GOOGLE MAPS
// ================================
function direccionUrl($direccion) {
    // 1. Insertar '+'' después de '.' si sigue una letra mayúscula
    $direccion = preg_replace('/\.([A-Z])/', '.+$1', $direccion);

    // 2. Convertir espacios en '+'
    $direccion = str_replace(' ', '+', $direccion);

    // 3. Reducir múltiples '+' consecutivos a uno solo
    return preg_replace('/\++/', '+', $direccion);
}

function obtenerCoordenadasGoogle($direccion) {
    $url = 'https://www.google.com/maps/place/' . direccionUrl($direccion);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    // Verificar respuesta
    if ($httpCode !== 200 || !$html || strpos($contentType, 'html') === false) {
        return false;
    }

    // Patrones comunes donde aparecen las coordenadas
    // Patrón: cualquier par de números decimales separados por coma
    $patron = '/(-?\d+\.\d+),\s*(-?\d+\.\d+)/';

    preg_match_all($patron, $html, $matches, PREG_SET_ORDER);

    foreach ($matches as $m) {
        $num1 = (float)$m[1];
        $num2 = (float)$m[2];

        // Validar rango EE.UU. (Texas)
        $es_lat_posible = ($num1 >= 24.0 && $num1 <= 50.0);
        $es_lng_posible = ($num2 >= -125.0 && $num2 <= -65.0);

        $es_lat_posible_inv = ($num2 >= 24.0 && $num2 <= 50.0);
        $es_lng_posible_inv = ($num1 >= -125.0 && $num1 <= -65.0);

        if ($es_lat_posible && $es_lng_posible) {
            // num1 = lat, num2 = lng
            return ['lat' => $num1, 'lng' => $num2];
        } elseif ($es_lat_posible_inv && $es_lng_posible_inv) {
            // num2 = lat, num1 = lng
            return ['lat' => $num2, 'lng' => $num1];
        }
    }

    return false;
}

// ================================
// 5. PROCESAR CADA SERVICIO
// ================================

$procesados = 0;
$actualizados = 0;
$fallidos = [];

echo "🚀 Iniciando geocodificación...\n\n";

foreach ($resultado as $servicio) {
    $procesados++;
    $direccion = $servicio['direccion'];

    echo "📍 [$procesados/$total] Procesando: {$servicio['id_servicio']}\n";
    echo "   → $direccion\n";

    $coordenadas = obtenerCoordenadasGoogle($direccion);

    if ($coordenadas) {
        // Actualizar en la base de datos
        $lat = $coordenadas['lat'];
        $lon = $coordenadas['lng'];
        $id_servicio = $servicio['id_servicio'];
                
        $actualizado = $controller->act_lat_long_direcciones($id_servicio, $lat, $lon);
        
        $total = count($actualizado);

        if ($total === 0) {
            echo "✅ No se actualizo la direccion.\n";
            exit;
        } else {
            echo "   ✅ OK: {$coordenadas['lat']}, {$coordenadas['lng']}\n\n";
            $actualizados++;
        }
    } else {
        echo "   ❌ NO ENCONTRADAS\n\n";
        $fallidos[] = $servicio['id_servicio'];
    }

    // Esperar entre 5 y 8 segundos (muy lento, seguro)
    echo "   ⏳ Esperando 7 segundos...\n\n";
    sleep(7);
}

// ================================
// 6. RESUMEN FINAL
// ================================

echo "========================================\n";
echo "✅ RESUMEN DE EJECUCIÓN\n";
echo "========================================\n";
echo "Total procesados: $procesados\n";
echo "Actualizados:     $actualizados\n";
echo "Fallidos:         " . count($fallidos) . "\n";

if (count($fallidos) > 0) {
    echo "\n📋 ID de servicios fallidos:\n";
    print_r($fallidos);
}

echo "\n✅ Script finalizado. Puedes eliminar bus_direccion.php.\n";