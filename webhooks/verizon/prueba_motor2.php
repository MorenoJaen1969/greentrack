<?php
/**
 * prueba_motor2.php - Prueba CLI segura y simple
 * No modifica mainModel ni motor2Controller
 * No depende de php://input
 * Funciona en PHP 8.4 + VSCode
 */

if ($argc < 2) {
    echo json_encode([
        'error' => 'Uso: php prueba_motor2.php <TruckID>',
        'ejemplo' => 'php prueba_motor2.php Truck10'
    ]) . "\n";
    exit(1);
}

$vehicle_id = $argv[1];

// === Cargar configuración y controlador ===
define('APP_R_PROY', '/var/www/greentrack/');
require_once APP_R_PROY . 'config/app.php';
require_once APP_R_PROY . 'app/controllers/motor2Controller.php';

use app\controllers\motor2Controller;

// === Simular datos de entrada directamente en el controlador ===
$inputDataMock = [
    'modulo_motor2' => 'obtener_gps_verizon',
    'vehicle_id' => $vehicle_id
];

// === Capturar salida del controlador ===
ob_start();

try {
    $controller = new motor2Controller();

    // === Inyección temporal de datos (sin tocar archivos de producción) ===
    // Este bloque simula lo que haría motor2Ajax.php
    $rawInputMock = json_encode($inputDataMock);

    // Guardamos original para restaurar
    $original_func = null;
    if (function_exists('file_get_contents_override')) {
        $original_func = 'file_get_contents_override';
    }

    // Redefinimos file_get_contents solo para php://input
    function file_get_contents($filename) {
        static $mocked_input = null;
        if ($filename === 'php://input') {
            return $mocked_input ?? '';
        }
        return file_get_contents_original($filename);
    }

    // Renombramos la función original si es posible
    if (!function_exists('file_get_contents_original')) {
        rename_function('file_get_contents', 'file_get_contents_original');
    }

    // Inyectamos el valor mockeado
    $GLOBALS['PHP_INPUT_MOCK'] = $rawInputMock;

    // Llamamos al método
    $controller->obtenerGpsVerizon();

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'status' => 'exception',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]) . "\n";
    exit(1);
}

// === Capturar y mostrar respuesta ===
$response = ob_get_clean();
echo $response . "\n";

// === Evaluar resultado ===
$data = @json_decode($response, true);
if (isset($data['lat'], $data['lng'])) {
    echo "[✅ ÉXITO] Coordenadas obtenidas: {$data['lat']}, {$data['lng']}\n";
    if (isset($data['speed']))     echo "[⚡ Velocidad:] {$data['speed']} km/h\n";
    if (isset($data['course']))    echo "[🧭 Dirección:] {$data['course']}°\n";
    if (isset($data['timestamp'])) echo "[🕒 Timestamp:] {$data['timestamp']}\n";
} else {
    echo "[❌ ERROR] No se obtuvieron coordenadas válidas\n";
    if (isset($data['error'])) {
        echo "[📝 Mensaje:] {$data['error']}\n";
    }
}