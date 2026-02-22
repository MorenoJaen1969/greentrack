<?php
/**
 * Wrapper para ejecutar scripts como www-data
 * Uso: php scripts/ejecutar_como_wwwdata.php crear_tablas_mensuales_historicas.php
 */

if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos\n");
}

if ($argc < 2) {
    die("Uso: php ejecutar_como_wwwdata.php <script_a_ejecutar.php>\n");
}

$script_a_ejecutar = $argv[1];
$script_path = __DIR__ . '/' . $script_a_ejecutar;

if (!file_exists($script_path)) {
    die("Error: El script '$script_a_ejecutar' no existe\n");
}

// Verificar si somos www-data
$current_user = posix_getpwuid(posix_geteuid())['name'];

if ($current_user !== 'www-data') {
    echo "⚠️  No eres www-data. Ejecutando con sudo...\n";
    $cmd = "sudo -u www-data php " . escapeshellarg($script_path);
    
    // Pasar argumentos adicionales si existen
    if ($argc > 2) {
        $args = array_slice($argv, 2);
        $cmd .= ' ' . implode(' ', array_map('escapeshellarg', $args));
    }
    
    passthru($cmd, $return_var);
    exit($return_var);
}

// Si ya somos www-data, ejecutar directamente
echo "✓ Ejecutando como www-data: $script_a_ejecutar\n";
echo "========================================\n\n";

require_once $script_path;
?>