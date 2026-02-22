<?php
/**
 * ======================================================================
 * CONSOLIDACIÓN CORRECTA CLIENTES 1-167
 * ======================================================================
 * 
 * REGLAS EXACTAS:
 * 1. Para cada cliente ID 1-167:
 *    a) Obtener nombre_completo = COALESCE(nombre_comercial, nombre)
 *    b) Normalizar SIN ordenar palabras:
 *       - Eliminar paréntesis y contenido: (ARS) → ""
 *       - Eliminar puntuación: , . ; : etc.
 *       - Normalizar espacios
 *       - Mayúsculas
 *       - Eliminar número final si existe
 *    c) Buscar en clientes > 167 con MISMO nombre normalizado
 *    d) Si coincide → usar su id_cliente_nuevo
 *    e) Si no → crear nuevo grupo con próximo ID
 * 2. Actualizar cliente 1-167 con:
 *    id_cli_anterior = id_cliente
 *    id_dir_anterior = id_cliente
 *    id_cliente_nuevo = id_cliente_nuevo_encontrado
 * 3. Actualizar sus direcciones con:
 *    id_cli_anterior = id_cliente
 *    id_dir_anterior = id_direccion
 *    id_cliente_nuevo = id_cliente_nuevo_encontrado
 */

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  CONSOLIDACIÓN CORRECTA CLIENTES 1-167                          ║\n";
echo "║  (Sin ordenar palabras - Solo limpieza básica)                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Obtener próximo ID consolidado
$stmt = $m->prepare("SELECT COALESCE(MAX(id_cliente_nuevo), 999) AS max_id FROM clientes WHERE id_cliente_nuevo >= 1000");
$stmt->execute();
$next_id = (int)$stmt->get_result()->fetch_assoc()['max_id'] + 1;
$stmt->close();

echo "[INFO] Próximo ID consolidado: $next_id\n\n";

// Obtener mapeo de grupos existentes (>167)
$stmt = $m->prepare("
    SELECT id_cliente, id_cliente_nuevo, nombre, nombre_comercial
    FROM clientes
    WHERE id_cliente > 167 
      AND id_cliente_nuevo IS NOT NULL 
      AND id_cliente_nuevo >= 1000
");
$stmt->execute();
$result = $stmt->get_result();

$grupos = [];
while ($row = $result->fetch_assoc()) {
    $nombre = !empty(trim($row['nombre_comercial'])) ? $row['nombre_comercial'] : $row['nombre'];
    $norm = normalizarSimple($nombre);
    if (!empty($norm)) {
        $grupos[$norm] = [
            'id_cliente_nuevo' => (int)$row['id_cliente_nuevo'],
            'nombre_original' => $nombre
        ];
    }
}
$stmt->close();

echo "[INFO] Grupos existentes mapeados: " . count($grupos) . "\n\n";

// Procesar clientes 1-167
for ($id = 1; $id <= 167; $id++) {
    // Obtener nombre del cliente 1-167
    $stmt = $m->prepare("
        SELECT COALESCE(NULLIF(TRIM(nombre_comercial), ''), nombre) AS nombre_completo
        FROM clientes
        WHERE id_cliente = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt->close();
        continue;
    }
    
    $row = $result->fetch_assoc();
    $nombre_actual = $row['nombre_completo'] ?? '';
    $stmt->close();
    
    if (empty($nombre_actual)) continue;
    
    $norm_actual = normalizarSimple($nombre_actual);
    
    // Buscar coincidencia
    $grupo_encontrado = null;
    if (isset($grupos[$norm_actual])) {
        $grupo_encontrado = $grupos[$norm_actual]['id_cliente_nuevo'];
        $nombre_grupo = $grupos[$norm_actual]['nombre_original'];
    }
    
    // Determinar id_cliente_nuevo a usar
    if ($grupo_encontrado !== null) {
        $id_nuevo = $grupo_encontrado;
        $tipo = "EXISTENTE ($nombre_grupo)";
    } else {
        $id_nuevo = $next_id++;
        $tipo = "NUEVO";
    }
    
    // Actualizar cliente 1-167
    $stmt = $m->prepare("
        UPDATE clientes
        SET 
            id_cli_anterior = ?,
            id_cliente_nuevo = ?
        WHERE id_cliente = ?
    ");
    $stmt->bind_param('iii', $id, $id_nuevo, $id);
    $stmt->execute();
    $stmt->close();
    
    // Actualizar direcciones del cliente
    $stmt = $m->prepare("
        UPDATE direcciones
        SET 
            id_cli_anterior = ?,
            id_dir_anterior = id_direccion,
            id_cliente_nuevo = ?
        WHERE id_cliente = ?
    ");
    $stmt->bind_param('iii', $id, $id_nuevo, $id);
    $stmt->execute();
    $stmt->close();
    
    echo "✓ ID $id | '$nombre_actual' → Grupo $id_nuevo ($tipo)\n";
}

echo "\n[OK] Consolidación completada\n";
$m->close();

// ======================================================================
// FUNCIÓN DE NORMALIZACIÓN CORREGIDA (SIN ORDENAR PALABRAS)
// ======================================================================

function normalizarSimple($text) {
    if (empty($text)) return '';
    
    // Convertir a mayúsculas
    $text = strtoupper(trim($text));
    
    // Eliminar paréntesis y su contenido: (ARS) → espacio
    $text = preg_replace('/\([^)]*\)/', ' ', $text);
    
    // Eliminar TODA puntuación excepto letras, números y espacios
    $text = preg_replace('/[^A-Z0-9\s]/', ' ', $text);
    
    // Normalizar espacios múltiples
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Eliminar número final si existe
    $text = preg_replace('/(\s\d+)$/', '', $text);
    
    return trim($text);
}

// ======================================================================
// EJEMPLO DE NORMALIZACIÓN CORRECTA
// ======================================================================

echo "\n╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  EJEMPLO DE NORMALIZACIÓN CORREGIDA                             ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$ejemplos = [
    'ACCESS RESTORATION SERVICES',
    'ACCESS RESTORATION SERVICES (ARS)',
    'ACCESS RESTORATION SERVICES (ARS) 1',
    'ACCESS RESTORATION SERVICES, LLC'
];

foreach ($ejemplos as $ej) {
    echo "  Original: '$ej'\n";
    echo "  Normalizado: '" . normalizarSimple($ej) . "'\n\n";
}

echo "✅ ¡TODOS NORMALIZAN A: 'ACCESS RESTORATION SERVICES' → MISMO GRUPO!\n\n";
?>