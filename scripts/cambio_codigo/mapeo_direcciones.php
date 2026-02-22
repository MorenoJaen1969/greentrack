<?php

/**
 * MAPEO DE DIRECCIONES - CONSOLIDACIÓN SEGÚN direcciones.csv
 * 
 * Lógica:
 * 1. Lee direcciones.csv (ID_DIRECCION, Cod_DIRECCION_Anterior, DESCRIPCION)
 * 2. Asigna id_direccion_nuevo = 5000 + posición_en_csv para cada línea
 * 3. Para cada id_anterior, actualiza:
 *    - direcciones (WHERE id_dir_anterior = id_anterior)
 *    - servicios (WHERE id_dir_anterior = id_anterior)
 *    - contratos (WHERE id_dir_anterior = id_anterior)
 */

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
$m->set_charset('utf8mb4');

echo "[INFO] Leyendo direcciones.csv\n";

$csv_file = '/var/www/greentrack/scripts/direcciones.csv';
if (!file_exists($csv_file)) {
    die("[ERROR] Archivo no encontrado: $csv_file\n");
}

$lines = file($csv_file);
$mapeo = [];
$contador = 5000;

foreach ($lines as $i => $line) {
    if ($i === 0) continue; // Header

    $parts = explode(';', trim($line));
    if (count($parts) < 2) continue;

    $id_nuevo = $contador;
    $ids_anteriores_str = trim($parts[1]);

    // Procesar ids anteriores
    $ids_anteriores = [];
    if (!empty($ids_anteriores_str)) {
        $ids_anteriores = array_map('trim', explode(',', $ids_anteriores_str));
        $ids_anteriores = array_filter($ids_anteriores, fn($x) => !empty($x) && is_numeric($x));
    }

    if (!empty($ids_anteriores)) {
        $mapeo[] = [
            'id_nuevo' => $id_nuevo,
            'ids_anteriores' => $ids_anteriores
        ];
    }

    $contador++;
}

echo "[OK] " . count($mapeo) . " registros en direcciones.csv\n";
echo "\n[PROCESANDO] Actualizando BD...\n";

$actualizaciones = 0;

foreach ($mapeo as $item) {
    $id_nuevo = $item['id_nuevo'];
    $ids_ant = $item['ids_anteriores'];

    // ÚNICA lógica: Procesar IDs anteriores
    foreach ($ids_ant as $id_ant) {
        $id_ant = (int)$id_ant;

        // DIRECCIONES
        $m->query("UPDATE direcciones SET id_direccion_nuevo = $id_nuevo WHERE id_dir_anterior = $id_ant");
        $actualizaciones += $m->affected_rows;

        // SERVICIOS
        $m->query("UPDATE servicios SET id_direccion_nuevo = $id_nuevo WHERE id_dir_anterior = $id_ant");
        $actualizaciones += $m->affected_rows;

        // CONTRATOS
        $m->query("UPDATE contratos SET id_direccion_nuevo = $id_nuevo WHERE id_dir_anterior = $id_ant");
        $actualizaciones += $m->affected_rows;
    }
}

// Verificación final
$r = $m->query("SELECT COUNT(*) as total, SUM(CASE WHEN id_direccion_nuevo IS NOT NULL THEN 1 ELSE 0 END) as con_nuevo FROM direcciones");
$row = $r->fetch_assoc();

echo "\n[OK] Mapeo de direcciones completado\n";
echo "    Total updates: $actualizaciones\n";
echo "    Direcciones con id_direccion_nuevo: {$row['con_nuevo']}/{$row['total']}\n";

$r = $m->query("SELECT COUNT(*) as total, SUM(CASE WHEN id_direccion_nuevo IS NOT NULL THEN 1 ELSE 0 END) as con_nuevo FROM servicios");
$row = $r->fetch_assoc();
echo "    Servicios con id_direccion_nuevo: {$row['con_nuevo']}/{$row['total']}\n";

echo "\n[FIN] Mapeo de direcciones completado\n";

$m->close();
