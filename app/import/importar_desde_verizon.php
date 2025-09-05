<?php
// Activar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir configuración
require_once '../../config/app.php';
require_once '../../config/server.php';
require_once '../models/mainModel.php';
require_once '../controllers/VerizonImportController.php';

// Ejecutar importación
$importador = new app\controllers\VerizonImportController();
$resultado = $importador->importarGeocercas('mmoreno', 'alele4');

echo "<h2>Importación desde Verizon Connect</h2>";
echo "<p>✅ Insertados: {$resultado['insertados']}</p>";
if (!empty($resultado['errores'])) {
    echo "<h3>❌ Errores:</h3><ul>";
    foreach ($resultado['errores'] as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}
?>
