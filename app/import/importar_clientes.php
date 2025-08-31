// /app/import/importar_clientes.php
<?php

// Activar errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir configuración

require_once '../../config/app.php';       // Constantes

$ruta_config = __DIR__ . '/../../config/server.php';
if (file_exists($ruta_config)) {
    require_once $ruta_config;
} else {
    die('No se encontró server.php');
}

// Incluir modelo
$ruta_main = '/var/www/greentrack/app/models/mainModel.php';

require_once $ruta_main;
use app\models\mainModel;

class Importador extends mainModel
{
    // Colores por Crew
    private $colores = [
        '1'  => '#4FC3F7',
        '2'  => '#8BC34A',
        '3'  => '#FF9800',
        '4'  => '#E91E63',
        '5'  => '#9C27B0',
        '6'  => '#FF5722',
        '7'  => '#795548',
        '8'  => '#3F51B5',
        '9'  => '#009688',
        '10' => '#CDDC39',
        '11' => '#607D8B',
        '12' => '#FFC107',
        ''   => '#9E9E9E'
    ];

    public function importar($datos)
    {
        $insertados = 0;
        $errores = [];

        foreach ($datos as $fila) {
            try {
                $cliente = $this->limpiarCadena($fila['cliente']);
                $direccion = $this->limpiarCadena($fila['direccion'] ?? '');
                $truck = $this->limpiarCadena($fila['truck']);
                $lat = (float)$fila['lat'];
                $lng = (float)$fila['lng'];
                $geofence_id = $this->limpiarCadena($fila['geofence_id'] ?? uniqid('gf_'));

                $datos_servicio = [
                    ['campo_nombre' => 'cliente', 'campo_marcador' => ':cliente', 'campo_valor' => $cliente],
                    ['campo_nombre' => 'direccion', 'campo_marcador' => ':direccion', 'campo_valor' => $direccion],
                    ['campo_nombre' => 'truck', 'campo_marcador' => ':truck', 'campo_valor' => $truck],
                    ['campo_nombre' => 'crew_color', 'campo_marcador' => ':crew_color', 'campo_valor' => $this->colores[$truck] ?? '#9e9e9e'],
                    ['campo_nombre' => 'lat', 'campo_marcador' => ':lat', 'campo_valor' => $lat],
                    ['campo_nombre' => 'lng', 'campo_marcador' => ':lng', 'campo_valor' => $lng],
                    ['campo_nombre' => 'geofence_id', 'campo_marcador' => ':geofence_id', 'campo_valor' => $geofence_id],
                    ['campo_nombre' => 'finalizado', 'campo_marcador' => ':finalizado', 'campo_valor' => 0]
                ];

                $id = $this->guardarDatos('servicios', $datos_servicio);
                if ($id) {
                    $insertados++;
                }
            } catch (Exception $e) {
                $errores[] = "Error con {$fila['cliente']}: " . $e->getMessage();
            }
        }

        return [
            'insertados' => $insertados,
            'errores' => $errores
        ];
    }
}

// === Datos de ejemplo (reemplaza con tu CSV o API) ===

// === Obtener datos desde API externa ===

$api_url = 'https://api.tuapi.com/clientes'; // Cambia por la URL real
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADER, true); // Obtener headers también
// Si necesitas headers, agrega aquí:
// curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Authorization: Bearer TU_TOKEN' ]);
$full_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($full_response, 0, $header_size);
$response = substr($full_response, $header_size);

if (curl_errno($ch)) {
    echo '<pre style="color:red;">Error cURL: ' . curl_error($ch) . '</pre>';
    die('Error al consultar la API: ' . curl_error($ch));
}
curl_close($ch);

// Depuración avanzada
echo '<pre style="background:#eee;padding:10px;">';
echo "Código HTTP: $http_code\n";
echo "Headers:\n" . htmlspecialchars($headers) . "\n";
echo "Respuesta cruda de la API:\n" . htmlspecialchars($response) . "\n";
echo '</pre>';

if (empty($response)) {
    echo '<pre style="color:red;">La respuesta de la API está vacía.</pre>';
    die('La respuesta de la API está vacía');
}

$datos_clientes = json_decode($response, true);
if (!is_array($datos_clientes)) {
    echo '<pre style="color:red;">Error: La respuesta de la API no es válida.<br>Contenido recibido:<br>';
    echo htmlspecialchars($response);
    echo '</pre>';
    die('La respuesta de la API no es válida');
}

// === Ejecutar importación ===
$importador = new Importador();
$resultado = $importador->importar($datos_clientes);

echo "<h2>Importación completada</h2>";
echo "<p>✅ Insertados: {$resultado['insertados']}</p>";
if (!empty($resultado['errores'])) {
    echo "<h3>❌ Errores:</h3><ul>";
    foreach ($resultado['errores'] as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}
?>