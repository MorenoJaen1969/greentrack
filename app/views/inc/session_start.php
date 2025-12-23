<?php
/**
 * Verifica si la sesión ya está iniciada
 * @return bool
 */
function is_session_started() {
    if (php_sapi_name() === 'cli') {
        return false;
    }
    if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
        return session_status() === PHP_SESSION_ACTIVE;
    }
    return session_id() !== '';
}

/**
 * Determina si la ejecución es local (IP de confianza)
 * @return bool
 */
function esEjecucionLocal() {
    $ipCliente = $_SERVER['REMOTE_ADDR'] ?? '';

    // Normalizar IPv6 mapeado a IPv4 (ej: ::ffff:192.168.86.32)
    if (strpos($ipCliente, '::ffff:') === 0) {
        $ipCliente = substr($ipCliente, 7); // Extrae la parte IPv4
    }

    $ipLocales = [
        '127.0.0.1',
        '::1',
        '192.168.86.1',
        '192.168.86.32',
        '192.168.86.47',
        '192.168.86.52'
    ];

    return in_array($ipCliente, $ipLocales, true);
}

/**
 * Obtiene la MAC address de la interfaz por defecto
 * @return string|false
 */
function getMacAddress() {
    $interface = trim(shell_exec("ip route show default 2>/dev/null | awk '/default/ {print $5}'"));
    if (!$interface) return false;

    $macFile = "/sys/class/net/{$interface}/address";
    if (file_exists($macFile)) {
        $mac = trim(file_get_contents($macFile));
        return strtolower($mac);
    }
    return false;
}

/**
 * Obtiene un identificador único del hardware sin usar sudo
 * Usa UUID, serial de placa, CPU, o un hash combinado
 * @return string
 */
function getMotherboardId() {
    $identifiers = [];

    // 1. Product UUID (accesible sin sudo en muchos sistemas)
    $uuidFile = '/sys/devices/virtual/dmi/id/product_uuid';
    if (file_exists($uuidFile)) {
        $uuid = trim(@file_get_contents($uuidFile));
        if ($uuid && !preg_match('/not|unknown|none|default/i', $uuid)) {
            $identifiers[] = $uuid;
        }
    }

    // 2. Serial de la placa base (a veces accesible sin sudo)
    $boardSerialFile = '/sys/class/dmi/id/board_serial';
    if (file_exists($boardSerialFile)) {
        $serial = trim(@file_get_contents($boardSerialFile));
        if ($serial && !preg_match('/not|unknown|none|default/i', $serial)) {
            $identifiers[] = $serial;
        }
    }

    // 3. Nombre del modelo de placa
    $boardNameFile = '/sys/class/dmi/id/board_name';
    if (file_exists($boardNameFile)) {
        $name = trim(@file_get_contents($boardNameFile));
        if ($name) {
            $identifiers[] = $name;
        }
    }

    // 4. Serial de CPU (si está disponible)
    $cpuInfo = @file_get_contents('/proc/cpuinfo');
    if ($cpuInfo && preg_match('/^Serial\s*:\s*(\w+)/mi', $cpuInfo, $matches)) {
        $identifiers[] = $matches[1];
    }

    // 5. Fallback: hash del hostname + release
    if (empty($identifiers)) {
        $identifiers[] = gethostname();
        $identifiers[] = php_uname('r'); // Kernel
        $identifiers[] = php_uname('n'); // Nodo
    }

    // Devuelve un hash único (SHA256) del conjunto
    return hash('sha256', implode('|', $identifiers));
}

// === INICIO DEL FLUJO ===

// Limpiar buffer previo si no hay ninguno activo
if (ob_get_level() === 0) {
    ob_start();
}

// Iniciar sesión si no está iniciada
if (!is_session_started()) {
    //  -------------------    session_name(APP_SESSION_NAME ?? 'mi_app');
    session_start();
}

// === Validar hardware ===
//    ------------------     validarHardware();

// No cerramos el buffer: lo deja abierto para el resto del script
// El script principal decidirá cuándo hacer ob_end_flush() o similar


