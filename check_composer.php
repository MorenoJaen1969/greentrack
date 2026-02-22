<?php
require_once 'vendor/autoload.php';

echo "=== Verificación de Dependencias ===\n";

$checks = [
    'Composer Autoload' => class_exists('Composer\\Autoload\\ClassLoader'),
    'Ratchet' => interface_exists('Ratchet\\MessageComponentInterface'),
    'GuzzleHttp' => class_exists('GuzzleHttp\\Client'),
    'Firebase JWT' => class_exists('Firebase\\JWT\\JWT'),
];

foreach ($checks as $name => $status) {
    echo $status ? "✅ $name\n" : "❌ $name\n";
}

if (array_filter($checks)) {
    echo "\n🎉 Todas las dependencias están listas!\n";
} else {
    echo "\n⚠️  Hay problemas con las dependencias\n";
}
?>
