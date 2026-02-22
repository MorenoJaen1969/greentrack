<?php
/**
 * GENERAR CSV DE DIRECCIONES CONFLICTIVAS
 * Formato: id_cliente, nombre_cliente, direccion
 * Un grupo por dirección, línea en blanco entre grupos
 */

// Configuración
$db_config = [
    'host' => 'localhost',
    'user' => 'mmoreno',
    'pass' => 'Noloseno#2017',
    'name' => 'greentrack_live'
];

// Conectar a la base de datos
$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['pass'],
    $db_config['name']
);

if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error . "\n");
}

echo "========================================\n";
echo "🚀 GENERANDO REPORTE DE DIRECCIONES CONFLICTIVAS\n";
echo "========================================\n";

// Consulta principal
$query = "
    SELECT 
        d.direccion,
        GROUP_CONCAT(d.id_cliente ORDER BY d.id_cliente SEPARATOR '|') as clientes_ids,
        GROUP_CONCAT(d.id_direccion ORDER BY d.id_direccion SEPARATOR '|') as ids_direcciones
    FROM direcciones d
    WHERE d.id_cliente IS NOT NULL
    GROUP BY d.direccion
    HAVING COUNT(*) > 1 
       AND COUNT(DISTINCT d.id_cliente) > 1
    ORDER BY d.direccion
";

$result = $conn->query($query);

if (!$result) {
    die("❌ Error en consulta: " . $conn->error . "\n");
}

echo "📊 Grupos encontrados: " . $result->num_rows . "\n\n";

// Crear archivo CSV
$filename = 'direcciones_conflictivas_' . date('Ymd_His') . '.csv';
$fp = fopen($filename, 'w');

// Escribir encabezados
fputcsv($fp, ['id_cliente', 'nombre_cliente', 'direccion']);

$contador_grupos = 0;
$contador_registros = 0;

while ($row = $result->fetch_assoc()) {
    $contador_grupos++;
    $direccion = $row['direccion'];
    $clientes_ids = explode('|', $row['clientes_ids']);
    $ids_direcciones = explode('|', $row['ids_direcciones']);
    
    // Para cada cliente en este grupo
    for ($i = 0; $i < count($clientes_ids); $i++) {
        $id_cliente = $clientes_ids[$i];
        $id_direccion = $ids_direcciones[$i];
        
        // Obtener nombre del cliente
        $q = "SELECT 
                COALESCE(
                    CASE 
                        WHEN id_tipo_persona = 1 THEN TRIM(CONCAT_WS(' ', NULLIF(nombre, ''), NULLIF(apellido, '')))
                        WHEN id_tipo_persona = 2 THEN NULLIF(nombre_comercial, '')
                        ELSE NULLIF(nombre, '')
                    END,
                    '[SIN NOMBRE]'
                ) as nombre
              FROM clientes 
              WHERE id_cliente = $id_cliente";
        
        $r = $conn->query($q);
        if ($r && $r->num_rows > 0) {
            $nombre_cliente = $r->fetch_assoc()['nombre'];
        } else {
            $nombre_cliente = '[SIN NOMBRE]';
        }
        
        // Escribir fila
        fputcsv($fp, [$id_cliente, $nombre_cliente, $direccion]);
        $contador_registros++;
    }
    
    // Línea en blanco entre grupos (excepto después del último)
    if ($contador_grupos < $result->num_rows) {
        fputcsv($fp, ['', '', '']);
    }
    
    // Mostrar progreso
    if ($contador_grupos % 10 == 0) {
        echo "   Procesados {$contador_grupos} grupos...\n";
    }
}

fclose($fp);

echo "\n========================================\n";
echo "✅ REPORTE GENERADO\n";
echo "📁 Archivo: {$filename}\n";
echo "📊 Total grupos: {$contador_grupos}\n";
echo "📋 Total registros: {$contador_registros}\n";
echo "========================================\n";

// Mostrar los primeros 3 grupos como ejemplo
if ($contador_grupos > 0) {
    echo "\n📋 PRIMEROS 3 GRUPOS (vista previa):\n";
    echo str_repeat("-", 80) . "\n";
    
    // Volver al inicio del resultado
    $result->data_seek(0);
    $limite = min(3, $contador_grupos);
    
    for ($g = 0; $g < $limite; $g++) {
        $row = $result->fetch_assoc();
        $direccion = $row['direccion'];
        $clientes_ids = explode('|', $row['clientes_ids']);
        
        echo "📍 {$direccion}\n";
        
        foreach (array_slice($clientes_ids, 0, 3) as $id_cliente) {
            $q = "SELECT 
                    COALESCE(
                        CASE 
                            WHEN id_tipo_persona = 1 THEN TRIM(CONCAT_WS(' ', NULLIF(nombre, ''), NULLIF(apellido, '')))
                            WHEN id_tipo_persona = 2 THEN NULLIF(nombre_comercial, '')
                            ELSE NULLIF(nombre, '')
                        END,
                        '[SIN NOMBRE]'
                    ) as nombre
                  FROM clientes 
                  WHERE id_cliente = $id_cliente";
            $r = $conn->query($q);
            $nombre = ($r && $r->num_rows > 0) ? $r->fetch_assoc()['nombre'] : '[SIN NOMBRE]';
            
            echo "   - Cliente {$id_cliente}: {$nombre}\n";
        }
        
        if (count($clientes_ids) > 3) {
            echo "     ... y " . (count($clientes_ids) - 3) . " clientes más\n";
        }
        
        if ($g < $limite - 1) {
            echo "\n";
        }
    }
}

$conn->close();
?>