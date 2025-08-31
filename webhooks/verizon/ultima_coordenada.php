<?php
ob_start();
require_once '../../config/app.php';
require_once APP_R_PROY . 'app/controllers/webhookController.php';

use app\controllers\webhookController;

// CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$vehicle_id = $_GET['vehicle_id'] ?? 'Crew 1';

try {
    $controller = new webhookController();
    $resultado = $controller->obtenerUltimaCoordenada($vehicle_id);

    if ($resultado) {
        $respuesta = [
            'vehicle_id' => $resultado['vehicle_id'],
            'lat' => (float)$resultado['lat'],
            'lng' => (float)$resultado['lng'],
            'timestamp' => $resultado['timestamp']
        ];
        http_response_code(200);
        echo json_encode($respuesta);
    } else {
        $respuesta = [
            'lat' => 30.3096,
            'lng' => -95.4750,
            'timestamp' => null,
            'vehicle_id' => $vehicle_id
        ];
        http_response_code(200);
        echo json_encode($respuesta);
    }
} catch (Exception $e) {
    error_log("Error en ultima_coordenada.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
}
?>