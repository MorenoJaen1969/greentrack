<?php
// revisar_manual.php
$conn = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
$ids_problematicos = [5017, 5061, 5073, 5110, 5117, 5138, 5155, 5180, 5194, 5202, 5211, 5224, 5225, 5235, 5241, 5242, 5257, 5258, 5259, 5264, 5270, 5280, 5299, 5306, 5324, 5350, 5366, 5376, 5384, 5396, 5427, 5434, 5436];

$ids_str = implode(',', $ids_problematicos);
$result = $conn->query("SELECT id_direccion, direccion, lat, lng FROM direcciones WHERE id_direccion IN ($ids_str) ORDER BY id_direccion");

$csv = fopen('revision_manual.csv', 'w');
fputcsv($csv, ['ID', 'Dirección', 'Latitud', 'Longitud', 'URL Google Maps']);

while ($row = $result->fetch_assoc()) {
    $url = "https://www.google.com/maps/search/?api=1&query={$row['lat']},{$row['lng']}";
    fputcsv($csv, [
        $row['id_direccion'],
        $row['direccion'],
        $row['lat'],
        $row['lng'],
        $url
    ]);
}

fclose($csv);
$conn->close();
echo "✅ Archivo 'revision_manual.csv' generado con " . count($ids_problematicos) . " direcciones.\n";