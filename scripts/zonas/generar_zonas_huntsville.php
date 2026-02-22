<?php
/**
 * GENERADOR DE ZONAS PARA HUNTSVILLE
 * Extiende la cuadrícula hacia el norte desde la última fila existente
 * hasta cubrir el área de Huntsville (30.721)
 */

// Configuración de base de datos
$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "===========================================\n";
echo "GENERADOR DE ZONAS - GREENTRACK\n";
echo "===========================================\n\n";

// ============================================================
// PASO 1: DETECTAR PARÁMETROS DE LA CUADRÍCULA EXISTENTE
// ============================================================

echo "[1] Analizando cuadrícula existente...\n";

// Obtener la zona más al norte (mayor lat_ne)
$sql_norte = "SELECT MAX(lat_ne) as max_lat_ne FROM zonas_cuadricula";
$result = $m->query($sql_norte);
$row = $result->fetch_assoc();
$lat_norte_actual = (float)$row['max_lat_ne'];

// Obtener la zona más al sur (menor lat_sw) - para referencia
$sql_sur = "SELECT MIN(lat_sw) as min_lat_sw FROM zonas_cuadricula";
$result = $m->query($sql_sur);
$row = $result->fetch_assoc();
$lat_sur_actual = (float)$row['min_lat_sw'];

// Obtener límites este-oeste
$sql_oeste = "SELECT MIN(lng_sw) as min_lng FROM zonas_cuadricula";
$result = $m->query($sql_oeste);
$row = $result->fetch_assoc();
$lng_oeste = (float)$row['min_lng'];

$sql_este = "SELECT MAX(lng_ne) as max_lng FROM zonas_cuadricula";
$result = $m->query($sql_este);
$row = $result->fetch_assoc();
$lng_este = (float)$row['max_lng'];

// Obtener el ID máximo existente
$sql_max_id = "SELECT MAX(id_zona) as max_id FROM zonas_cuadricula";
$result = $m->query($sql_max_id);
$row = $result->fetch_assoc();
$max_id_actual = (int)$row['max_id'];

// Detectar tamaño de celda analizando una zona existente
$sql_muestra = "SELECT lat_sw, lat_ne, lng_sw, lng_ne 
                FROM zonas_cuadricula 
                WHERE id_zona = 1 
                LIMIT 1";
$result = $m->query($sql_muestra);
$zona_muestra = $result->fetch_assoc();

$tamano_lat = (float)$zona_muestra['lat_ne'] - (float)$zona_muestra['lat_sw'];
$tamano_lng = (float)$zona_muestra['lng_ne'] - (float)$zona_muestra['lng_sw'];

echo "    ✓ Latitud norte actual: {$lat_norte_actual}\n";
echo "    ✓ Latitud sur actual: {$lat_sur_actual}\n";
echo "    ✓ Longitud oeste: {$lng_oeste}\n";
echo "    ✓ Longitud este: {$lng_este}\n";
echo "    ✓ Tamaño de celda: {$tamano_lat}° × {$tamano_lng}°\n";
echo "    ✓ Último ID: {$max_id_actual}\n\n";

// ============================================================
// PASO 2: CONFIGURAR EXTENSIÓN
// ============================================================

echo "[2] Configurando extensión hacia Huntsville...\n";

$lat_objetivo = 30.721;  // Norte de Huntsville según tu consulta
$lat_inicio = $lat_norte_actual;  // Comenzar desde el norte actual

echo "    ✓ Inicio de extensión: {$lat_inicio}\n";
echo "    ✓ Objetivo norte: {$lat_objetivo}\n";

// Calcular número de filas necesarias
$diferencia = $lat_objetivo - $lat_inicio;
$num_filas = ceil($diferencia / $tamano_lat);

echo "    ✓ Filas a generar: {$num_filas}\n";

// ============================================================
// PASO 3: GENERAR COLUMNAS (basado en límites detectados)
// ============================================================

echo "\n[3] Generando estructura de columnas...\n";

$columnas = [];
$lng_actual = $lng_oeste;
$num_columna = 0;

while ($lng_actual < $lng_este - 0.0001) {
    $columnas[] = [
        'sw' => round($lng_actual, 6),
        'ne' => round($lng_actual + $tamano_lng, 6),
        'centro' => round($lng_actual + ($tamano_lng / 2), 6),
        'numero' => $num_columna
    ];
    $lng_actual += $tamano_lng;
    $num_columna++;
}

echo "    ✓ Columnas generadas: " . count($columnas) . "\n";
echo "    ✓ Primera: {$columnas[0]['sw']}\n";
echo "    ✓ Última: {$columnas[count($columnas)-1]['sw']}\n\n";

// ============================================================
// PASO 4: GENERAR E INSERTAR ZONAS
// ============================================================

echo "[4] Generando zonas...\n";

$id_actual = $max_id_actual + 1;
$total_zonas = 0;
$lat_fila = $lat_inicio;
$errores = 0;

// Preparar statement para inserción eficiente
$stmt = $m->prepare("INSERT INTO zonas_cuadricula 
    (id_zona, nombre_zona, lat_sw, lng_sw, lat_ne, lng_ne, centro_lat, centro_lng, activo, id_ciudad_origen) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NULL)");

for ($fila = 0; $fila < $num_filas; $fila++) {
    $lat_sw = round($lat_fila, 6);
    $lat_ne = round($lat_fila + $tamano_lat, 6);
    $centro_lat = round($lat_fila + ($tamano_lat / 2), 6);
    
    foreach ($columnas as $col) {
        // Generar nombre con formato ZONA_XXXXX
        $nombre = 'ZONA_' . str_pad($id_actual, 5, '0', STR_PAD_LEFT);
        
        // Verificar que no exista (doble seguridad)
        $check = $m->query("SELECT id_zona FROM zonas_cuadricula WHERE id_zona = {$id_actual}");
        if ($check->num_rows > 0) {
            echo "    ⚠ ID {$id_actual} ya existe, saltando...\n";
            $id_actual++;
            continue;
        }
        
        // Insertar
        $stmt->bind_param("isdddddd", 
            $id_actual, 
            $nombre, 
            $lat_sw, 
            $col['sw'], 
            $lat_ne, 
            $col['ne'], 
            $centro_lat, 
            $col['centro']
        );
        
        if ($stmt->execute()) {
            $total_zonas++;
        } else {
            echo "    ✗ Error en ID {$id_actual}: " . $stmt->error . "\n";
            $errores++;
        }
        
        $id_actual++;
    }
    
    $lat_fila += $tamano_lat;
    
    // Progreso cada 10 filas
    if (($fila + 1) % 10 == 0 || $fila == $num_filas - 1) {
        echo "    → Progreso: " . ($fila + 1) . "/{$num_filas} filas...\n";
    }
}

$stmt->close();

// ============================================================
// RESUMEN
// ============================================================

echo "\n===========================================\n";
echo "RESUMEN DE INSERCIÓN\n";
echo "===========================================\n";
echo "Total de zonas insertadas: {$total_zonas}\n";
echo "Total de errores: {$errores}\n";
echo "ID inicial: " . ($max_id_actual + 1) . "\n";
echo "ID final: " . ($id_actual - 1) . "\n";
echo "Rango latitudinal: {$lat_inicio} - {$lat_fila}\n";
echo "===========================================\n";

$m->close();
echo "\n✓ Proceso completado.\n";
?>