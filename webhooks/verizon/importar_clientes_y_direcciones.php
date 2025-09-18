<?php

// --- CONFIGURACIÓN DE LA BASE DE DATOS ---
// Reemplaza con tus credenciales de conexión a la base de datos
$host = 'localhost'; // O la IP/host de tu servidor MySQL
$dbname = 'greentrack_live'; // Reemplaza con el nombre de tu base de datos
$username_db = 'mmoreno'; // Reemplaza con tu usuario de MySQL
$password_db = 'Noloseno#2017'; // Reemplaza con tu contraseña de MySQL

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Conexión a la base de datos establecida.\n";
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
// --- FIN CONFIGURACIÓN DE LA BASE DE DATOS ---

// --- CONFIGURACIÓN DEL ARCHIVO CSV ---
$csv_file_path = 'todos_los_places.csv'; // Asegúrate de que esta ruta sea correcta
// $categoria_a_importar = 'S_CATEGORY_CLIENT'; // *** CORREGIDO: Usar el valor real del CSV ***
$delimiter = ','; // Delimitador del CSV (normalmente coma)
// --- FIN CONFIGURACIÓN DEL ARCHIVO CSV ---

/**
 * Función para construir una dirección completa desde los componentes del CSV.
 */
function construirDireccion($row) {
    $componentes = [
        trim($row['AddressLine1'] ?? ''),
        trim($row['AddressLine2'] ?? ''),
        trim($row['Locality'] ?? ''),
        trim($row['AdministrativeArea'] ?? ''),
        trim($row['PostalCode'] ?? ''),
        trim($row['Country'] ?? '')
    ];
    // Filtrar componentes vacíos y unirlos con coma y espacio
    return implode(', ', array_filter($componentes, function($part) { return !empty($part); }));
}

echo "Iniciando proceso de importación...\n";

// --- 1. (Opcional) Vaciar las tablas existentes ---
// ¡¡¡ADVERTENCIA!!!: Este paso eliminará todos los datos actuales de las tablas.
// Comenta o elimina estas líneas si no quieres perder datos existentes.
/*
try {
    $pdo->beginTransaction();
    $pdo->exec("DELETE FROM direcciones"); // Asumiendo que 'direcciones' no tiene FK que impidan el borrado
    echo "Tabla 'direcciones' vaciada.\n";
    $pdo->exec("DELETE FROM clientes"); // Asumiendo que 'clientes' no tiene FK que impidan el borrado
    echo "Tabla 'clientes' vaciada.\n";
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollback();
    die("Error al vaciar las tablas: " . $e->getMessage());
}
*/

// --- 2. Preparar sentencias SQL ---
// Sentencia para insertar en la tabla 'clientes'
$sql_cliente = "INSERT INTO clientes (nombre, telefono, email, id_status, fecha_creacion, historial, tiene_historial) VALUES (?, ?, ?, ?, NOW(), ?, ?)";
$stmt_cliente = $pdo->prepare($sql_cliente);

// Sentencia para insertar en la tabla 'direcciones'
$sql_direccion = "INSERT INTO direcciones (id_cliente, direccion, lat, lng, geofence_id, id_status, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, NOW())";
$stmt_direccion = $pdo->prepare($sql_direccion);

// --- 3. Leer el archivo CSV ---
if (!file_exists($csv_file_path) || !is_readable($csv_file_path)) {
    die("Error: No se puede leer el archivo CSV '$csv_file_path'.\n");
} else {
    echo "Archivo CSV '$csv_file_path' encontrado y es legible.\n";
}

$handle = fopen($csv_file_path, 'r');
if ($handle === false) {
    die("Error al abrir el archivo CSV.\n");
} else {
    echo "Archivo CSV abierto correctamente.\n";
}

// Leer la primera línea para obtener los encabezados
$headers = fgetcsv($handle, 0, $delimiter);
if ($headers === false) {
    fclose($handle);
    die("Error: El archivo CSV está vacío o no tiene encabezados.\n");
} else {
    echo "Encabezados del CSV leídos correctamente.\n";  
}

// Crear un mapa de encabezados a índices para facilitar el acceso
$header_map = array_flip($headers);

// Verificar que los encabezados necesarios existan
$required_headers = ['GeoFenceName', 'CategoryName', 'Latitude', 'Longitude', 'PlaceId'];
foreach ($required_headers as $required_header) {
    if (!isset($header_map[$required_header])) {
        fclose($handle);
        die("Error: El encabezado '$required_header' no se encuentra en el archivo CSV.\n");
    } else {
        echo "Encabezado '$required_header' encontrado en el CSV.\n";
    }
}

$contador_importados = 0;
$fecha_importacion = date('Y-m-d H:i:s'); // Fecha/hora de importación para todos los registros


// --- 4. Iterar por cada fila del CSV ---
while (($row_data = fgetcsv($handle, 0, $delimiter)) !== false) {
    // Combinar encabezados con datos de la fila para crear un array asociativo
    $row = array_combine($headers, $row_data);

    // Filtrar por categoría
    //if (($row['CategoryName'] ?? '') !== $categoria_a_importar) {
    //    continue; // Saltar si no es la categoría que queremos
    //} else {
    //    echo "Procesando lugar de categoría '$categoria_a_importar': " . ($row['GeoFenceName'] ?? 'N/A') . "\n";
    //}

    // --- 5. Extraer datos del CSV ---
    $nombre_cliente = trim($row['GeoFenceName'] ?? '');
    $telefono_cliente = trim($row['PhoneNumber'] ?? '');
    $email_cliente = ''; // No disponible en el CSV
    $id_status_cliente = 1; // Activo
    $historial_cliente = 0;
    $tiene_historial_cliente = 0; // Falso

    $direccion_completa = construirDireccion($row);
    $latitud = trim($row['Latitude'] ?? '');
    $longitud = trim($row['Longitude'] ?? '');
    $geofence_id = trim($row['PlaceId'] ?? '');
    $id_status_direccion = 1; // Activo

    // Validaciones básicas
    if (empty($nombre_cliente)) {
        echo "Advertencia: Fila ignorada por nombre de cliente vacío. GeofenceName: '" . ($row['GeoFenceName'] ?? 'N/A') . "'\n";
        continue;
    }else{
        echo "Nombre del cliente: '$nombre_cliente'\n";
    }
    if (!is_numeric($latitud) || !is_numeric($longitud)) {
        echo "Advertencia: Fila ignorada por coordenadas no numéricas. Nombre: '$nombre_cliente', Lat: '$latitud', Lng: '$longitud'\n";
        continue;
    } else {
        echo "Coordenadas válidas: Latitud = $latitud, Longitud = $longitud\n";
    }
    //if (empty($geofence_id)) {
    //    echo "Advertencia: Fila ignorada por PlaceId vacío. Nombre: '$nombre_cliente'\n";
    //    continue;
    //} else {
    //    echo "PlaceId válido: '$geofence_id'\n";
    //}

    try {
        echo "Inicio de transacción para cliente '$nombre_cliente'.\n";
        $pdo->beginTransaction();

        // --- 6. Insertar en la tabla 'clientes' ---
        $stmt_cliente->execute([
            $nombre_cliente,
            $telefono_cliente,
            $email_cliente,
            $id_status_cliente,
            $historial_cliente,
            $tiene_historial_cliente
        ]);
        $id_cliente_insertado = $pdo->lastInsertId();

        if (!$id_cliente_insertado) {
             throw new Exception("No se pudo obtener el ID del cliente insertado.");
        }

        // --- 7. Insertar en la tabla 'direcciones' ---
        $stmt_direccion->execute([
            $id_cliente_insertado,
            $direccion_completa,
            $latitud,
            $longitud,
            $geofence_id,
            $id_status_direccion
        ]);

        $pdo->commit();
        $contador_importados++;
        echo "Importado: Cliente '$nombre_cliente' (ID: $id_cliente_insertado) y su dirección.\n";

    } catch (Exception $e) {
        $pdo->rollback();
        echo "Error al importar el cliente '$nombre_cliente': " . $e->getMessage() . "\n";
        // Dependiendo de tus necesidades, puedes continuar con la siguiente fila o detener el proceso
        // continue; // Para continuar
        // break; // Para detener
    }
}

fclose($handle);

echo "\n--- RESUMEN DE IMPORTACIÓN ---\n";
echo "Proceso finalizado. Se importaron $contador_importados clientes y direcciones de todas las categorías.\n";
echo "Fecha de importación: $fecha_importacion\n";

?>