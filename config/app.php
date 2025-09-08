<?php
// config/app.php

// URL base del proyecto
define('APP_URL', 'https://positron4tx.ddns.net');

// Ruta real desde la raíz
define('RUTA_REAL', '');

// Modo desarrollo
define('DEBUG', true);

define('APP_R_PROY', realpath(__DIR__ . '/../') . '/');

define('APP_SESSION_NAME', "DIRECTORY");

const RUTA_FONTAWESOME=APP_URL."node_modules/@fortawesome/fontawesome-free/";
const RUTA_SWEETALERT=APP_URL."node_modules/sweetalert2/dist/";

