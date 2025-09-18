<?php
define('APP_URL', 'https://positron4tx.ddns.net');

// Ruta real desde la raíz
define('RUTA_REAL', '');

// Modo desarrollo
define('DEBUG', true);

if (!defined('APP_R_PROY')) {
    define('APP_R_PROY', '/var/www/greentrack/');
}

define('APP_SESSION_NAME', "DIRECTORY");

const RUTA_FONTAWESOME=APP_URL."node_modules/@fortawesome/fontawesome-free/";
const RUTA_SWEETALERT=APP_URL."node_modules/sweetalert2/dist/";

// === CREDENCIALES VERIZON FLEETMANAGEMENT (FIM API) ===
define('VERIZON_USERNAME', 'REST_IntengracionconGreenTrack_9217@1233691.com');
define('VERIZON_PASSWORD', 'X1wmy87J');
define('VERIZON_APP_ID', 'fleetmatics-p-us-7rnUGbM64kQdj72Q79DRor6hkF04E5gyRqOZ3fSo');

define('VERIZON_TOKEN_URL', 'https://fim.api.us.fleetmatics.com/token');

// === TIEMPOS DE CACHEO DE TOKEN ===
define('VERIZON_TOKEN_TTL_MINUTES', 19); // Minutos antes de renovar

define('VERIZON_PLOTS_URL', 'https://fim.api.us.fleetmatics.com/fim/v1/plots/search');

// === TIEMPO ENTRE LECTURAS DE GPS (en segundos) ===
define('GPS_POLLING_INTERVAL', 5); // Cada 5 segundos